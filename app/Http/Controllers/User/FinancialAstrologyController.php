<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SymbolList;
use App\Models\HistoricalOptionsData;
use App\Models\EarlyHistoricalOptionsData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialAstrologyController extends Controller
{
    /**
     * Financial Astrology Weekly Analysis
     */
    public function financialAstrology()
    {
        $pageTitle = 'Financial Astrology - Weekly Market Prediction';
        
        // Get available date range from historical data
        $dates = HistoricalOptionsData::select('date')
            ->distinct()
            ->orderBy('date', 'desc')
            ->limit(30)
            ->pluck('date')
            ->toArray();
        
        $symbols = SymbolList::select('symbol')
            ->distinct()
            ->orderBy('symbol')
            ->pluck('symbol')
            ->toArray();
        
        return view($this->activeTemplate . 'user.option.analysis.financial-astrology', 
            compact('pageTitle', 'symbols', 'dates'));
    }

    /**
     * Generate Weekly Astrology Report
     */
    public function financialAstrologyGenerate(Request $request)
    {
        $startDate = $request->get('start_date');
        $days = $request->get('days', 5);
        $symbols = $request->get('symbols', []); // Array of symbols to analyze
        
        if (empty($startDate)) {
            $startDate = now()->startOfWeek()->format('Y-m-d');
        }
        
        // Generate planetary positions (simulated for demo)
        $weekData = $this->generateWeeklyPlanetaryData($startDate, $days);
        
        // Analyze market data with astrological overlay
        $marketAnalysis = $this->analyzeMarketWithAstrology($startDate, $days, $symbols);
        
        // Generate Acts (narrative structure)
        $acts = $this->generateWeeklyActs($weekData, $marketAnalysis);
        
        // Daily breakdown with predictions
        $dailyPredictions = $this->generateDailyPredictions($weekData, $marketAnalysis);
        
        // Sector recommendations based on planetary positions
        $sectorRecommendations = $this->generateSectorRecommendations($weekData);
        
        // PE/CE strategy recommendations
        $optionsStrategy = $this->generateOptionsStrategy($weekData, $marketAnalysis);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'week_range' => [
                    'start' => $startDate,
                    'end' => date('Y-m-d', strtotime($startDate . " +{$days} days"))
                ],
                'theme' => $acts['theme'],
                'acts' => $acts,
                'daily_predictions' => $dailyPredictions,
                'sector_recommendations' => $sectorRecommendations,
                'options_strategy' => $optionsStrategy,
                'planetary_data' => $weekData
            ]
        ]);
    }

    /**
     * Generate Planetary Data for the Week (Simulated)
     */
    private function generateWeeklyPlanetaryData($startDate, $days)
    {
        $week = [];
        $baseDate = strtotime($startDate);
        
        // Seed for consistency
        srand(crc32($startDate));
        
        for ($i = 0; $i < $days; $i++) {
            $currentDate = date('Y-m-d', strtotime("+{$i} days", $baseDate));
            $dayOfWeek = date('l', strtotime($currentDate));
            
            // Generate planetary longitudes (0-360 degrees)
            $base = rand(0, 359);
            $week[$currentDate] = [
                'date' => $currentDate,
                'day' => $dayOfWeek,
                'sun' => fmod($base + $i * 1.0, 360),
                'moon' => fmod($base * 2 + $i * 12.9, 360),
                'mercury' => fmod($base + $i * 1.2, 360),
                'venus' => fmod($base + $i * 1.1, 360),
                'mars' => fmod($base + $i * 0.6, 360),
                'jupiter' => fmod($base + $i * 0.2, 360),
                'saturn' => fmod($base + $i * 0.1, 360),
                'uranus' => fmod($base + $i * 0.07, 360),
                'neptune' => fmod($base + $i * 0.04, 360),
                'pluto' => fmod($base + $i * 0.03, 360),
            ];
            
            // Calculate zodiac signs
            $week[$currentDate]['sun_sign'] = $this->getZodiacSign($week[$currentDate]['sun']);
            $week[$currentDate]['mercury_sign'] = $this->getZodiacSign($week[$currentDate]['mercury']);
            
            // Detect aspects
            $week[$currentDate]['aspects'] = $this->detectPlanetaryAspects($week[$currentDate]);
        }
        
        return $week;
    }

    /**
     * Analyze Market Data with Astrological Overlay
     */
    private function analyzeMarketWithAstrology($startDate, $days, $symbols)
    {
        $analysis = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime($startDate . " +{$i} days"));
            
            // Get market data for this date
            $query = HistoricalOptionsData::whereDate('date', $date);
            
            if (!empty($symbols)) {
                $query->whereIn('underlying', $symbols);
            }
            
            $marketData = $query->get();
            
            if ($marketData->isEmpty()) {
                continue;
            }
            
            // Aggregate market sentiment
            $bullishCount = 0;
            $bearishCount = 0;
            $neutralCount = 0;
            $avgVolatility = 0;
            $totalOIChange = 0;
            
            foreach ($marketData as $data) {
                $trend = strtolower($data->trend ?? '');
                
                if (strpos($trend, 'bullish') !== false) {
                    $bullishCount++;
                } elseif (strpos($trend, 'bearish') !== false) {
                    $bearishCount++;
                } else {
                    $neutralCount++;
                }
                
                // Calculate volatility indicator
                $ceChange = abs($data->ce_oi_chg_pct ?? 0);
                $peChange = abs($data->pe_oi_chg_pct ?? 0);
                $avgVolatility += ($ceChange + $peChange) / 2;
                
                $totalOIChange += ($data->ce_oi_change ?? 0) + ($data->pe_oi_change ?? 0);
            }
            
            $total = $marketData->count();
            $avgVolatility = $total > 0 ? $avgVolatility / $total : 0;
            
            $analysis[$date] = [
                'bullish_ratio' => $total > 0 ? round(($bullishCount / $total) * 100, 2) : 0,
                'bearish_ratio' => $total > 0 ? round(($bearishCount / $total) * 100, 2) : 0,
                'neutral_ratio' => $total > 0 ? round(($neutralCount / $total) * 100, 2) : 0,
                'volatility' => round($avgVolatility, 2),
                'oi_change' => $totalOIChange,
                'market_sentiment' => $this->calculateMarketSentiment($bullishCount, $bearishCount, $neutralCount),
            ];
        }
        
        return $analysis;
    }

    /**
     * Calculate Market Sentiment
     */
    private function calculateMarketSentiment($bullish, $bearish, $neutral)
    {
        $total = $bullish + $bearish + $neutral;
        if ($total == 0) return 'Neutral';
        
        $bullishPct = ($bullish / $total) * 100;
        $bearishPct = ($bearish / $total) * 100;
        
        if ($bullishPct > 60) return 'Strong Bullish';
        if ($bullishPct > 40) return 'Mild Bullish';
        if ($bearishPct > 60) return 'Strong Bearish';
        if ($bearishPct > 40) return 'Mild Bearish';
        
        return 'Neutral / Sideways';
    }

    /**
     * Detect Planetary Aspects
     */
    private function detectPlanetaryAspects($planetaryData)
    {
        $aspects = [];
        
        // Check major aspects
        $aspectDefinitions = [
            'conjunction' => ['angle' => 0, 'orb' => 6],
            'opposition' => ['angle' => 180, 'orb' => 6],
            'trine' => ['angle' => 120, 'orb' => 5],
            'square' => ['angle' => 90, 'orb' => 5],
            'sextile' => ['angle' => 60, 'orb' => 4],
        ];
        
        $planetPairs = [
            ['Mars', 'Jupiter', 'mars', 'jupiter'],
            ['Sun', 'Jupiter', 'sun', 'jupiter'],
            ['Mercury', 'Uranus', 'mercury', 'uranus'],
            ['Sun', 'Pluto', 'sun', 'pluto'],
            ['Mars', 'Uranus', 'mars', 'uranus'],
            ['Venus', 'Jupiter', 'venus', 'jupiter'],
        ];
        
        foreach ($planetPairs as $pair) {
            [$name1, $name2, $key1, $key2] = $pair;
            $angle = $this->calculateAngleBetween($planetaryData[$key1], $planetaryData[$key2]);
            
            foreach ($aspectDefinitions as $aspectName => $config) {
                if (abs($angle - $config['angle']) <= $config['orb']) {
                    $aspects[] = [
                        'type' => $aspectName,
                        'planets' => "$name1 $aspectName $name2",
                        'angle' => round($angle, 2),
                        'interpretation' => $this->interpretAspect($name1, $name2, $aspectName)
                    ];
                }
            }
        }
        
        return $aspects;
    }

    /**
     * Calculate Angle Between Two Planets
     */
    private function calculateAngleBetween($deg1, $deg2)
    {
        $diff = abs($deg1 - $deg2) % 360;
        return $diff > 180 ? 360 - $diff : $diff;
    }

    /**
     * Get Zodiac Sign from Longitude
     */
    private function getZodiacSign($longitude)
    {
        $signs = ['Aries', 'Taurus', 'Gemini', 'Cancer', 'Leo', 'Virgo', 
                'Libra', 'Scorpio', 'Sagittarius', 'Capricorn', 'Aquarius', 'Pisces'];
        $index = floor(($longitude % 360) / 30);
        return $signs[$index];
    }

    /**
     * Interpret Planetary Aspect
     */
    private function interpretAspect($planet1, $planet2, $aspect)
    {
        $interpretations = [
            'Mars-Jupiter-trine' => 'Aggressive risk-on impulse—good for momentum trades',
            'Mercury-Uranus-opposition' => 'High-velocity headline risk, expect sudden spikes',
            'Sun-Pluto-square' => 'Distribution pressure, leadership may wobble',
            'Mars-Uranus-square' => 'Accident-prone tape, wide stops recommended',
            'Venus-Jupiter-sextile' => 'Supportive tone for consumption stocks',
        ];
        
        $key = "$planet1-$planet2-$aspect";
        return $interpretations[$key] ?? "Moderate influence on market sentiment";
    }

    /**
     * Generate Weekly Acts (Narrative Structure)
     */
    private function generateWeeklyActs($weekData, $marketAnalysis)
    {
        $dates = array_keys($weekData);
        
        // Determine overall week theme
        $theme = $this->determineWeeklyTheme($weekData, $marketAnalysis);
        
        // Act I: Monday-Tuesday (Early Week)
        $actI = $this->generateAct(array_slice($dates, 0, 2), $weekData, $marketAnalysis, 'risk-on engine');
        
        // Act II: Wednesday (Mid-Week Pivot)
        $actII = $this->generateAct(array_slice($dates, 2, 1), $weekData, $marketAnalysis, 'pivot point');
        
        // Act III: Thursday-Friday (Late Week)
        $actIII = $this->generateAct(array_slice($dates, 3, 2), $weekData, $marketAnalysis, 'follow-through');
        
        return [
            'theme' => $theme,
            'act_i' => $actI,
            'act_ii' => $actII,
            'act_iii' => $actIII,
        ];
    }

    /**
     * Determine Weekly Theme
     */
    private function determineWeeklyTheme($weekData, $marketAnalysis)
    {
        $volatilityCount = 0;
        $bullishCount = 0;
        $bearishCount = 0;
        
        foreach ($weekData as $date => $data) {
            $hasVolatileAspect = false;
            foreach ($data['aspects'] as $aspect) {
                if (in_array($aspect['type'], ['opposition', 'square'])) {
                    $hasVolatileAspect = true;
                    break;
                }
            }
            
            if ($hasVolatileAspect) $volatilityCount++;
            
            $sentiment = $marketAnalysis[$date]['market_sentiment'] ?? 'Neutral';
            if (strpos($sentiment, 'Bullish') !== false) $bullishCount++;
            if (strpos($sentiment, 'Bearish') !== false) $bearishCount++;
        }
        
        if ($volatilityCount >= 3) {
            return "High-volatility week with potential for violent reversals. Mid-week pivot likely to forge durable turning point.";
        }
        
        if ($bullishCount > $bearishCount) {
            return "Risk-on momentum week with early strength attempting to build conviction through Friday follow-through.";
        }
        
        if ($bearishCount > $bullishCount) {
            return "Distribution-heavy week with heavier undertones. Expect fade-the-rally dynamics and defensive positioning.";
        }
        
        return "Balanced week featuring rotation and two-way action. Watch for mid-week catalyst to determine direction.";
    }

    /**
     * Generate Act Description
     */
    private function generateAct($dates, $weekData, $marketAnalysis, $actType)
    {
        $days = [];
        
        foreach ($dates as $date) {
            if (!isset($weekData[$date])) continue;
            
            $dayData = $weekData[$date];
            $market = $marketAnalysis[$date] ?? [];
            
            $days[] = [
                'date' => $date,
                'day' => $dayData['day'],
                'bias' => $this->determineDailyBias($dayData, $market),
                'conviction' => $this->calculateConviction($dayData, $market),
                'volatility' => $market['volatility'] ?? 0,
                'sectors' => $this->getSectorTilt($dayData['sun_sign'], $dayData['mercury_sign']),
            ];
        }
        
        return [
            'type' => $actType,
            'days' => $days,
            'summary' => $this->generateActSummary($days, $actType)
        ];
    }

    /**
     * Determine Daily Bias
     */
    private function determineDailyBias($planetaryData, $marketData)
    {
        $sentiment = $marketData['market_sentiment'] ?? 'Neutral';
        $volatility = $marketData['volatility'] ?? 0;
        
        // Check for strong aspects
        $hasPositiveAspect = false;
        $hasNegativeAspect = false;
        
        foreach ($planetaryData['aspects'] as $aspect) {
            if (strpos($aspect['planets'], 'trine') !== false || 
                strpos($aspect['planets'], 'sextile') !== false) {
                $hasPositiveAspect = true;
            }
            if (strpos($aspect['planets'], 'square') !== false || 
                strpos($aspect['planets'], 'opposition') !== false) {
                $hasNegativeAspect = true;
            }
        }
        
        if ($hasPositiveAspect && strpos($sentiment, 'Bullish') !== false) {
            return 'Strong Risk-ON (buy dips)';
        }
        
        if ($hasNegativeAspect && $volatility > 50) {
            return 'Shock / Fade Rallies';
        }
        
        if ($hasNegativeAspect && strpos($sentiment, 'Bearish') !== false) {
            return 'Distribution / Sell Rips';
        }
        
        return 'Neutral / Two-Way Action';
    }

    /**
     * Calculate Conviction Score
     */
    private function calculateConviction($planetaryData, $marketData)
    {
        $baseScore = 50;
        
        // Adjust based on aspects
        foreach ($planetaryData['aspects'] as $aspect) {
            if ($aspect['type'] === 'trine') $baseScore += 10;
            if ($aspect['type'] === 'sextile') $baseScore += 5;
            if ($aspect['type'] === 'square') $baseScore -= 10;
            if ($aspect['type'] === 'opposition') $baseScore -= 5;
        }
        
        // Adjust based on market sentiment
        $sentiment = $marketData['market_sentiment'] ?? '';
        if (strpos($sentiment, 'Strong') !== false) {
            $baseScore += 15;
        }
        
        return max(0, min(100, $baseScore));
    }

    /**
     * Get Sector Tilt Based on Zodiac Signs
     */
    private function getSectorTilt($sunSign, $mercurySign)
    {
        $sectorMap = [
            'Aries' => ['IT', 'Auto', 'Defence'],
            'Taurus' => ['Materials', 'FMCG'],
            'Gemini' => ['Media', 'IT', 'Telecom'],
            'Cancer' => ['FMCG', 'Realty'],
            'Leo' => ['Energy', 'Auto'],
            'Virgo' => ['Pharma', 'Logistics'],
            'Libra' => ['Banks', 'FinServ'],
            'Scorpio' => ['Pharma', 'Chemicals'],
            'Sagittarius' => ['IT', 'Travel', 'NBFC'],
            'Capricorn' => ['Banks', 'Infra', 'Industrials'],
            'Aquarius' => ['Tech', 'Renewables', 'Power'],
            'Pisces' => ['Pharma', 'Biotech'],
        ];
        
        $sectors = array_merge(
            $sectorMap[$sunSign] ?? ['IT'],
            $sectorMap[$mercurySign] ?? ['Pharma']
        );
        
        return array_unique($sectors);
    }

    /**
     * Generate Daily Predictions
     */
    private function generateDailyPredictions($weekData, $marketAnalysis)
    {
        $predictions = [];
        
        foreach ($weekData as $date => $data) {
            $market = $marketAnalysis[$date] ?? [];
            
            $predictions[] = [
                'date' => $date,
                'day' => $data['day'],
                'sun_sign' => $data['sun_sign'],
                'mercury_sign' => $data['mercury_sign'],
                'aspects' => $data['aspects'],
                'bias' => $this->determineDailyBias($data, $market),
                'conviction' => $this->calculateConviction($data, $market),
                'volatility' => $market['volatility'] ?? 0,
                'market_sentiment' => $market['market_sentiment'] ?? 'Neutral',
                'sectors' => $this->getSectorTilt($data['sun_sign'], $data['mercury_sign']),
                'top_stocks' => $this->getTopStocks($data['sun_sign'], $data['mercury_sign']),
                'bullets' => $this->generateDailyBullets($data['aspects']),
                'tactics' => $this->generateTradingTactics($data, $market),
            ];
        }
        
        return $predictions;
    }

    /**
     * Get Top Stock Recommendations
     */
    private function getTopStocks($sunSign, $mercurySign)
    {
        $stockMap = [
            'IT' => ['TCS', 'INFY', 'HCLTECH'],
            'Pharma' => ['SUNPHARMA', 'DRREDDY', 'CIPLA'],
            'Banks' => ['HDFCBANK', 'ICICIBANK', 'AXISBANK'],
            'Auto' => ['TATAMOTORS', 'MARUTI', 'M&M'],
            'Energy' => ['RELIANCE', 'ONGC', 'BPCL'],
        ];
        
        $sectors = $this->getSectorTilt($sunSign, $mercurySign);
        $stocks = [];
        
        foreach ($sectors as $sector) {
            if (isset($stockMap[$sector])) {
                $stocks = array_merge($stocks, $stockMap[$sector]);
            }
        }
        
        return array_slice(array_unique($stocks), 0, 5);
    }

    /**
     * Generate Daily Interpretation Bullets
     */
    private function generateDailyBullets($aspects)
    {
        $bullets = [];
        
        foreach ($aspects as $aspect) {
            $bullets[] = $aspect['interpretation'];
        }
        
        if (empty($bullets)) {
            $bullets[] = 'Neutral planetary configuration—rely on technical levels and market flow.';
        }
        
        return $bullets;
    }

    /**
     * Generate Trading Tactics
     */
    private function generateTradingTactics($planetaryData, $marketData)
    {
        $tactics = [];
        
        $volatility = $marketData['volatility'] ?? 0;
        $sentiment = $marketData['market_sentiment'] ?? '';
        
        if (strpos($sentiment, 'Bullish') !== false) {
            $tactics[] = "Buy strength in sector leaders during first 30 minutes";
            $tactics[] = "Use ATM calls for momentum plays";
        }
        
        if (strpos($sentiment, 'Bearish') !== false) {
            $tactics[] = "Sell into rallies, use 1-step OTM puts";
            $tactics[] = "Book profits early, avoid overnight holds";
        }
        
        if ($volatility > 50) {
            $tactics[] = "Wait 15-30 mins after open to confirm direction";
            $tactics[] = "Use wider stops or reduce position size";
            $tactics[] = "Avoid fresh entries during mid-day spikes";
        }
        
        return $tactics;
    }

    /**
     * Generate Sector Recommendations
     */
    private function generateSectorRecommendations($weekData)
    {
        $sectorCount = [];
        
        foreach ($weekData as $data) {
            $sectors = $this->getSectorTilt($data['sun_sign'], $data['mercury_sign']);
            foreach ($sectors as $sector) {
                $sectorCount[$sector] = ($sectorCount[$sector] ?? 0) + 1;
            }
        }
        
        arsort($sectorCount);
        
        $recommendations = [];
        foreach (array_slice($sectorCount, 0, 5, true) as $sector => $count) {
            $recommendations[] = [
                'sector' => $sector,
                'frequency' => $count,
                'strength' => round(($count / count($weekData)) * 100, 2)
            ];
        }
        
        return $recommendations;
    }

    /**
     * Generate Options Strategy
     */
    private function generateOptionsStrategy($weekData, $marketAnalysis)
    {
        $avgVolatility = 0;
        $bullishDays = 0;
        $bearishDays = 0;
        
        foreach ($marketAnalysis as $data) {
            $avgVolatility += $data['volatility'] ?? 0;
            
            $sentiment = $data['market_sentiment'] ?? '';
            if (strpos($sentiment, 'Bullish') !== false) $bullishDays++;
            if (strpos($sentiment, 'Bearish') !== false) $bearishDays++;
        }
        
        $avgVolatility = $avgVolatility / max(count($marketAnalysis), 1);
        
        $strategy = [
            'primary_strategy' => '',
            'entry_windows' => [],
            'index_focus' => '',
            'risk_management' => [],
        ];
        
        if ($bullishDays > $bearishDays) {
            $strategy['primary_strategy'] = 'Buy ATM Calls on dips, sell 50-70% into strength';
            $strategy['entry_windows'] = ['09:20-09:45 on gap-downs', '10:30-11:00 on consolidation'];
            $strategy['index_focus'] = 'NIFTY (broader participation)';
        } else {
            $strategy['primary_strategy'] = 'Sell ATM to 1-step OTM Puts on rallies';
            $strategy['entry_windows'] = ['09:35-10:15 on gap-ups', '11:00-12:00 after lower-highs'];
            $strategy['index_focus'] = 'BANKNIFTY (stress plays better)';
        }
        
        if ($avgVolatility > 50) {
            $strategy['risk_management'] = [
                'Book 50-70% by early afternoon',
                'Avoid fresh entries during violent spikes',
                'Use wider stops (1.5x normal)',
            ];
        } else {
            $strategy['risk_management'] = [
                'Standard stop-loss at 20-25%',
                'Trail profits above 40% gains',
                'Hold overnight only with strong conviction',
            ];
        }
        
        return $strategy;
    }

    /**
     * Generate Act Summary
     */
    private function generateActSummary($days, $actType)
    {
        if (empty($days)) return "No data available for this period.";
        
        $avgConviction = array_sum(array_column($days, 'conviction')) / count($days);
        $avgVolatility = array_sum(array_column($days, 'volatility')) / count($days);
        
        if ($actType === 'risk-on engine') {
            return sprintf(
                "Early week attempts to establish %s bias with conviction of %.0f%%. Volatility at %.0f%% suggests %s environment.",
                $days[0]['bias'],
                $avgConviction,
                $avgVolatility,
                $avgVolatility > 50 ? 'choppy' : 'steady'
            );
        }
        
        if ($actType === 'pivot point') {
            return sprintf(
                "Mid-week pivot day with %.0f%% conviction and %.0f%% volatility. Critical juncture for direction confirmation.",
                $avgConviction,
                $avgVolatility
            );
        }
        
        return sprintf(
            "Late week follow-through phase with %.0f%% conviction. %s momentum into weekly close.",
            $avgConviction,
            $avgConviction > 60 ? 'Strong' : 'Moderate'
        );
    }
}
