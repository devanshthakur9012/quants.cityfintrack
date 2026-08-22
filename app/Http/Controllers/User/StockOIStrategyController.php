<?php
// FILE: app/Http/Controllers/User/StockOIStrategyController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\OI\AdaptiveOILearningEngine;
use App\Services\OI\Lichsgfin1030OIEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stock-Specific OI Strategy Engine — LICHSGFIN / PAYTM / SHRIRAMFIN
 *
 * Wraps three client-supplied strategy scripts as one page:
 *
 *   LICHSGFIN  -> Lichsgfin1030OIEngine        (rule-based 10:30 gap-fail engine)
 *   PAYTM      -> AdaptiveOILearningEngine      (adaptive learning engine, config A)
 *   SHRIRAMFIN -> AdaptiveOILearningEngine      (adaptive learning engine, config B)
 *
 * PAYTM and SHRIRAMFIN use the *same* algorithm — the client's two
 * scripts are functionally identical, only the tuning constants
 * differ — so both are served by one engine class, configured below.
 *
 * ── DATA SOURCE ──────────────────────────────────────────────────────
 * Wired to the real collector tables (per Cp\CpCollectStock / CpCollectFut
 * / CpCollectOption):
 *
 *   cp_stock_ohlc_15min  — underlying EQ OHLC. Column: `symbol`.
 *   cp_fut_ohlc_15min    — futures OHLC+OI. Column: `base_symbol`.
 *   cp_option_ohlc_15min — CE/PE OHLC+OI per strike_position. Column: `base_symbol`.
 *
 * `strike_position` values are assumed to be 'ATM-1' / 'ATM' / 'ATM+1'
 * (matches the literal field names the LICHSGFIN engine expects and the
 * `->where('strike_position','ATM')` filter already used elsewhere) — if
 * `getStrikePosition()` in CpOhlcBase actually emits something else
 * (e.g. 'ITM1'/'OTM1'), adjust the two `match()` blocks below.
 */
class StockOIStrategyController extends Controller
{
    private const STOCK_TABLE = 'cp_stock_ohlc_15min';
    private const FUT_TABLE = 'cp_fut_ohlc_15min';
    private const OPT_TABLE = 'cp_option_ohlc_15min';

    /** Symbols this page serves and which engine drives each one. */
    private const SYMBOLS = ['LICHSGFIN', 'PAYTM', 'SHRIRAMFIN'];

    /** How many calendar days of history to pull for the adaptive-learning engines. */
    private const ADAPTIVE_LOOKBACK_DAYS = 90;

    // ── Page ──────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Stock OI Strategy Engine';
        return view(activeTemplate() . 'user.stock-oi-strategy.index', compact('pageTitle'));
    }

    // ── Last available date (per symbol — Fix 3) ────────────────────────

    /**
     * Fix 3: a date having a CE row doesn't guarantee PE/FUT/Stock/ATM-1/
     * ATM+1 all exist too, so "max(trade_date) from CE" (the old version)
     * could hand the UI a date that then comes back NO_TRADE / "no
     * synchronized data" for reasons that have nothing to do with the
     * strategy. This now walks backward from the most recent CE date and
     * returns the newest date that's actually fully synchronized for the
     * requested symbol's engine (LICHSGFIN needs FUT+CE+PE; PAYTM/SHRIRAMFIN
     * need STOCK+CE+PE).
     */
    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            $today = Carbon::today()->toDateString();

            if (!$config) {
                return response()->json(['success' => false, 'last_date' => $today, 'is_today' => true]);
            }

            $symbol = strtoupper((string) $request->get('symbol', ''));
            if (!in_array($symbol, self::SYMBOLS, true)) {
                $symbol = self::SYMBOLS[0];
            }

            $lastDate = $this->latestSyncedDate($config->id, $symbol) ?? $today;

            return response()->json([
                'success' => true,
                'last_date' => $lastDate,
                'is_today' => $lastDate === $today,
            ]);
        } catch (\Exception $e) {
            Log::error('StockOIStrategy lastDate: ' . $e->getMessage());
            return response()->json(['success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true]);
        }
    }

    /**
     * Walks backward, most recent candidate date first, checking each one
     * for real synchronization before accepting it. Checks the last 20
     * candidate dates so it doesn't scan the whole table when data is stale.
     */
    private function latestSyncedDate(int $configId, string $symbol): ?string
    {
        $candidateDates = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', 'CE')
            ->where('is_missing', false)
            ->orderBy('trade_date', 'desc')
            ->distinct()
            ->limit(20)
            ->pluck('trade_date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values();

        foreach ($candidateDates as $date) {
            if ($symbol === 'LICHSGFIN') {
                $futOk = DB::table(self::FUT_TABLE)
                    ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
                    ->whereDate('trade_date', $date)->where('is_missing', false)
                    ->whereIn(DB::raw("TIME(interval_time)"), ['09:15:00', '10:30:00'])
                    ->distinct()->count(DB::raw('TIME(interval_time)')) === 2;

                $optOk = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->whereDate('trade_date', $date)->where('is_missing', false)
                    ->whereRaw("TIME(interval_time) = '10:30:00'")
                    ->count() >= 2; // at least CE+PE ATM present at 10:30

                if ($futOk && $optOk) {
                    return $date;
                }
                continue;
            }

            $stockOk = DB::table(self::STOCK_TABLE)
                ->where('analysis_config_id', $configId)->where('symbol', $symbol)
                ->whereDate('trade_date', $date)->where('is_missing', false)
                ->whereIn(DB::raw("TIME(interval_time)"), ['09:15:00', '10:00:00'])
                ->distinct()->count(DB::raw('TIME(interval_time)')) === 2;

            $optOk = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $configId)->where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $date)->where('is_missing', false)
                ->whereRaw("TIME(interval_time) = '10:00:00'")
                ->count() >= 2;

            if ($stockOk && $optOk) {
                return $date;
            }
        }

        // Nothing fully synced in the recent window — fall back to whatever
        // the most recent CE date is, so the UI still shows something (the
        // analyze() response's data_status will make the gap visible).
        return $candidateDates->first();
    }

    // ── Symbols ─────────────────────────────────────────────────────────

    public function getSymbols(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'symbols' => self::SYMBOLS]);
    }

    // ── Analyze ──────────────────────────────────────────────────────────

    public function analyze(Request $request): JsonResponse
    {
        try {
            $date = $request->get('date');
            $symbol = strtoupper((string) $request->get('symbol', ''));

            if (!$date) {
                return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => null]);
            }
            if (!in_array($symbol, self::SYMBOLS, true)) {
                return response()->json(['success' => false, 'message' => 'Unknown symbol.', 'data' => null]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success' => false,
                    'no_config' => true,
                    'message' => 'No active Analysis Config found. Go to Admin → Analysis Config.',
                    'data' => null,
                ]);
            }

            $result = $symbol === 'LICHSGFIN'
                ? $this->runLichsgfin($config->id, $symbol, $date)
                : $this->runAdaptive($config->id, $symbol, $date);

            return response()->json([
                'success' => true,
                'engine' => $symbol === 'LICHSGFIN' ? 'GAP_FAIL_1030' : 'ADAPTIVE_LEARNING',
                'symbol' => $symbol,
                'date' => $date,
                'is_today' => $date === Carbon::today()->toDateString(),
                'available_symbols' => self::SYMBOLS,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('StockOIStrategy analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => null], 500);
        }
    }

    // ── LICHSGFIN (rule-based gap-fail engine) ──────────────────────────

    private function runLichsgfin(int $configId, string $symbol, string $date): array
    {
        // Needs the selected day plus the previous trading day (for previousDayClose),
        // built up to and including 10:30.
        $prevDate = $this->getPreviousTradingDate($date);
        $rows = $this->loadCombinedRows($configId, $symbol, $prevDate, $date);

        // Fix 2: fetch the previous day's real final FUT close directly,
        // instead of trusting that $rows[$start-1] happens to be it.
        $explicitPrevClose = $this->getPreviousFutClose($configId, $symbol, $prevDate);

        // Fix 7: how much of the required 09:15→10:30 window for the
        // SELECTED day actually made it through as complete rows.
        $expectedSlots = ['09:15', '09:30', '09:45', '10:00', '10:15', '10:30'];
        $presentSlots = 0;
        foreach ($rows as $r) {
            if (substr($r['datetime'], 0, 10) === $date && in_array(substr($r['datetime'], 11, 5), $expectedSlots, true)) {
                $presentSlots++;
            }
        }
        $dataStatus = $presentSlots === 0 ? 'INVALID' : ($presentSlots < count($expectedSlots) ? 'PARTIAL' : 'COMPLETE');

        if (empty($rows) || $presentSlots === 0) {
            return [
                'signal' => 'NO_TRADE',
                'reason' => 'No complete 15-min rows for ' . $symbol . ' on ' . $date . ' (missing FUT/CE/PE legs — see server log)',
                'data_status' => 'INVALID',
            ];
        }

        if ($explicitPrevClose === null) {
            return [
                'signal' => 'NO_TRADE',
                'reason' => 'Previous trading day (' . $prevDate . ') FUT close not found — cannot compute gap',
                'data_status' => 'INVALID',
            ];
        }

        $engine = new Lichsgfin1030OIEngine($rows);

        // Find the row index for the selected date's last available interval
        // (the engine internally locates 09:15/10:30 for that date).
        $currentIndex = null;
        foreach ($rows as $i => $r) {
            if (substr($r['datetime'], 0, 10) === $date) {
                $currentIndex = $i;
            }
        }

        if ($currentIndex === null) {
            return ['signal' => 'NO_TRADE', 'reason' => 'No rows found for ' . $date, 'data_status' => 'INVALID'];
        }

        $result = $engine->analyse1030($currentIndex, $explicitPrevClose);
        $result['data_status'] = $result['data_status'] ?? $dataStatus;
        $result['complete_slots'] = $presentSlots . '/' . count($expectedSlots);

        return $result;
    }

    /**
     * Fix 2: previous trading day's final available FUT close, queried
     * directly — the row closest to end-of-session that isn't flagged
     * is_missing. Independent of whichever rows made it into loadCombinedRows().
     */
    private function getPreviousFutClose(int $configId, string $symbol, string $prevDate): ?float
    {
        $close = DB::table(self::FUT_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereDate('trade_date', $prevDate)
            ->where('is_missing', false)
            ->orderBy('interval_time', 'desc')
            ->value('close');

        return $close !== null ? (float) $close : null;
    }

    /**
     * Fix 1: single source of truth for strike_position mapping, used by
     * both loadCombinedRows() and loadAdaptiveSeries(). Returns null for
     * anything not explicitly recognized so callers can reject the row
     * instead of silently treating it as ATM.
     */
    private function mapStrikePosition(?string $raw): ?string
    {
        return match ($raw) {
            'ATM-1', 'ATM_MINUS_1', 'ATM_MINUS1' => 'ATM-1',
            'ATM' => 'ATM',
            'ATM+1', 'ATM_PLUS_1', 'ATM_PLUS1' => 'ATM+1',
            default => null,
        };
    }


    /**
     * Build the wide-row shape Lichsgfin1030OIEngine expects:
     * one row per 15-min interval with future OHLC/OI + CE/PE ATM-1/ATM/ATM+1 close+OI.
     * Futures come from cp_fut_ohlc_15min; CE/PE from cp_option_ohlc_15min.
     */
    private function loadCombinedRows(int $configId, string $symbol, string $fromDate, string $toDate): array
    {
        $byDatetime = [];

        $futRows = DB::table(self::FUT_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereBetween(DB::raw('DATE(trade_date)'), [$fromDate, $toDate])
            ->where('is_missing', false)
            ->select(['trade_date', 'interval_time', 'open', 'high', 'low', 'close', 'oi'])
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get();

        foreach ($futRows as $r) {
            $dt = Carbon::parse($r->trade_date)->toDateString() . ' ' . substr($r->interval_time, 11, 5) . ':00';
            $byDatetime[$dt] ??= ['datetime' => $dt];
            $byDatetime[$dt]['future_open'] = (float) $r->open;
            $byDatetime[$dt]['future_high'] = (float) $r->high;
            $byDatetime[$dt]['future_low'] = (float) $r->low;
            $byDatetime[$dt]['future_close'] = (float) $r->close;
            $byDatetime[$dt]['future_oi'] = (float) $r->oi;
        }

        $optRows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereBetween(DB::raw('DATE(trade_date)'), [$fromDate, $toDate])
            ->where('is_missing', false)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->select(['trade_date', 'interval_time', 'instrument_type', 'strike_position', 'close', 'oi'])
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get();

        foreach ($optRows as $r) {
            $dt = Carbon::parse($r->trade_date)->toDateString() . ' ' . substr($r->interval_time, 11, 5) . ':00';

            // Fix 1: strict strike_position mapping — an unrecognized value
            // must NOT silently fall through to 'ATM' (that would blend a
            // wrong strike's OI into the ATM leg and distort the score).
            $strikeKey = $this->mapStrikePosition($r->strike_position);
            if ($strikeKey === null) {
                Log::warning("StockOIStrategy: unmapped strike_position '{$r->strike_position}' for {$symbol} {$dt} — row skipped");
                continue;
            }

            $byDatetime[$dt] ??= ['datetime' => $dt];
            $side = strtolower($r->instrument_type); // ce | pe
            $suffix = match ($strikeKey) {
                'ATM-1' => 'atm_minus_1',
                'ATM+1' => 'atm_plus_1',
                default => 'atm',
            };

            $byDatetime[$dt]["{$side}_{$suffix}_close"] = (float) $r->close;
            $byDatetime[$dt]["{$side}_{$suffix}_oi"] = (float) $r->oi;
        }

        // Drop incomplete rows (a 15-min slot missing FUT or any leg) — the
        // engine tolerates gaps in the wider series but each row it reads
        // must have every field it accesses.
        $required = [
            'future_open', 'future_high', 'future_low', 'future_close', 'future_oi',
            'ce_atm_minus_1_close', 'ce_atm_minus_1_oi', 'ce_atm_close', 'ce_atm_oi',
            'ce_atm_plus_1_close', 'ce_atm_plus_1_oi',
            'pe_atm_minus_1_close', 'pe_atm_minus_1_oi', 'pe_atm_close', 'pe_atm_oi',
            'pe_atm_plus_1_close', 'pe_atm_plus_1_oi',
        ];

        $rows = [];
        foreach ($byDatetime as $row) {
            $complete = true;
            foreach ($required as $field) {
                if (!array_key_exists($field, $row)) {
                    $complete = false;
                    break;
                }
            }
            if ($complete) {
                $rows[] = $row;
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['datetime'], $b['datetime']));

        return $rows;
    }

    // ── PAYTM / SHRIRAMFIN (adaptive learning engine) ───────────────────

    private function runAdaptive(int $configId, string $symbol, string $date): array
    {
        $engine = $this->makeAdaptiveEngine($symbol);

        $fromDate = Carbon::parse($date)->subDays(self::ADAPTIVE_LOOKBACK_DAYS)->toDateString();
        [$stockByDate, $ce, $pe, $dates] = $this->loadAdaptiveSeries($configId, $symbol, $fromDate, $date);

        if (empty($dates)) {
            return ['latest' => ['signal' => 'NO_TRADE', 'reason' => 'No synchronized stock+CE+PE data found for ' . $symbol . ' in the lookback window', 'data_status' => 'INVALID']];
        }

        // Historical profile for the LATEST-day signal only uses days
        // strictly before the selected date — no leakage there.
        $historyDates = array_values(array_filter($dates, fn ($d) => $d < $date));
        $profile = $engine->buildHistoricalProfile($stockByDate, $ce, $pe, $historyDates);

        $latest = $engine->latestAnalysis($stockByDate, $ce, $pe, $dates, $profile);
        $gapStudy = $engine->gapReversalStudy($stockByDate, $historyDates);

        // Fix 4: backtest() now re-learns the profile fresh for every day in
        // the walk-forward loop internally — it no longer takes a single
        // precomputed $profile, so it can't leak future days into past signals.
        $backtest = $engine->backtest($stockByDate, $ce, $pe, $historyDates);

        return [
            'latest' => $latest,
            'gap_reversal_study' => $gapStudy,
            'backtest_summary' => $backtest['summary'],
            'backtest_rows' => array_slice(array_reverse($backtest['rows']), 0, 30), // most recent 30
        ];
    }

    private function makeAdaptiveEngine(string $symbol): AdaptiveOILearningEngine
    {
        // Both PAYTM and SHRIRAMFIN ship with the client's default tuning
        // (gap 0.20%, weights 25/50/25, buy>=70, sell<=30, min 3 obs).
        // Adjust per-symbol here if the client wants different tuning later.
        return new AdaptiveOILearningEngine(
            stockName: $symbol,
            gapThresholdPct: 0.20,
            atmMinusWeight: 0.25,
            atmWeight: 0.50,
            atmPlusWeight: 0.25,
            buyThreshold: 70.0,
            sellThreshold: 30.0,
            profileMinN: 3,
        );
    }

    /**
     * Build $stockByDate / $ce / $pe in the shape AdaptiveOILearningEngine
     * expects. $stockByDate now comes from the real underlying-EQ table
     * (cp_stock_ohlc_15min — column `symbol`, no `base_symbol`), matching
     * the client's original "Stock" sheet 1:1. CE/PE come from cp_option_ohlc_15min.
     */
    private function loadAdaptiveSeries(int $configId, string $symbol, string $fromDate, string $toDate): array
    {
        $stockByDate = [];

        $stockRows = DB::table(self::STOCK_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('symbol', $symbol)
            ->whereBetween(DB::raw('DATE(trade_date)'), [$fromDate, $toDate])
            ->where('is_missing', false)
            ->select(['trade_date', 'interval_time', 'open', 'high', 'low', 'close', 'volume'])
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get();

        foreach ($stockRows as $r) {
            $d = Carbon::parse($r->trade_date)->toDateString();
            $t = substr($r->interval_time, 11, 5);

            $stockByDate[$d][] = [
                'date' => $d,
                'time' => $t,
                'open' => (float) $r->open,
                'high' => (float) $r->high,
                'low' => (float) $r->low,
                'close' => (float) $r->close,
                'volume' => (float) $r->volume,
            ];
        }

        foreach ($stockByDate as $d => $dayRows) {
            usort($stockByDate[$d], fn ($a, $b) => strcmp($a['time'], $b['time']));
        }

        $ce = [];
        $pe = [];

        $optRows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereBetween(DB::raw('DATE(trade_date)'), [$fromDate, $toDate])
            ->where('is_missing', false)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->select(['trade_date', 'interval_time', 'instrument_type', 'strike_position', 'open', 'high', 'low', 'close', 'oi'])
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get();

        foreach ($optRows as $r) {
            $d = Carbon::parse($r->trade_date)->toDateString();
            $t = substr($r->interval_time, 11, 5);

            // Fix 1: strict mapping, reject unrecognized strike_position instead
            // of defaulting to 'ATM' (which would silently corrupt the ATM leg).
            $strikeKey = $this->mapStrikePosition($r->strike_position);
            if ($strikeKey === null) {
                Log::warning("StockOIStrategy: unmapped strike_position '{$r->strike_position}' for {$symbol} {$d} {$t} — row skipped");
                continue;
            }

            $leg = [
                'strike' => 0,
                'open' => (float) $r->open,
                'high' => (float) $r->high,
                'low' => (float) $r->low,
                'close' => (float) $r->close,
                'volume' => 0,
                'oi' => (float) $r->oi,
                'type' => $r->instrument_type,
            ];

            if ($r->instrument_type === 'CE') {
                $ce[$d][$t][$strikeKey] = $leg;
            } else {
                $pe[$d][$t][$strikeKey] = $leg;
            }
        }

        $dates = array_values(array_intersect(array_keys($stockByDate), array_keys($ce), array_keys($pe)));
        sort($dates);

        return [$stockByDate, $ce, $pe, $dates];
    }

    // ── Shared helpers ───────────────────────────────────────────────────

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', '15min')
            ->where('is_active', 1)
            ->first();
    }

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->toDateString())) {
                return $prev->toDateString();
            }
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}