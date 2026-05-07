<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StengthTb;

class StoreStrengthData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store_strength_data:every_fifteen_minute';

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

    // function market_sentiment($atm_ce_iv, $atm_pe_iv, $atm_ce_delta, $atm_pe_delta, $atm_ce_theta, $atm_pe_theta, $atm_ce_vega, $atm_pe_vega, $atm_ce_gamma, $atm_pe_gamma){

   
    //     $sentiment_atm = $this->calculate_sentiment($atm_ce_iv, $atm_pe_iv, $atm_ce_delta, $atm_pe_delta, $atm_ce_theta, $atm_pe_theta, $atm_ce_vega, $atm_pe_vega, $atm_ce_gamma, $atm_pe_gamma);

    //     // Calculate strength based on the difference between CE and PE values
    //     $strength_atm = $this->calculate_strength($atm_ce_iv, $atm_pe_iv);

    //     // Combine sentiments and strengths for all three strikes
    //     $market_sentiment = array(
    //         array_merge($sentiment_atm, ['Strength' => $strength_atm])
    //     );

    //     return $market_sentiment;
    // }

    // function calculate_sentiment($ce_iv, $pe_iv, $ce_delta, $pe_delta, $ce_theta, $pe_theta, $ce_vega, $pe_vega, $ce_gamma, $pe_gamma) {
    //     // Calculate the overall Delta, Theta, Vega for CE and PE
    //     $overall_delta = $ce_delta + $pe_delta;
    //     $overall_theta = $ce_theta + $pe_theta;
    //     $overall_vega = $ce_vega + $pe_vega;
    //     $overall_gamma = $ce_gamma + $pe_gamma;
        
    //     // Determine sentiment based on the provided criteria
    //     $sentiment = '';        
    //     if ($ce_iv > $pe_iv) {
    //         $sentiment = "Bullish";
    //     } elseif ($ce_iv < $pe_iv) {
    //         $sentiment = "Bearish";
    //     } else {
    //         $sentiment = "Neutral";
    //     }
        
    //     if ($overall_delta > 0) {
    //         $sentiment .= " Bullish";
    //     } elseif ($overall_delta < 0) {
    //         $sentiment .= " Bearish";
    //     } else {
    //         $sentiment .= " Neutral";
    //     }
        
    //     if ($overall_theta > 0) {
    //         $sentiment .= " Bullish";
    //     } elseif ($overall_theta < 0) {
    //         $sentiment .= " Bearish";
    //     } else {
    //         $sentiment .= " Neutral";
    //     }
        
    //     if ($overall_vega > 0) {
    //         $sentiment .= " Bullish";
    //     } elseif ($overall_vega < 0) {
    //         $sentiment .= " Bearish";
    //     } else {
    //         $sentiment .= " Neutral";
    //     }

    //     if ($overall_gamma > 0) {
    //         $sentiment .= " Bullish";
    //     } elseif ($overall_gamma < 0) {
    //         $sentiment .= " Bearish";
    //     } else {
    //         $sentiment .= " Neutral";
    //     }
        
    //     return array(
    //         'IV' => array('CE' => $ce_iv, 'PE' => $pe_iv),
    //         'Delta' => array('CE' => $ce_delta, 'PE' => $pe_delta),
    //         'Theta' => array('CE' => $ce_theta, 'PE' => $pe_theta),
    //         'Vega' => array('CE' => $ce_vega, 'PE' => $pe_vega),
    //         'Gamma' => array('CE' => $ce_gamma, 'PE' => $pe_gamma),
    //         'Sentiment' => $sentiment
    //     );
    // }

    // Function to calculate strength based on the difference between CE and PE values
    // function calculate_strength($ce_iv, $pe_iv) {
    //     if ($ce_iv > $pe_iv) {
    //         return "Bullish";
    //     } elseif ($ce_iv < $pe_iv) {
    //         return "Bearish";
    //     } else {
    //         return "Neutral";
    //     }
    // }

    // DATA
    function determine_sentiment($ce_iv, $pe_iv, $ce_delta, $pe_delta, $ce_theta, $pe_theta, $ce_vega, $pe_vega, $ce_gamma, $pe_gamma) {
        $sentiment = [];
        // Determine sentiment for each value
        $sentiment['CE IV'] = ($ce_iv > $pe_iv) ? 'Bullish' : 'Bearish';
        $sentiment['PE IV'] = ($pe_iv > $ce_iv) ? 'Bullish' : 'Bearish';
        $sentiment['CE Delta'] = ($ce_delta > 0) ? 'Bullish' : 'Bearish';
        $sentiment['PE Delta'] = ($pe_delta > 0) ? 'Bullish' : 'Bearish';
        $sentiment['CE Theta'] = ($ce_theta > 0) ? 'Bearish' : 'Bullish';
        $sentiment['PE Theta'] = ($pe_theta > 0) ? 'Bearish' : 'Bullish';
        $sentiment['CE Vega'] = ($ce_vega > 0) ? 'Bullish' : 'Bearish';
        $sentiment['PE Vega'] = ($pe_vega > 0) ? 'Bullish' : 'Bearish';
        $sentiment['CE Gamma'] = ($ce_gamma > 0) ? 'Bullish' : 'Bearish';
        $sentiment['PE Gamma'] = ($pe_gamma > 0) ? 'Bullish' : 'Bearish';
        return $sentiment;
    }

    function calculate_market_sentiment($ce_iv_sentiment, $pe_iv_sentiment, $ce_delta_sentiment, $pe_delta_sentiment) {
        // Count occurrences of bullish, bearish, and neutral sentiments for each data point
        $sentiments = [$ce_iv_sentiment, $pe_iv_sentiment, $ce_delta_sentiment, $pe_delta_sentiment];
        $counts = array_count_values($sentiments);
        // Determine the overall sentiment based on the majority
        $bullish_count = isset($counts['Bullish']) ? $counts['Bullish'] : 0;
        $bearish_count = isset($counts['Bearish']) ? $counts['Bearish'] : 0;
        $neutral_count = isset($counts['Neutral']) ? $counts['Neutral'] : 0;
        if ($bullish_count > $bearish_count && $bullish_count > $neutral_count) {
            $market_sentiment = 'Bullish';
        } elseif ($bearish_count > $bullish_count && $bearish_count > $neutral_count) {
            $market_sentiment = 'Bearish';
        } else {
            $market_sentiment = 'Neutral';
        }
        return $market_sentiment;
    }

    public function handle()
    {
        $todayDate = date("Y-m-d",strtotime('-1Day'));
        $timeframe = 15;
        $symbolArr = ['CRUDEOIL','BANKNIFTY','FINNIFTY','NIFTY','MIDCPNIFTY','NATURALGAS']; // REMOVED GOLD,SILVER 
        foreach ($symbolArr as $table) {
            $data =  \DB::connection('mysql_rm')->table($table)->select('*')->where(['date' => $todayDate, 'timeframe' => $timeframe])->get();

            $timeStampData = [];
            foreach ($data as $value) {
                $timeStampData[$value->timestamp][] = $value;
            }

            foreach ($timeStampData as $timestamp => $data) {
                $ce_vi_data = [];
                $pe_vi_data = [];
                $ce_delta_data = [];
                $pe_delta_data = [];
                $ce_theta_data = [];
                $pe_theta_data = [];
                $ce_vega_data = [];
                $pe_vega_data = [];
                $ce_gamma_data = [];
                $pe_gamma_data = [];
                $finalData = [];
                foreach ($data as  $value) {
                    array_push($ce_vi_data,$value->ce_iv);
                    array_push($pe_vi_data,$value->pe_iv);
                    array_push($ce_delta_data,$value->ce_delta);
                    array_push($pe_delta_data,$value->pe_delta);
                    array_push($ce_theta_data,$value->ce_theta);
                    array_push($pe_theta_data,$value->pe_theta);
                    array_push($ce_vega_data,$value->ce_vega);
                    array_push($pe_vega_data,$value->pe_vega);
                    array_push($ce_gamma_data,$value->ce_gamma);
                    array_push($pe_gamma_data,$value->pe_gamma);
                }       

                // FINAL DATA
                $finalData['ce_iv'] = array_sum($ce_vi_data)/3;
                $finalData['pe_iv'] = array_sum($pe_vi_data)/3;
                $finalData['ce_delta'] = array_sum($ce_delta_data)/3;
                $finalData['pe_delta'] = array_sum($pe_delta_data)/3;
                $finalData['ce_theta'] = array_sum($ce_theta_data)/3;
                $finalData['pe_theta'] = array_sum($pe_theta_data)/3;
                $finalData['ce_vega'] = array_sum($ce_vega_data)/3;
                $finalData['pe_vega'] = array_sum($pe_vega_data)/3;
                $finalData['ce_gamma'] = array_sum($ce_gamma_data)/3;
                $finalData['pe_gamma'] = array_sum($pe_gamma_data)/3;

                $sentiment = $this->determine_sentiment($finalData['ce_iv'],$finalData['pe_iv'],$finalData['ce_delta'],$finalData['pe_delta'],$finalData['ce_theta'],$finalData['pe_theta'],$finalData['ce_vega'],$finalData['pe_vega'],$finalData['ce_gamma'],$finalData['pe_gamma']);

                $market_sentiment = $this->calculate_market_sentiment($sentiment['CE IV'], $sentiment['PE IV'], $sentiment['CE Delta'], $sentiment['PE Delta']);


                if(count($sentiment)){
                    try {
                        $addStrength = StengthTb::create([
                            'symbol_name'=>$table,
                            'ce_iv'=>$sentiment['CE IV'],
                            'pe_iv'=>$sentiment['PE IV'],
                            'ce_delta'=>$sentiment['CE Delta'],
                            'pe_delta'=>$sentiment['PE Delta'],
                            'ce_theta'=>$sentiment['CE Theta'],
                            'pe_theta'=>$sentiment['PE Theta'],
                            'ce_vega'=>$sentiment['CE Vega'],
                            'pe_vega'=>$sentiment['PE Vega'],
                            'ce_gamma'=>$sentiment['CE Gamma'],
                            'pe_gamma'=>$sentiment['PE Gamma'],
                            'strength'=>$market_sentiment,
                            'timestamp'=>$timestamp
                        ]);
                    } catch (\Throwable $th) {
                        dd($th->getMessage());
                    }
                }
            }

            
        }

    }

}
