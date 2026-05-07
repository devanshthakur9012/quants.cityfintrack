<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MutualFundStock;
use App\Models\MfStockFutureOhlc;
use App\Models\MfStockOptionOhlc;
use App\Models\MutualFund;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MfMultiAssetController
 *
 * ═══════════════════════════════════════════════════════════════
 * STRATEGY:  BUY 1 FUT + SELL 2 OTM CE  (same stock, same expiry)
 * ═══════════════════════════════════════════════════════════════
 *
 * HOW IT WORKS (per stock, per expiry cycle):
 *
 *   ENTRY SIGNAL (OI-based):
 *     → Look at CE OI buildup on 09:15 candle of entry day
 *     → Highest OI strike = market's resistance level = our SELL strike
 *     → That day 09:15: BUY 1 FUT at FUT close price
 *                       SELL 2 CE at that strike's close price
 *
 *   HOLD: track P&L every hourly candle
 *     FUT P&L  = (current_FUT - entry_FUT) × lot_size
 *     CE  P&L  = (entry_CE   - current_CE) × 2 × lot_size   ← profit when CE falls
 *     Net P&L  = FUT_PnL + CE_PnL
 *
 *   EXIT:
 *     → User selects exit date (simulate "sold on 20 April")
 *     → Exit uses the last available candle of that exit date
 *     → Shows: entry details, exit details, final P&L
 *
 * DISPLAY: One row per stock — Entry date/time, FUT buy, CE sell,
 *          Exit date/time, Net P&L, Status (OPEN / CLOSED)
 */
class MfMultiAssetController extends Controller
{
    // CE strike to sell = ATM + this many strikes above (OI overrides if available)
    private const DEFAULT_OTM_STRIKES = 2;

    // ─────────────────────────────────────────────────────────────────
    // PAGE
    // ─────────────────────────────────────────────────────────────────
    public function index()
    {
        $pageTitle = 'Multi-Asset MF — FUT + Options Strategy';
        $funds     = MutualFund::active()->orderBy('name')->get(['id','name','code','category']);

        $symbols = MfStockFutureOhlc::select('symbol')
            ->distinct()->orderBy('symbol')->pluck('symbol');

        $availableDates = MfStockFutureOhlc::select(DB::raw('DATE(trade_date) as d'))
            ->distinct()->orderBy('d','desc')->limit(60)->pluck('d');

        $expiries = MfStockFutureOhlc::select('expiry_date')
            ->distinct()->orderBy('expiry_date')
            ->pluck('expiry_date')
            ->map(fn($d) => Carbon::parse($d)->toDateString());

        return view(
            $this->activeTemplate . 'user.mf.multi-asset',
            compact('pageTitle','funds','symbols','availableDates','expiries')
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // SIMULATE — trade log for ALL MF stocks for a given entry→exit period
    //
    // GET /mf-multi-asset/simulate
    //   ?entry_date=2024-04-10
    //   &exit_date=2024-04-20        ← blank = still open (show running P&L)
    //   &expiry=2024-04-25
    //   &fund_id=                    ← optional: filter by fund
    // ─────────────────────────────────────────────────────────────────
    public function simulate(Request $request)
    {
        try {
            $entryDate = $request->get('entry_date');
            $exitDate  = $request->get('exit_date', '');   // blank = OPEN trade
            $expiry    = $request->get('expiry', '');
            $fundId    = $request->get('fund_id', '');

            if (! $entryDate) {
                return response()->json(['success' => false, 'message' => 'Entry date is required.']);
            }

            $isOpen = empty($exitDate); // no exit date = position still open

            // Get MF stock symbols (filter by fund if given)
            $symQuery = MutualFundStock::active()
                ->whereHas('fund', fn($q) => $q->active())
                ->with('fund:id,name,code')
                ->select('stock_symbol','mutual_fund_id','stock_name','allocation_percentage');
            if ($fundId) $symQuery->where('mutual_fund_id', $fundId);
            $stocks = $symQuery->get();

            if ($stocks->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No stocks found.']);
            }

            // Deduplicate symbols (same stock may appear in multiple funds — take first)
            $symbolMap = [];
            foreach ($stocks as $s) {
                if (! isset($symbolMap[$s->stock_symbol])) {
                    $symbolMap[$s->stock_symbol] = $s;
                }
            }

            $rows        = [];
            $totalNetPnl = 0;
            $openCount   = 0;
            $closedCount = 0;
            $winCount    = 0;

            foreach ($symbolMap as $symbol => $stock) {
                $result = $this->simulateTrade($symbol, $stock, $entryDate, $exitDate, $expiry, $isOpen);
                if (! $result) continue;

                $rows[]       = $result;
                $totalNetPnl += $result['net_pnl'];
                if ($result['status'] === 'OPEN')   $openCount++;
                else                                $closedCount++;
                if ($result['net_pnl'] > 0)         $winCount++;
            }

            // Sort: biggest P&L first
            usort($rows, fn($a,$b) => $b['net_pnl'] <=> $a['net_pnl']);

            $total = count($rows);
            $winRate = $total > 0 ? round(($winCount / $total) * 100, 1) : 0;

            return response()->json([
                'success'      => true,
                'entry_date'   => $entryDate,
                'exit_date'    => $exitDate ?: 'OPEN',
                'expiry'       => $expiry,
                'data'         => $rows,
                'summary'      => [
                    'total_symbols' => $total,
                    'open_count'    => $openCount,
                    'closed_count'  => $closedCount,
                    'total_net_pnl' => round($totalNetPnl, 2),
                    'win_count'     => $winCount,
                    'loss_count'    => $total - $winCount,
                    'win_rate'      => $winRate,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('MfMultiAsset simulate: ' . $e->getMessage() . ' L' . $e->getLine());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // SIMULATE ONE STOCK TRADE
    // ─────────────────────────────────────────────────────────────────
    private function simulateTrade(
        string $symbol,
        $stock,
        string $entryDate,
        string $exitDate,
        string $expiry,
        bool   $isOpen
    ): ?array {
        // ── Resolve expiry ────────────────────────────────────────
        $resolvedExpiry = $expiry;
        if (! $resolvedExpiry) {
            $resolvedExpiry = MfStockFutureOhlc::where('symbol', $symbol)
                ->where('trade_date', '>=', $entryDate)
                ->orderBy('expiry_date','asc')
                ->value('expiry_date');
            if (! $resolvedExpiry) return null;
            $resolvedExpiry = Carbon::parse($resolvedExpiry)->toDateString();
        }

        // ── Entry candle: first candle of entry date (09:15) ─────
        $entryFut = MfStockFutureOhlc::where('symbol', $symbol)
            ->where('trade_date', $entryDate)
            ->where('expiry_date', $resolvedExpiry)
            ->where('is_missing', false)
            ->orderBy('interval_time','asc')
            ->first();

        if (! $entryFut) return null;

        $lotSize      = (int) ($entryFut->lot_size ?: 1);
        $atmStrike    = (float) $entryFut->atm_strike;
        $entryFutPx   = (float) $entryFut->close;
        $entryTime    = Carbon::parse($entryFut->interval_time)->format('d M Y H:i');

        // ── Determine CE strike to SELL ────────────────────────────
        // Use highest OI CE strike on entry day 09:15 (OI-based, as client wants)
        // Fallback: ATM + 2 strikes
        $strikeInterval = $this->strikeInterval($symbol, $resolvedExpiry);
        $sellStrike     = $this->resolveSellStrike($symbol, $entryDate, $atmStrike, $strikeInterval, $resolvedExpiry);

        // ── Entry CE price ────────────────────────────────────────
        $entryCeRow = MfStockOptionOhlc::where('symbol', $symbol)
            ->where('trade_date', $entryDate)
            ->where('expiry_date', $resolvedExpiry)
            ->where('option_type', 'CE')
            ->where('strike_price', $sellStrike)
            ->where('is_missing', false)
            ->orderBy('interval_time','asc')
            ->first();

        $entryCePx   = $entryCeRow ? (float) $entryCeRow->close : 0;
        $premiumRcvd = round($entryCePx * $lotSize * 2, 2); // 2 lots sold

        // ── Exit candle ───────────────────────────────────────────
        $exitFut = null; $exitCeRow = null;
        $exitFutPx = $entryFutPx; $exitCePx = $entryCePx;
        $exitTime  = '—';
        $status    = 'OPEN';

        if (! $isOpen) {
            // Use LAST candle of exit date
            $exitFut = MfStockFutureOhlc::where('symbol', $symbol)
                ->where('trade_date', $exitDate)
                ->where('expiry_date', $resolvedExpiry)
                ->where('is_missing', false)
                ->orderBy('interval_time','desc')
                ->first();

            if (! $exitFut) {
                // Try latest available candle on or before exit date
                $exitFut = MfStockFutureOhlc::where('symbol', $symbol)
                    ->where('trade_date', '<=', $exitDate)
                    ->where('trade_date', '>=', $entryDate)
                    ->where('expiry_date', $resolvedExpiry)
                    ->where('is_missing', false)
                    ->orderBy('interval_time','desc')
                    ->first();
            }

            if ($exitFut) {
                $exitFutPx  = (float) $exitFut->close;
                $exitTime   = Carbon::parse($exitFut->interval_time)->format('d M Y H:i');
                $status     = 'CLOSED';

                $exitCeRow = MfStockOptionOhlc::where('symbol', $symbol)
                    ->where('trade_date', Carbon::parse($exitFut->interval_time)->toDateString())
                    ->where('expiry_date', $resolvedExpiry)
                    ->where('option_type', 'CE')
                    ->where('strike_price', $sellStrike)
                    ->where('is_missing', false)
                    ->orderBy('interval_time','desc')
                    ->first();

                $exitCePx = $exitCeRow ? (float) $exitCeRow->close : $entryCePx;
            }
        } else {
            // Open — use latest available candle
            $latestFut = MfStockFutureOhlc::where('symbol', $symbol)
                ->where('trade_date', '>=', $entryDate)
                ->where('expiry_date', $resolvedExpiry)
                ->where('is_missing', false)
                ->orderBy('interval_time','desc')
                ->first();

            if ($latestFut) {
                $exitFutPx = (float) $latestFut->close;
                $exitTime  = Carbon::parse($latestFut->interval_time)->format('d M Y H:i') . ' (live)';

                $latestCe = MfStockOptionOhlc::where('symbol', $symbol)
                    ->where('trade_date', Carbon::parse($latestFut->interval_time)->toDateString())
                    ->where('expiry_date', $resolvedExpiry)
                    ->where('option_type', 'CE')
                    ->where('strike_price', $sellStrike)
                    ->where('is_missing', false)
                    ->orderBy('interval_time','desc')
                    ->first();

                $exitCePx = $latestCe ? (float) $latestCe->close : $entryCePx;
            }
        }

        // ── P&L CALCULATION ───────────────────────────────────────
        // FUT leg:  BUY 1 lot → profit when FUT goes up
        $futPnl  = round(($exitFutPx - $entryFutPx) * $lotSize, 2);
        // CE leg:   SELL 2 lots → profit when CE price falls (premium decay)
        $cePnl   = round(($entryCePx - $exitCePx)   * $lotSize * 2, 2);
        $netPnl  = round($futPnl + $cePnl, 2);

        // % return on capital at risk (FUT margin approx = entry price × lot)
        $capitalAtRisk = $entryFutPx * $lotSize;
        $netPnlPct     = $capitalAtRisk > 0 ? round(($netPnl / $capitalAtRisk) * 100, 2) : 0;

        // FUT change %
        $futChgPct = $entryFutPx > 0 ? round((($exitFutPx - $entryFutPx) / $entryFutPx) * 100, 2) : 0;

        // CE decay % (how much the CE we sold has fallen = our profit)
        $ceDecayPct = $entryCePx > 0 ? round((($entryCePx - $exitCePx) / $entryCePx) * 100, 2) : 0;

        return [
            'symbol'         => $symbol,
            'stock_name'     => $stock->stock_name,
            'fund_code'      => $stock->fund->code ?? '—',
            'expiry'         => $resolvedExpiry,
            'lot_size'       => $lotSize,
            'status'         => $status,

            // Entry
            'entry_date'     => $entryDate,
            'entry_time'     => $entryTime,
            'entry_fut_px'   => $entryFutPx,       // BUY FUT at this price
            'entry_ce_strike'=> $sellStrike,        // SELL CE at this strike
            'entry_ce_px'    => $entryCePx,         // CE premium collected
            'premium_rcvd'   => $premiumRcvd,       // total premium received = CE × 2 × lot

            // Exit
            'exit_date'      => $isOpen ? '—' : ($exitDate ?: '—'),
            'exit_time'      => $exitTime,
            'exit_fut_px'    => round($exitFutPx, 2),
            'exit_ce_px'     => round($exitCePx, 2),

            // P&L
            'fut_pnl'        => $futPnl,
            'ce_pnl'         => $cePnl,
            'net_pnl'        => $netPnl,
            'net_pnl_pct'    => $netPnlPct,
            'fut_chg_pct'    => $futChgPct,
            'ce_decay_pct'   => $ceDecayPct,

            // Signal
            'signal'         => $netPnl >= 0 ? 'PROFIT' : 'LOSS',
            'atm_strike'     => $atmStrike,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // DAILY CANDLE P&L — click a row → see hour-by-hour P&L for that stock
    // GET /mf-multi-asset/candles?symbol=X&entry_date=Y&exit_date=Z&expiry=E
    // ─────────────────────────────────────────────────────────────────
    public function candles(Request $request)
    {
        try {
            $symbol    = strtoupper($request->get('symbol', ''));
            $entryDate = $request->get('entry_date', '');
            $exitDate  = $request->get('exit_date', '');
            $expiry    = $request->get('expiry', '');

            if (! $symbol || ! $entryDate || ! $expiry) {
                return response()->json(['success' => false, 'message' => 'symbol, entry_date, expiry required.']);
            }

            $toDate = $exitDate ?: Carbon::today()->toDateString();

            // Load FUT candles for the full period
            $futCandles = MfStockFutureOhlc::where('symbol', $symbol)
                ->where('expiry_date', $expiry)
                ->whereBetween('trade_date', [$entryDate, $toDate])
                ->where('is_missing', false)
                ->orderBy('interval_time','asc')
                ->get();

            if ($futCandles->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'No candle data found.']);
            }

            // Entry values
            $firstFut  = $futCandles->first();
            $atmStrike = (float) $firstFut->atm_strike;
            $lotSize   = (int) ($firstFut->lot_size ?: 1);
            $entryFutPx = (float) $firstFut->close;

            // Resolve sell strike
            $strikeInterval = $this->strikeInterval($symbol, $expiry);
            $sellStrike     = $this->resolveSellStrike($symbol, $entryDate, $atmStrike, $strikeInterval, $expiry);

            // Load CE candles
            $ceCandles = MfStockOptionOhlc::where('symbol', $symbol)
                ->where('expiry_date', $expiry)
                ->where('option_type', 'CE')
                ->where('strike_price', $sellStrike)
                ->whereBetween('trade_date', [$entryDate, $toDate])
                ->where('is_missing', false)
                ->orderBy('interval_time','asc')
                ->get()
                ->keyBy(fn($r) => Carbon::parse($r->interval_time)->format('Y-m-d H:i'));

            $entryCePx = 0;
            $firstCe   = $ceCandles->first();
            if ($firstCe) $entryCePx = (float) $firstCe->close;

            // Build candle rows
            $rows = [];
            foreach ($futCandles as $fut) {
                $tKey    = Carbon::parse($fut->interval_time)->format('Y-m-d H:i');
                $ce      = $ceCandles->get($tKey);
                $futPx   = (float) $fut->close;
                $cePx    = $ce ? (float) $ce->close : $entryCePx;

                $futPnl  = round(($futPx  - $entryFutPx) * $lotSize, 2);
                $cePnl   = round(($entryCePx - $cePx)    * $lotSize * 2, 2);
                $netPnl  = round($futPnl + $cePnl, 2);

                $rows[] = [
                    'datetime'  => Carbon::parse($fut->interval_time)->format('d M H:i'),
                    'fut_price' => round($futPx, 2),
                    'ce_price'  => round($cePx,  2),
                    'fut_pnl'   => $futPnl,
                    'ce_pnl'    => $cePnl,
                    'net_pnl'   => $netPnl,
                    'fut_oi'    => (int) $fut->oi,
                    'ce_oi'     => $ce ? (int) $ce->oi : 0,
                ];
            }

            return response()->json([
                'success'     => true,
                'symbol'      => $symbol,
                'lot_size'    => $lotSize,
                'sell_strike' => $sellStrike,
                'entry_fut'   => $entryFutPx,
                'entry_ce'    => $entryCePx,
                'candles'     => $rows,
            ]);

        } catch (\Exception $e) {
            Log::error('MfMultiAsset candles: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * OI-based sell strike selection:
     * Find CE strike with HIGHEST OI on entry date 09:15 candle.
     * This is what the market "expects" as resistance → ideal CE to sell.
     * Fallback: ATM + DEFAULT_OTM_STRIKES.
     */
    private function resolveSellStrike(
        string $symbol,
        string $entryDate,
        float  $atmStrike,
        ?float $strikeInterval,
        string $expiry
    ): float {
        $highOiStrike = MfStockOptionOhlc::where('symbol', $symbol)
            ->where('trade_date', $entryDate)
            ->where('expiry_date', $expiry)
            ->where('option_type', 'CE')
            ->where('strike_price', '>', $atmStrike)   // only OTM calls
            ->where('is_missing', false)
            ->orderBy('oi', 'desc')
            ->value('strike_price');

        if ($highOiStrike) return (float) $highOiStrike;

        // Fallback: ATM + N strikes
        $interval = $strikeInterval ?? 50;
        return $atmStrike + (self::DEFAULT_OTM_STRIKES * $interval);
    }

    private function strikeInterval(string $symbol, string $expiry): ?float
    {
        $strikes = MfStockOptionOhlc::where('symbol', $symbol)
            ->where('expiry_date', $expiry)
            ->where('option_type', 'CE')
            ->select('strike_price')->distinct()
            ->orderBy('strike_price')
            ->pluck('strike_price')
            ->map(fn($s) => (float)$s)->sort()->values();

        if ($strikes->count() < 2) return null;

        $gaps = [];
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gaps[] = $strikes[$i] - $strikes[$i - 1];
        }
        return min($gaps) ?: null;
    }
}