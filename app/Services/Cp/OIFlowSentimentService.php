<?php

namespace App\Services\Cp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OI Flow Sentiment — ALL logic lives here now.
 * T = today 14:45 | T-1 = prev day 15:00
 * CE↑+PE↓ → BULLISH  CE↓+PE↑ → BEARISH  (reversed logic, as currently live)
 *
 * Two callers, one source of truth:
 *   - OIFlowSentimentController  → analyze() / lastDate() / symbols()  (dashboard/UI)
 *   - CpAnalysisSignalResolver   → getSignalsForDate()                 (auto-order cron)
 * Both paths run through the SAME calcOISignal() / getActiveConfig() / etc.
 * Change the sentiment math once, here, and both pick it up automatically.
 */
class OIFlowSentimentService
{
    private const TF             = '15min';
    private const ANALYSIS_TIME  = '14:45:00';
    private const PREV_DAY_TIME  = '15:00:00';
    private const PREV_OPEN_TIME = '09:15:00';
    private const OPT_TABLE      = 'cp_option_ohlc_15min';

    // ── Used by OIFlowSentimentController::lastDate() ───────────────────

    public function lastDate(): array
    {
        try {
            $config = $this->getActiveConfig();
            $today  = Carbon::today()->toDateString();

            if (!$config) {
                return ['success' => false, 'last_date' => $today, 'is_today' => true];
            }

            $lastDate = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->where('is_missing', false)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->max('trade_date');

            if (!$lastDate) {
                $lastDate = DB::table(self::OPT_TABLE)
                    ->where('analysis_config_id', $config->id)
                    ->where('is_missing', false)
                    ->max('trade_date');
            }

            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;

            return ['success' => true, 'last_date' => $lastDate, 'is_today' => $lastDate === $today];

        } catch (\Exception $e) {
            Log::error('OIFlowSentimentService lastDate: ' . $e->getMessage());
            return ['success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true];
        }
    }

    // ── Used by OIFlowSentimentController::getSymbols() ─────────────────

    public function symbols(): array
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return [
                'success'   => true,
                'symbols'   => [],
                'no_config' => true,
                'message'   => 'No active analysis config found.',
            ];
        }
        return ['success' => true, 'symbols' => $this->getConfigSymbols($config->id)];
    }

    // ── Used by OIFlowSentimentController::analyze() — the dashboard ────

    public function analyze(string $fromDate, string $toDate, array $symbolReq = [], string $actionFilter = ''): array
    {
        try {
            $config = $this->getActiveConfig();
            if (!$config) {
                return [
                    'success'   => false,
                    'no_config' => true,
                    'message'   => 'No active Analysis Config found. Go to Admin → Analysis Config.',
                    'data'      => [],
                ];
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return ['success' => false, 'message' => 'No symbols configured.', 'data' => []];
            }

            $symbols  = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;
            $optTable = self::OPT_TABLE;

            $tradeDates = DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return [
                    'success'           => true,
                    'data'              => [],
                    'date'              => $fromDate,
                    'is_today'          => $fromDate === Carbon::today()->toDateString(),
                    'available_symbols' => $configSymbols,
                    'message'           => 'No data found for the selected date.',
                ];
            }

            $prevDateMap = [];
            foreach ($tradeDates as $d) $prevDateMap[$d] = $this->getPreviousTradingDate($d);
            $prevDates = array_values(array_unique(array_values($prevDateMap)));

            $todayMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
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

            $prevMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $prevDates)
                ->whereRaw('TIME(interval_time) = ?', [self::PREV_DAY_TIME])
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

            $prevOpenMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $prevDates)
                ->whereRaw('TIME(interval_time) = ?', [self::PREV_OPEN_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type',
                        DB::raw('DATE(trade_date) as trade_day'),
                        DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
                ->orderBy('base_symbol')
                ->each(function ($r) use (&$prevOpenMap) {
                    $prevOpenMap["{$r->base_symbol}|{$r->trade_day}|{$r->instrument_type}"] = (int) $r->total_oi;
                });

            $priceMap = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereIn(DB::raw('DATE(trade_date)'), $tradeDates)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->where('instrument_type', 'CE')
                ->where('strike_position', 'ATM')
                ->where('is_missing', false)
                ->select(['base_symbol', DB::raw('DATE(trade_date) as trade_day'),
                          'atm_strike', 'future_price', 'expiry_date'])
                ->orderBy('base_symbol')
                ->each(function ($r) use (&$priceMap) {
                    $priceMap["{$r->base_symbol}|{$r->trade_day}"] = $r;
                });

            $results = [];
            foreach ($tradeDates as $d) {
                $prevDate = $prevDateMap[$d];
                foreach ($symbols as $symbol) {
                    $ceToday = $todayMap["{$symbol}|{$d}|CE"] ?? 0;
                    $peToday = $todayMap["{$symbol}|{$d}|PE"] ?? 0;
                    if ($ceToday === 0 && $peToday === 0) continue;

                    $cePrev = $prevMap["{$symbol}|{$prevDate}|CE"] ?? 0;
                    $pePrev = $prevMap["{$symbol}|{$prevDate}|PE"] ?? 0;

                    $prevOpenCe = $prevOpenMap["{$symbol}|{$prevDate}|CE"] ?? 0;
                    $prevOpenPe = $prevOpenMap["{$symbol}|{$prevDate}|PE"] ?? 0;

                    $prevTrendCe = $this->buildupTag($prevOpenCe, $cePrev);
                    $prevTrendPe = $this->buildupTag($prevOpenPe, $pePrev);

                    $oiConfirmSignal = $this->oiConfirmFromTrend($prevTrendCe, $prevTrendPe);

                    $cePct = $cePrev > 0 ? round((($ceToday - $cePrev) / $cePrev) * 100, 2) : 0;
                    $pePct = $pePrev > 0 ? round((($peToday - $pePrev) / $pePrev) * 100, 2) : 0;

                    $signal      = $this->calcOISignal($cePct, $pePct);
                    $tradeAction = match ($signal['sentiment']) {
                        'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                    };

                    $oiConfirmOk = ($tradeAction === 'BUY CE' && $oiConfirmSignal === 'BUY_CE')
                        || ($tradeAction === 'BUY PE' && $oiConfirmSignal === 'BUY_PE');

                    if ($actionFilter && $tradeAction !== $actionFilter) continue;

                    $diff         = round(abs($cePct - $pePct), 2);
                    $strengthRank = match (true) {
                        $diff > 40 => 'Rank 1', $diff > 25 => 'Rank 2',
                        $diff > 10 => 'Rank 3', $diff > 5  => 'Rank 4', default => 'Normal'
                    };

                    $priceRow = $priceMap["{$symbol}|{$d}"] ?? null;

                    $results[] = [
                        'date'              => $d,
                        'symbol'            => $symbol,
                        'expiry'            => $priceRow ? substr($priceRow->expiry_date, 0, 10) : null,
                        'atm_strike'        => $priceRow?->atm_strike,
                        'fut_price'         => $priceRow ? round((float) $priceRow->future_price, 2) : null,
                        'ce_oi'             => $ceToday,
                        'pe_oi'             => $peToday,
                        'ce_oi_prev'        => $cePrev,
                        'pe_oi_prev'        => $pePrev,
                        'ce_oi_pct'         => $cePct,
                        'pe_oi_pct'         => $pePct,
                        'oi_diff'           => $diff,
                        'sentiment'         => $signal['sentiment'],
                        'condition'         => $signal['condition'],
                        'reason'            => $signal['reason'],
                        'trade_action'      => $tradeAction,
                        'strength_rank'     => $strengthRank,
                        'pc_ratio'          => $ceToday > 0 ? round($peToday / $ceToday, 2) : 0,
                        'oi_confirm_signal' => $oiConfirmSignal,
                        'oi_confirm_ok'     => $oiConfirmOk,
                        'prev_ce_trend'     => $prevTrendCe,
                        'prev_pe_trend'     => $prevTrendPe,
                    ];
                }
            }

            usort($results, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['symbol'], $b['symbol']));

            return [
                'success'           => true,
                'data'              => $results,
                'total_records'     => count($results),
                'buy_ce_count'      => count(array_filter($results, fn ($r) => $r['trade_action'] === 'BUY CE')),
                'buy_pe_count'      => count(array_filter($results, fn ($r) => $r['trade_action'] === 'BUY PE')),
                'wait_count'        => count(array_filter($results, fn ($r) => $r['trade_action'] === 'WAIT')),
                'bullish_count'     => count(array_filter($results, fn ($r) => $r['sentiment'] === 'BULLISH')),
                'bearish_count'     => count(array_filter($results, fn ($r) => $r['sentiment'] === 'BEARISH')),
                'message'           => count($results) . ' record(s) found for ' . $fromDate,
                'available_symbols' => $configSymbols,
                'date'              => $fromDate,
                'is_today'          => $fromDate === Carbon::today()->toDateString(),
            ];

        } catch (\Exception $e) {
            Log::error('OIFlowSentimentService analyze: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    // ── Used by CpAnalysisSignalResolver — the auto-order cron ──────────
    // Lean single-date version. Runs through the SAME calcOISignal() as
    // analyze() above, so the cron and the dashboard can never drift.

    /**
     * @return array<int, array{symbol:string,date:string,sentiment:string,trade_action:string,ce_oi_pct:float,pe_oi_pct:float,ce_oi:int,pe_oi:int}>
     */
    public function getSignalsForDate(string $date): array
    {
        try {
            $config = $this->getActiveConfig();
            if (!$config) return [];

            $symbols = $this->getConfigSymbols($config->id);
            if (empty($symbols)) return [];

            $prevDate = $this->getPreviousTradingDate($date);
            $optTable = self::OPT_TABLE;

            $ceToday = []; $peToday = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereDate('trade_date', $date)
                ->whereRaw('TIME(interval_time) = ?', [self::ANALYSIS_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type', DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type')
                ->get()
                ->each(function ($r) use (&$ceToday, &$peToday) {
                    if ($r->instrument_type === 'CE') $ceToday[$r->base_symbol] = (int) $r->total_oi;
                    else $peToday[$r->base_symbol] = (int) $r->total_oi;
                });

            if (empty($ceToday) && empty($peToday)) return []; // 14:45 candle hasn't printed yet today

            $cePrevArr = []; $pePrevArr = [];
            DB::table($optTable)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereDate('trade_date', $prevDate)
                ->whereRaw('TIME(interval_time) = ?', [self::PREV_DAY_TIME])
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->where('is_missing', false)
                ->select(['base_symbol', 'instrument_type', DB::raw('SUM(oi) as total_oi')])
                ->groupBy('base_symbol', 'instrument_type')
                ->get()
                ->each(function ($r) use (&$cePrevArr, &$pePrevArr) {
                    if ($r->instrument_type === 'CE') $cePrevArr[$r->base_symbol] = (int) $r->total_oi;
                    else $pePrevArr[$r->base_symbol] = (int) $r->total_oi;
                });

            $results = [];
            foreach ($symbols as $symbol) {
                $ceNow = $ceToday[$symbol] ?? 0;
                $peNow = $peToday[$symbol] ?? 0;
                if ($ceNow === 0 && $peNow === 0) continue;

                $cePrev = $cePrevArr[$symbol] ?? 0;
                $pePrev = $pePrevArr[$symbol] ?? 0;

                $cePct = $cePrev > 0 ? round((($ceNow - $cePrev) / $cePrev) * 100, 2) : 0;
                $pePct = $pePrev > 0 ? round((($peNow - $pePrev) / $pePrev) * 100, 2) : 0;

                $signal      = $this->calcOISignal($cePct, $pePct); // ← same method analyze() uses
                $tradeAction = match ($signal['sentiment']) {
                    'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                };

                if ($tradeAction === 'WAIT') continue; // order engine only acts on CE/PE signals

                $results[] = [
                    'symbol'       => $symbol,
                    'date'         => $date,
                    'sentiment'    => $signal['sentiment'],
                    'trade_action' => $tradeAction,
                    'ce_oi_pct'    => $cePct,
                    'pe_oi_pct'    => $pePct,
                    'ce_oi'        => $ceNow,
                    'pe_oi'        => $peNow,
                ];
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('OIFlowSentimentService getSignalsForDate: ' . $e->getMessage());
            return [];
        }
    }

    // ── Shared private helpers — used by BOTH analyze() and getSignalsForDate() ──

    private function oiConfirmFromTrend(string $ceTrend, string $peTrend): string
    {
        if ($ceTrend === 'Buildup'   && $peTrend === 'Buildup')   return 'IGNORE';
        if ($ceTrend === 'Unwinding' && $peTrend === 'Unwinding') return 'IGNORE';
        if ($ceTrend === 'Buildup'   && $peTrend === 'Unwinding') return 'BUY_CE';
        if ($ceTrend === 'Unwinding' && $peTrend === 'Buildup')   return 'BUY_PE';
        if ($ceTrend === 'Buildup'   && $peTrend === 'Flat')      return 'BUY_CE';
        if ($ceTrend === 'Flat'      && $peTrend === 'Buildup')   return 'BUY_PE';
        return 'IGNORE';
    }

    private function buildupTag(?float $openOi, ?float $closeOi): string
    {
        if (!$openOi || !$closeOi || $openOi <= 0) return '-';
        $chg = (($closeOi - $openOi) / $openOi) * 100;
        return $chg > 1 ? 'Buildup' : ($chg < -1 ? 'Unwinding' : 'Flat');
    }

    /**
     * THE single source of truth for OI sentiment. analyze() and
     * getSignalsForDate() both call this — never duplicate this logic
     * anywhere else. (Currently the "reversed" version, as live.)
     */
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