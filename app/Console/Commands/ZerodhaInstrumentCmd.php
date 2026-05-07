<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\ZerodhaInstrument;
use App\Models\OrderBook;
class ZerodhaInstrumentCmd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zerodha_instrument:insert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'daily insert zerodha instruments in database';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = date("Y-m-d");
        $dayName = date("l");

        //----------------------------------------
        // 1️⃣ Skip Saturday & Sunday
        //----------------------------------------
        // if ($dayName == "Saturday" || $dayName == "Sunday") {
        //     $this->info("Skipped: Weekend ($dayName)");
        //     return 0;
        // }

        //----------------------------------------
        // 2️⃣ Skip if market holiday from DB
        //----------------------------------------
        $isHoliday = \DB::table('market_holidays')
            ->where('market_name', 'NSE')
            ->where('holiday_date', $today)
            ->exists();

        // if ($isHoliday) {
        //     $this->info("Skipped: Market Holiday ($today)");
        //     return 0;
        // }

        set_time_limit(0);

        $content = file_get_contents('https://api.kite.trade/instruments');
        file_put_contents(public_path('file.csv'), $content);
        $file = fopen(public_path('file.csv'), 'r');
        if($file != false){
            ZerodhaInstrument::truncate();
            $header = fgetcsv($file);
            while (($row = fgetcsv($file)) !== false) {
                if(empty($row[0])){
                    break;
                }
                $zerodhaObj = new ZerodhaInstrument();
                $zerodhaObj->instrument_token = $row[0];
                $zerodhaObj->exchange_token = $row[1];
                $zerodhaObj->trading_symbol = $row[2];
                $zerodhaObj->name = $row[3];
                $zerodhaObj->last_price = $row[4];
                $zerodhaObj->expiry = $row[5];
                $zerodhaObj->strike = $row[6];
                $zerodhaObj->tick_size = $row[7];
                $zerodhaObj->lot_size = $row[8];
                $zerodhaObj->instrument_type = $row[9];
                $zerodhaObj->segment = $row[10];
                $zerodhaObj->exchange = $row[11];
                $zerodhaObj->save();
            }
        }        
        fclose($file);
        $this->info("Zerodha Instrument Inserted Successfully for $today");
        return 0;
    }
}
