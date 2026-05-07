<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Every 30 seconds — full order status sync
        $schedule->command('oiiv:sync-orders')
            ->everyMinute()
            ->weekdays()
            ->between('09:15', '15:35')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(2)
            ->runInBackground();

        // Every 15 seconds during market hours — fast batch LTP fetch
        $schedule->command('oiiv:fetch-ltps')
            ->everyMinute()
            ->weekdays()
            ->between('09:15', '15:35')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(1)
            ->runInBackground();

        // 9:15 AM — place SL + profit orders for yesterday's OIIV positions
        $schedule->command('oiiv:btst-exit --phase=9am')
            ->weekdays()
            ->at('09:15')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(5)
            ->runInBackground();
        
        // 10:00 AM — sweep remaining open positions
        $schedule->command('oiiv:btst-exit --phase=10am')
            ->weekdays()
            ->at('10:00')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping(5)
            ->runInBackground();

        $schedule->command('angel_instrument:daily_update')->dailyAt('8:30')->sendOutputTo('command4_output.log');

        // Daily instrument update at 8:00 AM
        $schedule->command('zerodha_instrument:insert')->dailyAt('8:00')->sendOutputTo(storage_path('logs/instrument_update.log'));

        // ── FUT Contrarian OI Auto Trading ───────────────────────────────────────
        // 30-min window: runs at 10:05 (data for 09:45 candle should be ingested)
        $schedule->command('fut-contrarian:30min')
            ->weekdays()
            ->dailyAt('10:05')
            ->timezone('Asia/Kolkata')
            ->when(fn() => \App\Models\OptionOhlcData::whereDate('trade_date', now()->format('Y-m-d'))
                ->whereRaw("TIME(interval_time) = '09:45:00'")->exists())
            ->withoutOverlapping(5);
        
        // 1-HR window: runs at 10:35 (data for 10:15 candle should be ingested)
        $schedule->command('fut-contrarian:1hr')
            ->weekdays()
            ->dailyAt('10:35')
            ->timezone('Asia/Kolkata')
            ->when(fn() => \App\Models\OptionOhlcData::whereDate('trade_date', now()->format('Y-m-d'))
                ->whereRaw("TIME(interval_time) = '10:15:00'")->exists())
            ->withoutOverlapping(5);

        // ORDER PLACEMNET FOR - PIVOT 30MIN
        // $schedule->command('pivot:place-orders')
        // ->cron('18,48 * * * *')
        // ->weekdays()
        // ->timezone('Asia/Kolkata')        // ← ADD THIS
        // ->between('9:45', '15:55')    // first run 09:48, last run 15:48
        // ->appendOutputTo(storage_path('logs/pivot-order-placement.log'));

        // FOR 1HR OHLC DATA COLLECTION - LIVE MARKET
        $schedule->command('options:live-collect-30min')
        // ->cron('16,46 * * * *')
        ->cron('16 * * * *')
        ->weekdays()
        ->timezone('Asia/Kolkata')  
        ->between('9:30', '16:20')
        ->appendOutputTo(storage_path('logs/live-30min-ohlc-collect.log'));

        // ORIGNAL 30MIN OHLC COLLECTION - LIVE MARKET
        $schedule->command('options:live-collect-original-30min')
        ->cron('16,46 * * * *')   // runs at :16 and :46 — captures bars closing at :15 and :45
        ->weekdays()
        ->timezone('Asia/Kolkata')
        ->between('9:30', '15:50')
        ->appendOutputTo(storage_path('logs/live-original-30min-ohlc.log'));
        
        // EVERY 15 Minute Run Data Collection - OHLC DATA 15 MIN.
        // $schedule->command('options:live-collect')
        // // ->everyFifteenMinutes()
        // ->cron('1,16,31,46 * * * *')
        // ->weekdays()
        // // ->between('9:15', '15:30')
        // ->between('9:30', '15:40')
        // // ->withoutOverlapping()    
        // // ->runInBackground()       
        // ->appendOutputTo(storage_path('logs/live-ohlc-collect.log'));

        $schedule->command('options:live-collect')
        ->cron('1,16,31,46 * * * *')   // :01, :16, :31, :46 every hour
        ->weekdays()                    // Monday–Friday only
        ->between('9:30', '15:40')  // only run when actually needed
        ->appendOutputTo(storage_path('logs/live-ohlc-collect.log'));

        // $schedule->command('options:collect-next-series-daily')
        // ->cron('1,16,31,46 * * * *')   // :01, :16, :31, :46 every hour
        // ->weekdays()                    // Monday–Friday only
        // ->between('9:30', '15:40')  // only run when actually needed
        // ->appendOutputTo(storage_path('logs/live-ohlc-collect.log'));

        // 12:15PM OI DATA -- ORDER FOR 12:15PM OI DATA
        $schedule->command('pece:9to12-auto-trade')
        ->dailyAt('12:20')
        ->timezone('Asia/Kolkata')
        ->when(function () {
            // Check if OI data exists for today
            $today = now()->format('Y-m-d');
            return \App\Models\OptionStrike::where('trading_date', $today)
                ->exists();
        })
        ->withoutOverlapping(5);

        // 3PM OI DATA -- ORDER FOR 3PM OI DATA
        $schedule->command('pece:auto-trade')
        ->dailyAt('15:05')
        ->timezone('Asia/Kolkata')
        ->when(function () {
            // Check if OI data exists for today
            $today = now()->format('Y-m-d');
            return \App\Models\OptionStrike::where('trading_date', $today)
                ->exists();
        })
        ->withoutOverlapping(5);

        // $schedule->command('pivot:place-orders')
        // ->cron('5,20,35,50 * * * *')
        // ->weekdays()
        // ->between('9:30', '15:30')
        // ->appendOutputTo(storage_path('logs/pivot-orders.log'));

        // ── 1HR PIVOT COMMAND ──────────────────────────────────────────────────────
        // Only fires configs with interval_type = '1hr'
        // Slot detection: getLastCompletedSlot() — bar complete at open+60min
        // ──────────────────────────────────────────────────────────────────────────
        // $schedule->command('pivot:place-orders')
        // ->cron('18,48 * * * *')           // :18 and :48 every hour
        // ->weekdays()
        // ->between('9:30', '15:45')
        // ->withoutOverlapping(10)           // prevent stacking if it runs long
        // ->appendOutputTo(storage_path('logs/pivot-orders.log'));
        
        
        // ── 15MIN PIVOT COMMAND ────────────────────────────────────────────────────
        // Only fires configs with interval_type = '15min'
        // Slot detection: getLastCompletedSlot() — bar complete at open+15min
        // ──────────────────────────────────────────────────────────────────────────
        // $schedule->command('pivot15:place-orders')
        // ->cron('5,20,35,50 * * * *')   // 5 min after each 15-min candle close
        // ->weekdays()
        // ->between('9:20', '15:35')
        // ->withoutOverlapping(10)
        // ->appendOutputTo(storage_path('logs/pivot15-orders.log'));

        // Daily OHLC collector — runs at 15:50 every weekday
        $schedule->command('options:collect-daily-ohlc')
        ->dailyAt('16:00')
        ->timezone('Asia/Kolkata')
        ->weekdays()
        ->withoutOverlapping(5)
        ->appendOutputTo(storage_path('logs/daily-ohlc-collect.log'));
       

        // MCX ORDER PLACEMENT
        // $schedule->command('mcx:pece-auto-trade')
        // ->dailyAt('23:04')
        // ->timezone('Asia/Kolkata')
        // ->when(function () {
        //     // Check if OI data exists for today
        //     $today = now()->format('Y-m-d');
        //     return \App\Models\OptionStrike::where('trading_date', $today)
        //         ->exists();
        // })
        // ->withoutOverlapping(5);

        // Fetch positions daily at 9:20 AM on weekdays
        $schedule->command('positions:fetch-daily --all')->weekdays()->everyMinute()->timezone('Asia/Kolkata');

        // 🔎 TEST CRON - Runs every minute
        $schedule->command('cron:test')->everyMinute();

        // SMART MONEY DAILY COMMAND
        $schedule->command('stocks:collect-daily-ohlc --mode=live')
        ->weekdays()
        ->dailyAt('16:05')
        ->timezone('Asia/Kolkata')
        ->appendOutputTo(storage_path('logs/stock-daily-ohlc.log'));
        
        // MCX START
        $schedule->command('mcx:live-collect-3hr')
        ->cron('1 12,15,18,21 * * 1-5')   // Mon–Fri at 12:01, 15:01, 18:01, 21:01
        ->appendOutputTo(storage_path('logs/mcx-3hr-collect.log'));

        $schedule->command('mcx:live-collect-3hr')
        ->cron('1 0 * * 2-6')             // Tue–Sat 00:01 — collects 21:00 bar
        ->appendOutputTo(storage_path('logs/mcx-3hr-collect.log'));

        // MCX ORDER PLACEMENT
        $schedule->command('mcx:place-pivot-orders')
        ->cron('4 12,15,18,21 * * 1-5')   // Mon–Fri at 12:04, 15:04, 18:04, 21:04
        ->appendOutputTo(storage_path('logs/mcx-pivot-orders.log'));

        $schedule->command('mcx:place-pivot-orders')
        ->cron('4 0 * * 2-6')             // Tue–Sat 00:04 — orders for 21:00 bar
        ->appendOutputTo(storage_path('logs/mcx-pivot-orders.log'));
        // MCX START END


    //    $schedule->command('sync:broker-positions')->everyMinute();

        // WILL SEE THIS...
        // $schedule->command('futures:maintenance --clean-days=30 --auto-rollover --rollover-days=7')
        // ->dailyAt('08:00')
        // ->timezone('Asia/Kolkata')
        // ->sendOutputTo(storage_path('logs/futures_maintenance.log'));

        // ORDER FOR 5 MIN ONLY...
        // $schedule->command('zerodha:auto-trade')
        // ->everyMinute()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/zerodha_auto_trade_1min.log'))
        // ->appendOutputTo(storage_path('logs/zerodha_auto_trade_1min_all.log'));

        // $schedule->command('futures:fetch-1min')
        // ->everyMinute()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     // Only run Monday to Friday during market hours
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1) // Prevent overlap, max 1 minute wait
        // ->sendOutputTo(storage_path('logs/futures_1min.log'))
        // ->appendOutputTo(storage_path('logs/futures_1min_all.log'));

        // $schedule->command('futures:fetch-5min')
        // ->everyFiveMinutes()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     // Only run Monday to Friday during market hours
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1) // Prevent overlap, max 5 minute wait
        // ->sendOutputTo(storage_path('logs/futures_5min.log'))
        // ->appendOutputTo(storage_path('logs/futures_5min_all.log'));

        
        // $schedule->command('futures:fetch-15min')
        // // ->everyFiveMinutes()
        // ->cron('16,31,46,01 * * * *')
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     // Only run Monday to Friday during market hours
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1) // Prevent overlap, max 5 minute wait
        // ->sendOutputTo(storage_path('logs/futures_15min.log'))
        // ->appendOutputTo(storage_path('logs/futures_15min_all.log'));


        // ORDER FOR 15 MIN ONLY...
        // $schedule->command('zerodha:auto-trade-15min')
        // ->everyMinute()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/zerodha_auto_trade_15min.log'))
        // ->appendOutputTo(storage_path('logs/zerodha_auto_trade_15min_all.log'));

        // FOR EXPIRY DATA
        // $schedule->command('expiry:fetch-1min')
        // ->everyMinute()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/expiry_data.log'))
        // ->appendOutputTo(storage_path('logs/expiry_data_all.log'));

        // FOR EXPIRY ORDER PLACEMENT
        // $schedule->command('expiry:auto-trade')
        // ->everyMinute()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/expiry_order.log'))
        // ->appendOutputTo(storage_path('logs/expiry_order_all.log'));

        // $schedule->command('symbols:fetch-5min')
        // ->cron('1,6,11,16,21,26,31,36,41,46,51,56 9-15 * * 1-5')
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/symbol_data_5min.log'))
        // ->appendOutputTo(storage_path('logs/symbol_data_all.log'));

        // $schedule->command('symbol:auto-trade-5min')
        // ->cron('2,7,12,17,22,27,32,37,42,47,52,57 9-15 * * 1-5')
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/symbol_order_5min.log'))
        // ->appendOutputTo(storage_path('logs/symbol_order_all.log'));

        // ============================================
        // 1-MINUTE SYMBOL DATA FETCH (runs at :00 seconds)
        // ============================================
        // $schedule->command('symbols:fetch-1min')
        // ->everyMinute()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/symbol_data_1min.log'))
        // ->appendOutputTo(storage_path('logs/symbol_data_all.log'));

        // ============================================
        // 1-MINUTE AUTO TRADING (runs at :30 seconds)
        // ============================================
        // $schedule->command('symbol:auto-trade-1min')
        // ->everyMinute()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && 
        //         $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/symbol_order_1min.log'))
        // ->appendOutputTo(storage_path('logs/symbol_order_all.log'));


        // $schedule->command('target:monitor')
        // ->everyFiveMinutes()
        // ->between('09:00', '15:45')
        // ->weekdays()
        // ->timezone('Asia/Kolkata')
        // ->withoutOverlapping(10) // Prevent overlapping runs with 10 min expiry
        // ->onSuccess(function () {
        //     \Log::info('Auto target monitoring completed successfully');
        // })
        // ->onFailure(function () {
        //     \Log::error('Auto target monitoring failed');
        // });

        // $schedule->command('target:cleanup')
        // ->weeklyOn(0, '02:00')
        // ->timezone('Asia/Kolkata');


        // ========== OPTION STRIKES UPDATE (Daily at 9:00 AM) ==========
        // $schedule->command('options:update-strikes')
        //     ->dailyAt('09:00')
        //     ->timezone('Asia/Kolkata')
        //     ->when(function () {
        //         $currentTime = Carbon::now('Asia/Kolkata');
        //         $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 0);
                
        //         return $currentTime->isWeekday() && 
        //             !$this->isMarketHoliday($currentTime->format('Y-m-d'));
        //     })
        //     ->withoutOverlapping(5)
        //     ->sendOutputTo(storage_path('logs/option_strikes_update.log'))
        //     ->appendOutputTo(storage_path('logs/option_strikes_all.log'));
        
        // ========== OPTION OI DATA FETCH (Every Minute during market hours) ==========
        // $schedule->command('options:fetch-oi')
        //     ->everyMinute()
        //     ->when(function () {
        //         $currentTime = Carbon::now('Asia/Kolkata');
        //         $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //         $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
                
        //         return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose) &&
        //             !$this->isMarketHoliday($currentTime->format('Y-m-d'));
        //     })
        //     ->withoutOverlapping(1)
        //     ->sendOutputTo(storage_path('logs/option_oi_fetch_1min.log'))
        //     ->appendOutputTo(storage_path('logs/option_oi_fetch_all.log'));


        ////////// 5 MIN DATA FETCH  //////////////
        // $schedule->command('symbols:fetch-5min')
        // ->everyFiveMinutes()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     // return $currentTime->isWeekday() && 
        //     //         $currentTime->between($marketOpen, $marketClose);
        //     return $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/symbol_data_5min.log'))
        // ->appendOutputTo(storage_path('logs/symbol_data_all.log'));


        ////////// 5 MIN ORDER PLACEMNET  //////////////
        // $schedule->command('symbol:auto-trade-5min')
        // ->everyFiveMinutes()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     // return $currentTime->isWeekday() && 
        //     //     $currentTime->between($marketOpen, $marketClose);
        //     return $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/symbol_order_5min.log'))
        // ->appendOutputTo(storage_path('logs/symbol_order_all.log'));

        
        ////////// 15 MIN DATA FETCH  //////////////
        // $schedule->command('symbols:fetch-15min')
        // ->everyFifteenMinutes()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/symbol_data.log'))
        // ->appendOutputTo(storage_path('logs/symbol_data_all.log'));
        // $schedule->command('symbols:fetch-15min')
        // ->cron('1,16,31,46 9-15 * * 1-5')  // Run 1 min AFTER candle close
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 16);  // After first candle
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 31);
            
        //     return $currentTime->isWeekday() && $currentTime->between($marketOpen, $marketClose);
        // });

        // $schedule->command('options:select-strikes')
        // ->cron('1,16,31,46 9-15 * * 1-5')  // ✅ Every 15 min during market hours
        // ->timezone('Asia/Kolkata')
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 16);  
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 31);
            
        //     return $currentTime->isWeekday() && $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(10)
        // ->sendOutputTo(storage_path('logs/select-strikes.log'));

        ////////// 15 MIN ORDER PLACEMNET  //////////////
        // $schedule->command('symbol:auto-trade-15min')
        // // ->cron('2,17,32,47 9-15 * * 1-5')
        // ->everyTwoMinutes()
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     return $currentTime->isWeekday() && 
        //             $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(1)
        // ->sendOutputTo(storage_path('logs/symbol_order.log'))
        // ->appendOutputTo(storage_path('logs/symbol_order_all.log'));


        // One-Percent Auto Trading (5-minute check)
        // $schedule->command('onepercent:auto-trade')
        //     ->everyTwoMinutes()
        //     ->when(function () {
        //         $currentTime = Carbon::now('Asia/Kolkata');
        //         $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //         $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
                
        //         // return $currentTime->isWeekday() && 
        //         //     $currentTime->between($marketOpen, $marketClose);
        //         return $currentTime->between($marketOpen, $marketClose);
        //     })
        //     ->withoutOverlapping(1)
        //     ->sendOutputTo(storage_path('logs/onepercent_auto.log'))
        //     ->appendOutputTo(storage_path('logs/all_auto_trades.log'));


        // $schedule->command('options:fetch-oi')
        // ->dailyAt('16:00')
        // ->timezone('Asia/Kolkata')
        // ->when(function () {
        //     $currentTime = Carbon::now('Asia/Kolkata');
        //     $marketOpen = Carbon::today('Asia/Kolkata')->setTime(9, 15);
        //     $marketClose = Carbon::today('Asia/Kolkata')->setTime(15, 30);
            
        //     // return $currentTime->isWeekday() && 
        //     //     $currentTime->between($marketOpen, $marketClose);
        //     return $currentTime->between($marketOpen, $marketClose);
        // })
        // ->withoutOverlapping(30)
        // ->sendOutputTo(storage_path('logs/option_oi_fetch_daily.log'))
        // ->appendOutputTo(storage_path('logs/option_oi_fetch_all.log'));

        // ============================================
        // INTRADAY OI + IV FETCH (15minute interval)
        // Runs at 12:15 PM (Previous day 12:15 to Current day 12:15)
        // ============================================
        // $schedule->command('options:fetch-oi-intraday')
        //     ->dailyAt('12:15')
        //     ->timezone('Asia/Kolkata')
        //     ->when(function () {
        //         $currentTime = Carbon::now('Asia/Kolkata');
                
        //         // Special trading Sunday
        //         if ($currentTime->format('Y-m-d') === '2026-02-01') {
        //             return true;
        //         }
                
        //         // Skip weekends
        //         if ($currentTime->isWeekend()) {
        //             return false;
        //         }
                
        //         // Skip holidays
        //         $isHoliday = DB::table('market_holidays')
        //             ->where('market_name', 'NSE')
        //             ->where('holiday_date', $currentTime->format('Y-m-d'))
        //             ->exists();
                
        //         return !$isHoliday;
        //     })
        //     ->withoutOverlapping(30)
        //     ->sendOutputTo(storage_path('logs/option_oi_iv_fetch_intraday.log'))
        //     ->appendOutputTo(storage_path('logs/option_oi_iv_fetch_all.log'));

        // $schedule->command('oiiv:auto-trade')
        // ->cron('*/1 15-15 * * 1-5')  // Every minute from 3:00-3:59 PM Mon-Fri
        // ->timezone('Asia/Kolkata')
        // ->when(function () {
        //     // Check if OI data exists for today
        //     $today = now()->format('Y-m-d');
        //     return \App\Models\OptionStrike::where('trading_date', $today)
        //         ->exists();
        // })
        // ->withoutOverlapping(5);
        
        // $schedule->command('oiiv:auto-trade')
        // ->cron('15-30 15 * * 1-5') // Every minute from 3:15-3:30 PM Mon-Fri
        // ->timezone('Asia/Kolkata')
        // ->when(function () {
        //     // Check if OI data exists for today
        //     $today = now()->format('Y-m-d');
        //     return \App\Models\OptionStrike::where('trading_date', $today)
        //         ->exists();
        // })
        // ->withoutOverlapping(5);

        // $schedule->command('oiiv:auto-trade')
        // ->cron('0-30 15 * * 1-5') // Every minute from 3:00-3:30 PM Mon-Fri
        // ->timezone('Asia/Kolkata')
        // ->when(function () {
        //     // Check if OI data exists for today
        //     $today = now()->format('Y-m-d');
        //     return \App\Models\OptionStrike::where('trading_date', $today)
        //         ->exists();
        // })
        // ->withoutOverlapping(5);
        
        // Auto square-off at 9:16 AM on weekdays
        // $schedule->command('positions:auto-square-off --all')
        //     ->weekdays()
        //     ->at('09:16')
        //     ->timezone('Asia/Kolkata');

        // positions:fetch-daily


        // ── Run every 2 minutes during market hours (9:15 AM to 3:30 PM IST) ──
        // $schedule->command('positions:sync')
        //     ->everyTwoMinutes()
        //     ->between('09:15', '15:35')
        //     ->timezone('Asia/Kolkata')
        //     ->withoutOverlapping()
        //     ->runInBackground();

        // // ── Run once more at 3:35 PM to capture final close-of-day state ──
        // $schedule->command('positions:sync')
        //     ->dailyAt('15:40')
        //     ->timezone('Asia/Kolkata')
        //     ->withoutOverlapping();


        // // STOCK FETCHING
        // // ── STEP 1: Collect OHLC (your existing command) ──────────────────────
        // // Fetches today's EOD OHLC from Zerodha for all active symbols.
        // $schedule
        //     ->command('stocks:collect-daily-ohlc --mode=live')
        //     ->weekdays()
        //     ->dailyAt('16:05')
        //     ->timezone('Asia/Kolkata')
        //     ->withoutOverlapping(30)          // max lock time 30 minutes
        //     ->appendOutputTo(storage_path('logs/step1-ohlc.log'));

        // // ── STEP 2: Pivot Detection ───────────────────────────────────────────
        // // Detects swing highs/lows from the last 60 days of OHLC.
        // // --days=60 ensures any new bar from today is scanned.
        // // The command is idempotent (updateOrCreate) — safe to re-run.
        // $schedule
        //     ->command('stocks:generate-pivots --days=60')
        //     ->weekdays()
        //     ->dailyAt('16:10')
        //     ->timezone('Asia/Kolkata')
        //     ->withoutOverlapping(20)
        //     ->appendOutputTo(storage_path('logs/step2-pivots.log'));

        // // ── STEP 3: Pattern Detection ─────────────────────────────────────────
        // // Scans the last 30 candles for breakouts, double tops/bottoms, etc.
        // // Uses the pivots generated in step 2.
        // $schedule
        //     ->command('stocks:generate-patterns')
        //     ->weekdays()
        //     ->dailyAt('16:15')
        //     ->timezone('Asia/Kolkata')
        //     ->withoutOverlapping(20)
        //     ->appendOutputTo(storage_path('logs/step3-patterns.log'));

        // // ── STEP 4: Feature Engineering ───────────────────────────────────────
        // // Computes RSI, trend, vol, SMA, distances for the last 5 days.
        // // --days=5 is fast and sufficient for daily runs.
        // // On the FIRST run (bootstrap), use: stocks:generate-features (no --days)
        // $schedule
        //     ->command('stocks:generate-features --days=5')
        //     ->weekdays()
        //     ->dailyAt('16:18')
        //     ->timezone('Asia/Kolkata')
        //     ->withoutOverlapping(20)
        //     ->appendOutputTo(storage_path('logs/step4-features.log'));

        // // ── STEP 5: Signal Generation ─────────────────────────────────────────
        // // Runs confluence scoring for ALL symbols for today's date.
        // // Output: stock_signals rows with BUY/SELL/HOLD + confidence + reason.
        // $schedule
        //     ->command('stocks:generate-signals')
        //     ->weekdays()
        //     ->dailyAt('16:22')
        //     ->timezone('Asia/Kolkata')
        //     ->withoutOverlapping(20)
        //     ->appendOutputTo(storage_path('logs/step5-signals.log'));

        $schedule->command('auro:daily-verdict')->weekdays()->at('14:52');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
