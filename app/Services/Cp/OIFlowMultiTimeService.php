<?php

namespace App\Services\Cp;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OI Flow Sentiment — Multi Snapshot. ALL logic lives here now.
 * Two callers, one source of truth:
 *   - OIFlowMultiTimeController → analyze()/lastDate()/symbols() (dashboard/UI)
 *   - CpMultiTimeOrderPlacementService → getSignalsForDate()     (auto-order cron)
 */
class OIFlowMultiTimeService
{
    private const TF              = '15min';
    private const PREV_CLOSE_TIME = '15:00:00';
    private const PREV_OPEN_TIME  = '09:15:00';
    private const OPT_TABLE       = 'cp_option_ohlc_15min';
    private const MAX_RANGE_DAYS  = 120;

    private const SNAPSHOT_TIMES = [
        '10:15:00' => '10:15',
        '11:15:00' => '11:15',
        '12:15:00' => '12:15',
    ];

    // ── Used by OIFlowMultiTimeController::lastDate() ───────────────────

    public function lastDate(): array
    {
        try {
            $config = $this->getActiveConfig();
            $today  = Carbon::today()->toDateString();
            if (!$config) {
                return ['success' => false, 'last_date' => $today, 'is_today' => true];
            }

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

            $lastDate = $lastDate ? Carbon::parse($lastDate)->toDateString() : $today;
            return ['success' => true, 'last_date' => $lastDate, 'is_today' => $lastDate === $today];

        } catch (\Exception $e) {
            Log::error('OIFlowMultiTimeService lastDate: ' . $e->getMessage());
            return ['success' => false, 'last_date' => Carbon::today()->toDateString(), 'is_today' => true];
        }
    }

    // ── Used by OIFlowMultiTimeController::getSymbols() ──────────────────

    public function symbols(): array
    {
        $config = $this->getActiveConfig();
        if (!$config) {
            return ['success' => true, 'symbols' => [], 'no_config' => true, 'message' => 'No active analysis config found.'];
        }
        return ['success' => true, 'symbols' => $this->getConfigSymbols($config->id)];
    }

    // ── Used by OIFlowMultiTimeController::analyze() — the dashboard ─────

    public function analyze(string $fromDate, string $toDate, array $symbolReq = [], string $actionFilter = ''): array
    {
        try {
            $spanDays = Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate));
            if ($spanDays > self::MAX_RANGE_DAYS) {
                return ['success' => false, 'message' => 'Date range too large — please pick ' . self::MAX_RANGE_DAYS . ' days or fewer.', 'data' => []];
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return ['success' => false, 'no_config' => true, 'message' => 'No active Analysis Config found. Go to Admin → Analysis Config.', 'data' => []];
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return ['success' => false, 'message' => 'No symbols configured.', 'data' => []];
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $tradeDates = DB::table(self::OPT_TABLE)
                ->where('analysis_config_id', $config->id)
                ->whereIn('base_symbol', $symbols)
                ->whereBetween('trade_date', [$fromDate, $toDate])
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()->orderBy('d')->pluck('d')->toArray();

            if (empty($tradeDates)) {
                return [
                    'success' => true, 'data' => [], 'date' => $fromDate,
                    'is_today' => $fromDate === Carbon::today()->toDateString(),
                    'available_symbols' => $configSymbols,
                    'snapshot_labels' => array_values(self::SNAPSHOT_TIMES),
                    'message' => 'No data found for the selected date' . ($fromDate === $toDate ? '' : ' range') . '.',
                ];
            }

            $prevDateMap = [];
            foreach ($tradeDates as $d) $prevDateMap[$d] = $this->getPreviousTradingDate($d);
            $prevDates = array_values(array_unique(array_values($prevDateMap)));

            $prevCloseMap = $this->fetchOiMap($config->id, $symbols, $prevDates, self::PREV_CLOSE_TIME);
            $prevOpenMap  = $this->fetchOiMap($config->id, $symbols, $prevDates, self::PREV_OPEN_TIME);

            $snapshotOiMaps = [];
            foreach (self::SNAPSHOT_TIMES as $time => $label) {
                $snapshotOiMaps[$label] = $this->fetchOiMap($config->id, $symbols, $tradeDates, $time);
            }

            $snapshotPriceMaps = [];
            foreach (self::SNAPSHOT_TIMES as $time => $label) {
                $snapshotPriceMaps[$label] = $this->fetchPriceMap($config->id, $symbols, $tradeDates, $time);
            }
            $labelsLatestFirst = array_reverse(array_values(self::SNAPSHOT_TIMES));

            $results = [];
            foreach ($tradeDates as $d) {
                $prevDate = $prevDateMap[$d];

                foreach ($symbols as $symbol) {
                    $prevCloseCe = $prevCloseMap["{$symbol}|{$prevDate}|CE"] ?? 0;
                    $prevClosePe = $prevCloseMap["{$symbol}|{$prevDate}|PE"] ?? 0;
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

                        if ($ceNow === null && $peNow === null) { $snapshots[$label] = null; continue; }
                        $rowHasData = true;
                        $ceNow = $ceNow ?? 0; $peNow = $peNow ?? 0;

                        $cePct = $prevCloseCe > 0 ? round((($ceNow - $prevCloseCe) / $prevCloseCe) * 100, 2) : 0;
                        $pePct = $prevClosePe > 0 ? round((($peNow - $prevClosePe) / $prevClosePe) * 100, 2) : 0;

                        $signal      = $this->calcOISignal($cePct, $pePct);
                        $tradeAction = match ($signal['sentiment']) {
                            'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                        };

                        $diff = round(abs($cePct - $pePct), 2);
                        $strengthRank = match (true) {
                            $diff > 40 => 'Rank 1', $diff > 25 => 'Rank 2',
                            $diff > 10 => 'Rank 3', $diff > 5  => 'Rank 4', default => 'Normal'
                        };

                        $angelSymbol = null;
                        if ($tradeAction !== 'WAIT') {
                            // priceRow for THIS snapshot/day is looked up right here — need the
                            // ATM strike + expiry to resolve the Angel symbol for this snapshot
                            $snapPriceRow = $snapshotPriceMaps[$label]["{$symbol}|{$d}"] ?? null;
                            if ($snapPriceRow && !empty($snapPriceRow->atm_strike) && !empty($snapPriceRow->expiry_date)) {
                                $optType = $tradeAction === 'BUY CE' ? 'CE' : 'PE';
                                $angelSymbol = $this->resolveAngelSymbol($symbol, $snapPriceRow->expiry_date, (float) $snapPriceRow->atm_strike, $optType);
                            }
                        }

                        $snapshots[$label] = [
                            'ce_oi' => $ceNow, 'pe_oi' => $peNow,
                            'ce_oi_pct' => $cePct, 'pe_oi_pct' => $pePct, 'oi_diff' => $diff,
                            'sentiment' => $signal['sentiment'], 'condition' => $signal['condition'],
                            'reason' => $signal['reason'], 'trade_action' => $tradeAction,
                            'strength_rank' => $strengthRank,
                            'angel_symbol' => $angelSymbol, // ← NEW
                        ];
                    }

                    if (!$rowHasData) continue;

                    if ($actionFilter) {
                        $matches = false;
                        foreach ($snapshots as $s) { if ($s && $s['trade_action'] === $actionFilter) { $matches = true; break; } }
                        if (!$matches) continue;
                    }

                    $priceRow = null;
                    foreach ($labelsLatestFirst as $label) {
                        if (isset($snapshotPriceMaps[$label]["{$symbol}|{$d}"])) {
                            $priceRow = $snapshotPriceMaps[$label]["{$symbol}|{$d}"];
                            break;
                        }
                    }

                    $results[] = [
                        'date' => $d, 'symbol' => $symbol,
                        'expiry' => $priceRow ? substr($priceRow->expiry_date, 0, 10) : null,
                        'atm_strike' => $priceRow?->atm_strike,
                        'fut_price' => $priceRow ? round((float) $priceRow->future_price, 2) : null,
                        'prev_close_ce_oi' => $prevCloseCe, 'prev_close_pe_oi' => $prevClosePe,
                        'prev_ce_trend' => $prevTrendCe, 'prev_pe_trend' => $prevTrendPe,
                        'snapshots' => $snapshots,
                    ];
                }
            }

            usort($results, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strcmp($a['symbol'], $b['symbol']));

            $stats = [];
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
                $stats[$label] = ['buy_ce' => $buyCe, 'buy_pe' => $buyPe, 'wait' => $wait, 'bullish' => $bull, 'bearish' => $bear];
            }

            $message = count($results) . ' record(s) found' . ($fromDate === $toDate ? " for {$fromDate}" : " from {$fromDate} to {$toDate}");

            return [
                'success' => true, 'data' => $results, 'total_records' => count($results),
                'stats' => $stats, 'snapshot_labels' => array_values(self::SNAPSHOT_TIMES),
                'message' => $message, 'available_symbols' => $configSymbols,
                'date' => $fromDate, 'from_date' => $fromDate, 'to_date' => $toDate,
                'is_today' => $fromDate === $toDate && $fromDate === Carbon::today()->toDateString(),
            ];

        } catch (\Exception $e) {
            Log::error('OIFlowMultiTimeService analyze: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'data' => []];
        }
    }

    // ── Used by CpMultiTimeOrderPlacementService — the auto-order cron ───

    /**
     * @return array<int, array{symbol:string,date:string,signal_time:string,sentiment:string,trade_action:string,option_price:float,underlying_price:float}>
     */
    public function getSignalsForDate(string $date): array
    {
        try {
            $config = $this->getActiveConfig();
            if (!$config) return [];

            $symbols = $this->getConfigSymbols($config->id);
            if (empty($symbols)) return [];

            $prevDate = $this->getPreviousTradingDate($date);

            $prevCloseMap = $this->fetchOiMap($config->id, $symbols, [$prevDate], self::PREV_CLOSE_TIME);
            if (empty($prevCloseMap)) return [];

            $results = [];

            foreach (self::SNAPSHOT_TIMES as $time => $label) {
                $oiMap = $this->fetchOiMap($config->id, $symbols, [$date], $time);
                if (empty($oiMap)) continue;

                $priceMap = $this->fetchPriceMap($config->id, $symbols, [$date], $time);

                foreach ($symbols as $symbol) {
                    $ceNow = $oiMap["{$symbol}|{$date}|CE"] ?? null;
                    $peNow = $oiMap["{$symbol}|{$date}|PE"] ?? null;
                    if ($ceNow === null && $peNow === null) continue;
                    $ceNow = $ceNow ?? 0; $peNow = $peNow ?? 0;

                    $prevCloseCe = $prevCloseMap["{$symbol}|{$prevDate}|CE"] ?? 0;
                    $prevClosePe = $prevCloseMap["{$symbol}|{$prevDate}|PE"] ?? 0;
                    if ($prevCloseCe === 0 && $prevClosePe === 0) continue;

                    $cePct = $prevCloseCe > 0 ? round((($ceNow - $prevCloseCe) / $prevCloseCe) * 100, 2) : 0;
                    $pePct = $prevClosePe > 0 ? round((($peNow - $prevClosePe) / $prevClosePe) * 100, 2) : 0;

                    $signal      = $this->calcOISignal($cePct, $pePct);
                    $tradeAction = match ($signal['sentiment']) {
                        'BULLISH' => 'BUY CE', 'BEARISH' => 'BUY PE', default => 'WAIT'
                    };
                    if ($tradeAction === 'WAIT') continue;

                    $priceRow = $priceMap["{$symbol}|{$date}"] ?? null;
                    if (!$priceRow || empty($priceRow->future_price)) continue;

                    $optionPrice = $this->fetchOptionPremium($config->id, $symbol, $date, $time, $tradeAction === 'BUY CE' ? 'CE' : 'PE');
                    if ($optionPrice === null) continue;

                    $results[] = [
                        'symbol'           => $symbol,
                        'date'             => $date,
                        'signal_time'      => $label,
                        'sentiment'        => $signal['sentiment'],
                        'trade_action'     => $tradeAction,
                        'option_price'     => round((float) $optionPrice, 2),
                        'underlying_price' => round((float) $priceRow->future_price, 2),
                    ];
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('OIFlowMultiTimeService getSignalsForDate: ' . $e->getMessage());
            return [];
        }
    }

    // ── Shared private helpers ────────────────────────────────────────────

    private function buildupTag(?float $openOi, ?float $closeOi): string
    {
        if (!$openOi || !$closeOi || $openOi <= 0) return '-';
        $chg = (($closeOi - $openOi) / $openOi) * 100;
        return $chg > 1 ? 'Buildup' : ($chg < -1 ? 'Unwinding' : 'Flat');
    }

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
            ->select(['base_symbol', 'instrument_type', DB::raw('DATE(trade_date) as trade_day'), DB::raw('SUM(oi) as total_oi')])
            ->groupBy('base_symbol', 'instrument_type', DB::raw('DATE(trade_date)'))
            ->orderBy('base_symbol')
            ->each(function ($r) use (&$map) {
                $map["{$r->base_symbol}|{$r->trade_day}|{$r->instrument_type}"] = (int) $r->total_oi;
            });
        return $map;
    }

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
            ->select(['base_symbol', DB::raw('DATE(trade_date) as trade_day'), 'atm_strike', 'future_price', 'expiry_date'])
            ->orderBy('base_symbol')
            ->each(function ($r) use (&$map) {
                $map["{$r->base_symbol}|{$r->trade_day}"] = $r;
            });
        return $map;
    }

    private function fetchOptionPremium(int $configId, string $symbol, string $date, string $time, string $type): ?float
    {
        $row = DB::table(self::OPT_TABLE)
            ->where('analysis_config_id', $configId)
            ->where('base_symbol', $symbol)
            ->whereDate('trade_date', $date)
            ->whereRaw('TIME(interval_time) = ?', [$time])
            ->where('instrument_type', $type)
            ->where('strike_position', 'ATM')
            ->where('is_missing', false)
            ->value('close');

        return $row !== null ? (float) $row : null;
    }

    private function getActiveConfig(): ?object
    {
        return DB::table('analysis_configs')->where('time_frame', self::TF)->where('is_active', 1)->first();
    }

    private function getConfigSymbols(int $configId): array
    {
        return DB::table('analysis_config_symbols')
            ->join('symbol_lists', 'symbol_lists.id', '=', 'analysis_config_symbols.symbol_list_id')
            ->where('analysis_config_symbols.analysis_config_id', $configId)
            ->pluck('symbol_lists.symbol')->toArray();
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

    /**
     * Resolves the Angel-formatted tradable symbol for a CE/PE at a given
     * strike+expiry — same matching logic as AngelBrokerService::matchAngelOption(),
     * duplicated here (read-only, UI-only) so the dashboard can show what the
     * bot will actually buy without depending on the broker service directly.
    */
    private function resolveAngelSymbol(string $baseSymbol, string $expiryDate, float $strike, string $optionType): ?string
    {
        $expiry = \Carbon\Carbon::parse($expiryDate)->format('Y-m-d');
        $instrumentType = in_array($baseSymbol, ['NIFTY', 'BANKNIFTY', 'SENSEX']) ? 'OPTIDX' : 'OPTSTK';

        return DB::table('angel_api_instruments')
            ->where('name', strtoupper($baseSymbol))
            ->where('expiry', $expiry)
            ->where('exch_seg', 'NFO')
            ->where('instrumenttype', $instrumentType)
            ->where('symbol_name', 'LIKE', '%' . strtoupper($optionType))
            ->where(function ($q) use ($strike) {
                $q->whereRaw('ABS(CAST(strike AS DECIMAL(15,2)) - ?) < 0.01', [$strike])
                ->orWhereRaw('ABS(CAST(strike AS DECIMAL(15,2)) - ?) < 0.01', [$strike * 100]);
            })
            ->value('symbol_name');
    }
}