<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\IndexOptionStrike;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use App\Models\OptionOhlcData;          // ← for 50 MA
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use KiteConnect\KiteConnect;

class IndexOIController extends Controller
{
    private $kite = null;

    // =========================================================================
    // PAGE
    // =========================================================================

    public function peCeAnalysis()
    {
        $pageTitle = 'Index PE/CE OI Analysis (BankNifty)';
        return view($this->activeTemplate . 'user.index-oi.pece-analysis', compact('pageTitle'));
    }

    // =========================================================================
    // API: SYMBOLS
    // =========================================================================

    public function getSymbols()
    {
        $symbols = IndexOptionStrike::where('strike_position', 'FUT')
            ->distinct()->pluck('underlying_symbol')->sort()->values();
        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================================
    // API: ANALYZE PE/CE SIGNALS
    // =========================================================================

    public function analyzePECESignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both From and To dates', 'data' => []]);
            }

            $query = IndexOptionStrike::where('strike_position', 'FUT')
                ->whereBetween('trading_date', [$fromDate, $toDate])
                ->whereNotNull('pe_ce_ratio');

            if (!empty($selectedSymbols)) $query->whereIn('underlying_symbol', $selectedSymbols);
            if (!empty($filterAction))    $query->where('trade_action', $filterAction);

            $futRecords = $query->orderBy('trading_date', 'desc')
                ->orderBy('underlying_symbol', 'asc')
                ->get();

            $results = [];
            foreach ($futRecords as $futRecord) {
                $results[] = $this->formatPECEAnalysisData($futRecord);
            }

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('[IndexOIController] analyzePECESignals: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================================
    // FORMAT ROW
    // =========================================================================

    private function formatPECEAnalysisData($futRecord)
    {
        $ceData = IndexOptionStrike::where('underlying_symbol', $futRecord->underlying_symbol)
            ->where('strike_position', 'CE_MERGED')
            ->where('trading_date', $futRecord->trading_date)
            ->first();

        $peData = IndexOptionStrike::where('underlying_symbol', $futRecord->underlying_symbol)
            ->where('strike_position', 'PE_MERGED')
            ->where('trading_date', $futRecord->trading_date)
            ->first();

        $signal = [
            'date'         => $futRecord->trading_date,
            'symbol'       => $futRecord->underlying_symbol,
            'trade_action' => $futRecord->trade_action,
            'spot_price'   => $futRecord->spot_price,
        ];
        $resolvedOptionSymbol = $this->getOptionSymbolFromSignal($signal) ?? 'N/A';

        $futPriceToday     = round($futRecord->daily_close          ?? 0, 2);
        $futPricePrev      = round($futRecord->daily_close_prev     ?? 0, 2);
        $futPriceChange    = round($futRecord->daily_close_change   ?? 0, 2);
        $futPriceChangePct = round($futRecord->daily_close_change_pct ?? 0, 2);

        $futPriceSignal = 'N/A';
        if ($futPricePrev > 0 && $futPriceToday > 0) {
            $futPriceSignal = $futPriceToday > $futPricePrev ? 'BULLISH'
                : ($futPriceToday < $futPricePrev ? 'BEARISH' : 'NEUTRAL');
        }

        // ── 50 MA signal from option_ohlc_data FUT candles ──────────────────
        $tradingDate = is_string($futRecord->trading_date)
            ? $futRecord->trading_date
            : Carbon::parse($futRecord->trading_date)->toDateString();

        $fut50Ma = $this->getFut50MaSignal($futRecord->underlying_symbol, $tradingDate);

        $profitData = $this->calculateRowProfit($signal);

        return [
            // Basic
            'date'       => Carbon::parse($futRecord->trading_date)->format('Y-m-d'),
            'symbol'     => $futRecord->underlying_symbol,
            'fut_symbol' => $futRecord->trading_symbol,

            // CE OI
            'ce_oi'            => $ceData ? $ceData->daily_oi            : 0,
            'ce_oi_prev'       => $ceData ? $ceData->daily_oi_prev       : 0,
            'ce_oi_change_pct' => $ceData ? round($ceData->daily_oi_change_pct ?? 0, 2) : 0,

            // PE OI
            'pe_oi'            => $peData ? $peData->daily_oi            : 0,
            'pe_oi_prev'       => $peData ? $peData->daily_oi_prev       : 0,
            'pe_oi_change_pct' => $peData ? round($peData->daily_oi_change_pct ?? 0, 2) : 0,

            // FUT OI
            'fut_oi'            => $futRecord->daily_oi,
            'fut_oi_prev'       => $futRecord->daily_oi_prev,
            'fut_oi_change_pct' => round($futRecord->daily_oi_change_pct ?? 0, 2),

            // CE/PE % stored on FUT row
            'ce_oi_change_pct_fut' => round($futRecord->ce_oi_change_pct ?? 0, 2),
            'pe_oi_change_pct_fut' => round($futRecord->pe_oi_change_pct ?? 0, 2),

            // Stronger side
            'stronger_side' => $this->determineStrongerSide($futRecord),

            // PE/CE Ratio & interpretation
            'pe_ce_ratio'       => round($futRecord->pe_ce_ratio    ?? 0, 2),
            'oi_interpretation' => $futRecord->oi_interpretation ?? 'N/A',
            'oi_condition'      => $futRecord->oi_condition      ?? 'N/A',

            // Sentiment & action
            'options_sentiment' => $futRecord->options_sentiment ?? 'N/A',
            'futures_oi_view'   => $futRecord->futures_oi_view   ?? 'N/A',
            'final_sentiment'   => $futRecord->final_sentiment   ?? 'N/A',
            'trade_action'      => $futRecord->trade_action      ?? 'N/A',
            'sentiment_color'   => $this->getSentimentColor($futRecord->final_sentiment),
            'action_color'      => $this->getActionColor($futRecord->trade_action),

            // BTST
            'btst_signal'     => $futRecord->btst_signal     ?? 'N/A',
            'btst_confidence' => $futRecord->btst_confidence ?? 0,
            'btst_reason'     => $futRecord->btst_reason     ?? 'N/A',

            // Spot
            'spot_price' => round($futRecord->spot_price ?? 0, 2),

            // FUT price columns
            'fut_price_today'      => $futPriceToday,
            'fut_price_prev'       => $futPricePrev,
            'fut_price_change'     => $futPriceChange,
            'fut_price_change_pct' => $futPriceChangePct,
            'fut_price_signal'     => $futPriceSignal,

            // ✅ 50 MA signal
            'fut_50ma_signal' => $fut50Ma,

            // Option symbol
            'option_symbol' => $resolvedOptionSymbol,

            // Profit
            'investment'             => $profitData['investment']             ?? 0,
            'entry_price'            => $profitData['entry_price']            ?? 0,
            'exit_price'             => $profitData['exit_price']             ?? 0,
            'highest_price'          => $profitData['highest_price']          ?? 0,
            'profit_loss'            => $profitData['profit_loss']            ?? 0,
            'highest_profit'         => $profitData['highest_profit']         ?? 0,
            'return_percent'         => $profitData['return_percent']         ?? 0,
            'highest_return_percent' => $profitData['highest_return_percent'] ?? 0,
            'has_profit_data'        => $profitData['has_data']               ?? false,
        ];
    }

    // =========================================================================
    // ✅  50 MA — reads option_ohlc_data FUT candles (same as 9to12 controller)
    //
    //  For EOD / daily analysis the "current" candle is the last candle of the
    //  trading day (15:15 or nearest available).  If that isn't present we fall
    //  back to the latest candle on that date.
    // =========================================================================

    /**
     * Work out how far back we need to go to collect maPeriod daily closing
     * candles.  Each trading day has ~25 fifteen-minute candles so we go back
     * ⌈period/25⌉ + a small buffer to handle holidays / weekends.
     */
    private function historyStartDate(string $tradeDate, int $maPeriod = 50): string
    {
        $daysBack = (int) ceil($maPeriod / 25) + 5;
        return Carbon::parse($tradeDate)->subDays($daysBack)->toDateString();
    }

    /**
     * Simple rolling SMA — returns an array parallel to $values where
     * element $i is the SMA of the previous $period elements (inclusive),
     * or null if there aren't enough data points yet.
     */
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

    /**
     * Returns 'BULLISH' | 'BEARISH' | 'NEUTRAL' | 'N/A'
     *
     * Strategy:
     *  1. Pull all FUT candles for $baseSymbol from historyStart → $tradeDate
     *     ordered chronologically.
     *  2. Compute rolling 50-period SMA across all candle closes.
     *  3. For $tradeDate find the EOD candle (15:15, or latest available).
     *  4. Compare that candle's close to its SMA value.
     */
    private function getFut50MaSignal(string $baseSymbol, string $tradeDate): string
    {
        $maPeriod     = 50;
        $historyStart = $this->historyStartDate($tradeDate, $maPeriod);

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->whereBetween('trade_date', [$historyStart, $tradeDate])
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

        // ── Find the EOD candle index for $tradeDate ────────────────────────
        // Preference: 15:15 candle.  Fallback: latest candle on the date.
        $targetIdx = null;

        // Pass 1 — exact 15:15 or within 15:15–15:29
        foreach ($allCandles as $idx => $candle) {
            $candleDate = is_string($candle->candle_date)
                ? $candle->candle_date
                : Carbon::parse($candle->candle_date)->toDateString();

            if ($candleDate !== $tradeDate) continue;

            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($time >= '15:15' && $time <= '15:29') {
                $targetIdx = $idx;
                break;
            }
        }

        // Pass 2 — any candle on $tradeDate (pick the latest)
        if ($targetIdx === null) {
            foreach ($allCandles as $idx => $candle) {
                $candleDate = is_string($candle->candle_date)
                    ? $candle->candle_date
                    : Carbon::parse($candle->candle_date)->toDateString();

                if ($candleDate === $tradeDate) {
                    $targetIdx = $idx; // keep overwriting → ends up as last one
                }
            }
        }

        if ($targetIdx === null || !isset($closeMa[$targetIdx])) return 'N/A';

        $close = $closeValues[$targetIdx];
        $ma    = $closeMa[$targetIdx];

        if ($ma === null) return 'N/A';

        if ($close > $ma)     return 'BULLISH';   // price above 50 MA → bullish
        elseif ($close < $ma) return 'BEARISH';   // price below 50 MA → bearish
        return 'NEUTRAL';
    }

    // =========================================================================
    // BULK PROFIT CALCULATION
    // =========================================================================

    public function calculateBulkProfit(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');

            $query = IndexOptionStrike::where('strike_position', 'FUT')
                ->whereBetween('trading_date', [$fromDate, $toDate])
                ->whereNotNull('pe_ce_ratio');

            if (!empty($selectedSymbols)) $query->whereIn('underlying_symbol', $selectedSymbols);
            if (!empty($filterAction))    $query->where('trade_action', $filterAction);

            $futRecords = $query->orderBy('trading_date', 'desc')
                ->orderBy('underlying_symbol', 'asc')
                ->get();

            $this->initializeKite();

            $resultsArr = [];
            $totalProfit = $totalHighestProfit = $totalInvestment = 0;
            $totalTrades = $winningTrades = $losingTrades = $highestWinningTrades = 0;

            foreach ($futRecords as $futRecord) {
                $tradeAction = $futRecord->trade_action ?? 'N/A';
                if (empty($tradeAction) || in_array($tradeAction, ['N/A', 'WAIT'])) continue;

                $signal = [
                    'date'         => $futRecord->trading_date,
                    'symbol'       => $futRecord->underlying_symbol,
                    'trade_action' => $tradeAction,
                    'spot_price'   => $futRecord->spot_price,
                ];

                $profitData = $this->calculateSignalProfit($signal);
                if ($profitData) {
                    $profitData['symbol'] = $futRecord->underlying_symbol;
                    $profitData['date']   = $futRecord->trading_date;
                    $resultsArr[]          = $profitData;
                    $totalProfit          += $profitData['profit_loss'];
                    $totalHighestProfit   += $profitData['highest_profit'];
                    $totalInvestment      += $profitData['investment'];
                    $totalTrades++;
                    if ($profitData['profit_loss']    > 0) $winningTrades++;
                    if ($profitData['profit_loss']    < 0) $losingTrades++;
                    if ($profitData['highest_profit'] > 0) $highestWinningTrades++;
                }
            }

            return response()->json([
                'success' => true,
                'data'    => $resultsArr,
                'summary' => [
                    'total_trades'         => $totalTrades,
                    'winning_trades'       => $winningTrades,
                    'losing_trades'        => $losingTrades,
                    'win_rate'             => $totalTrades > 0 ? round($winningTrades / $totalTrades * 100, 2) : 0,
                    'highest_win_rate'     => $totalTrades > 0 ? round($highestWinningTrades / $totalTrades * 100, 2) : 0,
                    'total_investment'     => round($totalInvestment, 2),
                    'total_profit_loss'    => round($totalProfit, 2),
                    'total_highest_profit' => round($totalHighestProfit, 2),
                    'avg_profit_loss'      => $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0,
                    'avg_highest_profit'   => $totalTrades > 0 ? round($totalHighestProfit / $totalTrades, 2) : 0,
                    'roi_percent'          => $totalInvestment > 0 ? round($totalProfit / $totalInvestment * 100, 2) : 0,
                    'highest_roi_percent'  => $totalInvestment > 0 ? round($totalHighestProfit / $totalInvestment * 100, 2) : 0,
                ],
                'message' => 'Profit calculation completed',
            ]);

        } catch (\Exception $e) {
            Log::error('[IndexOIController] calculateBulkProfit: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // ROW PROFIT
    // =========================================================================

    private function calculateRowProfit($signal)
    {
        try {
            if (!isset($signal['trade_action']) || in_array($signal['trade_action'], ['N/A', 'WAIT', null])) {
                return ['has_data' => false];
            }

            $optionSymbol = $this->getOptionSymbolFromSignal($signal);
            if (!$optionSymbol) return ['has_data' => false];

            $signalDate = Carbon::parse($signal['date']);
            $instrument = ZerodhaInstrument::where('trading_symbol', $optionSymbol)->where('exchange', 'NFO')->first();
            if (!$instrument) return ['has_data' => false];

            if (!$this->kite) $this->initializeKite();

            $entryDateTime = Carbon::parse($signalDate->format('Y-m-d') . ' 15:00:00', 'Asia/Kolkata');
            $exitDateTime  = $this->getNextTradingDay($signalDate)->setTime(15, 0, 0);

            $buyPrice  = $this->getOptionPriceAtTime($instrument, $entryDateTime);
            $sellPrice = $this->getOptionPriceAtTime($instrument, $exitDateTime);

            if (!$buyPrice || !$sellPrice) return ['has_data' => false];

            $highestPriceData = $this->getHighestPriceBetween($instrument, $entryDateTime, $exitDateTime);
            if ($highestPriceData['price'] <= 0) $highestPriceData['price'] = max($buyPrice, $sellPrice);

            $quantity      = $instrument->lot_size ?? 1;
            $profitLoss    = ($sellPrice - $buyPrice) * $quantity;
            $highestProfit = ($highestPriceData['price'] - $buyPrice) * $quantity;
            $investment    = $buyPrice * $quantity;

            return [
                'has_data'               => true,
                'option_symbol'          => $optionSymbol,
                'entry_price'            => round($buyPrice, 2),
                'exit_price'             => round($sellPrice, 2),
                'highest_price'          => round($highestPriceData['price'], 2),
                'investment'             => round($investment, 2),
                'profit_loss'            => round($profitLoss, 2),
                'highest_profit'         => round($highestProfit, 2),
                'return_percent'         => $buyPrice > 0 ? round(($sellPrice - $buyPrice) / $buyPrice * 100, 2) : 0,
                'highest_return_percent' => $buyPrice > 0 ? round(($highestPriceData['price'] - $buyPrice) / $buyPrice * 100, 2) : 0,
            ];

        } catch (\Exception $e) {
            Log::error('[IndexOIController] calculateRowProfit: ' . $e->getMessage());
            return ['has_data' => false];
        }
    }

    // =========================================================================
    // SIGNAL PROFIT
    // =========================================================================

    private function calculateSignalProfit($signal)
    {
        try {
            $optionSymbol = $this->getOptionSymbolFromSignal($signal);
            if (!$optionSymbol) return null;

            $signalDate = Carbon::parse($signal['date']);
            $instrument = ZerodhaInstrument::where('trading_symbol', $optionSymbol)->where('exchange', 'NFO')->first();
            if (!$instrument) return null;

            $entryDateTime = Carbon::parse($signalDate->format('Y-m-d') . ' 15:00:00', 'Asia/Kolkata');
            $exitDateTime  = $this->getNextTradingDay($signalDate)->setTime(15, 0, 0);

            $buyPrice  = $this->getOptionPriceAtTime($instrument, $entryDateTime);
            $sellPrice = $this->getOptionPriceAtTime($instrument, $exitDateTime);
            if (!$buyPrice || !$sellPrice) return null;

            $highestPriceData = $this->getHighestPriceBetween($instrument, $entryDateTime, $exitDateTime);
            if ($highestPriceData['price'] <= 0) $highestPriceData['price'] = max($buyPrice, $sellPrice);

            $quantity      = $instrument->lot_size ?? 1;
            $profitLoss    = ($sellPrice - $buyPrice) * $quantity;
            $highestProfit = ($highestPriceData['price'] - $buyPrice) * $quantity;
            $investment    = $buyPrice * $quantity;

            return [
                'option_symbol'          => $optionSymbol,
                'signal_date'            => $signalDate->format('Y-m-d'),
                'entry_time'             => $entryDateTime->format('Y-m-d H:i:s'),
                'exit_time'              => $exitDateTime->format('Y-m-d H:i:s'),
                'buy_price'              => round($buyPrice, 2),
                'sell_price'             => round($sellPrice, 2),
                'highest_price'          => round($highestPriceData['price'], 2),
                'highest_price_time'     => $highestPriceData['time'],
                'quantity'               => $quantity,
                'investment'             => round($investment, 2),
                'profit_loss'            => round($profitLoss, 2),
                'highest_profit'         => round($highestProfit, 2),
                'return_percent'         => $buyPrice > 0 ? round(($sellPrice - $buyPrice) / $buyPrice * 100, 2) : 0,
                'highest_return_percent' => $buyPrice > 0 ? round(($highestPriceData['price'] - $buyPrice) / $buyPrice * 100, 2) : 0,
            ];

        } catch (\Exception $e) {
            Log::error('[IndexOIController] calculateSignalProfit: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // OPTION SYMBOL RESOLVER
    // =========================================================================

    private function getOptionSymbolFromSignal($signal)
    {
        try {
            $optionType = match ($signal['trade_action']) {
                'BUY CE' => 'CE',
                'BUY PE' => 'PE',
                default  => null,
            };
            if (!$optionType) return null;

            $strikeIntervals = [
                'BANKNIFTY'  => 100,
                'NIFTY'      => 50,
                'FINNIFTY'   => 50,
                'MIDCPNIFTY' => 25,
            ];
            $strikeInterval = $strikeIntervals[$signal['symbol']] ?? 100;
            $atmStrike      = round($signal['spot_price'] / $strikeInterval) * $strikeInterval;

            $option = ZerodhaInstrument::where('name', $signal['symbol'])
                ->where('exchange', 'NFO')
                ->where('instrument_type', $optionType)
                ->where('strike', $atmStrike)
                ->whereDate('expiry', '>=', $signal['date'])
                ->orderBy('expiry', 'ASC')
                ->first();

            if (!$option) {
                $option = ZerodhaInstrument::where('name', $signal['symbol'])
                    ->where('exchange', 'NFO')
                    ->where('instrument_type', $optionType)
                    ->whereDate('expiry', '>=', $signal['date'])
                    ->selectRaw('*, ABS(strike - ?) as strike_diff', [$atmStrike])
                    ->orderBy('strike_diff', 'ASC')
                    ->orderBy('expiry', 'ASC')
                    ->first();
            }

            return $option ? $option->trading_symbol : null;

        } catch (\Exception $e) {
            Log::error('[IndexOIController] getOptionSymbolFromSignal: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // PRICE HELPERS
    // =========================================================================

    private function getHighestPriceBetween($instrument, $startDateTime, $endDateTime)
    {
        try {
            $highest = \App\Models\SymbolData::where('trading_symbol', $instrument->trading_symbol)
                ->where('interval', '5minute')
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('high', 'DESC')->first();

            if ($highest && $highest->high > 0) {
                return ['price' => $highest->high, 'time' => $highest->timestamp->format('Y-m-d H:i')];
            }
            return $this->getHighestPriceFromKite($instrument, $startDateTime, $endDateTime);

        } catch (\Exception $e) {
            return ['price' => 0, 'time' => null];
        }
    }

    private function getHighestPriceFromKite($instrument, $startDateTime, $endDateTime)
    {
        try {
            if (!$this->kite) return ['price' => 0, 'time' => null];
            usleep(350000);

            $response     = $this->kite->getHistoricalData($instrument->instrument_token, '5minute', $startDateTime->format('Y-m-d H:i:s'), $endDateTime->format('Y-m-d H:i:s'));
            $candles      = is_array($response) ? $response : (array) $response;
            $highestPrice = 0; $highestTime = null;

            foreach ($candles as $candle) {
                $candle = is_object($candle) ? (array) $candle : $candle;
                $high   = $candle['high'] ?? 0;
                if ($high > $highestPrice) {
                    $highestPrice = $high;
                    $highestTime  = isset($candle['date'])
                        ? (is_string($candle['date']) ? date('Y-m-d H:i', strtotime($candle['date'])) : $candle['date']->format('Y-m-d H:i'))
                        : null;
                }
            }
            return ['price' => $highestPrice, 'time' => $highestTime];

        } catch (\Exception $e) {
            return ['price' => 0, 'time' => null];
        }
    }

    private function getOptionPriceAtTime($instrument, $datetime)
    {
        try {
            $cached = \App\Models\OptionPriceCache::where('trading_symbol', $instrument->trading_symbol)
                ->where('price_datetime', $datetime)->first();
            if ($cached) return $cached->price;

            $price = $this->fetchPriceFromKite($instrument, $datetime);
            if ($price && $price > 0) {
                \App\Models\OptionPriceCache::updateOrCreate(
                    ['trading_symbol' => $instrument->trading_symbol, 'price_datetime' => $datetime],
                    ['instrument_token' => $instrument->instrument_token, 'price' => $price, 'cached_at' => now()]
                );
                return $price;
            }
            return null;

        } catch (\Exception $e) { return null; }
    }

    private function fetchPriceFromKite($instrument, $datetime)
    {
        try {
            if (!$this->kite) return null;
            usleep(350000);

            $response = $this->kite->getHistoricalData(
                $instrument->instrument_token, '5minute',
                $datetime->copy()->subMinutes(30)->format('Y-m-d H:i:s'),
                $datetime->copy()->addMinutes(30)->format('Y-m-d H:i:s')
            );

            $candles = is_array($response) ? $response : (array) $response;
            if (empty($candles)) return null;

            $target = $datetime->timestamp; $closest = null; $minDiff = PHP_INT_MAX;
            foreach ($candles as $candle) {
                $candle = is_object($candle) ? (array) $candle : $candle;
                $t = isset($candle['date']) ? (is_string($candle['date']) ? strtotime($candle['date']) : $candle['date']->getTimestamp()) : null;
                if ($t === null) continue;
                $diff = abs($t - $target);
                if ($diff < $minDiff) { $minDiff = $diff; $closest = $candle; }
            }
            return ($closest && ($closest['close'] ?? 0) > 0) ? $closest['close'] : null;

        } catch (\Exception $e) { return null; }
    }

    // =========================================================================
    // DATE HELPERS
    // =========================================================================

    private function getNextTradingDay($date)
    {
        $next = Carbon::parse($date)->addDay();
        for ($i = 0; $i < 10; $i++) {
            if ($next->isWeekend()) { $next->addDay(); continue; }
            if (DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $next->format('Y-m-d'))->exists()) { $next->addDay(); continue; }
            return $next;
        }
        return Carbon::parse($date)->addDay();
    }

    // =========================================================================
    // MISC HELPERS
    // =========================================================================

    private function determineStrongerSide($futRecord)
    {
        $ce = abs($futRecord->ce_oi_change_pct ?? 0);
        $pe = abs($futRecord->pe_oi_change_pct ?? 0);
        if ($ce > $pe) return 'CE';
        if ($pe > $ce) return 'PE';
        return 'EQUAL';
    }

    private function getSentimentColor($s) { return match ($s) { 'BULLISH' => 'success', 'BEARISH' => 'danger', default => 'secondary' }; }
    private function getActionColor($a)    { return match ($a) { 'BUY CE'  => 'success', 'BUY PE'  => 'danger',  default => 'warning'  }; }

    private function initializeKite()
    {
        if ($this->kite) return;
        try {
            $broker = BrokerApi::where('client_type', 'Zerodha')
                ->where('is_token_valid', true)
                ->where('token_expires_at', '>', now())
                ->first();
            if (!$broker) throw new \Exception('No valid Zerodha broker found');
            $this->kite = new KiteConnect($broker->api_key);
            $this->kite->setAccessToken($broker->access_token);
        } catch (\Exception $e) {
            Log::error('[IndexOIController] Kite init: ' . $e->getMessage());
            $this->kite = null;
        }
    }
}