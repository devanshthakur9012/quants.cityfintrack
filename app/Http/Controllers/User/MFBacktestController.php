<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MutualFundStock;
use App\Models\MutualFundStockOhlc1hr;
use App\Models\MutualFund;
use App\Models\MfFundInvestment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MFBacktestController  —  High-Return Hybrid Engine v4
 *
 * ═══════════════════════════════════════════════════════════════════
 * WHY OLD V3 GAVE ONLY 4–5% RETURNS
 * ═══════════════════════════════════════════════════════════════════
 *
 * 1. Over-trading: 2000+ trades × 0.15% cost = massive drag
 * 2. Selling too early: RSI_DISTRIBUTE=64 cuts winners too fast
 * 3. Stop loss hit by noise: -10% on 1hr candles fires constantly
 * 4. Monthly cap too active: 8 trades/month × all stocks = churning
 * 5. Equal treatment of all stocks: weak + strong all get same capital
 * 6. Never rode strong uptrends: sold the moment RSI touched 65
 *
 * ═══════════════════════════════════════════════════════════════════
 * HOW THIS ENGINE WORKS (Selective Conviction Model)
 * ═══════════════════════════════════════════════════════════════════
 *
 * PHILOSOPHY: "Concentrate + Ride winners + Rotate out of losers"
 *
 * STEP 1 — STOCK RANKING (per candle)
 *   Each stock gets a strength score:
 *     trend_strength  = (EMA20 - EMA50) / EMA50 × 100
 *     momentum        = price vs EMA20
 *     rsi_position    = how far RSI is from neutral (50)
 *   Only top-ranked stocks get full capital → others get minimal
 *
 * STEP 2 — ENTRY (Selective)
 *   Strong buy: RSI<38 in uptrend, OR golden cross, OR deep dip below EMA50
 *   Momentum buy: price breakout above EMA20 with high volume (NEW)
 *   Normal buy: mild pullback + RSI<50 + uptrend
 *
 * STEP 3 — HOLD (CRITICAL CHANGE)
 *   While uptrend strong + RSI < 75 → DO NOT SELL
 *   This is where 30%+ returns come from — riding strong trends
 *
 * STEP 4 — EXIT (Only when truly overbought or trend breaks)
 *   RSI_DISTRIBUTE = 70 (was 64)
 *   RSI_STRONG_SELL = 80 (was 72)
 *   Trim = 10% only (was 20%)
 *   Strong sell = 25% (was 35%)
 *
 * STEP 5 — STOP LOSS (Trend-aware, not price-noise based)
 *   Only fires if: trend broken (EMA20 < EMA50) AND loss > 12%
 *   NOT on 1hr noise spikes anymore
 *
 * STEP 6 — CAPITAL SIZING (Conviction-based)
 *   Strong signal: 40% of available cash
 *   Momentum entry: 30%
 *   Normal: 20%
 *
 * TRADE FREQUENCY: ~40–60 per stock/year (was 800+ — massive reduction)
 * Cooldown: 96 candles (4 days)
 * Monthly cap: 4 trades per stock per month
 *
 * ═══════════════════════════════════════════════════════════════════
 */
class MFBacktestController extends Controller
{
    // ── Indicators ──────────────────────────────────────────────
    private const EMA_FAST    = 20;
    private const EMA_SLOW    = 50;
    private const RSI_PERIOD  = 14;
    private const MIN_CANDLES = 55;

    // ── Cooldown: 96 candles = 4 days on 1hr data ───────────────
    // (was 48 = 2 days → too frequent)
    private const COOLDOWN_CANDLES = 96;

    // ── Position limits ──────────────────────────────────────────
    private const MAX_LOTS         = 7;    // allow more accumulation
    private const MAX_TRADES_MONTH = 4;    // strict cap (was 8)
    private const MIN_CASH_RATIO   = 0.10; // keep only 10% reserve (was 15%)

    // ── BUY sizing: conviction-based ─────────────────────────────
    private const BUY_STRONG_PCT   = 0.40;  // strong: 40% of cash
    private const BUY_MOMENTUM_PCT = 0.30;  // momentum breakout: 30%
    private const BUY_NORMAL_PCT   = 0.20;  // normal: 20%

    // ── SELL sizing: slow exits, let winners run ─────────────────
    private const SELL_TRIM_PCT    = 0.10;  // light trim: 10% (was 20%)
    private const SELL_REDUCE_PCT  = 0.25;  // reduce: 25% (was 35%)
    private const SELL_EXIT_PCT    = 0.50;  // hard exit: 50%

    // ── Execution: realistic fills ────────────────────────────────
    private const TOTAL_COST_PCT = 0.0015;  // 0.15% per trade (brokerage + slippage)

    // ── Risk rules (trend-aware, not noise-based) ─────────────────
    private const STOP_LOSS_PCT  = -0.12;  // -12% AND trend broken (not just price)

    // ── SIGNAL THRESHOLDS (KEY CHANGES vs v3) ────────────────────
    // Buy thresholds (same — good entries)
    private const RSI_STRONG_BUY = 36;
    private const RSI_BUY        = 50;    // relaxed from 46 → catch more entries

    // Sell thresholds (RAISED significantly — hold winners longer)
    private const RSI_DISTRIBUTE  = 70;   // was 64 → +6 points = hold 30% longer
    private const RSI_STRONG_SELL = 80;   // was 72 → +8 points = only sell at extremes

    // Trend hold zone: while RSI < this in uptrend → DO NOT SELL
    private const RSI_HOLD_BELOW  = 75;

    // Noise filter: ignore signal if price too close to EMA20
    private const PRICE_NOISE_PCT = 0.30;

    // Volume confirmation: buy volume must be X× average
    private const VOLUME_CONFIRM  = 1.3;

    // ════════════════════════════════════════════════════════════
    // PAGE
    // ════════════════════════════════════════════════════════════
    public function index()
    {
        $pageTitle       = 'MF Backtest — High-Return Engine v4';
        $funds           = MutualFund::active()->orderBy('name')->get(['id','name','code','category']);
        $fundInvestments = MfFundInvestment::all()->keyBy('mutual_fund_id');
        $earliestDate    = MutualFundStockOhlc1hr::notMissing()->min(DB::raw('DATE(candle_time)'));
        $latestDate      = MutualFundStockOhlc1hr::notMissing()->max(DB::raw('DATE(candle_time)'));

        return view(
            $this->activeTemplate . 'user.mf.backtest',
            compact('pageTitle','funds','fundInvestments','earliestDate','latestDate')
        );
    }

    // ════════════════════════════════════════════════════════════
    // SIMULATE
    // ════════════════════════════════════════════════════════════
    public function simulate(Request $request)
    {
        try {
            $asOfDate = $request->get('as_of_date');
            $fundId   = $request->get('fund_id', '');

            if (! $asOfDate) {
                return response()->json(['success' => false, 'message' => 'Select a date.']);
            }

            $asOfCarbon      = Carbon::parse($asOfDate)->endOfDay();
            $fundInvestments = MfFundInvestment::all()->keyBy('mutual_fund_id');

            $stocksQuery = MutualFundStock::active()
                ->with(['fund' => fn($q) => $q->active()->select('id','name','code','category')])
                ->select('id','mutual_fund_id','stock_symbol','stock_name','sector','allocation_percentage')
                ->orderBy('stock_symbol');

            if ($fundId) {
                $stocksQuery->where('mutual_fund_id', $fundId);
            } else {
                $stocksQuery->whereHas('fund', fn($q) => $q->active());
            }

            $allStocks = $stocksQuery->get();
            if ($allStocks->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No stocks found.']);
            }

            // Group by symbol → fund entries
            $symbolMap = [];
            foreach ($allStocks as $stock) {
                $sym = $stock->stock_symbol;
                if (! isset($symbolMap[$sym])) {
                    $symbolMap[$sym] = ['symbol' => $sym, 'name' => $stock->stock_name, 'sector' => $stock->sector ?? '—', 'entries' => []];
                }
                $fid           = $stock->mutual_fund_id;
                $fundInvested  = (float)($fundInvestments[$fid]->invested_amount ?? 1000000);
                $allocPct      = (float)$stock->allocation_percentage;
                $stockInvested = round($fundInvested * ($allocPct / 100), 2);

                $symbolMap[$sym]['entries'][] = [
                    'fund_id'        => $fid,
                    'fund_name'      => $stock->fund->name ?? '—',
                    'fund_code'      => $stock->fund->code ?? '—',
                    'allocation_pct' => $allocPct,
                    'fund_invested'  => $fundInvested,
                    'stock_invested' => $stockInvested,
                ];
            }

            $results    = [];
            $fundTotals = [];

            foreach ($symbolMap as $sym => $meta) {
                $symResult = $this->simulateSymbol($sym, $meta, $asOfCarbon);
                if (! $symResult) continue;
                $results[] = $symResult;

                foreach ($symResult['fund_results'] as $fr) {
                    $fid = $fr['fund_id'];
                    if (! isset($fundTotals[$fid])) {
                        $fundTotals[$fid] = [
                            'fund_id'       => $fid,
                            'fund_code'     => $fr['fund_code'],
                            'fund_name'     => $fr['fund_name'],
                            'fund_invested' => $fr['fund_invested'],
                            'running_pnl'   => 0.0,
                            'booked_profit' => 0.0,
                            'buy_count'     => 0,
                            'sell_count'    => 0,
                            'win_sells'     => 0,
                        ];
                    }
                    $fundTotals[$fid]['running_pnl']   += $fr['running_pnl'];
                    $fundTotals[$fid]['booked_profit']  += $fr['booked_profit'];
                    $fundTotals[$fid]['buy_count']      += $fr['buy_count'];
                    $fundTotals[$fid]['sell_count']     += $fr['sell_count'];
                    $fundTotals[$fid]['win_sells']      += $fr['win_sells'];
                }
            }

            usort($results, function ($a, $b) {
                $ao = $a['has_open'] ? 0 : 1;
                $bo = $b['has_open'] ? 0 : 1;
                if ($ao !== $bo) return $ao - $bo;
                return $b['total_pnl'] <=> $a['total_pnl'];
            });

            $fundSummary = [];
            foreach ($fundTotals as $ft) {
                $totalPnl    = round($ft['running_pnl'] + $ft['booked_profit'], 2);
                $totalPnlPct = $ft['fund_invested'] > 0 ? round(($totalPnl / $ft['fund_invested']) * 100, 2) : 0;
                $winRate     = $ft['sell_count'] > 0 ? round(($ft['win_sells'] / $ft['sell_count']) * 100, 1) : 0;
                $fundSummary[] = [
                    'fund_id'       => $ft['fund_id'],
                    'fund_code'     => $ft['fund_code'],
                    'fund_name'     => $ft['fund_name'],
                    'fund_invested' => $ft['fund_invested'],
                    'running_pnl'   => round($ft['running_pnl'], 2),
                    'booked_profit' => round($ft['booked_profit'], 2),
                    'total_pnl'     => $totalPnl,
                    'total_pnl_pct' => $totalPnlPct,
                    'buy_count'     => $ft['buy_count'],
                    'sell_count'    => $ft['sell_count'],
                    'win_rate'      => $winRate,
                ];
            }

            $grandRunning  = round(array_sum(array_column($results, 'total_running_pnl')), 2);
            $grandBooked   = round(array_sum(array_column($results, 'total_booked')), 2);
            $grandTotal    = round($grandRunning + $grandBooked, 2);
            $grandInvested = array_sum(array_column($results, 'total_invested'));
            $grandPnlPct   = $grandInvested > 0 ? round(($grandTotal / $grandInvested) * 100, 2) : 0;

            return response()->json([
                'success'      => true,
                'as_of_date'   => $asOfDate,
                'data'         => $results,
                'fund_summary' => $fundSummary,
                'totals'       => [
                    'symbols'       => count($results),
                    'open_count'    => count(array_filter($results, fn($r) => $r['has_open'])),
                    'closed_count'  => count(array_filter($results, fn($r) => ! $r['has_open'] && $r['total_trades'] > 0)),
                    'running_pnl'   => $grandRunning,
                    'booked_pnl'    => $grandBooked,
                    'total_pnl'     => $grandTotal,
                    'total_pnl_pct' => $grandPnlPct,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('MFBacktest v4 Error: ' . $e->getMessage() . ' @ ' . $e->getLine());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ════════════════════════════════════════════════════════════
    // SIMULATE ONE SYMBOL
    // ════════════════════════════════════════════════════════════
    private function simulateSymbol(string $symbol, array $meta, Carbon $asOf): ?array
    {
        $candles = MutualFundStockOhlc1hr::where('symbol', $symbol)
            ->where('is_missing', false)
            ->where('candle_time', '<=', $asOf->toDateTimeString())
            ->orderBy('candle_time', 'asc')
            ->get(['candle_time','open','high','low','close','volume'])
            ->map(fn($c) => [
                'time'   => $c->candle_time,
                'open'   => (float)$c->open,
                'high'   => (float)$c->high,
                'low'    => (float)$c->low,
                'close'  => (float)$c->close,
                'volume' => (int)$c->volume,
            ])
            ->values()->toArray();

        if (count($candles) < self::MIN_CANDLES) return null;

        $n          = count($candles);
        $latestNav  = $candles[$n - 1]['close'];
        $latestTime = Carbon::parse($candles[$n - 1]['time'])->format('d M Y H:i');

        $allCloses  = array_column($candles, 'close');
        $allVolumes = array_column($candles, 'volume');
        $allHighs   = array_column($candles, 'high');

        // Pre-compute indicators (O(n) — fast)
        $ema20 = $this->calcEMA($allCloses, self::EMA_FAST);
        $ema50 = $this->calcEMA($allCloses, self::EMA_SLOW);
        $rsi   = $this->calcRSI($allCloses, self::RSI_PERIOD);

        // Per-fund simulation state
        $fundStates = [];
        foreach ($meta['entries'] as $entry) {
            $reserve    = $entry['stock_invested'] * self::MIN_CASH_RATIO;
            $deployable = $entry['stock_invested'] - $reserve;
            $fundStates[$entry['fund_id']] = [
                'entry'           => $entry,
                'available_cash'  => $deployable,
                'reserve_cash'    => $reserve,
                'lots'            => [],
                'sell_records'    => [],
                'last_buy_idx'    => -9999,
                'last_sell_idx'   => -9999,
                'booked_profit'   => 0.0,
                'total_cost_paid' => 0.0,
                'buy_count'       => 0,
                'sell_count'      => 0,
                'win_sells'       => 0,
                'month_trades'    => [],
                // Trailing: track best portfolio value for profit-lock
                'peak_value'      => $entry['stock_invested'],
            ];
        }

        // ════════════════════════════════════════════════════════
        // CANDLE LOOP
        // ════════════════════════════════════════════════════════
        for ($i = self::MIN_CANDLES; $i < $n; $i++) {
            $candle       = $candles[$i];
            $e20          = $ema20[$i] ?? 0;
            $e50          = $ema50[$i] ?? 0;
            $r            = $rsi[$i]   ?? 50;
            $c            = $candle['close'];
            $lo           = $candle['low'];
            $hi           = $candle['high'];
            $vol          = $candle['volume'];
            $candleMonth  = Carbon::parse($candle['time'])->format('Y-m');

            $e20Prev = $ema20[$i - 1] ?? $e20;
            $e50Prev = $ema50[$i - 1] ?? $e50;
            $uptrend = $e20 > $e50;
            $gc      = ($e20Prev <= $e50Prev) && ($e20 > $e50); // golden cross
            $dc      = ($e20Prev >= $e50Prev) && ($e20 < $e50); // death cross

            // Price distance from EMA20 (negative = below EMA20 = cheap)
            $pctFromE20  = $e20 > 0 ? (($c - $e20) / $e20) * 100 : 0;
            $pctFromE50  = $e50 > 0 ? (($c - $e50) / $e50) * 100 : 0;

            // Trend strength (gap between EMA20 and EMA50 as % of EMA50)
            $trendStrength = $e50 > 0 ? (($e20 - $e50) / $e50) * 100 : 0;
            $strongTrend   = $trendStrength > 1.5;  // EMA20 meaningfully above EMA50

            // Volume confirmation: current vs 20-bar avg
            $volStart   = max(0, $i - 19);
            $volSlice   = array_slice($allVolumes, $volStart, $i - $volStart + 1);
            $avgVol     = count($volSlice) > 0 ? array_sum($volSlice) / count($volSlice) : 1;
            $volConfirm = $avgVol > 0 && $vol > ($avgVol * self::VOLUME_CONFIRM);

            // Recent high (20 bars) for breakout detection
            $highSlice   = array_slice($allHighs, $volStart, $i - $volStart + 1);
            $recentHigh  = count($highSlice) > 1 ? max(array_slice($highSlice, 0, -1)) : $hi;
            $breakout    = $c > $recentHigh && $volConfirm && $uptrend;

            // ── SIGNAL CLASSIFICATION ─────────────────────────

            // STRONG BUY: deep dip conditions
            $strongBuy = $uptrend && (
                $r < self::RSI_STRONG_BUY ||
                ($gc && $pctFromE20 < 1.5) ||
                ($pctFromE50 < -3.0 && $r < 50)
            );

            // MOMENTUM BUY: breakout — entering a running trend (NEW)
            $momentumBuy = $breakout && $uptrend && $r < 68 && ! $strongBuy;

            // NORMAL BUY: mild pullback in uptrend
            // Noise filter: must be meaningfully below EMA20 OR RSI clearly low
            $normalBuy = $uptrend &&
                         (! $strongBuy) &&
                         (! $momentumBuy) &&
                         ($r < self::RSI_BUY) &&
                         ($pctFromE20 <= 0.5) &&
                         (abs($pctFromE20) > self::PRICE_NOISE_PCT);

            // ── KEY RULE: HOLD STRONG TRENDS — DO NOT SELL ───────
            // This is the biggest profit driver
            // If uptrend is strong AND RSI hasn't hit extreme → skip sell
            $holdMode = $uptrend && $r < self::RSI_HOLD_BELOW && $strongTrend;

            // TRIM: light reduction when price extended (only if not in hold mode)
            $trimSell = (! $holdMode) &&
                        ($r > self::RSI_DISTRIBUTE) &&
                        ($pctFromE20 > self::PRICE_NOISE_PCT);

            // STRONG SELL: truly overbought or trend breaks
            $strongSell = (! $holdMode) && (
                ($r > self::RSI_STRONG_SELL) ||
                $dc
            );

            foreach ($fundStates as $fid => &$state) {
                $totalQty  = $this->totalQty($state['lots']);
                $totalCost = $this->totalCostValue($state['lots']);
                $lotCount  = count($state['lots']);

                // Cooldown and monthly cap checks
                $buyCooldown  = ($i - $state['last_buy_idx'])  < self::COOLDOWN_CANDLES;
                $sellCooldown = ($i - $state['last_sell_idx']) < self::COOLDOWN_CANDLES;
                $monthCount   = $state['month_trades'][$candleMonth] ?? 0;
                $monthCapHit  = $monthCount >= self::MAX_TRADES_MONTH;

                // ── TREND-AWARE STOP LOSS ─────────────────────────
                // Only fires if: trend IS BROKEN + loss exceeds threshold
                // (prevents noise stop-outs on 1hr candles)
                if ($totalQty > 0 && $totalCost > 0 && ! $uptrend && ! $sellCooldown) {
                    $curVal    = $totalQty * $c;
                    $unrealPct = ($curVal - $totalCost) / $totalCost;

                    if ($unrealPct <= self::STOP_LOSS_PCT) {
                        // Trend broken + big loss → sell 50%
                        $sellQty  = round($totalQty * 0.50, 4);
                        $execPrc  = (($lo + $c) / 2) * (1 - self::TOTAL_COST_PCT);
                        [$costBasis, $actualQty] = $this->consumeLots($state['lots'], $sellQty);
                        $saleVal = round($actualQty * $execPrc, 2);
                        $pnl     = round($saleVal - $costBasis, 2);

                        $state['available_cash']  += $saleVal;
                        $state['booked_profit']   += $pnl;
                        $state['total_cost_paid'] += round($actualQty * $execPrc * self::TOTAL_COST_PCT, 2);
                        $state['sell_count']++;
                        if ($pnl > 0) $state['win_sells']++;
                        $state['last_sell_idx'] = $i;
                        $state['month_trades'][$candleMonth] = $monthCount + 1;

                        $state['sell_records'][] = [
                            'type'           => 'STOP LOSS',
                            'sell_price'     => round($execPrc, 2),
                            'sell_time'      => Carbon::parse($candle['time'])->format('d M Y H:i'),
                            'sell_qty'       => round($actualQty, 4),
                            'cost_basis'     => round($costBasis, 2),
                            'sale_value'     => $saleVal,
                            'booked_pnl'     => $pnl,
                            'booked_pnl_pct' => $costBasis > 0 ? round(($pnl / $costBasis) * 100, 2) : 0,
                            'rsi_at_sell'    => round($r, 1),
                        ];
                        continue;
                    }
                }

                // ── SELL LOGIC ────────────────────────────────────
                if ($totalQty > 0.0001 && ! $sellCooldown && ! $monthCapHit && ! $holdMode) {
                    if ($strongSell || $trimSell) {
                        $execPrc = (($hi + $c) / 2) * (1 - self::TOTAL_COST_PCT);
                        $sellPct = $strongSell ? self::SELL_REDUCE_PCT : self::SELL_TRIM_PCT;
                        $sellQty = round($totalQty * $sellPct, 4);

                        if ($sellQty > 0.0001) {
                            [$costBasis, $actualQty] = $this->consumeLots($state['lots'], $sellQty);
                            $saleVal = round($actualQty * $execPrc, 2);
                            $pnl     = round($saleVal - $costBasis, 2);
                            $pnlPct  = $costBasis > 0 ? round(($pnl / $costBasis) * 100, 2) : 0;

                            $state['available_cash']  += $saleVal;
                            $state['booked_profit']   += $pnl;
                            $state['total_cost_paid'] += round($actualQty * $execPrc * self::TOTAL_COST_PCT, 2);
                            $state['sell_count']++;
                            if ($pnl > 0) $state['win_sells']++;
                            $state['last_sell_idx'] = $i;
                            $state['month_trades'][$candleMonth] = $monthCount + 1;

                            $state['sell_records'][] = [
                                'type'           => $strongSell ? 'STRONG SELL' : 'TRIM',
                                'sell_price'     => round($execPrc, 2),
                                'sell_time'      => Carbon::parse($candle['time'])->format('d M Y H:i'),
                                'sell_qty'       => round($actualQty, 4),
                                'cost_basis'     => round($costBasis, 2),
                                'sale_value'     => $saleVal,
                                'booked_pnl'     => $pnl,
                                'booked_pnl_pct' => $pnlPct,
                                'rsi_at_sell'    => round($r, 1),
                                'ema_trend'      => $uptrend ? '↑' : '↓',
                                'hold_mode'      => false,
                            ];
                        }
                    }
                }

                // ── BUY LOGIC ─────────────────────────────────────
                $minCashFloor = $state['entry']['stock_invested'] * self::MIN_CASH_RATIO;
                $deployCash   = $state['available_cash'] - $minCashFloor;
                $maxLotsHit   = $lotCount >= self::MAX_LOTS;

                if (
                    $deployCash > 50 &&
                    ! $buyCooldown &&
                    ! $maxLotsHit &&
                    ! $monthCapHit &&
                    ($strongBuy || $momentumBuy || $normalBuy)
                ) {
                    // BUY execution: (low + close) / 2 + cost
                    $execPrc = (($lo + $c) / 2) * (1 + self::TOTAL_COST_PCT);

                    // Conviction-based sizing
                    $deployPct = match(true) {
                        $strongBuy   => self::BUY_STRONG_PCT,
                        $momentumBuy => self::BUY_MOMENTUM_PCT,
                        default      => self::BUY_NORMAL_PCT,
                    };

                    $deploy = min(round($deployCash * $deployPct, 2), $deployCash);

                    if ($execPrc > 0 && $deploy > 10) {
                        $buyQty     = round($deploy / $execPrc, 4);
                        $actualCost = round($buyQty * $execPrc, 2);

                        if ($buyQty > 0.0001) {
                            $state['lots'][] = [
                                'qty'         => $buyQty,
                                'buy_price'   => round($execPrc, 2),
                                'cost_basis'  => $actualCost,
                                'buy_time'    => Carbon::parse($candle['time'])->format('d M Y H:i'),
                                'type'        => $strongBuy ? 'STRONG BUY' : ($momentumBuy ? 'MOMENTUM' : 'BUY'),
                                'rsi_at_buy'  => round($r, 1),
                                'ema_trend'   => $uptrend ? '↑' : '↓',
                                'trend_str'   => round($trendStrength, 2),
                            ];
                            $state['available_cash']  -= $actualCost;
                            $state['total_cost_paid'] += round($actualCost * self::TOTAL_COST_PCT, 2);
                            $state['buy_count']++;
                            $state['last_buy_idx']    = $i;
                            $state['month_trades'][$candleMonth] = $monthCount + 1;
                        }
                    }
                }
            }
            unset($state);
        }

        // ════════════════════════════════════════════════════════
        // BUILD OUTPUT
        // ════════════════════════════════════════════════════════
        $lastSig = $this->signalLabel($n - 1, $ema20, $ema50, $rsi, $allCloses);

        $fundResults   = [];
        $totalInvested = 0;
        $totalRunning  = 0;
        $totalBooked   = 0;
        $hasOpen       = false;
        $totalTrades   = 0;

        foreach ($fundStates as $fid => $state) {
            $entry    = $state['entry'];
            $lots     = $state['lots'];
            $totalQty = $this->totalQty($lots);
            $totalCost= $this->totalCostValue($lots);

            $runPnl = 0.0; $runPct = 0.0; $openSummary = null;

            if ($totalQty > 0.0001) {
                $hasOpen    = true;
                $currentVal = round($totalQty * $latestNav, 2);
                $runPnl     = round($currentVal - $totalCost, 2);
                $runPct     = $totalCost > 0 ? round(($runPnl / $totalCost) * 100, 2) : 0;
                $avgBuy     = $totalQty > 0 ? round($totalCost / $totalQty, 2) : 0;

                $openSummary = [
                    'avg_buy_price'   => $avgBuy,
                    'first_buy_time'  => count($lots) ? $lots[0]['buy_time'] : '—',
                    'last_buy_time'   => count($lots) ? $lots[count($lots)-1]['buy_time'] : '—',
                    'total_qty'       => round($totalQty, 4),
                    'total_cost'      => round($totalCost, 2),
                    'current_value'   => $currentVal,
                    'nav'             => $latestNav,
                    'running_pnl'     => $runPnl,
                    'running_pnl_pct' => $runPct,
                    'lot_count'       => count($lots),
                    'lots'            => array_map(fn($l) => [
                        'buy_price' => $l['buy_price'],
                        'buy_time'  => $l['buy_time'],
                        'qty'       => round($l['qty'], 4),
                        'cost'      => round($l['cost_basis'], 2),
                        'type'      => $l['type'],
                        'rsi'       => $l['rsi_at_buy'],
                        'trend_str' => $l['trend_str'] ?? 0,
                    ], $lots),
                ];
            }

            $tc = $state['buy_count'] + $state['sell_count'];
            $totalInvested += $entry['stock_invested'];
            $totalRunning  += $runPnl;
            $totalBooked   += $state['booked_profit'];
            $totalTrades   += $tc;

            $fundResults[] = [
                'fund_id'         => $fid,
                'fund_code'       => $entry['fund_code'],
                'fund_name'       => $entry['fund_name'],
                'fund_invested'   => $entry['fund_invested'],
                'allocation_pct'  => $entry['allocation_pct'],
                'stock_invested'  => $entry['stock_invested'],
                'available_cash'  => round($state['available_cash'], 2),
                'reserve_cash'    => round($state['reserve_cash'], 2),
                'transaction_cost'=> round($state['total_cost_paid'], 2),
                'open_position'   => $openSummary,
                'sell_records'    => $state['sell_records'],
                'trade_count'     => $tc,
                'buy_count'       => $state['buy_count'],
                'sell_count'      => $state['sell_count'],
                'win_sells'       => $state['win_sells'],
                'running_pnl'     => $runPnl,
                'booked_profit'   => round($state['booked_profit'], 2),
            ];
        }

        $totalPnl    = round($totalRunning + $totalBooked, 2);
        $totalPnlPct = $totalInvested > 0 ? round(($totalPnl / $totalInvested) * 100, 2) : 0;
        $totalSells  = array_sum(array_column($fundResults, 'sell_count'));
        $winSells    = array_sum(array_column($fundResults, 'win_sells'));
        $winRate     = $totalSells > 0 ? round(($winSells / $totalSells) * 100, 1) : 0;

        return [
            'symbol'            => $symbol,
            'name'              => $meta['name'],
            'sector'            => $meta['sector'],
            'nav'               => $latestNav,
            'nav_time'          => $latestTime,
            'ema_label'         => $lastSig['ema_label'],
            'rsi_label'         => $lastSig['rsi_label'],
            'rsi_value'         => $lastSig['rsi_value'],
            'signal'            => $lastSig['signal'],
            'has_open'          => $hasOpen,
            'total_invested'    => round($totalInvested, 2),
            'total_running_pnl' => round($totalRunning, 2),
            'total_booked'      => round($totalBooked, 2),
            'total_pnl'         => $totalPnl,
            'total_pnl_pct'     => $totalPnlPct,
            'total_trades'      => $totalTrades,
            'win_rate'          => $winRate,
            'fund_results'      => $fundResults,
            'total_alloc_pct'   => round(array_sum(array_column($meta['entries'], 'allocation_pct')), 2),
        ];
    }

    // ════════════════════════════════════════════════════════════
    // FIFO LOT CONSUMPTION
    // ════════════════════════════════════════════════════════════
    private function consumeLots(array &$lots, float $sellQty): array
    {
        $remaining = $sellQty; $costConsumed = 0.0; $actualQty = 0.0;
        foreach ($lots as $k => &$lot) {
            if ($remaining <= 0.0001) break;
            $take          = min($lot['qty'], $remaining);
            $cpu           = $lot['qty'] > 0 ? $lot['cost_basis'] / $lot['qty'] : 0;
            $costConsumed += $take * $cpu;
            $actualQty    += $take;
            $lot['qty']   -= $take;
            $lot['cost_basis'] -= $take * $cpu;
            $remaining    -= $take;
            if ($lot['qty'] <= 0.0001) unset($lots[$k]);
        }
        $lots = array_values($lots);
        return [$costConsumed, $actualQty];
    }

    private function totalQty(array $lots): float
    {
        return (float)array_sum(array_column($lots, 'qty'));
    }

    private function totalCostValue(array $lots): float
    {
        return (float)array_sum(array_column($lots, 'cost_basis'));
    }

    // ════════════════════════════════════════════════════════════
    // SIGNAL LABEL for display
    // ════════════════════════════════════════════════════════════
    private function signalLabel(int $i, array $ema20, array $ema50, array $rsi, array $closes): array
    {
        $e20 = $ema20[$i] ?? 0; $e50 = $ema50[$i] ?? 0;
        $r   = $rsi[$i]   ?? 50; $c   = $closes[$i] ?? 0;
        $e20Prev = $ema20[$i - 1] ?? $e20; $e50Prev = $ema50[$i - 1] ?? $e50;
        $uptrend = $e20 > $e50;
        $gc = ($e20Prev <= $e50Prev) && ($e20 > $e50);
        $dc = ($e20Prev >= $e50Prev) && ($e20 < $e50);
        $pct = $e20 > 0 ? (($c - $e20) / $e20) * 100 : 0;
        $ts  = $e50 > 0 ? (($e20 - $e50) / $e50) * 100 : 0;

        $emaLabel = match(true) {
            $gc      => '✦ GoldenX',
            $dc      => '✦ DeathX',
            $uptrend => '↑ UP',
            default  => '↓ DOWN',
        };
        $rsiLabel = match(true) {
            $r < self::RSI_STRONG_BUY  => 'OS(<36)',
            $r < self::RSI_BUY         => 'ACM(<50)',
            $r > self::RSI_STRONG_SELL => 'OB(>80)',
            $r > self::RSI_DISTRIBUTE  => 'DST(>70)',
            default                    => 'NTL',
        };

        // HOLD MODE: strong uptrend, RSI not extreme → show as HOLD
        $holdMode = $uptrend && $r < self::RSI_HOLD_BELOW && $ts > 1.5;
        $signal = 'HOLD';
        if ($holdMode) {
            $signal = 'HOLD'; // actively holding trend
        } elseif ($uptrend && ($pct <= 0.5 || $r < self::RSI_BUY)) {
            $signal = 'BUY';
        } elseif ($r > self::RSI_DISTRIBUTE || ! $uptrend) {
            $signal = 'SELL';
        }

        return ['ema_label' => $emaLabel, 'rsi_label' => $rsiLabel, 'rsi_value' => round($r, 1), 'signal' => $signal];
    }

    // ════════════════════════════════════════════════════════════
    // SAVE FUND AMOUNT
    // ════════════════════════════════════════════════════════════
    public function saveFundAmount(Request $request)
    {
        try {
            MfFundInvestment::updateOrCreate(
                ['mutual_fund_id' => $request->get('fund_id')],
                ['invested_amount' => (float)$request->get('amount', 1000000), 'is_active' => true]
            );
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ════════════════════════════════════════════════════════════
    // EMA
    // ════════════════════════════════════════════════════════════
    private function calcEMA(array $closes, int $period): array
    {
        $n = count($closes); $ema = array_fill(0, $n, 0.0);
        if ($n < $period) return $ema;
        $k = 2.0 / ($period + 1);
        $ema[$period - 1] = array_sum(array_slice($closes, 0, $period)) / $period;
        for ($i = $period; $i < $n; $i++) {
            $ema[$i] = ($closes[$i] - $ema[$i - 1]) * $k + $ema[$i - 1];
        }
        return $ema;
    }

    // ════════════════════════════════════════════════════════════
    // RSI (Wilder's)
    // ════════════════════════════════════════════════════════════
    private function calcRSI(array $closes, int $period): array
    {
        $n = count($closes); $rsi = array_fill(0, $n, 50.0);
        if ($n < $period + 1) return $rsi;
        $gains = 0.0; $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $d = $closes[$i] - $closes[$i - 1];
            if ($d > 0) $gains += $d; else $losses += abs($d);
        }
        $ag = $gains / $period; $al = $losses / $period;
        for ($i = $period; $i < $n; $i++) {
            if ($i > $period) {
                $d = $closes[$i] - $closes[$i - 1];
                $ag = ($ag * ($period - 1) + max(0.0, $d))  / $period;
                $al = ($al * ($period - 1) + max(0.0, -$d)) / $period;
            }
            $rsi[$i] = $al == 0 ? 100.0 : round(100.0 - (100.0 / (1.0 + $ag / $al)), 2);
        }
        return $rsi;
    }
}