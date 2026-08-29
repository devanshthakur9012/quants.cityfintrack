<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Advanced OI Metrics — STANDALONE, READ-ONLY analysis page.
 *
 * Follows the exact same time convention and page pattern as
 * OIFlowSentimentController: T = today 14:45, T-1 = previous trading day
 * 15:00. No user-selectable time — only date (+ optional symbol filter),
 * same as the OI Flow Sentiment page. Reuses cp_option_ohlc_15min exactly
 * as-is. Does NOT touch collectors, does NOT touch OIFlowSentimentController,
 * does NOT change BUY CE / BUY PE / WAIT logic. Zero schema changes.
 *
 * ── STRUCTURAL DATA LIMITATIONS (confirmed from CpCollectOption.php) ──
 *
 * 1) STRIKE ROLL-OVER VELOCITY (R) — permanently INSUFFICIENT_DATA.
 *    CpCollectOption::resolveCePeExpiry() resolves exactly ONE expiry per
 *    symbol per trade_date, and collectOptionDay() only fetches strikes for
 *    that single expiry. There is no concurrent current-month + next-month
 *    option chain stored for the same trade_date, so ΔOI(next expiry) is
 *    never available. This is a data-collection gap, not a query bug — do
 *    not attempt to fabricate it.
 *
 * 2) INTRADAY OI MOMENTUM DELTA (ΔM) — permanently INSUFFICIENT_DATA.
 *    All three collectors (CpCollectOption / CpCollectFut / CpCollectStock)
 *    only support --timeframe=15min|30min|1hr. No 5-minute interval data
 *    exists anywhere in the schema. Do not interpolate 15-min into 5-min.
 *
 * ── ASSUMPTION FLAGGED ──
 * Decay Velocity's "3-strike basket" is not explicitly defined in the
 * client spec (unlike NTM Bias, which explicitly says ATM-1/ATM/ATM+1).
 * This implementation reuses the SAME ATM-1/ATM/ATM+1 window for Decay
 * Velocity. If the client meant a different window (e.g. ATM/ATM+1/ATM+2),
 * change BASKET_OFFSETS below — it is centralised in one place.
 */
class AdvancedOIMetricsController extends Controller
{
    private const TF             = '15min';
    private const ANALYSIS_TIME  = '14:45:00'; // today snapshot — same as OI Flow Sentiment
    private const PREV_DAY_TIME  = '15:00:00'; // previous trading day anchor — same as OI Flow Sentiment
    private const OPT_TABLE      = 'cp_option_ohlc_15min';

    /** Strike-ladder offsets used for both NTM Bias and Decay Velocity baskets. */
    private const BASKET_OFFSETS = [-1, 0, 1];

    private const DEEP_OTM_OFFSET = 4;

    public function index()
    {
        $pageTitle = 'Advanced OI Metrics';
        return view(activeTemplate() . 'user.advanced-oi-metrics.index', compact('pageTitle'));
    }

    /**
     * Same convention as OIFlowSentimentController::lastDate() — finds the
     * most recent trade_date that actually has a 14:45 snapshot, so the
     * page opens on real data instead of an empty "today".
     */
    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            $today  = Carbon::today()->toDateString();

            if (!$config) {
                return response()->json(['success' => false, 'last_date' => $today, 'is_today' => true]);
            }

            $lastDate = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->max('trade_date');

            if (!$lastDate) {
                $lastDate = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $config->id)
                    ->where('is_missing', false)
                    ->max('trade_date');
            }

            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;

            return response()->json(['success' => true, 'last_date' => $lastDate, 'is_today' => $lastDate === $today]);

        } catch (\Exception $e) {
            Log::error('AdvancedOIMetricsController lastDate: ' . $e->getMessage());
            return response()->json(['success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true]);
        }
    }

    public function getSymbols(Request $request): JsonResponse
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json([
                'success'   => true,
                'symbols'   => [],
                'no_config' => true,
                'message'   => 'No active analysis config found.',
            ]);
        }
        return response()->json(['success' => true, 'symbols' => $this->getConfigSymbols($config->id)]);
    }

    /**
     * Analyzes every symbol (or a filtered subset) for one date, always
     * using the fixed 14:45 / 15:00 snapshot pair — no time param, same
     * as OI Flow Sentiment's analyze().
     */
    public function analyze(Request $request): JsonResponse
    {
        $date = $request->get('date');

        if (!$date) {
            return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => []]);
        }

        $symbolReq = array_filter((array) $request->get('symbols', []));

        try {
            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => 'No active Analysis Config found. Go to Admin → Analysis Config.',
                    'data'      => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $rows = [];
            foreach ($symbols as $symbol) {
                $result  = $this->runAnalysisForSymbol($config, $symbol, $date);
                $rows[]  = array_merge(['symbol' => $symbol], $result);
            }

            usort($rows, fn ($a, $b) => strcmp($a['symbol'], $b['symbol']));

            $withData    = array_filter($rows, fn ($r) => $r['success'] && empty($r['no_data']));
            $bullish     = count(array_filter($withData, fn ($r) => ($r['advanced_oi']['signal'] ?? null) === 'BULLISH'));
            $bearish     = count(array_filter($withData, fn ($r) => ($r['advanced_oi']['signal'] ?? null) === 'BEARISH'));
            $neutral     = count(array_filter($withData, fn ($r) => ($r['advanced_oi']['signal'] ?? null) === 'NEUTRAL'));
            $noDataCount = count($rows) - count($withData);

            return response()->json([
                'success'            => true,
                'data'               => $rows,
                'date'               => $date,
                'is_today'           => $date === Carbon::today()->toDateString(),
                'available_symbols'  => $configSymbols,
                'total_records'      => count($rows),
                'bullish_count'      => $bullish,
                'bearish_count'      => $bearish,
                'neutral_count'      => $neutral,
                'no_data_count'      => $noDataCount,
                'message'            => count($rows) . ' symbol(s) analyzed for ' . $date,
            ]);

        } catch (\Exception $e) {
            Log::error('AdvancedOIMetricsController analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ═════════════════════════ SHARED PER-SYMBOL COMPUTATION ═════════════════════════

    /**
     * Loads live (today 14:45) + anchor (prev trading day 15:00) OI/volume
     * rows for ONE symbol, builds the strike ladder, and computes all six
     * advanced metrics. Time is always the class constants — never a param.
     */
    private function runAnalysisForSymbol(object $config, string $symbol, string $date): array
    {
        try {
            $prevDate = $this->getPreviousTradingDate($date);

            // ── Bulk load: LIVE snapshot rows (selected date, 14:45) ──
            $liveRows = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('base_symbol', $symbol)
                ->whereDate('trade_date', $date)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['instrument_type', 'strike', 'atm_strike', 'expiry_date', 'oi', 'volume'])
                ->get();

            // ── Bulk load: ANCHOR snapshot rows (prev trading day, 15:00) ──
            $anchorRows = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('base_symbol', $symbol)
                ->whereDate('trade_date', $prevDate)
                ->whereRaw('TIME(interval_time) = ?', [self::PREV_DAY_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['instrument_type', 'strike', 'oi'])
                ->get();

            if ($liveRows->isEmpty()) {
                return [
                    'success'     => true,
                    'no_data'     => true,
                    'message'     => "No data found for {$symbol} on {$date} " . substr(self::ANALYSIS_TIME, 0, 5) . '.',
                    'advanced_oi' => $this->emptyAdvancedOi(),
                ];
            }

            // ── Build lookup maps: [type][strike] => oi / volume ──
            $liveOi = ['CE' => [], 'PE' => []];
            $liveVol = ['CE' => [], 'PE' => []];
            $atmStrike = null;
            $expiryUsed = null;

            foreach ($liveRows as $r) {
                $liveOi[$r->instrument_type][(string) $r->strike] = (int) $r->oi;
                $liveVol[$r->instrument_type][(string) $r->strike] = (int) $r->volume;
                if ($atmStrike === null) $atmStrike = (float) $r->atm_strike;
                if ($expiryUsed === null) $expiryUsed = $r->expiry_date;
            }

            $anchorOi = ['CE' => [], 'PE' => []];
            foreach ($anchorRows as $r) {
                $anchorOi[$r->instrument_type][(string) $r->strike] = (int) $r->oi;
            }

            // ── Build actual strike ladder from LIVE data (CE side; PE strikes are identical) ──
            $ladder = collect(array_keys($liveOi['CE']))
                ->map(fn ($s) => (float) $s)
                ->unique()
                ->sort()
                ->values()
                ->toArray();

            $atmIndex = $this->findAtmIndex($ladder, $atmStrike);

            $advanced = [
                'meta' => [
                    'symbol'          => $symbol,
                    'date'            => $date,
                    'time'            => substr(self::ANALYSIS_TIME, 0, 5),
                    'anchor_date'     => $prevDate,
                    'anchor_time'     => substr(self::PREV_DAY_TIME, 0, 5),
                    'expiry_used'     => $expiryUsed,
                    'atm_strike'      => $atmStrike,
                    'strike_ladder'   => $ladder,
                ],
                'decay_velocity'      => $this->calcDecayVelocity($ladder, $atmIndex, $liveOi, $anchorOi),
                'oi_volume_efficiency'=> $this->calcEfficiency($liveOi, $anchorOi, $liveVol),
                'ntm_bias'            => $this->calcNtmBias($ladder, $atmIndex, $liveOi),
                'rollover_velocity'   => $this->calcRolloverVelocity(),      // always INSUFFICIENT_DATA — see class docblock
                'deep_otm_inflection' => $this->calcDeepOtmIndex($ladder, $atmIndex, $liveOi, $anchorOi),
                'intraday_momentum'   => $this->calcIntradayMomentum(),      // always INSUFFICIENT_DATA — see class docblock
            ];

            $advanced['bullish_score'] = 0;
            $advanced['bearish_score'] = 0;

            if (($advanced['decay_velocity']['ce_status'] ?? null) === 'TRIGGERED') $advanced['bullish_score']++;
            if (($advanced['decay_velocity']['pe_status'] ?? null) === 'TRIGGERED') $advanced['bearish_score']++;
            if (($advanced['oi_volume_efficiency']['ce_status'] ?? null) === 'TRIGGERED') $advanced['bullish_score']++;
            if (($advanced['oi_volume_efficiency']['pe_status'] ?? null) === 'TRIGGERED') $advanced['bearish_score']++;
            if (($advanced['ntm_bias']['bullish_status'] ?? null) === 'TRIGGERED') $advanced['bullish_score']++;
            if (($advanced['ntm_bias']['bearish_status'] ?? null) === 'TRIGGERED') $advanced['bearish_score']++;
            if (($advanced['deep_otm_inflection']['ce_status'] ?? null) === 'TRIGGERED') $advanced['bullish_score']++;
            if (($advanced['deep_otm_inflection']['pe_status'] ?? null) === 'TRIGGERED') $advanced['bearish_score']++;
            // rollover_velocity + intraday_momentum are structurally INSUFFICIENT_DATA — never contribute to score.

            $advanced['signal'] = $this->deriveSignal($advanced['bullish_score'], $advanced['bearish_score']);

            return ['success' => true, 'no_data' => false, 'advanced_oi' => $advanced];

        } catch (\Exception $e) {
            Log::error('AdvancedOIMetricsController runAnalysisForSymbol (' . $symbol . '): ' . $e->getMessage());
            return [
                'success'     => false,
                'message'     => $e->getMessage(),
                'advanced_oi' => $this->emptyAdvancedOi(),
            ];
        }
    }

    // ═════════════════════════ METRIC 1 — DECAY VELOCITY ═════════════════════════

    private function calcDecayVelocity(array $ladder, ?int $atmIndex, array $liveOi, array $anchorOi): array
    {
        $ce = $this->basketVelocity($ladder, $atmIndex, $liveOi['CE'], $anchorOi['CE']);
        $pe = $this->basketVelocity($ladder, $atmIndex, $liveOi['PE'], $anchorOi['PE']);

        return [
            'ce'        => $ce,
            'pe'        => $pe,
            'ce_status' => $ce === null ? 'INSUFFICIENT_DATA' : ($ce <= 0.70 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
            'pe_status' => $pe === null ? 'INSUFFICIENT_DATA' : ($pe <= 0.70 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
        ];
    }

    private function basketVelocity(array $ladder, ?int $atmIndex, array $liveMap, array $anchorMap): ?float
    {
        if ($atmIndex === null) return null;

        $liveSum = 0; $anchorSum = 0;

        foreach (self::BASKET_OFFSETS as $offset) {
            $strike = $this->strikeAtOffset($ladder, $atmIndex, $offset);
            if ($strike === null) return null;

            $key = (string) $strike;
            if (!array_key_exists($key, $liveMap) || !array_key_exists($key, $anchorMap)) return null;

            $liveSum   += $liveMap[$key];
            $anchorSum += $anchorMap[$key];
        }

        if ($anchorSum <= 0) return null;

        return round($liveSum / $anchorSum, 4);
    }

    // ═════════════════════════ METRIC 2 — OI-TO-VOLUME EFFICIENCY ═════════════════════════

    private function calcEfficiency(array $liveOi, array $anchorOi, array $liveVol): array
    {
        $ce = $this->efficiencyForSide($liveOi['CE'], $anchorOi['CE'], $liveVol['CE']);
        $pe = $this->efficiencyForSide($liveOi['PE'], $anchorOi['PE'], $liveVol['PE']);

        return [
            'ce'        => $ce,
            'pe'        => $pe,
            'ce_status' => $ce === null ? 'INSUFFICIENT_DATA' : ($ce >= 0.40 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
            'pe_status' => $pe === null ? 'INSUFFICIENT_DATA' : ($pe >= 0.40 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
        ];
    }

    private function efficiencyForSide(array $liveMap, array $anchorMap, array $volMap): ?float
    {
        $liveTotal   = array_sum($liveMap);
        $anchorTotal = array_sum($anchorMap);
        $volTotal    = array_sum($volMap);

        if ($volTotal <= 0) return null;
        if (empty($anchorMap)) return null;

        return round(abs($liveTotal - $anchorTotal) / $volTotal, 4);
    }

    // ═════════════════════════ METRIC 3 — NTM BIAS RATIO ═════════════════════════

    private function calcNtmBias(array $ladder, ?int $atmIndex, array $liveOi): array
    {
        if ($atmIndex === null) {
            return [
                'ratio' => null, 'pe_sum' => null, 'ce_sum' => null,
                'bullish_status' => 'INSUFFICIENT_DATA', 'bearish_status' => 'INSUFFICIENT_DATA',
            ];
        }

        $peSum = 0; $ceSum = 0; $complete = true;

        foreach (self::BASKET_OFFSETS as $offset) {
            $strike = $this->strikeAtOffset($ladder, $atmIndex, $offset);
            if ($strike === null) { $complete = false; break; }
            $key = (string) $strike;
            if (!array_key_exists($key, $liveOi['PE']) || !array_key_exists($key, $liveOi['CE'])) { $complete = false; break; }
            $peSum += $liveOi['PE'][$key];
            $ceSum += $liveOi['CE'][$key];
        }

        if (!$complete || $ceSum <= 0) {
            return [
                'ratio' => null, 'pe_sum' => $complete ? $peSum : null, 'ce_sum' => $complete ? $ceSum : null,
                'bullish_status' => 'INSUFFICIENT_DATA', 'bearish_status' => 'INSUFFICIENT_DATA',
            ];
        }

        $ratio = round($peSum / $ceSum, 4);

        return [
            'ratio'          => $ratio,
            'pe_sum'         => $peSum,
            'ce_sum'         => $ceSum,
            'bullish_status' => $ratio >= 1.25 ? 'TRIGGERED' : 'NOT_TRIGGERED',
            'bearish_status' => $ratio <= 0.75 ? 'TRIGGERED' : 'NOT_TRIGGERED',
        ];
    }

    // ═════════════════════════ METRIC 4 — STRIKE ROLL-OVER VELOCITY ═════════════════════════
    // Permanently INSUFFICIENT_DATA — see class docblock.

    private function calcRolloverVelocity(): array
    {
        return [
            'ce' => null, 'pe' => null,
            'ce_status' => 'INSUFFICIENT_DATA', 'pe_status' => 'INSUFFICIENT_DATA',
            'reason' => 'Only a single expiry is collected per symbol per trade_date (see CpCollectOption::resolveCePeExpiry). Next-month expiry OI is not stored alongside current-month data, so this ratio cannot be computed from existing data.',
        ];
    }

    // ═════════════════════════ METRIC 5 — DEEP OTM INFLECTION INDEX ═════════════════════════

    private function calcDeepOtmIndex(array $ladder, ?int $atmIndex, array $liveOi, array $anchorOi): array
    {
        $ce = $this->deepOtmForSide($ladder, $atmIndex, $liveOi['CE'], $anchorOi['CE']);
        $pe = $this->deepOtmForSide($ladder, $atmIndex, $liveOi['PE'], $anchorOi['PE']);

        return [
            'ce'        => $ce,
            'pe'        => $pe,
            'ce_status' => $ce === null ? 'INSUFFICIENT_DATA' : ($ce >= 3.0 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
            'pe_status' => $pe === null ? 'INSUFFICIENT_DATA' : ($pe >= 3.0 ? 'TRIGGERED' : 'NOT_TRIGGERED'),
        ];
    }

    private function deepOtmForSide(array $ladder, ?int $atmIndex, array $liveMap, array $anchorMap): ?float
    {
        if ($atmIndex === null) return null;

        $atmStrike = $this->strikeAtOffset($ladder, $atmIndex, 0);
        $otmStrike = $this->strikeAtOffset($ladder, $atmIndex, self::DEEP_OTM_OFFSET);
        if ($atmStrike === null || $otmStrike === null) return null;

        $atmKey = (string) $atmStrike;
        $otmKey = (string) $otmStrike;

        if (!array_key_exists($atmKey, $liveMap) || !array_key_exists($atmKey, $anchorMap)) return null;
        if (!array_key_exists($otmKey, $liveMap) || !array_key_exists($otmKey, $anchorMap)) return null;

        $atmChange = $liveMap[$atmKey] - $anchorMap[$atmKey];
        $otmChange = $liveMap[$otmKey] - $anchorMap[$otmKey];

        if ($atmChange === 0) return null;

        return round($otmChange / $atmChange, 4);
    }

    // ═════════════════════════ METRIC 6 — INTRADAY OI MOMENTUM DELTA ═════════════════════════
    // Permanently INSUFFICIENT_DATA — see class docblock.

    private function calculateIntradayOIMomentumDelta(): array
    {
        return [
            'ce' => null, 'pe' => null,
            'ce_status' => 'INSUFFICIENT_DATA', 'pe_status' => 'INSUFFICIENT_DATA',
            'reason' => 'CpCollectOption / CpCollectFut / CpCollectStock only support --timeframe=15min|30min|1hr. No 5-minute OI observations exist in this schema, so rate-of-change over 5-minute intervals cannot be computed without fabricating data.',
        ];
    }

    private function calcIntradayMomentum(): array
    {
        return $this->calculateIntradayOIMomentumDelta();
    }

    // ═════════════════════════ SIGNAL ═════════════════════════

    private function deriveSignal(int $bullish, int $bearish): string
    {
        if ($bullish === 0 && $bearish === 0) return 'NEUTRAL';
        if ($bullish > $bearish) return 'BULLISH';
        if ($bearish > $bullish) return 'BEARISH';
        return 'NEUTRAL';
    }

    private function emptyAdvancedOi(): array
    {
        $ins = ['ce' => null, 'pe' => null, 'ce_status' => 'INSUFFICIENT_DATA', 'pe_status' => 'INSUFFICIENT_DATA'];
        return [
            'decay_velocity'       => $ins,
            'oi_volume_efficiency' => $ins,
            'ntm_bias'             => ['ratio' => null, 'pe_sum' => null, 'ce_sum' => null, 'bullish_status' => 'INSUFFICIENT_DATA', 'bearish_status' => 'INSUFFICIENT_DATA'],
            'rollover_velocity'    => $this->calcRolloverVelocity(),
            'deep_otm_inflection'  => $ins,
            'intraday_momentum'    => $this->calcIntradayMomentum(),
            'bullish_score'        => 0,
            'bearish_score'        => 0,
            'signal'               => 'INSUFFICIENT_DATA',
        ];
    }

    // ═════════════════════════ STRIKE LADDER HELPERS ═════════════════════════

    private function findAtmIndex(array $ladder, ?float $atmStrike): ?int
    {
        if ($atmStrike === null || empty($ladder)) return null;

        foreach ($ladder as $i => $s) {
            if (abs($s - $atmStrike) < 0.01) return $i;
        }

        $closestIdx = null; $closestDiff = null;
        foreach ($ladder as $i => $s) {
            $diff = abs($s - $atmStrike);
            if ($closestDiff === null || $diff < $closestDiff) { $closestDiff = $diff; $closestIdx = $i; }
        }
        return $closestIdx;
    }

    private function strikeAtOffset(array $ladder, ?int $atmIndex, int $offset): ?float
    {
        if ($atmIndex === null) return null;
        $idx = $atmIndex + $offset;
        return $ladder[$idx] ?? null;
    }

    // ═════════════════════════ SHARED HELPERS (mirrors OIFlowSentimentController convention) ═════════════════════════

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

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->toDateString())) return $prev->toDateString();
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}