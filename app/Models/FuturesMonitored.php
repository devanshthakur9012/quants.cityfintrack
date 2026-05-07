<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuturesMonitored extends Model
{
    use HasFactory;
    
    protected $table = 'futures_monitored';

    protected $fillable = [
        'trading_symbol',
        'exchange',
        'instrument_token',
        'intervals',
        'is_active',
        'last_fetched_at',
        'expiry_date',
        'lot_size'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime',
        'expiry_date' => 'date'
    ];

    /**
     * Get intervals as array
     */
    public function getIntervalsArrayAttribute()
    {
        return explode(',', $this->intervals);
    }

    /**
     * Get active futures
     */
    public static function getActiveFutures()
    {
        return self::where('is_active', true)->get();
    }

    /**
     * Futures data relationship
     */
    public function futuresData()
    {
        return $this->hasMany(FuturesData::class, 'trading_symbol', 'trading_symbol');
    }
}