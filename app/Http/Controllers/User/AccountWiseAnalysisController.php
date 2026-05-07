<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * AccountWiseAnalysisController
 *
 * ZZL Account Symbols: ASIANPAINT, AUROPHARMA, BSE, MCX, BDL
 * OQJ Account Symbols: DRREDDY, CHOLAFIN, AXISBANK, HEROMOTOCO, BAJAJFINSV, BAJAJ-AUTO, CDSL, ADANIPORTS
 *
 * Logic:
 *  - Signal detected at EOD (3:00 PM / 14:45 candle for OI, 15:00 for price)
 *  - Buy price  = ATM option close at 15:15 on signal day (fallback: 15:00 if 15:15 missing)
 *  - Exit price = MAX(high) of 09:15, 09:30, 09:45 candles on next trading day
 *  - High price = MAX(high) from signal day 15:15 → next day 09:45 candle
 *  - Low  price = MIN(low)  from signal day 15:15 → next day 09:45 candle
 *  - Investment per stock = user-selected (default 10 Lakh)
 *  - Lots = floor(investment / (buy_price × lot_size))
 *
 * NOTE on column types:
 *   trade_date    = DATETIME  e.g. "2026-02-02 09:15:00"
 *   interval_time = DATETIME  e.g. "2026-02-02 09:15:00"
 *   Always use whereDate() for date comparisons, TIME() for time comparisons.
 */
class AccountWiseAnalysisController extends Controller
{
    // =========================================================
    //  ACCOUNT SYMBOL MAPS
    // =========================================================

    private const ACCOUNT_SYMBOLS = [
        'ZZL' => [
            'ASIANPAINT',
            'AUROPHARMA',
            'BSE',
            'MCX',
            'BDL',
        ],
        'OQJ' => [
            'DRREDDY',
            'CHOLAFIN',
            'AXISBANK',
            'HEROMOTOCO',
            'BAJAJFINSV',
            'BAJAJ-AUTO',
            'CDSL',
            'ADANIPORTS',
        ],
    ];

    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'Account Wise EOD Analysis — ZZL & OQJ';
        return view($this->activeTemplate . 'user.account-wise.index', compact('pageTitle'));
    }

    // =========================================================
    //  EXPIRY HELPERS  (mirrors OIIVAutoController exactly)
    // =========================================================

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

    // =========================================================
    //  MAIN ANALYSIS ENDPOINT
    // =========================================================

    /**
     * GET /account-wise/analyze
     * Params: from_date, to_date, account (ZZL|OQJ|both), investment
     */
    public function analyze(Request $request)
    {
        try {
            $fromDate   = $request->get('from_date');
            $toDate     = $request->get('to_date');
            $account    = $request->get('account', 'both');   // ZZL | OQJ | both
            $investment = (float) $request->get('investment', 1000000); // default 10L

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            $results = [];

            $accounts = $account === 'both' ? ['ZZL', 'OQJ'] : [strtoupper($account)];

            foreach ($accounts as $acc) {
                $symbols = self::ACCOUNT_SYMBOLS[$acc] ?? [];
                if (empty($symbols)) continue;

                // Get all trade dates in range that have FUT data
                $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                    ->whereIn('base_symbol', $symbols)
                    ->whereDate('trade_date', '>=', $fromDate)
                    ->whereDate('trade_date', '<=', $toDate)
                    ->select(DB::raw('DATE(trade_date) as d'))
                    ->distinct()
                    ->orderBy('d')
                    ->pluck('d')
                    ->toArray();

                foreach ($tradeDates as $date) {
                    $prevDate = $this->getPreviousTradingDate($date);
                    $rows     = $this->buildRowsForDate($date, $prevDate, $symbols, $acc, $investment);
                    foreach ($rows as $row) {
                        $results[] = $row;
                    }
                }
            }

            // Sort: newest date first, then by account, then symbol
            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date']
                    ?: $a['account'] <=> $b['account']
                        ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('AccountWiseAnalysis Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR ONE DATE
    // =========================================================

    private function buildRowsForDate(
        string $date,
        string $prevDate,
        array  $symbols,
        string $account,
        float  $investment
    ): array {
        // FUT candle at 14:45 — used for OI comparison and spot price
        $futCandles = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereIn('base_symbol', $symbols)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->get()
            ->keyBy('base_symbol');

        // FUT candle at 15:00 — used as buy price proxy for ATM option lookup
        $futClose = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereIn('base_symbol', $symbols)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->get()
            ->keyBy('base_symbol');

        if ($futCandles->isEmpty() && $futClose->isEmpty()) return [];

        $activeSymbols = $futCandles->keys()->merge($futClose->keys())->unique()->toArray();

        $rows = [];

        foreach ($activeSymbols as $symbol) {
            try {
                $fut1445 = $futCandles[$symbol]   ?? null;
                $fut1500 = $futClose[$symbol]      ?? null;
                $futRow  = $fut1445 ?? $fut1500;

                if (!$futRow) continue;

                $spotPrice = (float) ($fut1500->close ?? $fut1445->close ?? 0);
                if ($spotPrice <= 0) $spotPrice = (float) ($futRow->open ?? 0);
                if ($spotPrice <= 0) continue;

                // ── Expiry resolution ─────────────────────────────────────
                $rawExpiry     = $this->getNearestExpiryForDate($symbol, $date);
                $isExpiryDay   = ($rawExpiry !== null && $rawExpiry === $date);
                $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
                $prevExpiry    = $currentExpiry
                    ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                    : null;

                // ── Today CE/PE OI @ 14:45 ───────────────────────────────
                $todayCeOI = $this->sumOI($symbol, $date, 'CE', '14:45:00', $currentExpiry);
                $todayPeOI = $this->sumOI($symbol, $date, 'PE', '14:45:00', $currentExpiry);

                if ($todayCeOI == 0 && $todayPeOI == 0) continue;

                // ── Prev day CE/PE OI @ 15:00 ────────────────────────────
                $prevCeOI = $this->sumOI($symbol, $prevDate, 'CE', '15:00:00', $prevExpiry);
                $prevPeOI = $this->sumOI($symbol, $prevDate, 'PE', '15:00:00', $prevExpiry);

                // ── OI change % ───────────────────────────────────────────
                $ceOiPct = $prevCeOI > 0 ? round((($todayCeOI - $prevCeOI) / $prevCeOI) * 100, 4) : 0;
                $peOiPct = $prevPeOI > 0 ? round((($todayPeOI - $prevPeOI) / $prevPeOI) * 100, 4) : 0;

                // ── OI Signal ─────────────────────────────────────────────
                $oiSignal    = $this->getOISignal($ceOiPct, $peOiPct);
                $tradeAction = match ($oiSignal['signal']) {
                    'BULLISH' => 'BUY CE',
                    'BEARISH' => 'BUY PE',
                    default   => 'WAIT',
                };

                // ── P/C Ratio ─────────────────────────────────────────────
                $peCeRatio = $todayCeOI > 0 ? round($todayPeOI / $todayCeOI, 2) : 0;

                // ── Strength ──────────────────────────────────────────────
                $diff = abs($ceOiPct - $peOiPct);
                if      ($diff > 40) $strengthRank = 'Rank 1';
                elseif  ($diff > 25) $strengthRank = 'Rank 2';
                elseif  ($diff > 10) $strengthRank = 'Rank 3';
                elseif  ($diff > 5)  $strengthRank = 'Rank 4';
                else                 $strengthRank = 'Normal';

                // ── Lot size + ATM option lookup ──────────────────────────
                $optionType = $tradeAction === 'BUY CE' ? 'CE' : ($tradeAction === 'BUY PE' ? 'PE' : null);

                $profitData = ['option_symbol' => null, 'strike' => null, 'buy_price' => 0,
                               'lot_size' => 0, 'lots_bought' => 0, 'investment_actual' => 0,
                               'exit_price' => 0, 'exit_pl' => 0, 'exit_roi' => 0,
                               'high_price' => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
                               'low_price' => 0,  'low_time' => null,  'low_pl'  => 0, 'low_roi'  => 0,
                               'error' => null];

                if ($optionType) {
                    $profitData = $this->calculateProfitForRow(
                        $symbol, $date, $optionType, $spotPrice, $currentExpiry, $investment
                    );
                }

                $rows[] = [
                    'account'    => $account,
                    'date'       => $date,
                    'symbol'     => $symbol,
                    'spot_price' => round($spotPrice, 2),

                    'ce_oi'            => $todayCeOI,
                    'ce_oi_prev'       => $prevCeOI,
                    'ce_oi_change_pct' => $ceOiPct,

                    'pe_oi'            => $todayPeOI,
                    'pe_oi_prev'       => $prevPeOI,
                    'pe_oi_change_pct' => $peOiPct,

                    'oi_condition' => $oiSignal['condition'],
                    'sentiment'    => $oiSignal['signal'],
                    'trade_action' => $tradeAction,

                    'pe_ce_ratio'  => $peCeRatio,
                    'strength_rank'=> $strengthRank,
                    'strength_diff'=> round($diff, 2),

                    'is_expiry_day'  => $isExpiryDay,
                    'current_expiry' => $currentExpiry,

                    // Profit/Loss fields
                    'option_symbol'     => $profitData['option_symbol'],
                    'strike'            => $profitData['strike'],
                    'lot_size'          => $profitData['lot_size'],
                    'lots_bought'       => $profitData['lots_bought'],
                    'buy_price'         => $profitData['buy_price'],
                    'investment_actual' => $profitData['investment_actual'],
                    'exit_price'        => $profitData['exit_price'],
                    'exit_pl'           => $profitData['exit_pl'],
                    'exit_roi'          => $profitData['exit_roi'],
                    'high_price'        => $profitData['high_price'],
                    'high_time'         => $profitData['high_time'],
                    'high_pl'           => $profitData['high_pl'],
                    'high_roi'          => $profitData['high_roi'],
                    'low_price'         => $profitData['low_price'],
                    'low_time'          => $profitData['low_time'],
                    'low_pl'            => $profitData['low_pl'],
                    'low_roi'           => $profitData['low_roi'],
                    'profit_error'      => $profitData['error'],
                ];

            } catch (\Exception $e) {
                Log::error("AccountWise row error ({$account}/{$symbol}/{$date}): " . $e->getMessage());
            }
        }

        return $rows;
    }

    // =========================================================
    //  PROFIT CALCULATION (inline — no separate AJAX needed)
    // =========================================================

    /**
     * Calculate full profit/loss for one signal row.
     *
     * Buy  = ATM option close at 15:15 on signal day (fallback: 15:00 if 15:15 missing)
     * Exit = MAX(high) of 09:15, 09:30, 09:45 candles on next trading day
     * High = MAX(high) from signal day 15:15 → next day up to 09:45
     * Low  = MIN(low)  from signal day 15:15 → next day up to 09:45
     *
     * Lots = floor(investment / (buyPrice × lotSize))
     * investment_actual = lots × buyPrice × lotSize
     */
    private function calculateProfitForRow(
        string  $symbol,
        string  $date,
        string  $optionType,
        float   $spotPrice,
        ?string $currentExpiry,
        float   $investment
    ): array {
        $blank = [
            'option_symbol' => null, 'strike' => null,
            'lot_size' => 0, 'lots_bought' => 0, 'buy_price' => 0, 'investment_actual' => 0,
            'exit_price' => 0, 'exit_pl' => 0, 'exit_roi' => 0,
            'high_price' => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
            'low_price'  => 0, 'low_time'  => null, 'low_pl'  => 0, 'low_roi'  => 0,
            'error' => null,
        ];

        try {
            $nextDate = $this->getNextTradingDate($date);
            $lotSize  = $this->getLotSize($symbol);

            // ── Find ATM option @ 15:15 on signal day (PRIMARY) ──────────
            $atmQuery = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->whereDate('trade_date', $date)
                ->where('strike_position', 'ATM')
                ->where('is_missing', 0)
                ->whereNotNull('expiry_date')
                ->whereRaw("TIME(interval_time) = '15:15:00'");

            if ($currentExpiry) $atmQuery->whereDate('expiry_date', $currentExpiry);
            $atmRow = $atmQuery->orderBy('expiry_date')->first();

            // ── Fallback 1: ATM strike_position @ 15:00 ──────────────────
            if (!$atmRow) {
                $fbAtm = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $date)
                    ->where('strike_position', 'ATM')
                    ->where('is_missing', 0)
                    ->whereNotNull('expiry_date')
                    ->whereRaw("TIME(interval_time) = '15:00:00'");
                if ($currentExpiry) $fbAtm->whereDate('expiry_date', $currentExpiry);
                $atmRow = $fbAtm->orderBy('expiry_date')->first();
            }

            // ── Fallback 2: nearest strike by spot price @ 15:15 ─────────
            if (!$atmRow) {
                $fb = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $date)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) = '15:15:00'")
                    ->whereNotNull('strike')
                    ->whereNotNull('expiry_date');
                if ($currentExpiry) $fb->whereDate('expiry_date', $currentExpiry);
                $atmRow = $fb->orderByRaw('ABS(strike - ?)', [$spotPrice])->orderBy('expiry_date')->first();
            }

            // ── Fallback 3: nearest strike by spot price @ 15:00 ─────────
            if (!$atmRow) {
                $fb = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $date)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) = '15:00:00'")
                    ->whereNotNull('strike')
                    ->whereNotNull('expiry_date');
                if ($currentExpiry) $fb->whereDate('expiry_date', $currentExpiry);
                $atmRow = $fb->orderByRaw('ABS(strike - ?)', [$spotPrice])->orderBy('expiry_date')->first();
            }

            if (!$atmRow) {
                $blank['error'] = 'NO_ATM_ROW';
                return $blank;
            }

            $strike     = $atmRow->strike;
            $expiryDate = substr($atmRow->expiry_date, 0, 10);
            $buyPrice   = (float) ($atmRow->close ?? 0);
            if ($buyPrice <= 0) $buyPrice = (float) ($atmRow->open ?? 0);

            if ($buyPrice <= 0) {
                $blank['error']         = 'NO_BUY_PRICE';
                $blank['option_symbol'] = $atmRow->trading_symbol ?? null;
                $blank['strike']        = $strike;
                $blank['lot_size']      = $lotSize;
                return $blank;
            }

            // ── Lots = floor(investment / (buyPrice × lotSize)) ───────────
            $costPerLot      = $buyPrice * $lotSize;
            $lots            = $costPerLot > 0 ? (int) floor($investment / $costPerLot) : 0;
            $investmentActual = round($lots * $costPerLot, 2);

            if ($lots <= 0) {
                $blank['error']         = 'INSUFFICIENT_INVESTMENT';
                $blank['option_symbol'] = $atmRow->trading_symbol ?? null;
                $blank['strike']        = $strike;
                $blank['lot_size']      = $lotSize;
                $blank['buy_price']     = round($buyPrice, 2);
                return $blank;
            }

            // ── Exit price: MAX(high) of 09:15, 09:30, 09:45 on next day ─
            $exitCandles = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->where('strike', $strike)
                ->whereDate('expiry_date', $expiryDate)
                ->whereDate('trade_date', $nextDate)
                ->where('is_missing', 0)
                ->whereRaw("TIME(interval_time) IN ('09:15:00','09:30:00','09:45:00')")
                ->get(['high', 'open', 'close', 'interval_time']);

            // ── Fallback: if original strike missing, use nearest available strike ─
            if ($exitCandles->isEmpty()) {
                $exitCandles = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $nextDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) IN ('09:15:00','09:30:00','09:45:00')")
                    ->orderByRaw('ABS(strike - ?)', [$strike])
                    ->get(['high', 'open', 'close', 'interval_time', 'strike']);
            }

            $exitPrice = 0;
            $exitRow   = null;
            if ($exitCandles->isNotEmpty()) {
                // Take the candle with the maximum high
                $exitRow   = $exitCandles->sortByDesc('high')->first();
                $exitPrice = (float) ($exitRow->high ?? 0);
                // If high is missing/zero, fallback to close then open
                if ($exitPrice <= 0) $exitPrice = (float) ($exitRow->close ?? $exitRow->open ?? 0);
            }

            // ── Window candles: signal day 15:15 → next day 09:45 ────────
            $windowCandles = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->where('strike', $strike)
                ->whereDate('expiry_date', $expiryDate)
                ->where('is_missing', 0)
                ->where(function ($q) use ($date, $nextDate) {
                    $q->where(function ($q2) use ($date) {
                        $q2->whereDate('trade_date', $date)
                           ->whereRaw("TIME(interval_time) >= '15:15:00'");
                    })->orWhere(function ($q2) use ($nextDate) {
                        $q2->whereDate('trade_date', $nextDate)
                           ->whereRaw("TIME(interval_time) <= '09:45:00'");
                    });
                })
                ->get(['high', 'low', 'interval_time']);

            $highPrice = $highTime = $lowPrice = $lowTime = null;

            if ($windowCandles->isNotEmpty()) {
                $highRow   = $windowCandles->sortByDesc('high')->first();
                $lowRow    = $windowCandles->sortBy('low')->first();
                $highPrice = (float) $highRow->high;
                $lowPrice  = (float) $lowRow->low;
                $highTime  = Carbon::parse($highRow->interval_time)->format('H:i');
                $lowTime   = Carbon::parse($lowRow->interval_time)->format('H:i');
            } else {
                // Fallback to exit candle OHLC
                $highPrice = $exitRow ? (float) ($exitRow->high ?? $buyPrice) : $buyPrice;
                $lowPrice  = $exitRow ? (float) ($exitRow->low  ?? $buyPrice) : $buyPrice;
            }

            $highPrice = $highPrice ?: $buyPrice;
            $lowPrice  = $lowPrice  ?: $buyPrice;

            // ── P/L calculations (based on lots × lotSize contracts) ──────
            $qty = $lots * $lotSize;   // total contracts

            $exitPL  = $exitPrice > 0 ? round(($exitPrice - $buyPrice) * $qty, 2) : 0;
            $exitRoi = $investmentActual > 0 && $exitPrice > 0
                ? round(($exitPL / $investmentActual) * 100, 2) : 0;

            $highPL  = round(($highPrice - $buyPrice) * $qty, 2);
            $highRoi = $investmentActual > 0 ? round(($highPL / $investmentActual) * 100, 2) : 0;

            $lowPL   = round(($lowPrice - $buyPrice) * $qty, 2);
            $lowRoi  = $investmentActual > 0 ? round(($lowPL / $investmentActual) * 100, 2) : 0;

            return [
                'option_symbol'     => $atmRow->trading_symbol ?? "{$symbol}{$optionType}{$strike}",
                'strike'            => $strike,
                'lot_size'          => $lotSize,
                'lots_bought'       => $lots,
                'buy_price'         => round($buyPrice, 2),
                'investment_actual' => $investmentActual,
                'exit_price'        => round($exitPrice, 2),
                'exit_pl'           => $exitPL,
                'exit_roi'          => $exitRoi,
                'high_price'        => round($highPrice, 2),
                'high_time'         => $highTime,
                'high_pl'           => $highPL,
                'high_roi'          => $highRoi,
                'low_price'         => round($lowPrice, 2),
                'low_time'          => $lowTime,
                'low_pl'            => $lowPL,
                'low_roi'           => $lowRoi,
                'error'             => null,
            ];

        } catch (\Exception $e) {
            Log::error("calculateProfitForRow ({$symbol}/{$date}): " . $e->getMessage());
            $blank['error'] = 'EXCEPTION';
            return $blank;
        }
    }

    // =========================================================
    //  OI HELPERS
    // =========================================================

    private function sumOI(string $symbol, string $date, string $type, string $time, ?string $expiry): int
    {
        $q = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = ?", [$time]);

        if ($expiry) $q->whereDate('expiry_date', $expiry);

        return (int) $q->sum('oi');
    }

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  LOT SIZE HELPER
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $lots = [
            'NIFTY'       => 25,
            'BANKNIFTY'   => 15,
            'FINNIFTY'    => 25,
            'MIDCPNIFTY'  => 50,
            'SENSEX'      => 10,
            'BANKEX'      => 15,
        ];

        $instrument = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->value('lot_size');

        if ($instrument) return (int) $instrument;

        return $lots[$symbol] ?? 1;
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

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