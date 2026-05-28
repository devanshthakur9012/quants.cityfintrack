<?php
// FILE: app/Http/Controllers/User/NiftyDrivenBreakoutAnalysisController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NIFTY-Driven Multi-Symbol Breakout Analyzer — 15min only
 *
 * Signal source : NIFTY FUT 15min candles
 *   CE signal   : any candle HIGH  >= 09:15 open + threshold
 *   PE signal   : any candle LOW   <= 09:15 open − threshold
 *   Entry       : OPEN of NEXT candle after trigger bar
 *   Strike pick : highest-OI ATM strike at buy candle time
 *   Lot size    : from zerodha_instruments
 */
class NiftyDrivenBreakoutAnalysisController extends Controller
{
    private const TF           = '15min';
    private const MARKET_OPEN  = '09:15';
    private const NIFTY_SYMBOL = 'NIFTY';

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'NIFTY Breakout — Signal Analyzer';
        return view(activeTemplate() . 'user.nifty-breakout-analyzer.index', compact('pageTitle'));
    }

    // ── Symbols API ───────────────────────────────────────────────────────────

    public function getSymbols(Request $request): JsonResponse
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json([
                'success'   => true,
                'symbols'   => [],
                'no_config' => true,
                'message'   => 'No active 15min Analysis Config. Go to Admin → Analysis Config.',
            ]);
        }
        return response()->json([
            'success' => true,
            'symbols' => $this->getConfigSymbols($config->id),
        ]);
    }

    // ── Main Analyze API ──────────────────────────────────────────────────────

    public function analyze(Request $request): JsonResponse
    {
        try {
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $threshold    = (float) $request->get('threshold', 30);
            $signalFilter = strtoupper($request->get('filter', 'BOTH'));    // CE | PE | BOTH
            $symbolFilter = strtoupper($request->get('symbol_filter', 'ALL'));

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates.', 'data' => []]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => 'No active 15min Analysis Config.',
                    'data'      => [],
                ]);
            }

            $allSymbols = $this->getConfigSymbols($config->id);
            if (empty($allSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols = ($symbolFilter === 'ALL' || !in_array($symbolFilter, $allSymbols))
                ? $allSymbols
                : [$symbolFilter];

            $futTable = 'cp_fut_ohlc_15min';
            $optTable = 'cp_option_ohlc_15min';

            // ── Trading dates ─────────────────────────────────────────────
            $tradeDates = DB::table($futTable)
                ->where('analysis_config_id', $config->id)
                ->where('base_symbol', self::NIFTY_SYMBOL)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->where('is_missing', false)
                ->selectRaw('DATE(trade_date) as d')
                ->distinct()->orderBy('d')
                ->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json([
                    'success' => true, 'data' => [],
                    'message' => 'No NIFTY FUT data for this range.',
                ]);
            }

            // ── NIFTY FUT candles ─────────────────────────────────────────
            $futRows = DB::table($futTable)
                ->where('analysis_config_id', $config->id)
                ->where('base_symbol', self::NIFTY_SYMBOL)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->where('is_missing', false)
                ->select(['trade_date', 'interval_time', 'open', 'high', 'low'])
                ->orderBy('trade_date')->orderBy('interval_time')
                ->get();

            $futByDate = [];
            foreach ($futRows as $row) {
                $date    = substr($row->trade_date, 0, 10);
                $timeKey = substr($row->interval_time, 11, 5);
                $futByDate[$date][$timeKey] = $row;
            }

            // ── Detect signals ────────────────────────────────────────────
            $signals = [];
            foreach ($tradeDates as $date) {
                $candles = $futByDate[$date] ?? [];
                if (empty($candles)) continue;
                ksort($candles);
                $times   = array_keys($candles);
                $dayOpen = (float) ($candles[self::MARKET_OPEN]->open ?? reset($candles)->open);
                $ceFired = false;
                $peFired = false;

                foreach ($times as $i => $t) {
                    $c = $candles[$t];
                    if (!$ceFired && in_array($signalFilter, ['CE', 'BOTH']) && (float)$c->high >= $dayOpen + $threshold) {
                        $ceFired  = true;
                        $buyTime  = $times[$i + 1] ?? null;
                        if ($buyTime) {
                            $signals[] = [
                                'date'          => $date,
                                'signal_type'   => 'CE',
                                'trigger_time'  => $t,
                                'buy_time'      => $buyTime,
                                'nifty_open'    => $dayOpen,
                                'nifty_trigger' => (float) $c->high,
                                'nifty_move'    => round((float)$c->high - $dayOpen, 2),
                            ];
                        }
                    }
                    if (!$peFired && in_array($signalFilter, ['PE', 'BOTH']) && (float)$c->low <= $dayOpen - $threshold) {
                        $peFired  = true;
                        $buyTime  = $times[$i + 1] ?? null;
                        if ($buyTime) {
                            $signals[] = [
                                'date'          => $date,
                                'signal_type'   => 'PE',
                                'trigger_time'  => $t,
                                'buy_time'      => $buyTime,
                                'nifty_open'    => $dayOpen,
                                'nifty_trigger' => (float) $c->low,
                                'nifty_move'    => round((float)$c->low - $dayOpen, 2),
                            ];
                        }
                    }
                    if ($ceFired && $peFired) break;
                }
            }

            if (empty($signals)) {
                return response()->json([
                    'success' => true, 'data' => [],
                    'message' => "No signals found. Try a lower threshold (current: {$threshold} pts).",
                ]);
            }

            $optDates = array_unique(array_column($signals, 'date'));

            // ── Option rows (highest-OI per slot) ─────────────────────────
            $optRows = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $optDates)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->where('oi', '>', 0)
                ->select([
                    'base_symbol', 'instrument_type', 'strike',
                    'expiry_date', 'trading_symbol', 'oi', 'open',
                    DB::raw('DATE(trade_date) as trade_day'),
                    DB::raw("TIME_FORMAT(interval_time, '%H:%i') as slot_time"),
                ])
                ->orderByDesc('oi')
                ->get();

            // ── Lot sizes from zerodha_instruments ────────────────────────
            $tradingSymbols = $optRows->pluck('trading_symbol')->unique()->values()->toArray();
            $lotSizeMap = DB::table('zerodha_instruments')
                ->whereIn('trading_symbol', $tradingSymbols)
                ->pluck('lot_size', 'trading_symbol')
                ->toArray();

            // ── Index: date|symbol|type|slot → highest-OI row ─────────────
            $optMap = [];
            foreach ($optRows as $r) {
                $key = $r->trade_day . '|' . $r->base_symbol . '|' . $r->instrument_type . '|' . $r->slot_time;
                if (!isset($optMap[$key])) {
                    $optMap[$key] = $r;
                }
            }

            // ── Build results ─────────────────────────────────────────────
            $results = [];
            foreach ($signals as $sig) {
                foreach ($symbols as $symbol) {
                    $mapKey = $sig['date'] . '|' . $symbol . '|' . $sig['signal_type'] . '|' . $sig['buy_time'];
                    $opt    = $optMap[$mapKey] ?? null;
                    if (!$opt) continue;

                    $lotSize  = (int) ($lotSizeMap[$opt->trading_symbol] ?? 1);
                    $buyPrice = (float) $opt->open;

                    $results[] = [
                        'date'          => $sig['date'],
                        'symbol'        => $symbol,
                        'signal_type'   => $sig['signal_type'],
                        'trigger_time'  => $sig['trigger_time'],
                        'buy_time'      => $sig['buy_time'],
                        'nifty_open'    => $sig['nifty_open'],
                        'nifty_trigger' => $sig['nifty_trigger'],
                        'nifty_move'    => $sig['nifty_move'],
                        'strike'        => $opt->strike,
                        'expiry_date'   => $opt->expiry_date,
                        'strike_oi'     => (int) $opt->oi,
                        'buy_price'     => $buyPrice,
                        'lot_size'      => $lotSize,
                        'investment'    => round($buyPrice * $lotSize, 2),
                    ];
                }
            }

            $ceRows = array_filter($results, fn($r) => $r['signal_type'] === 'CE');
            $peRows = array_filter($results, fn($r) => $r['signal_type'] === 'PE');

            return response()->json([
                'success'           => true,
                'data'              => array_values($results),
                'total_records'     => count($results),
                'ce_count'          => count($ceRows),
                'pe_count'          => count($peRows),
                'symbols_hit'       => count(array_unique(array_column($results, 'symbol'))),
                'total_investment'  => round(array_sum(array_column($results, 'investment')), 2),
                'signal_count'      => count($signals),
                'threshold'         => $threshold,
                'message'           => count($results) . ' trade(s) found',
                'available_symbols' => $allSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('NiftyDrivenBreakout: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── Config helpers ────────────────────────────────────────────────────────

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', self::TF)
            ->where('is_active', 1)
            ->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')
            ->toArray();
    }
}