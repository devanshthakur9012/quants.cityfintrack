<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ThirtyMinOhlcData;
use App\Models\NewPivotOrderConfig;
use App\Models\NewPivotOrder;
use App\Models\BrokerApi;
use App\Helpers\ZerodhaPivotHelper;
use App\Helpers\AngelPivotHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * PlacePivotOrders
 *
 * Supports BOTH Zerodha and Angel One brokers.
 * Broker type is auto-detected from broker_apis.client_type per config.
 *
 * Cron: 48,18 * * * *  (weekdays 09:30–15:30)
 * Runs ~2 min after the 30-min OHLC data collector (:46 and :16).
 *
 * ═══════════════════════════════════════════════════════════════════════
 * OI SENTIMENT-DRIVEN ORDER PLACEMENT (KEY LOGIC)
 * ═══════════════════════════════════════════════════════════════════════
 *
 * For each candle slot we compute the SAME 1hr OI Sentiment that the
 * frontend displays (PivotSignalController::calcOiSignal).
 *
 *   BULLISH sentiment:
 *     S1 → BUY  CE  (calls gain as market rises from support)
 *     R1 → SELL PE  (puts lose value as market pushes through resistance)
 *
 *   BEARISH sentiment:
 *     S1 → BUY  PE  (puts gain as market falls)
 *     R1 → SELL CE  (calls lose value as market drops)
 *
 *   N/A / no data → SKIP (do not place any order for this slot)
 *
 * KEY: S1 and R1 always trade OPPOSITE option types.
 * S1 buys the option that profits from the move.
 * R1 sells the OTHER side option that will decay/lose at that level.
 *
 * This mirrors exactly what the frontend highlights — if the 1hr OI chart
 * shows BULLISH, we only act on the CE side; if BEARISH, PE side only.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * STRIKE SELECTION — matches the frontend exactly
 * ═══════════════════════════════════════════════════════════════════════
 *
 *   BUY  orders → ATM strike  (strike_position = 'ATM')
 *   SELL orders → ATM±1 strike with highest volume for that slot
 *                 (same getBestNonAtmCandle logic)
 *
 * ═══════════════════════════════════════════════════════════════════════
 * SYMBOL FILTERING
 * ═══════════════════════════════════════════════════════════════════════
 *
 * Each config stores a `symbols` JSON array. Orders are placed ONLY for
 * symbols the user has explicitly selected. Empty symbols = skip config.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * FLOW
 * ═══════════════════════════════════════════════════════════════════════
 *
 * 1. Determine last completed 1hr candle slot.
 * 2. Load active configs that have ≥1 symbol selected.
 * 3. Discover symbols that have data for this date+slot (global pool).
 * 4. For each config:
 *      a. Intersect config symbols with global pool → effective symbols.
 *      b. Resolve broker → build ZerodhaPivotHelper or AngelPivotHelper.
 *      c. For each effective symbol:
 *           i.  Compute 1hr OI Sentiment for this slot (CE% vs PE% change).
 *           ii. Sentiment = BULLISH → use CE layers only.
 *               Sentiment = BEARISH → use PE layers only.
 *               Sentiment = N/A     → skip symbol for this slot.
 *          iii. Fetch ATM candle for the decided side (CE or PE).
 *           iv. For BUY layers → ATM strike.
 *               For SELL layers → ATM±1 strike by volume.
 *            v. Compute PP / S1 / R1 from that candle.
 *           vi. Place S1 layers (BUY side) and R1 layers (SELL side).
 *      e. Idempotency: skip if already placed for this config+symbol+slot+layer.
 */
class PlacePivotOrders extends Command
{
    protected $signature = 'pivot:place-orders
                            {--date=   : Override date (Y-m-d), default = today}
                            {--slot=   : Override candle slot (H:i), e.g. 09:15}
                            {--dry-run : Compute but do NOT send orders to broker}
                            {--config= : Run only a specific config ID}';

    protected $description = 'Place Zerodha / Angel One orders based on 1hr OI Sentiment pivot signals — BULLISH→CE, BEARISH→PE';

    // ── 1hr slot schedule (matches Live30MinOhlcCollector) ────────────────────
    private const INTERVALS = [
        '09:15', '10:15', '11:15', '12:15', '13:15', '14:15', '15:15',
    ];

    // ── Freeze limits in LOTS ─────────────────────────────────────────────────
    private const FREEZE_LIMITS = [
        'NIFTY'       => 18,  'BANKNIFTY'  => 20,  'FINNIFTY'   => 24,  'MIDCPNIFTY' => 24,
        'ADANIPORTS'  => 30,  'AMBUJACEM'  => 40,  'ASIANPAINT' => 40,  'AUROPHARMA' => 40,
        'AXISBANK'    => 30,  'BAJAJFINSV' => 50,  'BAJFINANCE' => 30,  'BHARATFORG' => 30,
        'BHARTIARTL'  => 30,  'BHEL'       => 30,  'BPCL'       => 30,  'BSE'        => 30,
        'CDSL'        => 30,  'COFORGE'    => 30,  'BDL'        => 40,  'DELHIVERY'  => 30,
        'DRREDDY'     => 30,  'ETERNAL'    => 30,  'FORTIS'     => 40,  'HAL'        => 40,
        'HAVELLS'     => 30,  'HEROMOTOCO' => 30,  'HINDALCO'   => 40,  'ICICIBANK'  => 30,
        'INDUSINDBK'  => 40,  'INFY'       => 40,  'JSWSTEEL'   => 30,  'LAURUSLABS' => 30,
        'LICHSGFIN'   => 40,  'LT'         => 40,  'LTF'        => 40,  'M&M'        => 30,
        'NATIONALUM'  => 20,  'PAYTM'      => 30,  'PGEL'       => 40,  'POLICYBZR'  => 40,
        'SBIN'        => 30,  'SHRIRAMFIN' => 30,  'SRF'        => 40,  'TATACONSUM' => 40,
        'TATAELXSI'   => 40,  'TATATECH'   => 50,  'TITAN'      => 40,  'TMPV'       => 50,
        'TCS'         => 40,  'UPL'        => 30,  'VBL'        => 40,  'VEDL'       => 30,
        'VOLTAS'      => 40,  'MCX'        => 20,
    ];

    /**
     * Cached broker helper instances keyed by broker_api.id.
     * @var array<int, ZerodhaPivotHelper|AngelPivotHelper>
     */
    private array $brokerHelpers = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $today    = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        // ── 1. Determine target candle slot ───────────────────────────────────
        $slot = $this->option('slot') ?: $this->getLastCompletedSlot();

        if (!$slot) {
            $this->warn('No completed 1hr slot yet. Exiting.');
            return 0;
        }

        $this->info("=== PlacePivotOrders (OI Sentiment Mode) | Date: {$today} | Slot: {$slot}" . ($isDryRun ? ' | DRY-RUN' : '') . ' ===');
        $this->info("   Rule: BULLISH sentiment → CE orders | BEARISH sentiment → PE orders | N/A → skip");

        // ── 2. Load active configs that have at least one symbol selected ─────
        $configQuery = NewPivotOrderConfig::where('status', true)
        ->where(function($q) {
            $q->where('interval_type', '1hr')->orWhereNull('interval_type');
        })
        ->with('broker');

        if ($this->option('config')) {
            $configQuery->where('id', $this->option('config'));
        }

        $configs = $configQuery->get()->filter(fn($c) => $c->hasSymbols());

        if ($configs->isEmpty()) {
            $this->warn('No active configs with symbols found. Exiting.');
            return 0;
        }

        $this->info("Found {$configs->count()} active config(s) with symbols.");

        // ── 3. Discover ALL symbols that have data for this date+slot ─────────
        $availableSymbols = ThirtyMinOhlcData::whereDate('trade_date', $today)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('base_symbol')
            ->distinct()
            ->pluck('base_symbol')
            ->toArray();

        if (empty($availableSymbols)) {
            $this->warn("No 1hr data found for date={$today} slot={$slot}. Has the collector run?");
            return 0;
        }

        $this->info('Symbols with data in DB: ' . implode(', ', $availableSymbols));

        // ── 4. Pre-compute previous trading day for OI baseline ───────────────
        $prevTradingDate = $this->getPreviousTradingDate($today);

        // ── 5. Process each config ─────────────────────────────────────────────
        $totalPlaced  = 0;
        $totalSkipped = 0;
        $totalErrors  = 0;

        foreach ($configs as $config) {

            // Intersect config's selected symbols with available DB symbols
            $configSymbols    = array_map('strtoupper', $config->symbols ?? []);
            $effectiveSymbols = array_values(array_intersect($configSymbols, $availableSymbols));

            if (empty($effectiveSymbols)) {
                $this->warn("  Config #{$config->id}: Selected symbols [" . implode(',', $configSymbols) . "] have no data for slot {$slot}. Skipping config.");
                continue;
            }

            $this->line("\n--- Config #{$config->id} | Symbols: [" . implode(', ', $effectiveSymbols) . "] ---");

            // Resolve broker record
            $brokerRecord = BrokerApi::where('id', $config->broker_api_id)->first();

            if (!$brokerRecord) {
                $this->error("  Config #{$config->id}: Broker record not found. Skipping.");
                continue;
            }

            $broker = match($brokerRecord->client_type) {
                'Zerodha'  => BrokerApi::where('id', $config->broker_api_id)->validToken()->first(),
                'AngelOne' => $brokerRecord,
                default    => null,
            };

            if (!$broker) {
                $this->error("  Config #{$config->id}: Broker token invalid/expired ({$brokerRecord->client_type}). Skipping.");
                continue;
            }

            $helper = $this->getBrokerHelper($broker);

            if (!$helper) {
                $this->error("  Config #{$config->id}: Unsupported broker type '{$broker->client_type}'. Skipping.");
                continue;
            }

            $this->line("  Broker: {$broker->client_name} | {$broker->client_type} | {$config->order_type}/{$config->product}");

            // Process only effective symbols
            foreach ($effectiveSymbols as $symbol) {

                $expiry = $this->getNearestExpiry($symbol, $today);
                if (!$expiry) {
                    $this->warn("  [{$symbol}] No expiry found. Skipping.");
                    continue;
                }

                // ── STEP A: Compute 1hr OI Sentiment for this slot ───────────
                // This is the SAME logic as PivotSignalController::getSignals()
                // and calcOiSignal() — we replicate it here exactly.
                $sentiment = $this->computeOiSentiment($symbol, $today, $slot, $prevTradingDate);

                $this->line("  [{$symbol}] Slot: {$slot} | OI Sentiment: {$sentiment['signal']} ({$sentiment['strength']}) | CE%: {$sentiment['ce_oi_pct']}% PE%: {$sentiment['pe_oi_pct']}%");
                $this->line("             Condition: {$sentiment['condition']} | Reason: {$sentiment['reason']}");

                // ── STEP B: Decide trade sides ────────────────────────────────
                //
                //  The OI sentiment tells us market direction.
                //  S1 = support level  → BUY the option that BENEFITS from the move
                //  R1 = resistance lvl → SELL the option that LOSES value at resistance
                //
                //  BULLISH sentiment (market going UP):
                //    S1 → BUY  CE  (calls gain as market rises from support)
                //    R1 → SELL PE  (puts lose value as market pushes through resistance)
                //
                //  BEARISH sentiment (market going DOWN):
                //    S1 → BUY  PE  (puts gain as market falls from resistance)
                //    R1 → SELL CE  (calls lose value as market drops through support)
                //
                //  N/A → no OI data → skip entirely
                //
                if ($sentiment['signal'] === 'N/A') {
                    $this->warn("  [{$symbol}] OI Sentiment is N/A — no data to decide side. Skipping slot.");
                    $totalSkipped++;
                    continue;
                }

                $isBullish = ($sentiment['signal'] === 'BULLISH');

                // S1 side: the option that profits from the directional move
                $s1Side = $isBullish ? 'CE' : 'PE';

                // R1 side: the OPPOSITE option — sold at resistance/support exhaustion
                $r1Side = $isBullish ? 'PE' : 'CE';

                $this->line("  [{$symbol}] Sentiment={$sentiment['signal']} → S1 BUY {$s1Side} | R1 SELL {$r1Side}");

                $freezeLimit = self::FREEZE_LIMITS[$symbol] ?? null;

                // ── STEP C: Fetch candles for both sides ──────────────────────
                //
                //  S1 BUY  → ATM strike of s1Side
                //  R1 SELL → ATM±1 strike of r1Side (highest volume = best liquidity)
                //
                //  We also fetch ATM of r1Side as fallback if no ATM±1 exists.
                //
                $s1AtmCandle  = $this->getAtmCandle($symbol, $s1Side, $expiry, $today, $slot);
                $r1SellCandle = $this->getBestNonAtmCandle($symbol, $r1Side, $expiry, $today, $slot);
                $r1AtmCandle  = $this->getAtmCandle($symbol, $r1Side, $expiry, $today, $slot);

                // Use ATM as fallback for R1 SELL if no ATM±1 found
                $r1Candle = $r1SellCandle ?? $r1AtmCandle;

                if (!$s1AtmCandle) {
                    $this->warn("  [{$symbol}] No ATM {$s1Side} candle for S1 at slot {$slot}. Skipping S1 orders.");
                }
                if (!$r1Candle) {
                    $this->warn("  [{$symbol}] No {$r1Side} candle for R1 at slot {$slot}. Skipping R1 orders.");
                }

                if (!$s1AtmCandle && !$r1Candle) {
                    $totalSkipped++;
                    continue;
                }

                if ($s1AtmCandle) {
                    [$PP_s1, $S1_s1, $R1_s1] = $this->calcPivots($s1AtmCandle);
                    $this->line("  [{$symbol}] S1 side: {$s1Side} ATM strike={$s1AtmCandle->strike} ({$s1AtmCandle->trading_symbol}) PP={$PP_s1} S1={$S1_s1} R1={$R1_s1}");
                }
                if ($r1Candle) {
                    [$PP_r1, $S1_r1, $R1_r1] = $this->calcPivots($r1Candle);
                    $r1StrikePos = $r1Candle->strike_position ?? 'ATM';
                    $this->line("  [{$symbol}] R1 side: {$r1Side} strike={$r1Candle->strike}({$r1StrikePos}) ({$r1Candle->trading_symbol}) PP={$PP_r1} S1={$S1_r1} R1={$R1_r1}");
                }

                // ── STEP D: S1 layers — BUY s1Side at S1 level ───────────────
                //
                //   BULLISH → use s1_ce_layers config  (user configured CE BUY layers)
                //   BEARISH → use s1_pe_layers config  (user configured PE BUY layers)
                //
                if ($s1AtmCandle) {
                    $s1Layers = $isBullish ? ($config->s1_ce_layers ?? []) : ($config->s1_pe_layers ?? []);

                    foreach ($s1Layers as $idx => $layer) {
                        $layerNum = $idx + 1;
                        if (($layer['quantity'] ?? 0) <= 0) continue;

                        // S1 layers are always BUY — override any stale config value
                        $txType = 'BUY';

                        [$PP2, $S1_2, $R1_2] = $this->calcPivots($s1AtmCandle);
                        $price  = $config->applyDiscount($S1_2, $layer);

                        $result = $this->processOrder(
                            $config, $broker, $helper, $s1AtmCandle,
                            $s1Side, 'S1', $S1_2, $price, $layer, $layerNum,
                            $today, $slot, $isDryRun, $txType, $freezeLimit
                        );
                        $result === 'placed' ? $totalPlaced++ : ($result === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }

                // ── STEP E: R1 layers — SELL r1Side at R1 level ──────────────
                //
                //   BULLISH → use r1_pe_layers config  (user configured PE SELL layers)
                //   BEARISH → use r1_ce_layers config  (user configured CE SELL layers)
                //
                if ($r1Candle) {
                    $r1Layers = $isBullish ? ($config->r1_pe_layers ?? []) : ($config->r1_ce_layers ?? []);

                    foreach ($r1Layers as $idx => $layer) {
                        $layerNum = $idx + 1;
                        if (($layer['quantity'] ?? 0) <= 0) continue;

                        // R1 layers are always SELL — override any stale config value
                        $txType = 'SELL';

                        [$PP2, $S1_2, $R1_2] = $this->calcPivots($r1Candle);
                        $price  = $config->applyDiscount($R1_2, $layer);

                        $result = $this->processOrder(
                            $config, $broker, $helper, $r1Candle,
                            $r1Side, 'R1', $R1_2, $price, $layer, $layerNum,
                            $today, $slot, $isDryRun, $txType, $freezeLimit
                        );
                        $result === 'placed' ? $totalPlaced++ : ($result === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }
            }
        }

        $this->info("\n=== Done | Placed: {$totalPlaced} | Skipped: {$totalSkipped} | Errors: {$totalErrors} ===");
        return 0;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // OI Sentiment Computation
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Compute the 1hr OI Sentiment for a given symbol + date + slot.
     *
     * Replicates PivotSignalController::getOiByInterval() +
     * getLastDayOi() + calcOiSignal() exactly.
     *
     * Returns array with keys: signal, condition, reason, strength, difference,
     *                          ce_oi_pct, pe_oi_pct
     */
    private function computeOiSentiment(
        string $symbol,
        string $date,
        string $slot,
        string $prevTradingDate
    ): array {
        // Build ordered list of ALL interval times for this date (up to + including current slot)
        $allIntervals = collect(self::INTERVALS)
            ->filter(fn($t) => $t <= $slot)
            ->values()
            ->toArray();

        if (empty($allIntervals)) {
            return $this->noSentiment();
        }

        // Load ALL per-interval OI totals for this symbol+date in ONE query each
        $ceOiByInterval = $this->getOiByInterval($symbol, 'CE', $date);
        $peOiByInterval = $this->getOiByInterval($symbol, 'PE', $date);

        if (empty($ceOiByInterval) && empty($peOiByInterval)) {
            return $this->noSentiment();
        }

        // Find the index of the current slot in the ordered list
        $slotIndex = array_search($slot, $allIntervals);

        if ($slotIndex === false) {
            return $this->noSentiment();
        }

        $curCeOi = $ceOiByInterval[$slot] ?? 0;
        $curPeOi = $peOiByInterval[$slot] ?? 0;

        // Previous interval OI — use previous day last candle for the first slot
        if ($slotIndex === 0) {
            $prevCeOi = $this->getLastDayOi($symbol, 'CE', $prevTradingDate);
            $prevPeOi = $this->getLastDayOi($symbol, 'PE', $prevTradingDate);
        } else {
            $prevSlot = $allIntervals[$slotIndex - 1];
            $prevCeOi = $ceOiByInterval[$prevSlot] ?? 0;
            $prevPeOi = $peOiByInterval[$prevSlot] ?? 0;
        }

        if ($prevCeOi == 0 && $prevPeOi == 0) {
            return $this->noSentiment();
        }

        $cePct = $prevCeOi > 0 ? round((($curCeOi - $prevCeOi) / $prevCeOi) * 100, 2) : 0;
        $pePct = $prevPeOi > 0 ? round((($curPeOi - $prevPeOi) / $prevPeOi) * 100, 2) : 0;

        $signal = $this->calcOiSignal($cePct, $pePct);

        return array_merge($signal, [
            'ce_oi'     => (int) $curCeOi,
            'pe_oi'     => (int) $curPeOi,
            'ce_oi_pct' => $cePct,
            'pe_oi_pct' => $pePct,
            'time'      => $slot,
        ]);
    }

    /**
     * Load ALL interval OI totals for a symbol+type on a date in ONE query.
     * Returns: [ "09:15" => 123456789, "10:15" => 234567890, ... ]
     *
     * Exact copy of PivotSignalController::getOiByInterval().
     */
    private function getOiByInterval(string $symbol, string $type, string $date): array
    {
        return ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->where('is_missing', 0)
            ->groupBy('interval_time')
            ->orderBy('interval_time')
            ->selectRaw('interval_time, SUM(oi) as total_oi')
            ->pluck('total_oi', 'interval_time')
            ->mapWithKeys(fn($oi, $time) => [
                Carbon::parse($time)->format('H:i') => (int) $oi
            ])
            ->toArray();
    }

    /**
     * Get the last interval's total OI from the previous trading day.
     * Used as baseline for the first candle of the current day.
     *
     * Exact copy of PivotSignalController::getLastDayOi().
     */
    private function getLastDayOi(string $symbol, string $type, string $prevDate): int
    {
        $lastInterval = ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $prevDate)
            ->where('is_missing', 0)
            ->orderByDesc('interval_time')
            ->value('interval_time');

        if (!$lastInterval) return 0;

        return (int) ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $prevDate)
            ->where('interval_time', $lastInterval)
            ->where('is_missing', 0)
            ->sum('oi');
    }

    /**
     * Core OI signal logic — exact copy of PivotSignalController::calcOiSignal().
     *
     * Case 1: CE% > 0 && PE% < 0  → BEARISH  (call buildup + put unwinding → resistance)
     * Case 2: CE% < 0 && PE% > 0  → BULLISH  (call unwinding + put buildup → support)
     * Case 3: CE% > 0 && PE% > 0  → PE% > CE% ? BULLISH : BEARISH  (dominant side wins)
     * Case 4: CE% < 0 && PE% < 0  → |CE%| > |PE%| ? BULLISH : BEARISH  (larger CE unwind = short covering)
     *
     * Strength = |CE% − PE%|
     *   > 3    → Very Strong
     *   > 1.5  → Strong
     *   > 0.5  → Moderate
     *   else   → Weak
     */
    private function calcOiSignal(float $cePct, float $pePct): array
    {
        // Case 1: Call buildup + Put unwinding → Bearish (resistance forming)
        if ($cePct > 0 && $pePct < 0) {
            $signal    = 'BEARISH';
            $condition = 'CE ↑ + PE ↓';
            $reason    = 'Call buildup + Put unwinding → Resistance forming';

        // Case 2: Call unwinding + Put buildup → Bullish (support forming)
        } elseif ($cePct < 0 && $pePct > 0) {
            $signal    = 'BULLISH';
            $condition = 'CE ↓ + PE ↑';
            $reason    = 'Call unwinding + Put buildup → Support forming';

        // Case 3: Both OI increasing → dominant side decides
        } elseif ($cePct > 0 && $pePct > 0) {
            if ($pePct > $cePct) {
                $signal    = 'BULLISH';
                $condition = 'Both ↑ (PE > CE)';
                $reason    = "Put buildup stronger (+{$pePct}% vs CE +{$cePct}%) → Bullish";
            } else {
                $signal    = 'BEARISH';
                $condition = 'Both ↑ (CE ≥ PE)';
                $reason    = "Call buildup stronger (+{$cePct}% vs PE +{$pePct}%) → Bearish";
            }

        // Case 4: Both OI decreasing → larger unwind side decides
        } else {
            $absCe = abs($cePct);
            $absPe = abs($pePct);
            if ($absCe > $absPe) {
                $signal    = 'BULLISH';
                $condition = 'Both ↓ (|CE| > |PE|)';
                $reason    = "Call unwinding larger ({$cePct}% vs PE {$pePct}%) → Short covering → Bullish";
            } else {
                $signal    = 'BEARISH';
                $condition = 'Both ↓ (|PE| ≥ |CE|)';
                $reason    = "Put unwinding larger ({$pePct}% vs CE {$cePct}%) → Long covering → Bearish";
            }
        }

        // Strength = |CE% − PE%|
        $difference = round(abs($cePct - $pePct), 2);

        if ($difference > 3) {
            $strength = 'Very Strong Signal';
        } elseif ($difference > 1.5) {
            $strength = 'Strong Signal';
        } elseif ($difference > 0.5) {
            $strength = 'Moderate Signal';
        } else {
            $strength = 'Weak Signal';
        }

        return [
            'signal'     => $signal,
            'condition'  => $condition,
            'reason'     => $reason,
            'strength'   => $strength,
            'difference' => $difference,
        ];
    }

    private function noSentiment(): array
    {
        return [
            'signal'     => 'N/A',
            'condition'  => 'N/A',
            'reason'     => 'No OI data',
            'strength'   => 'N/A',
            'difference' => 0,
            'ce_oi'      => 0,
            'pe_oi'      => 0,
            'ce_oi_pct'  => 0,
            'pe_oi_pct'  => 0,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Date helpers
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Exact copy of PivotSignalController::getPreviousTradingDate().
     */
    private function getPreviousTradingDate(string $date): string
    {
        $prev     = Carbon::parse($date)->subDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isMarketHoliday($prev->toDateString())) {
                return $prev->toDateString();
            }
            $prev->subDay();
            $attempts++;
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isMarketHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Broker helper factory
    // ═════════════════════════════════════════════════════════════════════════

    private function getBrokerHelper(BrokerApi $broker): ZerodhaPivotHelper|AngelPivotHelper|null
    {
        if (isset($this->brokerHelpers[$broker->id])) {
            return $this->brokerHelpers[$broker->id];
        }

        $helper = match($broker->client_type) {
            'Zerodha'  => ZerodhaPivotHelper::isValid($broker) ? new ZerodhaPivotHelper($broker) : null,
            'AngelOne' => AngelPivotHelper::isValid($broker)   ? new AngelPivotHelper($broker)   : null,
            default    => null,
        };

        if ($helper) {
            $this->brokerHelpers[$broker->id] = $helper;
        }

        return $helper;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Core order processing
    // ═════════════════════════════════════════════════════════════════════════

    private function processOrder(
        NewPivotOrderConfig                 $config,
        BrokerApi                           $broker,
        ZerodhaPivotHelper|AngelPivotHelper $helper,
        object                              $candle,
        string                              $optionType,   // 'CE' or 'PE' — sentiment-driven
        string                              $level,        // 'S1' or 'R1'
        float                               $rawLevel,
        float                               $orderPrice,
        array                               $layer,
        int                                 $layerNum,
        string                              $date,
        string                              $slot,
        bool                                $isDryRun,
        string                              $transactionType = 'BUY',
        ?int                                $freezeLimitLots = null
    ): string {

        $symbol     = $candle->base_symbol;
        $optSymbol  = $candle->trading_symbol;
        $optToken   = (int) $candle->instrument_token;
        $strike     = $candle->strike;
        $lots       = (int)($layer['quantity'] ?? 0);
        $candleTime = "{$date} {$slot}:00";

        [$lotSize, $tickSize] = $this->getLotAndTick($helper, $optSymbol, $optToken);
        $qty = $lots * $lotSize;

        $strikePosLabel = $candle->strike_position ?? 'ATM';
        $this->line("      [{$symbol}] {$optionType} {$level} L{$layerNum} | {$transactionType} | strike={$strike}({$strikePosLabel}) | lots={$lots} × lot={$lotSize} = qty={$qty} | price=₹{$orderPrice}");

        // ── Idempotency check ─────────────────────────────────────────────────
        $alreadyPlaced = NewPivotOrder::where('config_id',         $config->id)
            ->where('symbol',           $symbol)
            ->where('option_type',      $optionType)
            ->where('trigger_level',    $level)
            ->where('layer_index',      $layerNum)
            ->where('candle_time',      $candleTime)
            ->where('transaction_type', $transactionType)
            ->where('is_order_placed',  true)
            ->exists();

        if ($alreadyPlaced) {
            $this->line("      [{$symbol}] {$optionType} {$level} L{$layerNum} {$transactionType} → already placed. Skipping.");
            return 'skipped';
        }

        $label = "[{$symbol}] {$optionType} {$level} L{$layerNum} {$transactionType} strike={$strike} @ ₹{$orderPrice} (raw={$rawLevel} lots={$lots} qty={$qty}) via {$broker->client_type}";

        // ── Dry run ───────────────────────────────────────────────────────────
        if ($isDryRun) {
            $this->info("      DRY-RUN: {$label}");
            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                null, 'DRY_RUN', false
            ));
            return 'placed';
        }

        // ── Live order ────────────────────────────────────────────────────────
        try {
            $result = $helper->placeOrder(
                $optSymbol,
                $optToken,
                $transactionType,
                $config->order_type,
                $config->product,
                $lots,
                $orderPrice,
                $freezeLimitLots
            );

            $orderIdStr = implode(',', $result['order_ids']);

            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                $orderIdStr, 'OPEN', true, now()
            ));

            $this->info("      ✓ PLACED: {$label} | Order ID(s): {$orderIdStr}");
            Log::info("PlacePivotOrders: PLACED {$label} | config={$config->id} order_ids={$orderIdStr}");

            return 'placed';

        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
            $this->error("      ✗ ERROR: {$label} | {$errMsg}");
            Log::error("PlacePivotOrders: ERROR {$label} | {$errMsg}");

            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                null, 'ERROR', false, null, $errMsg
            ));

            return 'error';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Instrument info shim
    // ─────────────────────────────────────────────────────────────────────────

    private function getLotAndTick(
        ZerodhaPivotHelper|AngelPivotHelper $helper,
        string $tradingSymbol,
        int    $instrumentToken
    ): array {
        if ($helper instanceof AngelPivotHelper) {
            $angelSymbol = $helper->toAngelSymbol($tradingSymbol);
            $info        = $helper->getInstrumentInfo($angelSymbol, $instrumentToken);
            return [$info[1], $info[2]];
        }
        return $helper->getInstrumentInfo($tradingSymbol, $instrumentToken);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NewPivotOrder payload builder
    // ─────────────────────────────────────────────────────────────────────────

    private function buildOrderPayload(
        NewPivotOrderConfig $config,
        BrokerApi           $broker,
        object              $candle,
        string              $optionType,
        string              $level,
        int                 $layerNum,
        string              $transactionType,
        float               $rawLevel,
        float               $orderPrice,
        string              $candleTime,
        int                 $qty,
        ?string             $orderId      = null,
        string              $kiteStatus   = 'OPEN',
        bool                $isPlaced     = true,
        $placedAt           = null,
        ?string             $errorMessage = null
    ): array {
        return [
            'user_id'          => $config->user_id,
            'config_id'        => $config->id,
            'broker_api_id'    => $config->broker_api_id,
            'symbol'           => $candle->base_symbol,
            'option_symbol'    => $candle->trading_symbol,
            'option_token'     => $candle->instrument_token,
            'option_type'      => $optionType,
            'strike_price'     => $candle->strike,
            'trigger_level'    => $level,
            'layer_index'      => $layerNum,
            'transaction_type' => $transactionType,
            'raw_level_price'  => $rawLevel,
            'order_price'      => $orderPrice,
            'candle_time'      => $candleTime,
            'order_type'       => $config->order_type,
            'product'          => $config->product,
            'quantity'         => $qty,
            'kite_order_id'    => $orderId,
            'kite_status'      => $kiteStatus,
            'is_order_placed'  => $isPlaced,
            'order_placed_at'  => $placedAt ?? ($isPlaced ? now() : null),
            'error_message'    => $errorMessage,
            'status'           => $isPlaced,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pivot calculation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PP = (H+L+C)/3 of the current 1hr candle.
     * R1 = 2*PP − L
     * S1 = 2*PP − H
     *
     * Matches PivotSignalController::buildSignals() exactly.
     */
    private function calcPivots(object $candle): array
    {
        $H  = (float) $candle->high;
        $L  = (float) $candle->low;
        $C  = (float) $candle->close;
        $PP = round(($H + $L + $C) / 3, 2);
        $R1 = round((2 * $PP) - $L, 2);
        $S1 = round((2 * $PP) - $H, 2);
        return [$PP, $S1, $R1];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Last completed 1hr slot
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns the last completed 1hr slot (e.g. "09:15", "10:15", ...).
     *
     * Bars run 09:15, 10:15, ..., 15:15.
     * A bar at T is complete when now >= T + 60 min.
     *
     * Example: now = 10:30 → 09:15 bar completed at 10:15 ✓ → return "09:15"
     *          now = 11:20 → 10:15 bar completed at 11:15 ✓ → return "10:15"
     */
    private function getLastCompletedSlot(): ?string
    {
        $now        = Carbon::now();
        $nowMinutes = $now->hour * 60 + $now->minute;

        $lastCompleted = null;

        foreach (self::INTERVALS as $slot) {
            [$h, $m] = explode(':', $slot);
            $slotStart    = (int)$h * 60 + (int)$m;
            $slotComplete = $slotStart + 60; // bar is complete 60 min after open

            if ($nowMinutes >= $slotComplete) {
                $lastCompleted = $slot;
            }
        }

        return $lastCompleted;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Data helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getNearestExpiry(string $symbol, string $date): ?string
    {
        $expiry = ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if ($expiry) return $expiry;

        return ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    /**
     * Fetch the ATM candle for BUY order placement.
     * strike_position = 'ATM' — exactly what the frontend displays.
     */
    private function getAtmCandle(string $symbol, string $type, string $expiry, string $date, string $slot): ?object
    {
        return ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->first();
    }

    /**
     * Fetch the best ATM±1 candle for SELL order placement.
     * Picks ATM-1 or ATM+1 — whichever has the higher volume in this slot.
     * Higher volume = better liquidity = tighter spread when selling.
     */
    private function getBestNonAtmCandle(string $symbol, string $type, string $expiry, string $date, string $slot): ?object
    {
        $candidates = ThirtyMinOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereIn('strike_position', ['ATM-1', 'ATM+1'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('interval_time', 'like', "%{$slot}%")
            ->where('is_missing', 0)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortByDesc('volume')->first();
    }
}