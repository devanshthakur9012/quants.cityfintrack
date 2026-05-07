<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\BrokerApi;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * OIIVTriSentimentController — EOD (3 PM) PE/CE Analysis with 3 Sentiment Modes
 *
 * Same base logic as OIIVAutoController but each symbol row now shows
 * THREE independent sentiment signals computed from different strike sets:
 *
 *  Sentiment 1 — "3-Strike Wide"  : OI summed across ATM-1 + ATM + ATM+1
 *  Sentiment 2 — "ATM Only"       : OI from ATM strike alone
 *  Sentiment 3 — "Directional"    : CE  uses ATM + ATM+1  |  PE uses ATM + ATM-1
 *
 * For each sentiment the same getOISignal() logic applies:
 *   CE↑+PE↓ → BEARISH   |   CE↓+PE↑ → BULLISH
 *   Both↑   → CE%>PE%=BEARISH else BULLISH
 *   Both↓   → CE%<PE%=BULLISH else BEARISH
 *
 * COLUMN TYPES (unchanged from parent):
 *   trade_date    = DATETIME  e.g. "2026-02-02 09:15:00"
 *   interval_time = DATETIME  e.g. "2026-02-02 09:15:00"
 *   → Always use whereDate() for date filtering.
 *
 * PROFIT WINDOW (BTST) — unchanged:
 *   Buy   = signal day ATM close @ 14:45
 *   Exit  = next trading day 09:30 open
 *   High  = MAX(high) from signal day 15:15 → next day 09:30
 *   Low   = MIN(low)  from signal day 15:15 → next day 09:30
 */
class OIIVTriSentimentController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'Tri-Sentiment PE/CE Analysis (3 PM)';
        return view($this->activeTemplate . 'user.oiiv-tri.index', compact('pageTitle'));
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
    //  EXPIRY HELPERS  (identical to OIIVAutoController)
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

    public function analyzeSignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');   // optional

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both dates',
                    'data'    => [],
                ]);
            }

            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

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
            Log::error('TriSentiment Analysis Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR ONE DATE
    // =========================================================

    private function buildRowsForDate(
        string $date,
        string $prevDate,
        array  $symbolFilter,
        ?string $actionFilter
    ): array {

        // ── FUT candles @ 14:45 (to get active symbols + spot price) ──
        $futQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');

        if ($futCandles->isEmpty()) return [];

        $symbols = $futCandles->keys()->toArray();
        $rows    = [];

        foreach ($symbols as $symbol) {
            $futCandle = $futCandles[$symbol];
            if ((float) $futCandle->close <= 0) continue;

            $currentClose = (float) $futCandle->close;

            // ── Resolve expiries ─────────────────────────────────────────
            $currentExpiry = $this->getNearestExpiryForDate($symbol, $date);
            $prevExpiry    = $currentExpiry
                ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                : null;

            // ── Load today's option rows @ 14:45 (current expiry) ────────
            $todayOptQuery = OptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->where('is_missing', 0);
            if ($currentExpiry) $todayOptQuery->whereDate('expiry_date', $currentExpiry);
            $todayOpts = $todayOptQuery->get();  // Collection of option rows

            // ── Load prev day's option rows @ 15:00 (prev expiry) ────────
            $prevOptQuery = OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->where('is_missing', 0);
            if ($prevExpiry) $prevOptQuery->whereDate('expiry_date', $prevExpiry);
            $prevOpts = $prevOptQuery->get();

            // Skip if today has zero options at all
            if ($todayOpts->isEmpty()) continue;

            // ── Resolve ATM strike (closest to spot) ─────────────────────
            $atmStrike = $this->resolveAtmStrike($todayOpts, $currentClose);
            if (!$atmStrike) continue;

            // ── Build the three sentiment sets ───────────────────────────

            // Sentiment 1: ATM-1 + ATM + ATM+1
            $s1 = $this->computeSentiment(
                $todayOpts, $prevOpts,
                [$this->strikeBelow($todayOpts, $atmStrike), $atmStrike, $this->strikeAbove($todayOpts, $atmStrike)],
                [$this->strikeBelow($todayOpts, $atmStrike), $atmStrike, $this->strikeAbove($todayOpts, $atmStrike)]
            );

            // Sentiment 2: ATM only
            $s2 = $this->computeSentiment(
                $todayOpts, $prevOpts,
                [$atmStrike],
                [$atmStrike]
            );

            // Sentiment 3: CE → ATM + ATM+1 | PE → ATM + ATM-1
            $s3 = $this->computeSentiment(
                $todayOpts, $prevOpts,
                [$atmStrike, $this->strikeAbove($todayOpts, $atmStrike)], // CE strikes
                [$this->strikeBelow($todayOpts, $atmStrike), $atmStrike]  // PE strikes
            );

            // Skip row entirely only if ALL three have zero OI (nothing meaningful)
            if ($s1['ce_cur_oi'] == 0 && $s1['pe_cur_oi'] == 0
             && $s2['ce_cur_oi'] == 0 && $s2['pe_cur_oi'] == 0
             && $s3['ce_cur_oi'] == 0 && $s3['pe_cur_oi'] == 0) {
                continue;
            }

            // ── Determine "primary" trade action from Sentiment 1 (default) ─
            $primarySignal   = $this->getOISignal($s1['ce_pct'], $s1['pe_pct']);
            $tradeAction     = match($primarySignal['signal']) {
                'BULLISH' => 'BUY CE',
                'BEARISH' => 'BUY PE',
                default   => 'WAIT',
            };

            if (!empty($actionFilter) && $tradeAction !== $actionFilter) continue;

            // ── FUT OI change ─────────────────────────────────────────────
            $prevFutCandle = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->first();

            $futOI     = (int) ($futCandle->oi ?? 0);
            $futPrevOI = $prevFutCandle ? (int) ($prevFutCandle->oi ?? 0) : 0;
            $futOiPct  = $futPrevOI > 0
                ? round((($futOI - $futPrevOI) / $futPrevOI) * 100, 2)
                : 0;

            // ── 50 MA ─────────────────────────────────────────────────────
            $fut50Ma = $this->getFut50MaSignal($symbol, $date);

            // ── FUT price comparison ──────────────────────────────────────
            $futPrices = $this->getFutPricesFromOhlc($symbol, $date, $prevDate);

            // ── Strength from Sentiment 1 ─────────────────────────────────
            $diff  = abs($s1['ce_pct'] - $s1['pe_pct']);
            if      ($diff > 40) $strengthRank = 'Rank 1';
            elseif  ($diff > 25) $strengthRank = 'Rank 2';
            elseif  ($diff > 10) $strengthRank = 'Rank 3';
            elseif  ($diff > 5)  $strengthRank = 'Rank 4';
            else                 $strengthRank = 'Normal';

            $rows[] = [
                'date'        => $date,
                'symbol'      => $symbol,
                'fut_symbol'  => $futCandle->trading_symbol ?? $symbol,
                'atm_strike'  => $atmStrike,
                'spot_price'  => round($currentClose, 2),

                // ── Sentiment 1 (ATM-1 + ATM + ATM+1) ───────────────────
                's1_ce_oi'          => $s1['ce_cur_oi'],
                's1_pe_oi'          => $s1['pe_cur_oi'],
                's1_ce_pct'         => $s1['ce_pct'],
                's1_pe_pct'         => $s1['pe_pct'],
                's1_signal'         => $s1['signal']['signal'],
                's1_condition'      => $s1['signal']['condition'],
                's1_trade_action'   => match($s1['signal']['signal']) {
                    'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                },

                // ── Sentiment 2 (ATM only) ────────────────────────────────
                's2_ce_oi'          => $s2['ce_cur_oi'],
                's2_pe_oi'          => $s2['pe_cur_oi'],
                's2_ce_pct'         => $s2['ce_pct'],
                's2_pe_pct'         => $s2['pe_pct'],
                's2_signal'         => $s2['signal']['signal'],
                's2_condition'      => $s2['signal']['condition'],
                's2_trade_action'   => match($s2['signal']['signal']) {
                    'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                },

                // ── Sentiment 3 (Directional: CE→ATM+ATM+1, PE→ATM+ATM-1) ─
                's3_ce_oi'          => $s3['ce_cur_oi'],
                's3_pe_oi'          => $s3['pe_cur_oi'],
                's3_ce_pct'         => $s3['ce_pct'],
                's3_pe_pct'         => $s3['pe_pct'],
                's3_signal'         => $s3['signal']['signal'],
                's3_condition'      => $s3['signal']['condition'],
                's3_trade_action'   => match($s3['signal']['signal']) {
                    'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                },

                // ── Derived / shared ──────────────────────────────────────
                'trade_action'    => $tradeAction,   // driven by Sentiment 1
                'strength_rank'   => $strengthRank,
                'strength_diff'   => round($diff, 2),

                'fut_oi'              => $futOI,
                'fut_oi_prev'         => $futPrevOI,
                'fut_oi_change_pct'   => $futOiPct,

                'fut_price_prev'       => $futPrices['fut_price_prev'],
                'fut_price_today'      => $futPrices['fut_price_today'],
                'fut_price_change'     => $futPrices['fut_price_change'],
                'fut_price_change_pct' => $futPrices['fut_price_change_pct'],
                'fut_price_signal'     => $futPrices['fut_price_signal'],

                'fut_50ma_signal'  => $fut50Ma,
                'current_expiry'   => $currentExpiry,
                'prev_expiry'      => $prevExpiry,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  CORE: COMPUTE ONE SENTIMENT BLOCK
    // =========================================================

    /**
     * Compute CE/PE OI change % for a given set of CE strikes and PE strikes.
     *
     * @param  \Illuminate\Support\Collection $todayOpts   Today's option candles
     * @param  \Illuminate\Support\Collection $prevOpts    Prev day's option candles
     * @param  array  $ceStrikes  Strikes to sum for CE OI
     * @param  array  $peStrikes  Strikes to sum for PE OI
     * @return array  [ce_cur_oi, pe_cur_oi, ce_prev_oi, pe_prev_oi, ce_pct, pe_pct, signal]
     */
    private function computeSentiment(
        $todayOpts,
        $prevOpts,
        array $ceStrikes,
        array $peStrikes
    ): array {
        $ceStrikes = array_filter($ceStrikes);   // remove nulls
        $peStrikes = array_filter($peStrikes);

        $ceCurOI  = (int) $todayOpts->whereIn('instrument_type', ['CE'])
            ->whereIn('strike', $ceStrikes)->sum('oi');
        $peCurOI  = (int) $todayOpts->whereIn('instrument_type', ['PE'])
            ->whereIn('strike', $peStrikes)->sum('oi');

        $cePrevOI = (int) $prevOpts->whereIn('instrument_type', ['CE'])
            ->whereIn('strike', $ceStrikes)->sum('oi');
        $pePrevOI = (int) $prevOpts->whereIn('instrument_type', ['PE'])
            ->whereIn('strike', $peStrikes)->sum('oi');

        $cePct = $cePrevOI > 0 ? round((($ceCurOI - $cePrevOI) / $cePrevOI) * 100, 4) : 0;
        $pePct = $pePrevOI > 0 ? round((($peCurOI - $pePrevOI) / $pePrevOI) * 100, 4) : 0;

        return [
            'ce_cur_oi'  => $ceCurOI,
            'pe_cur_oi'  => $peCurOI,
            'ce_prev_oi' => $cePrevOI,
            'pe_prev_oi' => $pePrevOI,
            'ce_pct'     => $cePct,
            'pe_pct'     => $pePct,
            'signal'     => $this->getOISignal($cePct, $pePct),
        ];
    }

    // =========================================================
    //  ATM STRIKE HELPERS
    // =========================================================

    /**
     * Resolve ATM strike — prefers strike_position='ATM' flag,
     * falls back to closest strike to spot price.
     */
    private function resolveAtmStrike($optRows, float $spotPrice): ?float
    {
        // Prefer explicitly flagged ATM row
        $flagged = $optRows->where('strike_position', 'ATM')->first();
        if ($flagged) return (float) $flagged->strike;

        // Fallback: unique strikes, closest to spot
        $strikes = $optRows->pluck('strike')
            ->filter()
            ->unique()
            ->map(fn($s) => (float) $s)
            ->values();

        if ($strikes->isEmpty()) return null;

        return $strikes->sortBy(fn($s) => abs($s - $spotPrice))->first();
    }

    /**
     * Return the next strike ABOVE $atm from available option rows.
     * "ATM+1" = one strike step up (e.g. 100-point gap for NIFTY).
     */
    private function strikeAbove($optRows, float $atm): ?float
    {
        $higher = $optRows->pluck('strike')
            ->filter()
            ->unique()
            ->map(fn($s) => (float) $s)
            ->filter(fn($s) => $s > $atm)
            ->sort()
            ->values();

        return $higher->isNotEmpty() ? $higher->first() : null;
    }

    /**
     * Return the next strike BELOW $atm from available option rows.
     * "ATM-1" = one strike step down.
     */
    private function strikeBelow($optRows, float $atm): ?float
    {
        $lower = $optRows->pluck('strike')
            ->filter()
            ->unique()
            ->map(fn($s) => (float) $s)
            ->filter(fn($s) => $s < $atm)
            ->sortDesc()
            ->values();

        return $lower->isNotEmpty() ? $lower->first() : null;
    }

    // =========================================================
    //  OI SIGNAL (unchanged from OIIVAutoController)
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup CE stronger (CE:+{$cePct}% > PE:+{$pePct}%)", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup PE stronger (PE:+{$pePct}% > CE:+{$cePct}%)", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding CE stronger (CE:{$cePct}% < PE:{$pePct}%)", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding PE stronger (PE:{$pePct}% < CE:{$cePct}%)", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // =========================================================
    //  50 MA  (unchanged from OIIVAutoController)
    // =========================================================

    private function calculateRollingMA(array $values, int $period): array
    {
        $ma  = [];
        $n   = count($values);
        $sum = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sum += $values[$i];
            if ($i >= $period) $sum -= $values[$i - $period];
            $ma[] = ($i >= $period - 1) ? ($sum / $period) : null;
        }

        return $ma;
    }

    private function getFut50MaSignal(string $baseSymbol, string $tradeDate): string
    {
        $maPeriod     = 50;
        $historyStart = Carbon::parse($tradeDate)->subDays(120)->toDateString();

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereDate('trade_date', '>=', $historyStart)
            ->whereDate('trade_date', '<=', $tradeDate)
            ->orderBy('trade_date')
            ->orderBy('interval_time')
            ->get([
                DB::raw("DATE(trade_date) as candle_date"),
                DB::raw("TIME(interval_time) as candle_time"),
                'close',
            ]);

        if ($allCandles->isEmpty()) return 'N/A';

        $closeValues = $allCandles->pluck('close')->map(fn($v) => (float) $v)->toArray();
        $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

        $targetIdx = null;
        foreach ($allCandles as $idx => $candle) {
            $candleDate = is_string($candle->candle_date)
                ? $candle->candle_date
                : Carbon::parse($candle->candle_date)->toDateString();
            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($candleDate === $tradeDate && $time >= '14:45' && $time <= '15:15') {
                $targetIdx = $idx;
                break;
            }
        }

        if ($targetIdx === null) {
            foreach ($allCandles as $idx => $candle) {
                $candleDate = is_string($candle->candle_date)
                    ? $candle->candle_date
                    : Carbon::parse($candle->candle_date)->toDateString();
                if ($candleDate === $tradeDate) $targetIdx = $idx;
            }
        }

        if ($targetIdx === null || !isset($closeMa[$targetIdx])) return 'N/A';

        $close = $closeValues[$targetIdx];
        $ma    = $closeMa[$targetIdx];

        if ($ma === null)  return 'N/A';
        if ($close > $ma)  return 'BULLISH';
        if ($close < $ma)  return 'BEARISH';
        return 'NEUTRAL';
    }

    // =========================================================
    //  FUT PRICES  (unchanged from OIIVAutoController)
    // =========================================================

    private function getFutPricesFromOhlc(string $baseSymbol, string $date, string $prevDate): array
    {
        try {
            $todayCandle = OptionOhlcData::where('base_symbol', $baseSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->first();

            $futPriceToday = $todayCandle ? (float) $todayCandle->close : 0;

            $prevCandle = OptionOhlcData::where('base_symbol', $baseSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->first();

            $futPricePrev = $prevCandle ? (float) $prevCandle->close : 0;

            $priceChange    = 0;
            $priceChangePct = 0;

            if ($futPricePrev > 0 && $futPriceToday > 0) {
                $priceChange    = $futPriceToday - $futPricePrev;
                $priceChangePct = round(($priceChange / $futPricePrev) * 100, 2);
            }

            $signal = 'N/A';
            if ($futPricePrev > 0 && $futPriceToday > 0) {
                $signal = $futPriceToday > $futPricePrev ? 'BULLISH'
                        : ($futPriceToday < $futPricePrev ? 'BEARISH' : 'NEUTRAL');
            }

            return [
                'fut_price_today'      => round($futPriceToday, 2),
                'fut_price_prev'       => round($futPricePrev, 2),
                'fut_price_change'     => round($priceChange, 2),
                'fut_price_change_pct' => $priceChangePct,
                'fut_price_signal'     => $signal,
            ];

        } catch (\Exception $e) {
            Log::error("getFutPricesFromOhlc ({$baseSymbol}): " . $e->getMessage());
            return [
                'fut_price_today'      => 0,
                'fut_price_prev'       => 0,
                'fut_price_change'     => 0,
                'fut_price_change_pct' => 0,
                'fut_price_signal'     => 'N/A',
            ];
        }
    }

    // =========================================================
    //  CALCULATE PROFIT  — BTST WINDOW  (unchanged logic)
    // =========================================================

    public function calculateProfit(Request $request)
    {
        $signals = $request->input('signals', []);

        if (empty($signals)) {
            return response()->json(['success' => false, 'message' => 'No signals provided', 'data' => []]);
        }

        $results = [];

        foreach ($signals as $signal) {
            $idx         = (int)   ($signal['index']       ?? 0);
            $symbol      =          $signal['symbol']       ?? '';
            $tradeDate   =          $signal['date']         ?? '';
            $tradeAction =          $signal['trade_action'] ?? '';
            $spotPrice   = (float) ($signal['spot_price']  ?? 0);

            $placeholder = [
                'index'         => $idx,
                'option_symbol' => null,
                'strike'        => null,
                'option_type'   => null,
                'buy_price'     => 0,
                'lot_size'      => 0,
                'investment'    => 0,
                'exit_price'    => 0, 'exit_pl' => 0, 'exit_roi' => 0,
                'high_price'    => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
                'low_price'     => 0, 'low_time'  => null, 'low_pl'  => 0, 'low_roi'  => 0,
                'profit_loss'   => 0,
                'roi_percent'   => 0,
                'error'         => null,
            ];

            if (!$symbol || !$tradeDate || !in_array($tradeAction, ['BUY CE', 'BUY PE'])) {
                $placeholder['error'] = 'WAIT';
                $results[] = $placeholder;
                continue;
            }

            try {
                $optionType    = $tradeAction === 'BUY CE' ? 'CE' : 'PE';
                $nextDate      = $this->getNextTradingDate($tradeDate);
                $currentExpiry = $this->getNearestExpiryForDate($symbol, $tradeDate);

                // ── ATM option @ 14:45 on signal day ─────────────────────
                $atmQuery = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('strike_position', 'ATM')
                    ->where('is_missing', 0)
                    ->whereNotNull('expiry_date')
                    ->whereRaw("TIME(interval_time) = '14:45:00'");

                if ($currentExpiry) $atmQuery->whereDate('expiry_date', $currentExpiry);
                $atmRow = $atmQuery->orderBy('expiry_date')->first();

                // Fallback: nearest strike to spot
                if (!$atmRow) {
                    $fb = OptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) = '14:45:00'")
                        ->whereNotNull('strike')
                        ->whereNotNull('expiry_date');

                    if ($currentExpiry) $fb->whereDate('expiry_date', $currentExpiry);
                    $atmRow = $fb->orderByRaw('ABS(strike - ?)', [$spotPrice])
                        ->orderBy('expiry_date')
                        ->first();
                }

                if (!$atmRow) {
                    $placeholder['error'] = 'NO_ATM_ROW';
                    $results[] = $placeholder;
                    continue;
                }

                $strike     = $atmRow->strike;
                $expiryDate = $this->dateStr($atmRow->expiry_date);
                $buyPrice   = (float) ($atmRow->close ?? 0);
                if ($buyPrice <= 0) $buyPrice = (float) ($atmRow->open ?? 0);

                if ($buyPrice <= 0) {
                    $placeholder['error']         = 'NO_BUY_PRICE';
                    $placeholder['option_symbol'] = $atmRow->trading_symbol ?? null;
                    $placeholder['strike']        = $strike;
                    $placeholder['option_type']   = $optionType;
                    $results[] = $placeholder;
                    continue;
                }

                // ── Exit: next day 09:30 open ─────────────────────────────
                $exitRow = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $nextDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) = '09:30:00'")
                    ->first();

                $exitPrice = 0;
                if ($exitRow) {
                    $exitPrice = (float) ($exitRow->open ?? 0);
                    if ($exitPrice <= 0) $exitPrice = (float) ($exitRow->close ?? 0);
                }

                // ── Window candles: today 15:15 → next day 09:30 ─────────
                $windowCandles = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->where('is_missing', 0)
                    ->where(function ($q) use ($tradeDate, $nextDate) {
                        $q->where(function ($q2) use ($tradeDate) {
                            $q2->whereDate('trade_date', $tradeDate)
                               ->whereRaw("TIME(interval_time) >= '15:15:00'");
                        })->orWhere(function ($q2) use ($nextDate) {
                            $q2->whereDate('trade_date', $nextDate)
                               ->whereRaw("TIME(interval_time) <= '09:30:00'");
                        });
                    })
                    ->get(['high', 'low', 'interval_time']);

                if ($windowCandles->isNotEmpty()) {
                    $highRow   = $windowCandles->sortByDesc('high')->first();
                    $lowRow    = $windowCandles->sortBy('low')->first();
                    $highPrice = (float) $highRow->high;
                    $highTime  = Carbon::parse($highRow->interval_time)->format('H:i');
                    $lowPrice  = (float) $lowRow->low;
                    $lowTime   = Carbon::parse($lowRow->interval_time)->format('H:i');
                } else {
                    $highPrice = $exitRow ? (float) ($exitRow->high ?? $buyPrice) : $buyPrice;
                    $highTime  = null;
                    $lowPrice  = $exitRow ? (float) ($exitRow->low  ?? $buyPrice) : $buyPrice;
                    $lowTime   = null;
                }

                $lotSize    = $this->getLotSize($symbol);
                $investment = round($buyPrice * $lotSize, 2);

                $exitPL  = $exitPrice > 0 ? round(($exitPrice - $buyPrice) * $lotSize, 2) : 0;
                $exitRoi = ($investment > 0 && $exitPrice > 0)
                    ? round(($exitPL / $investment) * 100, 2) : 0;

                $highPL  = round(($highPrice - $buyPrice) * $lotSize, 2);
                $highRoi = $investment > 0 ? round(($highPL / $investment) * 100, 2) : 0;
                $lowPL   = round(($lowPrice - $buyPrice) * $lotSize, 2);
                $lowRoi  = $investment > 0 ? round(($lowPL / $investment) * 100, 2) : 0;

                $results[] = [
                    'index'         => $idx,
                    'option_symbol' => $atmRow->trading_symbol ?? "{$symbol}{$optionType}{$strike}",
                    'strike'        => $strike,
                    'option_type'   => $optionType,
                    'lot_size'      => $lotSize,
                    'investment'    => $investment,
                    'buy_price'     => round($buyPrice, 2),
                    'exit_price'    => round($exitPrice, 2),
                    'exit_pl'       => $exitPL,
                    'exit_roi'      => $exitRoi,
                    'high_price'    => round($highPrice, 2),
                    'high_time'     => $highTime,
                    'high_pl'       => $highPL,
                    'high_roi'      => $highRoi,
                    'low_price'     => round($lowPrice, 2),
                    'low_time'      => $lowTime,
                    'low_pl'        => $lowPL,
                    'low_roi'       => $lowRoi,
                    'profit_loss'   => $exitPL,
                    'roi_percent'   => $exitRoi,
                    'error'         => null,
                ];

            } catch (\Exception $e) {
                Log::error("TriSentiment Profit row error (idx={$idx}): " . $e->getMessage());
                $placeholder['error'] = 'EXCEPTION: ' . $e->getMessage();
                $results[] = $placeholder;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $results,
            'message' => count($results) . ' profit records calculated',
        ]);
    }

    // =========================================================
    //  LOT SIZE  (unchanged)
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

    // =========================================================
    //  DATE HELPERS  (unchanged)
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