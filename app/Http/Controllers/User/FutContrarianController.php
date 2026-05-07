<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FutContrarianController — Intraday Contrarian OI Analysis
 *
 * LOGIC:
 *   FUT direction (prev day 15:00 close → today 09:30 open):
 *     FUT UP   → BUY PE  (sellers who wrote puts are trapped → PE unwinding)
 *     FUT DOWN → BUY CE  (call writers trapped → CE unwinding)
 *
 *   Option pick: ATM, ATM+1, ATM-1 → pick the ONE with HIGHEST OI
 *   Scope: active expiry only (handles expiry-day shift)
 *
 *   OI-30min signal:  prev day 15:15 candle  vs  today 09:45 candle  CE+PE OI change%
 *   OI-1HR   signal:  prev day 15:15 candle  vs  today 10:15 candle  CE+PE OI change%
 *
 *   Signal rules for OI columns:
 *     CE OI ↑ + PE OI ↓ → BEARISH  (call buildup + put unwinding)
 *     CE OI ↓ + PE OI ↑ → BULLISH  (call unwinding + put buildup)
 *     Both ↑ → CE%>PE% = BEARISH; else BULLISH
 *     Both ↓ → CE%<PE% = BULLISH; else BEARISH
 *
 * DB column notes (same as OIIVAutoController):
 *   trade_date    = DATETIME  → always use whereDate()
 *   interval_time = DATETIME  → always use TIME(interval_time) = 'HH:MM:SS'
 */
class FutContrarianController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'FUT Contrarian OI Analysis';
        return view($this->activeTemplate . 'user.fut-contrarian.index', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS
    // =========================================================

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
    //  DATE HELPERS
    // =========================================================

    private function dateStr($value): string
    {
        if ($value instanceof Carbon) return $value->toDateString();
        if (is_string($value))        return substr($value, 0, 10);
        return Carbon::parse($value)->toDateString();
    }

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

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    // =========================================================
    //  MAIN ANALYSIS
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            // All trade dates in range
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')
                ->pluck('d')->toArray();

            $results = [];
            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                $rows     = $this->buildRowsForDate($date, $prevDate, $selectedSymbols, $filterAction);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            }

            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('FutContrarian Analysis Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR A SINGLE DATE
    // =========================================================

    private function buildRowsForDate(string $date, string $prevDate, array $symbolFilter, ?string $actionFilter): array
    {
        // ── 1. Get FUT candles we need ──────────────────────────────────
        // Today 09:30 open  → entry/direction reference
        $futQuery09 = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '09:30:00'");
        if (!empty($symbolFilter)) $futQuery09->whereIn('base_symbol', $symbolFilter);
        $futCandles09 = $futQuery09->get()->keyBy('base_symbol');

        if ($futCandles09->isEmpty()) return [];

        // Prev day 15:00 close → compare for FUT direction
        $prevFutSymbols = $futCandles09->keys()->toArray();
        $prevFutClose = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->whereIn('base_symbol', $prevFutSymbols)
            ->get()->keyBy('base_symbol');

        // ── 2. OI candles for OI-30min & OI-1HR ────────────────────────
        // Prev day 15:15 (base for both OI comparisons)
        $prevOI1515 = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $prevFutSymbols)
            ->where('is_missing', 0)
            ->get();

        // Today 09:45 (OI-30min)
        $todayOI0945 = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '09:45:00'")
            ->whereIn('base_symbol', $prevFutSymbols)
            ->where('is_missing', 0)
            ->get();

        // Today 10:15 (OI-1HR)
        $todayOI1015 = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '10:15:00'")
            ->whereIn('base_symbol', $prevFutSymbols)
            ->where('is_missing', 0)
            ->get();

        $rows = [];

        foreach ($futCandles09->keys() as $symbol) {
            $fut09     = $futCandles09[$symbol];
            $futPrev   = $prevFutClose[$symbol] ?? null;

            $todayOpen  = (float) $fut09->open;
            $prevClose  = $futPrev ? (float) $futPrev->close : 0;

            if ($todayOpen <= 0) continue;

            // ── FUT direction ─────────────────────────────────────────────
            $futDir = 'FLAT';
            $futChangePct = 0;
            if ($prevClose > 0) {
                $futChangePct = round((($todayOpen - $prevClose) / $prevClose) * 100, 4);
                $futDir = $futChangePct > 0 ? 'UP' : ($futChangePct < 0 ? 'DOWN' : 'FLAT');
            }

            // Contrarian action:  FUT UP → BUY PE,  FUT DOWN → BUY CE,  FLAT → WAIT
            $baseAction = match($futDir) {
                'UP'   => 'BUY PE',
                'DOWN' => 'BUY CE',
                default => 'WAIT',
            };

            // Apply action filter early
            if (!empty($actionFilter) && $baseAction !== $actionFilter) continue;

            // ── Expiry resolution ─────────────────────────────────────────
            $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
            $prevExpiry    = $currentExpiry
                ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                : null;
            $isExpiryDay   = ($this->getNearestExpiryForDate($symbol, $date) === $date);

            // ── Best ATM option (highest OI among ATM, ATM-1, ATM+1) ─────
            $optionType = $baseAction === 'BUY CE' ? 'CE' : ($baseAction === 'BUY PE' ? 'PE' : null);

            $bestOption = null;
            $bestStrike = null;
            $bestOI     = 0;

            if ($optionType) {
                $atmRows = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $date)
                    ->whereRaw("TIME(interval_time) = '09:30:00'")
                    ->whereIn('strike_position', ['ATM', 'ATM+1', 'ATM-1'])
                    ->where('is_missing', 0)
                    ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
                    ->get();

                // Pick highest OI
                foreach ($atmRows as $atmRow) {
                    $rowOI = (int) ($atmRow->oi ?? 0);
                    if ($rowOI > $bestOI) {
                        $bestOI     = $rowOI;
                        $bestStrike = $atmRow->strike;
                        $bestOption = $atmRow;
                    }
                }

                // Fallback: closest 3 strikes by price if strike_position missing
                if (!$bestOption) {
                    $allStrikes = OptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->whereDate('trade_date', $date)
                        ->whereRaw("TIME(interval_time) = '09:30:00'")
                        ->where('is_missing', 0)
                        ->whereNotNull('strike')
                        ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
                        ->orderByRaw('ABS(strike - ?)', [$todayOpen])
                        ->limit(3)
                        ->get();

                    foreach ($allStrikes as $sr) {
                        $rowOI = (int) ($sr->oi ?? 0);
                        if ($rowOI > $bestOI) {
                            $bestOI     = $rowOI;
                            $bestStrike = $sr->strike;
                            $bestOption = $sr;
                        }
                    }
                }
            }

            // ── OI sums for OI-30min and OI-1HR ──────────────────────────
            // Filter to active expiry
            $filterExpiry = fn($collection) => $collection
                ->where('base_symbol', $symbol)
                ->when($currentExpiry, fn($c) => $c->where(
                    fn($r) => $this->dateStr($r->expiry_date) === $currentExpiry
                ));

            $filterPrevExpiry = fn($collection) => $collection
                ->where('base_symbol', $symbol)
                ->when($prevExpiry, fn($c) => $c->where(
                    fn($r) => $this->dateStr($r->expiry_date) === $prevExpiry
                ));

            // Prev 15:15 OI sums
            $prevCE1515 = (int) $filterPrevExpiry($prevOI1515)->where('instrument_type', 'CE')->sum('oi');
            $prevPE1515 = (int) $filterPrevExpiry($prevOI1515)->where('instrument_type', 'PE')->sum('oi');

            // Today 09:45 OI sums
            $ce0945 = (int) $filterExpiry($todayOI0945)->where('instrument_type', 'CE')->sum('oi');
            $pe0945 = (int) $filterExpiry($todayOI0945)->where('instrument_type', 'PE')->sum('oi');

            // Today 10:15 OI sums
            $ce1015 = (int) $filterExpiry($todayOI1015)->where('instrument_type', 'CE')->sum('oi');
            $pe1015 = (int) $filterExpiry($todayOI1015)->where('instrument_type', 'PE')->sum('oi');

            // ── OI % changes ──────────────────────────────────────────────
            // 30min: prev 15:15 → today 09:45
            $ce30Pct = $prevCE1515 > 0 ? round((($ce0945 - $prevCE1515) / $prevCE1515) * 100, 2) : 0;
            $pe30Pct = $prevPE1515 > 0 ? round((($pe0945 - $prevPE1515) / $prevPE1515) * 100, 2) : 0;

            // 1HR: prev 15:15 → today 10:15
            $ce1hPct = $prevCE1515 > 0 ? round((($ce1015 - $prevCE1515) / $prevCE1515) * 100, 2) : 0;
            $pe1hPct = $prevPE1515 > 0 ? round((($pe1015 - $prevPE1515) / $prevPE1515) * 100, 2) : 0;

            // ── OI signals ───────────────────────────────────────────────
            $oi30Signal = $this->getOISignal($ce30Pct, $pe30Pct);
            $oi1hSignal = $this->getOISignal($ce1hPct, $pe1hPct);

            // ── Spot price at 09:30 ───────────────────────────────────────
            $spotPrice = round($todayOpen, 2);

            // ── Build result row ──────────────────────────────────────────
            $row = [
                'date'   => $date,
                'symbol' => $symbol,

                // FUT data
                'fut_prev_close'   => round($prevClose, 2),
                'fut_today_open'   => round($todayOpen, 2),
                'fut_change_pct'   => round($futChangePct, 4),
                'fut_direction'    => $futDir,

                // Contrarian action
                'trade_action'     => $baseAction,

                // Best option selected
                'option_type'      => $optionType,
                'best_strike'      => $bestStrike,
                'best_strike_pos'  => $bestOption?->strike_position ?? null,
                'best_oi'          => $bestOI,
                'best_option_sym'  => $bestOption?->trading_symbol ?? null,
                'best_open_price'  => $bestOption ? round((float)$bestOption->open, 2) : null,
                'best_close_price' => $bestOption ? round((float)$bestOption->close, 2) : null,

                // OI raw values
                'prev_ce_oi_1515'  => $prevCE1515,
                'prev_pe_oi_1515'  => $prevPE1515,
                'ce_oi_0945'       => $ce0945,
                'pe_oi_0945'       => $pe0945,
                'ce_oi_1015'       => $ce1015,
                'pe_oi_1015'       => $pe1015,

                // OI-30min signal (prev 15:15 → today 09:45)
                'ce_oi_30min_pct'  => $ce30Pct,
                'pe_oi_30min_pct'  => $pe30Pct,
                'oi_30min_signal'  => $oi30Signal['signal'],
                'oi_30min_cond'    => $oi30Signal['condition'],

                // OI-1HR signal (prev 15:15 → today 10:15)
                'ce_oi_1hr_pct'    => $ce1hPct,
                'pe_oi_1hr_pct'    => $pe1hPct,
                'oi_1hr_signal'    => $oi1hSignal['signal'],
                'oi_1hr_cond'      => $oi1hSignal['condition'],

                // Expiry info
                'current_expiry'   => $currentExpiry,
                'prev_expiry'      => $prevExpiry,
                'is_expiry_day'    => $isExpiryDay,
                'spot_price'       => $spotPrice,
            ];

            $rows[] = $row;
        }

        return $rows;
    }

    // =========================================================
    //  OI SIGNAL HELPER
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE↑ PE↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE↓ PE↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both ↑ CE stronger",  'condition' => 'Both↑ CE>PE']
            : ['signal' => 'BULLISH', 'reason' => "Both ↑ PE stronger",  'condition' => 'Both↑ PE>CE'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both ↓ CE unwinds more", 'condition' => 'Both↓ CE<PE']
            : ['signal' => 'BEARISH', 'reason' => "Both ↓ PE unwinds more", 'condition' => 'Both↓ PE<CE'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // =========================================================
    //  P/L CALCULATION
    //
    //  Called via POST after the main analysis loads.
    //  Receives an array of aligned signal rows from the frontend.
    //
    //  OI-30min trade:
    //    Buy  = open price of 10:00 candle for the best-strike option
    //    Sell = MAX(high) from 10:00 candle onwards for the rest of the day
    //
    //  OI-1HR trade:
    //    Buy  = open price of 10:30 candle for the best-strike option
    //    Sell = MAX(high) from 10:30 candle onwards for the rest of the day
    //
    //  Both use lot size from zerodha_instruments (same helper as existing controller).
    // =========================================================

    public function calculatePL(Request $request)
    {
        try {
            $signals = $request->input('signals', []);

            if (empty($signals)) {
                return response()->json(['success' => false, 'message' => 'No signals provided', 'data' => []]);
            }

            $results30m = [];
            $results1h  = [];

            foreach ($signals as $signal) {
                $symbol      = $signal['symbol']      ?? '';
                $date        = $signal['date']         ?? '';
                $optionType  = $signal['option_type']  ?? '';   // CE or PE
                $strike      = $signal['best_strike']  ?? null;
                $expiry      = $signal['current_expiry'] ?? null;
                $idx         = $signal['idx']          ?? 0;

                if (!$symbol || !$date || !$optionType || !$strike) {
                    $results30m[] = $this->emptyPL($idx, 'MISSING_DATA');
                    $results1h[]  = $this->emptyPL($idx, 'MISSING_DATA');
                    continue;
                }

                $lotSize = $this->getLotSize($symbol);

                // ── Shared: fetch all intraday candles for this option strike ──
                // We need from 10:00 onwards for 30m trade, from 10:30 onwards for 1h trade
                $candles = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('trade_date', $date)
                    ->where('is_missing', 0)
                    ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
                    ->whereRaw("TIME(interval_time) >= '10:00:00'")
                    ->orderBy('interval_time')
                    ->get(['open', 'high', 'low', 'close', 'interval_time']);

                // ── OI-30min P/L: buy at 10:00 open, sell at max high from 10:00+ ──
                $candle1000 = $candles->first(fn($c) => substr($c->interval_time, 11, 5) === '10:00');

                if ($candle1000 && (float)$candle1000->open > 0) {
                    $buyPrice30  = (float) $candle1000->open;
                    // Max high from 10:00 candle onwards (inclusive)
                    $maxHigh30   = (float) $candles->max('high');
                    $investment30 = round($buyPrice30 * $lotSize, 2);
                    $pl30         = round(($maxHigh30 - $buyPrice30) * $lotSize, 2);
                    $roi30        = $investment30 > 0 ? round(($pl30 / $investment30) * 100, 2) : 0;

                    $results30m[] = [
                        'idx'         => $idx,
                        'symbol'      => $symbol,
                        'date'        => $date,
                        'option_type' => $optionType,
                        'strike'      => $strike,
                        'lot_size'    => $lotSize,
                        'buy_price'   => round($buyPrice30, 2),
                        'sell_price'  => round($maxHigh30,  2),
                        'investment'  => $investment30,
                        'pl'          => $pl30,
                        'roi'         => $roi30,
                        'error'       => null,
                    ];
                } else {
                    $results30m[] = $this->emptyPL($idx, 'NO_10:00_CANDLE');
                }

                // ── OI-1HR P/L: buy at 10:30 open, sell at max high from 10:30+ ──
                $candle1030 = $candles->first(fn($c) => substr($c->interval_time, 11, 5) === '10:30');
                $candlesFrom1030 = $candles->filter(fn($c) => substr($c->interval_time, 11, 5) >= '10:30');

                if ($candle1030 && (float)$candle1030->open > 0) {
                    $buyPrice1h  = (float) $candle1030->open;
                    $maxHigh1h   = $candlesFrom1030->isNotEmpty() ? (float) $candlesFrom1030->max('high') : $buyPrice1h;
                    $investment1h = round($buyPrice1h * $lotSize, 2);
                    $pl1h         = round(($maxHigh1h - $buyPrice1h) * $lotSize, 2);
                    $roi1h        = $investment1h > 0 ? round(($pl1h / $investment1h) * 100, 2) : 0;

                    $results1h[] = [
                        'idx'         => $idx,
                        'symbol'      => $symbol,
                        'date'        => $date,
                        'option_type' => $optionType,
                        'strike'      => $strike,
                        'lot_size'    => $lotSize,
                        'buy_price'   => round($buyPrice1h, 2),
                        'sell_price'  => round($maxHigh1h,  2),
                        'investment'  => $investment1h,
                        'pl'          => $pl1h,
                        'roi'         => $roi1h,
                        'error'       => null,
                    ];
                } else {
                    $results1h[] = $this->emptyPL($idx, 'NO_10:30_CANDLE');
                }
            }

            return response()->json([
                'success'     => true,
                'data_30min'  => $results30m,
                'data_1hr'    => $results1h,
                'message'     => count($results30m) . ' records calculated',
            ]);

        } catch (\Exception $e) {
            Log::error('FutContrarian P/L Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    private function emptyPL(int $idx, string $error): array
    {
        return [
            'idx' => $idx, 'symbol' => null, 'date' => null,
            'option_type' => null, 'strike' => null, 'lot_size' => 0,
            'buy_price' => 0, 'sell_price' => 0, 'investment' => 0,
            'pl' => 0, 'roi' => 0, 'error' => $error,
        ];
    }

    // =========================================================
    //  LOT SIZE HELPER  (mirrors OIIVAutoController)
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $lots = [
            'NIFTY'      => 25,
            'BANKNIFTY'  => 15,
            'FINNIFTY'   => 25,
            'MIDCPNIFTY' => 50,
            'SENSEX'     => 10,
            'BANKEX'     => 15,
        ];

        $instrument = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->value('lot_size');

        if ($instrument) return (int) $instrument;

        return $lots[$symbol] ?? 1;
    }
}