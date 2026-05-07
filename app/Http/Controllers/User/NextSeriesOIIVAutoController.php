<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NextSeriesOptionOhlcData;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * NextSeriesOIIVAutoController
 *
 * 100% identical logic to OIIVAutoController EXCEPT:
 *   - All option OI queries use NextSeriesOptionOhlcData (next_series_option_ohlc_data)
 *   - FUT price queries still use OptionOhlcData (option_ohlc_data) — FUT is same table
 *   - Page titles updated to reflect "Next Series"
 *   - No config/order/auto-trading — analysis only
 */
class NextSeriesOIIVAutoController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function peCeAnalysis()
    {
        $pageTitle = 'Next Series EOD PE/CE Analysis (3 PM)';
        return view($this->activeTemplate . 'user.next-series-oiiv.pece-analysis', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS
    // =========================================================

    public function getSymbols()
    {
        // Next series symbols — from next_series_option_ohlc_data FUT rows
        $symbols = NextSeriesOptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  EXPIRY HELPERS — data-driven from next series table
    // =========================================================

    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        $expiry = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return NextSeriesOptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)
            ->where('is_missing', 0)
            ->exists();

        if ($exists) return $currentExpiry;

        return NextSeriesOptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')
            ->where('is_missing', 0)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  PE/CE ANALYSIS
    // =========================================================

    public function analyzePECESignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            // Trade dates from next series FUT data
            $tradeDates = NextSeriesOptionOhlcData::where('instrument_type', 'FUT')
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
                $rows     = $this->buildSignalRowsForDate($date, $prevDate, $selectedSymbols, $filterAction);
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
            Log::error('Next Series EOD PE/CE Analysis Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    private function buildSignalRowsForDate(string $date, string $prevDate, array $symbolFilter, ?string $actionFilter): array
    {
        // FUT candles @ 14:45 — from next series table
        $futQuery = NextSeriesOptionOhlcData::whereDate('trade_date', $date)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');

        if ($futCandles->isEmpty()) return [];

        $rows = [];

        foreach ($futCandles->keys() as $symbol) {
            $futCandle    = $futCandles[$symbol];
            $currentClose = (float) $futCandle->close;
            if ($currentClose <= 0) continue;

            // Expiries from next series table
            $currentExpiry = $this->getNearestExpiryForDate($symbol, $date);
            $prevExpiry    = $currentExpiry
                ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                : null;

            // Today's CE/PE @ 14:45 — next series table
            $todayCeQuery = NextSeriesOptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '14:45:00'");
            if ($currentExpiry) $todayCeQuery->whereDate('expiry_date', $currentExpiry);
            $todayCeCandles = $todayCeQuery->get();

            $todayPeQuery = NextSeriesOptionOhlcData::whereDate('trade_date', $date)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '14:45:00'");
            if ($currentExpiry) $todayPeQuery->whereDate('expiry_date', $currentExpiry);
            $todayPeCandles = $todayPeQuery->get();

            // Prev day CE/PE @ 15:00 — next series table
            $prevCeQuery = NextSeriesOptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '15:00:00'");
            if ($prevExpiry) $prevCeQuery->whereDate('expiry_date', $prevExpiry);
            $prevCeCandles = $prevCeQuery->get();

            $prevPeQuery = NextSeriesOptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '15:00:00'");
            if ($prevExpiry) $prevPeQuery->whereDate('expiry_date', $prevExpiry);
            $prevPeCandles = $prevPeQuery->get();

            $ceCurOI  = (int) $todayCeCandles->sum('oi');
            $peCurOI  = (int) $todayPeCandles->sum('oi');
            $ceOpenOI = (int) $prevCeCandles->sum('oi');
            $peOpenOI = (int) $prevPeCandles->sum('oi');

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

            // FUT price comparison — uses current series OptionOhlcData (same FUT)
            $futPrices = $this->getFutPricesFromOhlc($symbol, $date, $prevDate);

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

            // FUT OI — from next series table
            $prevFutCandle = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->first();

            $futOI     = (int) ($futCandle->oi ?? 0);
            $futPrevOI = $prevFutCandle ? (int) ($prevFutCandle->oi ?? 0) : 0;
            $futOiPct  = $futPrevOI > 0 ? round((($futOI - $futPrevOI) / $futPrevOI) * 100, 2) : 0;

            $fut50Ma = $this->getFut50MaSignal($symbol, $date);
            $mmTrap  = $this->getMmTrapForSymbolDate($symbol, $date, $prevDate, $currentExpiry, $prevExpiry);

            $rows[] = [
                'date'       => $date,
                'symbol'     => $symbol,
                'fut_symbol' => $futCandle->trading_symbol ?? $symbol,

                'ce_oi'            => $ceCurOI,
                'ce_oi_prev'       => $ceOpenOI,
                'ce_oi_change_pct' => $ceOiPct,

                'pe_oi'            => $peCurOI,
                'pe_oi_prev'       => $peOpenOI,
                'pe_oi_change_pct' => $peOiPct,

                'fut_oi'            => $futOI,
                'fut_oi_prev'       => $futPrevOI,
                'fut_oi_change_pct' => $futOiPct,

                'ce_oi_change_pct_fut' => round($ceOiPct, 2),
                'pe_oi_change_pct_fut' => round($peOiPct, 2),

                'strength_rank' => $strengthRank,
                'strength_diff' => round($diff, 2),
                'stronger_side' => $strongerSide,

                'pe_ce_ratio'       => $peCeRatio,
                'oi_interpretation' => $this->getOiInterpretation($peCeRatio),
                'oi_condition'      => $oiSignal['condition'],

                'options_sentiment' => $oiSignal['signal'],
                'futures_oi_view'   => 'N/A',
                'final_sentiment'   => $oiSignal['signal'],
                'trade_action'      => $tradeAction,

                'fut_price_prev'       => $futPrices['fut_price_prev'],
                'fut_price_today'      => $futPrices['fut_price_today'],
                'fut_price_change'     => $futPrices['fut_price_change'],
                'fut_price_change_pct' => $futPrices['fut_price_change_pct'],
                'fut_price_signal'     => $futPrices['fut_price_signal'],

                'spot_price'      => round($currentClose, 2),
                'fut_50ma_signal' => $fut50Ma,
                'mm_trap'         => $mmTrap,

                'current_expiry'  => $currentExpiry,
                'prev_expiry'     => $prevExpiry,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  50 MA — uses current series FUT data (same table)
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

        // 50MA uses current series FUT data — same as OIIVAutoController
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
    //  HELPER
    // =========================================================

    private function dateStr($value): string
    {
        if ($value instanceof Carbon) return $value->toDateString();
        if (is_string($value))        return substr($value, 0, 10);
        return Carbon::parse($value)->toDateString();
    }

    // =========================================================
    //  OI HELPERS
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

    private function getOiInterpretation(float $peCeRatio): string
    {
        if ($peCeRatio > 1.2) return 'Put Writing';
        if ($peCeRatio < 0.8) return 'Call Writing';
        return 'Balanced';
    }

    // =========================================================
    //  FUT PRICE — from current series (OptionOhlcData)
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
            Log::error("NextSeries getFutPricesFromOhlc ({$baseSymbol}): " . $e->getMessage());
            return ['fut_price_today' => 0, 'fut_price_prev' => 0, 'fut_price_change' => 0, 'fut_price_change_pct' => 0, 'fut_price_signal' => 'N/A'];
        }
    }

    // =========================================================
    //  MM TRAP — uses next series table for option OI walls
    // =========================================================

    private function getMmTrapForSymbolDate(
        string $symbol, string $date, string $prevDate,
        ?string $currentExpiry = null, ?string $prevExpiry = null
    ): array {
        $noTrap = ['call_trap' => false, 'put_trap' => false, 'type' => null,
                   'detail' => null, 'call_wall' => null, 'put_wall' => null, 'fut_price' => null];

        // FUT price from current series
        $futRow  = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first();

        $currFut = $futRow ? (float) $futRow->close : null;
        $noTrap['fut_price'] = $currFut;

        // Option rows — from next series table
        $allOptionQuery = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0);
        if ($currentExpiry) $allOptionQuery->whereDate('expiry_date', $currentExpiry);
        $allOptionRows = $allOptionQuery->get(['instrument_type', 'strike', 'close', 'oi', 'strike_position']);

        if ($allOptionRows->isEmpty()) return $noTrap;

        $ceOiByStrike = [];
        $peOiByStrike = [];
        foreach ($allOptionRows as $r) {
            $strike = (float) $r->strike;
            if ($strike <= 0) continue;
            if ($r->instrument_type === 'CE') {
                $ceOiByStrike[$strike] = ($ceOiByStrike[$strike] ?? 0) + (int) $r->oi;
            } else {
                $peOiByStrike[$strike] = ($peOiByStrike[$strike] ?? 0) + (int) $r->oi;
            }
        }

        $callWall = $callWallOi = $putWall = $putWallOi = null;
        foreach ($ceOiByStrike as $s => $o) {
            if ($callWallOi === null || $o > $callWallOi) { $callWall = $s; $callWallOi = $o; }
        }
        foreach ($peOiByStrike as $s => $o) {
            if ($putWallOi === null || $o > $putWallOi) { $putWall = $s; $putWallOi = $o; }
        }

        $noTrap['call_wall'] = $callWall;
        $noTrap['put_wall']  = $putWall;

        if (!$currFut) return $noTrap;

        $currAtmCe = $allOptionRows->where('instrument_type', 'CE')->where('strike_position', 'ATM')->first();
        $currAtmPe = $allOptionRows->where('instrument_type', 'PE')->where('strike_position', 'ATM')->first();

        // Prev day ATM — from next series table
        $prevOptionQuery = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $prevDate)
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->where('is_missing', 0);
        if ($prevExpiry) $prevOptionQuery->whereDate('expiry_date', $prevExpiry);
        $prevOptionRows = $prevOptionQuery->get(['instrument_type', 'strike', 'close', 'strike_position']);

        $prevAtmCe = $prevOptionRows->where('instrument_type', 'CE')->where('strike_position', 'ATM')->first();
        $prevAtmPe = $prevOptionRows->where('instrument_type', 'PE')->where('strike_position', 'ATM')->first();

        $trap = $noTrap;

        if ($callWall && $currFut > $callWall) {
            $premRising = $currAtmCe && $prevAtmCe
                && (float) $prevAtmCe->close > 0
                && (((float) $currAtmCe->close - (float) $prevAtmCe->close) / (float) $prevAtmCe->close) > 0.10;

            if ($premRising) {
                $trap['call_trap'] = true;
                $trap['type']      = 'CALL TRAP';
                $trap['detail']    = 'FUT ₹' . number_format($currFut, 0) . ' > Call Wall ₹' . number_format($callWall, 0);
            }
        }

        if ($putWall && $currFut < $putWall) {
            $premRising = $currAtmPe && $prevAtmPe
                && (float) $prevAtmPe->close > 0
                && (((float) $currAtmPe->close - (float) $prevAtmPe->close) / (float) $prevAtmPe->close) > 0.10;

            if ($premRising) {
                $trap['put_trap'] = true;
                if (!$trap['type']) {
                    $trap['type']   = 'PUT TRAP';
                    $trap['detail'] = 'FUT ₹' . number_format($currFut, 0) . ' < Put Wall ₹' . number_format($putWall, 0);
                }
            }
        }

        return $trap;
    }

    // =========================================================
    //  CALCULATE PROFIT — uses OptionOhlcData for ATM option lookup
    //  (profit is based on current series option prices for exit)
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
                'index' => $idx, 'option_symbol' => null, 'strike' => null, 'option_type' => null,
                'buy_price' => 0, 'lot_size' => 0, 'investment' => 0,
                'exit_price' => 0, 'exit_pl' => 0, 'exit_roi' => 0,
                'high_price' => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
                'low_price'  => 0, 'low_time'  => null, 'low_pl'  => 0, 'low_roi'  => 0,
                'profit_loss' => 0, 'roi_percent' => 0, 'error' => null,
            ];

            if (!$symbol || !$tradeDate || !in_array($tradeAction, ['BUY CE', 'BUY PE'])) {
                $placeholder['error'] = 'WAIT';
                $results[] = $placeholder;
                continue;
            }

            try {
                $optionType    = $tradeAction === 'BUY CE' ? 'CE' : 'PE';
                $nextDate      = $this->getNextTradingDate($tradeDate);

                // Resolve next series expiry from next_series_option_ohlc_data
                $currentExpiry = $this->getNearestExpiryForDate($symbol, $tradeDate);

                // ATM option from next series table
                $atmQuery = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('strike_position', 'ATM')
                    ->where('is_missing', 0)
                    ->whereNotNull('expiry_date')
                    ->whereRaw("TIME(interval_time) = '14:45:00'");
                if ($currentExpiry) $atmQuery->whereDate('expiry_date', $currentExpiry);
                $atmRow = $atmQuery->orderBy('expiry_date')->first();

                if (!$atmRow) {
                    $atmFallback = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) = '14:45:00'")
                        ->whereNotNull('strike')->whereNotNull('expiry_date');
                    if ($currentExpiry) $atmFallback->whereDate('expiry_date', $currentExpiry);
                    $atmRow = $atmFallback->orderByRaw('ABS(strike - ?)', [$spotPrice])
                        ->orderBy('expiry_date')->first();
                }

                if (!$atmRow) {
                    $placeholder['error'] = 'NO_ATM_ROW'; $results[] = $placeholder; continue;
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
                    $results[] = $placeholder; continue;
                }

                // Exit price from next series table
                $exitRow = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
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

                // Window candles from next series table
                $windowCandles = NextSeriesOptionOhlcData::where('base_symbol', $symbol)
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
                $exitPL     = $exitPrice > 0 ? round(($exitPrice - $buyPrice) * $lotSize, 2) : 0;
                $exitRoi    = ($investment > 0 && $exitPrice > 0) ? round(($exitPL / $investment) * 100, 2) : 0;
                $highPL     = round(($highPrice - $buyPrice) * $lotSize, 2);
                $highRoi    = $investment > 0 ? round(($highPL / $investment) * 100, 2) : 0;
                $lowPL      = round(($lowPrice - $buyPrice) * $lotSize, 2);
                $lowRoi     = $investment > 0 ? round(($lowPL / $investment) * 100, 2) : 0;

                $results[] = [
                    'index'         => $idx,
                    'option_symbol' => $atmRow->trading_symbol ?? "{$symbol}{$optionType}{$strike}",
                    'strike'        => $strike,
                    'option_type'   => $optionType,
                    'lot_size'      => $lotSize,
                    'investment'    => $investment,
                    'buy_price'     => round($buyPrice, 2),
                    'exit_price'    => round($exitPrice, 2),
                    'exit_pl'       => $exitPL,   'exit_roi'  => $exitRoi,
                    'high_price'    => round($highPrice, 2),
                    'high_time'     => $highTime, 'high_pl'   => $highPL,  'high_roi'  => $highRoi,
                    'low_price'     => round($lowPrice, 2),
                    'low_time'      => $lowTime,  'low_pl'    => $lowPL,   'low_roi'   => $lowRoi,
                    'profit_loss'   => $exitPL,   'roi_percent' => $exitRoi,
                    'error'         => null,
                ];

            } catch (\Exception $e) {
                Log::error("NextSeries Profit row error (idx={$idx}): " . $e->getMessage());
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
    //  LOT SIZE
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $lots = [
            'NIFTY' => 25, 'BANKNIFTY' => 15, 'FINNIFTY' => 25,
            'MIDCPNIFTY' => 50, 'SENSEX' => 10, 'BANKEX' => 15,
        ];

        $instrument = DB::table('zerodha_instruments')
            ->where('name', $symbol)->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])->value('lot_size');

        if ($instrument) return (int) $instrument;
        return $lots[$symbol] ?? 1;
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d')))
                return $prev->format('Y-m-d');
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function getNextTradingDate(string $date): string
    {
        $next = Carbon::parse($date)->addDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$next->isWeekend() && !$this->isHoliday($next->format('Y-m-d')))
                return $next->format('Y-m-d');
            $next->addDay();
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