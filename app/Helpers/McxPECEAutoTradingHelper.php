<?php

namespace App\Helpers;

use App\Models\BrokerApi;
use App\Models\McxOiivAutoConfig;
use App\Models\McxOiivAutoOrder;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KiteConnect\KiteConnect;
use Carbon\Carbon;

/**
 * McxPECEAutoTradingHelper
 *
 * MCX equivalent of PECEAutoTradingHelper.
 *
 * KEY DIFFERENCES from NFO:
 *   - Data table  : mcx_ohlc_data           (not option_ohlc_data)
 *   - Config table: mcx_oiiv_auto_configs    (not oiiv_auto_configs)
 *   - Order table : mcx_oiiv_auto_orders     (not oiiv_auto_orders)
 *   - Exchange    : MCX                      (not NFO)
 *   - EOD signal  : 23:00 candle            (not 15:00)
 *   - Prev ref    : 23:00 candle of prev MCX trading day
 *   - Trading days: Mon–Sat (skip Sunday only — not full weekend)
 *   - FUT expiry  : separate from option expiry (critical MCX quirk)
 *   - Strike      : from mcx_symbols.strike_interval
 *   - Lot size    : from mcx_symbols.lot_size or zerodha_instruments(MCX FUT)
 */
class McxPECEAutoTradingHelper
{
    // MCX EOD candle time
    const EOD_HOUR   = 23;
    const EOD_MINUTE = 0;   // 23:00

    // Default strike intervals per commodity (used if mcx_symbols row not found)
    const MCX_STRIKE_INTERVALS = [
        'CRUDEOIL'   => 50,
        'GOLD'       => 100,
        'GOLDM'      => 100,
        'SILVER'     => 500,
        'SILVERM'    => 100,
        'SILVERMIC'  => 100,
        'COPPER'     => 5,
        'ZINC'       => 2.5,
        'NICKEL'     => 10,
        'LEAD'       => 2,
        'ALUMINIUM'  => 2,
        'NATURALGAS' => 5,
        'NATGASMINI' => 5,
        'CRUDEOILM'  => 50,
        'COTTON'     => 50,
    ];

    // MCX freeze qty limits per lot
    const MCX_FREEZE_LIMITS = [
        'CRUDEOIL'   => 300,
        'GOLD'       => 10,
        'GOLDM'      => 100,
        'SILVER'     => 30,
        'SILVERM'    => 10,
        'SILVERMIC'  => 10,
        'COPPER'     => 50,
        'ZINC'       => 600,
        'NICKEL'     => 300,
        'LEAD'       => 600,
        'ALUMINIUM'  => 600,
        'NATURALGAS' => 1250,
        'NATGASMINI' => 4000,
        'CRUDEOILM'  => 3000,
    ];

    private array $kiteInstances = [];

    // =========================================================
    //  PUBLIC ENTRY POINTS
    // =========================================================

    public function processSignals(?string $testDate = null): void
    {
        try {
            Log::info('=== MCX EOD: Starting CE/PE OI Auto Trading Signal Detection ===');

            $processingDate = $testDate
                ? Carbon::parse($testDate . ' 23:05:00', 'Asia/Kolkata')
                : Carbon::now('Asia/Kolkata');

            $mode        = $testDate ? 'TEST' : 'LIVE';
            $currentDate = $processingDate->format('Y-m-d');

            Log::info("{$mode} MODE | Time: " . $processingDate->format('Y-m-d H:i:s'));

            $configs = McxOiivAutoConfig::where('status', true)->get();

            if ($configs->isEmpty()) {
                Log::info('MCX EOD: No active configurations found');
                return;
            }

            Log::info("MCX EOD: {$configs->count()} active config(s) | Date: {$currentDate}");

            $optionSeries = $this->resolveMcxOptionSeries($currentDate);
            if (!$optionSeries) {
                Log::warning("MCX EOD: Could not resolve option series for {$currentDate} — abort");
                return;
            }

            $prevDate = $this->getPreviousMcxTradingDate($currentDate);

            Log::info("MCX EOD | OptionSeries: {$optionSeries} | PrevDate: {$prevDate}");

            $aggregatedSignals = $this->aggregateSignalsFromOhlc($currentDate, $prevDate, $optionSeries);

            if (empty($aggregatedSignals)) {
                Log::warning("MCX EOD: No signal data for {$currentDate}");
                return;
            }

            $aligned = count(array_filter($aggregatedSignals, fn($s) => $s['is_aligned']));
            Log::info("MCX EOD: {$aligned} BULLISH/BEARISH | " . (count($aggregatedSignals) - $aligned) . " NEUTRAL/WAIT");

            foreach ($configs as $config) {
                $broker = $config->broker;
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("MCX Config {$config->id}: invalid token — skip");
                    continue;
                }
                $this->ensureKiteInstance($broker);
                $this->processConfigSignals(
                    $config, $aggregatedSignals, $broker,
                    $currentDate, $processingDate, $optionSeries
                );
            }

            Log::info('=== MCX EOD: Signal Detection Completed ===');

        } catch (\Throwable $e) {
            Log::error('MCX EOD processSignals: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }

    public function placeOrders(?string $testDate = null): void
    {
        try {
            Log::info('=== MCX EOD: Starting Order Placement ===');

            $pendingOrders = McxOiivAutoOrder::where('is_order_placed', false)
                ->where('status', true)
                ->whereHas('config', fn($q) => $q->where('status', true))
                ->with(['config', 'broker'])
                ->get();

            if ($pendingOrders->isEmpty()) {
                Log::info('MCX EOD: No pending orders');
                return;
            }

            Log::info("MCX EOD: {$pendingOrders->count()} pending orders");

            foreach ($pendingOrders->groupBy('broker_api_id') as $brokerId => $orders) {
                $broker = BrokerApi::find($brokerId);
                if (!$broker || !$broker->hasValidToken()) {
                    Log::warning("MCX Broker {$brokerId}: invalid token — skip");
                    continue;
                }
                $this->ensureKiteInstance($broker);
                foreach ($orders as $order) {
                    $this->placeOrder($order);
                }
            }

            Log::info('=== MCX EOD: Order Placement Completed ===');

        } catch (\Throwable $e) {
            Log::error('MCX EOD placeOrders: ' . $e->getMessage());
        }
    }

    // =========================================================
    //  MCX OPTION SERIES RESOLUTION
    //  MCX FUT expiry != option expiry — resolve options separately
    // =========================================================

    private function resolveMcxOptionSeries(string $currentDate): ?string
    {
        $expiry = ZerodhaInstrument::where('exchange', 'MCX')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry', '>=', $currentDate)
            ->orderBy('expiry', 'ASC')
            ->value('expiry');

        if (!$expiry) {
            $expiry = ZerodhaInstrument::where('exchange', 'MCX')
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->orderBy('expiry', 'DESC')
                ->value('expiry');
        }

        $series = $expiry
            ? (is_string($expiry) ? substr($expiry, 0, 10) : Carbon::parse($expiry)->toDateString())
            : null;

        Log::info("resolveMcxOptionSeries({$currentDate}) → {$series}");
        return $series;
    }

    // =========================================================
    //  AGGREGATE SIGNALS FROM mcx_ohlc_data
    // =========================================================

    private function aggregateSignalsFromOhlc(
        string $currentDate,
        string $prevDate,
        string $optionSeries
    ): array {
        $eodTime = sprintf('%02d:%02d:00', self::EOD_HOUR, self::EOD_MINUTE);

        // Today 23:00 candles
        $todayCandles = DB::table('mcx_ohlc_data')
            ->whereDate('trade_date', $currentDate)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) = ?", [$eodTime])
            ->where('is_missing', 0)
            ->get();

        // Fallback: 22:45+
        if ($todayCandles->isEmpty()) {
            Log::warning("MCX EOD: No 23:00 candles for {$currentDate} — fallback 22:45+");
            $todayCandles = DB::table('mcx_ohlc_data')
                ->whereDate('trade_date', $currentDate)
                ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
                ->whereRaw("TIME(interval_time) >= '22:45:00'")
                ->where('is_missing', 0)
                ->orderByRaw("TIME(interval_time) DESC")
                ->get();
        }

        if ($todayCandles->isEmpty()) {
            Log::warning("MCX EOD: No candles at all for {$currentDate}");
            return [];
        }

        // Prev day 23:00 candles (CE + PE only)
        $prevCandles = DB::table('mcx_ohlc_data')
            ->whereDate('trade_date', $prevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = ?", [$eodTime])
            ->where('is_missing', 0)
            ->get();

        if ($prevCandles->isEmpty()) {
            $prevCandles = DB::table('mcx_ohlc_data')
                ->whereDate('trade_date', $prevDate)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereRaw("TIME(interval_time) >= '22:45:00'")
                ->where('is_missing', 0)
                ->orderByRaw("TIME(interval_time) DESC")
                ->get();
        }

        // Group today: [symbol][type][] = candles
        $todayGrouped = [];
        foreach ($todayCandles as $c) {
            $todayGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }

        // Group prev: [symbol][type][strike] = candle
        $prevGrouped = [];
        foreach ($prevCandles as $c) {
            $prevGrouped[$c->base_symbol][$c->instrument_type][(string)$c->strike] = $c;
        }

        $signals = [];

        foreach ($todayGrouped as $symbol => $typeMap) {
            $futCandle = $typeMap['FUT'][0] ?? null;
            if (!$futCandle || (float)$futCandle->close <= 0) continue;

            $currentClose = (float)$futCandle->close;

            [$ceOpenOI, $ceCurOI] = $this->aggregateByStrike(
                $prevGrouped[$symbol]['CE'] ?? [],
                $typeMap['CE'] ?? []
            );
            [$peOpenOI, $peCurOI] = $this->aggregateByStrike(
                $prevGrouped[$symbol]['PE'] ?? [],
                $typeMap['PE'] ?? []
            );

            if ($ceCurOI === 0 && $peCurOI === 0 && $ceOpenOI === 0 && $peOpenOI === 0) continue;

            $ceOiPct  = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $peOiPct  = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;
            $oiResult = $this->getOISignal($ceOiPct, $peOiPct);
            $pcRatio  = $ceCurOI > 0 ? round($peCurOI / $ceCurOI, 2) : 0;
            $sentiment = $oiResult['signal'];
            $isAligned = ($sentiment === 'BULLISH' || $sentiment === 'BEARISH');

            $unit = DB::table('mcx_symbols')->where('symbol', $symbol)->value('unit') ?? '';

            Log::info(sprintf(
                "MCX EOD %s | Sentiment: %s | Aligned: %s | CE%%: %.2f | PE%%: %.2f | FUT: %.2f",
                $symbol, $sentiment, $isAligned ? 'YES 🎯' : 'NO ❌', $ceOiPct, $peOiPct, $currentClose
            ));

            $signals[$symbol] = [
                'underlying_symbol' => $symbol,
                'trading_symbol'    => $futCandle->trading_symbol ?? $symbol,
                'instrument_token'  => $futCandle->instrument_token ?? null,
                'current_close'     => $currentClose,
                'spot_price'        => $currentClose,
                'ce_oi_change_pct'  => $ceOiPct,
                'pe_oi_change_pct'  => $peOiPct,
                'pe_ce_ratio'       => $pcRatio,
                'oi_condition'      => $oiResult['condition'],
                'final_sentiment'   => $sentiment,
                'trade_action'      => match($sentiment) {
                    'BULLISH' => 'BUY CE',
                    'BEARISH' => 'BUY PE',
                    default   => 'WAIT',
                },
                'is_aligned'        => $isAligned,
                'option_series'     => $optionSeries,
                'trading_date'      => $currentDate,
                'unit'              => $unit,
            ];
        }

        return $signals;
    }

    private function aggregateByStrike(array $prevByStrike, array $curCandles): array
    {
        if (empty($prevByStrike) || empty($curCandles)) return [0, 0];

        $curByStrike = [];
        foreach ($curCandles as $c) {
            $curByStrike[(string)$c->strike] = $c;
        }

        $openOI = 0;
        $curOI  = 0;
        foreach ($prevByStrike as $strike => $pc) {
            $cc = $curByStrike[$strike] ?? null;
            if (!$cc) continue;
            $openOI += (int)($pc->oi ?? 0);
            $curOI  += (int)($cc->oi ?? 0);
        }

        return [$openOI, $curOI];
    }

    // =========================================================
    //  PROCESS SIGNALS PER CONFIG
    // =========================================================

    private function processConfigSignals(
        McxOiivAutoConfig $config,
        array $aggregatedSignals,
        BrokerApi $broker,
        string $currentDate,
        Carbon $processingDateTime,
        string $optionSeries
    ): void {
        $lockTime = Carbon::parse($currentDate . ' 23:00:00', 'Asia/Kolkata');

        if ($processingDateTime->lessThan($lockTime)) {
            Log::info("MCX Config {$config->id}: before 23:00 — skip");
            return;
        }

        $created = $skipped = $skippedNeutral = $errors = 0;

        foreach ($aggregatedSignals as $symbol => $signalData) {
            try {
                if (!$signalData['is_aligned']) {
                    $skippedNeutral++;
                    continue;
                }

                $ce   = (float)($signalData['ce_oi_change_pct'] ?? 0);
                $pe   = (float)($signalData['pe_oi_change_pct'] ?? 0);
                $rank = McxOiivAutoConfig::computeStrengthRank($ce, $pe);

                $rawOptionType   = $signalData['final_sentiment'] === 'BULLISH' ? 'CE' : 'PE';
                $finalOptionType = $config->shouldReverseSignal()
                    ? ($rawOptionType === 'CE' ? 'PE' : 'CE')
                    : $rawOptionType;

                // Rank-based quantity, fallback to base
                $quantity = $rank ? $config->getQuantityForRank($rank, $finalOptionType) : 0;
                if ($quantity <= 0) {
                    $quantity = $finalOptionType === 'CE'
                        ? (int)($config->ce_quantity ?? 0)
                        : (int)($config->pe_quantity ?? 0);
                }

                if ($quantity <= 0) {
                    Log::info("MCX EOD {$symbol}: qty=0 — skip");
                    $skipped++;
                    continue;
                }

                // Duplicate guard
                $exists = McxOiivAutoOrder::where('config_id', $config->id)
                    ->where('symbol', $symbol)
                    ->whereDate('signal_detected_at', $currentDate)
                    ->where('status', true)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                $ok = $this->createOrder(
                    $config, $signalData, $broker,
                    $currentDate, $rank, $rawOptionType, $finalOptionType, $quantity, $optionSeries
                );

                $ok ? $created++ : $errors++;

            } catch (\Throwable $e) {
                Log::error("MCX EOD {$symbol}: " . $e->getMessage());
                $errors++;
            }
        }

        Log::info("MCX Config {$config->id} — Created:{$created} | SkipNeutral:{$skippedNeutral} | SkipOther:{$skipped} | Errors:{$errors}");
    }

    // =========================================================
    //  CREATE ORDER RECORD
    // =========================================================

    private function createOrder(
        McxOiivAutoConfig $config,
        array $signalData,
        BrokerApi $broker,
        string $date,
        ?int $rank,
        string $rawOptionType,
        string $finalOptionType,
        int $quantity,
        string $optionSeries
    ): bool {
        try {
            $symbol    = $signalData['underlying_symbol'];
            $direction = $signalData['final_sentiment'];
            $ce        = (float)($signalData['ce_oi_change_pct'] ?? 0);
            $pe        = (float)($signalData['pe_oi_change_pct'] ?? 0);
            $modeLabel = $config->shouldReverseSignal() ? 'OPPOSITE' : 'ALIGN';
            $rankLabel = $rank ? "Rank{$rank}" : 'RankNULL';

            $currentPrice = $signalData['current_close'] ?? 0;
            if ($currentPrice <= 0) {
                Log::error("MCX EOD: No FUT price for {$symbol}");
                return false;
            }

            $optionDetails = $this->getATMOption(
                $broker, $symbol, $finalOptionType, $currentPrice, $config, $optionSeries
            );

            if (!$optionDetails) {
                Log::error("MCX EOD: No ATM option for {$symbol} type={$finalOptionType}");
                return false;
            }

            if ($optionDetails['ltp'] <= 0) {
                Log::error("MCX EOD: LTP=0 for {$optionDetails['symbol']} — skip");
                return false;
            }

            $reason = sprintf(
                "MCX EOD | %s | Mode:%s | BUY %s | CE%%:%.2f | PE%%:%.2f | Diff:%.2f | %s | Sentiment:%s | Series:%s | Qty:%d | Unit:%s",
                $rankLabel, $modeLabel, $finalOptionType, $ce, $pe, abs($ce - $pe),
                $signalData['oi_condition'] ?? 'N/A', $direction, $optionSeries, $quantity,
                $signalData['unit'] ?? ''
            );

            $order = McxOiivAutoOrder::create([
                'user_id'            => $config->user_id,
                'config_id'          => $config->id,
                'broker_api_id'      => $broker->id,
                'symbol'             => $symbol,
                'trading_symbol'     => $signalData['trading_symbol'],
                'instrument_token'   => $signalData['instrument_token'] ?? null,
                'btst_signal'        => "MCX_{$rankLabel}_{$direction}_{$modeLabel}_{$finalOptionType}",
                'btst_confidence'    => 100,
                'btst_reason'        => $reason,
                'signal_detected_at' => Carbon::parse($date . ' 23:00:00', 'Asia/Kolkata'),
                'fut_oi_signal'      => "MCX EOD {$rankLabel} | " . ($signalData['oi_condition'] ?? 'N/A'),
                'fut_oi_strength'    => $direction,
                'ce_oi_signal'       => 'N/A',
                'pe_oi_signal'       => 'N/A',
                'spot_price'         => $currentPrice,
                'option_symbol'      => $optionDetails['symbol'],
                'option_token'       => $optionDetails['token'],
                'option_type'        => $finalOptionType,
                'strike_price'       => $optionDetails['strike'],
                'strike_position'    => $finalOptionType === 'CE' ? 'ATM+1' : 'ATM-1',
                'unit'               => $signalData['unit'] ?? null,
                'order_type'         => $config->order_type,
                'product'            => $config->product,
                'quantity'           => $quantity,
                'entry_price'        => $optionDetails['ltp'],
                'current_price'      => $optionDetails['ltp'],
                'strength_rank'      => $rank,
                'is_order_placed'    => false,
                'status'             => true,
            ]);

            Log::info("MCX Order #{$order->id} created | {$optionDetails['symbol']} | {$finalOptionType} | Strike:{$optionDetails['strike']} | {$rankLabel} | Qty:{$quantity}");
            return true;

        } catch (\Throwable $e) {
            Log::error("MCX createOrder {$signalData['underlying_symbol']}: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================
    //  ATM OPTION LOOKUP (MCX exchange)
    //  CE → ATM+1 | PE → ATM-1 (same as NFO PECEAutoTradingHelper)
    // =========================================================

    private function getATMOption(
        BrokerApi $broker,
        string $baseSymbol,
        string $optionType,
        float $futurePrice,
        McxOiivAutoConfig $config,
        string $optionSeries
    ): ?array {
        try {
            // Strike interval from mcx_symbols table first, then constant fallback
            $interval = (float)(
                DB::table('mcx_symbols')->where('symbol', $baseSymbol)->value('strike_interval')
                ?? (self::MCX_STRIKE_INTERVALS[$baseSymbol] ?? 50)
            );

            $atmStrike = round($futurePrice / $interval) * $interval;
            $atmStrike = $optionType === 'CE'
                ? $atmStrike + $interval   // CE = ATM+1 (OTM call)
                : $atmStrike - $interval;  // PE = ATM-1 (OTM put)

            $allExpiries = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'MCX')
                ->where('instrument_type', $optionType)
                ->whereDate('expiry', '>=', $optionSeries)
                ->distinct()
                ->orderBy('expiry', 'ASC')
                ->pluck('expiry')
                ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
                ->unique()
                ->values();

            // Fallback: any future MCX option expiry
            if ($allExpiries->isEmpty()) {
                $allExpiries = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'MCX')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>', now())
                    ->distinct()
                    ->orderBy('expiry', 'ASC')
                    ->pluck('expiry')
                    ->map(fn($d) => is_string($d) ? substr($d, 0, 10) : Carbon::parse($d)->toDateString())
                    ->unique()
                    ->values();
            }

            if ($allExpiries->isEmpty()) {
                Log::warning("MCX ATM {$baseSymbol}: no expiries for type={$optionType}");
                return null;
            }

            $targetExpiry = $config->useNextSeries()
                ? ($allExpiries->get(1) ?? $allExpiries->get(0))
                : $allExpiries->get(0);

            Log::debug("MCX ATM {$baseSymbol} [{$optionType}]: interval={$interval} | strike={$atmStrike} | expiry={$targetExpiry}");

            // Exact match
            $option = ZerodhaInstrument::where('name', $baseSymbol)
                ->where('exchange', 'MCX')
                ->where('instrument_type', $optionType)
                ->where('strike', $atmStrike)
                ->whereDate('expiry', $targetExpiry)
                ->first();

            // Nearest strike fallback
            if (!$option) {
                $option = ZerodhaInstrument::where('name', $baseSymbol)
                    ->where('exchange', 'MCX')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', $targetExpiry)
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                    ->orderBy('strike_diff')
                    ->first();
            }

            if (!$option) {
                Log::warning("MCX ATM {$baseSymbol}: no option strike~{$atmStrike} expiry={$targetExpiry}");
                return null;
            }

            $ltp = $this->getOptionLTP($broker, $option->instrument_token, $option->trading_symbol);

            Log::info("MCX ATM {$baseSymbol}: {$option->trading_symbol} | strike={$option->strike} | expiry={$targetExpiry} | LTP={$ltp}");

            return [
                'symbol' => $option->trading_symbol,
                'token'  => $option->instrument_token,
                'strike' => $option->strike,
                'ltp'    => $ltp,
                'expiry' => $option->expiry,
            ];

        } catch (\Throwable $e) {
            Log::error("MCX ATM {$baseSymbol}: " . $e->getMessage());
            return null;
        }
    }

    // =========================================================
    //  ORDER PLACEMENT (MCX exchange)
    // =========================================================

    private function placeOrder(McxOiivAutoOrder $order): void
    {
        try {
            Log::info("MCX PLACE: {$order->option_symbol}");

            $broker = $order->broker;
            if (!$broker->hasValidToken()) {
                $this->saveFailedOrder($order, 'Invalid broker token');
                return;
            }

            $this->ensureKiteInstance($broker);

            $instrument = ZerodhaInstrument::where('instrument_token', $order->option_token)
                ->first();

            if (!$instrument) {
                $instrument = ZerodhaInstrument::where('trading_symbol', $order->option_symbol)
                    ->where('exchange', 'MCX')
                    ->first();
            }

            if (!$instrument) {
                $this->saveFailedOrder($order, "MCX instrument not found: {$order->option_symbol}");
                return;
            }

            $this->placeKiteOrder($order, $order->quantity, $instrument, $this->kiteInstances[$broker->id]);

            $order->update(['is_order_placed' => true, 'order_placed_at' => now()]);
            Log::info("MCX ORDER Done: #{$order->id} | {$order->option_symbol}");

        } catch (\Throwable $e) {
            Log::error("MCX PLACE {$order->option_symbol}: " . $e->getMessage());
            $this->saveFailedOrder($order, $e->getMessage());
        }
    }

    private function placeKiteOrder(McxOiivAutoOrder $order, int $quantity, object $instrument, object $kite): void
    {
        $price = null;
        if ($order->order_type === 'LIMIT') {
            $discount = ($order->entry_price * $order->config->disc_ltp) / 100;
            $raw      = $order->entry_price - $discount;
            $tick     = $instrument->tick_size ?? 0.05;
            $price    = number_format(round($raw / $tick) * $tick, 2, '.', '');
        }

        $freezeLimit = self::MCX_FREEZE_LIMITS[$order->symbol] ?? null;

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

    private function executeSingleOrder(McxOiivAutoOrder $order, int $quantity, ?string $price, object $instrument, object $kite): void
    {
        $lotSize = (int)($instrument->lot_size ?? 1);

        $params = [
            'exchange'         => 'MCX',
            'tradingsymbol'    => $order->option_symbol,
            'transaction_type' => 'BUY',
            'quantity'         => $quantity * $lotSize,
            'product'          => $order->product,
            'order_type'       => $order->order_type === 'MARKET' ? 'MARKET' : 'LIMIT',
            'validity'         => 'DAY',
        ];

        if ($order->order_type !== 'MARKET') {
            $params['price'] = $price;
        }

        $result  = $kite->placeOrder('regular', $params);
        $orderId = is_object($result) ? $result->order_id : ($result['order_id'] ?? null);

        Log::info("MCX Kite Order Placed! ID:{$orderId} | {$order->option_symbol} | Units:" . ($quantity * $lotSize));
        $this->saveToOrderBook($order, $orderId, $quantity, $price);
    }

    // =========================================================
    //  LTP FETCH (MCX quote)
    // =========================================================

    private function getOptionLTP(BrokerApi $broker, $instrumentToken, string $tradingSymbol): float
    {
        try {
            $this->ensureKiteInstance($broker);
            $kite     = $this->kiteInstances[$broker->id];
            $quoteKey = "MCX:{$tradingSymbol}";
            $quotes   = $kite->getQuote([$quoteKey]);

            if (isset($quotes->$quoteKey->last_price)) {
                return (float)$quotes->$quoteKey->last_price;
            }

            $arr = json_decode(json_encode($quotes), true);
            if (isset($arr[$quoteKey]['last_price'])) {
                return (float)$arr[$quoteKey]['last_price'];
            }
        } catch (\Throwable $e) {
            Log::error("MCX LTP {$tradingSymbol}: " . $e->getMessage());
        }

        return 0.0; // Caller skips order when 0
    }

    // =========================================================
    //  OI SIGNAL LOGIC
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

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
    //  MCX TRADING DATE HELPERS
    //  Mon–Sat trading — skip Sunday only (unlike NFO Mon–Fri)
    // =========================================================

    private function getPreviousMcxTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isSunday() && !$this->isMcxHoliday($prev->toDateString())) {
                return $prev->toDateString();
            }
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isMcxHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->whereIn('market_name', ['MCX', 'NSE'])
            ->where('holiday_date', $date)
            ->exists();
    }

    // =========================================================
    //  ORDER BOOK HELPERS
    // =========================================================

    private function saveToOrderBook(McxOiivAutoOrder $order, $orderId, int $quantity, ?string $price): void
    {
        try {
            sleep(2);
            $kite         = $this->kiteInstances[$order->broker_api_id] ?? null;
            $orderHistory = $kite ? $kite->getOrderHistory($orderId) : [];
            $last         = !empty($orderHistory) ? end($orderHistory) : null;

            OrderBook::create([
                'user_id'            => $order->user_id,
                'broker_username'    => $order->broker->account_user_name ?? 'MCX',
                'order_id'           => $orderId ?? '-',
                'status'             => $last->status ?? 'PENDING',
                'trading_symbol'     => $order->option_symbol,
                'order_type'         => $order->order_type,
                'transaction_type'   => 'BUY',
                'product'            => $order->product,
                'price'              => $price ?? '-',
                'quantity'           => $quantity,
                'status_message'     => $last->status_message ?? 'MCX order placed',
                'order_datetime'     => now(),
                'oiiv_auto_order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error("MCX ORDER_BOOK save: " . $e->getMessage());
        }
    }

    private function saveFailedOrder(McxOiivAutoOrder $order, string $error): void
    {
        try {
            OrderBook::create([
                'user_id'            => $order->user_id,
                'broker_username'    => $order->broker->account_user_name ?? 'N/A',
                'order_id'           => '-',
                'status'             => 'FAILED',
                'trading_symbol'     => $order->option_symbol,
                'order_type'         => $order->order_type,
                'transaction_type'   => 'BUY',
                'product'            => $order->product,
                'price'              => '-',
                'quantity'           => $order->quantity ?? 0,
                'status_message'     => substr($error, 0, 500),
                'order_datetime'     => now(),
                'oiiv_auto_order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            Log::error("MCX ORDER_BOOK failed save: " . $e->getMessage());
        }
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