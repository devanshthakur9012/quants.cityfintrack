<?php

namespace App\Traits;

use App\Models\ZerodhaInstrument;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * McxExpiryResolver — Self-contained, no OptionExpiryResolver dependency.
 *
 * KEY INSIGHT from DB analysis:
 *   MCX FUT  expiry for ZINC = 2026-03-30
 *   MCX OPT  expiry for ZINC = 2026-03-23  ← DIFFERENT DATE!
 *
 *   So we must resolve FUT expiry and OPTION expiry separately.
 *   The command uses FUT expiry to fetch futures data,
 *   and OPTION expiry to fetch CE/PE data.
 *
 *   Also: NCO exchange has duplicate ZINC options — must filter segment='MCX-OPT'
 */
trait McxExpiryResolver
{
    private const MCX_ROLLOVER_TRADING_DAYS = 3;

    // ─────────────────────────────────────────────────────────────────────────
    // FUT expiry resolution
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve 1 or 2 FUT expiry dates for an MCX symbol on tradeDate.
     */
    protected function resolveMcxFutExpiries(string $symbol, Carbon $tradeDate): array
    {
        $expiries = ZerodhaInstrument::where('exchange', 'MCX')
            ->where('instrument_type', 'FUT')
            ->where('segment', 'MCX-FUT')
            ->where('name', $symbol)
            ->whereDate('expiry', '>=', $tradeDate->copy()->startOfMonth())
            ->orderBy('expiry')
            ->pluck('expiry')
            ->map(fn($e) => Carbon::parse($e)->toDateString())
            ->unique()
            ->values()
            ->toArray();

        if (empty($expiries)) return [];

        $current = null;
        $next    = null;

        foreach ($expiries as $i => $exp) {
            if (Carbon::parse($exp)->gte($tradeDate)) {
                $current = $exp;
                $next    = $expiries[$i + 1] ?? null;
                break;
            }
        }

        if ($current === null) return [end($expiries)];

        $daysLeft = $this->mcxTradingDaysBetween($tradeDate, Carbon::parse($current));

        if ($daysLeft <= self::MCX_ROLLOVER_TRADING_DAYS && $next !== null) {
            return [$current, $next];
        }

        return [$current];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OPTION expiry resolution (separate from FUT — MCX has different dates!)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve the nearest option expiry for a symbol on tradeDate.
     * MCX option expiry != FUT expiry (e.g. ZINC OPT=2026-03-23, FUT=2026-03-30)
     * Filters segment='MCX-OPT' to avoid NCO duplicates.
     */
    protected function resolveMcxOptionExpiry(string $symbol, Carbon $tradeDate): ?string
    {
        return ZerodhaInstrument::where('exchange', 'MCX')
            ->where('instrument_type', 'CE')
            ->where('segment', 'MCX-OPT')           // ← avoids NCO duplicates
            ->where('name', $symbol)
            ->whereDate('expiry', '>=', $tradeDate->toDateString())
            ->orderBy('expiry')
            ->value('expiry')
            ? Carbon::parse(
                ZerodhaInstrument::where('exchange', 'MCX')
                    ->where('instrument_type', 'CE')
                    ->where('segment', 'MCX-OPT')
                    ->where('name', $symbol)
                    ->whereDate('expiry', '>=', $tradeDate->toDateString())
                    ->orderBy('expiry')
                    ->value('expiry')
              )->toDateString()
            : null;
    }

    /**
     * Get the nearest MCX option expiry — optimized single query version.
     */
    protected function getNearestMcxOptionExpiry(string $symbol, Carbon $tradeDate): ?string
    {
        $expiry = ZerodhaInstrument::where('exchange', 'MCX')
            ->where('instrument_type', 'CE')
            ->where('segment', 'MCX-OPT')
            ->where('name', $symbol)
            ->whereDate('expiry', '>=', $tradeDate->toDateString())
            ->orderBy('expiry')
            ->value('expiry');

        return $expiry ? Carbon::parse($expiry)->toDateString() : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shared helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Count MCX trading days from $from (exclusive) to $to (inclusive).
     * MCX trades Mon–Sat, skips Sunday + MCX/NSE holidays.
     */
    protected function mcxTradingDaysBetween(Carbon $from, Carbon $to): int
    {
        if ($from->gte($to)) return 0;

        $marketName = DB::table('market_holidays')->where('market_name', 'MCX')->exists()
            ? 'MCX' : 'NSE';

        $holidays = DB::table('market_holidays')
            ->where('market_name', $marketName)
            ->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])
            ->pluck('holiday_date')
            ->flip()
            ->toArray();

        $count   = 0;
        $current = $from->copy()->addDay();

        while ($current->lte($to)) {
            if (!$current->isSunday() && !isset($holidays[$current->toDateString()])) {
                $count++;
            }
            $current->addDay();
        }

        return $count;
    }

    /**
     * Check if a date is an MCX holiday (falls back to NSE holidays).
     */
    protected function isMarketHoliday(string $date): bool
    {
        return DB::table('market_holidays')
            ->whereIn('market_name', ['MCX', 'NSE'])
            ->where('holiday_date', $date)
            ->exists();
    }
}