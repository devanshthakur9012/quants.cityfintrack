<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * NiftyDrivenAllSymbolsController — FINAL VERSION
 *
 * ═══════════════════════════════════════════════════════════════════
 * SIGNAL LOGIC (CORRECTED)
 * ═══════════════════════════════════════════════════════════════════
 *
 * Signal source : NIFTY FUT 15-min candles only
 * Trade targets : ALL symbols in option_ohlc_data
 *
 * OPEN REFERENCE:
 *   We use the OPEN price of the very FIRST 09:15 candle as the
 *   day's reference open. This matches what Zerodha shows.
 *
 * CANDLE COMPLETION RULE (why prices differed before):
 *   A 09:45 candle on Zerodha is live from 09:45 → 10:00.
 *   It is COMPLETE (and its close/high/low are final) only at 10:00.
 *   So if the 09:45 candle triggers the signal, the earliest we can
 *   BUY is the 10:00 candle (i.e., next candle after trigger).
 *   trigger_time   = candle interval_time (e.g. "09:45")
 *   buy_time       = next candle's interval_time (e.g. "10:00")
 *   buy_price      = OPEN of the buy candle
 *
 * CE SIGNAL : FIRST candle whose HIGH >= open + threshold
 * PE SIGNAL : FIRST candle whose LOW  <= open - threshold
 *   - Both can trigger independently on same day.
 *   - Each direction triggers only ONCE (first crossover).
 *   - 09:15 candle is excluded from trigger scan.
 *
 * EXIT P&L TABLE (replaces old high/low window):
 *   For each 15-min slot AFTER the buy candle (up to 15:15),
 *   we compute: what if ALL positions were exited at that candle's OPEN?
 *   → total sell value, total investment, total profit, ROI%
 *   This lets the user see every possible exit scenario at a glance.
 * ═══════════════════════════════════════════════════════════════════
 */
class NiftyDrivenAllSymbolsController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'NIFTY-Driven Multi-Symbol Breakout';
        return view($this->activeTemplate . 'user.nifty-driven-breakout.index', compact('pageTitle'));
    }

    // =========================================================
    //  MAIN ANALYSIS — returns trade rows (no P&L)
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $threshold    = (float) $request->get('threshold', 30);
            $filter       = $request->get('filter', 'BOTH');   // CE | PE | BOTH
            $symbolFilter = strtoupper($request->get('symbol_filter', 'ALL'));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            // All trading days in range with NIFTY FUT data
            $tradeDates = OptionOhlcData::where('base_symbol', 'NIFTY')
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')
                ->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json([
                    'success'       => true,
                    'data'          => [],
                    'total_records' => 0,
                    'message'       => 'No NIFTY FUT data found for selected range',
                ]);
            }

            // All base symbols with option data
            $allSymbols = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
                ->where('oi', '>', 0)
                ->distinct()->orderBy('base_symbol')
                ->pluck('base_symbol')->values()->toArray();

            if ($symbolFilter !== 'ALL' && in_array($symbolFilter, $allSymbols)) {
                $allSymbols = [$symbolFilter];
            }

            $results = [];

            foreach ($tradeDates as $date) {
                $dayRows = $this->processDay($date, $threshold, $filter, $allSymbols);
                foreach ($dayRows as $row) {
                    $results[] = $row;
                }
            }

            // Sort: newest date first, CE before PE, then symbol
            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date']
                    ?: $a['signal_type'] <=> $b['signal_type']
                    ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' trades found',
            ]);

        } catch (\Exception $e) {
            Log::error('NiftyDrivenBreakout analyze: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  EXIT P&L TABLE
    //  For each 15-min slot after the buy candle, computes
    //  aggregate exit scenario for all trades on that day+signal.
    //
    //  Request params same as analyze() plus signal_type=CE|PE|BOTH
    //  Returns: { ce: [...exitSlots], pe: [...exitSlots] }
    //    exitSlot = { exit_time, sell_total, investment, profit, roi, trade_count }
    // =========================================================

    public function exitPnl(Request $request)
    {
        try {
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $threshold    = (float) $request->get('threshold', 30);
            $filter       = $request->get('filter', 'BOTH');
            $symbolFilter = strtoupper($request->get('symbol_filter', 'ALL'));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates']);
            }

            $tradeDates = OptionOhlcData::where('base_symbol', 'NIFTY')
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')
                ->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json(['success' => true, 'ce' => [], 'pe' => []]);
            }

            $allSymbols = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
                ->where('oi', '>', 0)
                ->distinct()->orderBy('base_symbol')
                ->pluck('base_symbol')->values()->toArray();

            if ($symbolFilter !== 'ALL' && in_array($symbolFilter, $allSymbols)) {
                $allSymbols = [$symbolFilter];
            }

            // Accumulate: exitTime → { sell_total, investment, profit, trade_count }
            // Separate for CE and PE
            $ceSlots = [];  // keyed by "date|exit_time"
            $peSlots = [];

            foreach ($tradeDates as $date) {
                $this->accumulateExitPnl($date, $threshold, $filter, $allSymbols, $ceSlots, $peSlots);
            }

            return response()->json([
                'success' => true,
                'ce'      => $this->summariseSlots($ceSlots),
                'pe'      => $this->summariseSlots($peSlots),
            ]);

        } catch (\Exception $e) {
            Log::error('NiftyDrivenBreakout exitPnl: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * For one day, compute exit P&L at every 15-min slot for CE and PE trades.
     * Appends to $ceSlots / $peSlots arrays (passed by reference).
     */
    private function accumulateExitPnl(
        string $date,
        float  $threshold,
        string $filter,
        array  $allSymbols,
        array  &$ceSlots,
        array  &$peSlots
    ): void {
        // Load NIFTY FUT candles
        $niftyCandles = OptionOhlcData::where('base_symbol', 'NIFTY')
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close'])
            ->values();

        if ($niftyCandles->isEmpty()) return;

        // OPEN reference = OPEN of 09:15 candle (first candle of day)
        $openCandle = $niftyCandles->first();
        $openPrice  = (float) $openCandle->open;
        if ($openPrice <= 0) return;

        $ceThreshold = $openPrice + $threshold;
        $peThreshold = $openPrice - $threshold;

        // Find trigger candles
        $ceTriggerIdx = null;
        $peTriggerIdx = null;

        foreach ($niftyCandles as $idx => $candle) {
            $timeKey = Carbon::parse($candle->interval_time)->format('H:i');
            if ($timeKey === '09:15') continue;

            if ($ceTriggerIdx === null && in_array($filter, ['BOTH', 'CE'])) {
                if ((float) $candle->high >= $ceThreshold) {
                    $ceTriggerIdx = $idx;
                }
            }
            if ($peTriggerIdx === null && in_array($filter, ['BOTH', 'PE'])) {
                if ((float) $candle->low <= $peThreshold) {
                    $peTriggerIdx = $idx;
                }
            }
        }

        // Process CE trades for this day
        if ($ceTriggerIdx !== null) {
            $buyIdx = $ceTriggerIdx + 1;
            $buyCandle = $niftyCandles[$buyIdx] ?? null;
            if ($buyCandle) {
                $buyTime = Carbon::parse($buyCandle->interval_time)->format('H:i');
                $this->buildExitSlots(
                    $date, 'CE', $buyIdx, $buyTime,
                    $niftyCandles, $allSymbols,
                    $ceSlots
                );
            }
        }

        // Process PE trades for this day
        if ($peTriggerIdx !== null) {
            $buyIdx = $peTriggerIdx + 1;
            $buyCandle = $niftyCandles[$buyIdx] ?? null;
            if ($buyCandle) {
                $buyTime = Carbon::parse($buyCandle->interval_time)->format('H:i');
                $this->buildExitSlots(
                    $date, 'PE', $buyIdx, $buyTime,
                    $niftyCandles, $allSymbols,
                    $peSlots
                );
            }
        }
    }

    /**
     * For a given day + signal type, collect buy price for every symbol,
     * then for each subsequent 15-min candle compute exit scenario.
     */
    private function buildExitSlots(
        string $date,
        string $optionType,
        int    $buyIdx,
        string $buyTime,
        $niftyCandles,
        array  $allSymbols,
        array  &$slots
    ): void {
        // For each symbol: find highest-OI strike → buy price at buyTime
        $trades = []; // [symbol => [strike, expiry, buy_price, lot_size, investment]]

        foreach ($allSymbols as $symbol) {
            $strikeRow = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->whereDate('trade_date', $date)
                ->where('is_missing', 0)
                ->where('oi', '>', 0)
                ->orderByDesc('oi')
                ->first(['strike', 'expiry_date', 'oi']);

            if (!$strikeRow) continue;

            $strike     = $strikeRow->strike;
            $expiryDate = Carbon::parse($strikeRow->expiry_date)->toDateString();

            $buyCandle = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->where('strike', $strike)
                ->whereDate('expiry_date', $expiryDate)
                ->whereDate('trade_date', $date)
                ->where('is_missing', 0)
                ->whereRaw("TIME(interval_time) = ?", [$buyTime . ':00'])
                ->first(['open', 'close']);

            $buyPrice = $buyCandle ? (float) $buyCandle->open : 0;
            if ($buyPrice <= 0 && $buyCandle) $buyPrice = (float) $buyCandle->close;
            if ($buyPrice <= 0) continue;

            $lotSize    = $this->getLotSize($symbol);
            $investment = $buyPrice * $lotSize;

            $trades[$symbol] = [
                'strike'     => $strike,
                'expiry'     => $expiryDate,
                'buy_price'  => $buyPrice,
                'lot_size'   => $lotSize,
                'investment' => $investment,
            ];
        }

        if (empty($trades)) return;

        $totalInvestment = array_sum(array_column($trades, 'investment'));

        // For each exit candle (after buy candle, up to EOD)
        $exitCandleCount = $niftyCandles->count();

        for ($exitIdx = $buyIdx + 1; $exitIdx < $exitCandleCount; $exitIdx++) {
            $exitNiftyCandle = $niftyCandles[$exitIdx];
            $exitTime        = Carbon::parse($exitNiftyCandle->interval_time)->format('H:i');

            // Skip after 15:15 (market close)
            if ($exitTime > '15:15') break;

            $totalSellValue = 0;
            $validTrades    = 0;

            foreach ($trades as $symbol => $trade) {
                $exitCandle = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $trade['strike'])
                    ->whereDate('expiry_date', $trade['expiry'])
                    ->whereDate('trade_date', $date)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) = ?", [$exitTime . ':00'])
                    ->first(['open', 'close']);

                if (!$exitCandle) continue;

                // Sell at OPEN of exit candle (realistic market order)
                $sellPrice = (float) $exitCandle->open;
                if ($sellPrice <= 0) $sellPrice = (float) $exitCandle->close;
                if ($sellPrice <= 0) continue;

                $totalSellValue += $sellPrice * $trade['lot_size'];
                $validTrades++;
            }

            if ($validTrades === 0) continue;

            // Scale investment to only valid trades for fairness
            $scaledInvestment = 0;
            $vi = 0;
            foreach ($trades as $symbol => $trade) {
                // recheck which trades were valid at this exit time
                $scaledInvestment += $trade['investment'];
                $vi++;
            }

            $profit = $totalSellValue - $totalInvestment;
            $roi    = $totalInvestment > 0 ? round(($profit / $totalInvestment) * 100, 2) : 0;

            $key = $date . '|' . $exitTime;

            if (!isset($slots[$key])) {
                $slots[$key] = [
                    'date'        => $date,
                    'exit_time'   => $exitTime,
                    'sell_total'  => 0,
                    'investment'  => 0,
                    'profit'      => 0,
                    'trade_count' => 0,
                    'day_count'   => 0,
                ];
            }

            $slots[$key]['sell_total']  += round($totalSellValue, 2);
            $slots[$key]['investment']  += round($totalInvestment, 2);
            $slots[$key]['profit']      += round($profit, 2);
            $slots[$key]['trade_count'] += $validTrades;
            $slots[$key]['day_count']   += 1;
        }
    }

    /**
     * Convert slots array to sorted list with ROI computed.
     */
    private function summariseSlots(array $slots): array
    {
        $list = array_values($slots);

        // Group by exit_time across all dates for aggregate view
        $byTime = [];
        foreach ($list as $row) {
            $t = $row['exit_time'];
            if (!isset($byTime[$t])) {
                $byTime[$t] = [
                    'exit_time'   => $t,
                    'sell_total'  => 0,
                    'investment'  => 0,
                    'profit'      => 0,
                    'trade_count' => 0,
                    'day_count'   => 0,
                    'daily'       => [],
                ];
            }
            $byTime[$t]['sell_total']  += $row['sell_total'];
            $byTime[$t]['investment']  += $row['investment'];
            $byTime[$t]['profit']      += $row['profit'];
            $byTime[$t]['trade_count'] += $row['trade_count'];
            $byTime[$t]['day_count']   += $row['day_count'];
            $byTime[$t]['daily'][]      = $row;
        }

        foreach ($byTime as &$row) {
            $row['roi'] = $row['investment'] > 0
                ? round(($row['profit'] / $row['investment']) * 100, 2)
                : 0;
            $row['sell_total'] = round($row['sell_total'], 2);
            $row['investment'] = round($row['investment'], 2);
            $row['profit']     = round($row['profit'], 2);
        }
        unset($row);

        ksort($byTime);
        return array_values($byTime);
    }

    // =========================================================
    //  AVAILABLE SYMBOLS (for filter dropdown)
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->where('oi', '>', 0)
            ->distinct()->orderBy('base_symbol')
            ->pluck('base_symbol')->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  PROCESS ONE DAY — for main trade table
    // =========================================================

    private function processDay(string $date, float $threshold, string $filter, array $allSymbols): array
    {
        $niftyCandles = OptionOhlcData::where('base_symbol', 'NIFTY')
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close'])
            ->values();

        if ($niftyCandles->isEmpty()) return [];

        // ── CORRECTED: Use OPEN of first (09:15) candle as reference ──────────
        $openCandle = $niftyCandles->first();
        $openPrice  = (float) $openCandle->open;
        if ($openPrice <= 0) return [];

        $ceThreshold = $openPrice + $threshold;
        $peThreshold = $openPrice - $threshold;

        // ── First pass: find BOTH trigger candle indices ───────────────────────
        $ceTriggerIdx  = null;
        $peTriggerIdx  = null;
        $ceTriggerTime = null;
        $peTriggerTime = null;

        foreach ($niftyCandles as $idx => $candle) {
            $timeKey = Carbon::parse($candle->interval_time)->format('H:i');
            if ($timeKey === '09:15') continue;  // Skip opening candle

            if ($ceTriggerIdx === null && in_array($filter, ['BOTH', 'CE'])) {
                if ((float) $candle->high >= $ceThreshold) {
                    $ceTriggerIdx  = $idx;
                    $ceTriggerTime = $timeKey;
                }
            }
            if ($peTriggerIdx === null && in_array($filter, ['BOTH', 'PE'])) {
                if ((float) $candle->low <= $peThreshold) {
                    $peTriggerIdx  = $idx;
                    $peTriggerTime = $timeKey;
                }
            }
        }

        $results = [];

        // ── CE trades ─────────────────────────────────────────────────────────
        if ($ceTriggerIdx !== null) {
            $ceTriggerCandle  = $niftyCandles[$ceTriggerIdx];
            $ceTriggerHighVal = (float) $ceTriggerCandle->high;
            $ceTimeFmt        = Carbon::parse($ceTriggerCandle->interval_time)->format('H:i');

            // Buy candle = NEXT candle after trigger (candle completes at next bar open)
            $nextCe    = $niftyCandles[$ceTriggerIdx + 1] ?? null;
            $ceBuyTime = $nextCe
                ? Carbon::parse($nextCe->interval_time)->format('H:i')
                : $ceTimeFmt;

            foreach ($allSymbols as $symbol) {
                $row = $this->buildSymbolTrade(
                    $symbol, $date, 'CE',
                    $openPrice, $ceTriggerHighVal, $ceTimeFmt,
                    $ceBuyTime, $threshold
                );
                if ($row) $results[] = $row;
            }
        }

        // ── PE trades ─────────────────────────────────────────────────────────
        if ($peTriggerIdx !== null) {
            $peTriggerCandle = $niftyCandles[$peTriggerIdx];
            $peTriggerLowVal = (float) $peTriggerCandle->low;
            $peTimeFmt       = Carbon::parse($peTriggerCandle->interval_time)->format('H:i');

            $nextPe    = $niftyCandles[$peTriggerIdx + 1] ?? null;
            $peBuyTime = $nextPe
                ? Carbon::parse($nextPe->interval_time)->format('H:i')
                : $peTimeFmt;

            foreach ($allSymbols as $symbol) {
                $row = $this->buildSymbolTrade(
                    $symbol, $date, 'PE',
                    $openPrice, $peTriggerLowVal, $peTimeFmt,
                    $peBuyTime, $threshold
                );
                if ($row) $results[] = $row;
            }
        }

        return $results;
    }

    // =========================================================
    //  BUILD ONE SYMBOL TRADE ROW (no high/low window, just buy)
    // =========================================================

    private function buildSymbolTrade(
        string $symbol,
        string $date,
        string $optionType,
        float  $niftyOpen,
        float  $niftyTriggerVal,
        string $triggerTime,
        string $buyTime,
        float  $threshold
    ): ?array {

        // Highest-OI strike for this symbol on this date
        $strikeRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->where('oi', '>', 0)
            ->orderByDesc('oi')
            ->first(['strike', 'expiry_date', 'trading_symbol', 'oi']);

        if (!$strikeRow) return null;

        $strike     = $strikeRow->strike;
        $expiryDate = Carbon::parse($strikeRow->expiry_date)->toDateString();
        $optSymbol  = $strikeRow->trading_symbol ?? "{$symbol}{$optionType}{$strike}";

        // Buy candle at buyTime
        $buyCandle = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->where('strike', $strike)
            ->whereDate('expiry_date', $expiryDate)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) = ?", [$buyTime . ':00'])
            ->first(['open', 'close', 'high', 'low']);

        $buyPrice = $buyCandle ? (float) $buyCandle->open : 0;
        if ($buyPrice <= 0 && $buyCandle) $buyPrice = (float) $buyCandle->close;
        if ($buyPrice <= 0) return null;

        $lotSize    = $this->getLotSize($symbol);
        $investment = round($buyPrice * $lotSize, 2);

        return [
            'date'          => $date,
            'symbol'        => $symbol,
            'signal_type'   => $optionType,

            // NIFTY context
            'nifty_open'    => round($niftyOpen, 2),
            'nifty_trigger' => round($niftyTriggerVal, 2),
            'trigger_time'  => $triggerTime,
            'nifty_move'    => round($niftyTriggerVal - $niftyOpen, 2),
            'threshold'     => $threshold,

            // Option details
            'option_symbol' => $optSymbol,
            'strike'        => $strike,
            'expiry_date'   => $expiryDate,
            'strike_oi'     => (int) ($strikeRow->oi ?? 0),

            // Buy (at OPEN of candle AFTER the trigger candle completes)
            'buy_time'      => $buyTime,
            'buy_price'     => round($buyPrice, 2),
            'lot_size'      => $lotSize,
            'investment'    => $investment,
        ];
    }

    // =========================================================
    //  LOT SIZE
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $defaults = [
            'NIFTY'      => 25,
            'BANKNIFTY'  => 15,
            'FINNIFTY'   => 25,
            'MIDCPNIFTY' => 50,
            'SENSEX'     => 10,
            'BANKEX'     => 15,
        ];

        $fromDb = DB::table('zerodha_instruments')
            ->where('name', $symbol)->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])->value('lot_size');

        return $fromDb ? (int) $fromDb : ($defaults[$symbol] ?? 1);
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}