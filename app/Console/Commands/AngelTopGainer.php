<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Traits\AngelApiAuth;
use App\Models\TopPortfolio;
use Illuminate\Support\Facades\Log;

class AngelTopGainer extends Command
{
    use AngelApiAuth;

    protected $signature = 'angel_top_gainer:every_minute';
    protected $description = 'Fetch and store daily top gainers from Angel API';

    public function handle()
    {
        sleep(2);
        $jwtToken = $this->generate_access_token();

        if (!$jwtToken) {
            Log::error('AngelTopGainer: JWT token generation failed.');
            return Command::FAILURE;
        }

        try {
            $response = Http::retry(3, 1000)->withHeaders([
                'X-UserType' => 'USER',
                'X-SourceID' => 'WEB',
                'X-PrivateKey' => $this->apiKey,
                'X-ClientLocalIP' => $this->clientLocalIp,
                'X-ClientPublicIP' => $this->clientPublicIp,
                'X-MACAddress' => $this->macAddress,
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/json',
            ])->post('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/gainersLosers', [
                'datatype' => 'PercPriceGainers',
                'expirytype' => 'NEAR',
            ]);

            if (!$response->successful()) {
                Log::error('AngelTopGainer: API call failed', ['status' => $response->status(), 'body' => $response->body()]);
                return Command::FAILURE;
            }

            $data = $response->json();

            if (!isset($data['status']) || !$data['status']) {
                Log::warning('AngelTopGainer: Invalid response', $data);
                return Command::FAILURE;
            }

            foreach ($data['data'] as $value) {
                // TopPortfolio::updateOrCreate(
                //     ['symbol' => $value['tradingSymbol'], 'type' => 'gainer'],
                //     [
                //         'token' => $value['symbolToken'],
                //         'ltp' => $value['ltp'],
                //         'net_change' => $value['netChange'],
                //         'per_change' => $value['percentChange'],
                //         'updated_at' => now(),
                //     ]
                // );
                TopPortfolio::create(
                    [
                        'symbol' => $value['tradingSymbol'],
                        'type' => 'gainer',
                        'token' => $value['symbolToken'],
                        'ltp' => $value['ltp'],
                        'net_change' => $value['netChange'],
                        'per_change' => $value['percentChange'],
                        'updated_at' => now(),
                    ]
                );
            }

            $this->info('Top gainers updated successfully.');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            Log::error('AngelTopGainer: Exception occurred', ['message' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }
}