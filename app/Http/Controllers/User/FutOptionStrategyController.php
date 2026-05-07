<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FutOptionStrategyController
 *
 * ══════════════════════════════════════════════════════════════
 *  STRATEGY LOGIC
 * ══════════════════════════════════════════════════════════════
 *
 *  OI Signal (same as OIIVAutoController, measured at 14:45):
 *    BULLISH  →  BUY  1 lot FUT  +  SELL 2 lots CE (ATM)
 *    BEARISH  →  SELL 1 lot FUT  +  SELL 2 lots PE (ATM)
 *    NEUTRAL  →  WAIT (no trade)
 *
 *  POSITION (entry):
 *    Date  = Signal date (today)
 *    Time  = 14:45 candle close
 *    Price = FUT close at 14:45  |  Option close at 14:45
 *
 *  EXIT:
 *    Window  = NEXT trading day  09:15 → 10:30
 *    FUT BUY  → exit at MAX HIGH candle in window
 *    FUT SELL → exit at MIN LOW  candle in window
 *    OPT SELL → exit at MIN LOW  candle in window (max premium decay for seller)
 *    exit_time = exact candle time where best exit occurred
 *    exit_date = next trading day
 *
 *  P/L:
 *    FUT BUY   = (exit_price − position_price) × lot_size × 1
 *    FUT SELL  = (position_price − exit_price) × lot_size × 1
 *    OPT SELL  = (position_price − exit_price) × lot_size × 2
 *    Combined  = FUT P/L + OPT P/L
 *
 * ══════════════════════════════════════════════════════════════
 */
class FutOptionStrategyController extends Controller
{
    // Exit window: next trading day candles between these times
    private const EXIT_FROM = '09:15:00';
    private const EXIT_TO   = '10:30:00';

    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'FUT + Option Sell Strategy';
        return view($this->activeTemplate . 'user.fut-option-strategy.index', compact('pageTitle'));
    }

    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  MAIN ENDPOINT
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both dates',
                    'data'    => [],
                ]);
            }

            // All distinct trade dates in range (signal dates)
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            $results = [];

            foreach ($tradeDates as $signalDate) {
                $prevDate = $this->getPreviousTradingDate($signalDate);
                $nextDate = $this->getNextTradingDate($signalDate);   // EXIT date

                foreach ($this->buildRowsForDate(
                    $signalDate, $prevDate, $nextDate,
                    $selectedSymbols, $filterAction
                ) as $row) {
                    $results[] = $row;
                }
            }

            // Sort: newest signal date first, then symbol A-Z
            usort($results, fn($a, $b) =>
                $b['signal_date'] <=> $a['signal_date'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('FutOptionStrategy::analyze — ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR ONE SIGNAL DATE
    // =========================================================

    private function buildRowsForDate(
        string  $signalDate,
        string  $prevDate,
        string  $nextDate,          // EXIT date
        array   $symbolFilter,
        ?string $actionFilter
    ): array {

        // ── All FUT symbols with a 14:45 candle on signal date ────────────
        $futQuery = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) {
            $futQuery->whereIn('base_symbol', $symbolFilter);
        }

        $futCandles = $futQuery->get()->keyBy('base_symbol');
        if ($futCandles->isEmpty()) return [];

        $rows = [];

        foreach ($futCandles->keys() as $symbol) {
            $futEntry = $futCandles[$symbol];
            $futPositionPrice = (float) $futEntry->close;
            if ($futPositionPrice <= 0) continue;

            // ── Expiry resolution ─────────────────────────────────────────
            $currentExpiry = $this->resolveActiveExpiry($symbol, $signalDate);
            $prevExpiry    = $currentExpiry
                ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                : null;

            // ── OI: today at 14:45 ────────────────────────────────────────
            $ceCurOI = (int) OptionOhlcData::whereDate('trade_date', $signalDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
                ->sum('oi');

            $peCurOI = (int) OptionOhlcData::whereDate('trade_date', $signalDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
                ->sum('oi');

            if ($ceCurOI == 0 && $peCurOI == 0) continue;

            // ── OI: prev day at 15:00 (baseline) ─────────────────────────
            $ceOpenOI = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))
                ->sum('oi');

            $peOpenOI = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))
                ->sum('oi');

            // ── OI % change ───────────────────────────────────────────────
            $cePct = $ceOpenOI > 0
                ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $pePct = $peOpenOI > 0
                ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;

            // ── OI Signal (same logic as OIIVAutoController) ──────────────
            $oiResult    = $this->getOISignal($cePct, $pePct);
            $oiSignal    = $oiResult['signal'];    // BULLISH / BEARISH / NEUTRAL
            $oiCondition = $oiResult['condition'];

            // ── Trade action mapping ───────────────────────────────────────
            //   BULLISH → BUY  FUT + SELL CE (2 lots)
            //   BEARISH → SELL FUT + SELL PE (2 lots)
            //   NEUTRAL → WAIT
            $tradeAction  = 'WAIT';
            $optionAction = null;
            $optionType   = null;

            if ($oiSignal === 'BULLISH') {
                $tradeAction  = 'BUY FUT';
                $optionAction = 'SELL CE';
                $optionType   = 'CE';
            } elseif ($oiSignal === 'BEARISH') {
                $tradeAction  = 'SELL FUT';
                $optionAction = 'SELL PE';
                $optionType   = 'PE';
            }

            // Apply action filter (skip WAIT rows if filtering)
            if (!empty($actionFilter) && $tradeAction !== $actionFilter) continue;

            $lotSize = $this->getLotSize($symbol);

            // ── Calculate FUT P/L ─────────────────────────────────────────
            $futResult = $this->calcFutPL(
                $symbol, $signalDate, $nextDate,
                $futPositionPrice, $tradeAction, $lotSize
            );

            // ── Calculate Option SELL P/L ─────────────────────────────────
            $optResult = $this->calcOptionSellPL(
                $symbol, $signalDate, $nextDate,
                $optionType, $futPositionPrice, $currentExpiry, $lotSize
            );

            $combinedPL = round(
                ($futResult['pl'] ?? 0) + ($optResult['pl'] ?? 0),
                2
            );

            $rows[] = [
                // ── Identification ─────────────────────────────────────────
                'signal_date'   => $signalDate,    // day we take position
                'exit_date_exp' => $nextDate,      // expected exit day
                'symbol'        => $symbol,
                'fut_symbol'    => $futEntry->trading_symbol ?? $symbol . 'FUT',
                'lot_size'      => $lotSize,

                // ── OI Data ────────────────────────────────────────────────
                'ce_oi_cur'    => $ceCurOI,
                'pe_oi_cur'    => $peCurOI,
                'ce_oi_prev'   => $ceOpenOI,
                'pe_oi_prev'   => $peOpenOI,
                'ce_oi_pct'    => round($cePct, 2),
                'pe_oi_pct'    => round($pePct, 2),
                'oi_signal'    => $oiSignal,
                'oi_condition' => $oiCondition,

                // ── Actions ────────────────────────────────────────────────
                'trade_action'  => $tradeAction,
                'option_action' => $optionAction,
                'option_type'   => $optionType,

                // ── FUT Trade ──────────────────────────────────────────────
                // Position = entry on signal date at 14:45
                'fut_position_date'  => $tradeAction !== 'WAIT' ? $signalDate  : null,
                'fut_position_time'  => $tradeAction !== 'WAIT' ? '14:45'       : null,
                'fut_position_price' => $tradeAction !== 'WAIT' ? round($futPositionPrice, 2) : null,
                // Exit = next trading day within 09:15-10:30 window
                'fut_exit_date'      => $futResult['exit_date'],
                'fut_exit_time'      => $futResult['exit_time'],
                'fut_exit_price'     => $futResult['exit_price'],
                'fut_pl'             => $futResult['pl'],
                'fut_pl_pct'         => $futResult['pl_pct'],

                // ── Option Trade ───────────────────────────────────────────
                // Position = sell at 14:45 on signal date
                'opt_symbol'         => $optResult['option_symbol'],
                'opt_strike'         => $optResult['strike'],
                'opt_position_date'  => ($tradeAction !== 'WAIT' && !$optResult['error']) ? $signalDate : null,
                'opt_position_time'  => ($tradeAction !== 'WAIT' && !$optResult['error']) ? '14:45'      : null,
                'opt_position_price' => $optResult['position_price'],
                // Exit = next trading day within 09:15-10:30 window (buy back at min low)
                'opt_exit_date'      => $optResult['exit_date'],
                'opt_exit_time'      => $optResult['exit_time'],
                'opt_exit_price'     => $optResult['exit_price'],
                'opt_pl'             => $optResult['pl'],
                'opt_pl_pct'         => $optResult['pl_pct'],
                'opt_error'          => $optResult['error'],
                'opt_lots'           => 2,

                // ── Combined ───────────────────────────────────────────────
                'combined_pl'        => $combinedPL,

                // ── Meta ───────────────────────────────────────────────────
                'current_expiry' => $currentExpiry,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  FUT P/L  (exit next trading day 09:15 → 10:30)
    // =========================================================

    private function calcFutPL(
        string $symbol,
        string $signalDate,
        string $nextDate,
        float  $positionPrice,
        string $tradeAction,
        int    $lotSize
    ): array {
        $no = [
            'exit_price' => null,
            'exit_time'  => null,
            'exit_date'  => null,
            'pl'         => 0,
            'pl_pct'     => 0,
        ];

        if ($tradeAction === 'WAIT') return $no;

        // Exit window: NEXT trading day only, 09:15 to 10:30
        $candles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $nextDate)
            ->whereRaw("TIME(interval_time) >= '" . self::EXIT_FROM . "'")
            ->whereRaw("TIME(interval_time) <= '" . self::EXIT_TO   . "'")
            ->get(['high', 'low', 'close', 'interval_time', DB::raw('DATE(trade_date) as cdate')]);

        if ($candles->isEmpty()) return $no;

        if ($tradeAction === 'BUY FUT') {
            // Seller gets best (highest) price → we pick MAX HIGH candle
            $best      = $candles->sortByDesc('high')->first();
            $exitPrice = (float) $best->high;
            $pl        = round(($exitPrice - $positionPrice) * $lotSize, 2);
            $plPct     = $positionPrice > 0
                ? round((($exitPrice - $positionPrice) / $positionPrice) * 100, 2) : 0;
        } else {
            // SELL FUT: we shorted → best exit = MIN LOW (price fell most)
            $best      = $candles->sortBy('low')->first();
            $exitPrice = (float) $best->low;
            $pl        = round(($positionPrice - $exitPrice) * $lotSize, 2);
            $plPct     = $positionPrice > 0
                ? round((($positionPrice - $exitPrice) / $positionPrice) * 100, 2) : 0;
        }

        return [
            'exit_price' => round($exitPrice, 2),
            'exit_time'  => Carbon::parse($best->interval_time)->format('H:i'),
            'exit_date'  => is_string($best->cdate)
                ? $best->cdate
                : Carbon::parse($best->cdate)->toDateString(),
            'pl'     => $pl,
            'pl_pct' => $plPct,
        ];
    }

    // =========================================================
    //  OPTION SELL P/L  (exit next trading day 09:15 → 10:30)
    // =========================================================

    private function calcOptionSellPL(
        string  $symbol,
        string  $signalDate,
        string  $nextDate,
        ?string $optionType,
        float   $spotPrice,
        ?string $expiry,
        int     $lotSize
    ): array {
        $no = [
            'option_symbol'  => null,
            'strike'         => null,
            'position_price' => null,
            'exit_price'     => null,
            'exit_time'      => null,
            'exit_date'      => null,
            'pl'             => 0,
            'pl_pct'         => 0,
            'error'          => null,
        ];

        if (!$optionType) return $no;

        // ── Find ATM option entry at 14:45 on signal date ─────────────────
        $atmQ = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $signalDate)
            ->where('strike_position', 'ATM')
            ->where('is_missing', 0)
            ->whereNotNull('expiry_date')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if ($expiry) $atmQ->whereDate('expiry_date', $expiry);

        $atmRow = $atmQ->orderBy('expiry_date')->first();

        // Fallback: nearest strike to spot price
        if (!$atmRow) {
            $fb = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->whereDate('trade_date', $signalDate)
                ->where('is_missing', 0)
                ->whereNotNull('strike')
                ->whereNotNull('expiry_date')
                ->whereRaw("TIME(interval_time) = '14:45:00'");

            if ($expiry) $fb->whereDate('expiry_date', $expiry);

            $atmRow = $fb->orderByRaw('ABS(strike - ?)', [$spotPrice])
                ->orderBy('expiry_date')
                ->first();
        }

        if (!$atmRow) {
            $no['error'] = 'NO_ATM';
            return $no;
        }

        $strike        = (float) $atmRow->strike;
        $expiryDate    = substr($atmRow->expiry_date, 0, 10);
        $positionPrice = (float) ($atmRow->close ?: $atmRow->open);  // premium sold

        if ($positionPrice <= 0) {
            $no['error']          = 'ZERO_PREMIUM';
            $no['option_symbol']  = $atmRow->trading_symbol ?? null;
            $no['strike']         = $strike;
            return $no;
        }

        // ── Exit window: NEXT trading day 09:15 → 10:30 ──────────────────
        // As option SELLER we want to BUY BACK at minimum price (max decay)
        $candles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->where('strike', $strike)
            ->whereDate('expiry_date', $expiryDate)
            ->whereDate('trade_date', $nextDate)
            ->whereRaw("TIME(interval_time) >= '" . self::EXIT_FROM . "'")
            ->whereRaw("TIME(interval_time) <= '" . self::EXIT_TO   . "'")
            ->get(['low', 'close', 'interval_time', DB::raw('DATE(trade_date) as cdate')]);

        if ($candles->isNotEmpty()) {
            // Option SELLER profit = premium sold - premium bought back
            // Best exit for seller = min LOW (maximum decay / cheapest buy-back)
            $best      = $candles->sortBy('low')->first();
            $exitPrice = max(0.05, (float) $best->low);   // floor at 0.05 (can't be 0)
            $exitTime  = Carbon::parse($best->interval_time)->format('H:i');
            $exitDate  = is_string($best->cdate)
                ? $best->cdate
                : Carbon::parse($best->cdate)->toDateString();
        } else {
            // No candles in window → assume held at position price (no exit found)
            $exitPrice = $positionPrice;
            $exitTime  = null;
            $exitDate  = $nextDate;
        }

        // P/L for OPTION SELL = (sold_premium - bought_back_premium) × lot × 2 lots
        $pl    = round(($positionPrice - $exitPrice) * $lotSize * 2, 2);
        $plPct = $positionPrice > 0
            ? round((($positionPrice - $exitPrice) / $positionPrice) * 100, 2) : 0;

        return [
            'option_symbol'  => $atmRow->trading_symbol ?? "{$symbol}{$optionType}{$strike}",
            'strike'         => $strike,
            'position_price' => round($positionPrice, 2),
            'exit_price'     => round($exitPrice, 2),
            'exit_time'      => $exitTime,
            'exit_date'      => $exitDate,
            'pl'             => $pl,
            'pl_pct'         => $plPct,
            'error'          => null,
        ];
    }

    // =========================================================
    //  OI SIGNAL  (identical to OIIVAutoController)
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        // CE ↑ + PE ↓ → More calls being written, puts unwinding → BEARISH
        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓'];
        // CE ↓ + PE ↑ → Calls unwinding, puts being written → BULLISH
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑'];

        // Both rising → whoever rose more dominates
        if ($ceUp && $peUp) {
            return $cePct > $pePct
                ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)']
                : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)'];
        }

        // Both falling → whoever fell more dominates
        if ($ceDown && $peDown) {
            return $cePct < $pePct
                ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)']
                : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)'];
        }

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  EXPIRY HELPERS  (mirrors OIIVAutoController exactly)
    // =========================================================

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

    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        $e = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($e) return $e;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getNextSeriesExpiry(string $symbol, string $date, string $currentExpiry): ?string
    {
        $n = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>', $currentExpiry)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($n) return $n;

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

        $lot = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->value('lot_size');

        return $lot ? (int) $lot : ($defaults[$symbol] ?? 1);
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function getPreviousTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) {
                return $d->format('Y-m-d');
            }
            $d->subDay();
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function getNextTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->addDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) {
                return $d->format('Y-m-d');
            }
            $d->addDay();
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