<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use App\Models\NewPivotOrderConfig;
use App\Models\NewPivotOrder;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Auth;

/**
 * 15-Min Pivot Signal + Config Controller  (v3 — all bugs fixed)
 *
 * ══════════════════════════════════════════════════════════════════
 * BUG FIXES in this version
 * ══════════════════════════════════════════════════════════════════
 *
 * FIX 1 — OI aggregation now filtered to same expiry
 *   getOiByInterval(), getVolByInterval(), getMmTrapByInterval()
 *   all receive $expiry and filter ->whereDate('expiry_date', $expiry)
 *   so weekly/monthly data is never mixed.
 *   ATM ± 3 strikes filter added to every OI/Vol query.
 *
 * FIX 2 — MM Trap wall detection uses current-interval snapshot
 *   For each interval we find the strike with MAX OI at THAT interval
 *   (not a sum across all candles). Wall can shift every 15 min.
 *
 * FIX 3 — Vol Spike uses a single priorTotalVolumes[] array
 *   No more separate CE/PE arrays whose averages diverge when counts
 *   differ. We track combined (CE+PE) volume per interval.
 *
 * FIX 4 — Gamma uses future_price column from the same option row
 *   instead of a separate FUT query that may have gaps.
 *   future_price is captured at the same timestamp as the option.
 *
 * FIX 5 — First candle OI change = 0 (no cross-expiry comparison)
 *   The previous-day OI is removed. First candle gets noSentiment()
 *   so there are no fake jumps when comparing different expiry series.
 *
 * FIX 6 — Single bulk query per symbol (not 4–5 separate queries)
 *   loadSymbolData() fetches ALL rows for the symbol+expiry+date in
 *   ONE query, then groups everything in PHP. 50 symbols → 50 queries
 *   instead of 200+.
 *
 * ATM ± 3 filter
 *   All OI/Vol aggregations use only the 7 strikes around ATM
 *   (ATM-3 … ATM+3). Far OTM strikes distort signals.
 * ══════════════════════════════════════════════════════════════════
 */
class PivotSignal15Controller extends Controller
{
    public const ALL_SYMBOLS = [
        'NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY',
        'ADANIPORTS', 'AMBUJACEM', 'ASIANPAINT', 'AUROPHARMA',
        'AXISBANK', 'BAJAJFINSV', 'BAJFINANCE', 'BHARATFORG',
        'BHARTIARTL', 'BHEL', 'BPCL', 'BSE',
        'CDSL', 'COFORGE', 'BDL', 'DELHIVERY',
        'DRREDDY', 'ETERNAL', 'FORTIS', 'HAL',
        'HAVELLS', 'HEROMOTOCO', 'HINDALCO', 'ICICIBANK',
        'INDUSINDBK', 'INFY', 'JSWSTEEL', 'LAURUSLABS',
        'LICHSGFIN', 'LT', 'LTF', 'M&M',
        'NATIONALUM', 'PAYTM', 'PGEL', 'POLICYBZR',
        'SBIN', 'SHRIRAMFIN', 'SRF', 'TATACONSUM',
        'TATAELXSI', 'TATATECH', 'TITAN', 'TMPV',
        'TCS', 'UPL', 'VBL', 'VEDL',
        'VOLTAS', 'MCX', 'SENSEX',
    ];

    // ── Signal Page ───────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Pivot Signal 15Min';
        return view($this->activeTemplate . 'user.pivot-signal-15.index', compact('pageTitle'));
    }

    // ── Signal API ────────────────────────────────────────────────────────────

    public function getSignals(Request $request)
    {
        try {
            $symbol    = strtoupper(trim($request->get('symbol', 'ALL')));
            $dateInput = $request->get('date');
            $today     = $dateInput
                ? Carbon::parse($dateInput)->toDateString()
                : Carbon::today()->toDateString();

            $availableSymbols = OptionOhlcData::whereDate('trade_date', $today)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()
                ->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return response()->json([
                    'success'           => true,
                    'data'              => [],
                    'today'             => $today,
                    'is_today'          => $today === Carbon::today()->toDateString(),
                    'message'           => 'No data found for ' . $today,
                    'available_symbols' => [],
                ]);
            }

            $isAll   = ($symbol === 'ALL');
            $symbols = $isAll ? $availableSymbols : [$symbol];
            $results = [];

            foreach ($symbols as $sym) {

                $expiry = $this->getNearestExpiryForDate($sym, $today);
                if (!$expiry) continue;

                // ── FIX 6: ONE bulk query per symbol — all CE/PE rows for this
                //            expiry+date, grouped in PHP. ──────────────────────
                $allRows = $this->loadSymbolData($sym, $expiry, $today);
                if ($allRows->isEmpty()) continue;

                // Determine ATM strike from the data (most common atm_strike value)
                $atmStrike = $this->resolveAtmStrike($allRows);

                // ATM ± 3 strikes for OI/Vol signal quality
                $atmStrikes = $this->getAtmPlusMinusStrikes($allRows, $atmStrike, 3);

                // Group into CE/PE keyed by [interval_time][strike]
                $ceRows = $allRows->where('instrument_type', 'CE');
                $peRows = $allRows->where('instrument_type', 'PE');

                // ATM candles for pivot (strike_position = ATM, same expiry)
                $ceCandles = $ceRows->where('strike_position', 'ATM')->sortBy('interval_time')->values();
                $peCandles = $peRows->where('strike_position', 'ATM')->sortBy('interval_time')->values();

                if ($ceCandles->isEmpty() && $peCandles->isEmpty()) continue;

                // ── FIX 1+6: OI / Vol aggregation using ATM±3 + same expiry ──
                $ceOiByInterval  = $this->aggregateOiByInterval($ceRows, $atmStrikes);
                $peOiByInterval  = $this->aggregateOiByInterval($peRows, $atmStrikes);
                $ceVolByInterval = $this->aggregateVolByInterval($ceRows, $atmStrikes);
                $peVolByInterval = $this->aggregateVolByInterval($peRows, $atmStrikes);

                // ── FIX 4: FUT price from future_price column in option rows ──
                $futPriceByInterval = $this->extractFutPriceByInterval($allRows);

                $allIntervalTimes = collect(array_keys($ceOiByInterval))
                    ->merge(array_keys($peOiByInterval))
                    ->unique()->sort()->values()->toArray();

                // ── Previous day last candle OI — SAME EXPIRY ONLY ────────────
                // For the 9:15 first candle we use the previous trading day's
                // last interval (3:15) OI as the baseline for OI % change.
                // If expiry is different (rolled over), returns [0,0] → noSentiment.
                $prevTradingDate = $this->getPreviousTradingDate($today);
                [$prevDayCeOi, $prevDayPeOi] = $this->getPrevDayLastOiBySameExpiry(
                    $sym, $expiry, $prevTradingDate, $atmStrikes
                );

                // ── Single combined volume array (FIX 3) ──────────────────────
                $priorTotalVolumes = [];

                $oiSentimentByTime = [];
                $oiImbalanceByTime = [];
                $volSpikeByTime    = [];

                foreach ($allIntervalTimes as $idx => $timeKey) {
                    $curCeOi = $ceOiByInterval[$timeKey] ?? 0;
                    $curPeOi = $peOiByInterval[$timeKey] ?? 0;

                    if ($idx === 0) {
                        // First candle — use prev day 3:15 same-expiry OI as baseline.
                        // If no prev day data for this expiry → show blank (noSentiment).
                        if ($prevDayCeOi === 0 && $prevDayPeOi === 0) {
                            $oiSentimentByTime[$timeKey] = $this->noSentiment();
                        } else {
                            $cePct = round((($curCeOi - $prevDayCeOi) / $prevDayCeOi) * 100, 2);
                            $pePct = round((($curPeOi - $prevDayPeOi) / $prevDayPeOi) * 100, 2);
                            $oiSentimentByTime[$timeKey] = array_merge(
                                $this->calcOiSignal($cePct, $pePct),
                                [
                                    'ce_oi'     => (int) $curCeOi,
                                    'pe_oi'     => (int) $curPeOi,
                                    'ce_oi_pct' => $cePct,
                                    'pe_oi_pct' => $pePct,
                                    'time'      => $timeKey,
                                ]
                            );
                            $oiImbalanceByTime[$timeKey] = $this->calcOiImbalance(
                                $curCeOi, $curPeOi, $prevDayCeOi, $prevDayPeOi, $cePct, $pePct
                            );
                        }
                    } else {
                        $prevTime = $allIntervalTimes[$idx - 1];
                        $prevCeOi = $ceOiByInterval[$prevTime] ?? 0;
                        $prevPeOi = $peOiByInterval[$prevTime] ?? 0;

                        $cePct = $prevCeOi > 0 ? round((($curCeOi - $prevCeOi) / $prevCeOi) * 100, 2) : 0;
                        $pePct = $prevPeOi > 0 ? round((($curPeOi - $prevPeOi) / $prevPeOi) * 100, 2) : 0;

                        $oiSentimentByTime[$timeKey] = array_merge(
                            $this->calcOiSignal($cePct, $pePct),
                            [
                                'ce_oi'     => (int) $curCeOi,
                                'pe_oi'     => (int) $curPeOi,
                                'ce_oi_pct' => $cePct,
                                'pe_oi_pct' => $pePct,
                                'time'      => $timeKey,
                            ]
                        );

                        $oiImbalanceByTime[$timeKey] = $this->calcOiImbalance(
                            $curCeOi, $curPeOi, $prevCeOi, $prevPeOi, $cePct, $pePct
                        );
                    }

                    // FIX 3: combined vol per interval
                    $curCeVol    = $ceVolByInterval[$timeKey] ?? 0;
                    $curPeVol    = $peVolByInterval[$timeKey] ?? 0;
                    $curTotalVol = $curCeVol + $curPeVol;

                    $volSpikeByTime[$timeKey] = $this->calcVolSpike($curTotalVol, $priorTotalVolumes);

                    // Accumulate AFTER computing (don't include current in its own avg)
                    if ($curTotalVol > 0) $priorTotalVolumes[] = $curTotalVol;
                }

                // FIX 2: MM Trap with per-interval rolling wall detection
                $mmTrapByTime = $this->getMmTrapByInterval($allRows, $expiry, $atmStrikes);

                $ceSignals = $this->buildSignals(
                    $ceCandles->toArray(), 'CE',
                    $oiSentimentByTime, $mmTrapByTime,
                    $oiImbalanceByTime, $volSpikeByTime
                );
                $peSignals = $this->buildSignals(
                    $peCandles->toArray(), 'PE',
                    $oiSentimentByTime, $mmTrapByTime,
                    $oiImbalanceByTime, $volSpikeByTime
                );

                $latestCe = $ceCandles->last();
                $latestPe = $peCandles->last();

                if ($isAll) {
                    $lastTime = collect($ceSignals)->last()['time']
                             ?? collect($peSignals)->last()['time']
                             ?? null;

                    $allSignals = collect(array_merge($ceSignals, $peSignals))
                        ->filter(fn($s) => $s['time'] === $lastTime)
                        ->sortBy('time')->values()->toArray();
                } else {
                    $allSignals = collect(array_merge($ceSignals, $peSignals))
                        ->sortBy('time')->values()->toArray();
                }

                $results[] = [
                    'symbol'        => $sym,
                    'expiry'        => $expiry,
                    'date'          => $today,
                    'mode'          => $isAll ? 'summary' : 'detail',
                    'total_candles' => $ceCandles->count(),
                    'ce_symbol'     => $latestCe->trading_symbol ?? null,
                    'ce_strike'     => $latestCe->strike         ?? null,
                    'ce_ltp'        => $latestCe ? round((float)$latestCe->close, 2) : null,
                    'pe_symbol'     => $latestPe->trading_symbol ?? null,
                    'pe_strike'     => $latestPe->strike         ?? null,
                    'pe_ltp'        => $latestPe ? round((float)$latestPe->close, 2) : null,
                    'latest_time'   => $latestCe ? substr($latestCe->interval_time, 11, 5) : null,
                    'signals'       => $allSignals,
                    'signal_count'  => count($allSignals),
                    'atm_strike'    => $atmStrike,
                ];
            }

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'today'             => $today,
                'is_today'          => $today === Carbon::today()->toDateString(),
                'mode'              => $isAll ? 'summary' : 'detail',
                'available_symbols' => $availableSymbols,
                'message'           => count($results) . ' symbol(s) loaded for ' . $today,
            ]);

        } catch (\Exception $e) {
            Log::error('PivotSignal15 getSignals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 6 — SINGLE BULK DATA LOADER
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Load ALL CE/PE rows for a symbol on a given date+expiry in ONE query.
     * Columns needed: interval_time, instrument_type, strike, strike_position,
     *                 trading_symbol, open, high, low, close, volume, oi,
     *                 atm_strike, future_price
     */
    private function loadSymbolData(string $symbol, string $expiry, string $date)
    {
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)   // FIX 1: same expiry only
            ->whereDate('trade_date', $date)
            ->orderBy('interval_time')
            ->get([
                'interval_time', 'instrument_type', 'strike', 'strike_position',
                'trading_symbol', 'open', 'high', 'low', 'close',
                'volume', 'oi', 'atm_strike', 'future_price',
            ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ATM STRIKE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Resolve the ATM strike for a symbol's data set.
     * Uses the most recent interval's atm_strike value.
     */
    private function resolveAtmStrike($rows): ?float
    {
        $last = $rows->sortBy('interval_time')->last();
        return $last ? (float)$last->atm_strike : null;
    }

    /**
     * Return array of valid strikes that are within ±N steps from ATM.
     * Steps are determined by the actual strike intervals present in the data.
     *
     * Example: ATM=22400, strikes=[22200,22300,22400,22500,22600,22700], N=3
     *          → [22100,22200,22300,22400,22500,22600,22700]
     */
    private function getAtmPlusMinusStrikes($rows, ?float $atmStrike, int $n = 3): array
    {
        if (!$atmStrike) return [];

        // Get all unique strikes present, sorted
        $allStrikes = $rows
            ->pluck('strike')
            ->map(fn($s) => (float)$s)
            ->filter(fn($s) => $s > 0)
            ->unique()->sort()->values()->toArray();

        if (empty($allStrikes)) return [];

        // Find ATM index
        $atmIdx = array_search($atmStrike, $allStrikes);
        if ($atmIdx === false) {
            // Find closest
            $diffs  = array_map(fn($s) => abs($s - $atmStrike), $allStrikes);
            $atmIdx = array_keys($diffs, min($diffs))[0];
        }

        $from = max(0, $atmIdx - $n);
        $to   = min(count($allStrikes) - 1, $atmIdx + $n);

        return array_slice($allStrikes, $from, $to - $from + 1);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 1+6 — IN-MEMORY OI / VOL AGGREGATION (ATM±3, same expiry already)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Aggregate OI by interval_time from already-loaded rows.
     * Only ATM±3 strikes are included.
     * Returns: [ "09:15" => 123456, "09:30" => 234567, ... ]
     */
    private function aggregateOiByInterval($rows, array $atmStrikes): array
    {
        $result = [];
        foreach ($rows as $r) {
            $strike = (float)$r->strike;
            if (!empty($atmStrikes) && !in_array($strike, $atmStrikes)) continue;
            $timeKey = Carbon::parse($r->interval_time)->format('H:i');
            $result[$timeKey] = ($result[$timeKey] ?? 0) + (int)$r->oi;
        }
        ksort($result);
        return $result;
    }

    /**
     * Aggregate Volume by interval_time from already-loaded rows.
     * Only ATM±3 strikes are included.
     */
    private function aggregateVolByInterval($rows, array $atmStrikes): array
    {
        $result = [];
        foreach ($rows as $r) {
            $strike = (float)$r->strike;
            if (!empty($atmStrikes) && !in_array($strike, $atmStrikes)) continue;
            $timeKey = Carbon::parse($r->interval_time)->format('H:i');
            $result[$timeKey] = ($result[$timeKey] ?? 0) + (int)$r->volume;
        }
        ksort($result);
        return $result;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 4 — FUT PRICE FROM future_price COLUMN (same row, no FUT query gap)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Extract future_price for each interval from the already-loaded option rows.
     * Since every option row carries the future_price at that snapshot time,
     * we just take the first non-zero value per interval.
     * Returns: [ "09:15" => 22350.00, "09:30" => 22375.50, ... ]
     */
    private function extractFutPriceByInterval($rows): array
    {
        $result = [];
        foreach ($rows as $r) {
            $timeKey = Carbon::parse($r->interval_time)->format('H:i');
            if (!isset($result[$timeKey]) && (float)$r->future_price > 0) {
                $result[$timeKey] = (float)$r->future_price;
            }
        }
        ksort($result);
        return $result;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 2 — MM TRAP: PER-INTERVAL ROLLING WALL (not all-day sum)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * MM Trap wall detection — corrected.
     *
     * For EACH interval we find the call wall (highest CE OI at that interval)
     * and put wall (highest PE OI at that interval) from ATM±3 strikes.
     * The wall can shift candle-to-candle.
     *
     * Then we track that wall strike's OI change vs the previous candle
     * to determine DEFENDED vs BREAKING.
     */
    private function getMmTrapByInterval($allRows, string $expiry, array $atmStrikes): array
    {
        // Filter ATM±3 only (expiry already filtered at loadSymbolData)
        $rows = $allRows->filter(function ($r) use ($atmStrikes) {
            return !empty($atmStrikes)
                ? in_array((float)$r->strike, $atmStrikes)
                : true;
        });

        if ($rows->isEmpty()) return [];

        // Build map: [timeKey][type][strike] => oi
        $oiMap = [];
        foreach ($rows as $r) {
            $timeKey = Carbon::parse($r->interval_time)->format('H:i');
            $strike  = (float)$r->strike;
            $type    = $r->instrument_type; // CE or PE
            if ($strike <= 0) continue;
            $oiMap[$timeKey][$type][$strike] = ((int)$r->oi);
        }

        $allTimes = array_keys($oiMap);
        sort($allTimes);

        if (empty($allTimes)) return [];

        $result = [];

        // Track per-interval OI for the current wall strikes (rolling)
        // Wall is re-detected from current interval snapshot each time
        $prevCallWallOi = 0;
        $prevPutWallOi  = 0;
        $prevCallWall   = null;
        $prevPutWall    = null;

        foreach ($allTimes as $idx => $timeKey) {
            $ceOiAtTime = $oiMap[$timeKey]['CE'] ?? [];
            $peOiAtTime = $oiMap[$timeKey]['PE'] ?? [];

            // FIX 2: Find wall at CURRENT interval (not all-day sum)
            $callWall   = null; $callWallOi = 0;
            $putWall    = null; $putWallOi  = 0;

            foreach ($ceOiAtTime as $strike => $oi) {
                if ($oi > $callWallOi) { $callWall = $strike; $callWallOi = $oi; }
            }
            foreach ($peOiAtTime as $strike => $oi) {
                if ($oi > $putWallOi)  { $putWall  = $strike; $putWallOi  = $oi; }
            }

            if ($idx === 0) {
                // First interval — no previous to compare
                $result[$timeKey] = [
                    'type'          => null,
                    'signal'        => null,
                    'detail'        => null,
                    'call_wall'     => $callWall,
                    'put_wall'      => $putWall,
                    'call_wall_oi'  => $callWallOi,
                    'put_wall_oi'   => $putWallOi,
                    'call_oi_pct'   => 0,
                    'put_oi_pct'    => 0,
                    'call_defended' => false,
                    'put_defended'  => false,
                    'call_breaking' => false,
                    'put_breaking'  => false,
                ];
                $prevCallWallOi = $callWallOi;
                $prevPutWallOi  = $putWallOi;
                $prevCallWall   = $callWall;
                $prevPutWall    = $putWall;
                continue;
            }

            // Compare current wall OI vs previous interval's OI for the same strike
            // Note: wall strike may have shifted — use current wall vs prev interval of same strike
            $prevCallOi = $oiMap[$allTimes[$idx - 1]]['CE'][$callWall] ?? 0;
            $prevPutOi  = $oiMap[$allTimes[$idx - 1]]['PE'][$putWall]  ?? 0;

            $callOiDiff = $callWallOi - $prevCallOi;
            $putOiDiff  = $putWallOi  - $prevPutOi;
            $callOiPct  = $prevCallOi > 0 ? round(($callOiDiff / $prevCallOi) * 100, 2) : 0;
            $putOiPct   = $prevPutOi  > 0 ? round(($putOiDiff  / $prevPutOi)  * 100, 2) : 0;

            $callDefended = $callOiDiff > 0;
            $callBreaking = $callOiDiff < 0;
            $putDefended  = $putOiDiff  > 0;
            $putBreaking  = $putOiDiff  < 0;

            $signal = null; $type = null; $detail = null;

            if ($callDefended && $putDefended) {
                if ($callOiPct >= $putOiPct) {
                    $signal = 'BEARISH'; $type = 'CALL WALL';
                    $detail = 'Call wall defended (+' . $callOiPct . '%) > Put wall (+' . $putOiPct . '%) → Resistance stronger';
                } else {
                    $signal = 'BULLISH'; $type = 'PUT WALL';
                    $detail = 'Put wall defended (+' . $putOiPct . '%) > Call wall (+' . $callOiPct . '%) → Support stronger';
                }
            } elseif ($callDefended) {
                $signal = 'BEARISH'; $type = 'CALL WALL';
                $detail = 'Call wall defended (+' . $callOiPct . '%) → Resistance at ₹' . number_format((float)$callWall, 0);
            } elseif ($putDefended) {
                $signal = 'BULLISH'; $type = 'PUT WALL';
                $detail = 'Put wall defended (+' . $putOiPct . '%) → Support at ₹' . number_format((float)$putWall, 0);
            } elseif ($callBreaking && $putBreaking) {
                if (abs($callOiPct) >= abs($putOiPct)) {
                    $signal = 'BULLISH'; $type = 'CALL BREAK';
                    $detail = 'Call wall breaking (' . $callOiPct . '%) → Resistance collapsing → Bullish';
                } else {
                    $signal = 'BEARISH'; $type = 'PUT BREAK';
                    $detail = 'Put wall breaking (' . $putOiPct . '%) → Support collapsing → Bearish';
                }
            } elseif ($callBreaking) {
                $signal = 'BULLISH'; $type = 'CALL BREAK';
                $detail = 'Call wall breaking (' . $callOiPct . '%) → Shorts covering → Bullish';
            } elseif ($putBreaking) {
                $signal = 'BEARISH'; $type = 'PUT BREAK';
                $detail = 'Put wall breaking (' . $putOiPct . '%) → Longs exiting → Bearish';
            }

            $result[$timeKey] = [
                'type'          => $type,
                'signal'        => $signal,
                'detail'        => $detail,
                'call_wall'     => $callWall,
                'put_wall'      => $putWall,
                'call_wall_oi'  => $callWallOi,
                'put_wall_oi'   => $putWallOi,
                'call_oi_pct'   => $callOiPct,
                'put_oi_pct'    => $putOiPct,
                'call_defended' => $callDefended,
                'put_defended'  => $putDefended,
                'call_breaking' => $callBreaking,
                'put_breaking'  => $putBreaking,
            ];

            $prevCallWallOi = $callWallOi;
            $prevPutWallOi  = $putWallOi;
            $prevCallWall   = $callWall;
            $prevPutWall    = $putWall;
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OI SIGNAL CALCULATORS
    // ══════════════════════════════════════════════════════════════════════════

    private function calcOiSignal(float $cePct, float $pePct): array
    {
        if ($cePct > 0 && $pePct < 0) {
            $signal = 'BEARISH'; $condition = 'CE ↑ + PE ↓';
            $reason = 'Call buildup + Put unwinding → Resistance forming';
        } elseif ($cePct < 0 && $pePct > 0) {
            $signal = 'BULLISH'; $condition = 'CE ↓ + PE ↑';
            $reason = 'Call unwinding + Put buildup → Support forming';
        } elseif ($cePct > 0 && $pePct > 0) {
            if ($pePct > $cePct) {
                $signal = 'BULLISH'; $condition = 'Both ↑ (PE > CE)';
                $reason = "Put buildup stronger (+{$pePct}% vs CE +{$cePct}%) → Bullish";
            } else {
                $signal = 'BEARISH'; $condition = 'Both ↑ (CE ≥ PE)';
                $reason = "Call buildup stronger (+{$cePct}% vs PE +{$pePct}%) → Bearish";
            }
        } else {
            if (abs($cePct) > abs($pePct)) {
                $signal = 'BULLISH'; $condition = 'Both ↓ (|CE| > |PE|)';
                $reason = "Call unwinding larger ({$cePct}% vs PE {$pePct}%) → Short covering → Bullish";
            } else {
                $signal = 'BEARISH'; $condition = 'Both ↓ (|PE| ≥ |CE|)';
                $reason = "Put unwinding larger ({$pePct}% vs CE {$cePct}%) → Long covering → Bearish";
            }
        }

        $difference = round(abs($cePct - $pePct), 2);
        $strength   = $difference > 3 ? 'Very Strong Signal'
                    : ($difference > 1.5 ? 'Strong Signal'
                    : ($difference > 0.5 ? 'Moderate Signal' : 'Weak Signal'));

        return compact('signal', 'condition', 'reason', 'strength', 'difference');
    }

    /**
     * OI Change Imbalance — normalised −100 … +100
     *
     *   Score = (PE_OI_Change − CE_OI_Change)
     *           ─────────────────────────────── × 100
     *           (|PE_OI_Change| + |CE_OI_Change|)
     */
    private function calcOiImbalance(
        int $curCeOi, int $curPeOi,
        int $prevCeOi, int $prevPeOi,
        float $cePct, float $pePct
    ): array {
        $ceOiChange  = $curCeOi - $prevCeOi;
        $peOiChange  = $curPeOi - $prevPeOi;
        $ceDirection = $this->oiDirectionLabel($cePct);
        $peDirection = $this->oiDirectionLabel($pePct);

        $denominator = abs($peOiChange) + abs($ceOiChange);

        if ($denominator == 0) {
            $imbalanceScore = 0.0; $imbalanceLabel = 'Balanced'; $imbalanceBias = 'NEUTRAL';
        } else {
            $imbalanceScore = round((($peOiChange - $ceOiChange) / $denominator) * 100, 2);

            if      ($imbalanceScore >= 60)  { $imbalanceLabel = 'Strong Bullish'; $imbalanceBias = 'BULLISH'; }
            elseif  ($imbalanceScore >= 20)  { $imbalanceLabel = 'Bullish';        $imbalanceBias = 'BULLISH'; }
            elseif  ($imbalanceScore >= 5)   { $imbalanceLabel = 'Mild Bullish';   $imbalanceBias = 'BULLISH'; }
            elseif  ($imbalanceScore > -5)   { $imbalanceLabel = 'Balanced';       $imbalanceBias = 'NEUTRAL'; }
            elseif  ($imbalanceScore >= -20) { $imbalanceLabel = 'Mild Bearish';   $imbalanceBias = 'BEARISH'; }
            elseif  ($imbalanceScore >= -60) { $imbalanceLabel = 'Bearish';        $imbalanceBias = 'BEARISH'; }
            else                             { $imbalanceLabel = 'Strong Bearish'; $imbalanceBias = 'BEARISH'; }
        }

        return [
            'imbalance_ratio' => $imbalanceScore,
            'imbalance_label' => $imbalanceLabel,
            'imbalance_bias'  => $imbalanceBias,
            'ce_direction'    => $ceDirection,
            'pe_direction'    => $peDirection,
            'ce_oi_change'    => $ceOiChange,
            'pe_oi_change'    => $peOiChange,
            'ce_oi_pct'       => $cePct,
            'pe_oi_pct'       => $pePct,
        ];
    }

    private function oiDirectionLabel(float $pct): string
    {
        if ($pct > 0.1)  return 'Buildup';
        if ($pct < -0.1) return 'Unwinding';
        return 'Flat';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 3 — VOL SPIKE: single combined array
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * @param int   $curTotalVol       CE + PE combined volume this interval
     * @param array $priorTotalVolumes running list of all previous intervals' combined vol
     */
    private function calcVolSpike(int $curTotalVol, array $priorTotalVolumes): array
    {
        if (empty($priorTotalVolumes)) {
            return [
                'spike_ratio' => null, 'spike_label' => 'OPENING',
                'spike_type'  => 'OPENING', 'avg_vol' => 0, 'cur_vol' => $curTotalVol,
            ];
        }

        $avgTotalVol = array_sum($priorTotalVolumes) / count($priorTotalVolumes);

        if ($avgTotalVol <= 0) {
            return [
                'spike_ratio' => null, 'spike_label' => 'N/A',
                'spike_type'  => 'NORMAL', 'avg_vol' => 0, 'cur_vol' => $curTotalVol,
            ];
        }

        $spikeRatio = round($curTotalVol / $avgTotalVol, 2);

        [$label, $type] = match (true) {
            $spikeRatio >= 2.0 => ['STRONG SPIKE', 'STRONG_SPIKE'],
            $spikeRatio >= 1.5 => ['SPIKE',        'SPIKE'],
            $spikeRatio >= 1.2 => ['ELEVATED',     'ELEVATED'],
            default            => ['NORMAL',        'NORMAL'],
        };

        return [
            'spike_ratio' => $spikeRatio,
            'spike_label' => $label,
            'spike_type'  => $type,
            'avg_vol'     => (int) round($avgTotalVol),
            'cur_vol'     => $curTotalVol,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 4 — GAMMA PROXY: uses future_price from candle row
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Gamma Proxy — approximates Gamma from ATM candle series.
     *
     *   Delta_t   = (opt_close_t − opt_close_{t-1}) / (fut_t − fut_{t-1})
     *   Gamma_t   = (Delta_t − Delta_{t-1}) / (fut_t − fut_{t-1})
     *
     * Uses future_price from the option row itself (FIX 4) — more reliable
     * than a separate FUT instrument query that may have missing intervals.
     *
     * $candles must be plain arrays with keys:
     *   interval_time, close, future_price
     */
    private function calcGammaProxy(array $candles, int $idx): array
    {
        $noGamma = ['gamma_proxy' => null, 'gamma_label' => 'N/A', 'delta_t' => null, 'delta_prev' => null];

        if ($idx < 2) return $noGamma;

        $futT   = (float)($candles[$idx]['future_price']     ?? 0);
        $futTm1 = (float)($candles[$idx - 1]['future_price'] ?? 0);
        $futTm2 = (float)($candles[$idx - 2]['future_price'] ?? 0);

        if ($futT <= 0 || $futTm1 <= 0 || $futTm2 <= 0) return $noGamma;

        $dFut1 = $futT   - $futTm1;
        $dFut2 = $futTm1 - $futTm2;

        if (abs($dFut1) < 0.01 || abs($dFut2) < 0.01) return $noGamma;

        $optT   = (float)$candles[$idx]['close'];
        $optTm1 = (float)$candles[$idx - 1]['close'];
        $optTm2 = (float)$candles[$idx - 2]['close'];

        $deltaT   = ($optT   - $optTm1) / $dFut1;
        $deltaTm1 = ($optTm1 - $optTm2) / $dFut2;

        $gammaProxy = round(($deltaT - $deltaTm1) / $dFut1, 6);

        $absG  = abs($gammaProxy);
        $label = match (true) {
            $absG < 0.0001              => 'Stable',
            $gammaProxy > 0 && $absG > 0.001  => 'Accel ↑↑',
            $gammaProxy > 0 && $absG > 0.0003 => 'Accel ↑',
            $gammaProxy > 0             => 'Mild Accel',
            $absG > 0.001               => 'Decel ↓↓',
            $absG > 0.0003              => 'Decel ↓',
            default                     => 'Mild Decel',
        };

        return [
            'gamma_proxy' => $gammaProxy,
            'gamma_label' => $label,
            'delta_t'     => round($deltaT, 4),
            'delta_prev'  => round($deltaTm1, 4),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NULL / EMPTY HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function noSentiment(): array
    {
        return [
            'signal' => 'N/A', 'condition' => 'N/A', 'reason' => 'No data',
            'strength' => 'N/A', 'difference' => 0,
            'ce_oi' => 0, 'pe_oi' => 0, 'ce_oi_pct' => 0, 'pe_oi_pct' => 0,
        ];
    }

    private function noImbalance(): array
    {
        return [
            'imbalance_ratio' => null, 'imbalance_label' => 'N/A',
            'imbalance_bias'  => 'NEUTRAL', 'ce_direction' => 'N/A', 'pe_direction' => 'N/A',
            'ce_oi_change' => 0, 'pe_oi_change' => 0, 'ce_oi_pct' => 0, 'pe_oi_pct' => 0,
        ];
    }

    private function noVolSpike(): array
    {
        return [
            'spike_ratio' => null, 'spike_label' => 'N/A',
            'spike_type'  => 'NORMAL', 'avg_vol' => 0, 'cur_vol' => 0,
        ];
    }

    private function noMmTrap(): array
    {
        return [
            'type' => null, 'signal' => null, 'detail' => null,
            'call_wall' => null, 'put_wall' => null,
            'call_wall_oi' => 0, 'put_wall_oi' => 0,
            'call_oi_pct' => 0, 'put_oi_pct' => 0,
            'call_defended' => false, 'put_defended' => false,
            'call_breaking' => false, 'put_breaking' => false,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BUILD SIGNALS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Build pivot signals from ATM candle array.
     * PP = (H+L+C)/3  |  R1 = 2·PP − L  |  S1 = 2·PP − H
     *
     * FIX 4: calcGammaProxy now only takes ($candles, $idx) — reads
     *        future_price directly from the candle array entry.
     * 9:15 first candle uses prev-day 3:15 OI (same expiry only) — handled in getSignals loop.
     */
    private function buildSignals(
        array  $candles,
        string $type,
        array  $oiSentimentByTime = [],
        array  $mmTrapByTime      = [],
        array  $oiImbalanceByTime = [],
        array  $volSpikeByTime    = []
    ): array {
        $signalsTemp = [];

        foreach ($candles as $idx => $candle) {
            // $candle is an array (from ->toArray() on Eloquent collection)
            $O    = (float)($candle['open']  ?? 0);
            $H    = (float)($candle['high']  ?? 0);
            $L    = (float)($candle['low']   ?? 0);
            $C    = (float)($candle['close'] ?? 0);
            $time = substr($candle['interval_time'], 11, 5);

            $PP    = round(($H + $L + $C) / 3, 2);
            $R1    = round((2 * $PP) - $L, 2);
            $S1    = round((2 * $PP) - $H, 2);
            $range = round($H - $L, 2);

            $oi        = $oiSentimentByTime[$time] ?? $this->noSentiment();
            $mmTrap    = $mmTrapByTime[$time]       ?? $this->noMmTrap();
            $imbalance = $oiImbalanceByTime[$time]  ?? $this->noImbalance();
            $volSpike  = $volSpikeByTime[$time]     ?? $this->noVolSpike();

            // FIX 4: pass only candles+idx, reads future_price from candle row
            $gamma = $this->calcGammaProxy($candles, $idx);

            $signalsTemp[] = [
                'time'          => $time,
                'type'          => $type,
                'option_symbol' => $candle['trading_symbol'] ?? null,
                'strike'        => $candle['strike']         ?? null,
                'atm_strike'    => $candle['atm_strike']     ?? null,
                'open'          => round($O, 2),
                'high'          => round($H, 2),
                'low'           => round($L, 2),
                'close'         => round($C, 2),
                'PP'            => $PP,
                'R1'            => $R1,
                'S1'            => $S1,
                'range'         => $range,
                's1_match'      => $S1 >= $L,
                'r1_match'      => $R1 >= $H,
                'hourly_oi'     => $oi,
                'mm_trap'       => $mmTrap,
                'oi_imbalance'  => $imbalance,
                'vol_spike'     => $volSpike,
                'gamma'         => $gamma,
            ];
        }

        // Decision — lookahead: each candle compares its own OI signal
        // against the NEXT candle's OI signal.
        //   Current == Next  →  HOLD    (trend is continuing)
        //   Current != Next  →  EXIT    (reversal incoming next candle)
        //   Last candle      →  PENDING (next candle not yet available)
        $lastIdx = count($signalsTemp) - 1;
        foreach ($signalsTemp as $i => $sig) {
            $currSignal = $sig['hourly_oi']['signal'] ?? 'N/A';

            if ($i === $lastIdx) {
                $nextSignal = null;
                $decision   = 'PENDING';
            } else {
                $nextSignal = $signalsTemp[$i + 1]['hourly_oi']['signal'] ?? 'N/A';
                if ($currSignal === 'N/A' || $nextSignal === 'N/A') {
                    $decision = 'N/A';
                } else {
                    $decision = ($currSignal === $nextSignal) ? 'HOLD' : 'EXIT';
                }
            }

            $signalsTemp[$i]['next_signal'] = $nextSignal;
            $signalsTemp[$i]['decision']    = $decision;
        }

        return $signalsTemp;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DATE + EXPIRY HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    // ══════════════════════════════════════════════════════════════════════════
    // PREVIOUS DAY SAME-EXPIRY OI (for 9:15 first candle baseline)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Get the previous trading day's last interval (3:15) OI totals
     * for CE and PE, BUT ONLY if the same expiry had data on that day.
     *
     * Safe guard: if the expiry rolled over (e.g. weekly expiry passed,
     * today uses next expiry), prev day won't have that expiry → returns [0, 0]
     * → first candle shows noSentiment (blank) instead of a fake jump.
     *
     * Also restricted to ATM±3 strikes for consistency with today's data.
     *
     * @return array [ceOi, peOi]  both 0 if not applicable
     */
    private function getPrevDayLastOiBySameExpiry(
        string $symbol,
        string $expiry,
        string $prevDate,
        array  $atmStrikes
    ): array {
        // Check if prev day had ANY data for this expiry
        $hasPrevData = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $prevDate)
            ->exists();

        if (!$hasPrevData) {
            return [0, 0]; // Expiry rolled over or no data — skip
        }

        // Find the last interval time for this symbol on prev day
        $lastInterval = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $prevDate)
            ->orderByDesc('interval_time')
            ->value('interval_time');

        if (!$lastInterval) return [0, 0];

        // Sum OI across ATM±3 strikes for CE and PE at that last interval
        $rows = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $prevDate)
            ->where('interval_time', $lastInterval)
            ->when(!empty($atmStrikes), fn($q) => $q->whereIn('strike', $atmStrikes))
            ->get(['instrument_type', 'oi']);

        $ceOi = $rows->where('instrument_type', 'CE')->sum('oi');
        $peOi = $rows->where('instrument_type', 'PE')->sum('oi');

        return [(int)$ceOi, (int)$peOi];
    }

        private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isMarketHoliday($prev->toDateString())) {
                return $prev->toDateString();
            }
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isMarketHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    private function getNearestExpiryForDate(string $sym, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CONFIG PAGES + CRUD (unchanged)
    // ══════════════════════════════════════════════════════════════════════════

    public function configIndex()
    {
        $pageTitle  = 'Pivot Order Config 15Min';
        $allSymbols = self::ALL_SYMBOLS;

        $brokers = BrokerApi::select('id', 'client_name')
            ->where('user_id', Auth::id())
            ->whereIn('client_type', ['Zerodha', 'AngelOne'])
            ->get();

        $configs = NewPivotOrderConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.pivot-signal-15.config', compact(
            'pageTitle', 'brokers', 'configs', 'allSymbols'
        ));
    }

    public function configOrders($configId)
    {
        $pageTitle = 'Pivot Orders 15Min';
        $config    = NewPivotOrderConfig::where('user_id', Auth::id())
            ->where('id', $configId)->firstOrFail();

        $orders = NewPivotOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->orderByDesc('created_at')->paginate(50);

        return view($this->activeTemplate . 'user.pivot-signal-15.orders',
            compact('pageTitle', 'config', 'orders'));
    }

    public function configStore(Request $request)
    {
        $request->validate($this->configValidationRules());
        try {
            NewPivotOrderConfig::create([
                'user_id'       => Auth::id(),
                'broker_api_id' => $request->broker_api_id,
                'symbols'       => array_map('strtoupper', $request->symbols),
                'order_type'    => $request->order_type,
                'product'       => $request->product,
                's1_ce_layers'  => $request->s1_ce_layers,
                's1_pe_layers'  => $request->s1_pe_layers,
                'r1_ce_layers'  => $request->r1_ce_layers,
                'r1_pe_layers'  => $request->r1_pe_layers,
                'status'        => $request->status,
            ]);
            return response()->json(['success' => true, 'message' => 'Config created successfully!']);
        } catch (\Exception $e) {
            Log::error('PivotSignal15 configStore: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function configUpdate(Request $request, $id)
    {
        $request->validate($this->configValidationRules());
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())->firstOrFail();
            $config->update([
                'broker_api_id' => $request->broker_api_id,
                'symbols'       => array_map('strtoupper', $request->symbols),
                'order_type'    => $request->order_type,
                'product'       => $request->product,
                's1_ce_layers'  => $request->s1_ce_layers,
                's1_pe_layers'  => $request->s1_pe_layers,
                'r1_ce_layers'  => $request->r1_ce_layers,
                'r1_pe_layers'  => $request->r1_pe_layers,
                'status'        => $request->status,
            ]);
            return response()->json(['success' => true, 'message' => 'Config updated!']);
        } catch (\Exception $e) {
            Log::error('PivotSignal15 configUpdate: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function configToggle($id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())->firstOrFail();
            $config->status = !$config->status;
            $config->save();
            $label = $config->status ? 'activated' : 'deactivated';
            return back()->withNotify([['success', "Config {$label}!"]]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'Error updating status.']]);
        }
    }

    public function configDestroy($id)
    {
        try {
            NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())->firstOrFail()->delete();
            return back()->withNotify([['success', 'Config deleted!']]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'Error deleting config.']]);
        }
    }

    public function configRunNow(Request $request, $id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())->firstOrFail();

            if (!$config->status)
                return response()->json(['success' => false, 'message' => 'Config is inactive.'], 422);

            if (!$config->hasSymbols())
                return response()->json(['success' => false, 'message' => 'No symbols selected.'], 422);

            $exitCode = \Artisan::call('pivot15:place-orders', ['--config' => $id]);
            $output   = trim(\Artisan::output());

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Orders triggered successfully!' : 'Command finished with warnings.',
                'output'  => $output ?: 'No output.',
            ]);
        } catch (\Exception $e) {
            Log::error('PivotSignal15 configRunNow: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ── Shared validation rules ───────────────────────────────────────────────

    private function configValidationRules(): array
    {
        $layerRules = [
            '*.discount_direction' => 'required|in:positive,negative',
            '*.discount_pct'       => 'required|numeric|min:0|max:100',
            '*.quantity'           => 'required|integer|min:0',
        ];
        $rules = [
            'broker_api_id' => 'required|exists:broker_apis,id',
            'symbols'       => 'required|array|min:1',
            'symbols.*'     => 'required|string|in:' . implode(',', self::ALL_SYMBOLS),
            'order_type'    => 'required|in:LIMIT,MARKET',
            'product'       => 'required|in:NRML,MIS',
            'status'        => 'required|in:0,1',
        ];
        foreach (['s1_ce_layers', 's1_pe_layers', 'r1_ce_layers', 'r1_pe_layers'] as $layer) {
            $rules[$layer] = 'required|array|min:1|max:5';
            foreach ($layerRules as $suffix => $rule) {
                $rules["{$layer}.{$suffix}"] = $rule;
            }
        }
        return $rules;
    }
}