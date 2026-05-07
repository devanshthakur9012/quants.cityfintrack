<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Traits\AngelApiAuth;
use App\Models\StoreMarketData;

class store_market_data extends Command
{
    use AngelApiAuth;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store_market_data:store_data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $jwtToken =  $this->generate_access_token();
        $errData = [];
        if($jwtToken!=null){
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/market/v1/quote/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "mode": "FULL",
                "exchangeTokens": {
                    "NFO": ["35730","35731"]
                }
            }',
            CURLOPT_HTTPHEADER => array(
                'X-UserType: USER',
                'X-SourceID: WEB',
                'X-PrivateKey: '.$this->apiKey,
                'X-ClientLocalIP: '.$this->clientLocalIp,
                'X-ClientPublicIP: '.$this->clientPublicIp,
                'X-MACAddress: '.$this->macAddress,
                'Content-Type: application/json',
                'Authorization: Bearer '.$jwtToken
            ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                return $errData;
            }
            $errData = json_decode($response,true);
            if(isset($errData)){
                if($errData['status'] == true){
                    $result = $errData['data']['fetched'];
                    foreach ($result as $key => $value) {
                        $marketData = new StoreMarketData;
                        $marketData->token = $value['symbolToken'];
                        $marketData->symbol = $value['tradingSymbol'];
                        $marketData->exchange = $value['exchange'];
                        $marketData->ltp = $value['ltp'];
                        $marketData->open = $value['open'];
                        $marketData->high = $value['high'];
                        $marketData->low = $value['low'];
                        $marketData->close = $value['close'];
                        $marketData->lastTradeQty = $value['lastTradeQty'];
                        $marketData->exchFeedTime = $value['exchFeedTime'];
                        $marketData->exchTradeTime = $value['exchTradeTime'];
                        $marketData->netChange = $value['netChange'];
                        $marketData->percentChange = $value['percentChange'];
                        $marketData->avgPrice = $value['avgPrice'];
                        $marketData->tradeVolume = $value['tradeVolume'];
                        $marketData->opnInterest = $value['opnInterest'];
                        $marketData->lowerCircuit = $value['lowerCircuit'];
                        $marketData->upperCircuit = $value['upperCircuit'];
                        $marketData->totBuyQuan = $value['totBuyQuan'];
                        $marketData->totSellQuan = $value['totSellQuan'];
                        $marketData->WeekLow52 = $value['52WeekLow'];
                        $marketData->WeekHigh52 = $value['52WeekHigh'];
                        $marketData->save();
                    }
                }
            }
        }
    }
}
