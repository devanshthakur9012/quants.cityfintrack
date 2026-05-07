<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InstrumentHistoricalData extends Model
{
    protected $table = 'instrument_historical_data';

    protected $fillable = [
        'underlying',
        'symbol',
        'token',
        'type',
        'data_date',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'price_change',
        'oi_change',
        'oi_change_pct',
        'strike_price',
        'strike_position',
        'trend',
        'futures_score',
        'options_score',
        'final_score'
    ];

    protected $casts = [
        'data_date' => 'date',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'strike_price' => 'decimal:2',
        'price_change' => 'decimal:2',
        'oi_change_pct' => 'decimal:2',
    ];

    // Scopes
    public function scopeByUnderlying(Builder $query, string $underlying)
    {
        return $query->where('underlying', $underlying);
    }

    public function scopeByDate(Builder $query, string $date)
    {
        return $query->where('data_date', $date);
    }

    public function scopeByType(Builder $query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeFutures(Builder $query)
    {
        return $query->where('type', 'FUT');
    }

    public function scopeCalls(Builder $query)
    {
        return $query->where('type', 'CE');
    }

    public function scopePuts(Builder $query)
    {
        return $query->where('type', 'PE');
    }

    public function scopeByToken(Builder $query, string $token)
    {
        return $query->where('token', $token);
    }

    // Helper methods
    public static function getLatestByToken(string $token, string $beforeDate = null)
    {
        $query = self::byToken($token);
        
        if ($beforeDate) {
            $query->where('data_date', '<', $beforeDate);
        }
        
        return $query->latest('data_date')->first();
    }

    public static function getChainByDate(string $underlying, string $date)
    {
        return self::byUnderlying($underlying)
            ->byDate($date)
            ->orderBy('type')
            ->orderBy('strike_position')
            ->get()
            ->groupBy('type');
    }

    public static function getTrendData(string $underlying, string $date)
    {
        return self::byUnderlying($underlying)
            ->byDate($date)
            ->whereNotNull('trend')
            ->first();
    }
}