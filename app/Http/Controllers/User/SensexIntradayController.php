<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * SensexIntradayController — v4 (Pure 30-Stock, Put-Call Parity Price)
 *
 * ALL metrics are derived exclusively from the 30 SENSEX constituent stocks.
 * No direct SENSEX index or futures queries are used anywhere.
 *
 * ── HOW DATA IS STORED (from LiveOptionOhlcCollector) ────────────────────
 *
 *   atm_strike      = frozen ATM value for that day (e.g. 22500), SAME on every
 *                     row for a symbol+date. Set once from FUT 09:15 close.
 *
 *   strike          = the actual strike price of THIS row (K in Put-Call Parity)
 *
 *   strike_position = 'ATM'   → strike === atm_strike  (our ATM row)
 *                     'ATM+1' → one step above ATM
 *                     'ATM-1' → one step below ATM
 *                     'N/A'   → ATM±2 through ATM±5 (outside named range)
 *
 *   close           = option premium for this strike row at this candle
 *
 * ── PUT-CALL PARITY FORMULA ───────────────────────────────────────────────
 *
 *   For the ATM row (strike_position = 'ATM'):
 *     K = strike  (the actual ATM strike value, e.g. 22500)
 *     C = ATM CE close  (premium)
 *     P = ATM PE close  (premium)
 *     S* = K + C − P   → implied spot price
 *
 *   This is the correct approach. atm_strike is NOT used here — we use
 *   the strike column from the ATM row itself, which equals atm_strike
 *   but is fetched directly from the row data.
 *
 *   Weighted Synthetic Index = Σ (stock_S* × stock_weight / 100)
 *   normalised by total weight of stocks with valid ATM data that candle.
 *
 * ── VWAP ─────────────────────────────────────────────────────────────────
 *   Running VWAP = Σ(S* × weighted_ATM_vol) / Σ(weighted_ATM_vol)
 *   Vol per stock = (ATM CE vol + ATM PE vol) × weight/100
 *   Accumulated from 09:15 → current candle (NOT reset each candle).
 *
 * ── OI TYPE ──────────────────────────────────────────────────────────────
 *   synthetic_move_pct (direction) × oi_chg_pct (aggregate OI Δ from open)
 *   LONG_BUILD  = price ↑ + OI ↑  → fresh longs
 *   SHORT_BUILD = price ↓ + OI ↑  → fresh shorts
 *   SHORT_COVER = price ↑ + OI ↓  → shorts exiting
 *   LONG_UNWIND = price ↓ + OI ↓  → longs exiting
 */
class SensexIntradayController extends Controller
{
    private const SENSEX30_STOCKS = [
        ['symbol' => 'HDFCBANK',   'sector' => 'Financial Services',     'weight' => 14.2],
        ['symbol' => 'ICICIBANK',  'sector' => 'Financial Services',     'weight' =>  8.1],
        ['symbol' => 'KOTAKBANK',  'sector' => 'Financial Services',     'weight' =>  4.3],
        ['symbol' => 'AXISBANK',   'sector' => 'Financial Services',     'weight' =>  4.1],
        ['symbol' => 'BAJFINANCE', 'sector' => 'Financial Services',     'weight' =>  3.5],
        ['symbol' => 'SBIN',       'sector' => 'Financial Services',     'weight' =>  3.1],
        ['symbol' => 'INFY',       'sector' => 'Information Technology', 'weight' =>  8.0],
        ['symbol' => 'TCS',        'sector' => 'Information Technology', 'weight' =>  4.6],
        ['symbol' => 'HCLTECH',    'sector' => 'Information Technology', 'weight' =>  2.4],
        ['symbol' => 'RELIANCE',   'sector' => 'Oil Gas & Energy',       'weight' =>  9.2],
        ['symbol' => 'NTPC',       'sector' => 'Oil Gas & Energy',       'weight' =>  1.5],
        ['symbol' => 'POWERGRID',  'sector' => 'Oil Gas & Energy',       'weight' =>  1.3],
        ['symbol' => 'HINDUNILVR', 'sector' => 'FMCG',                  'weight' =>  3.9],
        ['symbol' => 'ITC',        'sector' => 'FMCG',                  'weight' =>  2.8],
        ['symbol' => 'NESTLEIND',  'sector' => 'FMCG',                  'weight' =>  1.3],
        ['symbol' => 'MARUTI',     'sector' => 'Automobile',             'weight' =>  3.1],
        ['symbol' => 'M&M',        'sector' => 'Automobile',             'weight' =>  2.4],
        ['symbol' => 'BAJAJ-AUTO', 'sector' => 'Automobile',             'weight' =>  1.5],
        ['symbol' => 'TATASTEEL',  'sector' => 'Metals & Mining',        'weight' =>  1.8],
        ['symbol' => 'JSWSTEEL',   'sector' => 'Metals & Mining',        'weight' =>  1.7],
        ['symbol' => 'HINDALCO',   'sector' => 'Metals & Mining',        'weight' =>  1.5],
        ['symbol' => 'SUNPHARMA',  'sector' => 'Healthcare',             'weight' =>  2.6],
        ['symbol' => 'DRREDDY',    'sector' => 'Healthcare',             'weight' =>  1.4],
        ['symbol' => 'CIPLA',      'sector' => 'Healthcare',             'weight' =>  1.0],
        ['symbol' => 'LT',         'sector' => 'Capital Goods',          'weight' =>  3.8],
        ['symbol' => 'ULTRACEMCO', 'sector' => 'Capital Goods',          'weight' =>  1.2],
        ['symbol' => 'TITAN',      'sector' => 'Consumer Durables',      'weight' =>  1.8],
        ['symbol' => 'ASIANPAINT', 'sector' => 'Consumer Durables',      'weight' =>  1.2],
        ['symbol' => 'BHARTIARTL', 'sector' => 'Telecommunication',      'weight' =>  2.3],
        ['symbol' => 'ETERNAL',    'sector' => 'Consumer Services',      'weight' =>  1.2],
    ];

    private const INTERVALS = [
        '09:15', '09:30', '09:45', '10:00', '10:15', '10:30',
        '10:45', '11:00', '11:15', '11:30', '11:45', '12:00',
        '12:15', '12:30', '12:45', '13:00', '13:15', '13:30',
        '13:45', '14:00', '14:15', '14:30', '14:45',
        '15:00', '15:15',  // ← full trading day — do not remove
    ];

    // ══════════════════════════════════════════════════════════════════════════
    public function index()
    {
        $pageTitle = 'SENSEX Intraday — 15-Min OI Shift Tracker';
        return view($this->activeTemplate . 'user.sensex-intraday.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GET /sensex-intraday/data?date=Y-m-d
    // ══════════════════════════════════════════════════════════════════════════
    public function data(Request $request)
    {
        try {
            $date     = $request->get('date', Carbon::today()->toDateString());
            $prevDate = $this->getPreviousTradingDate($date);
            $isExpiry = $this->isExpiryDay($date);

            $stockSymbols = array_column(self::SENSEX30_STOCKS, 'symbol');

            // ── 1. Resolve nearest expiry per stock ──────────────────────────
            $expiryMap = [];
            foreach ($stockSymbols as $sym) {
                $expiryMap[$sym] = $this->resolveNearestExpiry($sym, $date);
            }

            // ── 2. Single query — all 30 stocks, all strikes, all intervals ──
            //
            //    Columns we need:
            //      oi, volume          → OI aggregation (all strikes)
            //      close               → option premium (ATM rows only, for S* = K+C−P)
            //      strike              → K in Put-Call Parity (the actual strike value)
            //      strike_position     → 'ATM' flag to isolate ATM rows
            //      atm_strike          → NOT used for PCP formula; kept for reference only
            //                           (it equals strike on ATM rows anyway)
            //
            $rawToday = OptionOhlcData::whereIn('base_symbol', $stockSymbols)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $date)
                ->where('is_missing', 0)
                ->whereNotNull('expiry_date')
                ->select([
                    'base_symbol',
                    'instrument_type',
                    'interval_time',
                    'oi',
                    'volume',
                    'close',           // premium → used in S* = K + C − P for ATM rows
                    'strike',          // K → the actual strike price of this row
                    'strike_position', // 'ATM' | 'ATM+1' | 'ATM-1' | 'N/A'
                    DB::raw('DATE(expiry_date) as exp_date'),
                ])
                ->get();

            // ── Build lookup tables ───────────────────────────────────────────
            //
            // stockOI[sym][type][HH:mm]  = { oi, vol }
            //   All strikes summed → OI delta / PCR / cumulative % / weight split
            //
            // atmData[sym][type][HH:mm]  = { oi, vol, close_sum, cnt, strike }
            //   ATM rows ONLY (strike_position = 'ATM')
            //   strike = K for Put-Call Parity (= atm_strike value)
            //   close_sum / cnt = average ATM premium across any duplicate ATM rows
            //
            $stockOI = [];
            $atmData = [];

            foreach ($rawToday as $row) {
                $sym = $row->base_symbol;
                $exp = $expiryMap[$sym] ?? null;
                if ($exp && $row->exp_date !== $exp) continue; // wrong expiry, skip

                $t    = Carbon::parse($row->interval_time)->format('H:i');
                $type = $row->instrument_type;

                // ── All-strike OI aggregate ───────────────────────────────
                $stockOI[$sym][$type][$t]['oi']  = ($stockOI[$sym][$type][$t]['oi']  ?? 0) + (int)$row->oi;
                $stockOI[$sym][$type][$t]['vol'] = ($stockOI[$sym][$type][$t]['vol'] ?? 0) + (int)$row->volume;

                // ── ATM-only aggregate ────────────────────────────────────
                if ($row->strike_position === 'ATM') {
                    $atmData[$sym][$type][$t]['oi']        = ($atmData[$sym][$type][$t]['oi']        ?? 0) + (int)$row->oi;
                    $atmData[$sym][$type][$t]['vol']       = ($atmData[$sym][$type][$t]['vol']       ?? 0) + (int)$row->volume;
                    $atmData[$sym][$type][$t]['close_sum'] = ($atmData[$sym][$type][$t]['close_sum'] ?? 0.0) + (float)$row->close;
                    $atmData[$sym][$type][$t]['cnt']       = ($atmData[$sym][$type][$t]['cnt']       ?? 0) + 1;
                    // K = strike from the ATM row itself (= atm_strike column value)
                    // Store once per sym+time (CE and PE share the same K)
                    if (!isset($atmData[$sym][$t]['K']) && $row->strike > 0) {
                        $atmData[$sym][$t]['K'] = (float)$row->strike;
                    }
                }
            }

            // ── 3. Prev-day 15:15 OI per stock ───────────────────────────────
            //    Used for:
            //      a) Day CE%/PE% change columns (vs prev close)
            //      b) SEED prevCeOI/prevPeOI so the 09:15 delta is real:
            //         delta_09:15 = OI_09:15_today − OI_15:15_prevday
            $rawPrev = OptionOhlcData::whereIn('base_symbol', $stockSymbols)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $prevDate)
                ->whereRaw("TIME(interval_time) = '15:15:00'")
                ->where('is_missing', 0)
                ->whereNotNull('expiry_date')
                ->select(['base_symbol', 'instrument_type', 'oi',
                          DB::raw('DATE(expiry_date) as exp_date')])
                ->get();

            $prevOI = [];
            foreach ($rawPrev as $row) {
                $sym = $row->base_symbol;
                $exp = $expiryMap[$sym] ?? null;
                if ($exp && $row->exp_date !== $exp) continue;
                $prevOI[$sym][$row->instrument_type] =
                    ($prevOI[$sym][$row->instrument_type] ?? 0) + (int)$row->oi;
            }

            // ── 4. Totals computed once ───────────────────────────────────────
            $prevCeTotal = 0;
            $prevPeTotal = 0;
            foreach (self::SENSEX30_STOCKS as $def) {
                $prevCeTotal += $prevOI[$def['symbol']]['CE'] ?? 0;
                $prevPeTotal += $prevOI[$def['symbol']]['PE'] ?? 0;
            }

            $baselineCe = 0;
            $baselinePe = 0;
            foreach (self::SENSEX30_STOCKS as $def) {
                $baselineCe += $stockOI[$def['symbol']]['CE']['09:15']['oi'] ?? 0;
                $baselinePe += $stockOI[$def['symbol']]['PE']['09:15']['oi'] ?? 0;
            }

            // ── 5. Synthetic price at 09:15 open (baseline for Move%) ─────────
            $syntheticOpenPrice = $this->calcWeightedSyntheticPrice($atmData, '09:15');

            // ── 6. Running VWAP accumulators (carry across candles) ───────────
            $cumTPV = 0.0;
            $cumVol = 0.0;

            // ── 7. State trackers ─────────────────────────────────────────────
            // (openWTotalOI tracks weight-adjusted open OI — see below)
            // Seed with prev-day 15:15 totals so 09:15 candle gets a real delta.
            // Falls back to null if prev-day data missing (no delta shown).
            // Raw prev (kept for display reference only — Day CE%/PE% columns)
            $prevCeOI = $prevCeTotal > 0 ? $prevCeTotal : null;
            $prevPeOI = $prevPeTotal > 0 ? $prevPeTotal : null;

            // ── Weight-adjusted prev OI trackers ──────────────────────────
            // ALL signal logic (delta, signal, PCR, cumulative, OI type)
            // uses THESE — not raw totals — so heavy stocks drive the signal.
            //
            // Seeded from prev-day 15:15 weight-adjusted totals so the
            // 09:15 candle gets a real weighted delta vs yesterday's close.
            $prevWCeOI  = 0.0;
            $prevWPeOI  = 0.0;
            foreach (self::SENSEX30_STOCKS as $def) {
                $prevWCeOI += ($prevOI[$def['symbol']]['CE'] ?? 0) * ($def['weight'] / 100);
                $prevWPeOI += ($prevOI[$def['symbol']]['PE'] ?? 0) * ($def['weight'] / 100);
            }
            $prevWCeOI = $prevWCeOI > 0 ? $prevWCeOI : null;
            $prevWPeOI = $prevWPeOI > 0 ? $prevWPeOI : null;

            // Weight-adjusted baseline at 09:15 (for cumulative % from open)
            $baselineWCe = 0.0;
            $baselineWPe = 0.0;
            foreach (self::SENSEX30_STOCKS as $def) {
                $baselineWCe += ($stockOI[$def['symbol']]['CE']['09:15']['oi'] ?? 0) * ($def['weight'] / 100);
                $baselineWPe += ($stockOI[$def['symbol']]['PE']['09:15']['oi'] ?? 0) * ($def['weight'] / 100);
            }

            // Weight-adjusted prev-day totals (for Day CE%/PE% columns)
            $prevWCeTotal = 0.0;
            $prevWPeTotal = 0.0;
            foreach (self::SENSEX30_STOCKS as $def) {
                $prevWCeTotal += ($prevOI[$def['symbol']]['CE'] ?? 0) * ($def['weight'] / 100);
                $prevWPeTotal += ($prevOI[$def['symbol']]['PE'] ?? 0) * ($def['weight'] / 100);
            }

            // Weight-adjusted open OI tracker (for OI Type % change)
            $openWTotalOI = null;

            foreach (self::INTERVALS as $time) {

                // ── Aggregate all 30 stocks at this candle ─────────────────
                $totalCeOI  = 0; $totalPeOI  = 0;
                $totalCeVol = 0; $totalPeVol = 0;
                $wCeOI = 0.0;   $wPeOI = 0.0;

                // ATM aggregates (weight-adjusted) — for ATM cols + PCP price
                $wAtmCeOI   = 0.0; $wAtmPeOI   = 0.0;
                $wAtmCePrem = 0.0; $wAtmPePrem = 0.0; // weighted avg premiums
                $wSynth     = 0.0; // Σ (S* × w/100)
                $wUsed      = 0.0; // Σ (w/100) for stocks with valid ATM data
                $wAtmVol    = 0.0; // Σ (ATM_vol × w/100) for VWAP this candle
                $found      = 0;

                foreach (self::SENSEX30_STOCKS as $def) {
                    $sym = $def['symbol'];
                    $w   = $def['weight'];

                    $ceOI = $stockOI[$sym]['CE'][$time]['oi']  ?? 0;
                    $ceV  = $stockOI[$sym]['CE'][$time]['vol'] ?? 0;
                    $peOI = $stockOI[$sym]['PE'][$time]['oi']  ?? 0;
                    $peV  = $stockOI[$sym]['PE'][$time]['vol'] ?? 0;

                    if ($ceOI === 0 && $peOI === 0) continue;
                    $found++;

                    // All-strike OI
                    $totalCeOI  += $ceOI; $totalPeOI  += $peOI;
                    $totalCeVol += $ceV;  $totalPeVol += $peV;
                    $wCeOI += $ceOI * ($w / 100);
                    $wPeOI += $peOI * ($w / 100);

                    // ATM OI (weight-adjusted, for ATM CE%/PE%/PCR columns)
                    $atmCeOI = $atmData[$sym]['CE'][$time]['oi'] ?? 0;
                    $atmPeOI = $atmData[$sym]['PE'][$time]['oi'] ?? 0;
                    $wAtmCeOI += $atmCeOI * ($w / 100);
                    $wAtmPeOI += $atmPeOI * ($w / 100);

                    // ATM premiums — average over cnt (handles rare duplicate ATM rows)
                    $ceCnt  = max(1, $atmData[$sym]['CE'][$time]['cnt'] ?? 1);
                    $peCnt  = max(1, $atmData[$sym]['PE'][$time]['cnt'] ?? 1);
                    $cePrem = ($atmData[$sym]['CE'][$time]['close_sum'] ?? 0.0) / $ceCnt;
                    $pePrem = ($atmData[$sym]['PE'][$time]['close_sum'] ?? 0.0) / $peCnt;
                    $wAtmCePrem += $cePrem * ($w / 100);
                    $wAtmPePrem += $pePrem * ($w / 100);

                    // ── Put-Call Parity: S* = K + C − P ──────────────────
                    //    K = strike column from the ATM row (stored as atmData[sym][time]['K'])
                    //    C = ATM CE close  (avg premium)
                    //    P = ATM PE close  (avg premium)
                    $K = $atmData[$sym][$time]['K'] ?? null;
                    if ($K !== null && ($cePrem > 0 || $pePrem > 0)) {
                        $synthetic  = $K + $cePrem - $pePrem;
                        $wSynth    += $synthetic * ($w / 100);
                        $wUsed     += $w / 100;

                        // VWAP: weight-scaled ATM volume as "candle volume"
                        $atmCeVol   = ($atmData[$sym]['CE'][$time]['vol'] ?? 0);
                        $atmPeVol   = ($atmData[$sym]['PE'][$time]['vol'] ?? 0);
                        $scaledVol  = ($atmCeVol + $atmPeVol) * ($w / 100);
                        $wAtmVol   += $scaledVol;

                        // Accumulate TP × Vol for running VWAP
                        // Using synthetic price as TP (= implied spot)
                        $cumTPV    += $synthetic * ($w / 100) * $scaledVol;
                        $cumVol    += $scaledVol;
                    }
                }

                if ($found === 0) {
                    $prevWCeOI = null;
                    $prevWPeOI = null;
                    continue;
                }

                // Weight-adjusted totals this candle
                $wTotal   = $wCeOI + $wPeOI;
                $wTotalOI = $wCeOI + $wPeOI; // alias for clarity

                // Track open (weight-adjusted) for OI Type
                if ($openWTotalOI === null) $openWTotalOI = $wTotalOI;

                // ── A) Weight-adjusted OI Delta (PRIMARY SIGNAL) ──────────
                // HDFCBANK delta counts 14.2× more than ETERNAL delta.
                // This prevents low-weight stocks from overriding the signal.
                $wDeltaCe = ($prevWCeOI !== null) ? $wCeOI - $prevWCeOI : 0.0;
                $wDeltaPe = ($prevWPeOI !== null) ? $wPeOI - $prevWPeOI : 0.0;
                $wAbsDelta = abs($wDeltaCe) + abs($wDeltaPe);

                // Raw delta (for display numbers in the table — K/M notation)
                $totalOI  = $totalCeOI + $totalPeOI;
                $rawDeltaCe = ($prevCeOI !== null) ? $totalCeOI - $prevCeOI : 0;
                $rawDeltaPe = ($prevPeOI !== null) ? $totalPeOI - $prevPeOI : 0;
                $rawAbsDelta = abs($rawDeltaCe) + abs($rawDeltaPe);

                // % split shown in bar uses weighted delta share
                $deltaCePct = $wAbsDelta > 0 ? round($wDeltaCe / $wAbsDelta * 100, 1) : 0;
                $deltaPePct = $wAbsDelta > 0 ? round($wDeltaPe / $wAbsDelta * 100, 1) : 0;

                // ── B) Cumulative weight-adjusted OI change from 09:15 ────
                $cumCeChg = $baselineWCe > 0
                    ? round(($wCeOI - $baselineWCe) / $baselineWCe * 100, 2) : 0;
                $cumPeChg = $baselineWPe > 0
                    ? round(($wPeOI - $baselineWPe) / $baselineWPe * 100, 2) : 0;

                // ── C) Weight-adjusted OI change vs prev day close ────────
                $dayChgCe = $prevWCeTotal > 0
                    ? round(($wCeOI - $prevWCeTotal) / $prevWCeTotal * 100, 2) : 0;
                $dayChgPe = $prevWPeTotal > 0
                    ? round(($wPeOI - $prevWPeTotal) / $prevWPeTotal * 100, 2) : 0;

                // ── D) Weight-adjusted CE/PE split % ─────────────────────
                $wCePct = $wTotal > 0 ? round($wCeOI / $wTotal * 100, 2) : 50.0;
                $wPePct = round(100 - $wCePct, 2);

                // ── E) Weight-adjusted PCR ────────────────────────────────
                // Uses wCeOI/wPeOI so heavy stocks drive the ratio
                $pcr = $wCeOI > 0 ? round($wPeOI / $wCeOI, 3) : 0;

                // ── F) Signal — driven entirely by weight-adjusted delta ──
                // Key: wDeltaCe/wDeltaPe reflect Sensex weight, so
                // HDFCBANK adding CE OI will dominate over 10 tiny stocks adding PE.
                if ($prevWCeOI === null) {
                    // No prev data: use weight-adjusted day change direction
                    $signal = $dayChgPe > $dayChgCe ? 'BULLISH'
                            : ($dayChgCe > $dayChgPe ? 'BEARISH' : 'NEUTRAL');
                } else {
                    if ($wDeltaCe > 0 && $wDeltaPe > 0) {
                        $signal = $wDeltaPe > $wDeltaCe ? 'BULLISH' : 'BEARISH';
                    } elseif ($wDeltaCe > 0 && $wDeltaPe <= 0) {
                        $signal = 'BEARISH';  // weighted CE building → shorts
                    } elseif ($wDeltaPe > 0 && $wDeltaCe <= 0) {
                        $signal = 'BULLISH';  // weighted PE building → longs
                    } elseif ($wDeltaCe < 0 && $wDeltaPe < 0) {
                        $signal = abs($wDeltaCe) > abs($wDeltaPe) ? 'BULLISH' : 'BEARISH';
                    } else {
                        $signal = 'NEUTRAL';
                    }
                }

                // ── G) ATM columns (30-stock, weight-adjusted) ────────────
                $atmTotal = $wAtmCeOI + $wAtmPeOI;
                $atmCePct = $atmTotal > 0 ? round($wAtmCeOI / $atmTotal * 100, 2) : 50;
                $atmPePct = round(100 - $atmCePct, 2);
                $atmPcr   = $wAtmCeOI > 0 ? round($wAtmPeOI / $wAtmCeOI, 3) : 0;

                // ── H) Synthetic price (Put-Call Parity) ──────────────────
                // Normalise by wUsed (not 1.0) in case some stocks lack ATM data
                $syntheticPrice = $wUsed > 0 ? round($wSynth / $wUsed, 2) : null;

                // ── I) Running VWAP ────────────────────────────────────────
                $vwap     = $cumVol > 0 ? round($cumTPV / $cumVol, 2) : null;
                $vwapPos  = 'UNKNOWN';
                $vwapDist = null;
                if ($vwap !== null && $syntheticPrice !== null) {
                    $vwapDist = round(($syntheticPrice - $vwap) / $vwap * 100, 2);
                    $vwapPos  = $vwapDist > 0.3  ? 'ABOVE'
                              : ($vwapDist < -0.3 ? 'BELOW' : 'AT');
                }

                // ── J) Synthetic price move% from open ────────────────────
                $synthMovePct = null;
                $synthMoveAbs = null;
                if ($syntheticOpenPrice !== null && $syntheticPrice !== null && $syntheticOpenPrice > 0) {
                    $synthMovePct = round(($syntheticPrice - $syntheticOpenPrice) / $syntheticOpenPrice * 100, 2);
                    $synthMoveAbs = round($syntheticPrice - $syntheticOpenPrice, 2);
                }

                // ── K) OI Type — weight-adjusted OI change from open ──────
                $oiChgPct = ($openWTotalOI && $openWTotalOI > 0)
                    ? round(($wTotalOI - $openWTotalOI) / $openWTotalOI * 100, 2)
                    : null;

                $oiType = null;
                if ($synthMovePct !== null && $oiChgPct !== null) {
                    $oiType = match(true) {
                        $synthMovePct > 0  && $oiChgPct > 1  => 'LONG_BUILD',
                        $synthMovePct < 0  && $oiChgPct > 1  => 'SHORT_BUILD',
                        $synthMovePct > 0  && $oiChgPct <= 1 => 'SHORT_COVER',
                        $synthMovePct < 0  && $oiChgPct <= 1 => 'LONG_UNWIND',
                        default                               => 'NEUTRAL',
                    };
                }

                $timeline[] = [
                    'time'               => $time,
                    // Raw OI (all strikes, 30 stocks)
                    'ce_oi'              => $totalCeOI,
                    'pe_oi'              => $totalPeOI,
                    'ce_vol'             => $totalCeVol,
                    'pe_vol'             => $totalPeVol,
                    'pcr'                => $pcr,
                    'signal'             => $signal,
                    'stocks_found'       => $found,
                    // A) Candle delta — raw numbers for display, weighted % for bar
                    'delta_ce'           => $rawDeltaCe,   // raw absolute (for K/M display)
                    'delta_pe'           => $rawDeltaPe,
                    'w_delta_ce'         => round($wDeltaCe, 2), // weighted (drives signal)
                    'w_delta_pe'         => round($wDeltaPe, 2),
                    'delta_ce_pct'       => $deltaCePct,   // weighted share % (for bar width)
                    'delta_pe_pct'       => $deltaPePct,
                    // B) Cumulative from open
                    'cum_ce_chg'         => $cumCeChg,
                    'cum_pe_chg'         => $cumPeChg,
                    // C) Vs prev day
                    'day_ce_chg'         => $dayChgCe,
                    'day_pe_chg'         => $dayChgPe,
                    // D) Weight-adjusted split
                    'w_ce_pct'           => $wCePct,
                    'w_pe_pct'           => $wPePct,
                    // G) ATM OI (30-stock, weight-adjusted)
                    'atm_ce_oi'          => round($wAtmCeOI),
                    'atm_pe_oi'          => round($wAtmPeOI),
                    'atm_ce_pct'         => $atmCePct,
                    'atm_pe_pct'         => $atmPePct,
                    'atm_pcr'            => $atmPcr,
                    'atm_ce_prem'        => round($wAtmCePrem, 2), // weighted avg CE premium
                    'atm_pe_prem'        => round($wAtmPePrem, 2), // weighted avg PE premium
                    // H) Synthetic price  S* = K + C - P
                    'synthetic_price'    => $syntheticPrice,
                    'synthetic_open'     => $syntheticOpenPrice,
                    // I) VWAP (running, synthetic price × ATM volume, weight-scaled)
                    'vwap'               => $vwap,
                    'vwap_position'      => $vwapPos,
                    'vwap_dist_pct'      => $vwapDist,
                    // J) Synthetic move from open
                    'synthetic_move_abs' => $synthMoveAbs,
                    'synthetic_move_pct' => $synthMovePct,
                    // K) OI Type
                    'oi_chg_pct'         => $oiChgPct,
                    'oi_type'            => $oiType,
                ];

                $prevCeOI  = $totalCeOI;  // raw — for display reference
                $prevPeOI  = $totalPeOI;
                $prevWCeOI = $wCeOI;      // weighted — drives all signal logic
                $prevWPeOI = $wPeOI;
            }

            if (empty($timeline)) {
                return response()->json([
                    'success'   => true,
                    'date'      => $date,
                    'timeline'  => [],
                    'summary'   => null,
                    'is_expiry' => $isExpiry,
                    'message'   => 'No data found for this date.',
                ]);
            }

            return response()->json([
                'success'       => true,
                'date'          => $date,
                'prev_date'     => $prevDate,
                'is_expiry'     => $isExpiry,
                'timeline'      => $timeline,
                'summary'       => $this->buildSummary($timeline),
                'total_candles' => count($timeline),
                'analyzed_at'   => now()->format('Y-m-d H:i:s'),
            ]);

        } catch (\Exception $e) {
            Log::error('SensexIntraday v4 — ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    /**
     * Weighted synthetic price at a specific time slot.
     *
     * S* = K + C − P  (Put-Call Parity)
     *   K = atmData[sym][time]['K']  = strike column from ATM row
     *   C = ATM CE avg premium
     *   P = ATM PE avg premium
     *
     * Normalised by total weight of stocks that had valid ATM data.
     */
    private function calcWeightedSyntheticPrice(array $atmData, string $time): ?float
    {
        $wSynth = 0.0;
        $wUsed  = 0.0;

        foreach (self::SENSEX30_STOCKS as $def) {
            $sym = $def['symbol'];
            $w   = $def['weight'];
            $K   = $atmData[$sym][$time]['K'] ?? null;
            if ($K === null) continue;

            $ceCnt  = max(1, $atmData[$sym]['CE'][$time]['cnt'] ?? 1);
            $peCnt  = max(1, $atmData[$sym]['PE'][$time]['cnt'] ?? 1);
            $cePrem = ($atmData[$sym]['CE'][$time]['close_sum'] ?? 0.0) / $ceCnt;
            $pePrem = ($atmData[$sym]['PE'][$time]['close_sum'] ?? 0.0) / $peCnt;

            if ($cePrem <= 0 && $pePrem <= 0) continue;

            $wSynth += ($K + $cePrem - $pePrem) * ($w / 100);
            $wUsed  += $w / 100;
        }

        return $wUsed > 0 ? round($wSynth / $wUsed, 2) : null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    private function buildSummary(array $tl): array
    {
        $first = $tl[0];
        $last  = $tl[count($tl) - 1];
        $pcrs  = array_filter(array_column($tl, 'pcr'), fn($v) => $v > 0);

        $bullCount = count(array_filter($tl, fn($r) => $r['signal'] === 'BULLISH'));
        $bearCount = count(array_filter($tl, fn($r) => $r['signal'] === 'BEARISH'));
        $neutCount = count($tl) - $bullCount - $bearCount;

        $flips = [];
        for ($i = 1; $i < count($tl); $i++) {
            $p = $tl[$i - 1]['signal'];
            $c = $tl[$i]['signal'];
            if ($p !== $c && $c !== 'NEUTRAL' && $p !== 'NEUTRAL') {
                $flips[] = ['time' => $tl[$i]['time'], 'from' => $p, 'to' => $c];
            }
        }

        $maxPeCandle = collect($tl)->sortByDesc('delta_pe')->first();
        $maxCeCandle = collect($tl)->sortByDesc('delta_ce')->first();

        $synthMove = ($first['synthetic_price'] && $last['synthetic_price'])
            ? round($last['synthetic_price'] - $first['synthetic_price'], 2) : null;

        return [
            'opening_signal'       => $first['signal'],
            'closing_signal'       => $last['signal'],
            'opening_pcr'          => $first['pcr'],
            'closing_pcr'          => $last['pcr'],
            'pcr_min'              => $pcrs ? round(min($pcrs), 3) : 0,
            'pcr_max'              => $pcrs ? round(max($pcrs), 3) : 0,
            'net_ce_chg'           => $last['cum_ce_chg'],
            'net_pe_chg'           => $last['cum_pe_chg'],
            'day_ce_chg'           => $last['day_ce_chg'],
            'day_pe_chg'           => $last['day_pe_chg'],
            'bull_candles'         => $bullCount,
            'bear_candles'         => $bearCount,
            'neut_candles'         => $neutCount,
            'total_flips'          => count($flips),
            'flips'                => $flips,
            'dominant_bias'        => $bullCount >= $bearCount ? 'BULLISH' : 'BEARISH',
            'max_pe_add_time'      => $maxPeCandle['time']     ?? null,
            'max_pe_add'           => $maxPeCandle['delta_pe'] ?? null,
            'max_ce_add_time'      => $maxCeCandle['time']     ?? null,
            'max_ce_add'           => $maxCeCandle['delta_ce'] ?? null,
            'opening_synthetic'    => $first['synthetic_price'],
            'closing_synthetic'    => $last['synthetic_price'],
            'total_synthetic_move' => $synthMove,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    private function resolveNearestExpiry(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if (!$expiry) {
            $expiry = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('expiry_date')
                ->whereDate('trade_date', $date)
                ->orderByDesc('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
        }

        if ($expiry === $date) {
            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $date)
                ->whereDate('expiry_date', '>', $expiry)
                ->orderBy('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
            if ($next) return $next;
        }

        return $expiry;
    }

    private function isExpiryDay(string $date): bool
    {
        return OptionOhlcData::whereIn('base_symbol', array_column(self::SENSEX30_STOCKS, 'symbol'))
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('expiry_date', $date)
            ->whereDate('trade_date', $date)
            ->exists();
    }

    private function getPreviousTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) {
                return $d->format('Y-m-d');
            }
            $d->subDay();
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->whereIn('market_name', ['BSE', 'NSE'])
            ->where('holiday_date', $date)
            ->exists();
    }
}