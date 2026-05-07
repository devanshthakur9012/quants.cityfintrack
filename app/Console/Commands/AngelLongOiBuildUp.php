<?php

// namespace App\Console\Commands;

// use Illuminate\Console\Command;
// use Illuminate\Support\Facades\Http;
// use App\Traits\AngelApiAuth;
// use App\Models\OiBuildUp;

// class AngelLongOiBuildUp extends Command
// {
//     use AngelApiAuth;

//     protected $signature = 'angel_long_oi_buildup:every_minute';
//     protected $description = 'Fetch Long OI Buildup data from Angel Broking API and store it';

//     public function handle()
//     {
//         sleep(2);
//         $jwtToken = $this->generate_access_token();

//         if (!$jwtToken) {
//             $this->error("Failed to generate JWT token.");
//             return 1;
//         }

//         try {
//             $response = Http::retry(3, 1000)->withHeaders([
//                 'X-UserType' => 'USER',
//                 'X-SourceID' => 'WEB',
//                 'X-PrivateKey' => $this->apiKey,
//                 'X-ClientLocalIP' => $this->clientLocalIp,
//                 'X-ClientPublicIP' => $this->clientPublicIp,
//                 'X-MACAddress' => $this->macAddress,
//                 'Content-Type' => 'application/json',
//                 'Authorization' => 'Bearer ' . $jwtToken,
//             ])->post('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/OIBuildup', [
//                 'datatype' => 'Long Built Up',
//                 'expirytype' => 'NEAR',
//             ]);

//             $data = $response->json();

//             if ($response->successful() && isset($data['status']) && $data['status'] === true) {
//                 dd($data);
//                 foreach ($data['data'] as $value) {
//                     OiBuildUp::Create(
//                         [
//                             'symbol' => $value['tradingSymbol'],
//                             'type' => "long",
//                             'ltp' => $value['ltp'],
//                             'net_change' => $value['netChange'],
//                             'per_change' => $value['percentChange'],
//                             'oi' => $value['opnInterest'],
//                             'oi_change' => $value['netChangeOpnInterest'],
//                         ]
//                     );
//                 }
//                 $this->info("Long OI Buildup data updated successfully.");
//             } else {
//                 $this->error("API responded with an error or invalid data.");
//             }

//         } catch (\Exception $e) {
//             $this->error("Error occurred: " . $e->getMessage());
//         }

//         return 0;
//     }
// }

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Traits\AngelApiAuth;
use App\Models\OiBuildUp;
use Carbon\Carbon;

class AngelLongOiBuildUp extends Command
{
    use AngelApiAuth;

    protected $signature = 'angel_long_oi_buildup:every_minute';
    protected $description = 'Fetch Long OI Buildup data from Angel Broking API and store it';

    public function handle()
    {
        sleep(2);
        $jwtToken = $this->generate_access_token();

        if (!$jwtToken) {
            $this->error("Failed to generate JWT token.");
            return 1;
        }

        try {
            $response = Http::retry(3, 1000)->withHeaders([
                'X-UserType' => 'USER',
                'X-SourceID' => 'WEB',
                'X-PrivateKey' => $this->apiKey,
                'X-ClientLocalIP' => $this->clientLocalIp,
                'X-ClientPublicIP' => $this->clientPublicIp,
                'X-MACAddress' => $this->macAddress,
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $jwtToken,
            ])->post('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/OIBuildup', [
                'datatype' => 'Long Built Up',
                'expirytype' => 'NEAR',
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['status']) && $data['status'] === true) {

                foreach ($data['data'] as $value) {
                    $symbol = $value['tradingSymbol'];
                    $current_price = $value['ltp'];
                    $current_oi = $value['opnInterest'];
                    $today = Carbon::now()->toDateString();

                    // Get the last saved entry for the same symbol
                    $lastRecord = OiBuildUp::where('symbol', $symbol)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    $price_diff = 0;
                    $oi_diff = 0;
                    $signal = 'HOLD';

                    if ($lastRecord) {
                        $price_diff = round($current_price - $lastRecord->ltp, 2);
                        $oi_diff = round($current_oi - $lastRecord->oi, 2);

                        // Determine signal
                        if ($price_diff > 0 && $oi_diff > 0) {
                            $signal = 'Buy - Long Buildup';
                        } elseif ($price_diff < 0 && $oi_diff > 0) {
                            $signal = 'Sell - Short Buildup';
                        } elseif ($price_diff > 0 && $oi_diff < 0) {
                            $signal = 'Buy - Short Covering';
                        } elseif ($price_diff < 0 && $oi_diff < 0) {
                            $signal = 'Sell - Long Unwinding';
                        }
                    }

                    // Save new record with signal
                    OiBuildUp::create([
                        'symbol' => $symbol,
                        'type' => "long",
                        'ltp' => $current_price,
                        'net_change' => $value['netChange'],
                        'per_change' => $value['percentChange'],
                        'oi' => $current_oi,
                        'oi_change' => $value['netChangeOpnInterest'],
                        'oi_signal' => $signal,
                        'price_diff' => $price_diff,
                        'oi_diff' => $oi_diff,
                    ]);
                }

                $this->info("Long OI Buildup data with signals updated successfully.");
            } else {
                $this->error("API responded with an error or invalid data.");
            }

        } catch (\Exception $e) {
            $this->error("Error occurred: " . $e->getMessage());
        }

        return 0;
    }
}
