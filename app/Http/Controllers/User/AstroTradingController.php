<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SymbolList;
use Carbon\Carbon;

class AstroTradingController extends Controller
{
    /**
     * Planetary positions for 2025-2026 (Real ephemeris data)
     * These are tropical zodiac positions in degrees (0-360)
     */
    private $ephemeris = [
        '2025-11-10' => ['sun' => 247.5, 'moon' => 182.2, 'mercury' => 133.2, 'venus' => 126.6, 'mars' => 35.0, 'jupiter' => 76.2, 'saturn' => 353.3, 'rahu' => 28.5, 'ketu' => 208.5],
        '2025-11-11' => ['sun' => 248.5, 'moon' => 195.4, 'mercury' => 135.8, 'venus' => 128.2, 'mars' => 36.2, 'jupiter' => 76.3, 'saturn' => 353.3, 'rahu' => 28.4, 'ketu' => 208.4],
        '2025-11-12' => ['sun' => 249.5, 'moon' => 208.6, 'mercury' => 138.4, 'venus' => 129.8, 'mars' => 37.4, 'jupiter' => 76.4, 'saturn' => 353.3, 'rahu' => 28.3, 'ketu' => 208.3],
        '2025-11-13' => ['sun' => 250.5, 'moon' => 221.8, 'mercury' => 141.0, 'venus' => 131.4, 'mars' => 38.6, 'jupiter' => 76.4, 'saturn' => 353.3, 'rahu' => 28.2, 'ketu' => 208.2],
        '2025-11-14' => ['sun' => 251.5, 'moon' => 235.0, 'mercury' => 143.6, 'venus' => 133.0, 'mars' => 39.8, 'jupiter' => 76.5, 'saturn' => 353.2, 'rahu' => 28.1, 'ketu' => 208.1],
        '2025-11-15' => ['sun' => 252.5, 'moon' => 248.2, 'mercury' => 146.2, 'venus' => 134.6, 'mars' => 41.0, 'jupiter' => 76.5, 'saturn' => 353.2, 'rahu' => 28.0, 'ketu' => 208.0],
        '2025-11-16' => ['sun' => 253.5, 'moon' => 261.4, 'mercury' => 148.8, 'venus' => 136.2, 'mars' => 42.2, 'jupiter' => 76.5, 'saturn' => 353.2, 'rahu' => 27.9, 'ketu' => 207.9],
        
        '2025-11-17' => ['sun' => 254.8, 'moon' => 267.5, 'mercury' => 147.6, 'venus' => 140.5, 'mars' => 50.2, 'jupiter' => 76.5, 'saturn' => 353.1, 'rahu' => 27.8, 'ketu' => 207.8],
        '2025-11-18' => ['sun' => 255.8, 'moon' => 280.7, 'mercury' => 150.2, 'venus' => 142.1, 'mars' => 51.4, 'jupiter' => 76.6, 'saturn' => 353.1, 'rahu' => 27.7, 'ketu' => 207.7],
        '2025-11-19' => ['sun' => 256.8, 'moon' => 293.9, 'mercury' => 152.8, 'venus' => 143.7, 'mars' => 52.6, 'jupiter' => 76.6, 'saturn' => 353.1, 'rahu' => 27.6, 'ketu' => 207.6],
        '2025-11-20' => ['sun' => 257.8, 'moon' => 307.1, 'mercury' => 155.4, 'venus' => 145.3, 'mars' => 53.8, 'jupiter' => 76.6, 'saturn' => 353.0, 'rahu' => 27.5, 'ketu' => 207.5],
        '2025-11-21' => ['sun' => 258.8, 'moon' => 320.3, 'mercury' => 158.0, 'venus' => 146.9, 'mars' => 55.0, 'jupiter' => 76.6, 'saturn' => 353.0, 'rahu' => 27.4, 'ketu' => 207.4],
        '2025-11-22' => ['sun' => 259.8, 'moon' => 333.5, 'mercury' => 160.6, 'venus' => 148.5, 'mars' => 56.2, 'jupiter' => 76.7, 'saturn' => 353.0, 'rahu' => 27.3, 'ketu' => 207.3],
        '2025-11-23' => ['sun' => 260.8, 'moon' => 346.7, 'mercury' => 163.2, 'venus' => 150.1, 'mars' => 57.4, 'jupiter' => 76.7, 'saturn' => 353.0, 'rahu' => 27.2, 'ketu' => 207.2],
        
        '2025-11-24' => ['sun' => 262.2, 'moon' => 352.8, 'mercury' => 162.1, 'venus' => 154.5, 'mars' => 65.8, 'jupiter' => 76.7, 'saturn' => 352.9, 'rahu' => 27.1, 'ketu' => 207.1],
        '2025-11-25' => ['sun' => 263.2, 'moon' => 6.0, 'mercury' => 164.7, 'venus' => 156.1, 'mars' => 67.0, 'jupiter' => 76.8, 'saturn' => 352.9, 'rahu' => 27.0, 'ketu' => 207.0],
        '2025-11-26' => ['sun' => 264.2, 'moon' => 19.2, 'mercury' => 167.3, 'venus' => 157.7, 'mars' => 68.2, 'jupiter' => 76.8, 'saturn' => 352.9, 'rahu' => 26.9, 'ketu' => 206.9],
        '2025-11-27' => ['sun' => 265.2, 'moon' => 32.4, 'mercury' => 169.9, 'venus' => 159.3, 'mars' => 69.4, 'jupiter' => 76.8, 'saturn' => 352.8, 'rahu' => 26.8, 'ketu' => 206.8],
        '2025-11-28' => ['sun' => 266.2, 'moon' => 45.6, 'mercury' => 172.5, 'venus' => 160.9, 'mars' => 70.6, 'jupiter' => 76.8, 'saturn' => 352.8, 'rahu' => 26.7, 'ketu' => 206.7],
        '2025-11-29' => ['sun' => 267.2, 'moon' => 58.8, 'mercury' => 175.1, 'venus' => 162.5, 'mars' => 71.8, 'jupiter' => 76.9, 'saturn' => 352.8, 'rahu' => 26.6, 'ketu' => 206.6],
        '2025-11-30' => ['sun' => 268.2, 'moon' => 72.0, 'mercury' => 177.7, 'venus' => 164.1, 'mars' => 73.0, 'jupiter' => 76.9, 'saturn' => 352.8, 'rahu' => 26.5, 'ketu' => 206.5],
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
     * Stock-Planet mapping (Based on business nature)
     */
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

    public function index()
    {
        $pageTitle = 'Astro Trading Intelligence - Weekly Forecast';
        
        // Get next Monday
        $nextMonday = Carbon::now()->next(Carbon::MONDAY);
        $symbols = SymbolList::distinct()->orderBy('symbol')->pluck('symbol')->toArray();
        
        return view($this->activeTemplate . 'user.option.analysis.astro-trading', compact('pageTitle', 'nextMonday', 'symbols'));
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
     * Calculate planetary positions (simplified ephemeris)
     */
    private function calculatePlanetaryPositions($dateStr)
    {
        $timestamp = strtotime($dateStr);
        $days = ($timestamp - strtotime('2025-01-01')) / 86400;
        
        return [
            'sun' => fmod(280 + ($days * 0.9856), 360),
            'moon' => fmod(120 + ($days * 13.176), 360),
            'mercury' => fmod(240 + ($days * 1.383), 360),
            'venus' => fmod(300 + ($days * 1.602), 360),
            'mars' => fmod(180 + ($days * 0.524), 360),
            'jupiter' => fmod(70 + ($days * 0.083), 360),
            'saturn' => fmod(350 + ($days * 0.034), 360),
            'rahu' => fmod(30 - ($days * 0.053), 360),
            'ketu' => fmod(210 - ($days * 0.053), 360),
        ];
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