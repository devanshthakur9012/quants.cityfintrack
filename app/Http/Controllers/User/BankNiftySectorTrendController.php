<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * BankNiftySectorTrendController
 *
 * Analyses ALL 14 BANKNIFTY stocks using the exact same OI logic as
 * OIIVAutoController::buildSignalRowsForDate() and then derives
 * a weighted next-day BANKNIFTY directional bias.
 *
 * OI Logic (mirror of OIIVAutoController):
 *   CE ↑ + PE ↓  → BEARISH
 *   CE ↓ + PE ↑  → BULLISH
 *   Both ↑       → CE% > PE% = BEARISH, else BULLISH
 *   Both ↓       → CE% < PE% = BULLISH, else BEARISH
 *
 * Stock Weightages (from NSE BANKNIFTY composition):
 *   HDFCBANK     25.73%
 *   SBIN         20.66%
 *   ICICIBANK    19.71%
 *   AXISBANK      8.25%
 *   KOTAKBANK     8.03%
 *   BANKBARODA    2.92%
 *   UNIONBANK     2.86%
 *   PNB           2.64%
 *   CANARABANK    2.56%
 *   FEDERALBNK    1.46%
 *   AUBANK        1.44%
 *   INDUSINDBK    1.34%
 *   YESBANK       1.24%
 *   IDFCFIRSTB    1.16%
 *
 * DB Notes (matches OIIVAutoController exactly):
 *   trade_date    = DATETIME → always use whereDate()
 *   interval_time = DATETIME → use whereRaw("TIME(interval_time) = '...'")
 */
class BankNiftySectorTrendController extends Controller
{
    // ── All 14 BANKNIFTY stocks with exact weightages ─────────────────────────
    private const BANKNIFTY_STOCKS = [
        ['symbol' => 'HDFCBANK',   'company' => 'HDFC Bank Ltd',              'weight' => 25.73],
        ['symbol' => 'SBIN',       'company' => 'State Bank of India',         'weight' => 20.66],
        ['symbol' => 'ICICIBANK',  'company' => 'ICICI Bank Ltd',              'weight' => 19.71],
        ['symbol' => 'AXISBANK',   'company' => 'Axis Bank Ltd',               'weight' =>  8.25],
        ['symbol' => 'KOTAKBANK',  'company' => 'Kotak Mahindra Bank Ltd',     'weight' =>  8.03],
        ['symbol' => 'BANKBARODA', 'company' => 'Bank of Baroda',              'weight' =>  2.92],
        ['symbol' => 'UNIONBANK',  'company' => 'Union Bank of India',         'weight' =>  2.86],
        ['symbol' => 'PNB',        'company' => 'Punjab National Bank',        'weight' =>  2.64],
        ['symbol' => 'CANARABANK', 'company' => 'Canara Bank',                 'weight' =>  2.56],
        ['symbol' => 'FEDERALBNK', 'company' => 'The Federal Bank Ltd',        'weight' =>  1.46],
        ['symbol' => 'AUBANK',     'company' => 'AU Small Finance Bank Ltd',   'weight' =>  1.44],
        ['symbol' => 'INDUSINDBK', 'company' => 'IndusInd Bank Ltd',           'weight' =>  1.34],
        ['symbol' => 'YESBANK',    'company' => 'Yes Bank Ltd',                'weight' =>  1.24],
        ['symbol' => 'IDFCFIRSTB', 'company' => 'IDFC First Bank Ltd',         'weight' =>  1.16],
    ];

    // Top 5 heavy-weights (control ~82% of index)
    private const HEAVY_WEIGHTS = ['HDFCBANK', 'SBIN', 'ICICIBANK', 'AXISBANK', 'KOTAKBANK'];

    // ══════════════════════════════════════════════════════════════════════════
    // Page
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'BANKNIFTY Sector Trend — Next Day Bias';
        return view($this->activeTemplate . 'user.banknifty-sector-trend.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Main Analysis
    // GET /banknifty-sector-trend/analyze?date=Y-m-d
    // ══════════════════════════════════════════════════════════════════════════

    public function analyze(Request $request)
    {
        try {
            $date     = $request->get('date', Carbon::today()->toDateString());
            $prevDate = $this->getPreviousTradingDate($date);

            Log::info('=== BANKNIFTY SECTOR TREND ANALYSIS ===', [
                'date' => $date, 'prev_date' => $prevDate,
            ]);

            $stockResults  = [];
            $weightedBull  = 0;
            $weightedBear  = 0;
            $heavyBull     = 0;   // weighted sum from top-5 only
            $heavyBear     = 0;

            foreach (self::BANKNIFTY_STOCKS as $stockDef) {
                $result = $this->analyzeStock(
                    $stockDef['symbol'],
                    $stockDef['company'],
                    $stockDef['weight'],
                    $date,
                    $prevDate
                );

                if ($result === null) continue;

                $stockResults[] = $result;

                if ($result['signal'] === 'BULLISH') {
                    $weightedBull += $stockDef['weight'];
                    if (in_array($stockDef['symbol'], self::HEAVY_WEIGHTS)) {
                        $heavyBull += $stockDef['weight'];
                    }
                } elseif ($result['signal'] === 'BEARISH') {
                    $weightedBear += $stockDef['weight'];
                    if (in_array($stockDef['symbol'], self::HEAVY_WEIGHTS)) {
                        $heavyBear += $stockDef['weight'];
                    }
                }
            }

            $biasData  = $this->computeWeightedBias($stockResults, $weightedBull, $weightedBear, $heavyBull, $heavyBear);
            $breadth   = $this->computeBreadth($stockResults);
            $tradePlan = $this->buildTradePlan($biasData, $date);
            $heavySummary = $this->buildHeavySummary($stockResults);

            return response()->json([
                'success'        => true,
                'date'           => $date,
                'prev_date'      => $prevDate,
                'bias'           => $biasData,
                'stocks'         => $stockResults,
                'trade_plan'     => $tradePlan,
                'breadth'        => $breadth,
                'heavy_summary'  => $heavySummary,
                'analyzed_at'    => now()->format('Y-m-d H:i:s'),
                'total_tracked'  => count($stockResults),
            ]);

        } catch (\Exception $e) {
            Log::error('BankNiftySectorTrend::analyze — ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Analysis error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Single stock — mirrors OIIVAutoController::buildSignalRowsForDate()
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeStock(
        string $symbol,
        string $company,
        float  $weight,
        string $date,
        string $prevDate
    ): ?array {
        $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
        $prevExpiry    = $currentExpiry
            ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
            : null;

        // Today CE/PE OI at 14:45
        $todayCeQ = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'CE')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->where('is_missing', 0);
        if ($currentExpiry) $todayCeQ->whereDate('expiry_date', $currentExpiry);
        $todayCeOI = (int) $todayCeQ->sum('oi');

        $todayPeQ = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'PE')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->where('is_missing', 0);
        if ($currentExpiry) $todayPeQ->whereDate('expiry_date', $currentExpiry);
        $todayPeOI = (int) $todayPeQ->sum('oi');

        if ($todayCeOI === 0 && $todayPeOI === 0) return null;

        // Prev day CE/PE OI at 15:00
        $prevCeQ = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'CE')
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->where('is_missing', 0);
        if ($prevExpiry) $prevCeQ->whereDate('expiry_date', $prevExpiry);
        $prevCeOI = (int) $prevCeQ->sum('oi');

        $prevPeQ = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'PE')
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->where('is_missing', 0);
        if ($prevExpiry) $prevPeQ->whereDate('expiry_date', $prevExpiry);
        $prevPeOI = (int) $prevPeQ->sum('oi');

        $ceOiPct = $prevCeOI > 0 ? round((($todayCeOI - $prevCeOI) / $prevCeOI) * 100, 2) : 0;
        $peOiPct = $prevPeOI > 0 ? round((($todayPeOI - $prevPeOI) / $prevPeOI) * 100, 2) : 0;

        $oiSignal = $this->getOISignal($ceOiPct, $peOiPct);

        // Strength rank
        $diff = abs($ceOiPct - $peOiPct);
        if      ($diff > 40) $strengthRank = 'Rank 1';
        elseif  ($diff > 25) $strengthRank = 'Rank 2';
        elseif  ($diff > 10) $strengthRank = 'Rank 3';
        elseif  ($diff > 5)  $strengthRank = 'Rank 4';
        else                 $strengthRank = 'Normal';

        $absCe = abs($ceOiPct);
        $absPe = abs($peOiPct);
        $isBoth = str_contains($oiSignal['condition'], 'Both');
        $strongerSide = $isBoth
            ? ($absCe > $absPe ? 'CE' : ($absPe > $absCe ? 'PE' : 'EQUAL'))
            : 'CLEAR';

        $futRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first(['close']);
        $futPrice = $futRow ? round((float) $futRow->close, 2) : null;

        $peCeRatio = $todayCeOI > 0 ? round($todayPeOI / $todayCeOI, 2) : 0;

        $tradeAction = match($oiSignal['signal']) {
            'BULLISH' => 'BUY CE',
            'BEARISH' => 'BUY PE',
            default   => 'WAIT',
        };

        $isHeavy = in_array($symbol, self::HEAVY_WEIGHTS);

        return [
            'symbol'        => $symbol,
            'company'       => $company,
            'weight'        => $weight,
            'is_heavy'      => $isHeavy,
            'expiry'        => $currentExpiry,
            'fut_price'     => $futPrice,

            'ce_oi'         => $todayCeOI,
            'ce_oi_prev'    => $prevCeOI,
            'ce_oi_pct'     => $ceOiPct,
            'ce_direction'  => $ceOiPct > 0 ? 'UP' : ($ceOiPct < 0 ? 'DOWN' : 'FLAT'),

            'pe_oi'         => $todayPeOI,
            'pe_oi_prev'    => $prevPeOI,
            'pe_oi_pct'     => $peOiPct,
            'pe_direction'  => $peOiPct > 0 ? 'UP' : ($peOiPct < 0 ? 'DOWN' : 'FLAT'),

            'signal'        => $oiSignal['signal'],
            'condition'     => $oiSignal['condition'],
            'reason'        => $oiSignal['reason'],
            'trade_action'  => $tradeAction,
            'strength_rank' => $strengthRank,
            'strength_diff' => round($diff, 2),
            'stronger_side' => $strongerSide,
            'pe_ce_ratio'   => $peCeRatio,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OI Signal — exact copy from OIIVAutoController
    // ══════════════════════════════════════════════════════════════════════════

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup, CE stronger (CE:{$cePct}% > PE:{$pePct}%)", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup, PE stronger (PE:{$pePct}% > CE:{$cePct}%)", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding, CE stronger (CE:{$cePct}% < PE:{$pePct}%)", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding, PE stronger (PE:{$pePct}% < CE:{$cePct}%)", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Weighted Bias — weight-aware, HDFCBANK+SBIN+ICICIBANK control ~66%
    // ══════════════════════════════════════════════════════════════════════════

    private function computeWeightedBias(
        array $stocks,
        float $weightedBull,
        float $weightedBear,
        float $heavyBull,
        float $heavyBear
    ): array {
        $total         = array_sum(array_column(self::BANKNIFTY_STOCKS, 'weight')); // ~100
        $bullPct       = round($weightedBull / $total * 100, 1);
        $bearPct       = round($weightedBear / $total * 100, 1);

        $direction = 'NEUTRAL';
        if ($weightedBull > $weightedBear) $direction = 'BULLISH';
        elseif ($weightedBear > $weightedBull) $direction = 'BEARISH';

        $reasons = [];

        // HDFC + SBIN + ICICI alone = ~66% → override if aligned
        // Top-3 weighted stocks
        $topThreeBull = 0;
        $topThreeBear = 0;
        foreach ($stocks as $s) {
            if (in_array($s['symbol'], ['HDFCBANK', 'SBIN', 'ICICIBANK'])) {
                if ($s['signal'] === 'BULLISH') $topThreeBull += $s['weight'];
                if ($s['signal'] === 'BEARISH') $topThreeBear += $s['weight'];
            }
        }

        $top3Total = 25.73 + 20.66 + 19.71; // 66.10
        if ($topThreeBull >= $top3Total * 0.67) {
            $direction = 'BULLISH';
            $reasons[] = 'Top-3 banks (66%) aligned BULLISH';
        } elseif ($topThreeBear >= $top3Total * 0.67) {
            $direction = 'BEARISH';
            $reasons[] = 'Top-3 banks (66%) aligned BEARISH';
        }

        // Heavy weights (top 5 = 82%) override
        if ($heavyBull > $heavyBear && $heavyBull >= 30) {
            $direction = 'BULLISH';
            $reasons[] = 'Top-5 heavyweights (82%) lean BULLISH';
        } elseif ($heavyBear > $heavyBull && $heavyBear >= 30) {
            $direction = 'BEARISH';
            $reasons[] = 'Top-5 heavyweights (82%) lean BEARISH';
        }

        // Strength + confidence
        $dominant = max($bullPct, $bearPct);
        if ($dominant >= 40)     { $strength = 'STRONG';    $confidence = 88; }
        elseif ($dominant >= 25) { $strength = 'MODERATE';  $confidence = 72; }
        else                     { $strength = 'WEAK';      $confidence = 55; }

        // All 14 same direction = very high confidence
        $bullCount = count(array_filter($stocks, fn($s) => $s['signal'] === 'BULLISH'));
        $bearCount = count(array_filter($stocks, fn($s) => $s['signal'] === 'BEARISH'));
        if ($bullCount >= 10 || $bearCount >= 10) {
            $strength   = 'STRONG';
            $confidence = 92;
            $reasons[]  = ($bullCount >= 10 ? $bullCount : $bearCount) . '/14 stocks aligned';
        }

        if (empty($reasons)) {
            $reasons[] = "Weighted Bull: {$bullPct}% | Weighted Bear: {$bearPct}%";
        }

        return [
            'direction'    => $direction,
            'strength'     => $strength,
            'confidence'   => $confidence,
            'reason'       => implode(' | ', $reasons),
            'bull_weight'  => $bullPct,
            'bear_weight'  => $bearPct,
            'heavy_bull'   => round($heavyBull, 1),
            'heavy_bear'   => round($heavyBear, 1),
            'top3_bull'    => round($topThreeBull, 1),
            'top3_bear'    => round($topThreeBear, 1),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Heavy weight summary (top 5 stocks detail for quick scan)
    // ══════════════════════════════════════════════════════════════════════════

    private function buildHeavySummary(array $stocks): array
    {
        $heavy = array_filter($stocks, fn($s) => $s['is_heavy']);
        return array_values($heavy);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Breadth
    // ══════════════════════════════════════════════════════════════════════════

    private function computeBreadth(array $stocks): array
    {
        $total   = count($stocks);
        $bull    = count(array_filter($stocks, fn($s) => $s['signal'] === 'BULLISH'));
        $bear    = count(array_filter($stocks, fn($s) => $s['signal'] === 'BEARISH'));
        $neutral = $total - $bull - $bear;

        $bullPct = $total > 0 ? round($bull / $total * 100, 1) : 0;
        $bearPct = $total > 0 ? round($bear / $total * 100, 1) : 0;

        $signal = 'NEUTRAL';
        if ($bullPct >= 65)      $signal = 'STRONG BULLISH';
        elseif ($bullPct >= 50)  $signal = 'BULLISH';
        elseif ($bearPct >= 65)  $signal = 'STRONG BEARISH';
        elseif ($bearPct >= 50)  $signal = 'BEARISH';

        return [
            'total'    => $total,
            'bullish'  => $bull,
            'bearish'  => $bear,
            'neutral'  => $neutral,
            'bull_pct' => $bullPct,
            'bear_pct' => $bearPct,
            'signal'   => $signal,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Trade Plan
    // ══════════════════════════════════════════════════════════════════════════

    private function buildTradePlan(array $biasData, string $date): array
    {
        $direction = $biasData['direction'];
        $nextDay   = $this->getNextTradingDate($date);

        if (in_array($direction, ['NEUTRAL', 'NO_DATA'])) {
            return [
                'trade_date'    => $nextDay,
                'action'        => 'WAIT',
                'option_type'   => 'NONE',
                'strike'        => 'ATM',
                'entry_time'    => '09:30 — wait & watch',
                'entry_trigger' => 'No clear bias — wait for first 15-min candle breakout',
                'stop_loss'     => 'N/A',
                'target'        => 'N/A',
                'index'         => 'BANKNIFTY',
            ];
        }

        $isBull     = $direction === 'BULLISH';
        $optionType = $isBull ? 'CE' : 'PE';
        $strike     = $isBull ? 'ATM or ATM+1' : 'ATM or ATM-1';
        $trigger    = $isBull
            ? 'Break ABOVE first 15-min candle HIGH (09:15–09:30)'
            : 'Break BELOW first 15-min candle LOW (09:15–09:30)';

        return [
            'trade_date'    => $nextDay,
            'action'        => "BUY {$optionType}",
            'option_type'   => $optionType,
            'strike'        => $strike,
            'entry_time'    => '09:30 after first candle closes',
            'entry_trigger' => $trigger,
            'stop_loss'     => 'Previous candle high/low OR 25% premium SL',
            'target'        => '1.5× to 2× risk | Trail via VWAP',
            'confidence'    => $biasData['confidence'],
            'strength'      => $biasData['strength'],
            'index'         => 'BANKNIFTY',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Expiry Helpers — exact copies from OIIVAutoController
    // ══════════════════════════════════════════════════════════════════════════

    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = $this->getNearestExpiryForDate($symbol, $date);
        if (!$expiry) return null;

        if ($expiry === $date) {
            $next = $this->getNextSeriesExpiry($symbol, $date, $expiry);
            if ($next) return $next;
        }

        return $expiry;
    }

    private function getNextSeriesExpiry(string $symbol, string $date, string $currentExpiry): ?string
    {
        $next = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>', $currentExpiry)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($next) return $next;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $currentExpiry)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)
            ->where('is_missing', 0)
            ->exists();

        if ($exists) return $currentExpiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')
            ->where('is_missing', 0)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Date helpers — exact copies from OIIVAutoController
    // ══════════════════════════════════════════════════════════════════════════

    private function getPreviousTradingDate(string $date): string
    {
        $prev     = Carbon::parse($date)->subDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) {
                return $prev->format('Y-m-d');
            }
            $prev->subDay();
            $attempts++;
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function getNextTradingDate(string $date): string
    {
        $next     = Carbon::parse($date)->addDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$next->isWeekend() && !$this->isHoliday($next->format('Y-m-d'))) {
                return $next->format('Y-m-d');
            }
            $next->addDay();
            $attempts++;
        }
        return Carbon::parse($date)->addDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}