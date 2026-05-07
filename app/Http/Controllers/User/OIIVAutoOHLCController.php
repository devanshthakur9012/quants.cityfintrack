<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Auth;

/**
 * OHLC Candle Signal Controller — NIFTY & BANKNIFTY Only
 *
 * Signal Logic:
 *   BUY  → Close > Prev High + Close > Open + Body% > 50% + OI Bullish + Above Pivot
 *   SELL → Close < Prev Low  + Close < Open + Body% > 50% + OI Bearish + Below Pivot
 *
 * Candle Patterns:
 *   Hammer         → Bullish reversal  (lower wick > 2×body, close > open)
 *   Shooting Star  → Bearish reversal  (upper wick > 2×body, close < open)
 *   Expansion      → Range > 5-candle avg + Close near High/Low
 *
 * NIFTY note: NIFTY has weekly option expiries but only monthly FUT.
 *   The FUT row for a weekly series is stored with the monthly FUT expiry_date.
 *   So FUT queries use a date-range approach (>= weekly expiry, <= month-end)
 *   rather than exact expiry_date match.
 */
class OIIVAutoOHLCController extends Controller
{
    // Symbols we support — NIFTY & BANKNIFTY only
    private const ALLOWED_SYMBOLS = ['NIFTY', 'BANKNIFTY'];

    // Symbols with weekly option expiries (FUT is monthly only)
    private const WEEKLY_EXPIRY_SYMBOLS = ['NIFTY'];

    // Fixed interval — 15 min only (symbol/interval tabs removed from frontend)
    private const INTERVAL_MIN = 15;

    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'OHLC Candle Signal Analysis — NIFTY & BANKNIFTY';
        return view($this->activeTemplate . 'user.oiiv-auto.ohlc-signal', compact('pageTitle'));
    }

    // =========================================================
    //  SERIES
    //  Returns unique option expiry dates for NIFTY + BANKNIFTY.
    //  For NIFTY: returns ALL weekly expiries (each Thursday/Tuesday).
    //  For BANKNIFTY: monthly only — but since we query across both,
    //  we just return all distinct expiry_dates and let user pick.
    // =========================================================

    public function getSeries()
    {
        $today = Carbon::today()->toDateString();

        $series = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereIn('base_symbol', self::ALLOWED_SYMBOLS)
            ->whereNotNull('expiry_date')
            ->distinct()
            ->orderBy('expiry_date', 'ASC')
            ->pluck('expiry_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($series)) {
            return response()->json(['success' => true, 'series' => [], 'current_series' => null]);
        }

        // Current series = nearest expiry >= today
        $currentSeries = collect($series)->first(fn($d) => $d >= $today);
        if (!$currentSeries) $currentSeries = end($series);

        $formatted = array_map(function ($d) {
            $dt = Carbon::parse($d);
            // Distinguish weekly vs monthly for label
            $label = $dt->format('d M Y') . ' (' . $d . ')';
            return [
                'value' => $d,
                'label' => $label,
            ];
        }, $series);

        return response()->json([
            'success'        => true,
            'series'         => $formatted,
            'current_series' => $currentSeries,
        ]);
    }

    // =========================================================
    //  MAIN SIGNAL ANALYSIS
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $seriesExpiry = $request->get('series_expiry');
            $filterSignal = $request->get('filter_signal');   // BUY / SELL / ''

            // Interval and symbol are fixed — frontend tabs removed
            $interval = self::INTERVAL_MIN;
            $symbols  = self::ALLOWED_SYMBOLS;

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }
            if (!$seriesExpiry) {
                return response()->json(['success' => false, 'message' => 'Series (expiry) is required', 'data' => []]);
            }

            // ── Fetch FUT candles ─────────────────────────────────────────────
            // For NIFTY weekly: the FUT row has monthly FUT expiry_date, NOT the
            // weekly option expiry. So we fetch FUT without expiry_date filter,
            // then match CE/PE by option expiry separately.
            //
            // Strategy: fetch FUT for all ALLOWED_SYMBOLS in date range,
            // no expiry_date restriction on FUT (there's only one FUT per symbol
            // per day anyway — the near-month contract).
            $futCandles = OptionOhlcData::whereIn('base_symbol', $symbols)
                ->where('instrument_type', 'FUT')
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->where('is_missing', 0)
                ->select([
                    'base_symbol',
                    'trade_date',
                    'expiry_date',
                    'open', 'high', 'low', 'close',
                    'volume', 'oi',
                    DB::raw("TIME(interval_time) as candle_time"),
                    DB::raw("DATE(trade_date) as trade_day"),
                ])
                ->orderBy('base_symbol')
                ->orderBy('trade_date')
                ->orderBy('interval_time')
                ->get();

            if ($futCandles->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No FUT data found for selected date range', 'data' => []]);
            }

            // ── Fetch CE/PE OI snapshots ──────────────────────────────────────
            // These ARE filtered by the selected option series expiry
            $oiRows = OptionOhlcData::whereIn('base_symbol', $symbols)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->whereDate('expiry_date', $seriesExpiry)
                ->where('is_missing', 0)
                ->whereRaw("TIME(interval_time) IN ('09:30:00','12:15:00')")
                ->whereIn('strike_position', ['ATM-1', 'ATM', 'ATM+1'])
                ->select([
                    'base_symbol',
                    'instrument_type',
                    'strike_position',
                    'oi',
                    DB::raw("DATE(trade_date) as trade_day"),
                    DB::raw("TIME(interval_time) as candle_time"),
                ])
                ->get();

            // Build OI map: [symbol][date][CE|PE][09:30|12:15] → total_oi
            $oiMap = [];
            foreach ($oiRows as $r) {
                $oiMap[$r->base_symbol][$r->trade_day][$r->instrument_type][$r->candle_time] =
                    ($oiMap[$r->base_symbol][$r->trade_day][$r->instrument_type][$r->candle_time] ?? 0) + (int)($r->oi ?? 0);
            }

            // ── Group FUT candles by symbol → date → list ─────────────────────
            $grouped = [];
            foreach ($futCandles as $c) {
                $day = is_string($c->trade_day) ? $c->trade_day : Carbon::parse($c->trade_day)->toDateString();
                $grouped[$c->base_symbol][$day][] = $c;
            }

            $results = [];

            foreach ($grouped as $sym => $dayMap) {
                foreach ($dayMap as $day => $candles) {
                    $candles = array_values($candles);

                    // Sort by time
                    usort($candles, fn($a, $b) => strcmp($a->candle_time, $b->candle_time));

                    // Aggregate into 15-min buckets (always 15)
                    $buckets = $this->aggregateIntoBuckets($candles, $interval);

                    if (count($buckets) < 2) continue;

                    // Rolling 5-bucket average range for expansion check
                    $avgRange = $this->rollingAvgRange($buckets, 5);

                    foreach ($buckets as $bIdx => $bucket) {
                        if ($bIdx === 0) continue; // need prev candle

                        $prev   = $buckets[$bIdx - 1];
                        $cur    = $bucket;
                        $signal = $this->calcCandleSignal($cur, $prev, $avgRange[$bIdx] ?? null);

                        if ($filterSignal && $signal['action'] !== $filterSignal) continue;

                        // OI context
                        $ceOpen = $oiMap[$sym][$day]['CE']['09:30:00'] ?? 0;
                        $ceCur  = $oiMap[$sym][$day]['CE']['12:15:00'] ?? $ceOpen;
                        $peOpen = $oiMap[$sym][$day]['PE']['09:30:00'] ?? 0;
                        $peCur  = $oiMap[$sym][$day]['PE']['12:15:00'] ?? $peOpen;

                        $oiSig = $this->getOISentiment($ceOpen, $ceCur, $peOpen, $peCur);

                        // Pivot (previous day FUT OHLC — no expiry filter for NIFTY)
                        $prevOhlc = $this->getPreviousDayOHLC($sym, $day);
                        $pivots   = $prevOhlc ? $this->calcPivots($prevOhlc) : null;
                        $pivotPos = $pivots   ? $this->getPivotPosition($cur['close'], $pivots) : 'N/A';

                        // Multi-factor confirmation
                        $confirmation = $this->getConfirmation($signal, $oiSig, $pivots ? $cur['close'] > $pivots['P'] : null);

                        $results[] = [
                            'symbol'         => $sym,
                            'date'           => $day,
                            'candle_time'    => $cur['time'],
                            'interval'       => $interval . 'min',

                            // Current candle OHLC
                            'open'           => round($cur['open'],  2),
                            'high'           => round($cur['high'],  2),
                            'low'            => round($cur['low'],   2),
                            'close'          => round($cur['close'], 2),
                            'volume'         => $cur['volume'] ?? 0,

                            // Prev candle reference
                            'prev_high'      => round($prev['high'], 2),
                            'prev_low'       => round($prev['low'],  2),
                            'prev_close'     => round($prev['close'],2),

                            // Candle metrics
                            'body_pct'       => $signal['body_pct'],
                            'upper_wick_pct' => $signal['upper_wick_pct'],
                            'lower_wick_pct' => $signal['lower_wick_pct'],
                            'candle_type'    => $signal['candle_type'],
                            'pattern'        => $signal['pattern'],
                            'breakout'       => $signal['breakout'],

                            // OI
                            'ce_oi_open'     => $ceOpen,
                            'ce_oi_cur'      => $ceCur,
                            'ce_oi_pct'      => $ceOpen > 0 ? round(($ceCur - $ceOpen) / $ceOpen * 100, 2) : 0,
                            'pe_oi_open'     => $peOpen,
                            'pe_oi_cur'      => $peCur,
                            'pe_oi_pct'      => $peOpen > 0 ? round(($peCur - $peOpen) / $peOpen * 100, 2) : 0,
                            'oi_sentiment'   => $oiSig['signal'],
                            'oi_condition'   => $oiSig['condition'],

                            // Pivot
                            'pivot_position' => $pivotPos,
                            'above_pivot'    => $pivots ? ($cur['close'] >= $pivots['P']) : null,
                            'pivot'          => $pivots ? round($pivots['P'],  2) : null,
                            'r1'             => $pivots ? round($pivots['R1'], 2) : null,
                            'r2'             => $pivots ? round($pivots['R2'], 2) : null,
                            'r3'             => $pivots ? round($pivots['R3'], 2) : null,
                            's1'             => $pivots ? round($pivots['S1'], 2) : null,
                            's2'             => $pivots ? round($pivots['S2'], 2) : null,
                            's3'             => $pivots ? round($pivots['S3'], 2) : null,

                            // Signal
                            'action'         => $signal['action'],
                            'strength'       => $signal['strength'],
                            'reasons'        => $signal['reasons'],
                            'confirmation'   => $confirmation,
                            'confirmed_factors' => $this->confirmedFactors($signal, $oiSig, $pivots ? $cur['close'] > $pivots['P'] : null),
                        ];
                    }
                }
            }

            // Sort: date desc, symbol, time
            usort($results, function ($a, $b) {
                $d = strcmp($b['date'], $a['date']);
                if ($d !== 0) return $d;
                $s = strcmp($a['symbol'], $b['symbol']);
                if ($s !== 0) return $s;
                return strcmp($b['candle_time'], $a['candle_time']);
            });

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' candle signals found',
                'series_expiry' => $seriesExpiry,
            ]);

        } catch (\Exception $e) {
            Log::error('OHLC Signal Error: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  CANDLE AGGREGATION
    // =========================================================

    private function aggregateIntoBuckets(array $candles, int $intervalMin): array
    {
        $buckets = [];

        foreach ($candles as $c) {
            $timeParts = explode(':', $c->candle_time);
            $h = (int)$timeParts[0];
            $m = (int)$timeParts[1];
            $totalMin  = $h * 60 + $m;
            $bucketIdx = (int) floor(($totalMin - (9 * 60 + 15)) / $intervalMin);

            if (!isset($buckets[$bucketIdx])) {
                $buckets[$bucketIdx] = [
                    'time'   => $c->candle_time,
                    'open'   => (float)$c->open,
                    'high'   => (float)$c->high,
                    'low'    => (float)$c->low,
                    'close'  => (float)$c->close,
                    'volume' => (int)($c->volume ?? 0),
                    'oi'     => (int)($c->oi ?? 0),
                ];
            } else {
                $buckets[$bucketIdx]['high']   = max($buckets[$bucketIdx]['high'],  (float)$c->high);
                $buckets[$bucketIdx]['low']    = min($buckets[$bucketIdx]['low'],   (float)$c->low);
                $buckets[$bucketIdx]['close']  = (float)$c->close;
                $buckets[$bucketIdx]['volume'] += (int)($c->volume ?? 0);
                $buckets[$bucketIdx]['oi']     = (int)($c->oi ?? 0);
            }
        }

        ksort($buckets);
        return array_values($buckets);
    }

    private function rollingAvgRange(array $buckets, int $period): array
    {
        $result = [];
        for ($i = 0; $i < count($buckets); $i++) {
            $start  = max(0, $i - $period + 1);
            $slice  = array_slice($buckets, $start, $i - $start + 1);
            $result[$i] = count($slice) > 0
                ? array_sum(array_map(fn($b) => $b['high'] - $b['low'], $slice)) / count($slice)
                : 0;
        }
        return $result;
    }

    // =========================================================
    //  CORE SIGNAL LOGIC
    // =========================================================

    private function calcCandleSignal(array $cur, array $prev, ?float $avgRange): array
    {
        $O = $cur['open'];
        $H = $cur['high'];
        $L = $cur['low'];
        $C = $cur['close'];

        $range     = $H - $L;
        $body      = abs($C - $O);
        $upperWick = $H - max($O, $C);
        $lowerWick = min($O, $C) - $L;

        $bodyPct      = $range > 0 ? round($body / $range * 100, 1) : 0;
        $upperWickPct = $range > 0 ? round($upperWick / $range * 100, 1) : 0;
        $lowerWickPct = $range > 0 ? round($lowerWick / $range * 100, 1) : 0;

        $isBullish = $C > $O;
        $isBearish = $C < $O;
        $isDoji    = $bodyPct < 10;

        $candleType = $isDoji ? 'Doji' : ($isBullish ? 'Bullish' : 'Bearish');

        // Pattern detection
        $pattern = 'Normal';
        if (!$isDoji) {
            if ($isBullish && $lowerWickPct >= 60 && $upperWickPct <= 10) {
                $pattern = 'Hammer';
            } elseif ($isBearish && $upperWickPct >= 60 && $lowerWickPct <= 10) {
                $pattern = 'ShootingStar';
            } elseif ($bodyPct >= 60) {
                $pattern = 'Strong';
            }
        }

        if ($avgRange && $avgRange > 0 && $range > $avgRange * 1.5) {
            if ($pattern === 'Normal') $pattern = 'Expansion';
        }

        // Breakout check
        $abovePrevHigh = $C > $prev['high'];
        $belowPrevLow  = $C < $prev['low'];

        $breakout = 'None';
        if ($abovePrevHigh) $breakout = 'Above Prev High';
        if ($belowPrevLow)  $breakout = 'Below Prev Low';

        // BUY conditions
        $buyReasons = [];
        if ($isBullish)     $buyReasons[] = 'Bullish candle (Close > Open)';
        if ($abovePrevHigh) $buyReasons[] = 'Breakout above Prev High (' . round($prev['high'], 0) . ')';
        if ($bodyPct >= 50) $buyReasons[] = 'Strong body (' . $bodyPct . '% of range)';
        if ($pattern === 'Hammer')                    $buyReasons[] = 'Hammer pattern';
        if ($pattern === 'Expansion' && $isBullish)   $buyReasons[] = 'Expansion candle (range +50% above avg)';

        // SELL conditions
        $sellReasons = [];
        if ($isBearish)     $sellReasons[] = 'Bearish candle (Close < Open)';
        if ($belowPrevLow)  $sellReasons[] = 'Breakdown below Prev Low (' . round($prev['low'], 0) . ')';
        if ($bodyPct >= 50) $sellReasons[] = 'Strong body (' . $bodyPct . '% of range)';
        if ($pattern === 'ShootingStar')              $sellReasons[] = 'Shooting Star pattern';
        if ($pattern === 'Expansion' && $isBearish)   $sellReasons[] = 'Expansion candle (range +50% above avg)';

        // Decision
        $buyScore  = count($buyReasons);
        $sellScore = count($sellReasons);

        $action   = 'WAIT';
        $strength = 'WEAK';
        $reasons  = [];

        if ($buyScore >= 3) {
            $action   = 'BUY';
            $reasons  = $buyReasons;
            $strength = $buyScore >= 4 ? 'STRONG' : 'MODERATE';
        } elseif ($sellScore >= 3) {
            $action   = 'SELL';
            $reasons  = $sellReasons;
            $strength = $sellScore >= 4 ? 'STRONG' : 'MODERATE';
        } elseif ($buyScore >= 2 && $buyScore > $sellScore) {
            $action   = 'BUY';
            $reasons  = $buyReasons;
            $strength = 'WEAK';
        } elseif ($sellScore >= 2 && $sellScore > $buyScore) {
            $action   = 'SELL';
            $reasons  = $sellReasons;
            $strength = 'WEAK';
        }

        return [
            'action'         => $action,
            'strength'       => $strength,
            'reasons'        => $reasons,
            'candle_type'    => $candleType,
            'pattern'        => $pattern,
            'breakout'       => $breakout,
            'body_pct'       => $bodyPct,
            'upper_wick_pct' => $upperWickPct,
            'lower_wick_pct' => $lowerWickPct,
            'is_bullish'     => $isBullish,
            'is_bearish'     => $isBearish,
        ];
    }

    // =========================================================
    //  OI SENTIMENT
    // =========================================================

    private function getOISentiment(int $ceOpen, int $ceCur, int $peOpen, int $peCur): array
    {
        if ($ceOpen <= 0 && $peOpen <= 0) {
            return ['signal' => 'N/A', 'condition' => 'No OI Data'];
        }

        $cePct = $ceOpen > 0 ? round(($ceCur - $ceOpen) / $ceOpen * 100, 2) : 0;
        $pePct = $peOpen > 0 ? round(($peCur - $peOpen) / $peOpen * 100, 2) : 0;

        $ceUp   = $cePct > 0; $ceDown = $cePct < 0;
        $peUp   = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ CE>PE']
            : ['signal' => 'BULLISH', 'condition' => 'Both ↑ PE>CE'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ CE<PE']
            : ['signal' => 'BEARISH', 'condition' => 'Both ↓ PE<CE'];

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  MULTI-FACTOR CONFIRMATION
    // =========================================================

    private function confirmedFactors(array $signal, array $oiSig, ?bool $abovePivot): array
    {
        $factors = [];
        $action  = $signal['action'];

        if ($action === 'WAIT') return [];

        if (in_array($signal['pattern'], ['Strong', 'Hammer', 'ShootingStar', 'Expansion'])) {
            $factors[] = ['name' => 'Strong Pattern', 'ok' => true];
        }

        if ($action === 'BUY'  && $signal['breakout'] === 'Above Prev High') $factors[] = ['name' => 'Breakout',  'ok' => true];
        if ($action === 'SELL' && $signal['breakout'] === 'Below Prev Low')  $factors[] = ['name' => 'Breakdown', 'ok' => true];

        $factors[] = ['name' => 'Body >' . $signal['body_pct'] . '%', 'ok' => $signal['body_pct'] >= 50];

        $oiAligned = ($action === 'BUY'  && $oiSig['signal'] === 'BULLISH')
                  || ($action === 'SELL' && $oiSig['signal'] === 'BEARISH');
        $factors[] = ['name' => 'OI Aligned', 'ok' => $oiAligned];

        if ($abovePivot !== null) {
            $pivotOk   = ($action === 'BUY' && $abovePivot) || ($action === 'SELL' && !$abovePivot);
            $factors[] = ['name' => 'Pivot Zone', 'ok' => $pivotOk];
        }

        return $factors;
    }

    private function getConfirmation(array $signal, array $oiSig, ?bool $abovePivot): string
    {
        if ($signal['action'] === 'WAIT') return 'N/A';

        $factors = $this->confirmedFactors($signal, $oiSig, $abovePivot);
        $okCount = count(array_filter($factors, fn($f) => $f['ok']));
        $total   = count($factors);

        if ($total === 0)   return 'UNCONFIRMED';
        if ($okCount >= 4)  return 'CONFIRMED';
        if ($okCount >= 2)  return 'PARTIAL';
        return 'UNCONFIRMED';
    }

    // =========================================================
    //  PIVOT HELPERS
    //
    //  IMPORTANT: No expiry_date filter on FUT here.
    //  For NIFTY weekly, FUT rows are stored with monthly expiry_date.
    //  Filtering by weekly expiry_date would return no rows.
    //  Since there's only one FUT contract active per day per symbol,
    //  fetching without expiry filter is safe and correct.
    // =========================================================

    private function getPreviousDayOHLC(string $symbol, string $tradeDate): ?array
    {
        $prevDate = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', '<', $tradeDate)
            ->where('is_missing', 0)
            ->orderByDesc('trade_date')
            ->value(DB::raw('DATE(trade_date)'));

        if (!$prevDate) return null;

        $rows = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)
            ->where('is_missing', 0)
            ->get(['high', 'low', 'close', 'interval_time']);

        if ($rows->isEmpty()) return null;

        return [
            'high'  => (float)$rows->max('high'),
            'low'   => (float)$rows->min('low'),
            'close' => (float)$rows->sortByDesc('interval_time')->first()->close,
        ];
    }

    private function calcPivots(array $ohlc): array
    {
        $H = $ohlc['high']; $L = $ohlc['low']; $C = $ohlc['close'];
        $P = ($H + $L + $C) / 3;
        return [
            'P'  => $P,
            'R1' => (2 * $P) - $L,
            'R2' => $P + ($H - $L),
            'R3' => $H + 2 * ($P - $L),
            'S1' => (2 * $P) - $H,
            'S2' => $P - ($H - $L),
            'S3' => $L - 2 * ($H - $P),
        ];
    }

    private function getPivotPosition(float $price, array $p): string
    {
        $levels = ['R3' => $p['R3'], 'R2' => $p['R2'], 'R1' => $p['R1'], 'P' => $p['P'], 'S1' => $p['S1'], 'S2' => $p['S2'], 'S3' => $p['S3']];
        foreach ($levels as $label => $level) {
            if ($level > 0 && abs($price - $level) / $level * 100 < 0.3) return "Near {$label}";
        }
        if ($price >= $p['R3']) return 'Above R3';
        if ($price >= $p['R2']) return 'R2–R3';
        if ($price >= $p['R1']) return 'R1–R2';
        if ($price >= $p['P'])  return 'P–R1';
        if ($price >= $p['S1']) return 'S1–P';
        if ($price >= $p['S2']) return 'S2–S1';
        if ($price >= $p['S3']) return 'S3–S2';
        return 'Below S3';
    }
}