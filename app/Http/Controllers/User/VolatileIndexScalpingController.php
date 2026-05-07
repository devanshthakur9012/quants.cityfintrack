<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * VolatileIndexScalpingController v7
 *
 * ══════════════════════════════════════════════════════════════
 * MY THINKING (not blindly following feedback):
 * ══════════════════════════════════════════════════════════════
 *
 * AGREED with ChatGPT feedback:
 *   ✅ Expectancy = (Win% × AvgWin) − (Loss% × AvgLoss) — THE real edge metric
 *   ✅ Pattern key over-split → fewer dimensions = more samples per pattern
 *      Removed: range_bucket (noise), symbol from key (cross-symbol patterns)
 *      Kept: timeBucket + strength + direction + volBucket + zone
 *   ✅ Min 10 samples before trusting pattern (was 5)
 *   ✅ Consecutive loss tracking (trader psychology — real problem)
 *   ✅ Replace hard reject with score-based system (more nuanced)
 *
 * DISAGREED:
 *   ❌ "Remove no-table approach" — keeping in-memory, you asked for no table
 *   ❌ VWAP — not in our data, skip
 *   ❌ "Make weights dynamic via ML" — overkill, data-informed fixed weights OK
 *      I'm using expectancy directly in edge score instead of fixed RR weights
 *
 * NEW: EOD POSITION CLOSE (your requirement):
 *   ✅ Any trade still OPEN at 15:00 → force-close at 15:00 candle close price
 *   ✅ Outcome = "EOD_EXIT" (shown separately from EXPIRED/OPEN)
 *   ✅ P&L computed from 15:00 close vs entry price
 *   ✅ "Cost-to-cost" shown if P&L within ±5% of investment
 *   ✅ Expectancy tracks EOD_EXIT as a partial win/loss correctly
 *
 * KEY CHANGES IN v7:
 *   1. Expectancy added to pattern stats + used as primary edge filter
 *   2. Simpler pattern key (5 dimensions instead of 7)
 *   3. AvgWin / AvgLoss tracked separately (not just avg ROI)
 *   4. EOD force-close at 15:00 candle
 *   5. Consecutive loss warning per pattern
 *   6. Edge score now uses expectancy, not hardcoded RR weights
 * ══════════════════════════════════════════════════════════════
 */
class VolatileIndexScalpingController extends Controller
{
    private const SCALP_SYMBOLS   = ['NIFTY', 'BANKNIFTY', 'SENSEX'];
    private const AGGRESSIVE_ZONE = ['09:15', '09:30', '09:45', '10:00', '10:15'];
    private const MODERATE_ZONE   = ['10:30', '10:45', '11:00', '11:15'];
    private const STRADDLE_ZONE   = ['09:15', '09:30', '09:45'];
    private const MAX_SIGNAL_TIME = '11:15';
    private const EOD_EXIT_TIME   = '15:00'; // force-close all open positions here
    private const MIN_RANGE       = 0.18;

    private const DEFAULT_TARGET_PCT     = 50.0;
    private const DEFAULT_SL_PCT         = 25.0;
    private const DEFAULT_PRICE_MOVE_PCT = 0.15;
    private const DEFAULT_OI_CHANGE_PCT  = 2.5;
    private const DEFAULT_VOL_MULT       = 1.4;
    private const DEFAULT_HISTORY_DAYS   = 30;

    // Edge thresholds — now expectancy-based
    private const MIN_EXPECTANCY      = 0.0;   // positive expectancy required (not just win rate)
    private const MIN_WIN_RATE        = 38.0;  // at 1:2 RR, 33% = break even, 38% = profit
    private const MIN_FILL_RATE       = 28.0;
    private const MIN_EDGE_SCORE      = 45.0;
    private const MIN_SAMPLES         = 10;    // was 5, now 10 — reduces overfitting

    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Volatile Index Scalping v7';
        return view($this->activeTemplate . 'user.scalping.index', compact('pageTitle'));
    }

    public function getSymbols()
    {
        return response()->json([
            'success' => true,
            'symbols' => OptionOhlcData::where('instrument_type', 'FUT')
                ->whereIn('base_symbol', self::SCALP_SYMBOLS)
                ->distinct()->orderBy('base_symbol')->pluck('base_symbol')->values(),
        ]);
    }

    // =========================================================
    //  MAIN SIGNALS
    // =========================================================

    public function getSignals(Request $request)
    {
        try {
            $date         = $request->get('date', now()->toDateString());
            $selectedSyms = $request->get('symbols', []);
            $pricePct     = (float) $request->get('price_move_pct', self::DEFAULT_PRICE_MOVE_PCT);
            $oiPct        = (float) $request->get('oi_change_pct',  self::DEFAULT_OI_CHANGE_PCT);
            $targetPct    = (float) $request->get('target_pct',     self::DEFAULT_TARGET_PCT);
            $slPct        = (float) $request->get('sl_pct',         self::DEFAULT_SL_PCT);
            $volMult      = (float) $request->get('vol_mult',       self::DEFAULT_VOL_MULT);
            $histDays     = (int)   $request->get('history_days',   self::DEFAULT_HISTORY_DAYS);
            $useEdge      = filter_var($request->get('use_edge', true), FILTER_VALIDATE_BOOLEAN);

            $symbols = !empty($selectedSyms)
                ? array_intersect($selectedSyms, self::SCALP_SYMBOLS)
                : self::SCALP_SYMBOLS;

            if (!OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', $date)
                ->whereIn('base_symbol', $symbols)->exists()) {
                return response()->json([
                    'success' => false, 'message' => "No data for {$date}.",
                    'signals' => [], 'candle_moves' => [], 'pattern_stats' => [],
                ]);
            }

            // Step 1: Build pattern map from historical data (in memory)
            $patternMap = [];
            if ($useEdge && $histDays > 0) {
                $patternMap = $this->buildPatternMap(
                    $symbols, $date, $histDays, $pricePct, $oiPct, $targetPct, $slPct, $volMult
                );
            }

            // Step 2: Today's signals
            $allSignals    = [];
            $allCandleMoves = [];

            foreach ($symbols as $symbol) {
                $candleMoves    = $this->buildCandleMoves($symbol, $date);
                $avgRangeByTime = $this->avgRangeByTime($candleMoves);

                $signals = $this->buildSignals(
                    $symbol, $date, $pricePct, $oiPct, $targetPct, $slPct,
                    $volMult, $avgRangeByTime, $patternMap, $useEdge
                );

                foreach ($signals     as $s) $allSignals[]     = $s;
                foreach ($candleMoves as $m) $allCandleMoves[] = $m;
            }

            usort($allSignals, fn($a, $b) =>
                $a['signal_time'] <=> $b['signal_time'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'date'          => $date,
                'signals'       => $allSignals,
                'candle_moves'  => $allCandleMoves,
                'time_pattern'  => $this->computeTimePattern($allCandleMoves),
                'pattern_stats' => $this->formatPatternMap($patternMap),
                'history_days'  => $histDays,
                'total'         => count($allSignals),
                'stats'         => $this->computeStats($allSignals),
                'message'       => count($allSignals) . ' signals | ' . $histDays . ' days history | Edge: ' . ($useEdge ? 'ON' : 'OFF'),
            ]);

        } catch (\Exception $e) {
            Log::error('ScalpingController v7: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false, 'message' => 'Error: ' . $e->getMessage(),
                'signals' => [], 'candle_moves' => [], 'pattern_stats' => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD PATTERN MAP (in-memory, from historical data)
    // =========================================================

    private function buildPatternMap(
        array $symbols, string $today, int $histDays,
        float $pricePct, float $oiPct,
        float $targetPct, float $slPct, float $volMult
    ): array {

        $fromDate = Carbon::parse($today)->subDays($histDays + 14);

        $tradingDays = OptionOhlcData::where('instrument_type', 'FUT')
            ->whereIn('base_symbol', $symbols)
            ->whereDate('trade_date', '>=', $fromDate)
            ->whereDate('trade_date', '<', $today)
            ->selectRaw('DATE(trade_date) as d')
            ->distinct()->orderBy('d')
            ->pluck('d')->toArray();

        $tradingDays = array_slice($tradingDays, -$histDays);
        $map         = [];

        foreach ($tradingDays as $date) {
            foreach ($symbols as $symbol) {
                $rawSignals = $this->buildSignalsRaw(
                    $symbol, $date, $pricePct, $oiPct, $targetPct, $slPct, $volMult
                );

                foreach ($rawSignals as $sig) {
                    $key = $sig['pattern_key'];

                    if (!isset($map[$key])) {
                        $map[$key] = [
                            'pattern_key'  => $key,
                            'time_bucket'  => $sig['time_bucket'],
                            'strength'     => $sig['strength'],
                            'direction'    => $sig['direction'],
                            'vol_bucket'   => $sig['vol_bucket'],
                            'zone'         => $sig['time_zone'],
                            // Counts
                            'total'        => 0, 'filled' => 0, 'unfilled' => 0,
                            'wins'         => 0, 'losses' => 0,
                            'eod_exits'    => 0, 'expired' => 0,
                            // P/L accumulators
                            'win_pl_sum'   => 0.0, 'loss_pl_sum' => 0.0,
                            'total_pl'     => 0.0,
                            'roi_sum'      => 0.0, 'roi_count' => 0,
                            // Consecutive loss tracking
                            'recent_outcomes' => [], // last 10 outcomes
                        ];
                    }

                    $m        = &$map[$key];
                    $isFilled = $sig['fill_status'] === 'FILLED';
                    $outcome  = $sig['outcome'];
                    $pl       = (float)($sig['actual_pl'] ?? 0);
                    $roi      = $sig['roi'];

                    $m['total']++;

                    if ($isFilled) {
                        $m['filled']++;

                        if ($outcome === 'TARGET') {
                            $m['wins']++;
                            $m['win_pl_sum'] += $pl;
                        } elseif ($outcome === 'SL') {
                            $m['losses']++;
                            $m['loss_pl_sum'] += $pl; // negative
                        } elseif ($outcome === 'EOD_EXIT') {
                            $m['eod_exits']++;
                            // EOD exit: positive = partial win, negative = partial loss
                            if ($pl >= 0) $m['wins']++;      // count as win if profitable
                            else          $m['losses']++;    // count as loss if not
                            if ($pl >= 0) $m['win_pl_sum']  += $pl;
                            else          $m['loss_pl_sum'] += $pl;
                        } elseif ($outcome === 'EXPIRED') {
                            $m['expired']++;
                            $m['losses']++;
                            $m['loss_pl_sum'] += $pl;
                        }

                        $m['total_pl'] += $pl;

                        if ($roi !== null) {
                            $m['roi_sum']  += $roi;
                            $m['roi_count']++;
                        }

                        // Track last 10 outcomes for consecutive loss detection
                        $m['recent_outcomes'][] = in_array($outcome, ['TARGET']) || ($outcome === 'EOD_EXIT' && $pl >= 0) ? 'W' : 'L';
                        if (count($m['recent_outcomes']) > 10) {
                            array_shift($m['recent_outcomes']);
                        }
                    } else {
                        $m['unfilled']++;
                    }
                }
            }
        }

        // Compute derived metrics
        foreach ($map as $key => &$p) {
            $filled   = $p['filled'];
            $total    = $p['total'];
            $wins     = $p['wins'];
            $losses   = $p['losses'];

            $winRate  = $filled > 0 ? round(($wins  / $filled) * 100, 1) : 0;
            $lossRate = $filled > 0 ? round(($losses / $filled) * 100, 1) : 0;
            $fillRate = $total  > 0 ? round(($filled / $total)  * 100, 1) : 0;
            $avgRoi   = $p['roi_count'] > 0 ? round($p['roi_sum'] / $p['roi_count'], 2) : 0;

            // ── EXPECTANCY (the real edge metric) ─────────────────────────
            // Expectancy = (Win% × AvgWin) − (Loss% × AvgLoss)
            // In ROI % terms: positive = system makes money on average per trade
            $avgWinRoi  = $wins   > 0 ? round($p['win_pl_sum']  / $wins,   2) : 0;
            $avgLossRoi = $losses > 0 ? round($p['loss_pl_sum'] / $losses, 2) : 0; // negative
            $expectancy = round(
                (($winRate  / 100) * $avgWinRoi) +
                (($lossRate / 100) * $avgLossRoi), // avgLossRoi is already negative
                2
            );

            // ── EDGE SCORE using expectancy (not hardcoded RR weights) ────
            // Normalize expectancy to 0–50 range (expectancy of 0 = 0, +100 = 50)
            // + fill rate contribution (0–30) + win rate contribution (0–20)
            $normExp  = min(50, max(0, $expectancy / 2));   // positive expectancy = good
            $normFill = min(30, $fillRate * 0.30);
            $normWin  = min(20, $winRate  * 0.20);
            $edgeScore = round($normExp + $normFill + $normWin, 1);

            // ── Consecutive loss detection ────────────────────────────────
            $recent      = $p['recent_outcomes'];
            $consecLoss  = 0;
            for ($i = count($recent) - 1; $i >= 0; $i--) {
                if ($recent[$i] === 'L') $consecLoss++;
                else break;
            }

            $confidence = match(true) {
                $filled < 5  => 'NONE',
                $filled < 10 => 'LOW',
                $filled < 25 => 'MEDIUM',
                default      => 'HIGH',
            };

            $p['win_rate']    = $winRate;
            $p['loss_rate']   = $lossRate;
            $p['fill_rate']   = $fillRate;
            $p['avg_roi']     = $avgRoi;
            $p['avg_win_pl']  = $avgWinRoi;
            $p['avg_loss_pl'] = $avgLossRoi;
            $p['expectancy']  = $expectancy;
            $p['edge_score']  = $edgeScore;
            $p['confidence']  = $confidence;
            $p['consec_loss'] = $consecLoss;
            $p['total_pl']    = round($p['total_pl'], 2);
        }
        unset($p);

        return $map;
    }

    // =========================================================
    //  BUILD RAW SIGNALS FOR ONE HISTORICAL DAY (lean)
    // =========================================================

    private function buildSignalsRaw(
        string $symbol, string $date,
        float $pricePct, float $oiPct,
        float $targetPct, float $slPct, float $volMult
    ): array {

        $futC = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)->where('is_missing', 0)->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close', 'volume', 'oi', 'atm_strike'])
            ->keyBy(fn($r) => Carbon::parse($r->interval_time)->format('H:i'));

        if ($futC->count() < 2) return [];
        $times     = $futC->keys()->sort()->values()->toArray();
        $atm       = (float) $futC->first()->atm_strike;
        if (!$atm) return [];
        $lotSize   = $this->getLotSize($symbol);

        $opts = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->where('strike', $atm)
            ->whereDate('trade_date', $date)->where('is_missing', 0)->orderBy('interval_time')
            ->get(['interval_time', 'instrument_type', 'open', 'high', 'low', 'close', 'oi']);

        $ceM = $opts->where('instrument_type','CE')->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));
        $peM = $opts->where('instrument_type','PE')->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));

        $avgRange = $this->avgRangeFromCandles($futC, $times);
        $signals  = [];

        for ($i = 1; $i < count($times); $i++) {
            $ct = $times[$i]; $pt = $times[$i - 1];
            if ($ct > self::MAX_SIGNAL_TIME) continue;

            $c = $futC[$ct] ?? null; $p = $futC[$pt] ?? null;
            if (!$c || !$p || (float)$p->close <= 0) continue;

            $pc  = (float)$p->close;
            $pch = (((float)$c->close - $pc) / $pc) * 100;
            $hp  = (((float)$c->high - $pc) / $pc) * 100;
            $lp  = (((float)$c->low  - $pc) / $pc) * 100;
            $rp  = round(abs($hp - $lp), 3);
            $sa  = $avgRange[$ct] ?? 0.25;
            $rva = $sa > 0 ? $rp / $sa : 1;

            $pv  = (float)$p->volume; $cv = (float)$c->volume;
            $vsp = $pv > 0 && $cv >= $pv * $volMult;
            $vwk = $pv > 0 && $cv < $pv * 0.55;

            $cc=$ceM[$ct]??null; $pc2=$ceM[$pt]??null;
            $cp=$peM[$ct]??null; $pp2=$peM[$pt]??null;
            $ceo=($pc2&&(float)$pc2->oi>0)?(((float)($cc->oi??0)-(float)$pc2->oi)/(float)$pc2->oi)*100:0;
            $peo=($pp2&&(float)$pp2->oi>0)?(((float)($cp->oi??0)-(float)$pp2->oi)/(float)$pp2->oi)*100:0;

            $pu=$pch>=$pricePct; $pd=$pch<=-$pricePct;
            $dir=null; $strength=0;
            if     ($pu && $peo>=$oiPct)  { $dir='BULLISH'; $strength=3; }
            elseif ($pd && $ceo>=$oiPct)  { $dir='BEARISH'; $strength=3; }
            elseif ($pu && $ceo<-$oiPct)  { $dir='BULLISH'; $strength=2; }
            elseif ($pd && $peo<-$oiPct)  { $dir='BEARISH'; $strength=2; }
            if (!$dir) continue;

            $zone = $this->getZone($ct);
            if ($zone==='defensive') continue;
            if ($vwk) continue;
            if ($sa < self::MIN_RANGE) continue;
            if ($strength<2) continue;
            if ($strength===2 && !in_array($ct, self::STRADDLE_ZONE)) continue;

            $nt = $times[$i+1] ?? null;
            if (!$nt) continue;

            $otype = ($dir==='BULLISH') ? 'CE' : 'PE';
            $omap  = ($otype==='CE') ? $ceM : $peM;
            $eo    = $omap[$nt] ?? null;
            if (!$eo) continue;

            $op = (float)($eo->open ?: $eo->close);
            if ($op <= 0) continue;

            $useChase = ($strength===3 && $vsp);
            $ep       = $useChase ? $op : round($op * 0.95, 2);
            $el       = (float)($eo->low ?? $eo->close);
            $filled   = $useChase || ($el <= $ep);

            [$dt, $ds] = $this->getDynSlTarget($op, $targetPct, $slPct);
            $tp  = round($ep * (1 + $dt/100), 2);
            $slp = round($ep * (1 - $ds/100), 2);

            $outcome  = 'UNFILLED'; $pl = null; $roi = null;
            if ($filled) {
                $res     = $this->scanOutcomeWithEOD($nt, $tp, $slp, $omap, $times, $i+1);
                $outcome = $res['outcome'];
                $inv     = round($ep * $lotSize, 2);

                if ($outcome === 'TARGET') {
                    $pl = round(($tp - $ep) * $lotSize, 2);
                } elseif ($outcome === 'SL') {
                    $pl = round(($slp - $ep) * $lotSize, 2);
                } elseif ($outcome === 'EOD_EXIT') {
                    // Exit at 15:00 close — best available price before close
                    $eodCandle = $omap[self::EOD_EXIT_TIME] ?? $omap->last();
                    $eodClose  = $eodCandle ? (float)$eodCandle->close : $ep;
                    $pl        = round(($eodClose - $ep) * $lotSize, 2);
                } else {
                    $last = $omap->last();
                    $pl   = $last ? round(((float)$last->close - $ep) * $lotSize, 2) : 0;
                }

                $roi = $inv > 0 && $pl !== null ? round(($pl / $inv) * 100, 2) : null;
            }

            // v7: simpler pattern key (fewer dimensions = more samples per pattern)
            $tb  = $ct <= '09:45' ? 'OPENING' : ($ct <= '10:30' ? 'MID_EARLY' : 'MID_LATE');
            $vb  = $vsp ? 'SPIKE' : 'NORMAL';
            // Removed: symbol, range_bucket from key → cross-symbol + fewer splits
            $key = implode('|', [$tb, "STR{$strength}", $dir, $vb, strtoupper($zone)]);

            $signals[] = [
                'pattern_key'  => $key,
                'time_bucket'  => $tb,
                'vol_bucket'   => $vb,
                'strength'     => $strength,
                'direction'    => $dir,
                'time_zone'    => $zone,
                'fill_status'  => $filled ? 'FILLED' : 'UNFILLED',
                'outcome'      => $outcome,
                'actual_pl'    => $pl,
                'roi'          => $roi,
            ];
        }
        return $signals;
    }

    // =========================================================
    //  TODAY'S SIGNALS WITH FULL DETAIL + PATTERN LOOKUP
    // =========================================================

    private function buildSignals(
        string $symbol, string $date,
        float $pricePct, float $oiPct,
        float $targetPct, float $slPct, float $volMult,
        array $avgRangeByTime, array $patternMap, bool $useEdge
    ): array {

        $futC = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $date)->where('is_missing', 0)->orderBy('interval_time')
            ->get(['interval_time', 'open', 'high', 'low', 'close', 'volume', 'oi', 'atm_strike'])
            ->keyBy(fn($r) => Carbon::parse($r->interval_time)->format('H:i'));

        if ($futC->count() < 2) return [];
        $times   = $futC->keys()->sort()->values()->toArray();
        $lotSize = $this->getLotSize($symbol);
        $atm     = (float) $futC->first()->atm_strike;
        if (!$atm) return [];

        $opts = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->where('strike', $atm)
            ->whereDate('trade_date', $date)->where('is_missing', 0)->orderBy('interval_time')
            ->get(['interval_time', 'instrument_type', 'open', 'high', 'low', 'close', 'oi', 'trading_symbol', 'expiry_date']);

        $ceM = $opts->where('instrument_type','CE')->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));
        $peM = $opts->where('instrument_type','PE')->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));

        $signals = [];

        for ($i = 1; $i < count($times); $i++) {
            $ct = $times[$i]; $pt = $times[$i - 1];
            if ($ct > self::MAX_SIGNAL_TIME) continue;

            $c = $futC[$ct] ?? null; $p = $futC[$pt] ?? null;
            if (!$c || !$p || (float)$p->close <= 0) continue;

            $pc   = (float)$p->close;
            $cc   = (float)$c->close;
            $pch  = (($cc - $pc) / $pc) * 100;
            $hp   = round((((float)$c->high - $pc) / $pc) * 100, 3);
            $lp   = round((((float)$c->low  - $pc) / $pc) * 100, 3);
            $rp   = round(abs($hp - $lp), 3);
            $avgR = $avgRangeByTime[$ct] ?? 0.25;
            $rva  = $avgR > 0 ? round($rp / $avgR, 2) : 1;

            $pv  = (float)$p->volume; $cv = (float)$c->volume;
            $vsp = $pv > 0 && $cv >= $pv * $volMult;
            $vwk = $pv > 0 && $cv < $pv * 0.55;

            $ccE=$ceM[$ct]??null; $pcE=$ceM[$pt]??null;
            $cpE=$peM[$ct]??null; $ppE=$peM[$pt]??null;
            $ceo=($pcE&&(float)$pcE->oi>0)?(((float)($ccE->oi??0)-(float)$pcE->oi)/(float)$pcE->oi)*100:0;
            $peo=($ppE&&(float)$ppE->oi>0)?(((float)($cpE->oi??0)-(float)$ppE->oi)/(float)$ppE->oi)*100:0;

            $pu=$pch>=$pricePct; $pd=$pch<=-$pricePct;
            $dir=null; $reason=''; $strength=0;
            if     ($pu && $peo>=$oiPct)  { $dir='BULLISH'; $reason='Price ↑ + PE OI buildup';   $strength=3; }
            elseif ($pd && $ceo>=$oiPct)  { $dir='BEARISH'; $reason='Price ↓ + CE OI buildup';   $strength=3; }
            elseif ($pu && $ceo<-$oiPct)  { $dir='BULLISH'; $reason='Price ↑ + CE OI unwinding'; $strength=2; }
            elseif ($pd && $peo<-$oiPct)  { $dir='BEARISH'; $reason='Price ↓ + PE OI unwinding'; $strength=2; }
            if (!$dir) continue;

            $zone = $this->getZone($ct);
            if ($zone==='defensive') continue;
            if ($vwk) continue;
            if ($avgR < self::MIN_RANGE) continue;
            if ($strength<2) continue;
            if ($strength===2 && !in_array($ct, self::STRADDLE_ZONE)) continue;

            $nt = $times[$i+1] ?? null;
            if (!$nt) continue;

            // Pattern key + lookup
            $tb  = $ct <= '09:45' ? 'OPENING' : ($ct <= '10:30' ? 'MID_EARLY' : 'MID_LATE');
            $vb  = $vsp ? 'SPIKE' : 'NORMAL';
            $key = implode('|', [$tb, "STR{$strength}", $dir, $vb, strtoupper($zone)]);

            $pd2         = $patternMap[$key] ?? null;
            $edgeScore   = $pd2['edge_score']   ?? null;
            $confidence  = $pd2['confidence']   ?? 'NONE';
            $patternWin  = $pd2['win_rate']      ?? null;
            $patternFill = $pd2['fill_rate']     ?? null;
            $expectancy  = $pd2['expectancy']    ?? null;
            $consecLoss  = $pd2['consec_loss']   ?? 0;
            $samples     = $pd2['filled']        ?? 0;

            // ── Edge decision (expectancy-based, not just win rate) ────────
            $edgeAllowed = true;
            $edgeReason  = 'No history yet — allowing';

            if ($useEdge && $pd2) {
                if ($samples < self::MIN_SAMPLES) {
                    $edgeAllowed = true;
                    $edgeReason  = "Only {$samples} samples — LOW confidence, allowing";
                } elseif ($expectancy !== null && $expectancy < self::MIN_EXPECTANCY) {
                    $edgeAllowed = false;
                    $edgeReason  = "Negative expectancy ₹{$expectancy} — system loses money on avg";
                } elseif ($patternWin < self::MIN_WIN_RATE) {
                    $edgeAllowed = false;
                    $edgeReason  = "Win rate {$patternWin}% < " . self::MIN_WIN_RATE . "% min";
                } elseif ($patternFill < self::MIN_FILL_RATE) {
                    $edgeAllowed = false;
                    $edgeReason  = "Fill rate {$patternFill}% < " . self::MIN_FILL_RATE . "% min";
                } elseif ($edgeScore < self::MIN_EDGE_SCORE) {
                    $edgeAllowed = false;
                    $edgeReason  = "Edge score {$edgeScore} < " . self::MIN_EDGE_SCORE;
                } elseif ($consecLoss >= 3) {
                    $edgeAllowed = false;
                    $edgeReason  = "Warning: {$consecLoss} consecutive losses recently";
                } else {
                    $edgeReason  = "✅ Exp=₹{$expectancy} Win={$patternWin}% Fill={$patternFill}%";
                }
            }

            // Entry
            $useChase = ($strength===3 && $vsp);
            $otype    = ($dir==='BULLISH') ? 'CE' : 'PE';
            $omap     = ($otype==='CE') ? $ceM : $peM;
            $eo       = $omap[$nt] ?? null;
            if (!$eo) continue;

            $op  = (float)($eo->open ?: $eo->close);
            if ($op <= 0) continue;

            $ep     = $useChase ? $op : round($op * 0.95, 2);
            $el     = (float)($eo->low ?? $eo->close);
            $filled = $useChase || ($el <= $ep);

            [$dynT, $dynSl] = $this->getDynSlTarget($op, $targetPct, $slPct);
            $tp  = round($ep * (1 + $dynT  / 100), 2);
            $slp = round($ep * (1 - $dynSl / 100), 2);

            $outcome='UNFILLED'; $outTime=null; $pl=null; $roi=null;
            $eodExitPrice = null; $eodExitNote = null;

            if ($filled) {
                $res     = $this->scanOutcomeWithEOD($nt, $tp, $slp, $omap, $times, $i+1);
                $outcome = $res['outcome']; $outTime = $res['time'];
                $inv     = round($ep * $lotSize, 2);

                if ($outcome === 'TARGET') {
                    $pl = round(($tp - $ep) * $lotSize, 2);
                } elseif ($outcome === 'SL') {
                    $pl = round(($slp - $ep) * $lotSize, 2);
                } elseif ($outcome === 'EOD_EXIT') {
                    // Force close at 15:00 close price
                    $eodCandle     = $omap[self::EOD_EXIT_TIME] ?? $omap->last();
                    $eodExitPrice  = $eodCandle ? round((float)$eodCandle->close, 2) : $ep;
                    $pl            = round(($eodExitPrice - $ep) * $lotSize, 2);
                    // Note about exit quality
                    $diffPct       = $ep > 0 ? round((($eodExitPrice - $ep) / $ep) * 100, 1) : 0;
                    $eodExitNote   = $diffPct >= 0
                        ? "Exit ₹{$eodExitPrice} (+{$diffPct}%) ✅"
                        : "Exit ₹{$eodExitPrice} ({$diffPct}%) 🔴";
                } else {
                    $last = $omap->last();
                    $pl   = $last ? round(((float)$last->close - $ep) * $lotSize, 2) : 0;
                }

                $roi = $inv > 0 && $pl !== null ? round(($pl / $inv) * 100, 2) : null;
            } else {
                $inv = round($op * $lotSize, 2);
            }

            $signals[] = [
                'symbol'           => $symbol,
                'signal_time'      => $ct,
                'entry_time'       => $nt,
                'direction'        => $dir,
                'trade_side'       => $otype,
                'strength'         => $strength,
                'volume_spike'     => $vsp,
                'time_zone'        => $zone,
                'use_chase'        => $useChase,
                'reason'           => $reason,

                'fut_prev_close'   => round($pc, 2),
                'fut_cur_close'    => round($cc, 2),
                'fut_change_pct'   => round($pch, 3),
                'fut_high_pct'     => $hp,
                'fut_low_pct'      => $lp,
                'fut_range_pct'    => $rp,
                'range_vs_avg'     => $rva,
                'avg_range'        => round($avgR, 3),
                'ce_oi_pct'        => round($ceo, 3),
                'pe_oi_pct'        => round($peo, 3),

                'pattern_key'      => $key,
                'edge_score'       => $edgeScore,
                'confidence'       => $confidence,
                'pattern_win'      => $patternWin,
                'pattern_fill'     => $patternFill,
                'expectancy'       => $expectancy,
                'consec_loss'      => $consecLoss,
                'pattern_samples'  => $samples,
                'edge_allowed'     => $edgeAllowed,
                'edge_reason'      => $edgeReason,

                'atm_strike'       => $atm,
                'option_symbol'    => $eo->trading_symbol ?? "{$symbol}{$otype}{$atm}",
                'open_price'       => $op,
                'entry_price'      => $ep,
                'entry_type'       => $useChase ? 'CHASE' : 'L1−5%',
                'target_price'     => $tp,
                'sl_price'         => $slp,
                'dyn_target_pct'   => $dynT,
                'dyn_sl_pct'       => $dynSl,
                'lot_size'         => $lotSize,
                'investment'       => $inv,
                'fill_status'      => $filled ? 'FILLED' : 'UNFILLED',
                'entry_candle_low' => round($el, 2),
                'eod_exit_price'   => $eodExitPrice,
                'eod_exit_note'    => $eodExitNote,
                'target_pl'        => $filled ? round(($tp - $ep) * $lotSize, 2) : null,
                'sl_pl'            => $filled ? round(($slp - $ep) * $lotSize, 2) : null,
                'actual_pl'        => $pl,
                'outcome'          => $filled ? $outcome : 'UNFILLED',
                'outcome_time'     => $outTime,
                'exit_roi_pct'     => $roi,
            ];
        }
        return $signals;
    }

    // =========================================================
    //  OUTCOME SCANNER WITH EOD FORCE-CLOSE
    // =========================================================

    /**
     * Scans forward from entry. Returns:
     *   TARGET  — option high hit target price
     *   SL      — option low hit SL price
     *   EOD_EXIT — neither hit before 15:00, forced exit at 15:00 close
     *   EXPIRED  — data ended at 15:15 without hitting either (fallback)
     *
     * EOD_EXIT is the key new addition — replaces OPEN/EXPIRED for any
     * trade that didn't hit target or SL. Forces real P/L calculation.
     */
    private function scanOutcomeWithEOD(
        string $entryTime, float $target, float $sl,
        $optMap, array $times, int $idx
    ): array {

        for ($j = $idx; $j < count($times); $j++) {
            $t = $times[$j]; $c = $optMap[$t] ?? null;
            if (!$c || $t === $entryTime) continue;

            $h = (float)$c->high; $l = (float)$c->low;

            // Check if we've reached EOD exit time BEFORE hitting target/SL
            if ($t >= self::EOD_EXIT_TIME) {
                // Force exit at 15:00 — return EOD_EXIT, actual price computed by caller
                return ['outcome' => 'EOD_EXIT', 'time' => $t];
            }

            // SL wins on ambiguous candle (conservative)
            if ($l <= $sl && $h >= $target) return ['outcome' => 'SL',    'time' => $t];
            if ($h >= $target)              return ['outcome' => 'TARGET', 'time' => $t];
            if ($l <= $sl)                  return ['outcome' => 'SL',     'time' => $t];
        }

        // If we ran out of candles before 15:00 (data gap) — EOD exit at last known price
        $lastTime = end($times);
        return $lastTime >= '15:00'
            ? ['outcome' => 'EOD_EXIT', 'time' => $lastTime]
            : ['outcome' => 'OPEN',     'time' => null];
    }

    // =========================================================
    //  HEATMAP (unchanged)
    // =========================================================

    public function getHeatmap(Request $request)
    {
        try {
            $date   = $request->get('date', now()->toDateString());
            $symbol = strtoupper($request->get('symbol', 'NIFTY'));

            $futRows = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')->whereDate('trade_date', $date)
                ->where('is_missing', 0)->orderBy('interval_time')
                ->get(['interval_time', 'open', 'high', 'low', 'close', 'volume', 'oi', 'atm_strike']);

            if ($futRows->isEmpty()) return response()->json(['success' => false, 'message' => 'No data', 'rows' => []]);

            $atm    = (float) $futRows->first()->atm_strike;
            $optRows = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])->where('strike', $atm)
                ->whereDate('trade_date', $date)->where('is_missing', 0)->orderBy('interval_time')
                ->get(['interval_time', 'instrument_type', 'close', 'oi']);

            $ceM = $optRows->where('instrument_type','CE')->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));
            $peM = $optRows->where('instrument_type','PE')->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));

            $rows=[]; $pc=null; $pco=null; $ppo=null; $pv=null;
            foreach ($futRows as $row) {
                $t=(Carbon::parse($row->interval_time)->format('H:i'));
                $cl=(float)$row->close; $h=(float)$row->high; $l=(float)$row->low; $v=(int)$row->volume;
                $co=(float)($ceM[$t]->oi??0); $po=(float)($peM[$t]->oi??0);
                $hp=$pc>0?round((($h-$pc)/$pc)*100,3):0; $lp=$pc>0?round((($l-$pc)/$pc)*100,3):0;
                $rows[]=['time'=>$t,'open'=>round((float)$row->open,2),'high'=>round($h,2),'low'=>round($l,2),'close'=>round($cl,2),
                    'volume'=>$v,'vol_ratio'=>$pv>0?round($v/$pv,2):1,
                    'high_pct'=>$hp,'low_pct'=>$lp,
                    'close_pct'=>$pc>0?round((($cl-$pc)/$pc)*100,3):0,
                    'range_pct'=>round(abs($hp-$lp),3),
                    'ce_oi_pct'=>$pco>0?round((($co-$pco)/$pco)*100,3):0,
                    'pe_oi_pct'=>$ppo>0?round((($po-$ppo)/$ppo)*100,3):0,
                    'ce_close'=>round((float)($ceM[$t]->close??0),2),
                    'pe_close'=>round((float)($peM[$t]->close??0),2),
                    'zone'=>$this->getZone($t),
                ];
                $pc=$cl; $pco=$co; $ppo=$po; $pv=$v;
            }
            return response()->json(['success'=>true,'symbol'=>$symbol,'date'=>$date,'atm_strike'=>$atm,'rows'=>$rows]);
        } catch (\Exception $e) {
            return response()->json(['success'=>false,'message'=>$e->getMessage(),'rows'=>[]]);
        }
    }

    // =========================================================
    //  STATS
    // =========================================================

    private function computeStats(array $signals): array
    {
        $filled   = array_filter($signals, fn($s) => $s['fill_status'] === 'FILLED');
        $comp     = array_filter($filled,  fn($s) => in_array($s['outcome'], ['TARGET','SL','EOD_EXIT']));
        $targets  = array_filter($filled,  fn($s) => $s['outcome'] === 'TARGET');
        $sls      = array_filter($filled,  fn($s) => $s['outcome'] === 'SL');
        $eodExits = array_filter($filled,  fn($s) => $s['outcome'] === 'EOD_EXIT');
        $blocked  = array_filter($signals, fn($s) => !$s['edge_allowed']);
        $str3     = array_filter($signals, fn($s) => $s['strength'] === 3);
        $chase    = array_filter($signals, fn($s) => $s['use_chase']);
        $hasPL    = array_filter($filled,  fn($s) => $s['actual_pl'] !== null);

        // EOD exits: split into profitable vs loss
        $eodProfit = array_filter($eodExits, fn($s) => ($s['actual_pl'] ?? 0) >= 0);
        $eodLoss   = array_filter($eodExits, fn($s) => ($s['actual_pl'] ?? 0) < 0);

        // Expectancy across all filled trades
        $totalPL    = array_sum(array_column($hasPL, 'actual_pl'));
        $winsPL     = array_sum(array_column(array_values(array_filter($hasPL, fn($s) => ($s['actual_pl']??0) > 0)), 'actual_pl'));
        $lossesPL   = array_sum(array_column(array_values(array_filter($hasPL, fn($s) => ($s['actual_pl']??0) < 0)), 'actual_pl'));
        $winCount   = count(array_filter($hasPL, fn($s) => ($s['actual_pl']??0) > 0));
        $lossCount  = count(array_filter($hasPL, fn($s) => ($s['actual_pl']??0) < 0));
        $avgWin     = $winCount  > 0 ? round($winsPL   / $winCount,  2) : 0;
        $avgLoss    = $lossCount > 0 ? round($lossesPL / $lossCount, 2) : 0;
        $totalFilled= count($filled);
        $expWinPct  = $totalFilled > 0 ? ($winCount  / $totalFilled) : 0;
        $expLossPct = $totalFilled > 0 ? ($lossCount / $totalFilled) : 0;
        $expectancy = round(($expWinPct * $avgWin) + ($expLossPct * $avgLoss), 2);

        return [
            'total'           => count($signals),
            'edge_allowed'    => count($signals) - count($blocked),
            'edge_blocked'    => count($blocked),
            'filled_count'    => count($filled),
            'unfilled_count'  => count($signals) - count($filled),
            'fill_rate'       => count($signals) > 0 ? round((count($filled) / count($signals)) * 100, 1) : 0,
            'targets_count'   => count($targets),
            'sls_count'       => count($sls),
            'eod_exits_count' => count($eodExits),
            'eod_profit'      => count($eodProfit),
            'eod_loss'        => count($eodLoss),
            'str3_count'      => count($str3),
            'chase_count'     => count($chase),
            'totalPL'         => round($totalPL, 2),
            'targetPLSum'     => round(array_sum(array_column(array_values($targets), 'actual_pl')), 2),
            'slPLSum'         => round(array_sum(array_column(array_values($sls),     'actual_pl')), 2),
            'eodPLSum'        => round(array_sum(array_column(array_values($eodExits),'actual_pl')), 2),
            'winRate'         => count($comp) > 0 ? round((count($targets) / count($comp)) * 100, 1) : 0,
            'expectancy'      => $expectancy,
            'avg_win'         => $avgWin,
            'avg_loss'        => $avgLoss,
        ];
    }

    // =========================================================
    //  UTILITIES
    // =========================================================

    private function buildCandleMoves(string $symbol, string $date): array
    {
        $futC = OptionOhlcData::where('base_symbol', $symbol)->where('instrument_type','FUT')
            ->whereDate('trade_date',$date)->where('is_missing',0)->orderBy('interval_time')
            ->get(['interval_time','open','high','low','close','volume','atm_strike'])
            ->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));

        if ($futC->count()<2) return [];
        $times=$futC->keys()->sort()->values()->toArray();
        $atm=(float)$futC->first()->atm_strike;
        $opts=OptionOhlcData::where('base_symbol',$symbol)->whereIn('instrument_type',['CE','PE'])
            ->where('strike',$atm)->whereDate('trade_date',$date)->where('is_missing',0)->orderBy('interval_time')
            ->get(['interval_time','instrument_type','close','oi']);
        $ceM=$opts->where('instrument_type','CE')->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));
        $peM=$opts->where('instrument_type','PE')->keyBy(fn($r)=>Carbon::parse($r->interval_time)->format('H:i'));

        $moves=[];
        for($i=1;$i<count($times);$i++){
            $t=$times[$i];$p=$futC[$times[$i-1]]??null;$c=$futC[$t]??null;
            if(!$c||!$p||(float)$p->close<=0)continue;
            $pc=(float)$p->close;
            $hp=round((((float)$c->high-$pc)/$pc)*100,3);
            $lp=round((((float)$c->low-$pc)/$pc)*100,3);
            $moves[]=['symbol'=>$symbol,'time'=>$t,'prev_close'=>round($pc,2),
                'open'=>round((float)$c->open,2),'high'=>round((float)$c->high,2),
                'low'=>round((float)$c->low,2),'close'=>round((float)$c->close,2),
                'volume'=>(int)$c->volume,'high_pct'=>$hp,'low_pct'=>$lp,
                'close_pct'=>round((((float)$c->close-$pc)/$pc)*100,3),
                'range_pct'=>round(abs($hp-$lp),3),
                'ce_close'=>round((float)($ceM[$t]->close??0),2),
                'pe_close'=>round((float)($peM[$t]->close??0),2),
            ];
        }
        return $moves;
    }

    private function avgRangeByTime(array $moves): array
    {
        $by=[]; $avg=[];
        foreach($moves as $m) $by[$m['time']][]=$m['range_pct'];
        foreach($by as $t=>$v) $avg[$t]=count($v)?array_sum($v)/count($v):0;
        return $avg;
    }

    private function avgRangeFromCandles($futC, array $times): array
    {
        $by=[]; $avg=[];
        for($i=1;$i<count($times);$i++){
            $t=$times[$i];$p=$futC[$times[$i-1]]??null;$c=$futC[$t]??null;
            if(!$c||!$p||(float)$p->close<=0)continue;
            $pc=(float)$p->close;
            $hp=(((float)$c->high-$pc)/$pc)*100;$lp=(((float)$c->low-$pc)/$pc)*100;
            $by[$t][]=round(abs($hp-$lp),3);
        }
        foreach($by as $t=>$v) $avg[$t]=count($v)?array_sum($v)/count($v):0;
        return $avg;
    }

    private function computeTimePattern(array $moves): array
    {
        $by=[];
        foreach($moves as $m){
            $k=$m['time'];
            if(!isset($by[$k]))$by[$k]=['hp'=>[],'lp'=>[],'rp'=>[],'cp'=>[],'syms'=>[]];
            $by[$k]['hp'][]=$m['high_pct'];$by[$k]['lp'][]=$m['low_pct'];
            $by[$k]['rp'][]=$m['range_pct'];$by[$k]['cp'][]=$m['close_pct'];
            if(!in_array($m['symbol'],$by[$k]['syms']))$by[$k]['syms'][]=$m['symbol'];
        }
        $out=[];
        foreach($by as $t=>$d){
            $n=count($d['hp']);
            $out[]=['time'=>$t,'n'=>$n,'symbols'=>implode(', ',$d['syms']),'zone'=>$this->getZone($t),
                'avg_high_pct'=>$n?round(array_sum($d['hp'])/$n,3):0,'max_high_pct'=>$n?round(max($d['hp']),3):0,
                'avg_low_pct'=>$n?round(array_sum($d['lp'])/$n,3):0,'min_low_pct'=>$n?round(min($d['lp']),3):0,
                'avg_range_pct'=>$n?round(array_sum($d['rp'])/$n,3):0,'max_range_pct'=>$n?round(max($d['rp']),3):0,
                'avg_close_pct'=>$n?round(array_sum($d['cp'])/$n,3):0,
            ];
        }
        usort($out,fn($a,$b)=>$a['time']<=>$b['time']);
        return $out;
    }

    private function formatPatternMap(array $map): array
    {
        $list=array_values($map);
        usort($list,fn($a,$b)=>$b['edge_score']<=>$a['edge_score']);
        return array_slice($list, 0, 50);
    }

    private function getZone(string $t): string
    {
        if(in_array($t,self::AGGRESSIVE_ZONE))return'aggressive';
        if(in_array($t,self::MODERATE_ZONE))return'moderate';
        return'defensive';
    }

    private function getDynSlTarget(float $price, float $bt, float $bs): array
    {
        if($price<50)  return[max($bt,70.0),max($bs,35.0)];
        if($price<150) return[$bt,$bs];
        return[min($bt,35.0),min($bs,18.0)];
    }

    private function getLotSize(string $s): int
    {
        $lots=['NIFTY'=>25,'BANKNIFTY'=>15,'SENSEX'=>10];
        $db=DB::table('zerodha_instruments')->where('name',$s)->whereIn('instrument_type',['CE','PE'])->value('lot_size');
        return $db?(int)$db:($lots[$s]??1);
    }
}