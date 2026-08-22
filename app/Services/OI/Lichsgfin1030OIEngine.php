<?php

namespace App\Services\OI;

/**
 * LICHSGFIN ONLY - 10:30 AM OI PROGRESSION TRADE ENGINE
 *
 * Strategy objective:
 *   Analyse LICHSGFIN's own 09:15 -> 10:30 behaviour.
 *   At/after 10:30:
 *      BUY_CE when a GAP DOWN fails and bullish OI progression confirms.
 *      BUY_PE when a GAP UP fails and bearish OI progression confirms.
 *
 * "Gap fails" is NOT based on a generic OI textbook rule.
 * It is defined from price acceptance/rejection plus the progression of
 * Futures OI and CE/PE ATM-1/ATM/ATM+1 behaviour during the opening window.
 *
 * IMPORTANT DATA FIELDS PER 15-MIN ROW:
 * datetime, future_open, future_high, future_low, future_close, future_oi,
 * ce_atm_minus_1_close, ce_atm_minus_1_oi,
 * ce_atm_close, ce_atm_oi,
 * ce_atm_plus_1_close, ce_atm_plus_1_oi,
 * pe_atm_minus_1_close, pe_atm_minus_1_oi,
 * pe_atm_close, pe_atm_oi,
 * pe_atm_plus_1_close, pe_atm_plus_1_oi
 */

final class Lichsgfin1030OIEngine
{
    private array $rows;

    public function __construct(array $rows)
    {
        usort($rows, fn($a,$b) => strcmp($a['datetime'], $b['datetime']));
        $this->rows = array_values($rows);
    }

    private function pct(float $current, float $base): float
    {
        return abs($base) < 0.0000001 ? 0.0 : (($current - $base) / abs($base)) * 100;
    }

    private function sign(float $x): int
    {
        return $x > 0 ? 1 : ($x < 0 ? -1 : 0);
    }

    private function row(int $i): array
    {
        return $this->rows[$i];
    }

    private function behaviour(float $p0,float $p1,float $oi0,float $oi1): string
    {
        $p = $this->sign($p0 - $p1);
        $o = $this->sign($oi0 - $oi1);

        if ($p > 0 && $o > 0) return 'LONG_BUILDUP';
        if ($p < 0 && $o > 0) return 'SHORT_BUILDUP';
        if ($p > 0 && $o < 0) return 'SHORT_COVERING';
        if ($p < 0 && $o < 0) return 'LONG_UNWINDING';
        return 'NEUTRAL';
    }

    private function optionNames(string $side): array
    {
        return [
            'ATM_MINUS_1' => [$side.'_atm_minus_1_close', $side.'_atm_minus_1_oi', 1.0],
            'ATM'         => [$side.'_atm_close',         $side.'_atm_oi',         2.0],
            'ATM_PLUS_1'  => [$side.'_atm_plus_1_close',  $side.'_atm_plus_1_oi',  1.0]
        ];
    }

    /**
     * Analyse the WHOLE opening progression, not just the 10:30 candle.
     * Returns premium/OI change from 09:15 to current index and weighted
     * directional pressure across ATM-1 / ATM / ATM+1.
     */
    private function openingOptionProgression(int $start, int $end, string $side): array
    {
        $first = $this->row($start);
        $last  = $this->row($end);
        $total = 0.0;
        $details = [];

        foreach ($this->optionNames($side) as $name => [$pk,$ok,$weight]) {
            $premiumPct = $this->pct((float)$last[$pk], (float)$first[$pk]);
            $oiPct      = $this->pct((float)$last[$ok], (float)$first[$ok]);

            // Raw option strength: premium expansion + OI context.
            // Premium gets greater weight because the strategy buys options.
            $score = 0.0;

            if ($premiumPct > 2 && $oiPct > 0.25) $score = 2.0;       // option long buildup
            elseif ($premiumPct > 2 && $oiPct < -0.25) $score = 2.5;  // option short covering
            elseif ($premiumPct < -2 && $oiPct > 0.25) $score = -2.5;// writing pressure
            elseif ($premiumPct < -2 && $oiPct < -0.25) $score = -1.5;// option long unwind

            $weighted = $score * $weight;
            $total += $weighted;

            $details[$name] = [
                'premium_change_pct' => round($premiumPct,2),
                'oi_change_pct' => round($oiPct,2),
                'behaviour' => $this->behaviour(
                    (float)$last[$pk], (float)$first[$pk],
                    (float)$last[$ok], (float)$first[$ok]
                ),
                'score' => $weighted
            ];
        }

        return ['score'=>$total,'details'=>$details];
    }

    /**
     * Count consistency across each 15-minute transition.
     * This is critical: one large 10:30 move is weaker than a progressive move.
     */
    private function transitionConsistency(int $start, int $end, string $side): array
    {
        $bull = 0; $bear = 0; $neutral = 0;
        $names = $this->optionNames($side);

        for ($i=$start+1; $i<=$end; $i++) {
            $prev=$this->row($i-1); $cur=$this->row($i);
            $step=0.0;

            foreach ($names as [$pk,$ok,$weight]) {
                $pc=$this->pct((float)$cur[$pk],(float)$prev[$pk]);
                $oc=$this->pct((float)$cur[$ok],(float)$prev[$ok]);

                if ($pc > 0.5 && $oc >= -0.5) $step += $weight;
                elseif ($pc < -0.5 && $oc >= -0.5) $step -= $weight;
            }

            if ($step > 0) $bull++;
            elseif ($step < 0) $bear++;
            else $neutral++;
        }

        return compact('bull','bear','neutral');
    }

    private function previousDayClose(int $start): ?float
    {
        if ($start <= 0) return null;
        return (float)$this->rows[$start-1]['future_close'];
    }

    private function openingRange(int $start, int $end): array
    {
        $slice=array_slice($this->rows,$start,$end-$start+1);
        return [
            'high'=>max(array_column($slice,'future_high')),
            'low'=>min(array_column($slice,'future_low'))
        ];
    }

    /**
     * Find the current day's 09:15 row and 10:30 row.
     */
    public function find1030Window(int $currentIndex): ?array
    {
        $date=substr($this->rows[$currentIndex]['datetime'],0,10);
        $start=null; $target=null;

        foreach ($this->rows as $i=>$r) {
            if (substr($r['datetime'],0,10)!==$date) continue;
            $time=substr($r['datetime'],11,5);
            if ($time==='09:15') $start=$i;
            if ($time==='10:30') $target=$i;
        }

        return ($start!==null && $target!==null && $currentIndex >= $target)
            ? ['start'=>$start,'target'=>$target]
            : null;
    }

    /**
     * Main LICHSGFIN analysis at 10:30.
     */
    public function analyse1030(int $currentIndex): array
    {
        $window=$this->find1030Window($currentIndex);
        if (!$window) return ['signal'=>'NO_TRADE','reason'=>'10:30 window not available'];

        [$start,$end]=[$window['start'],$window['target']];
        $open=$this->row($start);
        $now=$this->row($end);
        $prevClose=$this->previousDayClose($start);

        if ($prevClose===null) return ['signal'=>'NO_TRADE','reason'=>'Previous day close unavailable'];

        $gapPct=$this->pct((float)$open['future_open'],$prevClose);
        $priceFromOpen=$this->pct((float)$now['future_close'],(float)$open['future_open']);
        $priceVsPrev=$this->pct((float)$now['future_close'],$prevClose);
        $futureOIPct=$this->pct((float)$now['future_oi'],(float)$open['future_oi']);

        $ce=$this->openingOptionProgression($start,$end,'ce');
        $pe=$this->openingOptionProgression($start,$end,'pe');
        $ceConsistency=$this->transitionConsistency($start,$end,'ce');
        $peConsistency=$this->transitionConsistency($start,$end,'pe');

        $range=$this->openingRange($start,$end);
        $closePosition = ((float)$now['future_close']-$range['low']) /
                         max(0.0001,$range['high']-$range['low']);

        /*
         * GAP FAILURE:
         *
         * GAP DOWN FAILED -> underlying recovered above the open and preferably
         * above previous close. CE progression must dominate PE progression.
         *
         * GAP UP FAILED -> underlying fell below the open and preferably below
         * previous close. PE progression must dominate CE progression.
         *
         * For near-flat gaps, use rejection/recovery from opening range.
         */

        $gapDown = $gapPct <= -0.10;
        $gapUp   = $gapPct >= 0.10;

        $gapDownFailed =
            $gapDown &&
            $priceFromOpen > 0.05 &&
            $priceVsPrev > -0.10;

        $gapUpFailed =
            $gapUp &&
            $priceFromOpen < -0.05 &&
            $priceVsPrev < 0.10;

        // Opening OI/price reversal confirmation.
        $bullOIConfirm =
            $ce['score'] > 0 &&
            $ce['score'] > abs($pe['score']) * 0.60 &&
            $ceConsistency['bull'] >= $ceConsistency['bear'];

        $bearOIConfirm =
            $pe['score'] > 0 &&
            $pe['score'] > abs($ce['score']) * 0.60 &&
            $peConsistency['bull'] >= $peConsistency['bear'];

        // LICHSGFIN-specific score: price failure/recovery is primary,
        // options OI progression confirms it.
        $ceBuyScore=0.0;
        $peBuyScore=0.0;

        if ($gapDownFailed) $ceBuyScore += 5;
        if ($gapUpFailed)   $peBuyScore += 5;

        if ($priceFromOpen > 0.20) $ceBuyScore += 2;
        if ($priceFromOpen < -0.20) $peBuyScore += 2;

        if ($priceVsPrev > 0) $ceBuyScore += 2;
        if ($priceVsPrev < 0) $peBuyScore += 2;

        if ($closePosition >= 0.70) $ceBuyScore += 1.5;
        if ($closePosition <= 0.30) $peBuyScore += 1.5;

        $ceBuyScore += max(0,min(4,$ce['score']/2));
        $peBuyScore += max(0,min(4,$pe['score']/2));

        if ($ceConsistency['bull'] >= 3) $ceBuyScore += 1.5;
        if ($peConsistency['bull'] >= 3) $peBuyScore += 1.5;

        // Futures OI is confirmation only. Do not force textbook interpretation.
        // Positive price + rising OI supports continuation; falling OI does not
        // invalidate a reversal because LICHSGFIN can move through covering.
        if ($priceFromOpen > 0.20 && $futureOIPct > 0) $ceBuyScore += 1;
        if ($priceFromOpen < -0.20 && $futureOIPct > 0) $peBuyScore += 1;

        $signal='NO_TRADE';
        $reason=[];

        if ($gapDownFailed && $bullOIConfirm && $ceBuyScore >= 8) {
            $signal='BUY_CE';
            $reason[]='GAP_DOWN_FAILED';
            $reason[]='BULLISH_CE_OI_PROGRESSION';
        } elseif ($gapUpFailed && $bearOIConfirm && $peBuyScore >= 8) {
            $signal='BUY_PE';
            $reason[]='GAP_UP_FAILED';
            $reason[]='BEARISH_PE_OI_PROGRESSION';
        } else {
            // Optional near-flat/opening reversal logic, still stock-specific.
            if (!$gapUp && !$gapDown && $priceFromOpen > 0.35 && $bullOIConfirm && $ceBuyScore >= 7) {
                $signal='BUY_CE';
                $reason[]='OPENING_BULLISH_REVERSAL';
            } elseif (!$gapUp && !$gapDown && $priceFromOpen < -0.35 && $bearOIConfirm && $peBuyScore >= 7) {
                $signal='BUY_PE';
                $reason[]='OPENING_BEARISH_REVERSAL';
            } else {
                $reason[]='NO_CONFIRMED_LICHSGFIN_OPENING_PATTERN';
            }
        }

        return [
            'stock'=>'LICHSGFIN',
            'signal'=>$signal,
            'analysis_time'=>'10:30',
            'gap_pct'=>round($gapPct,3),
            'gap_down'=>$gapDown,
            'gap_up'=>$gapUp,
            'gap_down_failed'=>$gapDownFailed,
            'gap_up_failed'=>$gapUpFailed,
            'price_from_open_pct'=>round($priceFromOpen,3),
            'price_vs_previous_close_pct'=>round($priceVsPrev,3),
            'future_oi_from_open_pct'=>round($futureOIPct,3),
            'opening_range'=>$range,
            'close_position_in_opening_range'=>round($closePosition,3),
            'ce_progression'=>$ce,
            'pe_progression'=>$pe,
            'ce_consistency'=>$ceConsistency,
            'pe_consistency'=>$peConsistency,
            'ce_buy_score'=>round($ceBuyScore,2),
            'pe_buy_score'=>round($peBuyScore,2),
            'reason'=>$reason
        ];
    }
}

/*
 * Usage:
 *
 * $rows must contain all LICHSGFIN 15-minute rows in ascending datetime order.
 *
 * $engine = new LICHSGFIN1030OIEngine($rows);
 * $result = $engine->analyse1030(count($rows)-1);
 * print_r($result);
 */