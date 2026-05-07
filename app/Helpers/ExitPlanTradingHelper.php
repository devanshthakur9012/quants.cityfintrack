<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\ExitPlanConfig;
use App\Models\ExitPlanOrder;
use App\Models\OptionOhlcData;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * ExitPlanTradingHelper
 *
 * Exit Plan Logic (runs at 09:30 AM each trading day):
 *
 *   signal_date      = previous trading day  (trade was taken after 3 PM)
 *   exit_check_date  = today                 (next trading day after the trade)
 *
 *   OI comparison:
 *     Current OI  = exit_check_date  09:30 CE/PE candles
 *     Previous OI = signal_date      15:15 CE/PE candles  (fallback: 15:00)
 *
 *   Same OI logic → EXIT sentiment (BULLISH / BEARISH / NEUTRAL)
 *
 *   Decision:
 *     HOLD    → exit sentiment same as original trade direction → do nothing
 *     EXIT    → exit sentiment OPPOSITE to original trade       → place SELL order
 *     MONITOR → exit sentiment NEUTRAL                          → do nothing
 *
 *   Config signal_mode:
 *     align    → SELL on EXIT   decision (normal)
 *     opposite → SELL on HOLD   decision (contrarian)
 *
 *   Quantity selection:
 *     Index symbols  (NIFTY/BANKNIFTY/etc.) → index_ce_quantity / index_pe_quantity
 *     Stock symbols  (all others)           → stock_ce_quantity / stock_pe_quantity
 */
class ExitPlanTradingHelper
{
    private const INDEX_SYMBOLS = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY', 'SENSEX', 'BANKEX'];

    private const STRIKE_INTERVALS = [
        'NIFTY'        => 100, 'BANKNIFTY'  => 100, 'FINNIFTY'     => 50,
        'MIDCPNIFTY'   => 25,  'AXISBANK'   => 10,  'ICICIBANK'    => 10,
        'INDUSINDBK'   => 10,  'BHARTIARTL' => 20,  'SHRIRAMFIN'   => 10,
        'LTF'          => 5,   'PAYTM'      => 20,  'POLICYBZR'    => 20,
        'BAJAJFINSV'   => 20,  'INFY'       => 20,  'TATAELXSI'    => 50,
        'TATATECH'     => 10,  'HAVELLS'    => 20,  'TITAN'        => 20,
        'ASIANPAINT'   => 20,  'TATACONSUM' => 10,  'VOLTAS'       => 20,
        'AUROPHARMA'   => 10,  'LAURUSLABS' => 10,  'SRF'          => 20,
        'JSWSTEEL'     => 10,  'LT'         => 20,  'BHEL'         => 5,
        'ADANIPORTS'   => 20,  'HAL'        => 50,  'BDL'          => 20,
        'MCX'          => 20,  'BSE'        => 50,  'CDSL'         => 20,
        'LICHSGFIN'    => 5,   'DELHIVERY'  => 10,  'BHARATFORG'   => 20,
        'PGEL'         => 10,  'TMPV'       => 5,   'HINDALCO'     => 10,
        'VEDL'         => 10,  'DRREDDY'    => 50,  'HEROMOTOCO'   => 20,
        'AMBUJACEM'    => 5,   'FORTIS'     => 5,   'UPL'          => 10,
        'M&M'          => 20,  'NATIONALUM' => 5,   'BPCL'         => 10,
        'ETERNAL'      => 10,  'SBIN'       => 10,  'VBL'          => 20,
        'BAJFINANCE'   => 50,  'TCS'        => 50,  'COFORGE'      => 50,
        'EICHERMOT'    => 50,  'ABCCAPITAL' => 10,
    ];

    private const FREEZE_LIMITS = [
        'NIFTY'      => 18, 'BANKNIFTY' => 20, 'FINNIFTY'  => 24, 'MIDCPNIFTY' => 24,
        'SBIN'       => 30, 'ICICIBANK' => 30, 'AXISBANK'  => 30, 'INFY'       => 40,
        'TCS'        => 40, 'BAJFINANCE'=> 30,
    ];

    private array $kiteInstances = [];

    // =========================================================
    //  PUBLIC ENTRY POINTS
    // =========================================================

    public function processSignals(?string $testDate = null): void
    {
        try {
            Log::info('=== EXIT PLAN: Starting Exit Signal Detection ===');

            $exitCheckDate = $testDate ?? Carbon::today('Asia/Kolkata')->toDateString();
            $signalDate    = $this->getPreviousTradingDate($exitCheckDate);
            $mode          = $testDate ? 'TEST' : 'LIVE';

            Log::info("{$mode} | ExitCheckDate: {$exitCheckDate} | SignalDate: {$signalDate}");

            $configs = ExitPlanConfig::where('status', true)->get();

            if ($configs->isEmpty()) {
                Log::info('EXIT PLAN: No active configs found');
                return;
            }

            // Build exit signals for all symbols
            $signals = $this->buildExitSignals($exitCheckDate, $signalDate);

            if (empty($signals)) {
                Log::warning("EXIT PLAN: No exit signal data found for {$exitCheckDate}");
                return;
            }

            Log::info(sprintf(
                "EXIT PLAN: %d symbols | EXIT: %d | HOLD: %d | MONITOR: %d",
                count($signals),
                count(array_filter($signals, fn($s) => $s['exit_decision'] === 'EXIT')),
                count(array_filter($signals, fn($s) => $s['exit_decision'] === 'HOLD')),
                count(array_filter($signals, fn($s) => $s['exit_decision'] === 'MONITOR'))
            ));

            foreach ($configs as $config) {
                $broker = $config->broker;
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("EXIT PLAN Config {$config->id}: Invalid broker token — skipping");
                    continue;
                }
                $this->ensureKiteInstance($broker);
                $this->processConfigSignals($config, $signals, $exitCheckDate, $signalDate);
            }

            Log::info('=== EXIT PLAN: Signal Detection Completed ===');

        } catch (\Exception $e) {
            Log::error('EXIT PLAN processSignals Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
        }
    }

    public function placeOrders(?string $testDate = null): void
    {
        try {
            Log::info('=== EXIT PLAN: Starting Order Placement ===');

            $pendingOrders = ExitPlanOrder::where('is_order_placed', false)
                ->where('status', true)
                ->where('exit_decision', 'EXIT')
                ->whereHas('config', fn($q) => $q->where('status', true))
                ->with(['config', 'broker'])
                ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('EXIT PLAN: No pending SELL orders');
                return;
            }

            Log::info("EXIT PLAN: Found {$pendingOrders->count()} pending SELL orders");

            foreach ($pendingOrders->groupBy('broker_api_id') as $brokerId => $orders) {
                $broker = BrokerApi::find($brokerId);
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("EXIT PLAN Broker {$brokerId}: invalid token — skipping");
                    continue;
                }
                $this->ensureKiteInstance($broker);
                foreach ($orders as $order) {
                    $this->placeOrder($order);
                }
            }

            Log::info('=== EXIT PLAN: Order Placement Completed ===');

        } catch (\Exception $e) {
            Log::error('EXIT PLAN placeOrders Error: ' . $e->getMessage());
        }
    }

    // =========================================================
    //  CORE: BUILD EXIT SIGNALS
    // =========================================================

    private function buildExitSignals(string $exitCheckDate, string $signalDate): array
    {
        // Current OI: exit_check_date 09:30
        $morningCandles = OptionOhlcData::whereDate('trade_date', $exitCheckDate)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '09:30:00'")
            ->get();

        if ($morningCandles->isEmpty()) {
            Log::warning("EXIT PLAN: No 09:30 candles found for {$exitCheckDate}");
            return [];
        }

        $symbols = $morningCandles->pluck('base_symbol')->unique()->values()->toArray();

        // Previous OI: signal_date 15:15 (fallback 15:00)
        $prev15 = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        $prev00 = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        // Original signal OI: signal_date 14:45
        $origCandles = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        // Original prev OI: signal_date's previous day 15:00
        $origPrevDate    = $this->getPreviousTradingDate($signalDate);
        $origPrevCandles = OptionOhlcData::whereDate('trade_date', $origPrevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        // ── Group all data ────────────────────────────────────────────────────

        $morningGrouped = [];
        foreach ($morningCandles as $c) {
            $morningGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }

        // Prev OI: prefer 15:15, fill gaps with 15:00
        $prevGrouped = [];
        foreach ($prev15 as $c) {
            $prevGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }
        foreach ($prev00 as $c) {
            if (!isset($prevGrouped[$c->base_symbol][$c->instrument_type])) {
                $prevGrouped[$c->base_symbol][$c->instrument_type][] = $c;
            }
        }

        $origGrouped = [];
        foreach ($origCandles as $c) {
            $origGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }

        $origPrevGrouped = [];
        foreach ($origPrevCandles as $c) {
            $key = (string)($c->strike ?? '');
            if ($key !== '') {
                $origPrevGrouped[$c->base_symbol][$c->instrument_type][$key] = $c;
            }
        }

        $signals = [];

        foreach ($morningGrouped as $symbol => $typeMap) {

            $futCandle = ($typeMap['FUT'] ?? [])[0] ?? null;
            if (!$futCandle || (float)$futCandle->close <= 0) continue;

            // ── EXIT SIGNAL OI ────────────────────────────────────────────────
            $exitCeCurOI  = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $typeMap['CE'] ?? []));
            $exitPeCurOI  = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $typeMap['PE'] ?? []));
            $exitCePrevOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $prevGrouped[$symbol]['CE'] ?? []));
            $exitPePrevOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $prevGrouped[$symbol]['PE'] ?? []));

            if ($exitCeCurOI == 0 && $exitPeCurOI == 0) continue;

            $exitCeOiPct  = $exitCePrevOI > 0 ? round((($exitCeCurOI - $exitCePrevOI) / $exitCePrevOI) * 100, 4) : 0;
            $exitPeOiPct  = $exitPePrevOI > 0 ? round((($exitPeCurOI - $exitPePrevOI) / $exitPePrevOI) * 100, 4) : 0;
            $exitOiSignal = $this->getOISignal($exitCeOiPct, $exitPeOiPct);
            $exitSentiment = $exitOiSignal['signal'];

            // ── ORIGINAL SIGNAL OI (to confirm trade was taken) ───────────────
            $origCeCurOI  = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $origGrouped[$symbol]['CE'] ?? []));
            $origPeCurOI  = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), $origGrouped[$symbol]['PE'] ?? []));
            $origCePrevOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), array_values($origPrevGrouped[$symbol]['CE'] ?? [])));
            $origPePrevOI = array_sum(array_map(fn($c) => (int)($c->oi ?? 0), array_values($origPrevGrouped[$symbol]['PE'] ?? [])));

            $origCeOiPct  = $origCePrevOI > 0 ? round((($origCeCurOI - $origCePrevOI) / $origCePrevOI) * 100, 4) : 0;
            $origPeOiPct  = $origPePrevOI > 0 ? round((($origPeCurOI - $origPePrevOI) / $origPePrevOI) * 100, 4) : 0;
            $origOiSignal = $this->getOISignal($origCeOiPct, $origPeOiPct);
            $origSentiment = $origOiSignal['signal'];

            $origTradeAction = match($origSentiment) {
                'BULLISH' => 'BUY CE',
                'BEARISH' => 'BUY PE',
                default   => 'WAIT',
            };

            // No trade was taken on signal_date — skip
            if ($origTradeAction === 'WAIT') continue;

            // ── EXIT DECISION ─────────────────────────────────────────────────
            [$exitDecision, $exitReason] = $this->determineExitDecision(
                $origSentiment, $exitSentiment, $origTradeAction
            );

            $signals[$symbol] = [
                'symbol'                => $symbol,
                'trading_symbol'        => $futCandle->trading_symbol ?? $symbol,
                'fut_close'             => (float)$futCandle->close,

                'original_sentiment'    => $origSentiment,
                'original_trade_action' => $origTradeAction,
                'original_oi_condition' => $origOiSignal['condition'],
                'orig_ce_oi_pct'        => $origCeOiPct,
                'orig_pe_oi_pct'        => $origPeOiPct,

                'exit_sentiment'        => $exitSentiment,
                'exit_oi_condition'     => $exitOiSignal['condition'],
                'exit_ce_oi_pct'        => $exitCeOiPct,
                'exit_pe_oi_pct'        => $exitPeOiPct,

                'exit_decision'         => $exitDecision,
                'exit_reason'           => $exitReason,
            ];

            Log::info(sprintf(
                "EXIT PLAN %s | Orig: %s (%s) | Exit: %s (%s) | Decision: %s",
                $symbol,
                $origSentiment, $origOiSignal['condition'],
                $exitSentiment, $exitOiSignal['condition'],
                $exitDecision
            ));
        }

        return $signals;
    }

    // =========================================================
    //  PROCESS SIGNALS PER CONFIG
    // =========================================================

    private function processConfigSignals(
        ExitPlanConfig $config,
        array $signals,
        string $exitCheckDate,
        string $signalDate
    ): void {
        $created = $skipped = $errors = 0;

        foreach ($signals as $symbol => $signal) {
            try {
                $decision = $signal['exit_decision'];

                // Decide whether to create a SELL order based on signal_mode
                // align    → SELL only when decision = EXIT
                // opposite → SELL only when decision = HOLD  (contrarian)
                $shouldCreateOrder = $config->shouldReverseSignal()
                    ? ($decision === 'HOLD')
                    : ($decision === 'EXIT');

                if (!$shouldCreateOrder) {
                    $skipped++;
                    continue;
                }

                // Prevent duplicate for same config + symbol + check date
                $exists = ExitPlanOrder::where('config_id', $config->id)
                    ->where('symbol', $symbol)
                    ->where('exit_check_date', $exitCheckDate)
                    ->where('status', true)
                    ->exists();

                if ($exists) {
                    Log::debug("EXIT PLAN {$symbol}: duplicate for {$exitCheckDate} — skipping");
                    $skipped++;
                    continue;
                }

                // Determine option type to SELL
                // BUY CE was taken → SELL CE
                // BUY PE was taken → SELL PE
                $origTradeAction = $signal['original_trade_action'];
                $optionType      = $origTradeAction === 'BUY CE' ? 'CE' : 'PE';

                $isIndex  = in_array(strtoupper($symbol), self::INDEX_SYMBOLS);
                $quantity = $config->resolveQuantity($optionType, $isIndex);

                if ($quantity <= 0) {
                    Log::info("EXIT PLAN {$symbol}: quantity=0 for {$optionType}/isIndex={$isIndex} — skipping");
                    $skipped++;
                    continue;
                }

                // Find ATM option to SELL
                $optionDetails = $this->getATMOption(
                    $config->broker,
                    $symbol,
                    $optionType,
                    $signal['fut_close'],
                    $exitCheckDate
                );

                if (!$optionDetails) {
                    Log::error("EXIT PLAN {$symbol}: No ATM {$optionType} found for {$exitCheckDate}");
                    $errors++;
                    continue;
                }

                if ($optionDetails['ltp'] <= 0) {
                    Log::error("EXIT PLAN {$symbol}: LTP=0 for {$optionDetails['symbol']} — skipping");
                    $errors++;
                    continue;
                }

                ExitPlanOrder::create([
                    'user_id'               => $config->user_id,
                    'config_id'             => $config->id,
                    'broker_api_id'         => $config->broker_api_id,
                    'symbol'                => $symbol,
                    'trading_symbol'        => $signal['trading_symbol'],
                    'signal_date'           => $signalDate,
                    'exit_check_date'       => $exitCheckDate,
                    'original_sentiment'    => $signal['original_sentiment'],
                    'original_trade_action' => $origTradeAction,
                    'original_oi_condition' => $signal['original_oi_condition'],
                    'exit_sentiment'        => $signal['exit_sentiment'],
                    'exit_oi_condition'     => $signal['exit_oi_condition'],
                    'exit_ce_oi_pct'        => $signal['exit_ce_oi_pct'],
                    'exit_pe_oi_pct'        => $signal['exit_pe_oi_pct'],
                    'exit_decision'         => $decision,
                    'exit_reason'           => $signal['exit_reason'],
                    'option_symbol'         => $optionDetails['symbol'],
                    'option_token'          => $optionDetails['token'],
                    'option_type'           => $optionType,
                    'strike_price'          => $optionDetails['strike'],
                    'exit_price'            => $optionDetails['ltp'],
                    'current_price'         => $optionDetails['ltp'],
                    'order_type'            => $config->order_type,
                    'product'               => $config->product,
                    'quantity'              => $quantity,
                    'is_order_placed'       => false,
                    'status'                => true,
                    'signal_detected_at'    => now(),
                ]);

                Log::info(sprintf(
                    "EXIT PLAN Order created: %s | SELL %s | Strike: %s | LTP: %.2f | Qty: %d",
                    $symbol, $optionDetails['symbol'],
                    $optionDetails['strike'], $optionDetails['ltp'], $quantity
                ));

                $created++;

            } catch (\Exception $e) {
                Log::error("EXIT PLAN processConfig {$symbol}: " . $e->getMessage());
                $errors++;
            }
        }

        Log::info("EXIT PLAN Config {$config->id} — Created: {$created} | Skipped: {$skipped} | Errors: {$errors}");
    }

    // =========================================================
    //  ORDER PLACEMENT
    // =========================================================

    private function placeOrder(ExitPlanOrder $order): void
    {
        try {
            Log::info("EXIT PLAN SELL Placing: {$order->option_symbol}");

            $broker = $order->broker;
            if (!$broker->hasValidToken()) {
                $this->saveFailedOrder($order, 'Invalid token');
                return;
            }

            $this->ensureKiteInstance($broker);

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)->first();
            if (!$instrument) {
                $this->saveFailedOrder($order, "Instrument not found for token {$order->option_token}");
                return;
            }

            $this->placeKiteOrder($order, $order->quantity, $instrument, $this->kiteInstances[$broker->id]);
            $order->update(['is_order_placed' => true, 'order_placed_at' => now()]);

            Log::info("EXIT PLAN ORDER Done: ID {$order->id} — {$order->option_symbol}");

        } catch (\Exception $e) {
            Log::error("EXIT PLAN ORDER {$order->option_symbol}: " . $e->getMessage());
            $this->saveFailedOrder($order, $e->getMessage());
        }
    }

    private function placeKiteOrder(ExitPlanOrder $order, int $quantity, $instrument, $kite): void
    {
        $price = null;
        if ($order->order_type === 'LIMIT') {
            $discount = ($order->exit_price * $order->config->disc_ltp) / 100;
            $raw      = $order->exit_price + $discount; // SELL slightly above LTP
            $price    = number_format(
                round($raw / $instrument->tick_size) * $instrument->tick_size,
                2, '.', ''
            );
        }

        $baseSymbol  = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $order->option_symbol);
        $freezeLimit = self::FREEZE_LIMITS[$baseSymbol] ?? null;

        if ($freezeLimit && $quantity > $freezeLimit) {
            $remaining = $quantity;
            while ($remaining > 0) {
                $lots = min($freezeLimit, $remaining);
                $this->executeSingleOrder($order, $lots, $price, $instrument, $kite);
                $remaining -= $lots;
                if ($remaining > 0) sleep(2);
            }
        } else {
            $this->executeSingleOrder($order, $quantity, $price, $instrument, $kite);
        }
    }

    private function executeSingleOrder(ExitPlanOrder $order, int $quantity, $price, $instrument, $kite): void
    {
        $params = [
            'exchange'         => 'NFO',
            'tradingsymbol'    => $order->option_symbol,
            'transaction_type' => 'SELL',
            'quantity'         => $quantity * $instrument->lot_size,
            'product'          => $order->product,
            'validity'         => 'DAY',
            'order_type'       => $order->order_type === 'MARKET' ? 'MARKET' : 'LIMIT',
        ];

        if ($order->order_type !== 'MARKET') {
            $params['price'] = $price;
        }

        $result = $kite->placeOrder('regular', $params);
        Log::info("EXIT PLAN SELL Placed! Kite ID: {$result->order_id} | {$order->option_symbol}");
        $this->saveToOrderBook($order, $result->order_id, $quantity, $price);
    }

    // =========================================================
    //  ATM OPTION LOOKUP
    // =========================================================

    private function getATMOption(
        BrokerApi $broker,
        string $baseSymbol,
        string $optionType,
        float $futurePrice,
        string $tradeDate
    ): ?array {
        try {
            $interval  = self::STRIKE_INTERVALS[$baseSymbol] ?? 20;
            $atmStrike = round($futurePrice / $interval) * $interval;

            $expiry = $this->resolveNearestExpiry($baseSymbol, $tradeDate, $optionType);

            if (!$expiry) {
                Log::warning("EXIT PLAN ATM {$baseSymbol}: no expiry >= {$tradeDate} type={$optionType}");
                return null;
            }

            // Try exact ATM strike first
            $option = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $atmStrike)
                ->whereDate('expiry', $expiry)
                ->first();

            // Fallback: nearest strike
            if (!$option) {
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', $expiry)
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                    ->orderBy('strike_diff')
                    ->first();
            }

            if (!$option) {
                Log::warning("EXIT PLAN ATM {$baseSymbol}: no option found type={$optionType} strike≈{$atmStrike} expiry={$expiry}");
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            Log::info("EXIT PLAN ATM {$baseSymbol}: {$option->trading_symbol} | strike={$option->strike} | LTP={$ltp}");

            return [
                'symbol' => $option->trading_symbol,
                'token'  => $option->instrument_token,
                'strike' => $option->strike,
                'ltp'    => $ltp,
                'expiry' => $option->expiry,
            ];

        } catch (\Exception $e) {
            Log::error("EXIT PLAN ATM {$baseSymbol}: " . $e->getMessage());
            return null;
        }
    }

    private function resolveNearestExpiry(string $baseSymbol, string $tradeDate, string $optionType): ?string
    {
        $isWeekly = ($baseSymbol === 'NIFTY');

        $expiries = ZerodhaInstrument::where('name', $baseSymbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', $optionType)
            ->whereDate('expiry', '>=', $tradeDate)
            ->orderBy('expiry', 'ASC')
            ->distinct()
            ->pluck('expiry')
            ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($expiries)) return null;

        if (!$isWeekly) {
            // Monthly only: last expiry per calendar month
            $byMonth = [];
            foreach ($expiries as $exp) {
                $byMonth[substr($exp, 0, 7)] = $exp;
            }
            $expiries = array_values($byMonth);
        }

        return $expiries[0] ?? null;
    }

    private function getOptionLTP(BrokerApi $broker, $instrumentToken, string $tradingSymbol): float
    {
        try {
            $this->ensureKiteInstance($broker);
            $kite     = $this->kiteInstances[$broker->id];
            $quoteKey = "NFO:{$tradingSymbol}";
            $quotes   = $kite->getQuote([$quoteKey]);

            if (isset($quotes->$quoteKey->last_price)) return (float) $quotes->$quoteKey->last_price;

            $arr = json_decode(json_encode($quotes), true);
            if (isset($arr[$quoteKey]['last_price'])) return (float) $arr[$quoteKey]['last_price'];

        } catch (\Exception $e) {
            Log::error("EXIT PLAN LTP {$tradingSymbol}: " . $e->getMessage());
        }
        return 0.0;
    }

    // =========================================================
    //  OI SIGNAL LOGIC  (identical to OIIVAutoController)
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  EXIT DECISION LOGIC
    // =========================================================

    private function determineExitDecision(
        string $origSentiment,
        string $exitSentiment,
        string $tradeAction
    ): array {
        if ($exitSentiment === 'NEUTRAL') {
            return ['MONITOR', 'Exit OI is NEUTRAL — unclear direction, monitor closely'];
        }

        if ($origSentiment === $exitSentiment) {
            $dir = $exitSentiment === 'BULLISH' ? '📈 BULLISH' : '📉 BEARISH';
            return ['HOLD', "Exit OI confirms same direction ({$dir}) — HOLD your {$tradeAction} position"];
        }

        $origDir = $origSentiment === 'BULLISH' ? '📈 BULLISH' : '📉 BEARISH';
        $exitDir = $exitSentiment === 'BULLISH' ? '📈 BULLISH' : '📉 BEARISH';
        return [
            'EXIT',
            "Signal REVERSED: original={$origDir} → exit={$exitDir} — EXIT {$tradeAction}",
        ];
    }

    // =========================================================
    //  TRADING DATE HELPERS
    // =========================================================

    private function getPreviousTradingDate(string $date): string
    {
        $prev     = Carbon::parse($date)->subDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) {
                return $prev->format('Y-m-d');
            }
            $prev->subDay();
            $attempts++;
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    // =========================================================
    //  ORDER BOOK HELPERS
    // =========================================================

    private function saveToOrderBook(ExitPlanOrder $order, string $orderId, int $quantity, $price): void
    {
        try {
            sleep(2);
            $kite         = $this->kiteInstances[$order->broker_api_id] ?? null;
            $orderHistory = $kite ? $kite->getOrderHistory($orderId) : [];
            $last         = end($orderHistory) ?: null;

            OrderBook::create([
                'user_id'          => $order->user_id,
                'broker_username'  => $order->broker->account_user_name ?? 'N/A',
                'order_id'         => $orderId,
                'status'           => $last->status ?? 'PENDING',
                'trading_symbol'   => $order->option_symbol,
                'order_type'       => $order->order_type,
                'transaction_type' => 'SELL',
                'product'          => $order->product,
                'price'            => $price ?? '-',
                'quantity'         => $quantity,
                'status_message'   => $last->status_message ?? 'Exit order placed',
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("EXIT PLAN ORDER_BOOK: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(ExitPlanOrder $order, string $error): void
    {
        try {
            OrderBook::create([
                'user_id'          => $order->user_id,
                'broker_username'  => $order->broker->account_user_name ?? 'N/A',
                'order_id'         => '-',
                'status'           => 'FAILED',
                'trading_symbol'   => $order->option_symbol,
                'order_type'       => $order->order_type,
                'transaction_type' => 'SELL',
                'product'          => $order->product,
                'price'            => '-',
                'quantity'         => $order->quantity,
                'status_message'   => substr($error, 0, 500),
                'order_datetime'   => now(),
            ]);
        } catch (\Exception $e) {
            Log::error("EXIT PLAN ORDER_BOOK Failed: " . $e->getMessage());
        }
    }

    private function ensureKiteInstance(BrokerApi $broker): void
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $this->kiteInstances[$broker->id] = new KiteConnect($broker->api_key);
            $this->kiteInstances[$broker->id]->setAccessToken($broker->access_token);
        }
    }
}