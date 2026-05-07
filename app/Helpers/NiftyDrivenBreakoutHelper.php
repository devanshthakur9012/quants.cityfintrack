<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\NiftyDrivenBreakoutConfig;
use App\Models\NiftyDrivenBreakoutOrder;
use App\Models\OptionOhlcData;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * NiftyDrivenBreakoutHelper
 *
 * ════════════════════════════════════════════════════════════════════
 * ORDER FLOW (end-to-end):
 *
 *  [Artisan Command] → processSignals() → placeOrders()
 *
 *  processSignals():
 *    1. Load NIFTY FUT 15-min candles for today
 *    2. Open ref = close of 09:15 candle
 *    3. Scan every candle after 09:15:
 *         CE signal → close >= open + threshold (fires ONCE per day)
 *         PE signal → close <= open - threshold (fires ONCE per day)
 *    4. For each active config × each signal:
 *         - Determine finalOT (CE/PE based on signal_mode align/opposite)
 *         - Get all symbols with valid OI for today
 *         - Filter by config allowed_symbols if set
 *         - Batch-prefetch LTPs (one Kite getQuote per 500 symbols)
 *         - For each symbol:
 *             * DUPLICATE CHECK: skip if row already exists for
 *               (config_id, signal_date, symbol, signal_type, status=true)
 *             * Find best strike (highest OI among ATM-1, ATM, ATM+1)
 *             * Resolve quantity (lots or investment per-symbol budget)
 *             * Compute SL price and Target price from config
 *             * CREATE NiftyDrivenBreakoutOrder (is_order_placed=false)
 *
 *  placeOrders():
 *    For each pending order (is_order_placed=false):
 *    1. Look up ZerodhaInstrument for the option
 *    2. Compute LIMIT price = entry − disc_ltp%
 *    3. BUY order — split into legs if > freeze limit
 *    4. Mark is_order_placed=true, store kite_order_id
 *    5. If stoploss_enabled → place SL/SL-M SELL immediately
 *    6. If target_enabled  → place LIMIT/SL-M/SL SELL immediately
 *    7. Record in OrderBook table
 *
 * DUPLICATE PREVENTION (two layers):
 *   1. DB EXISTS check before creating any pending row
 *      → (config_id + signal_date + symbol + signal_type + status=true)
 *   2. withoutOverlapping(10) in Kernel.php prevents concurrent runs
 *
 * INVESTMENT MODE:
 *   Investment is PER SYMBOL, not shared across symbols.
 *   Index CE = ₹1L with 10 index symbols → each symbol gets ₹1L.
 *   Total deployed = ₹1L × 10 = ₹10L.
 *   Lots = floor(budget ÷ (LTP × lot_size)) per symbol independently.
 * ════════════════════════════════════════════════════════════════════
 */
class NiftyDrivenBreakoutHelper
{
    private const INDEX_SYMBOLS = [
        'NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY', 'SENSEX', 'BANKEX',
    ];

    private const FREEZE_LIMITS = [
        'NIFTY'       => 18,  'BANKNIFTY'   => 20,  'FINNIFTY'    => 24,
        'MIDCPNIFTY'  => 24,  'ADANIPORTS'  => 30,  'AMBUJACEM'   => 40,
        'ASIANPAINT'  => 40,  'AUROPHARMA'  => 40,  'AXISBANK'    => 30,
        'BAJAJFINSV'  => 50,  'BAJFINANCE'  => 30,  'BHARATFORG'  => 30,
        'BHARTIARTL'  => 30,  'BHEL'        => 30,  'BPCL'        => 30,
        'BSE'         => 20,  'CDSL'        => 30,  'COFORGE'     => 30,
        'BDL'         => 40,  'DELHIVERY'   => 30,  'DRREDDY'     => 30,
        'ETERNAL'     => 30,  'FORTIS'      => 40,  'HAL'         => 40,
        'HAVELLS'     => 30,  'HEROMOTOCO'  => 30,  'HINDALCO'    => 40,
        'ICICIBANK'   => 30,  'INDUSINDBK'  => 40,  'INFY'        => 40,
        'JSWSTEEL'    => 30,  'LAURUSLABS'  => 30,  'LICHSGFIN'   => 40,
        'LT'          => 40,  'LTF'         => 40,  'M&M'         => 30,
        'NATIONALUM'  => 20,  'PAYTM'       => 30,  'PGEL'        => 40,
        'POLICYBZR'   => 40,  'SBIN'        => 30,  'SHRIRAMFIN'  => 30,
        'SRF'         => 40,  'TATACONSUM'  => 40,  'TATAELXSI'   => 40,
        'TATATECH'    => 50,  'TITAN'       => 30,  'TCS'         => 40,
        'UPL'         => 30,  'VBL'         => 40,  'VEDL'        => 30,
        'VOLTAS'      => 40,  'MCX'         => 20,  'CHOLAFIN'    => 20,
        'TECHM'       => 30,  'SBICARD'     => 40,  'SBILIFE'     => 30,
    ];

    private const STRIKE_INTERVALS = [
        'NIFTY'       => 100, 'BANKNIFTY'   => 100, 'FINNIFTY'    => 50,
        'MIDCPNIFTY'  => 25,  'AXISBANK'    => 10,  'ICICIBANK'   => 10,
        'INDUSINDBK'  => 10,  'BHARTIARTL'  => 20,  'SHRIRAMFIN'  => 10,
        'LTF'         => 5,   'PAYTM'       => 20,  'POLICYBZR'   => 20,
        'BAJAJFINSV'  => 20,  'INFY'        => 20,  'TATAELXSI'   => 50,
        'TATATECH'    => 10,  'HAVELLS'     => 20,  'TITAN'       => 20,
        'ASIANPAINT'  => 20,  'TATACONSUM'  => 10,  'VOLTAS'      => 20,
        'AUROPHARMA'  => 10,  'LAURUSLABS'  => 10,  'SRF'         => 20,
        'JSWSTEEL'    => 10,  'LT'          => 20,  'BHEL'        => 5,
        'ADANIPORTS'  => 20,  'HAL'         => 50,  'BDL'         => 20,
        'MCX'         => 20,  'BSE'         => 50,  'CDSL'        => 20,
        'LICHSGFIN'   => 5,   'DELHIVERY'   => 10,  'BHARATFORG'  => 20,
        'PGEL'        => 10,  'HINDALCO'    => 10,  'VEDL'        => 10,
        'DRREDDY'     => 50,  'HEROMOTOCO'  => 20,  'AMBUJACEM'   => 5,
        'FORTIS'      => 5,   'UPL'         => 10,  'M&M'         => 20,
        'NATIONALUM'  => 5,   'BPCL'        => 10,  'ETERNAL'     => 10,
        'SBIN'        => 10,  'VBL'         => 20,  'BAJFINANCE'  => 50,
        'TCS'         => 50,  'COFORGE'     => 50,
    ];

    private array $kiteInstances   = [];
    private array $lastRequestTime = [];
    private array $ltpCache        = [];
    private int   $minMsBetween    = 350;

    // =========================================================
    //  PUBLIC ENTRY POINTS
    // =========================================================

    public function processSignals(?string $testDate = null): void
    {
        Log::info('=== NiftyDrivenBreakout: Starting signal detection ===');

        $today = $testDate ?? Carbon::now('Asia/Kolkata')->format('Y-m-d');

        $configs = NiftyDrivenBreakoutConfig::where('status', true)->get();
        if ($configs->isEmpty()) {
            Log::info('NiftyDrivenBreakout: No active configs');
            return;
        }

        foreach ($configs as $config) {
            $broker = $config->broker;
            if (!$broker || !$broker->hasValidToken()) {
                Log::warning("NiftyDrivenBreakout Config {$config->id}: invalid broker token — skipped");
                continue;
            }

            $this->ensureKiteInstance($broker);

            $configSignals = $this->detectNiftySignals($today, (float) $config->threshold);

            if (empty($configSignals)) {
                Log::info("NiftyDrivenBreakout Config {$config->id}: no signal yet for {$today}");
                continue;
            }

            $filtered = array_filter($configSignals, function ($sig) use ($config) {
                if ($config->filter === 'CE') return $sig['signal_type'] === 'CE';
                if ($config->filter === 'PE') return $sig['signal_type'] === 'PE';
                return true;
            });

            foreach ($filtered as $signal) {
                $this->processOneSignal($config, $broker, $signal, $today);
            }
        }

        Log::info('=== NiftyDrivenBreakout: Signal detection completed ===');
    }

    public function placeOrders(?string $testDate = null): void
    {
        Log::info('=== NiftyDrivenBreakout: Starting order placement ===');

        $pending = NiftyDrivenBreakoutOrder::where('is_order_placed', false)
            ->where('status', true)
            ->whereHas('config', fn($q) => $q->where('status', true))
            ->with(['config', 'broker'])
            ->get();

        if ($pending->isEmpty()) {
            Log::info('NiftyDrivenBreakout: No pending orders');
            return;
        }

        Log::info("NiftyDrivenBreakout: {$pending->count()} pending order(s)");

        foreach ($pending->groupBy('broker_api_id') as $brokerId => $orders) {
            $broker = BrokerApi::find($brokerId);
            if (!$broker || !$broker->hasValidToken()) {
                Log::warning("NiftyDrivenBreakout Broker {$brokerId}: invalid token — skipping {$orders->count()} order(s)");
                continue;
            }
            $this->ensureKiteInstance($broker);

            foreach ($orders as $order) {
                $this->placeOneOrder($order);
            }
        }

        Log::info('=== NiftyDrivenBreakout: Order placement completed ===');
    }

    // =========================================================
    //  STEP 1 — DETECT NIFTY SIGNALS
    // =========================================================

    private function detectNiftySignals(string $date, float $threshold): array
    {
        $candles = OptionOhlcData::where('base_symbol', 'NIFTY')
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close'])
            ->values();

        if ($candles->isEmpty()) {
            Log::warning("NiftyDrivenBreakout: No NIFTY FUT candles for {$date}");
            return [];
        }

        $openPrice = (float) $candles->first()->close;
        if ($openPrice <= 0) return [];

        $ceThreshold = $openPrice + $threshold;
        $peThreshold = $openPrice - $threshold;

        Log::info("NiftyDrivenBreakout: {$date} Open={$openPrice} | CE>={$ceThreshold} | PE<={$peThreshold}");

        $ceTriggered = $peTriggered = false;
        $signals = [];

        foreach ($candles as $i => $candle) {
            $timeKey = Carbon::parse($candle->interval_time)->format('H:i');
            // if ($timeKey === '09:15') continue;

            $high       = (float) $candle->high;
            $low        = (float) $candle->low;
            $close      = (float) $candle->close;
            $nextCandle = $candles[$i + 1] ?? null;
            $buyTime    = $nextCandle
                ? Carbon::parse($nextCandle->interval_time)->format('H:i')
                : $timeKey;

            // CE: candle HIGH touches or crosses above open + threshold
            if (!$ceTriggered && $high >= $ceThreshold) {
                $ceTriggered = true;
                $signals[] = [
                    'signal_type'   => 'CE',
                    'trigger_time'  => $timeKey,
                    'buy_time'      => $buyTime,
                    'nifty_open'    => round($openPrice, 2),
                    'nifty_trigger' => round($high, 2),         // high that crossed threshold
                    'nifty_move'    => round($high - $openPrice, 2),
                    'threshold'     => $threshold,
                ];
                Log::info("NiftyDrivenBreakout: CE SIGNAL at {$timeKey} high={$high} threshold={$ceThreshold}");
            }

            // PE: candle LOW touches or crosses below open - threshold
            if (!$peTriggered && $low <= $peThreshold) {
                $peTriggered = true;
                $signals[] = [
                    'signal_type'   => 'PE',
                    'trigger_time'  => $timeKey,
                    'buy_time'      => $buyTime,
                    'nifty_open'    => round($openPrice, 2),
                    'nifty_trigger' => round($low, 2),          // low that crossed threshold
                    'nifty_move'    => round($low - $openPrice, 2),
                    'threshold'     => $threshold,
                ];
                Log::info("NiftyDrivenBreakout: PE SIGNAL at {$timeKey} low={$low} threshold={$peThreshold}");
            }

            if ($ceTriggered && $peTriggered) break;
        }

        return $signals;
    }

    // =========================================================
    //  STEP 2 — CREATE PENDING ORDER ROWS
    // =========================================================

    private function processOneSignal(
        NiftyDrivenBreakoutConfig $config,
        BrokerApi $broker,
        array $signal,
        string $date
    ): void {
        $rawSignalType = $signal['signal_type'];
        $finalOT = $config->shouldReverseSignal()
            ? ($rawSignalType === 'CE' ? 'PE' : 'CE')
            : $rawSignalType;

        $symbolsOnDate = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->where('oi', '>', 0)
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values()
            ->toArray();

        $allowed = $config->getAllowedSymbols();
        $symbols = !empty($allowed)
            ? array_values(array_intersect($symbolsOnDate, $allowed))
            : $symbolsOnDate;

        if (empty($symbols)) {
            Log::warning("NiftyDrivenBreakout Config {$config->id}: no symbols on {$date}");
            return;
        }

        $this->prefetchATMOptionLTPs($broker, $symbols, $finalOT, $date, $signal);

        foreach ($symbols as $symbol) {
            try {
                // ── DUPLICATE PREVENTION ─────────────────────────────────────
                // Check: config + date + symbol + signal_type + active
                // This makes processSignals() fully idempotent.
                // Running the command every 15 min never double-creates rows.
                $exists = NiftyDrivenBreakoutOrder::where('config_id', $config->id)
                    ->where('signal_date', $date)
                    ->where('symbol', $symbol)
                    ->where('signal_type', $rawSignalType)
                    ->where('status', true)
                    ->exists();

                if ($exists) {
                    Log::debug("NiftyDrivenBreakout {$symbol} [{$rawSignalType}]: duplicate — skipped");
                    continue;
                }

                $optionDetails = $this->findBestStrike($symbol, $finalOT, $date);
                if (!$optionDetails) {
                    Log::warning("NiftyDrivenBreakout {$symbol}: no strike for {$finalOT}");
                    continue;
                }

                $ltp = $this->getCachedLTP($broker, $optionDetails['trading_symbol'])
                    ?? $this->getOptionLTP($broker, $optionDetails['instrument_token'], $optionDetails['trading_symbol'], $symbol);

                if ($ltp <= 0) {
                    Log::warning("NiftyDrivenBreakout {$symbol}: LTP=0 — skipped");
                    continue;
                }

                $lotSize     = $this->getLotSize($symbol);
                $quantity    = $config->resolveQuantity($symbol, $finalOT, $ltp, $lotSize);

                if ($quantity <= 0) {
                    Log::info("NiftyDrivenBreakout {$symbol}: qty=0 — skipped");
                    continue;
                }

                $investment  = round($ltp * $lotSize * $quantity, 2);
                $slPrice     = $config->computeStoplossPrice($ltp);
                $targetPrice = $config->computeTargetPrice($ltp);

                $reason = sprintf(
                    'NIFTY Breakout | Signal:%s | FinalType:%s | Mode:%s | Trigger:%s | BuyCandle:%s | '
                    . 'NiftyOpen:%.2f | NiftyTrigger:%.2f | Move:%.2f | Threshold:%.2f | '
                    . 'Strike:%s | Expiry:%s | LTP:%.2f | Qty:%d lots | Inv:₹%.2f | '
                    . 'SL:%s (%s %s) | Target:%s (%s %s)',
                    $rawSignalType, $finalOT, strtoupper($config->signal_mode),
                    $signal['trigger_time'], $signal['buy_time'],
                    $signal['nifty_open'], $signal['nifty_trigger'],
                    $signal['nifty_move'], $signal['threshold'],
                    $optionDetails['strike'], $optionDetails['expiry'],
                    $ltp, $quantity, $investment,
                    $slPrice     ? number_format($slPrice, 2)     : 'off',
                    $config->stoploss_type  ?? 'pct', $config->stoploss_value  ?? '-',
                    $targetPrice ? number_format($targetPrice, 2) : 'off',
                    $config->target_type    ?? 'pct', $config->target_value    ?? '-'
                );

                NiftyDrivenBreakoutOrder::create([
                    'user_id'            => $config->user_id,
                    'config_id'          => $config->id,
                    'broker_api_id'      => $broker->id,
                    'signal_date'        => $date,
                    'symbol'             => $symbol,
                    'signal_type'        => $rawSignalType,
                    'nifty_open'         => $signal['nifty_open'],
                    'nifty_trigger'      => $signal['nifty_trigger'],
                    'trigger_time'       => $signal['trigger_time'],
                    'nifty_move'         => $signal['nifty_move'],
                    'threshold'          => $signal['threshold'],
                    'option_symbol'      => $optionDetails['trading_symbol'],
                    'option_token'       => $optionDetails['instrument_token'],
                    'option_type'        => $finalOT,
                    'strike_price'       => $optionDetails['strike'],
                    'expiry_date'        => $optionDetails['expiry'],
                    'entry_price'        => $ltp,
                    'current_price'      => $ltp,
                    'spot_price'         => $signal['nifty_trigger'],
                    'lot_size'           => $lotSize,
                    'quantity'           => $quantity,
                    'investment'         => $investment,
                    'stoploss_enabled'   => $config->enable_stoploss,
                    'stoploss_price'     => $slPrice,
                    'target_enabled'     => $config->enable_target,
                    'target_price'       => $targetPrice,
                    'order_type'         => $config->order_type,
                    'product'            => $config->product,
                    'is_order_placed'    => false,
                    'signal_detected_at' => now(),
                    'signal_reason'      => $reason,
                    'status'             => true,
                ]);

                Log::info(sprintf(
                    'NiftyDrivenBreakout PENDING: %s | %s | %dL | LTP:%.2f | Inv:₹%.2f | SL:%s | Tgt:%s',
                    $optionDetails['trading_symbol'], $finalOT, $quantity, $ltp, $investment,
                    $slPrice     ? '₹'.number_format($slPrice, 2)     : 'off',
                    $targetPrice ? '₹'.number_format($targetPrice, 2) : 'off'
                ));

            } catch (\Exception $e) {
                Log::error("NiftyDrivenBreakout processOneSignal {$symbol}: " . $e->getMessage());
            }
        }
    }

    // =========================================================
    //  STEP 3 — PLACE ORDERS
    // =========================================================

    private function placeOneOrder(NiftyDrivenBreakoutOrder $order): void
    {
        try {
            Log::info("NiftyDrivenBreakout PLACING: {$order->option_symbol} | {$order->quantity}L | {$order->option_type}");

            $broker = $order->broker;
            if (!$broker || !$broker->hasValidToken()) {
                $this->recordFailed($order, 'Invalid broker token');
                return;
            }

            $this->ensureKiteInstance($broker);

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first()
                ?? ZerodhaInstrument::where('trading_symbol', $order->option_symbol)->first();

            if (!$instrument) {
                $this->recordFailed($order, "Instrument not found: {$order->option_symbol}");
                return;
            }

            $exchange  = $this->getExchangeFor($order->symbol);
            $kite      = $this->kiteInstances[$broker->id];
            $lotSize   = (int) ($instrument->lot_size ?? $order->lot_size ?? 1);
            $totalLots = (int) $order->quantity;
            $totalQty  = $totalLots * $lotSize;

            // ── LIMIT price = entry - disc_ltp% ──────────────────────────────
            $limitPrice = null;
            if ($order->order_type === 'LIMIT') {
                $config     = $order->config;
                $discPct    = (float) ($config->disc_ltp ?? 0);
                $raw        = (float) $order->entry_price * (1 - $discPct / 100);
                $tick       = max((float) ($instrument->tick_size ?? 0.05), 0.01);
                $limitPrice = number_format(round($raw / $tick) * $tick, 2, '.', '');
            }

            // ── BUY: split into legs if above freeze limit ────────────────────
            $freezeLots    = self::FREEZE_LIMITS[$order->symbol] ?? null;
            $placedOrderId = null;

            if ($freezeLots && $totalLots > $freezeLots) {
                $remaining = $totalLots;
                while ($remaining > 0) {
                    $lotsThisLeg = min($freezeLots, $remaining);
                    $oid = $this->executeKiteOrder($order, $kite, $exchange, 'BUY', $lotsThisLeg * $lotSize, $limitPrice);
                    if ($placedOrderId === null) $placedOrderId = $oid;
                    $remaining -= $lotsThisLeg;
                    if ($remaining > 0) sleep(2);
                }
            } else {
                $placedOrderId = $this->executeKiteOrder($order, $kite, $exchange, 'BUY', $totalQty, $limitPrice);
            }

            $order->update([
                'is_order_placed'   => true,
                'kite_order_id'     => $placedOrderId,
                'kite_order_status' => 'PENDING',
                'order_placed_at'   => now(),
            ]);

            $this->recordOrderBook($order, $placedOrderId, $totalQty, $limitPrice);
            Log::info("NiftyDrivenBreakout BUY PLACED: {$order->option_symbol} KiteID:{$placedOrderId}");

            // ── SL SELL order ─────────────────────────────────────────────────
            if ($order->stoploss_enabled && $order->stoploss_price > 0) {
                $this->placeStoplossOrder($order, $instrument, $kite, $exchange, $totalQty);
            }

            // ── Target SELL order ─────────────────────────────────────────────
            if ($order->target_enabled && $order->target_price > 0) {
                $this->placeTargetOrder($order, $instrument, $kite, $exchange, $totalQty);
            }

        } catch (\Exception $e) {
            Log::error("NiftyDrivenBreakout placeOneOrder {$order->option_symbol}: " . $e->getMessage());
            $this->recordFailed($order, $e->getMessage());
        }
    }

    private function placeStoplossOrder(
        NiftyDrivenBreakoutOrder $order,
        mixed $instrument,
        mixed $kite,
        string $exchange,
        int $quantityUnits
    ): void {
        try {
            $config      = $order->config;
            $slTrigger   = (float) $order->stoploss_price;
            $tick        = max((float) ($instrument->tick_size ?? 0.05), 0.01);
            $slOrderType = strtoupper($config->stoploss_order_type ?? 'SL-M');
            $triggerRounded = number_format(round($slTrigger / $tick) * $tick, 2, '.', '');

            $params = [
                'exchange'         => $exchange,
                'tradingsymbol'    => $order->option_symbol,
                'transaction_type' => 'SELL',
                'quantity'         => $quantityUnits,
                'product'          => $order->product,
                'order_type'       => $slOrderType,
                'validity'         => 'DAY',
                'trigger_price'    => $triggerRounded,
            ];

            if ($slOrderType === 'SL') {
                $params['price'] = number_format(round(($slTrigger - $tick) / $tick) * $tick, 2, '.', '');
            }

            $this->throttleRequest($order->broker_api_id);
            $result    = $kite->placeOrder('regular', $params);
            $slOrderId = $result->order_id ?? null;

            $order->update(['stoploss_placed' => true, 'stoploss_order_id' => $slOrderId]);

            Log::info("NiftyDrivenBreakout SL PLACED: {$order->option_symbol} | {$slOrderType} | Trigger:{$triggerRounded} | ID:{$slOrderId}");

        } catch (\Exception $e) {
            Log::error("NiftyDrivenBreakout SL failed {$order->option_symbol}: " . $e->getMessage());
            $order->update(['stoploss_placed' => false]);
        }
    }

    private function placeTargetOrder(
        NiftyDrivenBreakoutOrder $order,
        mixed $instrument,
        mixed $kite,
        string $exchange,
        int $quantityUnits
    ): void {
        try {
            $config          = $order->config;
            $targetPrice     = (float) $order->target_price;
            $tick            = max((float) ($instrument->tick_size ?? 0.05), 0.01);
            $targetOrderType = strtoupper($config->target_order_type ?? 'LIMIT');
            $targetRounded   = number_format(round($targetPrice / $tick) * $tick, 2, '.', '');

            if ($targetOrderType === 'LIMIT') {
                $params = [
                    'exchange'         => $exchange,
                    'tradingsymbol'    => $order->option_symbol,
                    'transaction_type' => 'SELL',
                    'quantity'         => $quantityUnits,
                    'product'          => $order->product,
                    'order_type'       => 'LIMIT',
                    'validity'         => 'DAY',
                    'price'            => $targetRounded,
                ];
            } elseif ($targetOrderType === 'SL-M') {
                $params = [
                    'exchange'         => $exchange,
                    'tradingsymbol'    => $order->option_symbol,
                    'transaction_type' => 'SELL',
                    'quantity'         => $quantityUnits,
                    'product'          => $order->product,
                    'order_type'       => 'SL-M',
                    'validity'         => 'DAY',
                    'trigger_price'    => $targetRounded,
                ];
            } else {
                $params = [
                    'exchange'         => $exchange,
                    'tradingsymbol'    => $order->option_symbol,
                    'transaction_type' => 'SELL',
                    'quantity'         => $quantityUnits,
                    'product'          => $order->product,
                    'order_type'       => 'SL',
                    'validity'         => 'DAY',
                    'trigger_price'    => $targetRounded,
                    'price'            => number_format(round(($targetPrice - $tick) / $tick) * $tick, 2, '.', ''),
                ];
            }

            $this->throttleRequest($order->broker_api_id);
            $result        = $kite->placeOrder('regular', $params);
            $targetOrderId = $result->order_id ?? null;

            $order->update(['target_placed' => true, 'target_order_id' => $targetOrderId]);

            Log::info("NiftyDrivenBreakout TARGET PLACED: {$order->option_symbol} | {$targetOrderType} | Price:{$targetRounded} | ID:{$targetOrderId}");

        } catch (\Exception $e) {
            Log::error("NiftyDrivenBreakout Target failed {$order->option_symbol}: " . $e->getMessage());
            $order->update(['target_placed' => false]);
        }
    }

    // =========================================================
    //  KITE EXECUTION
    // =========================================================

    private function executeKiteOrder(
        NiftyDrivenBreakoutOrder $order,
        mixed $kite,
        string $exchange,
        string $txnType,
        int $quantityUnits,
        ?string $limitPrice
    ): ?string {
        $params = [
            'exchange'         => $exchange,
            'tradingsymbol'    => $order->option_symbol,
            'transaction_type' => $txnType,
            'quantity'         => $quantityUnits,
            'product'          => $order->product,
            'order_type'       => $order->order_type === 'MARKET' ? 'MARKET' : 'LIMIT',
            'validity'         => 'DAY',
        ];

        if ($order->order_type !== 'MARKET') {
            $params['price'] = $limitPrice;
        }

        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->throttleRequest($order->broker_api_id);
                $result = $kite->placeOrder('regular', $params);
                return $result->order_id ?? null;
            } catch (\Exception $e) {
                if ($this->isRateLimitError($e) && $attempt < $maxRetries) {
                    sleep((int) pow(2, $attempt));
                    Log::warning("NiftyDrivenBreakout rate-limited — retry {$attempt}");
                } else {
                    throw $e;
                }
            }
        }
        return null;
    }

    // =========================================================
    //  STRIKE SELECTION
    // =========================================================

    private function findBestStrike(string $symbol, string $optionType, string $date): ?array
    {
        $futureRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->first(['future_price', 'atm_strike']);

        $interval  = self::STRIKE_INTERVALS[$symbol] ?? 50;
        $atmStrike = null;

        if ($futureRow) {
            if (!empty($futureRow->atm_strike) && $futureRow->atm_strike > 0) {
                $atmStrike = (float) $futureRow->atm_strike;
            } elseif (!empty($futureRow->future_price) && $futureRow->future_price > 0) {
                $atmStrike = round((float) $futureRow->future_price / $interval) * $interval;
            }
        }

        $topRow = null;
        if ($atmStrike) {
            $candidates = [$atmStrike - $interval, $atmStrike, $atmStrike + $interval];
            $topRow = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->whereDate('trade_date', $date)
                ->where('oi', '>', 0)->where('is_missing', 0)
                ->whereIn('strike', $candidates)
                ->orderByDesc('oi')
                ->first(['strike', 'expiry_date', 'trading_symbol']);
        }

        if (!$topRow) {
            $topRow = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->whereDate('trade_date', $date)
                ->where('oi', '>', 0)->where('is_missing', 0)
                ->orderByDesc('oi')
                ->first(['strike', 'expiry_date', 'trading_symbol']);
        }

        if (!$topRow) return null;

        $strike     = (float) $topRow->strike;
        $expiryDate = is_string($topRow->expiry_date)
            ? substr($topRow->expiry_date, 0, 10)
            : Carbon::parse($topRow->expiry_date)->toDateString();

        $exchange = $this->getExchangeFor($symbol);

        $instrument = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $exchange)
            ->where('instrument_type', $optionType)
            ->where('strike', $strike)
            ->whereDate('expiry', $expiryDate)
            ->first();

        if (!$instrument) {
            $instrument = ZerodhaInstrument::where('name', $symbol)
                ->where('exchange', $exchange)
                ->where('instrument_type', $optionType)
                ->whereDate('expiry', $expiryDate)
                ->selectRaw('*, ABS(strike - ?) as diff', [$strike])
                ->orderBy('diff')->first();
        }

        if (!$instrument) {
            Log::warning("NiftyDrivenBreakout: No ZerodhaInstrument for {$symbol} {$optionType} {$strike} {$expiryDate}");
            return null;
        }

        return [
            'trading_symbol'   => $instrument->trading_symbol,
            'instrument_token' => $instrument->instrument_token,
            'strike'           => $instrument->strike,
            'expiry'           => $expiryDate,
            'lot_size'         => $instrument->lot_size ?? 1,
        ];
    }

    // =========================================================
    //  LTP FETCHING
    // =========================================================

    private function prefetchATMOptionLTPs(BrokerApi $broker, array $symbols, string $optionType, string $date, array $signal): void
    {
        $toFetch = [];
        foreach ($symbols as $symbol) {
            $opt = $this->findBestStrike($symbol, $optionType, $date);
            if ($opt) $toFetch[] = $opt['trading_symbol'];
        }
        if (empty($toFetch)) return;

        foreach (array_chunk(array_unique($toFetch), 500) as $chunk) {
            try {
                $keys = array_map(fn($s) => $this->getExchangeFor(preg_replace('/\d{2}[A-Z]{3}\d+[CP]E$/i', '', $s)) . ":{$s}", $chunk);
                $this->throttleRequest($broker->id);
                $quotes = $this->kiteInstances[$broker->id]->getQuote($keys);
                $arr    = json_decode(json_encode($quotes), true);
                foreach ($arr as $key => $q) {
                    $sym = preg_replace('/^[A-Z]+:/', '', $key);
                    $ltp = (float) ($q['last_price'] ?? 0);
                    if ($ltp > 0) $this->ltpCache[$broker->id][$sym] = $ltp;
                }
            } catch (\Exception $e) {
                Log::warning("NiftyDrivenBreakout prefetchLTPs: " . $e->getMessage());
            }
        }
    }

    private function getCachedLTP(BrokerApi $broker, string $tradingSymbol): ?float
    {
        $val = $this->ltpCache[$broker->id][$tradingSymbol] ?? null;
        return ($val !== null && $val > 0) ? $val : null;
    }

    private function getOptionLTP(BrokerApi $broker, mixed $token, string $tradingSymbol, string $baseSymbol = ''): float
    {
        if (isset($this->ltpCache[$broker->id][$tradingSymbol])) {
            return (float) $this->ltpCache[$broker->id][$tradingSymbol];
        }
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $exch   = $this->getExchangeFor($baseSymbol ?: preg_replace('/\d{2}[A-Z]{3}\d+[CP]E$/i', '', $tradingSymbol));
                $this->throttleRequest($broker->id);
                $quotes = $this->kiteInstances[$broker->id]->getQuote(["{$exch}:{$tradingSymbol}"]);
                $arr    = json_decode(json_encode($quotes), true);
                $ltp    = (float) ($arr["{$exch}:{$tradingSymbol}"]['last_price'] ?? 0);
                $this->ltpCache[$broker->id][$tradingSymbol] = $ltp;
                return $ltp;
            } catch (\Exception $e) {
                if ($this->isRateLimitError($e) && $attempt < 3) { sleep((int) pow(2, $attempt)); }
                else { Log::error("NiftyDrivenBreakout LTP {$tradingSymbol}: " . $e->getMessage()); return 0.0; }
            }
        }
        return 0.0;
    }

    // =========================================================
    //  ORDER BOOK
    // =========================================================

    private function recordOrderBook(NiftyDrivenBreakoutOrder $order, ?string $kiteOrderId, int $qty, ?string $price): void
    {
        try {
            sleep(2);
            $kite    = $this->kiteInstances[$order->broker_api_id] ?? null;
            $history = ($kite && $kiteOrderId) ? $kite->getOrderHistory($kiteOrderId) : [];
            $last    = !empty($history) ? end($history) : null;
            OrderBook::create([
                'user_id'          => $order->user_id,
                'broker_username'  => $order->broker->account_user_name ?? '',
                'order_id'         => $kiteOrderId ?? '-',
                'status'           => $last->status ?? 'PENDING',
                'trading_symbol'   => $order->option_symbol,
                'order_type'       => $order->order_type,
                'transaction_type' => 'BUY',
                'product'          => $order->product,
                'price'            => $price ?? '-',
                'quantity'         => $qty,
                'status_message'   => $last->status_message ?? 'Order placed',
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("NiftyDrivenBreakout OrderBook: " . $e->getMessage());
        }
    }

    private function recordFailed(NiftyDrivenBreakoutOrder $order, string $error): void
    {
        $order->update(['error_message' => substr($error, 0, 500)]);
        try {
            OrderBook::create([
                'user_id'          => $order->user_id,
                'broker_username'  => $order->broker->account_user_name ?? '',
                'order_id'         => '-', 'status' => 'FAILED',
                'trading_symbol'   => $order->option_symbol,
                'order_type'       => $order->order_type,
                'transaction_type' => 'BUY',
                'product'          => $order->product,
                'price'            => '-',
                'quantity'         => $order->quantity,
                'status_message'   => substr($error, 0, 500),
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("NiftyDrivenBreakout recordFailed: " . $e->getMessage());
        }
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $defaults = ['NIFTY'=>25,'BANKNIFTY'=>15,'FINNIFTY'=>25,'MIDCPNIFTY'=>50,'SENSEX'=>10,'BANKEX'=>15];
        $fromDb = DB::table('zerodha_instruments')
            ->where('name', $symbol)->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE','PE'])->value('lot_size');
        return $fromDb ? (int) $fromDb : ($defaults[$symbol] ?? 1);
    }

    private function getExchangeFor(string $baseSymbol): string
    {
        return in_array(strtoupper($baseSymbol), ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
    }

    private function throttleRequest(int $brokerId): void
    {
        if (isset($this->lastRequestTime[$brokerId])) {
            $elapsed = (int) ((microtime(true) - $this->lastRequestTime[$brokerId]) * 1000);
            if ($elapsed < $this->minMsBetween) usleep(($this->minMsBetween - $elapsed) * 1000);
        }
        $this->lastRequestTime[$brokerId] = microtime(true);
    }

    private function isRateLimitError(\Exception $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'rate') || str_contains($msg, '429')
            || str_contains($msg, 'too many') || str_contains($msg, 'throttle');
    }

    private function ensureKiteInstance(BrokerApi $broker): void
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);
            $this->kiteInstances[$broker->id] = $kite;
        }
    }
}