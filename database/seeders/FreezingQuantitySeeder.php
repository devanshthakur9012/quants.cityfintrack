<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FreezingQuantitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Freezing quantity is the maximum quantity that can be placed in a single order.
     * These limits are set by NSE to prevent market manipulation.
     */
    public function run()
    {
        $freezingQuantities = [
            // Index Options - High volume
            ['symbol' => 'NIFTY', 'freezing_quantity' => 1800],
            ['symbol' => 'BANKNIFTY', 'freezing_quantity' => 900],
            ['symbol' => 'FINNIFTY', 'freezing_quantity' => 1800],
            ['symbol' => 'MIDCPNIFTY', 'freezing_quantity' => 2800],
            
            // Banking & Financial Services
            ['symbol' => 'HDFCBANK', 'freezing_quantity' => 500],
            ['symbol' => 'ICICIBANK', 'freezing_quantity' => 1400],
            ['symbol' => 'SBIN', 'freezing_quantity' => 2700],
            ['symbol' => 'AXISBANK', 'freezing_quantity' => 1200],
            ['symbol' => 'KOTAKBANK', 'freezing_quantity' => 600],
            ['symbol' => 'INDUSINDBK', 'freezing_quantity' => 900],
            ['symbol' => 'BAJFINANCE', 'freezing_quantity' => 125],
            ['symbol' => 'BAJAJFINSV', 'freezing_quantity' => 600],
            ['symbol' => 'HDFCLIFE', 'freezing_quantity' => 1750],
            ['symbol' => 'SBILIFE', 'freezing_quantity' => 1000],
            
            // IT Services
            ['symbol' => 'TCS', 'freezing_quantity' => 300],
            ['symbol' => 'INFY', 'freezing_quantity' => 700],
            ['symbol' => 'WIPRO', 'freezing_quantity' => 2400],
            ['symbol' => 'HCLTECH', 'freezing_quantity' => 700],
            ['symbol' => 'TECHM', 'freezing_quantity' => 850],
            ['symbol' => 'LTIM', 'freezing_quantity' => 200],
            ['symbol' => 'TATAELXSI', 'freezing_quantity' => 125],
            
            // Energy & Power
            ['symbol' => 'RELIANCE', 'freezing_quantity' => 500],
            ['symbol' => 'ONGC', 'freezing_quantity' => 7700],
            ['symbol' => 'NTPC', 'freezing_quantity' => 5400],
            ['symbol' => 'POWERGRID', 'freezing_quantity' => 4800],
            ['symbol' => 'COALINDIA', 'freezing_quantity' => 3150],
            ['symbol' => 'ADANIPORTS', 'freezing_quantity' => 900],
            ['symbol' => 'ADANIENT', 'freezing_quantity' => 400],
            
            // Automotive
            ['symbol' => 'TATAMOTORS', 'freezing_quantity' => 1700],
            ['symbol' => 'M&M', 'freezing_quantity' => 550],
            ['symbol' => 'MARUTI', 'freezing_quantity' => 100],
            ['symbol' => 'EICHERMOT', 'freezing_quantity' => 250],
            ['symbol' => 'BAJAJ-AUTO', 'freezing_quantity' => 175],
            ['symbol' => 'HEROMOTOCO', 'freezing_quantity' => 250],
            ['symbol' => 'TVSMOTOR', 'freezing_quantity' => 550],
            
            // Pharmaceuticals
            ['symbol' => 'SUNPHARMA', 'freezing_quantity' => 900],
            ['symbol' => 'DRREDDY', 'freezing_quantity' => 175],
            ['symbol' => 'CIPLA', 'freezing_quantity' => 900],
            ['symbol' => 'DIVISLAB', 'freezing_quantity' => 300],
            ['symbol' => 'APOLLOHOSP', 'freezing_quantity' => 200],
            
            // FMCG
            ['symbol' => 'HINDUNILVR', 'freezing_quantity' => 425],
            ['symbol' => 'ITC', 'freezing_quantity' => 3100],
            ['symbol' => 'NESTLEIND', 'freezing_quantity' => 50],
            ['symbol' => 'BRITANNIA', 'freezing_quantity' => 250],
            ['symbol' => 'DABUR', 'freezing_quantity' => 2200],
            ['symbol' => 'MARICO', 'freezing_quantity' => 2100],
            
            // Metals & Mining
            ['symbol' => 'TATASTEEL', 'freezing_quantity' => 9700],
            ['symbol' => 'HINDALCO', 'freezing_quantity' => 2200],
            ['symbol' => 'JSWSTEEL', 'freezing_quantity' => 1500],
            ['symbol' => 'VEDL', 'freezing_quantity' => 3650],
            ['symbol' => 'SAIL', 'freezing_quantity' => 11000],
            ['symbol' => 'NMDC', 'freezing_quantity' => 7400],
            
            // Cement
            ['symbol' => 'ULTRACEMCO', 'freezing_quantity' => 125],
            ['symbol' => 'GRASIM', 'freezing_quantity' => 550],
            ['symbol' => 'AMBUJACEM', 'freezing_quantity' => 2400],
            ['symbol' => 'ACC', 'freezing_quantity' => 550],
            
            // Telecom
            ['symbol' => 'BHARTIARTL', 'freezing_quantity' => 900],
            ['symbol' => 'INDIGO', 'freezing_quantity' => 250],
            
            // Consumer Durables
            ['symbol' => 'TITAN', 'freezing_quantity' => 325],
            ['symbol' => 'ASIANPAINT', 'freezing_quantity' => 350],
            ['symbol' => 'PIDILITIND', 'freezing_quantity' => 400],
            ['symbol' => 'VOLTAS', 'freezing_quantity' => 900],
            
            // Infrastructure & Construction
            ['symbol' => 'LT', 'freezing_quantity' => 350],
            ['symbol' => 'ADANIGREEN', 'freezing_quantity' => 725],
            ['symbol' => 'BOSCHLTD', 'freezing_quantity' => 50],
            
            // Petrochemicals
            ['symbol' => 'BPCL', 'freezing_quantity' => 3900],
            ['symbol' => 'IOC', 'freezing_quantity' => 9800],
            
            // Retail
            ['symbol' => 'DMART', 'freezing_quantity' => 300],
            ['symbol' => 'TRENT', 'freezing_quantity' => 50],
            
            // Real Estate
            ['symbol' => 'DLF', 'freezing_quantity' => 1650],
            ['symbol' => 'GODREJPROP', 'freezing_quantity' => 700],
            
            // Others
            ['symbol' => 'SBICARD', 'freezing_quantity' => 1500],
            ['symbol' => 'LICHSGFIN', 'freezing_quantity' => 2100],
            ['symbol' => 'CHOLAFIN', 'freezing_quantity' => 950],
            ['symbol' => 'MUTHOOTFIN', 'freezing_quantity' => 650],
            ['symbol' => 'RECLTD', 'freezing_quantity' => 4500],
            ['symbol' => 'PFC', 'freezing_quantity' => 2900],
            ['symbol' => 'IRCTC', 'freezing_quantity' => 1500],
            ['symbol' => 'ZOMATO', 'freezing_quantity' => 5400],
            ['symbol' => 'PAYTM', 'freezing_quantity' => 1650],
            ['symbol' => 'NYKAA', 'freezing_quantity' => 4850],
            
            // BSE-listed popular stocks
            ['symbol' => 'SENSEX', 'freezing_quantity' => 1800],
            ['symbol' => 'BANKEX', 'freezing_quantity' => 1800],
        ];

        // Add timestamps
        $now = now();
        foreach ($freezingQuantities as &$item) {
            $item['is_active'] = true;
            $item['created_at'] = $now;
            $item['updated_at'] = $now;
        }

        // Insert in chunks to avoid memory issues
        $chunks = array_chunk($freezingQuantities, 50);
        foreach ($chunks as $chunk) {
            DB::table('freezing_quantities')->insert($chunk);
        }

        $this->command->info('✅ Inserted ' . count($freezingQuantities) . ' freezing quantities');
    }
}