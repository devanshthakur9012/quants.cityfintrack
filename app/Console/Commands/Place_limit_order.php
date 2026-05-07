<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Traits\AngelApiAuth;
use App\Models\WatchList;
class Place_limit_order extends Command
{

    use AngelApiAuth;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'place_limit_ordre:limitOrder';

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
        $watchListData = WatchList::select('id','token','exchange','buy_price')->where('status','pending')->get()->toArray();
        $uniqueToken = array();
        $uniqueWatchListData = array();
        foreach ($watchListData as $key => $watchItem) {
            if(!in_array($watchItem['token'], $uniqueToken)){
                array_push($uniqueToken,$watchItem['token']);
                $data = [
                    'token'=>$watchItem['token'],
                    'exchange'=>$watchItem['exchange']
                ];
                array_push($uniqueWatchListData ,$data);
            }
        }
        $newArray = array_chunk($uniqueWatchListData,50);
        if($newArray != NULL){
            foreach ($newArray as $k => $watch) {
                $MCXpayload = [];
                $NFOpayload = [];
                foreach ($watch as $key => $value) {
                    if($value['exchange'] == "MCX"){
                        array_push($MCXpayload,$value['token']);
                    }else if($value['exchange'] == "NFO"){
                        array_push($NFOpayload,$value['token']);
                    }
                }

                $payload = [
                    'MCX'=>$MCXpayload,
                    'NFO'=>$NFOpayload
                ];

                $payload = json_encode($payload,true);
                $respond = $this->getWatchListRecords($payload);
                if($respond == NULL){
                    $respond = $this->getWatchListRecords($payload);
                }

                if($respond['data']['fetched'] != NULL){
                    $RespondData = $respond['data']['fetched'];
                    foreach ($RespondData as $key => $item) {
                        $searchToken = $item['symbolToken'];
                        $searchLtp = $item['ltp'];
                        $result = array_filter($watchListData, function($watchItem) use ($searchToken, $searchLtp) {
                            return $watchItem['token'] == $searchToken && $watchItem['buy_price'] >= $searchLtp;
                        });

                        if($result != NULL){
                            foreach ($result as $key => $value) {
                                $watchListData = WatchList::where('id',$value['id'])->first();
                                $watchListData->order_type = "market";
                                $watchListData->status = "executed";
                                $watchListData->save();

                                // Check For Previous BUY OR SELL FOR A PARTICULAR STOCK
                                $makeAvgPrice = WatchList::WHERE('status','executed')->Where('token',$watchListData->token)->WHERE('user_id',$watchListData->user_id)->get();
                                $totalBuyPrice = 0;
                                $totalBuyQuantity = 0;
                                $totalSellPrice = 0;
                                $totalSellQuantity = 0;

                                if(count($makeAvgPrice)){
                                    foreach ($makeAvgPrice as $key => $value) {
                                        if($value->type == "BUY"){
                                            $totalBuyPrice += ($value->quantity * $value->buy_price);
                                            $totalBuyQuantity += $value->quantity;
                                        }

                                        if($value->type == "SELL"){
                                            $totalSellPrice += ($value->quantity * $value->buy_price);
                                            $totalSellQuantity += $value->quantity;
                                        }
                                    }
                                }

                                // For CURRENT RECORD
                                if ($watchListData->type == "BUY") {
                                    $totalBuyPrice += ($watchListData->quantity * $watchListData->price);
                                    $totalBuyQuantity += $watchListData->quantity;
                                }

                                if ($watchListData->type == "SELL") {
                                    $totalSellPrice += ($watchListData->quantity * $watchListData->price);
                                    $totalSellQuantity += $watchListData->quantity;
                                }

                                if($totalBuyQuantity > 0){
                                    $BuyavgPrice = round($totalBuyPrice / $totalBuyQuantity,2);  
                                }else{
                                    $BuyavgPrice = 0;
                                }

                                if($totalSellQuantity > 0){
                                    $SellavgPrice = round($totalSellPrice / $totalSellQuantity,2);  
                                }else{
                                    $SellavgPrice = 0;
                                }

                                $netChange = ($totalBuyQuantity * $totalBuyPrice) - ($totalSellPrice * $totalSellQuantity);

                                if($watchListData->status == "executed"){
                                    $watchTradePosition = WatchTradePosition::Where('token',$watchListData->token)->WHERE('user_id',$watchListData->user_id)->first();
                                    if($watchTradePosition != NULL){
                                        $watchTradePosition->buy_quantity = $totalBuyQuantity;
                                        $watchTradePosition->buy_price = $BuyavgPrice;
                                        $watchTradePosition->net_change = $netChange;
                                        $watchTradePosition->sell_quantity = $totalSellQuantity;
                                        $watchTradePosition->sell_price = $SellavgPrice;
                                        $watchTradePosition->save();
                                    }else{
                                        $tradePostion = new WatchTradePosition;
                                        $tradePostion->user_id = $userId;
                                        $tradePostion->token = $watchListData->token;
                                        $tradePostion->symbol = $watchListData->symbol;
                                        $tradePostion->exchange = $watchListData->exchange;
                                        $tradePostion->buy_quantity = $totalBuyQuantity;
                                        $tradePostion->buy_price = $BuyavgPrice;
                                        $tradePostion->sell_quantity = $totalSellQuantity;
                                        $tradePostion->sell_price = $SellavgPrice;
                                        $tradePostion->net_change = $netChange;
                                        $tradePostion->save();
                                    }
                                }

                            }
                        }
                    }
                }
            }
        }
    }
}
