<?php
// FILE: app/Http/Controllers/User/IntradayOISnapshotController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Intraday OI Snapshot Analyzer — 15min only
 * Compares CE/PE OI at 09:15 (open) vs 12:00 (midday snapshot).
 * Same signal logic as OI Flow Sentiment — time window differs.
 */
class IntradayOISnapshotController extends Controller
{
    private const TF        = '15min';
    private const OPEN_TIME = '09:15:00';
    private const SNAP_TIME = '12:00:00';   // 15min: 12:00

    public function index()
    {
        $pageTitle = 'Intraday OI Snapshot';
        return view(activeTemplate() . 'user.intraday-oi-snapshot.index', compact('pageTitle'));
    }

    public function getSymbols(Request $request): JsonResponse
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return response()->json(['success'=>true,'symbols'=>[],'no_config'=>true,
                'message'=>'No active 15min config found.']);
        }
        return response()->json(['success'=>true,'symbols'=>$this->getConfigSymbols($config->id)]);
    }

    public function analyze(Request $request): JsonResponse
    {
        try {
            $fromDate     = $request->get('from_date');
            $toDate       = $request->get('to_date');
            $symbolReq    = array_filter((array)$request->get('symbols', []));
            $actionFilter = $request->get('filter_action', '');

            if (!$fromDate || !$toDate) {
                return response()->json(['success'=>false,'message'=>'Please select both dates.','data'=>[]]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json(['success'=>false,'no_config'=>true,
                    'message'=>'No active 15min Analysis Config. Go to Admin → Analysis Config.','data'=>[]]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success'=>false,'message'=>'No symbols configured.','data'=>[]]);
            }

            $symbols  = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;
            $optTable = 'cp_option_ohlc_15min';

            // Trading dates with data
            $tradeDates = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return response()->json(['success'=>true,'data'=>[],'message'=>'No data found for selected dates.']);
            }

            // Open OI at 09:15
            $oiMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [self::OPEN_TIME])
                ->whereIn('instrument_type', ['CE','PE'])
                ->where('is_missing', false)
                ->select(['base_symbol','instrument_type',DB::raw('DATE(trade_date) as trade_day'),DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol','instrument_type',DB::raw('DATE(trade_date)'))
                ->each(function($r) use (&$oiMap) {
                    $oiMap["{$r->base_symbol}|{$r->trade_day}|{$r->instrument_type}|o"] = (int)$r->total_oi;
                });

            // Snapshot OI at 12:00
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [self::SNAP_TIME])
                ->whereIn('instrument_type', ['CE','PE'])
                ->where('is_missing', false)
                ->select(['base_symbol','instrument_type',DB::raw('DATE(trade_date) as trade_day'),DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol','instrument_type',DB::raw('DATE(trade_date)'))
                ->each(function($r) use (&$oiMap) {
                    $oiMap["{$r->base_symbol}|{$r->trade_day}|{$r->instrument_type}|s"] = (int)$r->total_oi;
                });

            // ATM / price info from 09:15 CE ATM
            $infoMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw("TIME(interval_time) = ?", [self::OPEN_TIME])
                ->where('instrument_type','CE')->where('strike_position','ATM')->where('is_missing',false)
                ->select(['base_symbol',DB::raw('DATE(trade_date) as trade_day'),'atm_strike','expiry_date','future_price'])
                ->each(function($r) use (&$infoMap) {
                    $infoMap["{$r->base_symbol}|{$r->trade_day}"] = $r;
                });

            $results = [];
            foreach ($tradeDates as $date) {
                foreach ($symbols as $symbol) {
                    $k      = "{$symbol}|{$date}";
                    $ceOpen = $oiMap["{$k}|CE|o"] ?? 0;
                    $ceSnap = $oiMap["{$k}|CE|s"] ?? 0;
                    $peOpen = $oiMap["{$k}|PE|o"] ?? 0;
                    $peSnap = $oiMap["{$k}|PE|s"] ?? 0;

                    if ($ceOpen === 0 && $ceSnap === 0 && $peOpen === 0 && $peSnap === 0) continue;

                    $cePct = $ceOpen > 0 ? round((($ceSnap - $ceOpen) / $ceOpen) * 100, 2) : 0;
                    $pePct = $peOpen > 0 ? round((($peSnap - $peOpen) / $peOpen) * 100, 2) : 0;

                    $signal      = $this->calcSignal($cePct, $pePct);
                    $tradeAction = match($signal['sentiment']) {
                        'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                    };

                    if ($actionFilter && $tradeAction !== $actionFilter) continue;

                    $diff         = round(abs($cePct - $pePct), 2);
                    $strengthRank = match(true) {
                        $diff > 40 => 'Rank 1', $diff > 25 => 'Rank 2',
                        $diff > 10 => 'Rank 3', $diff > 5  => 'Rank 4', default => 'Normal'
                    };

                    $info = $infoMap[$k] ?? null;

                    $results[] = [
                        'date'          => $date,
                        'symbol'        => $symbol,
                        'expiry'        => $info ? substr($info->expiry_date ?? '', 0, 10) : null,
                        'atm_strike'    => $info?->atm_strike,
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

            usort($results, fn($a,$b) => strcmp($b['date'],$a['date']) ?: strcmp($a['symbol'],$b['symbol']));

            return response()->json([
                'success'           => true,
                'data'              => $results,
                'total_records'     => count($results),
                'buy_ce_count'      => count(array_filter($results, fn($r) => $r['trade_action']==='BUY CE')),
                'buy_pe_count'      => count(array_filter($results, fn($r) => $r['trade_action']==='BUY PE')),
                'wait_count'        => count(array_filter($results, fn($r) => $r['trade_action']==='WAIT')),
                'bullish_count'     => count(array_filter($results, fn($r) => $r['sentiment']==='BULLISH')),
                'bearish_count'     => count(array_filter($results, fn($r) => $r['sentiment']==='BEARISH')),
                'message'           => count($results) . ' record(s) found',
                'snapshot_time'     => self::SNAP_TIME,
                'available_symbols' => $configSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('IntradayOISnapshot: ' . $e->getMessage());
            return response()->json(['success'=>false,'message'=>$e->getMessage(),'data'=>[]], 500);
        }
    }

    private function calcSignal(float $cePct, float $pePct): array
    {
        if ($cePct > 0 && $pePct < 0) return ['sentiment'=>'BEARISH','condition'=>'CE ↑ + PE ↓','reason'=>'Call buildup + Put unwinding → Resistance forming'];
        if ($cePct < 0 && $pePct > 0) return ['sentiment'=>'BULLISH','condition'=>'CE ↓ + PE ↑','reason'=>'Call unwinding + Put buildup → Support forming'];
        if ($cePct > 0 && $pePct > 0) return $cePct > $pePct
            ? ['sentiment'=>'BEARISH','condition'=>'Both ↑ (CE > PE)','reason'=>"Call buildup stronger (+{$cePct}% vs +{$pePct}%) → Bearish"]
            : ['sentiment'=>'BULLISH','condition'=>'Both ↑ (PE > CE)','reason'=>"Put buildup stronger (+{$pePct}% vs +{$cePct}%) → Bullish"];
        if ($cePct < 0 && $pePct < 0) return $cePct < $pePct
            ? ['sentiment'=>'BULLISH','condition'=>'Both ↓ (CE < PE)','reason'=>"Call unwinding larger ({$cePct}% vs {$pePct}%) → Bullish"]
            : ['sentiment'=>'BEARISH','condition'=>'Both ↓ (PE < CE)','reason'=>"Put unwinding larger ({$pePct}% vs {$cePct}%) → Bearish"];
        return ['sentiment'=>'NEUTRAL','condition'=>'Flat','reason'=>'No clear OI direction'];
    }

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')->where('time_frame', self::TF)->where('is_active',1)->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists','symbol_lists.id','=','analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')->toArray();
    }
}