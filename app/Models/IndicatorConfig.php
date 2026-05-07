<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class IndicatorConfig extends Model
{
    protected $fillable = [
        'name',
        'scope',
        'trading_symbol',
        // Supertrend
        'supertrend_atr_period',
        'supertrend_multiplier',
        // Donchian
        'donchian_high_period',
        'donchian_low_period',
        'donchian_risk_reward',
        // RSI
        'rsi_period',
        'rsi_overbought',
        'rsi_oversold',
        // MACD
        'macd_fast_period',
        'macd_slow_period',
        'macd_signal_period',
        // VWAP
        'vwap_reset_daily',
        'vwap_band_multiplier',
        'vwap_band_period',
        'vwap_distance_percent',
        // Meta
        'is_active',
        'description'
    ];

    protected $casts = [
        'supertrend_atr_period' => 'integer',
        'supertrend_multiplier' => 'decimal:2',
        'donchian_high_period' => 'integer',
        'donchian_low_period' => 'integer',
        'donchian_risk_reward' => 'decimal:2',
        'rsi_period' => 'integer',
        'rsi_overbought' => 'decimal:2',
        'rsi_oversold' => 'decimal:2',
        'macd_fast_period' => 'integer',
        'macd_slow_period' => 'integer',
        'macd_signal_period' => 'integer',
        'vwap_reset_daily' => 'boolean',
        'vwap_band_multiplier' => 'decimal:2',
        'vwap_band_period' => 'integer',
        'vwap_distance_percent' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /**
     * Get configuration for a specific symbol (or global default)
     */
    public static function getForSymbol($tradingSymbol = null)
    {
        $cacheKey = $tradingSymbol 
            ? "indicator_config_{$tradingSymbol}" 
            : "indicator_config_default";

        return Cache::remember($cacheKey, 3600, function () use ($tradingSymbol) {
            if ($tradingSymbol) {
                $symbolConfig = self::where('scope', 'symbol')
                    ->where('trading_symbol', $tradingSymbol)
                    ->where('is_active', true)
                    ->first();

                if ($symbolConfig) {
                    return $symbolConfig;
                }
            }

            return self::where('scope', 'global')
                ->where('name', 'default')
                ->where('is_active', true)
                ->firstOrFail();
        });
    }

    /**
     * Get global default configuration
     */
    public static function getDefault()
    {
        return Cache::remember('indicator_config_default', 3600, function () {
            return self::where('scope', 'global')
                ->where('name', 'default')
                ->where('is_active', true)
                ->firstOrFail();
        });
    }

    /**
     * Clear configuration cache
     */
    public static function clearCache($tradingSymbol = null)
    {
        if ($tradingSymbol) {
            Cache::forget("indicator_config_{$tradingSymbol}");
        }
        Cache::forget('indicator_config_default');
    }

    /**
     * Boot method to clear cache on update
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($config) {
            self::clearCache($config->trading_symbol);
        });

        static::deleted(function ($config) {
            self::clearCache($config->trading_symbol);
        });
    }

    /**
     * Get all indicator descriptions for tooltips
     */
    public static function getIndicatorDescriptions()
    {
        return [
            'supertrend' => [
                'atr_period' => 'Number of periods for ATR calculation. Higher values make the indicator less sensitive to short-term price changes.',
                'multiplier' => 'Multiplier applied to ATR to calculate bands. Higher values create wider bands.'
            ],
            'donchian' => [
                'high_period' => 'Number of periods to look back for the highest high (upper channel). Industry standard: 20.',
                'low_period' => 'Number of periods to look back for the lowest low (lower channel). Industry standard: 20.',
                'risk_reward' => 'Risk-to-reward ratio for target calculation. 2.0 means target is twice the risk (stop loss distance).'
            ],
            'rsi' => [
                'period' => 'Number of periods for RSI calculation. Industry standard: 14.',
                'overbought' => 'RSI level considered overbought. Above this signals potential sell. Industry standard: 70.',
                'oversold' => 'RSI level considered oversold. Below this signals potential buy. Industry standard: 30.'
            ],
            'macd' => [
                'fast_period' => 'Fast EMA period. Industry standard: 12.',
                'slow_period' => 'Slow EMA period. Industry standard: 26.',
                'signal_period' => 'Signal line EMA period. Industry standard: 9.'
            ],
            'vwap' => [
                'reset_daily' => 'Reset VWAP calculation at market open (9:15 AM) each day. Recommended for intraday trading. When disabled, VWAP continues across sessions.',
                'band_multiplier' => 'Standard deviation multiplier for VWAP bands. 1.0 = 1 standard deviation. Higher values create wider bands.',
                'band_period' => 'Period for calculating standard deviation for VWAP bands. Industry standard: 20.',
                'distance_percent' => 'Minimum percentage distance from VWAP to trigger Gap-Up/Gap-Down signal. Default: 0.4%. Higher values make signals more conservative.'
            ]
        ];
    }
}