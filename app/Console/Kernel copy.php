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
    protected function schedule(Schedule $schedule)
    {
            // 1. Auto-login at 8:00 AM (if using automation)
            $schedule->command('zerodha:auto-login')
                    ->weekdays()
                    ->at('08:00')
                    ->timezone('Asia/Kolkata');

            // 2. Download instruments at 8:30 AM
            $schedule->command('zerodha_instrument:insert')
                    ->weekdays()
                    ->at('08:30')
                    ->timezone('Asia/Kolkata');

            // 3. Sync chains at 9:00 AM (before market opens)
            $schedule->command('zerodha:sync-chains')
                    ->weekdays()
                    ->at('09:00')
                    ->timezone('Asia/Kolkata');

            // 4. Store intraday data every hour during market hours
            $schedule->command('zerodha:store-historical --days=1')
                    ->weekdays()
                    ->hourly()
                    ->between('9:15', '15:30')
                    ->timezone('Asia/Kolkata');

            // 5. Final EOD storage at 4:00 PM
            $schedule->command('zerodha:store-historical --days=1')
                    ->weekdays()
                    ->at('16:00')
                    ->timezone('Asia/Kolkata');

            // 6. Weekly backfill on Saturday to catch missing data
            $schedule->command('zerodha:store-historical --backfill --days=7')
                    ->saturdays()
                    ->at('10:00')
                    ->timezone('Asia/Kolkata');

        // ACTIVE
        $withinMarketHours = function () {
            $now = Carbon::now();
            return $now->isWeekday() && $now->between(
                Carbon::createFromTime(9, 15),
                Carbon::createFromTime(15, 30)
            );
        };

        // LTP UPDATE
        // $schedule->command('our-portfolio:update-ltp')
        //     ->everyTwoMinutes()
        //     ->withoutOverlapping(5)
        //     ->runInBackground()
        //     ->onOneServer()
        //     ->sendOutputTo('our-portfolio-update-ltp.log');
        
        // ZERODHA INSTRUMENT
        // $schedule->command('zerodha_instrument:insert')->dailyAt('08:30')->sendOutputTo('command4_output.log'); 

        // ANGEL INSTRUMENT
        $schedule->command('angel_instrument:daily_update')->dailyAt('9:00')->sendOutputTo('command2_output.log');

        // INSTRUMENT CHAIN 
        $schedule->command('instruments:sync-chains')->dailyAt('15:30')->sendOutputTo('instruments-chain.log');
        $schedule->command('instruments:sync-historical')->dailyAt('15:50')->sendOutputTo('instruments-historical.log');

        // FOR HISTORICAL PORTFOLIO
        // $schedule->command('options:generate-chain')->dailyAt('15:30')->sendOutputTo('generate-chain.log');
        // $schedule->command('options:historical-data')->dailyAt('15:50')->sendOutputTo('update-historical.log');
        
        // FOR EARLY HISTORICAL PORTFOLIO
        // $schedule->command('options:early-one-day')->dailyAt('15:00')->sendOutputTo('early-one-day.log'); // GET 1 DAY DATA
        // $schedule->command('options:early-historical-data')->dailyAt('15:17')->sendOutputTo('update-early-historical.log');


        #### ORDER PLACEMENT CRON #####
        // $schedule->command('oms:sync-symbols')
        //     ->everyMinute()
        //     ->between('09:00', '15:30')
        //     ->weekdays()
        //     ->sendOutputTo('oms-symbol-sync.log');
            
        // $schedule->command('new-oms-config:command')
        //     ->everyMinute()
        //     ->between('09:00', '15:30')
        //     ->sendOutputTo('new-oms-config.log');

        #### HISTORICAL ORDER PLACEMENT CRON #####
        // $schedule->command('historical:sync-symbols')
        //     ->everyMinute()
        //     ->between('09:00', '15:30')
        //     ->weekdays()
        //     ->sendOutputTo('oms-symbol-sync.log');
            
        // $schedule->command('historical-order:placed')
        //     ->everyMinute()
        //     ->between('09:00', '15:30')
        //     ->sendOutputTo('new-oms-config.log');


        // // NEW MCX 
        // // $schedule->command('options:generate-mcx-data')->dailyAt('11:30')->sendOutputTo('generate-mcx-data.log');
        // // $schedule->command('options:mcx-historical-data')->dailyAt('11:35')->sendOutputTo('mcx-historical-data.log');

        // // ONE HOUR DATA COLLECTION  //

        // // 10:20 AM - Collects 9:15-10:15 data
        // $schedule->command('options:historical-one-hour')
        //     ->dailyAt('10:20')
        //     ->weekdays()
        //     ->timezone('Asia/Kolkata')
        //     ->name('hourly-data-1020');

        // // 11:20 AM - Collects 10:15-11:15 data
        // $schedule->command('options:historical-one-hour')
        //     ->dailyAt('11:20')
        //     ->weekdays()
        //     ->timezone('Asia/Kolkata')
        //     ->name('hourly-data-1120');

        // // 12:20 PM - Collects 11:15-12:15 data
        // $schedule->command('options:historical-one-hour')
        //     ->dailyAt('12:20')
        //     ->weekdays()
        //     ->timezone('Asia/Kolkata')
        //     ->name('hourly-data-1220');

        // // 1:20 PM - Collects 12:15-1:15 PM data
        // $schedule->command('options:historical-one-hour')
        //     ->dailyAt('13:20')
        //     ->weekdays()
        //     ->timezone('Asia/Kolkata')
        //     ->name('hourly-data-1320');

        // // 2:20 PM - Collects 1:15-2:15 PM data
        // $schedule->command('options:historical-one-hour')
        //     ->dailyAt('14:20')
        //     ->weekdays()
        //     ->timezone('Asia/Kolkata')
        //     ->name('hourly-data-1420');

        // // 3:20 PM - Collects 2:15-3:15 PM data
        // $schedule->command('options:historical-one-hour')
        //     ->dailyAt('15:20')
        //     ->weekdays()
        //     ->timezone('Asia/Kolkata')
        //     ->name('hourly-data-1520');

        // 4:20 PM - Collects 3:15-3:30 PM data (final candle)
        // $schedule->command('options:historical-one-hour')
        //     ->dailyAt('16:20')
        //     ->weekdays()
        //     ->timezone('Asia/Kolkata')
        //     ->name('hourly-data-1620');
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
