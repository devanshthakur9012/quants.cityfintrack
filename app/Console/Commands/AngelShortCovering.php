<?php

// namespace App\Console\Commands;

// use Illuminate\Console\Command;
// use Illuminate\Support\Facades\Http;
// use App\Traits\AngelApiAuth;
// use App\Models\OiBuildUp;
// use Illuminate\Support\Facades\Log;
// use Exception;

// class AngelShortCovering extends Command
// {
//     use AngelApiAuth;

//     protected $signature = 'angel_short_covering_buildup:every_minute';
//     protected $description = 'Fetch and store Short Covering OI buildup data from Angel Broking API';

//     public function handle()
//     {
//         sleep(2);
//         try {
//             $jwtToken = $this->generate_access_token();

//             if (!$jwtToken) {
//                 $this->error('JWT Token generation failed.');
//                 return Command::FAILURE;
//             }

//             $response = Http::retry(3, 1000)->withHeaders([
//                 'X-UserType'        => 'USER',
//                 'X-SourceID'        => 'WEB',
//                 'X-PrivateKey'      => $this->apiKey,
//                 'X-ClientLocalIP'   => $this->clientLocalIp,
//                 'X-ClientPublicIP'  => $this->clientPublicIp,
//                 'X-MACAddress'      => $this->macAddress,
//                 'Content-Type'      => 'application/json',
//                 'Authorization'     => 'Bearer ' . $jwtToken,
//             ])->post('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/OIBuildup', [
//                 'datatype'    => 'Short Covering',
//                 'expirytype'  => 'NEAR',
//             ]);

//             if (!$response->successful()) {
//                 $this->error('Failed API call to Angel Broking: ' . $response->body());
//                 Log::error('AngelShortCovering API call failed', ['response' => $response->json()]);
//                 return Command::FAILURE;
//             }

//             $data = $response->json();

//             if (!isset($data['status']) || !$data['status']) {
//                 $this->error('Invalid response from Angel Broking: ' . json_encode($data));
//                 Log::error('AngelShortCovering invalid response', ['response' => $data]);
//                 return Command::FAILURE;
//             }

//             foreach ($data['data'] as $item) {
//                 try {
//                     // OiBuildUp::updateOrCreate(
//                     //     ['symbol' => $item['tradingSymbol'], 'type' => 'covering'],
//                     //     [
//                     //         'ltp'         => $item['ltp'],
//                     //         'net_change'  => $item['netChange'],
//                     //         'per_change'  => $item['percentChange'],
//                     //         'oi'          => $item['opnInterest'],
//                     //         'oi_change'   => $item['netChangeOpnInterest'],
//                     //     ]
//                     // );
//                     OiBuildUp::create(
//                         [
//                             'symbol' => $item['tradingSymbol'],
//                             'type' => 'covering',
//                             'ltp'         => $item['ltp'],
//                             'net_change'  => $item['netChange'],
//                             'per_change'  => $item['percentChange'],
//                             'oi'          => $item['opnInterest'],
//                             'oi_change'   => $item['netChangeOpnInterest'],
//                         ]
//                     );
//                 } catch (Exception $e) {
//                     $this->error('DB error for symbol ' . $item['tradingSymbol'] . ': ' . $e->getMessage());
//                     Log::error('AngelShortCovering DB error', [
//                         'symbol' => $item['tradingSymbol'],
//                         'error' => $e->getMessage()
//                     ]);
//                 }
//             }

//             $this->info('Short Covering OI Buildup data successfully updated.');
//             return Command::SUCCESS;

//         } catch (Exception $e) {
//             $this->error('Unhandled error in AngelShortCovering command: ' . $e->getMessage());
//             Log::error('AngelShortCovering Unhandled Error', ['error' => $e->getMessage()]);
//             return Command::FAILURE;
//         }
//     }
// }

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Traits\AngelApiAuth;
use App\Models\OiBuildUp;
use Exception;

class AngelShortCovering extends Command
{
    use AngelApiAuth;

    protected $signature = 'angel_short_covering_buildup:every_minute';
    protected $description = 'Fetch and store Short Covering OI buildup data from Angel Broking API';

    public function handle()
    {
        sleep(2);

        try {
            $jwtToken = $this->generate_access_token();

            if (!$jwtToken) {
                $this->error('JWT Token generation failed.');
                return Command::FAILURE;
            }

            $response = Http::retry(3, 1000)->withHeaders([
                'X-UserType'        => 'USER',
                'X-SourceID'        => 'WEB',
                'X-PrivateKey'      => $this->apiKey,
                'X-ClientLocalIP'   => $this->clientLocalIp,
                'X-ClientPublicIP'  => $this->clientPublicIp,
                'X-MACAddress'      => $this->macAddress,
                'Content-Type'      => 'application/json',
                'Authorization'     => 'Bearer ' . $jwtToken,
            ])->post('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/OIBuildup', [
                'datatype'    => 'Short Covering',
                'expirytype'  => 'NEAR',
            ]);

            if (!$response->successful()) {
                $this->error('Failed API call to Angel Broking: ' . $response->body());
                Log::error('AngelShortCovering API call failed', ['response' => $response->json()]);
                return Command::FAILURE;
            }

            $data = $response->json();

            if (!isset($data['status']) || !$data['status']) {
                $this->error('Invalid response from Angel Broking: ' . json_encode($data));
                Log::error('AngelShortCovering invalid response', ['response' => $data]);
                return Command::FAILURE;
            }

            foreach ($data['data'] as $item) {
                try {
                    $symbol = $item['tradingSymbol'];
                    $current_price = $item['ltp'];
                    $current_oi = $item['opnInterest'];

                    // Get last saved record for this symbol
                    $last = OiBuildUp::where('symbol', $symbol)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    $price_diff = 0;
                    $oi_diff = 0;
                    $signal = 'HOLD';

                    if ($last) {
                        $price_diff = round($current_price - $last->ltp, 2);
                        $oi_diff = round($current_oi - $last->oi, 2);

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

                    // Save to DB
                    OiBuildUp::create([
                        'symbol'      => $symbol,
                        'type'        => 'covering',
                        'ltp'         => $current_price,
                        'net_change'  => $item['netChange'],
                        'per_change'  => $item['percentChange'],
                        'oi'          => $current_oi,
                        'oi_change'   => $item['netChangeOpnInterest'],
                        'oi_signal'   => $signal,
                        'price_diff'  => $price_diff,
                        'oi_diff'     => $oi_diff,
                    ]);

                } catch (Exception $e) {
                    $this->error('DB error for symbol ' . $item['tradingSymbol'] . ': ' . $e->getMessage());
                    Log::error('AngelShortCovering DB error', [
                        'symbol' => $item['tradingSymbol'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info('Short Covering OI Buildup data successfully updated.');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Unhandled error in AngelShortCovering command: ' . $e->getMessage());
            Log::error('AngelShortCovering Unhandled Error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}