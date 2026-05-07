<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\NewOmsConfigCron;

class NewOmsConfigSchedule extends Command
{
    /**
     *
     * @var string
     */
    protected $signature = 'new-oms-config:command';

    /**
     *
     * @var string
     */
    protected $description = 'place order to kite api';

    /**
     *
     * @return int
     */
    public function handle()
    {
        $day = date("D");
        if(in_array($day,['Sat','Sun'])){
            return;
        }
        
        $obj = new NewOmsConfigCron();
        $obj->placeOrder();
    }
}
