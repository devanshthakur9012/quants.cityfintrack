<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InstrumentChain extends Model
{
    protected $table = 'instrument_chains';

    protected $fillable = [
        'underlying',
        'symbol',
        'token',
        'type',
        'exchange',
        'strike_price',
        'strike_position',
        'is_atm',
        'expiry_date',
        'lot_size',
        'tick_size',
        'current_price',
        'step_value',
        'is_active',
        'generated_at'
    ];

    protected $casts = [
        'strike_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'step_value' => 'decimal:2',
        'tick_size' => 'decimal:4',
        'is_atm' => 'boolean',
        'is_active' => 'boolean',
        'expiry_date' => 'date',
        'generated_at' => 'datetime',
    ];

    // Scopes
    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByUnderlying(Builder $query, string $underlying)
    {
        return $query->where('underlying', $underlying);
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

    public function scopeAtm(Builder $query)
    {
        return $query->where('is_atm', true);
    }

    public function scopeByStrikePosition(Builder $query, int $position)
    {
        return $query->where('strike_position', $position);
    }

    // Helper methods
    public static function getChainForUnderlying(string $underlying)
    {
        return self::active()
            ->byUnderlying($underlying)
            ->orderBy('strike_position')
            ->orderBy('type')
            ->get()
            ->groupBy('strike_position');
    }

    public static function getFutureContract(string $underlying)
    {
        return self::active()
            ->byUnderlying($underlying)
            ->futures()
            ->first();
    }

    public static function getOptionsAtStrike(string $underlying, float $strikePrice)
    {
        $options = self::active()
            ->byUnderlying($underlying)
            ->where('strike_price', $strikePrice)
            ->whereIn('type', ['CE', 'PE'])
            ->get();

        return [
            'CE' => $options->firstWhere('type', 'CE'),
            'PE' => $options->firstWhere('type', 'PE')
        ];
    }

    public static function deactivateOldRecords(string $underlying)
    {
        return self::byUnderlying($underlying)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}