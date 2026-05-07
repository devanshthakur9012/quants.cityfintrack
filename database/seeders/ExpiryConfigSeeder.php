<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ExpiryConfig;

class ExpiryConfigSeeder extends Seeder
{
    
    public function run()
    {
        ExpiryConfig::updateOrCreate(
            [
                'scope' => 'global',
                'name' => 'default'
            ],
            [
                'supertrend_atr_period' => 10,
                'supertrend_multiplier' => 3.0,
                'is_active' => true,
                'description' => 'Default Supertrend configuration for expiry trading'
            ]
        );

        $this->command->info('✅ Expiry config seeded successfully!');
    }
}
