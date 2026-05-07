<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Traits\AngelApiAuth;
use App\Models\OiBuildUp;

class AngelLongOiBuildUpOld extends Command
{
    use AngelApiAuth;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'angel_long_oi_buildup:every_day';

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
            CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/OIBuildup',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => '{
                "datatype": "Long Built Up",
                "expirytype": "NEAR"
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
                    $result = $errData['data'];
                    foreach ($result as $key => $value) {
                        $topGainer = new OiBuildUp;
                        $topGainer->symbol = $value['tradingSymbol'];
                        $topGainer->ltp = $value['ltp'];
                        $topGainer->net_change = $value['netChange'];
                        $topGainer->per_change = $value['percentChange'];
                        $topGainer->oi = $value['opnInterest'];
                        $topGainer->oi_change = $value['netChangeOpnInterest'];
                        $topGainer->type = "long";
                        $topGainer->save();
                    }
                }
            }
        }
    }
}
