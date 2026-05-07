<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OIIVAutoConfig;
use App\Models\OIIVAutoOrder;
use App\Models\BrokerApi;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Auth;

/**
 * 9:30 AM → 12:15 PM Intraday PE/CE Analysis + Config Management
 *
 * Reads ONLY from option_ohlc_data (15-min OHLC candles per strike).
 *
 * open candle    = 09:30 interval_time  ← industry standard: skip 09:15 noise
 * current candle = 12:15 interval_time
 *
 * CE/PE aggregation: sum OI across ATM-1 + ATM + ATM+1 strikes
 *                    average close price across those strikes
 *
 * Removed columns (no longer in option_ohlc_data):
 *   ✗ iv
 *   ✗ fair_price
 *   ✗ valuation
 *   ✗ recommendation
 *   ✗ days_to_expiry
 */
class OIIVAuto9to12Controller extends Controller
{
    // =========================================================
    //  ANALYSIS PAGE
    // =========================================================

    public function peCeAnalysis()
    {
        $pageTitle = '9:30 AM → 12:15 PM Intraday PE/CE Analysis';
        return view($this->activeTemplate . 'user.oiiv-auto.pece-analysis-9to12', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS  (from option_ohlc_data)
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::distinct()
            ->whereNotNull('base_symbol')
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  ANALYZE PE/CE SIGNALS (AJAX)
    //  Pure aggregation from option_ohlc_data
    //
    //  open candle    = 09:30  (more stable than 09:15 open volatility)
    //  current candle = 12:15
    // =========================================================

    public function analyzePECESignals(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterAction    = $request->get('filter_action');

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data'    => [],
                ]);
            }

            // ── Fetch all relevant candles in one query ─────────────────
            // open = 09:30 (skip 09:15 noise), current = 12:15
            $query = OptionOhlcData::whereBetween('trade_date', [$fromDate, $toDate])
                ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
                ->whereRaw("TIME(interval_time) IN ('09:30:00', '12:15:00')")
                ->where('is_missing', 0)   // exclude zero-filled gap rows
                ->select([
                    'base_symbol',
                    'trade_date',
                    'instrument_type',
                    'strike',
                    'strike_position',
                    'close',
                    'oi',
                    'expiry_date',
                    'trading_symbol',
                    DB::raw("TIME(interval_time) as candle_time"),
                ]);

            if (!empty($selectedSymbols)) {
                $query->whereIn('base_symbol', $selectedSymbols);
            }

            $allCandles = $query->get();

            if ($allCandles->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data found for selected filters',
                    'data'    => [],
                ]);
            }

            // ── Group by date + symbol ────────────────────────────────
            $grouped = [];
            foreach ($allCandles as $candle) {
                $date = is_string($candle->trade_date)
                    ? substr($candle->trade_date, 0, 10)
                    : Carbon::parse($candle->trade_date)->toDateString();
                $sym  = $candle->base_symbol;
                $type = $candle->instrument_type;
                $time = substr($candle->candle_time, 0, 5);

                $grouped[$date][$sym][$type][$time][] = $candle;
            }

            $results = [];

            foreach ($grouped as $date => $symbols) {
                foreach ($symbols as $symbol => $typeMap) {

                    // ── FUT ──────────────────────────────────────────────
                    // open=09:30, current=12:15
                    $futOpen    = $typeMap['FUT']['09:30'][0] ?? null;
                    $futCurrent = $typeMap['FUT']['12:15'][0] ?? null;

                    if (!$futOpen || !$futCurrent) continue;

                    $openOiFut   = (int)   ($futOpen->oi    ?? 0);
                    $curOiFut    = (int)   ($futCurrent->oi ?? 0);
                    $oiChangeFut = $curOiFut - $openOiFut;
                    $oiPctFut    = $openOiFut > 0 ? round(($oiChangeFut / $openOiFut) * 100, 4) : 0;

                    $openCloseFut    = (float) ($futOpen->close    ?? 0);
                    $currentCloseFut = (float) ($futCurrent->close ?? 0);
                    $spotPrice       = $currentCloseFut;

                    $futClChange    = $currentCloseFut - $openCloseFut;
                    $futClChangePct = $openCloseFut > 0
                        ? round(($futClChange / $openCloseFut) * 100, 4)
                        : 0;
                    $futPriceSignal = $currentCloseFut > $openCloseFut
                        ? 'BULLISH'
                        : ($currentCloseFut < $openCloseFut ? 'BEARISH' : 'NEUTRAL');

                    $futOiSignal = $this->analyzeFutOI($curOiFut, $openOiFut);
                    $futBias     = $futOiSignal['market_bias'] ?? 'Normal';

                    // ── CE / PE aggregation (open=09:30, current=12:15) ──
                    $ceAgg = $this->aggregateOptionCandles(
                        $typeMap['CE']['09:30'] ?? [],
                        $typeMap['CE']['12:15'] ?? []
                    );
                    $peAgg = $this->aggregateOptionCandles(
                        $typeMap['PE']['09:30'] ?? [],
                        $typeMap['PE']['12:15'] ?? []
                    );

                    if ($ceAgg['cur_oi'] == 0 && $peAgg['cur_oi'] == 0) continue;

                    // ── OI % change ───────────────────────────────────────
                    $ceOiPct = $ceAgg['open_oi'] > 0
                        ? round((($ceAgg['cur_oi'] - $ceAgg['open_oi']) / $ceAgg['open_oi']) * 100, 4)
                        : 0;
                    $peOiPct = $peAgg['open_oi'] > 0
                        ? round((($peAgg['cur_oi'] - $peAgg['open_oi']) / $peAgg['open_oi']) * 100, 4)
                        : 0;

                    // ── Close % change ────────────────────────────────────
                    $ceClChangePct = ($ceAgg['open_close'] ?? 0) > 0
                        ? round((($ceAgg['cur_close'] - $ceAgg['open_close']) / $ceAgg['open_close']) * 100, 4)
                        : null;
                    $peClChangePct = ($peAgg['open_close'] ?? 0) > 0
                        ? round((($peAgg['cur_close'] - $peAgg['open_close']) / $peAgg['open_close']) * 100, 4)
                        : null;

                    // ── Sentiment ─────────────────────────────────────────
                    $oiSignal    = $this->getOISignal($ceOiPct, $peOiPct);
                    $sentiment   = $oiSignal['signal'];
                    $tradeAction = match ($sentiment) {
                        'BULLISH' => 'BUY CE',
                        'BEARISH' => 'BUY PE',
                        default   => 'WAIT',
                    };

                    if (!empty($filterAction) && $tradeAction !== $filterAction) continue;

                    // ── Ratio & strength ──────────────────────────────────
                    $peCeRatio = $ceAgg['cur_oi'] > 0
                        ? round($peAgg['cur_oi'] / $ceAgg['cur_oi'], 2)
                        : 0;

                    $diff = abs($ceOiPct - $peOiPct);
                    if      ($diff > 40) $strengthRank = 'Rank 1';
                    elseif  ($diff > 25) $strengthRank = 'Rank 2';
                    elseif  ($diff > 10) $strengthRank = 'Rank 3';
                    elseif  ($diff > 5)  $strengthRank = 'Rank 4';
                    else                 $strengthRank = 'Normal';

                    $isBoth       = str_contains($oiSignal['condition'], 'Both');
                    $strongerSide = $isBoth
                        ? (abs($ceOiPct) > abs($peOiPct) ? 'CE' : (abs($peOiPct) > abs($ceOiPct) ? 'PE' : 'EQUAL'))
                        : 'CLEAR';

                    $fut50Ma = $this->getFut50MaSignal($symbol, $date);

                    $results[] = [
                        'date'       => $date,
                        'symbol'     => $symbol,
                        'fut_symbol' => $futCurrent->trading_symbol ?? $symbol . 'FUT',

                        'ce_oi'            => $ceAgg['cur_oi'],
                        'ce_oi_prev'       => $ceAgg['open_oi'],
                        'ce_oi_change_pct' => $ceOiPct,

                        'pe_oi'            => $peAgg['cur_oi'],
                        'pe_oi_prev'       => $peAgg['open_oi'],
                        'pe_oi_change_pct' => $peOiPct,

                        'fut_oi'            => $curOiFut,
                        'fut_oi_prev'       => $openOiFut,
                        'fut_oi_change_pct' => round($oiPctFut, 2),

                        'ce_oi_change_pct_fut' => round($ceOiPct, 2),
                        'pe_oi_change_pct_fut' => round($peOiPct, 2),

                        'strength_rank' => $strengthRank,
                        'strength_diff' => round($diff, 2),
                        'stronger_side' => $strongerSide,

                        'pe_ce_ratio'       => $peCeRatio,
                        'oi_interpretation' => $oiSignal['reason'],
                        'oi_condition'      => $oiSignal['condition'],

                        'options_sentiment' => $sentiment,
                        'futures_oi_view'   => $futBias,
                        'final_sentiment'   => $sentiment,
                        'trade_action'      => $tradeAction,

                        'fut_price_prev'       => round($openCloseFut, 2),
                        'fut_price_today'      => round($currentCloseFut, 2),
                        'fut_price_change'     => round($futClChange, 2),
                        'fut_price_change_pct' => round($futClChangePct, 2),
                        'fut_price_signal'     => $futPriceSignal,

                        'ce_open_close'       => round($ceAgg['open_close']  ?? 0, 2),
                        'ce_current_close'    => round($ceAgg['cur_close']   ?? 0, 2),
                        'ce_close_change_pct' => round($ceClChangePct        ?? 0, 2),

                        'pe_open_close'       => round($peAgg['open_close']  ?? 0, 2),
                        'pe_current_close'    => round($peAgg['cur_close']   ?? 0, 2),
                        'pe_close_change_pct' => round($peClChangePct        ?? 0, 2),

                        'spot_price'      => round($spotPrice, 2),
                        'fut_50ma_signal' => $fut50Ma,
                    ];
                }
            }

            usort($results, function ($a, $b) {
                $dateComp = strcmp($b['date'], $a['date']);
                return $dateComp !== 0 ? $dateComp : strcmp($a['symbol'], $b['symbol']);
            });

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('9to12 PE/CE Analysis Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  AGGREGATE option candles across ATM-1, ATM, ATM+1
    //  Matches by strike_position so intraday moves don't
    //  mis-pair open vs current candles.
    //  IV removed — column no longer exists in option_ohlc_data.
    // =========================================================

    private function aggregateOptionCandles(array $openCandles, array $curCandles): array
    {
        // Index current candles by strike position ('ATM', 'ATM+1', 'ATM-1')
        $curByPosition = [];
        foreach ($curCandles as $c) {
            $pos = $c->strike_position ?? 'N/A';
            if ($pos === 'N/A') continue;
            $curByPosition[$pos] = $c;
        }

        $openOiTotal = 0;
        $curOiTotal  = 0;
        $openClTotal = 0.0;
        $curClTotal  = 0.0;
        $closeCount  = 0;

        foreach ($openCandles as $oc) {
            $pos = $oc->strike_position ?? 'N/A';
            if ($pos === 'N/A') continue;

            $cc = $curByPosition[$pos] ?? null;
            if (!$cc || (float)($cc->close ?? 0) <= 0) continue;

            $openOiTotal += (int)   ($oc->oi    ?? 0);
            $curOiTotal  += (int)   ($cc->oi    ?? 0);
            $openClTotal += (float) ($oc->close ?? 0);
            $curClTotal  += (float) ($cc->close ?? 0);
            $closeCount++;
        }

        return [
            'open_oi'    => $openOiTotal,
            'cur_oi'     => $curOiTotal,
            'open_close' => $closeCount > 0 ? $openClTotal / $closeCount : 0,
            'cur_close'  => $closeCount > 0 ? $curClTotal  / $closeCount : 0,
        ];
    }

    // =========================================================
    //  CALCULATE PROFIT
    //  Entry at 12:15, profit window 12:30 → 15:15
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
                'high_price'    => 0, 'high_time' => null, 'high_pl' => 0, 'high_roi' => 0,
                'low_price'     => 0, 'low_time'  => null, 'low_pl'  => 0, 'low_roi'  => 0,
                'eod_price'     => 0,                      'eod_pl'  => 0, 'eod_roi'  => 0,
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
                $optionType = $tradeAction === 'BUY CE' ? 'CE' : 'PE';

                // Entry candle = ATM option at 12:15
                $atmRow = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('strike_position', 'ATM')
                    ->where('is_missing', 0)
                    ->whereNotNull('expiry_date')
                    ->whereRaw("TIME(interval_time) = '12:15:00'")
                    ->orderBy('expiry_date')
                    ->first();

                // Fallback: nearest strike to spot
                if (!$atmRow) {
                    $atmRow = OptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) = '12:15:00'")
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
                $expiryDate = $atmRow->expiry_date instanceof \Carbon\Carbon
                    ? $atmRow->expiry_date->toDateString()
                    : (is_string($atmRow->expiry_date)
                        ? substr($atmRow->expiry_date, 0, 10)
                        : (string) $atmRow->expiry_date);

                // Buy candle: 12:15 open price (or close as fallback)
                $buyCandle = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->where(function ($q) {
                        $q->whereRaw("TIME(interval_time) = '12:15:00'")
                          ->orWhereRaw("TIME(interval_time) BETWEEN '12:15:00' AND '12:29:59'");
                    })
                    ->orderByRaw("ABS(TIME_TO_SEC(TIME(interval_time)) - TIME_TO_SEC('12:15:00'))")
                    ->first();

                $buyPrice = 0.0;
                if ($buyCandle) {
                    $candleOpen  = (float) ($buyCandle->open  ?? 0);
                    $candleClose = (float) ($buyCandle->close ?? 0);
                    $buyPrice    = $candleOpen > 0 ? $candleOpen : $candleClose;
                }
                if ($buyPrice <= 0 && $atmRow) {
                    $atmOpen  = (float) ($atmRow->open  ?? 0);
                    $atmClose = (float) ($atmRow->close ?? 0);
                    $buyPrice = $atmOpen > 0 ? $atmOpen : $atmClose;
                    $buyCandle = $atmRow;
                }

                if ($buyPrice <= 0) {
                    $placeholder['error']         = 'NO_BUY_PRICE';
                    $placeholder['option_symbol'] = $atmRow->trading_symbol ?? null;
                    $placeholder['strike']        = $strike;
                    $placeholder['option_type']   = $optionType;
                    $results[] = $placeholder;
                    continue;
                }

                // Profit window: 12:30 → 15:15 (candles after entry)
                $highRow = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) BETWEEN '12:30:00' AND '15:15:00'")
                    ->orderByDesc('high')
                    ->first();

                $highPrice = $highRow ? max((float) $highRow->high, $buyPrice) : $buyPrice;
                $highTime  = $highRow ? Carbon::parse($highRow->interval_time)->format('H:i') : null;

                $lowRow = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) BETWEEN '12:30:00' AND '15:15:00'")
                    ->orderBy('low')
                    ->first();

                $lowPrice = $lowRow ? (float) $lowRow->low : $buyPrice;
                $lowTime  = $lowRow ? Carbon::parse($lowRow->interval_time)->format('H:i') : null;

                // EOD candle = 15:15
                $eodCandle = OptionOhlcData::where('base_symbol', $symbol)
                    ->where('instrument_type', $optionType)
                    ->where('strike', $strike)
                    ->whereDate('expiry_date', $expiryDate)
                    ->whereDate('trade_date', $tradeDate)
                    ->where('is_missing', 0)
                    ->whereRaw("TIME(interval_time) = '15:15:00'")
                    ->first();

                // Fallback: last available candle after entry
                if (!$eodCandle) {
                    $eodCandle = OptionOhlcData::where('base_symbol', $symbol)
                        ->where('instrument_type', $optionType)
                        ->where('strike', $strike)
                        ->whereDate('expiry_date', $expiryDate)
                        ->whereDate('trade_date', $tradeDate)
                        ->where('is_missing', 0)
                        ->whereRaw("TIME(interval_time) > '12:15:00'")
                        ->orderByDesc('interval_time')
                        ->first();
                }

                $eodPrice   = $eodCandle ? (float) $eodCandle->close : $buyPrice;
                $lotSize    = $this->getLotSize($symbol);
                $investment = round($buyPrice * $lotSize, 2);

                $highPL  = round(($highPrice - $buyPrice) * $lotSize, 2);
                $highRoi = $investment > 0 ? round(($highPL  / $investment) * 100, 2) : 0;
                $lowPL   = round(($lowPrice  - $buyPrice) * $lotSize, 2);
                $lowRoi  = $investment > 0 ? round(($lowPL   / $investment) * 100, 2) : 0;
                $eodPL   = round(($eodPrice  - $buyPrice) * $lotSize, 2);
                $eodRoi  = $investment > 0 ? round(($eodPL   / $investment) * 100, 2) : 0;

                $results[] = [
                    'index'         => $idx,
                    'option_symbol' => ($buyCandle ?? $atmRow)->trading_symbol ?? "{$symbol}{$optionType}{$strike}",
                    'strike'        => $strike,
                    'option_type'   => $optionType,
                    'lot_size'      => $lotSize,
                    'investment'    => $investment,
                    'buy_price'     => round($buyPrice, 2),
                    'high_price'    => round($highPrice, 2),
                    'high_time'     => $highTime,
                    'high_pl'       => $highPL,
                    'high_roi'      => $highRoi,
                    'low_price'     => round($lowPrice, 2),
                    'low_time'      => $lowTime,
                    'low_pl'        => $lowPL,
                    'low_roi'       => $lowRoi,
                    'eod_price'     => round($eodPrice, 2),
                    'eod_pl'        => $eodPL,
                    'eod_roi'       => $eodRoi,
                    'profit_loss'   => $highPL,
                    'roi_percent'   => $highRoi,
                    'error'         => null,
                ];

            } catch (\Exception $e) {
                Log::error("9to12 Profit row error (idx={$idx}): " . $e->getMessage());
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
    //  LOT SIZE HELPER
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
    //  50 MA — uses 09:30 candle (matches analysis open candle)
    // =========================================================

    private function historyStartDate(string $tradeDate, int $maPeriod = 50): string
    {
        $daysBack = (int) ceil($maPeriod / 25) + 3;
        return Carbon::parse($tradeDate)->subDays($daysBack)->toDateString();
    }

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
        $historyStart = $this->historyStartDate($tradeDate, $maPeriod);

        $allCandles = OptionOhlcData::where('base_symbol', $baseSymbol)
            ->where('instrument_type', 'FUT')
            ->where('is_missing', 0)
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

        // Target: 12:15 candle on the trade date (matches analysis current candle)
        $targetIdx = null;
        foreach ($allCandles as $idx => $candle) {
            $candleDate = is_string($candle->candle_date)
                ? $candle->candle_date
                : Carbon::parse($candle->candle_date)->toDateString();
            if ($candleDate !== $tradeDate) continue;
            $time = substr($candle->candle_time ?? '', 0, 5);
            if ($time >= '12:15' && $time <= '12:29') { $targetIdx = $idx; break; }
        }

        // Fallback: last candle on trade date
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
    //  OI SIGNAL LOGIC
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
            ? ['signal' => 'BEARISH', 'reason' => "Both buildup but CE stronger (CE: +{$cePct}% > PE: +{$pePct}%)", 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both buildup but PE stronger (PE: +{$pePct}% > CE: +{$cePct}%)", 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both unwinding but CE stronger (CE: {$cePct}% < PE: {$pePct}%)", 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both unwinding but PE stronger (PE: {$pePct}% < CE: {$cePct}%)", 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    private function analyzeFutOI(int $curOI, int $openOI): array
    {
        $change    = $curOI - $openOI;
        $changePct = $openOI > 0 ? ($change / $openOI) * 100 : 0;

        if ($change > 0) {
            return ['direction' => 'BUILDUP',   'strength' => abs($changePct) > 10 ? 'STRONG' : 'MODERATE', 'market_bias' => 'Bullish'];
        } elseif ($change < 0) {
            return ['direction' => 'UNWINDING', 'strength' => abs($changePct) > 10 ? 'STRONG' : 'MODERATE', 'market_bias' => 'Bearish'];
        }

        return ['direction' => 'NEUTRAL', 'strength' => 'WEAK', 'market_bias' => 'Normal'];
    }

    // =========================================================
    //  CONFIG PAGE
    // =========================================================

    public function config()
    {
        $pageTitle = '9:30→12:15 Auto Trading Configuration';

        $brokers = BrokerApi::select('client_name', 'id')
            ->where('user_id', Auth::id())
            ->where('client_type', 'Zerodha')
            ->get();

        $configs = OIIVAutoConfig::where('user_id', Auth::id())
            ->where('config_type', '9to12')
            ->with('broker:id,client_name')
            ->withCount('orders')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view($this->activeTemplate . 'user.oiiv-auto.config-9to12', [
            'pageTitle' => $pageTitle,
            'brokers'   => $brokers,
            'configs'   => $configs,
        ]);
    }

    // =========================================================
    //  STORE CONFIG
    // =========================================================

    public function store(Request $request)
    {
        $request->validate([
            'broker_api_id'     => 'required|exists:broker_apis,id',
            'order_type'        => 'required|in:LIMIT,MARKET',
            'product'           => 'required|in:NRML,MIS',
            'disc_ltp'          => 'required|numeric|min:0|max:100',
            'index_ce_quantity' => 'required|integer|min:0',
            'index_pe_quantity' => 'required|integer|min:0',
            'stock_ce_quantity' => 'required|integer|min:0',
            'stock_pe_quantity' => 'required|integer|min:0',
            'signal_mode'       => 'required|in:align,opposite',
            'option_series'     => 'required|in:current,next',
            'status'            => 'required|in:1,0',
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
            OIIVAutoConfig::create([
                'user_id'            => Auth::id(),
                'broker_api_id'      => $request->broker_api_id,
                'order_type'         => $request->order_type,
                'product'            => $request->product,
                'disc_ltp'           => $request->disc_ltp,
                'index_quantity'     => $request->index_ce_quantity,
                'stock_quantity'     => $request->stock_ce_quantity,
                'index_ce_quantity'  => $request->index_ce_quantity,
                'index_pe_quantity'  => $request->index_pe_quantity,
                'stock_ce_quantity'  => $request->stock_ce_quantity,
                'stock_pe_quantity'  => $request->stock_pe_quantity,
                'signal_mode'        => $request->signal_mode,
                'option_series'      => $request->option_series,
                'status'             => $request->status,
                'strong_ce_quantity' => 0,
                'strong_pe_quantity' => 0,
                'rank1_ce_quantity'  => $request->rank1_ce_quantity ?? 0,
                'rank1_pe_quantity'  => $request->rank1_pe_quantity ?? 0,
                'rank2_ce_quantity'  => $request->rank2_ce_quantity ?? 0,
                'rank2_pe_quantity'  => $request->rank2_pe_quantity ?? 0,
                'rank3_ce_quantity'  => $request->rank3_ce_quantity ?? 0,
                'rank3_pe_quantity'  => $request->rank3_pe_quantity ?? 0,
                'rank4_ce_quantity'  => $request->rank4_ce_quantity ?? 0,
                'rank4_pe_quantity'  => $request->rank4_pe_quantity ?? 0,
                'config_type'        => '9to12',
            ]);

            $notify[] = ['success', '9to12 auto trading configuration created successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            Log::error('9to12 Config Store Error: ' . $e->getMessage());
            $notify[] = ['error', 'Error creating configuration'];
            return back()->withNotify($notify);
        }
    }

    // =========================================================
    //  UPDATE CONFIG
    // =========================================================

    public function update(Request $request, $id)
    {
        $request->validate([
            'broker_api_id'     => 'required|exists:broker_apis,id',
            'order_type'        => 'required|in:LIMIT,MARKET',
            'product'           => 'required|in:NRML,MIS',
            'disc_ltp'          => 'required|numeric|min:0|max:100',
            'index_ce_quantity' => 'required|integer|min:0',
            'index_pe_quantity' => 'required|integer|min:0',
            'stock_ce_quantity' => 'required|integer|min:0',
            'stock_pe_quantity' => 'required|integer|min:0',
            'signal_mode'       => 'required|in:align,opposite',
            'option_series'     => 'required|in:current,next',
            'status'            => 'required|in:1,0',
            'rank1_ce_quantity' => 'nullable|integer|min:0',
            'rank1_pe_quantity' => 'nullable|integer|min:0',
            'rank2_ce_quantity' => 'nullable|integer|min:0',
            'rank2_pe_quantity' => 'nullable|integer|min:0',
            'rank3_ce_quantity' => 'nullable|integer|min:0',
            'rank3_pe_quantity' => 'nullable|integer|min:0',
            'rank4_ce_quantity' => 'nullable|integer|min:0',
            'rank4_pe_quantity' => 'nullable|integer|min:0',
        ]);

        $config = OIIVAutoConfig::where('id', $id)
            ->where('user_id', Auth::id())
            ->where('config_type', '9to12')
            ->firstOrFail();

        $config->update([
            'broker_api_id'     => $request->broker_api_id,
            'order_type'        => $request->order_type,
            'product'           => $request->product,
            'disc_ltp'          => $request->disc_ltp,
            'index_ce_quantity' => $request->index_ce_quantity,
            'index_pe_quantity' => $request->index_pe_quantity,
            'stock_ce_quantity' => $request->stock_ce_quantity,
            'stock_pe_quantity' => $request->stock_pe_quantity,
            'signal_mode'       => $request->signal_mode,
            'option_series'     => $request->option_series,
            'status'            => $request->status,
            'rank1_ce_quantity' => $request->rank1_ce_quantity ?? 0,
            'rank1_pe_quantity' => $request->rank1_pe_quantity ?? 0,
            'rank2_ce_quantity' => $request->rank2_ce_quantity ?? 0,
            'rank2_pe_quantity' => $request->rank2_pe_quantity ?? 0,
            'rank3_ce_quantity' => $request->rank3_ce_quantity ?? 0,
            'rank3_pe_quantity' => $request->rank3_pe_quantity ?? 0,
            'rank4_ce_quantity' => $request->rank4_ce_quantity ?? 0,
            'rank4_pe_quantity' => $request->rank4_pe_quantity ?? 0,
        ]);

        $notify[] = ['success', '9to12 configuration updated successfully!'];
        return back()->withNotify($notify);
    }

    // =========================================================
    //  TOGGLE STATUS
    // =========================================================

    public function toggleStatus($id)
    {
        try {
            $config = OIIVAutoConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('config_type', '9to12')
                ->firstOrFail();

            $config->status = !$config->status;
            $config->save();

            $status   = $config->status ? 'activated' : 'deactivated';
            $notify[] = ['success', "9to12 configuration {$status} successfully!"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error updating configuration.'];
            return back()->withNotify($notify);
        }
    }

    // =========================================================
    //  DELETE CONFIG
    // =========================================================

    public function destroy($id)
    {
        try {
            $config = OIIVAutoConfig::where('id', $id)
                ->where('user_id', Auth::id())
                ->where('config_type', '9to12')
                ->firstOrFail();

            $pendingOrders = $config->orders()
                ->where('is_order_placed', false)
                ->where('status', true)
                ->count();

            if ($pendingOrders > 0) {
                $notify[] = ['error', "Cannot delete. {$pendingOrders} orders pending."];
                return back()->withNotify($notify);
            }

            $config->delete();

            $notify[] = ['success', '9to12 configuration deleted successfully!'];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', 'Error deleting configuration.'];
            return back()->withNotify($notify);
        }
    }

    // =========================================================
    //  VIEW ORDERS
    // =========================================================

    public function viewOrders($configId)
    {
        $pageTitle = '9to12 Auto Trading Orders';

        $config = OIIVAutoConfig::where('user_id', Auth::id())
            ->where('id', $configId)
            ->where('config_type', '9to12')
            ->firstOrFail();

        $orders = OIIVAutoOrder::where('config_id', $configId)
            ->where('user_id', Auth::id())
            ->with(['broker:id,client_name'])
            ->orderByDesc('signal_detected_at')
            ->paginate(50);

        return view($this->activeTemplate . 'user.oiiv-auto.orders', [
            'pageTitle' => $pageTitle,
            'config'    => $config,
            'orders'    => $orders,
        ]);
    }
}