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
 * ── DATA SOURCE ASSUMPTIONS (please confirm against the real schema) ──
 * This reuses `cp_option_ohlc_15min` (same table as OIFlowSentimentController)
 * with the existing columns: analysis_config_id, base_symbol, trade_date,
 * interval_time, instrument_type, strike_position, oi, close, is_missing.
 *
 * Two things this feature needs that OIFlowSentimentController's queries
 * didn't touch — please verify these exist / adjust the column names in
 * loadFutureRows()/loadOptionSeries() below if they don't match:
 *   1. Row-level `open`, `high`, `low` columns on cp_option_ohlc_15min
 *      (table is named *_ohlc_15min so this is assumed to already exist).
 *   2. A FUTURES row per (symbol, date, interval_time) — assumed stored
 *      with instrument_type = 'FUT' (strike_position null/'FUT') in the
 *      same table. If futures actually live in a separate table, only
 *      loadFutureRows() needs to change.
 */
class StockOIStrategyController extends Controller
{
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

    // ── Last available date (across all 3 symbols) ─────────────────────

    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            $today = Carbon::today()->toDateString();

            if (!$config) {
                return response()->json(['success' => false, 'last_date' => $today, 'is_today' => true]);
            }

            $lastDate = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', self::SYMBOLS)
                ->where('is_missing', false)
                ->max('trade_date');

            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;

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

        if (empty($rows)) {
            return ['signal' => 'NO_TRADE', 'reason' => 'No 15-min data found for ' . $symbol . ' around ' . $date];
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
            return ['signal' => 'NO_TRADE', 'reason' => 'No rows found for ' . $date];
        }

        return $engine->analyse1030($currentIndex);
    }

    /**
     * Build the wide-row shape Lichsgfin1030OIEngine expects:
     * one row per 15-min interval with future OHLC/OI + CE/PE ATM-1/ATM/ATM+1 close+OI.
     */
    private function loadCombinedRows(int $configId, string $symbol, string $fromDate, string $toDate): array
    {
        $optRows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereBetween(DB::raw('DATE(trade_date)'), [$fromDate, $toDate])
            ->where('is_missing', false)
            ->whereIn('instrument_type', ['CE', 'PE', 'FUT'])
            ->select(['trade_date', 'interval_time', 'instrument_type', 'strike_position', 'open', 'high', 'low', 'close', 'oi'])
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get();

        // Pivot into datetime => fields
        $byDatetime = [];
        foreach ($optRows as $r) {
            $dt = Carbon::parse($r->trade_date)->toDateString() . ' ' . substr($r->interval_time, 0, 5) . ':00';
            $byDatetime[$dt] ??= ['datetime' => $dt];

            if ($r->instrument_type === 'FUT') {
                $byDatetime[$dt]['future_open'] = (float) $r->open;
                $byDatetime[$dt]['future_high'] = (float) $r->high;
                $byDatetime[$dt]['future_low'] = (float) $r->low;
                $byDatetime[$dt]['future_close'] = (float) $r->close;
                $byDatetime[$dt]['future_oi'] = (float) $r->oi;
                continue;
            }

            $side = strtolower($r->instrument_type); // ce | pe
            $strikeKey = match ($r->strike_position) {
                'ATM-1', 'ATM_MINUS_1' => 'atm_minus_1',
                'ATM+1', 'ATM_PLUS_1' => 'atm_plus_1',
                default => 'atm',
            };

            $byDatetime[$dt]["{$side}_{$strikeKey}_close"] = (float) $r->close;
            $byDatetime[$dt]["{$side}_{$strikeKey}_oi"] = (float) $r->oi;
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
            return ['signal' => 'NO_TRADE', 'reason' => 'No synchronized data found for ' . $symbol];
        }

        // Historical profile is built from every day BEFORE the selected one —
        // the selected date is always treated as "today" being analysed.
        $historyDates = array_values(array_filter($dates, fn ($d) => $d < $date));
        $profile = $engine->buildHistoricalProfile($stockByDate, $ce, $pe, $historyDates);

        $latest = $engine->latestAnalysis($stockByDate, $ce, $pe, $dates, $profile);
        $gapStudy = $engine->gapReversalStudy($stockByDate, $historyDates);
        $backtest = $engine->backtest($stockByDate, $ce, $pe, $historyDates, $profile);

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
     * expects. The client's original scripts used a dedicated "Stock" sheet
     * (underlying cash price) for OHLC; since the DB table here only carries
     * option + futures rows, the FUT row is used as the underlying-price
     * proxy — confirm with the client whether a separate stock/futures OHLC
     * source should be used instead.
     */
    private function loadAdaptiveSeries(int $configId, string $symbol, string $fromDate, string $toDate): array
    {
        $rows = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereBetween(DB::raw('DATE(trade_date)'), [$fromDate, $toDate])
            ->where('is_missing', false)
            ->whereIn('instrument_type', ['CE', 'PE', 'FUT'])
            ->select(['trade_date', 'interval_time', 'instrument_type', 'strike_position', 'open', 'high', 'low', 'close', 'oi'])
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get();

        $stockByDate = [];
        $ce = [];
        $pe = [];

        foreach ($rows as $r) {
            $d = Carbon::parse($r->trade_date)->toDateString();
            $t = substr($r->interval_time, 0, 5);

            if ($r->instrument_type === 'FUT') {
                $stockByDate[$d][] = [
                    'date' => $d,
                    'time' => $t,
                    'open' => (float) $r->open,
                    'high' => (float) $r->high,
                    'low' => (float) $r->low,
                    'close' => (float) $r->close,
                    'volume' => 0,
                ];
                continue;
            }

            $strikeKey = match ($r->strike_position) {
                'ATM-1', 'ATM_MINUS_1' => 'ATM-1',
                'ATM+1', 'ATM_PLUS_1' => 'ATM+1',
                default => 'ATM',
            };

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

        foreach ($stockByDate as $d => $dayRows) {
            usort($stockByDate[$d], fn ($a, $b) => strcmp($a['time'], $b['time']));
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