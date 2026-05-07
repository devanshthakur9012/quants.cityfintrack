<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use App\Models\SignalPrediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * EodSignalController  v9.1
 *
 * FIX v9 → v9.1
 * ─────────────────────────────────────────────────────────────────────
 * [FIX-1] Column name: strike_price → strike
 *   The OptionOhlcData model / DB table uses the column `strike`,
 *   NOT `strike_price`. All queries and property accesses corrected:
 *
 *   analyseSymbol()         →  ->get([..., 'strike', ...])
 *   buildOrderBlock()       →  (float)$r->strike
 *   fetchStrikeDataFromDb() →  ->whereIn('strike', ...)
 *                              ->get(['strike', ...])
 *                              $r->strike
 *
 * Everything else is identical to v9.
 * ─────────────────────────────────────────────────────────────────────
 */
class EodSignalController extends Controller
{
    private const PCR_BULL        = 1.10;
    private const PCR_BEAR        = 0.80;
    private const MIN_OI          = 1000;
    private const MIN_OI_MAP      = [
        'NIFTY'      => 5000,
        'BANKNIFTY'  => 3000,
        'FINNIFTY'   => 2000,
        'MIDCPNIFTY' => 1500,
    ];
    private const LAST_HOUR       = '13:30';
    private const W_PCR           = 35;
    private const W_OI            = 35;
    private const W_PRICE         = 30;
    private const P_STRONG        = 0.80;
    private const P_MODERATE      = 0.35;
    private const P_WEAK          = 0.10;
    private const GAP_BIG         = 1.5;
    private const GAP_SMALL       = 0.8;
    private const GAP_OPP_BIG     = 1.0;
    private const GAP_OPP_SMALL   = 0.5;
    private const LAST_CANDLE     = '14:45';
    private const CLOSING_START   = '14:45';
    private const CLOSING_END     = '15:00';
    private const LOW_MOVE_THRESHOLD = 0.25;
    private const STRIKE_INTERVAL_MAP = [
        'NIFTY'      => 50,
        'BANKNIFTY'  => 100,
        'FINNIFTY'   => 50,
        'MIDCPNIFTY' => 25,
    ];
    private const VOLUME_CONVICTION_RATIO = 1.5;
    private const PREMIUM_MIN_TRADEABLE = 5;
    private const PREMIUM_IDEAL_LOW     = 30;
    private const PREMIUM_IDEAL_HIGH    = 250;
    private const PREMIUM_MAX_TRADEABLE = 400;

    // =========================================================================
    public static function getLogicDescription(): array
    {
        return [
            'title'   => 'EOD Signal Engine v9.1',
            'version' => 'v9.1',
            'weights' => ['pcr' => self::W_PCR, 'oi' => self::W_OI, 'price' => self::W_PRICE],
            'steps'   => [
                ['name' => '① PCR Score (35%) — context-aware',       'detail' => 'EOD PCR modulated by price direction. High PCR on DOWN day = reversal signal (+20). High PCR on UP day = hedging, not conviction (−10). Bonuses for intraday trend direction and vs prior day.'],
                ['name' => '② OI Change Score (35%) — microstructure', 'detail' => 'CE OI↑ = call writing = BEARISH. PE OI↑ = put writing = BULLISH. Writing into price strength = 1.2× conviction weight.'],
                ['name' => '③ Price Momentum Score (30%) — ATR-adaptive','detail' => 'STRONG/MODERATE/WEAK set by intraday ATR × multiplier. Last 90-min direction + candle consistency.'],
                ['name' => '④ Continuation Score',                     'detail' => 'Strong trend + last-hour confirmation = high next-day continuation.'],
                ['name' => '⑤ Strike Selection (ATM ± 1, highest vol)','detail' => 'Signal BUY_CE → CE strikes ATM-1/ATM/ATM+1. Pick highest 14:45 volume.'],
                ['name' => '⑥ Option Entry Price',                     'detail' => 'avg(14:45 close, 15:00 close) of the selected strike.'],
                ['name' => '⑦ Entry Protocol (next day 9:45)',         'detail' => 'Two-candle confirmation: 9:15 AND 9:30 must close in signal direction. Enter at 9:45.'],
            ],
        ];
    }

    // =========================================================================
    public function index()
    {
        $pageTitle = 'EOD Signal — Daily Trade Picker';
        $logic     = self::getLogicDescription();
        return view($this->activeTemplate . 'user.eod-signal.index', compact('pageTitle', 'logic'));
    }

    // =========================================================================
    public function getSignals(Request $request)
    {
        try {
            $date   = Carbon::parse($request->get('date', Carbon::today()->toDateString()))->toDateString();
            $symbol = strtoupper(trim($request->get('symbol', 'ALL')));
            $save   = (bool) $request->get('save', false);

            $now          = Carbon::now();
            $isToday      = ($date === Carbon::today()->toDateString());
            $marketClosed = !$isToday || ($now->format('H:i') >= '15:05');

            $allDates = $this->getAllTradingDates();
            $dateIdx  = array_search($date, $allDates);
            $prevDate = ($dateIdx !== false && $dateIdx > 0) ? $allDates[$dateIdx - 1] : null;
            $nextDate = ($dateIdx !== false && isset($allDates[$dateIdx + 1])) ? $allDates[$dateIdx + 1] : null;

            $availableSymbols = OptionOhlcData::whereDate('trade_date', $date)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return response()->json([
                    'success'           => true,
                    'data'              => [],
                    'date'              => $date,
                    'next_trade_date'   => $nextDate,
                    'is_today'          => $isToday,
                    'market_closed'     => $marketClosed,
                    'message'           => 'No data for ' . $date,
                    'available_symbols' => [],
                ]);
            }

            $symbols = ($symbol === 'ALL') ? $availableSymbols : [$symbol];
            $results = [];
            $incomplete = [];

            foreach ($symbols as $sym) {
                $result = $this->analyseSymbol($sym, $date, $prevDate, $nextDate, $marketClosed);
                if (!$result) continue;

                if (!empty($result['data_incomplete'])) {
                    $incomplete[] = $sym . ': ' . ($result['incomplete_reason'] ?? 'data missing');
                    $results[]    = $result;
                    continue;
                }

                if ($marketClosed && $save) {
                    $this->savePrediction($result);
                }
                $results[] = $result;
            }

            if (!empty($incomplete)) {
                Log::warning('EodSignal incomplete data: ' . implode(', ', $incomplete));
            }

            usort($results, function ($a, $b) {
                if (!empty($a['data_incomplete']) && empty($b['data_incomplete']))  return 1;
                if (empty($a['data_incomplete'])  && !empty($b['data_incomplete'])) return -1;
                $order = ['STRONG' => 0, 'MODERATE' => 1, 'WEAK' => 2, 'SPECULATIVE' => 3, 'NOT_READY' => 4];
                $aS = $order[$a['signal']['strength'] ?? 'SPECULATIVE'] ?? 3;
                $bS = $order[$b['signal']['strength'] ?? 'SPECULATIVE'] ?? 3;
                if ($aS !== $bS) return $aS <=> $bS;
                return ($b['signal']['confidence'] ?? 0) <=> ($a['signal']['confidence'] ?? 0);
            });

            return response()->json([
                'success'            => true,
                'data'               => $results,
                'date'               => $date,
                'next_trade_date'    => $nextDate,
                'prev_date'          => $prevDate,
                'is_today'           => $isToday,
                'market_closed'      => $marketClosed,
                'current_time'       => $now->format('H:i'),
                'available_symbols'  => $availableSymbols,
                'incomplete_symbols' => $incomplete,
                'message'            => count($results) . ' symbol(s) analysed'
                    . (count($incomplete) ? ', ' . count($incomplete) . ' incomplete' : ''),
            ]);

        } catch (\Exception $e) {
            Log::error('EodSignal: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================================
    // ANALYSE ONE SYMBOL
    // =========================================================================
    public function analyseSymbol(
        string  $sym,
        string  $date,
        ?string $prevDate,
        ?string $nextDate,
        bool    $marketClosed
    ): ?array {

        $expiry = $this->getNearestExpiry($sym, $date);
        if (!$expiry) return null;

        // [FIX-1] Use 'strike' — NOT 'strike_price' (model column is `strike`)
        $rows = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'instrument_type', 'strike',   // ← FIXED
                   'oi', 'volume', 'future_price', 'open', 'high', 'low', 'close']);

        if ($rows->isEmpty()) return null;

        $futRows = OptionOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close', 'future_price']);

        $prevOi = $prevDate
            ? OptionOhlcData::where('base_symbol', $sym)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $prevDate)
                ->where('is_missing', 0)
                ->whereTime('interval_time', '>=', '14:30:00')
                ->get(['instrument_type', 'oi'])
            : collect();

        $prevClose = $prevDate
            ? (float) OptionOhlcData::where('base_symbol', $sym)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $prevDate)
                ->where('is_missing', 0)
                ->orderByDesc('interval_time')
                ->value('close')
            : 0.0;

        $allTimes = $rows->pluck('interval_time')
            ->map(fn($t) => Carbon::parse($t)->format('H:i'))
            ->unique()->sort()->values()->toArray();

        if (count($allTimes) < 10) return null;

        if (!in_array(self::LAST_CANDLE, $allTimes) && $marketClosed) {
            return [
                'symbol'            => $sym,
                'date'              => $date,
                'expiry'            => $expiry,
                'next_trade_date'   => $nextDate,
                'data_incomplete'   => true,
                'incomplete_reason' => self::LAST_CANDLE . ' candle missing — retry after 15:05',
                'market_closed'     => $marketClosed,
                'signal' => [
                    'action'     => 'WAIT',
                    'label'      => 'Data incomplete — retry after 15:05',
                    'confidence' => 0,
                    'strength'   => 'NOT_READY',
                    'reasons'    => ['Missing ' . self::LAST_CANDLE . ' candle'],
                ],
            ];
        }

        $futMap     = $this->buildFutMap($futRows);
        $ceOiByTime = $this->buildOiByTimeForType($rows, 'CE');
        $peOiByTime = $this->buildOiByTimeForType($rows, 'PE');
        $oiByTime   = $this->buildOiByTime($rows);
        $minOi      = self::MIN_OI_MAP[$sym] ?? self::MIN_OI;

        $pcrSeries = [];
        foreach ($allTimes as $tk) {
            $ce = $oiByTime[$tk]['CE'] ?? 0;
            $pe = $oiByTime[$tk]['PE'] ?? 0;
            if (($ce + $pe) >= $minOi && $ce > 0) {
                $pcrSeries[$tk] = round($pe / $ce, 4);
            }
        }

        $prevCe  = $prevOi->where('instrument_type', 'CE')->sum('oi');
        $prevPe  = $prevOi->where('instrument_type', 'PE')->sum('oi');
        $prevPcr = $prevCe > 0 ? round($prevPe / $prevCe, 4) : null;

        $futPrices = [];
        $lhPrices  = [];
        foreach ($allTimes as $tk) {
            $fc = $futMap[$tk] ?? null;
            if (!$fc) continue;
            $p = (float)($fc['future_price'] ?: $fc['close']);
            if ($p > 0) {
                $futPrices[$tk] = $p;
                if ($tk >= self::LAST_HOUR) $lhPrices[$tk] = $p;
            }
        }
        if (empty($futPrices)) return null;

        $firstPrice = reset($futPrices);
        $lastPrice  = end($futPrices);
        $dayPct     = $firstPrice > 0 ? (($lastPrice - $firstPrice) / $firstPrice) * 100 : 0;

        $todayOpen = (float)($futMap['09:15']['open'] ?? $futMap['09:15']['future_price'] ?? $firstPrice);
        $gapPct    = ($prevClose > 0)
            ? round((($todayOpen - $prevClose) / $prevClose) * 100, 3)
            : 0.0;

        $strikeInterval = $this->getStrikeInterval($sym, $rows);
        $futEodClose    = (float)($futMap[self::LAST_CANDLE]['future_price']
                                  ?? $futMap[self::LAST_CANDLE]['close']
                                  ?? $lastPrice);
        $atmStrike = $strikeInterval > 0
            ? round($futEodClose / $strikeInterval) * $strikeInterval
            : 0;

        $atrThresholds = $this->computeAtrThresholds($futMap, $allTimes);
        $pcrInd        = $this->indicatorPcr($pcrSeries, $prevPcr, $dayPct);
        $oiInd         = $this->indicatorOiChange($ceOiByTime, $peOiByTime, $allTimes, $futMap, $sym);
        $priceInd      = $this->indicatorPrice(array_values($futPrices), $lhPrices, $firstPrice, $lastPrice, $atrThresholds);
        $signal        = $this->synthesize($pcrInd, $oiInd, $priceInd, $marketClosed, $gapPct);

        $orderBlock = null;
        if ($marketClosed && !empty($signal['action']) && in_array($signal['action'], ['BUY_CE', 'BUY_PE'])) {
            $orderBlock = $this->buildOrderBlock(
                $sym, $date, $expiry, $signal['action'],
                $atmStrike, $strikeInterval, $rows, $signal
            );
            if (!empty($orderBlock['volume_conviction'])) {
                $signal['confidence'] = min(95, $signal['confidence'] + 5);
                $signal['reasons'][]  = "High volume conviction on selected strike (+5%)";
            }
        }

        return [
            'symbol'           => $sym,
            'date'             => $date,
            'expiry'           => $expiry,
            'next_trade_date'  => $nextDate,
            'atm_strike'       => $atmStrike,
            'strike_interval'  => $strikeInterval,
            'market_closed'    => $marketClosed,
            'data_incomplete'  => false,
            'day' => [
                'open'       => round($firstPrice, 2),
                'close'      => round($lastPrice, 2),
                'today_open' => round($todayOpen, 2),
                'change_pct' => round($dayPct, 2),
                'gap_pct'    => $gapPct,
                'pcr_eod'    => !empty($pcrSeries) ? end($pcrSeries) : null,
                'pcr_prev'   => $prevPcr,
                'fut_eod'    => round($futEodClose, 2),
            ],
            'indicators' => [
                'pcr'   => $pcrInd,
                'oi'    => $oiInd,
                'price' => $priceInd,
            ],
            'signal' => $signal,
            'order'  => $orderBlock,
        ];
    }

    // =========================================================================
    // ORDER BLOCK — strike selection + entry price
    // =========================================================================
    private function buildOrderBlock(
        string  $sym,
        string  $date,
        string  $expiry,
        string  $action,
        float   $atmStrike,
        float   $strikeInterval,
        $rows,
        array   &$signal
    ): ?array {

        if ($atmStrike <= 0 || $strikeInterval <= 0) return null;

        $optionType = $action === 'BUY_CE' ? 'CE' : 'PE';

        $strikes = [
            $atmStrike - $strikeInterval,
            $atmStrike,
            $atmStrike + $strikeInterval,
        ];

        $closingTimes = [self::CLOSING_START, self::CLOSING_END];
        $strikeData   = [];

        foreach ($strikes as $strike) {
            $strikeRows = $rows->filter(function ($r) use ($strike, $optionType, $closingTimes) {
                $tk = Carbon::parse($r->interval_time)->format('H:i');
                // [FIX-1] Use ->strike (model property), not ->strike_price
                return (float)$r->strike === (float)$strike
                    && $r->instrument_type === $optionType
                    && in_array($tk, $closingTimes);
            });

            if ($strikeRows->isEmpty()) continue;

            $lastCandle = $strikeRows->first(function ($r) {
                return Carbon::parse($r->interval_time)->format('H:i') === self::CLOSING_START;
            });

            $volume = $lastCandle ? (int)$lastCandle->volume : 0;
            $oi     = $lastCandle ? (int)$lastCandle->oi     : 0;

            $closePrices = $strikeRows->map(fn($r) => (float)$r->close)->filter(fn($p) => $p > 0)->values();
            $entryPrice  = $closePrices->isNotEmpty()
                ? round($closePrices->average(), 2)
                : 0.0;

            if ($entryPrice <= 0) continue;

            $diffSteps = (int)round(($strike - $atmStrike) / $strikeInterval);
            $position  = match ($diffSteps) {
                -1 => 'ATM-1',
                0  => 'ATM',
                1  => 'ATM+1',
                default => 'UNKNOWN',
            };

            $strikeData[$strike] = [
                'strike'      => $strike,
                'position'    => $position,
                'type'        => $optionType,
                'volume'      => $volume,
                'oi'          => $oi,
                'entry_price' => $entryPrice,
            ];
        }

        if (empty($strikeData)) {
            $strikeData = $this->fetchStrikeDataFromDb(
                $sym, $date, $expiry, $optionType, $strikes, $strikeInterval, $atmStrike
            );
        }

        if (empty($strikeData)) return null;

        // Premium / IV filter
        $validStrikeData  = [];
        $skippedByPremium = [];
        foreach ($strikeData as $strike => $sd) {
            $ep = $sd['entry_price'];
            if ($ep < self::PREMIUM_MIN_TRADEABLE) {
                $skippedByPremium[$strike] = $sd + ['premium_status' => 'JUNK',          'skip_reason' => "₹{$ep} < ₹5 min — illiquid"];
            } elseif ($ep > self::PREMIUM_MAX_TRADEABLE) {
                $skippedByPremium[$strike] = $sd + ['premium_status' => 'TOO_EXPENSIVE', 'skip_reason' => "₹{$ep} > ₹400 — theta risk too high"];
            } else {
                $status = ($ep >= self::PREMIUM_IDEAL_LOW && $ep <= self::PREMIUM_IDEAL_HIGH)
                    ? 'IDEAL'
                    : ($ep < self::PREMIUM_IDEAL_LOW ? 'SPECULATIVE' : 'EXPENSIVE');
                $validStrikeData[$strike] = $sd + ['premium_status' => $status, 'skip_reason' => null];
            }
        }

        if (empty($validStrikeData)) {
            foreach ($strikeData as $strike => $sd) {
                $validStrikeData[$strike] = $sd + ['premium_status' => 'FORCED_FALLBACK', 'skip_reason' => 'All strikes outside ideal premium range'];
            }
        }

        $allCandidates = $validStrikeData + $skippedByPremium;

        uasort($validStrikeData, fn($a, $b) => $b['volume'] <=> $a['volume']);
        $volumes   = array_column($validStrikeData, 'volume');
        $best      = reset($validStrikeData);
        $secondVol = $volumes[1] ?? 0;

        if (($best['premium_status'] ?? '') === 'EXPENSIVE') {
            $signal['confidence'] = max(20, ($signal['confidence'] ?? 50) - 5);
            $signal['reasons'][]  = "Selected strike premium ₹{$best['entry_price']} is expensive — theta risk (−5%)";
        }
        if (($best['premium_status'] ?? '') === 'SPECULATIVE') {
            $signal['reasons'][] = "Selected strike premium ₹{$best['entry_price']} is low — wide spread risk";
        }

        $volumeConviction = ($secondVol > 0 && $best['volume'] >= $secondVol * self::VOLUME_CONVICTION_RATIO)
            || ($secondVol === 0 && $best['volume'] > 0);

        $strikeName = $best['position'] . ' ' . $optionType
            . ' (' . number_format($best['strike']) . ')';

        return [
            'symbol'            => $sym,
            'action'            => $action,
            'option_type'       => $optionType,
            'strike'            => $best['strike'],
            'strike_position'   => $best['position'],
            'expiry'            => $expiry,
            'entry_price'       => $best['entry_price'],
            'entry_price_note'  => 'avg of ' . self::CLOSING_START . ' & ' . self::CLOSING_END . ' candle close',
            'premium_status'    => $best['premium_status'] ?? 'UNKNOWN',
            'volume'            => $best['volume'],
            'oi'                => $best['oi'],
            'volume_conviction' => $volumeConviction,
            'candidates'        => array_values($allCandidates),
            'trade_date'        => null,
            'signal_strength'   => $signal['strength']   ?? 'SPECULATIVE',
            'confidence'        => $signal['confidence'] ?? 0,
            'entry_note'        => "Next day: Buy {$strikeName} at open after 9:45 confirmation",
        ];
    }

    /**
     * Fallback DB query for strike closing data.
     * [FIX-1] Uses 'strike' column (NOT 'strike_price').
     */
    private function fetchStrikeDataFromDb(
        string $sym,
        string $date,
        string $expiry,
        string $optionType,
        array  $strikes,
        float  $strikeInterval,
        float  $atmStrike
    ): array {

        // [FIX-1] Column name is `strike`, not `strike_price`
        $dbRows = OptionOhlcData::where('base_symbol', $sym)
            ->where('instrument_type', $optionType)
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->whereIn('strike', $strikes)                               // ← FIXED
            ->whereIn(DB::raw("TIME(interval_time)"), ['14:45:00', '15:00:00'])
            ->where('is_missing', 0)
            ->get(['strike', 'instrument_type', 'interval_time', 'volume', 'oi', 'close']); // ← FIXED

        if ($dbRows->isEmpty()) return [];

        $map = [];
        foreach ($dbRows as $r) {
            $s  = (float)$r->strike;                                    // ← FIXED
            $tk = Carbon::parse($r->interval_time)->format('H:i');
            if (!isset($map[$s])) {
                $map[$s] = ['strike' => $s, 'type' => $optionType, 'prices' => [], 'volume' => 0, 'oi' => 0];
            }
            if ($tk === self::CLOSING_START) {
                $map[$s]['volume'] = (int)$r->volume;
                $map[$s]['oi']     = (int)$r->oi;
            }
            if ((float)$r->close > 0) {
                $map[$s]['prices'][] = (float)$r->close;
            }
        }

        $result = [];
        foreach ($map as $s => $d) {
            if (empty($d['prices'])) continue;
            $diffSteps = (int)round(($s - $atmStrike) / $strikeInterval);
            $position  = match ($diffSteps) {
                -1 => 'ATM-1', 0 => 'ATM', 1 => 'ATM+1', default => 'UNKNOWN',
            };
            $result[$s] = [
                'strike'      => $s,
                'position'    => $position,
                'type'        => $optionType,
                'volume'      => $d['volume'],
                'oi'          => $d['oi'],
                'entry_price' => round(array_sum($d['prices']) / count($d['prices']), 2),
            ];
        }
        return $result;
    }

    // =========================================================================
    // STRIKE INTERVAL — from map or auto-detected from data
    // =========================================================================
    private function getStrikeInterval(string $sym, $rows): float
    {
        if (isset(self::STRIKE_INTERVAL_MAP[$sym])) {
            return (float)self::STRIKE_INTERVAL_MAP[$sym];
        }

        $interval = $rows->whereNotNull('strike_interval')->first()?->strike_interval ?? null;
        if ($interval && (float)$interval > 0) {
            return (float)$interval;
        }

        // [FIX-1] Use ->strike (not ->strike_price)
        $strikes = $rows->pluck('strike')
            ->map(fn($s) => (float)$s)
            ->filter(fn($s) => $s > 0)
            ->unique()->sort()->values()->toArray();

        if (count($strikes) < 2) return 50;

        $gaps = [];
        for ($i = 1; $i < count($strikes); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0) $gaps[] = $gap;
        }
        if (empty($gaps)) return 50;

        $freq = array_count_values(array_map('intval', $gaps));
        arsort($freq);
        return (float)array_key_first($freq);
    }

    // =========================================================================
    // INDICATOR 1: PCR
    // =========================================================================
    private function indicatorPcr(array $series, ?float $prevPcr, float $dayPct): array
    {
        if (empty($series)) return $this->neutralIndicator('PCR', 'No OI data');

        $vals = array_values(array_filter($series, fn($v) => $v !== null));
        if (empty($vals)) return $this->neutralIndicator('PCR', 'PCR all null');

        $eod  = end($vals);
        $open = $vals[0];
        $n    = count($vals);

        $trend = 'FLAT'; $trendMag = 0;
        if ($n >= 3) {
            $change = $eod - $open; $trendMag = abs($change);
            if ($change > 0.04)      $trend = 'RISING';
            elseif ($change < -0.04) $trend = 'FALLING';
        }

        $lqVals  = array_slice($vals, intdiv($n * 3, 4));
        $lhTrend = 'FLAT';
        if (count($lqVals) >= 2) {
            $lhC = $lqVals[count($lqVals) - 1] - $lqVals[0];
            if ($lhC > 0.02)      $lhTrend = 'RISING';
            elseif ($lhC < -0.02) $lhTrend = 'FALLING';
        }

        $prevScore = 0; $vsPrev = 'UNCHANGED';
        if ($prevPcr !== null) {
            $diff = $eod - $prevPcr;
            if ($diff > 0.08)      { $vsPrev = 'HIGHER';          $prevScore =  15; }
            elseif ($diff > 0.03)  { $vsPrev = 'SLIGHTLY_HIGHER'; $prevScore =   8; }
            elseif ($diff < -0.08) { $vsPrev = 'LOWER';           $prevScore = -15; }
            elseif ($diff < -0.03) { $vsPrev = 'SLIGHTLY_LOWER';  $prevScore =  -8; }
        }

        $baseScore = 0; $reasons = [];
        if ($eod >= self::PCR_BULL) {
            $baseScore = min(60, (int)(($eod - self::PCR_BULL) / 0.60 * 60));
            $reasons[] = "PCR {$eod} ≥ " . self::PCR_BULL . " — put writing dominant (BULLISH base)";
        } elseif ($eod <= self::PCR_BEAR) {
            $baseScore = -min(60, (int)((self::PCR_BEAR - $eod) / 0.60 * 60));
            $reasons[] = "PCR {$eod} ≤ " . self::PCR_BEAR . " — call writing dominant (BEARISH base)";
        } else {
            $mid       = (self::PCR_BULL + self::PCR_BEAR) / 2;
            $baseScore = (int)(($eod - $mid) / ($mid - self::PCR_BEAR) * 20);
            $reasons[] = "PCR {$eod} neutral — " . ($baseScore >= 0 ? 'slight bull' : 'slight bear');
        }

        $contextScore = 0;
        $priceDir     = $dayPct >= 0.05 ? 'UP' : ($dayPct <= -0.05 ? 'DOWN' : 'FLAT');

        if ($eod >= self::PCR_BULL) {
            if ($priceDir === 'DOWN') { $contextScore =  20; $reasons[] = "PCR bullish + price DOWN → put-writers absorbing dip (+20)"; }
            elseif ($priceDir === 'UP') { $contextScore = -10; $reasons[] = "PCR bullish but price UP → hedge not conviction (−10)"; }
        } elseif ($eod <= self::PCR_BEAR) {
            if ($priceDir === 'UP')       { $contextScore = -20; $reasons[] = "PCR bearish + price UP → call-writers absorbing rally (−20)"; }
            elseif ($priceDir === 'DOWN') { $contextScore =  10; $reasons[] = "PCR bearish but price DOWN → hedge not conviction (+10)"; }
        }

        $trendScore = 0;
        if ($trend === 'RISING')  { $trendScore =  min(20, (int)($trendMag / 0.3 * 20)); $reasons[] = "PCR rising intraday (bullish)"; }
        if ($trend === 'FALLING') { $trendScore = -min(20, (int)($trendMag / 0.3 * 20)); $reasons[] = "PCR falling intraday (bearish)"; }

        $lhScore = 0;
        if ($lhTrend === 'RISING')  { $lhScore =  10; $reasons[] = "Last-hour PCR rising (confirms bullish)"; }
        if ($lhTrend === 'FALLING') { $lhScore = -10; $reasons[] = "Last-hour PCR falling (confirms bearish)"; }

        if ($vsPrev !== 'UNCHANGED') $reasons[] = "vs prev day: {$vsPrev} (prev PCR: {$prevPcr})";

        $total = max(-100, min(100, $baseScore + $contextScore + $trendScore + $lhScore + $prevScore));
        $bias  = $total >= 10 ? 'BULLISH' : ($total <= -10 ? 'BEARISH' : 'NEUTRAL');

        return [
            'name'       => 'PCR', 'bias' => $bias, 'score' => $total, 'strength' => abs($total),
            'pcr_eod'    => $eod, 'pcr_open' => $open, 'pcr_prev' => $prevPcr,
            'trend'      => $trend, 'lh_trend' => $lhTrend, 'vs_prev' => $vsPrev,
            'context'    => $priceDir, 'context_adj' => $contextScore,
            'reasons'    => $reasons,
        ];
    }

    // =========================================================================
    // INDICATOR 2: OI CHANGE — correct options microstructure
    // =========================================================================
    private function indicatorOiChange(
        array  $ceOiByTime,
        array  $peOiByTime,
        array  $allTimes,
        array  $futMap,
        string $sym
    ): array {

        $minOi  = self::MIN_OI_MAP[$sym] ?? self::MIN_OI;
        $ceBull = $ceBear = $peBull = $peBear = 0.0;
        $ceCandles = $peCandles = 0;

        for ($i = 1; $i < count($allTimes); $i++) {
            $tk  = $allTimes[$i];
            $ptk = $allTimes[$i - 1];

            $fcN = $futMap[$tk]  ?? null;
            $fcP = $futMap[$ptk] ?? null;
            if (!$fcN || !$fcP) continue;

            $pN = (float)($fcN['future_price'] ?: $fcN['close']);
            $pP = (float)($fcP['future_price'] ?: $fcP['close']);
            if ($pP <= 0 || $pN <= 0) continue;

            $pPct   = (($pN - $pP) / $pP) * 100;
            $thresh = max(0.03, (0.3 / $pP) * 100);
            $pDir   = abs($pPct) < $thresh ? 'FLAT' : ($pPct > 0 ? 'UP' : 'DOWN');
            if ($pDir === 'FLAT') continue;

            $zw = $tk >= '14:30' ? 1.5 : ($tk < '10:00' ? 0.5 : 1.0);

            $ceN = $ceOiByTime[$tk]  ?? 0;
            $ceP = $ceOiByTime[$ptk] ?? 0;
            if (($ceN + $ceP) >= $minOi && $ceP > 0) {
                $ceDelta = $ceN - $ceP;
                $ceMag   = min(2.0, 1.0 + abs($ceDelta) / max($ceP, 1));
                if ($ceDelta > 0) {
                    $ceBear += $zw * $ceMag * ($pDir === 'UP' ? 1.2 : 1.0);
                    $ceCandles++;
                } else {
                    if ($pDir === 'UP')   { $ceBull += $zw * $ceMag * 1.0; $ceCandles++; }
                    elseif ($pDir === 'DOWN') { $ceBear += $zw * $ceMag * 0.6; $ceCandles++; }
                }
            }

            $peN = $peOiByTime[$tk]  ?? 0;
            $peP = $peOiByTime[$ptk] ?? 0;
            if (($peN + $peP) >= $minOi && $peP > 0) {
                $peDelta = $peN - $peP;
                $peMag   = min(2.0, 1.0 + abs($peDelta) / max($peP, 1));
                if ($peDelta > 0) {
                    $peBull += $zw * $peMag * ($pDir === 'DOWN' ? 1.2 : 1.0);
                    $peCandles++;
                } else {
                    if ($pDir === 'DOWN') { $peBear += $zw * $peMag * 1.0; $peCandles++; }
                    elseif ($pDir === 'UP')  { $peBull += $zw * $peMag * 0.6; $peCandles++; }
                }
            }
        }

        $ceTot = $ceBull + $ceBear;
        $peTot = $peBull + $peBear;
        if ($ceTot <= 0 && $peTot <= 0) {
            return $this->neutralIndicator('OI', 'Insufficient OI changes');
        }

        $ceScore = $ceTot > 0 ? max(-100, min(100, (int)(($ceBull / $ceTot - 0.5) * 200))) : 0;
        $peScore = $peTot > 0 ? max(-100, min(100, (int)(($peBull / $peTot - 0.5) * 200))) : 0;
        $sides   = ($ceTot > 0 ? 1 : 0) + ($peTot > 0 ? 1 : 0);
        $score   = $sides > 0 ? (int)(($ceScore + $peScore) / $sides) : 0;
        $bias    = $score >= 10 ? 'BULLISH' : ($score <= -10 ? 'BEARISH' : 'NEUTRAL');

        $ceBullPct = $ceTot > 0 ? round($ceBull / $ceTot * 100, 1) : 0;
        $peBullPct = $peTot > 0 ? round($peBull / $peTot * 100, 1) : 0;

        $reasons = [
            "CE writers: " . round(100 - $ceBullPct, 1) . "% bearish signal (call writing pressure)",
            "PE writers: {$peBullPct}% bullish signal (put writing support)",
        ];
        if ($bias === 'BULLISH') $reasons[] = "Net OI: put writers absorbing — BULLISH";
        if ($bias === 'BEARISH') $reasons[] = "Net OI: call writers absorbing — BEARISH";

        return [
            'name'         => 'OI',
            'bias'         => $bias,
            'score'        => $score,
            'strength'     => abs($score),
            'ce_bull_pct'  => $ceBullPct,
            'ce_bear_pct'  => round(100 - $ceBullPct, 1),
            'pe_bull_pct'  => $peBullPct,
            'pe_bear_pct'  => round(100 - $peBullPct, 1),
            'ce_score'     => $ceScore,
            'pe_score'     => $peScore,
            'ce_candles'   => $ceCandles,
            'pe_candles'   => $peCandles,
            'bull_pct'     => round(($ceBullPct + $peBullPct) / 2, 1),
            'bear_pct'     => round((100 - $ceBullPct + 100 - $peBullPct) / 2, 1),
            'long_buildup' => round($peBull, 2),
            'short_buildup'=> round($ceBear, 2),
            'short_cover'  => round($ceBull, 2),
            'long_unwind'  => round($peBear, 2),
            'candles'      => max($ceCandles, $peCandles),
            'reasons'      => $reasons,
        ];
    }

    // =========================================================================
    // INDICATOR 3: PRICE MOMENTUM
    // =========================================================================
    private function indicatorPrice(
        array $prices,
        array $lhPrices,
        float $first,
        float $last,
        array $atrThresholds
    ): array {

        if ($first <= 0 || empty($prices)) return $this->neutralIndicator('PRICE', 'No price data');

        $dayPct = (($last - $first) / $first) * 100;
        $dir    = $dayPct >= 0 ? 'UP' : 'DOWN';

        $strongThr   = $atrThresholds['strong']   ?? self::P_STRONG;
        $moderateThr = $atrThresholds['moderate'] ?? self::P_MODERATE;
        $weakThr     = $atrThresholds['weak']     ?? self::P_WEAK;

        $str = 'FLAT';
        if (abs($dayPct) >= $strongThr)       $str = 'STRONG';
        elseif (abs($dayPct) >= $moderateThr) $str = 'MODERATE';
        elseif (abs($dayPct) >= $weakThr)     $str = 'WEAK';

        $lhDir = 'FLAT'; $lhPct = 0;
        if (!empty($lhPrices) && count($lhPrices) >= 2) {
            $lhF = reset($lhPrices); $lhL = end($lhPrices);
            if ($lhF > 0) {
                $lhPct = (($lhL - $lhF) / $lhF) * 100;
                $lhDir = abs($lhPct) >= 0.08 ? ($lhPct > 0 ? 'UP' : 'DOWN') : 'FLAT';
            }
        }

        $n = count($prices); $align = 0;
        for ($i = 1; $i < $n; $i++) {
            $mv = $prices[$i] - $prices[$i - 1];
            if ($dir === 'UP'   && $mv > 0) $align++;
            if ($dir === 'DOWN' && $mv < 0) $align++;
        }
        $consPct = $n > 1 ? ($align / ($n - 1)) * 100 : 50;

        $baseScore = match ($str) { 'STRONG' => 50, 'MODERATE' => 30, 'WEAK' => 15, default => 5 };
        $baseScore = $dir === 'DOWN' ? -$baseScore : $baseScore;

        $score = $baseScore; $reasons = [];
        $reasons[] = "Day: " . ($dayPct >= 0 ? '+' : '') . round($dayPct, 2)
            . "% ({$str}) [ATR: S{$strongThr}% M{$moderateThr}% W{$weakThr}%]";

        if ($lhDir !== 'FLAT') {
            if ($lhDir === $dir) {
                $score += ($lhDir === 'UP' ? 20 : -20);
                $reasons[] = "Last 90min confirms (" . ($lhDir === 'UP' ? '+' : '') . round($lhPct, 2) . "%)";
            } else {
                $score += ($lhDir === 'UP' ? 10 : -10);
                $reasons[] = "Last 90min reversal " . ($lhDir === 'UP' ? '+' : '') . round($lhPct, 2) . "%";
            }
        }

        if ($consPct >= 65)     { $score += ($dir === 'UP' ? 10 : -10); $reasons[] = "Consistent: " . round($consPct) . "% candles aligned"; }
        elseif ($consPct <= 35) { $score  = (int)($score * 0.8);        $reasons[] = "Choppy: " . round($consPct) . "% aligned"; }

        $score = max(-100, min(100, $score));
        $bias  = $score >= 10 ? 'BULLISH' : ($score <= -10 ? 'BEARISH' : 'NEUTRAL');

        return [
            'name'       => 'PRICE', 'bias' => $bias, 'score' => $score, 'strength' => abs($score),
            'day_pct'    => round($dayPct, 2), 'day_dir' => $dir, 'day_str' => $str,
            'lh_pct'     => round($lhPct, 2),  'lh_dir' => $lhDir, 'cons_pct' => round($consPct, 1),
            'atr_based'  => $atrThresholds['atr_based'] ?? false,
            'thresholds' => ['strong' => $strongThr, 'moderate' => $moderateThr, 'weak' => $weakThr],
            'reasons'    => $reasons,
        ];
    }

    // =========================================================================
    // SYNTHESIZE
    // =========================================================================
    private function synthesize(
        array $pcr,
        array $oi,
        array $price,
        bool  $marketClosed,
        float $gapPct = 0.0
    ): array {

        if (!$marketClosed) {
            return [
                'action' => 'WAIT', 'label' => 'Market open — signal ready after 15:05',
                'confidence' => 0, 'strength' => 'NOT_READY',
                'reasons' => ['Signal finalises after 15:05'],
            ];
        }

        $pScore = $pcr['score']   ?? 0;
        $oScore = $oi['score']    ?? 0;
        $rScore = $price['score'] ?? 0;
        $pBias  = $pcr['bias']    ?? 'NEUTRAL';
        $oBias  = $oi['bias']     ?? 'NEUTRAL';
        $rBias  = $price['bias']  ?? 'NEUTRAL';

        $composite = round(($pScore * self::W_PCR + $oScore * self::W_OI + $rScore * self::W_PRICE) / 100);
        if ($composite === 0) $composite = $pScore >= 0 ? 1 : -1;

        $action     = $composite > 0 ? 'BUY_CE' : 'BUY_PE';
        $confidence = (int)round(min(95, 28 + abs($composite) * 1.1));

        $aligned = 0;
        if ($action === 'BUY_CE') {
            if ($pBias === 'BULLISH') $aligned++;
            if ($oBias === 'BULLISH') $aligned++;
            if ($rBias === 'BULLISH') $aligned++;
        } else {
            if ($pBias === 'BEARISH') $aligned++;
            if ($oBias === 'BEARISH') $aligned++;
            if ($rBias === 'BEARISH') $aligned++;
        }
        if ($aligned === 3) $confidence = min(95, $confidence + 10);
        if ($aligned === 0) $confidence = max(25, $confidence - 15);

        $continuation = 0; $contReasons = [];
        $priceDir   = $price['day_dir']  ?? 'UP';
        $priceLhDir = $price['lh_dir']   ?? 'FLAT';
        $priceStr   = $price['day_str']  ?? 'FLAT';
        $dayPctAbs  = abs($price['day_pct'] ?? 0);
        $consPct    = $price['cons_pct'] ?? 50;
        $pcrLhTrend = $pcr['lh_trend']  ?? 'FLAT';

        if ($priceStr === 'STRONG'   && $priceLhDir === $priceDir) { $continuation += 15; $contReasons[] = "Strong trend + last 90min confirms (+15)"; }
        if ($priceStr === 'MODERATE' && $priceLhDir === $priceDir) { $continuation += 10; $contReasons[] = "Moderate trend + last 90min confirms (+10)"; }
        if (($action === 'BUY_CE' && $oBias === 'BULLISH') || ($action === 'BUY_PE' && $oBias === 'BEARISH')) { $continuation += 10; $contReasons[] = "OI confirms direction (+10)"; }
        if (($action === 'BUY_CE' && $pBias === 'BULLISH') || ($action === 'BUY_PE' && $pBias === 'BEARISH')) { $continuation += 10; $contReasons[] = "PCR confirms direction (+10)"; }
        if (($action === 'BUY_CE' && $pcrLhTrend === 'RISING') || ($action === 'BUY_PE' && $pcrLhTrend === 'FALLING')) { $continuation += 8; $contReasons[] = "Last-hour PCR committed (+8)"; }
        if ($priceLhDir !== 'FLAT' && $priceLhDir !== $priceDir) { $continuation -= 10; $contReasons[] = "Last 90min reversed → trend fading (−10)"; }
        if ($dayPctAbs < self::LOW_MOVE_THRESHOLD)               { $continuation -= 8;  $contReasons[] = "Low-move day → theta decay risk (−8)"; }
        if ($consPct < 40)                                        { $continuation -= 12; $contReasons[] = "Choppy day → no conviction (−12)"; }

        $continuation = max(-25, min(25, $continuation));
        $confidence   = max(20, min(95, $confidence + $continuation));

        $gapNote = ''; $gapAdj = 0; $gapAbs = abs($gapPct);
        $sameDir = ($action === 'BUY_CE' && $gapPct > 0) || ($action === 'BUY_PE' && $gapPct < 0);
        $oppDir  = ($action === 'BUY_CE' && $gapPct < 0) || ($action === 'BUY_PE' && $gapPct > 0);

        if      ($sameDir && $gapAbs >= self::GAP_BIG)       { $gapAdj = -15; $gapNote = "Gap " . round($gapPct, 2) . "% same-dir (large) → exhaustion risk (−15%)"; }
        elseif  ($sameDir && $gapAbs >= self::GAP_SMALL)     { $gapAdj =  -8; $gapNote = "Gap " . round($gapPct, 2) . "% same-dir → partial exhaustion (−8%)"; }
        elseif  ($oppDir  && $gapAbs >= self::GAP_OPP_BIG)   { $gapAdj = -20; $gapNote = "⚠ Gap " . round($gapPct, 2) . "% AGAINST signal → counter-move risk (−20%)"; }
        elseif  ($oppDir  && $gapAbs >= self::GAP_OPP_SMALL) { $gapAdj = -10; $gapNote = "Gap " . round($gapPct, 2) . "% against signal (−10%)"; }
        elseif  ($gapAbs < 0.2)                               { $gapNote = "Flat open expected — clean signal"; }

        $confidence = max(20, min(95, $confidence + $gapAdj));
        $strength   = $confidence >= 75 ? 'STRONG' : ($confidence >= 55 ? 'MODERATE' : ($confidence >= 40 ? 'WEAK' : 'SPECULATIVE'));

        $gapTol = $confidence >= 75 ? 0.20 : 0.15;
        $entryRules = [
            'step1' => $action === 'BUY_CE' ? "Check gap at open: skip if gap DOWN > {$gapTol}% (against signal)"  : "Check gap at open: skip if gap UP > {$gapTol}% (against signal)",
            'step2' => $action === 'BUY_CE' ? "9:15 candle MUST close GREEN — if red, abort"                        : "9:15 candle MUST close RED — if green, abort",
            'step3' => $action === 'BUY_CE' ? "9:30 candle MUST also close green (two-candle confirmation)"         : "9:30 candle MUST also close red (two-candle confirmation)",
            'step4' => "Enter at 9:45 open — after two confirmed candles",
            'step5' => $action === 'BUY_CE' ? "Stop Loss = below 9:15 candle LOW"                                   : "Stop Loss = above 9:15 candle HIGH",
            'step6' => "Target T1 = +1%, T2 = +2% — trail SL to cost after T1",
            'step7' => "HARD EXIT at 11:00 if no target hit — no exceptions",
        ];

        $allReasons = array_merge(
            $pcr['reasons']   ?? [],
            $oi['reasons']    ?? [],
            $price['reasons'] ?? [],
            $contReasons,
            ["━━ Composite: {$composite} (PCR {$pScore}×35 + OI {$oScore}×35 + Price {$rScore}×30) / 100"],
            ["Aligned: {$aligned}/3 | Continuation: " . ($continuation >= 0 ? '+' : '') . $continuation],
            $gapNote ? ["Gap: {$gapNote}"] : []
        );

        return [
            'action'           => $action,
            'label'            => $action === 'BUY_CE' ? 'BUY CE — Next Day' : 'BUY PE — Next Day',
            'bias'             => $action === 'BUY_CE' ? 'BULLISH' : 'BEARISH',
            'confidence'       => $confidence,
            'strength'         => $strength,
            'composite'        => $composite,
            'aligned'          => $aligned,
            'continuation_adj' => $continuation,
            'gap_pct'          => $gapPct,
            'gap_adj'          => $gapAdj,
            'pcr_bias'         => $pBias,
            'oi_bias'          => $oBias,
            'price_bias'       => $rBias,
            'reasons'          => $allReasons,
            'entry_rules'      => $entryRules,
        ];
    }

    // =========================================================================
    // ATR THRESHOLDS
    // =========================================================================
    private function computeAtrThresholds(array $futMap, array $allTimes): array
    {
        $ranges = [];
        foreach ($allTimes as $tk) {
            $fc = $futMap[$tk] ?? null;
            if (!$fc) continue;
            $h = (float)$fc['high']; $l = (float)$fc['low'];
            $c = (float)($fc['future_price'] ?: $fc['close']);
            if ($h > 0 && $l > 0 && $c > 0 && $h >= $l) $ranges[] = (($h - $l) / $c) * 100;
        }
        if (count($ranges) < 5) {
            return ['strong' => self::P_STRONG, 'moderate' => self::P_MODERATE, 'weak' => self::P_WEAK, 'atr_pct' => null, 'atr_based' => false];
        }
        $atrPct = max(0.1, min(5.0, array_sum($ranges) / count($ranges)));
        return [
            'strong'   => round($atrPct * 1.5, 3),
            'moderate' => round($atrPct * 0.8, 3),
            'weak'     => round($atrPct * 0.3, 3),
            'atr_pct'  => round($atrPct, 4),
            'atr_based'=> true,
        ];
    }

    // =========================================================================
    // SAVE PREDICTION
    // =========================================================================
    public function savePrediction(array $result): void
    {
        if (!empty($result['data_incomplete'])) return;

        try {
            $sig   = $result['signal'];
            $ind   = $result['indicators'];
            $day   = $result['day'];
            $order = $result['order'] ?? null;

            SignalPrediction::updateOrCreate(
                ['symbol' => $result['symbol'], 'signal_date' => $result['date'], 'version' => 'v9'],
                [
                    'trade_date'           => $result['next_trade_date'],
                    'action'               => $sig['action'],
                    'bias'                 => $sig['bias']             ?? null,
                    'confidence'           => $sig['confidence'],
                    'strength'             => $sig['strength'],
                    'composite'            => $sig['composite']        ?? null,
                    'continuation_adj'     => $sig['continuation_adj'] ?? null,
                    'gap_pct'              => $sig['gap_pct']          ?? null,
                    'gap_adj'              => $sig['gap_adj']          ?? null,
                    'pcr_eod'              => $ind['pcr']['pcr_eod']   ?? null,
                    'pcr_change'           => isset($ind['pcr']['pcr_eod'], $ind['pcr']['pcr_prev'])
                                              ? round($ind['pcr']['pcr_eod'] - $ind['pcr']['pcr_prev'], 4) : null,
                    'pcr_bias'             => $ind['pcr']['bias']      ?? null,
                    'pcr_context'          => $ind['pcr']['context']   ?? null,
                    'pcr_context_adj'      => $ind['pcr']['context_adj'] ?? null,
                    'oi_ce_score'          => $ind['oi']['ce_score']   ?? null,
                    'oi_pe_score'          => $ind['oi']['pe_score']   ?? null,
                    'oi_long_buildup'      => $ind['oi']['long_buildup']  ?? null,
                    'oi_short_buildup'     => $ind['oi']['short_buildup'] ?? null,
                    'oi_short_covering'    => $ind['oi']['short_cover']   ?? null,
                    'oi_long_unwind'       => $ind['oi']['long_unwind']   ?? null,
                    'oi_bias'              => $ind['oi']['bias']       ?? null,
                    'price_change_pct'     => $ind['price']['day_pct'] ?? null,
                    'price_direction'      => $ind['price']['day_dir'] ?? null,
                    'price_strength'       => $ind['price']['day_str'] ?? null,
                    'atr_pct'              => $ind['price']['thresholds']['atr_pct'] ?? null,
                    'last_hour_change_pct' => $ind['price']['lh_pct']  ?? null,
                    'last_hour_direction'  => $ind['price']['lh_dir']  ?? null,
                    'indicators_aligned'   => $sig['aligned']          ?? 0,
                    'atm_strike'           => $result['atm_strike']    ?? null,
                    'strike_interval'      => $result['strike_interval'] ?? null,
                    'fut_close'            => $day['fut_eod']          ?? null,
                    'order_strike'         => $order['strike']          ?? null,
                    'order_strike_pos'     => $order['strike_position']  ?? null,
                    'order_option_type'    => $order['option_type']      ?? null,
                    'order_entry_price'    => $order['entry_price']      ?? null,
                    'order_premium_status' => $order['premium_status']   ?? null,
                    'order_volume'         => $order['volume']           ?? null,
                    'order_oi'             => $order['oi']               ?? null,
                    'reasons'              => $sig['reasons']          ?? [],
                    'indicators_detail'    => $ind,
                    'order_detail'         => $order,
                    'outcome'              => 'PENDING',
                ]
            );
        } catch (\Exception $e) {
            Log::error('EodSignal savePrediction: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
    private function getAllTradingDates(): array
    {
        return OptionOhlcData::whereIn('instrument_type', ['CE', 'PE'])
            ->select(DB::raw('DATE(trade_date) as d'))
            ->distinct()->orderBy('d')
            ->pluck('d')->map(fn($d) => (string) $d)->toArray();
    }

    private function buildFutMap($rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $tk       = Carbon::parse($r->interval_time)->format('H:i');
            $fp       = (float)$r->future_price;
            $map[$tk] = [
                'open'         => (float)$r->open,
                'high'         => (float)$r->high,
                'low'          => (float)$r->low,
                'close'        => (float)$r->close,
                'future_price' => $fp > 0 ? $fp : (float)$r->close,
            ];
        }
        return $map;
    }

    private function buildOiByTime($rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            $tk = Carbon::parse($r->interval_time)->format('H:i');
            $map[$tk][$r->instrument_type] = ($map[$tk][$r->instrument_type] ?? 0) + (int)$r->oi;
        }
        return $map;
    }

    private function buildOiByTimeForType($rows, string $type): array
    {
        $map = [];
        foreach ($rows->where('instrument_type', $type) as $r) {
            $tk       = Carbon::parse($r->interval_time)->format('H:i');
            $map[$tk] = ($map[$tk] ?? 0) + (int)$r->oi;
        }
        return $map;
    }

    private function neutralIndicator(string $name, string $reason): array
    {
        return ['name' => $name, 'bias' => 'NEUTRAL', 'score' => 0, 'strength' => 0, 'reasons' => [$reason]];
    }

    private function getNearestExpiry(string $sym, string $date): ?string
    {
        $e = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        return $e ?? OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }
}