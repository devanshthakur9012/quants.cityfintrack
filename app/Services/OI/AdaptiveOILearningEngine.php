<?php

namespace App\Services\OI;

/**
 * ADAPTIVE OI LEARNING ENGINE
 *
 * Ported 1:1 from the client-supplied standalone scripts
 * `paytm_oi_engine.php` and `shriramfin_oi_engine.php`.
 *
 * Both files implement the EXACT SAME algorithm — only the tuning
 * constants differ per stock (gap threshold, ATM weights, buy/sell
 * thresholds, min profile observations). Rather than duplicate the
 * ~600 lines of logic twice, this single class is config-driven so
 * it can be reused for PAYTM, SHRIRAMFIN, or any future symbol that
 * uses this style of strategy.
 *
 * WHAT CHANGED vs the client's original scripts:
 *   - Original scripts read an uploaded .xlsx via PhpSpreadsheet and
 *     ran as a CLI tool. This class takes already-parsed PHP arrays
 *     (same shapes the originals built internally: $stockByDate,
 *     $ce, $pe) so it can be fed from the database instead. See
 *     StockOIStrategyController for the DB -> array adapter.
 *   - All scoring, classification, gap/reversal, profile-learning,
 *     and signal-threshold logic is otherwise UNCHANGED.
 *
 * Expected input shapes (identical to the client scripts):
 *
 *   $stockByDate[date] = [
 *      ['date'=>..,'time'=>'09:15','open'=>..,'high'=>..,'low'=>..,'close'=>..,'volume'=>..],
 *      ... one row per 15-min interval for that date, in time order
 *   ]
 *
 *   $ce[date][time][strike] = ['strike'=>..,'open'=>..,'high'=>..,'low'=>..,'close'=>..,'volume'=>..,'oi'=>..,'type'=>'CE']
 *   $pe[date][time][strike] = same shape, 'type'=>'PE'
 *   where $strike is one of 'ATM-1', 'ATM', 'ATM+1'
 */
class AdaptiveOILearningEngine
{
    public function __construct(
        private readonly string $stockName,
        private readonly float $gapThresholdPct = 0.20,
        private readonly float $atmMinusWeight = 0.25,
        private readonly float $atmWeight = 0.50,
        private readonly float $atmPlusWeight = 0.25,
        private readonly float $buyThreshold = 70.0,
        private readonly float $sellThreshold = 30.0,
        private readonly int $profileMinN = 3,
    ) {
    }

    // ── Basic helpers ────────────────────────────────────────────────

    private function pct(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return 0.0;
        }
        return (($current - $previous) / abs($previous)) * 100.0;
    }

    private function avg(array $values): float
    {
        $values = array_values(array_filter($values, fn ($v) => is_numeric($v)));
        return count($values) ? array_sum($values) / count($values) : 0.0;
    }

    private function clamp(float $x, float $min, float $max): float
    {
        return max($min, min($max, $x));
    }

    private function timeRow(array $rows, string $time): ?array
    {
        foreach ($rows as $r) {
            if ($r['time'] === $time) {
                return $r;
            }
        }
        return null;
    }

    private function optionAt(array $options, string $date, string $time, string $strike): ?array
    {
        return $options[$date][$time][$strike] ?? null;
    }

    // ── Classification ───────────────────────────────────────────────

    private function classifyOI(float $prevPrice, float $currPrice, float $prevOI, float $currOI): array
    {
        if ($prevPrice <= 0 || $prevOI <= 0) {
            return ['type' => 'UNKNOWN', 'price_change_pct' => 0.0, 'oi_change_pct' => 0.0];
        }

        $priceChange = $this->pct($currPrice, $prevPrice);
        $oiChange = $this->pct($currOI, $prevOI);

        if (abs($priceChange) < 0.05 && abs($oiChange) < 0.05) {
            $type = 'NEUTRAL';
        } elseif ($priceChange > 0 && $oiChange > 0) {
            $type = 'LONG_BUILDUP';
        } elseif ($priceChange < 0 && $oiChange > 0) {
            $type = 'SHORT_BUILDUP';
        } elseif ($priceChange > 0 && $oiChange < 0) {
            $type = 'SHORT_COVERING';
        } else {
            $type = 'LONG_UNWINDING';
        }

        return ['type' => $type, 'price_change_pct' => $priceChange, 'oi_change_pct' => $oiChange];
    }

    private function standardDirection(string $optionType, string $oiBehaviour): float
    {
        if ($optionType === 'CE') {
            return match ($oiBehaviour) {
                'LONG_BUILDUP' => +0.50,
                'SHORT_COVERING' => +1.00,
                'SHORT_BUILDUP' => -1.00,
                'LONG_UNWINDING' => -0.50,
                default => 0.00,
            };
        }

        return match ($oiBehaviour) {
            'SHORT_BUILDUP' => +1.00,
            'LONG_UNWINDING' => +0.50,
            'LONG_BUILDUP' => -0.50,
            'SHORT_COVERING' => -1.00,
            default => 0.00,
        };
    }

    private function directionLabel(float $score): string
    {
        if ($score >= 0.55) {
            return 'BULLISH';
        }
        if ($score <= -0.55) {
            return 'BEARISH';
        }
        return 'NEUTRAL';
    }

    private function buildOptionClassifications(array $ce, array $pe, string $date, string $time, string $previousTime): array
    {
        $result = [];

        foreach (['CE' => $ce, 'PE' => $pe] as $type => $data) {
            foreach (['ATM-1', 'ATM', 'ATM+1'] as $strike) {
                $curr = $this->optionAt($data, $date, $time, $strike);
                $prev = $this->optionAt($data, $date, $previousTime, $strike);

                if (!$curr || !$prev) {
                    $result[$type][$strike] = ['type' => 'UNKNOWN', 'price_change_pct' => 0, 'oi_change_pct' => 0];
                    continue;
                }

                $result[$type][$strike] = $this->classifyOI($prev['close'], $curr['close'], $prev['oi'], $curr['oi']);
            }
        }

        return $result;
    }

    // ── Historical profile (stock-specific learning) ────────────────

    private function addProfileCase(array &$profile, string $key, float $futureReturn): void
    {
        if (!isset($profile[$key])) {
            $profile[$key] = ['n' => 0, 'bull' => 0, 'bear' => 0, 'returns' => []];
        }

        $profile[$key]['n']++;
        $profile[$key]['returns'][] = $futureReturn;

        if ($futureReturn > 0) {
            $profile[$key]['bull']++;
        } elseif ($futureReturn < 0) {
            $profile[$key]['bear']++;
        }
    }

    private function profileProbability(array $profile, string $key, float $default = 0.50): float
    {
        if (!isset($profile[$key]) || $profile[$key]['n'] < $this->profileMinN) {
            return $default;
        }
        return $profile[$key]['bull'] / max(1, $profile[$key]['n']);
    }

    private function profileConfidence(array $profile, string $key): float
    {
        if (!isset($profile[$key])) {
            return 0.0;
        }
        // Confidence rises with observations, capped at 1.
        return $this->clamp($profile[$key]['n'] / 30.0, 0.0, 1.0);
    }

    /**
     * STEP 1 (client script): build the stock-specific historical OI
     * profile from every synchronized day EXCEPT the latest one, using
     * the 10:00 OI classification vs the 10:00 -> close future return.
     */
    public function buildHistoricalProfile(array $stockByDate, array $ce, array $pe, array $dates): array
    {
        $profile = [];

        foreach ($dates as $date) {
            if (!isset($ce[$date], $pe[$date], $stockByDate[$date])) {
                continue;
            }

            $dayRows = $stockByDate[$date];
            $classification10 = $this->buildOptionClassifications($ce, $pe, $date, '10:00', '09:45');
            $futureReturn = $this->intradayOutcome($dayRows);

            if ($futureReturn === null) {
                continue;
            }

            foreach (['CE', 'PE'] as $type) {
                foreach (['ATM-1', 'ATM', 'ATM+1'] as $strike) {
                    $behaviour = $classification10[$type][$strike]['type'] ?? 'UNKNOWN';
                    if ($behaviour === 'UNKNOWN') {
                        continue;
                    }
                    $this->addProfileCase($profile, $type . '_' . $behaviour, $futureReturn);
                }
            }
        }

        return $profile;
    }

    private function adaptiveOIScore(array $classification, array $profile): array
    {
        $weights = ['ATM-1' => $this->atmMinusWeight, 'ATM' => $this->atmWeight, 'ATM+1' => $this->atmPlusWeight];

        $score = 0.0;
        $weightUsed = 0.0;
        $details = [];
        $unknownCount = 0;
        $totalLegs = 0;

        foreach (['CE', 'PE'] as $type) {
            foreach ($weights as $strike => $weight) {
                $totalLegs++;
                $behaviour = $classification[$type][$strike]['type'] ?? 'UNKNOWN';
                if ($behaviour === 'UNKNOWN') {
                    $unknownCount++;
                    continue;
                }

                $pattern = $type . '_' . $behaviour;
                $probability = $this->profileProbability($profile, $pattern);
                $confidence = $this->profileConfidence($profile, $pattern);

                $historicalDirection = ($probability - 0.50) * 2.0;
                $standardDirection = $this->standardDirection($type, $behaviour);

                // Blend: stock-specific behaviour dominates as sample size grows.
                $direction = ($historicalDirection * $confidence) + ($standardDirection * (1.0 - $confidence));

                $score += $direction * $weight;
                $weightUsed += $weight;

                $details[$type][$strike] = [
                    'behaviour' => $behaviour,
                    'historical_probability' => round($probability * 100, 1),
                    'historical_n' => $profile[$pattern]['n'] ?? 0,
                    'direction' => round($direction, 3),
                ];
            }
        }

        $final = $weightUsed ? $score / $weightUsed : 0.0;

        return [
            'score' => $this->clamp($final, -1, 1),
            'label' => $this->directionLabel($final),
            'details' => $details,
            'unknown_legs' => $unknownCount,
            'total_legs' => $totalLegs,
        ];
    }

    // ── Gap / reversal / range ───────────────────────────────────────

    private function gapAnalysis(float $previousClose, float $todayOpen): array
    {
        $gap = $this->pct($todayOpen, $previousClose);

        if ($gap <= -$this->gapThresholdPct) {
            return ['type' => 'GAP_DOWN', 'gap_pct' => $gap];
        }
        if ($gap >= $this->gapThresholdPct) {
            return ['type' => 'GAP_UP', 'gap_pct' => $gap];
        }
        return ['type' => 'FLAT_OPEN', 'gap_pct' => $gap];
    }

    private function reversalAnalysis(array $dayRows): array
    {
        $r0915 = $this->timeRow($dayRows, '09:15');
        $r0930 = $this->timeRow($dayRows, '09:30');
        $r0945 = $this->timeRow($dayRows, '09:45');
        $r1000 = $this->timeRow($dayRows, '10:00');

        if (!$r0915 || !$r0930 || !$r0945 || !$r1000) {
            return ['state' => 'UNKNOWN', 'first_move_pct' => 0, 'higher_low' => false, 'lower_high' => false];
        }

        $firstMove = $this->pct($r0930['close'], $r0915['open']);

        $lowA = min($r0915['low'], $r0930['low']);
        $lowB = min($r0945['low'], $r1000['low']);
        $highA = max($r0915['high'], $r0930['high']);
        $highB = max($r0945['high'], $r1000['high']);

        $higherLow = $lowB > $lowA;
        $lowerHigh = $highB < $highA;

        if ($r0915['open'] > 0 && $r1000['close'] > $r0915['open'] && $higherLow) {
            return ['state' => 'BULLISH_REVERSAL', 'first_move_pct' => $firstMove, 'higher_low' => true, 'lower_high' => false];
        }

        if ($r0915['open'] > 0 && $r1000['close'] < $r0915['open'] && $lowerHigh) {
            return ['state' => 'BEARISH_REVERSAL', 'first_move_pct' => $firstMove, 'higher_low' => false, 'lower_high' => true];
        }

        return ['state' => 'NO_CONFIRMED_REVERSAL', 'first_move_pct' => $firstMove, 'higher_low' => $higherLow, 'lower_high' => $lowerHigh];
    }

    private function openingRangeAnalysis(array $dayRows): array
    {
        $r1000 = $this->timeRow($dayRows, '10:00');
        $r1015 = $this->timeRow($dayRows, '10:15');
        $r1030 = $this->timeRow($dayRows, '10:30');

        if (!$r1000 || !$r1015 || !$r1030) {
            return ['range_high' => 0, 'range_low' => 0, 'state' => 'UNKNOWN'];
        }

        $high = max($r1000['high'], $r1015['high']);
        $low = min($r1000['low'], $r1015['low']);
        $price = $r1030['close'];

        $state = $price > $high ? 'BREAKOUT_UP' : ($price < $low ? 'BREAKDOWN' : 'INSIDE_RANGE');

        return ['range_high' => $high, 'range_low' => $low, 'state' => $state];
    }

    private function intradayOutcome(array $dayRows): ?float
    {
        $r1000 = $this->timeRow($dayRows, '10:00');
        $last = end($dayRows);

        if (!$r1000 || !$last || $r1000['close'] <= 0) {
            return null;
        }

        return $this->pct($last['close'], $r1000['close']);
    }

    // ── Final signals ─────────────────────────────────────────────────

    private function finalIntradaySignal(array $gap, array $reversal, array $range, array $oi): array
    {
        $score = 50.0;

        if ($gap['type'] === 'GAP_DOWN' && $reversal['state'] === 'BULLISH_REVERSAL') {
            $score += 15;
        }
        if ($gap['type'] === 'GAP_UP' && $reversal['state'] === 'BEARISH_REVERSAL') {
            $score -= 15;
        }
        if ($gap['type'] === 'GAP_DOWN' && $reversal['state'] === 'BEARISH_REVERSAL') {
            $score -= 10;
        }
        if ($gap['type'] === 'GAP_UP' && $reversal['state'] === 'BULLISH_REVERSAL') {
            $score += 10;
        }

        $score += $oi['score'] * 25.0;

        if ($range['state'] === 'BREAKOUT_UP') {
            $score += 15;
        }
        if ($range['state'] === 'BREAKDOWN') {
            $score -= 15;
        }

        $score = $this->clamp($score, 0, 100);

        $signal = $score >= $this->buyThreshold ? 'BUY' : ($score <= $this->sellThreshold ? 'SELL' : 'NO_TRADE');

        return ['score' => round($score, 1), 'signal' => $signal, 'confidence' => round(abs($score - 50) * 2, 1)];
    }

    private function finalOvernightSignal(array $oi, float $todayReturn): array
    {
        $score = 50.0;
        $score += $oi['score'] * 30.0;

        if ($todayReturn > 0) {
            $score += min(15, $todayReturn * 4);
        }
        if ($todayReturn < 0) {
            $score -= min(15, abs($todayReturn) * 4);
        }

        if (abs($todayReturn) > 5) {
            $score *= 0.90;
            $score = 50 + ($score - 50) * 0.85;
        }

        $score = $this->clamp($score, 0, 100);

        $signal = $score >= 70 ? 'BUY_OVERNIGHT' : ($score <= 30 ? 'SELL/AVOID_OVERNIGHT' : 'NO_TRADE');

        return ['score' => round($score, 1), 'signal' => $signal, 'confidence' => round(abs($score - 50) * 2, 1)];
    }

    // ── Data quality ──────────────────────────────────────────────────

    /**
     * Fix 7: distinguish "strategy decided NO_TRADE" from "we don't have
     * enough clean data to decide". Looks at how many CE/PE legs came back
     * UNKNOWN (missing rows) and whether reversal/range could be computed.
     */
    private function dataStatus(array $classification10, array $reversal, array $range, ?float $prevClose, ?float $todayOpen): string
    {
        if ($prevClose === null || $todayOpen === null || $prevClose <= 0) {
            return 'INVALID';
        }

        $unknownLegs = 0;
        $totalLegs = 0;
        foreach (['CE', 'PE'] as $type) {
            foreach (['ATM-1', 'ATM', 'ATM+1'] as $strike) {
                $totalLegs++;
                if (($classification10[$type][$strike]['type'] ?? 'UNKNOWN') === 'UNKNOWN') {
                    $unknownLegs++;
                }
            }
        }

        if ($unknownLegs === $totalLegs || $reversal['state'] === 'UNKNOWN' || $range['state'] === 'UNKNOWN') {
            return 'INVALID';
        }
        if ($unknownLegs > 0) {
            return 'PARTIAL';
        }
        return 'COMPLETE';
    }

    /**
     * Shared per-date evaluation used by both backtest() and latestAnalysis(),
     * so the two can never drift apart. `$profile` must already be built from
     * dates strictly BEFORE `$date` — the caller is responsible for that
     * (see backtest()'s walk-forward loop and StockOIStrategyController).
     */
    private function evaluateDate(array $stockByDate, array $ce, array $pe, string $date, string $prevDate, array $profile): ?array
    {
        if (!isset($ce[$date], $pe[$date], $stockByDate[$date], $stockByDate[$prevDate])) {
            return null;
        }

        $todayRows = $stockByDate[$date];
        $prevRows = $stockByDate[$prevDate];
        $todayOpen = $this->timeRow($todayRows, '09:15');
        $prevCloseRow = end($prevRows);

        if (!$todayOpen || !$prevCloseRow) {
            return null;
        }

        $gap = $this->gapAnalysis($prevCloseRow['close'], $todayOpen['open']);
        $reversal = $this->reversalAnalysis($todayRows);
        $range = $this->openingRangeAnalysis($todayRows);
        $classification = $this->buildOptionClassifications($ce, $pe, $date, '10:00', '09:45');
        $oi = $this->adaptiveOIScore($classification, $profile);
        $signal = $this->finalIntradaySignal($gap, $reversal, $range, $oi);
        $futureReturn = $this->intradayOutcome($todayRows);
        $status = $this->dataStatus($classification, $reversal, $range, $prevCloseRow['close'], $todayOpen['open']);

        return [
            'date' => $date,
            'signal' => $status === 'INVALID' ? 'NO_TRADE' : $signal['signal'],
            'score' => $signal['score'],
            'gap_type' => $gap['type'],
            'gap_pct' => round($gap['gap_pct'], 3),
            'reversal_state' => $reversal['state'],
            'range_state' => $range['state'],
            'oi_score' => round($oi['score'], 3),
            'oi_label' => $oi['label'],
            'oi_unknown_legs' => $oi['unknown_legs'],
            'oi_total_legs' => $oi['total_legs'],
            'future_return_pct' => $futureReturn !== null ? round($futureReturn, 3) : null,
            'data_status' => $status,
        ];
    }

    // ── Backtest (STEP 4 in the client script) ──────────────────────

    /**
     * Fix 4 (walk-forward): for the date at index i, the profile is built
     * ONLY from dates[0..i-1] — never from dates that happen after it.
     * The client's original script (and the first version of this port)
     * built one profile from all history and reused it for every backtest
     * row, which let a later day's outcome leak into an earlier day's
     * signal. This version re-learns the profile fresh for every step,
     * exactly matching how the strategy would actually have run live.
     */
    public function backtest(array $stockByDate, array $ce, array $pe, array $dates): array
    {
        $rows = [];
        $summary = ['BUY' => ['n' => 0, 'wins' => 0, 'returns' => []], 'SELL' => ['n' => 0, 'wins' => 0, 'returns' => []], 'NO_TRADE' => ['n' => 0]];

        for ($i = 1; $i < count($dates); $i++) {
            $date = $dates[$i];
            $prevDate = $dates[$i - 1];

            // Walk-forward: only dates strictly before today ever inform today's profile.
            $profile = $this->buildHistoricalProfile($stockByDate, $ce, $pe, array_slice($dates, 0, $i));

            $result = $this->evaluateDate($stockByDate, $ce, $pe, $date, $prevDate, $profile);
            if ($result === null) {
                continue;
            }

            $futureReturn = $result['future_return_pct'];

            if ($result['signal'] === 'BUY') {
                $summary['BUY']['n']++;
                if ($futureReturn !== null) {
                    $summary['BUY']['returns'][] = $futureReturn;
                    if ($futureReturn > 0) {
                        $summary['BUY']['wins']++;
                    }
                }
            } elseif ($result['signal'] === 'SELL') {
                $summary['SELL']['n']++;
                if ($futureReturn !== null) {
                    $summary['SELL']['returns'][] = -$futureReturn;
                    if ($futureReturn < 0) {
                        $summary['SELL']['wins']++;
                    }
                }
            } else {
                $summary['NO_TRADE']['n']++;
            }

            $rows[] = $result;
        }

        $summaryOut = [];
        foreach (['BUY', 'SELL'] as $side) {
            $n = $summary[$side]['n'];
            if ($n === 0) {
                $summaryOut[$side] = ['trades' => 0, 'win_rate_pct' => null, 'avg_return_pct' => null];
                continue;
            }
            $summaryOut[$side] = [
                'trades' => $n,
                'win_rate_pct' => round(($summary[$side]['wins'] / $n) * 100, 1),
                'avg_return_pct' => round($this->avg($summary[$side]['returns']), 3),
            ];
        }
        $summaryOut['NO_TRADE'] = ['trades' => $summary['NO_TRADE']['n']];

        return ['rows' => $rows, 'summary' => $summaryOut];
    }

    // ── Gap + reversal historical study (STEP 3) ────────────────────

    public function gapReversalStudy(array $stockByDate, array $dates): array
    {
        $stats = [
            'GAP_DOWN' => ['n' => 0, 'bull' => 0, 'returns' => []],
            'GAP_UP' => ['n' => 0, 'bull' => 0, 'returns' => []],
            'FLAT_OPEN' => ['n' => 0, 'bull' => 0, 'returns' => []],
        ];

        foreach ($dates as $i => $date) {
            if ($i === 0) {
                continue;
            }
            $prevDate = $dates[$i - 1];
            if (!isset($stockByDate[$date], $stockByDate[$prevDate])) {
                continue;
            }

            $todayRows = $stockByDate[$date];
            $prevRows = $stockByDate[$prevDate];
            $openRow = $this->timeRow($todayRows, '09:15');
            $prevLast = end($prevRows);

            if (!$openRow || !$prevLast) {
                continue;
            }

            $gap = $this->gapAnalysis($prevLast['close'], $openRow['open']);
            $outcome = $this->intradayOutcome($todayRows);
            if ($outcome === null) {
                continue;
            }

            $stats[$gap['type']]['n']++;
            if ($outcome > 0) {
                $stats[$gap['type']]['bull']++;
            }
            $stats[$gap['type']]['returns'][] = $outcome;
        }

        $out = [];
        foreach ($stats as $type => $s) {
            if ($s['n'] === 0) {
                continue;
            }
            $out[$type] = [
                'n' => $s['n'],
                'bullish_outcome_pct' => round(($s['bull'] / $s['n']) * 100, 1),
                'avg_return_pct' => round($this->avg($s['returns']), 3),
            ];
        }
        return $out;
    }

    /**
     * STEP 5 (client script): full analysis of the LATEST date in
     * $dates — mirrors "LATEST <STOCK> ANALYSIS" + overnight block.
     */
    public function latestAnalysis(array $stockByDate, array $ce, array $pe, array $dates, array $profile): array
    {
        $latestDate = end($dates);

        if (!isset($ce[$latestDate], $pe[$latestDate], $stockByDate[$latestDate])) {
            return ['signal' => 'NO_TRADE', 'reason' => 'Latest day has no synchronized CE/PE/stock data', 'data_status' => 'INVALID'];
        }

        $latestIndex = array_search($latestDate, $dates, true);
        $prevDate = ($latestIndex !== false && $latestIndex > 0) ? $dates[$latestIndex - 1] : null;

        if ($prevDate === null || !isset($stockByDate[$prevDate])) {
            return ['signal' => 'NO_TRADE', 'reason' => 'No previous trading day available for gap analysis', 'data_status' => 'INVALID'];
        }

        $latestRows = $stockByDate[$latestDate];
        $open = $this->timeRow($latestRows, '09:15');
        $prevLast = end($stockByDate[$prevDate]);

        if (!$open || !$prevLast) {
            return ['signal' => 'NO_TRADE', 'reason' => '09:15 row or previous close unavailable', 'data_status' => 'INVALID'];
        }

        $gap = $this->gapAnalysis($prevLast['close'], $open['open']);
        $reversal = $this->reversalAnalysis($latestRows);
        $range = $this->openingRangeAnalysis($latestRows);
        $classification = $this->buildOptionClassifications($ce, $pe, $latestDate, '10:00', '09:45');
        $oi = $this->adaptiveOIScore($classification, $profile);
        $signal = $this->finalIntradaySignal($gap, $reversal, $range, $oi);
        $status = $this->dataStatus($classification, $reversal, $range, $prevLast['close'], $open['open']);

        // Overnight variant — uses latest available option snapshot of the day.
        $optionTimes = array_keys($ce[$latestDate] ?? []);
        sort($optionTimes);
        $eodTime = end($optionTimes) ?: '10:00';
        $prevOptionTime = count($optionTimes) >= 2 ? $optionTimes[count($optionTimes) - 2] : $eodTime;

        $eodClassification = $this->buildOptionClassifications($ce, $pe, $latestDate, $eodTime, $prevOptionTime);
        $eodOI = $this->adaptiveOIScore($eodClassification, $profile);

        $last = end($latestRows);
        $todayReturn = $this->pct($last['close'], $open['open']);
        $overnight = $this->finalOvernightSignal($eodOI, $todayReturn);

        return [
            'stock' => $this->stockName,
            'date' => $latestDate,
            'previous_close' => round($prevLast['close'], 2),
            'open' => round($open['open'], 2),
            'last_close' => round($last['close'], 2),
            'gap_type' => $gap['type'],
            'gap_pct' => round($gap['gap_pct'], 3),
            'reversal_state' => $reversal['state'],
            'opening_range' => ['low' => $range['range_low'], 'high' => $range['range_high']],
            'range_state' => $range['state'],
            'oi_score' => round($oi['score'], 3),
            'oi_label' => $oi['label'],
            'oi_details' => $oi['details'],
            'oi_unknown_legs' => $oi['unknown_legs'],
            'oi_total_legs' => $oi['total_legs'],
            'signal' => $status === 'INVALID' ? 'NO_TRADE' : $signal['signal'],
            'signal_score' => $signal['score'],
            'signal_confidence' => $signal['confidence'],
            'data_status' => $status,
            'today_return_pct' => round($todayReturn, 3),
            'overnight_signal' => $overnight['signal'],
            'overnight_score' => $overnight['score'],
            'overnight_confidence' => $overnight['confidence'],
        ];
    }
}