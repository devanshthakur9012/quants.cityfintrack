<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\OmsConfigCronRt;
class OmsConfigScheduleRt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oms-config-rt:command';

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
        
        $obj = new OmsConfigCronRt();
        $obj->placeOrder();
    }
}
