<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SymbolLtps extends Model
{
    use HasFactory;

    protected $fillable = [
        'trade_date',
        'symbol_token',
        'symbol_name',
        'ltp',
        'highest_ltp',
        'highest_time',
        'last_updated_at'
    ];
    
    protected $casts = [
        'ltp' => 'decimal:2',
        'highest_ltp' => 'decimal:2',
        'last_updated_at' => 'datetime',
        'highest_time' => 'string'
    ];

    /**
     * Get LTP by symbol token
     */
    public static function getLtpByToken($token)
    {
        return self::where('symbol_token', $token)->value('ltp');
    }

    /**
     * Get LTP by symbol name
     */
    public static function getLtpByName($symbolName)
    {
        return self::where('symbol_name', $symbolName)->value('ltp');
    }

    /**
     * Get latest LTP data by token
     */
    public static function getLatestByToken($token)
    {
        return self::where('symbol_token', $token)
                  ->orderBy('last_updated_at', 'desc')
                  ->first();
    }

    /**
     * Get latest LTP data by symbol name
     */
    public static function getLatestByName($symbolName)
    {
        return self::where('symbol_name', $symbolName)
                  ->orderBy('last_updated_at', 'desc')
                  ->first();
    }

    /**
     * Check if LTP data is fresh (updated within specified minutes)
     */
    public function isFresh($minutes = 5)
    {
        return $this->last_updated_at && 
               $this->last_updated_at->diffInMinutes(now()) <= $minutes;
    }

    /**
     * Get all symbols with fresh LTP data
     */
    public static function getFreshLtps($minutes = 5)
    {
        return self::where('last_updated_at', '>=', now()->subMinutes($minutes))
                  ->where('ltp', '>', 0)
                  ->get();
    }
}
