<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OiMaCrossoverController extends Controller
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
     * Compute how many calendar days back we need to guarantee enough
     * trading candles for a full MA warm-up.
     *
     * 15-min candles: ~25 per trading day.
     * We add +3 extra days as buffer for weekends / holidays.
     *
     *   maPeriod=50  → ceil(50/25)+3 = 5 calendar days back
     *   maPeriod=100 → ceil(100/25)+3 = 7 calendar days back
     *   maPeriod=200 → ceil(200/25)+3 = 11 calendar days back
     */
    private function historyStartDate(string $tradeDate, int $maPeriod): string
    {
        $daysBack = (int) ceil($maPeriod / 25) + 3;
        return Carbon::parse($tradeDate)->subDays($daysBack)->toDateString();
    }

    /**
     * OVERVIEW ROW — one row per symbol.
     *
     * Fetches enough historical candles (via whereBetween + Carbon::subDays)
     * so the MA is fully warmed-up before today's first candle.
     * Only today's last candle is used for the final signal shown in the table.
     */
    private function getOverviewRow(string $symbol, string $tradeDate, string $optionType, int $maPeriod): ?array
    {
        $result = ['symbol' => $symbol, 'last_time' => null, 'ce' => null, 'pe' => null];

        $types = match ($optionType) {
            'CE'    => ['CE'],
            'PE'    => ['PE'],
            default => ['CE', 'PE'],
        };

        $historyStart = $this->historyStartDate($tradeDate, $maPeriod);

        foreach ($types as $type) {
            $bestStrike = $this->getDominantStrike($symbol, $tradeDate, $type);
            if (!$bestStrike) continue;

            // Fetch history window + today, ordered chronologically
            $allCandles = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $type)
                ->where('strike', $bestStrike['strike'])
                ->whereDate('expiry_date', $bestStrike['expiry'])
                ->whereBetween('trade_date', [$historyStart, $tradeDate])
                ->orderBy('trade_date')
                ->orderBy('interval_time')
                ->get([
                    DB::raw("DATE(trade_date) as candle_date"),
                    DB::raw("TIME(interval_time) as candle_time"),
                    'oi', 'volume', 'close', 'strike', 'trading_symbol',
                    'atm_strike', 'strike_position',
                ]);

            if ($allCandles->isEmpty()) continue;

            // Calculate rolling MA on the full historical series
            $oiValues    = $allCandles->pluck('oi')->map(fn($v) => (float)$v)->toArray();
            $closeValues = $allCandles->pluck('close')->map(fn($v) => (float)$v)->toArray();
            $oiMa        = $this->calculateRollingMA($oiValues, $maPeriod);
            $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

            // Find where today's candles begin
            $todayStartIdx = null;
            foreach ($allCandles as $idx => $c) {
                if ($c->candle_date === $tradeDate) {
                    $todayStartIdx = $idx;
                    break;
                }
            }
            if ($todayStartIdx === null) continue;

            $todayCandles = $allCandles->slice($todayStartIdx)->values();
            $n            = $todayCandles->count();
            $lastTodayIdx = $todayStartIdx + $n - 1;

            $last      = $todayCandles->last();
            $lastOiMa  = $oiMa[$lastTodayIdx]   ?? null;
            $lastClMa  = $closeMa[$lastTodayIdx] ?? null;
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

            // Most recent OI crossover within TODAY's candles only
            $lastOiCrossTime = null;
            $lastOiCrossDir  = null;

            for ($i = 1; $i < $n; $i++) {
                $absIdx  = $todayStartIdx + $i;
                $absPrev = $absIdx - 1;

                $prevOi   = (float)($todayCandles[$i - 1]->oi ?? 0);
                $currOi   = (float)($todayCandles[$i]->oi    ?? 0);
                $prevOiMa = $oiMa[$absPrev] ?? null;
                $currOiMa = $oiMa[$absIdx]  ?? null;

                if ($prevOiMa === null || $currOiMa === null) continue;

                if ($prevOi <= $prevOiMa && $currOi > $currOiMa) {
                    $lastOiCrossTime = substr($todayCandles[$i]->candle_time ?? '', 0, 5);
                    $lastOiCrossDir  = 'BEARISH';
                } elseif ($prevOi >= $prevOiMa && $currOi < $currOiMa) {
                    $lastOiCrossTime = substr($todayCandles[$i]->candle_time ?? '', 0, 5);
                    $lastOiCrossDir  = 'BULLISH';
                }
            }

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
     * Pick the dominant strike for today: most candles, tie-break by ATM.
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
     * DETAIL ROWS — all 15-min candles for a symbol on the selected date.
     *
     * Uses whereBetween with historyStartDate to load enough prior candles
     * for MA warm-up. Only today's candles are displayed in the table.
     */
    private function getDetailRows(string $symbol, string $tradeDate, string $optionType, int $maPeriod): array
    {
        $types        = $optionType === 'BOTH' ? ['CE', 'PE'] : [$optionType];
        $result       = [];
        $historyStart = $this->historyStartDate($tradeDate, $maPeriod);

        foreach ($types as $type) {
            $bestStrike = $this->getDominantStrike($symbol, $tradeDate, $type);
            if (!$bestStrike) continue;

            $allCandles = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $type)
                ->where('strike', $bestStrike['strike'])
                ->whereDate('expiry_date', $bestStrike['expiry'])
                ->whereBetween('trade_date', [$historyStart, $tradeDate])
                ->orderBy('trade_date')
                ->orderBy('interval_time')
                ->get([
                    DB::raw("DATE(trade_date) as candle_date"),
                    DB::raw("TIME(interval_time) as candle_time"),
                    'oi', 'volume', 'close', 'open', 'high', 'low',
                    'strike', 'trading_symbol', 'atm_strike', 'strike_position',
                ]);

            if ($allCandles->isEmpty()) continue;

            // Calculate rolling MA on full series (history + today)
            $oiValues    = $allCandles->pluck('oi')->map(fn($v) => (float)$v)->toArray();
            $closeValues = $allCandles->pluck('close')->map(fn($v) => (float)$v)->toArray();
            $oiMa        = $this->calculateRollingMA($oiValues, $maPeriod);
            $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

            // Find today's starting index
            $todayStartIdx = null;
            foreach ($allCandles as $idx => $c) {
                if ($c->candle_date === $tradeDate) {
                    $todayStartIdx = $idx;
                    break;
                }
            }
            if ($todayStartIdx === null) continue;

            $todayCandles = $allCandles->slice($todayStartIdx)->values();
            $n            = $todayCandles->count();
            $rows         = [];
            $crossCount   = 0;

            for ($i = 0; $i < $n; $i++) {
                $c       = $todayCandles[$i];
                $absIdx  = $todayStartIdx + $i;
                $absPrev = $absIdx - 1;

                $currOi    = (float)($c->oi    ?? 0);
                $currClose = (float)($c->close  ?? 0);
                $currOiMa  = $oiMa[$absIdx]    ?? null;
                $currClMa  = $closeMa[$absIdx] ?? null;

                // ── OI Signal ──────────────────────────────────────────
                $oiSignal = 'NEUTRAL';
                $oiCross  = false;
                if ($currOiMa !== null) {
                    $prevOi   = $absPrev >= 0 ? (float)($allCandles[$absPrev]->oi ?? 0) : $currOi;
                    $prevOiMa = $absPrev >= 0 ? ($oiMa[$absPrev] ?? null) : null;

                    if ($prevOiMa !== null) {
                        if ($prevOi <= $prevOiMa && $currOi > $currOiMa) {
                            $oiSignal = 'BEARISH'; $oiCross = true; $crossCount++;
                        } elseif ($prevOi >= $prevOiMa && $currOi < $currOiMa) {
                            $oiSignal = 'BULLISH'; $oiCross = true; $crossCount++;
                        } else {
                            $oiSignal = ($currOi > $currOiMa) ? 'BEARISH' : 'BULLISH';
                        }
                    } else {
                        $oiSignal = ($currOi > $currOiMa) ? 'BEARISH' : 'BULLISH';
                    }
                }

                // ── Price Signal ───────────────────────────────────────
                $priceSignal = 'NEUTRAL';
                $priceCross  = false;
                if ($currClMa !== null) {
                    $prevClose = $absPrev >= 0 ? (float)($allCandles[$absPrev]->close ?? 0) : $currClose;
                    $prevClMa  = $absPrev >= 0 ? ($closeMa[$absPrev] ?? null) : null;

                    if ($prevClMa !== null) {
                        if ($prevClose <= $prevClMa && $currClose > $currClMa) {
                            $priceSignal = 'BULLISH'; $priceCross = true; $crossCount++;
                        } elseif ($prevClose >= $prevClMa && $currClose < $currClMa) {
                            $priceSignal = 'BEARISH'; $priceCross = true; $crossCount++;
                        } else {
                            $priceSignal = ($currClose > $currClMa) ? 'BULLISH' : 'BEARISH';
                        }
                    } else {
                        $priceSignal = ($currClose > $currClMa) ? 'BULLISH' : 'BEARISH';
                    }
                }

                $rows[] = [
                    'time'            => substr($c->candle_time ?? '', 0, 5),
                    'open'            => $c->open,
                    'high'            => $c->high,
                    'low'             => $c->low,
                    'close'           => $c->close,
                    'oi'              => $currOi,
                    'oi_ma'           => $currOiMa !== null ? round($currOiMa, 0) : null,
                    'close_ma'        => $currClMa  !== null ? round($currClMa,  2) : null,
                    'oi_signal'       => $oiSignal,
                    'oi_cross'        => $oiCross,
                    'price_signal'    => $priceSignal,
                    'price_cross'     => $priceCross,
                    'volume'          => $c->volume,
                    'strike_position' => $c->strike_position,
                ];
            }

            $result[] = [
                'type'            => $type,
                'strike'          => $bestStrike['strike'],
                'trading_symbol'  => $todayCandles[0]->trading_symbol ?? '',
                'rows'            => $rows,
                'crossover_count' => $crossCount,
                'total_candles'   => $n,
            ];
        }

        return $result;
    }

    /**
     * CHART DATA (AJAX)
     *
     * Same fix: whereBetween for history warm-up, return only today's candles.
     */
    public function chartData(Request $request)
    {
        $symbol       = $request->get('symbol');
        $type         = $request->get('type');
        $strike       = $request->get('strike');
        $date         = $request->get('date');
        $maPeriod     = (int) $request->get('ma_period', 50);
        $historyStart = $this->historyStartDate($date, $maPeriod);

        $allCandles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->where('strike', $strike)
            ->whereBetween('trade_date', [$historyStart, $date])
            ->orderBy('trade_date')
            ->orderBy('interval_time')
            ->get([
                DB::raw("DATE(trade_date) as candle_date"),
                DB::raw("TIME(interval_time) as candle_time"),
                'oi', 'volume', 'close',
            ]);

        $oiValues    = $allCandles->pluck('oi')->map(fn($v) => (float)$v)->toArray();
        $closeValues = $allCandles->pluck('close')->map(fn($v) => (float)$v)->toArray();
        $oiMa        = $this->calculateRollingMA($oiValues, $maPeriod);
        $closeMa     = $this->calculateRollingMA($closeValues, $maPeriod);

        // Return only today's candles with warmed-up MA values
        $labels = $oiData = $oiMaData = $closeData = $closeMaData = [];

        foreach ($allCandles as $i => $candle) {
            if ($candle->candle_date !== $date) continue;

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

    /**
     * Rolling Moving Average — production version.
     *
     * Uses a running sum for O(n) performance.
     * Returns NULL for the first (period-1) entries — strict warm-up,
     * no fake progressive averages.
     * From index (period-1) onward: true full-window MA matching broker charts.
     *
     * With historical candles loaded via whereBetween, today's very first
     * candle (09:15) already receives a fully accurate warmed-up MA value.
     */
    private function calculateRollingMA(array $values, int $period): array
    {
        $ma = [];
        $n  = count($values);

        if ($period <= 0) {
            return array_fill(0, $n, null);
        }

        $sum = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $sum += $values[$i];

            if ($i >= $period) {
                $sum -= $values[$i - $period];
            }

            if ($i >= $period - 1) {
                $ma[] = $sum / $period;   // TRUE full-window MA
            } else {
                $ma[] = null;             // Strict warm-up — no fake value
            }
        }

        return $ma;
    }
}