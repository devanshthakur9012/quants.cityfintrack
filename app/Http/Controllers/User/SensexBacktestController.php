<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SensexBacktestController — v2 (Institutional Grade)
 *
 * UPGRADED ENGINE — 8 critical fixes applied:
 *   Fix 1  — Price + OI Fusion (long build / short build / absorption detection)
 *   Fix 2  — VWAP filter (computed from 15-min candles, no external feed needed)
 *   Fix 3  — Strike-level ATM OI analysis (ATM, ATM±1, ATM±2)
 *   Fix 4  — Volume confirmation (OI + volume spike = strong; OI + flat volume = trap)
 *   Fix 5  — Futures OI change direction (price↑ + OI↑ = long build, etc.)
 *   Fix 6  — Slippage-aware backtest (entry = open + 3% slippage on premium)
 *   Fix 7  — Expiry day detection (confidence reduced, SL widened)
 *   Fix 8  — Time-weighted signals (rotation after 12:00 = stronger weight)
 *   Fix 9  — Priority reordered: Rotation > Trap > Stocks > Futures
 *   Fix 10 — Live signal score 0–100
 */
class SensexBacktestController extends Controller
{
    // ── SENSEX 30 stocks ──────────────────────────────────────────────────────
    private const SENSEX30_STOCKS = [
        ['symbol' => 'HDFCBANK',   'sector' => 'Financial Services', 'weight' => 14.2],
        ['symbol' => 'ICICIBANK',  'sector' => 'Financial Services', 'weight' =>  8.1],
        ['symbol' => 'KOTAKBANK',  'sector' => 'Financial Services', 'weight' =>  4.3],
        ['symbol' => 'AXISBANK',   'sector' => 'Financial Services', 'weight' =>  4.1],
        ['symbol' => 'BAJFINANCE', 'sector' => 'Financial Services', 'weight' =>  3.5],
        ['symbol' => 'SBIN',       'sector' => 'Financial Services', 'weight' =>  3.1],
        ['symbol' => 'INFY',       'sector' => 'Information Technology', 'weight' => 8.0],
        ['symbol' => 'TCS',        'sector' => 'Information Technology', 'weight' => 4.6],
        ['symbol' => 'HCLTECH',    'sector' => 'Information Technology', 'weight' => 2.4],
        ['symbol' => 'RELIANCE',   'sector' => 'Oil Gas & Energy',   'weight' =>  9.2],
        ['symbol' => 'NTPC',       'sector' => 'Oil Gas & Energy',   'weight' =>  1.5],
        ['symbol' => 'POWERGRID',  'sector' => 'Oil Gas & Energy',   'weight' =>  1.3],
        ['symbol' => 'HINDUNILVR', 'sector' => 'FMCG',               'weight' =>  3.9],
        ['symbol' => 'ITC',        'sector' => 'FMCG',               'weight' =>  2.8],
        ['symbol' => 'NESTLEIND',  'sector' => 'FMCG',               'weight' =>  1.3],
        ['symbol' => 'MARUTI',     'sector' => 'Automobile',         'weight' =>  3.1],
        ['symbol' => 'M&M',        'sector' => 'Automobile',         'weight' =>  2.4],
        ['symbol' => 'BAJAJ-AUTO', 'sector' => 'Automobile',         'weight' =>  1.5],
        ['symbol' => 'TATASTEEL',  'sector' => 'Metals & Mining',    'weight' =>  1.8],
        ['symbol' => 'JSWSTEEL',   'sector' => 'Metals & Mining',    'weight' =>  1.7],
        ['symbol' => 'HINDALCO',   'sector' => 'Metals & Mining',    'weight' =>  1.5],
        ['symbol' => 'SUNPHARMA',  'sector' => 'Healthcare',         'weight' =>  2.6],
        ['symbol' => 'DRREDDY',    'sector' => 'Healthcare',         'weight' =>  1.4],
        ['symbol' => 'CIPLA',      'sector' => 'Healthcare',         'weight' =>  1.0],
        ['symbol' => 'LT',         'sector' => 'Capital Goods',      'weight' =>  3.8],
        ['symbol' => 'ULTRACEMCO', 'sector' => 'Capital Goods',      'weight' =>  1.2],
        ['symbol' => 'TITAN',      'sector' => 'Consumer Durables',  'weight' =>  1.8],
        ['symbol' => 'ASIANPAINT', 'sector' => 'Consumer Durables',  'weight' =>  1.2],
        ['symbol' => 'BHARTIARTL', 'sector' => 'Telecommunication',  'weight' =>  2.3],
        ['symbol' => 'ETERNAL',    'sector' => 'Consumer Services',  'weight' =>  1.2],
    ];

    // Thresholds
    private const TRAP_DIFF_THRESHOLD       = 15.0;
    private const ROTATION_SWING_THRESHOLD  = 8.0;
    private const SLIPPAGE_PCT              = 3.0;   // Fix 6: 3% slippage on entry
    private const EXPIRY_CONFIDENCE_PENALTY = 15;    // Fix 7: reduce confidence on expiry day
    private const EXPIRY_SL_MULTIPLIER      = 1.5;   // Fix 7: wider SL on expiry

    // ══════════════════════════════════════════════════════════════════════════
    // Page
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'SENSEX Backtest — Institutional Signal Engine v2';
        return view($this->activeTemplate . 'user.sensex-backtest.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // EOD Analysis
    // GET /sensex-backtest/analyze?date=Y-m-d
    // ══════════════════════════════════════════════════════════════════════════

    public function analyze(Request $request)
    {
        try {
            $date     = $request->get('date', Carbon::today()->toDateString());
            $prevDate = $this->getPreviousTradingDate($date);
            $isExpiry = $this->isExpiryDay('SENSEX', $date);   // Fix 7

            $stockResults  = [];
            $sectorBuckets = [];

            foreach (self::SENSEX30_STOCKS as $def) {
                $result = $this->analyzeStock(
                    $def['symbol'], $def['sector'], $def['weight'], $date, $prevDate
                );
                if ($result === null) continue;
                $stockResults[] = $result;

                $sec = $def['sector'];
                if (!isset($sectorBuckets[$sec])) {
                    $sectorBuckets[$sec] = [
                        'sector'  => $sec,
                        'weight'  => $this->sectorTotalWeight($def['sector']),
                        'stocks'  => 0, 'bullish' => 0, 'bearish' => 0, 'neutral' => 0,
                    ];
                }
                $sectorBuckets[$sec]['stocks']++;
                match($result['oi_signal']) {
                    'BULLISH' => $sectorBuckets[$sec]['bullish']++,
                    'BEARISH' => $sectorBuckets[$sec]['bearish']++,
                    default   => $sectorBuckets[$sec]['neutral']++,
                };
            }

            foreach ($sectorBuckets as &$sec) {
                $sec['signal'] = $this->sectorMajoritySignal($sec);
            }
            unset($sec);

            // SENSEX index-level engines
            $sensexOI     = $this->getSensexIndexOI($date, $prevDate);
            $strikeLevel  = $this->getStrikeLevelAnalysis($date);        // Fix 3
            $vwap         = $this->computeVWAP('SENSEX', $date);         // Fix 2
            $futureBias   = $this->getSensexFutureBias($date);           // Fix 5
            $rotation     = $this->detectRotation($date, $prevDate);     // Fix 8 (time-weighted)
            $trap         = $this->detectTrap($sensexOI, $strikeLevel);  // Fix 3+4

            // Fix 9: Rotation > Trap > Stocks > Futures
            $bias         = $this->computeWeightedBias(
                $sectorBuckets, $stockResults, $trap, $rotation, $futureBias, $vwap, $isExpiry
            );

            // Fix 10: Signal score 0–100
            $signalScore  = $this->computeSignalScore($bias, $trap, $rotation, $futureBias, $vwap, $strikeLevel);

            $breadth      = $this->computeBreadth($stockResults);
            $tradePlan    = $this->buildTradePlan($bias, $signalScore, $date, $isExpiry);

            return response()->json([
                'success'       => true,
                'date'          => $date,
                'prev_date'     => $prevDate,
                'is_expiry'     => $isExpiry,
                'bias'          => $bias,
                'signal_score'  => $signalScore,
                'trap'          => $trap,
                'rotation'      => $rotation,
                'futures'       => $futureBias,
                'vwap'          => $vwap,
                'strike_level'  => $strikeLevel,
                'sensex_oi'     => $sensexOI,
                'sectors'       => array_values($sectorBuckets),
                'stocks'        => $stockResults,
                'trade_plan'    => $tradePlan,
                'breadth'       => $breadth,
                'analyzed_at'   => now()->format('Y-m-d H:i:s'),
                'total_tracked' => count($stockResults),
            ]);
        } catch (\Exception $e) {
            Log::error('SensexBacktest::analyze v2 — ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Backtest
    // GET /sensex-backtest/backtest?from=Y-m-d&to=Y-m-d&sl_pct=15&target_pct=30
    // ══════════════════════════════════════════════════════════════════════════

    public function backtest(Request $request)
    {
        try {
            $from      = $request->get('from', Carbon::now()->subDays(30)->toDateString());
            $to        = $request->get('to',   Carbon::today()->toDateString());
            $slPct     = (float) $request->get('sl_pct',     15);
            $targetPct = (float) $request->get('target_pct', 30);
            $minScore  = (int)   $request->get('min_score',  55);  // Fix 10: score filter

            $tradingDays = $this->getTradingDays($from, $to);
            $trades      = [];
            $signals     = [];

            foreach ($tradingDays as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                $nextDate = $this->getNextTradingDate($date);
                $isExpiry = $this->isExpiryDay('SENSEX', $date);

                // Build signal
                $stockResults  = [];
                $sectorBuckets = [];
                foreach (self::SENSEX30_STOCKS as $def) {
                    $r = $this->analyzeStock($def['symbol'], $def['sector'], $def['weight'], $date, $prevDate);
                    if (!$r) continue;
                    $stockResults[] = $r;
                    $sec = $def['sector'];
                    if (!isset($sectorBuckets[$sec])) {
                        $sectorBuckets[$sec] = ['sector' => $sec, 'weight' => $this->sectorTotalWeight($def['sector']),
                                                'stocks' => 0, 'bullish' => 0, 'bearish' => 0, 'neutral' => 0];
                    }
                    $sectorBuckets[$sec]['stocks']++;
                    match($r['oi_signal']) {
                        'BULLISH' => $sectorBuckets[$sec]['bullish']++,
                        'BEARISH' => $sectorBuckets[$sec]['bearish']++,
                        default   => $sectorBuckets[$sec]['neutral']++,
                    };
                }
                foreach ($sectorBuckets as &$s) { $s['signal'] = $this->sectorMajoritySignal($s); }
                unset($s);

                $sensexOI    = $this->getSensexIndexOI($date, $prevDate);
                $strikeLevel = $this->getStrikeLevelAnalysis($date);
                $vwap        = $this->computeVWAP('SENSEX', $date);
                $futureBias  = $this->getSensexFutureBias($date);
                $rotation    = $this->detectRotation($date, $prevDate);
                $trap        = $this->detectTrap($sensexOI, $strikeLevel);
                $bias        = $this->computeWeightedBias(
                    $sectorBuckets, $stockResults, $trap, $rotation, $futureBias, $vwap, $isExpiry
                );
                $score       = $this->computeSignalScore($bias, $trap, $rotation, $futureBias, $vwap, $strikeLevel);

                $signals[] = [
                    'date'        => $date,
                    'direction'   => $bias['direction'],
                    'score'       => $score['total'],
                    'confidence'  => $bias['confidence'],
                    'trap'        => $trap['type'],
                    'rotation'    => $rotation['detected'],
                    'futures'     => $futureBias['bias'],
                    'is_expiry'   => $isExpiry,
                    'vwap_pos'    => $vwap['position'] ?? 'UNKNOWN',
                    'source'      => $bias['source'],
                ];

                // Skip low-score / neutral / expiry (configurable)
                if (in_array($bias['direction'], ['NEUTRAL', 'NO_DATA'])) continue;
                if ($score['total'] < $minScore) continue;

                $trade = $this->simulateTrade($signals[count($signals) - 1], $nextDate, $slPct, $targetPct, $isExpiry);
                if ($trade) $trades[] = $trade;
            }

            return response()->json([
                'success'    => true,
                'from'       => $from,
                'to'         => $to,
                'sl_pct'     => $slPct,
                'target_pct' => $targetPct,
                'min_score'  => $minScore,
                'signals'    => $signals,
                'trades'     => $trades,
                'metrics'    => $this->computeMetrics($trades),
            ]);
        } catch (\Exception $e) {
            Log::error('SensexBacktest::backtest v2 — ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 1+4: Per-stock analysis with Price+OI Fusion + Volume confirmation
    // ══════════════════════════════════════════════════════════════════════════

    private function analyzeStock(
        string $symbol, string $sector, float $weight, string $date, string $prevDate
    ): ?array {
        $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
        $prevExpiry    = $currentExpiry
            ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
            : null;

        // Today 14:45 snapshot
        $todayCeRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'CE')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
            ->where('is_missing', 0)
            ->selectRaw('SUM(oi) as total_oi, SUM(volume) as total_vol')
            ->first();

        $todayPeRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'PE')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
            ->where('is_missing', 0)
            ->selectRaw('SUM(oi) as total_oi, SUM(volume) as total_vol')
            ->first();

        $todayCeOI  = (int)($todayCeRow->total_oi  ?? 0);
        $todayCeVol = (int)($todayCeRow->total_vol ?? 0);
        $todayPeOI  = (int)($todayPeRow->total_oi  ?? 0);
        $todayPeVol = (int)($todayPeRow->total_vol ?? 0);

        if ($todayCeOI === 0 && $todayPeOI === 0) return null;

        // Previous day 15:00 snapshot
        $prevCeOI = (int) OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'CE')->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))
            ->where('is_missing', 0)->sum('oi');

        $prevPeOI = (int) OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'PE')->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))
            ->where('is_missing', 0)->sum('oi');

        // FUT price change for price+OI fusion (Fix 1)
        $futOpen  = (float)(OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '09:15:00'")->value('open') ?? 0);
        $futClose = (float)(OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")->value('close') ?? 0);

        $priceChangePct = ($futOpen > 0)
            ? round((($futClose - $futOpen) / $futOpen) * 100, 2)
            : 0;
        $priceUp = $priceChangePct > 0.1;
        $priceDn = $priceChangePct < -0.1;

        // OI change %
        $ceOiPct = $prevCeOI > 0 ? round((($todayCeOI - $prevCeOI) / $prevCeOI) * 100, 2) : 0;
        $peOiPct = $prevPeOI > 0 ? round((($todayPeOI - $prevPeOI) / $prevPeOI) * 100, 2) : 0;

        // Fix 1: Price + OI fusion
        $priceOiFusion = $this->classifyPriceOI($priceUp, $priceDn, $ceOiPct, $peOiPct);

        // Fix 4: Volume confirmation
        $volumeSignal = $this->classifyVolume($todayCeVol, $todayPeVol, $ceOiPct, $peOiPct);

        // OI-only signal (base)
        $oiSignal = $this->getOISignal($ceOiPct, $peOiPct);

        // Combined signal — fusion takes priority if both agree
        $finalSignal = $this->combinedSignal($oiSignal['signal'], $priceOiFusion['signal'], $volumeSignal['confirmed']);

        $diff         = abs($ceOiPct - $peOiPct);
        $strengthRank = match(true) {
            $diff > 40 => 'Rank 1', $diff > 25 => 'Rank 2',
            $diff > 10 => 'Rank 3', $diff > 5  => 'Rank 4',
            default    => 'Normal',
        };

        return [
            'symbol'          => $symbol,
            'sector'          => $sector,
            'weight'          => $weight,
            'expiry'          => $currentExpiry,
            'fut_price'       => $futClose ?: null,
            'price_change_pct'=> $priceChangePct,
            // OI data
            'ce_oi'           => $todayCeOI,
            'ce_oi_prev'      => $prevCeOI,
            'ce_oi_pct'       => $ceOiPct,
            'ce_vol'          => $todayCeVol,
            'pe_oi'           => $todayPeOI,
            'pe_oi_prev'      => $prevPeOI,
            'pe_oi_pct'       => $peOiPct,
            'pe_vol'          => $todayPeVol,
            // Signals
            'oi_signal'       => $oiSignal['signal'],
            'oi_condition'    => $oiSignal['condition'],
            'oi_reason'       => $oiSignal['reason'],
            'price_oi_fusion' => $priceOiFusion['type'],
            'fusion_signal'   => $priceOiFusion['signal'],
            'volume_signal'   => $volumeSignal['label'],
            'volume_confirmed'=> $volumeSignal['confirmed'],
            'signal'          => $finalSignal,   // used for sector counting
            'trade_action'    => match($finalSignal) { 'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT' },
            'strength_rank'   => $strengthRank,
            'strength_diff'   => round($diff, 2),
            'pe_ce_ratio'     => $todayCeOI > 0 ? round($todayPeOI / $todayCeOI, 2) : 0,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 1: Price + OI Fusion
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Price ↑ + CE ↑ → SHORT BUILD    (bearish — institutions shorting into rally)
     * Price ↑ + PE ↑ → HEDGE / WEAK   (bullish but hedged, watch for reversal)
     * Price ↓ + CE ↑ → STRONG BEARISH (strong directional put on a falling market)
     * Price ↓ + PE ↑ → LONG BUILD     (strong bullish — put buying on dip = reversal)
     * Price ↑ + CE ↓ → UNWINDING      (CE shorts covering = strong bullish)
     * Price ↓ + PE ↓ → UNWINDING      (PE longs exiting = strong bearish continuation)
     */
    private function classifyPriceOI(bool $priceUp, bool $priceDn, float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 2;
        $peUp = $pePct > 2;
        $ceDn = $cePct < -2;
        $peDn = $pePct < -2;

        if ($priceUp && $ceUp && !$peUp) return ['type' => 'SHORT_BUILD',     'signal' => 'BEARISH', 'desc' => 'Price ↑ + CE ↑ — institutions shorting rally'];
        if ($priceUp && $peUp && !$ceUp) return ['type' => 'HEDGE_BULLISH',   'signal' => 'BULLISH', 'desc' => 'Price ↑ + PE ↑ — hedged long, weak bullish'];
        if ($priceUp && $ceUp && $peUp)  return ['type' => 'BOTH_BUILD_UP',   'signal' => 'BEARISH', 'desc' => 'Price ↑ + both OI ↑ — likely CE trap setup'];
        if ($priceDn && $ceUp && !$peUp) return ['type' => 'STRONG_BEARISH',  'signal' => 'BEARISH', 'desc' => 'Price ↓ + CE ↑ — directional CE short on fall'];
        if ($priceDn && $peUp && !$ceUp) return ['type' => 'LONG_BUILD',      'signal' => 'BULLISH', 'desc' => 'Price ↓ + PE ↑ — smart money buying dip via puts'];
        if ($priceDn && $ceUp && $peUp)  return ['type' => 'BOTH_BUILD_DOWN', 'signal' => 'BEARISH', 'desc' => 'Price ↓ + both OI ↑ — strong bearish accumulation'];
        if ($priceUp && $ceDn)           return ['type' => 'CE_UNWIND',       'signal' => 'BULLISH', 'desc' => 'Price ↑ + CE ↓ — shorts covering = bullish'];
        if ($priceDn && $peDn)           return ['type' => 'PE_UNWIND',       'signal' => 'BEARISH', 'desc' => 'Price ↓ + PE ↓ — longs exiting = bearish'];

        return ['type' => 'NEUTRAL', 'signal' => 'NEUTRAL', 'desc' => 'No clear price+OI pattern'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 4: Volume Confirmation
    // ══════════════════════════════════════════════════════════════════════════

    private function classifyVolume(
        int $ceVol, int $peVol, float $cePct, float $pePct
    ): array {
        $totalVol = $ceVol + $peVol;
        if ($totalVol === 0) return ['label' => 'NO_DATA', 'confirmed' => false];

        $cePctOfVol = $totalVol > 0 ? ($ceVol / $totalVol) * 100 : 0;
        $pePctOfVol = $totalVol > 0 ? ($peVol / $totalVol) * 100 : 0;

        // OI ↑ + Volume ↑ = genuine move; OI ↑ + Volume flat = trap
        $ceOiUp  = $cePct > 5;
        $peOiUp  = $pePct > 5;
        $ceVolDominant = $cePctOfVol > 60;
        $peVolDominant = $pePctOfVol > 60;

        if ($ceOiUp && $ceVolDominant)   return ['label' => 'CE_VOL_CONFIRM',  'confirmed' => true];
        if ($peOiUp && $peVolDominant)   return ['label' => 'PE_VOL_CONFIRM',  'confirmed' => true];
        if ($ceOiUp && !$ceVolDominant)  return ['label' => 'CE_OI_NO_VOL',   'confirmed' => false]; // trap warning
        if ($peOiUp && !$peVolDominant)  return ['label' => 'PE_OI_NO_VOL',   'confirmed' => false]; // trap warning

        return ['label' => 'BALANCED', 'confirmed' => false];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Combined OI + Price + Volume signal
    // ══════════════════════════════════════════════════════════════════════════

    private function combinedSignal(string $oiSig, string $fusionSig, bool $volConfirmed): string
    {
        // Both agree — strong signal
        if ($oiSig === $fusionSig && $oiSig !== 'NEUTRAL') {
            return $oiSig;
        }
        // Volume confirmed OI signal
        if ($volConfirmed && $oiSig !== 'NEUTRAL') {
            return $oiSig;
        }
        // Fusion overrides OI when they conflict
        if ($fusionSig !== 'NEUTRAL') {
            return $fusionSig;
        }
        return $oiSig !== 'NEUTRAL' ? $oiSig : 'NEUTRAL';
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OI Signal (base — unchanged from v1)
    // ══════════════════════════════════════════════════════════════════════════

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'CE buildup + PE unwind',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'CE unwind + PE buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both ↑ CE stronger ({$cePct}%>{$pePct}%)", 'condition' => 'Both ↑ (CE>PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both ↑ PE stronger ({$pePct}%>{$cePct}%)", 'condition' => 'Both ↑ (PE>CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both ↓ CE unwinds more ({$cePct}%<{$pePct}%)", 'condition' => 'Both ↓ (CE<PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both ↓ PE unwinds more ({$pePct}%<{$cePct}%)", 'condition' => 'Both ↓ (PE<CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 2: VWAP Computation from 15-min candles
    // ══════════════════════════════════════════════════════════════════════════

    private function computeVWAP(string $symbol, string $date): array
    {
        $candles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['open', 'high', 'low', 'close', 'volume', 'interval_time']);

        if ($candles->isEmpty()) {
            return ['vwap' => null, 'current_price' => null, 'position' => 'UNKNOWN', 'pct_from_vwap' => null];
        }

        // VWAP = Σ(typical_price × volume) / Σ(volume)
        $cumTPV  = 0; // typical_price × volume
        $cumVol  = 0;
        foreach ($candles as $c) {
            $tp      = ((float)$c->high + (float)$c->low + (float)$c->close) / 3;
            $vol     = (int)$c->volume;
            $cumTPV += $tp * $vol;
            $cumVol += $vol;
        }

        if ($cumVol === 0) {
            return ['vwap' => null, 'current_price' => null, 'position' => 'UNKNOWN', 'pct_from_vwap' => null];
        }

        $vwap         = round($cumTPV / $cumVol, 2);
        $lastPrice    = (float)$candles->last()->close;
        $pctFromVwap  = round((($lastPrice - $vwap) / $vwap) * 100, 2);

        $position = match(true) {
            $pctFromVwap > 0.3  => 'ABOVE_VWAP',
            $pctFromVwap < -0.3 => 'BELOW_VWAP',
            default             => 'AT_VWAP',
        };

        return [
            'vwap'          => $vwap,
            'current_price' => $lastPrice,
            'pct_from_vwap' => $pctFromVwap,
            'position'      => $position,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 3: Strike-level ATM OI Analysis
    // ══════════════════════════════════════════════════════════════════════════

    private function getStrikeLevelAnalysis(string $date): array
    {
        // Get ATM strike from stored strike_position
        $atmCe = OptionOhlcData::where('base_symbol', 'SENSEX')
            ->where('instrument_type', 'CE')
            ->whereDate('trade_date', $date)
            ->where('strike_position', 'ATM')
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->where('is_missing', 0)
            ->selectRaw('SUM(oi) as total_oi, SUM(volume) as total_vol, AVG(close) as avg_premium, atm_strike')
            ->first();

        $atmPe = OptionOhlcData::where('base_symbol', 'SENSEX')
            ->where('instrument_type', 'PE')
            ->whereDate('trade_date', $date)
            ->where('strike_position', 'ATM')
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->where('is_missing', 0)
            ->selectRaw('SUM(oi) as total_oi, SUM(volume) as total_vol, AVG(close) as avg_premium')
            ->first();

        // ATM±1 (most sensitive to moves)
        $atmP1Ce = (int) OptionOhlcData::where('base_symbol', 'SENSEX')
            ->where('instrument_type', 'CE')->whereDate('trade_date', $date)
            ->where('strike_position', 'ATM+1')
            ->whereRaw("TIME(interval_time) = '14:45:00'")->where('is_missing', 0)->sum('oi');

        $atmM1Pe = (int) OptionOhlcData::where('base_symbol', 'SENSEX')
            ->where('instrument_type', 'PE')->whereDate('trade_date', $date)
            ->where('strike_position', 'ATM-1')
            ->whereRaw("TIME(interval_time) = '14:45:00'")->where('is_missing', 0)->sum('oi');

        $atmCeOI  = (int)($atmCe->total_oi ?? 0);
        $atmPeOI  = (int)($atmPe->total_oi ?? 0);
        $atmStrike = (float)($atmCe->atm_strike ?? 0);

        // Strike-level PCR (more accurate than total PCR)
        $atmPcr   = $atmCeOI > 0 ? round($atmPeOI / $atmCeOI, 2) : 0;

        // ATM OI signal — where real gamma sits
        $atmOiSignal = 'NEUTRAL';
        if ($atmCeOI > 0 && $atmPeOI > 0) {
            if ($atmPeOI > $atmCeOI * 1.2)      $atmOiSignal = 'BULLISH'; // PE > CE at ATM = support
            elseif ($atmCeOI > $atmPeOI * 1.2)  $atmOiSignal = 'BEARISH'; // CE > PE at ATM = resistance
        }

        // Premium check (expensive CE/PE = expectation of move)
        $atmCePremium = round((float)($atmCe->avg_premium ?? 0), 2);
        $atmPePremium = round((float)($atmPe->avg_premium ?? 0), 2);
        $premiumBias  = match(true) {
            $atmCePremium > $atmPePremium * 1.3 => 'CE_EXPENSIVE',
            $atmPePremium > $atmCePremium * 1.3 => 'PE_EXPENSIVE',
            default                              => 'BALANCED',
        };

        return [
            'atm_strike'     => $atmStrike,
            'atm_ce_oi'      => $atmCeOI,
            'atm_pe_oi'      => $atmPeOI,
            'atm_pcr'        => $atmPcr,
            'atm_ce_premium' => $atmCePremium,
            'atm_pe_premium' => $atmPePremium,
            'premium_bias'   => $premiumBias,
            'atm_p1_ce_oi'   => $atmP1Ce,
            'atm_m1_pe_oi'   => $atmM1Pe,
            'atm_oi_signal'  => $atmOiSignal,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 3+4: Upgraded Trap Detection using strike-level + volume
    // ══════════════════════════════════════════════════════════════════════════

    private function detectTrap(array $sensexOI, array $strikeLevel): array
    {
        $cePct = $sensexOI['ce_pct'];
        $pePct = $sensexOI['pe_pct'];
        $diff  = abs($cePct - $pePct);

        // Volume-disconfirmed OI build = trap more likely (Fix 4)
        $ceVolConfirmed = $sensexOI['ce_vol_confirmed'] ?? false;
        $peVolConfirmed = $sensexOI['pe_vol_confirmed'] ?? false;

        // ATM-level corroboration (Fix 3)
        $atmBias = $strikeLevel['atm_oi_signal'] ?? 'NEUTRAL';

        $isTrap    = false;
        $trapType  = 'NO_TRAP';
        $trapDesc  = 'No clear trap pattern';
        $trapConf  = 0;
        $trapAct   = 'NONE';

        // Classic trap: both OI rising but lopsided
        if ($cePct > 0 && $pePct > 0 && $diff > self::TRAP_DIFF_THRESHOLD) {
            $isTrap = true;
            if ($pePct > $cePct) {
                $trapType = 'CE_TRAP';
                $trapDesc = sprintf('PE OI +%.1f%% >> CE OI +%.1f%% — retail long CE trapped; expect DOWN', $pePct, $cePct);
                $trapAct  = 'BUY_PE';
                $trapConf = min(88, 58 + (int)$diff);
            } else {
                $trapType = 'PE_TRAP';
                $trapDesc = sprintf('CE OI +%.1f%% >> PE OI +%.1f%% — retail short PE trapped; expect UP', $cePct, $pePct);
                $trapAct  = 'BUY_CE';
                $trapConf = min(88, 58 + (int)$diff);
            }
            // Fix 4: No volume confirmation = weaker trap signal
            if (!$ceVolConfirmed && !$peVolConfirmed) {
                $trapConf = (int)($trapConf * 0.8);
                $trapDesc .= ' [volume unconfirmed — moderate confidence]';
            }
        }

        // Extreme one-sided build
        if (!$isTrap && $cePct > 20 && $pePct < 3) {
            $isTrap = true; $trapType = 'CE_TRAP'; $trapAct = 'BUY_PE'; $trapConf = 72;
            $trapDesc = 'Extreme CE build + PE flat — strong resistance trap';
        }
        if (!$isTrap && $pePct > 20 && $cePct < 3) {
            $isTrap = true; $trapType = 'PE_TRAP'; $trapAct = 'BUY_CE'; $trapConf = 72;
            $trapDesc = 'Extreme PE build + CE flat — strong support trap';
        }

        // Fix 3: ATM corroboration strengthens trap
        if ($isTrap) {
            if ($trapType === 'CE_TRAP' && $atmBias === 'BEARISH') $trapConf = min(92, $trapConf + 8);
            if ($trapType === 'PE_TRAP' && $atmBias === 'BULLISH') $trapConf = min(92, $trapConf + 8);
        }

        return [
            'type'        => $trapType,
            'description' => $trapDesc,
            'action'      => $trapAct,
            'confidence'  => $trapConf,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SENSEX Index OI (now includes volume — Fix 4)
    // ══════════════════════════════════════════════════════════════════════════

    private function getSensexIndexOI(string $date, string $prevDate): array
    {
        $q = fn($sym, $type, $d, $time) => OptionOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', $type)->whereDate('trade_date', $d)
            ->whereRaw("TIME(interval_time) = '{$time}:00'")->where('is_missing', 0);

        $todayCe    = $q('SENSEX', 'CE', $date, '14:45')->selectRaw('SUM(oi) as oi, SUM(volume) as vol')->first();
        $todayPe    = $q('SENSEX', 'PE', $date, '14:45')->selectRaw('SUM(oi) as oi, SUM(volume) as vol')->first();
        $prevCeOI   = (int)$q('SENSEX', 'CE', $prevDate, '15:00')->sum('oi');
        $prevPeOI   = (int)$q('SENSEX', 'PE', $prevDate, '15:00')->sum('oi');

        $ceOI  = (int)($todayCe->oi  ?? 0);
        $peOI  = (int)($todayPe->oi  ?? 0);
        $ceVol = (int)($todayCe->vol ?? 0);
        $peVol = (int)($todayPe->vol ?? 0);

        $cePct = $prevCeOI > 0 ? round((($ceOI - $prevCeOI) / $prevCeOI) * 100, 2) : 0;
        $pePct = $prevPeOI > 0 ? round((($peOI - $prevPeOI) / $prevPeOI) * 100, 2) : 0;

        $totalVol = $ceVol + $peVol;
        $ceVolConf = $totalVol > 0 && ($ceVol / $totalVol) > 0.6 && $cePct > 5;
        $peVolConf = $totalVol > 0 && ($peVol / $totalVol) > 0.6 && $pePct > 5;

        return [
            'ce_oi'           => $ceOI,
            'pe_oi'           => $peOI,
            'ce_pct'          => $cePct,
            'pe_pct'          => $pePct,
            'ce_vol'          => $ceVol,
            'pe_vol'          => $peVol,
            'ce_vol_confirmed'=> $ceVolConf,
            'pe_vol_confirmed'=> $peVolConf,
            'pcr'             => $ceOI > 0 ? round($peOI / $ceOI, 2) : 0,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 5: Futures Bias with OI direction
    // ══════════════════════════════════════════════════════════════════════════

    private function getSensexFutureBias(string $date): array
    {
        $open  = OptionOhlcData::where('base_symbol', 'SENSEX')->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)->whereRaw("TIME(interval_time) = '09:15:00'")->first(['close', 'oi']);
        $close = OptionOhlcData::where('base_symbol', 'SENSEX')->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)->whereRaw("TIME(interval_time) = '14:45:00'")->first(['close', 'oi']);

        if (!$close) return ['bias' => 'NO_DATA', 'fut_price' => null, 'premium' => null, 'oi_direction' => null, 'price_oi_type' => null];

        $futPrice     = (float)$close->close;
        $openPrice    = $open ? (float)$open->close : null;
        $openOI       = $open ? (int)$open->oi : 0;
        $closeOI      = (int)$close->oi;

        $premium      = $openPrice ? round($futPrice - $openPrice, 2) : null;
        $oiChange     = $openOI > 0 ? round((($closeOI - $openOI) / $openOI) * 100, 2) : 0;
        $priceUp      = $premium !== null && $premium > 0;
        $oiUp         = $oiChange > 1;

        // Fix 5: Price + OI direction on futures
        $priceOiType = match(true) {
            $priceUp  && $oiUp   => 'LONG_BUILD',     // bullish
            !$priceUp && $oiUp   => 'SHORT_BUILD',    // bearish
            $priceUp  && !$oiUp  => 'SHORT_COVER',    // bullish but weak
            !$priceUp && !$oiUp  => 'LONG_UNWIND',    // bearish but weak
            default              => 'NEUTRAL',
        };

        $bias = match($priceOiType) {
            'LONG_BUILD'   => 'STRONG_BULLISH',
            'SHORT_COVER'  => 'BULLISH',
            'SHORT_BUILD'  => 'STRONG_BEARISH',
            'LONG_UNWIND'  => 'BEARISH',
            default        => 'NEUTRAL',
        };

        return [
            'bias'          => $bias,
            'fut_price'     => $futPrice,
            'premium'       => $premium,
            'oi_change_pct' => $oiChange,
            'oi_direction'  => $oiUp ? 'INCREASING' : 'DECREASING',
            'price_oi_type' => $priceOiType,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 8: Time-weighted Rotation Detection
    // ══════════════════════════════════════════════════════════════════════════

    private function detectRotation(string $date, string $prevDate): array
    {
        $intervals = ['09:30', '10:00', '10:30', '11:00', '11:30',
                      '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '14:45'];

        $ceByTime = [];
        $peByTime = [];
        foreach ($intervals as $time) {
            $ce = (int) OptionOhlcData::where('base_symbol', 'SENSEX')->where('instrument_type', 'CE')
                ->whereDate('trade_date', $date)->whereRaw("TIME(interval_time) = '{$time}:00'")->where('is_missing', 0)->sum('oi');
            $pe = (int) OptionOhlcData::where('base_symbol', 'SENSEX')->where('instrument_type', 'PE')
                ->whereDate('trade_date', $date)->whereRaw("TIME(interval_time) = '{$time}:00'")->where('is_missing', 0)->sum('oi');
            if ($ce > 0 || $pe > 0) { $ceByTime[$time] = $ce; $peByTime[$time] = $pe; }
        }

        if (count($ceByTime) < 4) {
            return ['detected' => false, 'type' => 'NONE', 'description' => 'Insufficient intraday data',
                    'time_weight' => 'LOW', 'confidence' => 0];
        }

        $times = array_keys($ceByTime);
        $n     = count($times);
        $mid   = (int)($n / 2);
        $earlyTimes = array_slice($times, 0, $mid);
        $lateTimes  = array_slice($times, $mid);

        $earlyPe = array_sum(array_map(fn($t) => $peByTime[$t], $earlyTimes)) / count($earlyTimes);
        $latePe  = array_sum(array_map(fn($t) => $peByTime[$t], $lateTimes))  / count($lateTimes);
        $earlyCe = array_sum(array_map(fn($t) => $ceByTime[$t], $earlyTimes)) / count($earlyTimes);
        $lateCe  = array_sum(array_map(fn($t) => $ceByTime[$t], $lateTimes))  / count($lateTimes);

        $peDrop = $earlyPe > 0 ? (($earlyPe - $latePe) / $earlyPe) * 100 : 0;
        $ceRise = $earlyCe > 0 ? (($lateCe  - $earlyCe)  / $earlyCe)  * 100 : 0;
        $ceDrop = $earlyCe > 0 ? (($earlyCe - $lateCe)   / $earlyCe)  * 100 : 0;
        $peRise = $earlyPe > 0 ? (($latePe  - $earlyPe)  / $earlyPe)  * 100 : 0;

        $threshold = self::ROTATION_SWING_THRESHOLD;

        // Fix 8: Time-based weight — rotation after 12:00 is more reliable
        $lastTime      = end($times);
        $lastHour      = (int)explode(':', $lastTime)[0];
        $timeWeight    = $lastHour >= 12 ? 'HIGH' : 'MEDIUM';
        $timeBonus     = $lastHour >= 12 ? 12 : 0;

        if ($peDrop > $threshold && $ceRise > $threshold) {
            $conf = min(90, 60 + (int)(($peDrop + $ceRise) / 4) + $timeBonus);
            return [
                'detected'    => true,
                'type'        => 'PE_TO_CE',
                'description' => sprintf('PE OI -%.1f%% + CE OI +%.1f%% — smart money rotating LONG', $peDrop, $ceRise),
                'action'      => 'BUY_CE',
                'confidence'  => $conf,
                'time_weight' => $timeWeight,
            ];
        }
        if ($ceDrop > $threshold && $peRise > $threshold) {
            $conf = min(90, 60 + (int)(($ceDrop + $peRise) / 4) + $timeBonus);
            return [
                'detected'    => true,
                'type'        => 'CE_TO_PE',
                'description' => sprintf('CE OI -%.1f%% + PE OI +%.1f%% — smart money rotating SHORT', $ceDrop, $peRise),
                'action'      => 'BUY_PE',
                'confidence'  => $conf,
                'time_weight' => $timeWeight,
            ];
        }

        return ['detected' => false, 'type' => 'NONE', 'description' => 'No significant rotation',
                'time_weight' => $timeWeight, 'confidence' => 0];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 9: Weighted Bias — Reordered: Rotation > Trap > Stocks > Futures
    // ══════════════════════════════════════════════════════════════════════════

    private function computeWeightedBias(
        array $sectorBuckets, array $stocks,
        array $trap, array $rotation, array $futureBias,
        array $vwap, bool $isExpiry
    ): array {
        $reasons = [];

        // PRIORITY 1: ROTATION (Fix 9 — upgraded from Priority 2)
        // Rotation = actual money moving, not a setup
        if ($rotation['detected']) {
            $direction = $rotation['type'] === 'PE_TO_CE' ? 'BULLISH' : 'BEARISH';
            $conf      = $rotation['confidence'];
            $reasons[] = 'ROTATION: ' . $rotation['description'];

            // Fix 2: VWAP filter on rotation
            if ($vwap['position'] !== 'UNKNOWN') {
                $vwapAgrees = ($direction === 'BULLISH' && $vwap['position'] === 'ABOVE_VWAP')
                           || ($direction === 'BEARISH' && $vwap['position'] === 'BELOW_VWAP');
                if ($vwapAgrees) {
                    $conf = min(95, $conf + 10);
                    $reasons[] = 'VWAP confirms (' . $vwap['position'] . ')';
                } else {
                    $conf = max(40, $conf - 10);
                    $reasons[] = 'VWAP disagrees (' . $vwap['position'] . ') — reduced confidence';
                }
            }

            // Fix 8: Time weight bonus
            if (($rotation['time_weight'] ?? '') === 'HIGH') {
                $conf = min(95, $conf + 5);
                $reasons[] = 'Late-session rotation (higher weight)';
            }

            // Fix 7: Expiry penalty
            if ($isExpiry) {
                $conf = max(30, $conf - self::EXPIRY_CONFIDENCE_PENALTY);
                $reasons[] = 'Expiry day — confidence reduced';
            }

            return [
                'direction'   => $direction,
                'strength'    => $conf >= 80 ? 'STRONG' : 'MODERATE',
                'confidence'  => $conf,
                'reason'      => implode(' | ', $reasons),
                'bull_weight' => $direction === 'BULLISH' ? $conf : 100 - $conf,
                'bear_weight' => $direction === 'BEARISH' ? $conf : 100 - $conf,
                'source'      => 'ROTATION' . ($rotation['time_weight'] === 'HIGH' ? '_LATE' : ''),
            ];
        }

        // PRIORITY 2: TRAP (Fix 9 — downgraded from Priority 1)
        if ($trap['type'] !== 'NO_TRAP') {
            $direction = $trap['type'] === 'CE_TRAP' ? 'BEARISH' : 'BULLISH';
            $conf      = $trap['confidence'];
            $reasons[] = 'TRAP: ' . $trap['description'];

            // Fix 2: VWAP filter on trap
            if ($vwap['position'] !== 'UNKNOWN') {
                $vwapAgrees = ($direction === 'BULLISH' && $vwap['position'] === 'ABOVE_VWAP')
                           || ($direction === 'BEARISH' && $vwap['position'] === 'BELOW_VWAP');
                $conf = $vwapAgrees ? min(92, $conf + 8) : max(40, $conf - 8);
                $reasons[] = 'VWAP: ' . $vwap['position'];
            }
            if ($isExpiry) { $conf = max(30, $conf - self::EXPIRY_CONFIDENCE_PENALTY); $reasons[] = 'Expiry day penalty'; }

            return [
                'direction'   => $direction,
                'strength'    => $conf >= 75 ? 'STRONG' : 'MODERATE',
                'confidence'  => $conf,
                'reason'      => implode(' | ', $reasons),
                'bull_weight' => $direction === 'BULLISH' ? $conf : 100 - $conf,
                'bear_weight' => $direction === 'BEARISH' ? $conf : 100 - $conf,
                'source'      => 'TRAP',
            ];
        }

        // PRIORITY 3: Stock weighted OI
        $sectorStockCount = [];
        foreach ($stocks as $s) { $sectorStockCount[$s['sector']] = ($sectorStockCount[$s['sector']] ?? 0) + 1; }

        $weightedBull = 0; $weightedBear = 0; $totalWeight = 0;
        foreach ($stocks as $s) {
            $cnt          = $sectorStockCount[$s['sector']] ?? 1;
            $stockWeight  = $s['weight'] / $cnt;
            $totalWeight += $stockWeight;
            if ($s['signal'] === 'BULLISH') $weightedBull += $stockWeight;
            if ($s['signal'] === 'BEARISH') $weightedBear += $stockWeight;
        }

        $bullPct   = $totalWeight > 0 ? round($weightedBull / 100 * 100, 1) : 0;
        $bearPct   = $totalWeight > 0 ? round($weightedBear / 100 * 100, 1) : 0;
        $direction = $weightedBull > $weightedBear ? 'BULLISH' : ($weightedBear > $weightedBull ? 'BEARISH' : 'NEUTRAL');

        // Financial sector override
        $finSec    = $sectorBuckets['Financial Services'] ?? null;
        $finSignal = $finSec ? $this->sectorMajoritySignal($finSec) : 'NO_DATA';
        if ($finSignal === 'BULLISH' && 38.3 > $weightedBear) { $direction = 'BULLISH'; $reasons[] = 'Financial (38%) bullish'; }
        if ($finSignal === 'BEARISH' && 38.3 > $weightedBull) { $direction = 'BEARISH'; $reasons[] = 'Financial (38%) bearish'; }

        // Fix 2: VWAP confirmation on stock signal
        if ($vwap['position'] !== 'UNKNOWN') {
            $vwapAgrees = ($direction === 'BULLISH' && $vwap['position'] === 'ABOVE_VWAP')
                       || ($direction === 'BEARISH' && $vwap['position'] === 'BELOW_VWAP');
            $reasons[] = 'VWAP: ' . $vwap['position'] . ($vwapAgrees ? ' (confirms)' : ' (conflicts)');
        }

        // PRIORITY 4: Futures confirmation
        $futBull = str_contains($futureBias['bias'] ?? '', 'BULLISH');
        $futBear = str_contains($futureBias['bias'] ?? '', 'BEARISH');
        if (($direction === 'BULLISH' && $futBull) || ($direction === 'BEARISH' && $futBear)) {
            $reasons[] = 'FUT ' . ($futureBias['price_oi_type'] ?? '') . ' confirms';
        }

        $dominant   = max($bullPct, $bearPct);
        $bullCount  = count(array_filter($stocks, fn($s) => $s['signal'] === 'BULLISH'));
        $bearCount  = count(array_filter($stocks, fn($s) => $s['signal'] === 'BEARISH'));
        $breadthPct = count($stocks) > 0 ? max($bullCount, $bearCount) / count($stocks) * 100 : 0;
        $strength   = match(true) { $dominant >= 30 => 'STRONG', $dominant >= 20 => 'MODERATE', default => 'WEAK' };
        $confidence = match($strength) { 'STRONG' => 78, 'MODERATE' => 62, default => 48 };

        if ($breadthPct >= 65) { $strength = 'STRONG'; $confidence = min(86, $confidence + 12); $reasons[] = round($breadthPct) . '% breadth'; }
        if ($isExpiry) { $confidence = max(30, $confidence - self::EXPIRY_CONFIDENCE_PENALTY); $reasons[] = 'Expiry day penalty'; }

        return [
            'direction'   => $direction,
            'strength'    => $strength,
            'confidence'  => $confidence,
            'reason'      => implode(' | ', array_slice($reasons, 0, 6)),
            'bull_weight' => $bullPct,
            'bear_weight' => $bearPct,
            'source'      => 'STOCKS',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 10: Signal Score 0–100
    // ══════════════════════════════════════════════════════════════════════════

    private function computeSignalScore(
        array $bias, array $trap, array $rotation,
        array $futureBias, array $vwap, array $strikeLevel
    ): array {
        $scores = [];

        // 1. Base bias confidence (0–35 pts)
        $biasScore = min(35, (int)($bias['confidence'] * 0.4));
        $scores['bias'] = $biasScore;

        // 2. Rotation (0–20 pts)
        $rotScore = $rotation['detected'] ? min(20, (int)($rotation['confidence'] * 0.22)) : 0;
        $scores['rotation'] = $rotScore;

        // 3. Trap (0–15 pts)
        $trapScore = $trap['type'] !== 'NO_TRAP' ? min(15, (int)($trap['confidence'] * 0.17)) : 0;
        $scores['trap'] = $trapScore;

        // 4. VWAP alignment (0–10 pts)
        $vwapScore = 0;
        if ($vwap['position'] !== 'UNKNOWN') {
            $vwapAgrees = ($bias['direction'] === 'BULLISH' && $vwap['position'] === 'ABOVE_VWAP')
                       || ($bias['direction'] === 'BEARISH' && $vwap['position'] === 'BELOW_VWAP');
            $vwapScore = $vwapAgrees ? 10 : 0;
        }
        $scores['vwap'] = $vwapScore;

        // 5. Futures alignment (0–10 pts)
        $futScore = 0;
        if (!in_array($futureBias['bias'], ['NO_DATA', 'NEUTRAL'])) {
            $futBull = str_contains($futureBias['bias'], 'BULLISH');
            $agrees  = ($bias['direction'] === 'BULLISH' && $futBull)
                    || ($bias['direction'] === 'BEARISH' && !$futBull);
            $futScore = $agrees ? ($futureBias['price_oi_type'] === 'LONG_BUILD' || $futureBias['price_oi_type'] === 'SHORT_BUILD' ? 10 : 6) : 0;
        }
        $scores['futures'] = $futScore;

        // 6. ATM strike alignment (0–10 pts) — Fix 3
        $strikeScore = 0;
        if ($strikeLevel['atm_oi_signal'] !== 'NEUTRAL') {
            $agrees = ($bias['direction'] === 'BULLISH' && $strikeLevel['atm_oi_signal'] === 'BULLISH')
                   || ($bias['direction'] === 'BEARISH' && $strikeLevel['atm_oi_signal'] === 'BEARISH');
            $strikeScore = $agrees ? 10 : 0;
        }
        $scores['strike'] = $strikeScore;

        $total = min(100, array_sum($scores));
        $grade = match(true) {
            $total >= 80 => 'A+',
            $total >= 70 => 'A',
            $total >= 60 => 'B',
            $total >= 50 => 'C',
            default      => 'D',
        };

        return ['total' => $total, 'grade' => $grade, 'breakdown' => $scores];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FIX 6+7: Slippage-aware, expiry-adjusted trade simulation
    // ══════════════════════════════════════════════════════════════════════════

    private function simulateTrade(
        array $signal, string $tradeDate,
        float $slPct, float $targetPct, bool $isExpiry
    ): ?array {
        $optionType = $signal['direction'] === 'BULLISH' ? 'CE' : 'PE';

        // Fix 7: Widen SL on expiry day
        if ($isExpiry) {
            $slPct     *= self::EXPIRY_SL_MULTIPLIER;
            $targetPct *= 1.2; // also target higher on expiry for the gamma move
        }

        $entryRow = OptionOhlcData::where('base_symbol', 'SENSEX')
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $tradeDate)
            ->whereRaw("TIME(interval_time) = '09:30:00'")
            ->where('strike_position', 'ATM')
            ->where('is_missing', 0)
            ->first(['open', 'close', 'high', 'low']);

        if (!$entryRow || !$entryRow->open) return null;

        // Fix 6: Add slippage to entry price
        $rawEntry    = (float)$entryRow->open;
        if ($rawEntry <= 0) return null;
        $slippage    = round($rawEntry * (self::SLIPPAGE_PCT / 100), 2);
        $entryPrice  = round($rawEntry + $slippage, 2);   // worse fill

        $slPrice     = round($entryPrice * (1 - $slPct / 100), 2);
        $targetPrice = round($entryPrice * (1 + $targetPct / 100), 2);

        $candles = OptionOhlcData::where('base_symbol', 'SENSEX')
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $tradeDate)
            ->where('strike_position', 'ATM')
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) >= '09:30:00'")
            ->orderBy('interval_time')
            ->get(['interval_time', 'high', 'low', 'close']);

        $exitPrice = null; $exitTime = null; $exitReason = 'EOD';
        foreach ($candles as $candle) {
            if ((float)$candle->high >= $targetPrice) { $exitPrice = $targetPrice; $exitTime = Carbon::parse($candle->interval_time)->format('H:i'); $exitReason = 'TARGET'; break; }
            if ((float)$candle->low  <= $slPrice)     { $exitPrice = $slPrice;     $exitTime = Carbon::parse($candle->interval_time)->format('H:i'); $exitReason = 'SL';     break; }
        }

        if (!$exitPrice) {
            $last = $candles->last();
            $exitPrice = $last ? (float)$last->close : $entryPrice;
            $exitTime  = '15:15';
        }

        $pnlPct = round((($exitPrice - $entryPrice) / $entryPrice) * 100, 2);

        return [
            'signal_date'  => $signal['date'],
            'trade_date'   => $tradeDate,
            'direction'    => $signal['direction'],
            'option_type'  => $optionType,
            'score'        => $signal['score'],
            'confidence'   => $signal['confidence'],
            'trap'         => $signal['trap'],
            'rotation'     => $signal['rotation'],
            'source'       => $signal['source'],
            'raw_entry'    => $rawEntry,
            'slippage'     => $slippage,
            'entry_price'  => $entryPrice,
            'exit_price'   => $exitPrice,
            'sl_price'     => $slPrice,
            'target_price' => $targetPrice,
            'sl_pct_used'  => $slPct,
            'exit_reason'  => $exitReason,
            'exit_time'    => $exitTime,
            'pnl_pct'      => $pnlPct,
            'result'       => $pnlPct > 0 ? 'WIN' : 'LOSS',
            'is_expiry'    => $isExpiry,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Trade Plan
    // ══════════════════════════════════════════════════════════════════════════

    private function buildTradePlan(array $bias, array $signalScore, string $date, bool $isExpiry): array
    {
        $nextDay = $this->getNextTradingDate($date);

        if (in_array($bias['direction'], ['NEUTRAL', 'NO_DATA']) || $signalScore['total'] < 45) {
            return [
                'trade_date'    => $nextDay,
                'action'        => 'WAIT',
                'option_type'   => 'NONE',
                'strike'        => 'ATM',
                'entry_time'    => '09:30 — wait & watch',
                'entry_trigger' => $signalScore['total'] < 45
                    ? 'Score too low (' . $signalScore['total'] . '/100) — skip this day'
                    : 'No clear bias — skip or wait for first candle breakout',
                'stop_loss'     => 'N/A',
                'target'        => 'N/A',
                'score'         => $signalScore['total'],
                'grade'         => $signalScore['grade'],
                'is_expiry'     => $isExpiry,
            ];
        }

        $isBull     = $bias['direction'] === 'BULLISH';
        $optionType = $isBull ? 'CE' : 'PE';
        $slNote     = $isExpiry ? '22% of premium (expiry — wider SL)' : '15% of premium OR previous candle extreme';
        $tgtNote    = $isExpiry ? '36% of premium (expiry — gamma move)' : '30% of premium | Trail via 15-min candle extreme';

        return [
            'trade_date'    => $nextDay,
            'action'        => "BUY {$optionType}",
            'option_type'   => $optionType,
            'strike'        => $isBull ? 'ATM or ATM+1' : 'ATM or ATM-1',
            'entry_time'    => '09:30 after first 15-min candle closes',
            'entry_trigger' => $isBull
                ? 'Break ABOVE first candle HIGH (09:15–09:30)'
                : 'Break BELOW first candle LOW (09:15–09:30)',
            'stop_loss'     => $slNote,
            'target'        => $tgtNote,
            'score'         => $signalScore['total'],
            'grade'         => $signalScore['grade'],
            'confidence'    => $bias['confidence'],
            'strength'      => $bias['strength'],
            'source'        => $bias['source'],
            'is_expiry'     => $isExpiry,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Backtest Metrics
    // ══════════════════════════════════════════════════════════════════════════

    private function computeMetrics(array $trades): array
    {
        if (empty($trades)) {
            return ['total' => 0, 'wins' => 0, 'losses' => 0, 'win_rate' => 0,
                    'avg_pnl_pct' => 0, 'total_pnl_pct' => 0,
                    'max_win_pct' => 0, 'max_loss_pct' => 0,
                    'avg_win_pct' => 0, 'avg_loss_pct' => 0,
                    'rr_ratio' => 0, 'max_drawdown' => 0, 'equity_curve' => [],
                    'target_hit' => 0, 'sl_hit' => 0, 'eod_exit' => 0];
        }

        $wins   = array_values(array_filter($trades, fn($t) => $t['result'] === 'WIN'));
        $losses = array_values(array_filter($trades, fn($t) => $t['result'] === 'LOSS'));
        $pnls   = array_column($trades, 'pnl_pct');
        $avgWin  = count($wins)   > 0 ? round(array_sum(array_column($wins,   'pnl_pct')) / count($wins),   2) : 0;
        $avgLoss = count($losses) > 0 ? round(array_sum(array_column($losses, 'pnl_pct')) / count($losses), 2) : 0;

        $equity = []; $cum = 0; $peak = 0; $maxDd = 0;
        foreach ($trades as $t) {
            $cum  += $t['pnl_pct'];
            $peak  = max($peak, $cum);
            $maxDd = max($maxDd, $peak - $cum);
            $equity[] = ['date' => $t['trade_date'], 'cumulative' => round($cum, 2)];
        }

        return [
            'total'         => count($trades),
            'wins'          => count($wins),
            'losses'        => count($losses),
            'win_rate'      => round(count($wins) / count($trades) * 100, 1),
            'avg_pnl_pct'   => round(array_sum($pnls) / count($pnls), 2),
            'total_pnl_pct' => round(array_sum($pnls), 2),
            'max_win_pct'   => !empty($wins)   ? max(array_column($wins,   'pnl_pct')) : 0,
            'max_loss_pct'  => !empty($losses) ? min(array_column($losses, 'pnl_pct')) : 0,
            'avg_win_pct'   => $avgWin,
            'avg_loss_pct'  => $avgLoss,
            'rr_ratio'      => $avgLoss != 0 ? round(abs($avgWin / $avgLoss), 2) : 0,
            'max_drawdown'  => round($maxDd, 2),
            'equity_curve'  => $equity,
            'target_hit'    => count(array_filter($trades, fn($t) => $t['exit_reason'] === 'TARGET')),
            'sl_hit'        => count(array_filter($trades, fn($t) => $t['exit_reason'] === 'SL')),
            'eod_exit'      => count(array_filter($trades, fn($t) => $t['exit_reason'] === 'EOD')),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function computeBreadth(array $stocks): array
    {
        $total = count($stocks);
        $bull  = count(array_filter($stocks, fn($s) => $s['signal'] === 'BULLISH'));
        $bear  = count(array_filter($stocks, fn($s) => $s['signal'] === 'BEARISH'));
        $bullPct = $total > 0 ? round($bull / $total * 100, 1) : 0;
        $bearPct = $total > 0 ? round($bear / $total * 100, 1) : 0;
        return ['total' => $total, 'bullish' => $bull, 'bearish' => $bear,
                'neutral' => $total - $bull - $bear, 'bull_pct' => $bullPct,
                'bear_pct' => $bearPct,
                'signal' => match(true) {
                    $bullPct >= 70 => 'STRONG BULLISH', $bullPct >= 55 => 'BULLISH',
                    $bearPct >= 70 => 'STRONG BEARISH', $bearPct >= 55 => 'BEARISH',
                    default        => 'NEUTRAL',
                }];
    }

    private function isExpiryDay(string $symbol, string $date): bool
    {
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $date)
            ->whereDate('trade_date', $date)
            ->exists();
    }

    private function sectorTotalWeight(string $sector): float
    {
        $w = [];
        foreach (self::SENSEX30_STOCKS as $s) { $w[$s['sector']] = ($w[$s['sector']] ?? 0) + $s['weight']; }
        return round($w[$sector] ?? 0, 1);
    }

    private function sectorMajoritySignal(array $sec): string
    {
        $t = $sec['stocks'];
        if ($t === 0) return 'NO_DATA';
        if ($sec['bullish'] > $sec['bearish'] && $sec['bullish'] >= ceil($t / 2)) return 'BULLISH';
        if ($sec['bearish'] > $sec['bullish'] && $sec['bearish'] >= ceil($t / 2)) return 'BEARISH';
        return 'NEUTRAL';
    }

    private function getTradingDays(string $from, string $to): array
    {
        $days = []; $c = Carbon::parse($from)->startOfDay(); $end = Carbon::parse($to)->startOfDay();
        while ($c->lte($end)) {
            if (!$c->isWeekend() && !$this->isHoliday($c->toDateString())) $days[] = $c->toDateString();
            $c->addDay();
        }
        return $days;
    }

    private function getPreviousTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) { if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) return $d->format('Y-m-d'); $d->subDay(); }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function getNextTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->addDay();
        for ($i = 0; $i < 10; $i++) { if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) return $d->format('Y-m-d'); $d->addDay(); }
        return Carbon::parse($date)->addDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->whereIn('market_name', ['BSE', 'NSE'])
            ->where('holiday_date', $date)->exists();
    }

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')->whereDate('trade_date', $date)->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));
        if (!$expiry) {
            $expiry = OptionOhlcData::where('base_symbol', $symbol)->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('expiry_date')->whereDate('trade_date', $date)
                ->orderByDesc('expiry_date')->value(DB::raw('DATE(expiry_date)'));
        }
        if ($expiry === $date) {
            $next = OptionOhlcData::where('base_symbol', $symbol)->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $date)->whereDate('expiry_date', '>', $expiry)
                ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));
            if ($next) return $next;
        }
        return $expiry;
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = OptionOhlcData::where('base_symbol', $symbol)->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)->whereDate('expiry_date', $currentExpiry)->where('is_missing', 0)->exists();
        if ($exists) return $currentExpiry;
        return OptionOhlcData::where('base_symbol', $symbol)->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)->whereNotNull('expiry_date')->where('is_missing', 0)
            ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));
    }
}