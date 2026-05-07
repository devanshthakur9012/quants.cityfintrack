<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class OptionStrikeIntraday extends Model
{
    protected $table = 'option_strikes_intraday';

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

        // Intraday OI fields
        'intraday_oi',
        'intraday_oi_prev',
        'intraday_oi_change',
        'intraday_oi_change_pct',

        // Intraday IV fields
        'intraday_iv',
        'intraday_iv_prev',
        'intraday_iv_change',
        'intraday_iv_change_pct',

        // OI Signal fields
        'direction',
        'strength',
        'market_bias',

        // IV Signal fields
        'iv_direction',
        'iv_strength',

        // BTST Signal fields
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
        
        // Intraday OI casts
        'intraday_oi' => 'integer',
        'intraday_oi_prev' => 'integer',
        'intraday_oi_change' => 'integer',
        'intraday_oi_change_pct' => 'decimal:2',
        
        // Intraday IV casts
        'intraday_iv' => 'decimal:4',
        'intraday_iv_prev' => 'decimal:4',
        'intraday_iv_change' => 'decimal:4',
        'intraday_iv_change_pct' => 'decimal:2',
        
        'spot_price' => 'decimal:2',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        
        'iv_direction' => 'string',
        'iv_strength' => 'string',
        'btst_signal' => 'string',
        'btst_confidence' => 'integer',
        'btst_reason' => 'string',
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
     * Scope: Filter by option type
     */
    public function scopeType($query, string $type)
    {
        return $query->where('option_type', strtoupper($type));
    }

    /**
     * Scope: Filter by position
     */
    public function scopePosition($query, string $position)
    {
        return $query->where('strike_position', $position);
    }

    /**
     * Scope: Filter by trading date
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('trading_date', $date);
    }

    /**
     * Scope: Get intraday summary rows (FUT + CE_MERGED + PE_MERGED)
     */
    public function scopeIntradaySummary($query)
    {
        return $query->whereIn('strike_position', ['FUT', 'CE_MERGED', 'PE_MERGED']);
    }

    /**
     * Get intraday summary for a specific date and symbol
     */
    public static function getIntradaySummary(string $underlying, string $date, int $brokerId)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('trading_date', $date)
            ->intradaySummary()
            ->orderByRaw("FIELD(strike_position, 'FUT', 'CE_MERGED', 'PE_MERGED')")
            ->get();
    }

    /**
     * Get full intraday summary with OI + IV
     */
    public static function getFullIntradaySummary(string $underlying, string $date, int $brokerId)
    {
        $data = self::getIntradaySummary($underlying, $date, $brokerId);

        $summary = [
            'date' => $date,
            'underlying' => $underlying,
            'timeframe' => 'Prev Day 12:15 to Current Day 12:15',
            'fut' => null,
            'ce' => null,
            'pe' => null,
        ];

        foreach ($data as $row) {
            if ($row->strike_position === 'FUT') {
                $summary['fut'] = [
                    'oi' => $row->intraday_oi,
                    'oi_change' => $row->intraday_oi_change,
                    'oi_change_pct' => $row->intraday_oi_change_pct,
                    'direction' => $row->direction,
                    'strength' => $row->strength,
                    'market_bias' => $row->market_bias,
                    'spot_price' => $row->spot_price,
                    'btst_signal' => $row->btst_signal,
                    'btst_confidence' => $row->btst_confidence,
                    'btst_reason' => $row->btst_reason,
                ];
            } elseif ($row->strike_position === 'CE_MERGED') {
                $summary['ce'] = [
                    'oi' => $row->intraday_oi,
                    'oi_change' => $row->intraday_oi_change,
                    'oi_change_pct' => $row->intraday_oi_change_pct,
                    'iv' => $row->intraday_iv,
                    'iv_change' => $row->intraday_iv_change,
                    'iv_change_pct' => $row->intraday_iv_change_pct,
                    'iv_direction' => $row->iv_direction,
                    'iv_strength' => $row->iv_strength,
                    'direction' => $row->direction,
                    'strength' => $row->strength,
                ];
            } elseif ($row->strike_position === 'PE_MERGED') {
                $summary['pe'] = [
                    'oi' => $row->intraday_oi,
                    'oi_change' => $row->intraday_oi_change,
                    'oi_change_pct' => $row->intraday_oi_change_pct,
                    'iv' => $row->intraday_iv,
                    'iv_change' => $row->intraday_iv_change,
                    'iv_change_pct' => $row->intraday_iv_change_pct,
                    'iv_direction' => $row->iv_direction,
                    'iv_strength' => $row->iv_strength,
                    'direction' => $row->direction,
                    'strength' => $row->strength,
                ];
            }
        }

        return $summary;
    }

    /**
     * Get intraday trend for date range
     */
    public static function getIntradayTrend(string $underlying, string $fromDate, string $toDate, int $brokerId, string $position = 'FUT')
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('strike_position', $position)
            ->whereBetween('trading_date', [$fromDate, $toDate])
            ->orderBy('trading_date', 'DESC')
            ->get();
    }

    /**
     * Check if OI increased
     */
    public function oiIncreased(): bool
    {
        return $this->intraday_oi_change !== null && $this->intraday_oi_change > 0;
    }

    /**
     * Check if OI decreased
     */
    public function oiDecreased(): bool
    {
        return $this->intraday_oi_change !== null && $this->intraday_oi_change < 0;
    }

    /**
     * Check if IV increased
     */
    public function ivIncreased(): bool
    {
        return $this->intraday_iv_change !== null && $this->intraday_iv_change > 0;
    }

    /**
     * Check if IV decreased
     */
    public function ivDecreased(): bool
    {
        return $this->intraday_iv_change !== null && $this->intraday_iv_change < 0;
    }

    /**
     * Get OI trend label
     */
    public function getOiTrendAttribute(): string
    {
        if ($this->intraday_oi_change === null) {
            return 'NEUTRAL';
        }

        if ($this->intraday_oi_change > 0) {
            return 'RISING';
        } elseif ($this->intraday_oi_change < 0) {
            return 'FALLING';
        }

        return 'FLAT';
    }

    /**
     * Get IV trend label
     */
    public function getIvTrendAttribute(): string
    {
        if ($this->intraday_iv_change === null) {
            return 'NEUTRAL';
        }

        if ($this->intraday_iv_change > 0) {
            return 'RISING';
        } elseif ($this->intraday_iv_change < 0) {
            return 'FALLING';
        }

        return 'FLAT';
    }

    /**
     * Format OI for display
     */
    public function getFormattedOiAttribute(): string
    {
        if (!$this->intraday_oi) {
            return '0';
        }

        $oi = $this->intraday_oi;

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
     * Format OI change for display
     */
    public function getFormattedOiChangeAttribute(): string
    {
        if ($this->intraday_oi_change === null) {
            return 'N/A';
        }

        $change = $this->intraday_oi_change;
        $sign = $change >= 0 ? '+' : '';

        if (abs($change) >= 10000000) {
            return $sign . number_format($change / 10000000, 2) . ' Cr';
        } elseif (abs($change) >= 100000) {
            return $sign . number_format($change / 100000, 2) . ' L';
        } elseif (abs($change) >= 1000) {
            return $sign . number_format($change / 1000, 2) . ' K';
        }

        return $sign . number_format($change);
    }

    /**
     * Format IV change for display
     */
    public function getFormattedIvChangeAttribute(): string
    {
        if ($this->intraday_iv_change_pct === null) {
            return 'N/A';
        }

        $sign = $this->intraday_iv_change_pct >= 0 ? '+' : '';
        return $sign . number_format($this->intraday_iv_change_pct, 2) . '%';
    }

    /**
     * Get signal color
     */
    public function getSignalColorAttribute(): string
    {
        if (!$this->direction) {
            return 'secondary';
        }

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

    /**
     * Get IV color
     */
    public function getIvColorAttribute(): string
    {
        if ($this->intraday_iv_change === null) {
            return 'secondary';
        }

        if ($this->intraday_iv_change > 0) {
            return 'danger'; // Rising IV
        } elseif ($this->intraday_iv_change < 0) {
            return 'success'; // Falling IV
        }

        return 'secondary';
    }

    /**
     * Check if has BTST signal
     */
    public function hasBtstSignal(): bool
    {
        return $this->strike_position === 'FUT' && $this->btst_signal !== null;
    }
}