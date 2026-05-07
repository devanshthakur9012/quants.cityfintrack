<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class OptionStrikeIndividual extends Model
{
    protected $table = 'option_strikes_individual';

    protected $fillable = [
        'broker_api_id',
        'underlying_symbol',
        'trading_symbol',
        'option_type',
        'strike_price',
        'strike_position',
        'expiry',
        'expiry_date',
        'trading_date',
        'instrument_token',
        'exchange',
        'lot_size',

        // OI fields
        'daily_oi',
        'daily_oi_prev',
        'daily_oi_change',
        'daily_oi_change_pct',

        // IV fields
        'daily_iv',
        'daily_iv_prev',
        'daily_iv_change',
        'daily_iv_change_pct',

        // Close Price fields
        'daily_close',
        'daily_close_prev',
        'daily_close_change',
        'daily_close_change_pct',

        // OI Signal fields
        'direction',
        'strength',
        'market_bias',

        // IV Signal fields
        'iv_direction',
        'iv_strength',

        // BTST Signal fields (FUT only)
        'btst_signal',
        'btst_confidence',
        'btst_reason',

        'spot_price',
        'is_active',
        'last_synced_at'
    ];

    protected $casts = [
        'strike_price' => 'decimal:2',
        'expiry_date' => 'date',
        'trading_date' => 'date',
        
        // OI casts
        'daily_oi' => 'integer',
        'daily_oi_prev' => 'integer',
        'daily_oi_change' => 'integer',
        'daily_oi_change_pct' => 'decimal:2',
        
        // IV casts
        'daily_iv' => 'decimal:4',
        'daily_iv_prev' => 'decimal:4',
        'daily_iv_change' => 'decimal:4',
        'daily_iv_change_pct' => 'decimal:2',
        
        // Close Price casts
        'daily_close' => 'decimal:2',
        'daily_close_prev' => 'decimal:2',
        'daily_close_change' => 'decimal:2',
        'daily_close_change_pct' => 'decimal:2',
        
        // Other casts
        'spot_price' => 'decimal:2',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        
        // Signal casts
        'btst_confidence' => 'integer',
    ];

    // ==================== RELATIONSHIPS ====================
    
    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    // ==================== SCOPES ====================
    
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUnderlying($query, string $symbol)
    {
        return $query->where('underlying_symbol', strtoupper($symbol));
    }

    public function scopeType($query, string $type)
    {
        return $query->where('option_type', strtoupper($type));
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('trading_date', $date);
    }

    public function scopeForBroker($query, int $brokerId)
    {
        return $query->where('broker_api_id', $brokerId);
    }

    public function scopeCurrentExpiry($query)
    {
        return $query->where('expiry_date', '>=', now())->orderBy('expiry_date', 'ASC');
    }

    // ==================== STATIC QUERY METHODS ====================
    
    /**
     * Get all strikes for a specific date and symbol
     */
    public static function getDailyStrikes(string $underlying, string $date, int $brokerId)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('trading_date', $date)
            ->orderByRaw("FIELD(option_type, 'FUT', 'CE', 'PE')")
            ->orderByRaw("FIELD(strike_position, 'FUT', 'ATM-2', 'ATM-1', 'ATM', 'ATM+1', 'ATM+2')")
            ->get();
    }

    /**
     * Get FUT data for a date
     */
    public static function getFutData(string $underlying, string $date, int $brokerId)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('trading_date', $date)
            ->where('option_type', 'FUT')
            ->first();
    }

    /**
     * Get all CE strikes for a date
     */
    public static function getCEStrikes(string $underlying, string $date, int $brokerId)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('trading_date', $date)
            ->where('option_type', 'CE')
            ->orderByRaw("FIELD(strike_position, 'ATM-2', 'ATM-1', 'ATM', 'ATM+1', 'ATM+2')")
            ->get();
    }

    /**
     * Get all PE strikes for a date
     */
    public static function getPEStrikes(string $underlying, string $date, int $brokerId)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('trading_date', $date)
            ->where('option_type', 'PE')
            ->orderByRaw("FIELD(strike_position, 'ATM-2', 'ATM-1', 'ATM', 'ATM+1', 'ATM+2')")
            ->get();
    }

    /**
     * Get trend for a specific strike over date range
     */
    public static function getStrikeTrend(
        string $underlying,
        string $fromDate,
        string $toDate,
        int $brokerId,
        string $strikePosition,
        string $optionType = 'CE'
    ) {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('strike_position', $strikePosition)
            ->where('option_type', $optionType)
            ->whereBetween('trading_date', [$fromDate, $toDate])
            ->orderBy('trading_date', 'DESC')
            ->get();
    }

    /**
     * Get aggregated summary (sum of all strikes) for CE/PE
     */
    public static function getAggregatedSummary(string $underlying, string $date, int $brokerId)
    {
        $ceStrikes = self::getCEStrikes($underlying, $date, $brokerId);
        $peStrikes = self::getPEStrikes($underlying, $date, $brokerId);
        $futData = self::getFutData($underlying, $date, $brokerId);

        return [
            'date' => $date,
            'underlying' => $underlying,
            'fut' => $futData ? [
                'oi' => $futData->daily_oi,
                'oi_change' => $futData->daily_oi_change,
                'oi_change_pct' => $futData->daily_oi_change_pct,
                'direction' => $futData->direction,
                'strength' => $futData->strength,
                'close' => $futData->daily_close,
                'close_change' => $futData->daily_close_change,
                'close_change_pct' => $futData->daily_close_change_pct,
                'btst_signal' => $futData->btst_signal,
                'btst_confidence' => $futData->btst_confidence,
                'btst_reason' => $futData->btst_reason,
            ] : null,
            'ce' => [
                'total_oi' => $ceStrikes->sum('daily_oi'),
                'total_oi_change' => $ceStrikes->sum('daily_oi_change'),
                'avg_iv' => $ceStrikes->avg('daily_iv'),
                'avg_close' => $ceStrikes->avg('daily_close'),
                'strikes' => $ceStrikes,
            ],
            'pe' => [
                'total_oi' => $peStrikes->sum('daily_oi'),
                'total_oi_change' => $peStrikes->sum('daily_oi_change'),
                'avg_iv' => $peStrikes->avg('daily_iv'),
                'avg_close' => $peStrikes->avg('daily_close'),
                'strikes' => $peStrikes,
            ],
        ];
    }

    // ==================== ACCESSORS ====================
    
    public function getIvPercentageAttribute()
    {
        return $this->daily_iv ? round($this->daily_iv * 100, 2) : null;
    }

    public function getIvPrevPercentageAttribute()
    {
        return $this->daily_iv_prev ? round($this->daily_iv_prev * 100, 2) : null;
    }

    public function getIvTrendAttribute(): string
    {
        if ($this->daily_iv_change === null) return 'NEUTRAL';
        if ($this->daily_iv_change > 0) return 'RISING';
        if ($this->daily_iv_change < 0) return 'FALLING';
        return 'FLAT';
    }

    public function getCloseTrendAttribute(): string
    {
        if ($this->daily_close_change === null) return 'NEUTRAL';
        if ($this->daily_close_change > 0) return 'RISING';
        if ($this->daily_close_change < 0) return 'FALLING';
        return 'FLAT';
    }

    public function getOiTrendAttribute(): string
    {
        if ($this->daily_oi_change === null) return 'NEUTRAL';
        if ($this->daily_oi_change > 0) return 'RISING';
        if ($this->daily_oi_change < 0) return 'FALLING';
        return 'FLAT';
    }

    // ==================== FORMATTED DISPLAYS ====================
    
    public function getFormattedOiAttribute(): string
    {
        if (!$this->daily_oi) return '0';
        
        $oi = $this->daily_oi;
        if ($oi >= 10000000) return number_format($oi / 10000000, 2) . ' Cr';
        if ($oi >= 100000) return number_format($oi / 100000, 2) . ' L';
        if ($oi >= 1000) return number_format($oi / 1000, 2) . ' K';
        
        return number_format($oi);
    }

    public function getFormattedOiChangeAttribute(): string
    {
        if ($this->daily_oi_change === null) return 'N/A';
        
        $change = $this->daily_oi_change;
        $sign = $change >= 0 ? '+' : '';
        
        if (abs($change) >= 10000000) return $sign . number_format($change / 10000000, 2) . ' Cr';
        if (abs($change) >= 100000) return $sign . number_format($change / 100000, 2) . ' L';
        if (abs($change) >= 1000) return $sign . number_format($change / 1000, 2) . ' K';
        
        return $sign . number_format($change);
    }

    public function getFormattedIvChangeAttribute(): string
    {
        if ($this->daily_iv_change_pct === null) return 'N/A';
        
        $sign = $this->daily_iv_change_pct >= 0 ? '+' : '';
        return $sign . number_format($this->daily_iv_change_pct, 2) . '%';
    }

    public function getFormattedCloseChangeAttribute(): string
    {
        if ($this->daily_close_change_pct === null) return 'N/A';
        
        $sign = $this->daily_close_change_pct >= 0 ? '+' : '';
        return $sign . number_format($this->daily_close_change_pct, 2) . '%';
    }

    // ==================== COLOR HELPERS ====================
    
    public function getSignalColorAttribute(): string
    {
        if (!$this->direction) return 'secondary';
        
        switch (strtoupper($this->direction)) {
            case 'BULLISH':
            case 'BUILDUP':
                return 'success';
            case 'BEARISH':
            case 'UNWINDING':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    public function getIvColorAttribute(): string
    {
        if ($this->daily_iv_change === null) return 'secondary';
        
        // Rising IV = Bad (red), Falling IV = Good (green)
        if ($this->daily_iv_change > 0) return 'danger';
        if ($this->daily_iv_change < 0) return 'success';
        
        return 'secondary';
    }

    public function getCloseColorAttribute(): string
    {
        if ($this->daily_close_change === null) return 'secondary';
        
        if ($this->daily_close_change > 0) return 'success';
        if ($this->daily_close_change < 0) return 'danger';
        
        return 'secondary';
    }

    // ==================== BOOLEAN CHECKS ====================
    
    public function oiIncreased(): bool
    {
        return $this->daily_oi_change !== null && $this->daily_oi_change > 0;
    }

    public function oiDecreased(): bool
    {
        return $this->daily_oi_change !== null && $this->daily_oi_change < 0;
    }

    public function ivIncreased(): bool
    {
        return $this->daily_iv_change !== null && $this->daily_iv_change > 0;
    }

    public function ivDecreased(): bool
    {
        return $this->daily_iv_change !== null && $this->daily_iv_change < 0;
    }

    public function closeIncreased(): bool
    {
        return $this->daily_close_change !== null && $this->daily_close_change > 0;
    }

    public function closeDecreased(): bool
    {
        return $this->daily_close_change !== null && $this->daily_close_change < 0;
    }

    public function hasBtstSignal(): bool
    {
        return $this->option_type === 'FUT' && $this->btst_signal !== null;
    }
}