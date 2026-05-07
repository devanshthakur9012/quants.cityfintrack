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
 * Rule:
 *   1. Resolve the CURRENT month expiry for a symbol on a given trade date.
 *   2. Resolve the NEXT month expiry.
 *   3. If the trade date is within ROLLOVER_TRADING_DAYS trading days
 *      BEFORE the current-month expiry, return BOTH expiries (overlap window).
 *   4. If the trade date is AFTER the current-month expiry, return only
 *      the next-month expiry.
 *   5. Otherwise return only the current-month expiry.
 *
 * "Trading days" excludes weekends and NSE market holidays.
 */
trait OptionExpiryResolverBackup
{
    /**
     * Number of trading days before expiry at which we start collecting
     * the next month's contracts in parallel.
     */
    private const ROLLOVER_TRADING_DAYS = 5;

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
        // All future/current expiries ordered by date
        $expiries = ZerodhaInstrument::where('name', $symbol)
            ->where('exchange', 'NFO')
            ->where('instrument_type', 'CE')
            ->whereDate('expiry', '>=', $tradeDate->copy()->startOfMonth())
            ->orderBy('expiry', 'ASC')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($expiries)) {
            // Nothing in DB — fallback: add 7 days
            return [$tradeDate->copy()->addDays(7)->toDateString()];
        }

        // ── Find current-month expiry ─────────────────────────────────────────
        // "Current month" = the nearest expiry that is >= tradeDate
        $currentExpiry = null;
        $nextExpiry    = null;

        foreach ($expiries as $i => $exp) {
            $expDate = Carbon::parse($exp);
            if ($expDate->gte($tradeDate)) {
                $currentExpiry = $exp;
                $nextExpiry    = $expiries[$i + 1] ?? null;
                break;
            }
        }

        if ($currentExpiry === null) {
            // tradeDate is past all known expiries
            return [end($expiries)];
        }

        // ── Determine overlap window ──────────────────────────────────────────
        $currentExpDate = Carbon::parse($currentExpiry);
        $tradingDaysLeft = $this->tradingDaysBetween($tradeDate, $currentExpDate);

        $result = [$currentExpiry];

        if ($tradingDaysLeft <= self::ROLLOVER_TRADING_DAYS && $nextExpiry !== null) {
            // We are within the rollover window — collect BOTH expiries
            $result[] = $nextExpiry;
        }

        return $result;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Count trading days from $from (exclusive) to $to (inclusive).
     * Excludes weekends and NSE market holidays.
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

    protected function isMarketHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $date)
            ->exists();
    }
}