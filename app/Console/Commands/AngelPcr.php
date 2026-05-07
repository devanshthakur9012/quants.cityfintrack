<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Traits\AngelApiAuth;
use App\Models\PcrVolume;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AngelPcr extends Command
{
    use AngelApiAuth;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'angel_pcr:every_minute';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store/update Put Call Ratio data from Angel Broking API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        sleep(1);
        $jwtToken = $this->generate_access_token();

        if (!$jwtToken) {
            Log::error('JWT Token generation failed.');
            $this->error('JWT Token generation failed.');
            return 1;
        }

        $response = Http::withHeaders([
            'X-UserType' => 'USER',
            'X-SourceID' => 'WEB',
            'X-PrivateKey' => $this->apiKey,
            'X-ClientLocalIP' => $this->clientLocalIp,
            'X-ClientPublicIP' => $this->clientPublicIp,
            'X-MACAddress' => $this->macAddress,
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $jwtToken,
        ])->get('https://apiconnect.angelbroking.com/rest/secure/angelbroking/marketData/v1/putCallRatio');

        if ($response->failed()) {
            Log::error('Angel API request failed.', ['response' => $response->body()]);
            $this->error('API request failed.');
            return 1;
        }

        $data = $response->json();

        if (!isset($data['status']) || !$data['status']) {
            Log::warning('API response status is false.', $data);
            $this->warn('API response status is false.');
            return 1;
        }

        foreach ($data['data'] ?? [] as $entry) {
            if (isset($entry['tradingSymbol'], $entry['pcr'])) {
                PcrVolume::updateOrCreate(
                    ['symbol' => $entry['tradingSymbol']],
                    [
                        'pcr' => $entry['pcr'],
                        'updated_at' => Carbon::now(),
                        'created_at' => Carbon::now(),
                    ]
                );
            }
        }

        $this->info('PCR data successfully fetched and updated.');
        return 0;
    }
}