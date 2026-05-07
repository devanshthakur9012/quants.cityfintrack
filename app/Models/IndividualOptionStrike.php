<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndividualOptionStrike extends Model
{
    protected $table = 'individual_option_strikes';
    
    protected $fillable = [
        'broker_api_id',
        'underlying_symbol',
        'trading_symbol',
        'trading_date',
        'option_type',
        'strike_price',
        'strike_position',
        'expiry',
        'expiry_date',
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
        
        // Price fields
        'daily_close',
        'daily_close_prev',
        'daily_close_change',
        'daily_close_change_pct',
        
        // Analysis fields
        'direction',
        'strength',
        'iv_direction',
        'iv_strength',
        
        'spot_price',
        'is_active',
        'last_synced_at'
    ];

    protected $casts = [
        'trading_date' => 'date',
        'expiry_date' => 'date',
        'strike_price' => 'decimal:2',
        
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
        
        // Price casts
        'daily_close' => 'decimal:2',
        'daily_close_prev' => 'decimal:2',
        'daily_close_change' => 'decimal:2',
        'daily_close_change_pct' => 'decimal:2',
        
        'spot_price' => 'decimal:2',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Broker relationship
     */
    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    /**
     * Scope: Active strikes only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by underlying
     */
    public function scopeForUnderlying($query, string $symbol)
    {
        return $query->where('underlying_symbol', $symbol);
    }

    /**
     * Scope: Filter by option type (CE/PE)
     */
    public function scopeType($query, string $type)
    {
        return $query->where('option_type', strtoupper($type));
    }

    /**
     * Scope: Filter by strike position
     */
    public function scopePosition($query, array $positions)
    {
        return $query->whereIn('strike_position', $positions);
    }

    /**
     * Scope: Filter by trading date
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('trading_date', $date);
    }

    /**
     * Scope: Filter by date range
     */
    public function scopeDateRange($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('trading_date', [$fromDate, $toDate]);
    }

    /**
     * Get all strikes for a symbol on a date grouped by position
     */
    public static function getStrikesGrouped(int $brokerId, string $symbol, string $date, array $positions)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', $symbol)
            ->where('trading_date', $date)
            ->whereIn('strike_position', $positions)
            ->get()
            ->groupBy(['option_type', 'strike_position']);
    }

    /**
     * Get CE strikes for merging
     */
    public static function getCEStrikes(int $brokerId, string $symbol, string $date, array $positions)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', $symbol)
            ->where('trading_date', $date)
            ->where('option_type', 'CE')
            ->whereIn('strike_position', $positions)
            ->orderBy('strike_price')
            ->get();
    }

    /**
     * Get PE strikes for merging
     */
    public static function getPEStrikes(int $brokerId, string $symbol, string $date, array $positions)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', $symbol)
            ->where('trading_date', $date)
            ->where('option_type', 'PE')
            ->whereIn('strike_position', $positions)
            ->orderBy('strike_price')
            ->get();
    }

    /**
     * Get aggregated OI for selected strikes
     */
    public static function getAggregatedOI(int $brokerId, string $symbol, string $date, array $positions, string $optionType)
    {
        $strikes = self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', $symbol)
            ->where('trading_date', $date)
            ->where('option_type', $optionType)
            ->whereIn('strike_position', $positions)
            ->get();

        return [
            'total_oi' => $strikes->sum('daily_oi'),
            'avg_oi_change_pct' => $strikes->avg('daily_oi_change_pct'),
            'avg_iv' => $strikes->avg('daily_iv'),
            'avg_close' => $strikes->avg('daily_close'),
            'strikes_count' => $strikes->count(),
        ];
    }

    /**
     * Check if OI increased
     */
    public function oiIncreased(): bool
    {
        return $this->daily_oi_change !== null && $this->daily_oi_change > 0;
    }

    /**
     * Check if OI decreased
     */
    public function oiDecreased(): bool
    {
        return $this->daily_oi_change !== null && $this->daily_oi_change < 0;
    }

    /**
     * Get OI trend label
     */
    public function getOiTrendAttribute(): string
    {
        if ($this->daily_oi_change === null) {
            return 'NEUTRAL';
        }

        if ($this->daily_oi_change > 0) {
            return 'RISING';
        } elseif ($this->daily_oi_change < 0) {
            return 'FALLING';
        }

        return 'FLAT';
    }

    /**
     * Check if IV increased
     */
    public function ivIncreased(): bool
    {
        return $this->daily_iv_change !== null && $this->daily_iv_change > 0;
    }

    /**
     * Check if IV decreased
     */
    public function ivDecreased(): bool
    {
        return $this->daily_iv_change !== null && $this->daily_iv_change < 0;
    }

    /**
     * Get IV trend label
     */
    public function getIvTrendAttribute(): string
    {
        if ($this->daily_iv_change === null) {
            return 'NEUTRAL';
        }

        if ($this->daily_iv_change > 0) {
            return 'RISING';
        } elseif ($this->daily_iv_change < 0) {
            return 'FALLING';
        }

        return 'FLAT';
    }

    /**
     * Format OI for display
     */
    public function getFormattedOiAttribute(): string
    {
        if (!$this->daily_oi) {
            return '0';
        }

        $oi = $this->daily_oi;

        if ($oi >= 10000000) {
            return number_format($oi / 10000000, 2) . ' Cr';
        } elseif ($oi >= 100000) {
            return number_format($oi / 100000, 2) . ' L';
        } elseif ($oi >= 1000) {
            return number_format($oi / 1000, 2) . ' K';
        }

        return number_format($oi);
    }

    /**
     * Get signal color based on direction
     */
    public function getSignalColorAttribute(): string
    {
        if (!$this->direction) {
            return 'secondary';
        }

        switch (strtoupper($this->direction)) {
            case 'BULLISH':
                return 'success';
            case 'BEARISH':
                return 'danger';
            default:
                return 'secondary';
        }
    }

    /**
     * Get IV color (green for falling, red for rising)
     */
    public function getIvColorAttribute(): string
    {
        if ($this->daily_iv_change === null) {
            return 'secondary';
        }

        // For options: Rising IV = Bad (red), Falling IV = Good (green)
        if ($this->daily_iv_change > 0) {
            return 'danger'; // Rising IV
        } elseif ($this->daily_iv_change < 0) {
            return 'success'; // Falling IV
        }

        return 'secondary';
    }

    /**
     * Get price color
     */
    public function getCloseColorAttribute(): string
    {
        if ($this->daily_close_change === null) {
            return 'secondary';
        }

        if ($this->daily_close_change > 0) {
            return 'success';
        } elseif ($this->daily_close_change < 0) {
            return 'danger';
        }

        return 'secondary';
    }
}