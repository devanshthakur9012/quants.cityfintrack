<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SensexExpiryAnalysisController
 *
 * Analyses SENSEX expiry day 1-min candle data from `sensex_expiry_candles`
 * and detects institutional activity / explosion signals.
 *
 * 6 Layered Signals (scored):
 *   1. OI Build (15+ min consecutive rise) + Price Sideways  → Accumulation   (+3)
 *   2. Volume Spike  (current > 3× rolling 10-min avg)       → Big player      (+2)
 *   3. OI × Price interpretation (long/short build/covering)  → Direction       (+2/+3)
 *   4. ATM option price expansion without FUT move            → Hidden IV       (+3)
 *   5. PCR crosses bull/bear threshold                        → Bias shift      (+2)
 *   6. All 3 core conditions together                         → EXPLOSION       (+10)
 *
 * DB: sensex_expiry_candles (1-min OHLCV + OI, CE + PE, 15 strikes each)
 */
class SensexExpiryAnalysisController extends Controller
{
    private const VOLUME_SPIKE_MULTIPLIER   = 3.0;
    private const VOLUME_AVG_LOOKBACK       = 10;
    private const OI_BUILD_WINDOW           = 15;
    private const PRICE_RANGE_THRESHOLD_PCT = 0.3;
    private const PCR_BULLISH_THRESHOLD     = 1.2;
    private const PCR_BEARISH_THRESHOLD     = 0.8;

    // ══════════════════════════════════════════════════════════════════════════
    // Page
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'SENSEX Expiry — Explosion Detector';
        return view($this->activeTemplate . 'user.sensex-expiry.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Main Analysis
    // GET /sensex-expiry/analyze?date=Y-m-d
    // ══════════════════════════════════════════════════════════════════════════

    public function analyze(Request $request)
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());

            Log::info('=== SENSEX EXPIRY ANALYSIS ===', ['date' => $date]);

            $candles = DB::table('sensex_expiry_candles')
                ->whereDate('trade_date', $date)
                ->where('is_missing', 0)
                ->orderBy('interval_time')
                ->get();

            if ($candles->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No data for {$date}.",
                ]);
            }

            $ceCandles = $candles->where('instrument_type', 'CE');
            $peCandles = $candles->where('instrument_type', 'PE');

            $timeline        = $this->buildTimeline($ceCandles, $peCandles);
            $pcr             = $this->computePcrTimeline($timeline);
            $strikeDominance = $this->computeStrikeDominance($ceCandles, $peCandles);
            $signals         = $this->detectExplosionSignals($timeline, $pcr);
            $summary         = $this->buildDaySummary($candles, $timeline, $signals, $date);

            // Slim timeline for JS (only fields needed for the table)
            $timelineSample = array_map(fn($s) => [
                'time'            => $s['time'],
                'ce_volume'       => $s['ce_volume'],
                'pe_volume'       => $s['pe_volume'],
                'ce_oi'           => $s['ce_oi'],
                'pe_oi'           => $s['pe_oi'],
                'total_oi_change' => $s['total_oi_change'],
                'vol_spike'       => $s['vol_spike'],
                'oi_building'     => $s['oi_building'],
                'market_mode'     => $s['market_mode'],
                'future_price'    => $s['future_price'],
                'atm_ce_close'    => $s['atm_ce_close'],
                'atm_pe_close'    => $s['atm_pe_close'],
                'pcr'             => round($s['pe_oi'] / max($s['ce_oi'], 1), 3),
            ], $timeline);

            return response()->json([
                'success'          => true,
                'date'             => $date,
                'summary'          => $summary,
                'signals'          => $signals,
                'pcr'              => $pcr,
                'strike_dominance' => $strikeDominance,
                'timeline'         => $timelineSample,
                'analyzed_at'      => now()->format('Y-m-d H:i:s'),
                'total_candles'    => $candles->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('SensexExpiryAnalysis::analyze — ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Analysis error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Build per-minute aggregated timeline
    // ══════════════════════════════════════════════════════════════════════════

    private function buildTimeline($ceCandles, $peCandles): array
    {
        $byTime = [];

        foreach ($ceCandles as $c) {
            $t = Carbon::parse($c->interval_time)->format('H:i');
            if (!isset($byTime[$t])) $byTime[$t] = $this->emptySlot($t);
            $byTime[$t]['ce_volume']    += $c->volume;
            $byTime[$t]['ce_oi']        += $c->oi;
            $byTime[$t]['ce_price_sum'] += $c->close;
            $byTime[$t]['ce_count']     += 1;
            $byTime[$t]['future_price']  = $c->future_price;
            $byTime[$t]['atm_strike']    = $c->atm_strike;
            if ($c->strike_position === 'ATM') {
                $byTime[$t]['atm_ce_close']  = $c->close;
                $byTime[$t]['atm_ce_volume'] = $c->volume;
                $byTime[$t]['atm_ce_oi']     = $c->oi;
            }
        }

        foreach ($peCandles as $c) {
            $t = Carbon::parse($c->interval_time)->format('H:i');
            if (!isset($byTime[$t])) $byTime[$t] = $this->emptySlot($t);
            $byTime[$t]['pe_volume']    += $c->volume;
            $byTime[$t]['pe_oi']        += $c->oi;
            $byTime[$t]['pe_price_sum'] += $c->close;
            $byTime[$t]['pe_count']     += 1;
            if ($c->strike_position === 'ATM') {
                $byTime[$t]['atm_pe_close']  = $c->close;
                $byTime[$t]['atm_pe_volume'] = $c->volume;
                $byTime[$t]['atm_pe_oi']     = $c->oi;
            }
        }

        ksort($byTime);
        $timeline     = array_values($byTime);
        $ceVolHistory = [];
        $peVolHistory = [];

        foreach ($timeline as $i => &$slot) {
            $slot['ce_avg_price'] = $slot['ce_count'] > 0
                ? round($slot['ce_price_sum'] / $slot['ce_count'], 2) : 0;
            $slot['pe_avg_price'] = $slot['pe_count'] > 0
                ? round($slot['pe_price_sum'] / $slot['pe_count'], 2) : 0;
            $slot['total_volume'] = $slot['ce_volume'] + $slot['pe_volume'];
            $slot['total_oi']     = $slot['ce_oi'] + $slot['pe_oi'];

            $ceVolHistory[] = $slot['ce_volume'];
            $peVolHistory[] = $slot['pe_volume'];
            $lb             = self::VOLUME_AVG_LOOKBACK;
            $ceWin          = array_slice($ceVolHistory, -($lb + 1), $lb);
            $peWin          = array_slice($peVolHistory, -($lb + 1), $lb);
            $slot['ce_vol_avg'] = count($ceWin) > 0 ? round(array_sum($ceWin) / count($ceWin)) : 0;
            $slot['pe_vol_avg'] = count($peWin) > 0 ? round(array_sum($peWin) / count($peWin)) : 0;

            if ($i > 0) {
                $prev = $timeline[$i - 1];
                $slot['ce_oi_change']        = $slot['ce_oi']      - $prev['ce_oi'];
                $slot['pe_oi_change']        = $slot['pe_oi']      - $prev['pe_oi'];
                $slot['total_oi_change']     = $slot['total_oi']   - $prev['total_oi'];
                $slot['fut_price_change']    = ($slot['future_price'] ?? 0) - ($prev['future_price'] ?? 0);
                $slot['atm_ce_price_change'] = ($slot['atm_ce_close'] ?? 0) - ($prev['atm_ce_close'] ?? 0);
                $slot['atm_pe_price_change'] = ($slot['atm_pe_close'] ?? 0) - ($prev['atm_pe_close'] ?? 0);
            } else {
                $slot['ce_oi_change'] = $slot['pe_oi_change'] = $slot['total_oi_change'] = 0;
                $slot['fut_price_change'] = $slot['atm_ce_price_change'] = $slot['atm_pe_price_change'] = 0;
            }

            $slot['ce_vol_spike'] = $slot['ce_vol_avg'] > 0
                && $slot['ce_volume'] >= (self::VOLUME_SPIKE_MULTIPLIER * $slot['ce_vol_avg']);
            $slot['pe_vol_spike'] = $slot['pe_vol_avg'] > 0
                && $slot['pe_volume'] >= (self::VOLUME_SPIKE_MULTIPLIER * $slot['pe_vol_avg']);
            $slot['vol_spike']    = $slot['ce_vol_spike'] || $slot['pe_vol_spike'];
            $slot['market_mode']  = $this->interpretOiPrice(
                $slot['fut_price_change'], $slot['ce_oi_change'] + $slot['pe_oi_change']
            );
        }
        unset($slot);

        // Rolling OI build (15-min consecutive window)
        $win = self::OI_BUILD_WINDOW;
        foreach ($timeline as $i => &$slot) {
            if ($i < $win) { $slot['oi_building'] = false; continue; }
            $ok = true;
            for ($j = $i - $win + 1; $j <= $i; $j++) {
                if ($timeline[$j]['total_oi_change'] <= 0) { $ok = false; break; }
            }
            $slot['oi_building'] = $ok;
        }
        unset($slot);

        // Price sideways (15-min rolling range ≤ threshold)
        foreach ($timeline as $i => &$slot) {
            if ($i < 15) { $slot['price_sideways'] = false; continue; }
            $prices = array_filter(array_column(array_slice($timeline, $i - 15, 15), 'future_price'));
            if (empty($prices)) { $slot['price_sideways'] = false; continue; }
            $range = max($prices) - min($prices);
            $slot['price_sideways'] = ($range / (min($prices) ?: 1) * 100) <= self::PRICE_RANGE_THRESHOLD_PCT;
        }
        unset($slot);

        return $timeline;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PCR timeline
    // ══════════════════════════════════════════════════════════════════════════

    private function computePcrTimeline(array $timeline): array
    {
        return array_map(function ($slot) {
            $ratio = round($slot['pe_oi'] / max($slot['ce_oi'], 1), 3);
            return [
                'time'  => $slot['time'],
                'pcr'   => $ratio,
                'bias'  => $ratio >= self::PCR_BULLISH_THRESHOLD ? 'bullish'
                    : ($ratio <= self::PCR_BEARISH_THRESHOLD ? 'bearish' : 'neutral'),
                'ce_oi' => $slot['ce_oi'],
                'pe_oi' => $slot['pe_oi'],
            ];
        }, $timeline);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Strike dominance
    // ══════════════════════════════════════════════════════════════════════════

    private function computeStrikeDominance($ceCandles, $peCandles): array
    {
        $build = function ($candles) {
            $map = [];
            foreach ($candles as $c) {
                $s = (string) $c->strike;
                if (!isset($map[$s])) {
                    $map[$s] = ['strike' => $c->strike, 'total_oi' => 0, 'total_vol' => 0, 'max_price' => 0];
                }
                $map[$s]['total_oi']  += $c->oi;
                $map[$s]['total_vol'] += $c->volume;
                $map[$s]['max_price']  = max($map[$s]['max_price'], $c->close);
            }
            usort($map, fn($a, $b) => $b['total_oi'] <=> $a['total_oi']);
            return array_slice(array_values($map), 0, 5);
        };

        return ['ce' => $build($ceCandles), 'pe' => $build($peCandles)];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Explosion signal detection — 6 layered signals
    // ══════════════════════════════════════════════════════════════════════════

    private function detectExplosionSignals(array $timeline, array $pcr): array
    {
        $signals = [];
        $pcrMap  = array_column($pcr, null, 'time');

        foreach ($timeline as $i => $slot) {
            if ($i === 0) continue;
            $prev    = $timeline[$i - 1];
            $pcrSlot = $pcrMap[$slot['time']] ?? null;
            $alerts  = [];
            $score   = 0;

            if ($slot['oi_building'] && $slot['price_sideways']) {
                $alerts[] = ['type' => 'accumulation', 'label' => 'Smart Money Accumulation',
                    'detail' => 'OI rising 15+ mins while price sideways — quiet position build.', 'weight' => 3, 'color' => 'yellow'];
                $score += 3;
            }

            if ($slot['vol_spike']) {
                $side = $slot['ce_vol_spike'] && $slot['pe_vol_spike'] ? 'CE+PE'
                    : ($slot['ce_vol_spike'] ? 'CE' : 'PE');
                $alerts[] = ['type' => 'volume_spike', 'label' => "Volume Spike ({$side})",
                    'detail' => self::VOLUME_SPIKE_MULTIPLIER . '× above rolling avg — big player detected.', 'weight' => 2, 'color' => 'orange'];
                $score += 2;
            }

            $mode = $slot['market_mode'];
            if (in_array($mode, ['long_buildup', 'short_buildup'])) {
                $alerts[] = ['type' => 'oi_price',
                    'label'  => $mode === 'long_buildup' ? '🐂 Long Build-Up' : '🐻 Short Build-Up',
                    'detail' => $mode === 'long_buildup'
                        ? 'Price ↑ + OI ↑ — fresh longs, bullish pressure.'
                        : 'Price ↓ + OI ↑ — fresh shorts, bearish pressure.',
                    'weight' => 2, 'color' => $mode === 'long_buildup' ? 'green' : 'red'];
                $score += 2;
            }
            if (in_array($mode, ['short_covering', 'long_unwinding'])) {
                $alerts[] = ['type' => 'oi_price',
                    'label'  => $mode === 'short_covering' ? '🚀 Short Covering' : '⚠️ Long Unwinding',
                    'detail' => $mode === 'short_covering'
                        ? 'Price ↑ + OI ↓ — shorts panicking, explosive upside possible.'
                        : 'Price ↓ + OI ↓ — longs exiting, downside accelerating.',
                    'weight' => 3, 'color' => $mode === 'short_covering' ? 'green' : 'red'];
                $score += 3;
            }

            $futAbs  = abs($slot['fut_price_change'] ?? 0);
            $ceExp   = $slot['atm_ce_price_change'] ?? 0;
            $peExp   = $slot['atm_pe_price_change'] ?? 0;
            if ($futAbs < 20 && $ceExp > 5) {
                $alerts[] = ['type' => 'iv_expansion', 'label' => '📈 CE IV Expansion',
                    'detail' => "ATM CE +₹{$ceExp} while FUT barely moved — smart CE loading.", 'weight' => 3, 'color' => 'blue'];
                $score += 3;
            }
            if ($futAbs < 20 && $peExp > 5) {
                $alerts[] = ['type' => 'iv_expansion', 'label' => '📉 PE IV Expansion',
                    'detail' => "ATM PE +₹{$peExp} while FUT barely moved — smart PE loading.", 'weight' => 3, 'color' => 'blue'];
                $score += 3;
            }

            if ($pcrSlot) {
                $prevBias = $pcrMap[$prev['time']]['bias'] ?? '';
                if ($pcrSlot['bias'] === 'bullish' && $prevBias !== 'bullish') {
                    $alerts[] = ['type' => 'pcr', 'label' => '📊 PCR Turned Bullish',
                        'detail' => "PCR = {$pcrSlot['pcr']} — PE OI crossed " . self::PCR_BULLISH_THRESHOLD . " threshold.", 'weight' => 2, 'color' => 'green'];
                    $score += 2;
                }
                if ($pcrSlot['bias'] === 'bearish' && $prevBias !== 'bearish') {
                    $alerts[] = ['type' => 'pcr', 'label' => '📊 PCR Turned Bearish',
                        'detail' => "PCR = {$pcrSlot['pcr']} — CE OI dominating, bearish.", 'weight' => 2, 'color' => 'red'];
                    $score += 2;
                }
            }

            if ($slot['oi_building'] && $slot['vol_spike']
                && in_array($mode, ['long_buildup', 'short_buildup', 'short_covering'])) {
                $dir = in_array($mode, ['long_buildup', 'short_covering']) ? 'CALL' : 'PUT';
                $alerts[] = ['type' => 'explosion', 'label' => "🚨 EXPLOSION — BUY {$dir}",
                    'detail' => "OI build + Volume spike + {$mode} confirmed. High-probability entry.", 'weight' => 10, 'color' => 'fire'];
                $score += 10;
            }

            if (!empty($alerts)) {
                $signals[] = [
                    'time'      => $slot['time'],
                    'score'     => $score,
                    'severity'  => $score >= 8 ? 'critical' : ($score >= 5 ? 'high' : ($score >= 3 ? 'medium' : 'low')),
                    'alerts'    => $alerts,
                    'fut_price' => $slot['future_price'],
                    'total_vol' => $slot['total_volume'],
                    'total_oi'  => $slot['total_oi'],
                    'oi_change' => $slot['total_oi_change'],
                    'pcr'       => $pcrSlot['pcr'] ?? null,
                    'mode'      => $mode,
                    'atm_ce'    => $slot['atm_ce_close'] ?? 0,
                    'atm_pe'    => $slot['atm_pe_close'] ?? 0,
                    'vol_spike' => $slot['vol_spike'],
                ];
            }
        }

        usort($signals, fn($a, $b) => $b['score'] <=> $a['score']);
        return $signals;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Day summary
    // ══════════════════════════════════════════════════════════════════════════

    private function buildDaySummary($candles, array $timeline, array $signals, string $date): array
    {
        $ceAll = $candles->where('instrument_type', 'CE');
        $peAll = $candles->where('instrument_type', 'PE');
        $atmCe = $ceAll->where('strike_position', 'ATM');
        $atmPe = $peAll->where('strike_position', 'ATM');

        $atmCeFirst = $atmCe->first();
        $atmCeLast  = $atmCe->last();
        $atmPeFirst = $atmPe->first();

        $explosions = count(array_filter($signals, fn($s) => $s['score'] >= 8));

        return [
            'date'             => $date,
            'total_candles'    => $candles->count(),
            'atm_strike'       => $atmCeFirst->atm_strike ?? 'N/A',
            'atm_ce_open'      => $atmCeFirst->open ?? 0,
            'atm_ce_high'      => $atmCe->max('high'),
            'atm_ce_close'     => $atmCeLast->close ?? 0,
            'atm_ce_move_pct'  => $atmCeFirst && $atmCeFirst->open > 0
                ? round((($atmCe->max('high') - $atmCeFirst->open) / $atmCeFirst->open) * 100, 1) : 0,
            'atm_pe_open'      => $atmPeFirst->open ?? 0,
            'atm_pe_high'      => $atmPe->max('high'),
            'atm_pe_close'     => $atmPe->last()->close ?? 0,
            'atm_pe_move_pct'  => $atmPeFirst && $atmPeFirst->open > 0
                ? round((($atmPe->max('high') - $atmPeFirst->open) / $atmPeFirst->open) * 100, 1) : 0,
            'total_ce_volume'  => $ceAll->sum('volume'),
            'total_pe_volume'  => $peAll->sum('volume'),
            'peak_ce_oi'       => $ceAll->max('oi'),
            'peak_pe_oi'       => $peAll->max('oi'),
            'total_signals'    => count($signals),
            'explosion_count'  => $explosions,
            'top_signal_time'  => !empty($signals) ? $signals[0]['time'] : 'N/A',
            'top_signal_score' => !empty($signals) ? $signals[0]['score'] : 0,
            'day_bias'         => $this->inferDayBias($timeline),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Utility
    // ══════════════════════════════════════════════════════════════════════════

    private function emptySlot(string $time): array
    {
        return [
            'time' => $time, 'ce_volume' => 0, 'pe_volume' => 0,
            'ce_oi' => 0, 'pe_oi' => 0, 'ce_price_sum' => 0, 'pe_price_sum' => 0,
            'ce_count' => 0, 'pe_count' => 0, 'ce_avg_price' => 0, 'pe_avg_price' => 0,
            'total_volume' => 0, 'total_oi' => 0, 'ce_oi_change' => 0, 'pe_oi_change' => 0,
            'total_oi_change' => 0, 'fut_price_change' => 0, 'ce_vol_avg' => 0, 'pe_vol_avg' => 0,
            'ce_vol_spike' => false, 'pe_vol_spike' => false, 'vol_spike' => false,
            'oi_building' => false, 'price_sideways' => false, 'market_mode' => 'neutral',
            'atm_ce_close' => 0, 'atm_pe_close' => 0, 'atm_ce_volume' => 0, 'atm_pe_volume' => 0,
            'atm_ce_oi' => 0, 'atm_pe_oi' => 0, 'atm_ce_price_change' => 0, 'atm_pe_price_change' => 0,
            'future_price' => null, 'atm_strike' => null,
        ];
    }

    private function interpretOiPrice(float $price, float $oi): string
    {
        return match (true) {
            $price > 0 && $oi > 0  => 'long_buildup',
            $price < 0 && $oi > 0  => 'short_buildup',
            $price > 0 && $oi < 0  => 'short_covering',
            $price < 0 && $oi < 0  => 'long_unwinding',
            default                 => 'neutral',
        };
    }

    private function inferDayBias(array $timeline): string
    {
        $bull = $bear = 0;
        foreach ($timeline as $s) {
            if (in_array($s['market_mode'], ['long_buildup', 'short_covering']))  $bull++;
            if (in_array($s['market_mode'], ['short_buildup', 'long_unwinding'])) $bear++;
        }
        if ($bull > $bear * 1.5) return 'bullish';
        if ($bear > $bull * 1.5) return 'bearish';
        return 'neutral';
    }
}