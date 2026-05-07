<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\OmsConfigCron;
use App\Helpers\OmsConfigCronOrder;

class OmsConfigSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oms-config:command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'place order to kite api';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $day =  date("D");
        if(in_array($day,['Sat','Sun'])){
            return;
        }
        
        $obj = new OmsConfigCron();
        $obj->placeOrder();
    }
}
