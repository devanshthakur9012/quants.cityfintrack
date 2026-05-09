<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use App\Models\BrokerApi;
use App\Models\ZerodhaInstrument;
use App\Helpers\BrokerZerodhaHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║         Straddle & Strangle — 15-Min Candle Controller                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║  TWO MODES (mirrors Pivot Signal page behaviour):                        ║
 * ║                                                                          ║
 * ║  MODE 1 — symbol=ALL  (default)                                          ║
 * ║    One summary row per symbol — entry + latest candle only               ║
 * ║    Same as the existing all-symbols table                                 ║
 * ║                                                                          ║
 * ║  MODE 2 — symbol=NIFTY (specific symbol selected)                        ║
 * ║    Every 15-min interval row for that symbol                             ║
 * ║    Shows how the strategy is performing candle by candle                 ║
 * ║    OI Init stays fixed (09:15), CE%/PE% updates each row                ║
 * ║    P&L, exit decision, remarks updated per interval                      ║
 * ║                                                                          ║
 * ║  ENTRY PRICE (live LTP cache):                                           ║
 * ║    Today  → Live LTP from Zerodha at ~09:16, cached 60 min              ║
 * ║    History → 09:15 candle open from DB                                   ║
 * ║                                                                          ║
 * ║  LOT SIZE:                                                               ║
 * ║    Fetched from ZerodhaInstrument table via trading_symbol               ║
 * ║    P&L per unit × lot_size = P&L per lot (shown separately)             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class StraddleStrangle15Controller extends Controller
{
    private const ENTRY_SLOT      = '09:15';
    private const LTP_CACHE_TTL   = 3600;           // 60 min
    private const BROKER_CLIENT_ID = 'DB0542';      // change to your broker

    private const STRATEGIES = [
        'long_strangle'  => ['name' => 'Long Strangle',  'ce_type' => 'Buy',  'ce_pos' => 'ATM+1', 'pe_type' => 'Buy',  'pe_pos' => 'ATM-1'],
        'short_strangle' => ['name' => 'Short Strangle', 'ce_type' => 'Sell', 'ce_pos' => 'ATM+1', 'pe_type' => 'Sell', 'pe_pos' => 'ATM-1'],
        'long_straddle'  => ['name' => 'Long Straddle',  'ce_type' => 'Buy',  'ce_pos' => 'ATM',   'pe_type' => 'Buy',  'pe_pos' => 'ATM'],
        'short_straddle' => ['name' => 'Short Straddle', 'ce_type' => 'Sell', 'ce_pos' => 'ATM',   'pe_type' => 'Sell', 'pe_pos' => 'ATM'],
    ];

    // ══════════════════════════════════════════════════════════════════════════
    // Page
    // ══════════════════════════════════════════════════════════════════════════

    public function index()
    {
        $pageTitle = 'Straddle & Strangle (15-Min)';
        return view($this->activeTemplate . 'user.straddle-strangle-15.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Data API
    // GET /straddle-strangle-15/data?strategy=long_straddle&date=Y-m-d&symbol=ALL|NIFTY
    // ══════════════════════════════════════════════════════════════════════════

    public function getData(Request $request)
    {
        try {
            $stratKey = $request->get('strategy', 'long_straddle');
            if (!isset(self::STRATEGIES[$stratKey])) $stratKey = 'long_straddle';
            $def = self::STRATEGIES[$stratKey];

            $today = $request->get('date')
                ? Carbon::parse($request->get('date'))->toDateString()
                : Carbon::today()->toDateString();

            $symbolFilter = strtoupper(trim($request->get('symbol', 'ALL')));
            $isAll        = ($symbolFilter === 'ALL');
            $isToday      = $today === Carbon::today()->toDateString();
            $isLiveTime   = $isToday && Carbon::now()->format('H:i') >= '09:15';

            // Current time in minutes for candle completeness check
            $nowMins = (int)Carbon::now()->format('H') * 60 + (int)Carbon::now()->format('i');

            // ── Available symbols on this date ────────────────────────────────
            $availableSymbols = OptionOhlcData::whereDate('trade_date', $today)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereNotNull('base_symbol')
                ->distinct()->pluck('base_symbol')
                ->sort()->values()->toArray();

            if (empty($availableSymbols)) {
                return $this->emptyJson($today, 'No data found for ' . $today, [], $symbolFilter);
            }

            // Symbols to process
            $symbolsToLoad = $isAll ? $availableSymbols : [$symbolFilter];

            // ── Load rows from DB ─────────────────────────────────────────────
            $query = OptionOhlcData::whereDate('trade_date', $today)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereIn('strike_position', [$def['ce_pos'], $def['pe_pos']])
                ->whereIn('base_symbol', $symbolsToLoad)
                ->orderBy('base_symbol')
                ->orderBy('interval_time');

            $allRows = $query->get();

            if ($allRows->isEmpty()) {
                return $this->emptyJson($today,
                    "No 15-min data for [{$def['name']}] on {$today}", $availableSymbols, $symbolFilter);
            }

            // ── Collect all unique trading symbols to look up lot sizes ────────
            $tradingSymbols = $allRows->pluck('trading_symbol')->filter()->unique()->values()->toArray();

            // ── Fetch lot sizes from ZerodhaInstrument ─────────────────────────
            // We look up by trading_symbol (NFO segment). If a symbol appears for
            // multiple expiries we take the first match — lot size is the same
            // within a base symbol family.
            $lotSizeMap = $this->fetchLotSizes($tradingSymbols);

            // ── Index: $indexed[symbol][CE|PE][pos][H:i] = row ───────────────
            // Also collect all time slots per symbol
            $indexed    = [];
            $allTimes   = [];   // [sym => sorted array of H:i]
            $latestTime = [];   // [sym => latest COMPLETE H:i]

            foreach ($allRows as $row) {
                $sym = $row->base_symbol;
                $t   = Carbon::parse($row->interval_time)->format('H:i');
                $indexed[$sym][$row->instrument_type][$row->strike_position][$t] = $row;
                $allTimes[$sym][$t] = true;

                // Track latest COMPLETE candle (T + 15 min <= now for today)
                if ($isToday) {
                    $cMins = (int)explode(':', $t)[0] * 60 + (int)explode(':', $t)[1];
                    if (($cMins + 15) <= $nowMins) {
                        if (!isset($latestTime[$sym]) || $t > $latestTime[$sym]) {
                            $latestTime[$sym] = $t;
                        }
                    }
                } else {
                    if (!isset($latestTime[$sym]) || $t > $latestTime[$sym]) {
                        $latestTime[$sym] = $t;
                    }
                }
            }

            // Sort time slots per symbol
            foreach ($allTimes as $sym => $tMap) {
                ksort($tMap);
                $allTimes[$sym] = array_keys($tMap);
            }

            // Fallback latest for very early morning
            foreach ($symbolsToLoad as $sym) {
                if (!isset($latestTime[$sym]) && isset($allTimes[$sym])) {
                    $latestTime[$sym] = end($allTimes[$sym]);
                }
            }

            // ── Live LTP fetch for entry prices ───────────────────────────────
            $liveLtpMap = [];
            if ($isLiveTime) {
                $liveLtpMap = $this->fetchOrCacheLiveLtp($symbolsToLoad, $def, $indexed, $today);
            }

            // ══════════════════════════════════════════════════════════════════
            // MODE 1: ALL symbols — one summary row per symbol
            // ══════════════════════════════════════════════════════════════════
            if ($isAll) {
                $results = [];
                foreach ($availableSymbols as $sym) {
                    if (!isset($indexed[$sym])) continue;
                    $latest = $latestTime[$sym] ?? null;
                    if (!$latest) continue;

                    $row = $this->buildSummaryRow($sym, $def, $indexed[$sym], $latest, $liveLtpMap, $isLiveTime, $lotSizeMap);
                    if ($row) $results[] = $row;
                }

                return response()->json([
                    'success'           => true,
                    'mode'              => 'summary',
                    'data'              => $results,
                    'strategy_key'      => $stratKey,
                    'strategy_name'     => $def['name'],
                    'ce_type'           => $def['ce_type'],
                    'pe_type'           => $def['pe_type'],
                    'ce_pos'            => $def['ce_pos'],
                    'pe_pos'            => $def['pe_pos'],
                    'today'             => $today,
                    'is_today'          => $isToday,
                    'entry_slot'        => self::ENTRY_SLOT,
                    'price_source'      => $isLiveTime ? 'live_ltp_9:16' : '09:15_candle_open',
                    'symbol'            => 'ALL',
                    'available_symbols' => $availableSymbols,
                    'total'             => count($results),
                ]);
            }

            // ══════════════════════════════════════════════════════════════════
            // MODE 2: Specific symbol — every 15-min interval row
            // ══════════════════════════════════════════════════════════════════
            if (!isset($indexed[$symbolFilter])) {
                return $this->emptyJson($today,
                    "No data for {$symbolFilter} on {$today}", $availableSymbols, $symbolFilter);
            }

            $sym     = $symbolFilter;
            $symData = $indexed[$sym];
            $times   = $allTimes[$sym] ?? [];

            $ceSlots = $symData['CE'][$def['ce_pos']] ?? [];
            $peSlots = $symData['PE'][$def['pe_pos']] ?? [];

            // Entry candle
            $ceEntry = $ceSlots[self::ENTRY_SLOT] ?? null;
            $peEntry = $peSlots[self::ENTRY_SLOT] ?? null;
            $atmStrike = $ceEntry?->atm_strike ?? $peEntry?->atm_strike ?? null;

            // Entry price (live or 09:15 open)
            $ceLtpKey    = "{$sym}_CE_{$def['ce_pos']}";
            $peLtpKey    = "{$sym}_PE_{$def['pe_pos']}";
            $cePrice     = $liveLtpMap[$ceLtpKey] ?? ($ceEntry ? round((float)$ceEntry->open, 2) : null);
            $pePrice     = $liveLtpMap[$peLtpKey] ?? ($peEntry ? round((float)$peEntry->open, 2) : null);
            $priceSource = isset($liveLtpMap[$ceLtpKey]) ? 'live' : '09:15_open';

            // OI initiation (fixed from 09:15)
            $ceOiInit = $ceEntry ? (int)$ceEntry->oi : null;
            $peOiInit = $peEntry ? (int)$peEntry->oi : null;

            // ── Lot sizes for this symbol ──────────────────────────────────────
            $ceTradingSymbol = $ceEntry?->trading_symbol;
            $peTradingSymbol = $peEntry?->trading_symbol;
            $ceLotSize       = $ceTradingSymbol ? ($lotSizeMap[$ceTradingSymbol] ?? 1) : 1;
            $peLotSize       = $peTradingSymbol ? ($lotSizeMap[$peTradingSymbol] ?? 1) : 1;
            // For a straddle/strangle both legs belong to the same underlying —
            // lot size is the same. We use CE as the canonical source; fallback to PE.
            $lotSize         = $ceLotSize > 1 ? $ceLotSize : ($peLotSize > 1 ? $peLotSize : 1);

            // Strategy combined premium (fixed for the day)
            $isShort         = ($def['ce_type'] === 'Sell');
            $strategyPremium = ($cePrice !== null && $pePrice !== null)
                ? round($cePrice + $pePrice, 2) : null;

            // Expiry
            $anyRow = $ceEntry ?? $peEntry;
            $expiry = $anyRow?->expiry_date
                ? Carbon::parse($anyRow->expiry_date)->toDateString()
                : null;

            // Build one row per 15-min interval
            $intervalRows = [];
            foreach ($times as $t) {
                $ce = $ceSlots[$t] ?? null;
                $pe = $peSlots[$t] ?? null;

                $ceCrntOi = $ce ? (int)$ce->oi    : null;
                $peCrntOi = $pe ? (int)$pe->oi    : null;
                $ceLtp    = $ce ? round((float)$ce->close, 2) : null;
                $peLtp    = $pe ? round((float)$pe->close, 2) : null;

                // OI % from initiation to this candle
                $cePct = ($ceOiInit && $ceOiInit > 0 && $ceCrntOi !== null)
                    ? round((($ceCrntOi - $ceOiInit) / $ceOiInit) * 100, 2) : null;
                $pePct = ($peOiInit && $peOiInit > 0 && $peCrntOi !== null)
                    ? round((($peCrntOi - $peOiInit) / $peOiInit) * 100, 2) : null;

                // Running premium at this candle
                $runningPremium = ($ceLtp !== null && $peLtp !== null)
                    ? round($ceLtp + $peLtp, 2) : null;

                // P&L per unit at this candle
                $pnl = null;
                if ($strategyPremium !== null && $runningPremium !== null) {
                    $pnl = $isShort
                        ? round($strategyPremium - $runningPremium, 2)
                        : round($runningPremium  - $strategyPremium, 2);
                }

                // P&L per lot (per unit × lot size)
                $pnlLot = $pnl !== null ? round($pnl * $lotSize, 2) : null;

                // OI signal & exit decision per interval
                $oiSignal = ($cePct !== null && $pePct !== null)
                    ? $this->calcOiSignal($cePct, $pePct)
                    : $this->noSentiment();

                [$exitFirst, $holdLeg, $remarks] = $this->calcExitDecision($cePct, $pePct);

                // Is this the latest complete candle?
                $isLatest = ($t === ($latestTime[$sym] ?? null));
                // Is this the entry candle?
                $isEntry  = ($t === self::ENTRY_SLOT);

                $intervalRows[] = [
                    'time'                      => $t,
                    'is_entry'                  => $isEntry,
                    'is_latest'                 => $isLatest,

                    // CE
                    'ce_trading_symbol'         => $ce?->trading_symbol ?? $ceEntry?->trading_symbol,
                    'ce_strike'                 => $ce ? (float)$ce->strike : ($ceEntry ? (float)$ceEntry->strike : null),
                    'ce_price'                  => $cePrice,       // fixed entry price all rows
                    'ce_oi_init'                => $ceOiInit,      // fixed
                    'ce_crnt_oi'                => $ceCrntOi,      // per interval
                    'ce_pct'                    => $cePct,
                    'ce_ltp'                    => $ceLtp,
                    'ce_open'                   => $ce ? round((float)$ce->open, 2) : null,
                    'ce_high'                   => $ce ? round((float)$ce->high, 2) : null,
                    'ce_low'                    => $ce ? round((float)$ce->low,  2) : null,

                    // PE
                    'pe_trading_symbol'         => $pe?->trading_symbol ?? $peEntry?->trading_symbol,
                    'pe_strike'                 => $pe ? (float)$pe->strike : ($peEntry ? (float)$peEntry->strike : null),
                    'pe_price'                  => $pePrice,
                    'pe_oi_init'                => $peOiInit,
                    'pe_crnt_oi'                => $peCrntOi,
                    'pe_pct'                    => $pePct,
                    'pe_ltp'                    => $peLtp,
                    'pe_open'                   => $pe ? round((float)$pe->open, 2) : null,
                    'pe_high'                   => $pe ? round((float)$pe->high, 2) : null,
                    'pe_low'                    => $pe ? round((float)$pe->low,  2) : null,

                    // Premiums & P&L
                    'strategy_combined_premium' => $strategyPremium,
                    'running_combined_premium'  => $runningPremium,
                    'profit_loss'               => $pnl,
                    'profit_loss_lot'           => $pnlLot,
                    'lot_size'                  => $lotSize,

                    // OI signal
                    'mkt_sentiment'             => $oiSignal['signal'],
                    'oi_condition'              => $oiSignal['condition'],
                    'oi_reason'                 => $oiSignal['reason'],
                    'oi_strength'               => $oiSignal['strength'],

                    // Exit
                    'exit_first'                => $exitFirst,
                    'hold_leg'                  => $holdLeg,
                    'remarks'                   => $remarks,
                ];
            }

            return response()->json([
                'success'                   => true,
                'mode'                      => 'detail',
                'data'                      => $intervalRows,
                'symbol'                    => $sym,
                'expiry'                    => $expiry,
                'atm_strike'                => $atmStrike,
                'ce_txn_type'               => $def['ce_type'],
                'pe_txn_type'               => $def['pe_type'],
                'ce_pos'                    => $def['ce_pos'],
                'pe_pos'                    => $def['pe_pos'],
                'ce_trading_symbol'         => $ceEntry?->trading_symbol,
                'pe_trading_symbol'         => $peEntry?->trading_symbol,
                'ce_price'                  => $cePrice,
                'pe_price'                  => $pePrice,
                'ce_oi_init'                => $ceOiInit,
                'pe_oi_init'                => $peOiInit,
                'strategy_combined_premium' => $strategyPremium,
                'lot_size'                  => $lotSize,
                'strategy_key'              => $stratKey,
                'strategy_name'             => $def['name'],
                'today'                     => $today,
                'is_today'                  => $isToday,
                'entry_slot'                => self::ENTRY_SLOT,
                'latest_slot'               => $latestTime[$sym] ?? null,
                'price_source'              => $priceSource,
                'total_intervals'           => count($intervalRows),
                'available_symbols'         => $availableSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('StraddleStrangle15 getData: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Fetch lot sizes from ZerodhaInstrument for given trading symbols
    // Returns: [ trading_symbol => lot_size, ... ]
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchLotSizes(array $tradingSymbols): array
    {
        if (empty($tradingSymbols)) return [];

        $rows = ZerodhaInstrument::whereIn('trading_symbol', $tradingSymbols)
            ->whereIn('segment', ['NFO-OPT', 'NFO'])
            ->select('trading_symbol', 'lot_size')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            // Only store first hit per symbol (lot size is uniform within a symbol)
            if (!isset($map[$row->trading_symbol])) {
                $map[$row->trading_symbol] = (int)$row->lot_size;
            }
        }

        return $map;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Resolve lot size for a base symbol from the lot size map.
    // Tries CE trading symbol first, then PE, then falls back to 1.
    // ══════════════════════════════════════════════════════════════════════════

    private function resolveLotSize(
        ?string $ceTradingSymbol,
        ?string $peTradingSymbol,
        array   $lotSizeMap
    ): int {
        if ($ceTradingSymbol && isset($lotSizeMap[$ceTradingSymbol]) && $lotSizeMap[$ceTradingSymbol] > 1) {
            return $lotSizeMap[$ceTradingSymbol];
        }
        if ($peTradingSymbol && isset($lotSizeMap[$peTradingSymbol]) && $lotSizeMap[$peTradingSymbol] > 1) {
            return $lotSizeMap[$peTradingSymbol];
        }
        return 1;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Build one summary row (ALL mode — entry + latest only)
    // ══════════════════════════════════════════════════════════════════════════

    private function buildSummaryRow(
        string $sym,
        array  $def,
        array  $symIndexed,
        string $latest,
        array  $liveLtpMap,
        bool   $isLiveTime,
        array  $lotSizeMap = []
    ): ?array {
        $ceSlots = $symIndexed['CE'][$def['ce_pos']] ?? [];
        $peSlots = $symIndexed['PE'][$def['pe_pos']] ?? [];

        $ceEntry  = $ceSlots[self::ENTRY_SLOT] ?? null;
        $peEntry  = $peSlots[self::ENTRY_SLOT] ?? null;
        $ceLatest = $ceSlots[$latest] ?? null;
        $peLatest = $peSlots[$latest] ?? null;

        $atmStrike   = $ceEntry?->atm_strike ?? $peEntry?->atm_strike ?? null;
        $ceLtpKey    = "{$sym}_CE_{$def['ce_pos']}";
        $peLtpKey    = "{$sym}_PE_{$def['pe_pos']}";
        $cePrice     = $liveLtpMap[$ceLtpKey] ?? ($ceEntry ? round((float)$ceEntry->open, 2) : null);
        $pePrice     = $liveLtpMap[$peLtpKey] ?? ($peEntry ? round((float)$peEntry->open, 2) : null);
        $priceSource = isset($liveLtpMap[$ceLtpKey]) ? 'live' : '09:15_open';

        $ceOiInit = $ceEntry  ? (int)$ceEntry->oi              : null;
        $peOiInit = $peEntry  ? (int)$peEntry->oi              : null;
        $ceCrntOi = $ceLatest ? (int)$ceLatest->oi             : null;
        $peCrntOi = $peLatest ? (int)$peLatest->oi             : null;
        $ceLtp    = $ceLatest ? round((float)$ceLatest->close, 2) : null;
        $peLtp    = $peLatest ? round((float)$peLatest->close, 2) : null;

        $anyRow = $ceEntry ?? $peEntry ?? $ceLatest ?? $peLatest;
        $expiry = $anyRow?->expiry_date ? Carbon::parse($anyRow->expiry_date)->toDateString() : null;

        $cePct = ($ceOiInit && $ceOiInit > 0 && $ceCrntOi !== null)
            ? round((($ceCrntOi - $ceOiInit) / $ceOiInit) * 100, 2) : null;
        $pePct = ($peOiInit && $peOiInit > 0 && $peCrntOi !== null)
            ? round((($peCrntOi - $peOiInit) / $peOiInit) * 100, 2) : null;

        $isShort         = ($def['ce_type'] === 'Sell');
        $strategyPremium = ($cePrice !== null && $pePrice !== null) ? round($cePrice + $pePrice, 2) : null;
        $runningPremium  = ($ceLtp !== null && $peLtp !== null) ? round($ceLtp + $peLtp, 2) : null;

        // Resolve lot size for this symbol
        $ceTradingSymbol = $ceEntry?->trading_symbol ?? $ceLatest?->trading_symbol;
        $peTradingSymbol = $peEntry?->trading_symbol ?? $peLatest?->trading_symbol;
        $lotSize         = $this->resolveLotSize($ceTradingSymbol, $peTradingSymbol, $lotSizeMap);

        $pnl = null;
        if ($strategyPremium !== null && $runningPremium !== null) {
            $pnl = $isShort
                ? round($strategyPremium - $runningPremium, 2)
                : round($runningPremium  - $strategyPremium, 2);
        }

        // P&L per lot
        $pnlLot = $pnl !== null ? round($pnl * $lotSize, 2) : null;

        $oiSignal = ($cePct !== null && $pePct !== null)
            ? $this->calcOiSignal($cePct, $pePct) : $this->noSentiment();

        [$exitFirst, $holdLeg, $remarks] = $this->calcExitDecision($cePct, $pePct);

        return [
            'symbol'                    => $sym,
            'expiry'                    => $expiry,
            'atm_strike'                => $atmStrike,
            'latest_slot'               => $latest,
            'price_source'              => $priceSource,
            'ce_txn_type'               => $def['ce_type'],
            'ce_symbol_name'            => $def['ce_pos'],
            'ce_trading_symbol'         => $ceTradingSymbol,
            'ce_strike'                 => $ceEntry ? (float)$ceEntry->strike : null,
            'ce_price'                  => $cePrice,
            'ce_oi_init'                => $ceOiInit,
            'ce_crnt_oi'                => $ceCrntOi,
            'ce_pct'                    => $cePct,
            'ce_ltp'                    => $ceLtp,
            'pe_txn_type'               => $def['pe_type'],
            'pe_symbol_name'            => $def['pe_pos'],
            'pe_trading_symbol'         => $peTradingSymbol,
            'pe_strike'                 => $peEntry ? (float)$peEntry->strike : null,
            'pe_price'                  => $pePrice,
            'pe_oi_init'                => $peOiInit,
            'pe_crnt_oi'                => $peCrntOi,
            'pe_pct'                    => $pePct,
            'pe_ltp'                    => $peLtp,
            'strategy_combined_premium' => $strategyPremium,
            'running_combined_premium'  => $runningPremium,
            'profit_loss'               => $pnl,
            'profit_loss_lot'           => $pnlLot,
            'lot_size'                  => $lotSize,
            'mkt_sentiment'             => $oiSignal['signal'],
            'oi_condition'              => $oiSignal['condition'],
            'oi_reason'                 => $oiSignal['reason'],
            'oi_strength'               => $oiSignal['strength'],
            'exit_first'                => $exitFirst,
            'hold_leg'                  => $holdLeg,
            'remarks'                   => $remarks,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Live LTP fetch + cache
    // ══════════════════════════════════════════════════════════════════════════

    private function fetchOrCacheLiveLtp(array $symbols, array $def, array $indexed, string $date): array
    {
        $result   = [];
        $toFetch  = [];
        $tokenMap = [];

        foreach ($symbols as $sym) {
            foreach (['CE' => $def['ce_pos'], 'PE' => $def['pe_pos']] as $type => $pos) {
                $cacheKey  = "SS15_LTP_{$date}_{$sym}_{$type}_{$pos}";
                $resultKey = "{$sym}_{$type}_{$pos}";
                $cached    = Cache::get($cacheKey);

                if ($cached !== null) {
                    $result[$resultKey] = (float)$cached;
                } else {
                    $row = $indexed[$sym][$type][$pos][self::ENTRY_SLOT] ?? null;
                    if ($row && $row->instrument_token) {
                        $token = (int)$row->instrument_token;
                        $toFetch[$token]  = $cacheKey;
                        $tokenMap[$token] = [$sym, $type, $pos, $resultKey];
                    }
                }
            }
        }

        if (empty($toFetch)) return $result;

        $broker = BrokerApi::where('client_name', self::BROKER_CLIENT_ID)
            ->zerodha()->validToken()->first();

        if (!$broker) {
            Log::warning('SS15: Broker [' . self::BROKER_CLIENT_ID . '] not found — using 09:15 open');
            return $result;
        }

        try {
            $helper = new BrokerZerodhaHelper($broker);

            // array_chunk WITHOUT preserve_keys so each chunk is a plain 0-indexed
            // array of token integers — iterating by value gives the actual token.
            $tokenChunks = array_chunk(array_keys($toFetch), 200);

            foreach ($tokenChunks as $chunk) {
                // $chunk is [token1, token2, ...] — values are the integer tokens
                $symParams = array_map(fn(int $t): string => "NFO:{$t}", $chunk);

                try {
                    $ltpData = $helper->getLTP($symParams);

                    foreach ($chunk as $token) {
                        $key = "NFO:{$token}";

                        // getLTP may return array or stdClass keyed by "NFO:{token}"
                        $row = null;
                        if (is_array($ltpData) && array_key_exists($key, $ltpData)) {
                            $row = $ltpData[$key];
                        } elseif (is_object($ltpData) && isset($ltpData->{$key})) {
                            $row = $ltpData->{$key};
                        }

                        if ($row === null) continue;

                        // Extract last_price whether $row is stdClass or array
                        $lastPrice = null;
                        if (is_object($row) && isset($row->last_price)) {
                            $lastPrice = $row->last_price;
                        } elseif (is_array($row) && array_key_exists('last_price', $row)) {
                            $lastPrice = $row['last_price'];
                        }

                        if ($lastPrice !== null && (float)$lastPrice > 0) {
                            $ltp       = round((float)$lastPrice, 2);
                            $cacheKey  = $toFetch[$token];
                            $resultKey = $tokenMap[$token][3];
                            Cache::put($cacheKey, $ltp, self::LTP_CACHE_TTL);
                            $result[$resultKey] = $ltp;
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('SS15 LTP chunk failed: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            Log::warning('SS15 broker init failed: ' . $e->getMessage());
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Clear LTP cache
    // ══════════════════════════════════════════════════════════════════════════

    public function clearLtpCache(Request $request)
    {
        $today    = Carbon::today()->toDateString();
        $stratKey = $request->get('strategy', 'long_straddle');
        $def      = self::STRATEGIES[$stratKey] ?? self::STRATEGIES['long_straddle'];

        $symbols = OptionOhlcData::whereDate('trade_date', $today)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->distinct()->pluck('base_symbol')->toArray();

        $cleared = 0;
        foreach ($symbols as $sym) {
            foreach (['CE' => $def['ce_pos'], 'PE' => $def['pe_pos']] as $type => $pos) {
                $k = "SS15_LTP_{$today}_{$sym}_{$type}_{$pos}";
                if (Cache::has($k)) { Cache::forget($k); $cleared++; }
            }
        }
        return response()->json(['success' => true, 'message' => "Cleared {$cleared} LTP cache entries"]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Exit Decision (client OI-based logic)
    // ══════════════════════════════════════════════════════════════════════════

    private function calcExitDecision(?float $cePct, ?float $pePct): array
    {
        if ($cePct === null || $pePct === null) {
            return ['—', '—', 'Insufficient OI data — monitor both legs'];
        }
        if ($cePct > 0 && $pePct < 0) {
            return ['EXIT PE', 'HOLD CE',
                'CE OI rising (+' . $cePct . '%) → Bearish pressure. EXIT PE first. Track CE OI — if CE keeps rising, hold CE leg.'];
        }
        if ($pePct > 0 && $cePct < 0) {
            return ['EXIT CE', 'HOLD PE',
                'PE OI rising (+' . $pePct . '%) → Bullish pressure. EXIT CE first. Track PE OI — if PE keeps rising, hold PE leg.'];
        }
        if ($cePct > 0 && $pePct > 0) {
            return ['EXIT BOTH', 'NONE',
                'Both CE OI (+' . $cePct . '%) & PE OI (+' . $pePct . '%) rising → Writers building both sides. Range market. EXIT BOTH legs.'];
        }
        return ['HOLD BOTH', 'BOTH',
            'Both CE OI (' . $cePct . '%) & PE OI (' . $pePct . '%) falling → Writers unwinding. Volatility expansion. HOLD BOTH — watch which OI builds first then exit opposite leg.'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OI Signal
    // ══════════════════════════════════════════════════════════════════════════

    private function calcOiSignal(float $cePct, float $pePct): array
    {
        if ($cePct > 0 && $pePct < 0)      { $s='BEARISH'; $c='CE↑ PE↓'; $r='Call buildup + Put unwinding → Resistance'; }
        elseif ($cePct < 0 && $pePct > 0)  { $s='BULLISH'; $c='CE↓ PE↑'; $r='Call unwinding + Put buildup → Support'; }
        elseif ($cePct > 0 && $pePct > 0)  {
            $s = $pePct > $cePct ? 'BULLISH' : 'BEARISH';
            $c = $pePct > $cePct ? 'Both↑ PE>CE' : 'Both↑ CE≥PE';
            $r = $pePct > $cePct ? 'PE buildup stronger → Bullish' : 'CE buildup stronger → Bearish';
        } else {
            $s = abs($cePct) > abs($pePct) ? 'BULLISH' : 'BEARISH';
            $c = abs($cePct) > abs($pePct) ? 'Both↓ |CE|>|PE|' : 'Both↓ |PE|≥|CE|';
            $r = abs($cePct) > abs($pePct) ? 'CE unwind larger → Bullish' : 'PE unwind larger → Bearish';
        }
        $d = round(abs($cePct - $pePct), 2);
        $st = $d > 3 ? 'Very Strong' : ($d > 1.5 ? 'Strong' : ($d > 0.5 ? 'Moderate' : 'Weak'));
        return ['signal' => $s, 'condition' => $c, 'reason' => $r, 'strength' => $st, 'diff' => $d];
    }

    private function noSentiment(): array
    {
        return ['signal' => 'N/A', 'condition' => '—', 'reason' => '—', 'strength' => '—', 'diff' => 0];
    }

    private function emptyJson(string $today, string $msg, array $syms = [], string $symbol = 'ALL'): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true, 'data' => [], 'today' => $today,
            'is_today' => $today === Carbon::today()->toDateString(),
            'message' => $msg, 'available_symbols' => $syms, 'symbol' => $symbol,
        ]);
    }
}