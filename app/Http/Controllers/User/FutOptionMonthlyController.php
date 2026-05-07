<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use App\Models\BrokerApi;
use App\Helpers\BrokerZerodhaHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * FutOptionMonthlyController
 *
 * Monthly P&L Dashboard for FUT + Option Sell Strategy.
 *
 * ── Investment per trade ──────────────────────────────────────────────────
 *   investment = (fut_position_price × lot_size × 1)   ← FUT notional (1 lot)
 *              + (opt_position_price × lot_size × 2)   ← premium collected (2 lots)
 *
 * ── FUT Margin per trade ──────────────────────────────────────────────────
 *   Fetched live from Zerodha basket-margin API:
 *     POST /margins/orders  →  response.data.final.total
 *   Cached in-memory per trading_symbol to avoid duplicate API calls.
 *   Falls back to 0 if broker unavailable or API fails.
 *
 * Both values roll up into day → month → summary totals.
 */
class FutOptionMonthlyController extends Controller
{
    private const EXIT_FROM = '09:15:00';
    private const EXIT_TO   = '10:30:00';

    /** In-memory margin cache: key = trading_symbol → float */
    private array $marginCache = [];

    /** Zerodha helper (lazy-booted once per request) */
    private ?BrokerZerodhaHelper $zerodha = null;

    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Monthly P&L Dashboard — FUT + Option Sell';
        return view($this->activeTemplate . 'user.fut-option-strategy.monthly', compact('pageTitle'));
    }

    // =========================================================
    //  MAIN AJAX ENDPOINT
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both dates.',
                    'data'    => [],
                ]);
            }

            // Boot Zerodha once (margin API). Silently skips if no valid broker.
            $this->bootZerodha();

            // All distinct signal dates in range
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            $allRows = [];

            foreach ($tradeDates as $signalDate) {
                $prevDate = $this->getPreviousTradingDate($signalDate);
                $nextDate = $this->getNextTradingDate($signalDate);

                foreach ($this->buildRowsForDate($signalDate, $prevDate, $nextDate, $selectedSymbols) as $row) {
                    $allRows[] = $row;
                }
            }

            // Active trades only (exclude WAIT)
            $activeRows = array_filter($allRows, fn($r) => $r['trade_action'] !== 'WAIT');

            // Group: Month → Day → trades[]
            $monthlyGroups = [];
            foreach ($activeRows as $row) {
                $month = substr($row['signal_date'], 0, 7);   // "YYYY-MM"
                $day   = $row['signal_date'];
                $monthlyGroups[$month][$day][] = $row;
            }

            $months = [];

            foreach ($monthlyGroups as $month => $days) {
                $monthTrades     = 0;
                $monthFutPL      = 0;
                $monthOptPL      = 0;
                $monthCombPL     = 0;
                $monthWins       = 0;
                $monthLosses     = 0;
                $monthBullish    = 0;
                $monthBearish    = 0;
                $monthInvestment = 0;
                $monthFutMargin  = 0;

                $dayRows = [];

                foreach ($days as $day => $trades) {
                    $dayFutPL      = array_sum(array_column($trades, 'fut_pl'));
                    $dayOptPL      = array_sum(array_column($trades, 'opt_pl'));
                    $dayCombPL     = array_sum(array_column($trades, 'combined_pl'));
                    $dayCount      = count($trades);
                    $dayWins       = count(array_filter($trades, fn($t) => ($t['combined_pl'] ?? 0) > 0));
                    $dayLosses     = count(array_filter($trades, fn($t) => ($t['combined_pl'] ?? 0) < 0));
                    $dayBull       = count(array_filter($trades, fn($t) => $t['oi_signal'] === 'BULLISH'));
                    $dayBear       = count(array_filter($trades, fn($t) => $t['oi_signal'] === 'BEARISH'));
                    $dayInvestment = array_sum(array_column($trades, 'investment'));
                    $dayFutMargin  = array_sum(array_column($trades, 'fut_margin'));

                    $dayRows[] = [
                        'date'        => $day,
                        'day_name'    => Carbon::parse($day)->format('D, d M'),
                        'trades'      => $dayCount,
                        'bullish'     => $dayBull,
                        'bearish'     => $dayBear,
                        'fut_pl'      => round($dayFutPL, 2),
                        'opt_pl'      => round($dayOptPL, 2),
                        'combined_pl' => round($dayCombPL, 2),
                        'wins'        => $dayWins,
                        'losses'      => $dayLosses,
                        'win_rate'    => $dayCount > 0 ? round($dayWins / $dayCount * 100, 1) : 0,
                        'investment'  => round($dayInvestment, 2),
                        'fut_margin'  => round($dayFutMargin, 2),
                    ];

                    $monthTrades     += $dayCount;
                    $monthFutPL      += $dayFutPL;
                    $monthOptPL      += $dayOptPL;
                    $monthCombPL     += $dayCombPL;
                    $monthWins       += $dayWins;
                    $monthLosses     += $dayLosses;
                    $monthBullish    += $dayBull;
                    $monthBearish    += $dayBear;
                    $monthInvestment += $dayInvestment;
                    $monthFutMargin  += $dayFutMargin;
                }

                usort($dayRows, fn($a, $b) => $a['date'] <=> $b['date']);

                $months[] = [
                    'month'       => $month,
                    'month_label' => Carbon::parse($month . '-01')->format('F Y'),
                    'trades'      => $monthTrades,
                    'bullish'     => $monthBullish,
                    'bearish'     => $monthBearish,
                    'fut_pl'      => round($monthFutPL, 2),
                    'opt_pl'      => round($monthOptPL, 2),
                    'combined_pl' => round($monthCombPL, 2),
                    'wins'        => $monthWins,
                    'losses'      => $monthLosses,
                    'win_rate'    => $monthTrades > 0 ? round($monthWins / $monthTrades * 100, 1) : 0,
                    'investment'  => round($monthInvestment, 2),
                    'fut_margin'  => round($monthFutMargin, 2),
                    'days'        => $dayRows,
                ];
            }

            usort($months, fn($a, $b) => $a['month'] <=> $b['month']);

            // Grand summary
            $totalTrades     = array_sum(array_column($months, 'trades'));
            $totalFutPL      = array_sum(array_column($months, 'fut_pl'));
            $totalOptPL      = array_sum(array_column($months, 'opt_pl'));
            $totalCombPL     = array_sum(array_column($months, 'combined_pl'));
            $totalWins       = array_sum(array_column($months, 'wins'));
            $totalLosses     = array_sum(array_column($months, 'losses'));
            $totalBull       = array_sum(array_column($months, 'bullish'));
            $totalBear       = array_sum(array_column($months, 'bearish'));
            $totalInvestment = array_sum(array_column($months, 'investment'));
            $totalFutMargin  = array_sum(array_column($months, 'fut_margin'));

            $summary = [
                'trades'      => $totalTrades,
                'bullish'     => $totalBull,
                'bearish'     => $totalBear,
                'fut_pl'      => round($totalFutPL, 2),
                'opt_pl'      => round($totalOptPL, 2),
                'combined_pl' => round($totalCombPL, 2),
                'wins'        => $totalWins,
                'losses'      => $totalLosses,
                'win_rate'    => $totalTrades > 0 ? round($totalWins / $totalTrades * 100, 1) : 0,
                'investment'  => round($totalInvestment, 2),
                'fut_margin'  => round($totalFutMargin, 2),
                'months'      => count($months),
            ];

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'months'  => $months,
                'message' => count($months) . ' months, ' . $totalTrades . ' active trades',
            ]);

        } catch (\Exception $e) {
            Log::error('FutOptionMonthly::analyze — ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // =========================================================
    //  BUILD ROWS FOR ONE SIGNAL DATE
    // =========================================================

    private function buildRowsForDate(
        string $signalDate,
        string $prevDate,
        string $nextDate,
        array  $symbolFilter
    ): array {

        $futQuery = OptionOhlcData::whereDate('trade_date', $signalDate)
            ->where('instrument_type', 'FUT')
            ->whereRaw("TIME(interval_time) = '14:45:00'");

        if (!empty($symbolFilter)) {
            $futQuery->whereIn('base_symbol', $symbolFilter);
        }

        $futCandles = $futQuery->get()->keyBy('base_symbol');
        if ($futCandles->isEmpty()) return [];

        $rows = [];

        foreach ($futCandles->keys() as $symbol) {
            $futEntry         = $futCandles[$symbol];
            $futPositionPrice = (float) $futEntry->close;
            if ($futPositionPrice <= 0) continue;

            $currentExpiry = $this->resolveActiveExpiry($symbol, $signalDate);
            $prevExpiry    = $currentExpiry
                ? $this->getPrevDayExpiry($symbol, $prevDate, $currentExpiry)
                : null;

            // OI at 14:45 today
            $ceCurOI = (int) OptionOhlcData::whereDate('trade_date', $signalDate)
                ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
                ->sum('oi');

            $peCurOI = (int) OptionOhlcData::whereDate('trade_date', $signalDate)
                ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '14:45:00'")
                ->when($currentExpiry, fn($q) => $q->whereDate('expiry_date', $currentExpiry))
                ->sum('oi');

            if ($ceCurOI == 0 && $peCurOI == 0) continue;

            // OI baseline at 15:00 prev day
            $ceOpenOI = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)->where('instrument_type', 'CE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))
                ->sum('oi');

            $peOpenOI = (int) OptionOhlcData::whereDate('trade_date', $prevDate)
                ->where('base_symbol', $symbol)->where('instrument_type', 'PE')
                ->whereRaw("TIME(interval_time) = '15:00:00'")
                ->when($prevExpiry, fn($q) => $q->whereDate('expiry_date', $prevExpiry))
                ->sum('oi');

            $cePct = $ceOpenOI > 0 ? round((($ceCurOI - $ceOpenOI) / $ceOpenOI) * 100, 4) : 0;
            $pePct = $peOpenOI > 0 ? round((($peCurOI - $peOpenOI) / $peOpenOI) * 100, 4) : 0;

            $oiResult    = $this->getOISignal($cePct, $pePct);
            $oiSignal    = $oiResult['signal'];
            $tradeAction = 'WAIT';
            $optionType  = null;

            if ($oiSignal === 'BULLISH') { $tradeAction = 'BUY FUT';  $optionType = 'CE'; }
            elseif ($oiSignal === 'BEARISH') { $tradeAction = 'SELL FUT'; $optionType = 'PE'; }

            $lotSize   = $this->getLotSize($symbol);
            $futResult = $this->calcFutPL($symbol, $signalDate, $nextDate, $futPositionPrice, $tradeAction, $lotSize);
            $optResult = $this->calcOptionSellPL($symbol, $signalDate, $nextDate, $optionType, $futPositionPrice, $currentExpiry, $lotSize);

            // ── Investment ────────────────────────────────────────────────────
            // FUT notional (1 lot)  : price × lot_size
            // Option premium (2 lots): opt_position_price × lot_size × 2
            $futNotional = round($futPositionPrice * $lotSize, 2);
            $optNotional = round(($optResult['position_price'] ?? 0) * $lotSize * 2, 2);
            $investment  = round($futNotional + $optNotional, 2);

            // ── FUT Margin (Zerodha basket-margin API, cached) ────────────────
            $futMargin = $this->getFutMargin(
                $symbol,
                $futEntry->trading_symbol ?? ($symbol . 'FUT'),
                $tradeAction,
                $lotSize
            );

            $rows[] = [
                'signal_date'  => $signalDate,
                'symbol'       => $symbol,
                'oi_signal'    => $oiSignal,
                'oi_condition' => $oiResult['condition'],
                'trade_action' => $tradeAction,
                'fut_pl'       => $futResult['pl']  ?? 0,
                'opt_pl'       => $optResult['pl']  ?? 0,
                'combined_pl'  => round(($futResult['pl'] ?? 0) + ($optResult['pl'] ?? 0), 2),
                'lot_size'     => $lotSize,
                'investment'   => $investment,       // FUT notional + option premium × 2 lots
                'fut_margin'   => $futMargin,         // margin from Zerodha API
            ];
        }

        return $rows;
    }

    // =========================================================
    //  FUT MARGIN — Zerodha basket-margin API
    // =========================================================

    /**
     * Fetch margin required for 1 lot FUT from Zerodha.
     *
     * The PHP KiteConnect SDK does NOT expose a public getOrderMargins() method.
     * The route "order.margins" => "/margins/orders" exists in the SDK's route
     * map but is only used internally. We therefore call the API directly via
     * a plain HTTP POST using the broker's api_key + access_token credentials,
     * exactly the same way the SDK itself authenticates every request.
     *
     * Endpoint : POST https://api.kite.trade/margins/orders
     * Headers  : X-Kite-Version: 3
     *            Authorization : token {api_key}:{access_token}
     *            Content-Type  : application/json
     * Body     : JSON array of order objects
     *
     * Response shape:
     *   { "status": "success", "data": [ { "total": N, ... } ] }
     *
     * We read data[0]['total'] — the total margin for the single FUT order.
     *
     * Cached per trading_symbol to avoid repeat API calls within one request.
     * Falls back to 0 silently on any error.
     */
    private function getFutMargin(
        string $symbol,
        string $tradingSymbol,
        string $tradeAction,
        int    $lotSize
    ): float {
        if ($tradeAction === 'WAIT') return 0.0;

        if (isset($this->marginCache[$tradingSymbol])) {
            return $this->marginCache[$tradingSymbol];
        }

        if (!$this->zerodha) {
            $this->marginCache[$tradingSymbol] = 0.0;
            return 0.0;
        }

        try {
            $broker          = $this->zerodha->getBroker();
            $exchange        = $this->getExchange($symbol);
            $transactionType = ($tradeAction === 'BUY FUT') ? 'BUY' : 'SELL';

            $orders = [[
                'exchange'         => $exchange,
                'tradingsymbol'    => $tradingSymbol,
                'transaction_type' => $transactionType,
                'variety'          => 'regular',
                'product'          => 'NRML',
                'order_type'       => 'MARKET',
                'quantity'         => $lotSize,
                'price'            => 0,
                'trigger_price'    => 0,
            ]];

            // Direct HTTP POST — same auth scheme the SDK uses internally:
            //   Authorization: token {api_key}:{access_token}
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-Kite-Version' => '3',
                'Authorization'  => 'token ' . $broker->api_key . ':' . $broker->access_token,
                'Content-Type'   => 'application/json',
            ])->post('https://api.kite.trade/margins/orders', $orders);

            if ($response->successful()) {
                $body = $response->json();

                // Response: { "status": "success", "data": [ { "total": N, ... } ] }
                $data   = $body['data'] ?? [];
                $margin = 0.0;

                if (!empty($data) && is_array($data)) {
                    // data is an array of per-order margin objects
                    // We sent 1 order → read data[0]['total']
                    $first  = reset($data);
                    $margin = (float) ($first['total'] ?? 0);
                }

                $this->marginCache[$tradingSymbol] = round($margin, 2);
            } else {
                Log::warning("FutOptionMonthly: margin API non-2xx for [{$tradingSymbol}] — HTTP {$response->status()}: {$response->body()}");
                $this->marginCache[$tradingSymbol] = 0.0;
            }

        } catch (\Exception $e) {
            Log::warning("FutOptionMonthly: margin fetch failed for [{$tradingSymbol}] — " . $e->getMessage());
            $this->marginCache[$tradingSymbol] = 0.0;
        }

        return $this->marginCache[$tradingSymbol];
    }

    /**
     * Boot BrokerZerodhaHelper using the first broker with a valid token.
     * Silently skips on any error — margin columns will show 0.
     */
    private function bootZerodha(): void
    {
        try {
            $broker = BrokerApi::zerodha()->validToken()->first();
            if ($broker) {
                $this->zerodha = new BrokerZerodhaHelper($broker);
            }
        } catch (\Exception $e) {
            Log::warning('FutOptionMonthly: Could not boot Zerodha — ' . $e->getMessage());
            $this->zerodha = null;
        }
    }

    private function getExchange(string $symbol): string
    {
        return in_array($symbol, ['SENSEX', 'BANKEX']) ? 'BFO' : 'NFO';
    }

    // =========================================================
    //  FUT P/L
    // =========================================================

    private function calcFutPL(
        string $symbol,
        string $signalDate,
        string $nextDate,
        float  $positionPrice,
        string $tradeAction,
        int    $lotSize
    ): array {
        $no = ['pl' => 0];
        if ($tradeAction === 'WAIT') return $no;

        $candles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', 'FUT')
            ->whereDate('trade_date', $nextDate)
            ->whereRaw("TIME(interval_time) >= '" . self::EXIT_FROM . "'")
            ->whereRaw("TIME(interval_time) <= '" . self::EXIT_TO   . "'")
            ->get(['high', 'low']);

        if ($candles->isEmpty()) return $no;

        $pl = $tradeAction === 'BUY FUT'
            ? round(((float)$candles->max('high') - $positionPrice) * $lotSize, 2)
            : round(($positionPrice - (float)$candles->min('low')) * $lotSize, 2);

        return ['pl' => $pl];
    }

    // =========================================================
    //  OPTION SELL P/L
    // =========================================================

    private function calcOptionSellPL(
        string  $symbol,
        string  $signalDate,
        string  $nextDate,
        ?string $optionType,
        float   $spotPrice,
        ?string $expiry,
        int     $lotSize
    ): array {
        $no = ['pl' => 0, 'position_price' => null];
        if (!$optionType) return $no;

        // ATM option at 14:45
        $atmQ = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->whereDate('trade_date', $signalDate)
            ->where('strike_position', 'ATM')
            ->where('is_missing', 0)
            ->whereNotNull('expiry_date')
            ->whereRaw("TIME(interval_time) = '14:45:00'");
        if ($expiry) $atmQ->whereDate('expiry_date', $expiry);
        $atmRow = $atmQ->orderBy('expiry_date')->first();

        // Fallback: nearest strike to spot
        if (!$atmRow) {
            $fb = OptionOhlcData::where('base_symbol', $symbol)
                ->where('instrument_type', $optionType)
                ->whereDate('trade_date', $signalDate)
                ->where('is_missing', 0)
                ->whereNotNull('strike')->whereNotNull('expiry_date')
                ->whereRaw("TIME(interval_time) = '14:45:00'");
            if ($expiry) $fb->whereDate('expiry_date', $expiry);
            $atmRow = $fb->orderByRaw('ABS(strike - ?)', [$spotPrice])->orderBy('expiry_date')->first();
        }

        if (!$atmRow) return $no;

        $strike        = (float) $atmRow->strike;
        $expiryDate    = substr($atmRow->expiry_date, 0, 10);
        $positionPrice = (float) ($atmRow->close ?: $atmRow->open);
        if ($positionPrice <= 0) return $no;

        // Exit: MIN LOW in next-day window (max premium decay for seller)
        $candles = OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $optionType)
            ->where('strike', $strike)
            ->whereDate('expiry_date', $expiryDate)
            ->whereDate('trade_date', $nextDate)
            ->whereRaw("TIME(interval_time) >= '" . self::EXIT_FROM . "'")
            ->whereRaw("TIME(interval_time) <= '" . self::EXIT_TO   . "'")
            ->get(['low']);

        $exitPrice = $candles->isNotEmpty()
            ? max(0.05, (float)$candles->min('low'))
            : $positionPrice;

        return [
            'pl'             => round(($positionPrice - $exitPrice) * $lotSize * 2, 2),
            'position_price' => round($positionPrice, 2),
        ];
    }

    // =========================================================
    //  OI SIGNAL  (identical to FutOptionStrategyController)
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'condition' => 'Flat'];
    }

    // =========================================================
    //  EXPIRY HELPERS
    // =========================================================

    private function resolveActiveExpiry(string $symbol, string $date): ?string
    {
        $expiry = $this->getNearestExpiryForDate($symbol, $date);
        if (!$expiry) return null;
        if ($expiry === $date) {
            $next = $this->getNextSeriesExpiry($symbol, $date, $expiry);
            if ($next) return $next;
        }
        return $expiry;
    }

    private function getNearestExpiryForDate(string $symbol, string $date): ?string
    {
        $e = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));
        if ($e) return $e;
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)->orderByDesc('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getNextSeriesExpiry(string $symbol, string $date, string $currentExpiry): ?string
    {
        $n = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereNotNull('expiry_date')
            ->whereDate('trade_date', $date)->whereDate('expiry_date', '>', $currentExpiry)
            ->orderBy('expiry_date')->value(DB::raw('DATE(expiry_date)'));
        if ($n) return $n;
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', $currentExpiry)->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    private function getPrevDayExpiry(string $symbol, string $prevDate, string $currentExpiry): ?string
    {
        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereDate('trade_date', $prevDate)
            ->whereDate('expiry_date', $currentExpiry)->where('is_missing', 0)->exists();
        if ($exists) return $currentExpiry;
        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])->whereDate('trade_date', $prevDate)
            ->whereNotNull('expiry_date')->where('is_missing', 0)->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));
    }

    // =========================================================
    //  LOT SIZE
    // =========================================================

    private function getLotSize(string $symbol): int
    {
        $defaults = [
            'NIFTY' => 25, 'BANKNIFTY' => 15, 'FINNIFTY' => 25,
            'MIDCPNIFTY' => 50, 'SENSEX' => 10, 'BANKEX' => 15,
        ];
        $lot = DB::table('zerodha_instruments')
            ->where('name', $symbol)->where('exchange', 'NFO')
            ->whereIn('instrument_type', ['CE', 'PE'])->value('lot_size');
        return $lot ? (int)$lot : ($defaults[$symbol] ?? 1);
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function getPreviousTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) return $d->format('Y-m-d');
            $d->subDay();
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function getNextTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->addDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) return $d->format('Y-m-d');
            $d->addDay();
        }
        return Carbon::parse($date)->addDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}