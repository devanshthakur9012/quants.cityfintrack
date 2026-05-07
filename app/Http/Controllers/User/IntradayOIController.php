<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * IntradayOIController v2
 *
 * SIGNAL LOGIC:
 *   Signal 1: Prev-day 09:30 OI  →  Today 09:30 OI   (inter-day)
 *   Signal 2: Today 09:30 OI     →  Today 09:45 OI   (intra-day confirmation)
 *   Both must agree (BULLISH or BEARISH) → take trade
 *
 * EMA FILTER (on FUT 15-min closes up to 09:45):
 *   BUY CE  → price ABOVE EMA20 AND EMA50
 *   BUY PE  → price BELOW EMA20 AND EMA50
 *   Fails   → skip row entirely (not shown in UI)
 *
 * OPTION SELECTION:
 *   BUY CE  → try ATM first, then ATM-1 (one strike below)
 *   BUY PE  → try ATM first, then ATM+1 (one strike above)
 *   Entry candle = 10:00 open
 *
 * ONLY rows with BUY CE or BUY PE are returned (WAIT/FILTERED hidden).
 */
class IntradayOIController extends Controller
{
    private const EMA_SHORT   = 20;
    private const EMA_LONG    = 50;
    private const EMA_HISTORY = 120;

    public function index()
    {
        $pageTitle = 'Intraday OI — Dual Confirmation (10:00 Entry)';
        return view($this->activeTemplate . 'user.intraday-oi.index', compact('pageTitle'));
    }

    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()->orderBy('base_symbol')
            ->pluck('base_symbol')->values();
        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // ─────────────────────────────────────────────────────────────
    //  MAIN ENDPOINT
    // ─────────────────────────────────────────────────────────────

    public function analyzeSignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            $results = [];
            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                foreach ($this->buildRowsForDate($date, $prevDate, $selectedSymbols) as $row) {
                    $results[] = $row;
                }
            }

            usort($results, fn($a, $b) => $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']);

            return response()->json([
                'success' => true, 'data' => $results,
                'total_records' => count($results),
                'message' => count($results) . ' tradeable signals found',
            ]);

        } catch (\Exception $e) {
            Log::error('IntradayOI v2 Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    //  BUILD ROWS FOR ONE DATE
    // ─────────────────────────────────────────────────────────────

    private function buildRowsForDate(string $date, string $prevDate, array $symbolFilter): array
    {
        // Symbols = those with FUT candle at 09:30 today
        $futQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '09:30:00'");
        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');
        if ($futCandles->isEmpty()) return [];

        $rows = [];

        foreach ($futCandles->keys() as $symbol) {
            // Need 09:45 FUT close for EMA
            $fut945 = OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'FUT')
                ->whereRaw("TIME(interval_time) = '09:45:00'")->first();
            $fut945Close = $fut945 ? (float) $fut945->close : 0;
            if ($fut945Close <= 0) continue;

            // Expiry
            $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
            $prevExpiry    = $currentExpiry ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry) : null;

            // ── SIGNAL 1: Prev 09:30 → Today 09:30 ─────────────────
            $ce930T = (int) OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))->sum('oi');

            $pe930T = (int) OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))->sum('oi');

            if ($ce930T == 0 && $pe930T == 0) continue;

            $ce930P = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))->sum('oi');

            $pe930P = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '09:30:00'")
                ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))->sum('oi');

            $cePct1 = $ce930P > 0 ? round((($ce930T - $ce930P) / $ce930P) * 100, 2) : 0;
            $pePct1 = $pe930P > 0 ? round((($pe930T - $pe930P) / $pe930P) * 100, 2) : 0;
            $s1     = $this->getOISignal($cePct1, $pePct1)['signal'];

            // ── SIGNAL 2: Today 09:30 → Today 09:45 ─────────────────
            $ce945T = (int) OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '09:45:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))->sum('oi');

            $pe945T = (int) OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '09:45:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))->sum('oi');

            $cePct2 = $ce930T > 0 ? round((($ce945T - $ce930T) / $ce930T) * 100, 2) : 0;
            $pePct2 = $pe930T > 0 ? round((($pe945T - $pe930T) / $pe930T) * 100, 2) : 0;
            $s2     = $this->getOISignal($cePct2, $pePct2)['signal'];

            // ── Alignment: both must agree ────────────────────────────
            if ($s1 === 'NEUTRAL' || $s2 === 'NEUTRAL' || $s1 !== $s2) continue;
            $finalSentiment = $s1; // BULLISH or BEARISH

            // ── EMA filter ────────────────────────────────────────────
            [$ema20, $ema50, $emaSignal] = $this->getEMASignal($symbol, $date, $fut945Close);

            if ($finalSentiment === 'BULLISH' && $emaSignal !== 'ABOVE') continue;
            if ($finalSentiment === 'BEARISH' && $emaSignal !== 'BELOW') continue;

            $tradeAction = $finalSentiment === 'BULLISH' ? 'BUY CE' : 'BUY PE';
            $optionType  = $tradeAction === 'BUY CE' ? 'CE' : 'PE';

            // ── Option selection at 10:00 ─────────────────────────────
            // CE: try ATM → ATM-1
            // PE: try ATM → ATM+1
            $fut1000 = OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)->where('instrument_type', 'FUT')
                ->whereRaw("TIME(interval_time) = '10:00:00'")->first();
            $spotRef = $fut1000 ? (float) $fut1000->open : $fut945Close;

            $atmRow = $this->findATMOption($symbol, $optionType, $date, $currentExpiry, $spotRef);

            // If no ATM found, skip
            if (!$atmRow) continue;

            $buyPrice        = (float) ($atmRow->open ?? 0);
            if ($buyPrice <= 0) $buyPrice = (float) ($atmRow->close ?? 0);
            if ($buyPrice <= 0) continue;

            $buyOptionSymbol = $atmRow->trading_symbol ?? null;
            $buyStrike       = (float) $atmRow->strike;
            $buyExpiry       = $this->dateStr($atmRow->expiry_date);
            $atmPosition     = $atmRow->strike_position ?? 'ATM';
            $lotSize         = $this->getLotSize($symbol);
            $investment      = round($buyPrice * $lotSize, 2);

            // ── Exit prices (after 10:00 till 15:15) ──────────────────
            $bestSell = $bestSellTime = $closeSell = null;
            $bestPL = $bestROI = $closePL = $closeROI = 0;

            $exitRows = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->where('strike', $buyStrike)
                ->whereDate('expiry_date', $buyExpiry)
                ->whereDate('trade_date', $date)
                ->where('is_missing', 0)
                ->whereRaw("TIME(interval_time) > '10:00:00'")
                ->whereRaw("TIME(interval_time) <= '15:15:00'")
                ->orderBy('interval_time')
                ->get(['high', 'low', 'close', 'interval_time']);

            if ($exitRows->isNotEmpty()) {
                $highRow      = $exitRows->sortByDesc('high')->first();
                $bestSell     = (float) $highRow->high;
                $bestSellTime = Carbon::parse($highRow->interval_time)->format('H:i');

                $c15 = $exitRows->first(fn($c) => substr($c->interval_time, 11, 5) === '15:00');
                if (!$c15) {
                    $c15 = $exitRows->filter(fn($c) => substr($c->interval_time, 11, 5) <= '15:00')
                        ->sortByDesc('interval_time')->first();
                }
                if ($c15) $closeSell = (float) ($c15->close ?? 0);

                if ($bestSell > 0) {
                    $bestPL  = round(($bestSell - $buyPrice) * $lotSize, 2);
                    $bestROI = round(($bestPL / $investment) * 100, 2);
                }
                if ($closeSell > 0) {
                    $closePL  = round(($closeSell - $buyPrice) * $lotSize, 2);
                    $closeROI = round(($closePL / $investment) * 100, 2);
                }
            }

            $rows[] = [
                'date'            => $date,
                'symbol'          => $symbol,

                // Signals (for display)
                'signal1'         => $s1,
                'signal2'         => $s2,
                'ema_signal'      => $emaSignal,
                'ema20'           => $ema20 ? round($ema20, 2) : null,
                'ema50'           => $ema50 ? round($ema50, 2) : null,

                // Trade
                'trade_action'    => $tradeAction,
                'option_type'     => $optionType,
                'atm_position'    => $atmPosition,
                'option_symbol'   => $buyOptionSymbol,
                'strike'          => $buyStrike,
                'expiry'          => $buyExpiry,
                'lot_size'        => $lotSize,
                'investment'      => $investment,
                'buy_price'       => round($buyPrice, 2),

                // Exit
                'best_sell'       => $bestSell  ? round($bestSell, 2)  : 0,
                'best_sell_time'  => $bestSellTime,
                'close_sell'      => $closeSell ? round($closeSell, 2) : 0,
                'best_pl'         => $bestPL,   'best_roi'  => $bestROI,
                'close_pl'        => $closePL,  'close_roi' => $closeROI,
            ];
        }

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────
    //  OPTION FINDER — ATM with fallback
    //  CE: ATM → ATM-1 (one strike below spot)
    //  PE: ATM → ATM+1 (one strike above spot)
    // ─────────────────────────────────────────────────────────────

    private function findATMOption(string $symbol, string $type, string $date, ?string $expiry, float $spot)
    {
        // Step 1: strike_position = ATM
        $q = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->where('strike_position', 'ATM')
            ->where('is_missing', 0)
            ->whereNotNull('expiry_date')
            ->whereRaw("TIME(interval_time) = '10:00:00'");
        if ($expiry) $q->whereDate('expiry_date', $expiry);
        $atm = $q->orderBy('expiry_date')->first();

        if ($atm) return $atm;

        // Step 2: nearest strike to spot (that IS the ATM)
        $q2 = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->whereNotNull('strike')
            ->whereNotNull('expiry_date')
            ->whereRaw("TIME(interval_time) = '10:00:00'");
        if ($expiry) $q2->whereDate('expiry_date', $expiry);
        $nearest = $q2->orderByRaw('ABS(strike - ?)', [$spot])->orderBy('expiry_date')->first();

        if (!$nearest) return null;
        $atmStrike = (float) $nearest->strike;

        // Step 3: find all strikes for this symbol/type/date at 10:00
        $strikes = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->whereNotNull('strike')
            ->whereNotNull('expiry_date')
            ->whereRaw("TIME(interval_time) = '10:00:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($s) => (float) $s)
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $atmIdx = array_search($atmStrike, $strikes);
        if ($atmIdx === false) return $nearest; // fallback

        // CE: ATM-1 = one index below; PE: ATM+1 = one index above
        if ($type === 'CE') {
            $targetStrike = $atmIdx > 0 ? $strikes[$atmIdx - 1] : $atmStrike;
        } else {
            $targetStrike = isset($strikes[$atmIdx + 1]) ? $strikes[$atmIdx + 1] : $atmStrike;
        }

        if ($targetStrike == $atmStrike) return $nearest;

        $q3 = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->where('strike', $targetStrike)
            ->where('is_missing', 0)
            ->whereNotNull('expiry_date')
            ->whereRaw("TIME(interval_time) = '10:00:00'");
        if ($expiry) $q3->whereDate('expiry_date', $expiry);
        $fallback = $q3->orderBy('expiry_date')->first();

        return $fallback ?? $nearest;
    }

    // ─────────────────────────────────────────────────────────────
    //  EMA
    // ─────────────────────────────────────────────────────────────

    private function getEMASignal(string $symbol, string $date, float $price): array
    {
        $start = Carbon::parse($date)->subDays(self::EMA_HISTORY)->toDateString();

        $closes = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')->where('is_missing', 0)
            ->whereDate('trade_date', '>=', $start)
            ->where(function ($q) use ($date) {
                $q->whereDate('trade_date', '<', $date)
                  ->orWhere(fn($q2) => $q2->whereDate('trade_date', $date)
                      ->whereRaw("TIME(interval_time) <= '09:45:00'"));
            })
            ->orderBy('trade_date')->orderBy('interval_time')
            ->pluck('close')->map(fn($v) => (float) $v)->toArray();

        if (count($closes) < self::EMA_LONG) return [null, null, 'N/A'];

        $ema20 = $this->calcEMA($closes, self::EMA_SHORT);
        $ema50 = $this->calcEMA($closes, self::EMA_LONG);
        if (!$ema20 || !$ema50) return [null, null, 'N/A'];

        $signal = match(true) {
            $price > $ema20 && $price > $ema50 => 'ABOVE',
            $price < $ema20 && $price < $ema50 => 'BELOW',
            default                             => 'MIXED',
        };

        return [$ema20, $ema50, $signal];
    }

    private function calcEMA(array $values, int $period): ?float
    {
        if (count($values) < $period) return null;
        $k   = 2 / ($period + 1);
        $ema = array_sum(array_slice($values, 0, $period)) / $period;
        for ($i = $period; $i < count($values); $i++) {
            $ema = $values[$i] * $k + $ema * (1 - $k);
        }
        return $ema;
    }

    // ─────────────────────────────────────────────────────────────
    //  OI SIGNAL
    // ─────────────────────────────────────────────────────────────

    private function getOISignal(float $ce, float $pe): array
    {
        if ($ce > 0 && $pe < 0) return ['signal' => 'BEARISH'];
        if ($ce < 0 && $pe > 0) return ['signal' => 'BULLISH'];
        if ($ce > 0 && $pe > 0) return ['signal' => $ce > $pe ? 'BEARISH' : 'BULLISH'];
        if ($ce < 0 && $pe < 0) return ['signal' => $ce < $pe ? 'BULLISH' : 'BEARISH'];
        return ['signal' => 'NEUTRAL'];
    }

    // ─────────────────────────────────────────────────────────────
    //  EXPIRY HELPERS
    // ─────────────────────────────────────────────────────────────

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));

        if (!$expiry) {
            $expiry = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE','PE'])->whereNotNull('expiry_date')
                ->whereDate('trade_date', $date)->orderByDesc('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
        }
        if (!$expiry) return null;

        if ($expiry === $date) {
            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE','PE'])->whereDate('trade_date', $date)
                ->whereDate('expiry_date', '>', $expiry)->orderBy('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
            if ($next) return $next;
        }
        return $expiry;
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $ok = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)->where('is_missing', 0)->exists();
        if ($ok) return $currentExpiry;
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')->where('is_missing', 0)->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // ─────────────────────────────────────────────────────────────
    //  HELPERS
    // ─────────────────────────────────────────────────────────────

    private function getLotSize(string $symbol): int
    {
        $lots = ['NIFTY'=>25,'BANKNIFTY'=>15,'FINNIFTY'=>25,'MIDCPNIFTY'=>50,'SENSEX'=>10,'BANKEX'=>15];
        $db   = DB::table('zerodha_instruments')->where('name',$symbol)->where('exchange','NFO')
            ->whereIn('instrument_type',['CE','PE'])->value('lot_size');
        return $db ? (int)$db : ($lots[$symbol] ?? 1);
    }

    private function dateStr($v): string
    {
        if ($v instanceof Carbon) return $v->toDateString();
        if (is_string($v))        return substr($v, 0, 10);
        return Carbon::parse($v)->toDateString();
    }

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) return $prev->format('Y-m-d');
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name','NSE')->where('holiday_date',$date)->exists();
    }
}