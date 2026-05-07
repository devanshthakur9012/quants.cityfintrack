<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\OptionDailyOhlcData;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * CE / PE Pivot Point Analysis (Daily Version)
 * ═══════════════════════════════════════════════════════════════
 *
 * PIVOT SOURCE:
 *   Pivots calculated from the LATEST available trade_date in DB.
 *   No previous day logic whatsoever.
 *
 * SERIES GROUPING (FIXED):
 *   Groups all expiry_dates by month, keeps the LAST expiry per
 *   month — same pattern as OIIVAuto9to12Controller.
 *   This means weekly expiries (e.g. 10 Mar, 12 Mar, 17 Mar) are
 *   collapsed into a single "Mar 2026" entry showing 30 Mar 2026
 *   (the monthly/last expiry), exactly like the 9to12 page.
 */
class PivotPointsController extends Controller
{
    public function index()
    {
        $pageTitle = 'CE / PE Pivot Analysis — Daily Data';
        return view($this->activeTemplate . 'user.pivot-points.index', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS
    // =========================================================
    public function getSymbols()
    {
        $latestExpiry = $this->resolveLatestExpiry();

        $query = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('base_symbol');

        if ($latestExpiry) {
            // Scope to the same month as the latest expiry (handles weekly vs monthly)
            $seriesMonth = substr($latestExpiry, 0, 7);
            $query->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth]);
        }

        $symbols = $query->distinct()->orderBy('base_symbol')->pluck('base_symbol')->values();

        return response()->json([
            'success'        => true,
            'symbols'        => $symbols,
            'current_series' => $latestExpiry,
        ]);
    }

    // =========================================================
    //  SERIES
    //  Groups by month, keeps the LAST expiry per month —
    //  same pattern as OIIVAuto9to12Controller::getSeries()
    // =========================================================
    public function getSeries()
    {
        $today = Carbon::today()->toDateString();

        $allDates = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
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

        // Group by month — keep the LAST expiry per month (collapses weekly expiries)
        $byMonth = [];
        foreach ($allDates as $d) {
            $month = substr($d, 0, 7); // "2026-03"
            if (!isset($byMonth[$month]) || $d > $byMonth[$month]) {
                $byMonth[$month] = $d;
            }
        }

        // Sort months ascending
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
            'label'      => Carbon::parse($d)->format('d M Y'),
            'is_expired' => $d < $today,
            'is_current' => $d === $currentSeries,
        ], $monthlyExpiries);

        return response()->json([
            'success'        => true,
            'series'         => $formatted,
            'current_series' => $currentSeries,
        ]);
    }

    // =========================================================
    //  MAIN DATA
    // =========================================================
    public function getData(Request $request)
    {
        $symbols    = $request->get('symbols', []);
        $expiry     = $request->get('series_expiry');
        $strikePref = $request->get('strike_pref', 'HIGH_VOL');

        if (!$expiry) {
            $expiry = $this->resolveLatestExpiry();
        }
        if (!$expiry) {
            return response()->json(['success' => false, 'message' => 'No active series found in database', 'data' => []]);
        }

        // Scope to the entire month of the selected expiry (handles weekly sub-expiries)
        $seriesMonth = substr($expiry, 0, 7);

        // ✅ Step 1: Find the LATEST trade_date in DB for this series month
        $latestDateQuery = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
            ->where('is_missing', 0)
            ->whereNotNull('strike')
            ->whereNotNull('strike_position');

        if (!empty($symbols)) {
            $latestDateQuery->whereIn('base_symbol', $symbols);
        }

        $latestDate = $latestDateQuery->max('trade_date');

        if (!$latestDate) {
            return response()->json([
                'success' => false,
                'message' => "No data found for expiry={$expiry}.",
                'data'    => [],
            ]);
        }

        $latestDate = Carbon::parse($latestDate)->toDateString();

        // ✅ Step 2: Fetch ONLY that latest date's rows, scoped to series month
        $optQuery = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $latestDate)
            ->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
            ->where('is_missing', 0)
            ->whereNotNull('strike')
            ->whereNotNull('strike_position');

        if (!empty($symbols)) {
            $optQuery->whereIn('base_symbol', $symbols);
        }

        $allRows = $optQuery
            ->orderBy('base_symbol')
            ->orderBy('instrument_type')
            ->get([
                'base_symbol', 'instrument_type', 'trade_date',
                'open', 'high', 'low', 'close', 'oi', 'volume',
                'strike', 'expiry_date', 'trading_symbol', 'strike_position',
                'future_price', 'atm_strike', 'future_symbol',
            ]);

        if ($allRows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "No CE/PE rows found for {$latestDate}, expiry={$expiry}.",
                'data'    => [],
            ]);
        }

        // ✅ Step 3: Group by symbol → instrument_type → strike_position
        // Keep highest volume row when duplicate broker_api_id rows exist
        $grouped = [];
        foreach ($allRows as $r) {
            $pos      = $r->strike_position;
            $existing = $grouped[$r->base_symbol][$r->instrument_type][$pos] ?? null;

            if (!$existing || ($r->volume ?? 0) >= ($existing->volume ?? 0)) {
                $grouped[$r->base_symbol][$r->instrument_type][$pos] = $r;
            }
        }

        $results = [];

        foreach ($grouped as $symbol => $typeMap) {

            $ceRow = $this->resolveBestStrikeRow($typeMap['CE'] ?? [], $strikePref);
            $peRow = $this->resolveBestStrikeRow($typeMap['PE'] ?? [], $strikePref);

            if (!$ceRow && !$peRow) continue;

            // ✅ Pivots from current row's own OHLC — zero prev-day dependency
            $ceStd = $ceRow ? $this->calculateStandard($ceRow->high, $ceRow->low, $ceRow->close)  : null;
            $ceCam = $ceRow ? $this->calculateCamarilla($ceRow->high, $ceRow->low, $ceRow->close) : null;
            $peStd = $peRow ? $this->calculateStandard($peRow->high, $peRow->low, $peRow->close)  : null;
            $peCam = $peRow ? $this->calculateCamarilla($peRow->high, $peRow->low, $peRow->close) : null;

            $results[] = [
                'symbol'      => $symbol,
                'date'        => $latestDate,
                'series'      => $expiry,
                'strike_pref' => $strikePref,

                'ce_std' => $ceStd,
                'ce_cam' => $ceCam,
                'pe_std' => $peStd,
                'pe_cam' => $peCam,

                'ce' => $this->buildOptionInfo($ceRow),
                'pe' => $this->buildOptionInfo($peRow),
            ];
        }

        usort($results, fn($a, $b) => $a['symbol'] <=> $b['symbol']);

        return response()->json([
            'success'       => true,
            'data'          => $results,
            'total'         => count($results),
            'active_series' => $expiry,
            'data_date'     => $latestDate,
        ]);
    }

    // =========================================================
    //  BEST STRIKE ROW
    //  Works with ANY positions that exist — no strict ATM requirement
    // =========================================================
    private function resolveBestStrikeRow(array $strikeMap, string $pref): ?object
    {
        if (empty($strikeMap)) return null;

        // Forced specific position — use it if exists, else fall back to highest vol
        if (in_array($pref, ['ATM', 'ATM-1', 'ATM+1']) && isset($strikeMap[$pref])) {
            return $strikeMap[$pref];
        }

        // HIGH_VOL or fallback: pick highest volume from whatever positions exist
        $bestRow = null;
        $bestVol = -1;
        foreach ($strikeMap as $row) {
            $vol = $row->volume ?? 0;
            if ($vol > $bestVol) {
                $bestVol = $vol;
                $bestRow = $row;
            }
        }

        return $bestRow;
    }

    // =========================================================
    //  BUILD OPTION INFO
    // =========================================================
    private function buildOptionInfo(?object $row): array
    {
        if (!$row) {
            return [
                'found'           => false,
                'strike'          => null,
                'trading_symbol'  => null,
                'strike_position' => null,
                'open'            => null,
                'high'            => null,
                'low'             => null,
                'close'           => null,
                'oi'              => null,
                'volume'          => null,
            ];
        }

        return [
            'found'           => true,
            'strike'          => $row->strike,
            'trading_symbol'  => $row->trading_symbol,
            'strike_position' => $row->strike_position,
            'open'            => round($row->open, 2),
            'high'            => round($row->high, 2),
            'low'             => round($row->low, 2),
            'close'           => round($row->close, 2),
            'oi'              => $row->oi,
            'volume'          => $row->volume,
        ];
    }

    // =========================================================
    //  CANDLES FOR CHART MODAL
    // =========================================================
    public function getCandles(Request $request)
    {
        $symbol     = $request->get('symbol');
        $type       = $request->get('type', 'CE');
        $expiry     = $request->get('series_expiry');
        $strikePref = $request->get('strike_pref', 'HIGH_VOL');

        if (!$symbol) {
            return response()->json(['success' => false, 'message' => 'Symbol required']);
        }

        if (!$expiry) $expiry = $this->resolveLatestExpiry();

        $seriesMonth = substr($expiry, 0, 7);

        // Find latest date for this symbol/type scoped to series month
        $latestDate = OptionDailyOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
            ->where('is_missing', 0)
            ->whereNotNull('strike_position')
            ->max('trade_date');

        if (!$latestDate) {
            return response()->json(['success' => false, 'message' => 'No data found']);
        }

        $latestDate = Carbon::parse($latestDate)->toDateString();

        $rows = OptionDailyOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $latestDate)
            ->whereRaw("DATE_FORMAT(expiry_date, '%Y-%m') = ?", [$seriesMonth])
            ->where('is_missing', 0)
            ->whereNotNull('strike_position')
            ->get([
                'open', 'high', 'low', 'close', 'volume', 'oi',
                'strike', 'strike_position', 'trading_symbol',
            ]);

        if ($rows->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No candle found']);
        }

        $byPos = [];
        foreach ($rows as $r) {
            $pos      = $r->strike_position;
            $existing = $byPos[$pos] ?? null;
            if (!$existing || ($r->volume ?? 0) >= ($existing->volume ?? 0)) {
                $byPos[$pos] = $r;
            }
        }

        $bestRow = $this->resolveBestStrikeRow($byPos, $strikePref);

        if (!$bestRow) {
            return response()->json(['success' => false, 'message' => 'No row for selected strike preference']);
        }

        // ✅ Pivots from current row's own OHLC
        $standard  = $this->calculateStandard($bestRow->high, $bestRow->low, $bestRow->close);
        $camarilla = $this->calculateCamarilla($bestRow->high, $bestRow->low, $bestRow->close);

        return response()->json([
            'success'   => true,
            'candles'   => [[
                'time'   => 'Daily',
                'open'   => (float) $bestRow->open,
                'high'   => (float) $bestRow->high,
                'low'    => (float) $bestRow->low,
                'close'  => (float) $bestRow->close,
                'volume' => (int)   $bestRow->volume,
                'oi'     => (int)   $bestRow->oi,
                'strike' => $bestRow->strike,
            ]],
            'standard'  => $standard,
            'camarilla' => $camarilla,
            'type'      => $type,
            'date'      => $latestDate,
        ]);
    }

    // =========================================================
    //  PIVOT FORMULAS
    // =========================================================
    private function calculateStandard(float $H, float $L, float $C): array
    {
        $P  = ($H + $L + $C) / 3;
        $BC = ($H + $L) / 2;
        $TC = (2 * $P) - $BC;
        $R  = $H - $L;
        return [
            'P'  => round($P,             2),
            'BC' => round($BC,            2),
            'TC' => round($TC,            2),
            'R1' => round(2*$P - $L,      2),
            'R2' => round($P + $R,        2),
            'R3' => round($H + 2*($P-$L), 2),
            'S1' => round(2*$P - $H,      2),
            'S2' => round($P - $R,        2),
            'S3' => round($L - 2*($H-$P), 2),
        ];
    }

    private function calculateCamarilla(float $H, float $L, float $C): array
    {
        $R = $H - $L;
        return [
            'R4' => round($C + $R*1.1/2,  2),
            'R3' => round($C + $R*1.1/4,  2),
            'R2' => round($C + $R*1.1/6,  2),
            'R1' => round($C + $R*1.1/12, 2),
            'S1' => round($C - $R*1.1/12, 2),
            'S2' => round($C - $R*1.1/6,  2),
            'S3' => round($C - $R*1.1/4,  2),
            'S4' => round($C - $R*1.1/2,  2),
        ];
    }

    // =========================================================
    //  HELPERS
    // =========================================================
    private function resolveLatestExpiry(): ?string
    {
        $today = Carbon::today()->toDateString();

        // Get all future/current expiry dates, group by month, pick last per month
        $allDates = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $today)
            ->distinct()
            ->orderBy('expiry_date', 'ASC')
            ->pluck('expiry_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (!empty($allDates)) {
            // Group by month, keep last per month, return the nearest month's last expiry
            $byMonth = [];
            foreach ($allDates as $d) {
                $month = substr($d, 0, 7);
                if (!isset($byMonth[$month]) || $d > $byMonth[$month]) {
                    $byMonth[$month] = $d;
                }
            }
            ksort($byMonth);
            return reset($byMonth); // nearest future month's last expiry
        }

        // Fallback: most recent past expiry (last month's last expiry)
        $allPast = OptionDailyOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->distinct()
            ->orderBy('expiry_date', 'DESC')
            ->pluck('expiry_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($allPast)) return null;

        $byMonth = [];
        foreach ($allPast as $d) {
            $month = substr($d, 0, 7);
            if (!isset($byMonth[$month]) || $d > $byMonth[$month]) {
                $byMonth[$month] = $d;
            }
        }
        return reset($byMonth);
    }
}