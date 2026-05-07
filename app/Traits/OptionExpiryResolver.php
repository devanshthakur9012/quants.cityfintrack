<?php

namespace App\Traits;

use App\Models\ZerodhaInstrument;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * OptionExpiryResolver
 *
 * Encapsulates the "smart expiry" logic shared between
 * CollectOptionOhlcData and LiveOptionOhlcCollector.
 *
 * Expiry types (as of Sep 1, 2025 — NSE Tuesday cycle):
 *   - NIFTY          → Weekly (every Tuesday) + monthly/quarterly
 *   - SENSEX         → Weekly (BSE — BFO exchange)
 *   - BANKNIFTY      → Monthly only (weekly discontinued Nov 2024)
 *   - FINNIFTY       → Monthly only
 *   - MIDCPNIFTY     → Monthly only
 *   - All stocks     → Monthly only
 *
 * Rule for MONTHLY symbols:
 *   1. Resolve the nearest expiry >= tradeDate (current expiry).
 *   2. Resolve the next expiry after that.
 *   3. If tradeDate is within ROLLOVER_TRADING_DAYS_MONTHLY (5) trading days
 *      before current expiry → return BOTH (overlap window).
 *   4. Otherwise return only current expiry.
 *
 * Rule for WEEKLY symbols (NIFTY, SENSEX):
 *   1. Resolve the nearest expiry >= tradeDate (current weekly expiry).
 *   2. Resolve the next weekly expiry.
 *   3. If tradeDate is within ROLLOVER_TRADING_DAYS_WEEKLY (1) trading day
 *      before current expiry → return BOTH (overlap window).
 *   4. Otherwise return only current weekly expiry.
 *
 * "Trading days" excludes weekends and NSE market holidays.
 */
trait OptionExpiryResolver
{
    /**
     * Symbols that have WEEKLY expiry contracts.
     * NIFTY (NSE/NFO) + SENSEX (BSE/BFO)
     */
    private const WEEKLY_EXPIRY_SYMBOLS = ['NIFTY', 'SENSEX'];

    /**
     * Symbols traded on BSE (BFO exchange) instead of NSE (NFO).
     */
    private const BSE_SYMBOLS = ['SENSEX', 'BANKEX'];

    /**
     * Rollover window for MONTHLY expiry symbols.
     * Start collecting next month's contracts 5 trading days before expiry.
     */
    private const ROLLOVER_TRADING_DAYS_MONTHLY = 5;

    /**
     * Rollover window for WEEKLY expiry symbols (NIFTY, SENSEX).
     * Start collecting next week's contracts only 1 trading day before expiry.
     * (Wider window would cause permanent dual-collection since next weekly
     *  is always ~5 trading days away.)
     */
    private const ROLLOVER_TRADING_DAYS_WEEKLY = 1;

    /**
     * Kept for backward compat — used in handle() info line.
     * Points to monthly default.
     */
    private const ROLLOVER_TRADING_DAYS = self::ROLLOVER_TRADING_DAYS_MONTHLY;

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the correct exchange string for a symbol.
     * SENSEX/BANKEX → 'BFO', everything else → 'NFO'
     */
    protected function getExchange(string $symbol): string
    {
        return in_array($symbol, self::BSE_SYMBOLS) ? 'BFO' : 'NFO';
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the expiry dates that should be collected for a symbol on
     * the given trade date.
     *
     * @return string[]  Array of date strings ('Y-m-d').
     *                   Usually 1 element; 2 elements during the overlap window.
     */
    protected function resolveExpiries(string $symbol, Carbon $tradeDate): array
    {
        $isWeekly    = in_array($symbol, self::WEEKLY_EXPIRY_SYMBOLS);
        $rolloverDays = $isWeekly
            ? self::ROLLOVER_TRADING_DAYS_WEEKLY
            : self::ROLLOVER_TRADING_DAYS_MONTHLY;

        // ── Fetch relevant expiries from DB ───────────────────────────────────
        // For weekly (NIFTY/SENSEX): fetch from start of current week so we get
        //   the current weekly expiry even if tradeDate is Mon/Tue before expiry.
        // For monthly: fetch from start of current month (original logic).
        $fetchFrom = $isWeekly
            ? $tradeDate->copy()->startOfWeek()   // Monday of current week
            : $tradeDate->copy()->startOfMonth();

        $expiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', $this->getExchange($symbol))   // NFO or BFO
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $fetchFrom)
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($expiries)) {
            // Nothing in DB — fallback
            $fallbackDays = $isWeekly ? 7 : 30;
            return [$tradeDate->copy()->addDays($fallbackDays)->toDateString()];
        }

        // ── For MONTHLY symbols: filter to only month-end expiries ────────────
        // This prevents weekly NIFTY/SENSEX contracts from bleeding into
        // monthly logic if this method is called for a monthly symbol that
        // also has mid-month rows in the DB.
        if (!$isWeekly) {
            $expiries = $this->filterToMonthlyExpiries($expiries);
        }

        // ── Find current expiry (nearest expiry >= tradeDate) ─────────────────
        $currentExpiry = null;
        $nextExpiry    = null;

        foreach ($expiries as $i => $exp) {
            if (Carbon::parse($exp)->gte($tradeDate)) {
                $currentExpiry = $exp;
                $nextExpiry    = $expiries[$i + 1] ?? null;
                break;
            }
        }

        if ($currentExpiry === null) {
            // tradeDate is past all known expiries
            return [end($expiries)];
        }

        // ── Determine overlap / rollover window ───────────────────────────────
        $tradingDaysLeft = $this->tradingDaysBetween($tradeDate, Carbon::parse($currentExpiry));

        $result = [$currentExpiry];

        if ($tradingDaysLeft <= $rolloverDays && $nextExpiry !== null) {
            $hasInstruments = ZerodhaInstrument::where('name', $symbol)
                ->where('exchange', $this->getExchange($symbol))
                ->where('instrument_type', 'CE')
                ->whereDate('expiry', $nextExpiry)
                ->exists();

            if ($hasInstruments) {
                $result[] = $nextExpiry;
            }
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Filter a list of expiry date strings to only include the last expiry
     * of each calendar month (i.e., the monthly contract expiry).
     *
     * @param  string[]  $expiries  Sorted ASC list of 'Y-m-d' strings
     * @return string[]
     */
    private function filterToMonthlyExpiries(array $expiries): array
    {
        $byMonth = [];

        foreach ($expiries as $exp) {
            $key = Carbon::parse($exp)->format('Y-m');  // e.g. "2025-03"
            $byMonth[$key] = $exp;                      // last one per month wins (sorted ASC)
        }

        return array_values($byMonth);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Count trading days from $from (exclusive) to $to (inclusive).
     * Excludes weekends and NSE/BSE market holidays.
     * Note: NSE and BSE share the same holiday calendar in India.
     */
    protected function tradingDaysBetween(Carbon $from, Carbon $to): int
    {
        if ($from->gte($to)) {
            return 0;
        }

        // Fetch holidays in the range once
        $holidays = DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->whereBetween('holiday_date', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->pluck('holiday_date')
            ->flip()
            ->toArray();

        $count   = 0;
        $current = $from->copy()->addDay();
        while ($current->lte($to)) {
            if (!$current->isWeekend() && !isset($holidays[$current->toDateString()])) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Convenience: return just the single "primary" expiry for display.
     * (The nearest valid expiry on or after tradeDate.)
     */
    protected function getPrimaryExpiry(string $symbol, Carbon $tradeDate): string
    {
        return $this->resolveExpiries($symbol, $tradeDate)[0];
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Check whether a symbol uses weekly expiry contracts.
     */
    protected function isWeeklyExpirySymbol(string $symbol): bool
    {
        return in_array($symbol, self::WEEKLY_EXPIRY_SYMBOLS);
    }

    // ─────────────────────────────────────────────────────────────────────────

    protected function isMarketHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}