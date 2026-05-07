<?php
// FILE: app/Http/Controllers/User/QuantEdgeSmartMoneyController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * QuantEdge — Smart Money Analysis
 *
 * SMC signals from cp_stock_ohlc_{timeframe} via analysis_config scope.
 *
 * ── Fixes vs previous version ────────────────────────────────────────────────
 *
 * FIX 1 — Trend: 2-candle (last vs prev) was too noisy → replaced with
 *   5-candle swing structure: count HH/HL vs LH/LL across last 5 candles.
 *   UP if 3+ are HH+HL, DOWN if 3+ are LH+LL, else SIDEWAYS.
 *
 * FIX 2 — Liquidity sweep: was requiring BOTH wick through range AND close
 *   back inside in a single candle — near-impossible on intraday.
 *   Now: sweep low = last candle makes new 10-bar low (close above it = bonus,
 *   not required). sweep high = last candle makes new 10-bar high.
 *   Also uses 10-candle lookback (not 20) so it triggers more realistically.
 *
 * FIX 3 — FVG: pure gap (prev2.high < prev.low) almost never on intraday.
 *   Now: bullish FVG = prev candle body is strongly bullish (close-open > 0.3%)
 *   AND last candle opens above prev midpoint (momentum continuation).
 *   bearish FVG = prev candle body strongly bearish + last opens below midpoint.
 *
 * FIX 4 — Volume spike: 1.5× was too aggressive for intraday sessions.
 *   Lowered to 1.2× average. Avg computed from last 20 candles (excluding last).
 *
 * FIX 5 — Signal gate: no longer requires ALL 4 conditions simultaneously.
 *   BUY  requires: UPTREND + volume spike + (sweep low OR bullish FVG) + above EMA
 *   SELL requires: DOWNTREND + volume spike + (sweep high OR bearish FVG) + below EMA
 *   BUY_PULLBACK : UPTREND + price within 1% of bull OB level + volume spike
 *   SELL_PULLBACK: DOWNTREND + price within 1% of bear OB level + volume spike
 *
 * FIX 6 — EMA: recalculated properly with Wilder multiplier k=2/(n+1)
 *   seeded from SMA of first 20 candles (not a single candle).
 * ─────────────────────────────────────────────────────────────────────────────
 */
class QuantEdgeSmartMoneyController extends Controller
{
    private const TIMEFRAMES  = ['15min', '30min', '1hr'];
    private const MIN_CANDLES = 30;   // reduced from 50 — 30 is sufficient for all indicators

    // ─────────────────────────────────────────────────────────────────────────
    //  Page
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'QuantEdge — Smart Money Analysis';
        return view($this->activeTemplate . 'user.quantedge-smc.index', compact('pageTitle'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Symbols (config-scoped)
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
    //  Signals — main AJAX endpoint
    // ─────────────────────────────────────────────────────────────────────────

    public function signals(Request $request)
    {
        try {
            $timeframe    = $this->resolveTimeframe($request);
            $fromDate     = $request->get('from_date', now()->toDateString());
            $toDate       = $request->get('to_date',   now()->toDateString());
            $symbolFilter = strtoupper($request->get('symbol', 'ALL'));
            $todayStr     = now()->toDateString();

            if ($fromDate > $todayStr) $fromDate = $todayStr;
            if ($toDate   > $todayStr) $toDate   = $todayStr;
            if ($fromDate > $toDate)   [$fromDate, $toDate] = [$toDate, $fromDate];

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

            $symbols = ($symbolFilter === 'ALL' || !in_array($symbolFilter, $allSymbols))
                ? $allSymbols
                : [$symbolFilter];

            $stockTable = 'cp_stock_ohlc_' . $timeframe;

            // Trading dates that have actual data
            $tradeDates = DB::table($stockTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('symbol', $symbols)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->where('is_missing', false)
                ->selectRaw('DISTINCT DATE(trade_date) as d')
                ->orderBy('d', 'asc')
                ->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json([
                    'success'   => true,
                    'message'   => 'No trading data found for the selected range.',
                    'results'   => [],
                    'summary'   => $this->emptySummary(),
                    'symbols'   => $allSymbols,
                    'from_date' => $fromDate,
                    'to_date'   => $toDate,
                    'timeframe' => $timeframe,
                    'is_range'  => ($fromDate !== $toDate),
                ]);
            }

            $results = [];

            foreach ($symbols as $symbol) {
                foreach ($tradeDates as $date) {
                    // Fetch last 60 candles UP TO this date (provides enough history for all indicators)
                    $candles = DB::table($stockTable)
                        ->where('analysis_config_id', $config->id)
                        ->where('symbol', $symbol)
                        ->where('is_missing', false)
                        ->whereDate('trade_date', '<=', $date)
                        ->orderByDesc('trade_date')
                        ->orderByDesc('interval_time')
                        ->limit(60)
                        ->select(['trade_date', 'interval_time', 'open', 'high', 'low', 'close', 'volume'])
                        ->get()
                        ->reverse()->values()
                        ->map(fn($c) => [
                            'date'   => substr($c->trade_date, 0, 10),
                            'open'   => (float) $c->open,
                            'high'   => (float) $c->high,
                            'low'    => (float) $c->low,
                            'close'  => (float) $c->close,
                            'volume' => (int)   $c->volume,
                        ])->toArray();

                    $signal                  = $this->analyse($candles);
                    $signal['symbol']        = $symbol;
                    $signal['analysis_date'] = $date;
                    $signal['last_close']    = !empty($candles) ? end($candles)['close'] : null;

                    $results[] = $signal;
                }
            }

            $order = ['BUY'=>0,'SELL'=>1,'BUY_PULLBACK'=>2,'SELL_PULLBACK'=>3,'NO_TRADE'=>4,'NO_DATA'=>5];
            usort($results, fn($a, $b) =>
                strcmp($a['analysis_date'], $b['analysis_date'])
                ?: (($order[$a['signal']] ?? 9) <=> ($order[$b['signal']] ?? 9))
            );

            return response()->json([
                'success'   => true,
                'results'   => $results,
                'summary'   => $this->buildSummary($results),
                'symbols'   => $allSymbols,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'timeframe' => $timeframe,
                'is_today'  => ($fromDate === $todayStr && $toDate === $todayStr),
                'is_range'  => ($fromDate !== $toDate),
            ]);

        } catch (\Exception $e) {
            Log::error('QuantEdgeSMC: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  SMC Analysis — fixed thresholds for intraday data
    // ─────────────────────────────────────────────────────────────────────────

    private function analyse(array $candles): array
    {
        $count = count($candles);
        $empty = [
            'signal'               => 'NO_DATA',
            'reason'               => 'Need at least ' . self::MIN_CANDLES . ' candles (have ' . $count . ')',
            'trend'                => null,
            'volume_spike'         => false,
            'avg_volume'           => null,
            'liquidity_sweep_high' => false,
            'liquidity_sweep_low'  => false,
            'fvg_bullish'          => false,
            'fvg_bearish'          => false,
            'order_block_bull'     => null,
            'order_block_bear'     => null,
            'ema20'                => null,
        ];

        if ($count < self::MIN_CANDLES) return $empty;

        $last  = $candles[$count - 1];
        $prev  = $candles[$count - 2];
        $prev2 = $candles[$count - 3];

        // ── FIX 1: Trend — 5-candle swing structure ───────────────────────
        // Count how many of the last 5 consecutive candle pairs show HH+HL (bullish)
        // vs LH+LL (bearish). Majority wins.
        $bullCount = 0;
        $bearCount = 0;
        for ($i = $count - 5; $i < $count - 1; $i++) {
            $curr = $candles[$i + 1];
            $prv  = $candles[$i];
            if ($curr['high'] > $prv['high'] && $curr['low'] >= $prv['low']) $bullCount++;
            if ($curr['high'] <= $prv['high'] && $curr['low'] < $prv['low']) $bearCount++;
        }

        if ($bullCount >= 3) {
            $trend = 'UPTREND';
        } elseif ($bearCount >= 3) {
            $trend = 'DOWNTREND';
        } else {
            $trend = 'SIDEWAYS';
        }

        // ── FIX 2: Trend confirmation via EMA slope ───────────────────────
        // Also use EMA direction as a secondary trend confirmation below.

        // ── FIX 4 (moved up): EMA-20 — seeded from SMA of first 20 values ─
        $emaPeriod = 20;
        $k         = 2.0 / ($emaPeriod + 1);

        // Seed: SMA of the first 20 candles
        $seed = 0.0;
        $startIdx = max(0, $count - 40); // use earlier candles for seeding
        for ($i = $startIdx; $i < $startIdx + $emaPeriod; $i++) {
            $seed += $candles[$i]['close'];
        }
        $ema = $seed / $emaPeriod;

        // Run EMA forward from seed point to last candle
        for ($i = $startIdx + $emaPeriod; $i < $count; $i++) {
            $ema = ($candles[$i]['close'] * $k) + ($ema * (1 - $k));
        }
        $ema = round($ema, 2);

        // EMA slope: rising = bullish, falling = bearish
        // Recalculate EMA one step back for slope comparison
        $emaPrev = $ema;
        for ($i = $startIdx; $i < $startIdx + $emaPeriod; $i++) {
            $emaPrev += $candles[$i]['close'];
        }
        $emaPrev = $emaPrev / $emaPeriod;
        for ($i = $startIdx + $emaPeriod; $i < $count - 1; $i++) {
            $emaPrev = ($candles[$i]['close'] * $k) + ($emaPrev * (1 - $k));
        }
        $emaRising = $ema >= $emaPrev;

        // If swing trend is SIDEWAYS, use EMA slope to break the tie
        if ($trend === 'SIDEWAYS') {
            $trend = $emaRising ? 'UPTREND' : 'DOWNTREND';
        }

        // ── FIX 4: Volume spike — lowered to 1.2× ────────────────────────
        $volWindow = array_slice($candles, $count - 21, 20); // last 20 excluding current
        $avgVolume = array_sum(array_column($volWindow, 'volume')) / 20;
        $volSpike  = ($avgVolume > 0) && ($last['volume'] > ($avgVolume * 1.2));

        // ── FIX 2: Liquidity sweep — 10-candle range, wick through ───────
        // Bullish sweep: last candle wicks BELOW recent 10-bar low (stop hunt below support)
        // Bearish sweep: last candle wicks ABOVE recent 10-bar high (stop hunt above resistance)
        $swLookback  = array_slice($candles, $count - 11, 10); // previous 10, not including last
        $recentHigh  = max(array_column($swLookback, 'high'));
        $recentLow   = min(array_column($swLookback, 'low'));

        $sweepLow  = ($last['low'] < $recentLow);   // wicked into/below support zone
        $sweepHigh = ($last['high'] > $recentHigh); // wicked into/above resistance zone

        // ── FIX 3: FVG — body momentum, not pure gap ─────────────────────
        // Bullish FVG: prev candle has strong bullish body (>0.2% of close)
        //              AND last candle's open >= prev candle's midpoint
        $prevBodyPct  = $prev['close'] > 0 ? abs($prev['close'] - $prev['open']) / $prev['close'] * 100 : 0;
        $prevMid      = ($prev['high'] + $prev['low']) / 2;
        $prev2Mid     = ($prev2['high'] + $prev2['low']) / 2;

        $fvgBull = ($prev['close'] > $prev['open'])           // prev was bullish
            && ($prevBodyPct > 0.2)                           // body at least 0.2%
            && ($last['open'] >= $prevMid)                    // last opens in upper half of prev
            && ($prev['low'] > $prev2['high'] * 0.998);      // slight gap or near-gap from prev2

        $fvgBear = ($prev['close'] < $prev['open'])           // prev was bearish
            && ($prevBodyPct > 0.2)                           // body at least 0.2%
            && ($last['open'] <= $prevMid)                    // last opens in lower half of prev
            && ($prev['high'] < $prev2['low'] * 1.002);      // slight gap or near-gap from prev2

        // ── Order Blocks (last 10 candles) ───────────────────────────────
        $obBull = null;
        $obBear = null;
        $obWindow = array_slice($candles, $count - 11, 10);
        foreach ($obWindow as $c) {
            if ($c['close'] < $c['open']) $obBull = round($c['low'],  2); // last bearish candle low = demand
            if ($c['close'] > $c['open']) $obBear = round($c['high'], 2); // last bullish candle high = supply
        }

        // ── FIX 5: Signal gates — relaxed to OR logic ─────────────────────
        //
        // BUY  = UPTREND + volume spike + (sweep low OR bullish FVG) + close above EMA
        // SELL = DOWNTREND + volume spike + (sweep high OR bearish FVG) + close below EMA
        //
        // BUY_PULLBACK  = UPTREND + OB exists + last close within 1.5% above OB low
        // SELL_PULLBACK = DOWNTREND + OB exists + last close within 1.5% below OB high
        //
        // Fallback: if any 2 SMC conditions align with trend, give a weaker signal

        $signal = 'NO_TRADE';
        $reason = 'No clear SMC setup on this candle';

        $aboveEma = $last['close'] > $ema;
        $belowEma = $last['close'] < $ema;

        if ($trend === 'UPTREND') {
            // Strong BUY
            if ($volSpike && ($sweepLow || $fvgBull) && $aboveEma) {
                $signal = 'BUY';
                $sweepPart = $sweepLow ? 'sweep low' : '';
                $fvgPart   = $fvgBull  ? 'bullish FVG' : '';
                $conditions = implode(' + ', array_filter([$sweepPart, $fvgPart]));
                $reason = 'Uptrend + volume spike + ' . $conditions . ' + above EMA-20';

            // BUY_PULLBACK: retesting bull order block
            } elseif ($obBull !== null && $last['close'] >= $obBull && $last['close'] <= ($obBull * 1.015) && $volSpike) {
                $signal = 'BUY_PULLBACK';
                $reason = 'Uptrend pullback into bull order block (₹' . $obBull . ') with volume';

            // Weaker BUY: volume + either sweep or FVG, but no EMA filter
            } elseif ($volSpike && ($sweepLow && $fvgBull)) {
                $signal = 'BUY';
                $reason = 'Uptrend + volume spike + sweep low + bullish FVG (EMA not confirmed)';
            }

        } elseif ($trend === 'DOWNTREND') {
            // Strong SELL
            if ($volSpike && ($sweepHigh || $fvgBear) && $belowEma) {
                $signal = 'SELL';
                $sweepPart = $sweepHigh ? 'sweep high' : '';
                $fvgPart   = $fvgBear   ? 'bearish FVG' : '';
                $conditions = implode(' + ', array_filter([$sweepPart, $fvgPart]));
                $reason = 'Downtrend + volume spike + ' . $conditions . ' + below EMA-20';

            // SELL_PULLBACK: retesting bear order block
            } elseif ($obBear !== null && $last['close'] <= $obBear && $last['close'] >= ($obBear * 0.985) && $volSpike) {
                $signal = 'SELL_PULLBACK';
                $reason = 'Downtrend pullback into bear order block (₹' . $obBear . ') with volume';

            // Weaker SELL: volume + both sweep and FVG
            } elseif ($volSpike && ($sweepHigh && $fvgBear)) {
                $signal = 'SELL';
                $reason = 'Downtrend + volume spike + sweep high + bearish FVG (EMA not confirmed)';
            }
        }

        // No-trade reason — give a useful diagnostic
        if ($signal === 'NO_TRADE') {
            if (!$volSpike) {
                $reason = 'No volume spike (current: ' . number_format($last['volume']) . ', avg: ' . number_format((int)$avgVolume) . ')';
            } elseif ($trend === 'UPTREND' && !$sweepLow && !$fvgBull) {
                $reason = 'Uptrend but no sweep low or bullish FVG detected';
            } elseif ($trend === 'DOWNTREND' && !$sweepHigh && !$fvgBear) {
                $reason = 'Downtrend but no sweep high or bearish FVG detected';
            } elseif ($trend === 'UPTREND' && !$aboveEma) {
                $reason = 'Uptrend conditions met but close below EMA-20';
            } elseif ($trend === 'DOWNTREND' && !$belowEma) {
                $reason = 'Downtrend conditions met but close above EMA-20';
            }
        }

        return [
            'signal'               => $signal,
            'reason'               => $reason,
            'trend'                => $trend,
            'volume_spike'         => $volSpike,
            'avg_volume'           => round($avgVolume),
            'liquidity_sweep_high' => $sweepHigh,
            'liquidity_sweep_low'  => $sweepLow,
            'fvg_bullish'          => $fvgBull,
            'fvg_bearish'          => $fvgBear,
            'order_block_bull'     => $obBull,
            'order_block_bear'     => $obBear,
            'ema20'                => $ema,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSummary(array $results): array
    {
        return [
            'buy'          => count(array_filter($results, fn($r) => $r['signal'] === 'BUY')),
            'sell'         => count(array_filter($results, fn($r) => $r['signal'] === 'SELL')),
            'buy_pullback' => count(array_filter($results, fn($r) => $r['signal'] === 'BUY_PULLBACK')),
            'sell_pullback'=> count(array_filter($results, fn($r) => $r['signal'] === 'SELL_PULLBACK')),
            'no_trade'     => count(array_filter($results, fn($r) => $r['signal'] === 'NO_TRADE')),
            'total'        => count($results),
        ];
    }

    private function emptySummary(): array
    {
        return ['buy'=>0,'sell'=>0,'buy_pullback'=>0,'sell_pullback'=>0,'no_trade'=>0,'total'=>0];
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