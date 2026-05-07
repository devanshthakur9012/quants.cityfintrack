<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class WeeklyAstroController extends Controller
{
     private $symbols = [
        'AXISBANK', 'BAJFINANCE', 'BHARTIARTL', 'DRREDDY', 
        'CIPLA', 'SHRIRAMFIN', 'CHOLAFIN', 'PAYTM', 'NIFTY', 'BANKNIFTY'
    ];

    // Enhanced symbol profiles with market behavior
    private $symbolProfile = [
        'AXISBANK' => [
            'sector' => 'Banking', 
            'volatility_base' => 45,
            'jupiter_weight' => 1.5,  // Jupiter affects banking heavily
            'mars_weight' => 0.8,
            'mercury_weight' => 1.0,
            'typical_range' => '1.5-2.5%',
            'momentum_bias' => 'trend_follower'
        ],
        'BAJFINANCE' => [
            'sector' => 'NBFC', 
            'volatility_base' => 60,
            'jupiter_weight' => 1.8,
            'mars_weight' => 1.2,
            'mercury_weight' => 1.5,  // Financial news sensitive
            'typical_range' => '2-4%',
            'momentum_bias' => 'aggressive'
        ],
        'BHARTIARTL' => [
            'sector' => 'Telecom', 
            'volatility_base' => 35,
            'uranus_weight' => 1.6,  // Tech disruption
            'mercury_weight' => 1.3,
            'mars_weight' => 0.7,
            'typical_range' => '1-2%',
            'momentum_bias' => 'slow_mover'
        ],
        'NIFTY' => [
            'sector' => 'Index', 
            'volatility_base' => 40,
            'sun_weight' => 1.5,
            'jupiter_weight' => 1.3,
            'saturn_weight' => 1.2,
            'typical_range' => '0.8-1.5%',
            'momentum_bias' => 'balanced'
        ],
        'BANKNIFTY' => [
            'sector' => 'Banking Index', 
            'volatility_base' => 55,
            'jupiter_weight' => 2.0,  // Banking is Jupiter's domain
            'saturn_weight' => 1.5,
            'mars_weight' => 1.3,
            'typical_range' => '1.5-3%',
            'momentum_bias' => 'volatile'
        ],
    ];

    private $signs = [
        'Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo',
        'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'
    ];

    // Market cycle patterns (combining lunar phases with market psychology)
    private $marketCycles = [
        'New Moon' => ['sentiment_boost' => 8, 'volatility_add' => -5, 'note' => 'Fresh starts, new positions'],
        'Waxing' => ['sentiment_boost' => 5, 'volatility_add' => 3, 'note' => 'Building momentum'],
        'Full Moon' => ['sentiment_boost' => -6, 'volatility_add' => 15, 'note' => 'Emotional peaks, profit booking'],
        'Waning' => ['sentiment_boost' => -3, 'volatility_add' => -2, 'note' => 'Cooling off, consolidation'],
    ];

    public function index()
    {
        $pageTitle = "Financial Astrology — Weekly Analysis";
        $symbols = $this->symbols;
        return view($this->activeTemplate . 'user.astro.weekly_analysis', compact('symbols','pageTitle'));
    }

    public function generateAnalysis(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'symbol' => 'required|in:' . implode(',', $this->symbols),
        ]);

        $startDate = Carbon::parse($request->start_date);
        $symbol = $request->symbol;
        $profile = $this->symbolProfile[$symbol] ?? $this->symbolProfile['NIFTY'];

        $weeklyData = [];
        $prevClose = 100; // Simulated previous close (in real app, fetch from DB/API)

        for ($i = 0; $i < 5; $i++) {
            $date = $startDate->copy()->addDays($i);
            
            if ($date->isWeekend()) {
                $date = $date->next(Carbon::MONDAY);
            }
            
            // Get astronomical data
            $ephemeris = $this->getEnhancedEphemeris($date);
            $prevDate = $date->copy()->subDay();
            if ($prevDate->isWeekend()) {
                $prevDate = $prevDate->previous(Carbon::FRIDAY);
            }
            $prevEphemeris = $this->getEnhancedEphemeris($prevDate);
            
            // Planetary analysis
            $aspects = $this->detectMajorAspects($ephemeris, $profile);
            $ingresses = $this->detectSignChanges($ephemeris, $prevEphemeris);
            $retrogrades = $this->getRetrogradeStatus($ephemeris);
            
            // Calculate sentiment with symbol-specific weighting
            $sentiment = $this->calculateDynamicSentiment(
                $ephemeris, $aspects, $retrogrades, $profile, $date
            );
            
            // Calculate volatility with market context
            $volatility = $this->calculateRealisticVolatility(
                $ephemeris, $aspects, $profile, $date
            );
            
            // Generate prediction with market structure
            $prediction = $this->generateRealisticPrediction(
                $symbol, $date, $ephemeris, $aspects, $ingresses, 
                $retrogrades, $sentiment, $volatility, $profile, $prevClose
            );
            
            // Update simulated close for next day
            $prevClose = $prediction['projected_close'] ?? $prevClose;
            
            $weeklyData[] = $prediction;
        }

        $pageTitle = "Financial Astrology — Weekly Analysis";
        $symbols = $this->symbols;
        $selectedSymbol = $symbol;
        $startDateFormatted = $startDate->format('Y-m-d');
        
        return view($this->activeTemplate . 'user.astro.weekly_analysis', 
            compact('pageTitle','symbols','selectedSymbol','startDateFormatted','weeklyData'));
    }

    private function calculateDynamicSentiment($ephemeris, $aspects, $retrogrades, $profile, $date)
    {
        $score = 50; // Neutral base
        
        // 1. Aspect influence (with symbol-specific weighting)
        foreach ($aspects as $aspect) {
            $weight = 1.0;
            
            // Apply symbol-specific planetary weights
            if (strpos($aspect['label'], 'Jupiter') !== false) {
                $weight = $profile['jupiter_weight'] ?? 1.0;
            }
            if (strpos($aspect['label'], 'Mercury') !== false) {
                $weight = $profile['mercury_weight'] ?? 1.0;
            }
            if (strpos($aspect['label'], 'Mars') !== false) {
                $weight = $profile['mars_weight'] ?? 1.0;
            }
            if (strpos($aspect['label'], 'Saturn') !== false) {
                $weight = $profile['saturn_weight'] ?? 1.0;
            }
            
            $adjustedScore = $aspect['score'] * ($aspect['strength'] / 100) * $weight;
            $score += $adjustedScore;
        }
        
        // 2. Moon phase cycle
        $cycle = $this->marketCycles[$ephemeris['moon_phase']] ?? ['sentiment_boost' => 0];
        $score += $cycle['sentiment_boost'];
        
        // 3. Void Moon penalty
        if ($ephemeris['moon_void']) {
            $score -= 12; // Strong negative
        }
        
        // 4. Retrograde drag (weighted)
        foreach ($retrogrades as $retro) {
            if ($retro['planet'] === 'Mercury') {
                $score -= 5 * ($profile['mercury_weight'] ?? 1.0);
            }
            if ($retro['planet'] === 'Mars') {
                $score -= 4 * ($profile['mars_weight'] ?? 1.0);
            }
            if ($retro['planet'] === 'Jupiter') {
                $score -= 6 * ($profile['jupiter_weight'] ?? 1.0);
            }
            if ($retro['planet'] === 'Saturn') {
                $score -= 3;
            }
        }
        
        // 5. Day of week psychology
        $dayOfWeek = $date->dayOfWeek;
        if ($dayOfWeek == 1) { // Monday
            $score -= 4; // Monday blues stronger
        } elseif ($dayOfWeek == 5) { // Friday
            $score += 3; // Friday optimism
        } elseif ($dayOfWeek == 3) { // Wednesday
            $score += 2; // Mid-week bounce
        }
        
        // 6. Symbol momentum bias
        if ($profile['momentum_bias'] === 'aggressive') {
            $score *= 1.1; // Amplify
        } elseif ($profile['momentum_bias'] === 'slow_mover') {
            $score = 50 + ($score - 50) * 0.7; // Dampen
        }
        
        // 7. Add some randomness for variation (market is never perfectly predictable)
        $randomFactor = (mt_rand(-100, 100) / 100) * 3;
        $score += $randomFactor;
        
        return max(15, min(85, $score)); // Keep in realistic range
    }

    private function calculateRealisticVolatility($ephemeris, $aspects, $profile, $date)
    {
        $volatility = $profile['volatility_base'] ?? 40;
        
        // 1. Aspect-driven volatility
        foreach ($aspects as $aspect) {
            if ($aspect['type'] === 'Highly Volatile') {
                $volatility += 25 * ($aspect['strength'] / 100);
            } elseif ($aspect['type'] === 'Volatile') {
                $volatility += 15 * ($aspect['strength'] / 100);
            }
            
            // Mars-Uranus always adds volatility
            if (strpos($aspect['label'], 'Mars') !== false && strpos($aspect['label'], 'Uranus') !== false) {
                $volatility += 20;
            }
        }
        
        // 2. Moon phase volatility
        $cycle = $this->marketCycles[$ephemeris['moon_phase']] ?? ['volatility_add' => 0];
        $volatility += $cycle['volatility_add'];
        
        // 3. Full/New Moon extremes
        if ($ephemeris['moon_phase'] === 'Full Moon') {
            $volatility += 12;
        } elseif ($ephemeris['moon_phase'] === 'New Moon') {
            $volatility -= 5;
        }
        
        // 4. Void Moon = unpredictability
        if ($ephemeris['moon_void']) {
            $volatility += 15;
        }
        
        // 5. Day of week patterns
        if ($date->dayOfWeek == 1) { // Monday
            $volatility += 8; // Opening volatility
        } elseif ($date->dayOfWeek == 5) { // Friday
            $volatility += 6; // Expiry/weekend positioning
        }
        
        // 6. Random market noise
        $randomVol = (mt_rand(-100, 100) / 100) * 5;
        $volatility += $randomVol;
        
        return max(20, min(95, $volatility));
    }

     private function getDayCharacteristic($date)
    {
        $day = $date->dayOfWeek;
        $characteristics = [
            1 => 'Monday - Opening volatility, set weekly tone',
            2 => 'Tuesday - Follow-through day, trend confirmation',
            3 => 'Wednesday - Mid-week pivot, often reversals',
            4 => 'Thursday - Pre-expiry positioning starts',
            5 => 'Friday - Weekly expiry, profit booking, weekend gaps'
        ];
        return $characteristics[$day] ?? 'Regular trading day';
    }

    private function getRiskLevel($volatility)
    {
        if ($volatility >= 75) return 'Extreme - Reduce size by 60%';
        if ($volatility >= 60) return 'High - Reduce size by 40%';
        if ($volatility >= 45) return 'Moderate - Normal position sizing';
        return 'Low - Can take slightly larger positions';
    }

    

    private function suggestPositionSize($volatility, $sentiment)
    {
        $baseSize = 100;
        
        // Reduce for high volatility
        if ($volatility >= 70) $baseSize *= 0.5;
        elseif ($volatility >= 55) $baseSize *= 0.7;
        elseif ($volatility >= 40) $baseSize *= 0.85;
        
        // Adjust for confidence (sentiment extremes)
        if ($sentiment >= 70 || $sentiment <= 30) {
            $baseSize *= 1.1; // High conviction
        } elseif ($sentiment >= 45 && $sentiment <= 55) {
            $baseSize *= 0.75; // Low conviction
        }
        
        return round($baseSize) . '% of normal size';
    }

    
    private function formatLevel($value)
    {
        return number_format($value, 2);
    }

    private function generateRealisticPrediction($symbol, $date, $ephemeris, $aspects, $ingresses, 
                                                  $retrogrades, $sentiment, $volatility, $profile, $prevClose)
    {
        // Determine bias with nuanced levels
        if ($sentiment >= 70) {
            $bias = 'Strong Bullish';
            $biasColor = 'success';
            $biasIcon = 'las la-rocket';
            $strategy = "Aggressive longs | Buy breakouts above {$this->formatLevel($prevClose * 1.005)} | Trail stops at VWAP";
            $expectedMove = '+' . number_format(($volatility / 30) * 1.8, 1) . '%';
        } elseif ($sentiment >= 58) {
            $bias = 'Bullish';
            $biasColor = 'success';
            $biasIcon = 'las la-arrow-up';
            $strategy = "Long bias | Buy dips to {$this->formatLevel($prevClose * 0.998)} | Book partial at +1%";
            $expectedMove = '+' . number_format(($volatility / 30) * 1.2, 1) . '%';
        } elseif ($sentiment >= 47) {
            $bias = 'Neutral-Bullish';
            $biasColor = 'warning';
            $biasIcon = 'las la-equals';
            $strategy = "Range trade | Buy {$this->formatLevel($prevClose * 0.997)}, Sell {$this->formatLevel($prevClose * 1.003)} | Tight stops";
            $expectedMove = '±' . number_format(($volatility / 30) * 0.8, 1) . '%';
        } elseif ($sentiment >= 40) {
            $bias = 'Neutral-Bearish';
            $biasColor = 'warning';
            $biasIcon = 'las la-minus';
            $strategy = "Defensive | Short rallies above {$this->formatLevel($prevClose * 1.002)} | Avoid longs";
            $expectedMove = '-' . number_format(($volatility / 30) * 0.9, 1) . '%';
        } elseif ($sentiment >= 30) {
            $bias = 'Bearish';
            $biasColor = 'danger';
            $biasIcon = 'las la-arrow-down';
            $strategy = "Short bias | Sell strength | Target {$this->formatLevel($prevClose * 0.985)} | Stop above day high";
            $expectedMove = '-' . number_format(($volatility / 30) * 1.3, 1) . '%';
        } else {
            $bias = 'Strong Bearish';
            $biasColor = 'danger';
            $biasIcon = 'las la-skull-crossbones';
            $strategy = "Aggressive shorts | Avoid longs | Target {$this->formatLevel($prevClose * 0.975)} | Hedge positions";
            $expectedMove = '-' . number_format(($volatility / 30) * 2.0, 1) . '%';
        }
        
        // Gap prediction logic
        if ($sentiment >= 65 && $volatility < 60 && count($aspects) > 0) {
            $gap = 'Gap-up Expected (0.3-0.7%)';
            $gapColor = 'success';
            $gapDirection = 1.005;
        } elseif ($sentiment <= 35 && $volatility >= 50) {
            $gap = 'Gap-down Risk (0.3-0.8%)';
            $gapColor = 'danger';
            $gapDirection = 0.995;
        } elseif ($volatility >= 75) {
            $gap = 'Volatile Gap (Either Way)';
            $gapColor = 'warning';
            $gapDirection = mt_rand(0, 1) ? 1.008 : 0.992;
        } else {
            $gap = 'Flat Open (±0.2%)';
            $gapColor = 'secondary';
            $gapDirection = 1.0;
        }
        
        // Calculate realistic price projections
        $expectedOpen = $prevClose * $gapDirection;
        $expectedHigh = $expectedOpen * (1 + ($volatility / 100) * 0.8);
        $expectedLow = $expectedOpen * (1 - ($volatility / 100) * 0.7);
        
        if ($sentiment >= 55) {
            $expectedClose = $expectedOpen * 1.005;
        } elseif ($sentiment >= 45) {
            $expectedClose = $expectedOpen * 1.001;
        } else {
            $expectedClose = $expectedOpen * 0.997;
        }
        
        // Trading windows (more dynamic)
        $windows = $this->generateDynamicWindows($sentiment, $volatility, $ephemeris, $date);
        
        // Key levels with actual numbers
        $keyLevels = [
            'Immediate Support' => $this->formatLevel($expectedOpen * 0.996),
            'Strong Support' => $this->formatLevel($expectedOpen * 0.992),
            'Immediate Resistance' => $this->formatLevel($expectedOpen * 1.004),
            'Strong Resistance' => $this->formatLevel($expectedOpen * 1.008),
            'Stop Loss (Long)' => $this->formatLevel($expectedOpen * 0.994),
            'Stop Loss (Short)' => $this->formatLevel($expectedOpen * 1.006),
        ];
        
        // Build comprehensive reasons
        $reasons = [];
        
        // Add top 3 aspects
        $topAspects = array_slice($aspects, 0, 3);
        foreach ($topAspects as $asp) {
            $reasons[] = $asp['label'] . " (" . $asp['type'] . ", " . $asp['strength'] . "% strength)";
        }
        
        // Market cycle
        $reasons[] = "Moon Phase: " . $ephemeris['moon_phase'] . " - " . ($this->marketCycles[$ephemeris['moon_phase']]['note'] ?? '');
        
        // Retrogrades
        foreach ($retrogrades as $retro) {
            $reasons[] = $retro['planet'] . " Retrograde active";
        }
        
        // Day pattern
        $reasons[] = "Day Pattern: " . $this->getDayCharacteristic($date);
        
        if (empty($reasons)) {
            $reasons[] = "No major planetary events. Trade technicals.";
        }
        
        return [
            'date' => $date->format('Y-m-d'),
            'weekday' => $date->format('l'),
            'day_num' => $date->format('d M'),
            
            'bias' => $bias,
            'bias_color' => $biasColor,
            'bias_icon' => $biasIcon,
            'strategy' => $strategy,
            
            'sentiment_score' => round($sentiment),
            'volatility_score' => round($volatility),
            
            'gap' => $gap,
            'gap_color' => $gapColor,
            'expected_move' => $expectedMove,
            
            // Price projections
            'expected_open' => $this->formatLevel($expectedOpen),
            'expected_high' => $this->formatLevel($expectedHigh),
            'expected_low' => $this->formatLevel($expectedLow),
            'expected_close' => $this->formatLevel($expectedClose),
            'projected_close' => $expectedClose, // For next day calculation
            
            'windows' => $windows,
            'key_levels' => $keyLevels,
            
            'reasons' => $reasons,
            'aspects' => $aspects,
            'ingresses' => $ingresses,
            'retrogrades' => $retrogrades,
            
            'moon_phase' => $ephemeris['moon_phase'],
            'moon_void' => $ephemeris['moon_void'],
            
            // Trading insights
            'risk_level' => $this->getRiskLevel($volatility),
            'position_size' => $this->suggestPositionSize($volatility, $sentiment),
        ];
    }

    private function generateDynamicWindows($sentiment, $volatility, $ephemeris, $date)
    {
        $isMonday = $date->dayOfWeek == 1;
        $isFriday = $date->dayOfWeek == 5;
        
        if ($sentiment >= 65 && $volatility < 55) {
            return [
                'best_entry' => $isMonday ? '09:45 - 10:15' : '09:30 - 10:00',
                'profit_booking' => $isFriday ? '14:00 - 15:00' : '14:15 - 15:00',
                'avoid' => '09:15 - 09:30 (opening spike)',
                'scalp_zones' => ['10:30 - 11:15', '14:00 - 14:45']
            ];
        } elseif ($sentiment <= 38 && $volatility >= 50) {
            return [
                'best_entry' => '10:00 - 10:45 (after panic selling)',
                'profit_booking' => '12:00 - 13:00 (before lunch reversal)',
                'avoid' => '09:15 - 09:50 (wait for direction)',
                'scalp_zones' => ['10:45 - 11:30', '14:30 - 15:15']
            ];
        } elseif ($volatility >= 70) {
            return [
                'best_entry' => '10:30 - 11:15 (after volatility settles)',
                'profit_booking' => '12:30 - 13:30 (before afternoon chaos)',
                'avoid' => '09:15 - 10:15, 15:00 - 15:30 (extreme moves)',
                'scalp_zones' => ['11:30 - 12:15', '13:45 - 14:30']
            ];
        } else {
            return [
                'best_entry' => '09:40 - 10:20, 13:45 - 14:15',
                'profit_booking' => '11:15 - 12:00',
                'avoid' => '12:30 - 13:15 (lunch lull)',
                'scalp_zones' => ['09:45 - 10:15', '14:30 - 15:10']
            ];
        }
    }

    private function getEnhancedEphemeris(Carbon $date)
    {
        // More accurate astronomical calculations
        $jd = $this->julianDay($date);
        $T = ($jd - 2451545.0) / 36525; // Julian centuries from J2000
        
        return [
            'date' => $date->format('Y-m-d'),
            'weekday' => $date->format('l'),
            'day_of_week' => $date->dayOfWeek,
            
            // Planetary positions (degrees in zodiac)
            'sun' => fmod(280.460 + 36000.772 * $T, 360),
            'moon' => fmod(218.316 + 481267.881 * $T, 360),
            'mercury' => fmod(252.250 + 149472.675 * $T, 360),
            'venus' => fmod(181.980 + 58517.816 * $T, 360),
            'mars' => fmod(355.433 + 19140.300 * $T, 360),
            'jupiter' => fmod(34.352 + 3034.906 * $T, 360),
            'saturn' => fmod(50.078 + 1222.114 * $T, 360),
            'uranus' => fmod(314.055 + 428.049 * $T, 360),
            'neptune' => fmod(304.880 + 218.486 * $T, 360),
            'pluto' => fmod(238.960 + 145.210 * $T, 360),
            
            // Moon phase
            'moon_phase' => $this->getMoonPhase($jd),
            
            // Retrograde status
            'mercury_retro' => $this->isMercuryRetrograde($date),
            'venus_retro' => $this->isVenusRetrograde($date),
            'mars_retro' => $this->isMarsRetrograde($date),
            'jupiter_retro' => $this->isJupiterRetrograde($date),
            'saturn_retro' => $this->isSaturnRetrograde($date),
            
            // Special positions
            'moon_void' => $this->isVoidOfCourseMoon($jd),
        ];
    }

    private function julianDay(Carbon $date)
    {
        $y = $date->year;
        $m = $date->month;
        $d = $date->day;
        
        if ($m <= 2) {
            $y -= 1;
            $m += 12;
        }
        
        $a = floor($y / 100);
        $b = 2 - $a + floor($a / 4);
        
        return floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $d + $b - 1524.5;
    }

    private function getMoonPhase($jd)
    {
        $T = ($jd - 2451545.0) / 36525;
        $D = fmod(297.8501921 + 445267.1114034 * $T, 360);
        
        if ($D < 0) $D += 360;
        
        if ($D < 45 || $D >= 315) return 'New Moon';
        if ($D < 135) return 'Waxing';
        if ($D < 225) return 'Full Moon';
        return 'Waning';
    }

    private function isMercuryRetrograde(Carbon $date)
    {
        // Mercury retrograde periods for 2024-2025
        $retroPeriods = [
            ['2024-04-01', '2024-04-25'],
            ['2024-08-05', '2024-08-28'],
            ['2024-11-25', '2024-12-15'],
            ['2025-03-15', '2025-04-07'],
            ['2025-07-18', '2025-08-11'],
            ['2025-11-09', '2025-11-29'],
        ];
        
        return $this->isDateInPeriods($date, $retroPeriods);
    }

    private function isVenusRetrograde(Carbon $date)
    {
        $retroPeriods = [
            ['2025-03-01', '2025-04-12'],
        ];
        return $this->isDateInPeriods($date, $retroPeriods);
    }

    private function isMarsRetrograde(Carbon $date)
    {
        $retroPeriods = [
            ['2024-12-06', '2025-02-23'],
        ];
        return $this->isDateInPeriods($date, $retroPeriods);
    }

    private function isJupiterRetrograde(Carbon $date)
    {
        $retroPeriods = [
            ['2024-10-09', '2025-02-04'],
        ];
        return $this->isDateInPeriods($date, $retroPeriods);
    }

    private function isSaturnRetrograde(Carbon $date)
    {
        $retroPeriods = [
            ['2024-06-29', '2024-11-15'],
            ['2025-07-13', '2025-11-28'],
        ];
        return $this->isDateInPeriods($date, $retroPeriods);
    }

    private function isDateInPeriods(Carbon $date, array $periods)
    {
        foreach ($periods as $period) {
            $start = Carbon::parse($period[0]);
            $end = Carbon::parse($period[1]);
            if ($date->between($start, $end)) {
                return true;
            }
        }
        return false;
    }

    private function isVoidOfCourseMoon($jd)
    {
        // Simplified: moon is void when it's in the last 2 degrees of a sign
        $T = ($jd - 2451545.0) / 36525;
        $moonPos = fmod(218.316 + 481267.881 * $T, 360);
        $degreeInSign = fmod($moonPos, 30);
        
        return $degreeInSign >= 28;
    }

    private function detectMajorAspects($ephemeris)
    {
        $aspects = [];
        
        // Define major aspects with their meanings
        $aspectPatterns = [
            // Bullish
            ['sun', 'jupiter', 0, 8, 'Sun conjunct Jupiter', 15, 'Very Bullish', 'Major optimism wave'],
            ['sun', 'jupiter', 120, 8, 'Sun trine Jupiter', 12, 'Bullish', 'Smooth growth energy'],
            ['venus', 'jupiter', 60, 6, 'Venus sextile Jupiter', 10, 'Bullish', 'Financial confidence'],
            ['mercury', 'jupiter', 0, 6, 'Mercury conjunct Jupiter', 11, 'Bullish', 'Positive news catalyst'],
            ['mars', 'jupiter', 60, 6, 'Mars sextile Jupiter', 9, 'Bullish', 'Action meets opportunity'],
            
            // Bearish
            ['mars', 'saturn', 90, 6, 'Mars square Saturn', -15, 'Very Bearish', 'Obstacles and frustration'],
            ['sun', 'saturn', 180, 8, 'Sun opposite Saturn', -12, 'Bearish', 'Reality check, pessimism'],
            ['venus', 'saturn', 90, 5, 'Venus square Saturn', -8, 'Bearish', 'Value concerns resurface'],
            ['mars', 'pluto', 90, 6, 'Mars square Pluto', -14, 'Very Bearish', 'Power struggles, crisis'],
            ['sun', 'pluto', 90, 7, 'Sun square Pluto', -11, 'Bearish', 'Forced transformation'],
            ['saturn', 'neptune', 90, 5, 'Saturn square Neptune', -9, 'Bearish', 'Confusion and doubt'],
            
            // Volatile
            ['mars', 'uranus', 90, 6, 'Mars square Uranus', 0, 'Highly Volatile', 'Sudden shocks, breakouts'],
            ['mars', 'uranus', 0, 6, 'Mars conjunct Uranus', 0, 'Highly Volatile', 'Explosive moves'],
            ['mercury', 'uranus', 180, 6, 'Mercury opposite Uranus', 0, 'Volatile', 'Surprise announcements'],
            ['sun', 'uranus', 90, 7, 'Sun square Uranus', 0, 'Volatile', 'Unexpected disruptions'],
            ['venus', 'uranus', 90, 5, 'Venus square Uranus', 0, 'Volatile', 'Erratic market behavior'],
            
            // Mixed
            ['mercury', 'saturn', 60, 5, 'Mercury sextile Saturn', 0, 'Neutral', 'Careful analysis prevails'],
            ['venus', 'mars', 90, 5, 'Venus square Mars', -3, 'Mixed', 'Desire vs prudence tension'],
            ['sun', 'neptune', 60, 6, 'Sun sextile Neptune', 4, 'Mildly Bullish', 'Optimism with caution'],
        ];

        foreach ($aspectPatterns as $pattern) {
            [$p1, $p2, $angle, $orb, $label, $score, $type, $meaning] = $pattern;
            
            if (isset($ephemeris[$p1]) && isset($ephemeris[$p2])) {
                $actualOrb = $this->getAspectOrb($ephemeris[$p1], $ephemeris[$p2], $angle);
                
                if ($actualOrb <= $orb) {
                    $strength = 100 - ($actualOrb / $orb * 100);
                    $aspects[] = [
                        'label' => $label,
                        'score' => $score,
                        'type' => $type,
                        'meaning' => $meaning,
                        'orb' => round($actualOrb, 1),
                        'strength' => round($strength),
                    ];
                }
            }
        }

        // Sort by strength
        usort($aspects, function($a, $b) {
            return abs($b['score']) * $b['strength'] - abs($a['score']) * $a['strength'];
        });

        return $aspects;
    }

    private function getAspectOrb($pos1, $pos2, $targetAngle)
    {
        $diff = abs($pos1 - $pos2);
        if ($diff > 180) $diff = 360 - $diff;
        return abs($diff - $targetAngle);
    }

    private function detectSignChanges($current, $previous)
    {
        $ingresses = [];
        $planets = ['sun', 'moon', 'mercury', 'venus', 'mars', 'jupiter'];

        foreach ($planets as $planet) {
            if (isset($current[$planet]) && isset($previous[$planet])) {
                $currSign = $this->getZodiacSign($current[$planet]);
                $prevSign = $this->getZodiacSign($previous[$planet]);

                if ($currSign !== $prevSign) {
                    $impact = $this->getIngressImpact($planet, $currSign);
                    $ingresses[] = [
                        'text' => ucfirst($planet) . " enters " . $currSign,
                        'impact' => $impact['type'],
                        'meaning' => $impact['meaning']
                    ];
                }
            }
        }

        return $ingresses;
    }

    private function getIngressImpact($planet, $sign)
    {
        // Simplified dignities
        $dignities = [
            'sun' => ['Leo' => 'Very Positive', 'Aries' => 'Positive'],
            'moon' => ['Cancer' => 'Very Positive', 'Taurus' => 'Positive'],
            'mercury' => ['Gemini' => 'Very Positive', 'Virgo' => 'Positive'],
            'venus' => ['Taurus' => 'Very Positive', 'Libra' => 'Positive'],
            'mars' => ['Aries' => 'Very Positive', 'Scorpio' => 'Positive'],
            'jupiter' => ['Sagittarius' => 'Very Positive', 'Pisces' => 'Positive'],
        ];

        if (isset($dignities[$planet][$sign])) {
            return [
                'type' => $dignities[$planet][$sign],
                'meaning' => ucfirst($planet) . " is strong in " . $sign
            ];
        }

        return [
            'type' => 'Neutral',
            'meaning' => ucfirst($planet) . ' changes energy'
        ];
    }

    private function getRetrogradeStatus($ephemeris)
    {
        $retrogrades = [];
        
        if ($ephemeris['mercury_retro']) {
            $retrogrades[] = [
                'planet' => 'Mercury',
                'impact' => 'Caution with communications, data, tech stocks'
            ];
        }
        
        if ($ephemeris['mars_retro']) {
            $retrogrades[] = [
                'planet' => 'Mars',
                'impact' => 'Reduced momentum, review strategies'
            ];
        }
        
        if ($ephemeris['jupiter_retro']) {
            $retrogrades[] = [
                'planet' => 'Jupiter',
                'impact' => 'Growth slows, reassess expansion'
            ];
        }
        
        if ($ephemeris['saturn_retro']) {
            $retrogrades[] = [
                'planet' => 'Saturn',
                'impact' => 'Past issues resurface, consolidate'
            ];
        }

        return $retrogrades;
    }

    private function calculateMarketSentiment($ephemeris, $aspects, $retrogrades)
    {
        $score = 50; // Start neutral
        
        // Aspect influence
        foreach ($aspects as $aspect) {
            $score += $aspect['score'] * ($aspect['strength'] / 100);
        }
        
        // Moon phase influence
        if ($ephemeris['moon_phase'] === 'New Moon') {
            $score += 5; // New beginnings
        } elseif ($ephemeris['moon_phase'] === 'Full Moon') {
            $score -= 3; // Emotional extremes
        }
        
        // Void of course moon
        if ($ephemeris['moon_void']) {
            $score -= 8; // Avoid major decisions
        }
        
        // Retrograde drag
        $score -= count($retrogrades) * 3;
        
        // Day of week influence
        if ($ephemeris['day_of_week'] == 1) { // Monday
            $score -= 2; // Monday blues
        } elseif ($ephemeris['day_of_week'] == 5) { // Friday
            $score += 2; // Friday optimism
        }
        
        return max(0, min(100, $score));
    }

    private function generateTradingPrediction($symbol, $date, $ephemeris, $aspects, $ingresses, $retrogrades, $sentiment)
    {
        $profile = $this->symbolProfile[$symbol];
        
        // Adjust sentiment based on symbol sensitivity
        $adjustedSentiment = $sentiment;
        foreach ($aspects as $aspect) {
            if (strpos($aspect['label'], 'Jupiter') !== false && ($profile['jupiter_sensitive'] ?? false)) {
                $adjustedSentiment += $aspect['score'] * 0.3;
            }
            if (strpos($aspect['label'], 'Mercury') !== false && ($profile['mercury_sensitive'] ?? false)) {
                $adjustedSentiment += $aspect['score'] * 0.3;
            }
            if (strpos($aspect['label'], 'Uranus') !== false && ($profile['uranus_sensitive'] ?? false)) {
                $adjustedSentiment += abs($aspect['score']) * 0.2; // More volatility
            }
        }
        
        $adjustedSentiment = max(0, min(100, $adjustedSentiment));
        
        // Determine bias
        if ($adjustedSentiment >= 75) {
            $bias = 'Strong Bullish';
            $biasColor = 'success';
            $biasIcon = 'las la-rocket';
            $strategy = 'Aggressive long positions, buy dips';
        } elseif ($adjustedSentiment >= 60) {
            $bias = 'Bullish';
            $biasColor = 'success';
            $biasIcon = 'las la-arrow-up';
            $strategy = 'Long bias, trail stops loosely';
        } elseif ($adjustedSentiment >= 45) {
            $bias = 'Neutral';
            $biasColor = 'warning';
            $biasIcon = 'las la-minus';
            $strategy = 'Range-bound, scalp both ways';
        } elseif ($adjustedSentiment >= 30) {
            $bias = 'Bearish';
            $biasColor = 'danger';
            $biasIcon = 'las la-arrow-down';
            $strategy = 'Short bias, tight stops on longs';
        } else {
            $bias = 'Strong Bearish';
            $biasColor = 'danger';
            $biasIcon = 'las la-skull-crossbones';
            $strategy = 'Avoid longs, aggressive shorts';
        }
        
        // Calculate volatility
        $volatility = 30; // Base volatility
        foreach ($aspects as $aspect) {
            if ($aspect['type'] === 'Highly Volatile' || $aspect['type'] === 'Volatile') {
                $volatility += 20;
            }
        }
        if ($profile['volatility'] === 'Very High') $volatility += 15;
        if ($profile['volatility'] === 'High') $volatility += 10;
        if ($ephemeris['moon_phase'] === 'Full Moon') $volatility += 10;
        $volatility = min(100, $volatility);
        
        // Gap prediction
        if ($adjustedSentiment >= 70 && $volatility < 60) {
            $gap = 'Gap-up Expected';
            $gapColor = 'success';
        } elseif ($adjustedSentiment <= 35 && $volatility >= 50) {
            $gap = 'Gap-down Risk';
            $gapColor = 'danger';
        } elseif ($volatility >= 70) {
            $gap = 'Volatile Open';
            $gapColor = 'warning';
        } else {
            $gap = 'Flat to Mild Gap';
            $gapColor = 'secondary';
        }
        
        // Trading windows
        $windows = $this->calculateTradingWindows($adjustedSentiment, $volatility, $ephemeris);
        
        // Build detailed reason
        $reasons = [];
        
        if (count($aspects) > 0) {
            $topAspects = array_slice($aspects, 0, 3);
            foreach ($topAspects as $asp) {
                $reasons[] = $asp['label'] . " (" . $asp['meaning'] . ")";
            }
        }
        
        foreach ($ingresses as $ing) {
            $reasons[] = $ing['text'];
        }
        
        foreach ($retrogrades as $retro) {
            $reasons[] = $retro['planet'] . " Retrograde";
        }
        
        if ($ephemeris['moon_void']) {
            $reasons[] = "Void of Course Moon - avoid major trades";
        }
        
        if (empty($reasons)) {
            $reasons[] = "No major planetary drivers today";
        }
        
        // Key levels suggestion
        $keyLevels = $this->suggestKeyLevels($adjustedSentiment, $volatility);
        
        return [
            'date' => $date->format('Y-m-d'),
            'weekday' => $date->format('l'),
            'day_num' => $date->format('d M'),
            
            'bias' => $bias,
            'bias_color' => $biasColor,
            'bias_icon' => $biasIcon,
            'strategy' => $strategy,
            
            'sentiment_score' => round($adjustedSentiment),
            'volatility_score' => round($volatility),
            
            'gap' => $gap,
            'gap_color' => $gapColor,
            
            'windows' => $windows,
            'key_levels' => $keyLevels,
            
            'reasons' => $reasons,
            'aspects' => $aspects,
            'ingresses' => $ingresses,
            'retrogrades' => $retrogrades,
            
            'moon_phase' => $ephemeris['moon_phase'],
            'moon_void' => $ephemeris['moon_void'],
        ];
    }

    private function calculateTradingWindows($sentiment, $volatility, $ephemeris)
    {
        // Customize based on conditions
        if ($sentiment >= 65 && $volatility < 60) {
            // Strong bullish, lower volatility
            return [
                'best_entry' => '09:40 - 10:30',
                'profit_booking' => '14:30 - 15:15',
                'avoid' => '09:15 - 09:35 (opening volatility)',
                'scalp_zones' => ['11:00 - 11:30', '14:00 - 14:30']
            ];
        } elseif ($sentiment <= 40 && $volatility >= 50) {
            // Bearish with volatility
            return [
                'best_entry' => '10:00 - 10:45 (after breakdown confirmation)',
                'profit_booking' => '12:30 - 13:30',
                'avoid' => '09:15 - 09:45 (wait for clarity)',
                'scalp_zones' => ['09:45 - 10:15', '14:30 - 15:00']
            ];
        } elseif ($volatility >= 70) {
            // High volatility day
            return [
                'best_entry' => '10:15 - 11:00 (after dust settles)',
                'profit_booking' => '13:00 - 14:00',
                'avoid' => '09:15 - 10:00 (extreme volatility)',
                'scalp_zones' => ['11:30 - 12:00', '14:30 - 15:15']
            ];
        } else {
            // Neutral/Range-bound
            return [
                'best_entry' => '09:50 - 10:30, 14:00 - 14:30',
                'profit_booking' => '11:30 - 12:30',
                'avoid' => '12:30 - 13:30 (lunch lull)',
                'scalp_zones' => ['09:40 - 10:10', '14:45 - 15:20']
            ];
        }
    }

    private function suggestKeyLevels($sentiment, $volatility)
    {
        if ($sentiment >= 65) {
            return [
                'watch' => 'Previous day high, round numbers',
                'support' => 'Opening price, VWAP',
                'resistance' => 'Intraday highs, psychological levels',
                'stop_loss' => 'Below VWAP or 0.5% from entry'
            ];
        } elseif ($sentiment <= 40) {
            return [
                'watch' => 'Previous day low, support zones',
                'support' => 'Intraday lows, key moving averages',
                'resistance' => 'VWAP, opening price',
                'stop_loss' => 'Above VWAP or 0.5% from entry'
            ];
        } else {
            return [
                'watch' => 'Opening range (first 15 min)',
                'support' => 'Day low, VWAP lower band',
                'resistance' => 'Day high, VWAP upper band',
                'stop_loss' => 'Outside opening range'
            ];
        }
    }

    private function getZodiacSign($degree)
    {
        $index = (int)floor(fmod($degree, 360) / 30);
        return $this->signs[$index] ?? 'Unknown';
    }
}