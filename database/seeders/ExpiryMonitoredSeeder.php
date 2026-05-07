<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ExpiryMonitored;
use Illuminate\Support\Facades\DB;

class ExpiryMonitoredSeeder extends Seeder
{
    public function run()
    {
        $symbols = [
            [
                'symbol' => 'NIFTY',
                'exchange' => 'NSE',
                'instrument_token' => '256265', // NIFTY 50 index token
                'is_active' => true
            ],
            [
                'symbol' => 'BANKNIFTY',
                'exchange' => 'NSE',
                'instrument_token' => '260105', // BANK NIFTY index token
                'is_active' => true
            ],
            [
                'symbol' => 'SENSEX',
                'exchange' => 'BSE',
                'instrument_token' => '265', // SENSEX index token
                'is_active' => true
            ]
        ];

        foreach ($symbols as $symbol) {
            ExpiryMonitored::updateOrCreate(
                ['symbol' => $symbol['symbol']],
                $symbol
            );
        }

        $this->command->info('✅ Expiry monitored symbols seeded successfully!');
    }
}

// php artisan db:seed --class=ExpiryMonitoredSeeder
// ALTER TABLE `order_books` ADD `expiry_auto_order_id` INT NULL AFTER `zerodha_auto_order_id`;
// php artisan db:seed --class=ExpiryConfigSeeder
