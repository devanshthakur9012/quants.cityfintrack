<?php

// namespace App\Console\Commands;

// use Illuminate\Console\Command;
// use Illuminate\Support\Facades\Http;
// use App\Traits\AngelApiAuth;
// use App\Models\OiBuildUp;
// use Illuminate\Support\Facades\Log;

// class AngelShortOiBuildUp extends Command
// {
//     use AngelApiAuth;

//     protected $signature = 'angel_short_oi_buildup:every_minute';
//     protected $description = 'Fetches Short Built Up OI Data from Angel Broking and saves it to DB';

//     public function handle()
//     {
//         sleep(2);
//         $jwtToken = $this->generate_access_token();

//         if (!$jwtToken) {
//             Log::error('AngelShortOiBuildUp: Failed to generate JWT token');
//             return Command::FAILURE;
//         }

//         try {
//             $response = Http::retry(3, 1000)->withHeaders([
//                 'X-UserType'        => 'USER',
//                 'X-SourceID'        => 'WEB',
//                 'X-PrivateKey'      => $this->apiKey,
//                 'X-ClientLocalIP'   => $this->clientLocalIp,
//                 'X-ClientPublicIP'  => $this->clientPublicIp,
//                 'X-MACAddress'      => $this->macAddress,
//                 'Authorization'     => 'Bearer ' . $jwtToken,
//                 'Content-Type'      => 'application/json',
//             ])->post('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/OIBuildup', [
//                 'datatype'   => 'Short Built Up',
//                 'expirytype' => 'NEAR',
//             ]);

//             $data = $response->json();

//             if (!$response->successful() || empty($data['status'])) {
//                 Log::error('AngelShortOiBuildUp: API response failed', ['response' => $data]);
//                 return Command::FAILURE;
//             }

//             foreach ($data['data'] as $entry) {
//                 // OiBuildUp::updateOrCreate(
//                 //     ['symbol' => $entry['tradingSymbol'], 'type' => 'short'],
//                 //     [
//                 //         'ltp'        => $entry['ltp'],
//                 //         'net_change' => $entry['netChange'],
//                 //         'per_change' => $entry['percentChange'],
//                 //         'oi'         => $entry['opnInterest'],
//                 //         'oi_change'  => $entry['netChangeOpnInterest'],
//                 //     ]
//                 // );
//                 OiBuildUp::create(
//                     [
//                         'symbol' => $entry['tradingSymbol'],
//                         'type' => 'short',
//                         'ltp'        => $entry['ltp'],
//                         'net_change' => $entry['netChange'],
//                         'per_change' => $entry['percentChange'],
//                         'oi'         => $entry['opnInterest'],
//                         'oi_change'  => $entry['netChangeOpnInterest'],
//                     ]
//                 );
//             }

//             $this->info("Short OI Buildup data synced successfully.");
//             return Command::SUCCESS;

//         } catch (\Exception $e) {
//             Log::error('AngelShortOiBuildUp: Exception occurred', ['message' => $e->getMessage()]);
//             return Command::FAILURE;
//         }
//     }
// }


namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Traits\AngelApiAuth;
use App\Models\OiBuildUp;
use Illuminate\Support\Facades\Log;
use Exception;

class AngelShortOiBuildUp extends Command
{
    use AngelApiAuth;

    protected $signature = 'angel_short_oi_buildup:every_minute';
    protected $description = 'Fetches Short Built Up OI Data from Angel Broking and saves it to DB';

    public function handle()
    {
        sleep(2);

        try {
            $jwtToken = $this->generate_access_token();

            if (!$jwtToken) {
                $this->error('JWT Token generation failed.');
                Log::error('AngelShortOiBuildUp: Failed to generate JWT token');
                return Command::FAILURE;
            }

            $response = Http::retry(3, 1000)->withHeaders([
                'X-UserType'        => 'USER',
                'X-SourceID'        => 'WEB',
                'X-PrivateKey'      => $this->apiKey,
                'X-ClientLocalIP'   => $this->clientLocalIp,
                'X-ClientPublicIP'  => $this->clientPublicIp,
                'X-MACAddress'      => $this->macAddress,
                'Authorization'     => 'Bearer ' . $jwtToken,
                'Content-Type'      => 'application/json',
            ])->post('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/OIBuildup', [
                'datatype'   => 'Short Built Up',
                'expirytype' => 'NEAR',
            ]);

            if (!$response->successful()) {
                $this->error('Failed API call to Angel Broking: ' . $response->body());
                Log::error('AngelShortOiBuildUp API call failed', ['response' => $response->json()]);
                return Command::FAILURE;
            }

            $data = $response->json();

            if (!isset($data['status']) || !$data['status']) {
                $this->error('Invalid response from Angel Broking: ' . json_encode($data));
                Log::error('AngelShortOiBuildUp invalid response', ['response' => $data]);
                return Command::FAILURE;
            }

            foreach ($data['data'] as $item) {
                try {
                    $symbol = $item['tradingSymbol'];
                    $ltp = $item['ltp'];
                    $oi = $item['opnInterest'];
                    $type = 'short';

                    $last = OiBuildUp::where('symbol', $symbol)
                        ->where('type', $type)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    $price_diff = $last ? round($ltp - $last->ltp, 2) : 0;
                    $oi_diff = $last ? round($oi - $last->oi, 2) : 0;

                    $signal = 'HOLD';
                    if ($price_diff > 0 && $oi_diff > 0) {
                        $signal = 'Buy - Long Buildup';
                    } elseif ($price_diff < 0 && $oi_diff > 0) {
                        $signal = 'Sell - Short Buildup';
                    } elseif ($price_diff > 0 && $oi_diff < 0) {
                        $signal = 'Buy - Short Covering';
                    } elseif ($price_diff < 0 && $oi_diff < 0) {
                        $signal = 'Sell - Long Unwinding';
                    }

                    OiBuildUp::create([
                        'symbol'      => $symbol,
                        'type'        => $type,
                        'ltp'         => $ltp,
                        'net_change'  => $item['netChange'],
                        'per_change'  => $item['percentChange'],
                        'oi'          => $oi,
                        'oi_change'   => $item['netChangeOpnInterest'],
                        'price_diff'  => $price_diff,
                        'oi_diff'     => $oi_diff,
                        'oi_signal'   => $signal,
                    ]);

                } catch (Exception $e) {
                    $this->error('DB error for symbol ' . $item['tradingSymbol'] . ': ' . $e->getMessage());
                    Log::error('AngelShortOiBuildUp DB error', [
                        'symbol' => $item['tradingSymbol'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->info('Short OI Buildup data synced successfully.');
            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error('Unhandled error in AngelShortOiBuildUp command: ' . $e->getMessage());
            Log::error('AngelShortOiBuildUp Unhandled Error', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}