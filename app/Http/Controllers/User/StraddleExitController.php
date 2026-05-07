<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * StraddleExitController v3 — Next-Day Straddle Exit Plan
 *
 * ENTRY  : Buy ATM CE + PE at 3 PM (Day 1)
 * ANALYSIS: Next-day exit plan from ATR · Gap · Delta · IV · OI
 *
 * ── v2 FIXES (retained) ──────────────────────────────────
 * FIX 1 — ATR-linked targets (not fixed %)
 * FIX 2 — ATM strike only with smart interval rounding + fallback
 * FIX 3 — Time-decay model (hard time-stops per gap strength)
 * FIX 4 — Gap strength tiers: STRONG >2.5% / MODERATE >1% / WEAK
 * FIX 5 — Breakout levels: prevClose ± ATR×0.5
 * FIX 6 — Reversal play gated by OI balance (|diff| < 10)
 *
 * ── v3 FIXES (new) ───────────────────────────────────────
 * FIX 7 — Dynamic delta per gap direction
 *   Gap Up  : ceDelta=0.65, peDelta=0.28  (CE ITM, PE OTM)
 *   Gap Down: peDelta=0.65, ceDelta=0.28  (PE ITM, CE OTM)
 *   Flat    : both use stock-type delta (~0.45–0.60)
 *   Each leg now gets its own correct delta — winner target
 *   is higher, loser SL is tighter, matching market reality.
 *
 * FIX 8 — IV expansion boost on winner side
 *   Uses option ATR as IV proxy. On gap days, winning leg
 *   benefits from IV expansion (premium explodes beyond delta).
 *   ivBoost = 0.25 (STRONG) / 0.10 (MODERATE) / 0 (WEAK)
 *   Applied as % of entry price added on top of delta target.
 *
 * FIX 9 — Decay penalty on loser SL
 *   Loser bleeds from: price move + IV crush + theta.
 *   decayPenalty = 15% of entry added to base SL reduction.
 *   Makes loser SL more realistic (exits earlier = less loss).
 *
 * FIX 10 — Breakout confirmation rule (enforced as output field)
 *   confirmation_rule tells trader: CE only valid above breakoutAbove,
 *   PE only valid below breakoutBelow. Not just informational now.
 *
 * FIX 11 — Gap failure logic
 *   Gap Up + price falls back to prevClose → gap failure → exit CE
 *   Gap Down + price rises back to prevClose → gap failure → exit PE
 *   fail_level = prevClose (the level that invalidates the gap)
 *
 * FIX 12 — Reversal gap threshold tightened
 *   Was: gapType===FLAT + oiDiff<10
 *   Now: gapType===FLAT + abs(gapPct)<0.8 + oiDiff<10
 *   A 0.9% gap technically stays FLAT but is too large for reversal.
 *
 * ── STOCK CLASSIFICATION ──────────────────────────────────
 *   ATR% > 2.5  → TRENDING
 *   ATR% > 1.2  → MODERATE
 *   ATR% ≤ 1.2  → RANGE
 */
class StraddleExitController extends Controller
{
    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Straddle Exit Planner';
        return view($this->activeTemplate . 'user.straddle-exit.index', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  MAIN ENDPOINT
    // =========================================================

    public function analyzeExit(Request $request)
    {
        try {
            $entryDate       = $request->get('entry_date');
            $selectedSymbols = $request->get('symbols', []);

            if (!$entryDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select the entry date (Day 1)',
                    'data'    => [],
                ]);
            }

            $exitDate = $this->getNextTradingDate($entryDate);
            $results  = $this->buildStraddleRows($entryDate, $exitDate, $selectedSymbols);

            usort($results, fn($a, $b) => $a['symbol'] <=> $b['symbol']);

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'entry_date'    => $entryDate,
                'exit_date'     => $exitDate,
                'total_records' => count($results),
                'message'       => count($results) . ' symbols found',
            ]);

        } catch (\Exception $e) {
            Log::error('Straddle Exit v2 Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS
    // =========================================================

    private function buildStraddleRows(string $entryDate, string $exitDate, array $symbolFilter): array
    {
        $futQuery = OptionOhlcData::whereDate('trade_date', $entryDate)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) $futQuery->whereIn('base_symbol', $symbolFilter);
        $futCandles = $futQuery->get()->keyBy('base_symbol');

        if ($futCandles->isEmpty()) return [];

        $rows = [];

        foreach ($futCandles->keys()->toArray() as $symbol) {

            // ── Prev close (entry day 15:00 FUT) ──────────────────
            $prevClose = (float) OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $entryDate)
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->value('close');

            if ($prevClose <= 0) continue;

            // ── FIX 2: ATM strike (smart rounding) ────────────────
            $atmStrike = $this->getAtmStrike($prevClose);

            // ── Expiry ────────────────────────────────────────────
            $entryExpiry = $this->getNearestExpiryForDate($symbol, $entryDate);

            // ── CE Entry @ 15:00 — ATM strike only ────────────────
            $ceEntryQ = OptionOhlcData::whereDate('trade_date', $entryDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->where('strike', $atmStrike);
            if ($entryExpiry) $ceEntryQ->whereDate('expiry_date', $entryExpiry);
            $ceEntry = (float) $ceEntryQ->value('close');

            // ── PE Entry @ 15:00 — ATM strike only ────────────────
            $peEntryQ = OptionOhlcData::whereDate('trade_date', $entryDate)
                ->where('base_symbol', $symbol)
                ->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->where('strike', $atmStrike);
            if ($entryExpiry) $peEntryQ->whereDate('expiry_date', $entryExpiry);
            $peEntry = (float) $peEntryQ->value('close');

            // Fallback: if exact ATM has no data, try nearest available strike
            if ($ceEntry <= 0 || $peEntry <= 0) {
                [$ceEntry, $peEntry, $atmStrike] = $this->getFallbackAtmPrices(
                    $symbol, $entryDate, $entryExpiry, $prevClose
                );
            }

            if ($ceEntry <= 0 && $peEntry <= 0) continue;

            // ── Next-day open ──────────────────────────────────────
            $todayOpen = (float) OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', 'FUT')
                ->whereDate('trade_date', $exitDate)
                ->whereRaw("TIME(interval_time) = '09:15:00'")
                ->value('open');

            // ── Gap (FIX 4 — strength tiers) ──────────────────────
            $gapPct      = $prevClose > 0 ? round((($todayOpen - $prevClose) / $prevClose) * 100, 2) : 0;
            $gapType     = $this->classifyGapType($gapPct);
            $gapStrength = $this->classifyGapStrength($gapPct);

            // ── ATR (FUT, 14-day) ─────────────────────────────────
            $atr = $this->calculateATR($symbol, $entryDate, 14);

            // ── Stock type ─────────────────────────────────────────
            $stockType = $this->classifyStock($atr, $prevClose);

            // ── OI data ────────────────────────────────────────────
            $prevEntryDate = $this->getPreviousTradingDate($entryDate);
            $oiData        = $this->getOIChangeForDate($symbol, $entryDate, $prevEntryDate, $entryExpiry);

            // ── Option ATRs (5-day, ATM strike) ───────────────────
            $ceAtr = $this->getOptionATR($symbol, 'CE', $entryDate, $entryExpiry, $atmStrike, 5);
            $peAtr = $this->getOptionATR($symbol, 'PE', $entryDate, $entryExpiry, $atmStrike, 5);

            // ── Build exit plan ────────────────────────────────────
            $exitPlan = $this->buildExitPlan(
                $ceEntry, $peEntry,
                $atr, $gapPct, $gapType, $gapStrength,
                $stockType, $prevClose,
                $oiData
            );

            $rows[] = [
                'symbol'       => $symbol,
                'entry_date'   => $entryDate,
                'exit_date'    => $exitDate,

                // Entry
                'atm_strike'   => $atmStrike,
                'ce_entry'     => round($ceEntry, 2),
                'pe_entry'     => round($peEntry, 2),
                'total_cost'   => round($ceEntry + $peEntry, 2),

                // Market context
                'prev_close'   => round($prevClose, 2),
                'today_open'   => round($todayOpen, 2),
                'gap_pct'      => $gapPct,
                'gap_type'     => $gapType,
                'gap_strength' => $gapStrength,
                'atr'          => round($atr, 2),
                'stock_type'   => $stockType,

                // Option ATRs (ATM only)
                'ce_atr'       => round($ceAtr, 2),
                'pe_atr'       => round($peAtr, 2),

                // OI
                'oi_strategy'  => $oiData['strategy'],
                'oi_sentiment' => $oiData['sentiment'],
                'ce_oi_pct'    => $oiData['ce_pct'],
                'pe_oi_pct'    => $oiData['pe_pct'],

                // Exit plan (all fixes inside)
                'exit_plan'    => $exitPlan,
            ];
        }

        return $rows;
    }

    // =========================================================
    //  FIX 2 — ATM STRIKE HELPERS
    // =========================================================

    /**
     * Smart ATM rounding — auto-detects strike interval from price band.
     *
     * Price band → interval:
     *   < 500     → 5
     *   < 1000    → 10
     *   < 3000    → 50
     *   < 10000   → 100
     *   >= 10000  → 200
     */
    private function getAtmStrike(float $price): float
    {
        $interval = match(true) {
            $price < 500   => 5,
            $price < 1000  => 10,
            $price < 3000  => 50,
            $price < 10000 => 100,
            default        => 200,
        };

        return round($price / $interval) * $interval;
    }

    /**
     * If exact ATM strike has no data, find closest available strike.
     * Returns [$ceEntry, $peEntry, $usedStrike]
     */
    private function getFallbackAtmPrices(
        string $symbol,
        string $date,
        ?string $expiry,
        float $prevClose
    ): array {
        // Get nearest CE strike to ATM
        $ceRow = OptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', 'CE')
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->where('close', '>', 0)
            ->orderByRaw('ABS(strike - ?)', [$prevClose])
            ->first();

        $peRow = OptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $symbol)
            ->where('instrument_type', 'PE')
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->where('close', '>', 0)
            ->orderByRaw('ABS(strike - ?)', [$prevClose])
            ->first();

        $usedStrike = $ceRow ? (float)$ceRow->strike : ($peRow ? (float)$peRow->strike : 0);

        return [
            (float)($ceRow?->close ?? 0),
            (float)($peRow?->close ?? 0),
            $usedStrike,
        ];
    }

    // =========================================================
    //  FIX 4 — GAP CLASSIFICATION
    // =========================================================

    private function classifyGapType(float $gapPct): string
    {
        if ($gapPct > 1)  return 'GAP_UP';
        if ($gapPct < -1) return 'GAP_DOWN';
        return 'FLAT';
    }

    private function classifyGapStrength(float $gapPct): string
    {
        return match(true) {
            abs($gapPct) > 2.5 => 'STRONG',
            abs($gapPct) > 1.0 => 'MODERATE',
            default            => 'WEAK',
        };
    }

    // =========================================================
    //  EXIT PLAN ENGINE — v3 (ALL 12 FIXES APPLIED)
    // =========================================================

    private function buildExitPlan(
        float $ceEntry,
        float $peEntry,
        float $atr,
        float $gapPct,
        string $gapType,
        string $gapStrength,
        string $stockType,
        float $prevClose,
        array $oiData
    ): array {

        // ─────────────────────────────────────────────────────────
        //  STEP 1 — EXPECTED UNDERLYING MOVE (FIX 1)
        //  Conservative 80% of ATR = realistic intraday range
        // ─────────────────────────────────────────────────────────
        $expectedMove = round($atr * 0.8, 2);

        // Stock-type multiplier: how aggressively the move flows into option premium
        $multiplier = match($stockType) {
            'TRENDING' => 1.8,
            'MODERATE' => 1.3,
            default    => 1.0,
        };

        // ─────────────────────────────────────────────────────────
        //  STEP 2 — DYNAMIC DELTA PER GAP DIRECTION (FIX 7)
        //
        //  After a gap:
        //   - Winner side has moved ITM → delta rises to ~0.65
        //   - Loser  side has moved OTM → delta collapses to ~0.28
        //  Flat open: both legs stay near ATM → use stock-type delta
        // ─────────────────────────────────────────────────────────
        $baseDelta = match($stockType) {
            'TRENDING' => 0.60,
            'MODERATE' => 0.52,
            default    => 0.45,
        };

        if ($gapType === 'GAP_UP') {
            $ceDelta = 0.65; // CE moved ITM after gap up
            $peDelta = 0.28; // PE moved OTM — low sensitivity
        } elseif ($gapType === 'GAP_DOWN') {
            $peDelta = 0.65; // PE moved ITM after gap down
            $ceDelta = 0.28; // CE moved OTM — low sensitivity
        } else {
            $ceDelta = $baseDelta;
            $peDelta = $baseDelta;
        }

        // ─────────────────────────────────────────────────────────
        //  STEP 3 — IV EXPANSION BOOST ON WINNER SIDE (FIX 8)
        //
        //  On gap days, the winning option gets an IV spike on top
        //  of the delta move. We use a % of entry as an IV proxy
        //  (since we have historical option ATR but not live IV here).
        //
        //  STRONG gap → IV can spike 25%+ on winner premium
        //  MODERATE   → ~10% IV expansion
        //  WEAK/FLAT  → no expansion modelled
        // ─────────────────────────────────────────────────────────
        $ivBoost = match($gapStrength) {
            'STRONG'   => 0.25,
            'MODERATE' => 0.10,
            default    => 0.0,
        };

        // ─────────────────────────────────────────────────────────
        //  STEP 4 — DECAY PENALTY ON LOSER SIDE (FIX 9)
        //
        //  Loser bleeds from 3 sources: delta (price move against),
        //  IV crush (vol collapses on losing side), theta decay.
        //  15% of entry premium added to base SL reduction.
        // ─────────────────────────────────────────────────────────
        $decayPenalty = 0.15;

        // ─────────────────────────────────────────────────────────
        //  STEP 5 — COMPUTE CE & PE TARGETS + SL
        //
        //  Winner: entry + (move × winnerDelta × multiplier) + IV boost
        //  Loser SL: entry − (move × loserDelta × 0.5) − decay penalty
        // ─────────────────────────────────────────────────────────

        // CE as winner (Gap Up scenario)
        $ceWinnerAdd  = ($expectedMove * $ceDelta * $multiplier) + ($ceEntry * $ivBoost);
        $ceLoserSub   = ($expectedMove * $ceDelta * 0.50) + ($ceEntry * $decayPenalty);

        // PE as winner (Gap Down scenario)
        $peWinnerAdd  = ($expectedMove * $peDelta * $multiplier) + ($peEntry * $ivBoost);
        $peLoserSub   = ($expectedMove * $peDelta * 0.50) + ($peEntry * $decayPenalty);

        // Default (flat): symmetric using base delta
        $flatWinnerAdd = $expectedMove * $baseDelta * $multiplier;
        $flatLoserSub  = ($expectedMove * $baseDelta * 0.50) + ($ceEntry * $decayPenalty);

        // Assign based on gap direction
        if ($gapType === 'GAP_UP') {
            $ceTarget = round($ceEntry + $ceWinnerAdd, 2); // CE = winner
            $ceSL     = round($ceEntry - $ceLoserSub,  2); // CE SL (fallback — CE is winner so this won't trigger easily)
            $peTarget = round($peEntry + $peLoserSub,  2); // PE recovery target (unlikely but shown)
            $peSL     = round($peEntry - $peLoserSub,  2); // PE = loser SL (tighter due to decay)
        } elseif ($gapType === 'GAP_DOWN') {
            $peTarget = round($peEntry + $peWinnerAdd, 2); // PE = winner
            $peSL     = round($peEntry - $peLoserSub,  2); // PE SL (fallback)
            $ceTarget = round($ceEntry + $ceLoserSub,  2); // CE recovery target
            $ceSL     = round($ceEntry - $ceLoserSub,  2); // CE = loser SL
        } else {
            // Flat: symmetric
            $ceTarget = round($ceEntry + $flatWinnerAdd, 2);
            $ceSL     = round($ceEntry - $flatLoserSub,  2);
            $peTarget = round($peEntry + $flatWinnerAdd, 2);
            $peSL     = round($peEntry - $flatLoserSub,  2);
        }

        // Hard floor: SL never below 15% of entry (options can't go below 0)
        $ceSL = max($ceSL, round($ceEntry * 0.15, 2));
        $peSL = max($peSL, round($peEntry * 0.15, 2));

        // ─────────────────────────────────────────────────────────
        //  STEP 6 — TIME-DECAY SCHEDULE (FIX 3)
        // ─────────────────────────────────────────────────────────
        $loserMaxHold     = '10:30';
        $winnerTrailStart = '11:00';
        $hardExitTime     = '14:30';

        if ($gapStrength === 'STRONG') {
            $loserMaxHold = '09:45'; // no waiting on a strong gap — loser dies fast
        } elseif ($gapStrength === 'MODERATE') {
            $loserMaxHold = '10:00';
        }

        // ─────────────────────────────────────────────────────────
        //  STEP 7 — BREAKOUT LEVELS (FIX 5)
        // ─────────────────────────────────────────────────────────
        $breakoutAbove = round($prevClose + $atr * 0.5, 2);
        $breakoutBelow = round($prevClose - $atr * 0.5, 2);

        // ─────────────────────────────────────────────────────────
        //  STEP 8 — CONFIRMATION RULE (FIX 10)
        //  Tells trader which underlying price must be reached
        //  before the option move can be trusted
        // ─────────────────────────────────────────────────────────
        $confirmationRule = [
            'ce_valid_only_if_above' => $breakoutAbove,
            'pe_valid_only_if_below' => $breakoutBelow,
            'note'                   => "CE target reliable only if underlying stays above ₹{$breakoutAbove} | "
                                      . "PE target reliable only if underlying stays below ₹{$breakoutBelow}",
        ];

        // ─────────────────────────────────────────────────────────
        //  STEP 9 — GAP FAILURE LOGIC (FIX 11)
        //  If a gap reverses back to prevClose, it's a failed gap.
        //  The winning side can quickly reverse — exit immediately.
        // ─────────────────────────────────────────────────────────
        $failLevel    = $prevClose;
        $gapFailNote  = '';

        if ($gapType === 'GAP_UP') {
            $gapFailNote = "⚠ Gap Failure: If underlying falls back to ₹{$failLevel} → Exit CE immediately (gap trap).";
        } elseif ($gapType === 'GAP_DOWN') {
            $gapFailNote = "⚠ Gap Failure: If underlying rises back to ₹{$failLevel} → Exit PE immediately (short covering trap).";
        }

        // ─────────────────────────────────────────────────────────
        //  STEP 10 — WINNER/LOSER ASSIGNMENT + RULES
        // ─────────────────────────────────────────────────────────
        $winner         = 'WAIT';
        $loser          = 'WAIT';
        $entryTime      = '09:20–09:30';
        $winnerExitRule = '';
        $loserExitRule  = '';
        $reversalPlay   = false;
        $note           = '';

        if ($gapType === 'GAP_UP') {
            $winner = 'CE';
            $loser  = 'PE';

            $winnerExitRule = "Book 50% CE @ ₹{$ceTarget} | Trail rest from {$winnerTrailStart} | Hard exit {$hardExitTime}";
            $loserExitRule  = "Exit PE @ ₹{$peSL} OR by {$loserMaxHold} — whichever first | No recovery wait";

            $note = "Gap Up ({$gapStrength}) → CE runner (delta {$ceDelta}). "
                . "PE collapses (delta {$peDelta}). "
                . "Confirm above ₹{$breakoutAbove}. "
                . ($stockType === 'TRENDING' ? 'Trending — hold CE hard.' : '');

        } elseif ($gapType === 'GAP_DOWN') {
            $winner = 'PE';
            $loser  = 'CE';

            $winnerExitRule = "Book 50% PE @ ₹{$peTarget} | Trail rest from {$winnerTrailStart} | Hard exit {$hardExitTime}";
            $loserExitRule  = "Exit CE @ ₹{$ceSL} OR by {$loserMaxHold} — whichever first | No recovery wait";

            $note = "Gap Down ({$gapStrength}) → PE runner (delta {$peDelta}). "
                . "CE collapses (delta {$ceDelta}). "
                . "Confirm below ₹{$breakoutBelow}. "
                . ($stockType === 'TRENDING' ? 'Trending — hold PE hard.' : '');

        } else {
            $entryTime = '09:45–10:00';

            $winnerExitRule = "Once direction shows: book 50% winner at target | Trail from {$winnerTrailStart} | Hard exit {$hardExitTime}";
            $loserExitRule  = "Hold loser till {$loserMaxHold} for recovery | SL on price or time — whichever first";

            $note = "Flat open — wait till 09:45. "
                . "Expected move ±₹{$expectedMove}. "
                . "CE above ₹{$breakoutAbove} | PE below ₹{$breakoutBelow}.";

            // FIX 12: Reversal gated by gap size (<0.8%) + OI balance + non-trending
            $oiDiff = abs(($oiData['ce_pct'] ?? 0) - ($oiData['pe_pct'] ?? 0));
            if ($stockType !== 'TRENDING' && abs($gapPct) < 0.8 && $oiDiff < 10) {
                $reversalPlay = true;
                $note .= ' Gap tiny + OI balanced → Reversal play eligible.';
            }
        }

        // Add gap failure note to main note
        if ($gapFailNote) {
            $note .= ' ' . $gapFailNote;
        }

        // OI confirmation
        $oiConfirmation = $this->getOIConfirmation($oiData, $winner);

        // ─────────────────────────────────────────────────────────
        //  RETURN FULL PLAN
        // ─────────────────────────────────────────────────────────
        return [
            'winner'           => $winner,
            'loser'            => $loser,
            'entry_time'       => $entryTime,

            // CE exit
            'ce_target'        => $ceTarget,
            'ce_sl'            => $ceSL,
            'ce_target_pct'    => $ceEntry > 0 ? round((($ceTarget - $ceEntry) / $ceEntry) * 100, 1) : 0,
            'ce_sl_pct'        => $ceEntry > 0 ? round((($ceSL    - $ceEntry) / $ceEntry) * 100, 1) : 0,
            'ce_delta'         => $ceDelta,

            // PE exit
            'pe_target'        => $peTarget,
            'pe_sl'            => $peSL,
            'pe_target_pct'    => $peEntry > 0 ? round((($peTarget - $peEntry) / $peEntry) * 100, 1) : 0,
            'pe_sl_pct'        => $peEntry > 0 ? round((($peSL    - $peEntry) / $peEntry) * 100, 1) : 0,
            'pe_delta'         => $peDelta,

            // Market mechanics
            'expected_move'    => $expectedMove,
            'iv_boost_applied' => $ivBoost > 0 ? round($ivBoost * 100) . '%' : null,
            'decay_penalty'    => round($decayPenalty * 100) . '%',

            // Breakout levels
            'breakout_above'   => $breakoutAbove,
            'breakout_below'   => $breakoutBelow,
            'fail_level'       => $failLevel,
            'gap_fail_note'    => $gapFailNote,

            // FIX 10: Confirmation rule
            'confirmation_rule' => $confirmationRule,

            // Gap
            'gap_strength'     => $gapStrength,

            // Time-decay schedule
            'time_exit'        => [
                'loser_exit_by'      => $loserMaxHold,
                'winner_trail_start' => $winnerTrailStart,
                'hard_exit'          => $hardExitTime,
            ],

            // Human-readable rules
            'winner_exit_rule' => $winnerExitRule,
            'loser_exit_rule'  => $loserExitRule,
            'note'             => trim($note),
            'oi_confirmation'  => $oiConfirmation,

            // FIX 12: Reversal play (tightened conditions)
            'reversal_play'    => $reversalPlay,
            'reversal_note'    => $reversalPlay
                ? "Exit winner at +40% early. Hold loser till {$loserMaxHold} for reversal. Max loser loss = -60%."
                : null,
        ];
    }

    // =========================================================
    //  OI CONFIRMATION
    // =========================================================

    private function getOIConfirmation(array $oiData, string $winner): string
    {
        $strategy = $oiData['strategy'] ?? '';
        if ($winner === 'CE' && $strategy === 'BULLISH_DIRECTIONAL') return '✅ OI Confirms Bullish';
        if ($winner === 'PE' && $strategy === 'BEARISH_DIRECTIONAL') return '✅ OI Confirms Bearish';
        if ($strategy === 'LONG_STRADDLE')  return '⚡ OI Supports Volatility';
        if ($strategy === 'SHORT_STRADDLE') return '⚠ OI Suggests Range — careful';
        if ($winner !== 'WAIT' &&
            in_array($strategy, ['BULLISH_DIRECTIONAL', 'BEARISH_DIRECTIONAL'])) {
            return '⚠ OI Diverges from Gap — trade carefully';
        }
        return 'ℹ No OI Confirmation';
    }

    // =========================================================
    //  OI DATA FOR ENTRY DAY
    // =========================================================

    private function getOIChangeForDate(string $symbol, string $date, string $prevDate, ?string $expiry): array
    {
        $ceToday = (int) OptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->sum('oi');

        $ceOpen = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
            ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->sum('oi');

        $peToday = (int) OptionOhlcData::whereDate('trade_date', $date)
            ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
            ->whereRaw("TIME(interval_time) = '14:45:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->sum('oi');

        $pePrev = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
            ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
            ->whereRaw("TIME(interval_time) = '15:00:00'")
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->sum('oi');

        $cePct = $ceOpen > 0 ? round((($ceToday - $ceOpen) / $ceOpen) * 100, 2) : 0;
        $pePct = $pePrev > 0 ? round((($peToday - $pePrev) / $pePrev) * 100, 2) : 0;

        return [
            'ce_pct'    => $cePct,
            'pe_pct'    => $pePct,
            'strategy'  => $this->deriveOIStrategy($cePct, $pePct),
            'sentiment' => $this->deriveOISentiment($cePct, $pePct),
        ];
    }

    private function deriveOIStrategy(float $ce, float $pe): string
    {
        if ($ce < 0 && $pe < 0) return 'LONG_STRADDLE';
        if ($ce > 0 && $pe > 0) return 'SHORT_STRADDLE';
        if ($ce > 0 && $pe < 0) return 'BEARISH_DIRECTIONAL';
        if ($ce < 0 && $pe > 0) return 'BULLISH_DIRECTIONAL';
        return 'NO_TRADE';
    }

    private function deriveOISentiment(float $ce, float $pe): string
    {
        if ($ce > 0 && $pe < 0) return 'BEARISH';
        if ($ce < 0 && $pe > 0) return 'BULLISH';
        if ($ce > 0 && $pe > 0) return $ce > $pe ? 'BEARISH' : 'BULLISH';
        if ($ce < 0 && $pe < 0) return $ce < $pe ? 'BULLISH' : 'BEARISH';
        return 'NEUTRAL';
    }

    // =========================================================
    //  ATR — UNDERLYING (14-day)
    // =========================================================

    private function calculateATR(string $symbol, string $date, int $period = 14): float
    {
        $days = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', '<=', $date)
            ->select(
                DB::raw('DATE(trade_date) as d'),
                DB::raw('MAX(high) as day_high'),
                DB::raw('MIN(low) as day_low'),
                DB::raw('MAX(CASE WHEN TIME(interval_time) = "15:00:00" THEN close END) as day_close')
            )
            ->groupBy(DB::raw('DATE(trade_date)'))
            ->orderByDesc('d')
            ->limit($period + 1)
            ->get();

        if ($days->count() < 2) return 0;

        $days = $days->reverse()->values();
        $trs  = [];

        for ($i = 1; $i < $days->count(); $i++) {
            $cur    = $days[$i];
            $prev   = $days[$i - 1];
            $high   = (float) $cur->day_high;
            $low    = (float) $cur->day_low;
            $pClose = (float) $prev->day_close;
            if ($pClose <= 0) continue;
            $trs[] = max($high - $low, abs($high - $pClose), abs($low - $pClose));
        }

        return count($trs) ? round(array_sum($trs) / count($trs), 2) : 0;
    }

    // =========================================================
    //  ATR — OPTION (5-day, ATM strike only) — FIX 2
    // =========================================================

    private function getOptionATR(
        string $symbol,
        string $type,
        string $date,
        ?string $expiry,
        float $atmStrike,
        int $days = 5
    ): float {
        $query = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->where('strike', $atmStrike)
            ->whereDate('trade_date', '<=', $date)
            ->when($expiry, fn($q) => $q->whereDate('expiry_date', $expiry))
            ->select(
                DB::raw('DATE(trade_date) as d'),
                DB::raw('MAX(high) as day_high'),
                DB::raw('MIN(low) as day_low')
            )
            ->groupBy(DB::raw('DATE(trade_date)'))
            ->orderByDesc('d')
            ->limit($days)
            ->get();

        if ($query->isEmpty()) return 0;

        $ranges = $query->map(fn($r) => (float)$r->day_high - (float)$r->day_low)->toArray();
        return count($ranges) ? round(array_sum($ranges) / count($ranges), 2) : 0;
    }

    // =========================================================
    //  STOCK CLASSIFICATION
    // =========================================================

    private function classifyStock(float $atr, float $price): string
    {
        if ($price <= 0) return 'RANGE';
        $atrPct = ($atr / $price) * 100;
        if ($atrPct > 2.5) return 'TRENDING';
        if ($atrPct > 1.2) return 'MODERATE';
        return 'RANGE';
    }

    // =========================================================
    //  EXPIRY HELPERS
    // =========================================================

    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function getNextTradingDate(string $date): string
    {
        $next = Carbon::parse($date)->addDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$next->isWeekend() && !$this->isHoliday($next->format('Y-m-d'))) {
                return $next->format('Y-m-d');
            }
            $next->addDay();
            $attempts++;
        }
        return Carbon::parse($date)->addDay()->format('Y-m-d');
    }

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) {
                return $prev->format('Y-m-d');
            }
            $prev->subDay();
            $attempts++;
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}