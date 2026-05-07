<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ExpiryConfig extends Model
{
    protected $table = 'expiry_configs';
    
    protected $fillable = [
        'name',
        'scope',
        'symbol',
        'supertrend_atr_period',
        'supertrend_multiplier',
        'is_active',
        'description'
    ];

    protected $casts = [
        'supertrend_atr_period' => 'integer',
        'supertrend_multiplier' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    /**
     * Get configuration for a specific symbol (or global default)
     */
    public static function getForSymbol($symbol = null)
    {
        $cacheKey = $symbol ? "expiry_config_{$symbol}" : "expiry_config_default";

        return Cache::remember($cacheKey, 3600, function () use ($symbol) {
            if ($symbol) {
                $symbolConfig = self::where('scope', 'symbol')
                    ->where('symbol', $symbol)
                    ->where('is_active', true)
                    ->first();

                if ($symbolConfig) {
                    return $symbolConfig;
                }
            }

            // Return global default
            return self::where('scope', 'global')
                ->where('name', 'default')
                ->where('is_active', true)
                ->first() ?? self::getDefaultConfig();
        });
    }

    /**
     * Get default configuration
     */
    private static function getDefaultConfig()
    {
        return (object)[
            'supertrend_atr_period' => 10,
            'supertrend_multiplier' => 3.0
        ];
    }

    /**
     * Clear cache
     */
    public static function clearCache($symbol = null)
    {
        if ($symbol) {
            Cache::forget("expiry_config_{$symbol}");
        }
        Cache::forget('expiry_config_default');
    }
}