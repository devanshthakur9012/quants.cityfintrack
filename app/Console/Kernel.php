<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // ═══════════════════════════════════════════════════════════════════
        // Zerodha Instrument Sync — runs every weekday at 8:30 AM
        // Truncates and reloads all instruments from Kite API before market open
        // ═══════════════════════════════════════════════════════════════════
        $schedule->command('zerodha_instrument:insert')
            ->weekdays()
            ->dailyAt('08:30')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/zerodha-instrument.log'));
 
        // ═══════════════════════════════════════════════════════════════════
        // 15MIN TIMEFRAME — every 15 minutes, 9:15 AM to 3:30 PM, Mon-Fri
        // ═══════════════════════════════════════════════════════════════════
        $schedule->command('cp:orchestrate --timeframe=15min')
            ->everyFifteenMinutes()
            ->weekdays()
            ->between('09:15', '15:30')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(5) // max 5 min overlap lock
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cp-collect-15min.log'));
 
        // ═══════════════════════════════════════════════════════════════════
        // 30MIN TIMEFRAME — every 30 minutes, 9:15 AM to 3:30 PM, Mon-Fri
        // ═══════════════════════════════════════════════════════════════════
        $schedule->command('cp:orchestrate --timeframe=30min')
            ->everyThirtyMinutes()
            ->weekdays()
            ->between('09:15', '15:30')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(10)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cp-collect-30min.log'));
 
        // ═══════════════════════════════════════════════════════════════════
        // 1HR TIMEFRAME — every hour, 9:15 AM to 3:30 PM, Mon-Fri
        // Note: everyTwoHours() not suitable — use cron expression for :15
        // Runs at 09:15, 10:15, 11:15, 12:15, 13:15, 14:15, 15:15
        // ═══════════════════════════════════════════════════════════════════
        $schedule->command('cp:orchestrate --timeframe=1hr')
            ->cron('15 9-15 * * 1-5') // At minute 15 past every hour from 9 to 15, Mon-Fri
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(15)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cp-collect-1hr.log'));
 
        // ═══════════════════════════════════════════════════════════════════
        // INDIVIDUAL COMMANDS (run manually or for debugging only)
        // These are NOT scheduled — call them directly:
        //
        //   php artisan cp:collect-stock  --timeframe=15min
        //   php artisan cp:collect-fut    --timeframe=15min
        //   php artisan cp:collect-option --timeframe=15min
        //
        // Historical:
        //   php artisan cp:orchestrate --timeframe=15min --from=2026-01-01 --to=2026-03-31
        //   php artisan cp:collect-option --timeframe=30min --from=2026-01-01 --symbol=NIFTY
        // ═══════════════════════════════════════════════════════════════════
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
