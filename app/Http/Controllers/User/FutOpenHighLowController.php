<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FUT Open=High / Open=Low Analysis
 *
 * SERIES GROUPING (FIXED):
 *   Groups all expiry_dates by month, keeps the LAST expiry per month —
 *   same pattern as OIIVAuto9to12Controller / PivotPointsController.
 *   Weekly expiries (e.g. 10 Mar, 12 Mar, 17 Mar) collapse into a single
 *   "Mar 2026" entry. Data is then scoped by month, not a single date.
 */
class FutOpenHighLowController extends Controller
{
    public function analysis()
    {
        $pageTitle = 'FUT Open=High / Open=Low Signal Analysis';
        return view($this->activeTemplate . 'user.oiiv-auto.fut-open-high-low', compact('pageTitle'));
    }

    public function getSymbols()
    {
        $symbols = OptionOhlcData::distinct()
            ->whereNotNull('base_symbol')
            ->where('instrument_type', 'FUT')
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  SERIES — groups by month, one pill per month
    // =========================================================
    public function getSeries()
    {
        $today = Carbon::today()->toDateString();

        $allDates = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->distinct()
            ->orderBy('expiry_date', 'ASC')
            ->pluck('expiry_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($allDates)) {
            return response()->json(['success' => true, 'series' => [], 'current_series' => null]);
        }

        // Group by month — keep the LAST expiry per month
        $byMonth = [];
        foreach ($allDates as $d) {
            $month = substr($d, 0, 7);
            if (!isset($byMonth[$month]) || $d > $byMonth[$month]) {
                $byMonth[$month] = $d;
            }
        }

        ksort($byMonth);
        $monthlyExpiries = array_values($byMonth);

        // Current = nearest month whose last expiry >= today
        $currentSeries = null;
        foreach ($monthlyExpiries as $lastExpiry) {
            if ($lastExpiry >= $today) {
                $currentSeries = $lastExpiry;
                break;
            }
        }
        if (!$currentSeries) {
            $currentSeries = end($monthlyExpiries);
        }

        $formatted = array_map(fn($d) => [
            'value'      => $d,
            'label'      => Carbon::parse($d)->format('M Y'),
            'is_current' => $d === $currentSeries,
        ], $monthlyExpiries);

        return response()->json([
            'success'        => true,
            'series'         => $formatted,
            'current_series' => $currentSeries,
        ]);
    }

    // =========================================================
    //  ANALYZE
    // =========================================================
    public function analyze(Request $request)
    {
        try {
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $symbols      = $request->get('symbols', []);
            $seriesExpiry = $request->get('series_expiry');
            $tolerance    = (float) ($request->get('tolerance', 1));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }
            if (!$seriesExpiry) {
                return response()->json(['success' => false, 'message' => 'Series (expiry) is required', 'data' => []]);
            }

            // Scope to the entire month of the selected expiry (handles weekly sub-expiries)
            $seriesMonth = substr($seriesExpiry, 0, 7);

            // Step 1: Fetch 9:15 FUT candles scoped to series month
            $query915 = OptionOhlcData::whereBetween('trade_date', [$fromDate, $toDate])
                ->where('instrument_type', 'FUT')
                ->whereRaw("TIME(interval_time) = '09:15:00'")
                ->where('is_missing', 0)
                ->where(function ($q) use ($seriesMonth) {
                    $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                      ->orWhereNull('expiry_date');
                })
                ->select(['base_symbol', 'trade_date', 'open', 'high', 'low', 'close', 'expiry_date', 'trading_symbol']);

            if (!empty($symbols)) {
                $query915->whereIn('base_symbol', $symbols);
            }

            $candles915 = $query915->get();

            if ($candles915->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No FUT 9:15 candle data found', 'data' => []]);
            }

            // Step 2: Filter qualifying candles
            $qualifyingMap     = [];
            $qualifyingSymbols = [];
            $qualifyingDates   = [];

            foreach ($candles915 as $c) {
                $date = is_string($c->trade_date)
                    ? substr($c->trade_date, 0, 10)
                    : Carbon::parse($c->trade_date)->toDateString();

                $open = (float) $c->open;
                $high = (float) $c->high;
                $low  = (float) $c->low;

                $diffHigh = abs($open - $high);
                $diffLow  = abs($open - $low);

                if ($diffHigh <= $tolerance || $diffLow <= $tolerance) {
                    $key = $c->base_symbol . '|' . $date;
                    $qualifyingMap[$key] = [
                        'candle'     => $c,
                        'date'       => $date,
                        'open'       => $open,
                        'high'       => $high,
                        'low'        => $low,
                        'isOpenHigh' => $diffHigh <= $tolerance,
                        'isOpenLow'  => $diffLow  <= $tolerance,
                    ];
                    $qualifyingSymbols[] = $c->base_symbol;
                    $qualifyingDates[]   = $date;
                }
            }

            if (empty($qualifyingMap)) {
                return response()->json([
                    'success'       => true,
                    'data'          => [],
                    'total_records' => 0,
                    'message'       => '0 signals found — no 9:15 candle where Open=High or Open=Low within ' . $tolerance . ' pt(s)',
                    'tolerance'     => $tolerance,
                ]);
            }

            $uniqueSymbols = array_unique($qualifyingSymbols);
            $uniqueDates   = array_unique($qualifyingDates);

            // Step 3: Day-level Latest High / Low
            $dailyAgg = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->whereIn('base_symbol', $uniqueSymbols)
                ->whereIn(DB::raw("DATE(trade_date)"), $uniqueDates)
                ->where('is_missing', 0)
                ->where(function ($q) use ($seriesMonth) {
                    $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                      ->orWhereNull('expiry_date');
                })
                ->select([
                    'base_symbol',
                    DB::raw("DATE(trade_date) as trade_day"),
                    DB::raw("MAX(high) as latest_high"),
                    DB::raw("MIN(low)  as latest_low"),
                ])
                ->groupBy('base_symbol', DB::raw("DATE(trade_date)"))
                ->get()
                ->keyBy(fn($r) => $r->base_symbol . '|' . $r->trade_day);

            // Step 4: LTP = close of last candle of the day
            $ltpData = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->whereIn('base_symbol', $uniqueSymbols)
                ->whereIn(DB::raw("DATE(trade_date)"), $uniqueDates)
                ->where('is_missing', 0)
                ->where(function ($q) use ($seriesMonth) {
                    $q->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
                      ->orWhereNull('expiry_date');
                })
                ->select([
                    'base_symbol',
                    DB::raw("DATE(trade_date) as trade_day"),
                    DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(`close` ORDER BY interval_time DESC), ',', 1) as ltp"),
                ])
                ->groupBy('base_symbol', DB::raw("DATE(trade_date)"))
                ->get()
                ->keyBy(fn($r) => $r->base_symbol . '|' . $r->trade_day);

            // Step 5: Build results
            $results = [];

            foreach ($qualifyingMap as $key => $item) {
                $agg    = $dailyAgg->get($key);
                $ltpRow = $ltpData->get($key);

                $latestHigh = $agg    ? round((float) $agg->latest_high, 2) : round($item['high'], 2);
                $latestLow  = $agg    ? round((float) $agg->latest_low,  2) : round($item['low'],  2);
                $ltp        = $ltpRow ? round((float) $ltpRow->ltp,      2) : round((float) $item['candle']->close, 2);

                $open      = $item['open'];
                $change    = round($ltp - $open, 2);
                $changePct = $open != 0 ? round(($change / $open) * 100, 2) : 0.00;

                $base = [
                    'date'          => $item['date'],
                    'symbol'        => $item['candle']->base_symbol,
                    'series_expiry' => $seriesExpiry,
                    'open'          => round($open, 2),
                    'high_915'      => round($item['high'], 2),
                    'low_915'       => round($item['low'],  2),
                    'latest_high'   => $latestHigh,
                    'latest_low'    => $latestLow,
                    'ltp'           => $ltp,
                    'change'        => $change,
                    'change_pct'    => $changePct,
                ];

                if ($item['isOpenHigh']) {
                    $results[] = array_merge($base, ['signal' => 'OPEN=HIGH', 'trade_action' => 'BUY PE']);
                }
                if ($item['isOpenLow']) {
                    $results[] = array_merge($base, ['signal' => 'OPEN=LOW', 'trade_action' => 'BUY CE']);
                }
            }

            usort($results, fn($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['symbol'], $b['symbol']));

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' signals found',
                'tolerance'     => $tolerance,
            ]);

        } catch (\Exception $e) {
            Log::error('FutOpenHighLow Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }
}