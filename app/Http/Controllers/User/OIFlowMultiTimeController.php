<?php
// FILE: app/Http/Controllers/User/OIFlowMultiTimeController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OI Flow Sentiment — Multi Snapshot Analyzer
 * ─────────────────────────────────────────────────────────────
 * Anchor  : Previous trading day's CLOSING OI (15:00)
 * Compare : Today's OI at 10:15, 11:15, 12:15 — EACH checked
 *           independently against the same prev-day close anchor.
 *
 *   Prev Close OI  vs  Today 10:15  →  Snapshot #1 signal
 *   Prev Close OI  vs  Today 11:15  →  Snapshot #2 signal
 *   Prev Close OI  vs  Today 12:15  →  Snapshot #3 signal
 *
 * CE↑+PE↓ → BULLISH   CE↓+PE↑ → BEARISH   (same sentiment engine
 * as OIFlowSentimentController, just re-anchored per snapshot)
 */
class OIFlowMultiTimeController extends Controller
{
    private const TF              = '15min';
    private const PREV_CLOSE_TIME = '15:00:00'; // anchor
    private const PREV_OPEN_TIME  = '09:15:00'; // for prev-day buildup/unwinding tag
    private const OPT_TABLE       = 'cp_option_ohlc_15min';

    // Snapshot times to compare against the prev-day close anchor.
    // Add/remove entries here to change the columns shown — nothing
    // else in the controller needs to change.
    private const SNAPSHOT_TIMES = [
        '10:15:00' => '10:15',
        '11:15:00' => '11:15',
        '12:15:00' => '12:15',
    ];

    public function index()
    {
        $pageTitle = 'OI Flow Sentiment — Multi Snapshot';
        return view(activeTemplate() . 'user.oi-flow-multi-time.index', compact('pageTitle'));
    }

    // ── Last Available Date ────────────────────────────────────────────────────

    public function lastDate(Request $request): JsonResponse
    {
        try {
            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'last_date' => Carbon::today()->toDateString(),
                    'is_today'  => true,
                ]);
            }

            // Prefer the latest snapshot time (12:15) to decide "last date with data"
            $latestSnapTime = array_key_last(self::SNAPSHOT_TIMES);

            $lastDate = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw('TIME(interval_time) = ?', [$latestSnapTime])
                ->max('trade_date');

            if (!$lastDate) {
                $lastDate = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $config->id)
                    ->where('is_missing', false)
                    ->max('trade_date');
            }

            $today    = Carbon::today()->toDateString();
            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;

            return response()->json([
                'success'   => true,
                'last_date' => $lastDate,
                'is_today'  => $lastDate === $today,
            ]);

        } catch (\Exception $e) {
            Log::error('OIFlowMultiTime lastDate: ' . $e->getMessage());
            return response()->json([
                'success'   => false,
                'last_date' => Carbon::today()->toDateString(),
                'is_today'  => true,
            ]);
        }
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
                'message'   => 'No active analysis config found.',
            ]);
        }
        return response()->json(['success' => true, 'symbols' => $this->getConfigSymbols($config->id)]);
    }

    // ── Analyze API ───────────────────────────────────────────────────────────

    public function analyze(Request $request): JsonResponse
    {
        try {
            $date         = $request->get('date');
            $fromDate     = $date ?? $request->get('from_date');
            $toDate       = $date ?? $request->get('to_date');
            $symbolReq    = array_filter((array) $request->get('symbols', []));
            $actionFilter = $request->get('filter_action', ''); // matches if ANY snapshot hits this action

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select a date.', 'data' => []]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => 'No active Analysis Config found. Go to Admin → Analysis Config.',
                    'data'      => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            // Trading dates with any data in range
            $tradeDates = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json([
                    'success'           => true,
                    'data'              => [],
                    'date'              => $fromDate,
                    'is_today'          => $fromDate === Carbon::today()->toDateString(),
                    'available_symbols' => $configSymbols,
                    'snapshot_labels'   => array_values(self::SNAPSHOT_TIMES),
                    'message'           => 'No data found for the selected date.',
                ]);
            }

            // Prev trading date for each trade date
            $prevDateMap = [];
            foreach ($tradeDates as $d) $prevDateMap[$d] = $this->getPreviousTradingDate($d);
            $prevDates = array_values(array_unique(array_values($prevDateMap)));

            // ── Anchor: previous day's CLOSING OI (15:00) ──
            $prevCloseMap = $this->fetchOiMap($config->id, $symbols, $prevDates, self::PREV_CLOSE_TIME);

            // Previous day's OPENING OI (09:15) — only used for the buildup/unwinding tag
            $prevOpenMap = $this->fetchOiMap($config->id, $symbols, $prevDates, self::PREV_OPEN_TIME);

            // ── Today's OI at each snapshot time ──
            $snapshotOiMaps = [];
            foreach (self::SNAPSHOT_TIMES as $time => $label) {
                $snapshotOiMaps[$label] = $this->fetchOiMap($config->id, $symbols, $tradeDates, $time);
            }

            // ── Price/ATM info at each snapshot time (latest available wins per row) ──
            $snapshotPriceMaps = [];
            foreach (self::SNAPSHOT_TIMES as $time => $label) {
                $snapshotPriceMaps[$label] = $this->fetchPriceMap($config->id, $symbols, $tradeDates, $time);
            }
            $labelsLatestFirst = array_reverse(array_values(self::SNAPSHOT_TIMES));

            // Build results
            $results = [];
            foreach ($tradeDates as $d) {
                $prevDate = $prevDateMap[$d];

                foreach ($symbols as $symbol) {
                    $prevCloseCe = $prevCloseMap["{$symbol}|{$prevDate}|CE"] ?? 0;
                    $prevClosePe = $prevCloseMap["{$symbol}|{$prevDate}|PE"] ?? 0;

                    // Nothing to anchor against — skip this symbol/date
                    if ($prevCloseCe === 0 && $prevClosePe === 0) continue;

                    $prevOpenCe = $prevOpenMap["{$symbol}|{$prevDate}|CE"] ?? 0;
                    $prevOpenPe = $prevOpenMap["{$symbol}|{$prevDate}|PE"] ?? 0;
                    $prevTrendCe = $this->buildupTag($prevOpenCe, $prevCloseCe);
                    $prevTrendPe = $this->buildupTag($prevOpenPe, $prevClosePe);

                    $snapshots = [];
                    $rowHasData = false;

                    foreach (self::SNAPSHOT_TIMES as $time => $label) {
                        $ceNow = $snapshotOiMaps[$label]["{$symbol}|{$d}|CE"] ?? null;
                        $peNow = $snapshotOiMaps[$label]["{$symbol}|{$d}|PE"] ?? null;

                        if ($ceNow === null && $peNow === null) {
                            $snapshots[$label] = null; // no data yet for this time slot (e.g. mid-session)
                            continue;
                        }
                        $rowHasData = true;
                        $ceNow = $ceNow ?? 0;
                        $peNow = $peNow ?? 0;

                        $cePct = $prevCloseCe > 0 ? round((($ceNow - $prevCloseCe) / $prevCloseCe) * 100, 2) : 0;
                        $pePct = $prevClosePe > 0 ? round((($peNow - $prevClosePe) / $prevClosePe) * 100, 2) : 0;

                        $signal      = $this->calcOISignal($cePct, $pePct);
                        $tradeAction = match ($signal['sentiment']) {
                            'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                        };

                        $diff         = round(abs($cePct - $pePct), 2);
                        $strengthRank = match (true) {
                            $diff > 40 => 'Rank 1', $diff > 25 => 'Rank 2',
                            $diff > 10 => 'Rank 3', $diff > 5  => 'Rank 4', default => 'Normal'
                        };

                        $snapshots[$label] = [
                            'ce_oi'         => $ceNow,
                            'pe_oi'         => $peNow,
                            'ce_oi_pct'     => $cePct,
                            'pe_oi_pct'     => $pePct,
                            'oi_diff'       => $diff,
                            'sentiment'     => $signal['sentiment'],
                            'condition'     => $signal['condition'],
                            'reason'        => $signal['reason'],
                            'trade_action'  => $tradeAction,
                            'strength_rank' => $strengthRank,
                        ];
                    }

                    if (!$rowHasData) continue;

                    if ($actionFilter) {
                        $matches = false;
                        foreach ($snapshots as $s) {
                            if ($s && $s['trade_action'] === $actionFilter) { $matches = true; break; }
                        }
                        if (!$matches) continue;
                    }

                    // Use the latest snapshot time that has price data for this row
                    $priceRow = null;
                    foreach ($labelsLatestFirst as $label) {
                        if (isset($snapshotPriceMaps[$label]["{$symbol}|{$d}"])) {
                            $priceRow = $snapshotPriceMaps[$label]["{$symbol}|{$d}"];
                            break;
                        }
                    }

                    $results[] = [
                        'date'            => $d,
                        'symbol'          => $symbol,
                        'expiry'          => $priceRow ? substr($priceRow->expiry_date, 0, 10) : null,
                        'atm_strike'      => $priceRow?->atm_strike,
                        'fut_price'       => $priceRow ? round((float) $priceRow->future_price, 2) : null,
                        'prev_close_ce_oi' => $prevCloseCe,
                        'prev_close_pe_oi' => $prevClosePe,
                        'prev_ce_trend'    => $prevTrendCe,
                        'prev_pe_trend'    => $prevTrendPe,
                        'snapshots'        => $snapshots, // keyed by '10:15','11:15','12:15'
                    ];
                }
            }

            usort($results, fn($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['symbol'], $b['symbol']));

            // Per-snapshot stats
            $stats = [];
            foreach (self::SNAPSHOT_TIMES as $label) { /* placeholder overwritten below */ }
            foreach (array_values(self::SNAPSHOT_TIMES) as $label) {
                $buyCe = 0; $buyPe = 0; $wait = 0; $bull = 0; $bear = 0;
                foreach ($results as $r) {
                    $s = $r['snapshots'][$label] ?? null;
                    if (!$s) continue;
                    if ($s['trade_action'] === 'BUY CE') $buyCe++;
                    elseif ($s['trade_action'] === 'BUY PE') $buyPe++;
                    else $wait++;
                    if ($s['sentiment'] === 'BULLISH') $bull++;
                    elseif ($s['sentiment'] === 'BEARISH') $bear++;
                }
                $stats[$label] = [
                    'buy_ce' => $buyCe, 'buy_pe' => $buyPe, 'wait' => $wait,
                    'bullish' => $bull, 'bearish' => $bear,
                ];
            }

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'total_records'     => count($results),
                'stats'             => $stats,
                'snapshot_labels'   => array_values(self::SNAPSHOT_TIMES),
                'message'           => count($results) . ' record(s) found for ' . $fromDate,
                'available_symbols' => $configSymbols,
                'date'              => $fromDate,
                'is_today'          => $fromDate === Carbon::today()->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('OIFlowMultiTime analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── Data helpers ──────────────────────────────────────────────────────────

    /** Sum OI per base_symbol|trade_day|instrument_type at a fixed clock time. */
    private function fetchOiMap(int $configId, array $symbols, array $dates, string $time): array
    {
        $map = [];
        DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereIn(DB::raw('DATE(trade_date)'), $dates)
            ->whereRaw('TIME(interval_time) = ?', [$time])
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->where('is_missing', false)
            ->select(['base_symbol', 'instrument_type',
                      DB::raw('DATE(trade_date) as trade_day'),
                      DB::raw('SUM(oi) as total_oi')])
            ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
            ->orderBy('base_symbol')
            ->each(function ($r) use (&$map) {
                $map["{$r->base_symbol}|{$r->trade_day}|{$r->instrument_type}"] = (int) $r->total_oi;
            });
        return $map;
    }

    /** ATM strike / future price / expiry per base_symbol|trade_day at a fixed clock time. */
    private function fetchPriceMap(int $configId, array $symbols, array $dates, string $time): array
    {
        $map = [];
        DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereIn(DB::raw('DATE(trade_date)'), $dates)
            ->whereRaw('TIME(interval_time) = ?', [$time])
            ->where('instrument_type', 'CE')
            ->where('strike_position', 'ATM')
            ->where('is_missing', false)
            ->select(['base_symbol', DB::raw('DATE(trade_date) as trade_day'),
                      'atm_strike', 'future_price', 'expiry_date'])
            ->orderBy('base_symbol')
            ->each(function ($r) use (&$map) {
                $map["{$r->base_symbol}|{$r->trade_day}"] = $r;
            });
        return $map;
    }

    private function buildupTag(?float $openOi, ?float $closeOi): string
    {
        if (!$openOi || !$closeOi || $openOi <= 0) return '-';
        $chg = (($closeOi - $openOi) / $openOi) * 100;
        return $chg > 1 ? 'Buildup' : ($chg < -1 ? 'Unwinding' : 'Flat');
    }

    // ── Signal logic (same engine as the 15-min single-snapshot analyzer) ──────

    private function calcOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp && $peDown)
            return ['sentiment' => 'BULLISH', 'condition' => 'CE ↑ + PE ↓', 'reason' => 'Call buildup + Put unwinding → Support forming'];
        if ($ceDown && $peUp)
            return ['sentiment' => 'BEARISH', 'condition' => 'CE ↓ + PE ↑', 'reason' => 'Call unwinding + Put buildup → Resistance forming'];
        if ($ceUp && $peUp) {
            if ($cePct > $pePct)
                return ['sentiment' => 'BULLISH', 'condition' => 'Both ↑ (CE > PE)', 'reason' => "Call buildup stronger (+{$cePct}% vs +{$pePct}%) → Bullish"];
            return ['sentiment' => 'BEARISH', 'condition' => 'Both ↑ (PE > CE)', 'reason' => "Put buildup stronger (+{$pePct}% vs +{$cePct}%) → Bearish"];
        }
        if ($ceDown && $peDown) {
            if ($cePct < $pePct)
                return ['sentiment' => 'BEARISH', 'condition' => 'Both ↓ (CE < PE)', 'reason' => "Call unwinding larger ({$cePct}% vs {$pePct}%) → Long covering"];
            return ['sentiment' => 'BULLISH', 'condition' => 'Both ↓ (PE < CE)', 'reason' => "Put unwinding larger ({$pePct}% vs {$cePct}%) → Short covering"];
        }
        return ['sentiment' => 'NEUTRAL', 'condition' => 'Flat', 'reason' => 'No clear OI direction'];
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

    private function getPreviousTradingDate(string $date): string
    {
        $prev = Carbon::parse($date)->subDay();
        for ($i = 0; $i < 10; $i++) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->toDateString())) return $prev->toDateString();
            $prev->subDay();
        }
        return Carbon::parse($date)->subDay()->toDateString();
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')->where('market_name', 'NSE')->where('holiday_date', $date)->exists();
    }
}