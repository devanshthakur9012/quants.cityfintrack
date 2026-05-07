<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OptionOhlcData;
use App\Models\NewPivotOrderConfig;
use App\Models\NewPivotOrder;
use App\Models\BrokerApi;
use App\Helpers\ZerodhaPivotHelper;
use App\Helpers\AngelPivotHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * PlacePivotOrders15
 *
 * 15-min sibling of PlacePivotOrders (1hr).
 * Uses OptionOhlcData (option_ohlc_data) instead of ThirtyMinOhlcData.
 * Supports BOTH Zerodha and Angel One brokers.
 *
 * Cron: 5,20,35,50 * * * *  (weekdays 09:30–15:30)
 * Fires ~5 min after each 15-min bar close so data collector has time to populate.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * KEY DIFFERENCES vs pivot:place-orders (1hr)
 * ═══════════════════════════════════════════════════════════════════════
 *
 *  1. Data source  → OptionOhlcData  (option_ohlc_data table)
 *     1hr uses     → ThirtyMinOhlcData (30min_ohlc_data table)
 *
 *  2. Slot schedule→ every 15 min: 09:15, 09:30, 09:45, 10:00 … 15:15
 *     1hr uses     → every 60 min: 09:15, 10:15, 11:15 … 15:15
 *
 *  3. Config filter→ only loads configs where interval_type = '15min'
 *     1hr uses     → only loads configs where interval_type = '1hr'
 *
 *  4. OI sentiment → same expiry filter + ATM±3 strikes (no cross-expiry noise)
 *     1hr uses     → all strikes, any expiry in 30min_ohlc_data
 *
 *  5. No is_missing column on option_ohlc_data — that filter is absent.
 *
 *  6. First candle OI baseline: uses prev day same-expiry last-interval OI.
 *     If expiry wasn't traded prev day (new expiry rollover) → returns N/A.
 *     This prevents fake 100% OI jump at Monday open or after rollover.
 *
 * ═══════════════════════════════════════════════════════════════════════
 * OI SENTIMENT → ORDER SIDE (identical to 1hr)
 * ═══════════════════════════════════════════════════════════════════════
 *
 *   BULLISH → S1 BUY CE  | R1 SELL PE
 *   BEARISH → S1 BUY PE  | R1 SELL CE
 *   N/A     → skip symbol for this slot
 *
 * ═══════════════════════════════════════════════════════════════════════
 */
class PlacePivotOrders15 extends Command
{
    protected $signature = 'pivot15:place-orders
                            {--date=   : Override date (Y-m-d), default = today}
                            {--slot=   : Override candle slot (H:i), e.g. 09:30}
                            {--dry-run : Compute pivots but do NOT send to broker}
                            {--config= : Run only a specific config ID}';

    protected $description = 'Place 15-min pivot orders (OptionOhlcData) — BULLISH→CE, BEARISH→PE';

    // ── 15-min slots: 09:15 → 15:15 every 15 min ─────────────────────────────
    private const INTERVALS = [
        '09:15', '09:30', '09:45',
        '10:00', '10:15', '10:30', '10:45',
        '11:00', '11:15', '11:30', '11:45',
        '12:00', '12:15', '12:30', '12:45',
        '13:00', '13:15', '13:30', '13:45',
        '14:00', '14:15', '14:30', '14:45',
        '15:00', '15:15',
    ];

    // ── Freeze limits in LOTS (same as 1hr command) ───────────────────────────
    private const FREEZE_LIMITS = [
        'NIFTY'       => 18,  'BANKNIFTY'  => 20,  'FINNIFTY'   => 24,  'MIDCPNIFTY' => 24,
        'SENSEX'      => 20,  'BANKEX'     => 20,
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

    /** @var array<int, ZerodhaPivotHelper|AngelPivotHelper> */
    private array $brokerHelpers = [];

    // ─────────────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $today    = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        // ── 1. Determine last completed 15-min candle slot ────────────────────
        $slot = $this->option('slot') ?: $this->getLastCompletedSlot();

        if (!$slot) {
            $this->warn('No completed 15-min slot yet for today. Exiting.');
            return 0;
        }

        $this->info("=== PlacePivotOrders15 | Date: {$today} | Slot: {$slot}" . ($isDryRun ? ' | DRY-RUN' : '') . ' ===');
        $this->info('   Rule: BULLISH→CE orders | BEARISH→PE orders | N/A→skip');

        // ── 2. Load active 15min configs that have ≥1 symbol selected ─────────
        $configQuery = NewPivotOrderConfig::where('status', true)
            ->where('interval_type', '15min')
            ->with('broker');

        if ($this->option('config')) {
            $configQuery->where('id', $this->option('config'));
        }

        $configs = $configQuery->get()->filter(fn($c) => $c->hasSymbols());

        if ($configs->isEmpty()) {
            $this->warn('No active 15min configs with symbols found. Exiting.');
            return 0;
        }

        $this->info("Found {$configs->count()} active 15min config(s) with symbols.");

        // ── 3. Discover symbols that have data for today+slot in option_ohlc_data
        $availableSymbols = OptionOhlcData::whereDate('trade_date', $today)
            ->where('interval_time', 'like', "% {$slot}:%")
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('base_symbol')
            ->whereNotNull('expiry_date')
            ->distinct()
            ->pluck('base_symbol')
            ->toArray();

        if (empty($availableSymbols)) {
            $this->warn("No 15min data found for date={$today} slot={$slot}. Has the collector run?");
            return 0;
        }

        $this->info('Symbols with data in DB: ' . implode(', ', $availableSymbols));

        // ── 4. Previous trading day (for first-candle OI baseline) ────────────
        $prevTradingDate = $this->getPreviousTradingDate($today);

        // ── 5. Process each config ────────────────────────────────────────────
        $totalPlaced  = 0;
        $totalSkipped = 0;
        $totalErrors  = 0;

        foreach ($configs as $config) {

            $configSymbols    = array_map('strtoupper', $config->symbols ?? []);
            $effectiveSymbols = array_values(array_intersect($configSymbols, $availableSymbols));

            if (empty($effectiveSymbols)) {
                $this->warn("  Config #{$config->id}: No symbol overlap with slot={$slot} data. Skipping.");
                continue;
            }

            $this->line("\n--- Config #{$config->id} [15MIN] | Symbols: [" . implode(', ', $effectiveSymbols) . '] ---');

            // Resolve broker
            $brokerRecord = BrokerApi::find($config->broker_api_id);
            if (!$brokerRecord) {
                $this->error("  Config #{$config->id}: Broker record not found. Skipping.");
                continue;
            }

            $broker = match ($brokerRecord->client_type) {
                'Zerodha'  => BrokerApi::where('id', $config->broker_api_id)->validToken()->first(),
                'AngelOne' => $brokerRecord,
                default    => null,
            };

            if (!$broker) {
                $this->error("  Config #{$config->id}: Broker invalid/expired ({$brokerRecord->client_type}). Skipping.");
                continue;
            }

            $helper = $this->getBrokerHelper($broker);
            if (!$helper) {
                $this->error("  Config #{$config->id}: Unsupported broker '{$broker->client_type}'. Skipping.");
                continue;
            }

            $this->line("  Broker: {$broker->client_name} | {$broker->client_type} | {$config->order_type}/{$config->product}");

            foreach ($effectiveSymbols as $symbol) {

                // Resolve nearest expiry from option_ohlc_data
                $expiry = $this->getNearestExpiry($symbol, $today);
                if (!$expiry) {
                    $this->warn("  [{$symbol}] No expiry found in option_ohlc_data for today. Skipping.");
                    continue;
                }

                // ── STEP A: Compute 15-min OI Sentiment ──────────────────────
                $sentiment = $this->computeOiSentiment($symbol, $expiry, $today, $slot, $prevTradingDate);

                $this->line("  [{$symbol}] Slot={$slot} Expiry={$expiry} | Sentiment={$sentiment['signal']} ({$sentiment['strength']}) | CE%={$sentiment['ce_oi_pct']}% PE%={$sentiment['pe_oi_pct']}%");
                $this->line("             {$sentiment['condition']} | {$sentiment['reason']}");

                // ── STEP B: Decide sides ──────────────────────────────────────
                if ($sentiment['signal'] === 'N/A') {
                    $this->warn("  [{$symbol}] OI Sentiment N/A — skipping slot.");
                    $totalSkipped++;
                    continue;
                }

                $isBullish = ($sentiment['signal'] === 'BULLISH');
                $s1Side    = $isBullish ? 'CE' : 'PE';
                $r1Side    = $isBullish ? 'PE' : 'CE';

                $this->line("  [{$symbol}] {$sentiment['signal']} → S1 BUY {$s1Side} | R1 SELL {$r1Side}");

                $freezeLimit = self::FREEZE_LIMITS[$symbol] ?? null;

                // ── STEP C: Fetch candles ─────────────────────────────────────
                $s1AtmCandle  = $this->getAtmCandle($symbol, $s1Side, $expiry, $today, $slot);
                $r1SellCandle = $this->getBestNonAtmCandle($symbol, $r1Side, $expiry, $today, $slot);
                $r1Candle     = $r1SellCandle ?? $this->getAtmCandle($symbol, $r1Side, $expiry, $today, $slot);

                if (!$s1AtmCandle) {
                    $this->warn("  [{$symbol}] No ATM {$s1Side} candle at slot={$slot}. Skipping S1.");
                }
                if (!$r1Candle) {
                    $this->warn("  [{$symbol}] No {$r1Side} candle at slot={$slot}. Skipping R1.");
                }
                if (!$s1AtmCandle && !$r1Candle) {
                    $totalSkipped++;
                    continue;
                }

                if ($s1AtmCandle) {
                    [, $S1_val,] = $this->calcPivots($s1AtmCandle);
                    $this->line("  [{$symbol}] S1 {$s1Side} ATM strike={$s1AtmCandle->strike} ({$s1AtmCandle->trading_symbol}) S1={$S1_val}");
                }
                if ($r1Candle) {
                    [,, $R1_val] = $this->calcPivots($r1Candle);
                    $this->line("  [{$symbol}] R1 {$r1Side} strike={$r1Candle->strike}({$r1Candle->strike_position}) ({$r1Candle->trading_symbol}) R1={$R1_val}");
                }

                // ── STEP D: S1 BUY layers ─────────────────────────────────────
                if ($s1AtmCandle) {
                    $s1Layers = $isBullish ? ($config->s1_ce_layers ?? []) : ($config->s1_pe_layers ?? []);
                    foreach ($s1Layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        [, $S1_price,] = $this->calcPivots($s1AtmCandle);
                        $price  = $config->applyDiscount($S1_price, $layer);
                        $result = $this->processOrder(
                            $config, $broker, $helper, $s1AtmCandle,
                            $s1Side, 'S1', $S1_price, $price,
                            $layer, $idx + 1, $today, $slot,
                            $isDryRun, 'BUY', $freezeLimit
                        );
                        $result === 'placed' ? $totalPlaced++ : ($result === 'skipped' ? $totalSkipped++ : $totalErrors++);
                    }
                }

                // ── STEP E: R1 SELL layers ─────────────────────────────────────
                if ($r1Candle) {
                    $r1Layers = $isBullish ? ($config->r1_pe_layers ?? []) : ($config->r1_ce_layers ?? []);
                    foreach ($r1Layers as $idx => $layer) {
                        if (($layer['quantity'] ?? 0) <= 0) continue;
                        [,, $R1_price] = $this->calcPivots($r1Candle);
                        $price  = $config->applyDiscount($R1_price, $layer);
                        $result = $this->processOrder(
                            $config, $broker, $helper, $r1Candle,
                            $r1Side, 'R1', $R1_price, $price,
                            $layer, $idx + 1, $today, $slot,
                            $isDryRun, 'SELL', $freezeLimit
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
    // OI Sentiment — mirrors PivotSignal15Controller logic exactly
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Compute 15-min OI Sentiment for symbol + expiry + date + slot.
     *
     * Key differences from 1hr computeOiSentiment:
     *  - Filters to same expiry_date (no cross-expiry contamination)
     *  - Uses ATM±3 strikes only (far OTM distorts signal)
     *  - First-candle baseline: prev day SAME-EXPIRY last interval
     *    (if expiry didn't exist prev day → returns N/A instead of fake 100% jump)
     */
    private function computeOiSentiment(
        string $symbol,
        string $expiry,
        string $date,
        string $slot,
        string $prevTradingDate
    ): array {
        $allIntervals = collect(self::INTERVALS)
            ->filter(fn($t) => $t <= $slot)
            ->values()
            ->toArray();

        if (empty($allIntervals)) return $this->noSentiment();

        // ── Bulk load all CE/PE rows for today, same expiry ───────────────────
        $allRows = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->get(['interval_time', 'instrument_type', 'strike', 'oi', 'atm_strike']);

        if ($allRows->isEmpty()) return $this->noSentiment();

        // ── Resolve ATM strike and ATM±3 range ───────────────────────────────
        $atmStrike  = (float) ($allRows->first()->atm_strike ?? 0);
        $atmStrikes = $this->getAtmPlusMinusStrikes($allRows, $atmStrike, 3);

        // ── Aggregate OI per 15-min interval (ATM±3 only) ────────────────────
        $ceRows = $allRows->where('instrument_type', 'CE');
        $peRows = $allRows->where('instrument_type', 'PE');

        $ceOiByInterval = $this->aggregateOiByInterval($ceRows, $atmStrikes);
        $peOiByInterval = $this->aggregateOiByInterval($peRows, $atmStrikes);

        if (empty($ceOiByInterval) && empty($peOiByInterval)) return $this->noSentiment();

        $slotIndex = array_search($slot, $allIntervals);
        if ($slotIndex === false) return $this->noSentiment();

        $curCeOi = $ceOiByInterval[$slot] ?? 0;
        $curPeOi = $peOiByInterval[$slot] ?? 0;

        // ── Previous interval baseline ────────────────────────────────────────
        if ($slotIndex === 0) {
            // First candle: use prev day SAME-EXPIRY last interval OI
            // If expiry not on prev day (new rollover) → N/A to avoid fake jump
            [$prevCeOi, $prevPeOi] = $this->getPrevDayLastOiBySameExpiry(
                $symbol, $expiry, $prevTradingDate, $atmStrikes
            );
            if ($prevCeOi === 0 && $prevPeOi === 0) return $this->noSentiment();
        } else {
            $prevSlot = $allIntervals[$slotIndex - 1];
            $prevCeOi = $ceOiByInterval[$prevSlot] ?? 0;
            $prevPeOi = $peOiByInterval[$prevSlot] ?? 0;
        }

        if ($prevCeOi == 0 && $prevPeOi == 0) return $this->noSentiment();

        $cePct = $prevCeOi > 0 ? round((($curCeOi - $prevCeOi) / $prevCeOi) * 100, 2) : 0;
        $pePct = $prevPeOi > 0 ? round((($curPeOi - $prevPeOi) / $prevPeOi) * 100, 2) : 0;

        return array_merge($this->calcOiSignal($cePct, $pePct), [
            'ce_oi'     => (int) $curCeOi,
            'pe_oi'     => (int) $curPeOi,
            'ce_oi_pct' => $cePct,
            'pe_oi_pct' => $pePct,
            'time'      => $slot,
        ]);
    }

    /**
     * Get strikes within ±$n steps of ATM from the already-loaded row collection.
     */
    private function getAtmPlusMinusStrikes($rows, float $atmStrike, int $n = 3): array
    {
        if (!$atmStrike) return [];

        $allStrikes = $rows->pluck('strike')
            ->map(fn($s) => (float) $s)
            ->filter(fn($s) => $s > 0)
            ->unique()->sort()->values()->toArray();

        if (empty($allStrikes)) return [];

        $diffs  = array_map(fn($s) => abs($s - $atmStrike), $allStrikes);
        $atmIdx = (int) array_search(min($diffs), $diffs);
        $from   = max(0, $atmIdx - $n);
        $to     = min(count($allStrikes) - 1, $atmIdx + $n);

        return array_slice($allStrikes, $from, $to - $from + 1);
    }

    /**
     * Aggregate OI by 15-min slot from a collection of rows.
     * Applies ATM±3 strike filter.
     * Returns: [ "09:15" => 123456, "09:30" => 234567, ... ]
     */
    private function aggregateOiByInterval($rows, array $atmStrikes): array
    {
        $result = [];
        foreach ($rows as $r) {
            $strike = (float) $r->strike;
            if (!empty($atmStrikes) && !in_array($strike, $atmStrikes, true)) continue;
            $timeKey          = Carbon::parse($r->interval_time)->format('H:i');
            $result[$timeKey] = ($result[$timeKey] ?? 0) + (int) $r->oi;
        }
        ksort($result);
        return $result;
    }

    /**
     * Fetch prev day last-interval OI for SAME expiry (CE + PE separately).
     * Returns [0, 0] if expiry had no data on prev day → guards against new-expiry rollover.
     */
    private function getPrevDayLastOiBySameExpiry(
        string $symbol,
        string $expiry,
        string $prevDate,
        array  $atmStrikes
    ): array {
        $hasPrevData = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $prevDate)
            ->exists();

        if (!$hasPrevData) return [0, 0];

        $lastInterval = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $prevDate)
            ->orderByDesc('interval_time')
            ->value('interval_time');

        if (!$lastInterval) return [0, 0];

        $rows = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $prevDate)
            ->where('interval_time', $lastInterval)
            ->when(!empty($atmStrikes), fn($q) => $q->whereIn('strike', $atmStrikes))
            ->get(['instrument_type', 'oi']);

        return [
            (int) $rows->where('instrument_type', 'CE')->sum('oi'),
            (int) $rows->where('instrument_type', 'PE')->sum('oi'),
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // OI Signal calculator — exact copy from PlacePivotOrders (1hr)
    // ═════════════════════════════════════════════════════════════════════════

    private function calcOiSignal(float $cePct, float $pePct): array
    {
        if ($cePct > 0 && $pePct < 0) {
            $signal = 'BEARISH'; $condition = 'CE ↑ + PE ↓';
            $reason = 'Call buildup + Put unwinding → Resistance forming';
        } elseif ($cePct < 0 && $pePct > 0) {
            $signal = 'BULLISH'; $condition = 'CE ↓ + PE ↑';
            $reason = 'Call unwinding + Put buildup → Support forming';
        } elseif ($cePct > 0 && $pePct > 0) {
            if ($pePct > $cePct) {
                $signal = 'BULLISH'; $condition = 'Both ↑ (PE > CE)';
                $reason = "Put buildup stronger (+{$pePct}% vs CE +{$cePct}%) → Bullish";
            } else {
                $signal = 'BEARISH'; $condition = 'Both ↑ (CE ≥ PE)';
                $reason = "Call buildup stronger (+{$cePct}% vs PE +{$pePct}%) → Bearish";
            }
        } else {
            if (abs($cePct) > abs($pePct)) {
                $signal = 'BULLISH'; $condition = 'Both ↓ (|CE| > |PE|)';
                $reason = "Call unwinding larger ({$cePct}% vs PE {$pePct}%) → Short covering → Bullish";
            } else {
                $signal = 'BEARISH'; $condition = 'Both ↓ (|PE| ≥ |CE|)';
                $reason = "Put unwinding larger ({$pePct}% vs CE {$cePct}%) → Long covering → Bearish";
            }
        }

        $difference = round(abs($cePct - $pePct), 2);
        $strength   = $difference > 3    ? 'Very Strong Signal'
                    : ($difference > 1.5 ? 'Strong Signal'
                    : ($difference > 0.5 ? 'Moderate Signal' : 'Weak Signal'));

        return compact('signal', 'condition', 'reason', 'strength', 'difference');
    }

    private function noSentiment(): array
    {
        return [
            'signal' => 'N/A', 'condition' => 'N/A', 'reason' => 'No OI data',
            'strength' => 'N/A', 'difference' => 0,
            'ce_oi' => 0, 'pe_oi' => 0, 'ce_oi_pct' => 0, 'pe_oi_pct' => 0,
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Order processing — identical pattern to 1hr command
    // ═════════════════════════════════════════════════════════════════════════

    private function processOrder(
        NewPivotOrderConfig                 $config,
        BrokerApi                           $broker,
        ZerodhaPivotHelper|AngelPivotHelper $helper,
        object                              $candle,
        string                              $optionType,
        string                              $level,
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
        $lots       = (int) ($layer['quantity'] ?? 0);
        $candleTime = "{$date} {$slot}:00";

        [$lotSize,] = $this->getLotAndTick($helper, $optSymbol, $optToken);
        $qty = $lots * $lotSize;

        $this->line("      [{$symbol}] {$optionType} {$level} L{$layerNum} | {$transactionType} | strike={$candle->strike}({$candle->strike_position}) | lots={$lots}×{$lotSize}={$qty} | ₹{$orderPrice}");

        // Idempotency: skip if already placed for this config+symbol+slot+layer
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
            $this->line("      [{$symbol}] {$optionType} {$level} L{$layerNum} → already placed. Skipping.");
            return 'skipped';
        }

        $label = "[{$symbol}] {$optionType} {$level} L{$layerNum} {$transactionType} strike={$candle->strike} @ ₹{$orderPrice} (raw={$rawLevel} lots={$lots} qty={$qty})";

        if ($isDryRun) {
            $this->info("      DRY-RUN: {$label}");
            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                null, 'DRY_RUN', false
            ));
            return 'placed';
        }

        try {
            $result     = $helper->placeOrder(
                $optSymbol, $optToken, $transactionType,
                $config->order_type, $config->product,
                $lots, $orderPrice, $freezeLimitLots
            );
            $orderIdStr = implode(',', $result['order_ids']);

            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                $orderIdStr, 'OPEN', true, now()
            ));

            $this->info("      ✓ PLACED: {$label} | Order ID(s): {$orderIdStr}");
            Log::info("PlacePivotOrders15: PLACED {$label} | config={$config->id} order_ids={$orderIdStr}");
            return 'placed';

        } catch (\Exception $e) {
            $errMsg = $e->getMessage();
            $this->error("      ✗ ERROR: {$label} | {$errMsg}");
            Log::error("PlacePivotOrders15: ERROR {$label} | {$errMsg}");

            NewPivotOrder::create($this->buildOrderPayload(
                $config, $broker, $candle, $optionType, $level, $layerNum,
                $transactionType, $rawLevel, $orderPrice, $candleTime, $qty,
                null, 'ERROR', false, null, $errMsg
            ));
            return 'error';
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═════════════════════════════════════════════════════════════════════════

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
                            $placedAt     = null,
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

    /**
     * PP = (H+L+C)/3   S1 = 2*PP−H   R1 = 2*PP−L
     */
    private function calcPivots(object $candle): array
    {
        $H  = (float) $candle->high;
        $L  = (float) $candle->low;
        $C  = (float) $candle->close;
        $PP = round(($H + $L + $C) / 3, 2);
        return [$PP, round((2 * $PP) - $H, 2), round((2 * $PP) - $L, 2)];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Slot helper — last completed 15-min bar (bar complete at open+15)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cron fires at :05/:20/:35/:50.
     * A 15-min bar opening at T is complete at T+15.
     * We want the bar that is fully closed AND whose data the collector
     * had time to write (~5 min grace window).
     *
     *  Cron fires 09:20 → last complete bar = 09:00 (complete 09:15) ✓
     *  Cron fires 09:35 → last complete bar = 09:15 (complete 09:30) ✓
     *  Cron fires 09:50 → last complete bar = 09:30 (complete 09:45) ✓
     *  Cron fires 10:05 → last complete bar = 09:45 (complete 10:00) ✓
     */
    private function getLastCompletedSlot(): ?string
    {
        $now        = Carbon::now();
        $nowMinutes = $now->hour * 60 + $now->minute;
        $lastSlot   = null;

        foreach (self::INTERVALS as $slot) {
            [$h, $m]      = explode(':', $slot);
            $slotComplete = (int)$h * 60 + (int)$m + 15; // bar done 15 min after open
            if ($nowMinutes >= $slotComplete) {
                $lastSlot = $slot;
            }
        }

        return $lastSlot;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Expiry + candle helpers — OptionOhlcData (no is_missing column)
    // ─────────────────────────────────────────────────────────────────────────

    private function getNearestExpiry(string $symbol, string $date): ?string
    {
        $row = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->first(['expiry_date']);

        if ($row) return Carbon::parse($row->expiry_date)->toDateString();

        // Fallback: latest expiry present in today's data (edge case)
        $row2 = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->orderByDesc('expiry_date')
            ->first(['expiry_date']);

        return $row2 ? Carbon::parse($row2->expiry_date)->toDateString() : null;
    }

    /** ATM candle for BUY orders — strike_position = 'ATM' */
    private function getAtmCandle(
        string $symbol, string $type, string $expiry, string $date, string $slot
    ): ?object {
        return OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('interval_time', 'like', "% {$slot}:%")
            ->first();
    }

    /** ATM±1 candle for SELL orders — highest volume = best liquidity */
    private function getBestNonAtmCandle(
        string $symbol, string $type, string $expiry, string $date, string $slot
    ): ?object {
        return OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereIn('strike_position', ['ATM-1', 'ATM+1'])
            ->whereDate('expiry_date', $expiry)
            ->whereDate('trade_date', $date)
            ->where('interval_time', 'like', "% {$slot}:%")
            ->orderByDesc('volume')
            ->first();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Broker helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getBrokerHelper(BrokerApi $broker): ZerodhaPivotHelper|AngelPivotHelper|null
    {
        if (isset($this->brokerHelpers[$broker->id])) {
            return $this->brokerHelpers[$broker->id];
        }
        $helper = match ($broker->client_type) {
            'Zerodha'  => ZerodhaPivotHelper::isValid($broker) ? new ZerodhaPivotHelper($broker) : null,
            'AngelOne' => AngelPivotHelper::isValid($broker)   ? new AngelPivotHelper($broker)   : null,
            default    => null,
        };
        if ($helper) $this->brokerHelpers[$broker->id] = $helper;
        return $helper;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Date helpers — exact copy from PlacePivotOrders (1hr)
    // ─────────────────────────────────────────────────────────────────────────

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isMarketHoliday($prev->toDateString())) {
                return $prev->toDateString();
            }
            $prev->subDay();
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
}