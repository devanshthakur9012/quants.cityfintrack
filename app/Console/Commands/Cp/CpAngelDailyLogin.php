<?php

namespace App\Console\Commands\Cp;

use App\Models\BrokerApi;
use App\Services\Broker\AngelBrokerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CpAngelDailyLogin extends Command
{
    protected $signature = 'cp:angel-login {--broker= : Specific broker_api ID}';
    protected $description = 'Refresh Angel One access tokens for all active Angel brokers before market open';

    public function handle()
    {
        $query = BrokerApi::where('client_type', 'AngelOne');
        if ($this->option('broker')) {
            $query->where('id', $this->option('broker'));
        }

        $brokers = $query->get();
        if ($brokers->isEmpty()) {
            $this->warn('No Angel One brokers found.');
            return 0;
        }

        foreach ($brokers as $broker) {
            try {
                (new AngelBrokerService($broker))->login();
                $this->info("✓ Broker #{$broker->id} ({$broker->account_user_name}) logged in.");
            } catch (\Exception $e) {
                $this->error("✗ Broker #{$broker->id}: {$e->getMessage()}");
                Log::error("CpAngelDailyLogin: broker #{$broker->id} — {$e->getMessage()}");
            }
        }

        return 0;
    }
}