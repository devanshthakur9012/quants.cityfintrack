<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuturesInstrument extends Model
{
    use HasFactory;
    protected $table = 'futures_instruments';

        protected $fillable = [
        'underlying',
        'symbol',
        'token',
        'exchange',
        'expiry_date',
        'lot_size',
        'tick_size',
        'instrument_type',
        'is_active',
        'last_synced_at'
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        'tick_size' => 'decimal:4',
    ];

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByUnderlying($query, string $underlying)
    {
        return $query->where('underlying', $underlying);
    }

    public function scopeByToken($query, string $token)
    {
        return $query->where('token', $token);
    }

    // Relationships
    public function candles()
    {
        return $this->hasMany(Futures15MinCandle::class, 'token', 'token');
    }

    public function signals()
    {
        return $this->hasMany(FuturesTradingSignal::class, 'token', 'token');
    }

    // Helper methods
    public static function deactivateAll(string $underlying)
    {
        return self::where('underlying', $underlying)
            ->update(['is_active' => false]);
    }

    public static function getActiveUnderlying()
    {
        return self::active()
            ->distinct()
            ->pluck('underlying')
            ->toArray();
    }
}
