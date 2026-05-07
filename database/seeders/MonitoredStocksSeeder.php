<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MonitoredStock;
use App\Models\ZerodhaInstrument;

class MonitoredStocksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get instrument token for AXISBANK from zerodha_instruments table
        $axisbank = ZerodhaInstrument::where('trading_symbol', 'AXISBANK')
            ->where('exchange', 'NSE')
            ->where('instrument_type', 'EQ')
            ->first();

        if (!$axisbank) {
            $this->command->warn('AXISBANK not found in zerodha_instruments. Run zerodha_instrument:insert first.');
            return;
        }

        MonitoredStock::updateOrCreate(
            [
                'trading_symbol' => 'AXISBANK',
                'exchange' => 'NSE'
            ],
            [
                'instrument_token' => $axisbank->instrument_token,
                'intervals' => '15minute,30minute,day', // You can customize intervals
                'is_active' => true
            ]
        );

        $this->command->info('✅ AXISBANK added to monitored stocks');

        // You can add more stocks here in future
        // Example:
        /*
        $infy = ZerodhaInstrument::where('trading_symbol', 'INFY')
            ->where('exchange', 'NSE')
            ->where('instrument_type', 'EQ')
            ->first();

        if ($infy) {
            MonitoredStock::updateOrCreate(
                [
                    'trading_symbol' => 'INFY',
                    'exchange' => 'NSE'
                ],
                [
                    'instrument_token' => $infy->instrument_token,
                    'intervals' => '15minute,30minute,day',
                    'is_active' => true
                ]
            );
            $this->command->info('✅ INFY added to monitored stocks');
        }
        */
    }
}