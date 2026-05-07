<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\FutContrarianConfig;
use App\Models\FutContrarianOrder;
use App\Models\FutContrarianOrderBook;
use App\Models\OptionOhlcData;
use App\Models\ZerodhaInstrument;
use App\Models\AngelApiInstrument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * FutContrarianTradingHelper
 *
 * Order placement for the FUT Contrarian OI analysis.
 *
 * LOGIC:
 *   1. Detect FUT direction (prev 15:00 close vs today 09:30 open)
 *      FUT UP   → BUY PE (contrarian)
 *      FUT DOWN → BUY CE (contrarian)
 *
 *   2. Check OI alignment:
 *      30min window: prev 15:15 vs today 09:45 OI
 *      1hr   window: prev 15:15 vs today 10:15 OI
 *      BUY CE expects BULLISH OI (CE unwinding + PE buildup)
 *      BUY PE expects BEARISH OI (CE buildup + PE unwinding)
 *
 *   3. Place order ONLY when aligned (signal matches OI):
 *      30min aligned → BUY at 10:00 candle open
 *      1hr   aligned → BUY at 10:30 candle open
 *
 *   4. Supports both Zerodha (KiteConnect) and Angel One (SmartAPI via HTTP)
 *      Broker type detected from broker_apis.client_type
 */
class FutContrarianTradingHelper
{
    private const INDEX_SYMBOLS = ['NIFTY','BANKNIFTY','FINNIFTY','MIDCPNIFTY','SENSEX','BANKEX'];

    private const FREEZE_LIMITS = [
        'NIFTY' => 18, 'BANKNIFTY' => 20, 'FINNIFTY' => 24, 'MIDCPNIFTY' => 24,
        'AXISBANK' => 30, 'ICICIBANK' => 30, 'SBIN' => 30, 'RELIANCE' => 30,
        'HDFCBANK' => 30, 'INFY' => 40, 'TCS' => 40, 'BAJFINANCE' => 30,
        'LT' => 40, 'TATASTEEL' => 30, 'HINDALCO' => 40, 'WIPRO' => 30,
    ];

    private const STRIKE_INTERVALS = [
        'NIFTY' => 50, 'BANKNIFTY' => 100, 'FINNIFTY' => 50, 'MIDCPNIFTY' => 25,
        'SENSEX' => 100, 'BANKEX' => 100,
        'AXISBANK' => 10, 'ICICIBANK' => 10, 'SBIN' => 10, 'HDFCBANK' => 10,
        'RELIANCE' => 20, 'INFY' => 20, 'TCS' => 50, 'BAJFINANCE' => 50,
        'LT' => 20, 'TATASTEEL' => 10, 'HINDALCO' => 10, 'WIPRO' => 10,
    ];

    private const ANGEL_MONTH_MAP = [
        '01' => 'JAN', '02' => 'FEB', '03' => 'MAR', '04' => 'APR',
        '05' => 'MAY', '06' => 'JUN', '07' => 'JUL', '08' => 'AUG',
        '09' => 'SEP', '10' => 'OCT', '11' => 'NOV', '12' => 'DEC',
    ];

    // ── Instance state ─────────────────────────────────────────────────────
    private array $kiteInstances   = [];
    private array $ltpCache        = [];
    private array $lastRequestTime = [];
    private int   $minMsBetween    = 350;

    // =========================================================
    //  PUBLIC ENTRY POINT
    // =========================================================

    /**
     * Process signals and place orders for all active configs.
     *
     * @param string|null $testDate  Override date (Y-m-d) for testing
     * @param string|null $window    'all' | '30min' | '1hr' — which window to process
     */
    public function run(?string $testDate = null, string $window = 'all'): void
    {
        $currentDate = $testDate ?? Carbon::now('Asia/Kolkata')->format('Y-m-d');
        $mode        = $testDate ? 'TEST' : 'LIVE';

        Log::info("=== FutContrarian: START {$mode} | Date:{$currentDate} | Window:{$window} ===");

        $configs = FutContrarianConfig::where('status', true)
            ->with('broker')
            ->get();

        if ($configs->isEmpty()) {
            Log::info('FutContrarian: No active configs found');
            return;
        }

        $prevDate = $this->getPreviousTradingDate($currentDate);
        Log::info("FutContrarian: {$configs->count()} configs | prevDate:{$prevDate}");

        // ── Build signal data for all symbols once ─────────────────────────
        $signals = $this->buildSignals($currentDate, $prevDate, $window);

        if (empty($signals)) {
            Log::warning("FutContrarian: No signals built for {$currentDate} window:{$window}");
            return;
        }

        Log::info("FutContrarian: Built " . count($signals) . " signal rows");

        // ── Process each config ────────────────────────────────────────────
        foreach ($configs as $config) {
            try {
                $broker = $config->broker;
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("FutContrarian Config {$config->id}: Invalid broker token");
                    continue;
                }

                $this->processConfig($config, $broker, $signals, $currentDate, $window);

            } catch (\Exception $e) {
                Log::error("FutContrarian Config {$config->id}: " . $e->getMessage());
            }
        }

        Log::info("=== FutContrarian: DONE ===");
    }

    // =========================================================
    //  BUILD SIGNALS FROM OHLC DATA
    // =========================================================

    /**
     * Returns array keyed by base_symbol, each entry containing:
     *   fut_direction, trade_action, option_type, best_strike, best_option_sym,
     *   best_instrument_token, best_open_price, current_expiry, lot_size,
     *   oi_30min_signal, oi_1hr_signal, aligned_30min, aligned_1hr,
     *   buy_price_30min (10:00 open), buy_price_1hr (10:30 open)
     */
    private function buildSignals(string $date, string $prevDate, string $window): array
    {
        // FUT candles at 09:30 — drives symbol list and direction
        $futCandles09 = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '09:30:00'")
            ->get()->keyBy('base_symbol');

        if ($futCandles09->isEmpty()) {
            Log::warning("FutContrarian: No FUT 09:30 candles for {$date}");
            return [];
        }

        $symbols = $futCandles09->keys()->toArray();

        // Prev day 15:00 FUT close (for direction)
        $prevFutClose = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->whereIn('base_symbol', $symbols)
            ->get()->keyBy('base_symbol');

        // OI candles — batch fetch all at once
        $prevOI1515  = OptionOhlcData::whereIn('instrument_type', ['CE','PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $symbols)->where('is_missing', 0)->get();

        $todayOI0945 = OptionOhlcData::whereIn('instrument_type', ['CE','PE'])
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '09:45:00'")
            ->whereIn('base_symbol', $symbols)->where('is_missing', 0)->get();

        $todayOI1015 = OptionOhlcData::whereIn('instrument_type', ['CE','PE'])
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '10:15:00'")
            ->whereIn('base_symbol', $symbols)->where('is_missing', 0)->get();

        $signals = [];

        foreach ($symbols as $symbol) {
            $fut09   = $futCandles09[$symbol];
            $futPrev = $prevFutClose[$symbol] ?? null;

            $todayOpen = (float) $fut09->open;
            $prevClose = $futPrev ? (float) $futPrev->close : 0;
            if ($todayOpen <= 0) continue;

            // ── FUT direction ──────────────────────────────────────────────
            $futChangePct = $prevClose > 0 ? (($todayOpen - $prevClose) / $prevClose) * 100 : 0;
            $futDir       = $futChangePct > 0 ? 'UP' : ($futChangePct < 0 ? 'DOWN' : 'FLAT');
            $action       = match($futDir) { 'UP' => 'BUY PE', 'DOWN' => 'BUY CE', default => 'WAIT' };
            if ($action === 'WAIT') continue;

            $optionType = $action === 'BUY CE' ? 'CE' : 'PE';
            $expectedOI = $action === 'BUY CE' ? 'BULLISH' : 'BEARISH';

            // ── Expiry resolution ──────────────────────────────────────────
            $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
            $prevExpiry    = $currentExpiry ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry) : null;

            // ── OI sums ────────────────────────────────────────────────────
            $flt = fn($col, $exp) => $col->where('base_symbol', $symbol)
                ->when($exp, fn($c) => $c->filter(fn($r) => substr($r->expiry_date ?? '', 0, 10) === $exp));

            $prevCE = (int) $flt($prevOI1515,  $prevExpiry)->where('instrument_type','CE')->sum('oi');
            $prevPE = (int) $flt($prevOI1515,  $prevExpiry)->where('instrument_type','PE')->sum('oi');
            $ce0945 = (int) $flt($todayOI0945, $currentExpiry)->where('instrument_type','CE')->sum('oi');
            $pe0945 = (int) $flt($todayOI0945, $currentExpiry)->where('instrument_type','PE')->sum('oi');
            $ce1015 = (int) $flt($todayOI1015, $currentExpiry)->where('instrument_type','CE')->sum('oi');
            $pe1015 = (int) $flt($todayOI1015, $currentExpiry)->where('instrument_type','PE')->sum('oi');

            $ce30Pct = $prevCE > 0 ? (($ce0945 - $prevCE) / $prevCE) * 100 : 0;
            $pe30Pct = $prevPE > 0 ? (($pe0945 - $prevPE) / $prevPE) * 100 : 0;
            $ce1hPct = $prevCE > 0 ? (($ce1015 - $prevCE) / $prevCE) * 100 : 0;
            $pe1hPct = $prevPE > 0 ? (($pe1015 - $prevPE) / $prevPE) * 100 : 0;

            $sig30 = $this->getOISignal($ce30Pct, $pe30Pct)['signal'];
            $sig1h = $this->getOISignal($ce1hPct, $pe1hPct)['signal'];

            $aligned30 = ($sig30 !== 'NEUTRAL' && $sig30 === $expectedOI);
            $aligned1h = ($sig1h !== 'NEUTRAL' && $sig1h === $expectedOI);

            // Always keep if FUT has a direction — config decides which window to use
            // The per-config window mode determines what is actually needed
            // (30min only, 1hr only, or both required)
            // Skip only if completely no alignment on either window
            if (!$aligned30 && !$aligned1h) continue;

            $lotSize = $this->getLotSize($symbol);

            // ── Best ATM option at 09:30 ───────────────────────────────────
            $bestOption = $this->getBestAtmOption($symbol, $date, $optionType, $todayOpen, $currentExpiry);
            if (!$bestOption) continue;

            $strike    = $bestOption->strike;
            $expiryStr = substr($bestOption->expiry_date ?? '', 0, 10);

            // ── Buy prices from intraday candles ───────────────────────────
            $buyPrice30 = null;
            $buyPrice1h = null;

            if ($aligned30 || $aligned1h) {
                $candles = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('trade_date', $date)
                    ->where('is_missing', 0)
                    ->when($expiryStr, fn($q) => $q->whereDate('expiry_date', $expiryStr))
                    ->whereRaw("TIME(interval_time) >= '10:00:00'")
                    ->orderBy('interval_time')
                    ->get(['open', 'interval_time']);

                if ($aligned30) {
                    $c1000 = $candles->first(fn($c) => substr($c->interval_time, 11, 5) === '10:00');
                    $buyPrice30 = $c1000 ? (float) $c1000->open : null;
                }

                if ($aligned1h) {
                    $c1030 = $candles->first(fn($c) => substr($c->interval_time, 11, 5) === '10:30');
                    $buyPrice1h = $c1030 ? (float) $c1030->open : null;
                }
            }

            $signals[$symbol] = [
                'symbol'              => $symbol,
                'trading_symbol'      => $fut09->trading_symbol ?? $symbol,
                'fut_prev_close'      => round($prevClose, 2),
                'fut_today_open'      => round($todayOpen, 2),
                'fut_change_pct'      => round($futChangePct, 4),
                'fut_direction'       => $futDir,
                'trade_action'        => $action,
                'option_type'         => $optionType,
                'best_strike'         => $strike,
                'option_symbol'       => $bestOption->trading_symbol ?? null,
                'option_token'        => (int) ($bestOption->instrument_token ?? 0),
                'current_expiry'      => $currentExpiry,
                'lot_size'            => $lotSize,
                'oi_30min_signal'     => $sig30,
                'oi_1hr_signal'       => $sig1h,
                'aligned_30min'       => $aligned30,
                'aligned_1hr'         => $aligned1h,
                'buy_price_30min'     => $buyPrice30,
                'buy_price_1hr'       => $buyPrice1h,
                'expected_oi'         => $expectedOI,
                'spot_price'          => round($todayOpen, 2),
            ];
        }

        return $signals;
    }

    // =========================================================
    //  PROCESS ONE CONFIG
    // =========================================================

    private function processConfig(
        FutContrarianConfig $config,
        BrokerApi           $broker,
        array               $signals,
        string              $date,
        string              $window
    ): void {
        $created = $skipped = $errors = 0;
        $brokerType = strtolower($broker->client_type ?? 'zerodha');

        // Boot broker connection
        if ($brokerType === 'zerodha') {
            $this->ensureKiteInstance($broker);
            // Prefetch LTPs for all aligned option symbols
            $optSymbols = collect($signals)
                ->filter(fn($s) => $s['option_symbol'])
                ->pluck('option_symbol')->toArray();
            $this->prefetchLTPs($broker, $optSymbols);
        }

        foreach ($signals as $symbol => $signalData) {
            try {
                if (!$config->isSymbolAllowed($symbol)) {
                    Log::debug("FutContrarian Config {$config->id}: {$symbol} not in allowed_symbols");
                    $skipped++;
                    continue;
                }

                $qty = $config->getQuantityForSymbol($symbol, $signalData['option_type'] ?? 'CE');
                if ($qty <= 0) {
                    Log::info("FutContrarian Config {$config->id}: {$symbol} qty=0 skip");
                    $skipped++;
                    continue;
                }

                // ── Duplicate guard ────────────────────────────────────────
                $existing = FutContrarianOrder::where('config_id', $config->id)
                    ->where('base_symbol', $symbol)
                    ->where('signal_date', $date)
                    ->where('status', true)
                    ->first();

                // Get LTP for entry price
                $ltp = 0;
                if ($brokerType === 'zerodha' && !empty($signalData['option_symbol'])) {
                    $ltp = $this->getLTP($broker, $signalData['option_token'], $signalData['option_symbol']);
                }
                // For Angel, LTP from DB (buy price from candle data)
                if ($ltp <= 0) {
                    $ltp = $signalData['buy_price_30min'] ?? $signalData['buy_price_1hr'] ?? 0;
                }

                // ── Create or reuse the signal row ─────────────────────────
                if (!$existing) {
                    $order = FutContrarianOrder::create([
                        'user_id'               => $config->user_id,
                        'config_id'             => $config->id,
                        'broker_api_id'         => $broker->id,
                        'signal_date'           => $date,
                        'base_symbol'           => $symbol,
                        'trading_symbol'        => $signalData['trading_symbol'],
                        'fut_prev_close'        => $signalData['fut_prev_close'],
                        'fut_today_open'        => $signalData['fut_today_open'],
                        'fut_change_pct'        => $signalData['fut_change_pct'],
                        'fut_direction'         => $signalData['fut_direction'],
                        'trade_action'          => $signalData['trade_action'],
                        'option_type'           => $signalData['option_type'],
                        'best_strike'           => $signalData['best_strike'],
                        'option_symbol'         => $signalData['option_symbol'],
                        'option_instrument_token' => $signalData['option_token'],
                        'entry_price'           => $ltp,
                        'lot_size'              => $signalData['lot_size'],
                        'quantity'              => $qty,
                        'expiry_date'           => $signalData['current_expiry'],
                        'oi_30min_signal'       => $signalData['oi_30min_signal'],
                        'oi_1hr_signal'         => $signalData['oi_1hr_signal'],
                        'alignment_30min'       => $signalData['aligned_30min'] ? 'MATCH' : 'NO',
                        'alignment_1hr'         => $signalData['aligned_1hr']   ? 'MATCH' : 'NO',
                        'signal_detected_at'    => now(),
                        'status'                => true,
                    ]);
                } else {
                    $order = $existing;
                }

                // ── Determine if this symbol qualifies under config window mode ───
                //
                // trade_30min=true, trade_1hr=false  → 30min ONLY:
                //   FUT + 30min OI must match → buy at 10:00 open
                //
                // trade_30min=false, trade_1hr=true  → 1hr ONLY:
                //   FUT + 1hr OI must match  → buy at 10:30 open
                //
                // trade_30min=true, trade_1hr=true   → BOTH required:
                //   FUT + 30min OI + 1hr OI ALL must match
                //   → buy 10:00 open (30min trade) AND buy 10:30 open (1hr trade)
                //
                $placed = false;

                $only30  = $config->trade_30min && !$config->trade_1hr;
                $only1h  = !$config->trade_30min && $config->trade_1hr;
                $bothReq = $config->trade_30min && $config->trade_1hr;

                if ($only30) {
                    // Window mode: 30min — need FUT + 30min OI aligned
                    if (!in_array($window, ['all', '30min'])) goto DONE;
                    if (!$signalData['aligned_30min']) {
                        Log::info("FutContrarian {$symbol} 30min-only: 30min OI not aligned — skip");
                        goto DONE;
                    }
                    if (!$order->traded_30min) {
                        $buyPrice = $signalData['buy_price_30min'];
                        if ($buyPrice && $buyPrice > 0) {
                            $this->placeOrderForWindow($order, $config, $broker, $brokerType, $signalData, $buyPrice, '30min', $qty);
                            $order->update(['traded_30min' => true]);
                            $placed = true;
                        } else {
                            Log::warning("FutContrarian {$symbol} 30min: missing 10:00 candle open price");
                        }
                    }

                } elseif ($only1h) {
                    // Window mode: 1hr — need FUT + 1hr OI aligned
                    if (!in_array($window, ['all', '1hr'])) goto DONE;
                    if (!$signalData['aligned_1hr']) {
                        Log::info("FutContrarian {$symbol} 1hr-only: 1hr OI not aligned — skip");
                        goto DONE;
                    }
                    if (!$order->traded_1hr) {
                        $buyPrice = $signalData['buy_price_1hr'];
                        if ($buyPrice && $buyPrice > 0) {
                            $this->placeOrderForWindow($order, $config, $broker, $brokerType, $signalData, $buyPrice, '1hr', $qty);
                            $order->update(['traded_1hr' => true]);
                            $placed = true;
                        } else {
                            Log::warning("FutContrarian {$symbol} 1hr: missing 10:30 candle open price");
                        }
                    }

                } elseif ($bothReq) {
                    // Window mode: BOTH — FUT + 30min + 1hr ALL must align
                    // Only execute the window currently being processed by the command
                    if (!$signalData['aligned_30min'] || !$signalData['aligned_1hr']) {
                        Log::info("FutContrarian {$symbol} both-required: 30min={$signalData['oi_30min_signal']} 1hr={$signalData['oi_1hr_signal']} — not fully aligned, skip");
                        goto DONE;
                    }

                    if (in_array($window, ['all', '30min']) && !$order->traded_30min) {
                        $buyPrice = $signalData['buy_price_30min'];
                        if ($buyPrice && $buyPrice > 0) {
                            $this->placeOrderForWindow($order, $config, $broker, $brokerType, $signalData, $buyPrice, '30min', $qty);
                            $order->update(['traded_30min' => true]);
                            $placed = true;
                        } else {
                            Log::warning("FutContrarian {$symbol} both/30min: missing 10:00 candle open price");
                        }
                    }

                    if (in_array($window, ['all', '1hr']) && !$order->traded_1hr) {
                        $buyPrice = $signalData['buy_price_1hr'];
                        if ($buyPrice && $buyPrice > 0) {
                            $this->placeOrderForWindow($order, $config, $broker, $brokerType, $signalData, $buyPrice, '1hr', $qty);
                            $order->update(['traded_1hr' => true]);
                            $placed = true;
                        } else {
                            Log::warning("FutContrarian {$symbol} both/1hr: missing 10:30 candle open price");
                        }
                    }
                }

                DONE:
                if ($placed) {
                    $order->update(['is_order_placed' => true, 'order_placed_at' => now()]);
                    $created++;
                } else {
                    $skipped++;
                }

            } catch (\Exception $e) {
                Log::error("FutContrarian Config {$config->id} {$symbol}: " . $e->getMessage());
                $errors++;
            }
        }

        Log::info("FutContrarian Config {$config->id} — Created:{$created} Skipped:{$skipped} Errors:{$errors}");
    }

    // =========================================================
    //  PLACE ORDER FOR ONE WINDOW (30min or 1hr)
    // =========================================================

    private function placeOrderForWindow(
        FutContrarianOrder  $order,
        FutContrarianConfig $config,
        BrokerApi           $broker,
        string              $brokerType,
        array               $signalData,
        float               $buyPrice,
        string              $window,   // '30min' or '1hr'
        int                 $lots
    ): void {
        $symbol       = $order->base_symbol;
        $optionSymbol = $order->option_symbol;
        $optionToken  = (int) $order->option_instrument_token;
        $lotSize      = $order->lot_size;
        $exchange     = $this->getExchange($symbol);

        $freezeLimit   = self::FREEZE_LIMITS[$symbol] ?? null;
        $chunkLotsMax  = $freezeLimit ?? $lots;
        $chunkTotal    = $freezeLimit ? (int) ceil($lots / $chunkLotsMax) : 1;

        // Compute LIMIT price
        $placedPrice = 0;
        if ($config->order_type === 'LIMIT') {
            $discPct     = (float) $config->disc_ltp;
            $raw         = $buyPrice - ($buyPrice * $discPct / 100);
            $tick        = 0.05;
            $placedPrice = round(round($raw / $tick) * $tick, 2);
        }

        $remaining   = $lots;
        $chunkNumber = 0;

        while ($remaining > 0) {
            $chunkLots  = min($chunkLotsMax, $remaining);
            $chunkUnits = $chunkLots * $lotSize;
            $chunkNumber++;

            $zerodhaOrderId = null;
            $statusMsg      = null;
            $placed         = false;

            if ($brokerType === 'zerodha') {
                [$placed, $zerodhaOrderId, $statusMsg] = $this->placeZerodhaOrder(
                    $broker, $optionSymbol, $exchange,
                    $chunkUnits, $config->order_type,
                    $config->product, $placedPrice ?: $buyPrice
                );
            } elseif (in_array($brokerType, ['angel', 'angelone'])) {
                [$placed, $zerodhaOrderId, $statusMsg] = $this->placeAngelOrder(
                    $broker, $optionSymbol, $optionToken,
                    $chunkUnits, $config->order_type,
                    $config->product, $placedPrice ?: $buyPrice,
                    $symbol
                );
            } else {
                $statusMsg = "Unknown broker type: {$brokerType}";
                Log::error("FutContrarian: {$statusMsg}");
            }

            // ── Write to order book ────────────────────────────────────────
            FutContrarianOrderBook::create([
                'user_id'              => $order->user_id,
                'broker_api_id'        => $broker->id,
                'fc_order_id'          => $order->id,
                'zerodha_order_id'     => $zerodhaOrderId,
                'trading_symbol'       => $optionSymbol,
                'base_symbol'          => $symbol,
                'exchange'             => $exchange,
                'option_type'          => $order->option_type,
                'strike_price'         => $order->best_strike,
                'expiry_date'          => $order->expiry_date,
                'instrument_token'     => $optionToken ?: null,
                'signal_date'          => $order->signal_date,
                'signal_window'        => $window,
                'oi_signal'            => $window === '30min' ? $signalData['oi_30min_signal'] : $signalData['oi_1hr_signal'],
                'fut_direction'        => $signalData['fut_direction'],
                'sentiment'            => $signalData['expected_oi'],
                'spot_price_at_signal' => $signalData['spot_price'],
                'transaction_type'     => 'BUY',
                'order_type'           => $config->order_type,
                'product'              => $config->product,
                'validity'             => 'DAY',
                'quantity'             => $chunkLots,
                'quantity_units'       => $chunkUnits,
                'lot_size'             => $lotSize,
                'trigger_price'        => $buyPrice,
                'placed_price'         => $placedPrice ?: $buyPrice,
                'filled_quantity'      => 0,
                'status'               => $placed ? FutContrarianOrderBook::STATUS_OPEN : FutContrarianOrderBook::STATUS_REJECTED,
                'status_message'       => $placed ? null : $statusMsg,
                'internal_status'      => $placed ? FutContrarianOrderBook::INT_PLACED : FutContrarianOrderBook::INT_FAILED,
                'lot_chunk_number'     => $chunkNumber,
                'lot_chunk_total'      => $chunkTotal,
                'broker_type'          => $brokerType,
                'signal_detected_at'   => $order->signal_detected_at,
                'placed_at'            => $placed ? now() : null,
                'last_synced_at'       => now(),
            ]);

            Log::info(sprintf(
                "FutContrarian %s | %s | %s | chunk %d/%d | units=%d | price=%.2f | placed=%s | zerodha_id=%s",
                $symbol, $window, $optionSymbol, $chunkNumber, $chunkTotal,
                $chunkUnits, $placedPrice ?: $buyPrice,
                $placed ? 'YES' : 'NO', $zerodhaOrderId ?? 'null'
            ));

            $remaining -= $chunkLots;
            if ($remaining > 0) sleep(1);
        }
    }

    // =========================================================
    //  ZERODHA ORDER PLACEMENT
    // =========================================================

    private function placeZerodhaOrder(
        BrokerApi $broker,
        string    $optionSymbol,
        string    $exchange,
        int       $units,
        string    $orderType,
        string    $product,
        float     $price
    ): array {
        $maxRetries = 3;

        $params = [
            'exchange'         => $exchange,
            'tradingsymbol'    => $optionSymbol,
            'transaction_type' => 'BUY',
            'quantity'         => $units,
            'product'          => $product,
            'order_type'       => $orderType === 'MARKET' ? 'MARKET' : 'LIMIT',
            'validity'         => 'DAY',
        ];
        if ($orderType !== 'MARKET') {
            $params['price'] = number_format($price, 2, '.', '');
        }

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->throttle($broker->id);
                $kite   = $this->kiteInstances[$broker->id];
                $result = $kite->placeOrder('regular', $params);
                $oid    = $result->order_id ?? null;
                Log::info("FutContrarian Zerodha order placed: {$optionSymbol} | order_id:{$oid}");
                return [true, $oid, null];
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                if ($this->isRateLimitError($e) && $attempt < $maxRetries) {
                    $wait = (int) pow(2, $attempt);
                    Log::warning("FutContrarian Zerodha rate-limit retry {$attempt} in {$wait}s");
                    sleep($wait);
                } else {
                    Log::error("FutContrarian Zerodha order failed (attempt {$attempt}): {$msg}");
                    return [false, null, $msg];
                }
            }
        }

        return [false, null, 'Max retries exceeded'];
    }

    // =========================================================
    //  ANGEL ONE ORDER PLACEMENT
    // =========================================================

    private function placeAngelOrder(
        BrokerApi $broker,
        string    $optionSymbol,
        int       $instrumentToken,
        int       $units,
        string    $orderType,
        string    $product,
        float     $price,
        string    $baseSymbol
    ): array {
        try {
            $jwtToken  = $this->getAngelJwt($broker);
            $tick      = 0.05;
            $roundedPx = number_format(round($price / $tick) * $tick, 2, '.', '');

            // Resolve Angel token + symbol from angel_api_instruments
            $zerodhaRow  = ZerodhaInstrument::where('instrument_token', $instrumentToken)->first();
            $angelRow    = null;
            $angelSymbol = $optionSymbol;
            $angelToken  = '';

            if ($zerodhaRow) {
                $angelRow = AngelApiInstrument::where('token', (string) $zerodhaRow->exchange_token)->first();
            }

            if ($angelRow) {
                $angelSymbol = $angelRow->symbol_name;
                $angelToken  = (string) $angelRow->token;
            } else {
                $angelSymbol = $this->toAngelSymbol($optionSymbol);
                $fb = AngelApiInstrument::where('symbol_name', $angelSymbol)->first();
                if ($fb) { $angelToken = (string) $fb->token; }
                Log::warning("FutContrarian Angel: fallback symbol lookup {$optionSymbol} → {$angelSymbol}");
            }

            if (empty($angelToken)) {
                return [false, null, "Angel: instrument not found for {$optionSymbol}"];
            }

            $payload = [
                'variety'         => 'NORMAL',
                'tradingsymbol'   => $angelSymbol,
                'symboltoken'     => $angelToken,
                'transactiontype' => 'BUY',
                'exchange'        => 'NFO',
                'ordertype'       => $orderType === 'MARKET' ? 'MARKET' : 'LIMIT',
                'producttype'     => $product === 'MIS' ? 'INTRADAY' : 'CARRYFORWARD',
                'duration'        => 'DAY',
                'quantity'        => (string) $units,
                'price'           => $orderType === 'LIMIT' ? $roundedPx : '0',
                'squareoff'       => '0',
                'stoploss'        => '0',
            ];

            $apiKey = $broker->api_key ?? env('ANGEL_API_KEY');
            $headers = [
                'X-UserType: USER',
                'X-SourceID: WEB',
                'X-PrivateKey: ' . $apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $jwtToken,
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://apiconnect.angelone.in/rest/secure/angelbroking/order/v1/placeOrder',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => $headers,
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $data = json_decode($response, true);
            if ($httpCode !== 200 || empty($data['data']['orderid'])) {
                $msg = $data['message'] ?? "HTTP {$httpCode}: " . substr($response, 0, 200);
                Log::error("FutContrarian Angel order failed: {$msg}");
                return [false, null, $msg];
            }

            $orderId = $data['data']['orderid'];
            Log::info("FutContrarian Angel order placed: {$angelSymbol} | order_id:{$orderId}");
            return [true, $orderId, null];

        } catch (\Exception $e) {
            Log::error("FutContrarian Angel exception: " . $e->getMessage());
            return [false, null, $e->getMessage()];
        }
    }

    // =========================================================
    //  ANGEL JWT  (uses broker table credentials if set, else .env)
    // =========================================================

    private array $angelJwtCache = [];

    private function getAngelJwt(BrokerApi $broker): string
    {
        if (isset($this->angelJwtCache[$broker->id])) {
            return $this->angelJwtCache[$broker->id];
        }

        // Prefer DB-stored credentials, fall back to .env
        $clientCode = $broker->account_user_name ?? env('ANGEL_CLIENT_CODE');
        $pin        = $broker->security_pin       ?? env('ANGEL_PIN');
        $apiKey     = $broker->api_key            ?? env('ANGEL_API_KEY');
        $totpSecret = $broker->totp               ?? env('ANGEL_TOTP_SECRET');
        $localIp    = env('ANGEL_CLIENT_LOCAL_IP',  '192.168.1.1');
        $publicIp   = env('ANGEL_CLIENT_PUBLIC_IP', '1.1.1.1');
        $mac        = env('ANGEL_MAC_ADDRESS',       '00-00-00-00-00-00');

        require_once app_path('Libraries/vendor/autoload.php');
        $totp      = \OTPHP\TOTP::create($totpSecret);
        $totpToken = $totp->now();

        $payload = ['clientcode' => $clientCode, 'password' => $pin, 'totp' => $totpToken];
        $headers = [
            'X-UserType: USER', 'X-SourceID: WEB',
            'X-PrivateKey: ' . $apiKey,
            'X-ClientLocalIP: ' . $localIp,
            'X-ClientPublicIP: ' . $publicIp,
            'X-MACAddress: ' . $mac,
            'Content-Type: application/json', 'Accept: application/json',
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response, true);
        if (empty($data['data']['jwtToken'])) {
            throw new \Exception("FutContrarian Angel login failed for broker {$broker->id}: " . json_encode($data));
        }

        $this->angelJwtCache[$broker->id] = $data['data']['jwtToken'];
        Log::info("FutContrarian: Angel JWT obtained for broker {$broker->id} ({$clientCode})");
        return $this->angelJwtCache[$broker->id];
    }

    // =========================================================
    //  ATM OPTION FINDER
    // =========================================================

    private function getBestAtmOption(string $symbol, string $date, string $optionType, float $spot, ?string $expiry): ?object
    {
        $rows = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $date)
            ->whereIn('strike_position', ['ATM', 'ATM+1', 'ATM-1'])
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) = '09:30:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->get();

        if ($rows->isEmpty()) {
            $rows = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->whereDate('trade_date', $date)
                ->where('is_missing', 0)->whereNotNull('strike')
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
                ->orderByRaw('ABS(strike - ?)', [$spot])->limit(3)->get();
        }

        if ($rows->isEmpty()) return null;
        return $rows->sortByDesc(fn($r) => (int)($r->oi ?? 0))->first();
    }

    // =========================================================
    //  LTP HELPERS
    // =========================================================

    private function prefetchLTPs(BrokerApi $broker, array $symbols): void
    {
        if (empty($symbols)) return;
        $this->ensureKiteInstance($broker);
        $kite   = $this->kiteInstances[$broker->id];
        $chunks = array_chunk(array_unique(array_filter($symbols)), 500);

        foreach ($chunks as $chunk) {
            try {
                $this->throttle($broker->id);
                $keys   = array_map(fn($s) => $this->getExchangeFromSymbol($s) . ':' . $s, $chunk);
                $quotes = $kite->getQuote($keys);
                $arr    = json_decode(json_encode($quotes), true);
                foreach ($arr as $key => $q) {
                    $sym = preg_replace('/^[A-Z]+:/', '', $key);
                    $this->ltpCache[$broker->id][$sym] = (float) ($q['last_price'] ?? 0);
                }
            } catch (\Exception $e) {
                Log::warning("FutContrarian prefetchLTPs: " . $e->getMessage());
            }
        }
    }

    private function getLTP(BrokerApi $broker, int $token, string $symbol): float
    {
        if (isset($this->ltpCache[$broker->id][$symbol])) {
            return $this->ltpCache[$broker->id][$symbol];
        }
        try {
            $this->ensureKiteInstance($broker);
            $this->throttle($broker->id);
            $exch   = $this->getExchangeFromSymbol($symbol);
            $quotes = $this->kiteInstances[$broker->id]->getQuote(["{$exch}:{$symbol}"]);
            $arr    = json_decode(json_encode($quotes), true);
            $ltp    = (float) ($arr["{$exch}:{$symbol}"]['last_price'] ?? 0);
            $this->ltpCache[$broker->id][$symbol] = $ltp;
            return $ltp;
        } catch (\Exception $e) {
            Log::warning("FutContrarian getLTP {$symbol}: " . $e->getMessage());
            return 0;
        }
    }

    // =========================================================
    //  OI SIGNAL
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;
        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE↑ PE↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE↓ PE↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'condition' => 'Both↑ CE>PE']
            : ['signal' => 'BULLISH', 'condition' => 'Both↑ PE>CE'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'condition' => 'Both↓ CE<PE']
            : ['signal' => 'BEARISH', 'condition' => 'Both↓ PE<CE'];
        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  EXPIRY HELPERS
    // =========================================================

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));

        if (!$expiry) {
            $expiry = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE','PE'])->whereNotNull('expiry_date')
                ->whereDate('trade_date', $date)->orderByDesc('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
        }

        if (!$expiry) return null;
        $expiry = substr((string)$expiry, 0, 10);

        // Expiry day shift
        if ($expiry === $date) {
            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE','PE'])->whereNotNull('expiry_date')
                ->whereDate('trade_date', $date)->whereDate('expiry_date', '>', $expiry)
                ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));
            if ($next) return substr((string)$next, 0, 10);
        }

        return $expiry;
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)->where('is_missing', 0)->exists();
        if ($exists) return $currentExpiry;
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')->where('is_missing', 0)->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  LOT SIZE
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $defaults = ['NIFTY' => 25, 'BANKNIFTY' => 15, 'FINNIFTY' => 25,
                     'MIDCPNIFTY' => 50, 'SENSEX' => 10, 'BANKEX' => 15];
        $lot = DB::table('zerodha_instruments')->where('name', $symbol)
            ->where('exchange', 'NFO')->whereIn('instrument_type', ['CE','PE'])->value('lot_size');
        return $lot ? (int)$lot : ($defaults[$symbol] ?? 1);
    }

    // =========================================================
    //  ANGEL SYMBOL CONVERSION (fallback)
    // =========================================================

    private function toAngelSymbol(string $zerodhaSymbol): string
    {
        if (preg_match('/^([A-Z0-9&]+)(\d{2})(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)(\d+)(CE|PE)$/i', $zerodhaSymbol)) {
            return strtoupper($zerodhaSymbol);
        }
        if (preg_match('/^([A-Z0-9&]+?)(\d{2})([1-9])(\d{2})(\d+)(CE|PE)$/i', $zerodhaSymbol, $m)) {
            $mm = str_pad($m[3], 2, '0', STR_PAD_LEFT);
            $mon = self::ANGEL_MONTH_MAP[$mm] ?? null;
            if ($mon) return strtoupper("{$m[1]}{$m[2]}{$mon}{$m[5]}{$m[6]}");
        }
        if (preg_match('/^([A-Z0-9&]+?)(\d{2})(\d{2})(\d{2})(\d+)(CE|PE)$/i', $zerodhaSymbol, $m)) {
            $mon = self::ANGEL_MONTH_MAP[$m[3]] ?? null;
            if ($mon) return strtoupper("{$m[1]}{$m[2]}{$mon}{$m[5]}{$m[6]}");
        }
        return strtoupper($zerodhaSymbol);
    }

    // =========================================================
    //  MISC HELPERS
    // =========================================================

    private function getExchange(string $symbol): string
    {
        return in_array(strtoupper($symbol), ['SENSEX','BANKEX']) ? 'BFO' : 'NFO';
    }

    private function getExchangeFromSymbol(string $tradingSymbol): string
    {
        foreach (['SENSEX','BANKEX'] as $bse) {
            if (str_starts_with(strtoupper($tradingSymbol), $bse)) return 'BFO';
        }
        return 'NFO';
    }

    private function ensureKiteInstance(BrokerApi $broker): void
    {
        if (!isset($this->kiteInstances[$broker->id])) {
            $kite = new KiteConnect($broker->api_key);
            $kite->setAccessToken($broker->access_token);
            $this->kiteInstances[$broker->id] = $kite;
        }
    }

    private function throttle(int $brokerId): void
    {
        if (isset($this->lastRequestTime[$brokerId])) {
            $elapsed = (int) ((microtime(true) - $this->lastRequestTime[$brokerId]) * 1000);
            if ($elapsed < $this->minMsBetween) {
                usleep(($this->minMsBetween - $elapsed) * 1000);
            }
        }
        $this->lastRequestTime[$brokerId] = microtime(true);
    }

    private function isRateLimitError(\Exception $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'rate') || str_contains($msg, '429') || str_contains($msg, 'too many');
    }

    private function getPreviousTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !DB::table('market_holidays')
                ->where('market_name','NSE')->where('holiday_date', $d->format('Y-m-d'))->exists()) {
                return $d->format('Y-m-d');
            }
            $d->subDay();
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }
}