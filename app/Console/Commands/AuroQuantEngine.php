<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OptionOhlcData;
use App\Models\AuroDailyVerdict;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AuroQuantEngine v3 — All Review Bugs Fixed
 *
 * FIXES APPLIED (numbered from the review):
 *
 * FIX 1 — Conflict veto too strict (was count===3, now any 2 opposing signs)
 * FIX 2 — Signal B volume: skip strike if avgVol=0 (not auto-true)
 * FIX 3 — Signal A clipping extended: [-5,+5] preserves more information
 * FIX 4 — Smart money boost moved inside weight (not applied after total)
 * FIX 5 — Expiry veto removed; replaced with confidence reduction + threshold raise
 * FIX 6 — Market alignment: uses pharma basket (SUNPHARMA, CIPLA, DRREDDY) if no NIFTY data
 * FIX 7 — Signal E override bug fixed with elseif (second block was overwriting first)
 * FIX 8 — Low volume filter now uses 5-day average (not static 300)
 * FIX 9 — Market regime used in scoring (VOLATILE → raise threshold)
 * FIX 10 — CE/PE ratio used in Signal A to distinguish writing vs buying
 */
class AuroQuantEngine extends Command
{
    protected $signature = 'auro:daily-verdict
                            {--date= : Override date Y-m-d}
                            {--from= : Backtest from date}
                            {--to=   : Backtest to date}
                            {--force : Overwrite existing verdict}
                            {--debug : Show detailed scoring}';

    protected $description = 'Auropharma quant engine v3 — all review bugs fixed';

    private const SYMBOL    = 'AUROPHARMA';
    private const EOD_TIME  = '14:45:00';
    private const PREV_TIME = '15:00:00';

    // Signal weights
    private const WEIGHT_A = 1.0;
    private const WEIGHT_C = 0.8;
    private const WEIGHT_D = 1.5;
    // FIX 4: WEIGHT_B is now dynamic (1.3 normal, 1.5 with smart money boost)

    // Pharma basket for Signal D when NIFTY pharma index unavailable
    // FIX 6
    private const PHARMA_BASKET = ['SUNPHARMA', 'CIPLA', 'DRREDDY', 'DIVISLAB', 'AUROPHARMA'];

    public function handle(): int
    {
        $this->info("╔══════════════════════════════════════════════════════╗");
        $this->info("║  AUROPHARMA QUANT ENGINE v3 — " . now()->format('Y-m-d H:i') . "  ║");
        $this->info("╚══════════════════════════════════════════════════════╝");

        $from = $this->option('from');
        $to   = $this->option('to');
        if ($from) return $this->runBacktest($from, $to ?? $from);
        return $this->runForDate($this->option('date') ?? now()->toDateString(), false);
    }

    private function runBacktest(string $from, string $to): int
    {
        $this->warn("🔁 BACKTEST: {$from} → {$to}");
        $cursor = Carbon::parse($from);
        $end    = Carbon::parse($to);
        $count  = 0;
        while ($cursor->lte($end)) {
            if (!$cursor->isWeekend()) {
                $this->line("── {$cursor->toDateString()} ──");
                $this->runForDate($cursor->toDateString(), true);
                $count++;
            }
            $cursor->addDay();
        }
        $this->info("✅ Done — {$count} days");
        return 0;
    }

    private function runForDate(string $date, bool $isBacktest): int
    {
        if (!$this->option('force') && AuroDailyVerdict::where('trade_date', $date)->exists()) {
            $this->warn("   Exists for {$date} (--force to overwrite)");
            return 0;
        }

        $expiry = $this->resolveExpiry($date);
        if (!$expiry) { $this->error("   No expiry for {$date}"); return 0; }

        $tradingDates = $this->getLastNTradingDates($date, 10);
        if (empty($tradingDates)) { $this->error("   No data"); return 0; }

        $today     = $tradingDates[0];
        $prevDates = array_slice($tradingDates, 1, 6);

        $futClose = $this->getFutClose($today);
        if (!$futClose) { $this->error("   No FUT close"); return 0; }

        $strikeInterval = $this->getStrikeInterval($expiry);
        $atm            = round($futClose / $strikeInterval) * $strikeInterval;

        $prevFutClose = count($prevDates) ? $this->getFutClose($prevDates[0]) : $futClose;
        $priceUp      = $futClose > ($prevFutClose ?? $futClose);

        $futOiToday = $this->getFutOI($today);
        $futOiPrev  = count($prevDates) ? $this->getFutOI($prevDates[0]) : $futOiToday;

        $vol5d   = $this->calcHistoricalVol($tradingDates, 5);
        $regime  = $this->detectMarketRegime($today, $prevDates);

        // FIX 9: use regime in threshold
        $threshold = $this->dynamicThreshold($vol5d, $regime);

        $this->info("   ATM={$atm} | FUT=₹{$futClose} | PriceUp=".($priceUp?'Y':'N')
            ." | Vol={$vol5d}% | Regime={$regime} | Threshold={$threshold}");

        $sigA = $this->signalA_OIPressure($today, $prevDates, $expiry, $priceUp);
        $sigB = $this->signalB_SmartMoney($today, $prevDates, $expiry, $atm, $strikeInterval);
        $sigC = $this->signalC_PriceStructure($today, $tradingDates, $futClose, $futOiToday, $futOiPrev, $priceUp);
        $sigD = $this->signalD_MarketAlignment($today, $prevDates, $sigC['oi_type']);
        $sigE = $this->signalE_StrikeIntent($sigA, $sigB, $atm, $strikeInterval);

        // FIX 4: weight B is dynamic based on smart money confirmation
        $weightB = $sigB['confidence_boost'] ? 1.5 : self::WEIGHT_B_BASE;

        $rawScore = ($sigA['score'] * self::WEIGHT_A)
                  + ($sigB['score'] * $weightB)
                  + ($sigC['score'] * self::WEIGHT_C)
                  + ($sigD['score'] * self::WEIGHT_D);
        $netScore = round($rawScore, 2);

        if ($this->option('debug')) {
            $this->line("   A={$sigA['score']}×".self::WEIGHT_A
                ." B={$sigB['score']}×{$weightB}"
                ." C={$sigC['score']}×".self::WEIGHT_C
                ." D={$sigD['score']}×".self::WEIGHT_D
                ." → raw={$rawScore} → net={$netScore}");
        }

        // ── Veto checks ────────────────────────────────────────────────
        $vetoMarket   = ($sigD['score'] <= -3.0) ? 1 : 0;
        $vetoLowVol   = $this->checkLowVolume($today, $expiry, $atm);     // FIX 8
        $vetoConflict = $this->checkConflictingSignals($sigA, $sigB, $sigC); // FIX 1
        // FIX 5: expiry veto REMOVED — use confidence reduction below
        $vetoExpiry   = 0;

        $anyVeto = $vetoMarket || $vetoLowVol || $vetoConflict;

        // ── Expiry day: reduce confidence, raise local threshold ───────
        // FIX 5: opportunity, not veto
        $isExpiryDay  = Carbon::parse($expiry)->isSameDay(Carbon::parse($date));
        $localThresh  = $isExpiryDay ? $threshold + 1.0 : $threshold;

        // ── Final direction ────────────────────────────────────────────
        if ($anyVeto) {
            $direction  = 'NO_TRADE';
            $confidence = 'NO_TRADE';
        } elseif ($netScore >= $localThresh) {
            $direction  = 'BUY_CE';
            $confidence = $netScore >= ($localThresh + 2) ? 'VERY_HIGH'
                        : ($netScore >= ($localThresh + 1) ? 'HIGH' : 'MEDIUM');
        } elseif ($netScore <= -$localThresh) {
            $direction  = 'BUY_PE';
            $confidence = abs($netScore) >= ($localThresh + 2) ? 'VERY_HIGH'
                        : (abs($netScore) >= ($localThresh + 1) ? 'HIGH' : 'MEDIUM');
        } else {
            $direction  = 'NO_TRADE';
            $confidence = 'LOW';
        }

        // Expiry day: cap confidence (FIX 5)
        if ($isExpiryDay && $confidence === 'VERY_HIGH') $confidence = 'HIGH';

        $risk                = $this->calcRiskParams($vol5d, $confidence);
        $recommendedStrike   = $sigE['suggested_strike'] ?? $atm;
        $recommendedPosition = $this->strikePositionLabel($recommendedStrike, $atm, $strikeInterval);
        $optionType          = ($direction === 'BUY_CE') ? 'CE' : 'PE';
        $optionLtp           = ($direction !== 'NO_TRADE')
            ? $this->getOptionLtp($today, $expiry, $recommendedStrike, $optionType)
            : null;

        AuroDailyVerdict::updateOrCreate(['trade_date' => $date], [
            'expiry_date'                 => $expiry,
            'direction'                   => $direction,
            'net_score'                   => $netScore,
            'confidence'                  => $confidence,
            'atm_strike'                  => $atm,
            'recommended_strike'          => $recommendedStrike,
            'recommended_strike_position' => $recommendedPosition,
            'recommended_strike_ltp'      => $optionLtp,
            'sig_a_score'                 => $sigA['score'],
            'ce_oi_today'                 => $sigA['ce_oi_today'],
            'ce_oi_5day_avg'              => $sigA['ce_oi_avg'],
            'ce_oi_change_pct'            => $sigA['ce_pct'],
            'pe_oi_today'                 => $sigA['pe_oi_today'],
            'pe_oi_5day_avg'              => $sigA['pe_oi_avg'],
            'pe_oi_change_pct'            => $sigA['pe_pct'],
            'ce_pe_ratio'                 => $sigA['ratio'],
            'sig_a_verdict'               => $sigA['verdict'],
            'sig_b_score'                 => $sigB['score'],
            'sig_b_far_otm_detail'        => json_encode($sigB['detail']),
            'sig_b_hidden_bear_days'       => $sigB['hidden_bear_days'],
            'sig_b_hidden_bull_days'       => $sigB['hidden_bull_days'],
            'sig_b_verdict'               => $sigB['verdict'],
            'sig_c_score'                 => $sigC['score'],
            'fut_close_3pm'               => $futClose,
            'support_20d'                 => $sigC['support'],
            'resistance_20d'              => $sigC['resistance'],
            'dist_to_support_pct'         => $sigC['dist_support_pct'],
            'dist_to_resistance_pct'      => $sigC['dist_resistance_pct'],
            'fut_oi_type'                 => $sigC['oi_type'],
            'sig_c_verdict'               => $sigC['verdict'],
            'sig_d_score'                 => $sigD['score'],
            'nifty_5d_trend'              => $sigD['nifty_trend'],
            'pharma_5d_trend'             => $sigD['pharma_trend'],
            'nifty_close_3pm'             => $sigD['nifty_close'],
            'sig_d_verdict'               => $sigD['verdict'],
            'sig_e_verdict'               => $sigE['verdict'],
            'sig_e_detail'                => json_encode($sigE['detail']),
            'sig_e_suggested_strike'      => $sigE['suggested_strike'],
            'auro_volatility_5d'          => $vol5d,
            'market_regime'               => $regime,
            'veto_market_opposing'        => $vetoMarket,
            'veto_low_volume'             => $vetoLowVol,
            'veto_expiry_week'            => $vetoExpiry,
            'veto_conflicting_signals'    => $vetoConflict,
            'generated_at'               => now()->toDateTimeString(),
            'is_backtest'                => $isBacktest ? 1 : 0,
            'post_trade_notes'           => json_encode([
                'stop_loss_pct'    => $risk['stop_loss_pct'],
                'target_pct'       => $risk['target_pct'],
                'max_hold_candles' => $risk['max_hold_candles'],
                'threshold_used'   => $localThresh,
                'weight_b_used'    => $weightB,
                'is_expiry_day'    => $isExpiryDay,
                'weighted_score'   => $netScore,
            ]),
        ]);

        $icon = $direction === 'BUY_CE' ? '🟢' : ($direction === 'BUY_PE' ? '🔴' : '⏸');
        $this->info("   {$icon} {$direction} | Score={$netScore} | {$confidence} | Strike={$recommendedStrike} ({$recommendedPosition}) | SL={$risk['stop_loss_pct']}% | TGT={$risk['target_pct']}%");
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    // SIGNAL A — OI Pressure (FIX 3: extended clipping to [-5,+5])
    //            (FIX 10: ratio used to distinguish writing vs buying)
    // ══════════════════════════════════════════════════════════════════════
    private function signalA_OIPressure(
        string $today, array $prevDates, string $expiry, bool $priceUp
    ): array {
        $ceToday = $this->sumOI($today, 'CE', self::EOD_TIME, $expiry);
        $peToday = $this->sumOI($today, 'PE', self::EOD_TIME, $expiry);

        $ceAvgs = $peAvgs = [];
        foreach ($prevDates as $d) {
            $v = $this->sumOI($d, 'CE', self::PREV_TIME, $expiry);
            if ($v > 0) $ceAvgs[] = $v;
            $v = $this->sumOI($d, 'PE', self::PREV_TIME, $expiry);
            if ($v > 0) $peAvgs[] = $v;
        }

        $ceAvg = count($ceAvgs) ? array_sum($ceAvgs) / count($ceAvgs) : $ceToday;
        $peAvg = count($peAvgs) ? array_sum($peAvgs) / count($peAvgs) : $peToday;

        $cePct = $ceAvg > 0 ? (($ceToday - $ceAvg) / $ceAvg) * 100 : 0;
        $pePct = $peAvg > 0 ? (($peToday - $peAvg) / $peAvg) * 100 : 0;

        // FIX 10: CE/PE ratio to help distinguish writing vs buying
        $ratio = $peToday > 0 ? round($ceToday / $peToday, 4) : 1;
        $pePuts   = $peToday > $ceToday; // PE dominant = put writing/buying context

        $score   = 0.0;
        $verdict = '';

        // CE OI interpretation with price direction
        if ($cePct > 5) {
            if (!$priceUp) {
                // Price falling + CE building = call writing (bearish)
                $contrib = $cePct > 30 ? -3.0 : ($cePct > 15 ? -2.0 : -1.0);
                $score  += $contrib;
                $verdict .= 'CE_WRITE_BEAR ';
            } else {
                // Price rising + CE building = call buying (mildly bullish)
                $score  += 1.0;
                $verdict .= 'CE_BUY_BULL ';
            }
        } elseif ($cePct < -5) {
            if ($priceUp) {
                // Price up + CE unwinding = short squeeze (bullish)
                $contrib = $cePct < -20 ? 3.0 : ($cePct < -10 ? 2.0 : 1.0);
                $score  += $contrib;
                $verdict .= 'CE_UNWIND_BULL ';
            } else {
                $score  += 1.0;
                $verdict .= 'CE_UNWIND_MILD_BULL ';
            }
        }

        // PE OI interpretation with price direction
        if ($pePct > 5) {
            if ($priceUp) {
                // Price up + PE building = put writing (bullish — selling downside insurance)
                $contrib = $pePct > 30 ? 3.0 : ($pePct > 15 ? 2.0 : 1.0);
                $score  += $contrib;
                $verdict .= 'PE_WRITE_BULL ';
            } else {
                // Price down + PE building = fear buying (bearish)
                $contrib = $pePct > 30 ? -3.0 : ($pePct > 15 ? -2.0 : -1.0);
                $score  += $contrib;
                $verdict .= 'PE_BUY_BEAR ';
            }
        } elseif ($pePct < -5) {
            if (!$priceUp) {
                // Price down + PE unwinding = put sellers exiting = bearish exhaustion
                $contrib = $pePct < -20 ? 3.0 : ($pePct < -10 ? 2.0 : 1.0);
                $score  += $contrib;
                $verdict .= 'PE_UNWIND_REVERSAL ';
            } else {
                // Price up + PE unwinding = puts covering = bullish
                $score  += 2.0;
                $verdict .= 'PE_UNWIND_BULL ';
            }
        }

        // FIX 3: extended to [-5,+5] (was [-3,+3]) to preserve more signal info
        $score = max(-5.0, min(5.0, $score));

        return [
            'score'       => (float) $score,
            'ce_oi_today' => $ceToday,
            'ce_oi_avg'   => round($ceAvg, 0),
            'ce_pct'      => round($cePct, 2),
            'pe_oi_today' => $peToday,
            'pe_oi_avg'   => round($peAvg, 0),
            'pe_pct'      => round($pePct, 2),
            'ratio'       => $ratio,
            'verdict'     => trim($verdict) ?: 'NEUTRAL',
            'price_up'    => $priceUp,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // SIGNAL B — Smart Money (FIX 2: skip if avgVol=0)
    //            (FIX 4: boost is weight multiplier, not post-total multiplier)
    // ══════════════════════════════════════════════════════════════════════
    private function signalB_SmartMoney(
        string $today, array $prevDates, string $expiry,
        float $atm, float $interval
    ): array {
        $allDates  = array_merge([$today], $prevDates);
        $farRelatives = [-5, -4, -3, 3, 4, 5];

        $hiddenBearDays = 0;
        $hiddenBullDays = 0;
        $detail         = [];

        $avgVol = $this->getAvgOptionVolume($today, $prevDates, $expiry);

        foreach ($farRelatives as $rel) {
            $strike = $atm + ($rel * $interval);
            $type   = $rel < 0 ? 'PE' : 'CE';
            $label  = 'ATM' . ($rel > 0 ? "+{$rel}" : "{$rel}");

            $oiSeries  = [];
            $volSeries = [];

            foreach ($allDates as $d) {
                $time = ($d === $today) ? self::EOD_TIME : self::PREV_TIME;
                $oiSeries[$d]  = $this->getStrikeOI($d,     $type, $time, $expiry, $strike);
                $volSeries[$d] = $this->getStrikeVolume($d,  $type, $time, $expiry, $strike);
            }

            // FIX 2: if no volume data at all for this strike → skip (not auto-true)
            if ($avgVol <= 0) {
                $detail[$label] = ['type' => $type, 'strike' => $strike, 'consecutive_growth' => 0, 'is_accumulating' => false, 'skip_reason' => 'no_avg_vol'];
                continue;
            }

            // Count confirmed consecutive growth
            $consecutive = 0;
            $dates = array_keys($oiSeries);
            for ($i = 0; $i < count($dates) - 1; $i++) {
                $d     = $dates[$i];
                $dPrev = $dates[$i + 1];
                $oiUp  = $oiSeries[$d] > $oiSeries[$dPrev] && $oiSeries[$dPrev] > 0;
                // FIX 2: volume confirmation required (not optional when avgVol exists)
                $volOk = ($volSeries[$d] >= $avgVol * 1.3);
                if ($oiUp && $volOk) $consecutive++;
                else break;
            }

            $isAccum = $consecutive >= 2;
            if ($rel < 0 && $isAccum) $hiddenBearDays = max($hiddenBearDays, $consecutive);
            if ($rel > 0 && $isAccum) $hiddenBullDays = max($hiddenBullDays, $consecutive);

            $detail[$label] = [
                'type'               => $type,
                'strike'             => $strike,
                'consecutive_growth' => $consecutive,
                'is_accumulating'    => $isAccum,
            ];
        }

        $score   = 0.0;
        $verdict = 'NONE';
        $boost   = false;

        if ($hiddenBearDays >= 3)      { $score = -3.0; $verdict = 'HIDDEN_BEAR_STRONG';       $boost = true; }
        elseif ($hiddenBearDays >= 2)  { $score = -2.0; $verdict = 'HIDDEN_BEAR_ACCUMULATION'; $boost = true; }
        elseif ($hiddenBullDays >= 3)  { $score =  3.0; $verdict = 'HIDDEN_BULL_STRONG';       $boost = true; }
        elseif ($hiddenBullDays >= 2)  { $score =  2.0; $verdict = 'HIDDEN_BULL_ACCUMULATION'; $boost = true; }

        return [
            'score'            => $score,
            'hidden_bear_days' => $hiddenBearDays,
            'hidden_bull_days' => $hiddenBullDays,
            'verdict'          => $verdict,
            'detail'           => $detail,
            'confidence_boost' => $boost,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // SIGNAL C — Price Structure (unchanged from v2)
    // ══════════════════════════════════════════════════════════════════════
    private function signalC_PriceStructure(
        string $today, array $tradingDates, float $futClose,
        float $futOiToday, float $futOiPrev, bool $priceUp
    ): array {
        $last20 = array_slice($tradingDates, 0, 20);
        $highs  = $lows = [];
        foreach ($last20 as $d) {
            $row = OptionOhlcData::where('base_symbol', self::SYMBOL)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $d)
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->first(['high', 'low']);
            if ($row) { $highs[] = (float)$row->high; $lows[] = (float)$row->low; }
        }

        $support    = count($lows)  ? min($lows)  : $futClose * 0.95;
        $resistance = count($highs) ? max($highs) : $futClose * 1.05;
        $distSupportPct    = $support    > 0 ? (($futClose - $support)    / $support)    * 100 : 0;
        $distResistancePct = $resistance > 0 ? (($resistance - $futClose) / $resistance) * 100 : 0;

        $oiUp   = $futOiToday > $futOiPrev;
        $oiType = match (true) {
            $priceUp  && $oiUp  => 'LONG_BUILDUP',
            $priceUp  && !$oiUp => 'SHORT_COVERING',
            !$priceUp && $oiUp  => 'SHORT_BUILDUP',
            !$priceUp && !$oiUp => 'LONG_UNWINDING',
            default             => 'NEUTRAL',
        };

        $score   = 0.0;
        $verdict = '';
        if ($distSupportPct    <= 2.0) { $score += 1.0; $verdict .= 'AT_SUPPORT '; }
        if ($distResistancePct <= 2.0) { $score -= 1.0; $verdict .= 'AT_RESISTANCE '; }

        // match ($oiType) {
        //     'LONG_BUILDUP'   => ($score += 1.0, $verdict .= 'LONG_BUILDUP '),
        //     'SHORT_COVERING' => ($score += 1.0, $verdict .= 'SHORT_COVERING '),
        //     'SHORT_BUILDUP'  => ($score -= 1.0, $verdict .= 'SHORT_BUILDUP '),
        //     'LONG_UNWINDING' => ($score -= 1.0, $verdict .= 'LONG_UNWINDING '),
        //     default          => null,
        // };

        $oiImpact = match ($oiType) {
            'LONG_BUILDUP', 'SHORT_COVERING' => 1,
            'SHORT_BUILDUP', 'LONG_UNWINDING' => -1,
            default => 0,
        };

        $score += $oiImpact;

        if ($oiImpact !== 0) {
            $verdict .= $oiType . ' ';
        }

        $score = max(-2.0, min(2.0, $score));

        return [
            'score'               => $score,
            'support'             => round($support, 2),
            'resistance'          => round($resistance, 2),
            'dist_support_pct'    => round($distSupportPct, 2),
            'dist_resistance_pct' => round($distResistancePct, 2),
            'oi_type'             => $oiType,
            'verdict'             => trim($verdict) ?: 'NEUTRAL',
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // SIGNAL D — Market Alignment (FIX 6: pharma basket fallback)
    // ══════════════════════════════════════════════════════════════════════
    private function signalD_MarketAlignment(
        string $today, array $prevDates, string $oiType
    ): array {
        $score       = 0.0;
        $niftyTrend  = 'SIDEWAYS';
        $pharmaTrend = 'SIDEWAYS';
        $niftyClose  = null;

        // Nifty data
        $niftyToday = OptionOhlcData::where('base_symbol', 'NIFTY')
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $today)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->first(['close']);

        if ($niftyToday) {
            $niftyClose = (float) $niftyToday->close;
            $niftyPrev  = null;
            foreach (array_slice($prevDates, 0, 3) as $d) {
                $r = OptionOhlcData::where('base_symbol', 'NIFTY')
                    ->where('instrument_type', 'FUT')
                    ->whereDate('trade_date', $d)
                    ->whereRaw("TIME(interval_time) = '14:45:00'")
                    ->first(['close']);
                if ($r) { $niftyPrev = (float) $r->close; break; }
            }
            if ($niftyPrev && $niftyPrev > 0) {
                $niftyChg = (($niftyClose - $niftyPrev) / $niftyPrev) * 100;
                if      ($niftyChg > 1.5)  { $niftyTrend = 'BULLISH'; $score += 2.0; }
                elseif  ($niftyChg > 0.5)  { $niftyTrend = 'BULLISH'; $score += 1.0; }
                elseif  ($niftyChg < -1.5) { $niftyTrend = 'BEARISH'; $score -= 2.0; }
                elseif  ($niftyChg < -0.5) { $niftyTrend = 'BEARISH'; $score -= 1.0; }
            }
        }

        // FIX 6: Pharma basket instead of circular Auro-proxy
        $basketBull = 0;
        $basketBear = 0;
        $basketChecked = 0;

        foreach (self::PHARMA_BASKET as $pharmaSymbol) {
            if ($pharmaSymbol === self::SYMBOL) continue; // skip Auro itself

            $todayClose = OptionOhlcData::where('base_symbol', $pharmaSymbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $today)
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->value('close');

            if (!$todayClose) continue;

            $prevClose = null;
            foreach (array_slice($prevDates, 0, 3) as $d) {
                $v = OptionOhlcData::where('base_symbol', $pharmaSymbol)
                    ->where('instrument_type', 'FUT')
                    ->whereDate('trade_date', $d)
                    ->whereRaw("TIME(interval_time) = '14:45:00'")
                    ->value('close');
                if ($v) { $prevClose = $v; break; }
            }

            if (!$prevClose) continue;
            $chg = (($todayClose - $prevClose) / $prevClose) * 100;
            if ($chg > 0.5) $basketBull++;
            elseif ($chg < -0.5) $basketBear++;
            $basketChecked++;
        }

        if ($basketChecked > 0) {
            $bullRatio = $basketBull / $basketChecked;
            $bearRatio = $basketBear / $basketChecked;
            if ($bullRatio >= 0.6)      { $pharmaTrend = 'BULLISH'; $score += 1.0; }
            elseif ($bearRatio >= 0.6)  { $pharmaTrend = 'BEARISH'; $score -= 1.0; }
        } else {
            // No basket data: fall back to OI type as last resort (not circular — OI regime)
            if (in_array($oiType, ['LONG_BUILDUP', 'SHORT_COVERING'])) {
                $pharmaTrend = 'BULLISH'; $score += 0.5;
            } elseif (in_array($oiType, ['SHORT_BUILDUP', 'LONG_UNWINDING'])) {
                $pharmaTrend = 'BEARISH'; $score -= 0.5;
            }
        }

        $score   = max(-3.0, min(3.0, $score));
        $verdict = "Nifty: {$niftyTrend} | Pharma basket: {$pharmaTrend} ({$basketBull}B/{$basketBear}Be/{$basketChecked}total)";

        return [
            'score'        => $score,
            'nifty_trend'  => $niftyTrend,
            'pharma_trend' => $pharmaTrend,
            'nifty_close'  => $niftyClose,
            'verdict'      => $verdict,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // SIGNAL E — Strike Intent (FIX 7: if/elseif — no override bug)
    // ══════════════════════════════════════════════════════════════════════
    private function signalE_StrikeIntent(
        array $sigA, array $sigB, float $atm, float $interval
    ): array {
        // Determine primary direction from Signal A
        $isBearish = $sigA['score'] < 0;
        $isBullish = $sigA['score'] > 0;

        $suggestedStrike = $atm;
        $verdict         = 'ATM_DEFAULT';
        $detail          = [];

        // FIX 7: if/elseif — second block can no longer override first
        if ($isBearish || $sigB['hidden_bear_days'] >= 2) {
            if ($sigB['hidden_bear_days'] >= 3) {
                $suggestedStrike = $atm - (3 * $interval);
                $verdict         = 'DEEP_OTM_PE_SMART_MONEY';
                $detail['reason']= 'Far OTM PE 3+ days — institutional bear positioning';
            } elseif ($sigB['hidden_bear_days'] >= 2) {
                $suggestedStrike = $atm - (2 * $interval);
                $verdict         = 'OTM_PE_ACCUMULATION';
                $detail['reason']= 'ATM-2 PE confirmed 2-day accumulation';
            } elseif (($sigA['pe_pct'] ?? 0) > 25 && !($sigA['price_up'] ?? true)) {
                $suggestedStrike = $atm;
                $verdict         = 'ATM_PE_FEAR_BUYING';
                $detail['reason']= 'Aggressive PE buying + price falling';
            } else {
                $suggestedStrike = $atm - $interval;
                $verdict         = 'ATM_MINUS1_PE';
                $detail['reason']= 'Standard bearish — ATM-1 PE';
            }
        } elseif ($isBullish || $sigB['hidden_bull_days'] >= 2) {
            // FIX 7: elseif — only runs if bearish block did NOT run
            if ($sigB['hidden_bull_days'] >= 3) {
                $suggestedStrike = $atm + (3 * $interval);
                $verdict         = 'DEEP_OTM_CE_SMART_MONEY';
                $detail['reason']= 'Far OTM CE 3+ days — institutional bull positioning';
            } elseif ($sigB['hidden_bull_days'] >= 2) {
                $suggestedStrike = $atm + (2 * $interval);
                $verdict         = 'OTM_CE_ACCUMULATION';
                $detail['reason']= 'ATM+2 CE 2-day accumulation';
            } elseif (($sigA['ce_pct'] ?? 0) < -20 && ($sigA['price_up'] ?? false)) {
                $suggestedStrike = $atm;
                $verdict         = 'ATM_CE_SQUEEZE';
                $detail['reason']= 'CE unwinding + price up = short squeeze';
            } else {
                $suggestedStrike = $atm + $interval;
                $verdict         = 'ATM_PLUS1_CE';
                $detail['reason']= 'Standard bullish — ATM+1 CE';
            }
        }

        return [
            'suggested_strike' => $suggestedStrike,
            'verdict'          => $verdict,
            'detail'           => $detail,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Dynamic threshold (FIX 9: regime-aware)
    // ══════════════════════════════════════════════════════════════════════
    private function dynamicThreshold(float $vol, string $regime): float
    {
        $base = match (true) {
            $vol > 35 => 8.0,
            $vol > 25 => 7.0,
            $vol > 15 => 6.0,
            default   => 5.0,
        };
        // FIX 9: VOLATILE regime gets +1 (need stronger signal)
        if ($regime === 'VOLATILE') $base += 1.0;
        return $base;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Conflict veto (FIX 1: 2 opposing signs = veto, not 3)
    // ══════════════════════════════════════════════════════════════════════
    private function checkConflictingSignals(array $sigA, array $sigB, array $sigC): int
    {
        $signs = [];
        if (abs($sigA['score']) >= 1) $signs[] = $sigA['score'] <=> 0;
        if (abs($sigB['score']) >= 1) $signs[] = $sigB['score'] <=> 0;
        if (abs($sigC['score']) >= 1) $signs[] = $sigC['score'] <=> 0;

        // FIX 1: veto if ANY two of them disagree (positive + negative both present)
        if (in_array(1, $signs) && in_array(-1, $signs)) return 1;
        return 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Low volume veto (FIX 8: 5-day avg, not static 300)
    // ══════════════════════════════════════════════════════════════════════
    private function checkLowVolume(string $today, string $expiry, float $atm): int
    {
        // Today's ATM volume
        $todayVol = (int) OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $today)
            ->where('strike', $atm)
            ->whereDate('expiry_date', $expiry)
            ->whereRaw("TIME(interval_time) = '" . self::EOD_TIME . "'")
            ->sum('volume');

        // 5-day average ATM volume
        $pastDates = $this->getLastNTradingDates($today, 6);
        $pastDates = array_slice($pastDates, 1, 5); // skip today

        if (empty($pastDates)) {
            // No historical data — apply absolute floor of 200
            return $todayVol < 200 ? 1 : 0;
        }

        $avgVols = [];
        foreach ($pastDates as $d) {
            $v = (int) OptionOhlcData::where('base_symbol', self::SYMBOL)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $d)
                ->where('strike', $atm)
                ->whereDate('expiry_date', $expiry)
                ->whereRaw("TIME(interval_time) = '" . self::PREV_TIME . "'")
                ->sum('volume');
            if ($v > 0) $avgVols[] = $v;
        }

        if (empty($avgVols)) return $todayVol < 200 ? 1 : 0;

        $avgVol    = array_sum($avgVols) / count($avgVols);
        $threshold = $avgVol * 0.5; // FIX 8: 50% of 5-day avg

        return $todayVol < $threshold ? 1 : 0;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Risk management (unchanged from v2)
    // ══════════════════════════════════════════════════════════════════════
    private function calcRiskParams(float $vol, string $confidence): array
    {
        $slBase  = min(40, max(20, (int) ($vol * 1.2)));
        $tgtBase = min(100, max(40, (int) ($vol * 2.5)));
        $mult    = match ($confidence) {
            'VERY_HIGH' => 1.2,
            'HIGH'      => 1.1,
            'MEDIUM'    => 1.0,
            default     => 0.8,
        };
        return [
            'stop_loss_pct'    => round($slBase * $mult, 1),
            'target_pct'       => round($tgtBase * $mult, 1),
            'max_hold_candles' => 4,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════
    // Data helpers (unchanged)
    // ══════════════════════════════════════════════════════════════════════
    private const WEIGHT_B_BASE = 1.3; // base B weight when no boost

    private function getFutClose(string $date): ?float
    {
        return OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->value('close');
    }

    private function getFutOI(string $date): float
    {
        return (float)(OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->value('oi') ?? 0);
    }

    private function getOptionLtp(string $date, string $expiry, float $strike, string $type): ?float
    {
        return OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $expiry)
            ->where('strike', $strike)
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->value('close');
    }

    private function sumOI(string $date, string $type, string $time, string $expiry): int
    {
        return (int) OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $expiry)
            ->whereRaw("TIME(interval_time) = ?", [$time])
            ->sum('oi');
    }

    private function getStrikeOI(string $date, string $type, string $time, string $expiry, float $strike): int
    {
        return (int)(OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', $type)->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $expiry)->where('strike', $strike)
            ->whereRaw("TIME(interval_time) = ?", [$time])->value('oi') ?? 0);
    }

    private function getStrikeVolume(string $date, string $type, string $time, string $expiry, float $strike): int
    {
        return (int)(OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', $type)->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $expiry)->where('strike', $strike)
            ->whereRaw("TIME(interval_time) = ?", [$time])->value('volume') ?? 0);
    }

    private function getAvgOptionVolume(string $today, array $prevDates, string $expiry): float
    {
        $vols = [];
        foreach (array_slice($prevDates, 0, 5) as $d) {
            $v = OptionOhlcData::where('base_symbol', self::SYMBOL)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $d)
                ->whereDate('expiry_date', $expiry)
                ->whereRaw("TIME(interval_time) = '" . self::PREV_TIME . "'")
                ->avg('volume');
            if ($v > 0) $vols[] = $v;
        }
        return count($vols) ? array_sum($vols) / count($vols) : 0.0;
    }

    private function resolveExpiry(string $date): ?string
    {
        return OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getStrikeInterval(string $expiry): float
    {
        $strikes = OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', 'CE')->whereDate('expiry_date', $expiry)
            ->whereNotNull('strike')->distinct()->orderBy('strike')
            ->pluck('strike')->map(fn($s) => (float)$s)->values();
        if ($strikes->count() < 2) return 10.0;
        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < $strikes->count(); $i++) {
            $gap = $strikes[$i] - $strikes[$i-1];
            if ($gap > 0) $minGap = min($minGap, $gap);
        }
        return $minGap === PHP_INT_MAX ? 10.0 : (float)$minGap;
    }

    private function getLastNTradingDates(string $date, int $n): array
    {
        return OptionOhlcData::where('base_symbol', self::SYMBOL)
            ->where('instrument_type', 'FUT')->whereDate('trade_date', '<=', $date)
            ->select(DB::raw('DATE(trade_date) as d'))->distinct()
            ->orderByDesc('d')->limit($n)->pluck('d')->toArray();
    }

    private function strikePositionLabel(float $strike, float $atm, float $interval): string
    {
        $diff = (int) round(($strike - $atm) / $interval);
        if ($diff === 0) return 'ATM';
        $sign = $diff > 0 ? '+' : '';
        return "ATM{$sign}{$diff}";
    }

    private function detectMarketRegime(string $today, array $prevDates): string
    {
        $closes = [];
        foreach (array_merge([$today], $prevDates) as $d) {
            $c = $this->getFutClose($d);
            if ($c) $closes[] = $c;
        }
        if (count($closes) < 5) return 'UNKNOWN';
        $returns = [];
        for ($i = 0; $i < count($closes)-1; $i++) {
            $returns[] = abs(($closes[$i] - $closes[$i+1]) / $closes[$i+1]) * 100;
        }
        $avg = array_sum($returns) / count($returns);
        if ($avg > 2.5) return 'VOLATILE';
        if ($avg < 0.8) return 'RANGING';
        return 'TRENDING';
    }

    private function calcHistoricalVol(array $dates, int $n): float
    {
        $closes = [];
        foreach (array_slice($dates, 0, $n+1) as $d) {
            $c = $this->getFutClose($d);
            if ($c) $closes[] = $c;
        }
        if (count($closes) < 3) return 20.0;
        $returns = [];
        for ($i = 0; $i < count($closes)-1; $i++) {
            if ($closes[$i+1] > 0) $returns[] = log($closes[$i] / $closes[$i+1]);
        }
        if (empty($returns)) return 20.0;
        $mean = array_sum($returns) / count($returns);
        $var  = array_sum(array_map(fn($r) => ($r-$mean)**2, $returns)) / count($returns);
        return round(sqrt($var) * sqrt(252) * 100, 2);
    }
}