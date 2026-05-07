<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrokerLiveLtpConfig extends Model
{
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'symbol_type',
        'profit_percent',
        'is_active',
    ];

    protected $casts = [
        'profit_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get configs for specific broker
     */
    public static function getForBroker($brokerId)
    {
        return static::where('broker_api_id', $brokerId)
            ->where('is_active', true)
            ->get()
            ->keyBy('symbol_type');
    }

    /**
     * Extract symbol type from trading symbol
     */
    public static function extractSymbolType($tradingSymbol)
    {
        if (str_ends_with($tradingSymbol, 'CE')) {
            return 'CE';
        } elseif (str_ends_with($tradingSymbol, 'PE')) {
            return 'PE';
        }
        
        return null; // Not an option
    }

    /**
     * Check if symbol matches config type
     */
    public static function symbolMatchesType($tradingSymbol, $configType)
    {
        if ($configType === 'BOTH') {
            return str_ends_with($tradingSymbol, 'CE') || str_ends_with($tradingSymbol, 'PE');
        }
        
        return str_ends_with($tradingSymbol, $configType);
    }

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function brokerApi()
    {
        return $this->belongsTo(BrokerApi::class);
    }
}