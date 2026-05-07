<?php

// namespace App\Console\Commands;

// use Illuminate\Console\Command;
// use Illuminate\Support\Facades\Http;
// use App\Traits\AngelApiAuth;
// use App\Models\OiBuildUp;
// use Exception;

// class AngelLongUnwinding extends Command
// {
//     use AngelApiAuth;

//     protected $signature = 'angel_long_unwinding_buildup:every_minute';
//     protected $description = 'Fetch and store Long Unwinding buildup data from Angel API';

//     public function handle()
//     {
//         sleep(2);
//         try {
//             $jwtToken = $this->generate_access_token();

//             if (!$jwtToken) {
//                 $this->error("JWT token generation failed.");
//                 return 1;
//             }

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
//                 'datatype' => 'Long Unwinding',
//                 'expirytype' => 'NEAR',
//             ]);

//             if ($response->failed()) {
//                 $this->error("API request failed: " . $response->body());
//                 return 1;
//             }

//             $data = $response->json();

//             if (!isset($data['status']) || !$data['status'] || empty($data['data'])) {
//                 $this->error("Invalid or empty response data.");
//                 return 1;
//             }

//             foreach ($data['data'] as $item) {
//                 OiBuildUp::create(
//                     [
//                         'symbol' => $item['tradingSymbol'],
//                         'type' => 'unwinding',
//                         'ltp' => $item['ltp'],
//                         'net_change' => $item['netChange'],
//                         'per_change' => $item['percentChange'],
//                         'oi' => $item['opnInterest'],
//                         'oi_change' => $item['netChangeOpnInterest'],
//                     ]
//                 );
//             }

//             $this->info("Long Unwinding data stored successfully.");
//             return 0;

//         } catch (Exception $e) {
//             $this->error("Error occurred: " . $e->getMessage());
//             return 1;
//         }
//     }
// }

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Traits\AngelApiAuth;
use App\Models\OiBuildUp;
use Exception;

class AngelLongUnwinding extends Command
{
    use AngelApiAuth;

    protected $signature = 'angel_long_unwinding_buildup:every_minute';
    protected $description = 'Fetch and store Long Unwinding buildup data from Angel API';

    public function handle()
    {
        sleep(2);

        try {
            $jwtToken = $this->generate_access_token();

            if (!$jwtToken) {
                $this->error("JWT token generation failed.");
                return 1;
            }

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
                'datatype' => 'Long Unwinding',
                'expirytype' => 'NEAR',
            ]);

            if ($response->failed()) {
                $this->error("API request failed: " . $response->body());
                return 1;
            }

            $data = $response->json();

            if (!isset($data['status']) || !$data['status'] || empty($data['data'])) {
                $this->error("Invalid or empty response data.");
                return 1;
            }

            foreach ($data['data'] as $item) {
                $symbol = $item['tradingSymbol'];
                $current_price = $item['ltp'];
                $current_oi = $item['opnInterest'];

                $last = OiBuildUp::where('symbol', $symbol)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $price_diff = 0;
                $oi_diff = 0;
                $signal = 'HOLD';

                if ($last) {
                    $price_diff = round($current_price - $last->ltp, 2);
                    $oi_diff = round($current_oi - $last->oi, 2);

                    // Apply signal logic
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

                OiBuildUp::create([
                    'symbol' => $symbol,
                    'type' => 'unwinding',
                    'ltp' => $current_price,
                    'net_change' => $item['netChange'],
                    'per_change' => $item['percentChange'],
                    'oi' => $current_oi,
                    'oi_change' => $item['netChangeOpnInterest'],
                    'oi_signal' => $signal,
                    'price_diff' => $price_diff,
                    'oi_diff' => $oi_diff,
                ]);
            }

            $this->info("Long Unwinding data with signals stored successfully.");
            return 0;

        } catch (Exception $e) {
            $this->error("Error occurred: " . $e->getMessage());
            return 1;
        }
    }
}