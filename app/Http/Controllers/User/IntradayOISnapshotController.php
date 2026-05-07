<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Intraday OI Snapshot Analyzer
 *
 * Compares CE/PE OI at 09:15 (open) vs 12:00 (midday snapshot).
 * Same signal logic as OI Flow Sentiment — only the time window differs.
 *
 * Open  = 09:15 candle  (first candle of the day)
 * Snap  = 12:00 candle  (15min/30min) | 11:15 (1hr)
 *
 * Tables: cp_option_ohlc_{timeframe}
 */
class IntradayOISnapshotController extends Controller
{
    private const TIMEFRAMES = ['15min', '30min', '1hr'];
    private const OPEN_TIME  = '09:15:00';
    private const SNAP_TIME  = [
        '15min' => '12:00:00',
        '30min' => '12:00:00',
        '1hr'   => '11:15:00',
    ];

    public function index()
    {
        $pageTitle = 'Intraday OI Snapshot';
        return view($this->activeTemplate . 'user.intraday-oi-snapshot.index', compact('pageTitle'));
    }

    public function getSymbols(Request $request)
    {
        $timeframe = $this->resolveTimeframe($request);
        $config    = $this->getActiveConfig($timeframe);
        if (!$config) {
            return response()->json(['success' => true, 'symbols' => [], 'no_config' => true,
                'message' => "No active config for [{$timeframe}]."]);
        }
        return response()->json(['success' => true, 'symbols' => $this->getConfigSymbols($config->id)]);
    }

    public function analyze(Request $request)
    {
        try {
            $timeframe    = $this->resolveTimeframe($request);
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $symbolReq    = array_filter((array)$request->get('symbols', []));
            $actionFilter = $request->get('filter_action', '');

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates.', 'data' => []]);
            }

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json(['success' => false, 'no_config' => true,
                    'message' => "No active Analysis Config for [{$timeframe}]. Go to Admin → Analysis Config.", 'data' => []]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false, 'message' => 'No symbols configured.', 'data' => []]);
            }

            $symbols  = !empty($symbolReq) ? array_values(array_intersect($symbolReq, $configSymbols)) : $configSymbols;
            $optTable = 'cp_option_ohlc_' . $timeframe;
            $snapTime = self::SNAP_TIME[$timeframe];

            // Trading dates in range
            $tradeDates = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json(['success' => true, 'data' => [], 'message' => 'No data for this date range.']);
            }

            // Bulk load open OI (09:15)
            $openRows = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [self::OPEN_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type',
                          DB::raw('DATE(trade_date) as trade_day'),
                          DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
                ->get();

            // Bulk load snapshot OI (12:00 or 11:15)
            $snapRows = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [$snapTime])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type',
                          DB::raw('DATE(trade_date) as trade_day'),
                          DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
                ->get();

            // ATM strike + expiry from open candle
            $infoRows = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [self::OPEN_TIME])
                ->where('instrument_type', 'CE')
                ->where('strike_position', 'ATM')
                ->where('is_missing', false)
                ->select(['base_symbol', DB::raw('DATE(trade_date) as trade_day'),
                          'atm_strike', 'expiry_date', 'future_price'])
                ->get();

            // Index maps
            $oiMap   = [];
            foreach ($openRows as $r) $oiMap[$r->base_symbol.'|'.$r->trade_day.'|'.$r->instrument_type.'|o'] = (int)$r->total_oi;
            foreach ($snapRows as $r) $oiMap[$r->base_symbol.'|'.$r->trade_day.'|'.$r->instrument_type.'|s'] = (int)$r->total_oi;
            $infoMap = [];
            foreach ($infoRows as $r) $infoMap[$r->base_symbol.'|'.$r->trade_day] = $r;

            $results = [];

            foreach ($tradeDates as $date) {
                foreach ($symbols as $symbol) {
                    $k      = $symbol . '|' . $date;
                    $ceOpen = $oiMap[$k.'|CE|o'] ?? 0;
                    $ceSnap = $oiMap[$k.'|CE|s'] ?? 0;
                    $peOpen = $oiMap[$k.'|PE|o'] ?? 0;
                    $peSnap = $oiMap[$k.'|PE|s'] ?? 0;

                    if ($ceOpen === 0 && $ceSnap === 0 && $peOpen === 0 && $peSnap === 0) continue;

                    $cePct = $ceOpen > 0 ? round((($ceSnap - $ceOpen) / $ceOpen) * 100, 2) : 0;
                    $pePct = $peOpen > 0 ? round((($peSnap - $peOpen) / $peOpen) * 100, 2) : 0;

                    $signal      = $this->calcSignal($cePct, $pePct);
                    $tradeAction = match($signal['sentiment']) {
                        'BULLISH' => 'BUY CE',
                        'BEARISH' => 'BUY PE',
                        default   => 'WAIT',
                    };

                    if ($actionFilter && $tradeAction !== $actionFilter) continue;

                    $diff         = round(abs($cePct - $pePct), 2);
                    $strengthRank = match(true) {
                        $diff > 40 => 'Rank 1',
                        $diff > 25 => 'Rank 2',
                        $diff > 10 => 'Rank 3',
                        $diff > 5  => 'Rank 4',
                        default    => 'Normal',
                    };

                    $info = $infoMap[$k] ?? null;

                    $results[] = [
                        'date'          => $date,
                        'symbol'        => $symbol,
                        'expiry'        => $info ? substr($info->expiry_date ?? '', 0, 10) : null,
                        'atm_strike'    => $info ? $info->atm_strike   : null,
                        'fut_price'     => $info ? round((float)$info->future_price, 2) : null,
                        'ce_oi'         => $ceSnap,
                        'ce_oi_prev'    => $ceOpen,
                        'ce_oi_pct'     => $cePct,
                        'pe_oi'         => $peSnap,
                        'pe_oi_prev'    => $peOpen,
                        'pe_oi_pct'     => $pePct,
                        'oi_diff'       => $diff,
                        'sentiment'     => $signal['sentiment'],
                        'condition'     => $signal['condition'],
                        'reason'        => $signal['reason'],
                        'trade_action'  => $tradeAction,
                        'strength_rank' => $strengthRank,
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
                'message'           => count($results) . ' record(s) found',
                'timeframe'         => $timeframe,
                'snapshot_time'     => $snapTime,
                'available_symbols' => $configSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('IntradayOISnapshot: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    private function calcSignal(float $cePct, float $pePct): array
    {
        if ($cePct > 0 && $pePct < 0) return ['sentiment' => 'BEARISH', 'condition' => 'CE ↑ + PE ↓', 'reason' => 'Call buildup + Put unwinding → Resistance forming'];
        if ($cePct < 0 && $pePct > 0) return ['sentiment' => 'BULLISH', 'condition' => 'CE ↓ + PE ↑', 'reason' => 'Call unwinding + Put buildup → Support forming'];
        if ($cePct > 0 && $pePct > 0) return $cePct > $pePct
            ? ['sentiment' => 'BEARISH', 'condition' => 'Both ↑ (CE > PE)', 'reason' => "Call buildup stronger (+{$cePct}% vs PE +{$pePct}%) → Bearish"]
            : ['sentiment' => 'BULLISH', 'condition' => 'Both ↑ (PE > CE)', 'reason' => "Put buildup stronger (+{$pePct}% vs CE +{$cePct}%) → Bullish"];
        if ($cePct < 0 && $pePct < 0) return $cePct < $pePct
            ? ['sentiment' => 'BULLISH', 'condition' => 'Both ↓ (CE < PE)', 'reason' => "Call unwinding larger ({$cePct}% vs PE {$pePct}%) → Bullish"]
            : ['sentiment' => 'BEARISH', 'condition' => 'Both ↓ (PE < CE)', 'reason' => "Put unwinding larger ({$pePct}% vs CE {$cePct}%) → Bearish"];
        return ['sentiment' => 'NEUTRAL', 'condition' => 'Flat', 'reason' => 'No clear OI direction'];
    }

    private function getActiveConfig(string $timeframe): ?object
    {
        return DB::table('analysis_configs')->where('time_frame', $timeframe)->where('is_active', 1)->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')->toArray();
    }

    private function resolveTimeframe(Request $request): string
    {
        $tf = strtolower(trim($request->get('timeframe', '15min')));
        return in_array($tf, self::TIMEFRAMES) ? $tf : '15min';
    }
}