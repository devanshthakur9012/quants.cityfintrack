<?php

namespace App\Console\Commands;

use App\Models\OptionsChain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\AngelApiAuth;

class GenerateOptionsChain extends Command
{
    use AngelApiAuth;

    protected $signature = 'options:generate-chain';
    protected $description = 'Generate options chain data for all symbols from symbol_lists table';

    public function handle()
    {
        set_time_limit(0);
        
        $this->info("Starting Options Chain Generation...");

        try {
            $neededSymbol = ['AXISBANK','BAJFINANCE','BHARTIARTL','DRREDDY','CIPLA','SHRIRAMFIN','CHOALFIN','PAYTM','NIFTY','BANKNIFTY'];

            // Get all symbols with their step_value from symbol_lists table
            $symbols = DB::table('symbol_lists')
            ->orderBy('id', 'DESC')
            ->orderBy('is_early')
            ->whereIn('symbol', $neededSymbol)
            ->get(['underlying', 'symbol', 'step_value', 'symbol_token']);
            
            if ($symbols->isEmpty()) {
                $this->error('No symbols found in symbol_lists table.');
                return 1;
            }

            $this->info("Found " . count($symbols) . " symbols to process.");

            // Clear existing options chain data
            OptionsChain::truncate();
            DB::beginTransaction();
            
            $totalProcessed = 0;
            $totalErrors = 0;

            foreach ($symbols as $symbolData) {
                try {
                    // Pass dynamic step_value into processSymbol
                    $result = $this->processSymbol(
                        $symbolData->symbol,
                        $symbolData->underlying,
                        $symbolData->step_value,
                        $symbolData->symbol_token
                    );

                    if ($result) {
                        $totalProcessed++;
                        $this->info("✓ Processed: {$symbolData->symbol}");
                    } else {
                        $totalErrors++;
                        $this->error("✗ Failed: {$symbolData->symbol} (No future found or no options)");
                    }
                } catch (\Exception $e) {
                    $totalErrors++;
                    $this->error("✗ Error processing {$symbolData->symbol}: " . $e->getMessage());
                    Log::error("Options chain generation error for {$symbolData->symbol}: " . $e->getMessage());
                }
            }

            DB::commit();
            
            $this->info("\n=== Summary ===");
            $this->info("Total Symbols: " . count($symbols));
            $this->info("Successfully Processed: {$totalProcessed}");
            $this->info("Errors: {$totalErrors}");
            $this->info("Options Chain Generation Completed!");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Options chain generation failed: " . $e->getMessage());
            $this->error('Critical Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Process a single symbol and generate its options chain
     */
    private function processSymbol($symbol, $underlying, $stepValue, $token)
    {
        // Step 1: Get Future symbol
        $future = $this->getFutureSymbol($symbol);
        if (!$future) {
            return false;
        }

        // Step 2: Get a sample future price (replace with actual API call later)
        $futPrice = $this->getSampleFuturePrice($token);

        // Step 3: Calculate strikes using dynamic step_value
        $strikes = $this->calculateStrikes($stepValue, $futPrice);

        // Step 4: Get options for each strike
        $optionsData = [];
        foreach ($strikes as $position => $strike) {
            $options = $this->getOptionsForStrike($symbol, $strike);
            // dd($symbol, $strike, $options);

            if (!empty($options['CE']) || !empty($options['PE'])) {
                $optionsData[] = [
                    'underlying' => $symbol,
                    'future_symbol' => $future->symbol_name ?? null,
                    'future_token' => $future->token ?? null,
                    'future_price' => $futPrice,
                    'strike_price' => $strike,
                    'strike_position' => $position - 3, 
                    
                    // CE Data
                    'ce_symbol' => $options['CE']->symbol_name ?? null,
                    'ce_token' => $options['CE']->token ?? null,
                    'ce_lotsize' => $options['CE']->lotsize ?? null,
                    'ce_exch_seg' => $options['CE']->exch_seg ?? null,
                    'ce_expiry' => $options['CE']->expiry ?? null,
                    'ce_tick_size' => $options['CE']->tick_size ?? null,
                    
                    // PE Data
                    'pe_symbol' => $options['PE']->symbol_name ?? null,
                    'pe_token' => $options['PE']->token ?? null,
                    'pe_lotsize' => $options['PE']->lotsize ?? null,
                    'pe_exch_seg' => $options['PE']->exch_seg ?? null,
                    'pe_expiry' => $options['PE']->expiry ?? null,
                    'pe_tick_size' => $options['PE']->tick_size ?? null,
                    
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Step 5: Insert into database
        if (!empty($optionsData)) {
            OptionsChain::insert($optionsData);
            return true;
        }

        return false;
    }

    /**
     * Get future symbol for underlying
     */
    private function getFutureSymbol($underlying)
    {
        $instrumentType = "FUTSTK";
        $indexSymbol = ['NIFTY','BANKNIFTY'];
        if(in_array($underlying,$indexSymbol)){
            $instrumentType = "FUTIDX";
        }

        return DB::table('angel_api_instruments')
            ->where('name', $underlying)
            ->where('instrumenttype', $instrumentType)
            ->where('exch_seg', 'NFO') 
            ->orderByRaw("STR_TO_DATE(expiry, '%d%b%Y') ASC")
            ->first();
    }

    private function getSampleFuturePrice($underlying)
    {
        try {
            
            $jwtToken =  $this->generate_access_token();
            $data = $this->get_average_price('NSE',$underlying,$jwtToken);
            
            if ($data['status'] && isset($data['data']['fetched'][0]['ltp'])) {
                return (float) $data['data']['fetched'][0]['ltp'];
            }
            
            return 1000; // fallback
            
        } catch (\Exception $e) {
            Log::error("Failed to get real price for {$underlying}: " . $e->getMessage());
            return 1000; // fallback
        }
    }

    private function calculateStrikes($stepValue, $futPrice)
    {
        $strikeGap = $stepValue ?? 50;
        $atm = round($futPrice / $strikeGap) * $strikeGap;

        return [
            0 => $atm - (3 * $strikeGap),
            1 => $atm - (2 * $strikeGap),
            2 => $atm - $strikeGap,
            3 => $atm,
            4 => $atm + $strikeGap,
            5 => $atm + (2 * $strikeGap),
            6 => $atm + (3 * $strikeGap),
        ];
    }

    /**
     * Get CE and PE options for a specific strike
     */
    private function getOptionsForStrike($underlying, $strike)
    {
        $options = ['CE' => null, 'PE' => null];

        $instrumentType = "OPTSTK";
        $indexSymbol = ['NIFTY','BANKNIFTY'];
        if(in_array($underlying,$indexSymbol)){
            $instrumentType = "OPTIDX";
        }

        $strike = number_format($strike * 100, 6, '.', '');
        $instruments = DB::table('angel_api_instruments')
            ->where('name', $underlying)
            ->where('instrumenttype', $instrumentType)
            ->where('exch_seg', 'NFO') // 
            ->where('strike', $strike)
            ->orderByRaw("STR_TO_DATE(expiry, '%d%b%Y') DESC")
            ->get();

        foreach ($instruments as $instrument) {
            $symbol = $instrument->symbol_name ?? '';
            if (str_ends_with($symbol, 'CE')) {
                $options['CE'] = $instrument;
            } elseif (str_ends_with($symbol, 'PE')) {
                $options['PE'] = $instrument;
            }
        }

        return $options;
    }
}