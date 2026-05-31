<?php
// FILE: app/Http/Controllers/User/OIFlowSentimentController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OI Flow Sentiment Analyzer — 15min only (internal — not shown to users)
 * T = today 14:45 | T-1 = prev day 15:00
 * CE↑+PE↓ → BEARISH  CE↓+PE↑ → BULLISH
 */
class OIFlowSentimentController extends Controller
{
    private const TF            = '15min';
    private const ANALYSIS_TIME = '14:45:00';
    private const PREV_DAY_TIME = '15:00:00';
    private const OPT_TABLE     = 'cp_option_ohlc_15min';

    public function index()
    {
        $pageTitle = 'OI Flow Sentiment Analyzer';
        return view(activeTemplate() . 'user.oi-flow-sentiment.index', compact('pageTitle'));
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

            $lastDate = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw("TIME(interval_time) = ?", [self::ANALYSIS_TIME])
                ->max('trade_date');

            // Fall back: any data at all
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
            Log::error('OIFlowSentiment lastDate: ' . $e->getMessage());
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
    //   Accepts single `date` param (preferred) OR `from_date`/`to_date` range.

    public function analyze(Request $request): JsonResponse
    {
        try {
            // Single date (preferred) — fall back to from/to if sent
            $date      = $request->get('date');
            $fromDate  = $date ?? $request->get('from_date');
            $toDate    = $date ?? $request->get('to_date');

            $symbolReq    = array_filter((array) $request->get('symbols', []));
            $actionFilter = $request->get('filter_action', '');

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

            $symbols  = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;
            $optTable = self::OPT_TABLE;

            // Trading dates with data
            $tradeDates = DB::table($optTable)
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
                    'message'           => 'No data found for the selected date.',
                ]);
            }

            // Prev date map
            $prevDateMap = [];
            foreach ($tradeDates as $d) $prevDateMap[$d] = $this->getPreviousTradingDate($d);
            $prevDates = array_values(array_unique(array_values($prevDateMap)));

            // Today OI at 14:45
            $todayMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [self::ANALYSIS_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type',
                          DB::raw('DATE(trade_date) as trade_day'),
                          DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
                ->orderBy('base_symbol')
                ->each(function ($r) use (&$todayMap) {
                    $todayMap["{$r->base_symbol}|{$r->trade_day}|{$r->instrument_type}"] = (int) $r->total_oi;
                });

            // Prev day OI at 15:00
            $prevMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $prevDates)
                ->whereRaw("TIME(interval_time) = ?", [self::PREV_DAY_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type',
                          DB::raw('DATE(trade_date) as trade_day'),
                          DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
                ->orderBy('base_symbol')
                ->each(function ($r) use (&$prevMap) {
                    $prevMap["{$r->base_symbol}|{$r->trade_day}|{$r->instrument_type}"] = (int) $r->total_oi;
                });

            // Price / ATM info
            $priceMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [self::ANALYSIS_TIME])
                ->where('instrument_type', 'CE')
                ->where('strike_position', 'ATM')
                ->where('is_missing', false)
                ->select(['base_symbol', DB::raw('DATE(trade_date) as trade_day'),
                          'atm_strike', 'future_price', 'expiry_date'])
                ->orderBy('base_symbol')
                ->each(function ($r) use (&$priceMap) {
                    $priceMap["{$r->base_symbol}|{$r->trade_day}"] = $r;
                });

            // Build results
            $results = [];
            foreach ($tradeDates as $d) {
                $prevDate = $prevDateMap[$d];
                foreach ($symbols as $symbol) {
                    $ceToday = $todayMap["{$symbol}|{$d}|CE"] ?? 0;
                    $peToday = $todayMap["{$symbol}|{$d}|PE"] ?? 0;
                    if ($ceToday === 0 && $peToday === 0) continue;

                    $cePrev = $prevMap["{$symbol}|{$prevDate}|CE"] ?? 0;
                    $pePrev = $prevMap["{$symbol}|{$prevDate}|PE"] ?? 0;

                    $cePct = $cePrev > 0 ? round((($ceToday - $cePrev) / $cePrev) * 100, 2) : 0;
                    $pePct = $pePrev > 0 ? round((($peToday - $pePrev) / $pePrev) * 100, 2) : 0;

                    $signal      = $this->calcOISignal($cePct, $pePct);
                    $tradeAction = match($signal['sentiment']) {
                        'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                    };

                    if ($actionFilter && $tradeAction !== $actionFilter) continue;

                    $diff         = round(abs($cePct - $pePct), 2);
                    $strengthRank = match(true) {
                        $diff > 40 => 'Rank 1', $diff > 25 => 'Rank 2',
                        $diff > 10 => 'Rank 3', $diff > 5  => 'Rank 4', default => 'Normal'
                    };

                    $priceRow = $priceMap["{$symbol}|{$d}"] ?? null;

                    $results[] = [
                        'date'          => $d,
                        'symbol'        => $symbol,
                        'expiry'        => $priceRow ? substr($priceRow->expiry_date, 0, 10) : null,
                        'atm_strike'    => $priceRow?->atm_strike,
                        'fut_price'     => $priceRow ? round((float) $priceRow->future_price, 2) : null,
                        'ce_oi'         => $ceToday,
                        'pe_oi'         => $peToday,
                        'ce_oi_prev'    => $cePrev,
                        'pe_oi_prev'    => $pePrev,
                        'ce_oi_pct'     => $cePct,
                        'pe_oi_pct'     => $pePct,
                        'oi_diff'       => $diff,
                        'sentiment'     => $signal['sentiment'],
                        'condition'     => $signal['condition'],
                        'reason'        => $signal['reason'],
                        'trade_action'  => $tradeAction,
                        'strength_rank' => $strengthRank,
                        'pc_ratio'      => $ceToday > 0 ? round($peToday / $ceToday, 2) : 0,
                    ];
                }
            }

            usort($results, fn($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['symbol'], $b['symbol']));

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'total_records'     => count($results),
                'buy_ce_count'      => count(array_filter($results, fn($r) => $r['trade_action'] === 'BUY CE')),
                'buy_pe_count'      => count(array_filter($results, fn($r) => $r['trade_action'] === 'BUY PE')),
                'wait_count'        => count(array_filter($results, fn($r) => $r['trade_action'] === 'WAIT')),
                'bullish_count'     => count(array_filter($results, fn($r) => $r['sentiment'] === 'BULLISH')),
                'bearish_count'     => count(array_filter($results, fn($r) => $r['sentiment'] === 'BEARISH')),
                'message'           => count($results) . ' record(s) found for ' . $fromDate,
                'available_symbols' => $configSymbols,
                'date'              => $fromDate,
                'is_today'          => $fromDate === Carbon::today()->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('OIFlowSentiment analyze: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── Signal logic ──────────────────────────────────────────────────────────

    private function calcOISignal(float $cePct, float $pePct): array
    {
        $ceUp = $cePct > 0; $ceDown = $cePct < 0;
        $peUp = $pePct > 0; $peDown = $pePct < 0;

        if ($ceUp && $peDown)
            return ['sentiment' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓', 'reason' => 'Call buildup + Put unwinding → Resistance forming'];
        if ($ceDown && $peUp)
            return ['sentiment' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑', 'reason' => 'Call unwinding + Put buildup → Support forming'];
        if ($ceUp && $peUp) {
            if ($cePct > $pePct)
                return ['sentiment' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)', 'reason' => "Call buildup stronger (+{$cePct}% vs +{$pePct}%) → Bearish"];
            return ['sentiment' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)', 'reason' => "Put buildup stronger (+{$pePct}% vs +{$cePct}%) → Bullish"];
        }
        if ($ceDown && $peDown) {
            if ($cePct < $pePct)
                return ['sentiment' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)', 'reason' => "Call unwinding larger ({$cePct}% vs {$pePct}%) → Short covering"];
            return ['sentiment' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)', 'reason' => "Put unwinding larger ({$pePct}% vs {$cePct}%) → Long covering"];
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