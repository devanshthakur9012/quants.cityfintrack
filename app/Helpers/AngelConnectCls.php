<?php
namespace App\Helpers;
require app_path('Libraries/vendor/autoload.php');
use OTPHP\TOTP;
use Illuminate\Support\Facades\Log;

class AngelConnectCls{
    private $accountUserName;
    private $apiKey;
    private $pin;
    private $totp_secret;
    private $clientLocalIp = '192.168.1.31';
    private $clientPublicIp = '122.161.67.85';
    private $macAddress = '14-85-7F-92-D0-B0';

    public function __construct(array $params)
    {
        $this->accountUserName = $params['accountUserName'];
        $this->apiKey = $params['apiKey'];
        $this->pin = $params['pin'];
        $this->totp_secret = $params['totp_secret'];
    }

    public function get_totp_token()
    {
        $totp = TOTP::create($this->totp_secret);
        return $totp->now();
    }

    public function generate_access_token()
    {
        try {
            $data = \Cache::remember('ANGEL_API_TOKEN_'.$this->accountUserName, 72000, function () {
                $postFields = [
                    "clientcode"=>$this->accountUserName,
                    "password"=>$this->pin,
                    "totp"=>$this->get_totp_token(),
                ];

                Log::info("Sending login request to Angel API", [
                    'url' => 'https://apiconnect.angelbroking.com/rest/auth/angelbroking/user/v1/loginByPassword',
                    'postFields' => $postFields
                ]);
    
                $curl = curl_init();
                curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://apiconnect.angelbroking.com/rest/auth/angelbroking/user/v1/loginByPassword',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>json_encode($postFields),
                CURLOPT_HTTPHEADER => array(
                    'X-UserType: USER',
                    'X-SourceID: WEB',
                    'X-PrivateKey: '.$this->apiKey,
                    'X-ClientLocalIP: '.$this->clientLocalIp,
                    'X-ClientPublicIP: '.$this->clientPublicIp,
                    'X-MACAddress: '.$this->macAddress,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ),
                ));
    
                $response = curl_exec($curl);
                $err = curl_error($curl);
                // echo $response;die;
                curl_close($curl);
                if ($err) {
                    Log::error("cURL error during Angel API login", ['error' => $err]);
                    return null;
                }
                
                $dataArr = json_decode($response);

                if(isset($dataArr->status) && $dataArr->status==true){
                    return [
                        'token'=>$dataArr->data->jwtToken,
                        'clientLocalIp'=>$this->clientLocalIp,
                        'clientPublicIp'=>$this->clientPublicIp,
                        'macAddress'=>$this->macAddress
                    ];
                }
                Log::warning("Angel API login failed", ['response' => $dataArr]);
                return null;
                
            });
            return $data;
        } catch (Exception $ex) {
            Log::error("Exception during Angel API token generation", [
                'error' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString()
            ]);
           return null;
        }
    }


}