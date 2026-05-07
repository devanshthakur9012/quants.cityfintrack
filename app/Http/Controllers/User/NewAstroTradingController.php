<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SymbolList;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NewAstroTradingController extends Controller
{
    /**
     * Enhanced Ephemeris with Retrograde flags
     */
    private $ephemeris = [
        '2025-11-18' => [
            'sun' => 255.8, 'moon' => 280.7, 'mercury' => 150.2, 'venus' => 142.1, 
            'mars' => 51.4, 'jupiter' => 76.6, 'saturn' => 353.1, 
            'uranus' => 52.3, 'neptune' => 356.8, 'pluto' => 300.2,
            'rahu' => 27.7, 'ketu' => 207.7,
            'mercury_retro' => 0, 'venus_retro' => 0, 'mars_retro' => 0,
            'jupiter_retro' => 1, 'saturn_retro' => 0
        ],
        '2025-11-19' => [
            'sun' => 256.8, 'moon' => 293.9, 'mercury' => 152.8, 'venus' => 143.7,
            'mars' => 52.6, 'jupiter' => 76.6, 'saturn' => 353.1,
            'uranus' => 52.2, 'neptune' => 356.8, 'pluto' => 300.2,
            'rahu' => 27.6, 'ketu' => 207.6,
            'mercury_retro' => 0, 'venus_retro' => 0, 'mars_retro' => 0,
            'jupiter_retro' => 1, 'saturn_retro' => 0
        ],
        '2025-11-20' => [
            'sun' => 257.8, 'moon' => 307.1, 'mercury' => 155.4, 'venus' => 145.3,
            'mars' => 53.8, 'jupiter' => 76.6, 'saturn' => 353.0,
            'uranus' => 52.1, 'neptune' => 356.8, 'pluto' => 300.2,
            'rahu' => 27.5, 'ketu' => 207.5,
            'mercury_retro' => 0, 'venus_retro' => 0, 'mars_retro' => 0,
            'jupiter_retro' => 1, 'saturn_retro' => 0
        ],
    ];

    /**
     * Stock-Planet Sensitivity Mapping
     */
    private $stockSensitivity = [
        'APOLLOHOSP' => ['sun' => 0.4, 'jupiter' => 0.3, 'ketu' => 0.3],
        'ASIANPAINT' => ['venus' => 0.5, 'moon' => 0.3, 'mercury' => 0.2],
        'AXISBANK' => ['jupiter' => 0.6, 'venus' => 0.3, 'mercury' => 0.1],
        'BAJAJ-AUTO' => ['mars' => 0.6, 'jupiter' => 0.3, 'mercury' => 0.1],
        'BAJAJFINSV' => ['jupiter' => 0.5, 'mercury' => 0.4, 'venus' => 0.1],
        'BAJFINANCE' => ['mercury' => 0.5, 'jupiter' => 0.4, 'venus' => 0.1],
        'BALKRISIND' => ['mars' => 0.5, 'saturn' => 0.3, 'mercury' => 0.2],
        'BANKNIFTY' => ['jupiter' => 0.6, 'venus' => 0.3, 'mercury' => 0.1],
        'BHARTIARTL' => ['mercury' => 0.7, 'jupiter' => 0.2, 'uranus' => 0.1],
        'BRITANNIA' => ['moon' => 0.6, 'venus' => 0.3, 'mercury' => 0.1],
        'CIPLA' => ['sun' => 0.4, 'jupiter' => 0.3, 'ketu' => 0.3],
        'CRUDEOIL' => ['sun' => 0.5, 'mars' => 0.4, 'pluto' => 0.1],
        'DIVISLAB' => ['sun' => 0.5, 'jupiter' => 0.3, 'mercury' => 0.2],
        'DRREDDY' => ['sun' => 0.5, 'jupiter' => 0.3, 'mercury' => 0.2],
        'EICHERMOT' => ['mars' => 0.6, 'venus' => 0.3, 'mercury' => 0.1],
        'GRASIM' => ['saturn' => 0.6, 'jupiter' => 0.3, 'mars' => 0.1],
    ];

    /**
     * Sector Classification
     */
    private $sectorMap = [
        'APOLLOHOSP' => 'Healthcare',
        'ASIANPAINT' => 'Materials',
        'AXISBANK' => 'Banks',
        'BAJAJ-AUTO' => 'Auto',
        'BAJAJFINSV' => 'NBFC',
        'BAJFINANCE' => 'NBFC',
        'BALKRISIND' => 'Auto',
        'BANKNIFTY' => 'Index',
        'BHARTIARTL' => 'Telecom',
        'BRITANNIA' => 'FMCG',
        'CIPLA' => 'Pharma',
        'CRUDEOIL' => 'Commodity',
        'DIVISLAB' => 'Pharma',
        'DRREDDY' => 'Pharma',
        'EICHERMOT' => 'Auto',
        'GRASIM' => 'Cement',
    ];

    /**
     * Sector rulership by planets (Financial Astrology)
     */
    private $sectorRulership = [
        'sun' => ['Energy', 'Oil & Gas', 'Power', 'Pharma', 'Government'],
        'moon' => ['FMCG', 'Retail', 'Food & Beverages', 'Agriculture'],
        'mars' => ['Steel', 'Defence', 'Engineering', 'Auto', 'Real Estate'],
        'mercury' => ['IT', 'Telecom', 'Media', 'E-commerce', 'Logistics'],
        'jupiter' => ['Banks', 'Financial Services', 'Insurance', 'Education'],
        'venus' => ['Luxury', 'Fashion', 'Hotels', 'Entertainment', 'Jewelry'],
        'saturn' => ['Infrastructure', 'Mining', 'Cement', 'Heavy Industries'],
        'rahu' => ['Tech Startups', 'Chemicals', 'Alcohol', 'Unconventional'],
        'ketu' => ['Spirituality', 'Healthcare', 'Alternative Medicine']
    ];

    /**
     * Aspect Definitions with Orbs
     */
    private $aspectDefinitions = [
        'conjunction' => ['angle' => 0, 'orb' => 8, 'nature' => 'powerful', 'strength' => 10],
        'opposition' => ['angle' => 180, 'orb' => 8, 'nature' => 'tense', 'strength' => 9],
        'trine' => ['angle' => 120, 'orb' => 8, 'nature' => 'harmonious', 'strength' => 8],
        'square' => ['angle' => 90, 'orb' => 7, 'nature' => 'challenging', 'strength' => 8],
        'sextile' => ['angle' => 60, 'orb' => 6, 'nature' => 'supportive', 'strength' => 6],
    ];

    /**
     * Aspect Impact on Market
     */
    private $aspectImpacts = [
        'Mars-Jupiter-trine' => 12,
        'Sun-Jupiter-trine' => 9,
        'Mercury-Jupiter-trine' => 7,
        'Venus-Jupiter-sextile' => 6,
        'Sun-Jupiter-sextile' => 6,
        'Mercury-Uranus-opposition' => 0, // volatility only
        'Mars-Uranus-square' => -12,
        'Sun-Pluto-square' => -14,
        'Mercury-Pluto-square' => -8,
        'Saturn-Mars-square' => -10,
        'Venus-Saturn-square' => -6,
    ];

    public function index()
    {
        $pageTitle = 'Advanced Astro Analysis - Stock Specific';
        $symbols = ['APOLLOHOSP', 'ASIANPAINT', 'AXISBANK', 'BAJAJ-AUTO', 'BAJAJFINSV', 
                    'BAJFINANCE', 'BALKRISIND', 'BANKNIFTY', 'BHARTIARTL', 'BRITANNIA',
                    'CIPLA', 'CRUDEOIL', 'DIVISLAB', 'DRREDDY', 'EICHERMOT', 'GRASIM'];
        
        return view($this->activeTemplate . 'user.option.analysis.new-astro-analysis', compact('pageTitle', 'symbols'));
    }

    public function generateReport(Request $request)
    {
        $date = $request->input('date', Carbon::now()->format('Y-m-d'));
        $symbols = $request->input('symbols', []);
        
        if (empty($symbols)) {
            return response()->json(['status' => 'error', 'message' => 'Please select at least one symbol'], 400);
        }

        try {
            // Get planetary positions
            $planets = $this->getPlanetaryData($date);
            
            // Detect aspects
            $aspects = $this->detectAspects($planets);
            
            // Calculate market conviction & volatility
            [$conviction, $volatility, $posture] = $this->calculateMarketMetrics($aspects, $planets);
            
            // Moon timing analysis
            $moonTimings = $this->analyzeMoonTimings($planets);
            
            // Generate stock reports
            $stockReports = [];
            foreach ($symbols as $symbol) {
                $stockReports[] = $this->generateStockReport($symbol, $planets, $aspects, $conviction, $volatility, $posture, $moonTimings, $date);
            }
            
            // Sector summary
            $sectorSummary = $this->generateSectorSummary($stockReports, $aspects, $planets);
            
            // Market overview
            $marketOverview = $this->generateMarketOverview($conviction, $volatility, $posture, $aspects, $planets);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'date' => $date,
                    'market_overview' => $marketOverview,
                    'conviction' => $conviction,
                    'volatility' => $volatility,
                    'posture' => $posture,
                    'planet_aspects' => $aspects,
                    'moon_timings' => $moonTimings,
                    'stock_reports' => $stockReports,
                    'sector_summary' => $sectorSummary,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get planetary data for date
     */
    private function getPlanetaryData($date)
    {
        if (isset($this->ephemeris[$date])) {
            return $this->ephemeris[$date];
        }
        
        // Fallback calculation
        return $this->calculatePlanetaryPositions($date);
    }

    /**
     * Calculate planetary positions (simplified)
     */
    private function calculatePlanetaryPositions($date)
    {
        $timestamp = strtotime($date);
        $days = ($timestamp - strtotime('2025-01-01')) / 86400;
        
        return [
            'sun' => fmod(280 + ($days * 0.9856), 360),
            'moon' => fmod(120 + ($days * 13.176), 360),
            'mercury' => fmod(240 + ($days * 1.383), 360),
            'venus' => fmod(300 + ($days * 1.602), 360),
            'mars' => fmod(180 + ($days * 0.524), 360),
            'jupiter' => fmod(70 + ($days * 0.083), 360),
            'saturn' => fmod(350 + ($days * 0.034), 360),
            'uranus' => fmod(50 + ($days * 0.012), 360),
            'neptune' => fmod(355 + ($days * 0.006), 360),
            'pluto' => fmod(300 + ($days * 0.004), 360),
            'rahu' => fmod(30 - ($days * 0.053), 360),
            'ketu' => fmod(210 - ($days * 0.053), 360),
            'mercury_retro' => 0,
            'venus_retro' => 0,
            'mars_retro' => 0,
            'jupiter_retro' => 0,
            'saturn_retro' => 0,
        ];
    }

    /**
     * Detect planetary aspects
     */
    private function detectAspects($planets)
    {
        $aspects = [];
        $planetPairs = [
            ['Mars', 'Jupiter'], ['Sun', 'Jupiter'], ['Mercury', 'Jupiter'],
            ['Venus', 'Jupiter'], ['Mercury', 'Uranus'], ['Mars', 'Uranus'],
            ['Sun', 'Pluto'], ['Mercury', 'Pluto'], ['Saturn', 'Mars'],
            ['Venus', 'Saturn'], ['Sun', 'Saturn'], ['Moon', 'Mars'],
            ['Moon', 'Jupiter'], ['Moon', 'Saturn'],
        ];

        foreach ($planetPairs as $pair) {
            $p1 = strtolower($pair[0]);
            $p2 = strtolower($pair[1]);
            
            if (!isset($planets[$p1], $planets[$p2])) continue;
            
            foreach ($this->aspectDefinitions as $aspectType => $aspectData) {
                $angle = $this->calculateAngle($planets[$p1], $planets[$p2]);
                
                if (abs($angle - $aspectData['angle']) <= $aspectData['orb']) {
                    $impactKey = $pair[0] . '-' . $pair[1] . '-' . $aspectType;
                    $impact = $this->aspectImpacts[$impactKey] ?? 0;
                    
                    $aspects[] = [
                        'planets' => $pair[0] . ' ' . $aspectType . ' ' . $pair[1],
                        'type' => $aspectType,
                        'angle' => round($angle, 2),
                        'nature' => $aspectData['nature'],
                        'strength' => $aspectData['strength'],
                        'impact' => $impact,
                        'description' => $this->getAspectDescription($pair[0], $pair[1], $aspectType),
                    ];
                    break;
                }
            }
        }

        return $aspects;
    }

    /**
     * Calculate angle between planets
     */
    private function calculateAngle($deg1, $deg2)
    {
        $diff = abs($deg1 - $deg2);
        if ($diff > 180) {
            $diff = 360 - $diff;
        }
        return $diff;
    }

    /**
     * Get aspect description
     */
    private function getAspectDescription($p1, $p2, $type)
    {
        $descriptions = [
            'Mars-Jupiter-trine' => 'Aggressive momentum - Breakout energy strong',
            'Sun-Jupiter-trine' => 'Leadership & institutional support - Very bullish',
            'Mercury-Jupiter-trine' => 'Optimistic news flow - Good for IT/NBFC',
            'Venus-Jupiter-sextile' => 'Peak optimism - Luxury & consumption up',
            'Mercury-Uranus-opposition' => 'Unexpected news - Sharp volatility',
            'Mars-Uranus-square' => 'Explosive moves - High risk day',
            'Sun-Pluto-square' => 'Power struggle - Systemic stress',
            'Mercury-Pluto-square' => 'Dark narrative - Fake breakouts',
            'Saturn-Mars-square' => 'Resistance - Breakdown risk',
            'Moon-Mars-square' => 'Emotional selling spike',
            'Moon-Jupiter-trine' => 'Buying strength window',
            'Moon-Saturn-opposition' => 'Fear peak - Slowdown',
        ];
        
        $key = $p1 . '-' . $p2 . '-' . $type;
        return $descriptions[$key] ?? 'Minor influence';
    }

    /**
     * Calculate market metrics
     */
    private function calculateMarketMetrics($aspects, $planets)
    {
        $score = 0;
        $shock = 0;
        $heavy = 0;

        foreach ($aspects as $aspect) {
            $score += $aspect['impact'];
            
            if (strpos($aspect['planets'], 'Uranus') !== false) {
                $shock += 12;
            }
            if (strpos($aspect['planets'], 'Pluto') !== false || $aspect['type'] === 'square') {
                $heavy += 8;
            }
        }

        // Retrograde impact
        if (!empty($planets['jupiter_retro'])) $score -= 5;
        if (!empty($planets['mercury_retro'])) $shock += 8;

        $conviction = max(0, min(100, 50 + $score * 3));
        $volatility = max(0, min(100, 30 + $shock + $heavy * 0.8));

        // Determine posture
        $posture = 'range';
        if ($conviction >= 70 && $volatility <= 60) {
            $posture = 'risk_on';
        } elseif ($conviction >= 58 && $volatility <= 70) {
            $posture = 'mild_up';
        } elseif ($volatility >= 68 && $conviction <= 55) {
            $posture = 'fade';
        }

        return [round($conviction, 2), round($volatility, 2), $posture];
    }

    /**
     * Analyze Moon timings
     */
    private function analyzeMoonTimings($planets)
    {
        $moonDeg = $planets['moon'];
        $moonSign = $this->getZodiacSign($moonDeg);
        
        // Moon aspects create intraday timing
        $timings = [
            'early_session' => '09:15 - 10:30',
            'mid_session' => '11:00 - 13:00',
            'late_session' => '14:00 - 15:30',
        ];

        // Fire signs = aggressive moves
        $fireSigns = ['Aries', 'Leo', 'Sagittarius'];
        // Earth signs = steady trends
        $earthSigns = ['Taurus', 'Virgo', 'Capricorn'];

        if (in_array($moonSign, $fireSigns)) {
            $buyWindow = '09:20 - 09:45 (Sharp early dip)';
            $sellWindow = '14:30 - 15:10 (Late spike)';
            $character = 'Aggressive - Fast reversals';
        } elseif (in_array($moonSign, $earthSigns)) {
            $buyWindow = '10:00 - 11:00 (Gradual dip)';
            $sellWindow = '14:00 - 14:45 (Steady rise)';
            $character = 'Stable - Trending moves';
        } else {
            $buyWindow = '09:30 - 10:15';
            $sellWindow = '14:15 - 15:00';
            $character = 'Moderate volatility';
        }

        return [
            'moon_sign' => $moonSign,
            'character' => $character,
            'buy_low_window' => $buyWindow,
            'sell_high_window' => $sellWindow,
            'volatility_bursts' => ['09:15-09:30', '14:45-15:15'],
        ];
    }

    /**
     * Get zodiac sign from degree
     */
    private function getZodiacSign($deg)
    {
        $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
                  'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
        return $signs[(int)floor(fmod($deg, 360) / 30)];
    }

    /**
     * Generate stock-specific report
     */
    private function generateStockReport($symbol, $planets, $aspects, $conviction, $volatility, $posture, $moonTimings, $date)
    {
        $sensitivity = $this->stockSensitivity[$symbol] ?? ['sun' => 0.4, 'jupiter' => 0.3, 'mercury' => 0.3];
        $sector = $this->sectorMap[$symbol] ?? 'General';
        
        // Calculate stock-specific score
        $stockScore = 0;
        foreach ($sensitivity as $planet => $weight) {
            $planetScore = $this->getPlanetScore($planet, $planets, $aspects);
            $stockScore += $planetScore * $weight;
        }

        // Volume-based bias (REVERSED as per requirement)
        // If volume ratio > 2, it's strong bearish (reversed from typical bullish)
        $volumeRatio = $this->getVolumeRatio($symbol, $date);
        if ($volumeRatio > 2.0) {
            $volumeBias = 'Strong Bearish';
            $stockScore -= 15;
        } elseif ($volumeRatio > 1.5) {
            $volumeBias = 'Mild Bearish';
            $stockScore -= 8;
        } elseif ($volumeRatio < 0.5) {
            $volumeBias = 'Strong Bullish';
            $stockScore += 15;
        } elseif ($volumeRatio < 0.8) {
            $volumeBias = 'Mild Bullish';
            $stockScore += 8;
        } else {
            $volumeBias = 'Neutral';
        }

        // Determine bias
        $bias = $this->determineBias($stockScore, $sector, $posture);
        
        // Generate trade plan
        $tradePlan = $this->generateTradePlan($bias, $moonTimings, $volatility);
        
        // Get sector impact
        $sectorImpact = $this->getSectorImpact($sector, $aspects, $planets);
        
        // Get sensitive planets reasoning
        $planetReasoning = $this->getPlanetReasoning($sensitivity, $planets, $aspects);

        return [
            'symbol' => $symbol,
            'sector' => $sector,
            'bias' => $bias,
            'stock_score' => round($stockScore, 2),
            'volume_ratio' => round($volumeRatio, 2),
            'volume_bias' => $volumeBias,
            'conviction' => round($conviction, 2),
            'volatility' => round($volatility, 2),
            'sensitive_planets' => array_keys($sensitivity),
            'planet_reasoning' => $planetReasoning,
            'sector_impact' => $sectorImpact,
            'trade_plan' => $tradePlan,
            'buy_zone' => $this->calculateBuyZone($symbol, $date),
            'sell_zone' => $this->calculateSellZone($symbol, $date),
            'stop_loss' => $this->calculateStopLoss($bias),
            'target' => $this->calculateTarget($bias, $volatility),
        ];
    }

    /**
     * Get planet score based on aspects
     */
    private function getPlanetScore($planet, $planets, $aspects)
    {
        $score = 0;
        
        foreach ($aspects as $aspect) {
            if (stripos($aspect['planets'], ucfirst($planet)) !== false) {
                $score += $aspect['impact'];
            }
        }

        // Check if retrograde
        $retroKey = $planet . '_retro';
        if (isset($planets[$retroKey]) && $planets[$retroKey] == 1) {
            if ($planet === 'mercury') {
                $score -= 10; // Fake breakouts
            } elseif ($planet === 'jupiter') {
                $score -= 8; // Weak financials
            }
        }

        return $score;
    }

    /**
     * Get volume ratio (mock - should fetch from DB)
     */
    private function getVolumeRatio($symbol, $date)
    {
        // Mock data - in production, fetch from database
        // Compare today's volume vs 20-day average
        $mockRatios = [
            'APOLLOHOSP' => 1.2,
            'ASIANPAINT' => 0.8,
            'AXISBANK' => 2.3,
            'BAJAJ-AUTO' => 1.5,
            'BAJAJFINSV' => 1.8,
            'BAJFINANCE' => 2.1,
            'BALKRISIND' => 0.9,
            'BANKNIFTY' => 1.4,
            'BHARTIARTL' => 1.1,
            'BRITANNIA' => 0.7,
            'CIPLA' => 1.3,
            'CRUDEOIL' => 1.6,
            'DIVISLAB' => 1.0,
            'DRREDDY' => 0.6,
            'EICHERMOT' => 1.7,
            'GRASIM' => 2.5,
        ];
        
        return $mockRatios[$symbol] ?? 1.0;
    }

    /**
     * Determine bias
     */
    private function determineBias($score, $sector, $posture)
    {
        if ($posture === 'fade' && in_array($sector, ['Banks', 'NBFC'])) {
            if ($score > 10) return 'Mild Bearish';
            if ($score < -10) return 'Strong Bearish';
            return 'Bearish';
        }

        if ($score > 15) return 'Strong Bullish';
        if ($score > 5) return 'Mild Bullish';
        if ($score < -15) return 'Strong Bearish';
        if ($score < -5) return 'Mild Bearish';
        return 'Neutral';
    }

    /**
     * Generate trade plan
     */
    private function generateTradePlan($bias, $moonTimings, $volatility)
    {
        $plan = [];
        
        if (strpos($bias, 'Bullish') !== false) {
            $plan['action'] = 'BUY';
            $plan['timing'] = $moonTimings['buy_low_window'];
            $plan['strategy'] = $volatility > 50 ? 'Buy dips but book intraday' : 'Buy dips and hold';
            $plan['avoid'] = 'Chasing breakouts in early session';
        } elseif (strpos($bias, 'Bearish') !== false) {
            $plan['action'] = 'SELL/SHORT';
            $plan['timing'] = $moonTimings['sell_high_window'];
            $plan['strategy'] = 'Sell rallies, avoid bottom fishing';
            $plan['avoid'] = 'Catching falling knives';
        } else {
            $plan['action'] = 'RANGE/SCALP';
            $plan['timing'] = 'Both buy & sell windows';
            $plan['strategy'] = 'Mean reversion - small size';
            $plan['avoid'] = 'Directional bets';
        }

        return $plan;
    }

    /**
     * Get sector impact
     */
    private function getSectorImpact($sector, $aspects, $planets)
    {
        $impacts = [];
        
        foreach ($aspects as $aspect) {
            if ($sector === 'NBFC' || $sector === 'Banks') {
                if (strpos($aspect['planets'], 'Jupiter') !== false && $aspect['impact'] > 0) {
                    $impacts[] = 'Jupiter support boosts BFSI sentiment';
                }
                if (strpos($aspect['planets'], 'Pluto') !== false && $aspect['impact'] < 0) {
                    $impacts[] = 'Systemic stress - book profits on rallies';
                }
            }
            
            if ($sector === 'Pharma' || $sector === 'Healthcare') {
                if (strpos($aspect['planets'], 'Jupiter') !== false) {
                    $impacts[] = 'Healthcare optimism - steady bid';
                }
            }
            
            if ($sector === 'Telecom') {
                if (strpos($aspect['planets'], 'Uranus') !== false) {
                    $impacts[] = 'Tech volatility - wait for setup';
                }
            }
        }

        if (empty($impacts)) {
            $impacts[] = 'Follow general market flow';
        }

        return $impacts;
    }

    /**
     * Get planet reasoning
     */
    private function getPlanetReasoning($sensitivity, $planets, $aspects)
    {
        $reasoning = [];
        
        foreach ($sensitivity as $planet => $weight) {
            $planetName = ucfirst($planet);
            $sign = $this->getZodiacSign($planets[$planet]);
            
            $status = 'positioned in ' . $sign;
            
            foreach ($aspects as $aspect) {
                if (stripos($aspect['planets'], $planetName) !== false) {
                    if ($aspect['impact'] > 0) {
                        $status .= ' - Positive aspect (' . $aspect['type'] . ')';
                    } elseif ($aspect['impact'] < 0) {
                        $status .= ' - Challenging aspect (' . $aspect['type'] . ')';
                    }
                    break;
                }
            }
            
            $reasoning[] = $planetName . ': ' . $status;
        }

        return $reasoning;
    }

    /**
     * Calculate buy zone (mock)
     */
    private function calculateBuyZone($symbol, $date)
    {
        return 'CPR BC-TC / PDL zone (confirm with VWAP)';
    }

    /**
     * Calculate sell zone (mock)
     */
    private function calculateSellZone($symbol, $date)
    {
        return 'PDH / R1 zone (book into strength)';
    }

    /**
     * Calculate stop loss
     */
    private function calculateStopLoss($bias)
    {
        if (strpos($bias, 'Strong') !== false) {
            return '-1.5% to -2.0%';
        } elseif (strpos($bias, 'Mild') !== false) {
            return '-1.0% to -1.5%';
        }
        return '-0.8% to -1.2%';
    }

    /**
     * Calculate target
     */
    private function calculateTarget($bias, $volatility)
    {
        if (strpos($bias, 'Strong Bullish') !== false) {
            return $volatility > 50 ? '+2.5% to +4.0%' : '+1.5% to +3.0%';
        } elseif (strpos($bias, 'Mild Bullish') !== false) {
            return '+1.0% to +2.0%';
        } elseif (strpos($bias, 'Strong Bearish') !== false) {
            return '-2.0% to -3.5%';
        } elseif (strpos($bias, 'Mild Bearish') !== false) {
            return '-1.0% to -2.0%';
        }
        return '+0.5% to +1.5%';
    }

    /**
     * Generate sector summary
     */
    private function generateSectorSummary($stockReports, $aspects, $planets)
    {
        $sectorData = [];
        
        foreach ($stockReports as $report) {
            $sector = $report['sector'];
            if (!isset($sectorData[$sector])) {
                $sectorData[$sector] = [
                    'stocks' => [],
                    'avg_score' => 0,
                    'bullish_count' => 0,
                    'bearish_count' => 0,
                ];
            }
            
            $sectorData[$sector]['stocks'][] = $report['symbol'];
            $sectorData[$sector]['avg_score'] += $report['stock_score'];
            
            if (strpos($report['bias'], 'Bullish') !== false) {
                $sectorData[$sector]['bullish_count']++;
            } elseif (strpos($report['bias'], 'Bearish') !== false) {
                $sectorData[$sector]['bearish_count']++;
            }
        }

        // Calculate averages and determine sector bias
        foreach ($sectorData as $sector => &$data) {
            $stockCount = count($data['stocks']);
            $data['avg_score'] = round($data['avg_score'] / $stockCount, 2);
            
            if ($data['bullish_count'] > $data['bearish_count']) {
                $data['sector_bias'] = 'Bullish';
            } elseif ($data['bearish_count'] > $data['bullish_count']) {
                $data['sector_bias'] = 'Bearish';
            } else {
                $data['sector_bias'] = 'Mixed';
            }
        }

        // Sort by average score
        uasort($sectorData, function($a, $b) {
            return $b['avg_score'] <=> $a['avg_score'];
        });

        return $sectorData;
    }

    /**
     * Generate market overview
     */
    private function generateMarketOverview($conviction, $volatility, $posture, $aspects, $planets)
    {
        $overview = [];
        
        // Overall bias
        if ($posture === 'risk_on') {
            $overview['bias'] = 'Strong Bullish - Risk On Environment';
            $overview['strategy'] = 'Buy dips above VWAP/CPR; hold quality positions';
        } elseif ($posture === 'mild_up') {
            $overview['strategy'] = 'Selective longs; avoid chasing spikes';
            $overview['bias'] = 'Mild Bullish - Cautious Optimism';
        } elseif ($posture === 'fade') {
            $overview['bias'] = 'Bearish - Fade Rallies';
            $overview['strategy'] = 'Sell strength; avoid bottom fishing';
        } else {
            $overview['bias'] = 'Neutral - Range Bound';
            $overview['strategy'] = 'Mean reversion; smaller position sizes';
        }

        // Opening expectation
        if ($conviction > 65) {
            $overview['opening'] = 'Gap up expected (0.3% - 0.8%)';
        } elseif ($conviction < 45) {
            $overview['opening'] = 'Gap down expected (0.3% - 0.7%)';
        } else {
            $overview['opening'] = 'Flat to slightly positive';
        }

        // Volatility guidance
        if ($volatility > 70) {
            $overview['volatility_guidance'] = 'Extreme volatility - Scalp with tight stops';
        } elseif ($volatility > 50) {
            $overview['volatility_guidance'] = 'High volatility - Momentum trading';
        } else {
            $overview['volatility_guidance'] = 'Low volatility - Range trading';
        }

        // Key planetary drivers
        $drivers = [];
        foreach ($aspects as $aspect) {
            if (abs($aspect['impact']) > 5) {
                $drivers[] = $aspect['planets'] . ' (' . $aspect['description'] . ')';
            }
        }
        $overview['key_drivers'] = $drivers;

        // Retrograde warnings
        $warnings = [];
        if (!empty($planets['mercury_retro'])) {
            $warnings[] = 'Mercury Retrograde: Expect fake breakouts and failed moves';
        }
        if (!empty($planets['jupiter_retro'])) {
            $warnings[] = 'Jupiter Retrograde: Banking sector may underperform';
        }
        $overview['warnings'] = $warnings;

        // Best trading times
        $overview['best_entry'] = '09:30 - 10:30 (After initial volatility)';
        $overview['best_exit'] = '14:30 - 15:15 (Book profits before close)';

        return $overview;
    }

    public function generateWeeklyForecast(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->next(Carbon::MONDAY)->format('Y-m-d'));
        
        try {
            $weekData = [];
            $allSymbols = SymbolList::distinct()->pluck('symbol')->toArray();
            
            // Generate 7 days forecast
            for ($i = 0; $i < 7; $i++) {
                $currentDate = Carbon::parse($startDate)->addDays($i);
                $dateStr = $currentDate->format('Y-m-d');
                $dayName = $currentDate->format('l');
                
                // Skip weekends for trading analysis (but show for learning)
                $isTradingDay = !in_array($dayName, ['Saturday', 'Sunday']);
                
                // Get planetary positions
                $planets = $this->getPlanetaryPositions($dateStr);
                
                // Detect aspects
                $aspects = $this->detectPlanetaryAspects($planets);
                
                // Calculate daily energies
                $energies = $this->calculateDailyEnergies($planets, $aspects);
                
                // Market predictions
                $marketView = $this->predictMarketView($energies, $aspects, $planets);
                
                // Timing analysis
                $timings = $this->calculateReversalTimings($planets, $currentDate);
                
                // Stock analysis
                $stockPredictions = $this->analyzeStocks($allSymbols, $planets, $energies, $aspects);
                
                // Sector analysis
                $sectorOutlook = $this->analyzeSectors($planets, $energies);
                
                $weekData[] = [
                    'date' => $dateStr,
                    'day' => $dayName,
                    'is_trading_day' => $isTradingDay,
                    'planets' => $planets,
                    'aspects' => $aspects,
                    'energies' => $energies,
                    'market_view' => $marketView,
                    'timings' => $timings,
                    'bullish_stocks' => $stockPredictions['bullish'],
                    'bearish_stocks' => $stockPredictions['bearish'],
                    'sector_outlook' => $sectorOutlook,
                ];
            }
            
            // Weekly summary
            $weeklySummary = $this->generateWeeklySummary($weekData);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'week_start' => $startDate,
                    'week_end' => Carbon::parse($startDate)->addDays(6)->format('Y-m-d'),
                    'daily_forecasts' => $weekData,
                    'weekly_summary' => $weeklySummary
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating forecast: ' . $e->getMessage()
            ], 500);
        }
    }

        /**
     * Get planetary positions for a date
     */
    private function getPlanetaryPositions($dateStr)
    {
        if (isset($this->ephemeris[$dateStr])) {
            $positions = $this->ephemeris[$dateStr];
        } else {
            // Calculate using simple ephemeris formulas
            $positions = $this->calculatePlanetaryPositions($dateStr);
        }
        
        // Add zodiac signs
        $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 
                  'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
        
        foreach ($positions as $planet => $degree) {
            $signIndex = floor($degree / 30);
            $positions[$planet . '_sign'] = $signs[$signIndex];
            $positions[$planet . '_degree'] = round($degree % 30, 2);
        }
        
        return $positions;
    }

      /**
     * Analyze Rahu-Ketu axis
     */
    private function analyzeRahuKetuAxis($planets)
    {
        // Rahu brings unexpected events, Ketu brings spirituality/detachment
        $rahuSign = $planets['rahu_sign'];
        $ketuSign = $planets['ketu_sign'];
        
        $rahuEffects = [
            'Aries' => 'Aggressive speculation in new ventures',
            'Taurus' => 'Volatile commodity prices, banking stress',
            'Gemini' => 'Media hype, tech stock volatility',
            'Cancer' => 'Emotional trading, FMCG fluctuations',
            'Leo' => 'Government policy surprises, PSU moves',
            'Virgo' => 'Healthcare sector focus, analytical chaos',
        ];
        
        return [
            'planets' => 'Rahu-Ketu Axis',
            'aspect' => 'Nodal Influence',
            'angle' => 180,
            'strength' => 7,
            'nature' => 'unpredictable',
            'market_impact' => ($rahuEffects[$rahuSign] ?? 'Unexpected sector moves') . ' | Ketu in ' . $ketuSign . ' suggests detachment from that sector',
        ];
    }

    
    /**
     * Calculate daily energies
     */
    private function calculateDailyEnergies($planets, $aspects)
    {
        $bullishScore = 0;
        $bearishScore = 0;
        $volatilityScore = 0;
        
        // Planetary strength analysis
        $strongPlanets = $this->getStrongPlanets($planets);
        $weakPlanets = $this->getWeakPlanets($planets);
        
        // Aspect influence
        foreach ($aspects as $aspect) {
            if ($aspect['nature'] === 'harmonious' || $aspect['nature'] === 'supportive') {
                $bullishScore += $aspect['strength'];
            } elseif ($aspect['nature'] === 'tense' || $aspect['nature'] === 'challenging') {
                $bearishScore += $aspect['strength'];
                $volatilityScore += $aspect['strength'] * 0.8;
            } elseif ($aspect['nature'] === 'powerful') {
                $volatilityScore += $aspect['strength'];
            }
        }
        
        // Strong benefic planets boost bullish score
        if (in_array('jupiter', $strongPlanets)) $bullishScore += 15;
        if (in_array('venus', $strongPlanets)) $bullishScore += 10;
        if (in_array('mercury', $strongPlanets)) $bullishScore += 8;
        
        // Strong malefic planets boost bearish score
        if (in_array('saturn', $strongPlanets)) $bearishScore += 12;
        if (in_array('mars', $strongPlanets)) $volatilityScore += 15;
        if (in_array('rahu', $strongPlanets)) $volatilityScore += 10;
        
        $totalScore = $bullishScore + $bearishScore;
        $bullishPercentage = $totalScore > 0 ? round(($bullishScore / $totalScore) * 100) : 50;
        
        return [
            'bullish_score' => round($bullishScore, 2),
            'bearish_score' => round($bearishScore, 2),
            'volatility_score' => round($volatilityScore, 2),
            'bullish_percentage' => $bullishPercentage,
            'strong_planets' => $strongPlanets,
            'weak_planets' => $weakPlanets,
            'dominant_energy' => $bullishScore > $bearishScore ? 'Bullish' : ($bearishScore > $bullishScore ? 'Bearish' : 'Neutral'),
        ];
    }

     /**
     * Get strong planets based on sign position
     */
    private function getStrongPlanets($planets)
    {
        $strong = [];
        
        // Exaltation signs
        $exaltations = [
            'sun' => 'Aries', 'moon' => 'Taurus', 'mercury' => 'Virgo',
            'venus' => 'Pisces', 'mars' => 'Capricorn', 'jupiter' => 'Cancer',
            'saturn' => 'Libra'
        ];
        
        foreach ($exaltations as $planet => $sign) {
            if ($planets[$planet . '_sign'] === $sign) {
                $strong[] = $planet;
            }
        }
        
        return $strong;
    }

    /**
     * Detect planetary aspects (Critical angles)
     */
    private function detectPlanetaryAspects($planets)
    {
        $aspects = [];
        $planetList = ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter', 'saturn'];
        
        $aspectDefinitions = [
            ['name' => 'Conjunction', 'angle' => 0, 'orb' => 8, 'strength' => 10, 'nature' => 'powerful'],
            ['name' => 'Opposition', 'angle' => 180, 'orb' => 8, 'strength' => 9, 'nature' => 'tense'],
            ['name' => 'Trine', 'angle' => 120, 'orb' => 8, 'strength' => 8, 'nature' => 'harmonious'],
            ['name' => 'Square', 'angle' => 90, 'orb' => 7, 'strength' => 8, 'nature' => 'challenging'],
            ['name' => 'Sextile', 'angle' => 60, 'orb' => 6, 'strength' => 6, 'nature' => 'supportive'],
        ];
        
        for ($i = 0; $i < count($planetList); $i++) {
            for ($j = $i + 1; $j < count($planetList); $j++) {
                $planet1 = $planetList[$i];
                $planet2 = $planetList[$j];
                
                $angle = $this->calculateAngleBetweenPlanets($planets[$planet1], $planets[$planet2]);
                
                foreach ($aspectDefinitions as $aspectDef) {
                    if (abs($angle - $aspectDef['angle']) <= $aspectDef['orb']) {
                        $aspects[] = [
                            'planets' => ucfirst($planet1) . '-' . ucfirst($planet2),
                            'aspect' => $aspectDef['name'],
                            'angle' => round($angle, 2),
                            'strength' => $aspectDef['strength'],
                            'nature' => $aspectDef['nature'],
                            'market_impact' => $this->getAspectMarketImpact($planet1, $planet2, $aspectDef['name']),
                        ];
                        break;
                    }
                }
            }
        }
        
        // Add Rahu-Ketu axis aspects
        $rahuKetu = $this->analyzeRahuKetuAxis($planets);
        if ($rahuKetu) {
            $aspects[] = $rahuKetu;
        }
        
        return $aspects;
    }

    /**
     * Calculate angle between two planets
     */
    private function calculateAngleBetweenPlanets($deg1, $deg2)
    {
        $diff = abs($deg1 - $deg2);
        if ($diff > 180) {
            $diff = 360 - $diff;
        }
        return $diff;
    }

    /**
     * Get market impact of planetary aspect
     */
    private function getAspectMarketImpact($planet1, $planet2, $aspect)
    {
        $impacts = [
            'sun-jupiter-Trine' => 'Extremely bullish - institutional buying, govt. support',
            'sun-jupiter-Conjunction' => 'Strong rally expected - leadership shines',
            'sun-saturn-Square' => 'Resistance from authorities - regulatory concerns',
            'sun-saturn-Opposition' => 'Power struggle - bearish for government stocks',
            
            'mars-jupiter-Trine' => 'Aggressive momentum - breakout likely',
            'mars-jupiter-Conjunction' => 'Explosive moves - high volume day',
            'mars-saturn-Square' => 'Conflict - volatile corrections',
            'mars-saturn-Opposition' => 'Breakdown - technical stops triggered',
            
            'mercury-venus-Conjunction' => 'Positive news flow - good for IT/Retail',
            'mercury-mars-Square' => 'Unexpected news - sharp intraday swings',
            'mercury-jupiter-Trine' => 'Optimistic sentiment - buying on dips',
            
            'venus-jupiter-Trine' => 'Peak optimism - luxury/consumption stocks up',
            'venus-jupiter-Conjunction' => 'Excessive bullishness - watch for top',
            'venus-saturn-Square' => 'Value concerns - profit booking in expensive stocks',
            
            'jupiter-saturn-Square' => 'Structural shifts - sector rotation',
            'jupiter-saturn-Trine' => 'Stable growth - balanced market',
            
            'moon-mars-Square' => 'Emotional volatility - retail panic/euphoria',
            'moon-saturn-Opposition' => 'Fear dominates - safe haven buying',
            'moon-jupiter-Trine' => 'Positive mood - small cap strength',
        ];
        
        $key = $planet1 . '-' . $planet2 . '-' . $aspect;
        return $impacts[$key] ?? 'Minor influence on market sentiment';
    }

    
     private function getStockPlanetMapping($symbol)
    {
        $mappings = [
            // IT & Tech - Mercury dominant
            'INFY' => ['mercury' => 0.7, 'jupiter' => 0.3],
            'TCS' => ['mercury' => 0.7, 'jupiter' => 0.3],
            'WIPRO' => ['mercury' => 0.65, 'jupiter' => 0.35],
            'TECHM' => ['mercury' => 0.7, 'jupiter' => 0.3],
            'HCLTECH' => ['mercury' => 0.7, 'jupiter' => 0.3],
            
            // Banks - Jupiter dominant
            'HDFCBANK' => ['jupiter' => 0.6, 'venus' => 0.4],
            'ICICIBANK' => ['jupiter' => 0.6, 'venus' => 0.4],
            'SBIN' => ['jupiter' => 0.7, 'saturn' => 0.3],
            'AXISBANK' => ['jupiter' => 0.6, 'venus' => 0.4],
            'KOTAKBANK' => ['jupiter' => 0.6, 'venus' => 0.4],
            
            // Energy - Sun dominant
            'RELIANCE' => ['sun' => 0.5, 'jupiter' => 0.3, 'mars' => 0.2],
            'ONGC' => ['sun' => 0.6, 'saturn' => 0.4],
            'IOC' => ['sun' => 0.6, 'saturn' => 0.4],
            'BPCL' => ['sun' => 0.6, 'mars' => 0.4],
            'POWERGRID' => ['sun' => 0.7, 'saturn' => 0.3],
            
            // Auto - Mars dominant
            'TATAMOTORS' => ['mars' => 0.6, 'mercury' => 0.4],
            'M&M' => ['mars' => 0.6, 'jupiter' => 0.4],
            'MARUTI' => ['mars' => 0.6, 'venus' => 0.4],
            'BAJAJ-AUTO' => ['mars' => 0.6, 'jupiter' => 0.4],
            
            // Pharma - Sun & Jupiter
            'SUNPHARMA' => ['sun' => 0.5, 'jupiter' => 0.5],
            'DRREDDY' => ['sun' => 0.5, 'jupiter' => 0.5],
            'CIPLA' => ['sun' => 0.5, 'ketu' => 0.5],
            'DIVISLAB' => ['sun' => 0.5, 'jupiter' => 0.5],
            
            // FMCG - Moon & Venus
            'HINDUNILVR' => ['moon' => 0.5, 'venus' => 0.5],
            'ITC' => ['moon' => 0.4, 'venus' => 0.3, 'rahu' => 0.3],
            'NESTLEIND' => ['moon' => 0.6, 'venus' => 0.4],
            'BRITANNIA' => ['moon' => 0.7, 'venus' => 0.3],
            
            // Metals & Mining - Saturn & Mars
            'TATASTEEL' => ['saturn' => 0.5, 'mars' => 0.5],
            'HINDALCO' => ['saturn' => 0.6, 'mars' => 0.4],
            'JSWSTEEL' => ['saturn' => 0.5, 'mars' => 0.5],
            'COALINDIA' => ['saturn' => 0.7, 'sun' => 0.3],
            
            // Cement - Saturn
            'ULTRACEMCO' => ['saturn' => 0.7, 'mars' => 0.3],
            'GRASIM' => ['saturn' => 0.6, 'jupiter' => 0.4],
            'SHREECEM' => ['saturn' => 0.7, 'mars' => 0.3],
            
            // Real Estate - Mars & Saturn
            'DLF' => ['mars' => 0.5, 'saturn' => 0.5],
            'GODREJPROP' => ['mars' => 0.5, 'venus' => 0.5],
            
            // Telecom - Mercury
            'BHARTIARTL' => ['mercury' => 0.7, 'jupiter' => 0.3],
            
            // Indices
            'NIFTY' => ['sun' => 0.3, 'jupiter' => 0.3, 'mars' => 0.2, 'mercury' => 0.2],
            'BANKNIFTY' => ['jupiter' => 0.6, 'venus' => 0.4],
        ];

        return $mappings[$symbol] ?? ['sun' => 0.4, 'jupiter' => 0.3, 'mercury' => 0.3];
    }

    /**
     * Get weak planets (debilitated)
     */
    private function getWeakPlanets($planets)
    {
        $weak = [];
        
        // Debilitation signs
        $debilitations = [
            'sun' => 'Libra', 'moon' => 'Scorpio', 'mercury' => 'Pisces',
            'venus' => 'Virgo', 'mars' => 'Cancer', 'jupiter' => 'Capricorn',
            'saturn' => 'Aries'
        ];
        
        foreach ($debilitations as $planet => $sign) {
            if ($planets[$planet . '_sign'] === $sign) {
                $weak[] = $planet;
            }
        }
        
        return $weak;
    }

    /**
     * Predict market view for the day
     */
    private function predictMarketView($energies, $aspects, $planets)
    {
        $view = [];
        
        // Opening prediction
        if ($energies['bullish_score'] > $energies['bearish_score'] + 10) {
            $view['opening'] = 'Gap Up Expected';
            $view['opening_range'] = '0.3% - 0.8% higher';
        } elseif ($energies['bearish_score'] > $energies['bullish_score'] + 10) {
            $view['opening'] = 'Gap Down Expected';
            $view['opening_range'] = '0.3% - 0.7% lower';
        } else {
            $view['opening'] = 'Flat to Slightly Positive';
            $view['opening_range'] = '-0.1% to +0.2%';
        }
        
        // Intraday trend
        if ($energies['volatility_score'] > 40) {
            $view['intraday_trend'] = 'Highly Volatile - Expect sharp swings';
            $view['trading_style'] = 'Scalping with tight stops';
        } elseif ($energies['volatility_score'] > 25) {
            $view['intraday_trend'] = 'Moderate volatility - Trending moves possible';
            $view['trading_style'] = 'Momentum trading';
        } else {
            $view['intraday_trend'] = 'Low volatility - Range-bound';
            $view['trading_style'] = 'Range trading / Theta strategies';
        }
        
        // Recovery/Selloff potential
        $recoveryPotential = 0;
        $selloffRisk = 0;
        
        foreach ($aspects as $aspect) {
            if (strpos($aspect['planets'], 'Jupiter') !== false && $aspect['nature'] === 'harmonious') {
                $recoveryPotential += 20;
            }
            if (strpos($aspect['planets'], 'Saturn') !== false && $aspect['nature'] === 'tense') {
                $selloffRisk += 15;
            }
            if (strpos($aspect['planets'], 'Mars') !== false && $aspect['nature'] === 'challenging') {
                $selloffRisk += 12;
            }
        }
        
        if ($recoveryPotential > 25) {
            $view['recovery_potential'] = 'High - Dips will be bought aggressively';
        } elseif ($recoveryPotential > 15) {
            $view['recovery_potential'] = 'Moderate - Selective buying in dips';
        } else {
            $view['recovery_potential'] = 'Low - Avoid catching falling knives';
        }
        
        if ($selloffRisk > 25) {
            $view['selloff_risk'] = 'High - Book profits early, avoid longs';
        } elseif ($selloffRisk > 15) {
            $view['selloff_risk'] = 'Moderate - Trail stops, book partials';
        } else {
            $view['selloff_risk'] = 'Low - Safe to hold quality positions';
        }
        
        // Overall bias
        if ($energies['bullish_percentage'] > 65) {
            $view['overall_bias'] = 'Strong Bullish - Buy dips strategy';
            $view['conviction'] = 'High';
        } elseif ($energies['bullish_percentage'] > 55) {
            $view['overall_bias'] = 'Mildly Bullish - Cautious longs';
            $view['conviction'] = 'Medium';
        } elseif ($energies['bullish_percentage'] > 45) {
            $view['overall_bias'] = 'Neutral - Wait for clarity';
            $view['conviction'] = 'Low';
        } elseif ($energies['bullish_percentage'] > 35) {
            $view['overall_bias'] = 'Mildly Bearish - Selective shorts';
            $view['conviction'] = 'Medium';
        } else {
            $view['overall_bias'] = 'Strong Bearish - Avoid longs, focus shorts';
            $view['conviction'] = 'High';
        }
        
        // Key levels (psychological based on moon position)
        $moonDegree = $planets['moon'];
        $view['support_level'] = 'Watch ' . floor($moonDegree / 10) . '% below current levels';
        $view['resistance_level'] = 'Resistance at ' . floor(($moonDegree + 180) / 10) . '% above';
        
        // Trading recommendation
        if ($energies['dominant_energy'] === 'Bullish' && $energies['volatility_score'] < 30) {
            $view['recommendation'] = 'Buy quality stocks on dips. Hold overnight.';
        } elseif ($energies['dominant_energy'] === 'Bullish' && $energies['volatility_score'] >= 30) {
            $view['recommendation'] = 'Buy momentum but book profits by EOD. High volatility.';
        } elseif ($energies['dominant_energy'] === 'Bearish' && $selloffRisk > 20) {
            $view['recommendation'] = 'Short on rallies. Avoid overnight longs. Book profits early.';
        } elseif ($energies['dominant_energy'] === 'Bearish') {
            $view['recommendation'] = 'Stay defensive. Cash is a position. Small shorts only.';
        } else {
            $view['recommendation'] = 'Stay flat. Low conviction day. Wait for better setups.';
        }
        
        return $view;
    }

    /**
     * Calculate reversal timings based on planetary hours
     */
    private function calculateReversalTimings($planets, $date)
    {
        $timings = [];
        
        // Calculate planetary hours (Hora system)
        $dayOfWeek = $date->dayOfWeek; // 0=Sunday, 1=Monday...
        $planetaryDayRuler = ['sun', 'moon', 'mars', 'mercury', 'jupiter', 'venus', 'saturn'][$dayOfWeek];
        
        // Critical reversal times based on planetary transits
        $criticalHours = [
            '09:15-09:45' => 'Opening volatility - Initial direction setter',
            '10:30-11:00' => 'First reversal window - Re-evaluation zone',
            '11:30-12:00' => 'Mid-morning pivot - Institutional activity peaks',
            '13:00-13:30' => 'Lunch time reversal - Low volume traps',
            '14:00-14:30' => 'Post-lunch momentum - Direction confirmation',
            '15:00-15:30' => 'Final hour setup - Closing positioning begins',
        ];
        
        // Enhanced based on strong aspects
        $moonSign = $planets['moon_sign'];
        $marsSign = $planets['mars_sign'];
        
        // Fire signs (Aries, Leo, Sagittarius) = aggressive reversals
        $fireSigns = ['Aries', 'Leo', 'Sagittarius'];
        // Earth signs (Taurus, Virgo, Capricorn) = gradual moves
        $earthSigns = ['Taurus', 'Virgo', 'Capricorn'];
        
        if (in_array($moonSign, $fireSigns)) {
            $timings['high_volatility_windows'] = ['09:15-09:30', '14:45-15:15'];
            $timings['reversal_probability'] = 'High - Moon in fire sign';
        } elseif (in_array($moonSign, $earthSigns)) {
            $timings['high_volatility_windows'] = ['09:30-10:00', '15:00-15:20'];
            $timings['reversal_probability'] = 'Low - Moon in earth sign (steady trends)';
        } else {
            $timings['high_volatility_windows'] = ['09:20-09:40', '14:30-15:00'];
            $timings['reversal_probability'] = 'Moderate';
        }
        
        $timings['critical_hours'] = $criticalHours;
        $timings['day_ruler'] = ucfirst($planetaryDayRuler);
        $timings['best_entry_time'] = $this->getBestEntryTime($planets, $planetaryDayRuler);
        $timings['best_exit_time'] = '15:00-15:15 (Book profits before closing chaos)';
        
        return $timings;
    }

    /**
     * Get best entry time based on planetary strength
     */
    private function getBestEntryTime($planets, $dayRuler)
    {
        // If day ruler is benefic (Jupiter, Venus, Mercury) - enter early
        if (in_array($dayRuler, ['jupiter', 'venus', 'mercury'])) {
            return '09:20-09:40 (Day ruler is benefic - early momentum)';
        }
        
        // If day ruler is malefic (Saturn, Mars) - wait for stability
        if (in_array($dayRuler, ['saturn', 'mars'])) {
            return '10:00-10:30 (Day ruler is malefic - wait for clarity)';
        }
        
        // Sun/Moon days - moderate approach
        return '09:30-10:00 (Balanced entry after initial volatility)';
    }

    /**
     * Analyze stocks based on planetary influences
     */
    private function analyzeStocks($symbols, $planets, $energies, $aspects)
    {
        $stockScores = [];
        
        foreach ($symbols as $symbol) {
            $planetMapping = $this->getStockPlanetMapping($symbol);
            $score = 0;
            
            // Calculate score based on ruling planets
            foreach ($planetMapping as $planet => $weight) {
                if (in_array($planet, $energies['strong_planets'])) {
                    $score += (30 * $weight);
                }
                if (in_array($planet, $energies['weak_planets'])) {
                    $score -= (20 * $weight);
                }
                
                // Check aspects involving this planet
                foreach ($aspects as $aspect) {
                    if (strpos(strtolower($aspect['planets']), $planet) !== false) {
                        if ($aspect['nature'] === 'harmonious') {
                            $score += (10 * $weight);
                        } elseif ($aspect['nature'] === 'tense') {
                            $score -= (8 * $weight);
                        }
                    }
                }
            }
            
            $stockScores[$symbol] = round($score, 2);
        }
        
        arsort($stockScores);
        
        // Get top 10 bullish and bearish
        $bullish = array_slice(array_filter($stockScores, fn($s) => $s > 5), 0, 10, true);
        $bearish = array_slice(array_filter($stockScores, fn($s) => $s < -5), 0, 10, true);
        
        // Add reasoning
        $bullishDetailed = [];
        foreach ($bullish as $symbol => $score) {
            $bullishDetailed[] = [
                'symbol' => $symbol,
                'score' => $score,
                'reason' => $this->getStockReason($symbol, $planets, $energies, true),
                'target' => '+' . (1 + ($score / 50)) . '% to ' . (2 + ($score / 30)) . '%',
                'stop_loss' => '-0.8% to -1.2%',
            ];
        }
        
        $bearishDetailed = [];
        foreach ($bearish as $symbol => $score) {
            $bearishDetailed[] = [
                'symbol' => $symbol,
                'score' => $score,
                'reason' => $this->getStockReason($symbol, $planets, $energies, false),
                'target' => abs($score / 40) . '% to ' . abs($score / 25) . '% down',
                'stop_loss' => '+0.8% to +1.2%',
            ];
        }
        
        return [
            'bullish' => $bullishDetailed,
            'bearish' => $bearishDetailed,
        ];
    }

    /**
     * Get reasoning for stock prediction
     */
    private function getStockReason($symbol, $planets, $energies, $isBullish)
    {
        $planetMapping = $this->getStockPlanetMapping($symbol);
        $dominantPlanet = array_key_first($planetMapping);
        $sign = $planets[$dominantPlanet . '_sign'];
        
        if ($isBullish) {
            if (in_array($dominantPlanet, $energies['strong_planets'])) {
                return "Ruling planet {$dominantPlanet} is strong in {$sign}. Favorable planetary alignment.";
            }
            return "Positive aspects supporting {$dominantPlanet}. Technical setup aligning with planetary energy.";
        } else {
            if (in_array($dominantPlanet, $energies['weak_planets'])) {
                return "Ruling planet {$dominantPlanet} is weak in {$sign}. Challenging planetary position.";
            }
            return "Adverse aspects affecting {$dominantPlanet}. Planetary resistance zones active.";
        }
    }

    /**
     * Analyze sectors
     */
    private function analyzeSectors($planets, $energies)
    {
        $sectorScores = [];
        
        foreach ($this->sectorRulership as $planet => $sectors) {
            $planetScore = 0;
            
            if (in_array($planet, $energies['strong_planets'])) {
                $planetScore = 8;
            } elseif (in_array($planet, $energies['weak_planets'])) {
                $planetScore = -6;
            } else {
                $planetScore = 3;
            }
            
            foreach ($sectors as $sector) {
                if (!isset($sectorScores[$sector])) {
                    $sectorScores[$sector] = 0;
                }
                $sectorScores[$sector] += $planetScore;
            }
        }
        
        arsort($sectorScores);
        
        $topSectors = array_slice($sectorScores, 0, 5, true);
        $weakSectors = array_slice(array_reverse($sectorScores, true), 0, 5, true);
        
        return [
            'top_sectors' => $topSectors,
            'weak_sectors' => $weakSectors,
        ];
    }

    /**
     * Generate weekly summary
     */
    private function generateWeeklySummary($weekData)
    {
        $totalBullishDays = 0;
        $totalBearishDays = 0;
        $highVolDays = 0;
        $bestTradingDay = null;
        $worstTradingDay = null;
        $maxBullishScore = -999;
        $maxBearishScore = 999;
        
        foreach ($weekData as $day) {
            if (!$day['is_trading_day']) continue;
            
            if ($day['energies']['dominant_energy'] === 'Bullish') {
                $totalBullishDays++;
            } elseif ($day['energies']['dominant_energy'] === 'Bearish') {
                $totalBearishDays++;
            }
            
            if ($day['energies']['volatility_score'] > 35) {
                $highVolDays++;
            }
            
            if ($day['energies']['bullish_score'] > $maxBullishScore) {
                $maxBullishScore = $day['energies']['bullish_score'];
                $bestTradingDay = $day;
            }
            
            if ($day['energies']['bearish_score'] > abs($maxBearishScore)) {
                $maxBearishScore = $day['energies']['bearish_score'];
                $worstTradingDay = $day;
            }
        }
        
        // Collect all bullish stocks across week
        $weeklyBullishStocks = [];
        $weeklyBearishStocks = [];
        
        foreach ($weekData as $day) {
            foreach ($day['bullish_stocks'] as $stock) {
                $symbol = $stock['symbol'];
                if (!isset($weeklyBullishStocks[$symbol])) {
                    $weeklyBullishStocks[$symbol] = 0;
                }
                $weeklyBullishStocks[$symbol]++;
            }
            
            foreach ($day['bearish_stocks'] as $stock) {
                $symbol = $stock['symbol'];
                if (!isset($weeklyBearishStocks[$symbol])) {
                    $weeklyBearishStocks[$symbol] = 0;
                }
                $weeklyBearishStocks[$symbol]++;
            }
        }
        
        arsort($weeklyBullishStocks);
        arsort($weeklyBearishStocks);
        
        $consistentBullish = array_slice($weeklyBullishStocks, 0, 8, true);
        $consistentBearish = array_slice($weeklyBearishStocks, 0, 8, true);
        
        // Week bias
        if ($totalBullishDays > $totalBearishDays + 1) {
            $weekBias = 'Bullish Week - Accumulate quality stocks';
        } elseif ($totalBearishDays > $totalBullishDays + 1) {
            $weekBias = 'Bearish Week - Stay defensive, short on rallies';
        } else {
            $weekBias = 'Mixed Week - Stock specific moves';
        }
        
        return [
            'week_bias' => $weekBias,
            'bullish_days' => $totalBullishDays,
            'bearish_days' => $totalBearishDays,
            'high_volatility_days' => $highVolDays,
            'best_trading_day' => $bestTradingDay ? [
                'date' => $bestTradingDay['date'],
                'day' => $bestTradingDay['day'],
                'reason' => 'Highest bullish score - ' . $bestTradingDay['energies']['bullish_score']
            ] : null,
            'riskiest_day' => $worstTradingDay ? [
                'date' => $worstTradingDay['date'],
                'day' => $worstTradingDay['day'],
                'reason' => 'Highest bearish score - Reduce exposure'
            ] : null,
            'consistent_bullish_stocks' => $consistentBullish,
            'consistent_bearish_stocks' => $consistentBearish,
            'trading_strategy' => $this->getWeeklyTradingStrategy($totalBullishDays, $totalBearishDays, $highVolDays),
        ];
    }

    /**
     * Get weekly trading strategy
     */
    private function getWeeklyTradingStrategy($bullishDays, $bearishDays, $highVolDays)
    {
        if ($bullishDays >= 3 && $highVolDays <= 1) {
            return 'Swing trading week - Build positions early in week, hold till Friday. Low risk environment.';
        } elseif ($bullishDays >= 3 && $highVolDays > 1) {
            return 'Momentum trading week - Buy dips intraday but book profits daily. Volatile but upward bias.';
        } elseif ($bearishDays >= 3) {
            return 'Short selling week - Aggressive shorts on rallies. Avoid bottom fishing. Exit by Thursday.';
        } elseif ($highVolDays >= 3) {
            return 'Scalping week - High volatility across days. Trade small, take quick profits. No overnight positions.';
        } else {
            return 'Selective trading week - Pick individual setups. No broad directional bias. Focus on stock-specific catalysts.';
        }
    }
}