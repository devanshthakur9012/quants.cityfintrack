<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ThirtyMinOhlcData;
use App\Models\NewPivotOrderConfig;
use App\Models\NewPivotOrder;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Auth;

/**
 * Unified Pivot Signal + Config Controller
 *
 * - 1hr candles (from 30min_ohlc_data table via ThirtyMinOhlcData model)
 * - Supports any symbol (NIFTY, BANKNIFTY, MCX, BSE from 30min_ohlc_symbols)
 * - By default shows latest row for ALL symbols; filter to show all rows for a specific symbol
 * - Layer-wise order placement: up to 5 S1 layers + 5 R1 layers
 * - PP = (H+L+C)/3 of CURRENT 1hr candle
 * - Each config now has a `symbols` field — orders only placed for selected symbols
 *
 * OI Sentiments:
 * - 1hr OI Sentiment: latest candle OI analysis from ThirtyMinOhlcData (CE/PE)
 */
class PivotSignalController extends Controller
{
    /**
     * Master list of all symbols available for selection.
     */
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
        'VOLTAS', 'MCX', 'SENSEX'
    ];

    // ── Signal Pages ──────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Pivot Signal';
        return view($this->activeTemplate . 'user.pivot-signal.index', compact('pageTitle'));
    }

    // ── Signal API ────────────────────────────────────────────────────────────

    /**
     * GET /pivot-signal/signals?symbol=ALL|NIFTY|BANKNIFTY|...&date=Y-m-d
     */
    public function getSignals(Request $request)
    {
        try {
            $symbol    = strtoupper(trim($request->get('symbol', 'ALL')));
            $dateInput = $request->get('date');
            $today     = $dateInput
                ? Carbon::parse($dateInput)->toDateString()
                : Carbon::today()->toDateString();

            // Discover which symbols have data on the selected date
            $availableSymbols = ThirtyMinOhlcData::whereDate('trade_date', $today)
                ->where('is_missing', 0)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()
                ->pluck('base_symbol')
                ->sort()
                ->values()
                ->toArray();

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

            $prevTradingDate = $this->getPreviousTradingDate($today);

            foreach ($symbols as $sym) {

                $expiry = $this->getNearestExpiryForDate($sym, $today);
                if (!$expiry) continue;

                $ceCandles = $this->getCandles($sym, 'CE', $expiry, $today);
                $peCandles = $this->getCandles($sym, 'PE', $expiry, $today);

                if ($ceCandles->isEmpty() && $peCandles->isEmpty()) continue;

                $ceOiByInterval = $this->getOiByInterval($sym, 'CE', $today);
                $peOiByInterval = $this->getOiByInterval($sym, 'PE', $today);

                $allIntervalTimes = collect(array_keys($ceOiByInterval))
                    ->merge(array_keys($peOiByInterval))
                    ->unique()->sort()->values()->toArray();

                $prevDayCeOi = $this->getLastDayOi($sym, 'CE', $prevTradingDate);
                $prevDayPeOi = $this->getLastDayOi($sym, 'PE', $prevTradingDate);

                $oiSentimentByTime = [];
                foreach ($allIntervalTimes as $idx => $timeKey) {
                    $curCeOi = $ceOiByInterval[$timeKey] ?? 0;
                    $curPeOi = $peOiByInterval[$timeKey] ?? 0;

                    if ($idx === 0) {
                        $prevCeOi = $prevDayCeOi;
                        $prevPeOi = $prevDayPeOi;
                    } else {
                        $prevTime = $allIntervalTimes[$idx - 1];
                        $prevCeOi = $ceOiByInterval[$prevTime] ?? 0;
                        $prevPeOi = $peOiByInterval[$prevTime] ?? 0;
                    }

                    if ($prevCeOi == 0 && $prevPeOi == 0) {
                        $oiSentimentByTime[$timeKey] = $this->noSentiment();
                        continue;
                    }

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
                }

                $mmTrapByTime = $this->getMmTrapByInterval($sym, $today);

                $prevDayCeLastSentiment = $this->getPrevDayLastOiSentiment($sym, $prevTradingDate);
                $prevDayPeLastSentiment = $prevDayCeLastSentiment;

                $ceSignals = $this->buildSignals($ceCandles->toArray(), 'CE', $oiSentimentByTime, $mmTrapByTime, $prevDayCeLastSentiment);
                $peSignals = $this->buildSignals($peCandles->toArray(), 'PE', $oiSentimentByTime, $mmTrapByTime, $prevDayPeLastSentiment);

                $latestCe = $ceCandles->last();
                $latestPe = $peCandles->last();

                if ($isAll) {
                    $lastCe   = collect($ceSignals)->last();
                    $lastPe   = collect($peSignals)->last();
                    $lastTime = $lastCe['time'] ?? ($lastPe['time'] ?? null);

                    $allSignals = collect(array_merge($ceSignals, $peSignals))
                        ->filter(fn($s) => $s['time'] === $lastTime)
                        ->sortBy('time')
                        ->values()
                        ->toArray();
                } else {
                    $allSignals = collect(array_merge($ceSignals, $peSignals))
                        ->sortBy('time')
                        ->values()
                        ->toArray();
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
                    'atm_strike'    => $latestCe->atm_strike ?? ($latestPe->atm_strike ?? null),
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
            Log::error('PivotSignal getSignals: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ── Trap Filter API ───────────────────────────────────────────────────────

    /**
     * GET /pivot-signal/traps
     *     ?start_date=Y-m-d
     *     &end_date=Y-m-d
     *     &symbol=ALL|NIFTY|...
     *     &trap_type=ALL|CE_TRAP|PE_TRAP
     *
     * Scans every trading day in [start_date, end_date] server-side and
     * returns only intervals that fired a MM Trap signal.
     * One row per (date + symbol + interval_time) — no CE/PE duplication.
     * Max range: 60 calendar days.
     */
    public function getTraps(Request $request)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date'   => 'required|date|after_or_equal:start_date',
                'symbol'     => 'nullable|string',
                'trap_type'  => 'nullable|in:ALL,CE_TRAP,PE_TRAP',
            ]);

            $startDate = Carbon::parse($request->start_date)->toDateString();
            $endDate   = Carbon::parse($request->end_date)->toDateString();
            $symbol    = strtoupper(trim($request->get('symbol', 'ALL')));
            $trapType  = strtoupper(trim($request->get('trap_type', 'ALL')));

            // Guard: max 60 calendar days
            $daySpan = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
            if ($daySpan > 60) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 60 days. Please narrow your selection.',
                    'data'    => [],
                ], 422);
            }

            $tradingDates = $this->getTradingDatesInRange($startDate, $endDate);

            if (empty($tradingDates)) {
                return response()->json([
                    'success' => true,
                    'data'    => [],
                    'message' => 'No trading days found in the selected range.',
                    'meta'    => ['dates_scanned' => 0, 'traps_found' => 0],
                ]);
            }

            $trapRows = [];

            foreach ($tradingDates as $date) {

                // Symbols with data on this date
                $dateSymbols = ThirtyMinOhlcData::whereDate('trade_date', $date)
                    ->where('is_missing', 0)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->whereNotNull('base_symbol')
                    ->distinct()
                    ->pluck('base_symbol')
                    ->sort()
                    ->values()
                    ->toArray();

                if ($symbol !== 'ALL') {
                    $dateSymbols = array_values(array_filter($dateSymbols, fn($s) => $s === $symbol));
                }

                foreach ($dateSymbols as $sym) {

                    $mmTrapByTime = $this->getMmTrapByInterval($sym, $date);

                    if (empty($mmTrapByTime)) continue;

                    foreach ($mmTrapByTime as $time => $mm) {
                        if (empty($mm['type'])) continue;

                        if ($trapType !== 'ALL' && $mm['type'] !== $trapType) continue;

                        $trapRows[] = [
                            'date'        => $date,
                            'time'        => $time,
                            'symbol'      => $sym,
                            'type'        => $mm['type'],
                            'signal'      => $mm['signal']      ?? null,
                            'strength'    => $mm['strength']    ?? null,
                            'ce_oi_pct'   => $mm['ce_oi_pct']   ?? null,
                            'put_oi_pct'  => $mm['put_oi_pct']  ?? null,
                            'diff'        => $mm['diff']        ?? null,
                            'detail'      => $mm['detail']      ?? null,
                        ];
                    }
                }
            }

            // Sort: date asc, time asc
            usort($trapRows, fn($a, $b) =>
                strcmp($a['date'] . $a['time'], $b['date'] . $b['time'])
            );

            $ceCount = count(array_filter($trapRows, fn($r) => $r['type'] === 'CE_TRAP'));
            $peCount = count(array_filter($trapRows, fn($r) => $r['type'] === 'PE_TRAP'));

            return response()->json([
                'success' => true,
                'data'    => $trapRows,
                'message' => count($trapRows) . ' trap(s) found across ' . count($tradingDates) . ' trading day(s).',
                'meta'    => [
                    'start_date'    => $startDate,
                    'end_date'      => $endDate,
                    'dates_scanned' => count($tradingDates),
                    'traps_found'   => count($trapRows),
                    'ce_traps'      => $ceCount,
                    'pe_traps'      => $peCount,
                    'symbol_filter' => $symbol,
                    'type_filter'   => $trapType,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => collect($ve->errors())->flatten()->first(),
                'data'    => [],
            ], 422);
        } catch (\Exception $e) {
            Log::error('PivotSignal getTraps: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    /**
     * Return all trading dates (no weekends, no market holidays) between
     * $startDate and $endDate inclusive.
     */
    private function getTradingDatesInRange(string $startDate, string $endDate): array
    {
        $dates = [];
        $cur   = Carbon::parse($startDate);
        $end   = Carbon::parse($endDate);

        while ($cur->lte($end)) {
            if (!$cur->isWeekend() && !$this->isMarketHoliday($cur->toDateString())) {
                $dates[] = $cur->toDateString();
            }
            $cur->addDay();
        }

        return $dates;
    }

    // ── Per-interval OI helpers ───────────────────────────────────────────────

    /**
     * Load ALL interval OI totals for a symbol+type on a date in ONE query.
     * Returns: [ "09:15" => 123456789, "10:15" => 234567890, ... ]
     */
    private function getOiByInterval(string $symbol, string $type, string $date): array
    {
        return ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->groupBy('interval_time')
            ->orderBy('interval_time')
            ->selectRaw('interval_time, SUM(oi) as total_oi')
            ->pluck('total_oi', 'interval_time')
            ->mapWithKeys(fn($oi, $time) => [
                Carbon::parse($time)->format('H:i') => (int) $oi
            ])
            ->toArray();
    }

    /**
     * Get the last interval's total OI from the previous trading day.
     */
    private function getLastDayOi(string $symbol, string $type, string $prevDate): int
    {
        $lastInterval = ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $prevDate)
            ->where('is_missing', 0)
            ->orderByDesc('interval_time')
            ->value('interval_time');

        if (!$lastInterval) return 0;

        return (int) ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $prevDate)
            ->where('interval_time', $lastInterval)
            ->where('is_missing', 0)
            ->sum('oi');
    }

    /**
     * Get the OI sentiment for the last candle of the previous trading day.
     */
    private function getPrevDayLastOiSentiment(string $symbol, string $prevDate): array
    {
        $ceOiByInterval = $this->getOiByInterval($symbol, 'CE', $prevDate);
        $peOiByInterval = $this->getOiByInterval($symbol, 'PE', $prevDate);

        $allTimes = collect(array_keys($ceOiByInterval))
            ->merge(array_keys($peOiByInterval))
            ->unique()->sort()->values()->toArray();

        if (count($allTimes) < 2) return $this->noSentiment();

        $lastSlot = end($allTimes);
        $lastIdx  = array_search($lastSlot, $allTimes);
        $prevSlot = $allTimes[$lastIdx - 1];

        $curCeOi  = $ceOiByInterval[$lastSlot] ?? 0;
        $curPeOi  = $peOiByInterval[$lastSlot] ?? 0;
        $prevCeOi = $ceOiByInterval[$prevSlot] ?? 0;
        $prevPeOi = $peOiByInterval[$prevSlot] ?? 0;

        if ($prevCeOi == 0 && $prevPeOi == 0) return $this->noSentiment();

        $cePct = $prevCeOi > 0 ? round((($curCeOi - $prevCeOi) / $prevCeOi) * 100, 2) : 0;
        $pePct = $prevPeOi > 0 ? round((($curPeOi - $prevPeOi) / $prevPeOi) * 100, 2) : 0;

        return array_merge(
            $this->calcOiSignal($cePct, $pePct),
            [
                'ce_oi'     => (int) $curCeOi,
                'pe_oi'     => (int) $curPeOi,
                'ce_oi_pct' => $cePct,
                'pe_oi_pct' => $pePct,
                'time'      => $lastSlot,
            ]
        );
    }

    /**
     * MM Trap Detection — Build-up Phase Logic
     *
     * Rules (applied per interval):
     *   - Both CE% > 0 AND PE% > 0  → build-up phase
     *   - diff = |CE% − PE%|
     *       > 5  → STRONG
     *       > 2  → MODERATE
     *       ≤ 2  → NO_TRAP
     *   - If PE% > CE% → CE_TRAP
     *   - If CE% > PE% → PE_TRAP
     */
    private function getMmTrapByInterval(string $symbol, string $date): array
    {
        $ceOiByInterval = $this->getOiByInterval($symbol, 'CE', $date);
        $peOiByInterval = $this->getOiByInterval($symbol, 'PE', $date);

        $allTimes = collect(array_keys($ceOiByInterval))
            ->merge(array_keys($peOiByInterval))
            ->unique()->sort()->values()->toArray();

        if (empty($allTimes)) return [];

        $result = [];

        foreach ($allTimes as $idx => $timeKey) {
            $prevTime = $idx > 0 ? $allTimes[$idx - 1] : null;

            $curCeOi  = $ceOiByInterval[$timeKey] ?? 0;
            $curPeOi  = $peOiByInterval[$timeKey] ?? 0;
            $prevCeOi = $prevTime ? ($ceOiByInterval[$prevTime] ?? 0) : 0;
            $prevPeOi = $prevTime ? ($peOiByInterval[$prevTime] ?? 0) : 0;

            if ($idx === 0 || ($prevCeOi == 0 && $prevPeOi == 0)) {
                $result[$timeKey] = $this->noMmTrap();
                continue;
            }

            $cePct = $prevCeOi > 0 ? round((($curCeOi - $prevCeOi) / $prevCeOi) * 100, 2) : 0;
            $pePct = $prevPeOi > 0 ? round((($curPeOi - $prevPeOi) / $prevPeOi) * 100, 2) : 0;

            if ($cePct <= 0 || $pePct <= 0) {
                $result[$timeKey] = array_merge($this->noMmTrap(), [
                    'ce_oi_pct'  => $cePct,
                    'put_oi_pct' => $pePct,
                ]);
                continue;
            }

            $diff = round(abs($cePct - $pePct), 2);

            if ($diff <= 2) {
                $result[$timeKey] = array_merge($this->noMmTrap(), [
                    'ce_oi_pct'  => $cePct,
                    'put_oi_pct' => $pePct,
                ]);
                continue;
            }

            $strength = $diff > 5 ? 'STRONG' : 'MODERATE';

            if ($pePct > $cePct) {
                $trap   = 'CE_TRAP';
                $signal = 'BEARISH';
                $detail = "CE TRAP [{$strength}]: PE buildup +{$pePct}% > CE +{$cePct}% (Δ{$diff}%) → Put writers dominant → Bull trap → Market likely to fall";
            } else {
                $trap   = 'PE_TRAP';
                $signal = 'BULLISH';
                $detail = "PE TRAP [{$strength}]: CE buildup +{$cePct}% > PE +{$pePct}% (Δ{$diff}%) → Call writers dominant → Bear trap → Market likely to rise";
            }

            $result[$timeKey] = [
                'type'          => $trap,
                'signal'        => $signal,
                'strength'      => $strength,
                'detail'        => $detail,
                'ce_oi_pct'     => $cePct,
                'put_oi_pct'    => $pePct,
                'diff'          => $diff,
                'call_wall'     => null,
                'put_wall'      => null,
                'call_wall_oi'  => 0,
                'put_wall_oi'   => 0,
                'call_oi_pct'   => $cePct,
                'call_defended' => false,
                'put_defended'  => false,
                'call_breaking' => false,
                'put_breaking'  => false,
            ];
        }

        return $result;
    }

    // ── OI Sentiment Helpers ──────────────────────────────────────────────────

    /**
     * Core OI signal logic.
     *
     * Case 1: CE% > 0 && PE% < 0  → BEARISH  (call buildup + put unwinding)
     * Case 2: CE% < 0 && PE% > 0  → BULLISH  (call unwinding + put buildup)
     * Case 3: CE% > 0 && PE% > 0  → dominant side wins
     * Case 4: CE% < 0 && PE% < 0  → larger unwind wins
     */
    private function calcOiSignal(float $cePct, float $pePct): array
    {
        if ($cePct > 0 && $pePct < 0) {
            $signal    = 'BEARISH';
            $condition = 'CE ↑ + PE ↓';
            $reason    = 'Call buildup + Put unwinding → Resistance forming';
        } elseif ($cePct < 0 && $pePct > 0) {
            $signal    = 'BULLISH';
            $condition = 'CE ↓ + PE ↑';
            $reason    = 'Call unwinding + Put buildup → Support forming';
        } elseif ($cePct > 0 && $pePct > 0) {
            if ($pePct > $cePct) {
                $signal    = 'BULLISH';
                $condition = 'Both ↑ (PE > CE)';
                $reason    = "Put buildup stronger (+{$pePct}% vs CE +{$cePct}%) → Bullish";
            } else {
                $signal    = 'BEARISH';
                $condition = 'Both ↑ (CE ≥ PE)';
                $reason    = "Call buildup stronger (+{$cePct}% vs PE +{$pePct}%) → Bearish";
            }
        } else {
            $absCe = abs($cePct);
            $absPe = abs($pePct);
            if ($absCe > $absPe) {
                $signal    = 'BULLISH';
                $condition = 'Both ↓ (|CE| > |PE|)';
                $reason    = "Call unwinding larger ({$cePct}% vs PE {$pePct}%) → Short covering → Bullish";
            } else {
                $signal    = 'BEARISH';
                $condition = 'Both ↓ (|PE| ≥ |CE|)';
                $reason    = "Put unwinding larger ({$pePct}% vs CE {$cePct}%) → Long covering → Bearish";
            }
        }

        $difference = round(abs($cePct - $pePct), 2);

        if ($difference > 3) {
            $strength = 'Very Strong Signal';
        } elseif ($difference > 1.5) {
            $strength = 'Strong Signal';
        } elseif ($difference > 0.5) {
            $strength = 'Moderate Signal';
        } else {
            $strength = 'Weak Signal';
        }

        return [
            'signal'     => $signal,
            'condition'  => $condition,
            'reason'     => $reason,
            'strength'   => $strength,
            'difference' => $difference,
        ];
    }

    private function noSentiment(): array
    {
        return [
            'signal'     => 'N/A',
            'condition'  => 'N/A',
            'reason'     => 'No data',
            'strength'   => 'N/A',
            'difference' => 0,
            'ce_oi'      => 0,
            'pe_oi'      => 0,
            'ce_oi_pct'  => 0,
            'pe_oi_pct'  => 0,
        ];
    }

    // ── Date helpers ──────────────────────────────────────────────────────────

    private function getPreviousTradingDate(string $date): string
    {
        $prev     = Carbon::parse($date)->subDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isMarketHoliday($prev->toDateString())) {
                return $prev->toDateString();
            }
            $prev->subDay();
            $attempts++;
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

    // ── Expiry helpers ────────────────────────────────────────────────────────

    private function getNearestExpiryForDate(string $sym, string $date): ?string
    {
        $expiry = ThirtyMinOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return ThirtyMinOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // ── Config Pages ──────────────────────────────────────────────────────────

    public function configIndex()
    {
        $pageTitle  = 'Pivot Order Config';
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

        return view($this->activeTemplate . 'user.pivot-signal.config', compact(
            'pageTitle', 'brokers', 'configs', 'allSymbols'
        ));
    }

    public function configOrders($configId)
    {
        $pageTitle = 'Pivot Orders';

        $config = NewPivotOrderConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = NewPivotOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.pivot-signal.orders', compact(
            'pageTitle', 'config', 'orders'
        ));
    }

    // ── Config CRUD ───────────────────────────────────────────────────────────

    public function configStore(Request $request)
    {
        $request->validate([
            'broker_api_id'                     => 'required|exists:broker_apis,id',
            'symbols'                            => 'required|array|min:1',
            'symbols.*'                          => 'required|string|in:' . implode(',', self::ALL_SYMBOLS),
            'order_type'                         => 'required|in:LIMIT,MARKET',
            'product'                            => 'required|in:NRML,MIS',
            'status'                             => 'required|in:0,1',
            's1_ce_layers'                       => 'required|array|min:1|max:5',
            's1_ce_layers.*.discount_direction'  => 'required|in:positive,negative',
            's1_ce_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            's1_ce_layers.*.quantity'            => 'required|integer|min:0',
            's1_pe_layers'                       => 'required|array|min:1|max:5',
            's1_pe_layers.*.discount_direction'  => 'required|in:positive,negative',
            's1_pe_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            's1_pe_layers.*.quantity'            => 'required|integer|min:0',
            'r1_ce_layers'                       => 'required|array|min:1|max:5',
            'r1_ce_layers.*.discount_direction'  => 'required|in:positive,negative',
            'r1_ce_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_ce_layers.*.quantity'            => 'required|integer|min:0',
            'r1_pe_layers'                       => 'required|array|min:1|max:5',
            'r1_pe_layers.*.discount_direction'  => 'required|in:positive,negative',
            'r1_pe_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_pe_layers.*.quantity'            => 'required|integer|min:0',
            'interval_type'                      => 'required|in:1hr,15min',
        ]);

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
                'interval_type' => $request->interval_type,
            ]);

            return response()->json(['success' => true, 'message' => 'Config created successfully!']);
        } catch (\Exception $e) {
            Log::error('PivotSignal configStore: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function configUpdate(Request $request, $id)
    {
        $request->validate([
            'broker_api_id'                     => 'required|exists:broker_apis,id',
            'symbols'                            => 'required|array|min:1',
            'symbols.*'                          => 'required|string|in:' . implode(',', self::ALL_SYMBOLS),
            'order_type'                         => 'required|in:LIMIT,MARKET',
            'product'                            => 'required|in:NRML,MIS',
            'status'                             => 'required|in:0,1',
            's1_ce_layers'                       => 'required|array|min:1|max:5',
            's1_ce_layers.*.discount_direction'  => 'required|in:positive,negative',
            's1_ce_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            's1_ce_layers.*.quantity'            => 'required|integer|min:0',
            's1_pe_layers'                       => 'required|array|min:1|max:5',
            's1_pe_layers.*.discount_direction'  => 'required|in:positive,negative',
            's1_pe_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            's1_pe_layers.*.quantity'            => 'required|integer|min:0',
            'r1_ce_layers'                       => 'required|array|min:1|max:5',
            'r1_ce_layers.*.discount_direction'  => 'required|in:positive,negative',
            'r1_ce_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_ce_layers.*.quantity'            => 'required|integer|min:0',
            'r1_pe_layers'                       => 'required|array|min:1|max:5',
            'r1_pe_layers.*.discount_direction'  => 'required|in:positive,negative',
            'r1_pe_layers.*.discount_pct'        => 'required|numeric|min:0|max:100',
            'r1_pe_layers.*.quantity'            => 'required|integer|min:0',
            'interval_type'                      => 'required|in:1hr,15min',
        ]);

        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

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
                'interval_type' => $request->interval_type,
            ]);

            return response()->json(['success' => true, 'message' => 'Config updated!']);
        } catch (\Exception $e) {
            Log::error('PivotSignal configUpdate: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function configToggle($id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();
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
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $config->delete();
            return back()->withNotify([['success', 'Config deleted!']]);
        } catch (\Exception $e) {
            return back()->withNotify([['error', 'Error deleting config.']]);
        }
    }

    // ── Data helpers ──────────────────────────────────────────────────────────

    private function getCandles(string $sym, string $type, string $expiry, string $today)
    {
        return ThirtyMinOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $today)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['trading_symbol', 'strike', 'open', 'high', 'low', 'close', 'interval_time', 'atm_strike'])
            ->values();
    }

    /**
     * Build pivot signals for a set of 1hr candles.
     * PP = (H+L+C)/3 of the CURRENT 1hr candle.
     * R1 = 2*PP − L
     * S1 = 2*PP − H
     */
    private function buildSignals(
        array $candles,
        string $type,
        array $oiSentimentByTime = [],
        array $mmTrapByTime = [],
        array $prevDayLastOi = []
    ): array {
        $signalsTemp = [];

        foreach ($candles as $candle) {
            $O    = (float)$candle['open'];
            $H    = (float)$candle['high'];
            $L    = (float)$candle['low'];
            $C    = (float)$candle['close'];
            $time = substr($candle['interval_time'], 11, 5);

            $PP    = round(($H + $L + $C) / 3, 2);
            $R1    = round((2 * $PP) - $L, 2);
            $S1    = round((2 * $PP) - $H, 2);
            $range = round($H - $L, 2);

            $oi     = $oiSentimentByTime[$time] ?? $this->noSentiment();
            $mmTrap = $mmTrapByTime[$time]      ?? $this->noMmTrap();

            $signalsTemp[] = [
                'time'          => $time,
                'type'          => $type,
                'option_symbol' => $candle['trading_symbol'],
                'strike'        => $candle['strike'],
                'atm_strike'    => $candle['atm_strike'] ?? null,
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
            ];
        }

        foreach ($signalsTemp as $i => $sig) {
            $prevOi     = $i > 0 ? $signalsTemp[$i - 1]['hourly_oi'] : $prevDayLastOi;
            $prevSignal = $prevOi['signal'] ?? 'N/A';
            $currSignal = $sig['hourly_oi']['signal'] ?? 'N/A';

            $decision = 'N/A';
            if ($currSignal !== 'N/A' && $prevSignal !== 'N/A') {
                $decision = ($currSignal === $prevSignal) ? 'HOLD' : 'EXIT';
            }

            $signalsTemp[$i]['prev_signal'] = $prevSignal;
            $signalsTemp[$i]['decision']    = $decision;
        }

        return $signalsTemp;
    }

    private function noMmTrap(): array
    {
        return [
            'type'          => null,
            'signal'        => null,
            'detail'        => null,
            'call_wall'     => null,
            'put_wall'      => null,
            'call_wall_oi'  => 0,
            'put_wall_oi'   => 0,
            'call_oi_pct'   => 0,
            'put_oi_pct'    => 0,
            'call_defended' => false,
            'put_defended'  => false,
            'call_breaking' => false,
            'put_breaking'  => false,
        ];
    }

    public function configRunNow(Request $request, $id)
    {
        try {
            $config = NewPivotOrderConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            if (!$config->status)
                return response()->json(['success' => false, 'message' => 'Config is inactive. Activate it first.'], 422);

            if (!$config->hasSymbols())
                return response()->json(['success' => false, 'message' => 'No symbols selected for this config.'], 422);

            $command = ($config->interval_type === '15min') ? 'pivot15:place-orders' : 'pivot:place-orders';

            $exitCode = \Artisan::call($command, ['--config' => $id]);
            $output   = trim(\Artisan::output());

            return response()->json([
                'success' => $exitCode === 0,
                'message' => $exitCode === 0 ? 'Orders triggered successfully!' : 'Command finished with warnings.',
                'output'  => $output ?: 'No output.',
            ]);

        } catch (\Exception $e) {
            Log::error('PivotSignal configRunNow: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
}