<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SymbolMonitored extends Model
{
    use HasFactory;

    protected $table = 'symbols_monitored';

    protected $fillable = [
        'broker_api_id',
        'symbol',
        'underlying_name',
        'exchange',
        'instrument_type',
        'interval',
        'trading_symbol',
        'instrument_token',
        'is_active',
        'last_synced_at'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime'
    ];

    /**
     * Relationship: Broker
     */
    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    /**
     * Scope: Active symbols only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: By broker
     */
    public function scopeByBroker($query, $brokerId)
    {
        return $query->where('broker_api_id', $brokerId);
    }

    /**
     * Scope: By exchange
     */
    public function scopeByExchange($query, $exchange)
    {
        return $query->where('exchange', $exchange);
    }

    /**
     * Sync instrument details from Zerodha
     */
    public function syncInstrumentDetails()
    {
        $instrument = ZerodhaInstrument::where('name', $this->symbol)
            ->where('exchange', $this->exchange)
            ->where('instrument_type', $this->instrument_type)
            ->first();

        if ($instrument) {
            $this->update([
                'trading_symbol' => $instrument->trading_symbol,
                'instrument_token' => $instrument->instrument_token,
                'last_synced_at' => now()
            ]);

            return true;
        }

        return false;
    }

    public function scopeByInterval($query, $interval)
    {
        return $query->where('interval', $interval);
    }
    
}