<?php
// FILE: app/Http/Controllers/User/StrataOptionsFairValueController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Helpers\OptionFairPriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Strata — Options Fair Value Engine
 *
 * Black-Scholes fair price vs market LTP for CE and PE.
 *
 * ── Root Cause Fix (circular IV loop) ────────────────────────────────────────
 * The original code derived ATM IV from the CE LTP, then computed BS fair price
 * for CE using that same IV → BS(IV_from_CE) ≈ CE_LTP → diff ≈ 0 always.
 *
 * Fix: Cross-leg IV (industry standard)
 *   • ce_iv_for_bs  = IV solved from ATM PE price  → used to price CE fairly
 *   • pe_iv_for_bs  = IV solved from ATM CE price  → used to price PE fairly
 *   • displayed_iv  = average of both legs          → shown in ATM IV column
 *
 * This is equivalent to implied-vol parity: the market's consensus IV for the
 * underlying, not for the specific option being evaluated.  Any CE/PE mispricing
 * now shows up as a real non-zero diff.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class StrataOptionsFairValueController extends Controller
{
    private const TIMEFRAMES = ['15min', '30min', '1hr'];

    // ─────────────────────────────────────────────────────────────────────────
    //  Page
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Strata — Options Fair Value';
        return view($this->activeTemplate . 'user.strata-options-fv.index', compact('pageTitle'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Symbols
    // ─────────────────────────────────────────────────────────────────────────

    public function getSymbols(Request $request)
    {
        $timeframe = $this->resolveTimeframe($request);
        $config    = $this->getActiveConfig($timeframe);

        if (!$config) {
            return response()->json([
                'success'   => true,
                'symbols'   => [],
                'no_config' => true,
                'message'   => "No active Analysis Config for [{$timeframe}].",
            ]);
        }

        return response()->json([
            'success'   => true,
            'symbols'   => $this->getConfigSymbols($config->id),
            'timeframe' => $timeframe,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Analyze
    // ─────────────────────────────────────────────────────────────────────────

    public function analyze(Request $request)
    {
        try {
            $timeframe    = $this->resolveTimeframe($request);
            $strikeFilter = $request->get('strike_filter', 'ATM');
            $sortBy       = $request->get('sort_by', 'symbol');
            $symbolParam  = $request->get('symbol');
            $dateFilter   = $request->get('date');

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => "No active Analysis Config for [{$timeframe}].",
                ]);
            }

            $allSymbols = $this->getConfigSymbols($config->id);
            if (empty($allSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.']);
            }

            $optTable = 'cp_option_ohlc_' . $timeframe;
            $futTable = 'cp_fut_ohlc_'    . $timeframe;

            // ── Resolve trade date ────────────────────────────────────────
            if ($dateFilter) {
                $exists = DB::table($optTable)
                    ->where('analysis_config_id', $config->id)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->whereDate('trade_date', $dateFilter)
                    ->exists();

                if (!$exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No data for ' . $dateFilter . '. Market may have been closed.',
                    ]);
                }
                $tradeDate = $dateFilter;
            } else {
                $tradeDate = DB::table($optTable)
                    ->where('analysis_config_id', $config->id)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->max(DB::raw('DATE(trade_date)'));

                if (!$tradeDate) {
                    return response()->json(['success' => false, 'message' => 'No data found in database.']);
                }
            }

            $isToday = ($tradeDate === now()->toDateString());
            $stepMap = $this->buildStepMap($allSymbols, $tradeDate, $optTable, $config->id);

            // ═════════════════════════════════════════════════════════════
            //  SINGLE-SYMBOL — all candles
            // ═════════════════════════════════════════════════════════════
            if ($symbolParam && in_array($symbolParam, $allSymbols)) {

                $cancleTimes = DB::table($optTable)
                    ->where('analysis_config_id', $config->id)
                    ->where('base_symbol', $symbolParam)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', false)
                    ->selectRaw("DISTINCT TIME_FORMAT(interval_time, '%H:%i') AS ct")
                    ->orderByRaw('interval_time ASC')
                    ->pluck('ct')->unique()->values()->toArray();

                if (empty($cancleTimes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No candle data for ' . $symbolParam . ' on ' . $tradeDate,
                    ]);
                }

                $step = $stepMap[$symbolParam] ?? 50;
                $rows = [];

                foreach ($cancleTimes as $ct) {
                    $row = $this->buildRow(
                        $symbolParam, $tradeDate, $ct, $strikeFilter,
                        $step, $config->id, $optTable, $futTable
                    );
                    if ($row) $rows[] = $row;
                }

                return response()->json([
                    'success'       => true,
                    'trade_date'    => $tradeDate,
                    'latest_time'   => end($cancleTimes),
                    'is_today'      => $isToday,
                    'mode'          => 'single',
                    'strike_filter' => $strikeFilter,
                    'timeframe'     => $timeframe,
                    'total_rows'    => count($rows),
                    'summary'       => $this->buildSummary($rows),
                    'rows'          => $rows,
                ]);
            }

            // ═════════════════════════════════════════════════════════════
            //  ALL-SYMBOLS — latest candle only
            // ═════════════════════════════════════════════════════════════
            $latestTime = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereDate('trade_date', $tradeDate)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->max(DB::raw("TIME_FORMAT(interval_time, '%H:%i')"));

            $rows = [];
            foreach ($allSymbols as $symbol) {
                $step = $stepMap[$symbol] ?? 50;
                $row  = $this->buildRow(
                    $symbol, $tradeDate, $latestTime, $strikeFilter,
                    $step, $config->id, $optTable, $futTable
                );
                if ($row) $rows[] = $row;
            }

            $rows = $this->sortRows($rows, $sortBy);

            return response()->json([
                'success'       => true,
                'trade_date'    => $tradeDate,
                'latest_time'   => $latestTime,
                'is_today'      => $isToday,
                'mode'          => 'all',
                'strike_filter' => $strikeFilter,
                'timeframe'     => $timeframe,
                'total_rows'    => count($rows),
                'summary'       => $this->buildSummary($rows),
                'rows'          => $rows,
            ]);

        } catch (\Exception $e) {
            Log::error('StrataOptionsFV: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Build one row
    //
    //  IV ISOLATION (the critical fix):
    //
    //  The ATM strike's CE and PE options share the same underlying. We use
    //  put-call parity logic to get a cross-leg IV:
    //
    //    ce_iv_for_bs = IV solved from ATM PE price  → price CE with this IV
    //    pe_iv_for_bs = IV solved from ATM CE price  → price PE with this IV
    //
    //  Why this works:
    //    • Both CE and PE at ATM reflect the SAME underlying volatility.
    //    • Using the opposite leg's IV to price the target leg breaks the
    //      circular loop (IV → BS → same price → diff = 0).
    //    • Any difference between the fair price and the market price is now a
    //      genuine mispricing signal, not a mathematical identity.
    //
    //  For ATM+1 / ATM-1 strikes:
    //    • We still solve IV from the ATM CE and ATM PE candles (not the OTM
    //      candle's own price). Same cross-leg logic applies.
    // ─────────────────────────────────────────────────────────────────────────

    private function buildRow(
        string $symbol,
        string $date,
        string $candleTime,
        string $strikeFilter,
        float  $step,
        int    $configId,
        string $optTable,
        string $futTable
    ): ?array {

        // ── Spot from FUT ─────────────────────────────────────────────────
        $futRow = DB::table($futTable)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)
            ->where('is_missing', false)
            ->whereRaw("TIME_FORMAT(interval_time, '%H:%i') <= ?", [$candleTime])
            ->orderByDesc('interval_time')
            ->select(['close', 'atm_strike'])
            ->first();

        $spot = $futRow ? (float) $futRow->close : 0;

        if ($spot <= 0) {
            $spot = $this->estimateSpot($symbol, $date, $candleTime, $configId, $optTable);
        }
        if ($spot <= 0) return null;

        // ── ATM + target strike ───────────────────────────────────────────
        $atm = $futRow && $futRow->atm_strike > 0
            ? (float) $futRow->atm_strike
            : round($spot / $step) * $step;

        $targetStrike = match ($strikeFilter) {
            'ATM+1' => $atm + $step,
            'ATM-1' => $atm - $step,
            default => $atm,
        };

        // ── Expiry + DTE ──────────────────────────────────────────────────
        $expiryRow = DB::table($optTable)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereNotNull('expiry_date')
            ->whereRaw('DATE(expiry_date) >= ?', [$date])
            ->orderByRaw('expiry_date ASC')
            ->select(['expiry_date'])
            ->first();

        $expiry       = $expiryRow ? substr($expiryRow->expiry_date, 0, 10) : null;
        $daysToExpiry = $expiry
            ? (int) max(1, Carbon::parse($date)->diffInDays(Carbon::parse($expiry)))
            : 30;

        // ── ATM CE and PE candles (always from ATM strike) ────────────────
        // Even when strikeFilter = ATM+1/ATM-1, we still solve IV from ATM,
        // then apply that IV to the target OTM/ITM strike.
        $atmCeCnd = $this->getOptionCandle($symbol, $date, $candleTime, 'CE', $atm, $expiry, $configId, $optTable);
        $atmPeCnd = $this->getOptionCandle($symbol, $date, $candleTime, 'PE', $atm, $expiry, $configId, $optTable);

        $atmCeLtp = $atmCeCnd ? (float) $atmCeCnd->close : 0;
        $atmPeLtp = $atmPeCnd ? (float) $atmPeCnd->close : 0;

        // ── CROSS-LEG IV DERIVATION ───────────────────────────────────────
        // iv_from_ce = solve IV using ATM CE price  → used to price PE
        // iv_from_pe = solve IV using ATM PE price  → used to price CE
        $ivFromCe = $atmCeLtp > 0
            ? OptionFairPriceCalculator::calcIV($spot, $atm, $daysToExpiry, $atmCeLtp, 'CE')
            : null;

        $ivFromPe = $atmPeLtp > 0
            ? OptionFairPriceCalculator::calcIV($spot, $atm, $daysToExpiry, $atmPeLtp, 'PE')
            : null;

        // Displayed ATM IV = average of both legs (consensus IV for the underlying)
        $displayIv = null;
        if ($ivFromCe !== null && $ivFromPe !== null) {
            $displayIv = ($ivFromCe + $ivFromPe) / 2;
        } elseif ($ivFromCe !== null) {
            $displayIv = $ivFromCe;
        } elseif ($ivFromPe !== null) {
            $displayIv = $ivFromPe;
        }

        // Fall back to symbol default if both legs fail
        $defaultIvMap = [
            'NIFTY' => 0.15, 'BANKNIFTY' => 0.18, 'FINNIFTY' => 0.16,
            'MIDCPNIFTY' => 0.20, 'SENSEX' => 0.15, 'BANKEX' => 0.18,
        ];
        $defaultIv = $defaultIvMap[strtoupper($symbol)] ?? 0.20;

        // IV to use for CE fair-price = IV derived from PE (cross-leg)
        // IV to use for PE fair-price = IV derived from CE (cross-leg)
        $ivForCe = $ivFromPe ?? $displayIv ?? $defaultIv;
        $ivForPe = $ivFromCe ?? $displayIv ?? $defaultIv;

        // ── Target strike candles ─────────────────────────────────────────
        $ceCnd = $this->getOptionCandle($symbol, $date, $candleTime, 'CE', $targetStrike, $expiry, $configId, $optTable);
        $peCnd = $this->getOptionCandle($symbol, $date, $candleTime, 'PE', $targetStrike, $expiry, $configId, $optTable);

        $ceLtp = $ceCnd ? (float) $ceCnd->close : 0;
        $peLtp = $peCnd ? (float) $peCnd->close : 0;

        if ($ceLtp <= 0 && $peLtp <= 0) return null;

        $snapshotTime = $ceCnd
            ? substr($ceCnd->interval_time, 11, 5)
            : ($peCnd ? substr($peCnd->interval_time, 11, 5) : $candleTime);

        // ── CE fair price (priced using PE-derived IV) ────────────────────
        $ceFairPrice = null;
        $ceDiff      = null;
        $ceDiffPct   = null;
        $ceStatus    = 'N/A';

        if ($ceLtp > 0) {
            $ceFairPrice = OptionFairPriceCalculator::calculateFairPrice(
                $spot, $targetStrike, $daysToExpiry, $ivForCe, 0.01, 'CE'
            );
            if ($ceFairPrice > 0) {
                $ceDiff    = round($ceLtp - $ceFairPrice, 2);
                $ceDiffPct = round(($ceDiff / $ceFairPrice) * 100, 2);
                $ceStatus  = OptionFairPriceCalculator::valuationStatus($ceLtp, $ceFairPrice);
            }
        }

        // ── PE fair price (priced using CE-derived IV) ────────────────────
        $peFairPrice = null;
        $peDiff      = null;
        $peDiffPct   = null;
        $peStatus    = 'N/A';

        if ($peLtp > 0) {
            $peFairPrice = OptionFairPriceCalculator::calculateFairPrice(
                $spot, $targetStrike, $daysToExpiry, $ivForPe, 0.01, 'PE'
            );
            if ($peFairPrice > 0) {
                $peDiff    = round($peLtp - $peFairPrice, 2);
                $peDiffPct = round(($peDiff / $peFairPrice) * 100, 2);
                $peStatus  = OptionFairPriceCalculator::valuationStatus($peLtp, $peFairPrice);
            }
        }

        // ── Expected move ─────────────────────────────────────────────────
        $expectedMove = $displayIv !== null
            ? OptionFairPriceCalculator::expectedMove($spot, $displayIv, $daysToExpiry)
            : null;

        return [
            'symbol'         => $symbol,
            'time'           => $snapshotTime,
            'spot'           => round($spot, 2),
            'atm_strike'     => $atm,
            'strike'         => $targetStrike,
            'strike_level'   => $strikeFilter,
            'days_to_expiry' => $daysToExpiry,
            'expiry_date'    => $expiry,
            // CE
            'ce_ltp'         => $ceLtp > 0 ? round($ceLtp, 2) : null,
            'ce_fair'        => $ceFairPrice,
            'ce_status'      => $ceStatus,
            'ce_diff'        => $ceDiff,
            'ce_diff_pct'    => $ceDiffPct,
            // PE
            'pe_ltp'         => $peLtp > 0 ? round($peLtp, 2) : null,
            'pe_fair'        => $peFairPrice,
            'pe_status'      => $peStatus,
            'pe_diff'        => $peDiff,
            'pe_diff_pct'    => $peDiffPct,
            // shared
            'atm_iv'         => $displayIv !== null ? round($displayIv * 100, 2) : null,
            'iv_from_ce'     => $ivFromCe  !== null ? round($ivFromCe  * 100, 2) : null,
            'iv_from_pe'     => $ivFromPe  !== null ? round($ivFromPe  * 100, 2) : null,
            'expected_move'  => $expectedMove,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Dynamic step map (cp_option_ohlc gaps → zerodha_instruments → 50)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildStepMap(array $symbols, string $date, string $optTable, int $configId): array
    {
        $strikeRows = DB::table($optTable)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('strike')
            ->select(['base_symbol', 'strike'])
            ->distinct()
            ->orderBy('base_symbol')->orderBy('strike')
            ->get();

        $stepMap  = [];
        $bySymbol = $strikeRows->groupBy('base_symbol');

        foreach ($bySymbol as $sym => $rows) {
            $strikes = $rows->pluck('strike')->map(fn($v) => (float)$v)->sort()->values()->toArray();
            if (count($strikes) >= 2) {
                $gaps = [];
                for ($i = 1; $i < count($strikes); $i++) {
                    $g = $strikes[$i] - $strikes[$i - 1];
                    if ($g > 0) $gaps[] = (int) round($g);
                }
                if (!empty($gaps)) {
                    $counts = array_count_values($gaps); arsort($counts);
                    $stepMap[$sym] = (float) array_key_first($counts);
                    continue;
                }
            }
            $stepMap[$sym] = null;
        }

        // Fill missing from zerodha_instruments
        $missing = array_unique(array_merge(
            array_keys(array_filter($stepMap, fn($v) => $v === null)),
            array_diff($symbols, array_keys($stepMap))
        ));

        if (!empty($missing)) {
            $zRows = DB::table('zerodha_instruments')
                ->whereIn('name', $missing)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('strike')->where('strike', '>', 0)
                ->select(['name', 'strike'])->orderBy('name')->orderBy('strike')
                ->get();

            foreach ($zRows->groupBy('name') as $sym => $g) {
                $strikes = $g->pluck('strike')->map(fn($v) => (float)$v)->sort()->values()->toArray();
                if (count($strikes) >= 2) {
                    $gaps = [];
                    for ($i = 1; $i < min(count($strikes), 10); $i++) {
                        $g2 = $strikes[$i] - $strikes[$i - 1];
                        if ($g2 > 0) $gaps[] = (int) round($g2);
                    }
                    if (!empty($gaps)) {
                        $counts = array_count_values($gaps); arsort($counts);
                        $stepMap[$sym] = (float) array_key_first($counts);
                        continue;
                    }
                }
                $stepMap[$sym] = 50.0;
            }
        }

        foreach ($symbols as $sym) {
            if (empty($stepMap[$sym])) $stepMap[$sym] = 50.0;
        }

        return $stepMap;
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getOptionCandle(
        string $symbol, string $date, string $candleTime,
        string $type, float $strike, ?string $expiry,
        int $configId, string $optTable
    ): ?object {
        $q = DB::table($optTable)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->where('strike', $strike)
            ->where('is_missing', false);

        if ($expiry) $q->whereDate('expiry_date', $expiry);

        $exact = (clone $q)->whereRaw("TIME_FORMAT(interval_time, '%H:%i') = ?", [$candleTime])->first();
        if ($exact) return $exact;

        return $q->whereRaw("TIME_FORMAT(interval_time, '%H:%i') <= ?", [$candleTime])
            ->orderByDesc('interval_time')->first();
    }

    private function estimateSpot(string $symbol, string $date, string $candleTime, int $configId, string $optTable): float
    {
        $rows = DB::table($optTable)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->where('is_missing', false)
            ->whereRaw("TIME_FORMAT(interval_time, '%H:%i') <= ?", [$candleTime])
            ->select(['instrument_type', 'strike', 'close'])
            ->orderByDesc('interval_time')->get();

        $bestSpot = 0.0; $bestDiff = PHP_FLOAT_MAX;
        foreach ($rows->groupBy('strike') as $strike => $g) {
            $ce = $g->where('instrument_type', 'CE')->first();
            $pe = $g->where('instrument_type', 'PE')->first();
            if ($ce && $pe) {
                $diff = abs((float)$ce->close - (float)$pe->close);
                if ($diff < $bestDiff) { $bestDiff = $diff; $bestSpot = (float) $strike; }
            }
        }
        return $bestSpot;
    }

    private function buildSummary(array $rows): array
    {
        $c = collect($rows);
        return [
            'ceOver'  => $c->where('ce_status', 'OVERPRICED')->count(),
            'ceUnder' => $c->where('ce_status', 'UNDERPRICED')->count(),
            'peOver'  => $c->where('pe_status', 'OVERPRICED')->count(),
            'peUnder' => $c->where('pe_status', 'UNDERPRICED')->count(),
        ];
    }

    private function sortRows(array $rows, string $sortBy): array
    {
        switch ($sortBy) {
            case 'ce_overpriced':  usort($rows, fn($a, $b) => ($b['ce_diff_pct'] ?? -999) <=> ($a['ce_diff_pct'] ?? -999)); break;
            case 'ce_underpriced': usort($rows, fn($a, $b) => ($a['ce_diff_pct'] ?? 999)  <=> ($b['ce_diff_pct'] ?? 999));  break;
            case 'pe_overpriced':  usort($rows, fn($a, $b) => ($b['pe_diff_pct'] ?? -999) <=> ($a['pe_diff_pct'] ?? -999)); break;
            case 'pe_underpriced': usort($rows, fn($a, $b) => ($a['pe_diff_pct'] ?? 999)  <=> ($b['pe_diff_pct'] ?? 999));  break;
            case 'mispricing':     usort($rows, fn($a, $b) => max(abs($b['ce_diff_pct'] ?? 0), abs($b['pe_diff_pct'] ?? 0)) <=> max(abs($a['ce_diff_pct'] ?? 0), abs($a['pe_diff_pct'] ?? 0))); break;
            default:               usort($rows, fn($a, $b) => strcmp($a['symbol'] ?? '', $b['symbol'] ?? ''));
        }
        return $rows;
    }

    private function getActiveConfig(string $timeframe): ?object
    {
        return DB::table('analysis_configs')->where('time_frame', $timeframe)->where('is_active', 1)->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')->toArray();
    }

    private function resolveTimeframe(Request $request): string
    {
        $tf = strtolower(trim($request->get('timeframe', '15min')));
        return in_array($tf, self::TIMEFRAMES) ? $tf : '15min';
    }
}