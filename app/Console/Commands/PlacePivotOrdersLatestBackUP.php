<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ThirtyMinOhlcData;
use App\Models\NewPivotOrderConfig;
use App\Models\NewPivotOrder;
use App\Models\BrokerApi;
use App\Helpers\ZerodhaPivotHelper;
use App\Helpers\AngelPivotHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * PlacePivotOrdersLatestBackUP
 *
 * Supports BOTH Zerodha and Angel One brokers.
 * Broker type is auto-detected from broker_apis.client_type per config.
 *
 * Cron: 48,18 * * * *  (weekdays 09:30–15:30)
 * Runs ~2 min after the 30-min OHLC data collector (:46 and :16).
 *
 * SYMBOL FILTERING (NEW):
 * ────────────────────────
 * Each config now stores a `symbols` JSON array (e.g. ["NIFTY","BANKNIFTY"]).
 * Orders are ONLY placed for symbols the user has explicitly selected in their config.
 * If a config has no symbols set (null / empty), it is skipped entirely — no orders placed.
 *
 * FLOW:
 * ─────
 * 1. Determine last completed 30-min candle slot.
 * 2. Load all active configs that have at least one symbol selected.
 * 3. Discover symbols that have data for this date+slot (global pool).
 * 4. For each config:
 *      a. Intersect config's selected symbols with the global pool → effective symbols.
 *      b. Resolve broker → build ZerodhaPivotHelper or AngelPivotHelper.
 *      c. For each effective symbol → fetch ATM CE + PE candle → compute PP / S1 / R1.
 *      d. For each layer in s1_ce / s1_pe / r1_ce / r1_pe → place order.
 *      e. Skip if already placed (idempotency check on NewPivotOrder table).
 *
 * STRIKE SELECTION LOGIC:
 * ────────────────────────
 * BUY  orders → always use ATM strike.
 * SELL orders → use ATM-1 or ATM+1 (whichever has higher volume in same slot).
 */
class PlacePivotOrdersLatestBackUP extends Command
{
    protected $signature = 'pivot:place-orders-latest-backup
                            {--date=   : Override date (Y-m-d), default = today}
                            {--slot=   : Override candle slot (H:i), e.g. 09:15}
                            {--dry-run : Compute but do NOT send orders to broker}
                            {--config= : Run only a specific config ID}';

    protected $description = 'Place Zerodha / Angel One orders based on 30-min pivot signals (S1/R1 layers per CE/PE) — only for user-selected symbols per config';

    // ── 30-min slot schedule ──────────────────────────────────────────────────
    private const INTERVALS = [
        '09:15','10:15','11:15','12:15','13:15','14:15','15:15',
    ];

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

    /**
     * Cached broker helper instances keyed by broker_api.id.
     * @var array<int, ZerodhaPivotHelper|AngelPivotHelper>
     */
    private array $brokerHelpers = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $today    = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        // ── 1. Determine target candle slot ───────────────────────────────────
        $slot = $this->option('slot') ?: $this->getLastCompletedSlot();

        if (!$slot) {
            $this->warn('No completed 30-min slot yet. Exiting.');
            return 0;
        }

        $this->info("=== PlacePivotOrders | Date: {$today} | Slot: {$slot}" . ($isDryRun ? ' | DRY-RUN' : '') . ' ===');

        // ── 2. Load active configs that have at least one symbol selected ─────
        $configQuery = NewPivotOrderConfig::where('status', true)->with('broker');

        if ($this->option('config')) {
            $configQuery->where('id', $this->option('config'));
        }

        // Only load configs that actually have symbols set
        $configs = $configQuery->get()->filter(fn($c) => $c->hasSymbols());

        if ($configs->isEmpty()) {
            $this->warn('No active configs with symbols found. Exiting.');
            return 0;
        }

        $this->info("Found {$configs->count()} active config(s) with symbols.");

        // ── 3. Discover ALL symbols that have data for this date+slot ─────────
        $availableSymbols = ThirtyMinOhlcData::whereDate('trade_date', $today)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('base_symbol')
            ->distinct()
            ->pluck('base_symbol')
            ->toArray();

        if (empty($availableSymbols)) {
            $this->warn("No 30-min data found for date={$today} slot={$slot}. Has the collector run?");
            return 0;
        }

        $this->info('Symbols with data in DB: ' . implode(', ', $availableSymbols));

        // ── 4. Process each config ─────────────────────────────────────────────
        $totalPlaced  = 0;
        $totalSkipped = 0;
        $totalErrors  = 0;

        foreach ($configs as $config) {

            // ── Intersect config's selected symbols with available DB symbols ──
            $configSymbols    = array_map('strtoupper', $config->symbols ?? []);
            $effectiveSymbols = array_values(array_intersect($configSymbols, $availableSymbols));

            if (empty($effectiveSymbols)) {
                $this->warn("  Config #{$config->id}: Selected symbols [" . implode(',', $configSymbols) . "] have no data for slot {$slot}. Skipping config.");
                continue;
            }

            $this->line("\n--- Config #{$config->id} | Symbols: [" . implode(', ', $effectiveSymbols) . "] ---");

            // ── Resolve broker record ──────────────────────────────────────────
            $brokerRecord = BrokerApi::where('id', $config->broker_api_id)->first();

            if (!$brokerRecord) {
                $this->error("  Config #{$config->id}: Broker record not found. Skipping.");
                continue;
            }

            $broker = match($brokerRecord->client_type) {
                'Zerodha'  => BrokerApi::where('id', $config->broker_api_id)->validToken()->first(),
                'AngelOne' => $brokerRecord,
                default    => null,
            };

            if (!$broker) {
                $this->error("  Config #{$config->id}: Broker token invalid/expired ({$brokerRecord->client_type}). Skipping.");
                continue;
            }

            // ── Build (or reuse) broker helper ────────────────────────────────
            $helper = $this->getBrokerHelper($broker);

            if (!$helper) {
                $this->error("  Config #{$config->id}: Unsupported broker type '{$broker->client_type}'. Skipping.");
                continue;
            }

            $this->line("  Broker: {$broker->client_name} | {$broker->client_type} | {$config->order_type}/{$config->product}");

            // ── Process only the effective (selected + available) symbols ──────
            foreach ($effectiveSymbols as $symbol) {

                $expiry = $this->getNearestExpiry($symbol, $today);
                if (!$expiry) {
                    $this->warn("  [{$symbol}] No expiry found. Skipping.");
                    continue;
                }

                $atmCeCandle  = $this->getAtmCandle($symbol, 'CE', $expiry, $today, $slot);
                $atmPeCandle  = $this->getAtmCandle($symbol, 'PE', $expiry, $today, $slot);
                $sellCeCandle = $this->getBestNonAtmCandle($symbol, 'CE', $expiry, $today, $slot);
                $sellPeCandle = $this->getBestNonAtmCandle($symbol, 'PE', $expiry, $today, $slot);

                if (!$atmCeCandle && !$atmPeCandle) {
                    $this->warn("  [{$symbol}] No ATM candle for slot {$slot}. Skipping.");
                    continue;
                }

                $this->line("  [{$symbol}] Expiry: {$expiry}");

                if ($atmCeCandle) $this->line("    ATM CE strike: {$atmCeCandle->strike}");
                if ($sellCeCandle) $this->line("    SELL CE strike (ATM±1 by volume): {$sellCeCandle->strike} | vol={$sellCeCandle->volume}");
                if ($atmPeCandle) $this->line("    ATM PE strike: {$atmPeCandle->strike}");
                if ($sellPeCandle) $this->line("    SELL PE strike (ATM±1 by volume): {$sellPeCandle->strike} | vol={$sellPeCandle->volume}");

                $freezeLimit = self::FREEZE_LIMITS[$symbol] ?? null;

                // ── S1 CE layers ───────────────────────────────────────────────
                if ($atmCeCandle && !empty($config->s1_ce_layers)) {
                    [$PP, $S1, $R1] = $this->calcPivots($atmCeCandle);
                    $this->line("    CE (ATM) → PP={$PP}  S1={$S1}  R1={$R1}");

                    foreach ($config->s1_ce_layers as $idx => $layer) {
                        $layerNum = $idx + 1;
                        if (($layer['quantity'] ?? 0) <= 0) continue;

                        $txType  = $layer['transaction_type'] ?? 'BUY';
                        $candle  = ($txType === 'SELL' && $sellCeCandle) ? $sellCeCandle : $atmCeCandle;
                        [$PP2, $S1_2, $R1_2] = $this->calcPivots($candle);

                        $price  = $config->applyDiscount($S1_2, $layer);
                        $result = $this->processOrder(
                            $config, $broker, $helper, $candle,
                            'CE', 'S1', $S1_2, $price, $layer, $layerNum,
                            $today, $slot, $isDryRun, $txType, $freezeLimit
                        );
                        $result === 'placed' ? $totalPlaced++ : ($result === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }

                // ── S1 PE layers ───────────────────────────────────────────────
                if ($atmPeCandle && !empty($config->s1_pe_layers)) {
                    [$PP, $S1, $R1] = $this->calcPivots($atmPeCandle);
                    $this->line("    PE (ATM) → PP={$PP}  S1={$S1}  R1={$R1}");

                    foreach ($config->s1_pe_layers as $idx => $layer) {
                        $layerNum = $idx + 1;
                        if (($layer['quantity'] ?? 0) <= 0) continue;

                        $txType  = $layer['transaction_type'] ?? 'BUY';
                        $candle  = ($txType === 'SELL' && $sellPeCandle) ? $sellPeCandle : $atmPeCandle;
                        [$PP2, $S1_2, $R1_2] = $this->calcPivots($candle);

                        $price  = $config->applyDiscount($S1_2, $layer);
                        $result = $this->processOrder(
                            $config, $broker, $helper, $candle,
                            'PE', 'S1', $S1_2, $price, $layer, $layerNum,
                            $today, $slot, $isDryRun, $txType, $freezeLimit
                        );
                        $result === 'placed' ? $totalPlaced++ : ($result === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }

                // ── R1 CE layers ───────────────────────────────────────────────
                if (!empty($config->r1_ce_layers)) {
                    foreach ($config->r1_ce_layers as $idx => $layer) {
                        $layerNum = $idx + 1;
                        if (($layer['quantity'] ?? 0) <= 0) continue;

                        $txType  = $layer['transaction_type'] ?? 'SELL';
                        $candle  = ($txType === 'SELL' && $sellCeCandle) ? $sellCeCandle : $atmCeCandle;

                        if (!$candle) {
                            $this->warn("      [{$symbol}] CE R1 L{$layerNum}: No candle for {$txType}. Skipping.");
                            $totalSkipped++;
                            continue;
                        }

                        [$PP, $S1, $R1] = $this->calcPivots($candle);
                        $price  = $config->applyDiscount($R1, $layer);
                        $result = $this->processOrder(
                            $config, $broker, $helper, $candle,
                            'CE', 'R1', $R1, $price, $layer, $layerNum,
                            $today, $slot, $isDryRun, $txType, $freezeLimit
                        );
                        $result === 'placed' ? $totalPlaced++ : ($result === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }

                // ── R1 PE layers ───────────────────────────────────────────────
                if (!empty($config->r1_pe_layers)) {
                    foreach ($config->r1_pe_layers as $idx => $layer) {
                        $layerNum = $idx + 1;
                        if (($layer['quantity'] ?? 0) <= 0) continue;

                        $txType  = $layer['transaction_type'] ?? 'SELL';
                        $candle  = ($txType === 'SELL' && $sellPeCandle) ? $sellPeCandle : $atmPeCandle;

                        if (!$candle) {
                            $this->warn("      [{$symbol}] PE R1 L{$layerNum}: No candle for {$txType}. Skipping.");
                            $totalSkipped++;
                            continue;
                        }

                        [$PP, $S1, $R1] = $this->calcPivots($candle);
                        $price  = $config->applyDiscount($R1, $layer);
                        $result = $this->processOrder(
                            $config, $broker, $helper, $candle,
                            'PE', 'R1', $R1, $price, $layer, $layerNum,
                            $today, $slot, $isDryRun, $txType, $freezeLimit
                        );
                        $result === 'placed' ? $totalPlaced++ : ($result === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }
            }
        }

        $this->info("\n=== Done | Placed: {$totalPlaced} | Skipped: {$totalSkipped} | Errors: {$totalErrors} ===");
        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Broker helper factory
    // ─────────────────────────────────────────────────────────────────────────

    private function getBrokerHelper(BrokerApi $broker): ZerodhaPivotHelper|AngelPivotHelper|null
    {
        if (isset($this->brokerHelpers[$broker->id])) {
            return $this->brokerHelpers[$broker->id];
        }

        $helper = match($broker->client_type) {
            'Zerodha'  => ZerodhaPivotHelper::isValid($broker) ? new ZerodhaPivotHelper($broker) : null,
            'AngelOne' => AngelPivotHelper::isValid($broker)   ? new AngelPivotHelper($broker)   : null,
            default    => null,
        };

        if ($helper) {
            $this->brokerHelpers[$broker->id] = $helper;
        }

        return $helper;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Core order processing
    // ─────────────────────────────────────────────────────────────────────────

    private function processOrder(
        NewPivotOrderConfig                 $config,
        BrokerApi                           $broker,
        ZerodhaPivotHelper|AngelPivotHelper $helper,
        object                              $candle,
        string                              $optionType,
        string                              $level,
        float                               $rawLevel,
        float                               $orderPrice,
        array                               $layer,
        int                                 $layerNum,
        string                              $date,
        string                              $slot,
        bool                                $isDryRun,
        string                              $transactionType = 'BUY',
        ?int                                $freezeLimitLots = null
    ): string {

        $symbol     = $candle->base_symbol;
        $optSymbol  = $candle->trading_symbol;
        $optToken   = (int) $candle->instrument_token;
        $strike     = $candle->strike;
        $lots       = (int)($layer['quantity'] ?? 0);
        $candleTime = "{$date} {$slot}:00";

        [$lotSize, $tickSize] = $this->getLotAndTick($helper, $optSymbol, $optToken);
        $qty = $lots * $lotSize;

        $strikePosLabel = $candle->strike_position ?? 'ATM';
        $this->line("      [{$symbol}] {$optionType} {$level} L{$layerNum} | {$transactionType} | strike={$strike}({$strikePosLabel}) | lots={$lots} × lot_size={$lotSize} = qty={$qty} | price=₹{$orderPrice}");

        // ── Idempotency check ─────────────────────────────────────────────────
        $alreadyPlaced = NewPivotOrder::where('config_id',        $config->id)
            ->where('symbol',          $symbol)
            ->where('option_type',     $optionType)
            ->where('trigger_level',   $level)
            ->where('layer_index',     $layerNum)
            ->where('candle_time',     $candleTime)
            ->where('transaction_type', $transactionType)
            ->where('is_order_placed', true)
            ->exists();

        if ($alreadyPlaced) {
            $this->line("      [{$symbol}] {$optionType} {$level} L{$layerNum} {$transactionType} → already placed. Skipping.");
            return 'skipped';
        }

        $label = "[{$symbol}] {$optionType} {$level} L{$layerNum} {$transactionType} strike={$strike} @ ₹{$orderPrice} (raw={$rawLevel} lots={$lots} qty={$qty}) via {$broker->client_type}";

        // ── Dry run ───────────────────────────────────────────────────────────
        if ($isDryRun) {
            $this->info("      DRY-RUN: {$label}");
            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                null, 'DRY_RUN', false
            ));
            return 'placed';
        }

        // ── Live order ────────────────────────────────────────────────────────
        try {
            $result = $helper->placeOrder(
                $optSymbol,
                $optToken,
                $transactionType,
                $config->order_type,
                $config->product,
                $lots,
                $orderPrice,
                $freezeLimitLots
            );

            $orderIdStr = implode(',', $result['order_ids']);

            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                $orderIdStr, 'OPEN', true, now()
            ));

            $this->info("      ✓ PLACED: {$label} | Order ID(s): {$orderIdStr}");
            Log::info("PlacePivotOrders: PLACED {$label} | config={$config->id} order_ids={$orderIdStr}");

            return 'placed';

        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
            $this->error("      ✗ ERROR: {$label} | {$errMsg}");
            Log::error("PlacePivotOrders: ERROR {$label} | {$errMsg}");

            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                null, 'ERROR', false, null, $errMsg
            ));

            return 'error';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Instrument info shim
    // ─────────────────────────────────────────────────────────────────────────

    private function getLotAndTick(
        ZerodhaPivotHelper|AngelPivotHelper $helper,
        string $tradingSymbol,
        int    $instrumentToken
    ): array {
        if ($helper instanceof AngelPivotHelper) {
            $angelSymbol = $helper->toAngelSymbol($tradingSymbol);
            $info        = $helper->getInstrumentInfo($angelSymbol, $instrumentToken);
            return [$info[1], $info[2]];
        }
        return $helper->getInstrumentInfo($tradingSymbol, $instrumentToken);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NewPivotOrder payload builder
    // ─────────────────────────────────────────────────────────────────────────

    private function buildOrderPayload(
        NewPivotOrderConfig $config,
        BrokerApi           $broker,
        object              $candle,
        string              $optionType,
        string              $level,
        int                 $layerNum,
        string              $transactionType,
        float               $rawLevel,
        float               $orderPrice,
        string              $candleTime,
        int                 $qty,
        ?string             $orderId      = null,
        string              $kiteStatus   = 'OPEN',
        bool                $isPlaced     = true,
        $placedAt           = null,
        ?string             $errorMessage = null
    ): array {
        return [
            'user_id'          => $config->user_id,
            'config_id'        => $config->id,
            'broker_api_id'    => $config->broker_api_id,
            'symbol'           => $candle->base_symbol,
            'option_symbol'    => $candle->trading_symbol,
            'option_token'     => $candle->instrument_token,
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
            'kite_order_id'    => $orderId,
            'kite_status'      => $kiteStatus,
            'is_order_placed'  => $isPlaced,
            'order_placed_at'  => $placedAt ?? ($isPlaced ? now() : null),
            'error_message'    => $errorMessage,
            'status'           => $isPlaced,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pivot calculation
    // ─────────────────────────────────────────────────────────────────────────

    private function calcPivots(object $candle): array
    {
        $H  = (float) $candle->high;
        $L  = (float) $candle->low;
        $C  = (float) $candle->close;
        $PP = round(($H + $L + $C) / 3, 2);
        $R1 = round((2 * $PP) - $L, 2);
        $S1 = round((2 * $PP) - $H, 2);
        return [$PP, $S1, $R1];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Last completed 30-min slot
    // ─────────────────────────────────────────────────────────────────────────

    private function getLastCompletedSlot(): ?string
    {
        $now    = Carbon::now();
        $hour   = $now->hour;
        $minute = $now->minute;

        if ($minute >= 15) {
            $slotHour   = $hour - 1;
            $slotMinute = 15;
        } else {
            $slotHour   = $hour - 2;
            $slotMinute = 15;
        }

        if ($slotHour < 9) return null;

        $candidate = sprintf('%02d:%02d', $slotHour, $slotMinute);

        if ($candidate < '09:15') $candidate = '09:15';
        if ($candidate > '15:15') $candidate = '15:15';

        if (!in_array($candidate, self::INTERVALS)) return null;

        return $candidate;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Data helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getNearestExpiry(string $symbol, string $date): ?string
    {
        $expiry = ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getAtmCandle(string $symbol, string $type, string $expiry, string $date, string $slot): ?object
    {
        return ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->first();
    }

    private function getBestNonAtmCandle(string $symbol, string $type, string $expiry, string $date, string $slot): ?object
    {
        $candidates = ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereIn('strike_position', ['ATM-1', 'ATM+1'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortByDesc('volume')->first();
    }
}