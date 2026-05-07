<?php

namespace App\Helpers;
require app_path('Libraries/vendor/autoload.php');
use KiteConnect\KiteConnect;
use OTPHP\TOTP;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;

class KiteConnectClsOld{
    // private $accountUserName = 'BFF348';
    // private $accountPassword = 'venue@123';
    // private $totp_secret = '4AMQ5W5EHKIRZ33Z6EVI7W4HUS3KKDB2';
    // private $apiKey = '99n9vrxlgyxklpht';
    // private $apiSecret = 'adjl97sewgv1utfycl3ens7ks545hpcr';
    private $accountUserName;
    private $accountPassword;
    private $totpSecret;
    private $apiKey;
    private $apiSecret;

    public function __construct(array $params){
        $this->accountUserName = $params['accountUserName'];
        $this->accountPassword = $params['accountPassword'];
        $this->totpSecret = $params['totpSecret'];
        $this->apiKey = $params['apiKey'];
        $this->apiSecret = $params['apiSecret'];
    }

    public function get_totp_token()
    {
        $totp = TOTP::create($this->totpSecret);
        return $totp->now();
    }

    public function generate_access_token($login_url)
    {
        try {

            $serverUrl = 'http://localhost:9515'; // if you don't start chromedriver with "--port=4444" as above, default port will be 9515
           
            $chromeOptions = new ChromeOptions();
            $chromeOptions->addArguments(['--headless']);
            $capabilities = DesiredCapabilities::chrome();
            $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $chromeOptions);

            $driver = RemoteWebDriver::create($serverUrl, $capabilities);
            $driver->get($login_url);
            $driver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath('//input[@type="text"]')))
                ->sendKeys($this->accountUserName);
                $driver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath('//input[@type="password"]')))
                ->sendKeys($this->accountPassword);
            sleep(2);
            $driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//button[@type="submit"]')))
                ->submit();


            sleep(2);

             $driver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath('//input[@type="number"]')))
                ->click();
            $driver->findElement(WebDriverBy::xpath('//input[@type="number"]'))->sendKeys($this->get_totp_token());

            $driver->wait()->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::xpath('//input[@type="password"]')))
            ->sendKeys($this->accountPassword);
            
            $driver->wait()->until(WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::xpath('//button[@type="submit"]')))
                ->submit();

             $driver->wait()->until(WebDriverExpectedCondition::urlContains('status=success'));

            $tokenurl = $driver->getCurrentURL();
            $parsed = parse_url($tokenurl);
            $aArr = $parsed['query'];
            $parsed_query = [];
            parse_str($aArr, $parsed_query);
            return $parsed_query['request_token'];
        } catch (Exception $ex) {
            echo "Error Occurred during request generation process: " . $ex->getMessage();
            throw $ex;
        }
    }

    public function generateSession(){
        try {
            $token = $this->generate_access_token("https://kite.zerodha.com/connect/login?v=3&api_key=".$this->apiKey);
            $kite = new KiteConnect($this->apiKey);
            $user = $kite->generateSession($token, $this->apiSecret);
            $kite->setAccessToken($user->access_token);
            return $kite;
        } catch(Exception $e) {
            echo "Authentication failed: ".$e->getMessage();
            throw $e;
        }
    }

    public function generateSessionManual($token){
        try {
            $kite = new KiteConnect($this->apiKey);
            $user = $kite->generateSession($token, $this->apiSecret);
            $kite->setAccessToken($user->access_token);
            return $kite;
        } catch(\Exception $e) {
            \Cache::forget('KITE_AUTH_'.$this->accountUserName);
            return 'TOKEN_EXPIRED';
        }
    }

    // public function generateSessionManual($token){
    //     try {
    //         if (empty($token)) {
    //             Log::error("Empty token provided for Kite auth", ['username' => $this->accountUserName]);
    //             return 'EMPTY_TOKEN';
    //         }
            
    //         $kite = new KiteConnect($this->apiKey);
    //         $user = $kite->generateSession($token, $this->apiSecret);
            
    //         if (!isset($user->access_token) || empty($user->access_token)) {
    //             Log::error("No access token received from Kite", ['username' => $this->accountUserName]);
    //             return 'NO_ACCESS_TOKEN';
    //         }
            
    //         $kite->setAccessToken($user->access_token);
            
    //         Log::info("Kite authentication successful", ['username' => $this->accountUserName]);
    //         return $kite;
            
    //     } catch(\Exception $e) {
    //         Log::error("Kite authentication failed", [
    //             'username' => $this->accountUserName,
    //             'error' => $e->getMessage(),
    //             'token' => substr($token, 0, 10) . '...' // Log partial token for debugging
    //         ]);
            
    //         \Cache::forget('KITE_AUTH_'.$this->accountUserName);
            
    //         // Return specific error types for better handling
    //         if (strpos($e->getMessage(), 'token') !== false) {
    //             return 'TOKEN_EXPIRED';
    //         } elseif (strpos($e->getMessage(), 'network') !== false || strpos($e->getMessage(), 'connection') !== false) {
    //             return 'NETWORK_ERROR';
    //         } else {
    //             return 'AUTH_FAILED';
    //         }
    //     }
    // }

}