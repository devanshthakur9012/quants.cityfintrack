<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Futures15MinCandle extends Model
{
    use HasFactory;

    protected $table = 'futures_15min_candles';

    protected $fillable = [
        'underlying',
        'symbol',
        'token',
        'data_date',
        'candle_time',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
    ];

    protected $casts = [
        'data_date' => 'date',
        'candle_time' => 'datetime',
        'open' => 'decimal:2',
        'high' => 'decimal:2',
        'low' => 'decimal:2',
        'close' => 'decimal:2',
        'volume' => 'integer',
        'oi' => 'integer',
    ];

    // Scopes
    public function scopeByUnderlying($query, string $underlying)
    {
        return $query->where('underlying', $underlying);
    }

    public function scopeByToken($query, string $token)
    {
        return $query->where('token', $token);
    }

    public function scopeByDate($query, string $date)
    {
        return $query->where('data_date', $date);
    }

    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('data_date', [$startDate, $endDate]);
    }

    // Relationship
    public function instrument()
    {
        return $this->belongsTo(FuturesInstrument::class, 'token', 'token');
    }
}
