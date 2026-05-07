<?php

namespace App\Console\Commands;

use App\Models\Early1DayOptionChain;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\AngelApiAuth;

class Early1DayOptionChainCommand extends Command
{
    use AngelApiAuth;

    protected $signature = 'options:early-one-day';
    protected $description = 'Generate options chain data for all symbols from early_symbols table';

    // public function handle()
    // {
    //     set_time_limit(0);
        
    //     $this->info("Starting Options Chain Generation...");

    //     try {
    //         $symbols = DB::table('early_symbols')->get(['underlying', 'symbol', 'step_value', 'symbol_token']);
            
    //         if ($symbols->isEmpty()) {
    //             $this->error('No symbols found in symbol_lists table.');
    //             return 1;
    //         }

    //         $this->info("Found " . count($symbols) . " symbols to process.");

    //         // Clear existing options chain data
    //         DB::beginTransaction();
    //         Early1DayOptionChain::truncate();
            
    //         $totalProcessed = 0;
    //         $totalErrors = 0;

    //         foreach ($symbols as $symbolData) {
    //             try {
    //                 $result = $this->processSymbol(
    //                     $symbolData->symbol,
    //                     $symbolData->underlying,
    //                     $symbolData->step_value,
    //                     $symbolData->symbol_token
    //                 );

    //                 if ($result) {
    //                     $totalProcessed++;
    //                     $this->info("✓ Processed: {$symbolData->symbol}");
    //                 } else {
    //                     $totalErrors++;
    //                     $this->error("✗ Failed: {$symbolData->symbol} (No future found or no options)");
    //                 }
    //             } catch (\Exception $e) {
    //                 $totalErrors++;
    //                 $this->error("✗ Error processing {$symbolData->symbol}: " . $e->getMessage());
    //                 Log::error("Early 1 Day : Options Chain generation error for {$symbolData->symbol}: " . $e->getMessage());
    //             }
    //         }

    //         DB::commit();
            
    //         $this->info("\n=== Summary ===");
    //         $this->info("Total Symbols: " . count($symbols));
    //         $this->info("Successfully Processed: {$totalProcessed}");
    //         $this->info("Errors: {$totalErrors}");
    //         $this->info("Options Chain Generation Completed!");

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error("Early 1 Day : Options chain generation failed: " . $e->getMessage());
    //         $this->error('Critical Error: ' . $e->getMessage());
    //         return 1;
    //     }

    //     return 0;
    // }

    public function handle()
    {
        set_time_limit(0);
        $this->info("Starting Options Chain Generation...");

        try {
            $symbols = DB::table('early_symbols')->get(['underlying', 'symbol', 'step_value', 'symbol_token']);
            if ($symbols->isEmpty()) {
                $this->warn("⚠️ No symbols found in early_symbols table.");
                return 0;
            }

            Early1DayOptionChain::truncate();

            $totalProcessed = 0;
            $totalErrors = 0;

            foreach ($symbols as $symbolData) {
                DB::beginTransaction();
                try {
                    $result = $this->processSymbol(
                        $symbolData->symbol,
                        $symbolData->underlying,
                        $symbolData->step_value,
                        $symbolData->symbol_token
                    );

                    if ($result) {
                        DB::commit();
                        $this->info("✓ Processed: {$symbolData->symbol}");
                        $totalProcessed++;
                    } else {
                        DB::rollBack();
                        $this->warn("✗ No options found for {$symbolData->symbol}");
                        $totalErrors++;
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::error("Early 1 Day : Error processing {$symbolData->symbol} => ".$e->getMessage());
                    $this->error("❌ {$symbolData->symbol}: ".$e->getMessage());
                    $totalErrors++;
                }
            }

            $this->info("\nSummary: Processed {$totalProcessed}, Errors {$totalErrors}");
            return 0;

        } catch (\Throwable $e) {
            Log::error("Early 1 Day : Fatal Error => ".$e->getMessage());
            $this->error("Critical: ".$e->getMessage());
            return 1;
        }
    }

    private function processSymbol($symbol, $underlying, $stepValue, $token)
    {
        $future = $this->getFutureSymbol($symbol);
        if (!$future) {
            return false;
        }

        $futPrice = $this->getSampleFuturePrice($token);

        $strikes = $this->calculateStrikes($stepValue, $futPrice);

        $optionsData = [];
        foreach ($strikes as $position => $strike) {
            $options = $this->getOptionsForStrike($symbol, $strike);

            if (!empty($options['CE']) || !empty($options['PE'])) {
                $optionsData[] = [
                    'underlying' => $symbol,
                    'future_symbol' => $future->symbol_name ?? null,
                    'future_token' => $future->token ?? null,
                    'future_price' => $futPrice,
                    'strike_price' => $strike,
                    'strike_position' => $position - 3,
                    
                    'ce_symbol' => $options['CE']->symbol_name ?? null,
                    'ce_token' => $options['CE']->token ?? null,
                    'ce_lotsize' => $options['CE']->lotsize ?? null,
                    'ce_exch_seg' => $options['CE']->exch_seg ?? null,
                    'ce_expiry' => $options['CE']->expiry ?? null,
                    'ce_tick_size' => $options['CE']->tick_size ?? null,
                    
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
            Early1DayOptionChain::insert($optionsData);
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
            
            Log::error("Early 1 Day : Failed to get real price for {$underlying}");
            return 1000;
            
        } catch (\Exception $e) {
            Log::error("Early 1Day : Failed to get real price for {$underlying}: " . $e->getMessage());
            return 1000;
        }
    }

    // private function calculateStrikes($stepValue, $futPrice)
    // {
    //     $strikeGap = $stepValue ?? 50;
    //     $atm = round($futPrice / $strikeGap) * $strikeGap;

    //     return [
    //         0 => $atm - (2 * $strikeGap), 
    //         1 => $atm - $strikeGap,  
    //         2 => $atm,                  
    //         3 => $atm + $strikeGap,      
    //         4 => $atm + (2 * $strikeGap),
    //     ];
    // }

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
            ->where('exch_seg', 'NFO')
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
