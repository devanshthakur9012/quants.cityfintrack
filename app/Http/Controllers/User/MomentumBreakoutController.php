<?php
// FILE: app/Http/Controllers/User/MomentumBreakoutController.php
namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Momentum Breakout Scanner
 * Instruments : Stock EQ | FUT | Option (ATM CE proxy)
 * Timeframe   : 15min ONLY
 *
 * LOGIC:
 *   Day Open = first candle open (09:15)
 *   Scan every candle in sequence:
 *     close ≥ open × (1 + threshold/100) → BUY CE
 *     close ≤ open × (1 − threshold/100) → BUY PE
 *   First trigger per symbol per day wins — one signal per day.
 */
class MomentumBreakoutController extends Controller
{
    private const TF = '15min';

    private const TABLES = [
        'stock'  => 'cp_stock_ohlc_15min',
        'fut'    => 'cp_fut_ohlc_15min',
        'option' => 'cp_option_ohlc_15min',
    ];

    // ── Page ──────────────────────────────────────────────────────────────────

    public function index()
    {
        $pageTitle = 'Momentum Breakout Scanner';
        return view(activeTemplate() . 'user.momentum-breakout.index', compact('pageTitle'));
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
                'message'   => 'No active 15min config found.',
            ]);
        }

        $symbols = $this->getConfigSymbols($config->id);
        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // ── Main Scan API ─────────────────────────────────────────────────────────

    public function scan(Request $request): JsonResponse
    {
        try {
            $instrument  = $this->resolveInstrument($request);
            $fromDate    = $request->get('from_date');
            $toDate      = $request->get('to_date');
            $symbolReq   = array_filter((array) $request->get('symbols', []));
            $threshold   = max(0.1, (float) $request->get('threshold', 1.0));
            $showNoTrade = (bool) $request->get('show_no_trade', false);

            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates.',
                    'data'    => [],
                ]);
            }

            $config = $this->getActiveConfig();
            if (!$config) {
                return response()->json([
                    'success'   => false,
                    'no_config' => true,
                    'message'   => 'No active 15min Analysis Config. Go to Admin → Analysis Config.',
                    'data'      => [],
                ]);
            }

            $configSymbols = $this->getConfigSymbols($config->id);
            if (empty($configSymbols)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No symbols configured.',
                    'data'    => [],
                ]);
            }

            $symbols = !empty($symbolReq)
                ? array_values(array_intersect($symbolReq, $configSymbols))
                : $configSymbols;

            $results = match ($instrument) {
                'stock'  => $this->scanStock($config->id, $fromDate, $toDate, $symbols, $threshold, $showNoTrade),
                'fut'    => $this->scanFut($config->id, $fromDate, $toDate, $symbols, $threshold, $showNoTrade),
                'option' => $this->scanOption($config->id, $fromDate, $toDate, $symbols, $threshold, $showNoTrade),
            };

            // Sort: date ASC, signal time ASC, NO_TRADE last
            usort($results, function ($a, $b) {
                $d = strcmp($a['date'], $b['date']);
                if ($d !== 0) return $d;
                if ($a['signal'] === 'NO_TRADE' && $b['signal'] !== 'NO_TRADE') return  1;
                if ($b['signal'] === 'NO_TRADE' && $a['signal'] !== 'NO_TRADE') return -1;
                return strcmp((string) $a['signal_time'], (string) $b['signal_time']);
            });

            $signals  = array_filter($results, fn($r) => $r['signal'] !== 'NO_TRADE');
            $noTrades = array_filter($results, fn($r) => $r['signal'] === 'NO_TRADE');

            return response()->json([
                'success'           => true,
                'data'              => array_values($results),
                'total_records'     => count($results),
                'total_signals'     => count($signals),
                'buy_ce_count'      => count(array_filter($signals, fn($r) => $r['signal'] === 'BUY_CE')),
                'buy_pe_count'      => count(array_filter($signals, fn($r) => $r['signal'] === 'BUY_PE')),
                'no_trade_count'    => count($noTrades),
                'message'           => count($signals) . ' signal(s) across ' . count($results) . ' records',
                'instrument'        => strtoupper($instrument),
                'threshold'         => $threshold,
                'available_symbols' => $configSymbols,
            ]);

        } catch (\Exception $e) {
            Log::error('MomentumBreakout scan: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => [],
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SCANNERS
    // ══════════════════════════════════════════════════════════════════════════

    private function scanStock(int $configId, string $from, string $to, array $symbols, float $threshold, bool $showNT): array
    {
        $allCandles = DB::table(self::TABLES['stock'])
            ->where('analysis_config_id', $configId)
            ->whereIn('symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get(['symbol', 'trading_symbol', 'trade_date', 'interval_time',
                   'open', 'high', 'low', 'close', 'volume'])
            ->toArray();

        return $this->processCandles($allCandles, 'symbol', 'stock', $threshold, $showNT);
    }

    private function scanFut(int $configId, string $from, string $to, array $symbols, float $threshold, bool $showNT): array
    {
        $allCandles = DB::table(self::TABLES['fut'])
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get(['base_symbol as symbol', 'trading_symbol', 'trade_date', 'interval_time',
                   'expiry_date', 'atm_strike', 'open', 'high', 'low', 'close', 'volume', 'oi'])
            ->toArray();

        return $this->processCandles($allCandles, 'symbol', 'fut', $threshold, $showNT);
    }

    private function scanOption(int $configId, string $from, string $to, array $symbols, float $threshold, bool $showNT): array
    {
        // Use ATM CE future_price as underlying proxy for breakout detection
        $allCandles = DB::table(self::TABLES['option'])
            ->where('analysis_config_id', $configId)
            ->whereIn('base_symbol', $symbols)
            ->whereBetween('trade_date', [$from, $to])
            ->where('is_missing', false)
            ->where('strike_position', 'ATM')
            ->where('instrument_type', 'CE')
            ->orderBy('trade_date')->orderBy('interval_time')
            ->get(['base_symbol as symbol', 'trading_symbol', 'trade_date', 'interval_time',
                   'expiry_date', 'atm_strike', 'future_price',
                   'open', 'high', 'low', 'close', 'volume', 'oi'])
            ->toArray();

        return $this->processCandles($allCandles, 'symbol', 'option', $threshold, $showNT);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CORE ENGINE
    // ══════════════════════════════════════════════════════════════════════════

    private function processCandles(array $allCandles, string $symKey, string $instrument, float $threshold, bool $showNT): array
    {
        // Group by [symbol][date]
        $grouped = [];
        foreach ($allCandles as $c) {
            $date = substr($c->trade_date, 0, 10);
            $grouped[$c->symbol][$date][] = $c;
        }

        $results = [];
        foreach ($grouped as $symbol => $days) {
            foreach ($days as $date => $candles) {
                $result = $instrument === 'option'
                    ? $this->detectByFutPrice($candles, $symbol, $date, $threshold)
                    : $this->detectBreakout($candles, $symbol, $date, $threshold, $instrument);

                if ($result['signal'] === 'NO_TRADE' && !$showNT) continue;
                $results[] = $result;
            }
        }
        return $results;
    }

    private function detectBreakout(array $candles, string $symbol, string $date, float $threshold, string $instrument): array
    {
        if (empty($candles)) return $this->noTrade($symbol, $date, $instrument);

        $dayOpen = (float) $candles[0]->open;
        if ($dayOpen <= 0) return $this->noTrade($symbol, $date, $instrument);

        $dayHigh  = max(array_map(fn($c) => (float) $c->high, $candles));
        $dayLow   = min(array_map(fn($c) => (float) $c->low,  $candles));
        $lastClose= (float) end($candles)->close;

        foreach ($candles as $c) {
            $close  = (float) $c->close;
            $chgPct = (($close - $dayOpen) / $dayOpen) * 100;

            if ($chgPct >= $threshold) {
                return $this->buildResult($c, $symbol, $date, 'BUY_CE', $chgPct, $dayOpen, $dayHigh, $dayLow, $lastClose, $instrument);
            }
            if ($chgPct <= -$threshold) {
                return $this->buildResult($c, $symbol, $date, 'BUY_PE', $chgPct, $dayOpen, $dayHigh, $dayLow, $lastClose, $instrument);
            }
        }

        return $this->noTrade($symbol, $date, $instrument, $dayOpen, $dayHigh, $dayLow, $lastClose,
            $candles[0]->trading_symbol ?? null,
            isset($candles[0]->expiry_date) ? substr($candles[0]->expiry_date, 0, 10) : null);
    }

    private function detectByFutPrice(array $candles, string $symbol, string $date, float $threshold): array
    {
        if (empty($candles)) return $this->noTrade($symbol, $date, 'option');

        $dayOpen = (float) ($candles[0]->future_price ?? $candles[0]->open);
        if ($dayOpen <= 0) $dayOpen = (float) $candles[0]->open;
        if ($dayOpen <= 0) return $this->noTrade($symbol, $date, 'option');

        $futPrices = array_map(fn($c) => (float) ($c->future_price ?? $c->close), $candles);
        $dayHigh   = max($futPrices);
        $dayLow    = min($futPrices);
        $lastClose = (float) (end($candles)->future_price ?? end($candles)->close);

        foreach ($candles as $c) {
            $futPrice = (float) ($c->future_price ?? $c->close);
            $chgPct   = (($futPrice - $dayOpen) / $dayOpen) * 100;

            if ($chgPct >= $threshold) {
                return $this->buildResult($c, $symbol, $date, 'BUY_CE', $chgPct, $dayOpen, $dayHigh, $dayLow, $lastClose, 'option');
            }
            if ($chgPct <= -$threshold) {
                return $this->buildResult($c, $symbol, $date, 'BUY_PE', $chgPct, $dayOpen, $dayHigh, $dayLow, $lastClose, 'option');
            }
        }

        return $this->noTrade($symbol, $date, 'option', $dayOpen, $dayHigh, $dayLow, $lastClose,
            $candles[0]->trading_symbol ?? null,
            isset($candles[0]->expiry_date) ? substr($candles[0]->expiry_date, 0, 10) : null);
    }

    // ── Result builders ───────────────────────────────────────────────────────

    private function buildResult($c, string $symbol, string $date, string $signal, float $chgPct,
        float $dayOpen, float $dayHigh, float $dayLow, float $lastClose, string $instrument): array
    {
        return [
            'date'         => $date,
            'symbol'       => $symbol,
            'instrument'   => strtoupper($instrument),
            'trading_sym'  => $c->trading_symbol ?? null,
            'expiry'       => isset($c->expiry_date) ? substr($c->expiry_date, 0, 10) : null,
            'atm_strike'   => $c->atm_strike ?? null,
            'signal'       => $signal,
            'signal_time'  => substr($c->interval_time, 11, 5),
            'day_open'     => round($dayOpen, 2),
            'signal_price' => round((float) $c->close, 2),
            'change_pct'   => round($chgPct, 2),
            'day_high'     => round($dayHigh, 2),
            'day_low'      => round($dayLow, 2),
            'last_close'   => round($lastClose, 2),
            'volume'       => (int) ($c->volume ?? 0),
            'oi'           => isset($c->oi) ? (int) $c->oi : null,
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
            'day_open'     => $dayOpen  ? round($dayOpen,  2) : null,
            'signal_price' => null,
            'change_pct'   => null,
            'day_high'     => $dayHigh  ? round($dayHigh,  2) : null,
            'day_low'      => $dayLow   ? round($dayLow,   2) : null,
            'last_close'   => $lastClose? round($lastClose, 2) : null,
            'volume'       => null,
            'oi'           => null,
        ];
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

    private function resolveInstrument(Request $request): string
    {
        $inst = strtolower(trim($request->get('instrument', 'stock')));
        return in_array($inst, ['stock', 'fut', 'option']) ? $inst : 'stock';
    }
}