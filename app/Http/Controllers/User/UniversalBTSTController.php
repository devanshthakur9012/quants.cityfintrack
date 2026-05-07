<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * UniversalBTSTController  — Pro Trader Logic v2
 *
 * ENTRY  : Blended avg of 15:00 + 15:15 candle OHLC averages
 * EXIT   : Blended avg of 09:20 + 09:25 + 09:30 candle OHLC averages
 *          Fallback: 09:15 → 09:30 → 09:45 close
 * WIN    : profit_pct > +0.5%
 * LOSS   : profit_pct < -0.5%
 * NOISE  : |profit_pct| <= 0.5%  → ignored (not counted)
 * FILTER : buyPrice >= 20  (avoids illiquid / junk options)
 * LOTS   : 1 lot only
 */
class UniversalBTSTController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Universal BTST Scanner — All Symbols';
        return view($this->activeTemplate . 'user.universal-btst.index', compact('pageTitle'));
    }

    // =========================================================
    //  MAIN ENDPOINT
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate = $request->get('from_date', date('Y-m-d'));
            $toDate   = $request->get('to_date',   date('Y-m-d'));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            // All symbols with FUT data in range
            $symbols = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select('base_symbol')->distinct()->orderBy('base_symbol')
                ->pluck('base_symbol')->toArray();

            if (empty($symbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols found for selected date range', 'data' => []]);
            }

            // All unique trade dates in range
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereIn('base_symbol', $symbols)
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')
                ->pluck('d')->toArray();

            // symbolStats[symbol] = [wins, losses, noise, skipped, total_tradeable]
            $symbolStats = [];

            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                $nextDate = $this->getNextTradingDate($date);

                // Batch FUT candles at 14:45 and 15:00 for OI/spot
                $fut1445 = OptionOhlcData::whereDate('trade_date', $date)
                    ->where('instrument_type', 'FUT')->whereIn('base_symbol', $symbols)
                    ->whereRaw("TIME(interval_time) = '14:45:00'")
                    ->get(['base_symbol','open','high','low','close'])->keyBy('base_symbol');

                $fut1500 = OptionOhlcData::whereDate('trade_date', $date)
                    ->where('instrument_type', 'FUT')->whereIn('base_symbol', $symbols)
                    ->whereRaw("TIME(interval_time) = '15:00:00'")
                    ->get(['base_symbol','open','high','low','close'])->keyBy('base_symbol');

                $activeSymbols = $fut1445->keys()->merge($fut1500->keys())->unique()->toArray();

                foreach ($activeSymbols as $symbol) {
                    try {
                        $futRow    = $fut1445[$symbol] ?? $fut1500[$symbol] ?? null;
                        if (!$futRow) continue;

                        $spotPrice = (float)($fut1500[$symbol]->close ?? $fut1445[$symbol]->close ?? 0);
                        if ($spotPrice <= 0) $spotPrice = (float)($futRow->open ?? 0);
                        if ($spotPrice <= 0) continue;

                        // Expiry
                        $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
                        $prevExpiry    = $currentExpiry
                            ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                            : null;

                        // OI signal
                        $todayCeOI = $this->sumOI($symbol, $date,     'CE', '14:45:00', $currentExpiry);
                        $todayPeOI = $this->sumOI($symbol, $date,     'PE', '14:45:00', $currentExpiry);
                        if ($todayCeOI == 0 && $todayPeOI == 0) continue;

                        $prevCeOI  = $this->sumOI($symbol, $prevDate, 'CE', '15:00:00', $prevExpiry);
                        $prevPeOI  = $this->sumOI($symbol, $prevDate, 'PE', '15:00:00', $prevExpiry);

                        $ceOiPct = $prevCeOI > 0 ? (($todayCeOI - $prevCeOI) / $prevCeOI) * 100 : 0;
                        $peOiPct = $prevPeOI > 0 ? (($todayPeOI - $prevPeOI) / $prevPeOI) * 100 : 0;

                        $signal = $this->getOISignal($ceOiPct, $peOiPct);
                        if ($signal === 'NEUTRAL') continue;

                        $optionType = $signal === 'BULLISH' ? 'CE' : 'PE';

                        if (!isset($symbolStats[$symbol])) $this->initStat($symbolStats, $symbol);

                        // ── BLENDED BUY PRICE ─────────────────────────────
                        $buyPrice = $this->getBlendedBuyPrice($symbol, $date, $optionType, $spotPrice, $currentExpiry);

                        // Filter: skip illiquid options
                        if ($buyPrice < 20) {
                            $symbolStats[$symbol]['skipped']++;
                            continue;
                        }

                        // ── BLENDED SELL PRICE ────────────────────────────
                        $sellPrice = $this->getBlendedSellPrice($symbol, $nextDate, $optionType, $currentExpiry, $spotPrice);

                        if ($sellPrice <= 0) {
                            $symbolStats[$symbol]['skipped']++;
                            continue;
                        }

                        // ── WIN / LOSS / NOISE ────────────────────────────
                        $profitPct = (($sellPrice - $buyPrice) / $buyPrice) * 100;

                        if ($profitPct > 0.5) {
                            $symbolStats[$symbol]['wins']++;
                            $symbolStats[$symbol]['total_tradeable']++;
                        } elseif ($profitPct < -0.5) {
                            $symbolStats[$symbol]['losses']++;
                            $symbolStats[$symbol]['total_tradeable']++;
                        } else {
                            // noise — don't count as win or loss
                            $symbolStats[$symbol]['noise']++;
                        }

                    } catch (\Exception $e) {
                        Log::error("UniversalBTST ({$symbol}/{$date}): " . $e->getMessage());
                    }
                }
            }

            // Build results — only symbols with at least 1 tradeable day
            $results = [];
            foreach ($symbolStats as $symbol => $s) {
                if ($s['total_tradeable'] === 0) continue;
                $winPct = round(($s['wins'] / $s['total_tradeable']) * 100, 1);
                $results[] = [
                    'symbol'          => $symbol,
                    'total_tradeable' => $s['total_tradeable'],
                    'wins'            => $s['wins'],
                    'losses'          => $s['losses'],
                    'noise'           => $s['noise'],
                    'skipped'         => $s['skipped'],
                    'win_pct'         => $winPct,
                ];
            }

            // Sort: win_pct DESC, then total_tradeable DESC
            usort($results, fn($a, $b) =>
                $b['win_pct'] <=> $a['win_pct'] ?: $b['total_tradeable'] <=> $a['total_tradeable']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_symbols' => count($results),
                'total_dates'   => count($tradeDates),
                'date_range'    => "$fromDate → $toDate",
                'message'       => count($results) . ' symbols across ' . count($tradeDates) . ' trading days',
            ]);

        } catch (\Exception $e) {
            Log::error('UniversalBTST Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  BLENDED BUY PRICE
    //  AVG of OHLC-avg(15:00) and OHLC-avg(15:15)
    //  Removes spike bias — realistic fill
    // =========================================================

    private function getBlendedBuyPrice(
        string  $symbol,
        string  $date,
        string  $optionType,
        float   $spotPrice,
        ?string $expiry
    ): float {
        $avg1500 = $this->candleOhlcAvg($this->getAtmCandle($symbol, $date, $optionType, $spotPrice, $expiry, '15:00:00'));
        $avg1515 = $this->candleOhlcAvg($this->getAtmCandle($symbol, $date, $optionType, $spotPrice, $expiry, '15:15:00'));

        $valid = array_filter([$avg1500, $avg1515], fn($v) => $v >= 1);

        if (empty($valid)) return 0;
        return round(array_sum($valid) / count($valid), 2);
    }

    // =========================================================
    //  BLENDED SELL PRICE
    //  Primary: avg of OHLC-avg for 09:20, 09:25, 09:30
    //  Fallback: close of 09:15 → 09:30 → 09:45
    //  Uses ATM of NEXT day (not locked to original strike)
    //  so missing strikes don't block the exit
    // =========================================================

    private function getBlendedSellPrice(
        string  $symbol,
        string  $nextDate,
        string  $optionType,
        ?string $expiry,
        float   $spotPrice
    ): float {
        // Primary: 3 mid-candles — typical retail execution window
        $primaryTimes = ['09:20:00', '09:25:00', '09:30:00'];
        $avgs = [];

        foreach ($primaryTimes as $time) {
            $candle = $this->getAtmCandle($symbol, $nextDate, $optionType, $spotPrice, $expiry, $time);
            $avg    = $this->candleOhlcAvg($candle);
            if ($avg >= 1) $avgs[] = $avg;
        }

        if (count($avgs) >= 2) {
            return round(array_sum($avgs) / count($avgs), 2);
        }

        // Fallback: use close of first available among 09:15, 09:30, 09:45
        $fallbackTimes = ['09:15:00', '09:30:00', '09:45:00'];
        foreach ($fallbackTimes as $time) {
            $candle = $this->getAtmCandle($symbol, $nextDate, $optionType, $spotPrice, $expiry, $time);
            if (!$candle) continue;
            $price = (float)($candle->close ?? 0);
            if ($price <= 0) $price = (float)($candle->open ?? 0);
            if ($price > 0) return round($price, 2);
        }

        return 0;
    }

    // =========================================================
    //  OHLC AVERAGE of a single candle
    //  Uses only non-zero values to avoid dragging average down
    // =========================================================

    private function candleOhlcAvg($candle): float
    {
        if (!$candle) return 0;
        $vals = array_filter([
            (float)($candle->open  ?? 0),
            (float)($candle->high  ?? 0),
            (float)($candle->low   ?? 0),
            (float)($candle->close ?? 0),
        ], fn($v) => $v > 0);
        return $vals ? array_sum($vals) / count($vals) : 0;
    }

    // =========================================================
    //  GET ATM CANDLE (or nearest by spot)
    // =========================================================

    private function getAtmCandle(
        string  $symbol,
        string  $date,
        string  $optionType,
        float   $spotPrice,
        ?string $expiry,
        string  $time
    ) {
        $base = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->whereNotNull('expiry_date')
            ->whereRaw("TIME(interval_time) = ?", [$time]);

        if ($expiry) $base->whereDate('expiry_date', $expiry);

        // Prefer strike_position = ATM
        $row = (clone $base)->where('strike_position', 'ATM')->orderBy('expiry_date')->first();
        if ($row) return $row;

        // Nearest strike by spot price
        return (clone $base)
            ->whereNotNull('strike')
            ->orderByRaw('ABS(strike - ?)', [$spotPrice])
            ->orderBy('expiry_date')
            ->first();
    }

    // =========================================================
    //  OI SIGNAL
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): string
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return 'BEARISH';
        if ($ceDown && $peUp)   return 'BULLISH';
        if ($ceUp   && $peUp)   return $cePct > $pePct ? 'BEARISH' : 'BULLISH';
        if ($ceDown && $peDown) return $cePct < $pePct ? 'BULLISH' : 'BEARISH';
        return 'NEUTRAL';
    }

    // =========================================================
    //  OI SUM
    // =========================================================

    private function sumOI(string $symbol, string $date, string $type, string $time, ?string $expiry): int
    {
        $q = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = ?", [$time]);
        if ($expiry) $q->whereDate('expiry_date', $expiry);
        return (int)$q->sum('oi');
    }

    // =========================================================
    //  EXPIRY HELPERS
    // =========================================================

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if (!$expiry) {
            $expiry = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE','PE'])
                ->whereNotNull('expiry_date')
                ->whereDate('trade_date', $date)
                ->orderByDesc('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
        }
        if (!$expiry) return null;

        if ($expiry === $date) {
            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE','PE'])
                ->whereNotNull('expiry_date')
                ->whereDate('trade_date', $date)
                ->whereDate('expiry_date', '>', $expiry)
                ->orderBy('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
            if ($next) return $next;
        }
        return $expiry;
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)
            ->where('is_missing', 0)->exists();
        if ($exists) return $currentExpiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE','PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')
            ->where('is_missing', 0)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  STAT INIT
    // =========================================================

    private function initStat(array &$stats, string $symbol): void
    {
        $stats[$symbol] = ['wins' => 0, 'losses' => 0, 'noise' => 0, 'skipped' => 0, 'total_tradeable' => 0];
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

    private function getNextTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->addDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) return $d->format('Y-m-d');
            $d->addDay();
        }
        return Carbon::parse($date)->addDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)->exists();
    }
}