<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuturesTradingSignal extends Model
{
    use HasFactory;
    protected $table = 'futures_trading_signals';

    protected $fillable = [
        'underlying',
        'symbol',
        'token',
        'data_date',
        'candle_time',
        'candle_index',
        'open',
        'high',
        'low',
        'close',
        'volume',
        'oi',
        'ha_open',
        'ha_close',
        'ha_high',
        'ha_low',
        'ha_color',
        'ha_strength',
        'structure_type',
        'structure_vol_change',
        'raw_signal',
        'oi_signal',
        'final_signal',
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
        'ha_open' => 'decimal:2',
        'ha_close' => 'decimal:2',
        'ha_high' => 'decimal:2',
        'ha_low' => 'decimal:2',
        'ha_strength' => 'decimal:4',
        'structure_vol_change' => 'decimal:4',
    ];

    // Scopes
    public function scopeByUnderlying($query, string $underlying)
    {
        return $query->where('underlying', $underlying);
    }

    public function scopeByDate($query, string $date)
    {
        return $query->where('data_date', $date);
    }

    public function scopeDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('data_date', [$startDate, $endDate]);
    }

    public function scopeBySignal($query, string $signal)
    {
        return $query->where('final_signal', $signal);
    }

    public function scopeByStructure($query, string $structure)
    {
        return $query->where('structure_type', $structure);
    }

    // Relationship
    public function instrument()
    {
        return $this->belongsTo(FuturesInstrument::class, 'token', 'token');
    }
}
