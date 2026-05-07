<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StockDailyOhlcData;
use App\Models\StockDailyOhlcSymbol;
use App\Services\SmartMoneySignalService;
use Carbon\Carbon;

class SmartMoneyAnalysisController extends Controller
{
    private SmartMoneySignalService $signalService;

    public function __construct(SmartMoneySignalService $signalService)
    {
        $this->signalService = $signalService;
    }

    // =========================================================================
    // index()  — renders the blade shell only (no data, AJAX fills it)
    // =========================================================================

    public function index(Request $request)
    {
        $todayStr = now()->toDateString();
        $pageTitle = "SMART MONEY";

        return view('templates.basic.user.smart-money.index', compact(
            'pageTitle', 'todayStr'
        ));
    }

    // =========================================================================
    // signals()  — JSON endpoint called by AJAX on date/filter change
    //
    // GET /smart-money/signals?date=YYYY-MM-DD
    // Returns: { success, is_today, date, summary, results[] }
    // =========================================================================
    // signals()  — JSON endpoint called by AJAX
    //
    // GET /smart-money/signals?from_date=YYYY-MM-DD&to_date=YYYY-MM-DD&symbol=ALL
    //
    // Date range behaviour:
    //   - Single date  : from_date == to_date  → same as before, 1 row per symbol
    //   - Date range   : from_date < to_date   → iterates each trading day in range,
    //                    runs full SMC analysis for that day, N rows per symbol
    //
    // Symbol filter:
    //   - symbol=ALL   → all active symbols (default)
    //   - symbol=RELIANCE → only that symbol
    //
    // Returns: { success, is_today, from_date, to_date, summary, results[], symbols[] }
    // =========================================================================

    public function signals(Request $request)
    {
        $todayStr  = now()->toDateString();

        // ── Date range ────────────────────────────────────────────────────────
        $fromDate = $request->get('from_date', $todayStr);
        $toDate   = $request->get('to_date',   $todayStr);

        // Clamp futures
        if ($fromDate > $todayStr) $fromDate = $todayStr;
        if ($toDate   > $todayStr) $toDate   = $todayStr;

        // Ensure from <= to
        if ($fromDate > $toDate) [$fromDate, $toDate] = [$toDate, $fromDate];

        $isToday   = ($toDate === $todayStr && $fromDate === $todayStr);
        $isSameDay = ($fromDate === $toDate);

        // ── Symbol filter ─────────────────────────────────────────────────────
        $symbolFilter = strtoupper($request->get('symbol', 'ALL'));

        // All active symbols (used for the dropdown list in response)
        $allSymbols = StockDailyOhlcSymbol::active()->orderBy('symbol')->pluck('symbol')->toArray();

        if (empty($allSymbols)) {
            return response()->json([
                'success'   => false,
                'message'   => 'No active symbols configured.',
                'is_today'  => $isToday,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'symbols'   => [],
                'summary'   => ['buy'=>0,'sell'=>0,'buy_pullback'=>0,'sell_pullback'=>0,'no_trade'=>0,'total'=>0],
                'results'   => [],
            ]);
        }

        // Symbols to actually process
        $symbolsToProcess = ($symbolFilter === 'ALL')
            ? $allSymbols
            : (in_array($symbolFilter, $allSymbols) ? [$symbolFilter] : $allSymbols);

        // ── Build list of trading dates in range ──────────────────────────────
        // We only run analysis on dates that actually have data in the DB.
        // This avoids processing weekends/holidays and keeps response fast.
        $tradingDates = StockDailyOhlcData::whereIn('symbol', $symbolsToProcess)
            ->where('is_missing', 0)
            ->whereBetween('trade_date', [$fromDate, $toDate])
            ->selectRaw('DISTINCT DATE(trade_date) as d')
            ->orderBy('d', 'asc')
            ->pluck('d')
            ->map(fn($d) => is_string($d) ? $d : Carbon::parse($d)->toDateString())
            ->toArray();

        if (empty($tradingDates)) {
            return response()->json([
                'success'   => true,
                'message'   => 'No trading data found for the selected date range.',
                'is_today'  => $isToday,
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'symbols'   => $allSymbols,
                'summary'   => ['buy'=>0,'sell'=>0,'buy_pullback'=>0,'sell_pullback'=>0,'no_trade'=>0,'total'=>0],
                'results'   => [],
            ]);
        }

        $results = [];

        foreach ($symbolsToProcess as $symbol) {
            foreach ($tradingDates as $analysisDate) {
                // Fetch last 60 candles UP TO this analysis date
                $candles = StockDailyOhlcData::where('symbol', $symbol)
                    ->where('is_missing', 0)
                    ->whereDate('trade_date', '<=', $analysisDate)
                    ->orderBy('trade_date', 'desc')
                    ->limit(60)
                    ->get()
                    ->reverse()
                    ->values()
                    ->map(fn($c) => [
                        'date'   => is_string($c->trade_date)
                            ? $c->trade_date
                            : $c->trade_date->toDateString(),
                        'open'   => (float) $c->open,
                        'high'   => (float) $c->high,
                        'low'    => (float) $c->low,
                        'close'  => (float) $c->close,
                        'volume' => (int)   $c->volume,
                    ])
                    ->toArray();

                $signal = $this->signalService->analyse($candles);
                $signal['symbol']       = $symbol;
                $signal['analysis_date']= $analysisDate;           // the date this analysis is for
                $signal['last_close']   = !empty($candles) ? end($candles)['close'] : null;
                $signal['last_date']    = !empty($candles) ? end($candles)['date']  : null;

                $results[] = $signal;
            }
        }

        // Sort: by date ASC (oldest first → newest last), then signal priority within each date
        $order = ['BUY'=>0,'SELL'=>1,'BUY_PULLBACK'=>2,'SELL_PULLBACK'=>3,'NO_TRADE'=>4,'NO_DATA'=>5];
        usort($results, function($a, $b) use ($order) {
            // Oldest date first
            $dateCmp = strcmp($a['analysis_date'], $b['analysis_date']);
            if ($dateCmp !== 0) return $dateCmp;
            // Then by signal priority
            return ($order[$a['signal']] ?? 9) <=> ($order[$b['signal']] ?? 9);
        });

        $summary = [
            'buy'          => count(array_filter($results, fn($r) => $r['signal'] === 'BUY')),
            'sell'         => count(array_filter($results, fn($r) => $r['signal'] === 'SELL')),
            'buy_pullback' => count(array_filter($results, fn($r) => $r['signal'] === 'BUY_PULLBACK')),
            'sell_pullback'=> count(array_filter($results, fn($r) => $r['signal'] === 'SELL_PULLBACK')),
            'no_trade'     => count(array_filter($results, fn($r) => $r['signal'] === 'NO_TRADE')),
            'total'        => count($results),
        ];

        return response()->json([
            'success'   => true,
            'is_today'  => $isToday,
            'is_range'  => !$isSameDay,
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'symbols'   => $allSymbols,
            'summary'   => $summary,
            'results'   => $results,
        ]);
    }

    // =========================================================================
    // show()  — single symbol detail page (still server-rendered)
    // =========================================================================

    public function show(Request $request, string $symbol)
    {
        $symbol       = strtoupper($symbol);
        $todayStr     = now()->toDateString();
        $selectedDate = $request->get('date', $todayStr);

        if ($selectedDate > $todayStr) {
            $selectedDate = $todayStr;
        }

        $isToday = ($selectedDate === $todayStr);

        $candles = StockDailyOhlcData::where('symbol', $symbol)
            ->where('is_missing', 0)
            ->whereDate('trade_date', '<=', $selectedDate)
            ->orderBy('trade_date', 'desc')
            ->limit(60)
            ->get()
            ->reverse()
            ->values()
            ->map(fn($c) => [
                'date'   => is_string($c->trade_date)
                    ? $c->trade_date
                    : $c->trade_date->toDateString(),
                'open'   => (float) $c->open,
                'high'   => (float) $c->high,
                'low'    => (float) $c->low,
                'close'  => (float) $c->close,
                'volume' => (int)   $c->volume,
            ])
            ->toArray();

        $signal = $this->signalService->analyse($candles);
        $signal['symbol']     = $symbol;
        $signal['last_close'] = !empty($candles) ? end($candles)['close'] : null;
        $signal['last_date']  = !empty($candles) ? end($candles)['date']  : null;

        $chartCandles = array_slice($candles, -30);
        $pageTitle    = "SMART MONEY";

        return view('templates.basic.user.smart-money.show', compact(
            'signal', 'symbol', 'chartCandles', 'pageTitle',
            'selectedDate', 'isToday', 'todayStr'
        ));
    }
}