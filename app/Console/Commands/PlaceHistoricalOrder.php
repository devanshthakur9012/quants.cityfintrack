<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\HistoricalOrderPlace;

class PlaceHistoricalOrder extends Command
{
    protected $signature = 'historical-order:placed';
    protected $description = 'place order to kite api';
    
    public function handle()
    {
        $day = date("D");
        if(in_array($day,['Sat','Sun'])){
            return;
        }
        
        $obj = new HistoricalOrderPlace();
        $obj->placeOrder();
    }
}
