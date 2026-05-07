<?php
// config/iv_analysis.php

return [
    // IV Regime Thresholds
    'regime' => [
        'low_threshold' => 80,      // Below 80% of historical average = LOW
        'high_threshold' => 120,    // Above 120% of historical average = HIGH
    ],
    
    // IV Trend Detection
    'trend' => [
        'rising_threshold_5m' => 0.5,   // If IV increased by 0.5+ in 5min
        'rising_threshold_15m' => 1.0,  // If IV increased by 1.0+ in 15min
        'falling_threshold_5m' => -0.5,
        'falling_threshold_15m' => -1.0,
    ],
    
    // IV Speed Detection
    'speed' => [
        'fast_threshold' => 1.0,    // If 5min change > 1.0 = FAST
    ],
    
    // Historical Analysis
    'historical' => [
        'baseline_days' => 10,      // Use 10 days for baseline
        'lookback_5min' => 5,       // Minutes to look back
        'lookback_15min' => 15,
        'lookback_1day' => 1440,    // 1 day in minutes
    ],
    
    // ATM Strike Selection
    'atm' => [
        'range' => 1,               // ATM-1, ATM, ATM+1
        'strike_intervals' => [
            'NIFTY' => 50,
            'BANKNIFTY' => 100,
            'FINNIFTY' => 50,
            'MIDCPNIFTY' => 25,
        ],
    ],
    
    // Data Collection
    'collection' => [
        'interval_minutes' => 5,     // Collect data every 5 minutes
        'market_open' => '09:15:00',
        'market_close' => '15:30:00',
    ],
];