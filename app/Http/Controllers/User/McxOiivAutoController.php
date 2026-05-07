<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\McxOiivAutoConfig;
use App\Models\McxOiivAutoOrder;
use App\Models\McxOhlcData;
use App\Models\McxSymbol;
use App\Models\BrokerApi;
use Illuminate\Support\Facades\DB;
use Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * McxOiivAutoController — MCX EOD OI+IV Analysis & Auto-Trading
 *
 * Mirrors OIIVAutoController exactly but uses:
 *   mcx_ohlc_data      instead of option_ohlc_data
 *   mcx_oiiv_auto_configs  instead of oiiv_auto_configs
 *   mcx_oiiv_auto_orders   instead of oiiv_auto_orders
 *   mcx_symbols            for lot_size
 *
 * MCX candle times differ from NSE:
 *   Market open : 09:00 (ATM freeze)
 *   EOD signal  : 23:00 close (last completed candle before 23:30 close)
 *   Prev day    : 23:00 or 23:15 candle of previous trading day
 */
class McxOiivAutoController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'MCX OI + IV Signal Analysis';
        return view($this->activeTemplate . 'user.mcx-oiiv.index', compact('pageTitle'));
    }

    public function peCeAnalysis()
    {
        $pageTitle = 'MCX EOD PE/CE Analysis';
        return view($this->activeTemplate . 'user.mcx-oiiv.pece-analysis', compact('pageTitle'));
    }

    public function config()
    {
        $pageTitle = 'MCX OI + IV Auto Trading Config';

        $brokers = BrokerApi::select('client_name', 'id')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();

        $configs = McxOiivAutoConfig::where('user_id', Auth::id())
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.mcx-oiiv.config', [
            'pageTitle' => $pageTitle,
            'brokers'   => $brokers,
            'configs'   => $configs,
        ]);
    }

    public function viewOrders($configId)
    {
        $pageTitle = 'MCX OI+IV Auto Trading Orders';

        $config = McxOiivAutoConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->firstOrFail();

        $orders = McxOiivAutoOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with(['broker:id,client_name'])
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.mcx-oiiv.orders', [
            'pageTitle' => $pageTitle,
            'config'    => $config,
            'orders'    => $orders,
        ]);
    }

    // =========================================================
    //  SYMBOLS
    // =========================================================

    public function getSymbols()
    {
        $symbols = McxOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  PE/CE ANALYSIS — main endpoint
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

            // Get all trade dates in range from mcx_ohlc_data
            $tradeDates = McxOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            $results = [];
            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousMcxTradingDate($date);
                $rows     = $this->buildSignalRowsForDate($date, $prevDate, $selectedSymbols, $filterAction);
                foreach ($rows as $row) $results[] = $row;
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
            Log::error('MCX EOD PE/CE Analysis Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    private function buildSignalRowsForDate(string $date, string $prevDate, array $symbolFilter, ?string $actionFilter): array
    {
        // MCX EOD signal uses the last completed candle, typically 23:00 or 23:15
        // We use TIME >= '23:00:00' and take the latest one
        $todayQuery = McxOhlcData::whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '23:00:00'");

        if (!empty($symbolFilter)) $todayQuery->whereIn('base_symbol', $symbolFilter);
        $todayCandles = $todayQuery->get();

        if ($todayCandles->isEmpty()) {
            // Fallback: try 22:45 or the latest available
            $todayCandles = McxOhlcData::whereDate('trade_date', $date)
                ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
                ->whereRaw("TIME(interval_time) >= '22:30:00'")
                ->where('is_missing', 0);
            if (!empty($symbolFilter)) $todayCandles->whereIn('base_symbol', $symbolFilter);
            $todayCandles = $todayCandles->get();
        }

        if ($todayCandles->isEmpty()) return [];

        $prevSymbols = $todayCandles->pluck('base_symbol')->unique()->values()->toArray();

        // Previous day's last candle (23:00 or 23:15)
        $prevCandles = McxOhlcData::whereDate('trade_date', $prevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) >= '22:45:00'")
            ->where('is_missing', 0)
            ->whereIn('base_symbol', $prevSymbols)
            ->get();

        $todayGrouped = [];
        foreach ($todayCandles as $c) {
            $todayGrouped[$c->base_symbol][$c->instrument_type][] = $c;
        }

        $prevByStrike = [];
        foreach ($prevCandles as $c) {
            $prevByStrike[$c->base_symbol][$c->instrument_type][(string) $c->strike] = $c;
        }

        $rows = [];

        foreach ($todayGrouped as $symbol => $typeMap) {
            $futCandles = $typeMap['FUT'] ?? [];

            // Take the latest FUT candle
            $futCandle = collect($futCandles)->sortBy('interval_time')->last();
            if (!$futCandle || (float) $futCandle->close <= 0) continue;

            $currentClose = (float) $futCandle->close;
            $unit         = $this->getSymbolUnit($symbol);

            [$ceOpenOI, $ceCurOI] = $this->sumOIVsPrev($typeMap['CE'] ?? [], $prevByStrike[$symbol]['CE'] ?? []);
            [$peOpenOI, $peCurOI] = $this->sumOIVsPrev($typeMap['PE'] ?? [], $prevByStrike[$symbol]['PE'] ?? []);

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

            // Strength rank
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

            // FUT OI change
            $prevFutCandle = McxOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) >= '22:45:00'")
                ->where('is_missing', 0)
                ->orderByDesc('interval_time')
                ->first();

            $futOI     = (int) ($futCandle->oi ?? 0);
            $futPrevOI = $prevFutCandle ? (int) ($prevFutCandle->oi ?? 0) : 0;
            $futOiPct  = $futPrevOI > 0 ? round((($futOI - $futPrevOI) / $futPrevOI) * 100, 2) : 0;

            $fut50Ma  = $this->getFut50MaSignal($symbol, $date);
            $futPrices = $this->getMcxFutPrices($symbol, $date, $prevDate);

            $rows[] = [
                'date'       => $date,
                'symbol'     => $symbol,
                'unit'       => $unit,
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

                'strength_rank' => $strengthRank,
                'strength_diff' => round($diff, 2),
                'stronger_side' => $strongerSide,

                'pe_ce_ratio'       => $peCeRatio,
                'oi_interpretation' => $this->getOiInterpretation($peCeRatio),
                'oi_condition'      => $oiSignal['condition'],

                'options_sentiment' => $oiSignal['signal'],
                'final_sentiment'   => $oiSignal['signal'],
                'trade_action'      => $tradeAction,

                'fut_price_prev'       => $futPrices['prev'],
                'fut_price_today'      => $futPrices['today'],
                'fut_price_change'     => $futPrices['change'],
                'fut_price_change_pct' => $futPrices['change_pct'],
                'fut_price_signal'     => $futPrices['signal'],

                'spot_price'      => round($currentClose, 2),
                'fut_50ma_signal' => $fut50Ma,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  50 MA (same rolling logic as NFO, uses mcx_ohlc_data)
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

        $allCandles = McxOhlcData::where('base_symbol', $baseSymbol)
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
            // MCX EOD: look for 23:00 candle
            if ($candleDate === $tradeDate && $time >= '23:00' && $time <= '23:15') {
                $targetIdx = $idx;
                break;
            }
        }

        // Fallback: last candle on trade date
        if ($targetIdx === null) {
            foreach ($allCandles as $idx => $candle) {
                $d = is_string($candle->candle_date)
                    ? $candle->candle_date
                    : Carbon::parse($candle->candle_date)->toDateString();
                if ($d === $tradeDate) $targetIdx = $idx;
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
    //  CALCULATE PROFIT — BTST window
    //  Buy = signal day 23:00 close
    //  Exit = next MCX trading day 09:15 open
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
                'option_symbol' => null, 'strike' => null, 'option_type' => null,
                'buy_price'     => 0,    'lot_size' => 0,  'investment'  => 0,
                'exit_price'    => 0, 'exit_pl' => 0,  'exit_roi'  => 0,
                'high_price'    => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
                'low_price'     => 0, 'low_time'  => null, 'low_pl'  => 0, 'low_roi'  => 0,
                'profit_loss'   => 0, 'roi_percent' => 0,  'error' => null,
            ];

            if (!$symbol || !$tradeDate || !in_array($tradeAction, ['BUY CE', 'BUY PE'])) {
                $placeholder['error'] = 'WAIT';
                $results[] = $placeholder;
                continue;
            }

            try {
                $optionType = $tradeAction === 'BUY CE' ? 'CE' : 'PE';
                $nextDate   = $this->getNextMcxTradingDate($tradeDate);

                // Find ATM option at 23:00 signal candle
                $atmRow = McxOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('strike_position', 'ATM')
                    ->where('is_missing', 0)
                    ->whereNotNull('expiry_date')
                    ->whereRaw("TIME(interval_time) = '23:00:00'")
                    ->orderBy('expiry_date')
                    ->first();

                if (!$atmRow) {
                    $atmRow = McxOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) = '23:00:00'")
                        ->whereNotNull('strike')
                        ->whereNotNull('expiry_date')
                        ->orderByRaw('ABS(strike - ?)', [$spotPrice])
                        ->orderBy('expiry_date')
                        ->first();
                }

                if (!$atmRow) {
                    $placeholder['error'] = 'NO_ATM_ROW';
                    $results[] = $placeholder;
                    continue;
                }

                $strike     = $atmRow->strike;
                $expiryDate = substr($atmRow->expiry_date, 0, 10);
                $buyPrice   = (float) ($atmRow->close ?? $atmRow->open ?? 0);

                if ($buyPrice <= 0) {
                    $placeholder['error']         = 'NO_BUY_PRICE';
                    $placeholder['option_symbol'] = $atmRow->trading_symbol ?? null;
                    $placeholder['strike']        = $strike;
                    $placeholder['option_type']   = $optionType;
                    $results[] = $placeholder;
                    continue;
                }

                // Exit: next MCX trading day 09:15 open
                $exitRow = McxOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $nextDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) = '09:15:00'")
                    ->first();

                // Try 09:00 if 09:15 not available
                if (!$exitRow) {
                    $exitRow = McxOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->where('strike', $strike)
                        ->whereDate('expiry_date', $expiryDate)
                        ->whereDate('trade_date', $nextDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) = '09:00:00'")
                        ->first();
                }

                $exitPrice = 0;
                if ($exitRow) {
                    $exitPrice = (float) ($exitRow->open ?? 0);
                    if ($exitPrice <= 0) $exitPrice = (float) ($exitRow->close ?? 0);
                }

                // Window candles: signal day 23:15 onwards + next day up to 09:15
                $windowCandles = McxOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->where('is_missing', 0)
                    ->where(function ($q) use ($tradeDate, $nextDate) {
                        $q->where(function ($q2) use ($tradeDate) {
                            $q2->whereDate('trade_date', $tradeDate)
                               ->whereRaw("TIME(interval_time) >= '23:15:00'");
                        })->orWhere(function ($q2) use ($nextDate) {
                            $q2->whereDate('trade_date', $nextDate)
                               ->whereRaw("TIME(interval_time) <= '09:15:00'");
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

                $lotSize    = $this->getMcxLotSize($symbol);
                $investment = round($buyPrice * $lotSize, 2);

                $exitPL  = $exitPrice > 0 ? round(($exitPrice - $buyPrice) * $lotSize, 2) : 0;
                $exitRoi = ($investment > 0 && $exitPrice > 0)
                    ? round(($exitPL / $investment) * 100, 2) : 0;
                $highPL  = round(($highPrice - $buyPrice) * $lotSize, 2);
                $highRoi = $investment > 0 ? round(($highPL / $investment) * 100, 2) : 0;
                $lowPL   = round(($lowPrice  - $buyPrice) * $lotSize, 2);
                $lowRoi  = $investment > 0 ? round(($lowPL  / $investment) * 100, 2) : 0;

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
                Log::error("MCX profit row (idx={$idx}): " . $e->getMessage());
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
    //  HELPERS
    // =========================================================

    private function getMcxFutPrices(string $baseSymbol, string $date, string $prevDate): array
    {
        try {
            $today = McxOhlcData::where('base_symbol', $baseSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $date)
                ->whereRaw("TIME(interval_time) = '23:00:00'")
                ->first();

            $prev = McxOhlcData::where('base_symbol', $baseSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) >= '22:45:00'")
                ->where('is_missing', 0)
                ->orderByDesc('interval_time')
                ->first();

            $t = $today ? (float) $today->close : 0;
            $p = $prev  ? (float) $prev->close  : 0;
            $c = ($p > 0 && $t > 0) ? $t - $p : 0;
            $pct = ($p > 0 && $t > 0) ? round(($c / $p) * 100, 2) : 0;
            $sig = ($p > 0 && $t > 0) ? ($t > $p ? 'BULLISH' : ($t < $p ? 'BEARISH' : 'NEUTRAL')) : 'N/A';

            return ['today' => round($t, 2), 'prev' => round($p, 2), 'change' => round($c, 2), 'change_pct' => $pct, 'signal' => $sig];
        } catch (\Exception $e) {
            return ['today' => 0, 'prev' => 0, 'change' => 0, 'change_pct' => 0, 'signal' => 'N/A'];
        }
    }

    private function sumOIVsPrev(array $todayCandles, array $prevByStrike): array
    {
        $prevOI = $todayOI = 0;
        foreach ($todayCandles as $tc) {
            $key     = (string) $tc->strike;
            $todayOI += (int) ($tc->oi ?? 0);
            if (isset($prevByStrike[$key])) {
                $prevOI += (int) ($prevByStrike[$key]->oi ?? 0);
            }
        }
        return [$prevOI, $todayOI];
    }

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup CE stronger", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup PE stronger", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding CE stronger", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding PE stronger", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    private function getOiInterpretation(float $peCeRatio): string
    {
        if ($peCeRatio > 1.2) return 'Put Writing';
        if ($peCeRatio < 0.8) return 'Call Writing';
        return 'Balanced';
    }

    private function getMcxLotSize(string $symbol): int
    {
        // Fetch from zerodha_instruments (MCX-FUT segment) — same source as CollectMcxOhlcData
        $inst = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->where('exchange', 'MCX')
            ->where('instrument_type', 'FUT')
            ->where('segment', 'MCX-FUT')
            ->value('lot_size');

        if ($inst) return (int) $inst;

        // Fallback: any MCX FUT entry without strict segment filter
        $instFallback = DB::table('zerodha_instruments')
            ->where('name', $symbol)
            ->where('exchange', 'MCX')
            ->where('instrument_type', 'FUT')
            ->value('lot_size');

        if ($instFallback) return (int) $instFallback;

        return 1; // MCX default
    }

    private function getSymbolUnit(string $symbol): string
    {
        $unit = DB::table('mcx_symbols')->where('symbol', $symbol)->value('unit');
        return $unit ?? '';
    }

    // ── Date helpers (MCX trades Mon-Sat, skip Sunday only) ──────────────────

    private function getPreviousMcxTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isSunday() && !$this->isMcxHoliday($prev->toDateString())) {
                return $prev->toDateString();
            }
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function getNextMcxTradingDate(string $date): string
    {
        $next = Carbon::parse($date)->addDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$next->isSunday() && !$this->isMcxHoliday($next->toDateString())) {
                return $next->toDateString();
            }
            $next->addDay();
        }
        return Carbon::parse($date)->addDay()->toDateString();
    }

    private function isMcxHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->whereIn('market_name', ['MCX', 'NSE'])
            ->where('holiday_date', $date)
            ->exists();
    }

    // =========================================================
    //  CONFIG CRUD (mirrors NFO OIIVAutoController exactly)
    // =========================================================

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'     => 'required|exists:broker_apis,id',
            'order_type'        => 'required|in:LIMIT,MARKET',
            'product'           => 'required|in:NRML,MIS',
            'disc_ltp'          => 'required|numeric|min:0|max:100',
            'ce_quantity'       => 'required|integer|min:0',
            'pe_quantity'       => 'required|integer|min:0',
            'signal_mode'       => 'required|in:align,opposite',
            'status'            => 'required|in:1,0',
            'option_series'     => 'required|in:current,next',
            'rank1_ce_quantity' => 'nullable|integer|min:0',
            'rank1_pe_quantity' => 'nullable|integer|min:0',
            'rank2_ce_quantity' => 'nullable|integer|min:0',
            'rank2_pe_quantity' => 'nullable|integer|min:0',
            'rank3_ce_quantity' => 'nullable|integer|min:0',
            'rank3_pe_quantity' => 'nullable|integer|min:0',
            'rank4_ce_quantity' => 'nullable|integer|min:0',
            'rank4_pe_quantity' => 'nullable|integer|min:0',
        ]);

        try {
            McxOiivAutoConfig::create([
                'user_id'           => Auth::id(),
                'broker_api_id'     => $request->broker_api_id,
                'order_type'        => $request->order_type,
                'product'           => $request->product,
                'disc_ltp'          => $request->disc_ltp,
                'ce_quantity'       => $request->ce_quantity,
                'pe_quantity'       => $request->pe_quantity,
                'signal_mode'       => $request->signal_mode,
                'status'            => $request->status,
                'option_series'     => $request->option_series,
                'rank1_ce_quantity' => $request->rank1_ce_quantity ?? 0,
                'rank1_pe_quantity' => $request->rank1_pe_quantity ?? 0,
                'rank2_ce_quantity' => $request->rank2_ce_quantity ?? 0,
                'rank2_pe_quantity' => $request->rank2_pe_quantity ?? 0,
                'rank3_ce_quantity' => $request->rank3_ce_quantity ?? 0,
                'rank3_pe_quantity' => $request->rank3_pe_quantity ?? 0,
                'rank4_ce_quantity' => $request->rank4_ce_quantity ?? 0,
                'rank4_pe_quantity' => $request->rank4_pe_quantity ?? 0,
            ]);
            $notify[] = ['success', 'MCX auto trading configuration created!'];
        } catch (\Exception $e) {
            Log::error('MCX Config Store: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration'];
        }
        return back()->withNotify($notify);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id'     => 'required|exists:broker_apis,id',
            'order_type'        => 'required|in:LIMIT,MARKET',
            'product'           => 'required|in:NRML,MIS',
            'disc_ltp'          => 'required|numeric|min:0|max:100',
            'ce_quantity'       => 'required|integer|min:0',
            'pe_quantity'       => 'required|integer|min:0',
            'signal_mode'       => 'required|in:align,opposite',
            'status'            => 'required|in:1,0',
            'option_series'     => 'required|in:current,next',
            'rank1_ce_quantity' => 'nullable|integer|min:0',
            'rank1_pe_quantity' => 'nullable|integer|min:0',
            'rank2_ce_quantity' => 'nullable|integer|min:0',
            'rank2_pe_quantity' => 'nullable|integer|min:0',
            'rank3_ce_quantity' => 'nullable|integer|min:0',
            'rank3_pe_quantity' => 'nullable|integer|min:0',
            'rank4_ce_quantity' => 'nullable|integer|min:0',
            'rank4_pe_quantity' => 'nullable|integer|min:0',
        ]);

        $config = McxOiivAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $config->update($request->only([
            'broker_api_id', 'order_type', 'product', 'disc_ltp',
            'ce_quantity', 'pe_quantity', 'signal_mode', 'status', 'option_series',
            'rank1_ce_quantity', 'rank1_pe_quantity',
            'rank2_ce_quantity', 'rank2_pe_quantity',
            'rank3_ce_quantity', 'rank3_pe_quantity',
            'rank4_ce_quantity', 'rank4_pe_quantity',
        ]));
        $notify[] = ['success', 'Configuration updated!'];
        return back()->withNotify($notify);
    }

    public function toggleStatus($id)
    {
        try {
            $config = McxOiivAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
            $config->status = !$config->status;
            $config->save();
            $notify[] = ['success', 'Configuration ' . ($config->status ? 'activated' : 'deactivated') . '!'];
        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating configuration.'];
        }
        return back()->withNotify($notify);
    }

    public function destroy($id)
    {
        try {
            $config = McxOiivAutoConfig::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
            $pending = $config->orders()->where('is_order_placed', false)->where('status', true)->count();
            if ($pending > 0) {
                $notify[] = ['error', "Cannot delete. {$pending} orders pending."];
                return back()->withNotify($notify);
            }
            $config->delete();
            $notify[] = ['success', 'Configuration deleted!'];
        } catch (\Exception $e) {
            $notify[] = ['error', 'Error deleting configuration.'];
        }
        return back()->withNotify($notify);
    }

    public function clearPriceCache(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'No cache — data sourced live.', 'affected' => 0]);
    }
}