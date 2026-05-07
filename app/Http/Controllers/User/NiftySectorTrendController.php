<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use App\Models\OptionStrike;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * NiftySectorTrendController
 *
 * Predicts next-day NIFTY direction using weighted sector trend analysis.
 *
 * Logic:
 *   1. Pull 15-min OHLC for key stocks across 3 dominant sectors
 *   2. Calculate VWAP-based trend score per stock
 *   3. Aggregate sector scores using NIFTY index weights
 *   4. Derive NIFTY bias: BULLISH / BEARISH / SIDEWAYS
 *   5. Recommend ATM strike + entry timing for next day
 *
 * Sector Weights (from NSE NIFTY composition):
 *   Financial Services  → 35.45%
 *   Oil Gas & Fuels     → 10.95%
 *   Information Tech    → 9.40%
 */
class NiftySectorTrendController extends Controller
{
    // ─── Sector weights (as fractions) ───────────────────────────────────────
    private const SECTOR_WEIGHTS = [
        'financial' => 0.3545,
        'oil'       => 0.1095,
        'it'        => 0.0940,
    ];

    // ─── Key stocks per sector ────────────────────────────────────────────────
    private const SECTOR_STOCKS = [
        'financial' => ['HDFCBANK', 'ICICIBANK', 'SBIN', 'AXISBANK', 'KOTAKBANK'],
        'oil'       => ['RELIANCE', 'ONGC', 'COALINDIA'],
        'it'        => ['INFY', 'TCS', 'HCLTECH', 'WIPRO'],
    ];

    // ─── Thresholds ───────────────────────────────────────────────────────────
    private const STRONG_THRESHOLD   = 0.003;  // ±0.3% VWAP deviation = strong
    private const MODERATE_THRESHOLD = 0.001;  // ±0.1% = moderate

    // ─── Analysis windows ─────────────────────────────────────────────────────
    private const POWER_HOUR_START = '14:30'; // 2:30 PM
    private const POWER_HOUR_END   = '15:15'; // 3:15 PM
    private const EOD_CANDLES      = 3;       // last N candles for momentum

    // ══════════════════════════════════════════════════════════════════════════
    // Pages
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'NIFTY Sector Trend — Next Day Bias';
        return view($this->activeTemplate . 'user.nifty-sector-trend.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Main Analysis API
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /nifty-sector-trend/analyze
     * Params: date (Y-m-d, defaults today)
     */
    public function analyze(Request $request)
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            $analysisDate = Carbon::parse($date);

            Log::info('=== NIFTY SECTOR TREND ANALYSIS START ===', ['date' => $date]);

            // ── Collect data per sector ───────────────────────────────────────
            $sectorResults = [];
            $allStockDetails = [];

            foreach (self::SECTOR_STOCKS as $sectorKey => $stocks) {
                $sectorData = $this->analyzeSector($sectorKey, $stocks, $analysisDate);
                $sectorResults[$sectorKey] = $sectorData;
                $allStockDetails = array_merge($allStockDetails, $sectorData['stocks']);
            }

            // ── Compute weighted NIFTY score ──────────────────────────────────
            $niftyScore = $this->computeNiftyScore($sectorResults);

            // ── Derive bias ───────────────────────────────────────────────────
            $biasData = $this->deriveBias($niftyScore, $sectorResults);

            // ── Build trade plan ──────────────────────────────────────────────
            $tradePlan = $this->buildTradePlan($biasData, $analysisDate);

            // ── Breadth check ─────────────────────────────────────────────────
            $breadth = $this->computeBreadth($allStockDetails);

            $response = [
                'success'      => true,
                'date'         => $date,
                'nifty_score'  => round($niftyScore, 6),
                'nifty_score_pct' => round($niftyScore * 100, 3),
                'bias'         => $biasData,
                'sectors'      => $this->formatSectorSummary($sectorResults),
                'stocks'       => $allStockDetails,
                'trade_plan'   => $tradePlan,
                'breadth'      => $breadth,
                'analyzed_at'  => now()->format('Y-m-d H:i:s'),
            ];

            Log::info('NIFTY Sector Trend Result', [
                'date'  => $date,
                'score' => $niftyScore,
                'bias'  => $biasData['direction'],
            ]);

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error('NiftySectorTrend Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Analysis error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Sector Analysis
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeSector(string $sectorKey, array $stocks, Carbon $date): array
    {
        $stockResults = [];
        $validScores  = [];

        foreach ($stocks as $symbol) {
            $result = $this->analyzeStock($symbol, $date);
            if ($result !== null) {
                $stockResults[] = $result;
                $validScores[]  = $result['score'];
            }
        }

        $sectorScore = count($validScores) > 0
            ? array_sum($validScores) / count($validScores)
            : 0;

        return [
            'key'         => $sectorKey,
            'score'       => $sectorScore,
            'weight'      => self::SECTOR_WEIGHTS[$sectorKey],
            'stocks'      => $stockResults,
            'valid_count' => count($validScores),
            'direction'   => $this->scoreToDirection($sectorScore),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Stock Analysis
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Analyze a single stock using:
     *   - VWAP deviation (50% weight)
     *   - Last 3-candle momentum (30% weight)
     *   - OI confirmation (20% weight)
     */
    private function analyzeStock(string $symbol, Carbon $date): ?array
    {
        // ── Fetch power-hour candles (2:30–3:15) ─────────────────────────────
        $candles = $this->fetchPowerHourCandles($symbol, $date);

        if ($candles->isEmpty()) {
            // Fallback: fetch full day candles
            $candles = $this->fetchFullDayCandles($symbol, $date);
        }

        if ($candles->isEmpty()) {
            Log::warning("NiftySectorTrend: No data for {$symbol} on {$date->toDateString()}");
            return null;
        }

        // ── VWAP calculation ──────────────────────────────────────────────────
        $vwap = $this->calculateVwap($candles);
        $lastCandle = $candles->last();
        $lastClose  = (float) $lastCandle->close;

        $vwapDeviation = $vwap > 0 ? ($lastClose - $vwap) / $vwap : 0;

        // ── Momentum score (last 3 candles) ───────────────────────────────────
        $momentumScore = $this->calculateMomentum($candles);

        // ── OI signal ─────────────────────────────────────────────────────────
        $oiSignal = $this->getOiSignal($symbol, $date);

        // ── Composite score ───────────────────────────────────────────────────
        $score = ($vwapDeviation * 0.5)
               + ($momentumScore * 0.003 * 0.3)   // normalise momentum to ~VWAP scale
               + ($oiSignal * 0.002 * 0.2);        // oi signal range ±1 → ±0.002

        // ── Classify ──────────────────────────────────────────────────────────
        $direction = $this->scoreToDirection($score);

        return [
            'symbol'         => $symbol,
            'last_close'     => round($lastClose, 2),
            'vwap'           => round($vwap, 2),
            'vwap_deviation' => round($vwapDeviation * 100, 3), // as %
            'momentum'       => $momentumScore,
            'oi_signal'      => $oiSignal,
            'score'          => $score,
            'score_pct'      => round($score * 100, 3),
            'direction'      => $direction,
            'candle_count'   => $candles->count(),
            'sector'         => $this->getSectorForSymbol($symbol),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VWAP Calculation
    // ══════════════════════════════════════════════════════════════════════════

    private function calculateVwap($candles): float
    {
        $totalPV     = 0;
        $totalVolume = 0;

        foreach ($candles as $candle) {
            $typicalPrice = ((float)$candle->high + (float)$candle->low + (float)$candle->close) / 3;
            $volume       = (float)($candle->volume ?? 1);
            $totalPV     += $typicalPrice * $volume;
            $totalVolume += $volume;
        }

        return $totalVolume > 0 ? $totalPV / $totalVolume : 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Momentum Score (-1 to +1)
    // ══════════════════════════════════════════════════════════════════════════

    private function calculateMomentum($candles): float
    {
        $last = $candles->slice(-self::EOD_CANDLES)->values();
        if ($last->count() < 2) return 0;

        $score = 0;
        for ($i = 1; $i < $last->count(); $i++) {
            $prev = (float) $last[$i - 1]->close;
            $curr = (float) $last[$i]->close;
            $score += $curr > $prev ? 1 : ($curr < $prev ? -1 : 0);
        }

        return $last->count() > 1 ? $score / ($last->count() - 1) : 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OI Signal (from option_ohlc_data or option_strikes)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Returns:
     *   +1  = Strong Bullish (Price ↑ + OI ↑ → CE unwinding / PE buildup)
     *   -1  = Strong Bearish (Price ↑ + OI ↑ for CE)
     *    0  = Neutral / no data
     *
     * Uses OptionStrike (CE_MERGED / PE_MERGED) if available, else OptionOhlcData.
     */
    private function getOiSignal(string $symbol, Carbon $date): float
    {
        // Try OptionStrike (daily summary table) first
        $ceRow = \App\Models\OptionStrike::where('underlying_symbol', $symbol)
            ->where('strike_position', 'CE_MERGED')
            ->where('trading_date', $date->toDateString())
            ->first();

        $peRow = \App\Models\OptionStrike::where('underlying_symbol', $symbol)
            ->where('strike_position', 'PE_MERGED')
            ->where('trading_date', $date->toDateString())
            ->first();

        if (!$ceRow && !$peRow) return 0;

        $ceOiChange = $ceRow ? (float)($ceRow->daily_oi_change_pct ?? 0) : 0;
        $peOiChange = $peRow ? (float)($peRow->daily_oi_change_pct ?? 0) : 0;

        // CE↓ + PE↑ → Bullish
        if ($ceOiChange < 0 && $peOiChange > 0) return  1;
        // CE↑ + PE↓ → Bearish
        if ($ceOiChange > 0 && $peOiChange < 0) return -1;
        // Both UP: if PE > CE → Bullish
        if ($ceOiChange > 0 && $peOiChange > 0) return $peOiChange > $ceOiChange ? 0.5 : -0.5;
        // Both DOWN: CE more negative → Bullish
        if ($ceOiChange < 0 && $peOiChange < 0) return $ceOiChange < $peOiChange ? 0.5 : -0.5;

        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Score → Direction
    // ══════════════════════════════════════════════════════════════════════════

    private function scoreToDirection(float $score): string
    {
        if ($score >= self::STRONG_THRESHOLD)   return 'STRONG_BULLISH';
        if ($score >= self::MODERATE_THRESHOLD) return 'BULLISH';
        if ($score <= -self::STRONG_THRESHOLD)  return 'STRONG_BEARISH';
        if ($score <= -self::MODERATE_THRESHOLD)return 'BEARISH';
        return 'SIDEWAYS';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Weighted NIFTY Score
    // ══════════════════════════════════════════════════════════════════════════

    private function computeNiftyScore(array $sectorResults): float
    {
        $score = 0;
        foreach ($sectorResults as $sectorKey => $data) {
            $score += $data['score'] * self::SECTOR_WEIGHTS[$sectorKey];
        }
        // Normalize by total weight covered
        $totalWeight = array_sum(self::SECTOR_WEIGHTS);
        return $totalWeight > 0 ? $score / $totalWeight : $score;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Bias Derivation
    // ══════════════════════════════════════════════════════════════════════════

    private function deriveBias(float $niftyScore, array $sectorResults): array
    {
        $financialDir = $sectorResults['financial']['direction'] ?? 'SIDEWAYS';
        $oilDir       = $sectorResults['oil']['direction'] ?? 'SIDEWAYS';
        $itDir        = $sectorResults['it']['direction'] ?? 'SIDEWAYS';

        // Financial sector override rule
        $financialScore = $sectorResults['financial']['score'] ?? 0;
        $isFinancialStrong = abs($financialScore) >= self::STRONG_THRESHOLD;

        $direction = $this->scoreToDirection($niftyScore);
        $strength  = 'MODERATE';
        $confidence = 60;
        $reason    = '';

        // Sector alignment check
        $bullishSectors = 0;
        $bearishSectors = 0;
        foreach ($sectorResults as $s) {
            $d = $s['direction'];
            if (str_contains($d, 'BULLISH')) $bullishSectors++;
            if (str_contains($d, 'BEARISH')) $bearishSectors++;
        }

        if ($bullishSectors >= 2 && str_contains($direction, 'BULLISH')) {
            $strength   = 'STRONG';
            $confidence = 80;
            $reason     = "{$bullishSectors}/3 sectors aligned bullish";
        } elseif ($bearishSectors >= 2 && str_contains($direction, 'BEARISH')) {
            $strength   = 'STRONG';
            $confidence = 80;
            $reason     = "{$bearishSectors}/3 sectors aligned bearish";
        }

        // Financial override
        if ($isFinancialStrong) {
            $financialBias = $financialScore > 0 ? 'BULLISH' : 'BEARISH';
            $reason .= " | Financial sector STRONG {$financialBias} (overrides)";
            $confidence = min(90, $confidence + 10);
            // Override direction if financial is very strong
            if (abs($financialScore) >= self::STRONG_THRESHOLD * 1.5) {
                $direction = $financialScore > 0 ? 'STRONG_BULLISH' : 'STRONG_BEARISH';
                $strength  = 'STRONG';
            }
        }

        if (empty($reason)) {
            $reason = $this->buildReasonString($niftyScore, $financialDir, $oilDir, $itDir);
        }

        return [
            'direction'     => $direction,
            'strength'      => $strength,
            'confidence'    => $confidence,
            'reason'        => trim($reason),
            'score'         => round($niftyScore, 6),
            'score_pct'     => round($niftyScore * 100, 3),
            'financial_dir' => $financialDir,
            'oil_dir'       => $oilDir,
            'it_dir'        => $itDir,
        ];
    }

    private function buildReasonString(float $score, string $fin, string $oil, string $it): string
    {
        return "Financial: {$fin} | Oil: {$oil} | IT: {$it} | Score: " . round($score * 100, 3) . "%";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Trade Plan Builder
    // ══════════════════════════════════════════════════════════════════════════

    private function buildTradePlan(array $biasData, Carbon $analysisDate): array
    {
        $direction  = $biasData['direction'];
        $nextDay    = $this->getNextTradingDay($analysisDate);
        $isBullish  = str_contains($direction, 'BULLISH');
        $isBearish  = str_contains($direction, 'BEARISH');
        $isSideways = $direction === 'SIDEWAYS';

        if ($isSideways) {
            return [
                'trade_date'     => $nextDay,
                'action'         => 'NO TRADE',
                'reason'         => 'Market bias is sideways — skip or wait for breakout confirmation',
                'entry_time'     => '09:30 (wait & watch)',
                'option_type'    => 'NONE',
                'strike'         => 'ATM',
                'stop_loss'      => 'N/A',
                'target'         => 'N/A',
                'entry_trigger'  => 'Breakout of first 15-min candle (either direction)',
            ];
        }

        $optionType    = $isBullish ? 'CE' : 'PE';
        $strikeAlias   = $isBullish ? 'ATM or ATM+1' : 'ATM or ATM-1';
        $entryTrigger  = $isBullish
            ? 'Buy on break ABOVE first 15-min candle HIGH (09:15–09:30)'
            : 'Buy on break BELOW first 15-min candle LOW (09:15–09:30)';
        $slRule        = 'Previous candle low/high OR 25% premium decay';
        $targetRule    = '1.5× to 2× risk | Trail via VWAP';

        return [
            'trade_date'    => $nextDay,
            'action'        => "BUY {$optionType}",
            'option_type'   => $optionType,
            'strike'        => $strikeAlias,
            'entry_time'    => '09:30 (after first candle closes)',
            'entry_trigger' => $entryTrigger,
            'stop_loss'     => $slRule,
            'target'        => $targetRule,
            'confidence'    => $biasData['confidence'],
            'strength'      => $biasData['strength'],
        ];
    }

    private function getNextTradingDay(Carbon $date): string
    {
        $next = $date->copy()->addDay();
        // Skip weekends
        while ($next->isWeekend()) {
            $next->addDay();
        }
        return $next->toDateString();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Breadth Check
    // ══════════════════════════════════════════════════════════════════════════

    private function computeBreadth(array $stocks): array
    {
        $total   = count($stocks);
        $bullish = count(array_filter($stocks, fn($s) => str_contains($s['direction'], 'BULLISH')));
        $bearish = count(array_filter($stocks, fn($s) => str_contains($s['direction'], 'BEARISH')));
        $neutral = $total - $bullish - $bearish;

        $bullPct = $total > 0 ? round($bullish / $total * 100, 1) : 0;
        $bearPct = $total > 0 ? round($bearish / $total * 100, 1) : 0;

        $breadthSignal = 'NEUTRAL';
        if ($bullPct >= 65) $breadthSignal = 'STRONG_BULLISH';
        elseif ($bullPct >= 50) $breadthSignal = 'BULLISH';
        elseif ($bearPct >= 65) $breadthSignal = 'STRONG_BEARISH';
        elseif ($bearPct >= 50) $breadthSignal = 'BEARISH';

        return [
            'total'          => $total,
            'bullish'        => $bullish,
            'bearish'        => $bearish,
            'neutral'        => $neutral,
            'bull_pct'       => $bullPct,
            'bear_pct'       => $bearPct,
            'breadth_signal' => $breadthSignal,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Data Fetchers
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchPowerHourCandles(string $symbol, Carbon $date)
    {
        return OptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereTime('interval_time', '>=', self::POWER_HOUR_START . ':00')
            ->whereTime('interval_time', '<=', self::POWER_HOUR_END . ':00')
            ->where('is_missing', 0)
            ->orderBy('interval_time', 'asc')
            ->get(['open', 'high', 'low', 'close', 'volume', 'interval_time']);
    }

    private function fetchFullDayCandles(string $symbol, Carbon $date)
    {
        return OptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->orderBy('interval_time', 'asc')
            ->get(['open', 'high', 'low', 'close', 'volume', 'interval_time']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Format helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function formatSectorSummary(array $sectorResults): array
    {
        $labels = [
            'financial' => 'Financial Services (35.45%)',
            'oil'       => 'Oil Gas & Fuels (10.95%)',
            'it'        => 'Information Technology (9.40%)',
        ];

        $formatted = [];
        foreach ($sectorResults as $key => $data) {
            $formatted[] = [
                'key'          => $key,
                'label'        => $labels[$key] ?? $key,
                'score'        => round($data['score'], 6),
                'score_pct'    => round($data['score'] * 100, 3),
                'weight'       => $data['weight'],
                'weight_pct'   => round($data['weight'] * 100, 2),
                'direction'    => $data['direction'],
                'valid_stocks' => $data['valid_count'],
            ];
        }
        return $formatted;
    }

    private function getSectorForSymbol(string $symbol): string
    {
        foreach (self::SECTOR_STOCKS as $sector => $stocks) {
            if (in_array($symbol, $stocks)) return $sector;
        }
        return 'unknown';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // History: past N days bias
    // ══════════════════════════════════════════════════════════════════════════

    public function history(Request $request)
    {
        try {
            $days  = min((int)$request->get('days', 5), 30);
            $date  = Carbon::today();
            $results = [];

            for ($i = 0; $i < $days; $i++) {
                $d = $date->copy()->subWeekdays($i);
                $fakeRequest = new Request(['date' => $d->toDateString()]);
                $response = $this->analyze($fakeRequest);
                $data = json_decode($response->getContent(), true);
                if ($data['success'] ?? false) {
                    $results[] = [
                        'date'      => $d->toDateString(),
                        'bias'      => $data['bias']['direction'],
                        'score_pct' => $data['bias']['score_pct'],
                        'strength'  => $data['bias']['strength'],
                    ];
                }
            }

            return response()->json(['success' => true, 'history' => array_reverse($results)]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}