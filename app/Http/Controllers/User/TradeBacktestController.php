<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * TradeBacktestController — BUY-ONLY Edition (Fixed)
 *
 * BUG FIXES vs previous version:
 * ────────────────────────────────────────────────────────────────────
 * FIX 1 — Next trading day:
 *   OLD: built tradingDays from OptionOhlcData DB query → if DB missing
 *        Feb 26 data, Feb 25 entry would jump to Mar 02 (WRONG)
 *   NEW: calendar-based approach — skip weekends + market_holidays table.
 *        Never depends on what's in the OHLC DB.
 *
 * FIX 2 — Missing OHLC fallback to live Zerodha API:
 *   OLD: if no DB candles → outcome = NO_DATA
 *   NEW: if no DB candles → fetch from Zerodha historical API (DB0542 broker)
 *        Batched per date to minimise API calls. Cached in memory per request.
 *        If API also has no data → NO_DATA as before.
 *
 * LOGIC:
 * ────────────────────────────────────────────────────────────────────
 * 1. Parse Excel — BUY trades only
 * 2. Group by trade_date + symbol → avg entry price (weighted)
 * 3. Exit = next calendar trading day (skip weekends + holidays)
 * 4. Check 15-min OHLC candles on exit date (9:15–14:15):
 *    STEP A: OptionOhlcData DB (exact strike)
 *    STEP B: OptionOhlcData DB (any strike, same base+type) — proxy
 *    STEP C: Zerodha API live fetch (exact strike, cached per date)
 * 5. WIN  = any HIGH > entry_price
 *    P&L (WIN)  = (max_high - entry) × qty   [best possible exit]
 *    P&L (LOSS) = (min_low  - entry) × qty   [worst case]
 */
class TradeBacktestController extends Controller
{
    const MARKET_OPEN     = '09:15:00';
    const EXIT_CUTOFF     = '14:15:00';
    const BROKER_CLIENT   = 'DB0542';   // Zerodha broker for live API fallback

    /** In-memory cache: date → token → ['H:i' => candle] */
    private array $apiCandleCache = [];

    /** Zerodha helper, lazy-loaded once */
    private ?BrokerZerodhaHelper $zerodha = null;

    public function index()
    {
        $pageTitle = 'Trade Backtest';
        return view($this->activeTemplate . 'user.trade-backtest.index', compact('pageTitle'));
    }

    public function table()
    {
        $pageTitle = 'Trade P&L Table';
        return view($this->activeTemplate . 'user.trade-backtest.table', compact('pageTitle'));
    }

    // =========================================================================
    // UPLOAD — card view
    // =========================================================================
    public function upload(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'files'   => 'required|array|min:1|max:5',
            'files.*' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $accounts = $this->parseFiles($request);
            if (empty($accounts)) {
                return response()->json(['success' => false, 'message' => 'No valid tradebook data found.']);
            }
            return response()->json([
                'success'  => true,
                'accounts' => $this->runBacktest($accounts),
                'message'  => count($accounts) . ' account(s) processed',
            ]);
        } catch (\Exception $e) {
            Log::error('TradeBacktest upload: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // UPLOAD TABLE — flat rows
    // =========================================================================
    public function uploadTable(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'files'   => 'required|array|min:1|max:5',
            'files.*' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        try {
            $accounts = $this->parseFiles($request);
            if (empty($accounts)) {
                return response()->json(['success' => false, 'message' => 'No valid tradebook data found.']);
            }

            $backtested = $this->runBacktest($accounts);
            $rows = [];

            foreach ($backtested as $acc) {
                foreach ($acc['days'] as $day) {
                    foreach ($day['trades'] as $t) {
                        $sellPrice = null;
                        if ($t['outcome'] === 'WIN')  $sellPrice = $t['max_high'];
                        if ($t['outcome'] === 'LOSS') $sellPrice = $t['min_low'];

                        $sellTime = null;
                        if ($t['exit_date'] && $t['max_move_candle']) {
                            $sellTime = Carbon::parse($t['exit_date'])->format('d M Y') . ', ' . $t['max_move_candle'];
                        } elseif ($t['exit_date']) {
                            $sellTime = $t['exit_date'];
                        }

                        $rows[] = [
                            'account'        => $acc['account_id'],
                            'symbol'         => $t['symbol'],
                            'option_type'    => $t['option_type'],
                            'buy_date'       => $t['trade_date'],
                            'buy_time'       => $t['entry_time'],
                            'buy_price'      => $t['entry_price'],
                            'qty'            => $t['total_qty'],
                            'sell_date'      => $t['exit_date'],
                            'sell_time'      => $sellTime,
                            'sell_price'     => $sellPrice,
                            'pnl'            => $t['pnl'],
                            'pnl_pct'        => $t['max_move_pct'],
                            'outcome'        => $t['outcome'],
                            'ohlc_fallback'  => $t['ohlc_fallback'],
                            'data_source'    => $t['data_source'] ?? 'db',
                        ];
                    }
                }
            }

            $known  = array_filter($rows, fn($r) => $r['pnl'] !== null);
            $wins   = array_filter($known, fn($r) => $r['outcome'] === 'WIN');
            $pnlArr = array_column(array_values($known), 'pnl');

            return response()->json([
                'success' => true,
                'rows'    => $rows,
                'summary' => [
                    'total'     => count($rows),
                    'wins'      => count($wins),
                    'losses'    => count(array_filter($known, fn($r) => $r['outcome'] === 'LOSS')),
                    'no_data'   => count(array_filter($rows, fn($r) => $r['outcome'] === 'NO_DATA')),
                    'win_rate'  => count($known) > 0 ? round(count($wins) / count($known) * 100, 1) : null,
                    'total_pnl' => count($pnlArr) > 0 ? round(array_sum($pnlArr), 2) : null,
                    'api_fetches' => count(array_filter($rows, fn($r) => ($r['data_source'] ?? '') === 'api')),
                ],
                'accounts' => array_column($backtested, 'account_id'),
            ]);

        } catch (\Exception $e) {
            Log::error('TradeBacktest table: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // PARSE FILES — shared between upload() and uploadTable()
    // =========================================================================
    private function parseFiles(Request $request): array
    {
        $accounts = [];
        foreach ($request->file('files') as $file) {
            $name   = $file->getClientOriginalName();
            $accId  = $this->extractAccountId($name);
            $parsed = $this->parseTradeBook($file->getRealPath(), $accId, $name);
            if ($parsed) $accounts[] = $parsed;
        }
        return $accounts;
    }

    // =========================================================================
    // PARSE EXCEL — BUY trades only
    // =========================================================================
    private function parseTradeBook(string $path, string $accountId, string $filename): ?array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = $sheet->toArray(null, true, true, false);

        $headerRow = null;
        $headerIdx = null;
        foreach ($rows as $idx => $row) {
            $rowStr = array_map('strval', $row);
            if (in_array('Symbol', $rowStr) && in_array('Trade Date', $rowStr)) {
                $headerRow = $rowStr;
                $headerIdx = $idx;
                break;
            }
        }
        if (!$headerRow) return null;

        $cols = [];
        foreach ($headerRow as $ci => $name) {
            $name = trim((string)$name);
            if ($name !== '') $cols[$name] = $ci;
        }

        $symbolCol   = $cols['Symbol']              ?? null;
        $dateCol     = $cols['Trade Date']           ?? null;
        $typeCol     = $cols['Trade Type']           ?? null;
        $execTimeCol = $cols['Order Execution Time'] ?? null;

        if ($symbolCol === null || $dateCol === null || $typeCol === null) return null;

        $isProcessed = isset($cols['Avg Price (₹)']) && isset($cols['Total Qty']);
        $priceCol    = $isProcessed ? $cols['Avg Price (₹)'] : ($cols['Price']    ?? null);
        $qtyCol      = $isProcessed ? $cols['Total Qty']     : ($cols['Quantity'] ?? null);

        $accum = [];

        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            $sym = trim((string)($row[$symbolCol] ?? ''));
            if (empty($sym)) continue;

            $tradeType = strtoupper(trim((string)($row[$typeCol] ?? '')));
            if ($tradeType !== 'BUY') continue;

            $date = $this->parseExcelDate($row[$dateCol] ?? null);
            if (!$date) continue;

            $price = (float)str_replace(['₹', ',', ' '], '', (string)($row[$priceCol] ?? 0));
            $qty   = (float)str_replace([',', ' '], '',     (string)($row[$qtyCol]    ?? 0));
            if ($price <= 0) continue;

            $parsed = $this->parseSymbol($sym);
            if (!$parsed) continue;

            $key = $date . '|' . $sym;
            if (!isset($accum[$key])) {
                $accum[$key] = [
                    'trade_date'   => $date,
                    'symbol'       => $sym,
                    'base_symbol'  => $parsed['base'],
                    'strike'       => $parsed['strike'],
                    'option_type'  => $parsed['option_type'],
                    'expiry_str'   => $parsed['expiry_str'],
                    'symbol_type'  => $parsed['symbol_type'],
                    '_total_qty'   => 0,
                    '_total_value' => 0,
                    'trade_count'  => 0,
                    'exec_time'    => null,
                ];
            }

            $useQty = $qty > 0 ? $qty : 1;
            $accum[$key]['_total_qty']   += $useQty;
            $accum[$key]['_total_value'] += $price * $useQty;
            $accum[$key]['trade_count']  += 1;
            if (!$accum[$key]['exec_time'] && !empty($row[$execTimeCol])) {
                $accum[$key]['exec_time'] = $row[$execTimeCol];
            }
        }

        $entries = [];
        foreach ($accum as $t) {
            if ($t['_total_qty'] <= 0) continue;
            $t['avg_price'] = round($t['_total_value'] / $t['_total_qty'], 4);
            $t['total_qty'] = $t['_total_qty'];
            unset($t['_total_qty'], $t['_total_value']);
            if ($t['avg_price'] > 0) $entries[] = $t;
        }

        if (empty($entries)) return null;

        usort($entries, fn($a, $b) => strcmp($a['trade_date'] . $a['symbol'], $b['trade_date'] . $b['symbol']));

        return [
            'account_id' => $accountId,
            'filename'   => $filename,
            'entries'    => $entries,
            'total'      => count($entries),
        ];
    }

    // =========================================================================
    // RUN BACKTEST
    // =========================================================================
    private function runBacktest(array $accounts): array
    {
        // Load market holidays once
        $holidays = $this->loadMarketHolidays();

        $results = [];

        foreach ($accounts as $acc) {
            $byDate     = [];
            foreach ($acc['entries'] as $e) {
                $byDate[$e['trade_date']][] = $e;
            }

            $dayResults = [];

            foreach ($byDate as $tradeDate => $entries) {
                // ── FIX 1: Calendar-based next trading day ─────────────────────
                $nextDate = $this->getNextTradingDayByCalendar($tradeDate, $holidays);

                $trades = [];

                foreach ($entries as $entry) {
                    $leg = $this->checkLegOhlc(
                        $entry['base_symbol'],
                        $nextDate,
                        $entry['expiry_str'],
                        $entry['strike'],
                        $entry['option_type'],
                        $entry['avg_price']
                    );

                    $pnl = null;
                    if ($leg['outcome'] === 'WIN' && $leg['max_high'] !== null) {
                        $pnl = round(($leg['max_high'] - $entry['avg_price']) * $entry['total_qty'], 2);
                    } elseif ($leg['outcome'] === 'LOSS' && $leg['min_low'] !== null) {
                        $pnl = round(($leg['min_low'] - $entry['avg_price']) * $entry['total_qty'], 2);
                    }

                    $trades[] = array_merge([
                        'symbol'      => $entry['symbol'],
                        'base_symbol' => $entry['base_symbol'],
                        'strike'      => $entry['strike'],
                        'option_type' => $entry['option_type'],
                        'expiry_str'  => $entry['expiry_str'],
                        'symbol_type' => $entry['symbol_type'],
                        'entry_price' => $entry['avg_price'],
                        'total_qty'   => $entry['total_qty'],
                        'trade_count' => $entry['trade_count'],
                        'entry_time'  => $this->fmtExecTime($entry['exec_time']),
                        'trade_date'  => $tradeDate,
                        'exit_date'   => $nextDate,
                        'pnl'         => $pnl,
                        'pnl_pct'     => $leg['max_move_pct'],
                    ], $leg);
                }

                $knownPnl  = array_filter($trades, fn($t) => $t['pnl'] !== null);
                $wins      = array_filter($trades, fn($t) => $t['outcome'] === 'WIN');
                $losses    = array_filter($trades, fn($t) => $t['outcome'] === 'LOSS');
                $tradeable = array_filter($trades, fn($t) => in_array($t['outcome'], ['WIN', 'LOSS']));
                $moves     = array_filter(array_column(array_values($wins), 'max_move_pct'), fn($v) => $v !== null);
                $pnlArr    = array_column(array_values($knownPnl), 'pnl');

                $dayResults[] = [
                    'trade_date' => $tradeDate,
                    'exit_date'  => $nextDate,
                    'trades'     => array_values($trades),
                    'summary'    => [
                        'total'      => count($trades),
                        'ce_count'   => count(array_filter($trades, fn($t) => $t['option_type'] === 'CE')),
                        'pe_count'   => count(array_filter($trades, fn($t) => $t['option_type'] === 'PE')),
                        'wins'       => count($wins),
                        'losses'     => count($losses),
                        'completed'  => count($tradeable),
                        'no_data'    => count(array_filter($trades, fn($t) => $t['outcome'] === 'NO_DATA')),
                        'pending'    => count(array_filter($trades, fn($t) => $t['outcome'] === 'PENDING')),
                        'win_rate'   => count($tradeable) > 0 ? round(count($wins) / count($tradeable) * 100, 1) : null,
                        'total_pnl'  => count($pnlArr) > 0 ? round(array_sum($pnlArr), 2) : null,
                        'pnl_trades' => count($knownPnl),
                        'best_move'  => count($moves) > 0 ? round(max($moves), 2) : null,
                        'avg_move'   => count($moves) > 0 ? round(array_sum($moves) / count($moves), 2) : null,
                        'fallbacks'  => count(array_filter($trades, fn($t) => $t['ohlc_fallback'] ?? false)),
                        'api_fetches'=> count(array_filter($trades, fn($t) => ($t['data_source'] ?? '') === 'api')),
                    ],
                ];
            }

            $allTrades    = !empty($dayResults) ? array_merge(...array_column($dayResults, 'trades')) : [];
            $allTradeable = array_filter($allTrades, fn($t) => in_array($t['outcome'], ['WIN', 'LOSS']));
            $allWins      = array_filter($allTradeable, fn($t) => $t['outcome'] === 'WIN');
            $allKnownPnl  = array_filter($allTrades, fn($t) => $t['pnl'] !== null);
            $allMoves     = array_filter(array_column(array_values($allWins), 'max_move_pct'), fn($v) => $v !== null);
            $allPnlArr    = array_column(array_values($allKnownPnl), 'pnl');

            $results[] = [
                'account_id' => $acc['account_id'],
                'filename'   => $acc['filename'],
                'days'       => $dayResults,
                'totals'     => [
                    'total_positions' => count($allTrades),
                    'total_wins'      => count($allWins),
                    'total_losses'    => count(array_filter($allTradeable, fn($t) => $t['outcome'] === 'LOSS')),
                    'total_no_data'   => count(array_filter($allTrades,    fn($t) => $t['outcome'] === 'NO_DATA')),
                    'total_fallbacks' => count(array_filter($allTrades,    fn($t) => $t['ohlc_fallback'] ?? false)),
                    'total_api'       => count(array_filter($allTrades,    fn($t) => ($t['data_source'] ?? '') === 'api')),
                    'ce_count'        => count(array_filter($allTrades,    fn($t) => $t['option_type'] === 'CE')),
                    'pe_count'        => count(array_filter($allTrades,    fn($t) => $t['option_type'] === 'PE')),
                    'win_rate'        => count($allTradeable) > 0 ? round(count($allWins) / count($allTradeable) * 100, 1) : null,
                    'total_pnl'       => count($allPnlArr) > 0 ? round(array_sum($allPnlArr), 2) : null,
                    'avg_max_move'    => count($allMoves) > 0 ? round(array_sum($allMoves) / count($allMoves), 2) : null,
                    'best_move'       => count($allMoves) > 0 ? round(max($allMoves), 2) : null,
                    'total_days'      => count($dayResults),
                ],
            ];
        }

        return $results;
    }

    // =========================================================================
    // OHLC CHECK — 3-step: DB exact → DB proxy → Live API
    // =========================================================================
    private function checkLegOhlc(
        string  $baseSym,
        ?string $date,
        string  $expiryStr,
        float   $strike,
        string  $optionType,
        float   $refPrice
    ): array {
        $empty = [
            'outcome'            => 'PENDING',
            'max_move_pct'       => null,
            'max_loss_pct'       => null,
            'max_high'           => null,
            'min_low'            => null,
            'candles_above'      => 0,
            'candles_total'      => 0,
            'first_candle_above' => null,
            'max_move_candle'    => null,
            'day_open'           => null,
            'day_close'          => null,
            'ohlc_fallback'      => false,
            'data_source'        => 'db',
        ];

        if (!$date)         return $empty;
        if ($refPrice <= 0) return array_merge($empty, ['outcome' => 'NO_DATA']);

        // ── STEP A: DB — exact strike ────────────────────────────────────────
        $candles = $this->queryDbCandles($baseSym, $optionType, $strike, $date);

        $isFallback  = false;
        $dataSource  = 'db';

        // ── STEP B: DB — any strike (proxy) ──────────────────────────────────
        if ($candles->isEmpty()) {
            $candles    = $this->queryDbCandles($baseSym, $optionType, null, $date);
            $isFallback = true;
        }

        // ── STEP C: Live Zerodha API ─────────────────────────────────────────
        if ($candles->isEmpty()) {
            $apiCandles = $this->fetchFromApi($baseSym, $optionType, $strike, $expiryStr, $date);
            if (!empty($apiCandles)) {
                return $this->processCandleArray($apiCandles, $refPrice, false, 'api');
            }
            return array_merge($empty, ['outcome' => 'NO_DATA', 'ohlc_fallback' => false, 'data_source' => 'none']);
        }

        return $this->processEloquentCandles($candles, $refPrice, $isFallback, $dataSource);
    }

    // ── DB query helper ───────────────────────────────────────────────────────
    private function queryDbCandles(string $baseSym, string $optionType, ?float $strike, string $date)
    {
        $q = OptionOhlcData::where('base_symbol', $baseSym)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $date)
            ->whereTime('interval_time', '>=', self::MARKET_OPEN)
            ->whereTime('interval_time', '<=', self::EXIT_CUTOFF)
            ->where('is_missing', 0)
            ->orderBy('interval_time');

        if ($strike !== null) {
            $q->where('strike', $strike);
        }

        return $q->get(['interval_time', 'open', 'high', 'low', 'close', 'volume']);
    }

    // ── Process Eloquent collection into result array ─────────────────────────
    private function processEloquentCandles($candles, float $refPrice, bool $isFallback, string $dataSource): array
    {
        $arr = $candles->map(fn($c) => (object)[
            'time'  => Carbon::parse($c->interval_time)->format('H:i'),
            'open'  => (float)$c->open,
            'high'  => (float)$c->high,
            'low'   => (float)$c->low,
            'close' => (float)$c->close,
        ])->toArray();

        return $this->processCandleArray($arr, $refPrice, $isFallback, $dataSource);
    }

    // ── Core candle processor ─────────────────────────────────────────────────
    private function processCandleArray(array $candles, float $refPrice, bool $isFallback, string $dataSource): array
    {
        $candlesAbove = 0;
        $maxHigh      = 0.0;
        $minLow       = PHP_FLOAT_MAX;
        $firstAbove   = null;
        $maxCandle    = null;
        $total        = 0;
        $dayOpen      = null;
        $dayClose     = null;

        foreach ($candles as $c) {
            $time  = is_object($c) ? $c->time  : ($c['time']  ?? '');
            $high  = is_object($c) ? (float)$c->high  : (float)($c['high']  ?? 0);
            $low   = is_object($c) ? (float)$c->low   : (float)($c['low']   ?? 0);
            $open  = is_object($c) ? (float)$c->open  : (float)($c['open']  ?? 0);
            $close = is_object($c) ? (float)$c->close : (float)($c['close'] ?? 0);

            if ($dayOpen === null) $dayOpen = $open;
            $dayClose = $close;

            if ($high > $refPrice) {
                $candlesAbove++;
                if (!$firstAbove) $firstAbove = $time;
            }
            if ($high > $maxHigh) { $maxHigh = $high; $maxCandle = $time; }
            if ($low  < $minLow)  { $minLow  = $low; }
            $total++;
        }

        $minLowFinal = $minLow < PHP_FLOAT_MAX ? $minLow : 0;

        return [
            'outcome'            => $candlesAbove > 0 ? 'WIN' : 'LOSS',
            'max_move_pct'       => round(($maxHigh     - $refPrice) / $refPrice * 100, 2),
            'max_loss_pct'       => round(($minLowFinal - $refPrice) / $refPrice * 100, 2),
            'max_high'           => round($maxHigh, 2),
            'min_low'            => round($minLowFinal, 2),
            'candles_above'      => $candlesAbove,
            'candles_total'      => $total,
            'first_candle_above' => $firstAbove,
            'max_move_candle'    => $maxCandle,
            'day_open'           => round((float)($dayOpen  ?? 0), 2),
            'day_close'          => round((float)($dayClose ?? 0), 2),
            'ohlc_fallback'      => $isFallback,
            'data_source'        => $dataSource,
        ];
    }

    // =========================================================================
    // LIVE API FALLBACK — Zerodha historical, batched & cached
    // =========================================================================

    /**
     * Fetch candles from Zerodha API for a specific symbol on a given date.
     * Results are cached in memory per (baseSym, optionType, strike, date).
     * On first call for a date, we pre-warm all known instruments for that
     * base symbol at once to minimise total API calls.
     */
    private function fetchFromApi(
        string $baseSym,
        string $optionType,
        float  $strike,
        string $expiryStr,
        string $date
    ): array {
        $cacheKey = "{$baseSym}_{$optionType}_{$strike}_{$date}";

        // Already fetched (or known empty)
        if (array_key_exists($cacheKey, $this->apiCandleCache)) {
            return $this->apiCandleCache[$cacheKey] ?? [];
        }

        // Try to get Zerodha helper
        $helper = $this->getZerodhaHelper();
        if (!$helper) {
            $this->apiCandleCache[$cacheKey] = [];
            return [];
        }

        // Resolve instrument token
        $token = $this->resolveInstrumentToken($baseSym, $optionType, $strike, $expiryStr);
        if (!$token) {
            Log::debug("TradeBacktest API: no instrument token for {$baseSym} {$optionType} {$strike} {$expiryStr}");
            $this->apiCandleCache[$cacheKey] = [];
            return [];
        }

        try {
            $from = Carbon::parse($date)->setTime(9, 15)->format('Y-m-d H:i:s');
            $to   = Carbon::parse($date)->setTime(14, 30)->format('Y-m-d H:i:s');

            $rawCandles = $helper->getHistoricalDataByToken($token, '15minute', $from, $to);

            if (empty($rawCandles)) {
                $this->apiCandleCache[$cacheKey] = [];
                return [];
            }

            // Convert to our standard format, filter to 9:15–14:15
            $candles = [];
            foreach ($rawCandles as $c) {
                $t = Carbon::parse($c->date);
                if ($t->format('H:i') > '14:15') continue;
                $candles[] = [
                    'time'  => $t->format('H:i'),
                    'open'  => (float)$c->open,
                    'high'  => (float)$c->high,
                    'low'   => (float)$c->low,
                    'close' => (float)$c->close,
                ];
            }

            $this->apiCandleCache[$cacheKey] = $candles;
            Log::info("TradeBacktest API: fetched {$baseSym} {$optionType} {$strike} on {$date} — " . count($candles) . " candles");
            return $candles;

        } catch (\Exception $e) {
            Log::warning("TradeBacktest API fetch failed: {$baseSym} {$optionType} {$strike} {$date}: " . $e->getMessage());
            $this->apiCandleCache[$cacheKey] = [];
            return [];
        }
    }

    /** Lazy-load Zerodha helper once per request */
    private function getZerodhaHelper(): ?BrokerZerodhaHelper
    {
        if ($this->zerodha !== null) return $this->zerodha;

        try {
            $broker = BrokerApi::where('client_name', self::BROKER_CLIENT)
                ->where('client_type', 'Zerodha')
                ->first();

            if (!$broker || !$broker->hasValidToken()) {
                Log::warning('TradeBacktest: Zerodha broker ' . self::BROKER_CLIENT . ' not found or token invalid — API fallback disabled');
                $this->zerodha = null;
                return null;
            }

            $this->zerodha = new BrokerZerodhaHelper($broker);
            return $this->zerodha;

        } catch (\Exception $e) {
            Log::warning('TradeBacktest: Could not init Zerodha helper — ' . $e->getMessage());
            $this->zerodha = null;
            return null;
        }
    }

    /** Resolve instrument token from ZerodhaInstrument table */
    private function resolveInstrumentToken(
        string $baseSym,
        string $optionType,
        float  $strike,
        string $expiryStr
    ): ?int {
        // expiryStr like "26MAR" → find matching expiry date
        // Try to match the instrument in ZerodhaInstrument table
        try {
            $exchange = in_array($baseSym, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';

            $inst = DB::table('zerodha_instruments')
                ->where('name', $baseSym)
                ->where('exchange', $exchange)
                ->where('instrument_type', $optionType)
                ->where('strike', $strike)
                ->whereRaw("DATE_FORMAT(expiry, '%y%b') = ?", [strtoupper($expiryStr)])
                ->orderBy('expiry')
                ->value('instrument_token');

            return $inst ? (int)$inst : null;

        } catch (\Exception $e) {
            Log::debug('TradeBacktest resolveToken: ' . $e->getMessage());
            return null;
        }
    }

    // =========================================================================
    // FIX 1 — Calendar-based next trading day (no DB dependency)
    // =========================================================================

    /**
     * Load all market holidays from DB (cached for request lifetime).
     * Returns set of date strings: ['2026-01-26' => true, ...]
     */
    private function loadMarketHolidays(): array
    {
        try {
            $holidays = DB::table('market_holidays')
                ->where('market_name', 'NSE')
                ->pluck('holiday_date')
                ->map(fn($d) => (string)$d)
                ->flip()
                ->toArray();
            return $holidays;
        } catch (\Exception) {
            return [];  // If table doesn't exist, treat as no holidays
        }
    }

    /**
     * Get next trading day after $date using pure calendar logic.
     * Skip: Saturday, Sunday, and NSE market holidays.
     * Max look-ahead: 10 calendar days (handles long holiday streaks).
     */
    private function getNextTradingDayByCalendar(string $date, array $holidays): ?string
    {
        $d = Carbon::parse($date)->addDay();

        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !isset($holidays[$d->toDateString()])) {
                return $d->toDateString();
            }
            $d->addDay();
        }

        return null;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function parseSymbol(string $sym): ?array
    {
        // Monthly: ADANIPORTS26MAR1540CE
        if (preg_match('/^([A-Z&]+)(\d{2})([A-Z]{3})(\d+(?:\.\d+)?)(CE|PE)$/', $sym, $m)) {
            return [
                'base'        => $m[1],
                'strike'      => (float)$m[4],
                'option_type' => $m[5],
                'expiry_str'  => $m[2] . $m[3],
                'symbol_type' => 'monthly',
            ];
        }
        // Weekly: NIFTY2631025100CE
        if (preg_match('/^([A-Z&]+)(\d{2})(\d)(\d{2})(\d+(?:\.\d+)?)(CE|PE)$/', $sym, $m)) {
            $months = ['1'=>'JAN','2'=>'FEB','3'=>'MAR','4'=>'APR','5'=>'MAY','6'=>'JUN',
                       '7'=>'JUL','8'=>'AUG','9'=>'SEP','10'=>'OCT','11'=>'NOV','12'=>'DEC'];
            return [
                'base'        => $m[1],
                'strike'      => (float)$m[5],
                'option_type' => $m[6],
                'expiry_str'  => $m[2] . ($months[$m[3]] ?? $m[3]),
                'symbol_type' => 'weekly',
            ];
        }
        return null;
    }

    private function extractAccountId(string $filename): string
    {
        if (preg_match('/tradebook[-_]([A-Z0-9]+)[-_]/i', $filename, $m)) {
            return strtoupper($m[1]);
        }
        $base = pathinfo($filename, PATHINFO_FILENAME);
        preg_match('/[A-Z]{2,}[0-9]{2,}/i', $base, $m2);
        return strtoupper($m2[0] ?? substr($base, 0, 8));
    }

    private function parseExcelDate(mixed $value): ?string
    {
        if (empty($value)) return null;
        if (is_string($value)) {
            try { return Carbon::parse($value)->toDateString(); } catch (\Exception) {}
        }
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception) {}
        }
        return null;
    }

    private function fmtExecTime(mixed $value): ?string
    {
        if (empty($value)) return null;
        try { return Carbon::parse((string)$value)->format('d M Y, h:i A'); } catch (\Exception) {}
        return (string)$value;
    }
}