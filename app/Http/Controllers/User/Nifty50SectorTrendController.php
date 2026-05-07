<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Nifty50SectorTrendController
 *
 * Analyses ALL 50 NIFTY stocks using the exact same OI logic as
 * OIIVAutoController::buildSignalRowsForDate() and then derives
 * a weighted next-day NIFTY directional bias.
 *
 * OI Logic (mirror of OIIVAutoController):
 *   CE ↑ + PE ↓  → BEARISH
 *   CE ↓ + PE ↑  → BULLISH
 *   Both ↑       → CE% > PE% = BEARISH, else BULLISH
 *   Both ↓       → CE% < PE% = BULLISH, else BEARISH
 *
 * Sector weights from NSE NIFTY50 composition (image provided):
 *   Financial Services          35.45%
 *   Oil Gas & Consumable Fuels  10.95%
 *   Information Technology       9.40%
 *   Automobile & Auto Comp.      6.60%
 *   Fast Moving Consumer Goods   5.96%
 *   Telecommunication            5.34%
 *   Metals & Mining              4.28%
 *   Healthcare                   4.68%
 *   Construction                 4.02%
 *   Power                        3.03%
 *   Consumer Services            2.33%
 *   Consumer Durables            2.55%
 *   Construction Materials       2.19%
 *   Services                     1.82%
 *   Capital Goods                1.40%
 *
 * DB Notes (matches OIIVAutoController exactly):
 *   trade_date    = DATETIME  → always use whereDate()
 *   interval_time = DATETIME  → use whereRaw("TIME(interval_time) = '...'")
 */
class Nifty50SectorTrendController extends Controller
{
    // ── All 50 NIFTY stocks with sector + weight ──────────────────────────────
    // Individual stock weight = sector_weight / stocks_in_sector (approximation).
    // For the purpose of trend scoring we use the sector weight for each stock.
    private const NIFTY50_STOCKS = [
        // Financial Services  (35.45%)
        ['symbol' => 'HDFCBANK',    'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'ICICIBANK',   'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'SBIN',        'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'AXISBANK',    'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'KOTAKBANK',   'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'BAJFINANCE',  'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'BAJAJFINSV',  'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'HDFCLIFE',    'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'SBILIFE',     'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'SHRIRAMFIN',  'sector' => 'Financial Services',         'sector_weight' => 35.45],
        ['symbol' => 'JIOFIN',      'sector' => 'Financial Services',         'sector_weight' => 35.45],

        // Oil Gas & Consumable Fuels  (10.95%)
        ['symbol' => 'RELIANCE',    'sector' => 'Oil Gas & Consumable Fuels', 'sector_weight' => 10.95],
        ['symbol' => 'ONGC',        'sector' => 'Oil Gas & Consumable Fuels', 'sector_weight' => 10.95],
        ['symbol' => 'COALINDIA',   'sector' => 'Oil Gas & Consumable Fuels', 'sector_weight' => 10.95],

        // Information Technology  (9.40%)
        ['symbol' => 'INFY',        'sector' => 'Information Technology',     'sector_weight' =>  9.40],
        ['symbol' => 'TCS',         'sector' => 'Information Technology',     'sector_weight' =>  9.40],
        ['symbol' => 'HCLTECH',     'sector' => 'Information Technology',     'sector_weight' =>  9.40],
        ['symbol' => 'TECHM',       'sector' => 'Information Technology',     'sector_weight' =>  9.40],
        ['symbol' => 'WIPRO',       'sector' => 'Information Technology',     'sector_weight' =>  9.40],

        // Automobile & Auto Components  (6.60%)
        ['symbol' => 'BAJAJ-AUTO',  'sector' => 'Automobile',                 'sector_weight' =>  6.60],
        ['symbol' => 'EICHERMOT',   'sector' => 'Automobile',                 'sector_weight' =>  6.60],
        ['symbol' => 'M&M',         'sector' => 'Automobile',                 'sector_weight' =>  6.60],
        ['symbol' => 'MARUTI',      'sector' => 'Automobile',                 'sector_weight' =>  6.60],
        ['symbol' => 'TMPV',        'sector' => 'Automobile',                 'sector_weight' =>  6.60],

        // Fast Moving Consumer Goods  (5.96%)
        ['symbol' => 'HINDUNILVR',  'sector' => 'FMCG',                       'sector_weight' =>  5.96],
        ['symbol' => 'ITC',         'sector' => 'FMCG',                       'sector_weight' =>  5.96],
        ['symbol' => 'NESTLEIND',   'sector' => 'FMCG',                       'sector_weight' =>  5.96],
        ['symbol' => 'TATACONSUM',  'sector' => 'FMCG',                       'sector_weight' =>  5.96],

        // Telecommunication  (5.34%)
        ['symbol' => 'BHARTIARTL',  'sector' => 'Telecommunication',          'sector_weight' =>  5.34],

        // Metals & Mining  (4.28%)
        ['symbol' => 'ADANIENT',    'sector' => 'Metals & Mining',            'sector_weight' =>  4.28],
        ['symbol' => 'HINDALCO',    'sector' => 'Metals & Mining',            'sector_weight' =>  4.28],
        ['symbol' => 'JSWSTEEL',    'sector' => 'Metals & Mining',            'sector_weight' =>  4.28],
        ['symbol' => 'TATASTEEL',   'sector' => 'Metals & Mining',            'sector_weight' =>  4.28],

        // Healthcare  (4.68%)
        ['symbol' => 'APOLLOHOSP',  'sector' => 'Healthcare',                 'sector_weight' =>  4.68],
        ['symbol' => 'CIPLA',       'sector' => 'Healthcare',                 'sector_weight' =>  4.68],
        ['symbol' => 'DRREDDY',     'sector' => 'Healthcare',                 'sector_weight' =>  4.68],
        ['symbol' => 'MAXHEALTH',   'sector' => 'Healthcare',                 'sector_weight' =>  4.68],
        ['symbol' => 'SUNPHARMA',   'sector' => 'Healthcare',                 'sector_weight' =>  4.68],

        // Construction  (4.02%)
        ['symbol' => 'LT',          'sector' => 'Construction',               'sector_weight' =>  4.02],

        // Power  (3.03%)
        ['symbol' => 'NTPC',        'sector' => 'Power',                      'sector_weight' =>  3.03],
        ['symbol' => 'POWERGRID',   'sector' => 'Power',                      'sector_weight' =>  3.03],

        // Consumer Services  (2.33%)
        ['symbol' => 'ETERNAL',     'sector' => 'Consumer Services',          'sector_weight' =>  2.33],
        ['symbol' => 'TRENT',       'sector' => 'Consumer Services',          'sector_weight' =>  2.33],

        // Consumer Durables  (2.55%)
        ['symbol' => 'ASIANPAINT',  'sector' => 'Consumer Durables',          'sector_weight' =>  2.55],
        ['symbol' => 'TITAN',       'sector' => 'Consumer Durables',          'sector_weight' =>  2.55],

        // Construction Materials  (2.19%)
        ['symbol' => 'GRASIM',      'sector' => 'Construction Materials',     'sector_weight' =>  2.19],
        ['symbol' => 'ULTRACEMCO',  'sector' => 'Construction Materials',     'sector_weight' =>  2.19],

        // Services  (1.82%)
        ['symbol' => 'ADANIPORTS',  'sector' => 'Services',                   'sector_weight' =>  1.82],
        ['symbol' => 'INDIGO',      'sector' => 'Services',                   'sector_weight' =>  1.82],

        // Capital Goods  (1.40%)
        ['symbol' => 'BEL',         'sector' => 'Capital Goods',              'sector_weight' =>  1.40],
    ];

    // ══════════════════════════════════════════════════════════════════════════
    // Page
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'NIFTY 50 Sector Trend — Next Day Bias';
        return view($this->activeTemplate . 'user.nifty50-sector-trend.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Main Analysis
    // GET /nifty50-sector-trend/analyze?date=Y-m-d
    // ══════════════════════════════════════════════════════════════════════════

    public function analyze(Request $request)
    {
        try {
            $date     = $request->get('date', Carbon::today()->toDateString());
            $prevDate = $this->getPreviousTradingDate($date);

            Log::info('=== NIFTY50 SECTOR TREND ANALYSIS ===', [
                'date' => $date, 'prev_date' => $prevDate,
            ]);

            $stockResults  = [];
            $sectorBuckets = [];   // sector => [signal, ...]

            foreach (self::NIFTY50_STOCKS as $stockDef) {
                $result = $this->analyzeStock(
                    $stockDef['symbol'],
                    $stockDef['sector'],
                    $stockDef['sector_weight'],
                    $date,
                    $prevDate
                );

                if ($result === null) continue;

                $stockResults[] = $result;

                $sec = $stockDef['sector'];
                if (!isset($sectorBuckets[$sec])) {
                    $sectorBuckets[$sec] = [
                        'sector'        => $sec,
                        'sector_weight' => $stockDef['sector_weight'],
                        'stocks'        => 0,
                        'bullish'       => 0,
                        'bearish'       => 0,
                        'neutral'       => 0,
                    ];
                }
                $sectorBuckets[$sec]['stocks']++;
                if ($result['signal'] === 'BULLISH') $sectorBuckets[$sec]['bullish']++;
                elseif ($result['signal'] === 'BEARISH') $sectorBuckets[$sec]['bearish']++;
                else $sectorBuckets[$sec]['neutral']++;
            }

            // Sector signal = majority vote
            foreach ($sectorBuckets as &$sec) {
                $sec['signal'] = $this->sectorMajoritySignal($sec);
            }
            unset($sec);

            $biasData  = $this->computeWeightedBias($sectorBuckets, $stockResults);
            $breadth   = $this->computeBreadth($stockResults);
            $tradePlan = $this->buildTradePlan($biasData, $date);

            return response()->json([
                'success'       => true,
                'date'          => $date,
                'prev_date'     => $prevDate,
                'bias'          => $biasData,
                'sectors'       => array_values($sectorBuckets),
                'stocks'        => $stockResults,
                'trade_plan'    => $tradePlan,
                'breadth'       => $breadth,
                'analyzed_at'   => now()->format('Y-m-d H:i:s'),
                'total_tracked' => count($stockResults),
            ]);

        } catch (\Exception $e) {
            Log::error('Nifty50SectorTrend::analyze — ' . $e->getMessage());
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
        string $sector,
        float  $sectorWeight,
        string $date,
        string $prevDate
    ): ?array {
        // Resolve active expiry (expiry-day aware — mirrors OIIVAutoController)
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

        // Skip if genuinely no data
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

        // OI change %
        $ceOiPct = $prevCeOI > 0 ? round((($todayCeOI - $prevCeOI) / $prevCeOI) * 100, 2) : 0;
        $peOiPct = $prevPeOI > 0 ? round((($todayPeOI - $prevPeOI) / $prevPeOI) * 100, 2) : 0;

        // OI Signal — exact copy of OIIVAutoController::getOISignal()
        $oiSignal = $this->getOISignal($ceOiPct, $peOiPct);

        // Strength rank — mirrors OIIVAutoController
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

        // FUT price at 14:45
        $futRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first(['close', 'trading_symbol']);
        $futPrice = $futRow ? round((float) $futRow->close, 2) : null;

        // PE/CE ratio
        $peCeRatio = $todayCeOI > 0 ? round($todayPeOI / $todayCeOI, 2) : 0;

        $tradeAction = match($oiSignal['signal']) {
            'BULLISH' => 'BUY CE',
            'BEARISH' => 'BUY PE',
            default   => 'WAIT',
        };

        return [
            'symbol'        => $symbol,
            'sector'        => $sector,
            'sector_weight' => $sectorWeight,
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
    // Sector majority vote
    // ══════════════════════════════════════════════════════════════════════════

    private function sectorMajoritySignal(array $sec): string
    {
        $t = $sec['stocks'];
        if ($t === 0) return 'NO_DATA';
        if ($sec['bullish'] > $sec['bearish'] && $sec['bullish'] >= ceil($t / 2)) return 'BULLISH';
        if ($sec['bearish'] > $sec['bullish'] && $sec['bearish'] >= ceil($t / 2)) return 'BEARISH';
        return 'NEUTRAL';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Weighted NIFTY Bias
    // ══════════════════════════════════════════════════════════════════════════

    private function computeWeightedBias(array $sectorBuckets, array $stocks): array
    {
        // Per-stock weighted score using sector_weight
        // Each stock contributes its sector_weight / stocks_in_sector
        $sectorStockCount = [];
        foreach ($stocks as $s) {
            $sec = $s['sector'];
            $sectorStockCount[$sec] = ($sectorStockCount[$sec] ?? 0) + 1;
        }

        $weightedBull = 0;
        $weightedBear = 0;
        $weightedTotal = 0;

        foreach ($stocks as $s) {
            $sec = $s['sector'];
            $cnt = $sectorStockCount[$sec] ?? 1;
            $stockWeight = $s['sector_weight'] / $cnt;  // distribute sector weight equally

            $weightedTotal += $stockWeight;
            if ($s['signal'] === 'BULLISH') $weightedBull += $stockWeight;
            if ($s['signal'] === 'BEARISH') $weightedBear += $stockWeight;
        }

        $totalPossible = 100; // all weights sum to ~100
        $bullPct = $weightedTotal > 0 ? round($weightedBull / $totalPossible * 100, 1) : 0;
        $bearPct = $weightedTotal > 0 ? round($weightedBear / $totalPossible * 100, 1) : 0;

        // Direction
        $direction = 'NEUTRAL';
        if ($weightedBull > $weightedBear) $direction = 'BULLISH';
        elseif ($weightedBear > $weightedBull) $direction = 'BEARISH';

        // Financial sector override (35.45%)
        $finSec          = $sectorBuckets['Financial Services'] ?? null;
        $finSignal        = $finSec ? $this->sectorMajoritySignal($finSec) : 'NO_DATA';
        $finWeight        = 35.45;
        $reasons         = [];

        foreach ($sectorBuckets as $sKey => $sec) {
            if ($sec['signal'] !== 'NO_DATA') {
                $reasons[] = $sKey . ': ' . $sec['signal'];
            }
        }

        if ($finSignal === 'BULLISH' && $finWeight > $weightedBear) {
            $direction = 'BULLISH';
            $reasons[] = 'Financial override (35.45%)';
        } elseif ($finSignal === 'BEARISH' && $finWeight > $weightedBull) {
            $direction = 'BEARISH';
            $reasons[] = 'Financial override (35.45%)';
        }

        // Strength
        $dominant = max($bullPct, $bearPct);
        if ($dominant >= 30)     { $strength = 'STRONG';    $confidence = 85; }
        elseif ($dominant >= 20) { $strength = 'MODERATE';  $confidence = 70; }
        else                     { $strength = 'WEAK';      $confidence = 55; }

        // Breadth bonus
        $totalStocks   = count($stocks);
        $bullishStocks = count(array_filter($stocks, fn($s) => $s['signal'] === 'BULLISH'));
        $bearishStocks = count(array_filter($stocks, fn($s) => $s['signal'] === 'BEARISH'));
        $dominantPct   = $totalStocks > 0 ? max($bullishStocks, $bearishStocks) / $totalStocks * 100 : 0;

        if ($dominantPct >= 65) {
            $strength   = 'STRONG';
            $confidence = min(92, $confidence + 10);
            $reasons[]  = round($dominantPct, 0) . '% breadth confirmation';
        }

        return [
            'direction'    => $direction,
            'strength'     => $strength,
            'confidence'   => $confidence,
            'reason'       => implode(' | ', array_slice($reasons, 0, 5)),
            'bull_weight'  => $bullPct,
            'bear_weight'  => $bearPct,
            'bull_stocks'  => $bullishStocks,
            'bear_stocks'  => $bearishStocks,
            'total_stocks' => $totalStocks,
        ];
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