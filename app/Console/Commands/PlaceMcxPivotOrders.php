<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Mcx3HrOhlcData;
use App\Models\McxPivotOrderConfig;
use App\Models\McxPivotOrder;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use KiteConnect\KiteConnect;

/**
 * PlaceMcxPivotOrders
 *
 * Runs ~2 minutes after the MCX 3-Hr data collector.
 *
 * Timing:
 *   Slot 09:00 → closes 11:59 → collector at 12:02 → orders at 12:05
 *   Slot 12:00 → closes 14:59 → collector at 15:02 → orders at 15:05
 *   Slot 15:00 → closes 23:30 → collector at 00:02 → orders at 00:05 (next day)
 *
 * Cron: 5 12,15 * * 1-5   and   5 0 * * 2-6
 *
 * Logic:
 *   1. Determine last completed 3-Hr slot for the trade date
 *   2. For each active config × all MCX symbols with data:
 *      - Fetch ATM CE/PE candle for that slot
 *      - Compute PP, S1, R1
 *      - Place orders per s1_ce_layers / s1_pe_layers / r1_ce_layers / r1_pe_layers
 *   3. Full idempotency — skip if already placed same config+symbol+slot+level+layer
 *   4. Config stores quantity as LOTS — multiplied by lot_size before sending to Kite
 *   5. Price rounded to nearest tick_size before sending LIMIT orders
 *   6. Orders exceeding freeze limit are split into chunks (same as PlacePivotOrders)
 */
class PlaceMcxPivotOrders extends Command
{
    protected $signature = 'mcx:place-pivot-orders
                            {--date=     : Trade date (Y-m-d). Default = today.}
                            {--slot=     : Force candle slot e.g. 09:00}
                            {--dry-run   : Compute only, NO Zerodha call}
                            {--config=   : Run only a specific config ID}';

    protected $description = 'Place MCX pivot orders from 3-Hr ATM candle (S1/R1 layers per CE/PE)';

    private const SLOTS = ['09:00', '12:00', '15:00', '18:00', '21:00'];

    // ── Freeze limits in LOTS ─────────────────────────────────────────────────
    private const FREEZE_LIMITS = [
        'NIFTY'       => 18,  'BANKNIFTY'  => 20,  'FINNIFTY'   => 24,  'MIDCPNIFTY' => 24,
        'ADANIPORTS'  => 30,  'AMBUJACEM'  => 40,  'ASIANPAINT' => 40,  'AUROPHARMA' => 40,
        'AXISBANK'    => 30,  'BAJAJFINSV' => 50,  'BAJFINANCE' => 30,  'BHARATFORG' => 30,
        'BHARTIARTL'  => 30,  'BHEL'       => 30,  'BPCL'       => 30,  'BSE'        => 30,
        'CDSL'        => 30,  'COFORGE'    => 30,  'BDL'        => 40,  'DELHIVERY'  => 30,
        'DRREDDY'     => 30,  'ETERNAL'    => 30,  'FORTIS'     => 40,  'HAL'        => 40,
        'HAVELLS'     => 30,  'HEROMOTOCO' => 30,  'HINDALCO'   => 40,  'ICICIBANK'  => 30,
        'INDUSINDBK'  => 40,  'INFY'       => 40,  'JSWSTEEL'   => 30,  'LAURUSLABS' => 30,
        'LICHSGFIN'   => 40,  'LT'         => 40,  'LTF'        => 40,  'M&M'        => 30,
        'NATIONALUM'  => 20,  'PAYTM'      => 30,  'PGEL'       => 40,  'POLICYBZR'  => 40,
        'SBIN'        => 30,  'SHRIRAMFIN' => 30,  'SRF'        => 40,  'TATACONSUM' => 40,
        'TATAELXSI'   => 40,  'TATATECH'   => 50,  'TITAN'      => 40,  'TMPV'       => 50,
        'TCS'         => 40,  'UPL'        => 30,  'VBL'        => 40,  'VEDL'       => 30,
        'VOLTAS'      => 40,  'MCX'        => 20,
    ];

    /** @var array<int, KiteConnect> Reusable Kite instances keyed by broker ID */
    private array $kiteInstances = [];

    /** @var array<string, array{lot_size:int, tick_size:float}> instrument info cache keyed by instrument_token */
    private array $instrumentCache = [];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        // Trade date — midnight run targets yesterday
        if ($this->option('date')) {
            $date = Carbon::parse($this->option('date'))->toDateString();
        } elseif (Carbon::now()->hour === 0) {
            $date = Carbon::yesterday()->toDateString();
        } else {
            $date = Carbon::today()->toDateString();
        }

        $slot = $this->option('slot') ?? $this->resolveLastCompletedSlot();

        if (!$slot) {
            $this->warn("No completed 3-Hr slot yet. Exiting.");
            return 0;
        }

        $this->info("=== MCX PlacePivotOrders | Date: {$date} | Slot: {$slot}"
            . ($isDryRun ? ' | DRY-RUN' : '') . " ===");

        // ── Load configs ──────────────────────────────────────────────────────
        $configQuery = McxPivotOrderConfig::where('status', true)->with('broker');
        if ($this->option('config')) $configQuery->where('id', $this->option('config'));
        $configs = $configQuery->get();

        if ($configs->isEmpty()) {
            $this->warn("No active MCX pivot configs found.");
            return 0;
        }

        $this->info("Found {$configs->count()} active config(s).");

        // ── Discover symbols with data ────────────────────────────────────────
        $symbols = Mcx3HrOhlcData::whereDate('trade_date', $date)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('base_symbol')
            ->distinct()
            ->pluck('base_symbol')
            ->toArray();

        if (empty($symbols)) {
            $this->warn("No MCX 3-Hr data for date={$date} slot={$slot}. Has the collector run?");
            return 0;
        }

        $this->info("Symbols: " . implode(', ', $symbols));

        // ── Process each config × symbol ──────────────────────────────────────
        $totalPlaced = $totalSkipped = $totalErrors = 0;

        foreach ($configs as $config) {
            $this->line("\n--- Config #{$config->id} | {$config->broker->client_name} | {$config->order_type}/{$config->product} ---");

            $broker = BrokerApi::where('id', $config->broker_api_id)
                ->zerodha()->validToken()->first();

            if (!$broker && !$isDryRun) {
                $this->error("  Config #{$config->id}: Broker token invalid. Skip.");
                continue;
            }

            if ($broker) {
                $this->ensureKiteInstance($broker);
            }

            foreach ($symbols as $symbol) {
                $expiry = $this->getNearestExpiry($symbol, $date);
                if (!$expiry) { $this->warn("  [{$symbol}] No expiry. Skip."); continue; }

                $ceCandle = $this->getAtmCandle($symbol, 'CE', $expiry, $date, $slot);
                $peCandle = $this->getAtmCandle($symbol, 'PE', $expiry, $date, $slot);

                if (!$ceCandle && !$peCandle) {
                    $this->warn("  [{$symbol}] No ATM candle for slot {$slot}. Skip.");
                    continue;
                }

                $this->line("  [{$symbol}] Expiry: {$expiry}");

                // S1 CE layers
                if ($ceCandle && !empty($config->s1_ce_layers)) {
                    [$PP, $S1, $R1] = $this->pivots($ceCandle);
                    $this->line("    CE → PP={$PP} S1={$S1} R1={$R1}");
                    foreach ($config->s1_ce_layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        $price = $config->applyDiscount($S1, $layer);
                        $r = $this->placeOrder($config, $broker, $ceCandle, 'CE', 'S1', $S1, $price, $layer, $idx + 1, $date, $slot, $isDryRun);
                        $r === 'placed' ? $totalPlaced++ : ($r === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }

                // S1 PE layers
                if ($peCandle && !empty($config->s1_pe_layers)) {
                    [$PP, $S1, $R1] = $this->pivots($peCandle);
                    $this->line("    PE → PP={$PP} S1={$S1} R1={$R1}");
                    foreach ($config->s1_pe_layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        $price = $config->applyDiscount($S1, $layer);
                        $r = $this->placeOrder($config, $broker, $peCandle, 'PE', 'S1', $S1, $price, $layer, $idx + 1, $date, $slot, $isDryRun);
                        $r === 'placed' ? $totalPlaced++ : ($r === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }

                // R1 CE layers
                if ($ceCandle && !empty($config->r1_ce_layers)) {
                    [$PP, $S1, $R1] = $this->pivots($ceCandle);
                    foreach ($config->r1_ce_layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        $price = $config->applyDiscount($R1, $layer);
                        $r = $this->placeOrder($config, $broker, $ceCandle, 'CE', 'R1', $R1, $price, $layer, $idx + 1, $date, $slot, $isDryRun, 'SELL');
                        $r === 'placed' ? $totalPlaced++ : ($r === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }

                // R1 PE layers
                if ($peCandle && !empty($config->r1_pe_layers)) {
                    [$PP, $S1, $R1] = $this->pivots($peCandle);
                    foreach ($config->r1_pe_layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        $price = $config->applyDiscount($R1, $layer);
                        $r = $this->placeOrder($config, $broker, $peCandle, 'PE', 'R1', $R1, $price, $layer, $idx + 1, $date, $slot, $isDryRun, 'SELL');
                        $r === 'placed' ? $totalPlaced++ : ($r === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }
            }
        }

        $this->info("\n=== Done | Placed: {$totalPlaced} | Skipped: {$totalSkipped} | Errors: {$totalErrors} ===");
        return 0;
    }

    // ── Kite instance management ──────────────────────────────────────────────

    private function ensureKiteInstance(BrokerApi $broker): void
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);
            $this->kiteInstances[$broker->id] = $kite;
        }
    }

    // ── Instrument info lookup (lot_size + tick_size, cached) ─────────────────

    /**
     * Returns [lot_size, tick_size] for the given instrument, cached per token.
     * MCX uses exchange='MCX' in zerodha_instruments.
     */
    private function getInstrumentInfo(string $tradingSymbol, int $instrumentToken): array
    {
        $cacheKey = (string) $instrumentToken;

        if (isset($this->instrumentCache[$cacheKey])) {
            return $this->instrumentCache[$cacheKey];
        }

        $instrument = ZerodhaInstrument::where('instrument_token', $instrumentToken)->first()
            ?? ZerodhaInstrument::where('trading_symbol', $tradingSymbol)
                ->where('exchange', 'MCX')
                ->first();

        $lotSize  = $instrument ? (int)   $instrument->lot_size  : 1;
        $tickSize = $instrument ? (float) $instrument->tick_size : 0.05;

        if ($lotSize  <= 0) $lotSize  = 1;
        if ($tickSize <= 0) $tickSize = 0.05;

        $this->instrumentCache[$cacheKey] = [$lotSize, $tickSize];
        return $this->instrumentCache[$cacheKey];
    }

    // ── Order placement ───────────────────────────────────────────────────────

    private function placeOrder(
        McxPivotOrderConfig $config,
        ?BrokerApi          $broker,
        object              $candle,
        string              $optionType,
        string              $level,
        float               $rawLevel,
        float               $orderPrice,
        array               $layer,
        int                 $layerNum,
        string              $date,
        string              $slot,
        bool                $isDryRun,
        string              $transactionType = 'BUY'
    ): string {
        $symbol    = $candle->base_symbol;
        $optSymbol = $candle->trading_symbol;
        $optToken  = $candle->instrument_token ?? 0;
        $lots      = (int)($layer['quantity'] ?? 0);   // config stores LOTS

        [$lotSize, $tickSize] = $this->getInstrumentInfo($optSymbol, (int) $optToken);
        $qty        = $lots * $lotSize;                 // actual units for Kite
        $candleTime = "{$date} {$slot}:00";

        $this->line("      [{$symbol}] {$optionType} {$level} L{$layerNum} | lots={$lots} × lot_size={$lotSize} = qty={$qty}");

        // Idempotency
        $exists = McxPivotOrder::where('config_id',      $config->id)
            ->where('symbol',        $symbol)
            ->where('option_type',   $optionType)
            ->where('trigger_level', $level)
            ->where('layer_index',   $layerNum)
            ->where('candle_time',   $candleTime)
            ->where('is_order_placed', true)
            ->exists();

        if ($exists) {
            $this->line("    [{$symbol}] {$optionType} {$level} L{$layerNum} → already placed. Skip.");
            return 'skipped';
        }

        $label = "[{$symbol}] {$optionType} {$level} L{$layerNum} @ ₹{$orderPrice} (lots={$lots}, qty={$qty})";

        if ($isDryRun) {
            $this->info("    DRY-RUN: {$label}");
            McxPivotOrder::create($this->orderPayload(
                $config, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                null, 'DRY_RUN', false
            ));
            return 'placed';
        }

        // ── Live order via Kite ───────────────────────────────────────────────
        try {
            $kite            = $this->kiteInstances[$broker->id];
            $freezeLimitLots = self::FREEZE_LIMITS[$symbol] ?? null;

            if ($freezeLimitLots && $lots > $freezeLimitLots) {
                return $this->placeChunkedOrder(
                    $kite, $config, $broker, $candle, $optionType, $level,
                    $rawLevel, $orderPrice, $layerNum, $candleTime,
                    $lots, $lotSize, $tickSize, $freezeLimitLots, $transactionType, $label
                );
            }

            $kiteOrderId = $this->sendKiteOrder($kite, $optSymbol, $transactionType, $config, $qty, $orderPrice, $tickSize);

            McxPivotOrder::create($this->orderPayload(
                $config, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                $kiteOrderId, 'OPEN', true, now()
            ));

            $this->info("    ✓ PLACED: {$label} | Kite: {$kiteOrderId}");
            Log::info("MCX PlacePivotOrders: PLACED {$label} config={$config->id} kite={$kiteOrderId}");
            return 'placed';

        } catch (\Exception $e) {
            return $this->recordError($e->getMessage(), $label, $config, $candle, $optionType, $level, $layerNum, $transactionType, $rawLevel, $orderPrice, $candleTime, $qty);
        }
    }

    /**
     * Send order in freeze-limit-sized chunks.
     * Records one McxPivotOrder row per chunk.
     */
    private function placeChunkedOrder(
        KiteConnect         $kite,
        McxPivotOrderConfig $config,
        BrokerApi           $broker,
        object              $candle,
        string              $optionType,
        string              $level,
        float               $rawLevel,
        float               $orderPrice,
        int                 $layerNum,
        string              $candleTime,
        int                 $lots,
        int                 $lotSize,
        float               $tickSize,
        int                 $freezeLimitLots,
        string              $transactionType,
        string              $label
    ): string {
        $remaining = $lots;
        $chunkNum  = 0;
        $anyError  = false;
        $optSymbol = $candle->trading_symbol;

        $this->line("      [{$candle->base_symbol}] Chunking {$lots} lots (freeze={$freezeLimitLots} lots each)");

        while ($remaining > 0) {
            $chunkLots = min($freezeLimitLots, $remaining);
            $chunkQty  = $chunkLots * $lotSize;
            $chunkNum++;

            try {
                $kiteOrderId = $this->sendKiteOrder($kite, $optSymbol, $transactionType, $config, $chunkQty, $orderPrice, $tickSize);

                McxPivotOrder::create($this->orderPayload(
                    $config, $candle, $optionType, $level, $layerNum,
                    $transactionType, $rawLevel, $orderPrice, $candleTime, $chunkQty,
                    $kiteOrderId, 'OPEN', true, now(),
                    "chunk {$chunkNum} of " . ceil($lots / $freezeLimitLots)
                ));

                $this->info("      ✓ CHUNK {$chunkNum}: {$label} | lots={$chunkLots} qty={$chunkQty} | Kite: {$kiteOrderId}");
                Log::info("MCX PlacePivotOrders: CHUNK {$chunkNum} PLACED {$label} qty={$chunkQty} kite={$kiteOrderId}");

            } catch (\Exception $e) {
                $errMsg = $e->getMessage();
                $this->error("      ✗ CHUNK {$chunkNum} ERROR: {$label} | {$errMsg}");
                Log::error("MCX PlacePivotOrders: CHUNK {$chunkNum} ERROR {$label} | {$errMsg}");
                $anyError = true;
            }

            $remaining -= $chunkLots;
            if ($remaining > 0) sleep(2);
        }

        return $anyError ? 'error' : 'placed';
    }

    /**
     * Send a single order to Kite.
     * Price is rounded to nearest tick_size before submission.
     */
    private function sendKiteOrder(
        KiteConnect         $kite,
        string              $tradingSymbol,
        string              $transactionType,
        McxPivotOrderConfig $config,
        int                 $qty,
        float               $orderPrice,
        float               $tickSize = 0.05
    ): string {
        // Round price to nearest tick (same as PECEAutoTradingHelper)
        $roundedPrice = number_format(round($orderPrice / $tickSize) * $tickSize, 2, '.', '');

        $params = [
            'tradingsymbol'    => $tradingSymbol,
            'exchange'         => 'MCX',
            'transaction_type' => $transactionType,
            'order_type'       => $config->order_type,
            'quantity'         => $qty,
            'product'          => $config->product,
            'validity'         => 'DAY',
        ];

        if ($config->order_type === 'LIMIT') {
            $params['price'] = $roundedPrice;
        }

        $response = $kite->placeOrder('regular', $params);
        // Kite SDK returns stdClass — access as object property
        return $response->order_id ?? '';
    }

    /**
     * Build the McxPivotOrder attribute array.
     */
    private function orderPayload(
        McxPivotOrderConfig $config,
        object              $candle,
        string              $optionType,
        string              $level,
        int                 $layerNum,
        string              $transactionType,
        float               $rawLevel,
        float               $orderPrice,
        string              $candleTime,
        int                 $qty,
        ?string             $kiteOrderId,
        string              $kiteStatus,
        bool                $isOrderPlaced,
        $placedAt           = null,
        ?string             $chunkNote      = null,
        ?string             $errorMessage   = null
    ): array {
        return [
            'user_id'          => $config->user_id,
            'config_id'        => $config->id,
            'broker_api_id'    => $config->broker_api_id,
            'symbol'           => $candle->base_symbol,
            'option_symbol'    => $candle->trading_symbol,
            'option_token'     => $candle->instrument_token ?? null,
            'option_type'      => $optionType,
            'strike_price'     => $candle->strike,
            'trigger_level'    => $level,
            'layer_index'      => $layerNum,
            'transaction_type' => $transactionType,
            'raw_level_price'  => $rawLevel,
            'order_price'      => $orderPrice,
            'candle_time'      => $candleTime,
            'order_type'       => $config->order_type,
            'product'          => $config->product,
            'quantity'         => $qty,
            'kite_order_id'    => $kiteOrderId,
            'kite_status'      => $kiteStatus,
            'is_order_placed'  => $isOrderPlaced,
            'order_placed_at'  => $placedAt,
            'error_message'    => $errorMessage ?? ($chunkNote ? "chunk: {$chunkNote}" : null),
            'status'           => $isOrderPlaced,
        ];
    }

    /**
     * Record a failed order attempt and return 'error'.
     */
    private function recordError(
        string              $errMsg,
        string              $label,
        McxPivotOrderConfig $config,
        object              $candle,
        string              $optionType,
        string              $level,
        int                 $layerNum,
        string              $transactionType,
        float               $rawLevel,
        float               $orderPrice,
        string              $candleTime,
        int                 $qty
    ): string {
        $this->error("    ✗ ERROR: {$label} | {$errMsg}");
        Log::error("MCX PlacePivotOrders ERROR: {$label} | {$errMsg}");

        McxPivotOrder::create($this->orderPayload(
            $config, $candle, $optionType, $level, $layerNum,
            $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
            null, 'ERROR', false, null, null, $errMsg
        ));

        return 'error';
    }

    // ── Pivot calculation ─────────────────────────────────────────────────────

    private function pivots(object $candle): array
    {
        $H  = (float)$candle->high;
        $L  = (float)$candle->low;
        $C  = (float)$candle->close;
        $PP = round(($H + $L + $C) / 3, 2);
        $S1 = round((2 * $PP) - $H, 2);
        $R1 = round((2 * $PP) - $L, 2);
        return [$PP, $S1, $R1];
    }

    // ── Slot resolution ───────────────────────────────────────────────────────

    private function resolveLastCompletedSlot(): ?string
    {
        $hour = (int) Carbon::now()->format('H');
        if ($hour === 0)  return '21:00'; // midnight run → collect 21:00 bar
        if ($hour >= 21)  return '18:00';
        if ($hour >= 18)  return '15:00';
        if ($hour >= 15)  return '12:00';
        if ($hour >= 12)  return '09:00';
        return null;
    }

    // ── Data helpers ──────────────────────────────────────────────────────────

    private function getNearestExpiry(string $symbol, string $date): ?string
    {
        $e = Mcx3HrOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        return $e ?? Mcx3HrOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getAtmCandle(string $symbol, string $type, string $expiry, string $date, string $slot): ?object
    {
        return Mcx3HrOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->first();
    }
}