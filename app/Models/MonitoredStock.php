<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoredStock extends Model
{
    use HasFactory;
    protected $table = 'monitored_stocks';

    protected $fillable = [
        'trading_symbol',
        'exchange',
        'instrument_token',
        'intervals',
        'is_active',
        'last_fetched_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_fetched_at' => 'datetime'
    ];

    /**
     * Get intervals as array
     */
    public function getIntervalsArrayAttribute()
    {
        return explode(',', $this->intervals);
    }

    /**
     * Get active stocks
     */
    public static function getActiveStocks()
    {
        return self::where('is_active', true)->get();
    }

    /**
     * Stock data relationship
     */
    public function stockData()
    {
        return $this->hasMany(StockData::class, 'trading_symbol', 'trading_symbol');
    }
}
