<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Index-Driven Signal Scanner
 *
 * Detects intraday breakout signals using NIFTY FUT candles.
 * Day Open  = 09:15 candle OPEN price (first candle of the day).
 * CE signal : any candle HIGH >= open + threshold  → BUY ATM CE (all configured symbols) at NEXT candle OPEN
 * PE signal : any candle LOW  <= open − threshold  → BUY ATM PE (all configured symbols) at NEXT candle OPEN
 * First occurrence only per direction per day.
 *
 * Tables:
 *   cp_fut_ohlc_{tf}    — NIFTY futures OHLC (base_symbol = 'NIFTY')
 *   cp_option_ohlc_{tf} — option OHLC for configured symbols
 *
 * Config-scoped: analysis_configs + analysis_config_symbols
 */
class IndexDrivenSignalController extends Controller
{
    private const TIMEFRAMES    = ['15min', '30min', '1hr'];
    private const OPEN_TIME     = '09:15:00';
    private const MARKET_CLOSE  = '15:15:00';
    private const INDEX_SYMBOL  = 'NIFTY';

    // =========================================================
    //  INDEX
    // =========================================================

    public function index()
    {
        $pageTitle = 'Index-Driven Signal Scanner';
        return view($this->activeTemplate . 'user.index-driven-signal.index', compact('pageTitle'));
    }

    // =========================================================
    //  GET SYMBOLS  (config-scoped, per timeframe)
    // =========================================================

    public function getSymbols(Request $request)
    {
        $timeframe = $this->resolveTimeframe($request);
        $config    = $this->getActiveConfig($timeframe);

        if (!$config) {
            return response()->json([
                'success'   => true,
                'symbols'   => [],
                'no_config' => true,
                'message'   => "No active Analysis Config for [{$timeframe}]. Go to Admin → Analysis Config.",
            ]);
        }

        return response()->json([
            'success' => true,
            'symbols' => $this->getConfigSymbols($config->id),
        ]);
    }

    // =========================================================
    //  MAIN ANALYZE
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $timeframe     = $this->resolveTimeframe($request);
            $fromDate      = $request->get('from_date');
            $toDate        = $request->get('to_date');
            $threshold     = (float) $request->get('threshold', 30);
            $signalFilter  = strtoupper($request->get('filter', 'BOTH'));   // CE | PE | BOTH
            $symbolReq     = array_filter((array) $request->get('symbols', []));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates.', 'data' => []]);
            }

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => "No active Analysis Config for [{$timeframe}]. Go to Admin → Analysis Config.",
                    'data'      => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols   = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $futTable = 'cp_fut_ohlc_' . $timeframe;
            $optTable = 'cp_option_ohlc_' . $timeframe;

            // ── 1. Trade dates in range ────────────────────────────────
            $tradeDates = DB::table($futTable)
                ->where('base_symbol', self::INDEX_SYMBOL)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json(['success' => true, 'data' => [], 'message' => 'No NIFTY data for this date range.']);
            }

            // ── 2. Load NIFTY FUT candles for all trade dates ──────────
            $niftyCandles = DB::table($futTable)
                ->where('base_symbol', self::INDEX_SYMBOL)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->select([
                    DB::raw('DATE(trade_date) as trade_day'),
                    DB::raw('TIME(interval_time) as candle_time'),
                    'interval_time',
                    'open', 'high', 'low', 'close',
                ])
                ->orderBy('trade_date')->orderBy('interval_time')
                ->get();

            // Group candles by date
            $candlesByDate = [];
            foreach ($niftyCandles as $c) {
                $candlesByDate[$c->trade_day][] = $c;
            }

            // ── 3. Detect CE/PE triggers per date ─────────────────────
            $triggers = []; // ['date','signal_type','nifty_open','nifty_trigger','trigger_time','nifty_move','buy_time']

            foreach ($tradeDates as $date) {
                $candles = $candlesByDate[$date] ?? [];
                if (empty($candles)) continue;

                // Find 09:15 open
                $openCandle = null;
                foreach ($candles as $c) {
                    if ($c->candle_time === self::OPEN_TIME) { $openCandle = $c; break; }
                }
                if (!$openCandle) continue;

                $dayOpen     = (float) $openCandle->open;
                $ceThreshold = $dayOpen + $threshold;
                $peThreshold = $dayOpen - $threshold;
                $ceDone      = false;
                $peDone      = false;
                $prevCandle  = null;

                foreach ($candles as $candle) {
                    if ($candle->candle_time === self::OPEN_TIME) { $prevCandle = $candle; continue; }

                    $high = (float) $candle->high;
                    $low  = (float) $candle->low;
                    $time = $candle->candle_time;

                    // CE signal
                    if (!$ceDone && in_array($signalFilter, ['CE', 'BOTH']) && $high >= $ceThreshold) {
                        $ceDone     = true;
                        $buyTime    = $this->nextCandleTime($time, $timeframe);
                        $triggers[] = [
                            'date'          => $date,
                            'signal_type'   => 'CE',
                            'nifty_open'    => $dayOpen,
                            'nifty_trigger' => $high,
                            'trigger_time'  => $this->fmt12($time),
                            'nifty_move'    => round($high - $dayOpen, 2),
                            'buy_time'      => $this->fmt12($buyTime),
                            'buy_time_raw'  => $buyTime,
                        ];
                    }

                    // PE signal
                    if (!$peDone && in_array($signalFilter, ['PE', 'BOTH']) && $low <= $peThreshold) {
                        $peDone     = true;
                        $buyTime    = $this->nextCandleTime($time, $timeframe);
                        $triggers[] = [
                            'date'          => $date,
                            'signal_type'   => 'PE',
                            'nifty_open'    => $dayOpen,
                            'nifty_trigger' => $low,
                            'trigger_time'  => $this->fmt12($time),
                            'nifty_move'    => round($low - $dayOpen, 2),
                            'buy_time'      => $this->fmt12($buyTime),
                            'buy_time_raw'  => $buyTime,
                        ];
                    }

                    if ($ceDone && $peDone) break;
                    $prevCandle = $candle;
                }
            }

            if (empty($triggers)) {
                return response()->json(['success' => true, 'data' => [], 'message' => 'No breakout signals found for this date range and threshold.']);
            }

            // ── 4. For each trigger, fetch ATM option data ─────────────
            // Collect unique (date, type, buy_time_raw) combos
            $results   = [];
            $ceCount   = 0;
            $peCount   = 0;

            foreach ($triggers as $trig) {
                $date       = $trig['date'];
                $sigType    = $trig['signal_type'];
                $buyTimeRaw = $trig['buy_time_raw'];

                foreach ($symbols as $symbol) {
                    // Fetch ATM CE or PE row at buy candle time for this symbol
                    $optRow = DB::table($optTable)
                        ->where('analysis_config_id', $config->id)
                        ->where('base_symbol', $symbol)
                        ->where(DB::raw('DATE(trade_date)'), $date)
                        ->whereRaw("TIME(interval_time) = ?", [$buyTimeRaw])
                        ->where('instrument_type', $sigType)
                        ->where('strike_position', 'ATM')
                        ->where('is_missing', false)
                        ->select(['strike', 'oi', 'expiry_date', 'open as buy_price', 'lot_size'])
                        ->first();

                    if (!$optRow) continue;

                    $buyPrice   = round((float) $optRow->buy_price, 2);
                    $lotSize    = (int) ($optRow->lot_size ?? 1);
                    $investment = round($buyPrice * $lotSize, 2);

                    $results[] = array_merge($trig, [
                        'symbol'      => $symbol,
                        'strike'      => $optRow->strike,
                        'strike_oi'   => (int) $optRow->oi,
                        'expiry_date' => substr($optRow->expiry_date ?? '', 0, 10),
                        'buy_price'   => $buyPrice,
                        'lot_size'    => $lotSize,
                        'investment'  => $investment,
                    ]);
                }

                if ($sigType === 'CE') $ceCount++;
                else                  $peCount++;
            }

            // Sort: date desc, signal_type, symbol
            usort($results, fn($a, $b) =>
                strcmp($b['date'], $a['date'])
                ?: strcmp($a['signal_type'], $b['signal_type'])
                ?: strcmp($a['symbol'], $b['symbol'])
            );

            $totalInv = array_sum(array_column($results, 'investment'));

            return response()->json([
                'success'          => true,
                'data'             => $results,
                'total_records'    => count($results),
                'ce_count'         => $ceCount,
                'pe_count'         => $peCount,
                'trigger_count'    => count($triggers),
                'symbol_count'     => count($symbols),
                'total_investment' => round($totalInv, 2),
                'message'          => count($results) . ' trade(s) found across ' . count($triggers) . ' signal(s)',
                'timeframe'        => $timeframe,
                'threshold'        => $threshold,
                'available_symbols'=> $configSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('IndexDrivenSignal analyze: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  EXIT P&L  — aggregate all-symbol exit at each candle
    // =========================================================

    public function exitPnl(Request $request)
    {
        try {
            $timeframe    = $this->resolveTimeframe($request);
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $threshold    = (float) $request->get('threshold', 30);
            $filterType   = strtoupper($request->get('filter', 'CE'));  // CE | PE
            $symbolReq    = array_filter((array) $request->get('symbols', []));

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json(['success' => false, 'message' => 'No active config.']);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            $symbols       = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $futTable = 'cp_fut_ohlc_' . $timeframe;
            $optTable = 'cp_option_ohlc_' . $timeframe;

            $tradeDates = DB::table($futTable)
                ->where('base_symbol', self::INDEX_SYMBOL)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json(['success' => true, 'ce' => [], 'pe' => []]);
            }

            $niftyCandles = DB::table($futTable)
                ->where('base_symbol', self::INDEX_SYMBOL)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->select([DB::raw('DATE(trade_date) as trade_day'), DB::raw('TIME(interval_time) as candle_time'), 'open', 'high', 'low'])
                ->orderBy('trade_date')->orderBy('interval_time')
                ->get();

            $candlesByDate = [];
            foreach ($niftyCandles as $c) $candlesByDate[$c->trade_day][] = $c;

            // Detect triggers, collect entry trades
            $entries    = [];   // [date, signal_type, buy_time_raw, symbol, buy_price, lot_size, investment]

            foreach ($tradeDates as $date) {
                $candles = $candlesByDate[$date] ?? [];
                $openC   = collect($candles)->firstWhere('candle_time', self::OPEN_TIME);
                if (!$openC) continue;

                $dayOpen = (float) $openC->open;
                $ceDone  = false;
                $peDone  = false;

                foreach ($candles as $candle) {
                    if ($candle->candle_time === self::OPEN_TIME) continue;

                    if (!$ceDone && $filterType === 'CE' && (float)$candle->high >= $dayOpen + $threshold) {
                        $ceDone  = true;
                        $buyTime = $this->nextCandleTime($candle->candle_time, $timeframe);
                        foreach ($symbols as $sym) {
                            $opt = DB::table($optTable)
                                ->where('analysis_config_id', $config->id)
                                ->where('base_symbol', $sym)
                                ->where(DB::raw('DATE(trade_date)'), $date)
                                ->whereRaw("TIME(interval_time) = ?", [$buyTime])
                                ->where('instrument_type', 'CE')
                                ->where('strike_position', 'ATM')
                                ->where('is_missing', false)
                                ->select(['strike', 'open as buy_price', 'lot_size'])
                                ->first();
                            if (!$opt) continue;
                            $bp = (float) $opt->buy_price;
                            $ls = (int)   ($opt->lot_size ?? 1);
                            $entries[] = ['date'=>$date,'type'=>'CE','symbol'=>$sym,'strike'=>$opt->strike,'buy_time_raw'=>$buyTime,'buy_price'=>$bp,'lot_size'=>$ls,'investment'=>$bp*$ls];
                        }
                    }

                    if (!$peDone && $filterType === 'PE' && (float)$candle->low <= $dayOpen - $threshold) {
                        $peDone  = true;
                        $buyTime = $this->nextCandleTime($candle->candle_time, $timeframe);
                        foreach ($symbols as $sym) {
                            $opt = DB::table($optTable)
                                ->where('analysis_config_id', $config->id)
                                ->where('base_symbol', $sym)
                                ->where(DB::raw('DATE(trade_date)'), $date)
                                ->whereRaw("TIME(interval_time) = ?", [$buyTime])
                                ->where('instrument_type', 'PE')
                                ->where('strike_position', 'ATM')
                                ->where('is_missing', false)
                                ->select(['strike', 'open as buy_price', 'lot_size'])
                                ->first();
                            if (!$opt) continue;
                            $bp = (float) $opt->buy_price;
                            $ls = (int)   ($opt->lot_size ?? 1);
                            $entries[] = ['date'=>$date,'type'=>'PE','symbol'=>$sym,'strike'=>$opt->strike,'buy_time_raw'=>$buyTime,'buy_price'=>$bp,'lot_size'=>$ls,'investment'=>$bp*$ls];
                        }
                    }

                    if ($ceDone && $peDone) break;
                }
            }

            if (empty($entries)) {
                return response()->json(['success' => true, $filterType === 'CE' ? 'ce' : 'pe' => []]);
            }

            // Build exit P&L for every candle time after the latest buy_time
            $exitTimes = $this->getCandleTimes($timeframe);
            $slots     = [];

            foreach ($exitTimes as $exitTime) {
                $totalSell  = 0;
                $totalInv   = 0;
                $tradeCount = 0;

                foreach ($entries as $e) {
                    // Only exit times AFTER the buy time
                    if ($exitTime <= $e['buy_time_raw']) continue;

                    $opt = DB::table($optTable)
                        ->where('analysis_config_id', $config->id)
                        ->where('base_symbol', $e['symbol'])
                        ->where(DB::raw('DATE(trade_date)'), $e['date'])
                        ->whereRaw("TIME(interval_time) = ?", [$exitTime])
                        ->where('instrument_type', $e['type'])
                        ->where('strike', $e['strike'])
                        ->where('is_missing', false)
                        ->value('open');

                    if ($opt === null) continue;

                    $sellVal    = (float)$opt * $e['lot_size'];
                    $totalSell += $sellVal;
                    $totalInv  += $e['investment'];
                    $tradeCount++;
                }

                if ($tradeCount === 0) continue;

                $profit = round($totalSell - $totalInv, 2);
                $roi    = $totalInv > 0 ? round(($profit / $totalInv) * 100, 2) : 0;

                $slots[] = [
                    'exit_time'   => $this->fmt12($exitTime),
                    'sell_total'  => round($totalSell, 2),
                    'investment'  => round($totalInv, 2),
                    'profit'      => $profit,
                    'roi'         => $roi,
                    'trade_count' => $tradeCount,
                ];
            }

            $key = strtolower($filterType);
            return response()->json(['success' => true, $key => $slots]);

        } catch (\Exception $e) {
            Log::error('IndexDrivenSignal exitPnl: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================
    //  HELPERS
    // =========================================================

    private function getActiveConfig(string $timeframe): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', $timeframe)
            ->where('is_active', 1)
            ->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')
            ->toArray();
    }

    private function resolveTimeframe(Request $request): string
    {
        $tf = strtolower(trim($request->get('timeframe', '15min')));
        return in_array($tf, self::TIMEFRAMES) ? $tf : '15min';
    }

    /**
     * Compute the next candle's interval_time string (HH:MM:SS) given the current candle time.
     */
    private function nextCandleTime(string $candleTime, string $timeframe): string
    {
        $minutes = match($timeframe) {
            '30min' => 30,
            '1hr'   => 60,
            default => 15,
        };
        [$h, $m, $s] = explode(':', $candleTime);
        $total  = (int)$h * 60 + (int)$m + $minutes;
        $nh     = intdiv($total, 60);
        $nm     = $total % 60;
        $result = sprintf('%02d:%02d:00', $nh, $nm);
        // Cap at market close
        return $result <= self::MARKET_CLOSE ? $result : self::MARKET_CLOSE;
    }

    /**
     * All candle times between 09:15 and 15:15 for a given timeframe.
     */
    private function getCandleTimes(string $timeframe): array
    {
        $minutes = match($timeframe) { '30min' => 30, '1hr' => 60, default => 15 };
        $times   = [];
        $cur     = 9 * 60 + 15;
        $end     = 15 * 60 + 15;
        while ($cur <= $end) {
            $times[] = sprintf('%02d:%02d:00', intdiv($cur, 60), $cur % 60);
            $cur    += $minutes;
        }
        return $times;
    }

    /**
     * Convert HH:MM:SS → H:MM AM/PM
     */
    private function fmt12(string $time): string
    {
        [$h, $m] = explode(':', $time);
        $h  = (int)$h;
        $am = $h < 12 ? 'AM' : 'PM';
        $h  = $h % 12 ?: 12;
        return "{$h}:{$m} {$am}";
    }
}