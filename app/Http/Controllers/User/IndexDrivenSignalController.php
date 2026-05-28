<?php
// FILE: app/Http/Controllers/User/IndexDrivenSignalController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Index-Driven Signal Scanner — 15min only
 *
 * Detects intraday breakout signals using NIFTY FUT candles.
 *   Day Open = 09:15 candle OPEN
 *   CE signal: any candle HIGH >= open + threshold → BUY ATM CE at NEXT candle OPEN
 *   PE signal: any candle LOW  <= open − threshold → BUY ATM PE at NEXT candle OPEN
 *   First occurrence per direction per day only.
 *
 * Tables:
 *   cp_fut_ohlc_15min    — NIFTY FUT candles
 *   cp_option_ohlc_15min — ATM option candles
 */
class IndexDrivenSignalController extends Controller
{
    private const TF           = '15min';
    private const OPEN_TIME    = '09:15:00';
    private const MARKET_CLOSE = '15:15:00';
    private const INDEX_SYMBOL = 'NIFTY';
    private const INTERVAL_MIN = 15;   // 15min only

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Index-Driven Signal Scanner';
        return view(activeTemplate() . 'user.index-driven-signal.index', compact('pageTitle'));
    }

    // ── Symbols API ───────────────────────────────────────────────────────────

    public function getSymbols(Request $request): JsonResponse
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json([
                'success'   => true,
                'symbols'   => [],
                'no_config' => true,
                'message'   => 'No active 15min Analysis Config. Go to Admin → Analysis Config.',
            ]);
        }
        return response()->json(['success' => true, 'symbols' => $this->getConfigSymbols($config->id)]);
    }

    // ── Main Analyze API ──────────────────────────────────────────────────────

    public function analyze(Request $request): JsonResponse
    {
        try {
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $threshold    = (float) $request->get('threshold', 30);
            $signalFilter = strtoupper($request->get('filter', 'BOTH'));   // CE | PE | BOTH
            $symbolReq    = array_filter((array) $request->get('symbols', []));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates.', 'data' => []]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => 'No active 15min Analysis Config. Go to Admin → Analysis Config.',
                    'data'      => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols  = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $futTable = 'cp_fut_ohlc_15min';
            $optTable = 'cp_option_ohlc_15min';

            // ── Trade dates in range ──────────────────────────────────────
            $tradeDates = DB::table($futTable)
                ->where('base_symbol', self::INDEX_SYMBOL)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json([
                    'success' => true, 'data' => [],
                    'message' => 'No NIFTY data for this date range.',
                ]);
            }

            // ── Load all NIFTY FUT candles ────────────────────────────────
            $niftyCandles = DB::table($futTable)
                ->where('base_symbol', self::INDEX_SYMBOL)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->select([
                    DB::raw('DATE(trade_date) as trade_day'),
                    DB::raw('TIME(interval_time) as candle_time'),
                    'interval_time', 'open', 'high', 'low', 'close',
                ])
                ->orderBy('trade_date')->orderBy('interval_time')
                ->get();

            $candlesByDate = [];
            foreach ($niftyCandles as $c) {
                $candlesByDate[$c->trade_day][] = $c;
            }

            // ── Detect triggers ───────────────────────────────────────────
            $triggers = [];

            foreach ($tradeDates as $date) {
                $candles = $candlesByDate[$date] ?? [];
                if (empty($candles)) continue;

                $openCandle = null;
                foreach ($candles as $c) {
                    if ($c->candle_time === self::OPEN_TIME) { $openCandle = $c; break; }
                }
                if (!$openCandle) continue;

                $dayOpen     = (float) $openCandle->open;
                $ceThreshold = $dayOpen + $threshold;
                $peThreshold = $dayOpen - $threshold;
                $ceDone = false;
                $peDone = false;

                foreach ($candles as $candle) {
                    if ($candle->candle_time === self::OPEN_TIME) continue;

                    $high = (float) $candle->high;
                    $low  = (float) $candle->low;

                    if (!$ceDone && in_array($signalFilter, ['CE', 'BOTH']) && $high >= $ceThreshold) {
                        $ceDone   = true;
                        $buyTime  = $this->nextCandleTime($candle->candle_time);
                        $triggers[] = [
                            'date'          => $date,
                            'signal_type'   => 'CE',
                            'nifty_open'    => $dayOpen,
                            'nifty_trigger' => $high,
                            'trigger_time'  => $this->fmt12($candle->candle_time),
                            'nifty_move'    => round($high - $dayOpen, 2),
                            'buy_time'      => $this->fmt12($buyTime),
                            'buy_time_raw'  => $buyTime,
                        ];
                    }

                    if (!$peDone && in_array($signalFilter, ['PE', 'BOTH']) && $low <= $peThreshold) {
                        $peDone   = true;
                        $buyTime  = $this->nextCandleTime($candle->candle_time);
                        $triggers[] = [
                            'date'          => $date,
                            'signal_type'   => 'PE',
                            'nifty_open'    => $dayOpen,
                            'nifty_trigger' => $low,
                            'trigger_time'  => $this->fmt12($candle->candle_time),
                            'nifty_move'    => round($low - $dayOpen, 2),
                            'buy_time'      => $this->fmt12($buyTime),
                            'buy_time_raw'  => $buyTime,
                        ];
                    }

                    if ($ceDone && $peDone) break;
                }
            }

            if (empty($triggers)) {
                return response()->json([
                    'success' => true, 'data' => [],
                    'message' => 'No breakout signals found for this date range and threshold.',
                ]);
            }

            // ── Fetch ATM option data per trigger ─────────────────────────
            $results  = [];
            $ceCount  = 0;
            $peCount  = 0;

            foreach ($triggers as $trig) {
                $date       = $trig['date'];
                $sigType    = $trig['signal_type'];
                $buyTimeRaw = $trig['buy_time_raw'];

                foreach ($symbols as $symbol) {
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

                    $buyPrice = round((float) $optRow->buy_price, 2);
                    $lotSize  = (int) ($optRow->lot_size ?? 1);

                    $results[] = array_merge($trig, [
                        'symbol'      => $symbol,
                        'strike'      => $optRow->strike,
                        'strike_oi'   => (int) $optRow->oi,
                        'expiry_date' => substr($optRow->expiry_date ?? '', 0, 10),
                        'buy_price'   => $buyPrice,
                        'lot_size'    => $lotSize,
                        'investment'  => round($buyPrice * $lotSize, 2),
                    ]);
                }

                if ($sigType === 'CE') $ceCount++;
                else                  $peCount++;
            }

            usort($results, fn($a, $b) =>
                strcmp($b['date'], $a['date'])
                ?: strcmp($a['signal_type'], $b['signal_type'])
                ?: strcmp($a['symbol'], $b['symbol'])
            );

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'total_records'     => count($results),
                'ce_count'          => $ceCount,
                'pe_count'          => $peCount,
                'trigger_count'     => count($triggers),
                'symbol_count'      => count($symbols),
                'total_investment'  => round(array_sum(array_column($results, 'investment')), 2),
                'message'           => count($results) . ' trade(s) found across ' . count($triggers) . ' signal(s)',
                'threshold'         => $threshold,
                'available_symbols' => $configSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('IndexDrivenSignal analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── Exit P&L API ──────────────────────────────────────────────────────────

    public function exitPnl(Request $request): JsonResponse
    {
        try {
            $fromDate   = $request->get('from_date');
            $toDate     = $request->get('to_date');
            $threshold  = (float) $request->get('threshold', 30);
            $filterType = strtoupper($request->get('filter', 'CE'));  // CE | PE
            $symbolReq  = array_filter((array) $request->get('symbols', []));

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json(['success' => false, 'message' => 'No active config.']);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            $symbols       = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $futTable = 'cp_fut_ohlc_15min';
            $optTable = 'cp_option_ohlc_15min';

            $tradeDates = DB::table($futTable)
                ->where('base_symbol', self::INDEX_SYMBOL)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                $key = strtolower($filterType);
                return response()->json(['success' => true, $key => []]);
            }

            $niftyCandles = DB::table($futTable)
                ->where('base_symbol', self::INDEX_SYMBOL)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->select([DB::raw('DATE(trade_date) as trade_day'), DB::raw('TIME(interval_time) as candle_time'), 'open', 'high', 'low'])
                ->orderBy('trade_date')->orderBy('interval_time')
                ->get();

            $candlesByDate = [];
            foreach ($niftyCandles as $c) $candlesByDate[$c->trade_day][] = $c;

            // Collect entries
            $entries = [];

            foreach ($tradeDates as $date) {
                $candles = $candlesByDate[$date] ?? [];
                $openC   = collect($candles)->firstWhere('candle_time', self::OPEN_TIME);
                if (!$openC) continue;

                $dayOpen = (float) $openC->open;
                $done    = false;

                foreach ($candles as $candle) {
                    if ($candle->candle_time === self::OPEN_TIME) continue;
                    if ($done) break;

                    $triggered = match($filterType) {
                        'CE' => (float)$candle->high >= $dayOpen + $threshold,
                        'PE' => (float)$candle->low  <= $dayOpen - $threshold,
                        default => false,
                    };

                    if ($triggered) {
                        $done    = true;
                        $buyTime = $this->nextCandleTime($candle->candle_time);

                        foreach ($symbols as $sym) {
                            $opt = DB::table($optTable)
                                ->where('analysis_config_id', $config->id)
                                ->where('base_symbol', $sym)
                                ->where(DB::raw('DATE(trade_date)'), $date)
                                ->whereRaw("TIME(interval_time) = ?", [$buyTime])
                                ->where('instrument_type', $filterType)
                                ->where('strike_position', 'ATM')
                                ->where('is_missing', false)
                                ->select(['strike', 'open as buy_price', 'lot_size'])
                                ->first();

                            if (!$opt) continue;

                            $bp = (float) $opt->buy_price;
                            $ls = (int) ($opt->lot_size ?? 1);
                            $entries[] = [
                                'date'        => $date,
                                'type'        => $filterType,
                                'symbol'      => $sym,
                                'strike'      => $opt->strike,
                                'buy_time_raw'=> $buyTime,
                                'buy_price'   => $bp,
                                'lot_size'    => $ls,
                                'investment'  => $bp * $ls,
                            ];
                        }
                    }
                }
            }

            if (empty($entries)) {
                $key = strtolower($filterType);
                return response()->json(['success' => true, $key => []]);
            }

            // Build exit P&L for every candle time
            $exitTimes = $this->getCandleTimes();
            $slots     = [];

            foreach ($exitTimes as $exitTime) {
                $totalSell  = 0;
                $totalInv   = 0;
                $tradeCount = 0;

                foreach ($entries as $e) {
                    if ($exitTime <= $e['buy_time_raw']) continue;

                    $exitPrice = DB::table($optTable)
                        ->where('analysis_config_id', $config->id)
                        ->where('base_symbol', $e['symbol'])
                        ->where(DB::raw('DATE(trade_date)'), $e['date'])
                        ->whereRaw("TIME(interval_time) = ?", [$exitTime])
                        ->where('instrument_type', $e['type'])
                        ->where('strike', $e['strike'])
                        ->where('is_missing', false)
                        ->value('open');

                    if ($exitPrice === null) continue;

                    $totalSell += (float)$exitPrice * $e['lot_size'];
                    $totalInv  += $e['investment'];
                    $tradeCount++;
                }

                if ($tradeCount === 0) continue;

                $profit = round($totalSell - $totalInv, 2);
                $slots[] = [
                    'exit_time'   => $this->fmt12($exitTime),
                    'sell_total'  => round($totalSell, 2),
                    'investment'  => round($totalInv, 2),
                    'profit'      => $profit,
                    'roi'         => $totalInv > 0 ? round(($profit / $totalInv) * 100, 2) : 0,
                    'trade_count' => $tradeCount,
                ];
            }

            $key = strtolower($filterType);
            return response()->json(['success' => true, $key => $slots]);

        } catch (\Exception $e) {
            Log::error('IndexDrivenSignal exitPnl: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', self::TF)
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

    private function nextCandleTime(string $candleTime): string
    {
        [$h, $m] = explode(':', $candleTime);
        $total   = (int)$h * 60 + (int)$m + self::INTERVAL_MIN;
        $result  = sprintf('%02d:%02d:00', intdiv($total, 60), $total % 60);
        return $result <= self::MARKET_CLOSE ? $result : self::MARKET_CLOSE;
    }

    private function getCandleTimes(): array
    {
        $times = [];
        $cur   = 9 * 60 + 15;
        $end   = 15 * 60 + 15;
        while ($cur <= $end) {
            $times[] = sprintf('%02d:%02d:00', intdiv($cur, 60), $cur % 60);
            $cur    += self::INTERVAL_MIN;
        }
        return $times;
    }

    private function fmt12(string $time): string
    {
        [$h, $m] = explode(':', $time);
        $h  = (int)$h;
        $am = $h < 12 ? 'AM' : 'PM';
        $h  = $h % 12 ?: 12;
        return "{$h}:{$m} {$am}";
    }
}