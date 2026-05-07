<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */

    /**
     * Register Artisan commands
     */
    protected $commands = [
        \App\Console\Commands\SyncFuturesInstruments::class,
        \App\Console\Commands\SyncFuturesCandles::class,
        \App\Console\Commands\GenerateFuturesSignals::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        
        // $schedule->command('angel_instrument:daily_update')->dailyAt('8:15')->sendOutputTo('command2_output.log');
        $schedule->command('zerodha_instrument:insert')->dailyAt('8:00')->sendOutputTo('command4_output.log');

        // Fetch stock data every 15 minutes during market hours (9:15 AM to 3:30 PM)
        // Monday to Friday
        // $schedule->command('stock:fetch-data')
        //     ->everyFifteenMinutes()
        //     ->between('9:15', '15:30')
        //     ->weekdays()
        //     ->sendOutputTo(storage_path('logs/stock_data_fetch.log'));

        // Optional: Fetch end-of-day data at 4:00 PM
        $schedule->command('stock:fetch-data')
            ->dailyAt('16:00')
            ->weekdays()
            ->sendOutputTo(storage_path('logs/stock_data_eod.log'));

          /*
        |--------------------------------------------------------------------------
        | 1️⃣ Sync Futures Instruments (Once Daily)
        |--------------------------------------------------------------------------
        | Updates active futures contracts from angel_api_instruments
        | Runs before market open
        */
        // $schedule->command('futures:sync-instruments')
        //     ->weekdays()
        //     ->at('08:30')
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo(storage_path('logs/futures_instruments.log'));

        // /*
        // |--------------------------------------------------------------------------
        // | 2️⃣ Sync 15-Min Futures Candles
        // |--------------------------------------------------------------------------
        // | Fetches candle + OI data every 15 minutes
        // */
        // $schedule->command('futures:sync-candles')
        //     ->weekdays()
        //     ->everyFifteenMinutes()
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo(storage_path('logs/futures_candles.log'));

        // /*
        // |--------------------------------------------------------------------------
        // | 3️⃣ Generate Trading Signals
        // |--------------------------------------------------------------------------
        // | Runs AFTER candle sync
        // */
        // $schedule->command('futures:generate-signals')
        //     ->weekdays()
        //     ->everyFifteenMinutes()
        //     ->withoutOverlapping()
        //     ->runInBackground()
        //     ->appendOutputTo(storage_path('logs/futures_signals.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
