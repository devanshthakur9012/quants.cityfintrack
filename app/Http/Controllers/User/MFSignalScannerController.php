<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MutualFundStock;
use App\Models\MutualFundStockOhlc1hr;
use App\Models\MutualFund;
use App\Models\MfPosition;
use App\Models\MfFundInvestment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MFSignalScannerController
 *
 * ── Money Logic ───────────────────────────────────────────────────
 * Fund invested      = ₹10,00,000 (configurable per fund)
 * Stock invested     = fund_invested × (allocation_pct / 100)
 *   e.g. PPFAS ₹10L, HDFCBANK 8.2% → ₹82,000 invested in HDFCBANK
 * Quantity           = stock_invested / buy_price
 * NAV                = current 1hr close price
 * Running P&L        = (NAV - buy_price) × quantity         [OPEN]
 * Booked Profit      = (sell_price - buy_price) × quantity  [CLOSED]
 * Current Value      = invested + running_pnl
 * Fund Total P&L     = sum(running_pnl) + sum(booked_profit)
 */
class MFSignalScannerController extends Controller
{
    private const RSI_PERIOD     = 14;
    private const EMA_FAST       = 20;
    private const EMA_SLOW       = 50;
    private const CANDLES_NEEDED = 120;
    private const BUY_SCORE      = 3.5;
    private const SELL_SCORE     = -3.0;

    // ─────────────────────────────────────────────────────────────────
    // PAGE
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $pageTitle       = 'MF Signal Scanner — EMA + RSI';
        $funds           = MutualFund::active()->orderBy('name')->get(['id', 'name', 'code', 'category']);
        $fundInvestments = MfFundInvestment::all()->keyBy('mutual_fund_id');
        $latestDate      = MutualFundStockOhlc1hr::notMissing()->max(DB::raw('DATE(candle_time)'));

        return view(
            $this->activeTemplate . 'user.mf.signal-scanner',
            compact('pageTitle', 'funds', 'latestDate', 'fundInvestments')
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SAVE FUND INVESTMENT AMOUNT
    // ─────────────────────────────────────────────────────────────────
    public function saveFundAmount(Request $request)
    {
        try {
            MfFundInvestment::updateOrCreate(
                ['mutual_fund_id' => $request->get('fund_id')],
                ['invested_amount' => (float)$request->get('amount', 1000000), 'is_active' => true]
            );
            return response()->json(['success' => true, 'message' => 'Saved.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // MAIN SCAN
    // ─────────────────────────────────────────────────────────────────
    public function scan(Request $request)
    {
        try {
            $fundId       = $request->get('fund_id', '');
            $signalFilter = $request->get('signal', '');
            $scanDate     = $request->get('date', '');
            $autoTrade    = (bool)$request->get('auto_trade', false);

            if (! $scanDate) {
                $scanDate = MutualFundStockOhlc1hr::notMissing()->max(DB::raw('DATE(candle_time)'));
            }
            if (! $scanDate) {
                return response()->json(['success' => false, 'message' => 'No 1hr OHLC data found.', 'data' => []]);
            }

            $fundInvestments = MfFundInvestment::all()->keyBy('mutual_fund_id');

            // ── Build stock list ──────────────────────────────────────
            $stocksQuery = MutualFundStock::active()
                ->with(['fund' => fn($q) => $q->active()->select('id', 'name', 'code', 'category')])
                ->select('id', 'mutual_fund_id', 'stock_symbol', 'stock_name', 'sector', 'allocation_percentage')
                ->orderBy('stock_symbol');

            if ($fundId) {
                $stocksQuery->where('mutual_fund_id', $fundId);
            } else {
                $stocksQuery->whereHas('fund', fn($q) => $q->active());
            }

            $allStocks = $stocksQuery->get();
            if ($allStocks->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No stocks found.', 'data' => []]);
            }

            // ── Group by symbol, collect per-fund allocation entries ───
            $symbolMap = [];
            foreach ($allStocks as $stock) {
                $sym = $stock->stock_symbol;
                if (! isset($symbolMap[$sym])) {
                    $symbolMap[$sym] = ['symbol' => $sym, 'name' => $stock->stock_name, 'sector' => $stock->sector, 'entries' => []];
                }
                $fid           = $stock->mutual_fund_id;
                $fundInvested  = (float)($fundInvestments[$fid]->invested_amount ?? 1000000);
                $allocPct      = (float)$stock->allocation_percentage;
                $stockInvested = round($fundInvested * ($allocPct / 100), 2);

                $symbolMap[$sym]['entries'][] = [
                    'fund_id'        => $fid,
                    'fund_name'      => $stock->fund->name ?? '—',
                    'fund_code'      => $stock->fund->code ?? '—',
                    'category'       => $stock->fund->category ?? '—',
                    'allocation_pct' => $allocPct,
                    'fund_invested'  => $fundInvested,
                    'stock_invested' => $stockInvested,
                ];
            }

            // ── Load open positions for all symbols ───────────────────
            $openPositions = MfPosition::open()
                ->get()
                ->groupBy(fn($p) => $p->symbol . '_' . $p->mutual_fund_id);

            $results = [];
            foreach ($symbolMap as $sym => $meta) {
                $row = $this->computeSignal($sym, $meta, $scanDate, $openPositions, $autoTrade);
                if ($row) $results[] = $row;
            }

            if ($signalFilter) {
                $results = array_values(array_filter($results, fn($r) => $r['signal'] === $signalFilter));
            }

            usort($results, function ($a, $b) {
                $order = ['BUY' => 0, 'SELL' => 1, 'WAIT' => 2];
                $oa = $order[$a['signal']] ?? 3;
                $ob = $order[$b['signal']] ?? 3;
                if ($oa !== $ob) return $oa - $ob;
                return $b['score'] <=> $a['score'];
            });

            $fundSummary = $this->buildFundSummary($fundId ? (int)$fundId : null, $fundInvestments);

            return response()->json([
                'success'      => true,
                'data'         => $results,
                'date'         => $scanDate,
                'count'        => count($results),
                'fund_summary' => $fundSummary,
                'summary'      => [
                    'total'          => count($results),
                    'buy'            => count(array_filter($results, fn($r) => $r['signal'] === 'BUY')),
                    'sell'           => count(array_filter($results, fn($r) => $r['signal'] === 'SELL')),
                    'wait'           => count(array_filter($results, fn($r) => $r['signal'] === 'WAIT')),
                    'symbols'        => count($symbolMap),
                    'open_positions' => $openPositions->count(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('MFSignalScanner Error: ' . $e->getMessage() . ' @ ' . $e->getLine());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // MANUALLY CLOSE POSITION
    // ─────────────────────────────────────────────────────────────────
    public function closePosition(Request $request)
    {
        try {
            $pos       = MfPosition::findOrFail($request->get('position_id'));
            $sellPrice = (float)$request->get('sell_price');

            if ($pos->status === 'CLOSED') {
                return response()->json(['success' => false, 'message' => 'Already closed.']);
            }

            $bookedProfit    = round(($sellPrice - (float)$pos->buy_price) * (float)$pos->quantity, 2);
            $bookedProfitPct = (float)$pos->buy_price > 0
                ? round((($sellPrice - (float)$pos->buy_price) / (float)$pos->buy_price) * 100, 4) : 0;

            $pos->update([
                'sell_price'         => $sellPrice,
                'sell_time'          => now(),
                'sell_signal_reason' => $request->get('reason', 'Manual close'),
                'booked_profit'      => $bookedProfit,
                'booked_profit_pct'  => $bookedProfitPct,
                'status'             => 'CLOSED',
            ]);

            return response()->json(['success' => true, 'booked_profit' => $bookedProfit, 'pct' => $bookedProfitPct]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // COMPUTE SIGNAL + POSITION FOR ONE SYMBOL
    // ─────────────────────────────────────────────────────────────────
    private function computeSignal(string $symbol, array $meta, string $scanDate, &$openPositions, bool $autoTrade): ?array
    {
        $candles = MutualFundStockOhlc1hr::where('symbol', $symbol)
            ->where('is_missing', false)
            ->where('candle_time', '<=', $scanDate . ' 23:59:59')
            ->orderBy('candle_time', 'desc')
            ->limit(self::CANDLES_NEEDED)
            ->get(['candle_time', 'open', 'high', 'low', 'close', 'volume'])
            ->sortBy('candle_time')->values();

        if ($candles->count() < (self::EMA_SLOW + 5)) return null;

        $closes  = $candles->pluck('close')->map(fn($v) => (float)$v)->toArray();
        $volumes = $candles->pluck('volume')->map(fn($v) => (int)$v)->toArray();
        $latest  = $candles->last();

        // ── Indicators ───────────────────────────────────────────────
        $ema20 = $this->calcEMA($closes, self::EMA_FAST);
        $ema50 = $this->calcEMA($closes, self::EMA_SLOW);
        $rsi   = $this->calcRSI($closes, self::RSI_PERIOD);

        $lastClose = (float)$latest->close;
        $lastEma20 = end($ema20);
        $lastEma50 = end($ema50);
        $lastRsi   = end($rsi);
        $prevEma20 = $ema20[count($ema20) - 2] ?? $lastEma20;
        $prevEma50 = $ema50[count($ema50) - 2] ?? $lastEma50;

        $uptrend     = $lastEma20 > $lastEma50;
        $goldenCross = ($prevEma20 <= $prevEma50) && ($lastEma20 > $lastEma50);
        $deathCross  = ($prevEma20 >= $prevEma50) && ($lastEma20 < $lastEma50);
        $pricePct    = $lastEma20 > 0 ? (($lastClose - $lastEma20) / $lastEma20) * 100 : 0;
        $nearEma20   = abs($pricePct) <= 1.5;

        $rsiOversold   = $lastRsi < 40;
        $rsiNeutral    = $lastRsi >= 40 && $lastRsi <= 60;
        $rsiOverbought = $lastRsi > 70;
        $rsiHigh       = $lastRsi > 60 && $lastRsi <= 70;

        $avgVol   = count($volumes) >= 20 ? array_sum(array_slice($volumes, -20)) / 20 : 0;
        $volRatio = $avgVol > 0 ? (end($volumes) / $avgVol) : 1.0;

        // ── Score ─────────────────────────────────────────────────────
        $score = 0.0; $reasons = [];
        if ($uptrend)                    { $score += 2.0; $reasons[] = 'EMA↑'; }
        if ($nearEma20 && $uptrend)      { $score += 1.5; $reasons[] = 'Pullback'; }
        if ($rsiNeutral)                 { $score += 2.0; $reasons[] = 'RSI40-60'; }
        if ($rsiOversold && $uptrend)    { $score += 1.0; $reasons[] = 'RSI<40'; }
        if ($goldenCross)                { $score += 2.5; $reasons[] = 'GoldX'; }
        if ($volRatio > 1.5 && $uptrend) { $score += 0.5; $reasons[] = 'Vol↑'; }
        if (!$uptrend)                   { $score -= 2.0; $reasons[] = 'EMA↓'; }
        if ($rsiOverbought)              { $score -= 2.0; $reasons[] = 'RSI>70'; }
        if ($deathCross)                 { $score -= 2.5; $reasons[] = 'DeathX'; }
        if ($rsiHigh && !$uptrend)       { $score -= 0.5; $reasons[] = 'RSI60-70'; }

        $signal = $score >= self::BUY_SCORE ? 'BUY' : ($score <= self::SELL_SCORE ? 'SELL' : 'WAIT');
        $absScore = abs($score);
        $strength = match(true) {
            $absScore >= 6.0 => 'STRONG',
            $absScore >= 4.5 => 'MEDIUM',
            $absScore >= 3.0 => 'WEAK',
            default          => 'NEUTRAL',
        };

        $rsiZone = $rsiOversold ? 'OVERSOLD' : ($rsiNeutral ? 'NEUTRAL' : ($rsiOverbought ? 'OVERBOUGHT' : 'HIGH'));
        $reasonStr = implode(' + ', $reasons);

        // ── Per-fund position data ────────────────────────────────────
        $positionData      = [];
        $totalInvested     = 0;
        $totalCurrentVal   = 0;
        $totalRunningPnl   = 0;
        $totalBookedProfit = 0;

        foreach ($meta['entries'] as $entry) {
            $fid           = $entry['fund_id'];
            $posKey        = $symbol . '_' . $fid;
            $openPos       = $openPositions->get($posKey)?->first();
            $stockInvested = $entry['stock_invested'];
            $totalInvested += $stockInvested;

            // Auto-trade: open on BUY / close on SELL
            if ($autoTrade) {
                if ($signal === 'BUY' && !$openPos) {
                    $qty = $lastClose > 0 ? round($stockInvested / $lastClose, 4) : 0;
                    $openPos = MfPosition::create([
                        'symbol'            => $symbol, 'exchange' => 'NSE',
                        'mutual_fund_id'    => $fid,
                        'allocation_pct'    => $entry['allocation_pct'],
                        'invested_amount'   => $stockInvested,
                        'quantity'          => $qty,
                        'buy_price'         => $lastClose,
                        'buy_time'          => Carbon::parse($latest->candle_time),
                        'buy_signal_reason' => $reasonStr,
                        'status'            => 'OPEN',
                    ]);
                    $openPositions->put($posKey, collect([$openPos]));
                } elseif ($signal === 'SELL' && $openPos) {
                    $bp    = round(($lastClose - (float)$openPos->buy_price) * (float)$openPos->quantity, 2);
                    $bpPct = (float)$openPos->buy_price > 0
                        ? round((($lastClose - (float)$openPos->buy_price) / (float)$openPos->buy_price) * 100, 4) : 0;
                    $openPos->update([
                        'sell_price' => $lastClose, 'sell_time' => Carbon::parse($latest->candle_time),
                        'sell_signal_reason' => $reasonStr,
                        'booked_profit' => $bp, 'booked_profit_pct' => $bpPct, 'status' => 'CLOSED',
                    ]);
                    $openPositions->forget($posKey);
                    $openPos = null;
                }
            }

            // Build display row
            $pd = [
                'fund_id'           => $fid,
                'fund_code'         => $entry['fund_code'],
                'fund_name'         => $entry['fund_name'],
                'allocation_pct'    => $entry['allocation_pct'],
                'fund_invested'     => $entry['fund_invested'],
                'stock_invested'    => $stockInvested,
                'nav'               => $lastClose,
                'position_id'       => null,
                'position_status'   => 'NO_POSITION',
                'buy_price'         => null, 'buy_time'  => null, 'buy_reason' => null,
                'sell_price'        => null, 'sell_time' => null,
                'quantity'          => null,
                'running_pnl'       => null, 'running_pnl_pct'    => null,
                'booked_profit'     => null, 'booked_profit_pct'  => null,
                'current_value'     => $stockInvested,
            ];

            if ($openPos) {
                $runPnl   = round(($lastClose - (float)$openPos->buy_price) * (float)$openPos->quantity, 2);
                $runPct   = (float)$openPos->buy_price > 0
                    ? round((($lastClose - (float)$openPos->buy_price) / (float)$openPos->buy_price) * 100, 2) : 0;
                $curVal   = (float)$openPos->invested_amount + $runPnl;
                $pd = array_merge($pd, [
                    'position_id'     => $openPos->id,
                    'position_status' => 'OPEN',
                    'buy_price'       => (float)$openPos->buy_price,
                    'buy_time'        => Carbon::parse($openPos->buy_time)->format('d M H:i'),
                    'buy_reason'      => $openPos->buy_signal_reason,
                    'quantity'        => (float)$openPos->quantity,
                    'running_pnl'     => $runPnl,
                    'running_pnl_pct' => $runPct,
                    'current_value'   => round($curVal, 2),
                ]);
                $totalRunningPnl += $runPnl;
                $totalCurrentVal += $curVal;
            } else {
                $closedPos = MfPosition::closed()
                    ->where('symbol', $symbol)->where('mutual_fund_id', $fid)
                    ->orderByDesc('sell_time')->first();

                if ($closedPos) {
                    $bookedVal = (float)$closedPos->invested_amount + (float)$closedPos->booked_profit;
                    $pd = array_merge($pd, [
                        'position_id'        => $closedPos->id,
                        'position_status'    => 'CLOSED',
                        'buy_price'          => (float)$closedPos->buy_price,
                        'buy_time'           => Carbon::parse($closedPos->buy_time)->format('d M H:i'),
                        'buy_reason'         => $closedPos->buy_signal_reason,
                        'sell_price'         => (float)$closedPos->sell_price,
                        'sell_time'          => Carbon::parse($closedPos->sell_time)->format('d M H:i'),
                        'quantity'           => (float)$closedPos->quantity,
                        'booked_profit'      => (float)$closedPos->booked_profit,
                        'booked_profit_pct'  => (float)$closedPos->booked_profit_pct,
                        'current_value'      => round($bookedVal, 2),
                    ]);
                    $totalBookedProfit += (float)$closedPos->booked_profit;
                    $totalCurrentVal   += $bookedVal;
                } else {
                    $totalCurrentVal += $stockInvested;
                }
            }

            $positionData[] = $pd;
        }

        $totalAllocPct = round(array_sum(array_column($meta['entries'], 'allocation_pct')), 2);

        return [
            'symbol'            => $symbol,
            'name'              => $meta['name'],
            'sector'            => $meta['sector'],
            'positions'         => $positionData,
            'close'             => round($lastClose, 2),
            'nav'               => round($lastClose, 2),
            'ema20'             => round($lastEma20, 2),
            'ema50'             => round($lastEma50, 2),
            'ema_gap_pct'       => round($lastEma50 > 0 ? (($lastEma20 - $lastEma50) / $lastEma50) * 100 : 0, 2),
            'rsi'               => round($lastRsi, 1),
            'rsi_zone'          => $rsiZone,
            'vol_ratio'         => round($volRatio, 2),
            'price_vs_ema20'    => round($pricePct, 2),
            'uptrend'           => $uptrend,
            'golden_cross'      => $goldenCross,
            'death_cross'       => $deathCross,
            'candle_time'       => Carbon::parse($latest->candle_time)->format('d M H:i'),
            'candles_used'      => $candles->count(),
            'signal'            => $signal,
            'score'             => round($score, 1),
            'strength'          => $strength,
            'reasons'           => $reasonStr,
            'total_invested'    => round($totalInvested, 2),
            'total_current_val' => round($totalCurrentVal > 0 ? $totalCurrentVal : $totalInvested, 2),
            'total_running_pnl' => round($totalRunningPnl, 2),
            'total_booked'      => round($totalBookedProfit, 2),
            'total_alloc_pct'   => $totalAllocPct,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // FUND-LEVEL PERFORMANCE
    // ─────────────────────────────────────────────────────────────────
    private function buildFundSummary(?int $filterFundId, $fundInvestments): array
    {
        $funds   = MutualFund::active()->orderBy('name')->get();
        $summary = [];

        foreach ($funds as $fund) {
            if ($filterFundId && $fund->id !== $filterFundId) continue;
            $fid          = $fund->id;
            $fundInvested = (float)($fundInvestments[$fid]->invested_amount ?? 1000000);

            $stocks        = MutualFundStock::active()->where('mutual_fund_id', $fid)->get();
            $totalAllocPct = $stocks->sum('allocation_percentage');

            $openPos = MfPosition::open()->where('mutual_fund_id', $fid)->get();

            // Get current prices for open positions
            $openSymbols   = $openPos->pluck('symbol')->unique()->toArray();
            $currentPrices = [];
            if (!empty($openSymbols)) {
                $latestTimes = MutualFundStockOhlc1hr::whereIn('symbol', $openSymbols)
                    ->where('is_missing', false)
                    ->select(DB::raw('symbol, MAX(candle_time) as max_time'))
                    ->groupBy('symbol')->get()->keyBy('symbol');

                foreach ($latestTimes as $sym => $row) {
                    $c = MutualFundStockOhlc1hr::where('symbol', $sym)->where('candle_time', $row->max_time)->first();
                    if ($c) $currentPrices[$sym] = (float)$c->close;
                }
            }

            $runningPnl = 0; $openValue = 0;
            foreach ($openPos as $pos) {
                $cur         = $currentPrices[$pos->symbol] ?? (float)$pos->buy_price;
                $pnl         = ($cur - (float)$pos->buy_price) * (float)$pos->quantity;
                $runningPnl += $pnl;
                $openValue  += (float)$pos->invested_amount + $pnl;
            }

            $bookedProfit = (float)MfPosition::closed()->where('mutual_fund_id', $fid)->sum('booked_profit');
            $openInvested = (float)$openPos->sum('invested_amount');
            $idleAmount   = $fundInvested - $openInvested;
            $currentValue = $idleAmount + $openValue + $bookedProfit;
            $totalPnl     = $currentValue - $fundInvested;
            $totalPnlPct  = $fundInvested > 0 ? round(($totalPnl / $fundInvested) * 100, 2) : 0;

            $totalTrades  = MfPosition::where('mutual_fund_id', $fid)->count();
            $closedTrades = MfPosition::closed()->where('mutual_fund_id', $fid)->count();
            $winTrades    = MfPosition::closed()->where('mutual_fund_id', $fid)->where('booked_profit', '>', 0)->count();

            $summary[] = [
                'fund_id'         => $fid,
                'fund_name'       => $fund->name,
                'fund_code'       => $fund->code,
                'category'        => $fund->category,
                'fund_invested'   => $fundInvested,
                'total_alloc_pct' => round($totalAllocPct, 2),
                'stock_count'     => $stocks->count(),
                'open_positions'  => $openPos->count(),
                'open_invested'   => round($openInvested, 2),
                'idle_amount'     => round($idleAmount, 2),
                'current_value'   => round($currentValue, 2),
                'running_pnl'     => round($runningPnl, 2),
                'booked_profit'   => round($bookedProfit, 2),
                'total_pnl'       => round($totalPnl, 2),
                'total_pnl_pct'   => $totalPnlPct,
                'total_trades'    => $totalTrades,
                'closed_trades'   => $closedTrades,
                'win_rate'        => $closedTrades > 0 ? round(($winTrades / $closedTrades) * 100, 1) : 0,
            ];
        }
        return $summary;
    }

    private function calcEMA(array $closes, int $period): array
    {
        $n = count($closes); $ema = array_fill(0, $n, 0.0);
        $k = 2.0 / ($period + 1); $sum = 0.0;
        for ($i = 0; $i < $period && $i < $n; $i++) $sum += $closes[$i];
        $ema[$period - 1] = $sum / $period;
        for ($i = $period; $i < $n; $i++) $ema[$i] = ($closes[$i] - $ema[$i - 1]) * $k + $ema[$i - 1];
        return $ema;
    }

    private function calcRSI(array $closes, int $period): array
    {
        $n = count($closes); $rsi = array_fill(0, $n, 50.0);
        if ($n < $period + 1) return $rsi;
        $gains = 0.0; $losses = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $c = $closes[$i] - $closes[$i - 1];
            if ($c > 0) $gains += $c; else $losses += abs($c);
        }
        $ag = $gains / $period; $al = $losses / $period;
        for ($i = $period; $i < $n; $i++) {
            if ($i > $period) {
                $c = $closes[$i] - $closes[$i - 1];
                $ag = ($ag * ($period - 1) + max(0.0, $c))   / $period;
                $al = ($al * ($period - 1) + max(0.0, -$c))  / $period;
            }
            $rsi[$i] = $al == 0 ? 100.0 : round(100.0 - (100.0 / (1.0 + $ag / $al)), 2);
        }
        return $rsi;
    }
}