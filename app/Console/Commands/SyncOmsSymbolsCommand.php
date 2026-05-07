<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\OmsSymbolSyncHelper;

class SyncOmsSymbolsCommand extends Command
{
    protected $signature = 'oms:sync-symbols';
    protected $description = 'Sync new symbols from buildup data based on master configurations';

    public function handle()
    {
        $day = date("D");
        if (in_array($day, ['Sat', 'Sun'])) {
            $this->info('Weekend - Skipping symbol sync');
            return;
        }

        $helper = new OmsSymbolSyncHelper();
        $result = $helper->syncSymbols();
        
        $this->info("Symbol sync completed. Added: {$result['added']}, Errors: {$result['errors']}");
    }
}
