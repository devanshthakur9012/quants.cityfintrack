<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AllSymbolOIAnalysisController
 *
 * Same OI logic as AuroOIAnalysisController but works for ANY symbol.
 * Returns only BUY CE / BUY PE rows — WAIT rows are suppressed.
 * Supports:
 *   - from_date / to_date  (required, date range)
 *   - symbol               (optional; if omitted, runs for ALL symbols in DB)
 */
class AllSymbolOIAnalysisController extends Controller
{
    private const EOD_TIME        = '14:45:00';
    private const PRV_TIME        = '15:00:00';
    private const TRADE_THRESHOLD = 3.0;

    // ── PAGE ─────────────────────────────────────────────────────────
    public function index()
    {
        $pageTitle = 'OI Analysis — All Symbols';

        // Get all unique base symbols that have FUT data (gives us the universe)
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->select('base_symbol')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol');

        return view($this->activeTemplate . 'user.oi.all-symbol-analysis', compact('pageTitle', 'symbols'));
    }

    // ── MAIN ENDPOINT ─────────────────────────────────────────────────
    public function analyze(Request $request)
    {
        try {
            $from   = $request->get('from_date');
            $to     = $request->get('to_date');
            $symbol = $request->get('symbol', ''); // blank = all symbols

            if (!$from || !$to) {
                return response()->json(['success' => false, 'message' => 'Select both dates', 'data' => []]);
            }

            // ── Determine which symbols to run ───────────────────────
            $symbolQuery = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $from)
                ->whereDate('trade_date', '<=', $to)
                ->select('base_symbol')
                ->distinct();

            if ($symbol) {
                $symbolQuery->where('base_symbol', strtoupper(trim($symbol)));
            }

            $symbols = $symbolQuery->orderBy('base_symbol')->pluck('base_symbol')->toArray();

            if (empty($symbols)) {
                return response()->json(['success' => false, 'message' => 'No data found for this range', 'data' => []]);
            }

            $results = [];

            foreach ($symbols as $sym) {
                // Trade dates for this symbol in the range
                $tradeDates = OptionOhlcData::where('base_symbol', $sym)
                    ->where('instrument_type', 'FUT')
                    ->whereDate('trade_date', '>=', $from)
                    ->whereDate('trade_date', '<=', $to)
                    ->select(DB::raw('DATE(trade_date) as d'))
                    ->distinct()
                    ->orderByDesc('d')
                    ->pluck('d')
                    ->toArray();

                foreach ($tradeDates as $date) {
                    $row = $this->buildRow($sym, $date);
                    // ── ONLY include actionable signals (no WAIT) ────
                    if ($row && in_array($row['signal'], ['BUY CE', 'BUY PE'])) {
                        $results[] = $row;
                    }
                }
            }

            // Sort by date desc, then symbol asc
            usort($results, function ($a, $b) {
                $dc = strcmp($b['date'], $a['date']);
                return $dc !== 0 ? $dc : strcmp($a['symbol'], $b['symbol']);
            });

            return response()->json([
                'success' => true,
                'data'    => $results,
                'count'   => count($results),
                'summary' => [
                    'total'    => count($results),
                    'buy_ce'   => count(array_filter($results, fn($r) => $r['signal'] === 'BUY CE')),
                    'buy_pe'   => count(array_filter($results, fn($r) => $r['signal'] === 'BUY PE')),
                    'symbols'  => count($symbols),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('AllSymbolOI Error: ' . $e->getMessage() . ' @ ' . $e->getLine());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // ── BUILD ONE ROW ─────────────────────────────────────────────────
    private function buildRow(string $symbol, string $date): ?array
    {
        $t  = $date;
        $t1 = $this->prevTradingDate($t);
        $t2 = $this->prevTradingDate($t1);

        $expT = $this->resolveExpiry($symbol, $t);
        if (!$expT) return null;
        $expT1 = $this->matchExpiry($symbol, $t1, $expT);
        $expT2 = $this->matchExpiry($symbol, $t2, $expT1 ?? $expT);

        // ── Fetch OI ─────────────────────────────────────────────────
        $ceT  = $this->sumOI($symbol, $t,  'CE', self::EOD_TIME, $expT);
        $ceT1 = $this->sumOI($symbol, $t1, 'CE', self::PRV_TIME, $expT1 ?? $expT);
        $ceT2 = $this->sumOI($symbol, $t2, 'CE', self::PRV_TIME, $expT2 ?? $expT1 ?? $expT);

        $peT  = $this->sumOI($symbol, $t,  'PE', self::EOD_TIME, $expT);
        $peT1 = $this->sumOI($symbol, $t1, 'PE', self::PRV_TIME, $expT1 ?? $expT);
        $peT2 = $this->sumOI($symbol, $t2, 'PE', self::PRV_TIME, $expT2 ?? $expT1 ?? $expT);

        if ($ceT === 0 && $peT === 0) return null;

        // ── % changes ────────────────────────────────────────────────
        $cePctT  = $ceT1 > 0 ? round((($ceT  - $ceT1) / $ceT1) * 100, 2) : 0.0;
        $pePctT  = $peT1 > 0 ? round((($peT  - $peT1) / $peT1) * 100, 2) : 0.0;
        $cePctT1 = $ceT2 > 0 ? round((($ceT1 - $ceT2) / $ceT2) * 100, 2) : 0.0;
        $pePctT1 = $peT2 > 0 ? round((($peT1 - $peT2) / $peT2) * 100, 2) : 0.0;

        $diff = round(abs($cePctT - $pePctT), 2);

        // ── Signals ──────────────────────────────────────────────────
        $baseSignal = $this->baseOISignal($cePctT, $pePctT);
        $flowSignal = $this->flowSignal($cePctT1, $cePctT, $pePctT1, $pePctT);

        $ceSpike = abs($cePctT) > 35;
        $peSpike = abs($pePctT) > 35;
        $spike   = match(true) {
            $ceSpike && $peSpike => 'DUAL',
            $ceSpike             => 'CE',
            $peSpike             => 'PE',
            default              => 'NONE',
        };

        $conflict = (
            ($baseSignal === 'BULLISH' && in_array($flowSignal, ['STRONG_BEAR', 'TRAP'])) ||
            ($baseSignal === 'BEARISH' && in_array($flowSignal, ['STRONG_BULL', 'TRAP']))
        );

        $score = $this->calcScore($baseSignal, $flowSignal, $diff, $spike);

        // ── Final signal ──────────────────────────────────────────────
        if ($conflict || $diff < 10) {
            $signal = 'WAIT';
        } elseif ($score >= self::TRADE_THRESHOLD) {
            $signal = 'BUY CE';
        } elseif ($score <= -self::TRADE_THRESHOLD) {
            $signal = 'BUY PE';
        } else {
            $signal = 'WAIT';
        }

        // ── Confidence ───────────────────────────────────────────────
        $absScore = abs($score);
        $conf = match(true) {
            $conflict                          => 'CONFLICT',
            $diff < 10                         => 'NO EDGE',
            $flowSignal === 'TRAP'             => 'TRAP',
            $absScore >= 6                     => 'HIGH',
            $absScore >= 4.5                   => 'MEDIUM',
            $absScore >= self::TRADE_THRESHOLD => 'LOW',
            $score > 0                         => 'LEAN BULL',
            $score < 0                         => 'LEAN BEAR',
            default                            => 'NEUTRAL',
        };

        return [
            'symbol'      => $symbol,
            'date'        => $date,
            'expiry'      => $expT,

            'ce_oi_t'     => $ceT,
            'ce_oi_t1'    => $ceT1,
            'ce_oi_t2'    => $ceT2,
            'ce_pct_t'    => $cePctT,
            'ce_pct_t1'   => $cePctT1,

            'pe_oi_t'     => $peT,
            'pe_oi_t1'    => $peT1,
            'pe_oi_t2'    => $peT2,
            'pe_pct_t'    => $pePctT,
            'pe_pct_t1'   => $pePctT1,

            'oi_diff'     => $diff,
            'condition'   => $this->oiConditionLabel($cePctT, $pePctT),
            'ce_cont'     => $this->continuationLabel($cePctT1, $cePctT),
            'pe_cont'     => $this->continuationLabel($pePctT1, $pePctT),

            'base_signal' => $baseSignal,
            'flow_signal' => $flowSignal,
            'spike'       => $spike,
            'conflict'    => $conflict,

            'score'       => round($score, 1),
            'signal'      => $signal,
            'confidence'  => $conf,
        ];
    }

    // ── BASE SIGNAL ───────────────────────────────────────────────────
    private function baseOISignal(float $cePct, float $pePct): string
    {
        $ceUp = $cePct > 0;
        $peUp = $pePct > 0;

        if (!$ceUp && $peUp)  return 'BULLISH';
        if ($ceUp && !$peUp)  return 'BEARISH';
        if ($ceUp && $peUp)   return $pePct > $cePct ? 'BULLISH' : 'BEARISH';
        return abs($cePct) > abs($pePct) ? 'BULLISH' : 'BEARISH';
    }

    // ── FLOW SIGNAL ───────────────────────────────────────────────────
    private function flowSignal(
        float $ceT1T2, float $ceT0T1,
        float $peT1T2, float $peT0T1
    ): string {
        $ceUnwindBoth = ($ceT1T2 < -3 && $ceT0T1 < -3);
        $peBuildBoth  = ($peT1T2 >  3 && $peT0T1 >  3);
        $ceBuildBoth  = ($ceT1T2 >  3 && $ceT0T1 >  3);
        $peUnwindBoth = ($peT1T2 < -3 && $peT0T1 < -3);

        if ($ceUnwindBoth && $peBuildBoth)  return 'STRONG_BULL';
        if ($ceBuildBoth  && $peUnwindBoth) return 'STRONG_BEAR';

        $ceTrap = abs($ceT1T2) > 25 && ($ceT1T2 * $ceT0T1 < 0) && abs($ceT0T1) > 15;
        $peTrap = abs($peT1T2) > 25 && ($peT1T2 * $peT0T1 < 0) && abs($peT0T1) > 15;
        if ($ceTrap || $peTrap) return 'TRAP';

        $ceRev = ($ceT1T2 > 5 && $ceT0T1 < -5) || ($ceT1T2 < -5 && $ceT0T1 > 5);
        $peRev = ($peT1T2 > 5 && $peT0T1 < -5) || ($peT1T2 < -5 && $peT0T1 > 5);
        if ($ceRev && $peRev) return 'REVERSAL';

        $ceCont = ($ceT1T2 > 3 && $ceT0T1 > 3) || ($ceT1T2 < -3 && $ceT0T1 < -3);
        $peCont = ($peT1T2 > 3 && $peT0T1 > 3) || ($peT1T2 < -3 && $peT0T1 < -3);
        if ($ceCont || $peCont) return 'CONTINUATION';

        return 'MIXED';
    }

    // ── SCORE ENGINE ──────────────────────────────────────────────────
    private function calcScore(
        string $baseSignal,
        string $flowSignal,
        float  $diff,
        string $spike
    ): float {
        $score = 0.0;

        match ($baseSignal) {
            'BULLISH' => $score += 2.0,
            'BEARISH' => $score -= 2.0,
            default   => null,
        };

        match ($flowSignal) {
            'STRONG_BULL'  => $score += 3.0,
            'STRONG_BEAR'  => $score -= 3.0,
            'CONTINUATION' => $score += ($baseSignal === 'BULLISH' ? 1.5 : -1.5),
            'REVERSAL'     => $score *= 0.5,
            'TRAP'         => $score  = 0.0,
            'MIXED'        => null,
        };

        if ($flowSignal === 'TRAP') {
            return 0.0;
        }

        if ($diff < 10)     $score *= 0.3;
        elseif ($diff > 40) $score *= 1.3;
        elseif ($diff > 25) $score *= 1.15;

        match ($spike) {
            'CE'   => $score -= 0.5,
            'PE'   => $score += 0.5,
            'DUAL' => $score *= 1.1,
            default => null,
        };

        return round($score, 2);
    }

    // ── LABELS ────────────────────────────────────────────────────────
    private function oiConditionLabel(float $cePct, float $pePct): string
    {
        $ceUp = $cePct > 0;
        $peUp = $pePct > 0;
        if (!$ceUp && $peUp)  return 'CE↓ PE↑';
        if ($ceUp && !$peUp)  return 'CE↑ PE↓';
        if ($ceUp && $peUp)   return 'Both↑';
        return 'Both↓';
    }

    private function continuationLabel(float $prev, float $today): string
    {
        if ($prev == 0 && $today == 0) return '—';
        if (($prev > 0) === ($today > 0)) {
            if (abs($today) > abs($prev) + 5) return 'Accel';
            if (abs($today) < abs($prev) - 5) return 'Decel';
            return 'Stable';
        }
        return $today > 0 ? 'Rev↗' : 'Rev↘';
    }

    // ── DATA HELPERS ──────────────────────────────────────────────────
    private function sumOI(string $symbol, string $date, string $type, string $time, ?string $expiry): int
    {
        if (!$expiry) return 0;
        return (int) OptionOhlcData::where('base_symbol', $symbol)
            ->where('instrument_type', $type)
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $expiry)
            ->whereRaw("TIME(interval_time) = ?", [$time])
            ->sum('oi');
    }

    private function resolveExpiry(string $symbol, string $date): ?string
    {
        $expiry = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'));

        if (!$expiry) {
            return OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $date)
                ->orderByDesc('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
        }

        if ($expiry === $date) {
            $next = OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $date)
                ->whereDate('expiry_date', '>', $date)
                ->orderBy('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
            if ($next) return $next;
        }

        return $expiry;
    }

    private function matchExpiry(string $symbol, string $date, ?string $preferredExpiry): ?string
    {
        if (!$preferredExpiry) return null;

        $exists = OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', $preferredExpiry)
            ->exists();

        if ($exists) return $preferredExpiry;

        return OptionOhlcData::where('base_symbol', $symbol)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereDate('trade_date', $date)
            ->whereDate('expiry_date', '>=', $date)
            ->orderBy('expiry_date')
            ->value(DB::raw('DATE(expiry_date)'))
            ?? OptionOhlcData::where('base_symbol', $symbol)
                ->whereIn('instrument_type', ['CE', 'PE'])
                ->whereDate('trade_date', $date)
                ->orderByDesc('expiry_date')
                ->value(DB::raw('DATE(expiry_date)'));
    }

    private function prevTradingDate(string $date): string
    {
        $d = Carbon::parse($date)->subDay();
        $tries = 0;
        while ($tries < 10) {
            if (!$d->isWeekend() && !$this->isHoliday($d->format('Y-m-d'))) {
                return $d->format('Y-m-d');
            }
            $d->subDay();
            $tries++;
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}