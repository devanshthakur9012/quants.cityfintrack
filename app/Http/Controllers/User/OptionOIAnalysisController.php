<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\OIAnalysisService;
use App\Models\OptionStrike;
use App\Models\OptionOiData;
use App\Models\ZerodhaInstrument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OptionOIAnalysisController extends Controller
{
    protected $oiService;

    public function __construct(OIAnalysisService $oiService)
    {
        $this->oiService = $oiService;
    }

    /**
     * Display OI Analysis Page
     */
    public function index()
    {
        $pageTitle = 'Option OI Analysis - Positioning & Signals';
        
        // Get available underlying symbols
        $underlyings = OptionStrike::active()
            ->distinct()
            ->pluck('underlying_symbol')
            ->sort()
            ->values()
            ->toArray();
            
        return view('templates.basic.user.new_option.oi-analysis', compact('pageTitle', 'underlyings'));
    }

    /**
     * Fetch OI Analysis Data (AJAX) - Analyze all or selected symbols
     */
    public function analysisFetch(Request $request)
    {
        try {
            Log::info('=== OI ANALYSIS START ===', [
                'inputs' => $request->all()
            ]);

            $fromDate = $request->get('from_date');
            $toDate = $request->get('to_date');
            $selectedSymbols = $request->get('symbols', []);
            $lookbackPeriod = (int) $request->get('lookback', 3);

            // Validation
            if (!$fromDate || !$toDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select both From and To dates',
                    'data' => []
                ]);
            }

            // Get symbols to analyze
            $symbolsQuery = OptionStrike::active()
                ->distinct()
                ->select('underlying_symbol');
            
            if (!empty($selectedSymbols)) {
                $symbolsQuery->whereIn('underlying_symbol', $selectedSymbols);
            }
            
            $symbols = $symbolsQuery->pluck('underlying_symbol')->unique()->values()->toArray();

            Log::info('Processing Symbols', [
                'total' => count($symbols),
                'symbols' => $symbols
            ]);

            if (empty($symbols)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid symbols to analyze',
                    'data' => []
                ]);
            }

            $startDateTime = $fromDate . ' 09:15:00';
            $endDateTime = $toDate . ' 15:30:00';

            // Analyze each symbol
            $allResults = [];

            foreach ($symbols as $underlying) {
                $result = $this->analyzeSymbol($underlying, $startDateTime, $endDateTime, $lookbackPeriod);
                
                if ($result) {
                    $allResults[] = $result;
                }
            }

            Log::info('=== OI ANALYSIS COMPLETE ===', [
                'total_results' => count($allResults)
            ]);

            return response()->json([
                'success' => true,
                'data' => $allResults,
                'total_signals' => count($allResults),
                'message' => count($allResults) . ' symbols analyzed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('OI Analysis Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Analyze a single symbol
     */
    private function analyzeSymbol($underlying, $startDateTime, $endDateTime, $lookbackPeriod)
    {
        try {
            // Get ATM strikes
            $strikes = OptionStrike::active()
                ->forUnderlying($underlying)
                ->whereIn('strike_position', ['ATM-1', 'ATM', 'ATM+1'])
                ->currentExpiry()
                ->get();

            if ($strikes->isEmpty()) {
                Log::warning("No active strikes found for {$underlying}");
                return null;
            }

            Log::info("Found {$strikes->count()} strikes for {$underlying}");

            // Group strikes by type
            $ceStrikes = $strikes->where('option_type', 'CE');
            $peStrikes = $strikes->where('option_type', 'PE');

            // Get OI time series for each strike
            $ceOI = $this->getOITimeSeries($ceStrikes, $startDateTime, $endDateTime);
            $peOI = $this->getOITimeSeries($peStrikes, $startDateTime, $endDateTime);

            // Check if we have data
            $hasCEData = collect($ceOI)->filter(fn($data) => !empty($data))->isNotEmpty();
            $hasPEData = collect($peOI)->filter(fn($data) => !empty($data))->isNotEmpty();

            if (!$hasCEData && !$hasPEData) {
                Log::warning("No OI data found for {$underlying}");
                return null;
            }

            // Perform OI analysis
            $ceResult = $this->oiService->aggregateStrikeBuildup($ceOI, $lookbackPeriod);
            $peResult = $this->oiService->aggregateStrikeBuildup($peOI, $lookbackPeriod);

            // Get market bias
            $marketBias = $this->oiService->interpretMarketBias($ceResult['netScore'], $peResult['netScore']);

            // Get professional insights
            $insights = $this->oiService->getProfessionalInsights($ceResult, $peResult, $marketBias);

            // Calculate OI change summary
            $ceChangeSummary = $this->oiService->calculateOIChangeSummary($ceOI);
            $peChangeSummary = $this->oiService->calculateOIChangeSummary($peOI);

            // Get positioning strength
            $ceStrength = $this->oiService->getPositioningStrength($ceResult['netScore']);
            $peStrength = $this->oiService->getPositioningStrength($peResult['netScore']);

            return [
                'underlying' => $underlying,
                'analysis_period' => Carbon::parse($startDateTime)->format('Y-m-d') . ' to ' . Carbon::parse($endDateTime)->format('Y-m-d'),
                'lookback' => $lookbackPeriod,
                'ce_analysis' => [
                    'strike_wise' => $ceResult['strikeWise'],
                    'net_score' => $ceResult['netScore'],
                    'summary' => $ceChangeSummary,
                    'strength' => $ceStrength
                ],
                'pe_analysis' => [
                    'strike_wise' => $peResult['strikeWise'],
                    'net_score' => $peResult['netScore'],
                    'summary' => $peChangeSummary,
                    'strength' => $peStrength
                ],
                'market_bias' => $marketBias,
                'insights' => $insights,
                'strikes_info' => [
                    'ce_strikes' => $ceStrikes->map(function($s) {
                        return [
                            'position' => $s->strike_position,
                            'strike' => $s->strike_price,
                            'symbol' => $s->trading_symbol,
                            'strike_id' => $s->id
                        ];
                    })->values()->toArray(),
                    'pe_strikes' => $peStrikes->map(function($s) {
                        return [
                            'position' => $s->strike_position,
                            'strike' => $s->strike_price,
                            'symbol' => $s->trading_symbol,
                            'strike_id' => $s->id
                        ];
                    })->values()->toArray()
                ]
            ];

        } catch (\Exception $e) {
            Log::error("Error analyzing {$underlying}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get OI time series for strikes
     */
    private function getOITimeSeries($strikes, $startDateTime, $endDateTime)
    {
        $oiData = [];

        foreach ($strikes as $strike) {
            $records = OptionOiData::where('option_strike_id', $strike->id)
                ->where('timestamp', '>=', $startDateTime)
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('timestamp', 'ASC')
                ->pluck('oi')
                ->toArray();

            $oiData[$strike->strike_position] = $records;
        }

        return $oiData;
    }

    /**
     * Calculate theoretical profit based on OI signals
     */
    public function calculateProfit(Request $request)
    {
        try {
            Log::info('=== OI PROFIT CALCULATION START ===');
            
            $signals = $request->input('signals', []);
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');
            
            if (empty($signals)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No signals provided',
                    'data' => []
                ]);
            }

            Log::info("Processing " . count($signals) . " signals");

            $results = [];
            $totalProfit = 0;
            $totalInvestment = 0;
            $totalTrades = 0;
            $winningTrades = 0;
            $losingTrades = 0;

            foreach ($signals as $signal) {
                // Only process signals with clear directional bias
                if (!isset($signal['market_bias']) || 
                    $signal['market_bias'] === 'NO_CLEAR_PRESSURE' ||
                    $signal['market_bias'] === 'RANGE / WRITER_DOMINANCE') {
                    continue;
                }

                $result = $this->calculateSignalProfit($signal, $fromDate, $toDate);
                
                if ($result) {
                    $results[] = $result;
                    $totalProfit += $result['profit_loss'];
                    $totalInvestment += $result['investment'];
                    $totalTrades++;
                    
                    if ($result['profit_loss'] > 0) {
                        $winningTrades++;
                    } elseif ($result['profit_loss'] < 0) {
                        $losingTrades++;
                    }
                }
            }

            $winRate = $totalTrades > 0 ? round(($winningTrades / $totalTrades) * 100, 2) : 0;
            $avgProfit = $totalTrades > 0 ? round($totalProfit / $totalTrades, 2) : 0;

            Log::info('=== OI PROFIT CALCULATION COMPLETE ===', [
                'total_trades' => $totalTrades,
                'total_profit' => $totalProfit,
                'win_rate' => $winRate
            ]);

            return response()->json([
                'success' => true,
                'data' => $results,
                'summary' => [
                    'total_trades' => $totalTrades,
                    'winning_trades' => $winningTrades,
                    'losing_trades' => $losingTrades,
                    'win_rate' => $winRate,
                    'total_investment' => round($totalInvestment, 2),
                    'total_profit_loss' => round($totalProfit, 2),
                    'avg_profit_loss' => $avgProfit,
                    'roi_percent' => $totalInvestment > 0 ? round(($totalProfit / $totalInvestment) * 100, 2) : 0
                ],
                'message' => 'Profit calculation completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Profit Calculation Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    /**
     * Calculate profit for a single signal
     */
    private function calculateSignalProfit($signal, $fromDate, $toDate)
    {
        try {
            $underlying = $signal['underlying'];
            $marketBias = $signal['market_bias'];
            
            Log::info("Calculating profit for: {$underlying} - Bias: {$marketBias}");

            // Determine which option to trade based on market bias
            if (strpos($marketBias, 'BULLISH') !== false) {
                $optionType = 'CE';
                $strikePosition = 'ATM';
            } elseif (strpos($marketBias, 'BEARISH') !== false) {
                $optionType = 'PE';
                $strikePosition = 'ATM';
            } else {
                return null;
            }

            // Get the option strike
            $strikes = $optionType === 'CE' ? $signal['strikes_info']['ce_strikes'] : $signal['strikes_info']['pe_strikes'];
            $strikeInfo = collect($strikes)->firstWhere('position', $strikePosition);

            if (!$strikeInfo) {
                Log::warning("Strike not found for {$underlying} {$optionType} {$strikePosition}");
                return null;
            }

            // Get option instrument
            $instrument = ZerodhaInstrument::where('trading_symbol', $strikeInfo['symbol'])
                ->where('exchange', 'NFO')
                ->first();

            if (!$instrument) {
                Log::warning("Instrument not found: {$strikeInfo['symbol']}");
                return null;
            }

            // Get opening and closing prices
            $startDateTime = $fromDate . ' 09:15:00';
            $endDateTime = $toDate . ' 15:25:00';

            $buyPriceData = OptionOiData::where('option_strike_id', $strikeInfo['strike_id'])
                ->where('timestamp', '>=', $startDateTime)
                ->orderBy('timestamp', 'ASC')
                ->first();

            $sellPriceData = OptionOiData::where('option_strike_id', $strikeInfo['strike_id'])
                ->where('timestamp', '<=', $endDateTime)
                ->orderBy('timestamp', 'DESC')
                ->first();

            if (!$buyPriceData || !$sellPriceData) {
                Log::warning("Price data not available for {$strikeInfo['symbol']}");
                return null;
            }

            $buyPrice = $buyPriceData->ltp;
            $sellPrice = $sellPriceData->ltp;

            if (!$buyPrice || !$sellPrice) {
                return null;
            }

            // Calculate P/L
            $quantity = $instrument->lot_size ?? 1;
            $profitLoss = ($sellPrice - $buyPrice) * $quantity;
            $investment = $buyPrice * $quantity;

            Log::info("✅ {$strikeInfo['symbol']}: Buy={$buyPrice}, Sell={$sellPrice}, P/L={$profitLoss}");

            return [
                'underlying' => $underlying,
                'option_symbol' => $strikeInfo['symbol'],
                'option_type' => $optionType,
                'strike_price' => $strikeInfo['strike'],
                'market_bias' => $marketBias,
                'buy_time' => $buyPriceData->timestamp->format('Y-m-d H:i:s'),
                'sell_time' => $sellPriceData->timestamp->format('Y-m-d H:i:s'),
                'buy_price' => round($buyPrice, 2),
                'sell_price' => round($sellPrice, 2),
                'quantity' => $quantity,
                'investment' => round($investment, 2),
                'profit_loss' => round($profitLoss, 2),
                'profit_loss_per_lot' => round($sellPrice - $buyPrice, 2),
                'return_percent' => $buyPrice > 0 ? round((($sellPrice - $buyPrice) / $buyPrice) * 100, 2) : 0,
                'ce_score' => $signal['ce_analysis']['net_score'],
                'pe_score' => $signal['pe_analysis']['net_score']
            ];

        } catch (\Exception $e) {
            Log::error("Error calculating profit: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Export to CSV
     */
    public function export(Request $request)
    {
        try {
            $data = json_decode($request->input('data'), true);

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No data to export'
                ], 400);
            }

            $filename = 'oi_analysis_' . date('Y-m-d_His') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($output, [
                'Underlying',
                'Market Bias',
                'Position',
                'Strike Price',
                'CE Signal',
                'PE Signal',
                'CE Net Score',
                'PE Net Score',
                'CE Strength',
                'PE Strength',
                'CE Symbol',
                'PE Symbol'
            ]);

            // CSV Data
            foreach ($data as $row) {
                $positions = ['ATM-1', 'ATM', 'ATM+1'];
                
                foreach ($positions as $position) {
                    $ceStrike = collect($row['strikes_info']['ce_strikes'] ?? [])->firstWhere('position', $position);
                    $peStrike = collect($row['strikes_info']['pe_strikes'] ?? [])->firstWhere('position', $position);
                    
                    $ceSignal = $row['ce_analysis']['strike_wise'][$position] ?? 'N/A';
                    $peSignal = $row['pe_analysis']['strike_wise'][$position] ?? 'N/A';
                    
                    fputcsv($output, [
                        $row['underlying'],
                        $row['market_bias'],
                        $position,
                        $ceStrike['strike'] ?? '-',
                        str_replace('_', ' ', $ceSignal),
                        str_replace('_', ' ', $peSignal),
                        $row['ce_analysis']['net_score'],
                        $row['pe_analysis']['net_score'],
                        $row['ce_analysis']['strength']['strength_level'] ?? 'N/A',
                        $row['pe_analysis']['strength']['strength_level'] ?? 'N/A',
                        $ceStrike['symbol'] ?? '-',
                        $peStrike['symbol'] ?? '-'
                    ]);
                }
            }

            fclose($output);
            exit;

        } catch (\Exception $e) {
            Log::error('Export CSV Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error exporting data'
            ], 500);
        }
    }
}