<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioSellOrderConfig extends Model
{
    protected $fillable = [
        'user_id',
        'symbol_type',
        'old_position_profit_percent',
        'fresh_position_profit_percent',
        'is_active',
    ];

    protected $casts = [
        'old_position_profit_percent' => 'decimal:2',
        'fresh_position_profit_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get all active configs for a user
     */
    public static function getActiveConfigsForUser($userId)
    {
        return static::where('user_id', $userId)
            ->where('is_active', true)
            ->get()
            ->keyBy('symbol_type'); // Returns collection keyed by symbol_type
    }

    /**
     * Get config for specific symbol type
     */
    public static function getForSymbolType($userId, $symbolType)
    {
        return static::where('user_id', $userId)
            ->where('symbol_type', $symbolType)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if symbol type has config
     */
    public static function hasConfig($userId, $symbolType)
    {
        return static::where('user_id', $userId)
            ->where('symbol_type', $symbolType)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Extract symbol type from trading symbol
     * Examples: NIFTY2531922500CE -> CE, NIFTY2531922500PE -> PE
     */
    public static function extractSymbolType($tradingSymbol)
    {
        // Check if symbol ends with CE or PE
        if (str_ends_with($tradingSymbol, 'CE')) {
            return 'CE';
        } elseif (str_ends_with($tradingSymbol, 'PE')) {
            return 'PE';
        }
        
        // Default to EQUITY for other symbols
        return 'EQUITY';
    }

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}