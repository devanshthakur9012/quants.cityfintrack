<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Traits\AngelApiAuth;
use App\Models\StengthTb;
use App\Models\FOPortfolios;
use App\Models\GlobalStockPortfolio;
use App\Models\MetalsPortfolio;
use App\Models\StockPortfolio;
use App\Models\ThematicPortfolio;
use App\Models\AngelApiInstrument;

class UpdatePortfoilioLtp extends Command
{
    use AngelApiAuth;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update_portfolio_ltp:every_minute';

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
        set_time_limit(0);
        $portfolioTables = ['stock_portfolios'];
        // $portfolioTables = ['thematic_portfolios','stock_portfolios','f_o_portfolios','global_stock_portfolios','metals_portfolios'];
        $todayDate = date("Y-m-d");

        $responseData = [];
        $apiResponse = [];
        $error = [];
        foreach ($portfolioTables as $key => $v) {
            $data = \DB::table($v)->orderBy('id','DESC')->distinct('stock_name')->pluck('stock_name')->toArray();
            $responseData = array_merge($responseData, $data);
        }

      
        $newArray = array_unique($responseData);
    
        if (count($newArray)) {
           $fetchData =  array_chunk($newArray,50);
            foreach ($fetchData as $symbolNames) {
                // $angelApiData = AngelApiInstrument::select('angel_api_instruments.exch_seg','angel_api_instruments.token','angel_api_instruments.name','angel_api_instruments.id')
                // ->whereIn('name', $symbolNames)
                // ->join(DB::raw('(SELECT MAX(id) as id FROM angel_api_instruments GROUP BY name) as latest_records'), 'angel_api_instruments.id', '=', 'latest_records.id')
                // ->orderBy('id', 'DESC')->get();

                // $alldata = $angelApiData->toArray();
                // $payloadData = $angelApiData->groupBy('exch_seg')->map(function($items) {
                //     return $items->pluck('token')->toArray();
                // })->toArray();

                $angelApiData = AngelApiInstrument::select('angel_api_instruments.exch_seg', 'angel_api_instruments.token', 'angel_api_instruments.name', 'angel_api_instruments.id')
                ->whereIn('symbol_name', $symbolNames) // Filter by symbol names
                ->where('exch_seg', 'NSE')      // Filter by exchange segment "NSE"
                ->join(DB::raw('(SELECT MAX(id) as id FROM angel_api_instruments GROUP BY symbol_name) as latest_records'), 'angel_api_instruments.id', '=', 'latest_records.id')
                ->orderBy('id', 'DESC')
                ->get();
            
            $alldata = $angelApiData->toArray();

            // Hardcoded value for NSA
            $hardcodedNSA = 'NSE';

            $payloadData = $angelApiData->groupBy(function () use ($hardcodedNSA) {
                return $hardcodedNSA;  // Group by hardcoded NSA value
            })->map(function($items) {
                return $items->pluck('token')->toArray();
            })->toArray();
        
                // dd($payloadData);
                $payload = json_encode($payloadData,true);
                $respond = $this->updatePortfolioLtpData($payload);
                // dd($respond);
                $respond = json_decode($respond,true);
                // dd($respond,$alldata);
                if(isset($respond)){
                    if($respond['status'] == true){
                        array_push($apiResponse,$respond['data']['fetched']);
                    }
                } 
                array_push($error,$respond);
                sleep(1);
            }

        }


        // UPDATE LTP FROM THE RESPONE DATA
        if(count($apiResponse)){
            foreach ($apiResponse as $k => $respond) {
                foreach ($respond as $y => $value) {
                    $searchToken = $value['symbolToken'];
                    $tokens = array_column($alldata, 'token');
                    $index = array_search($searchToken, $tokens);
                    if ($index !== false) {
                        $name = $alldata[$index]['name']."-EQ";
                        foreach ($portfolioTables as $key => $v) {
                            $data = \DB::table($v)->where('stock_name',$name)->update(['cmp' => $value['ltp']]);
                        }
                    }
                }
            }
        }
    }
}
