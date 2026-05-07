<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StockSignal;
use App\Models\StockPivot;
use App\Models\StockPattern;
use App\Models\StockFeature;
use App\Models\StockDailyOhlcData;
use App\Models\StockDailyOhlcSymbol;
use App\Services\Stock\SignalService;
use App\Services\Stock\SimilarityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * StockSignalController
 *
 * Routes:
 *   Route::middleware(['auth'])->prefix('stock-signals')->name('stock-signals.')->group(function () {
 *       Route::get('/index',   [StockSignalController::class, 'index'])->name('index');
 *       Route::get('/analyze', [StockSignalController::class, 'analyze'])->name('analyze');
 *       Route::get('/detail',  [StockSignalController::class, 'detail'])->name('detail');
 *   });
 */
class StockSignalController extends Controller
{
    public function __construct(
        private readonly SignalService     $signalService,
        private readonly SimilarityService $similarityService
    ) {}

    // =========================================================
    //  PAGE
    // =========================================================

    public function index()
    {
        $pageTitle = 'Stock Signal Scanner — BUY / SELL / HOLD';
        return view('templates.basic.user.stock-signals.index', compact('pageTitle'));
    }

    // =========================================================
    //  MAIN ENDPOINT
    //  GET /stock-signals/analyze
    //  Params: from_date, to_date, signal_type (ALL|BUY|SELL|HOLD), min_confidence
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate      = $request->get('from_date',      Carbon::today('Asia/Kolkata')->toDateString());
            $toDate        = $request->get('to_date',        Carbon::today('Asia/Kolkata')->toDateString());
            $signalFilter  = strtoupper($request->get('signal_type',   'ALL'));
            $minConfidence = (int) $request->get('min_confidence', 0);

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            // ── Fetch signals ─────────────────────────────────────────────────
            $query = StockSignal::whereBetween('signal_date', [$fromDate, $toDate])
                ->where('confidence', '>=', $minConfidence)
                ->orderByDesc('confidence')
                ->orderBy('symbol');

            if ($signalFilter !== 'ALL') {
                $query->where('signal_type', $signalFilter);
            }

            $signals = $query->get();

            if ($signals->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No signals found. Run: php artisan stocks:generate-signals',
                    'data'    => [],
                ]);
            }

            // ── Aggregate per-symbol ──────────────────────────────────────────
            $symbolStats = [];

            foreach ($signals as $sig) {
                $sym = $sig->symbol;

                if (!isset($symbolStats[$sym])) {
                    $symbolStats[$sym] = [
                        'symbol'        => $sym,
                        'buy_count'     => 0,
                        'sell_count'    => 0,
                        'hold_count'    => 0,
                        'total_signals' => 0,
                        'max_conf'      => 0,
                        'conf_sum'      => 0,
                        'latest_signal' => null,
                        'latest_date'   => null,
                        'latest_conf'   => 0,
                        'latest_reason' => null,
                        'latest_score'  => null,
                    ];
                }

                $s = &$symbolStats[$sym];
                $s['total_signals']++;
                $s['conf_sum']  += $sig->confidence;
                $s['max_conf']   = max($s['max_conf'], $sig->confidence);

                match ($sig->signal_type) {
                    'BUY'  => $s['buy_count']++,
                    'SELL' => $s['sell_count']++,
                    default => $s['hold_count']++,
                };

                $sigDate = is_string($sig->signal_date)
                    ? $sig->signal_date
                    : $sig->signal_date->format('Y-m-d');

                if ($s['latest_date'] === null || $sigDate > $s['latest_date']) {
                    $s['latest_date']   = $sigDate;
                    $s['latest_signal'] = $sig->signal_type;
                    $s['latest_conf']   = $sig->confidence;
                    $s['latest_reason'] = $sig->reason;
                    $s['latest_score']  = $sig->score_json;
                }

                unset($s);
            }

            // ── Enrich each symbol ────────────────────────────────────────────
            $results = [];

            foreach ($symbolStats as $sym => $s) {
                $s['avg_conf'] = $s['total_signals'] > 0
                    ? round($s['conf_sum'] / $s['total_signals'], 1)
                    : 0;

                // Latest OHLC close
                $ohlc = StockDailyOhlcData::where('symbol', $sym)
                    ->where('is_missing', 0)
                    ->where('close', '>', 0)
                    ->orderByDesc('trade_date')
                    ->first(['close', 'high', 'low', 'volume', 'trade_date']);

                $s['close']     = $ohlc ? round((float) $ohlc->close, 2) : null;
                $s['ohlc_date'] = $ohlc
                    ? (is_string($ohlc->trade_date) ? $ohlc->trade_date : $ohlc->trade_date->format('Y-m-d'))
                    : null;

                // Nearest support & resistance pivots
                $s['support'] = StockPivot::where('symbol', $sym)
                    ->where('pivot_type', 'LOW')
                    ->where('trade_date', '<=', $toDate)
                    ->orderByDesc('trade_date')
                    ->value('price');

                $s['resistance'] = StockPivot::where('symbol', $sym)
                    ->where('pivot_type', 'HIGH')
                    ->where('trade_date', '<=', $toDate)
                    ->orderByDesc('trade_date')
                    ->value('price');

                $s['support']    = $s['support']    ? round((float) $s['support'],    2) : null;
                $s['resistance'] = $s['resistance'] ? round((float) $s['resistance'], 2) : null;

                // Latest feature row
                $feat = StockFeature::where('symbol', $sym)
                    ->orderByDesc('trade_date')
                    ->first(['rsi_value', 'rsi_zone', 'trend', 'volatility', 'volume_spike']);

                $s['rsi_value']    = $feat?->rsi_value;
                $s['rsi_zone']     = $feat?->rsi_zone    ?? 'NEUTRAL';
                $s['trend']        = $feat?->trend        ?? '—';
                $s['volatility']   = $feat?->volatility   ?? '—';
                $s['volume_spike'] = $feat?->volume_spike ?? false;

                // Most recent pattern (last 30 days)
                $pattern = StockPattern::where('symbol', $sym)
                    ->where('end_date', '>=', Carbon::now()->subDays(30)->toDateString())
                    ->orderByDesc('confidence')
                    ->orderByDesc('end_date')
                    ->first(['pattern_type', 'confidence']);

                $s['pattern']      = $pattern?->pattern_type;
                $s['pattern_conf'] = $pattern?->confidence;

                unset($s['conf_sum']); // clean before sending

                $results[] = $s;
            }

            // ── Sort: latest_conf desc → total_signals desc ───────────────────
            usort($results, fn($a, $b) =>
                $b['latest_conf'] <=> $a['latest_conf']
                ?: $b['total_signals'] <=> $a['total_signals']
            );

            // ── Summary stats ─────────────────────────────────────────────────
            $todayStr   = Carbon::today('Asia/Kolkata')->toDateString();
            $todayBuys  = StockSignal::where('signal_date', $todayStr)->where('signal_type', 'BUY')->where('confidence', '>=', 65)->count();
            $todaySells = StockSignal::where('signal_date', $todayStr)->where('signal_type', 'SELL')->where('confidence', '<=', 35)->count();
            $highConf   = collect($results)->filter(fn($r) => $r['latest_conf'] >= 70)->count();
            $medConf    = collect($results)->filter(fn($r) => $r['latest_conf'] >= 50 && $r['latest_conf'] < 70)->count();

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_symbols' => count($results),
                'total_signals' => $signals->count(),
                'date_range'    => "{$fromDate} → {$toDate}",
                'today_buys'    => $todayBuys,
                'today_sells'   => $todaySells,
                'high_conf'     => $highConf,
                'med_conf'      => $medConf,
                'message'       => count($results) . ' symbols | ' . $signals->count() . ' total signals',
            ]);

        } catch (\Exception $e) {
            Log::error('StockSignalController@analyze: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  DETAIL ENDPOINT (modal popup)
    //  GET /stock-signals/detail?symbol=BSE&date=2024-06-15
    // =========================================================

    public function detail(Request $request)
    {
        try {
            $symbol = strtoupper(trim($request->get('symbol', '')));
            $date   = $request->get('date', Carbon::today('Asia/Kolkata')->toDateString());

            if (!$symbol) {
                return response()->json(['success' => false, 'message' => 'Symbol required']);
            }

            // Signal for requested date
            $signal = StockSignal::where('symbol', $symbol)
                ->where('signal_date', $date)
                ->first();

            // Last 10 signals
            $history = StockSignal::where('symbol', $symbol)
                ->orderByDesc('signal_date')
                ->limit(10)
                ->get(['signal_date', 'signal_type', 'confidence', 'reason'])
                ->map(fn($s) => [
                    'date'       => is_string($s->signal_date) ? $s->signal_date : $s->signal_date->format('Y-m-d'),
                    'type'       => $s->signal_type,
                    'confidence' => $s->confidence,
                    'reason'     => $s->reason,
                ]);

            // Last 5 OHLC candles
            $ohlcHistory = StockDailyOhlcData::where('symbol', $symbol)
                ->where('is_missing', 0)
                ->where('close', '>', 0)
                ->orderByDesc('trade_date')
                ->limit(5)
                ->get(['trade_date', 'open', 'high', 'low', 'close', 'volume'])
                ->map(fn($r) => [
                    'date'   => is_string($r->trade_date) ? $r->trade_date : $r->trade_date->format('Y-m-d'),
                    'open'   => round((float) $r->open,  2),
                    'high'   => round((float) $r->high,  2),
                    'low'    => round((float) $r->low,   2),
                    'close'  => round((float) $r->close, 2),
                    'volume' => (int) $r->volume,
                ]);

            // Pivots (last 90 days)
            $pivots = StockPivot::where('symbol', $symbol)
                ->where('trade_date', '>=', Carbon::now()->subDays(90)->toDateString())
                ->orderByDesc('trade_date')
                ->limit(20)
                ->get(['trade_date', 'pivot_type', 'price', 'strength'])
                ->map(fn($p) => [
                    'date'     => $p->trade_date->format('Y-m-d'),
                    'type'     => $p->pivot_type,
                    'price'    => $p->price,
                    'strength' => $p->strength,
                ]);

            // Patterns (last 60 days)
            $patterns = StockPattern::where('symbol', $symbol)
                ->where('end_date', '>=', Carbon::now()->subDays(60)->toDateString())
                ->orderByDesc('end_date')
                ->limit(5)
                ->get(['pattern_type', 'start_date', 'end_date', 'confidence', 'meta_json'])
                ->map(fn($p) => [
                    'type'       => $p->pattern_type,
                    'start'      => $p->start_date->format('Y-m-d'),
                    'end'        => $p->end_date->format('Y-m-d'),
                    'confidence' => $p->confidence,
                    'meta'       => $p->meta_json,
                ]);

            // Latest feature row
            $feature    = StockFeature::where('symbol', $symbol)->orderByDesc('trade_date')->first();
            $featureDate = $feature
                ? (is_string($feature->trade_date) ? $feature->trade_date : $feature->trade_date->format('Y-m-d'))
                : $date;

            // Similarity analysis
            $similarity = $this->similarityService->analyze($symbol, $featureDate);

            return response()->json([
                'success'    => true,
                'symbol'     => $symbol,
                'date'       => $date,
                'signal'     => $signal ? [
                    'type'       => $signal->signal_type,
                    'confidence' => $signal->confidence,
                    'reason'     => $signal->reason,
                    'score'      => $signal->score_json,
                ] : null,
                'feature'    => $feature ? [
                    'trend'      => $feature->trend,
                    'volatility' => $feature->volatility,
                    'rsi_value'  => $feature->rsi_value,
                    'rsi_zone'   => $feature->rsi_zone,
                    'dist_high'  => round($feature->distance_from_high, 2),
                    'dist_low'   => round($feature->distance_from_low,  2),
                    'vol_spike'  => $feature->volume_spike,
                ] : null,
                'similarity' => $similarity,
                'history'    => $history,
                'ohlc'       => $ohlcHistory,
                'pivots'     => $pivots,
                'patterns'   => $patterns,
            ]);

        } catch (\Exception $e) {
            Log::error('StockSignalController@detail: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}