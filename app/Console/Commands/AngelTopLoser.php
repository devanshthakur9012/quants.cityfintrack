<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Traits\AngelApiAuth;
use App\Models\TopPortfolio;

class AngelTopLoser extends Command
{
    use AngelApiAuth;

    protected $signature = 'angel_top_loser:every_minute';
    protected $description = 'Fetch and store top losers from Angel Broking API';

    public function handle()
    {
        sleep(2);
        $jwtToken = $this->generate_access_token();

        if (!$jwtToken) {
            $this->error('Failed to generate access token');
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
            ])->post('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/gainersLosers', [
                'datatype' => 'PercPriceLosers',
                'expirytype' => 'NEAR',
            ]);

            if ($response->successful() && isset($response['data'])) {
                foreach ($response['data'] as $stock) {
                    // TopPortfolio::updateOrCreate(
                    //     ['symbol' => $stock['tradingSymbol'], 'type' => 'loser'],
                    //     [
                    //         'token' => $stock['symbolToken'],
                    //         'per_change' => $stock['percentChange'],
                    //         'ltp' => $stock['ltp'],
                    //         'net_change' => $stock['netChange'],
                    //     ]
                    // );
                    TopPortfolio::create(
                        [
                            'symbol' => $stock['tradingSymbol'],
                            'type' => 'loser',
                            'token' => $stock['symbolToken'],
                            'per_change' => $stock['percentChange'],
                            'ltp' => $stock['ltp'],
                            'net_change' => $stock['netChange'],
                        ]
                    );
                }

                $this->info('Top losers updated successfully.');
            } else {
                $this->error('API error: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}