<?php
// FILE: app/Http/Controllers/User/StraddleStrategyController.php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * StraddleStrategyController — BUY CE / BUY PE Signal Engine
 *
 * Data  : cp_option_ohlc_{timeframe} + cp_fut_ohlc_{timeframe}
 * Scope : analysis_configs + analysis_config_symbols
 *
 * ── Signal Philosophy (industry standard) ────────────────────────────────────
 *
 * For a Straddle/Strangle setup the core question is directional:
 * "Which leg is the market telling me to ride?"
 *
 * We score 5 independent factors and require a minimum confluence of 3/5
 * to fire a signal. Each factor is binary (fires or does not fire).
 *
 * Factor 1 — Futures Momentum (+1 each side)
 *   CE signal: FUT close > FUT open of the same candle (bullish candle)
 *   PE signal: FUT close < FUT open (bearish candle)
 *   Weight: this is the primary directional anchor.
 *
 * Factor 2 — OI Confirmation (+1 each side)
 *   CE signal: CE OI increasing + CE LTP rising (fresh call buying)
 *   PE signal: PE OI increasing + PE LTP rising (fresh put buying)
 *   Weight: OI + price rising = real conviction, not short covering.
 *
 * Factor 3 — Premium Momentum (+1 each side)
 *   CE signal: CE LTP change % > PE LTP change % from candle open
 *              (calls gaining faster than puts = bullish premium flow)
 *   PE signal: PE LTP change % > CE LTP change % from candle open
 *   Weight: premium differential shows which side the market is chasing.
 *
 * Factor 4 — PCR (Put-Call Ratio) (+1 each side)
 *   CE signal: PCR < 0.8  → low put OI relative to calls = bullish
 *   PE signal: PCR > 1.2  → high put OI relative to calls = bearish
 *   Neutral  : 0.8 ≤ PCR ≤ 1.2 → no vote
 *   Weight: PCR is the market-wide directional gauge.
 *
 * Factor 5 — Candle Structure (+1 each side)
 *   CE signal: CE high > prev CE high AND CE close > CE open (CE breakout candle)
 *   PE signal: PE high > prev PE high AND PE close > PE open (PE breakout candle)
 *   Weight: price action confirms the premium move is real.
 *
 * ── Final Signal ─────────────────────────────────────────────────────────────
 *   BUY CE : CE score >= 3  AND CE score > PE score
 *   BUY PE : PE score >= 3  AND PE score > CE score
 *   WAIT   : No side reaches 3, or both sides tied
 *
 * ── Modes ────────────────────────────────────────────────────────────────────
 *   ALL    → latest candle only, one row per symbol
 *   SYMBOL → every candle row for that symbol (see signal evolve candle by candle)
 */
class StraddleStrategyController extends Controller
{
    private const TIMEFRAMES = ['15min', '30min', '1hr'];
    private const ENTRY_SLOT = '09:15';

    // Minimum factors required to fire a directional signal (out of 5)
    private const MIN_SCORE  = 3;

    private const STRATEGIES = [
        'long_straddle'  => ['name' => 'Long Straddle',  'ce_pos' => 'ATM',   'pe_pos' => 'ATM'],
        'short_straddle' => ['name' => 'Short Straddle', 'ce_pos' => 'ATM',   'pe_pos' => 'ATM'],
        'long_strangle'  => ['name' => 'Long Strangle',  'ce_pos' => 'ATM+1', 'pe_pos' => 'ATM-1'],
        'short_strangle' => ['name' => 'Short Strangle', 'ce_pos' => 'ATM+1', 'pe_pos' => 'ATM-1'],
    ];

    // ─────────────────────────────────────────────────────────────────────────
    //  Page
    // ─────────────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Straddle & Strangle — Signal Engine';
        return view($this->activeTemplate . 'user.straddle-strategy.index', compact('pageTitle'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Data endpoint
    // ─────────────────────────────────────────────────────────────────────────

    public function getData(Request $request)
    {
        try {
            $timeframe = $this->resolveTimeframe($request);
            $stratKey  = $request->get('strategy', 'long_straddle');
            if (!isset(self::STRATEGIES[$stratKey])) $stratKey = 'long_straddle';
            $def = self::STRATEGIES[$stratKey];

            $date = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            $symbolFilter = strtoupper(trim($request->get('symbol', 'ALL')));
            $isAll        = ($symbolFilter === 'ALL');
            $isToday      = ($date === Carbon::today()->toDateString());

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => "No active Analysis Config for [{$timeframe}].",
                ]);
            }

            $allSymbols = $this->getConfigSymbols($config->id);
            if (empty($allSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.']);
            }

            $optTable = 'cp_option_ohlc_' . $timeframe;
            $futTable = 'cp_fut_ohlc_'    . $timeframe;

            $hasData = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $allSymbols)
                ->whereDate('trade_date', $date)
                ->exists();

            if (!$hasData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data for ' . $date . '. Market may have been closed.',
                    'available_symbols' => $allSymbols,
                ]);
            }

            $symbols = $isAll
                ? $allSymbols
                : (in_array($symbolFilter, $allSymbols) ? [$symbolFilter] : $allSymbols);

            // ── Load option rows ──────────────────────────────────────────────
            $optRows = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereIn('strike_position', [$def['ce_pos'], $def['pe_pos']])
                ->whereDate('trade_date', $date)
                ->where('is_missing', false)
                ->orderBy('base_symbol')
                ->orderBy('interval_time')
                ->select([
                    'base_symbol', 'instrument_type', 'strike_position',
                    'strike', 'trading_symbol', 'interval_time',
                    'open', 'high', 'low', 'close', 'oi', 'expiry_date', 'atm_strike',
                ])
                ->get();

            if ($optRows->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No data for [{$def['name']}] positions on {$date}.",
                    'available_symbols' => $allSymbols,
                ]);
            }

            // ── Load FUT rows ──────────────────────────────────────────────────
            $futRows = DB::table($futTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereDate('trade_date', $date)
                ->where('is_missing', false)
                ->orderBy('base_symbol')
                ->orderBy('interval_time')
                ->select(['base_symbol', 'interval_time', 'open', 'high', 'low', 'close', 'oi'])
                ->get();

            // Index FUT: [sym][HH:MM] = row
            $futIdx = [];
            foreach ($futRows as $r) {
                $sym = $r->base_symbol;
                $t   = Carbon::parse($r->interval_time)->format('H:i');
                $futIdx[$sym][$t] = $r;
            }

            // Index OPT: [sym][CE|PE][pos][HH:MM] = row
            $optIdx   = [];
            $allTimes = [];

            foreach ($optRows as $r) {
                $sym = $r->base_symbol;
                $t   = Carbon::parse($r->interval_time)->format('H:i');
                $optIdx[$sym][$r->instrument_type][$r->strike_position][$t] = $r;
                $allTimes[$sym][$t] = true;
            }

            foreach ($allTimes as $sym => &$tMap) {
                ksort($tMap);
                $tMap = array_keys($tMap);
            }
            unset($tMap);

            // Latest complete slot per symbol
            $nowMins    = Carbon::now()->hour * 60 + Carbon::now()->minute;
            $candleMins = $timeframe === '1hr' ? 60 : ($timeframe === '30min' ? 30 : 15);
            $latestSlot = [];

            foreach ($allTimes as $sym => $times) {
                foreach (array_reverse($times) as $t) {
                    [$h, $m]  = explode(':', $t);
                    $slotMins = (int)$h * 60 + (int)$m;
                    if (!$isToday || ($slotMins + $candleMins) <= $nowMins) {
                        $latestSlot[$sym] = $t;
                        break;
                    }
                }
                if (!isset($latestSlot[$sym]) && !empty($times)) {
                    $latestSlot[$sym] = end($times);
                }
            }

            // ═════════════════════════════════════════════════════════════════
            //  MODE 1: ALL symbols — latest candle, one row per symbol
            // ═════════════════════════════════════════════════════════════════
            if ($isAll) {
                $results = [];
                foreach ($allSymbols as $sym) {
                    if (!isset($optIdx[$sym])) continue;
                    $latest = $latestSlot[$sym] ?? null;
                    if (!$latest) continue;

                    $row = $this->buildSummaryRow($sym, $def, $optIdx[$sym], $futIdx[$sym] ?? [], $latest);
                    if ($row) $results[] = $row;
                }

                return response()->json([
                    'success'           => true,
                    'mode'              => 'summary',
                    'data'              => $results,
                    'strategy_key'      => $stratKey,
                    'strategy_name'     => $def['name'],
                    'ce_pos'            => $def['ce_pos'],
                    'pe_pos'            => $def['pe_pos'],
                    'date'              => $date,
                    'is_today'          => $isToday,
                    'timeframe'         => $timeframe,
                    'total'             => count($results),
                    'buy_ce_count'      => count(array_filter($results, fn($r) => $r['signal'] === 'BUY_CE')),
                    'buy_pe_count'      => count(array_filter($results, fn($r) => $r['signal'] === 'BUY_PE')),
                    'wait_count'        => count(array_filter($results, fn($r) => $r['signal'] === 'WAIT')),
                    'available_symbols' => $allSymbols,
                    'min_score'         => self::MIN_SCORE,
                ]);
            }

            // ═════════════════════════════════════════════════════════════════
            //  MODE 2: Single symbol — every candle
            // ═════════════════════════════════════════════════════════════════
            $sym = $symbolFilter;
            if (!isset($optIdx[$sym])) {
                return response()->json([
                    'success'           => false,
                    'message'           => "No data for {$sym} on {$date}.",
                    'available_symbols' => $allSymbols,
                ]);
            }

            $times   = $allTimes[$sym]  ?? [];
            $ceSlots = $optIdx[$sym]['CE'][$def['ce_pos']] ?? [];
            $peSlots = $optIdx[$sym]['PE'][$def['pe_pos']] ?? [];
            $futSym  = $futIdx[$sym]    ?? [];

            $ceEntry   = $ceSlots[self::ENTRY_SLOT] ?? reset($ceSlots) ?: null;
            $peEntry   = $peSlots[self::ENTRY_SLOT] ?? reset($peSlots) ?: null;
            $atmStrike = $ceEntry?->atm_strike ?? $peEntry?->atm_strike ?? null;
            $expiry    = $ceEntry?->expiry_date ?? $peEntry?->expiry_date ?? null;

            $rows = [];
            $prevCe = null;
            $prevPe = null;
            $prevFut = null;

            foreach ($times as $t) {
                $ce  = $ceSlots[$t] ?? null;
                $pe  = $peSlots[$t] ?? null;
                $fut = $futSym[$t]  ?? null;

                $signal = $this->calcSignal($ce, $pe, $fut, $prevCe, $prevPe, $prevFut, $ceSlots, $peSlots, $futSym);

                // Total CE and PE OI at this slot (for PCR)
                $totalCeOi = array_sum(array_filter(array_map(
                    fn($pos) => isset($optIdx[$sym]['CE'][$pos][$t]) ? (int)$optIdx[$sym]['CE'][$pos][$t]->oi : 0,
                    array_keys($optIdx[$sym]['CE'] ?? [])
                )));
                $totalPeOi = array_sum(array_filter(array_map(
                    fn($pos) => isset($optIdx[$sym]['PE'][$pos][$t]) ? (int)$optIdx[$sym]['PE'][$pos][$t]->oi : 0,
                    array_keys($optIdx[$sym]['PE'] ?? [])
                )));
                $pcr = $totalCeOi > 0 ? round($totalPeOi / $totalCeOi, 3) : null;

                $rows[] = [
                    'time'            => $t,
                    'is_entry'        => ($t === self::ENTRY_SLOT),
                    'is_latest'       => ($t === ($latestSlot[$sym] ?? null)),
                    // CE
                    'ce_ltp'          => $ce  ? round((float)$ce->close, 2)  : null,
                    'ce_open'         => $ce  ? round((float)$ce->open,  2)  : null,
                    'ce_high'         => $ce  ? round((float)$ce->high,  2)  : null,
                    'ce_oi'           => $ce  ? (int)$ce->oi                 : null,
                    'ce_strike'       => $ce  ? (float)$ce->strike : ($ceEntry ? (float)$ceEntry->strike : null),
                    // PE
                    'pe_ltp'          => $pe  ? round((float)$pe->close, 2)  : null,
                    'pe_open'         => $pe  ? round((float)$pe->open,  2)  : null,
                    'pe_high'         => $pe  ? round((float)$pe->high,  2)  : null,
                    'pe_oi'           => $pe  ? (int)$pe->oi                 : null,
                    'pe_strike'       => $pe  ? (float)$pe->strike : ($peEntry ? (float)$peEntry->strike : null),
                    // FUT
                    'spot'            => $fut ? round((float)$fut->close, 2) : null,
                    'fut_open'        => $fut ? round((float)$fut->open,  2) : null,
                    // Combined
                    'combined_prem'   => ($ce && $pe) ? round((float)$ce->close + (float)$pe->close, 2) : null,
                    'pcr'             => $pcr,
                    // Signal
                    'signal'          => $signal['signal'],
                    'ce_score'        => $signal['ce_score'],
                    'pe_score'        => $signal['pe_score'],
                    'factors'         => $signal['factors'],
                    'reason'          => $signal['reason'],
                ];

                $prevCe  = $ce;
                $prevPe  = $pe;
                $prevFut = $fut;
            }

            return response()->json([
                'success'           => true,
                'mode'              => 'detail',
                'data'              => $rows,
                'symbol'            => $sym,
                'expiry'            => $expiry ? substr($expiry, 0, 10) : null,
                'atm_strike'        => $atmStrike,
                'ce_pos'            => $def['ce_pos'],
                'pe_pos'            => $def['pe_pos'],
                'strategy_key'      => $stratKey,
                'strategy_name'     => $def['name'],
                'date'              => $date,
                'is_today'          => $isToday,
                'timeframe'         => $timeframe,
                'entry_slot'        => self::ENTRY_SLOT,
                'latest_slot'       => $latestSlot[$sym] ?? null,
                'total_intervals'   => count($rows),
                'available_symbols' => $allSymbols,
                'min_score'         => self::MIN_SCORE,
            ]);

        } catch (\Exception $e) {
            Log::error('StraddleStrategy: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Core Signal Engine — 5-factor scoring
    //
    //  Each factor returns 1 for CE or 1 for PE (never both).
    //  Requires MIN_SCORE (3) from the same side to fire.
    // ─────────────────────────────────────────────────────────────────────────

    private function calcSignal(
        ?object $ce, ?object $pe, ?object $fut,
        ?object $prevCe, ?object $prevPe, ?object $prevFut,
        array $ceSlots, array $peSlots, array $futSlots
    ): array {

        $ceScore  = 0;
        $peScore  = 0;
        $factors  = [];

        // ── FACTOR 1: Futures Momentum ────────────────────────────────────────
        // The single most important directional anchor.
        // Bullish candle (close > open) = CE, Bearish (close < open) = PE.
        if ($fut) {
            $futOpen  = (float) $fut->open;
            $futClose = (float) $fut->close;
            $futBody  = $futClose - $futOpen;

            // Require meaningful body (at least 0.05% of price) to avoid noise
            $minBody = $futOpen * 0.0005;

            if ($futBody > $minBody) {
                $ceScore++;
                $factors[] = ['name' => 'Futures Momentum', 'side' => 'CE',
                    'detail' => 'FUT bullish candle (+' . round($futBody, 2) . ')'];
            } elseif ($futBody < -$minBody) {
                $peScore++;
                $factors[] = ['name' => 'Futures Momentum', 'side' => 'PE',
                    'detail' => 'FUT bearish candle (' . round($futBody, 2) . ')'];
            } else {
                $factors[] = ['name' => 'Futures Momentum', 'side' => 'NEUTRAL',
                    'detail' => 'FUT doji — no direction'];
            }
        } else {
            $factors[] = ['name' => 'Futures Momentum', 'side' => 'N/A', 'detail' => 'No FUT data'];
        }

        // ── FACTOR 2: OI Confirmation ─────────────────────────────────────────
        // Fresh buying = OI rising + LTP rising simultaneously.
        // Short covering = OI falling + LTP rising (weaker, does not vote).
        $ceOiRising  = $ce && $prevCe && (int)$ce->oi > (int)$prevCe->oi;
        $peLtpRising = $ce && $prevCe && (float)$ce->close > (float)$prevCe->close;
        $ceFreshBuy  = $ceOiRising && $peLtpRising;

        $peOiRising  = $pe && $prevPe && (int)$pe->oi > (int)$prevPe->oi;
        $pePeLtpRising = $pe && $prevPe && (float)$pe->close > (float)$prevPe->close;
        $peFreshBuy  = $peOiRising && $pePeLtpRising;

        if ($ceFreshBuy && !$peFreshBuy) {
            $ceScore++;
            $factors[] = ['name' => 'OI Confirmation', 'side' => 'CE',
                'detail' => 'CE OI ↑ + CE LTP ↑ → fresh call buying'];
        } elseif ($peFreshBuy && !$ceFreshBuy) {
            $peScore++;
            $factors[] = ['name' => 'OI Confirmation', 'side' => 'PE',
                'detail' => 'PE OI ↑ + PE LTP ↑ → fresh put buying'];
        } elseif ($ceFreshBuy && $peFreshBuy) {
            $factors[] = ['name' => 'OI Confirmation', 'side' => 'NEUTRAL',
                'detail' => 'Both CE and PE fresh buying — market uncertain'];
        } else {
            $factors[] = ['name' => 'OI Confirmation', 'side' => 'NEUTRAL',
                'detail' => 'No fresh buying detected'];
        }

        // ── FACTOR 3: Premium Momentum ────────────────────────────────────────
        // Which option is gaining faster within the candle?
        // CE candle return vs PE candle return — larger return side wins.
        if ($ce && $pe) {
            $ceOpen   = (float) $ce->open;
            $peOpen   = (float) $pe->open;
            $ceLtp    = (float) $ce->close;
            $peLtp    = (float) $pe->close;

            $cePremChg = $ceOpen > 0 ? (($ceLtp - $ceOpen) / $ceOpen) * 100 : 0;
            $pePremChg = $peOpen > 0 ? (($peLtp - $peOpen) / $peOpen) * 100 : 0;
            $diff      = round($cePremChg - $pePremChg, 2);

            // Need at least 2% differential to count as a vote
            if ($diff > 2.0) {
                $ceScore++;
                $factors[] = ['name' => 'Premium Momentum', 'side' => 'CE',
                    'detail' => 'CE premium gaining +'  . round($cePremChg, 1) . '% vs PE ' . round($pePremChg, 1) . '%'];
            } elseif ($diff < -2.0) {
                $peScore++;
                $factors[] = ['name' => 'Premium Momentum', 'side' => 'PE',
                    'detail' => 'PE premium gaining +' . round($pePremChg, 1) . '% vs CE ' . round($cePremChg, 1) . '%'];
            } else {
                $factors[] = ['name' => 'Premium Momentum', 'side' => 'NEUTRAL',
                    'detail' => 'Premium differential too small (' . $diff . '%)'];
            }
        } else {
            $factors[] = ['name' => 'Premium Momentum', 'side' => 'N/A', 'detail' => 'Missing CE or PE data'];
        }

        // ── FACTOR 4: PCR (Put-Call Ratio) ────────────────────────────────────
        // Computed from ALL CE and PE OI at this time slot, not just ATM.
        // PCR < 0.8 = bullish (less puts relative to calls)
        // PCR > 1.2 = bearish (more puts = hedging = directional fear)
        if ($ce && $pe) {
            $totalCeOi = (int) $ce->oi;
            $totalPeOi = (int) $pe->oi;
            $pcr       = $totalCeOi > 0 ? round($totalPeOi / $totalCeOi, 3) : null;

            if ($pcr !== null) {
                if ($pcr < 0.80) {
                    $ceScore++;
                    $factors[] = ['name' => 'PCR', 'side' => 'CE',
                        'detail' => 'PCR ' . $pcr . ' < 0.80 → bullish bias'];
                } elseif ($pcr > 1.20) {
                    $peScore++;
                    $factors[] = ['name' => 'PCR', 'side' => 'PE',
                        'detail' => 'PCR ' . $pcr . ' > 1.20 → bearish bias'];
                } else {
                    $factors[] = ['name' => 'PCR', 'side' => 'NEUTRAL',
                        'detail' => 'PCR ' . $pcr . ' (neutral range 0.80–1.20)'];
                }
            } else {
                $factors[] = ['name' => 'PCR', 'side' => 'N/A', 'detail' => 'No CE OI'];
            }
        } else {
            $factors[] = ['name' => 'PCR', 'side' => 'N/A', 'detail' => 'Missing data'];
        }

        // ── FACTOR 5: Candle Structure ────────────────────────────────────────
        // Is this a breakout candle on the CE or PE side?
        // CE breakout: CE high > prev CE high + CE is a bullish candle
        // PE breakout: PE high > prev PE high + PE is a bullish candle
        $ceBreakout = $ce && $prevCe
            && (float)$ce->high  > (float)$prevCe->high
            && (float)$ce->close > (float)$ce->open;

        $peBreakout = $pe && $prevPe
            && (float)$pe->high  > (float)$prevPe->high
            && (float)$pe->close > (float)$pe->open;

        if ($ceBreakout && !$peBreakout) {
            $ceScore++;
            $factors[] = ['name' => 'Candle Structure', 'side' => 'CE',
                'detail' => 'CE breaking out: new high + bullish close'];
        } elseif ($peBreakout && !$ceBreakout) {
            $peScore++;
            $factors[] = ['name' => 'Candle Structure', 'side' => 'PE',
                'detail' => 'PE breaking out: new high + bullish close'];
        } elseif ($ceBreakout && $peBreakout) {
            $factors[] = ['name' => 'Candle Structure', 'side' => 'NEUTRAL',
                'detail' => 'Both legs breaking out — volatility expansion'];
        } else {
            $factors[] = ['name' => 'Candle Structure', 'side' => 'NEUTRAL',
                'detail' => 'No breakout structure on either leg'];
        }

        // ── Final Signal ──────────────────────────────────────────────────────
        $signal = 'WAIT';
        $reason = 'Score below threshold (' . $ceScore . '/' . self::MIN_SCORE . ' CE, ' . $peScore . '/' . self::MIN_SCORE . ' PE)';

        if ($ceScore >= self::MIN_SCORE && $ceScore > $peScore) {
            $signal = 'BUY_CE';
            $reason = 'CE score ' . $ceScore . '/5 — ' . implode(', ', array_column(
                array_filter($factors, fn($f) => $f['side'] === 'CE'), 'name'
            ));
        } elseif ($peScore >= self::MIN_SCORE && $peScore > $ceScore) {
            $signal = 'BUY_PE';
            $reason = 'PE score ' . $peScore . '/5 — ' . implode(', ', array_column(
                array_filter($factors, fn($f) => $f['side'] === 'PE'), 'name'
            ));
        } elseif ($ceScore >= self::MIN_SCORE && $peScore >= self::MIN_SCORE) {
            $signal = 'WAIT';
            $reason = 'Both CE (' . $ceScore . ') and PE (' . $peScore . ') scoring equally — no clear direction';
        }

        return [
            'signal'   => $signal,
            'ce_score' => $ceScore,
            'pe_score' => $peScore,
            'factors'  => $factors,
            'reason'   => $reason,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Build summary row (ALL mode — latest candle only)
    // ─────────────────────────────────────────────────────────────────────────

    private function buildSummaryRow(
        string $sym, array $def, array $symIdx,
        array $futSym, string $latest
    ): ?array {
        $ceSlots = $symIdx['CE'][$def['ce_pos']] ?? [];
        $peSlots = $symIdx['PE'][$def['pe_pos']] ?? [];

        $ce  = $ceSlots[$latest]                    ?? null;
        $pe  = $peSlots[$latest]                    ?? null;
        $fut = $futSym[$latest]                     ?? null;

        // Previous slot for factor calculations
        $sortedTimes = array_keys($ceSlots + $peSlots);
        sort($sortedTimes);
        $prevTime    = null;
        foreach ($sortedTimes as $t) {
            if ($t >= $latest) break;
            $prevTime = $t;
        }

        $prevCe  = $prevTime ? ($ceSlots[$prevTime] ?? null) : null;
        $prevPe  = $prevTime ? ($peSlots[$prevTime] ?? null) : null;
        $prevFut = $prevTime ? ($futSym[$prevTime]  ?? null) : null;

        if (!$ce && !$pe) return null;

        $anyRow    = $ce ?? $pe;
        $atmStrike = $anyRow?->atm_strike;
        $expiry    = $anyRow?->expiry_date ? substr($anyRow->expiry_date, 0, 10) : null;

        $ceOi  = $ce  ? (int)$ce->oi                 : null;
        $peOi  = $pe  ? (int)$pe->oi                 : null;
        $ceLtp = $ce  ? round((float)$ce->close, 2)  : null;
        $peLtp = $pe  ? round((float)$pe->close, 2)  : null;
        $spot  = $fut ? round((float)$fut->close, 2) : null;
        $pcr   = ($ceOi && $ceOi > 0 && $peOi !== null) ? round($peOi / $ceOi, 3) : null;

        $signal = $this->calcSignal($ce, $pe, $fut, $prevCe, $prevPe, $prevFut, $ceSlots, $peSlots, $futSym);

        return [
            'symbol'         => $sym,
            'atm_strike'     => $atmStrike,
            'expiry'         => $expiry,
            'latest_slot'    => $latest,
            'spot'           => $spot,
            'ce_strike'      => $ce ? (float)$ce->strike : null,
            'pe_strike'      => $pe ? (float)$pe->strike : null,
            'ce_ltp'         => $ceLtp,
            'pe_ltp'         => $peLtp,
            'ce_oi'          => $ceOi,
            'pe_oi'          => $peOi,
            'combined_prem'  => ($ceLtp !== null && $peLtp !== null) ? round($ceLtp + $peLtp, 2) : null,
            'pcr'            => $pcr,
            'signal'         => $signal['signal'],
            'ce_score'       => $signal['ce_score'],
            'pe_score'       => $signal['pe_score'],
            'factors'        => $signal['factors'],
            'reason'         => $signal['reason'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getActiveConfig(string $timeframe): ?object
    {
        return DB::table('analysis_configs')->where('time_frame', $timeframe)->where('is_active', 1)->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')->toArray();
    }

    private function resolveTimeframe(Request $request): string
    {
        $tf = strtolower(trim($request->get('timeframe', '15min')));
        return in_array($tf, self::TIMEFRAMES) ? $tf : '15min';
    }
}