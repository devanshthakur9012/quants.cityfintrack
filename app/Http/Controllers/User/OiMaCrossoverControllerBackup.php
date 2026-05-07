<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OiMaCrossoverControllerBackup extends Controller
{
    public function index(Request $request)
    {
        $pageTitle      = 'OI & Price vs MA Crossover Signals';
        $maPeriod       = (int) $request->get('ma_period', 50);
        $optionType     = $request->get('option_type', 'BOTH');
        $selectedDate   = $request->get('trade_date');
        $selectedSymbol = $request->get('symbol', '');

        $latestDate = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])->max('trade_date');

        if (!$latestDate) {
            return view($this->activeTemplate . 'user.oi-crossover.index', [
                'pageTitle'        => $pageTitle,
                'crossovers'       => collect(),
                'latestDate'       => null,
                'selectedDate'     => null,
                'maPeriod'         => $maPeriod,
                'optionType'       => $optionType,
                'availableDates'   => collect(),
                'availableSymbols' => collect(),
                'selectedSymbol'   => '',
                'totalSymbols'     => 0,
                'detailMode'       => false,
                'detailRows'       => collect(),
            ]);
        }

        $tradeDate = $selectedDate ?? $latestDate;

        $availableDates = OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->select('trade_date')->distinct()->orderByDesc('trade_date')->limit(30)->pluck('trade_date');

        $allSymbols = OptionOhlcData::whereDate('trade_date', $tradeDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->distinct('base_symbol')->orderBy('base_symbol')->pluck('base_symbol');

        // DETAIL MODE
        if ($selectedSymbol && $allSymbols->contains($selectedSymbol)) {
            $detailRows = $this->getDetailRows($selectedSymbol, $tradeDate, $optionType, $maPeriod);

            return view($this->activeTemplate . 'user.oi-crossover.index', [
                'pageTitle'        => $pageTitle,
                'crossovers'       => collect(),
                'latestDate'       => $latestDate,
                'selectedDate'     => $tradeDate,
                'maPeriod'         => $maPeriod,
                'optionType'       => $optionType,
                'availableDates'   => $availableDates,
                'availableSymbols' => $allSymbols,
                'selectedSymbol'   => $selectedSymbol,
                'totalSymbols'     => $allSymbols->count(),
                'detailMode'       => true,
                'detailRows'       => $detailRows,
            ]);
        }

        // OVERVIEW MODE
        $overviewRows = collect();
        foreach ($allSymbols as $symbol) {
            $row = $this->getOverviewRow($symbol, $tradeDate, $optionType, $maPeriod);
            if ($row) {
                $overviewRows->push($row);
            }
        }

        return view($this->activeTemplate . 'user.oi-crossover.index', [
            'pageTitle'        => $pageTitle,
            'crossovers'       => $overviewRows,
            'latestDate'       => $latestDate,
            'selectedDate'     => $tradeDate,
            'maPeriod'         => $maPeriod,
            'optionType'       => $optionType,
            'availableDates'   => $availableDates,
            'availableSymbols' => $allSymbols,
            'selectedSymbol'   => $selectedSymbol,
            'totalSymbols'     => $allSymbols->count(),
            'detailMode'       => false,
            'detailRows'       => collect(),
        ]);
    }

    /**
     * OVERVIEW ROW — one row per symbol.
     *
     * Strike selection: pick the strike with the most candles across the day.
     * Among ties, prefer strike_position = ATM ever.
     * This ensures the strike tracked the FULL day (9:15 to 15:15) is chosen,
     * regardless of intraday ATM shifts.
     *
     * Layout: symbol + last_time in left block, then CE data, then PE data.
     * last_time = last interval_time across the day for this strike (should be 15:15).
     */
    private function getOverviewRow(string $symbol, string $tradeDate, string $optionType, int $maPeriod): ?array
    {
        $result = ['symbol' => $symbol, 'last_time' => null, 'ce' => null, 'pe' => null];

        $types = match ($optionType) {
            'CE'    => ['CE'],
            'PE'    => ['PE'],
            default => ['CE', 'PE'],
        };

        foreach ($types as $type) {
            $bestStrike = $this->getDominantStrike($symbol, $tradeDate, $type);
            if (!$bestStrike) continue;

            $candles = OptionOhlcData::whereDate('trade_date', $tradeDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', $type)
                ->where('strike', $bestStrike['strike'])
                ->whereDate('expiry_date', $bestStrike['expiry'])
                ->orderBy('interval_time')
                ->get([
                    DB::raw("TIME(interval_time) as candle_time"),
                    'oi', 'volume', 'close', 'strike', 'trading_symbol',
                    'atm_strike', 'strike_position',
                ]);

            if ($candles->isEmpty()) continue;

            $n           = $candles->count();
            $oiValues    = $candles->pluck('oi')->map(fn($v) => (float)$v)->toArray();
            $closeValues = $candles->pluck('close')->map(fn($v) => (float)$v)->toArray();
            $oiMa        = $this->calculateRollingMA($oiValues, $maPeriod);
            $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

            $lastIdx   = $n - 1;
            $last      = $candles->last();
            $lastOiMa  = $oiMa[$lastIdx]   ?? null;
            $lastClMa  = $closeMa[$lastIdx] ?? null;
            $lastOi    = (float)$last->oi;
            $lastClose = (float)$last->close;
            $lastTime  = substr($last->candle_time ?? '', 0, 5);

            // OI signal (inverted: OI > MA = writers dominating = BEARISH)
            $oiSignal = 'NEUTRAL';
            if ($lastOiMa !== null) {
                if ($lastOi > $lastOiMa)     $oiSignal = 'BEARISH';
                elseif ($lastOi < $lastOiMa) $oiSignal = 'BULLISH';
            }

            // Price signal (normal: Close > MA = BULLISH)
            $priceSignal = 'NEUTRAL';
            if ($lastClMa !== null) {
                if ($lastClose > $lastClMa)     $priceSignal = 'BULLISH';
                elseif ($lastClose < $lastClMa) $priceSignal = 'BEARISH';
            }

            // Most recent OI crossover event (informational)
            $candleArr       = $candles->values()->toArray();
            $lastOiCrossTime = null;
            $lastOiCrossDir  = null;
            for ($i = 1; $i < $n; $i++) {
                $prevOi   = (float)($candleArr[$i-1]['oi'] ?? 0);
                $currOi   = (float)($candleArr[$i]['oi']   ?? 0);
                $prevOiMa = $oiMa[$i-1] ?? null;
                $currOiMa = $oiMa[$i]   ?? null;
                if ($prevOiMa === null || $currOiMa === null) continue;
                if ($prevOi <= $prevOiMa && $currOi > $currOiMa) {
                    $lastOiCrossTime = substr($candleArr[$i]['candle_time'] ?? '', 0, 5);
                    $lastOiCrossDir  = 'BEARISH';
                } elseif ($prevOi >= $prevOiMa && $currOi < $currOiMa) {
                    $lastOiCrossTime = substr($candleArr[$i]['candle_time'] ?? '', 0, 5);
                    $lastOiCrossDir  = 'BULLISH';
                }
            }

            // last_time = whichever is latest between CE and PE
            if (!$result['last_time'] || $lastTime > $result['last_time']) {
                $result['last_time'] = $lastTime;
            }

            $result[strtolower($type)] = [
                'strike'             => $bestStrike['strike'],
                'trading_symbol'     => $last->trading_symbol,
                'strike_position'    => $last->strike_position,
                'atm_strike'         => $last->atm_strike,
                'ltp'                => $lastClose,
                'total_candles'      => $n,
                'latest_oi'          => $lastOi,
                'latest_oi_ma'       => $lastOiMa !== null ? round($lastOiMa, 0) : null,
                'latest_close_ma'    => $lastClMa  !== null ? round($lastClMa,  2) : null,
                'oi_signal'          => $oiSignal,
                'price_signal'       => $priceSignal,
                'last_oi_cross_time' => $lastOiCrossTime,
                'last_oi_cross_dir'  => $lastOiCrossDir,
            ];
        }

        if (!$result['ce'] && !$result['pe']) return null;
        return $result;
    }

    /**
     * Pick the dominant strike: the one with the most candles for the full day.
     * Tie-break: prefer one that was ATM at some point.
     */
    private function getDominantStrike(string $symbol, string $tradeDate, string $type): ?array
    {
        $row = OptionOhlcData::whereDate('trade_date', $tradeDate)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereNotNull('strike')
            ->whereNotNull('expiry_date')
            ->select(
                'strike',
                'expiry_date',
                DB::raw('COUNT(*) as candle_count'),
                DB::raw("MAX(CASE WHEN strike_position = 'ATM' THEN 1 ELSE 0 END) as is_atm_ever")
            )
            ->groupBy('strike', 'expiry_date')
            ->orderByDesc('candle_count')
            ->orderByDesc('is_atm_ever')
            ->first();

        return $row ? ['strike' => $row->strike, 'expiry' => $row->expiry_date] : null;
    }

    /**
     * DETAIL ROWS — all 15-min candles for a symbol.
     * Uses the dominant strike (most candles = full day coverage).
     */
    private function getDetailRows(string $symbol, string $tradeDate, string $optionType, int $maPeriod): array
    {
        $types  = $optionType === 'BOTH' ? ['CE', 'PE'] : [$optionType];
        $result = [];

        foreach ($types as $type) {
            $bestStrike = $this->getDominantStrike($symbol, $tradeDate, $type);
            if (!$bestStrike) continue;

            $candles = OptionOhlcData::whereDate('trade_date', $tradeDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', $type)
                ->where('strike', $bestStrike['strike'])
                ->whereDate('expiry_date', $bestStrike['expiry'])
                ->orderBy('interval_time')
                ->get([
                    DB::raw("TIME(interval_time) as candle_time"),
                    'oi', 'volume', 'close', 'open', 'high', 'low',
                    'strike', 'trading_symbol', 'atm_strike', 'strike_position',
                ]);

            if ($candles->isEmpty()) continue;

            $oiValues    = $candles->pluck('oi')->map(fn($v) => (float)$v)->toArray();
            $closeValues = $candles->pluck('close')->map(fn($v) => (float)$v)->toArray();
            $oiMa        = $this->calculateRollingMA($oiValues, $maPeriod);
            $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);
            $candleArr   = $candles->values()->toArray();
            $n           = count($candleArr);
            $rows        = [];
            $crossCount  = 0;

            for ($i = 0; $i < $n; $i++) {
                $c         = $candleArr[$i];
                $currOi    = (float)($c['oi']    ?? 0);
                $currClose = (float)($c['close']  ?? 0);
                $currOiMa  = $oiMa[$i]    ?? null;
                $currClMa  = $closeMa[$i] ?? null;

                $oiSignal = 'NEUTRAL';
                $oiCross  = false;
                if ($currOiMa !== null) {
                    if ($i > 0) {
                        $prevOi   = (float)($candleArr[$i-1]['oi'] ?? 0);
                        $prevOiMa = $oiMa[$i-1] ?? null;
                        if ($prevOiMa !== null) {
                            if ($prevOi <= $prevOiMa && $currOi > $currOiMa) {
                                $oiSignal = 'BEARISH'; $oiCross = true; $crossCount++;
                            } elseif ($prevOi >= $prevOiMa && $currOi < $currOiMa) {
                                $oiSignal = 'BULLISH'; $oiCross = true; $crossCount++;
                            } else {
                                $oiSignal = ($currOi > $currOiMa) ? 'BEARISH' : 'BULLISH';
                            }
                        }
                    } else {
                        $oiSignal = ($currOi > $currOiMa) ? 'BEARISH' : 'BULLISH';
                    }
                }

                $priceSignal = 'NEUTRAL';
                $priceCross  = false;
                if ($currClMa !== null) {
                    if ($i > 0) {
                        $prevClose = (float)($candleArr[$i-1]['close'] ?? 0);
                        $prevClMa  = $closeMa[$i-1] ?? null;
                        if ($prevClMa !== null) {
                            if ($prevClose <= $prevClMa && $currClose > $currClMa) {
                                $priceSignal = 'BULLISH'; $priceCross = true; $crossCount++;
                            } elseif ($prevClose >= $prevClMa && $currClose < $currClMa) {
                                $priceSignal = 'BEARISH'; $priceCross = true; $crossCount++;
                            } else {
                                $priceSignal = ($currClose > $currClMa) ? 'BULLISH' : 'BEARISH';
                            }
                        }
                    } else {
                        $priceSignal = ($currClose > $currClMa) ? 'BULLISH' : 'BEARISH';
                    }
                }

                $rows[] = [
                    'time'            => substr($c['candle_time'] ?? '', 0, 5),
                    'open'            => $c['open'],
                    'high'            => $c['high'],
                    'low'             => $c['low'],
                    'close'           => $c['close'],
                    'oi'              => $currOi,
                    'oi_ma'           => $currOiMa !== null ? round($currOiMa, 0) : null,
                    'close_ma'        => $currClMa  !== null ? round($currClMa,  2) : null,
                    'oi_signal'       => $oiSignal,
                    'oi_cross'        => $oiCross,
                    'price_signal'    => $priceSignal,
                    'price_cross'     => $priceCross,
                    'volume'          => $c['volume'],
                    'strike_position' => $c['strike_position'],
                ];
            }

            $result[] = [
                'type'            => $type,
                'strike'          => $bestStrike['strike'],
                'trading_symbol'  => $candleArr[0]['trading_symbol'] ?? '',
                'rows'            => $rows,
                'crossover_count' => $crossCount,
                'total_candles'   => $n,
            ];
        }

        return $result;
    }

    // CHART DATA (AJAX)
    public function chartData(Request $request)
    {
        $symbol   = $request->get('symbol');
        $type     = $request->get('type');
        $strike   = $request->get('strike');
        $date     = $request->get('date');
        $maPeriod = (int) $request->get('ma_period', 50);

        $candles = OptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->where('strike', $strike)
            ->orderBy('interval_time')
            ->get([DB::raw("TIME(interval_time) as candle_time"), 'oi', 'volume', 'close']);

        $oiValues    = $candles->pluck('oi')->map(fn($v) => (float)$v)->toArray();
        $closeValues = $candles->pluck('close')->map(fn($v) => (float)$v)->toArray();
        $oiMa        = $this->calculateRollingMA($oiValues, $maPeriod);
        $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

        $labels = $oiData = $oiMaData = $closeData = $closeMaData = [];
        foreach ($candles as $i => $candle) {
            $labels[]      = substr($candle->candle_time, 0, 5);
            $oiData[]      = (int) $candle->oi;
            $oiMaData[]    = isset($oiMa[$i])    ? round($oiMa[$i], 0)    : null;
            $closeData[]   = (float) $candle->close;
            $closeMaData[] = isset($closeMa[$i]) ? round($closeMa[$i], 2) : null;
        }

        return response()->json([
            'labels'   => $labels,
            'oi'       => $oiData,
            'oi_ma'    => $oiMaData,
            'close'    => $closeData,
            'close_ma' => $closeMaData,
        ]);
    }

    private function calculateRollingMA(array $values, int $period): array
    {
        $ma = [];
        $n  = count($values);
        for ($i = 0; $i < $n; $i++) {
            $slice = array_slice($values, max(0, $i - $period + 1), min($i + 1, $period));
            $ma[]  = count($slice) > 0 ? array_sum($slice) / count($slice) : null;
        }
        return $ma;
    }
}