<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * NearStrikeOIController — OI Analysis using ONLY Near Strikes
 *
 * Instead of summing ALL strikes (like OIIVAutoController), this controller
 * fetches ONLY the 4 strikes closest to ATM:
 *   ATM - 5  (put side deep)
 *   ATM - 4  (put side near)
 *   ATM + 4  (call side near)
 *   ATM + 5  (call side deep)
 *
 * Logic:
 *   CE OI  = sum of OI at (ATM+4) and (ATM+5) strikes for CE options
 *   PE OI  = sum of OI at (ATM-4) and (ATM-5) strikes for PE options
 *   Change = compare today 14:45 vs prev day 15:00
 *
 * All existing signal logic (OI signal, strength rank, MM trap, 50MA,
 * Gann Octave, Price Signal, profit calc) is preserved exactly.
 *
 * COLUMN TYPES:
 *   trade_date    = DATETIME  — always use whereDate()
 *   interval_time = DATETIME  — use whereRaw("TIME(interval_time) = 'HH:MM:SS'")
 */
class NearStrikeOIController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'Near Strike OI Analysis (ATM ±4 / ±5)';
        return view($this->activeTemplate . 'user.near-strike-oi.index', compact('pageTitle'));
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
    //  ATM STRIKE RESOLVER
    //  Returns the ATM strike price for a symbol on a given date.
    //  Uses strike_position = 'ATM' first; falls back to nearest
    //  strike to FUT close price.
    // =========================================================

    private function getAtmStrike(string $symbol, string $date, ?string $expiry): ?float
    {
        // Prefer strike_position = 'ATM' from option rows
        $q = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->where('strike_position', 'ATM')
            ->whereNotNull('strike')
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if ($expiry) $q->whereDate('expiry_date', $expiry);

        $row = $q->first();
        if ($row && $row->strike > 0) return (float) $row->strike;

        // Fallback: nearest strike to FUT close
        $futRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first();

        if (!$futRow || (float) $futRow->close <= 0) return null;

        $futPrice = (float) $futRow->close;

        $nearestRow = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereNotNull('strike')
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if ($expiry) $nearestRow->whereDate('expiry_date', $expiry);

        $nearestRow = $nearestRow
            ->orderByRaw('ABS(strike - ?)', [$futPrice])
            ->first();

        return $nearestRow ? (float) $nearestRow->strike : null;
    }

    // =========================================================
    //  STRIKE STEP RESOLVER
    //  Detects the standard strike interval for a symbol
    //  (e.g. 50 for NIFTY, 100 for BANKNIFTY, etc.)
    // =========================================================

    private function getStrikeStep(string $symbol, string $date, ?string $expiry): float
    {
        // Hard-coded well-known steps
        $known = [
            'NIFTY'      => 50,
            'BANKNIFTY'  => 100,
            'FINNIFTY'   => 50,
            'MIDCPNIFTY' => 25,
            'SENSEX'     => 100,
            'BANKEX'     => 100,
        ];

        if (isset($known[$symbol])) return (float) $known[$symbol];

        // Derive from data: find two adjacent strikes and compute gap
        $q = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereNotNull('strike')
            ->where('is_missing', 0)
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if ($expiry) $q->whereDate('expiry_date', $expiry);

        $strikes = $q->distinct()
            ->orderBy('strike')
            ->pluck('strike')
            ->map(fn($v) => (float) $v)
            ->toArray();

        if (count($strikes) < 2) return 50; // safe default

        // Find minimum positive gap
        $minGap = null;
        for ($i = 1; $i < count($strikes); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && ($minGap === null || $gap < $minGap)) {
                $minGap = $gap;
            }
        }

        return $minGap ?? 50;
    }

    // =========================================================
    //  NEAR STRIKE OI FETCHER  ← CORE NEW LOGIC
    //
    //  Returns OI summed for ONLY the 4 near strikes:
    //    CE: ATM+4 steps and ATM+5 steps  (call side)
    //    PE: ATM-4 steps and ATM-5 steps  (put side)
    //
    //  For CE type: we look at CE options at (atm + 4*step) and (atm + 5*step)
    //  For PE type: we look at PE options at (atm - 4*step) and (atm - 5*step)
    // =========================================================

    private function getNearStrikeOI(
        string  $symbol,
        string  $instrumentType,   // 'CE' or 'PE'
        string  $date,
        string  $time,             // e.g. '14:45:00'
        ?string $expiry,
        float   $atmStrike,
        float   $step
    ): array {
        // Determine which strikes to query
        if ($instrumentType === 'CE') {
            $strikes = [
                $atmStrike + (4 * $step),
                $atmStrike + (5 * $step),
            ];
        } else {
            $strikes = [
                $atmStrike - (4 * $step),
                $atmStrike - (5 * $step),
            ];
        }

        $rows = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $instrumentType)
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = ?", [$time])
            ->whereIn('strike', $strikes)
            ->where('is_missing', 0);

        if ($expiry) $rows->whereDate('expiry_date', $expiry);

        $data = $rows->get(['strike', 'oi', 'close', 'open', 'high', 'low']);

        $totalOI  = 0;
        $detail   = [];

        foreach ($data as $row) {
            $oi = (int) ($row->oi ?? 0);
            $totalOI += $oi;
            $detail[] = [
                'strike' => (float) $row->strike,
                'oi'     => $oi,
                'close'  => round((float) $row->close, 2),
            ];
        }

        return [
            'total_oi' => $totalOI,
            'strikes'  => $strikes,
            'detail'   => $detail,
        ];
    }

    // =========================================================
    //  MAIN ANALYSIS ENDPOINT
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
            Log::error('NearStrikeOI Analysis Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR A SINGLE DATE
    // =========================================================

    private function buildRowsForDate(string $date, string $prevDate, array $symbolFilter, ?string $actionFilter): array
    {
        $futQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');

        if ($futCandles->isEmpty()) return [];

        $rows = [];

        foreach ($futCandles as $symbol => $futCandle) {
            if ((float) $futCandle->close <= 0) continue;

            try {
                $currentClose = (float) $futCandle->close;

                // ── Resolve expiry ────────────────────────────────────
                $rawExpiry     = $this->getNearestExpiryForDate($symbol, $date);
                $isExpiryDay   = ($rawExpiry !== null && $rawExpiry === $date);
                $currentExpiry = $this->resolveActiveExpiry($symbol, $date);
                $prevExpiry    = $currentExpiry
                    ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                    : null;

                // ── ATM strike + step for TODAY ───────────────────────
                $atmStrike = $this->getAtmStrike($symbol, $date, $currentExpiry);
                if (!$atmStrike) continue;

                $step = $this->getStrikeStep($symbol, $date, $currentExpiry);

                // ── ATM strike for PREV day ───────────────────────────
                // We use today's ATM for prev day too (apples-to-apples comparison)
                // because we want to track the same strike chain over time.
                $prevAtmStrike = $this->getAtmStrike($symbol, $prevDate, $prevExpiry) ?? $atmStrike;
                $prevStep      = $this->getStrikeStep($symbol, $prevDate, $prevExpiry);

                // ── TODAY: CE OI at ATM+4 and ATM+5 ──────────────────
                $todayCE = $this->getNearStrikeOI($symbol, 'CE', $date, '14:45:00', $currentExpiry, $atmStrike, $step);

                // ── TODAY: PE OI at ATM-4 and ATM-5 ──────────────────
                $todayPE = $this->getNearStrikeOI($symbol, 'PE', $date, '14:45:00', $currentExpiry, $atmStrike, $step);

                // ── PREV: CE OI at same ATM±4/±5 (prev day 15:00) ────
                $prevCE = $this->getNearStrikeOI($symbol, 'CE', $prevDate, '15:00:00', $prevExpiry, $prevAtmStrike, $prevStep);

                // ── PREV: PE OI ───────────────────────────────────────
                $prevPE = $this->getNearStrikeOI($symbol, 'PE', $prevDate, '15:00:00', $prevExpiry, $prevAtmStrike, $prevStep);

                $ceCurOI  = $todayCE['total_oi'];
                $peCurOI  = $todayPE['total_oi'];
                $ceOpenOI = $prevCE['total_oi'];
                $peOpenOI = $prevPE['total_oi'];

                // Skip if genuinely no data
                if ($ceCurOI == 0 && $peCurOI == 0) continue;

                $ceOiPct = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
                $peOiPct = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;

                $oiSignal    = $this->getOISignal($ceOiPct, $peOiPct);
                $peCeRatio   = $ceCurOI > 0 ? round($peCurOI / $ceCurOI, 2) : 0;
                $tradeAction = match($oiSignal['signal']) {
                    'BULLISH' => 'BUY CE',
                    'BEARISH' => 'BUY PE',
                    default   => 'WAIT',
                };

                if (!empty($actionFilter) && $tradeAction !== $actionFilter) continue;

                // ── Strength rank ─────────────────────────────────────
                $absCe = abs($ceOiPct);
                $absPe = abs($peOiPct);
                $diff  = abs($ceOiPct - $peOiPct);

                if      ($diff > 40) $strengthRank = 'Rank 1';
                elseif  ($diff > 25) $strengthRank = 'Rank 2';
                elseif  ($diff > 10) $strengthRank = 'Rank 3';
                elseif  ($diff > 5)  $strengthRank = 'Rank 4';
                else                 $strengthRank = 'Normal';

                $isBoth       = str_contains($oiSignal['condition'], 'Both');
                $strongerSide = $isBoth
                    ? ($absCe > $absPe ? 'CE' : ($absPe > $absCe ? 'PE' : 'EQUAL'))
                    : 'CLEAR';

                // ── FUT OI change ─────────────────────────────────────
                $prevFutCandle = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', 'FUT')
                    ->whereDate('trade_date', $prevDate)
                    ->whereRaw("TIME(interval_time) = '15:00:00'")
                    ->first();

                $futOI     = (int) ($futCandle->oi ?? 0);
                $futPrevOI = $prevFutCandle ? (int) ($prevFutCandle->oi ?? 0) : 0;
                $futOiPct  = $futPrevOI > 0 ? round((($futOI - $futPrevOI) / $futPrevOI) * 100, 2) : 0;

                $futPrices   = $this->getFutPrices($symbol, $date, $prevDate);
                $fut50Ma     = $this->getFut50MaSignal($symbol, $date);
                $priceSignal = $this->getPriceSignal($symbol, $date, $prevDate);
                $gannOctave  = $this->getGannOctave($symbol, $date);

                $rows[] = [
                    'date'    => $date,
                    'symbol'  => $symbol,
                    'fut_symbol' => $futCandle->trading_symbol ?? $symbol,

                    // ATM info
                    'atm_strike'  => $atmStrike,
                    'strike_step' => $step,
                    'is_expiry_day' => $isExpiryDay,

                    // CE near-strike OI
                    'ce_oi'            => $ceCurOI,
                    'ce_oi_prev'       => $ceOpenOI,
                    'ce_oi_change_pct' => $ceOiPct,
                    'ce_strikes'       => $todayCE['strikes'],       // [ATM+4, ATM+5]
                    'ce_detail'        => $todayCE['detail'],        // per-strike breakdown
                    'ce_prev_strikes'  => $prevCE['strikes'],
                    'ce_prev_detail'   => $prevCE['detail'],

                    // PE near-strike OI
                    'pe_oi'            => $peCurOI,
                    'pe_oi_prev'       => $peOpenOI,
                    'pe_oi_change_pct' => $peOiPct,
                    'pe_strikes'       => $todayPE['strikes'],       // [ATM-4, ATM-5]
                    'pe_detail'        => $todayPE['detail'],
                    'pe_prev_strikes'  => $prevPE['strikes'],
                    'pe_prev_detail'   => $prevPE['detail'],

                    // FUT OI
                    'fut_oi'            => $futOI,
                    'fut_oi_prev'       => $futPrevOI,
                    'fut_oi_change_pct' => $futOiPct,

                    // Signal
                    'oi_signal'     => $oiSignal['signal'],
                    'oi_condition'  => $oiSignal['condition'],
                    'oi_reason'     => $oiSignal['reason'],
                    'trade_action'  => $tradeAction,
                    'pe_ce_ratio'   => $peCeRatio,

                    // Strength
                    'strength_rank' => $strengthRank,
                    'strength_diff' => round($diff, 2),
                    'stronger_side' => $strongerSide,

                    // Price
                    'spot_price'          => round($currentClose, 2),
                    'fut_price_today'      => $futPrices['fut_price_today'],
                    'fut_price_prev'       => $futPrices['fut_price_prev'],
                    'fut_price_change_pct' => $futPrices['fut_price_change_pct'],
                    'fut_price_signal'     => $futPrices['fut_price_signal'],

                    // Extra signals
                    'fut_50ma_signal'     => $fut50Ma,
                    'price_signal'        => $priceSignal['signal'],
                    'price_change_pct'    => $priceSignal['change_pct'],
                    'gann_bias'           => $gannOctave['bias'],
                    'gann_zone'           => $gannOctave['zone'],
                ];

            } catch (\Exception $e) {
                Log::error("NearStrikeOI row error ({$symbol} {$date}): " . $e->getMessage());
            }
        }

        return $rows;
    }

    // =========================================================
    //  OI SIGNAL LOGIC  (identical to OIIVAutoController)
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
    //  50 MA  (identical to OIIVAutoController)
    // =========================================================

    private function calculateRollingMA(array $values, int $period): array
    {
        $ma = []; $n = count($values); $sum = 0.0;
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
                $targetIdx = $idx; break;
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
    //  PRICE SIGNAL  (identical to OIIVAutoController)
    // =========================================================

    private function getPriceSignal(string $symbol, string $date, string $prevDate): array
    {
        $todayCandle = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first();

        $prevCandle = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->first();

        if (!$todayCandle || !$prevCandle) return ['signal' => 'N/A', 'today_close' => 0, 'prev_close' => 0, 'change_pct' => 0];

        $todayClose = (float) $todayCandle->close;
        $prevClose  = (float) $prevCandle->close;
        if ($prevClose <= 0 || $todayClose <= 0) return ['signal' => 'N/A', 'today_close' => $todayClose, 'prev_close' => $prevClose, 'change_pct' => 0];

        $changePct = (($todayClose - $prevClose) / $prevClose) * 100;
        $signal    = $todayClose > $prevClose ? 'BULLISH' : ($todayClose < $prevClose ? 'BEARISH' : 'NEUTRAL');

        return ['signal' => $signal, 'today_close' => round($todayClose, 2), 'prev_close' => round($prevClose, 2), 'change_pct' => round($changePct, 2)];
    }

    // =========================================================
    //  GANN OCTAVE  (identical to OIIVAutoController)
    // =========================================================

    private function getGannOctave(string $symbol, string $date): array
    {
        $noData = ['bias' => 'N/A', 'zone' => null, 'near_level' => null, 'near_price' => null, 'distance_pct' => null, 'swing_high' => null, 'swing_low' => null];

        $startDate = Carbon::parse($date)->subDays(20)->toDateString();
        $candles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
            ->whereDate('trade_date', '>=', $startDate)
            ->whereDate('trade_date', '<=', $date)
            ->get(['high', 'low', 'close']);

        if ($candles->isEmpty()) return $noData;

        $swingHigh = (float) $candles->max('high');
        $swingLow  = (float) $candles->min('low');
        if ($swingHigh <= $swingLow) return $noData;

        $range  = $swingHigh - $swingLow;
        $octave = $range / 8;
        $levels = [];
        for ($i = 0; $i <= 8; $i++) $levels[$i] = round($swingLow + ($octave * $i), 2);

        $currentRow = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first();

        $currentPrice = $currentRow ? (float) $currentRow->close : null;
        if (!$currentPrice || $currentPrice <= 0) return array_merge($noData, ['swing_high' => $swingHigh, 'swing_low' => $swingLow]);

        $zoneIndex = 0;
        if ($currentPrice <= $levels[0]) $zoneIndex = 0;
        elseif ($currentPrice >= $levels[8]) $zoneIndex = 7;
        else {
            for ($i = 0; $i < 8; $i++) {
                if ($currentPrice >= $levels[$i] && $currentPrice <= $levels[$i + 1]) { $zoneIndex = $i; break; }
            }
        }

        $bias = match(true) {
            $zoneIndex >= 6 => 'STRONG BULLISH',
            $zoneIndex >= 4 => 'BULLISH',
            $zoneIndex <= 1 => 'STRONG BEARISH',
            default         => 'BEARISH',
        };

        $nearestIdx = 0; $minDist = PHP_FLOAT_MAX;
        foreach ($levels as $idx => $lvlPrice) {
            $dist = abs($currentPrice - $lvlPrice);
            if ($dist < $minDist) { $minDist = $dist; $nearestIdx = $idx; }
        }

        return [
            'bias'         => $bias,
            'zone'         => $zoneIndex . '/8',
            'near_level'   => $nearestIdx . '/8',
            'near_price'   => round($levels[$nearestIdx], 2),
            'distance_pct' => round(($minDist / $currentPrice) * 100, 2),
            'swing_high'   => $swingHigh,
            'swing_low'    => $swingLow,
        ];
    }

    // =========================================================
    //  FUT PRICE COMPARISON  (identical to OIIVAutoController)
    // =========================================================

    private function getFutPrices(string $baseSymbol, string $date, string $prevDate): array
    {
        $todayCandle = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first();

        $prevCandle = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->first();

        $futPriceToday = $todayCandle ? (float) $todayCandle->close : 0;
        $futPricePrev  = $prevCandle  ? (float) $prevCandle->close  : 0;
        $priceChange   = ($futPricePrev > 0 && $futPriceToday > 0) ? $futPriceToday - $futPricePrev : 0;
        $priceChangePct= $futPricePrev > 0 ? round(($priceChange / $futPricePrev) * 100, 2) : 0;
        $signal        = $futPricePrev > 0 && $futPriceToday > 0
            ? ($futPriceToday > $futPricePrev ? 'BULLISH' : ($futPriceToday < $futPricePrev ? 'BEARISH' : 'NEUTRAL'))
            : 'N/A';

        return [
            'fut_price_today'      => round($futPriceToday, 2),
            'fut_price_prev'       => round($futPricePrev, 2),
            'fut_price_change'     => round($priceChange, 2),
            'fut_price_change_pct' => $priceChangePct,
            'fut_price_signal'     => $signal,
        ];
    }

    // =========================================================
    //  PROFIT CALCULATION  (ATM option BTST — same as OIIVAutoController)
    // =========================================================

    public function calculateProfit(Request $request)
    {
        $signals = $request->input('signals', []);
        if (empty($signals)) return response()->json(['success' => false, 'message' => 'No signals', 'data' => []]);

        $results = [];

        foreach ($signals as $signal) {
            $idx         = (int)   ($signal['index']       ?? 0);
            $symbol      =          $signal['symbol']       ?? '';
            $tradeDate   =          $signal['date']         ?? '';
            $tradeAction =          $signal['trade_action'] ?? '';
            $spotPrice   = (float) ($signal['spot_price']  ?? 0);

            $placeholder = [
                'index' => $idx, 'option_symbol' => null, 'strike' => null,
                'option_type' => null, 'buy_price' => 0, 'lot_size' => 0,
                'investment' => 0, 'exit_price' => 0, 'exit_pl' => 0, 'exit_roi' => 0,
                'high_price' => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
                'low_price' => 0, 'low_time' => null, 'low_pl' => 0, 'low_roi' => 0,
                'profit_loss' => 0, 'roi_percent' => 0, 'error' => null,
            ];

            if (!$symbol || !$tradeDate || !in_array($tradeAction, ['BUY CE', 'BUY PE'])) {
                $placeholder['error'] = 'WAIT'; $results[] = $placeholder; continue;
            }

            try {
                $optionType    = $tradeAction === 'BUY CE' ? 'CE' : 'PE';
                $nextDate      = $this->getNextTradingDate($tradeDate);
                $currentExpiry = $this->resolveActiveExpiry($symbol, $tradeDate);

                $atmQuery = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('strike_position', 'ATM')
                    ->where('is_missing', 0)
                    ->whereNotNull('expiry_date')
                    ->whereRaw("TIME(interval_time) = '14:45:00'");

                if ($currentExpiry) $atmQuery->whereDate('expiry_date', $currentExpiry);
                $atmRow = $atmQuery->orderBy('expiry_date')->first();

                if (!$atmRow) {
                    $atmFallback = OptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) = '14:45:00'")
                        ->whereNotNull('strike')->whereNotNull('expiry_date');
                    if ($currentExpiry) $atmFallback->whereDate('expiry_date', $currentExpiry);
                    $atmRow = $atmFallback->orderByRaw('ABS(strike - ?)', [$spotPrice])->orderBy('expiry_date')->first();
                }

                if (!$atmRow) { $placeholder['error'] = 'NO_ATM_ROW'; $results[] = $placeholder; continue; }

                $strike     = $atmRow->strike;
                $expiryDate = substr((string) $atmRow->expiry_date, 0, 10);
                $buyPrice   = (float) ($atmRow->close ?? 0);
                if ($buyPrice <= 0) $buyPrice = (float) ($atmRow->open ?? 0);

                if ($buyPrice <= 0) {
                    $placeholder['error'] = 'NO_BUY_PRICE';
                    $placeholder['option_symbol'] = $atmRow->trading_symbol ?? null;
                    $placeholder['strike'] = $strike; $placeholder['option_type'] = $optionType;
                    $results[] = $placeholder; continue;
                }

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

                $windowCandles = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->where('is_missing', 0)
                    ->where(function ($q) use ($tradeDate, $nextDate) {
                        $q->where(function ($q2) use ($tradeDate) {
                            $q2->whereDate('trade_date', $tradeDate)->whereRaw("TIME(interval_time) >= '15:15:00'");
                        })->orWhere(function ($q2) use ($nextDate) {
                            $q2->whereDate('trade_date', $nextDate)->whereRaw("TIME(interval_time) <= '09:30:00'");
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
                    $lowPrice  = $exitRow ? (float) ($exitRow->low ?? $buyPrice)  : $buyPrice;
                    $lowTime   = null;
                }

                $lotSize    = $this->getLotSize($symbol);
                $investment = round($buyPrice * $lotSize, 2);
                $exitPL     = $exitPrice > 0 ? round(($exitPrice - $buyPrice) * $lotSize, 2) : 0;
                $exitRoi    = ($investment > 0 && $exitPrice > 0) ? round(($exitPL / $investment) * 100, 2) : 0;
                $highPL     = round(($highPrice - $buyPrice) * $lotSize, 2);
                $highRoi    = $investment > 0 ? round(($highPL / $investment) * 100, 2) : 0;
                $lowPL      = round(($lowPrice - $buyPrice) * $lotSize, 2);
                $lowRoi     = $investment > 0 ? round(($lowPL / $investment) * 100, 2) : 0;

                $results[] = [
                    'index'         => $idx,
                    'option_symbol' => $atmRow->trading_symbol ?? "{$symbol}{$optionType}{$strike}",
                    'strike'        => $strike, 'option_type' => $optionType,
                    'lot_size'      => $lotSize, 'investment'  => $investment,
                    'buy_price'     => round($buyPrice, 2),
                    'exit_price'    => round($exitPrice, 2),
                    'exit_pl'       => $exitPL, 'exit_roi' => $exitRoi,
                    'high_price'    => round($highPrice, 2), 'high_time' => $highTime,
                    'high_pl'       => $highPL, 'high_roi' => $highRoi,
                    'low_price'     => round($lowPrice, 2),  'low_time'  => $lowTime,
                    'low_pl'        => $lowPL,  'low_roi'  => $lowRoi,
                    'profit_loss'   => $exitPL, 'roi_percent' => $exitRoi, 'error' => null,
                ];

            } catch (\Exception $e) {
                Log::error("NearStrike profit row error (idx={$idx}): " . $e->getMessage());
                $placeholder['error'] = 'EXCEPTION: ' . $e->getMessage();
                $results[] = $placeholder;
            }
        }

        return response()->json(['success' => true, 'data' => $results, 'message' => count($results) . ' records calculated']);
    }

    // =========================================================
    //  LOT SIZE  (identical to OIIVAutoController)
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $lots = ['NIFTY' => 25, 'BANKNIFTY' => 15, 'FINNIFTY' => 25, 'MIDCPNIFTY' => 50, 'SENSEX' => 10, 'BANKEX' => 15];
        $instrument = DB::table('zerodha_instruments')->where('name', $symbol)->where('exchange', 'NFO')->whereIn('instrument_type', ['CE', 'PE'])->value('lot_size');
        if ($instrument) return (int) $instrument;
        return $lots[$symbol] ?? 1;
    }

    // =========================================================
    //  DATE HELPERS  (identical to OIIVAutoController)
    // =========================================================

    private function dateStr($value): string
    {
        if ($value instanceof Carbon) return $value->toDateString();
        if (is_string($value)) return substr($value, 0, 10);
        return Carbon::parse($value)->toDateString();
    }

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay(); $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) return $prev->format('Y-m-d');
            $prev->subDay(); $attempts++;
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function getNextTradingDate(string $date): string
    {
        $next = Carbon::parse($date)->addDay(); $attempts = 0;
        while ($attempts < 10) {
            if (!$next->isWeekend() && !$this->isHoliday($next->format('Y-m-d'))) return $next->format('Y-m-d');
            $next->addDay(); $attempts++;
        }
        return Carbon::parse($date)->addDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }

    public function exportFilteredData(Request $request)
    {
        $fromDate = $request->get('from_date', '2026-03-23');
        $symbol   = $request->get('symbol', 'AUROPHARMA');

        $filename = "option_data_{$symbol}_" . now()->format('Ymd_His') . ".csv";

        return response()->streamDownload(function () use ($fromDate, $symbol) {

            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'id','base_symbol','trade_date','interval_time','instrument_type',
                'strike','expiry_date','open','high','low','close','oi'
            ]);

            DB::table('option_ohlc_data')
                ->whereDate('trade_date', '>=', $fromDate)
                ->where('base_symbol', $symbol)
                ->orderBy('trade_date')
                ->chunk(1000, function ($rows) use ($handle) {
                    foreach ($rows as $row) {
                        fputcsv($handle, (array) $row);
                    }
                });

            fclose($handle);

        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
    
}