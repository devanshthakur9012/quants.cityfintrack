<?php
// app/Models/OptionIVData.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class OptionIVData extends Model
{
    protected $table = 'option_iv_data';
    
    protected $fillable = [
        'broker_api_id',
        'symbol',
        'trading_symbol',
        'expiry',
        'strike',
        'option_type',
        'timestamp',
        'ltp',
        'iv',
        'zerodha_iv',        // ← ADDED: Zerodha's IV for comparison
        'oi',
        'volume',
        'bid',
        'ask',
        'delta',
        'gamma',
        'theta',
        'vega',
        'atm_position',
        'future_price',
        'days_to_expiry',    // ← ADDED: Days remaining to expiry
        'risk_free_rate',    // ← ADDED: Risk-free rate used in IV calculation
    ];
    
    protected $casts = [
        'expiry' => 'date',
        'timestamp' => 'datetime',
        'strike' => 'decimal:2',
        'ltp' => 'decimal:2',
        'iv' => 'decimal:6',           // Changed from 4 to 6 for more precision
        'zerodha_iv' => 'decimal:6',   // ← ADDED
        'bid' => 'decimal:2',
        'ask' => 'decimal:2',
        'delta' => 'decimal:6',
        'gamma' => 'decimal:6',
        'theta' => 'decimal:6',
        'vega' => 'decimal:6',
        'future_price' => 'decimal:2',
        'days_to_expiry' => 'integer',  // ← ADDED
        'risk_free_rate' => 'decimal:4', // ← ADDED
    ];
    
    /**
     * Get broker relationship
     */
    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
    
    /**
     * Scope: Get ATM options only
     */
    public function scopeAtmOnly($query)
    {
        return $query->whereIn('atm_position', ['ATM-1', 'ATM', 'ATM+1']);
    }
    
    /**
     * Scope: Get data for specific symbol and expiry
     */
    public function scopeForSymbolExpiry($query, $symbol, $expiry)
    {
        return $query->where('symbol', $symbol)
                     ->where('expiry', $expiry);
    }
    
    /**
     * Scope: Get data within time range
     */
    public function scopeInTimeRange($query, $startTime, $endTime)
    {
        return $query->whereBetween('timestamp', [$startTime, $endTime]);
    }
    
    /**
     * Get IV data from X minutes ago
     */
    public static function getHistoricalIV($symbol, $expiry, $strikes, $minutesAgo)
    {
        $targetTime = Carbon::now()->subMinutes($minutesAgo);
        
        $data = self::where('symbol', $symbol)
            ->where('expiry', $expiry)
            ->whereIn('strike', $strikes)
            ->where('timestamp', '>=', $targetTime->copy()->subMinutes(2))
            ->where('timestamp', '<=', $targetTime->copy()->addMinutes(2))
            ->get();
        
        $avgCE = $data->where('option_type', 'CE')->avg('iv') ?? 0;
        $avgPE = $data->where('option_type', 'PE')->avg('iv') ?? 0;
        
        return [
            'avg_ce' => round($avgCE, 4),
            'avg_pe' => round($avgPE, 4),
            'avg_total' => round(($avgCE + $avgPE) / 2, 4),
            'timestamp' => $targetTime,
        ];
    }
    
    /**
     * Get baseline IV for regime calculation
     */
    public static function getBaselineIV($symbol, $expiry)
    {
        $baselineDays = config('iv_analysis.historical.baseline_days', 10);
        
        $avgIV = self::where('symbol', $symbol)
            ->where('expiry', $expiry)
            ->where('timestamp', '>=', now()->subDays($baselineDays))
            ->atmOnly()
            ->avg('iv');
        
        return $avgIV ?? 0;
    }
    
    /**
     * Get IV as percentage (e.g., 0.1576 → 15.76%)
     */
    public function getIvPercentageAttribute()
    {
        return $this->iv ? round($this->iv * 100, 2) : null;
    }
    
    /**
     * Get Zerodha IV as percentage
     */
    public function getZerodhaIvPercentageAttribute()
    {
        return $this->zerodha_iv ? round($this->zerodha_iv * 100, 2) : null;
    }
    
    /**
     * Scope: Get CE (Call) options only
     */
    public function scopeCalls($query)
    {
        return $query->where('option_type', 'CE');
    }
    
    /**
     * Scope: Get PE (Put) options only
     */
    public function scopePuts($query)
    {
        return $query->where('option_type', 'PE');
    }
}