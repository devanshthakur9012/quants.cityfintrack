<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OptionOhlcData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * OIStrikeAnalysisController
 *
 * Three-way OI comparison for EOD (15:00) signals:
 *  1. ATM only          — CE ATM  vs PE ATM
 *  2. ATM+1 CE / ATM-1 PE — CE (ATM + ATM+1)  vs PE (ATM + ATM-1)
 *  3. Existing (all)    — all CE strikes vs all PE strikes (same as existing pece logic)
 *
 * OI change: today 15:00 vs prev trading day 15:15
 */
class OIStrikeAnalysisController extends Controller
{
    // =========================================================
    //  PAGES
    // =========================================================

    public function index()
    {
        $pageTitle = 'OI Strike Analysis — ATM Comparison';
        return view($this->activeTemplate . 'user.oiiv-auto.strike-analysis', compact('pageTitle'));
    }

    // =========================================================
    //  SYMBOLS
    // =========================================================

    public function getSymbols()
    {
        $symbols = OptionOhlcData::where('instrument_type', 'FUT')
            ->distinct()
            ->orderBy('base_symbol')
            ->pluck('base_symbol')
            ->values();

        return response()->json(['success' => true, 'symbols' => $symbols]);
    }

    // =========================================================
    //  MAIN ANALYSIS ENDPOINT
    // =========================================================

    public function analyze(Request $request)
    {
        try {
            $fromDate        = $request->get('from_date');
            $toDate          = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $filterSentiment = $request->get('filter_sentiment');

            if (!$fromDate || !$toDate) {
                return response()->json(['success' => false, 'message' => 'Please select both dates', 'data' => []]);
            }

            // Get all trading dates in range
            $tradeDates = OptionOhlcData::where('instrument_type', 'FUT')
                ->whereDate('trade_date', '>=', $fromDate)
                ->whereDate('trade_date', '<=', $toDate)
                ->select(DB::raw('DATE(trade_date) as d'))
                ->distinct()
                ->orderBy('d')
                ->pluck('d')
                ->toArray();

            if (empty($tradeDates)) {
                return response()->json(['success' => false, 'message' => 'No data found for selected date range', 'data' => []]);
            }

            $results = [];

            foreach ($tradeDates as $date) {
                $prevDate = $this->getPreviousTradingDate($date);
                $rows     = $this->buildRowsForDate($date, $prevDate, $selectedSymbols, $filterSentiment);
                foreach ($rows as $row) {
                    $results[] = $row;
                }
            }

            // Sort: newest date first, then symbol
            usort($results, fn($a, $b) =>
                $b['date'] <=> $a['date'] ?: $a['symbol'] <=> $b['symbol']
            );

            return response()->json([
                'success'       => true,
                'data'          => $results,
                'total_records' => count($results),
                'message'       => count($results) . ' records found',
            ]);

        } catch (\Exception $e) {
            Log::error('OI Strike Analysis Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => []], 500);
        }
    }

    // =========================================================
    //  CORE: Build rows for a single date
    // =========================================================

    private function buildRowsForDate(string $date, string $prevDate, array $symbolFilter, ?string $filterSentiment): array
    {
        // ── Today 15:00 candles (CE, PE, FUT) ──────────────────
        $todayQuery = OptionOhlcData::whereDate('trade_date', $date)
            ->whereIn('instrument_type', ['FUT', 'CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:00:00'");

        if (!empty($symbolFilter)) {
            $todayQuery->whereIn('base_symbol', $symbolFilter);
        }

        $todayCandles = $todayQuery->get();
        if ($todayCandles->isEmpty()) return [];

        $symbols = $todayCandles->pluck('base_symbol')->unique()->values()->toArray();

        // ── Prev 15:15 candles (CE, PE) ─────────────────────────
        $prevCandles = OptionOhlcData::whereDate('trade_date', $prevDate)
            ->whereIn('instrument_type', ['CE', 'PE'])
            ->whereRaw("TIME(interval_time) = '15:15:00'")
            ->whereIn('base_symbol', $symbols)
            ->get();

        // Index today candles: symbol → type → strikeInt → candle
        // KEY FIX: use (int) cast for strike to ensure consistent keys like 1540, not "1540.0"
        $todayIdx = [];
        foreach ($todayCandles as $c) {
            $strikeKey = $c->strike !== null ? (string)(int)(float)$c->strike : '0';
            $todayIdx[$c->base_symbol][$c->instrument_type][$strikeKey] = $c;
        }

        // Index prev candles with same int-cast key
        $prevIdx = [];
        foreach ($prevCandles as $c) {
            $strikeKey = $c->strike !== null ? (string)(int)(float)$c->strike : '0';
            $prevIdx[$c->base_symbol][$c->instrument_type][$strikeKey] = $c;
        }

        $rows = [];

        foreach ($symbols as $symbol) {
            $futCandle = ($todayIdx[$symbol]['FUT'] ?? []);
            $futCandle = !empty($futCandle) ? array_values($futCandle)[0] : null;

            if (!$futCandle || (float)$futCandle->close <= 0) continue;

            $spotPrice = (float)$futCandle->close;

            $ceStrikes  = $todayIdx[$symbol]['CE'] ?? [];
            $peStrikes  = $todayIdx[$symbol]['PE'] ?? [];
            $prevCeIdx  = $prevIdx[$symbol]['CE']  ?? [];
            $prevPeIdx  = $prevIdx[$symbol]['PE']  ?? [];

            // All available strikes sorted as integers
            $allCeStrikesNums = array_map('intval', array_keys($ceStrikes));
            $allPeStrikesNums = array_map('intval', array_keys($peStrikes));
            sort($allCeStrikesNums);
            sort($allPeStrikesNums);

            // Find ATM from already-loaded CE strikes (closest to spot)
            // First try strike_position = ATM from loaded candles
            $atmStrike = null;
            foreach ($ceStrikes as $strikeKey => $c) {
                if (isset($c->strike_position) && $c->strike_position === 'ATM') {
                    $atmStrike = (int)$strikeKey;
                    break;
                }
            }
            // Fallback: find closest strike to spot from loaded CE or PE data
            if ($atmStrike === null) {
                $allStrikes = array_unique(array_merge($allCeStrikesNums, $allPeStrikesNums));
                if (empty($allStrikes)) continue;
                sort($allStrikes);
                $atmStrike = $allStrikes[0];
                $minDiff   = PHP_INT_MAX;
                foreach ($allStrikes as $s) {
                    $diff = abs($s - $spotPrice);
                    if ($diff < $minDiff) { $minDiff = $diff; $atmStrike = $s; }
                }
            }

            if ($atmStrike === null) continue;

            $atmKey = (string)$atmStrike;

            // ATM+1 strike (next strike above ATM in CE)
            $atmPlus1  = $this->getAdjacentStrikeInt($allCeStrikesNums, $atmStrike, +1);
            // ATM-1 strike (next strike below ATM in PE)
            $atmMinus1 = $this->getAdjacentStrikeInt($allPeStrikesNums, $atmStrike, -1);

            // ── 1. ATM Only ─────────────────────────────────────
            $atmCeCandle = $ceStrikes[$atmKey] ?? null;
            $atmPeCandle = $peStrikes[$atmKey] ?? null;

            [$atmCePrev, $atmCeCur] = $this->oiPair($atmCeCandle, $prevCeIdx[$atmKey] ?? null);
            [$atmPePrev, $atmPeCur] = $this->oiPair($atmPeCandle, $prevPeIdx[$atmKey] ?? null);

            $atmCePct  = $atmCePrev > 0 ? round((($atmCeCur - $atmCePrev) / $atmCePrev) * 100, 4) : 0;
            $atmPePct  = $atmPePrev > 0 ? round((($atmPeCur - $atmPePrev) / $atmPePrev) * 100, 4) : 0;
            $atmSignal = $this->getOISignal($atmCePct, $atmPePct);

            // ── 2. ATM + ATM+1 CE  /  ATM + ATM-1 PE ───────────
            // CE side: ATM + ATM+1
            $atmCePrevOI   = $atmCePrev;
            $atmCeCurOI    = $atmCeCur;
            $p1Key         = $atmPlus1 !== null ? (string)$atmPlus1 : null;
            $atmP1CeCandle = $p1Key ? ($ceStrikes[$p1Key] ?? null) : null;
            [$p1CePrev, $p1CeCur] = $this->oiPair($atmP1CeCandle, $p1Key ? ($prevCeIdx[$p1Key] ?? null) : null);

            $combo2CePrev = $atmCePrevOI + $p1CePrev;
            $combo2CeCur  = $atmCeCurOI  + $p1CeCur;

            // PE side: ATM + ATM-1
            $atmPePrevOI   = $atmPePrev;
            $atmPeCurOI    = $atmPeCur;
            $m1Key         = $atmMinus1 !== null ? (string)$atmMinus1 : null;
            $atmM1PeCandle = $m1Key ? ($peStrikes[$m1Key] ?? null) : null;
            [$m1PePrev, $m1PeCur] = $this->oiPair($atmM1PeCandle, $m1Key ? ($prevPeIdx[$m1Key] ?? null) : null);

            $combo2PePrev = $atmPePrevOI + $m1PePrev;
            $combo2PeCur  = $atmPeCurOI  + $m1PeCur;

            $combo2CePct  = $combo2CePrev > 0 ? round((($combo2CeCur - $combo2CePrev) / $combo2CePrev) * 100, 4) : 0;
            $combo2PePct  = $combo2PePrev > 0 ? round((($combo2PeCur - $combo2PePrev) / $combo2PePrev) * 100, 4) : 0;
            $combo2Signal = $this->getOISignal($combo2CePct, $combo2PePct);

            // ── 3. Existing / All ────────────────────────────────
            $allCePrev = 0; $allCeCur = 0;
            foreach ($ceStrikes as $strikeKey => $tc) {
                $allCeCur  += (int)($tc->oi ?? 0);
                $allCePrev += (int)(($prevCeIdx[$strikeKey] ?? null)?->oi ?? 0);
            }

            $allPePrev = 0; $allPeCur = 0;
            foreach ($peStrikes as $strikeKey => $tc) {
                $allPeCur  += (int)($tc->oi ?? 0);
                $allPePrev += (int)(($prevPeIdx[$strikeKey] ?? null)?->oi ?? 0);
            }

            $allCePct  = $allCePrev > 0 ? round((($allCeCur - $allCePrev) / $allCePrev) * 100, 4) : 0;
            $allPePct  = $allPePrev > 0 ? round((($allPeCur - $allPePrev) / $allPePrev) * 100, 4) : 0;
            $allSignal = $this->getOISignal($allCePct, $allPePct);

            // ── Filter ───────────────────────────────────────────
            if (!empty($filterSentiment)) {
                // Apply filter: any of the 3 columns must match
                $match = $atmSignal['signal'] === $filterSentiment
                      || $combo2Signal['signal'] === $filterSentiment
                      || $allSignal['signal'] === $filterSentiment;
                if (!$match) continue;
            }

            $rows[] = [
                'date'       => $date,
                'symbol'     => $symbol,
                'spot_price' => round($spotPrice, 2),
                'atm_strike' => $atmStrike,

                // ATM
                'atm_ce_oi'   => $atmCeCur,
                'atm_ce_pct'  => $atmCePct,
                'atm_pe_oi'   => $atmPeCur,
                'atm_pe_pct'  => $atmPePct,
                'atm_cond'    => $atmSignal['condition'],
                'atm_sent'    => $atmSignal['signal'],

                // ATM + ATM+1 CE / ATM + ATM-1 PE
                'atm1_ce_oi'      => $combo2CeCur,
                'atm1_ce_pct'     => $combo2CePct,
                'atm1_pe_oi'      => $combo2PeCur,
                'atm1_pe_pct'     => $combo2PePct,
                'atm1_cond'       => $combo2Signal['condition'],
                'atm1_sent'       => $combo2Signal['signal'],
                'atm_plus1_used'  => $atmPlus1,
                'atm_minus1_used' => $atmMinus1,

                // All
                'all_ce_oi'  => $allCeCur,
                'all_ce_pct' => $allCePct,
                'all_pe_oi'  => $allPeCur,
                'all_pe_pct' => $allPePct,
                'all_cond'   => $allSignal['condition'],
                'all_sent'   => $allSignal['signal'],
            ];
        }

        return $rows;
    }

    // =========================================================
    //  GET ADJACENT STRIKE (int-based, no float precision issues)
    // =========================================================

    private function getAdjacentStrikeInt(array $sortedIntStrikes, int $atmStrike, int $direction): ?int
    {
        $idx = null;
        foreach ($sortedIntStrikes as $i => $s) {
            if ($s === $atmStrike) { $idx = $i; break; }
        }
        if ($idx === null) return null;

        $target = $idx + $direction;
        return isset($sortedIntStrikes[$target]) ? $sortedIntStrikes[$target] : null;
    }

    // =========================================================
    //  OI PAIR HELPER
    // =========================================================

    private function oiPair($todayCandle, $prevCandle): array
    {
        $cur  = $todayCandle ? (int)($todayCandle->oi ?? 0) : 0;
        $prev = $prevCandle  ? (int)($prevCandle->oi  ?? 0) : 0;
        return [$prev, $cur];
    }

    // =========================================================
    //  OI SIGNAL LOGIC (same as existing pece controller)
    // =========================================================

    private function getOISignal(float $cePct, float $pePct): array
    {
        $ceUp   = $cePct > 0;
        $ceDown = $cePct < 0;
        $peUp   = $pePct > 0;
        $peDown = $pePct < 0;

        if ($ceUp   && $peDown) return ['signal' => 'BEARISH', 'reason' => 'Call buildup + Put unwinding',   'condition' => 'CE ↑ + PE ↓'];
        if ($ceDown && $peUp)   return ['signal' => 'BULLISH', 'reason' => 'Call unwinding + Put buildup',   'condition' => 'CE ↓ + PE ↑'];
        if ($ceUp   && $peUp)   return $cePct > $pePct
            ? ['signal' => 'BEARISH', 'reason' => "Both ↑ CE stronger",  'condition' => 'Both ↑ (CE > PE)']
            : ['signal' => 'BULLISH', 'reason' => "Both ↑ PE stronger",  'condition' => 'Both ↑ (PE > CE)'];
        if ($ceDown && $peDown) return $cePct < $pePct
            ? ['signal' => 'BULLISH', 'reason' => "Both ↓ CE stronger",  'condition' => 'Both ↓ (CE < PE)']
            : ['signal' => 'BEARISH', 'reason' => "Both ↓ PE stronger",  'condition' => 'Both ↓ (PE < CE)'];

        return ['signal' => 'NEUTRAL', 'reason' => 'No clear OI direction', 'condition' => 'Flat'];
    }

    // =========================================================
    //  DATE HELPERS
    // =========================================================

    private function dateStr($value): string
    {
        if ($value instanceof Carbon) return $value->toDateString();
        if (is_string($value))        return substr($value, 0, 10);
        return Carbon::parse($value)->toDateString();
    }

    private function getPreviousTradingDate(string $date): string
    {
        $prev     = Carbon::parse($date)->subDay();
        $attempts = 0;
        while ($attempts < 10) {
            if (!$prev->isWeekend() && !$this->isHoliday($prev->format('Y-m-d'))) {
                return $prev->format('Y-m-d');
            }
            $prev->subDay();
            $attempts++;
        }
        return Carbon::parse($date)->subDay()->format('Y-m-d');
    }

    private function isHoliday(string $date): bool
    {
        return \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}