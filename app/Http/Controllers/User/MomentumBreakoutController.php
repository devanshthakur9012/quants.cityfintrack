<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║   Momentum Breakout Scanner                                      ║
 * ║   Instruments : Stock EQ | FUT | Option (ATM CE/PE)            ║
 * ║   Timeframes  : 15min | 30min | 1hr                             ║
 * ║                                                                  ║
 * ║   CORE LOGIC:                                                    ║
 * ║   For each symbol, each trading day:                            ║
 * ║     • Day Open = first candle's open price (09:15 slot)        ║
 * ║     • Scan every candle in sequence                             ║
 * ║     • close ≥ open × (1 + threshold/100) → BUY CE signal       ║
 * ║     • close ≤ open × (1 − threshold/100) → BUY PE signal       ║
 * ║     • First trigger wins — one signal per symbol per day        ║
 * ║                                                                  ║
 * ║   P/L CALCULATION (from cp_option_ohlc_ table):                 ║
 * ║     • Buy price  = ATM option close at signal candle time       ║
 * ║     • Exit price = ATM option close at user-selected exit time  ║
 * ║     • Best price = highest ATM option candle high (signal→EOD) ║
 * ║     • P/L = (exit − buy) × lot_size                            ║
 * ║                                                                  ║
 * ║   Tables used:                                                   ║
 * ║     cp_stock_ohlc_{tf}   — Stock EQ candles                     ║
 * ║     cp_fut_ohlc_{tf}     — Futures candles                      ║
 * ║     cp_option_ohlc_{tf}  — Options (CE/PE) candles              ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */
class MomentumBreakoutController extends Controller
{
    private const TIMEFRAMES = ['15min', '30min', '1hr'];

    private const TABLES = [
        'stock'  => 'cp_stock_ohlc',
        'fut'    => 'cp_fut_ohlc',
        'option' => 'cp_option_ohlc',
    ];

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Momentum Breakout Scanner';
        return view($this->activeTemplate . 'user.momentum-breakout.index', compact('pageTitle'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SYMBOLS API  — only config-scoped symbols for this timeframe
    // ══════════════════════════════════════════════════════════════════════════

    public function getSymbols(Request $request)
    {
        $timeframe = $this->resolveTimeframe($request);
        $config    = $this->getActiveConfig($timeframe);

        if (!$config) {
            return response()->json(['success' => true, 'symbols' => [], 'no_config' => true,
                'message' => "No active config for [{$timeframe}]."]);
        }

        $symbols = $this->getConfigSymbols($config->id);
        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // MAIN SCAN API
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET /momentum-breakout/scan
     *
     * Scans candles across a date range for percentage-move triggers.
     * Returns one result per symbol per trading day (first trigger wins).
     * Days without a trigger return signal = NO_TRADE.
     */
    public function scan(Request $request)
    {
        try {
            $timeframe  = $this->resolveTimeframe($request);
            $instrument = $this->resolveInstrument($request);
            $fromDate   = $request->get('from_date');
            $toDate     = $request->get('to_date');
            $symbolReq  = array_filter((array)$request->get('symbols', []));
            $threshold  = max(0.1, (float)$request->get('threshold', 1.0));
            $showNoTrade = (bool)$request->get('show_no_trade', false);

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false,
                    'message' => 'Please select both From and To dates.', 'data' => []]);
            }

            $config = $this->getActiveConfig($timeframe);
            if (!$config) {
                return response()->json(['success' => false, 'no_config' => true,
                    'message' => "No active Analysis Config for [{$timeframe}]. Go to Admin → Analysis Config.",
                    'data' => []]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json(['success' => false,
                    'message' => 'No symbols configured for this timeframe.', 'data' => []]);
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            // Route to correct instrument scanner
            $results = match ($instrument) {
                'stock'  => $this->scanStock($config->id, $timeframe, $fromDate, $toDate, $symbols, $threshold, $showNoTrade),
                'fut'    => $this->scanFut($config->id, $timeframe, $fromDate, $toDate, $symbols, $threshold, $showNoTrade),
                'option' => $this->scanOption($config->id, $timeframe, $fromDate, $toDate, $symbols, $threshold, $showNoTrade),
            };

            // Sort: date ASC, then signal time ASC, NO_TRADE last
            usort($results, function ($a, $b) {
                $dateCmp = strcmp($a['date'], $b['date']);
                if ($dateCmp !== 0) return $dateCmp;
                if ($a['signal'] === 'NO_TRADE' && $b['signal'] !== 'NO_TRADE') return 1;
                if ($b['signal'] === 'NO_TRADE' && $a['signal'] !== 'NO_TRADE') return -1;
                return strcmp((string)$a['signal_time'], (string)$b['signal_time']);
            });

            $signals   = array_filter($results, fn($r) => $r['signal'] !== 'NO_TRADE');
            $noTrades  = array_filter($results, fn($r) => $r['signal'] === 'NO_TRADE');

            return response()->json([
                'success'            => true,
                'data'               => array_values($results),
                'total_records'      => count($results),
                'total_signals'      => count($signals),
                'buy_ce_count'       => count(array_filter($signals, fn($r) => $r['signal'] === 'BUY_CE')),
                'buy_pe_count'       => count(array_filter($signals, fn($r) => $r['signal'] === 'BUY_PE')),
                'no_trade_count'     => count($noTrades),
                'message'            => count($signals) . ' signal(s) across ' . count($results) . ' day-symbol records',
                'timeframe'          => $timeframe,
                'instrument'         => strtoupper($instrument),
                'threshold'          => $threshold,
                'available_symbols'  => $configSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('MomentumBreakout scan: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STOCK SCANNER
    // ══════════════════════════════════════════════════════════════════════════

    private function scanStock(
        int $configId, string $tf, string $from, string $to,
        array $symbols, float $threshold, bool $showNoTrade
    ): array {
        $table = self::TABLES['stock'] . '_' . $tf;

        // Load all candles for all symbols in the date range in one query
        $allCandles = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->orderBy('trade_date')
            ->orderBy('interval_time')
            ->get(['symbol', 'trading_symbol', 'trade_date', 'interval_time',
                   'open', 'high', 'low', 'close', 'volume'])
            ->toArray();

        // Group: [symbol][date] => candles[]
        $grouped = [];
        foreach ($allCandles as $c) {
            $date = substr(is_string($c->trade_date) ? $c->trade_date : Carbon::parse($c->trade_date)->toDateString(), 0, 10);
            $grouped[$c->symbol][$date][] = $c;
        }

        $results = [];
        foreach ($symbols as $symbol) {
            if (!isset($grouped[$symbol])) continue;
            foreach ($grouped[$symbol] as $date => $candles) {
                $result = $this->detectBreakout($candles, $symbol, $date, $threshold, 'stock');
                if ($result['signal'] === 'NO_TRADE' && !$showNoTrade) continue;
                $results[] = $result;
            }
        }
        return $results;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FUT SCANNER
    // ══════════════════════════════════════════════════════════════════════════

    private function scanFut(
        int $configId, string $tf, string $from, string $to,
        array $symbols, float $threshold, bool $showNoTrade
    ): array {
        $table = self::TABLES['fut'] . '_' . $tf;

        $allCandles = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->orderBy('trade_date')
            ->orderBy('interval_time')
            ->get(['base_symbol as symbol', 'trading_symbol', 'trade_date', 'interval_time',
                   'expiry_date', 'atm_strike', 'open', 'high', 'low', 'close', 'volume', 'oi'])
            ->toArray();

        $grouped = [];
        foreach ($allCandles as $c) {
            $date = substr(is_string($c->trade_date) ? $c->trade_date : Carbon::parse($c->trade_date)->toDateString(), 0, 10);
            $grouped[$c->symbol][$date][] = $c;
        }

        $results = [];
        foreach ($symbols as $symbol) {
            if (!isset($grouped[$symbol])) continue;
            foreach ($grouped[$symbol] as $date => $candles) {
                $result = $this->detectBreakout($candles, $symbol, $date, $threshold, 'fut');
                if ($result['signal'] === 'NO_TRADE' && !$showNoTrade) continue;
                $results[] = $result;
            }
        }
        return $results;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OPTION SCANNER (scans underlying ATM CE/PE candle price moves)
    // ══════════════════════════════════════════════════════════════════════════

    private function scanOption(
        int $configId, string $tf, string $from, string $to,
        array $symbols, float $threshold, bool $showNoTrade
    ): array {
        $table = self::TABLES['option'] . '_' . $tf;

        // Use ATM CE candles to detect underlying move (CE price rises when underlying rises)
        // We scan both CE and PE; signal is which direction the underlying moved
        $allCandles = DB::table($table)
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->where('strike_position', 'ATM')
            ->where('instrument_type', 'CE') // use CE as proxy for underlying direction
            ->orderBy('trade_date')
            ->orderBy('interval_time')
            ->get(['base_symbol as symbol', 'trading_symbol', 'trade_date', 'interval_time',
                   'expiry_date', 'atm_strike', 'future_price',
                   'open', 'high', 'low', 'close', 'volume', 'oi'])
            ->toArray();

        $grouped = [];
        foreach ($allCandles as $c) {
            $date = substr(is_string($c->trade_date) ? $c->trade_date : Carbon::parse($c->trade_date)->toDateString(), 0, 10);
            $grouped[$c->symbol][$date][] = $c;
        }

        $results = [];
        foreach ($symbols as $symbol) {
            if (!isset($grouped[$symbol])) continue;
            foreach ($grouped[$symbol] as $date => $candles) {
                // For options, scan future_price (underlying) for the % move
                $result = $this->detectBreakoutByFutPrice($candles, $symbol, $date, $threshold);
                if ($result['signal'] === 'NO_TRADE' && !$showNoTrade) continue;
                $results[] = $result;
            }
        }
        return $results;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // BREAKOUT DETECTION ENGINE
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Scan candles for a single symbol×date.
     * Uses candle CLOSE price vs day OPEN to detect % threshold breach.
     * Returns first trigger (BUY_CE / BUY_PE) or NO_TRADE.
     */
    private function detectBreakout(array $candles, string $symbol, string $date, float $threshold, string $instrument): array
    {
        if (empty($candles)) return $this->noTrade($symbol, $date, $instrument);

        // Day open = first candle open
        $dayOpen = (float)$candles[0]->open;
        if ($dayOpen <= 0) return $this->noTrade($symbol, $date, $instrument);

        $dayHigh = 0; $dayLow = PHP_INT_MAX;
        foreach ($candles as $c) {
            if ((float)$c->high > $dayHigh) $dayHigh = (float)$c->high;
            if ((float)$c->low  < $dayLow)  $dayLow  = (float)$c->low;
        }
        $lastClose = (float)end($candles)->close;

        foreach ($candles as $c) {
            $close  = (float)$c->close;
            $chgPct = (($close - $dayOpen) / $dayOpen) * 100;
            $time   = substr($c->interval_time, 11, 5);

            if ($chgPct >= $threshold) {
                return $this->buildResult($c, $symbol, $date, 'BUY_CE', $chgPct, $dayOpen,
                    $dayHigh, $dayLow, $lastClose, $instrument);
            }
            if ($chgPct <= -$threshold) {
                return $this->buildResult($c, $symbol, $date, 'BUY_PE', $chgPct, $dayOpen,
                    $dayHigh, $dayLow, $lastClose, $instrument);
            }
        }

        return $this->noTrade($symbol, $date, $instrument, $dayOpen, $dayHigh, $dayLow, $lastClose,
            $candles[0]->trading_symbol ?? null,
            isset($candles[0]->expiry_date) ? substr($candles[0]->expiry_date, 0, 10) : null);
    }

    /**
     * For option scanner: uses future_price column to determine underlying move.
     * CE % move is based on ATM CE price change (option itself, not underlying).
     */
    private function detectBreakoutByFutPrice(array $candles, string $symbol, string $date, float $threshold): array
    {
        if (empty($candles)) return $this->noTrade($symbol, $date, 'option');

        // Use future_price as underlying proxy
        $dayOpen = (float)($candles[0]->future_price ?? $candles[0]->open);
        if ($dayOpen <= 0) {
            // Fallback to ATM CE open
            $dayOpen = (float)$candles[0]->open;
        }
        if ($dayOpen <= 0) return $this->noTrade($symbol, $date, 'option');

        $dayHigh = 0; $dayLow = PHP_INT_MAX;
        foreach ($candles as $c) {
            $fp = (float)($c->future_price ?? $c->close);
            if ($fp > $dayHigh) $dayHigh = $fp;
            if ($fp < $dayLow)  $dayLow  = $fp;
        }
        $lastFutPrice = (float)(end($candles)->future_price ?? end($candles)->close);

        foreach ($candles as $c) {
            $futPrice = (float)($c->future_price ?? $c->close);
            $chgPct   = (($futPrice - $dayOpen) / $dayOpen) * 100;

            if ($chgPct >= $threshold) {
                return $this->buildResult($c, $symbol, $date, 'BUY_CE', $chgPct, $dayOpen,
                    $dayHigh, $dayLow, $lastFutPrice, 'option');
            }
            if ($chgPct <= -$threshold) {
                return $this->buildResult($c, $symbol, $date, 'BUY_PE', $chgPct, $dayOpen,
                    $dayHigh, $dayLow, $lastFutPrice, 'option');
            }
        }

        return $this->noTrade($symbol, $date, 'option', $dayOpen, $dayHigh, $dayLow, $lastFutPrice,
            $candles[0]->trading_symbol ?? null,
            isset($candles[0]->expiry_date) ? substr($candles[0]->expiry_date, 0, 10) : null);
    }

    // ── Result builders ───────────────────────────────────────────────────────

    private function buildResult($candle, string $symbol, string $date, string $signal,
        float $chgPct, float $dayOpen, float $dayHigh, float $dayLow,
        float $lastClose, string $instrument): array
    {
        return [
            'date'          => $date,
            'symbol'        => $symbol,
            'instrument'    => strtoupper($instrument),
            'trading_sym'   => $candle->trading_symbol ?? null,
            'expiry'        => isset($candle->expiry_date) ? substr($candle->expiry_date, 0, 10) : null,
            'atm_strike'    => $candle->atm_strike ?? null,
            'signal'        => $signal,
            'signal_time'   => substr($candle->interval_time, 11, 5),
            'day_open'      => round($dayOpen, 2),
            'signal_price'  => round((float)$candle->close, 2),
            'change_pct'    => round($chgPct, 2),
            'day_high'      => round($dayHigh, 2),
            'day_low'       => round($dayLow, 2),
            'last_close'    => round($lastClose, 2),
            'volume'        => (int)($candle->volume ?? 0),
            'oi'            => isset($candle->oi) ? (int)$candle->oi : null,
        ];
    }

    private function noTrade(string $symbol, string $date, string $instrument,
        float $dayOpen = 0, float $dayHigh = 0, float $dayLow = 0, float $lastClose = 0,
        ?string $tradingSym = null, ?string $expiry = null): array
    {
        return [
            'date'         => $date,
            'symbol'       => $symbol,
            'instrument'   => strtoupper($instrument),
            'trading_sym'  => $tradingSym,
            'expiry'       => $expiry,
            'atm_strike'   => null,
            'signal'       => 'NO_TRADE',
            'signal_time'  => null,
            'day_open'     => $dayOpen ? round($dayOpen, 2) : null,
            'signal_price' => null,
            'change_pct'   => null,
            'day_high'     => $dayHigh ? round($dayHigh, 2) : null,
            'day_low'      => $dayLow  ? round($dayLow,  2) : null,
            'last_close'   => $lastClose ? round($lastClose, 2) : null,
            'volume'       => null,
            'oi'           => null,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Find closest row by HH:MM key.
     * direction='after'  → at or after target time (for buy price at signal)
     * direction='before' → at or before target time (for exit price)
     */
    private function findClosestRow(array $rowByTime, string $targetHHMM, string $direction): ?object
    {
        ksort($rowByTime);

        if ($direction === 'after') {
            foreach ($rowByTime as $hm => $row) {
                if ($hm >= $targetHHMM) return $row;
            }
            // Fallback: last available
            return !empty($rowByTime) ? end($rowByTime) : null;
        }

        // before or equal
        $best = null;
        foreach ($rowByTime as $hm => $row) {
            if ($hm <= $targetHHMM) $best = $row;
        }
        return $best ?? (!empty($rowByTime) ? reset($rowByTime) : null);
    }

    /**
     * Resolve lot size from zerodha_instruments for the ATM option.
     */
    private function resolveLotSize(string $baseSymbol, ?string $tradingSymbol): int
    {
        if ($tradingSymbol) {
            $lotSize = DB::table('zerodha_instruments')
                ->where('trading_symbol', $tradingSymbol)
                ->value('lot_size');
            if ($lotSize) return (int)$lotSize;
        }

        // Fallback by name
        $lotSize = DB::table('zerodha_instruments')
            ->where('name', $baseSymbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->value('lot_size');

        return $lotSize ? (int)$lotSize : 1;
    }

    private function getActiveConfig(string $timeframe): ?object
    {
        return DB::table('analysis_configs')
            ->where('time_frame', $timeframe)
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

    private function resolveTimeframe(Request $request): string
    {
        $tf = strtolower(trim($request->get('timeframe', '15min')));
        return in_array($tf, self::TIMEFRAMES) ? $tf : '15min';
    }

    private function resolveInstrument(Request $request): string
    {
        $inst = strtolower(trim($request->get('instrument', 'stock')));
        return in_array($inst, ['stock', 'fut', 'option']) ? $inst : 'stock';
    }
}