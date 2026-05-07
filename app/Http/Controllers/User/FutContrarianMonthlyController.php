<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FutContrarianMonthlyController
 *
 * Monthly P&L Dashboard for FUT Contrarian OI Analysis.
 * Produces TWO separate monthly breakdowns:
 *
 *  ── OI-30min trades ────────────────────────────────────────────────────────
 *    Aligned = FUT contrarian action matches OI-30min signal (FULL or PARTIAL)
 *    Buy     = 10:00 candle open of the best-OI ATM/±1 option
 *    Sell    = MAX(high) from 10:00 candle onwards for that day
 *
 *  ── OI-1HR trades ──────────────────────────────────────────────────────────
 *    Aligned = FUT contrarian action matches OI-1HR signal (FULL or PARTIAL)
 *    Buy     = 10:30 candle open of the best-OI ATM/±1 option
 *    Sell    = MAX(high) from 10:30 candle onwards for that day
 *
 *  Alignment logic (mirrors JS alignmentBadge):
 *    BUY CE (FUT DOWN) → expected OI signal = BULLISH
 *    BUY PE (FUT UP)   → expected OI signal = BEARISH
 *    Include row if:   signal matches expected  (FULL or PARTIAL counts)
 *    Exclude if:       CONFLICT, WAIT, no data
 *
 *  Grouping: Month → Day → trades[] (same structure as FutOptionMonthlyController)
 */
class FutContrarianMonthlyController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Monthly P&L — FUT Contrarian OI';
        return view($this->activeTemplate . 'user.fut-contrarian.monthly', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS  (reuse same FUT list)
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()->orderBy('base_symbol')->pluck('base_symbol')->values();
        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  STEP 1 - Return list of trade dates only (fast)
    //  Frontend calls this first, then loops analyzeDay() one
    //  date at a time so no single request ever times out.
    // =========================================================

    public function getTradeDates(Request $request)
    {
        $fromDate = $request->get('from_date');
        $toDate   = $request->get('to_date');

        if (!$fromDate || !$toDate) {
            return response()->json(['success' => false, 'message' => 'Please select both dates.']);
        }

        $dates = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', '>=', $fromDate)
            ->whereDate('trade_date', '<=', $toDate)
            ->select(DB::raw('DATE(trade_date) as d'))
            ->distinct()->orderBy('d')->pluck('d')->toArray();

        return response()->json(['success' => true, 'dates' => $dates, 'total' => count($dates)]);
    }

    // =========================================================
    //  STEP 2 - Process ONE date, return aligned rows for it
    //  Called sequentially per day from the frontend.
    // =========================================================

    public function analyzeDay(Request $request)
    {
        try {
            $date            = $request->get('date');
            $selectedSymbols = $request->get('symbols', []);

            if (!$date) {
                return response()->json(['success' => false, 'message' => 'Date required.']);
            }

            $prevDate = $this->getPreviousTradingDate($date);
            [$rows30m, $rows1h] = $this->buildAlignedRowsForDate($date, $prevDate, $selectedSymbols);

            return response()->json([
                'success'  => true,
                'date'     => $date,
                'rows_30m' => $rows30m,
                'rows_1h'  => $rows1h,
            ]);

        } catch (\Exception $e) {
            Log::error('FutContrarianMonthly::analyzeDay [' . $request->get('date') . '] - ' . $e->getMessage());
            return response()->json(['success' => false, 'date' => $request->get('date'), 'message' => $e->getMessage()]);
        }
    }

    // =========================================================
    //  BUILD ALIGNED ROWS FOR ONE DATE
    //  Returns [ $rows30min[], $rows1hr[] ]
    // =========================================================

    private function buildAlignedRowsForDate(string $date, string $prevDate, array $symbolFilter): array
    {
        // ── FUT candles at 09:30 today (drives symbol list + direction) ──
        $futQ = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '09:30:00'");
        if (!empty($symbolFilter)) $futQ->whereIn('base_symbol', $symbolFilter);
        $futCandles09 = $futQ->get()->keyBy('base_symbol');
        if ($futCandles09->isEmpty()) return [[], []];

        $symbols = $futCandles09->keys()->toArray();

        // ── Prev day 15:00 FUT close (for direction) ─────────────────────
        $prevFutClose = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->whereIn('base_symbol', $symbols)
            ->get()->keyBy('base_symbol');

        // ── OI candles (prev 15:15 base, today 09:45, today 10:15) ──────
        $prevOI1515  = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $symbols)->where('is_missing', 0)->get();

        $todayOI0945 = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '09:45:00'")
            ->whereIn('base_symbol', $symbols)->where('is_missing', 0)->get();

        $todayOI1015 = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '10:15:00'")
            ->whereIn('base_symbol', $symbols)->where('is_missing', 0)->get();

        $rows30m = [];
        $rows1h  = [];

        foreach ($symbols as $symbol) {
            $fut09   = $futCandles09[$symbol];
            $futPrev = $prevFutClose[$symbol] ?? null;

            $todayOpen = (float) $fut09->open;
            $prevClose = $futPrev ? (float) $futPrev->close : 0;
            if ($todayOpen <= 0) continue;

            // FUT direction → contrarian action
            $futChangePct = $prevClose > 0 ? (($todayOpen - $prevClose) / $prevClose) * 100 : 0;
            $futDir       = $futChangePct > 0 ? 'UP' : ($futChangePct < 0 ? 'DOWN' : 'FLAT');
            $action       = match($futDir) { 'UP' => 'BUY PE', 'DOWN' => 'BUY CE', default => 'WAIT' };
            if ($action === 'WAIT') continue;

            $optionType = $action === 'BUY CE' ? 'CE' : 'PE';
            // Expected OI signal: BUY CE needs BULLISH OI, BUY PE needs BEARISH OI
            $expectedOI = $action === 'BUY CE' ? 'BULLISH' : 'BEARISH';

            // ── Expiry ────────────────────────────────────────────────────
            $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
            $prevExpiry    = $currentExpiry ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry) : null;

            // ── Filter collections by symbol+expiry ───────────────────────
            $flt = fn($col, $exp) => $col->where('base_symbol', $symbol)
                ->when($exp, fn($c) => $c->filter(fn($r) => substr($r->expiry_date ?? '', 0, 10) === $exp));

            $prevCE = (int) $flt($prevOI1515,  $prevExpiry)->where('instrument_type', 'CE')->sum('oi');
            $prevPE = (int) $flt($prevOI1515,  $prevExpiry)->where('instrument_type', 'PE')->sum('oi');
            $ce0945 = (int) $flt($todayOI0945, $currentExpiry)->where('instrument_type', 'CE')->sum('oi');
            $pe0945 = (int) $flt($todayOI0945, $currentExpiry)->where('instrument_type', 'PE')->sum('oi');
            $ce1015 = (int) $flt($todayOI1015, $currentExpiry)->where('instrument_type', 'CE')->sum('oi');
            $pe1015 = (int) $flt($todayOI1015, $currentExpiry)->where('instrument_type', 'PE')->sum('oi');

            // ── OI % changes ──────────────────────────────────────────────
            $ce30Pct = $prevCE > 0 ? (($ce0945 - $prevCE) / $prevCE) * 100 : 0;
            $pe30Pct = $prevPE > 0 ? (($pe0945 - $prevPE) / $prevPE) * 100 : 0;
            $ce1hPct = $prevCE > 0 ? (($ce1015 - $prevCE) / $prevCE) * 100 : 0;
            $pe1hPct = $prevPE > 0 ? (($pe1015 - $prevPE) / $prevPE) * 100 : 0;

            $sig30 = $this->getOISignal($ce30Pct, $pe30Pct)['signal'];
            $sig1h = $this->getOISignal($ce1hPct, $pe1hPct)['signal'];

            $lotSize = $this->getLotSize($symbol);

            // ── Best ATM option (highest OI among ATM / ATM±1) at 09:30 ──
            $bestOption = $this->getBestAtmOption($symbol, $date, $optionType, $todayOpen, $currentExpiry);
            if (!$bestOption) continue;

            $strike     = $bestOption->strike;
            $expiry     = substr($bestOption->expiry_date ?? '', 0, 10);

            // ── Intraday candles from 10:00 onwards ───────────────────────
            $candles = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->where('strike', $strike)
                ->whereDate('trade_date', $date)
                ->where('is_missing', 0)
                ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
                ->whereRaw("TIME(interval_time) >= '10:00:00'")
                ->orderBy('interval_time')
                ->get(['open', 'high', 'interval_time']);

            // ── OI-30min row: aligned if sig30 matches expected ───────────
            $aligned30 = ($sig30 !== 'NEUTRAL' && $sig30 === $expectedOI);
            if ($aligned30) {
                $c1000 = $candles->first(fn($c) => substr($c->interval_time, 11, 5) === '10:00');
                if ($c1000 && (float)$c1000->open > 0) {
                    $buyPrice   = (float) $c1000->open;
                    $maxHigh    = (float) $candles->max('high');
                    $investment = round($buyPrice * $lotSize, 2);
                    $pl         = round(($maxHigh - $buyPrice) * $lotSize, 2);
                    $roi        = $investment > 0 ? round(($pl / $investment) * 100, 2) : 0;

                    $rows30m[] = [
                        'date'        => $date,
                        'symbol'      => $symbol,
                        'option_type' => $optionType,
                        'strike'      => $strike,
                        'lot_size'    => $lotSize,
                        'buy_price'   => round($buyPrice, 2),
                        'sell_price'  => round($maxHigh, 2),
                        'investment'  => $investment,
                        'pl'          => $pl,
                        'roi'         => $roi,
                        'action'      => $action,
                    ];
                }
            }

            // ── OI-1HR row: aligned if sig1h matches expected ─────────────
            $aligned1h = ($sig1h !== 'NEUTRAL' && $sig1h === $expectedOI);
            if ($aligned1h) {
                $c1030        = $candles->first(fn($c) => substr($c->interval_time, 11, 5) === '10:30');
                $from1030     = $candles->filter(fn($c) => substr($c->interval_time, 11, 5) >= '10:30');
                if ($c1030 && (float)$c1030->open > 0) {
                    $buyPrice   = (float) $c1030->open;
                    $maxHigh    = $from1030->isNotEmpty() ? (float) $from1030->max('high') : $buyPrice;
                    $investment = round($buyPrice * $lotSize, 2);
                    $pl         = round(($maxHigh - $buyPrice) * $lotSize, 2);
                    $roi        = $investment > 0 ? round(($pl / $investment) * 100, 2) : 0;

                    $rows1h[] = [
                        'date'        => $date,
                        'symbol'      => $symbol,
                        'option_type' => $optionType,
                        'strike'      => $strike,
                        'lot_size'    => $lotSize,
                        'buy_price'   => round($buyPrice, 2),
                        'sell_price'  => round($maxHigh, 2),
                        'investment'  => $investment,
                        'pl'          => $pl,
                        'roi'         => $roi,
                        'action'      => $action,
                    ];
                }
            }
        }

        return [$rows30m, $rows1h];
    }

    // =========================================================
    //  GROUP FLAT ROWS → Month → Day structure
    //  (same shape as FutOptionMonthlyController)
    // =========================================================

    private function groupByMonth(array $rows): array
    {
        $monthlyGroups = [];
        foreach ($rows as $row) {
            $month = substr($row['date'], 0, 7);
            $day   = $row['date'];
            $monthlyGroups[$month][$day][] = $row;
        }

        $months = [];
        foreach ($monthlyGroups as $month => $days) {
            $mTrades = $mWins = $mLosses = $mPL = $mInv = $mCE = $mPE = 0;
            $dayRows = [];

            foreach ($days as $day => $trades) {
                $dCount  = count($trades);
                $dPL     = array_sum(array_column($trades, 'pl'));
                $dInv    = array_sum(array_column($trades, 'investment'));
                $dWins   = count(array_filter($trades, fn($t) => $t['pl'] > 0));
                $dLosses = $dCount - $dWins;
                $dCE     = count(array_filter($trades, fn($t) => $t['option_type'] === 'CE'));
                $dPE     = count(array_filter($trades, fn($t) => $t['option_type'] === 'PE'));

                $dayRows[] = [
                    'date'     => $day,
                    'day_name' => Carbon::parse($day)->format('D, d M'),
                    'trades'   => $dCount,
                    'ce_count' => $dCE,
                    'pe_count' => $dPE,
                    'pl'       => round($dPL, 2),
                    'investment' => round($dInv, 2),
                    'wins'     => $dWins,
                    'losses'   => $dLosses,
                    'win_rate' => $dCount > 0 ? round($dWins / $dCount * 100, 1) : 0,
                ];

                $mTrades  += $dCount;
                $mPL      += $dPL;
                $mInv     += $dInv;
                $mWins    += $dWins;
                $mLosses  += $dLosses;
                $mCE      += $dCE;
                $mPE      += $dPE;
            }

            usort($dayRows, fn($a, $b) => $a['date'] <=> $b['date']);

            $months[] = [
                'month'       => $month,
                'month_label' => Carbon::parse($month . '-01')->format('F Y'),
                'days'        => $dayRows,
                'trades'      => $mTrades,
                'ce_count'    => $mCE,
                'pe_count'    => $mPE,
                'pl'          => round($mPL, 2),
                'investment'  => round($mInv, 2),
                'wins'        => $mWins,
                'losses'      => $mLosses,
                'win_rate'    => $mTrades > 0 ? round($mWins / $mTrades * 100, 1) : 0,
            ];
        }

        usort($months, fn($a, $b) => $a['month'] <=> $b['month']);

        // Grand summary
        $totalTrades = array_sum(array_column($months, 'trades'));
        $totalPL     = array_sum(array_column($months, 'pl'));
        $totalInv    = array_sum(array_column($months, 'investment'));
        $totalWins   = array_sum(array_column($months, 'wins'));
        $totalLosses = array_sum(array_column($months, 'losses'));
        $totalCE     = array_sum(array_column($months, 'ce_count'));
        $totalPE     = array_sum(array_column($months, 'pe_count'));

        return [
            'months'  => $months,
            'summary' => [
                'trades'     => $totalTrades,
                'ce_count'   => $totalCE,
                'pe_count'   => $totalPE,
                'pl'         => round($totalPL, 2),
                'investment' => round($totalInv, 2),
                'wins'       => $totalWins,
                'losses'     => $totalLosses,
                'win_rate'   => $totalTrades > 0 ? round($totalWins / $totalTrades * 100, 1) : 0,
                'months'     => count($months),
            ],
        ];
    }

    // =========================================================
    //  BEST ATM OPTION HELPER
    // =========================================================

    private function getBestAtmOption(string $symbol, string $date, string $optionType, float $spot, ?string $expiry): ?object
    {
        // Try strike_position first
        $rows = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $date)
            ->whereIn('strike_position', ['ATM', 'ATM+1', 'ATM-1'])
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) = '09:30:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->get();

        if ($rows->isEmpty()) {
            // Fallback: nearest 3 strikes by price
            $rows = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->whereDate('trade_date', $date)
                ->where('is_missing', 0)
                ->whereNotNull('strike')
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
                ->orderByRaw('ABS(strike - ?)', [$spot])
                ->limit(3)->get();
        }

        if ($rows->isEmpty()) return null;

        // Pick highest OI
        return $rows->sortByDesc(fn($r) => (int)($r->oi ?? 0))->first();
    }

    // =========================================================
    //  OI SIGNAL HELPER
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE↑ PE↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE↓ PE↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'condition' => 'Both↑ CE>PE']
            : ['signal' => 'BULLISH', 'condition' => 'Both↑ PE>CE'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'condition' => 'Both↓ CE<PE']
            : ['signal' => 'BEARISH', 'condition' => 'Both↓ PE<CE'];

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  LOT SIZE
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $defaults = ['NIFTY' => 25, 'BANKNIFTY' => 15, 'FINNIFTY' => 25, 'MIDCPNIFTY' => 50, 'SENSEX' => 10, 'BANKEX' => 15];
        $lot = DB::table('zerodha_instruments')
            ->where('name', $symbol)->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])->value('lot_size');
        return $lot ? (int)$lot : ($defaults[$symbol] ?? 1);
    }

    // =========================================================
    //  EXPIRY HELPERS
    // =========================================================

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = $this->getNearestExpiryForDate($symbol, $date);
        if (!$expiry) return null;
        if ($expiry === $date) {
            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])->whereNotNull('expiry_date')
                ->whereDate('trade_date', $date)->whereDate('expiry_date', '>', $expiry)
                ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));
            if ($next) return $next;
        }
        return $expiry;
    }

    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        $e = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));
        if ($e) return $e;
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)->where('is_missing', 0)->exists();
        if ($exists) return $currentExpiry;
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')->where('is_missing', 0)->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function getPreviousTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) return $d->format('Y-m-d');
            $d->subDay();
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}