<?php

namespace App\Console\Commands;

use App\Models\PivotNormalOrderConfig;
use App\Models\BrokerApi;
use App\Models\OptionDailyOhlcData;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Console\Command;
use KiteConnect\KiteConnect;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * php artisan pivot:place-normal-orders
 * php artisan pivot:place-normal-orders --dry-run
 * php artisan pivot:place-normal-orders --user_id=5
 * php artisan pivot:place-normal-orders --broker_id=3
 *
 * 100% replica of PivotPlaceAmoOrders with two differences:
 *   1. Reads from pivot_normal_order_configs table.
 *   2. Places variety='regular' (normal intraday) orders, NOT 'amo'.
 *
 * QUANTITY: config stores LOTS → lots × lot_size = actual Kite quantity.
 * PRICE:    S3 can go negative → automatically skipped.
 * MARKET orders: price param is omitted entirely.
 */
class PivotPlaceNormalOrders extends Command
{
    protected $signature = 'pivot:place-normal-orders
                            {--user_id=  : Run for a specific user only}
                            {--broker_id=: Run for a specific broker only}
                            {--expiry=   : Use a specific expiry date (Y-m-d)}
                            {--dry-run   : Preview orders without placing}';

    protected $description = 'Place normal (regular) intraday LIMIT/MARKET BUY orders at S1/S2/S3 pivot levels';

    public function handle(): int
    {
        $this->info('📍 Pivot Normal Order Placement — ' . now()->format('d M Y H:i:s'));
        $this->info('   Variety: regular (market hours) | NOT AMO');

        if ($this->option('dry-run')) {
            $this->warn('⚠  DRY-RUN mode — no real orders will be placed');
        }

        // ── Load active configs ──────────────────────────────
        $query = PivotNormalOrderConfig::where('is_active', true)->with('brokerApi');

        if ($uid = $this->option('user_id'))   $query->where('user_id', $uid);
        if ($bid = $this->option('broker_id')) $query->where('broker_api_id', $bid);

        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->warn('No active pivot normal order configs found.');
            return Command::FAILURE;
        }

        $this->info("Found {$configs->count()} active config(s).\n");

        $totalPlaced  = 0;
        $totalFailed  = 0;
        $totalSkipped = 0;

        foreach ($configs as $config) {
            $broker = $config->brokerApi;

            if (!$broker || !$broker->is_token_valid) {
                $this->warn("⚠  Broker #{$config->broker_api_id} token invalid — skipping.");
                continue;
            }

            $this->info("🔑 Broker  : {$broker->client_name} ({$broker->account_user_name})");
            $this->info("   Model   : {$config->model_type} | Instrument: {$config->instrument_type}");
            $this->info("   Variety : {$config->order_variety} | Product: {$config->product}");
            $this->info("   S1: qty={$config->s1_qty} lots | disc={$config->s1_discount} {$config->s1_discount_type}");
            $this->info("   S2: qty={$config->s2_qty} lots | disc={$config->s2_discount} {$config->s2_discount_type}");
            $this->info("   S3: qty={$config->s3_qty} lots | buf={$config->s3_buffer} {$config->s3_buffer_type}");

            // ── Fetch pivot data ─────────────────────────────
            $pivotData = $this->fetchPivotData($this->option('expiry'));

            if (empty($pivotData)) {
                $this->warn("   ⚠  No pivot data found — skipping.\n");
                continue;
            }

            $this->info("   📊 Found " . count($pivotData) . " symbol(s) | Data date: {$pivotData[0]['date']}");

            // ── Build order list ─────────────────────────────
            $orders = $this->buildOrders($pivotData, $config);

            if (empty($orders)) {
                $this->warn("   ⚠  No valid orders (all qty=0 or prices invalid).\n");
                continue;
            }

            $this->info("   📋 " . count($orders) . " order(s):");
            foreach ($orders as $o) {
                $priceStr = $o['order_variety'] === 'MARKET'
                    ? 'MARKET'
                    : "₹{$o['order_price']}";

                $this->line(sprintf(
                    "      %s %s %-22s  lots:%-3s → qty:%-6s  %s  [%s %s]",
                    $o['type'], $o['level'],
                    $o['trading_sym'],
                    $o['lots'], $o['qty'],
                    $priceStr,
                    $o['order_variety'],
                    $o['product']
                ));
            }

            if ($this->option('dry-run')) {
                $this->info("   [DRY-RUN] Skipping actual placement.\n");
                continue;
            }

            // ── Place orders via Kite ────────────────────────
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);

            foreach ($orders as $order) {
                $result = $this->placeOrder($kite, $broker, $order, $config);

                if ($result === 'placed') {
                    $totalPlaced++;
                    $priceStr = $order['order_variety'] === 'MARKET' ? 'MARKET' : "₹{$order['order_price']}";
                    $this->info("   ✅ {$order['type']} {$order['level']} {$order['trading_sym']} qty:{$order['qty']} @ {$priceStr} → PLACED");
                } elseif ($result === 'skipped') {
                    $totalSkipped++;
                    $this->warn("   ⏭  {$order['type']} {$order['level']} {$order['trading_sym']} SKIPPED (invalid price/qty)");
                } else {
                    $totalFailed++;
                    $this->error("   ❌ {$order['type']} {$order['level']} {$order['trading_sym']} FAILED");
                }

                usleep(400000);
            }

            $this->line('');
        }

        $this->info("═══════════════════════════════════════════");
        $this->info("✅ Placed : {$totalPlaced}");
        $this->info("⏭  Skipped: {$totalSkipped}  (price ≤ 0 or qty = 0)");
        $this->info("❌ Failed : {$totalFailed}");
        $this->info("═══════════════════════════════════════════");

        return Command::SUCCESS;
    }

    // =========================================================
    //  FETCH LATEST PIVOT DATA
    // =========================================================
    private function fetchPivotData(?string $expiry): array
    {
        if (!$expiry) {
            $today  = Carbon::today()->toDateString();
            $expiry = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $today)
                ->orderBy('expiry_date')
                ->value('expiry_date');

            if (!$expiry) {
                $expiry = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
                    ->whereNotNull('expiry_date')
                    ->orderBy('expiry_date', 'DESC')
                    ->value('expiry_date');
            }
            if (!$expiry) return [];
            $expiry = Carbon::parse($expiry)->toDateString();
        }

        $latestDate = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->where('is_missing', 0)
            ->whereNotNull('strike_position')
            ->max('trade_date');

        if (!$latestDate) return [];
        $latestDate = Carbon::parse($latestDate)->toDateString();

        $rows = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $latestDate)
            ->whereDate('expiry_date', $expiry)
            ->where('is_missing', 0)
            ->whereNotNull('strike_position')
            ->get([
                'base_symbol', 'instrument_type',
                'high', 'low', 'close', 'volume',
                'trading_symbol', 'strike_position',
            ]);

        if ($rows->isEmpty()) return [];

        $grouped = [];
        foreach ($rows as $r) {
            $existing = $grouped[$r->base_symbol][$r->instrument_type][$r->strike_position] ?? null;
            if (!$existing || ($r->volume ?? 0) >= ($existing->volume ?? 0)) {
                $grouped[$r->base_symbol][$r->instrument_type][$r->strike_position] = $r;
            }
        }

        $results = [];
        foreach ($grouped as $symbol => $typeMap) {
            $ceRow = $this->bestRow($typeMap['CE'] ?? []);
            $peRow = $this->bestRow($typeMap['PE'] ?? []);
            if (!$ceRow && !$peRow) continue;

            $results[] = [
                'symbol'         => $symbol,
                'date'           => $latestDate,
                'ce_trading_sym' => $ceRow?->trading_symbol,
                'pe_trading_sym' => $peRow?->trading_symbol,
                'ce_std'         => $ceRow ? $this->calcStd($ceRow->high, $ceRow->low, $ceRow->close) : null,
                'ce_cam'         => $ceRow ? $this->calcCam($ceRow->high, $ceRow->low, $ceRow->close) : null,
                'pe_std'         => $peRow ? $this->calcStd($peRow->high, $peRow->low, $peRow->close) : null,
                'pe_cam'         => $peRow ? $this->calcCam($peRow->high, $peRow->low, $peRow->close) : null,
            ];
        }

        return $results;
    }

    // =========================================================
    //  BUILD ORDERS — lots × lot_size, skip negative prices
    // =========================================================
    private function buildOrders(array $pivotData, PivotNormalOrderConfig $config): array
    {
        $orders          = [];
        $types           = $config->instrument_type === 'Both' ? ['CE', 'PE'] : [$config->instrument_type];
        $modelSuffix     = $config->model_type === 'Standard' ? 'std' : 'cam';
        $instrumentCache = [];

        foreach ($pivotData as $row) {
            foreach ($types as $type) {
                $pivotKey = strtolower($type) . '_' . $modelSuffix;
                $symKey   = strtolower($type) . '_trading_sym';

                $levels = $row[$pivotKey] ?? null;
                $tsym   = $row[$symKey]   ?? null;
                if (!$levels || !$tsym) continue;

                if (!isset($instrumentCache[$tsym])) {
                    $inst = ZerodhaInstrument::where('trading_symbol', $tsym)
                        ->where('exchange', 'NFO')
                        ->first(['lot_size', 'tick_size']);
                    $instrumentCache[$tsym] = [
                        'lot_size'  => (int)   ($inst?->lot_size  ?? 1),
                        'tick_size' => (float) ($inst?->tick_size ?? 0.05),
                    ];
                }

                $lotSize  = $instrumentCache[$tsym]['lot_size'];
                $tickSize = $instrumentCache[$tsym]['tick_size'];

                foreach (['S1', 'S2', 'S3'] as $level) {
                    $lots = $config->qtyFor($level);
                    if ($lots <= 0) continue;

                    $rawPrice = $levels[$level] ?? null;
                    if ($rawPrice === null) continue;

                    $orderPrice = $config->effectivePrice((float) $rawPrice, $level);

                    // Skip non-positive prices
                    if ($orderPrice <= 0) {
                        $this->warn("   ⏭  Skipping {$type} {$level} {$tsym}: price ₹{$orderPrice} ≤ 0");
                        continue;
                    }

                    $finalPrice = 0;
                    if ($config->order_variety === 'LIMIT') {
                        $finalPrice = $tickSize > 0
                            ? round(round($orderPrice / $tickSize) * $tickSize, 2)
                            : round($orderPrice, 2);
                        $finalPrice = max(0.05, $finalPrice);
                    }

                    $orders[] = [
                        'symbol'        => $row['symbol'],
                        'type'          => $type,
                        'level'         => $level,
                        'trading_sym'   => $tsym,
                        'exchange'      => 'NFO',
                        'raw_price'     => round((float) $rawPrice, 2),
                        'order_price'   => $finalPrice,
                        'display_price' => round($orderPrice, 2),
                        'lots'          => $lots,
                        'lot_size'      => $lotSize,
                        'qty'           => $lots * $lotSize,
                        'order_variety' => $config->order_variety,
                        'product'       => $config->product,
                    ];
                }
            }
        }

        return $orders;
    }

    // =========================================================
    //  PLACE A SINGLE ORDER — variety = 'regular'
    // =========================================================
    private function placeOrder(
        KiteConnect $kite,
        BrokerApi $broker,
        array $order,
        PivotNormalOrderConfig $config
    ): string {
        if ($order['qty'] <= 0) return 'skipped';
        if ($order['order_variety'] === 'LIMIT' && $order['order_price'] <= 0) return 'skipped';

        try {
            $params = [
                'exchange'         => $order['exchange'],
                'tradingsymbol'    => $order['trading_sym'],
                'transaction_type' => 'BUY',
                'quantity'         => $order['qty'],           // lots × lot_size
                'product'          => $order['product'],       // MIS | NRML
                'order_type'       => $order['order_variety'], // MARKET | LIMIT
                'validity'         => 'DAY',
                'variety'          => 'regular',               // ← regular, NOT amo
            ];

            if ($order['order_variety'] === 'LIMIT') {
                $params['price'] = $order['order_price'];
            }

            $result  = $kite->placeOrder('regular', $params);
            $orderId = $result->order_id ?? ($result['order_id'] ?? 'unknown');

            try {
                OrderBook::create([
                    'user_id'          => $broker->user_id,
                    'broker_username'  => $broker->account_user_name,
                    'order_id'         => $orderId,
                    'status'           => 'PENDING',
                    'trading_symbol'   => $order['trading_sym'],
                    'order_type'       => $order['order_variety'],
                    'transaction_type' => 'BUY',
                    'product'          => $order['product'],
                    'price'            => $order['order_variety'] === 'MARKET' ? '-' : $order['order_price'],
                    'quantity'         => $order['qty'],
                    'status_message'   => "Pivot Normal {$order['order_variety']} {$order['type']} {$order['level']} ({$config->model_type}) | {$order['lots']} lots × {$order['lot_size']}",
                    'order_datetime'   => now(),
                ]);
            } catch (\Exception $e) {
                Log::error("Pivot Normal OrderBook save failed: " . $e->getMessage());
            }

            return 'placed';

        } catch (\Exception $e) {
            Log::error("PivotNormal failed {$order['trading_sym']}: " . $e->getMessage());

            try {
                OrderBook::create([
                    'user_id'          => $broker->user_id,
                    'broker_username'  => $broker->account_user_name,
                    'order_id'         => '-',
                    'status'           => 'FAILED',
                    'trading_symbol'   => $order['trading_sym'],
                    'order_type'       => $order['order_variety'],
                    'transaction_type' => 'BUY',
                    'product'          => $order['product'],
                    'price'            => $order['order_variety'] === 'MARKET' ? '-' : $order['order_price'],
                    'quantity'         => $order['qty'],
                    'status_message'   => "Pivot Normal FAILED {$order['type']} {$order['level']}: " . substr($e->getMessage(), 0, 200),
                    'order_datetime'   => now(),
                ]);
            } catch (\Exception $e2) {
                Log::error("Pivot Normal failed-order save error: " . $e2->getMessage());
            }

            return 'failed';
        }
    }

    // =========================================================
    //  HELPERS
    // =========================================================
    private function bestRow(array $map): ?object
    {
        $best = null; $bv = -1;
        foreach ($map as $r) {
            $v = $r->volume ?? 0;
            if ($v > $bv) { $bv = $v; $best = $r; }
        }
        return $best;
    }

    private function calcStd(float $H, float $L, float $C): array
    {
        $P = ($H + $L + $C) / 3; $R = $H - $L;
        return [
            'P'  => round($P, 2), 'BC' => round(($H+$L)/2, 2), 'TC' => round(2*$P-($H+$L)/2, 2),
            'R1' => round(2*$P-$L, 2), 'R2' => round($P+$R, 2), 'R3' => round($H+2*($P-$L), 2),
            'S1' => round(2*$P-$H, 2), 'S2' => round($P-$R, 2), 'S3' => round($L-2*($H-$P), 2),
        ];
    }

    private function calcCam(float $H, float $L, float $C): array
    {
        $R = $H - $L;
        return [
            'R4' => round($C+$R*1.1/2, 2),  'R3' => round($C+$R*1.1/4, 2),
            'R2' => round($C+$R*1.1/6, 2),  'R1' => round($C+$R*1.1/12, 2),
            'S1' => round($C-$R*1.1/12, 2), 'S2' => round($C-$R*1.1/6, 2),
            'S3' => round($C-$R*1.1/4, 2),  'S4' => round($C-$R*1.1/2, 2),
        ];
    }
}