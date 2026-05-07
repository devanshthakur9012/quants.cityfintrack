<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * VolumeSpike15Controller
 *
 * KEY DATABASE INSIGHT (from option_ohlc_data schema):
 *   strike_position ENUM = ('ATM','ATM+1','ATM-1','N/A')
 *   ATM+2 and ATM-2 are stored as 'N/A' — NOT labelled in the DB.
 *   We compute ATM offset dynamically:
 *     offset = round((strike - atm_strike) / strike_interval)
 *   Strike interval = min gap between consecutive CE strikes for that symbol+expiry.
 *
 * Data layout per API response row (one per symbol per interval):
 *   ATM VOL SPIKE   : CE[0]  | PE[0]
 *   ATM±1 VOL SPIKE : CE[-1] | CE[+1] | PE[-1] | PE[+1]
 *   ATM±2 VOL SPIKE : CE[-2] | CE[+2] | PE[-2] | PE[+2]
 *   FINAL BLOCK     : Σ CE offsets -2..+2 | Σ PE offsets -2..+2
 *   15Min OI Sentiment: signal | ce_oi_pct | pe_oi_pct | strength
 */
class VolumeSpike15Controller extends Controller
{
    // ── Signal Page ───────────────────────────────────────────────────────────
    public function index()
    {
        $pageTitle = 'Volume Spike 15Min';
        return view($this->activeTemplate . 'user.volume-spike-15.index', compact('pageTitle'));
    }

    // ── Signal API ────────────────────────────────────────────────────────────
    public function getSignals(Request $request)
    {
        try {
            $symbol    = strtoupper(trim($request->get('symbol', 'ALL')));
            $dateInput = $request->get('date');
            $today     = $dateInput
                ? Carbon::parse($dateInput)->toDateString()
                : Carbon::today()->toDateString();

            $availableSymbols = OptionOhlcData::whereDate('trade_date', $today)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()
                ->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return response()->json([
                    'success' => true, 'data' => [], 'today' => $today,
                    'is_today' => $today === Carbon::today()->toDateString(),
                    'message' => 'No data found for ' . $today, 'available_symbols' => [],
                ]);
            }

            $isAll   = ($symbol === 'ALL');
            $symbols = $isAll ? $availableSymbols : [$symbol];
            $results = [];

            foreach ($symbols as $sym) {

                $expiry = $this->getNearestExpiry($sym, $today);
                if (!$expiry) continue;

                // ONE bulk query — all CE/PE rows for this symbol+expiry+date
                $allRows = OptionOhlcData::where('base_symbol', $sym)
                    ->whereIn('instrument_type', ['CE', 'PE'])
                    ->whereDate('expiry_date', $expiry)
                    ->whereDate('trade_date', $today)
                    ->orderBy('interval_time')
                    ->get(['interval_time', 'instrument_type', 'strike', 'atm_strike', 'volume', 'oi']);

                if ($allRows->isEmpty()) continue;

                // ATM strike from last interval's atm_strike column
                $atmStrike = (float) $allRows->sortBy('interval_time')->last()->atm_strike;

                // Strike interval = min gap between consecutive CE strikes
                $strikeInterval = $this->resolveStrikeInterval($allRows);
                if (!$strikeInterval) continue;

                // Get all interval times in order
                $allTimes = $allRows
                    ->pluck('interval_time')
                    ->map(fn($t) => Carbon::parse($t)->format('H:i'))
                    ->unique()->sort()->values()->toArray();

                if (empty($allTimes)) continue;

                // Build maps:
                //   volMap['CE'|'PE'][-2..+2]['H:i'] = volume
                //   oiMap['CE'|'PE']['H:i']           = sum oi (offsets -2..+2)
                //   strikeByOffset[offset]             = actual strike price (for display)
                $volMap         = [];
                $oiMap          = [];
                $strikeByOffset = [];

                foreach ($allRows as $r) {
                    $strike  = (float) $r->strike;
                    $timeKey = Carbon::parse($r->interval_time)->format('H:i');
                    $type    = $r->instrument_type; // CE or PE
                    $vol     = (int) $r->volume;
                    $oi      = (int) $r->oi;

                    // Compute ATM offset
                    $rawOffset = ($strike - $atmStrike) / $strikeInterval;
                    $offset    = (int) round($rawOffset);

                    if ($offset < -2 || $offset > 2) continue; // skip far OTM

                    $strikeByOffset[$offset] = $strike;

                    $volMap[$type][$offset][$timeKey] = ($volMap[$type][$offset][$timeKey] ?? 0) + $vol;
                    $oiMap[$type][$timeKey]           = ($oiMap[$type][$timeKey] ?? 0) + $oi;
                }

                // Session-average trackers (running arrays of prior vols)
                $priorVols    = [];  // key = "CE:0", "PE:-1", etc.
                $priorFinalCe = [];
                $priorFinalPe = [];

                $intervalResults = [];

                foreach ($allTimes as $idx => $timeKey) {

                    // Vol spike per (type, offset)
                    $spikeData = [];
                    foreach (['CE', 'PE'] as $type) {
                        for ($off = -2; $off <= 2; $off++) {
                            $curVol   = $volMap[$type][$off][$timeKey] ?? 0;
                            $trackKey = "{$type}:{$off}";
                            $spikeData[$type][$off] = $this->calcVolSpike($curVol, $priorVols[$trackKey] ?? []);
                            if ($curVol > 0) $priorVols[$trackKey][] = $curVol;
                        }
                    }

                    // Final block: sum offsets -2..+2 per type
                    $finalCeVol = 0;
                    $finalPeVol = 0;
                    for ($off = -2; $off <= 2; $off++) {
                        $finalCeVol += $volMap['CE'][$off][$timeKey] ?? 0;
                        $finalPeVol += $volMap['PE'][$off][$timeKey] ?? 0;
                    }
                    $finalCeSpike = $this->calcVolSpike($finalCeVol, $priorFinalCe);
                    $finalPeSpike = $this->calcVolSpike($finalPeVol, $priorFinalPe);
                    if ($finalCeVol > 0) $priorFinalCe[] = $finalCeVol;
                    if ($finalPeVol > 0) $priorFinalPe[] = $finalPeVol;

                    // 15Min OI Sentiment (CE vs PE OI change interval-over-interval)
                    $curCeOi = $oiMap['CE'][$timeKey] ?? 0;
                    $curPeOi = $oiMap['PE'][$timeKey] ?? 0;

                    if ($idx === 0) {
                        $oiSentiment = $this->noSentiment();
                    } else {
                        $prevTime = $allTimes[$idx - 1];
                        $prevCeOi = $oiMap['CE'][$prevTime] ?? 0;
                        $prevPeOi = $oiMap['PE'][$prevTime] ?? 0;
                        $cePct    = $prevCeOi > 0 ? round((($curCeOi - $prevCeOi) / $prevCeOi) * 100, 2) : 0;
                        $pePct    = $prevPeOi > 0 ? round((($curPeOi - $prevPeOi) / $prevPeOi) * 100, 2) : 0;
                        $oiSentiment = array_merge(
                            $this->calcOiSignal($cePct, $pePct),
                            ['ce_oi_pct' => $cePct, 'pe_oi_pct' => $pePct, 'time' => $timeKey]
                        );
                    }

                    // Always store every interval — JS filters client-side by selected time.
                    // (In ALL mode the frontend will default to showing only the latest time,
                    //  but the user can pick any earlier candle from the time dropdown.)
                    $intervalResults[] = [
                            'time'        => $timeKey,
                            'atm_strike'  => $atmStrike,
                            // Actual strike values for each offset (for display in UI)
                            'strikes' => [
                                'm2' => $strikeByOffset[-2] ?? null,
                                'm1' => $strikeByOffset[-1] ?? null,
                                'atm'=> $strikeByOffset[0]  ?? $atmStrike,
                                'p1' => $strikeByOffset[1]  ?? null,
                                'p2' => $strikeByOffset[2]  ?? null,
                            ],
                            // CE spike per offset
                            'ce' => [
                                'm2'  => $spikeData['CE'][-2] ?? $this->noSpike(),
                                'm1'  => $spikeData['CE'][-1] ?? $this->noSpike(),
                                'atm' => $spikeData['CE'][0]  ?? $this->noSpike(),
                                'p1'  => $spikeData['CE'][1]  ?? $this->noSpike(),
                                'p2'  => $spikeData['CE'][2]  ?? $this->noSpike(),
                            ],
                            // PE spike per offset
                            'pe' => [
                                'm2'  => $spikeData['PE'][-2] ?? $this->noSpike(),
                                'm1'  => $spikeData['PE'][-1] ?? $this->noSpike(),
                                'atm' => $spikeData['PE'][0]  ?? $this->noSpike(),
                                'p1'  => $spikeData['PE'][1]  ?? $this->noSpike(),
                                'p2'  => $spikeData['PE'][2]  ?? $this->noSpike(),
                            ],
                            'final_ce'     => $finalCeSpike,
                            'final_pe'     => $finalPeSpike,
                            'final_ce_vol' => $finalCeVol,
                            'final_pe_vol' => $finalPeVol,
                            'oi_sentiment' => $oiSentiment,
                        ];
                }

                if (empty($intervalResults)) continue;

                $results[] = [
                    'symbol'          => $sym,
                    'expiry'          => $expiry,
                    'date'            => $today,
                    'mode'            => $isAll ? 'summary' : 'detail',
                    'atm_strike'      => $atmStrike,
                    'strike_interval' => $strikeInterval,
                    'intervals'       => $intervalResults,
                    'total_intervals' => count($intervalResults),
                    'latest_time'     => end($intervalResults)['time'] ?? null,
                ];
            }

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'today'             => $today,
                'is_today'          => $today === Carbon::today()->toDateString(),
                'mode'              => $isAll ? 'summary' : 'detail',
                'available_symbols' => $availableSymbols,
                'message'           => count($results) . ' symbol(s) loaded for ' . $today,
            ]);

        } catch (\Exception $e) {
            Log::error('VolumeSpike15 getSignals: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /** Min gap between consecutive CE strikes → strike interval */
    private function resolveStrikeInterval($rows): ?float
    {
        $strikes = $rows->where('instrument_type', 'CE')
            ->pluck('strike')->map(fn($s) => (float)$s)
            ->filter(fn($s) => $s > 0)->unique()->sort()->values()->toArray();

        if (count($strikes) < 2) return null;

        $minGap = PHP_INT_MAX;
        for ($i = 1; $i < count($strikes); $i++) {
            $gap = $strikes[$i] - $strikes[$i - 1];
            if ($gap > 0 && $gap < $minGap) $minGap = $gap;
        }
        return $minGap === PHP_INT_MAX ? null : (float)$minGap;
    }

    private function calcVolSpike(int $curVol, array $priorVols): array
    {
        if (empty($priorVols)) {
            return ['spike_ratio' => null, 'spike_label' => 'OPENING', 'spike_type' => 'OPENING', 'avg_vol' => 0, 'cur_vol' => $curVol];
        }
        $avg = array_sum($priorVols) / count($priorVols);
        if ($avg <= 0) {
            return ['spike_ratio' => null, 'spike_label' => 'N/A', 'spike_type' => 'NORMAL', 'avg_vol' => 0, 'cur_vol' => $curVol];
        }
        $ratio = round($curVol / $avg, 2);
        [$label, $type] = match (true) {
            $ratio >= 3.0 => ['EXTREME', 'EXTREME'],
            $ratio >= 2.0 => ['STRONG SPIKE', 'STRONG_SPIKE'],
            $ratio >= 1.5 => ['SPIKE', 'SPIKE'],
            $ratio >= 1.2 => ['ELEVATED', 'ELEVATED'],
            default       => ['NORMAL', 'NORMAL'],
        };
        return ['spike_ratio' => $ratio, 'spike_label' => $label, 'spike_type' => $type, 'avg_vol' => (int)round($avg), 'cur_vol' => $curVol];
    }

    private function noSpike(): array
    {
        return ['spike_ratio' => null, 'spike_label' => 'N/A', 'spike_type' => 'NORMAL', 'avg_vol' => 0, 'cur_vol' => 0];
    }

    private function calcOiSignal(float $cePct, float $pePct): array
    {
        if ($cePct > 0 && $pePct < 0)     { $signal='BEARISH'; $condition='CE ↑ + PE ↓'; $reason='Call buildup + Put unwinding'; }
        elseif ($cePct < 0 && $pePct > 0) { $signal='BULLISH'; $condition='CE ↓ + PE ↑'; $reason='Call unwinding + Put buildup'; }
        elseif ($cePct > 0 && $pePct > 0) { if ($pePct>$cePct) { $signal='BULLISH'; $condition='Both ↑ PE>CE'; $reason="PE stronger +{$pePct}%"; } else { $signal='BEARISH'; $condition='Both ↑ CE≥PE'; $reason="CE stronger +{$cePct}%"; } }
        else                               { if (abs($cePct)>abs($pePct)) { $signal='BULLISH'; $condition='Both ↓ |CE|>|PE|'; $reason="CE unwinding {$cePct}%"; } else { $signal='BEARISH'; $condition='Both ↓ |PE|≥|CE|'; $reason="PE unwinding {$pePct}%"; } }
        $diff = round(abs($cePct - $pePct), 2);
        $strength = $diff>3 ? 'Very Strong Signal' : ($diff>1.5 ? 'Strong Signal' : ($diff>0.5 ? 'Moderate Signal' : 'Weak Signal'));
        return compact('signal', 'condition', 'reason', 'strength', 'diff');
    }

    private function noSentiment(): array
    {
        return ['signal'=>'N/A','condition'=>'Opening candle','reason'=>'','strength'=>'N/A','diff'=>0,'ce_oi_pct'=>0,'pe_oi_pct'=>0,'time'=>null];
    }

    private function getNearestExpiry(string $sym, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
        if ($expiry) return $expiry;
        return OptionOhlcData::where('base_symbol', $sym)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }
}