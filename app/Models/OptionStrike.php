<?php
// app/Models/OptionStrike.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OptionStrike extends Model
{
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

        // ✅ Close Price fields
        'daily_close',
        'daily_close_prev',
        'daily_close_change',
        'daily_close_change_pct',

        // OI Signal fields
        'direction',
        'strength',
        'market_bias',

        // IV & BTST SIGNAL FIELDS
        'iv_direction',
        'iv_strength',
        'btst_signal',
        'btst_confidence',
        'btst_reason',

        // ✅ CE/PE Ratio Analysis fields
        'pe_ce_ratio',
        'oi_interpretation',
        'oi_condition',              // ✅ NEW
        'ce_oi_change_pct',          // ✅ NEW
        'pe_oi_change_pct',          // ✅ NEW
        'options_sentiment',
        'futures_oi_view',
        'final_sentiment',
        'trade_action',

        // ✅ Price Lock fields
        'close_315_price',
        'close_315_locked_at',
        'open_915_price',
        'open_915_locked_at',
        
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
        
        // ✅ Close Price casts
        'daily_close' => 'decimal:2',
        'daily_close_prev' => 'decimal:2',
        'daily_close_change' => 'decimal:2',
        'daily_close_change_pct' => 'decimal:2',
        
        // Other casts
        'spot_price' => 'decimal:2',
        'close_315_price' => 'decimal:2',    // ✅ NEW
        'open_915_price' => 'decimal:2',     // ✅ NEW
        'close_315_locked_at' => 'datetime', // ✅ NEW
        'open_915_locked_at' => 'datetime',  // ✅ NEW
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
        
        // Signal casts
        'iv_direction'   => 'string',
        'iv_strength'    => 'string',
        'btst_signal'    => 'string',
        'btst_confidence'=> 'integer',
        'btst_reason'    => 'string',
        
        // ✅ CE/PE Analysis casts
        'pe_ce_ratio'        => 'decimal:2',
        'oi_interpretation'  => 'string',
        'oi_condition'       => 'string',  // ✅ NEW
        'ce_oi_change_pct'   => 'decimal:2', // ✅ NEW
        'pe_oi_change_pct'   => 'decimal:2', // ✅ NEW
        'options_sentiment'  => 'string',
        'futures_oi_view'    => 'string',
        'final_sentiment'    => 'string',
        'trade_action'       => 'string',
    ];

    /**
     * Broker relationship
     */
    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    /**
     * OI Data relationship
     */
    public function oiData(): HasMany
    {
        return $this->hasMany(OptionOiData::class);
    }

    /**
     * Get latest OI data
     */
    public function latestOi()
    {
        return $this->hasOne(OptionOiData::class)->latestOfMany('timestamp');
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
     * Scope: Current expiry
     */
    public function scopeCurrentExpiry($query)
    {
        return $query->where('expiry_date', '>=', now())->orderBy('expiry_date', 'ASC');
    }

    /**
     * Scope: Filter by trading date
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('trading_date', $date);
    }

    /**
     * Scope: Get daily summary rows (FUT + CE_MERGED + PE_MERGED)
     */
    public function scopeDailySummary($query)
    {
        return $query->whereIn('strike_position', ['FUT', 'CE_MERGED', 'PE_MERGED']);
    }

    /**
     * ✅ NEW: Scope: Filter by trade action
     */
    public function scopeByTradeAction($query, string $action)
    {
        return $query->where('trade_action', strtoupper($action));
    }

    /**
     * ✅ NEW: Scope: Filter by final sentiment
     */
    public function scopeBySentiment($query, string $sentiment)
    {
        return $query->where('final_sentiment', $sentiment);
    }

    /**
     * ✅ NEW: Scope: Strong signals only (Strong Bullish or Strong Bearish)
     */
    public function scopeStrongSignals($query)
    {
        return $query->whereIn('final_sentiment', ['Strong Bullish', 'Strong Bearish']);
    }

    /**
     * ✅ NEW: Scope: Bullish signals (including Strong Bullish)
     */
    public function scopeBullishSignals($query)
    {
        return $query->whereIn('final_sentiment', ['Bullish', 'Strong Bullish'])
            ->orWhere('trade_action', 'BUY CE');
    }

    /**
     * ✅ NEW: Scope: Bearish signals (including Strong Bearish)
     */
    public function scopeBearishSignals($query)
    {
        return $query->whereIn('final_sentiment', ['Bearish', 'Strong Bearish'])
            ->orWhere('trade_action', 'BUY PE');
    }

    /**
     * Get ATM strikes (ATM-1, ATM, ATM+1) for both CE and PE
     */
    public static function getATMStrikes(string $underlying, string $brokerId = null)
    {
        $query = self::active()
            ->forUnderlying($underlying)
            ->currentExpiry()
            ->whereIn('strike_position', ['ATM-1', 'ATM', 'ATM+1']);

        if ($brokerId) {
            $query->where('broker_api_id', $brokerId);
        }

        return $query->orderBy('option_type')
            ->orderByRaw("FIELD(strike_position, 'ATM-1', 'ATM', 'ATM+1')")
            ->get()
            ->groupBy('option_type');
    }

    /**
     * Get daily OI summary for a specific date and symbol
     * Returns: FUT, CE_MERGED, PE_MERGED rows
     */
    public static function getDailySummary(string $underlying, string $date, int $brokerId)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('trading_date', $date)
            ->dailySummary()
            ->orderByRaw("FIELD(strike_position, 'FUT', 'CE_MERGED', 'PE_MERGED')")
            ->get();
    }

    /**
     * Get daily OI trend for a date range
     */
    public static function getDailyTrend(string $underlying, string $fromDate, string $toDate, int $brokerId, string $position = 'FUT')
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('strike_position', $position)
            ->whereBetween('trading_date', [$fromDate, $toDate])
            ->orderBy('trading_date', 'DESC')
            ->get();
    }

    /**
     * ✅ Get daily IV trend for a date range
     */
    public static function getDailyIVTrend(string $underlying, string $fromDate, string $toDate, int $brokerId, string $position = 'CE_MERGED')
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('strike_position', $position)
            ->whereBetween('trading_date', [$fromDate, $toDate])
            ->whereNotNull('daily_iv')
            ->orderBy('trading_date', 'DESC')
            ->get();
    }

    /**
     * ✅ Get combined OI + IV + Close Price summary for a specific date
     */
    public static function getFullDailySummary(string $underlying, string $date, int $brokerId)
    {
        $data = self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('trading_date', $date)
            ->dailySummary()
            ->orderByRaw("FIELD(strike_position, 'FUT', 'CE_MERGED', 'PE_MERGED')")
            ->get();

        // Format into easy-to-use structure
        $summary = [
            'date' => $date,
            'underlying' => $underlying,
            'fut' => null,
            'ce' => null,
            'pe' => null,
        ];

        foreach ($data as $row) {
            if ($row->strike_position === 'FUT') {
                $summary['fut'] = [
                    'oi' => $row->daily_oi,
                    'oi_change' => $row->daily_oi_change,
                    'oi_change_pct' => $row->daily_oi_change_pct,
                    'direction' => $row->direction,
                    'strength' => $row->strength,
                    'market_bias' => $row->market_bias,
                    // ✅ Close Price
                    'close' => $row->daily_close,
                    'close_change' => $row->daily_close_change,
                    'close_change_pct' => $row->daily_close_change_pct,
                    'spot_price' => $row->spot_price,
                    // ✅ NEW: PE/CE Analysis
                    'pe_ce_ratio' => $row->pe_ce_ratio,
                    'oi_interpretation' => $row->oi_interpretation,
                    'options_sentiment' => $row->options_sentiment,
                    'futures_oi_view' => $row->futures_oi_view,
                    'final_sentiment' => $row->final_sentiment,
                    'trade_action' => $row->trade_action,
                    // BTST
                    'btst_signal' => $row->btst_signal,
                    'btst_confidence' => $row->btst_confidence,
                    'btst_reason' => $row->btst_reason,
                ];
            } elseif ($row->strike_position === 'CE_MERGED') {
               $summary['ce'] = [
                    'oi' => $row->daily_oi,
                    'oi_change' => $row->daily_oi_change,
                    'oi_change_pct' => $row->daily_oi_change_pct,

                    'iv' => $row->daily_iv,
                    'iv_change' => $row->daily_iv_change,
                    'iv_change_pct' => $row->daily_iv_change_pct,
                    'iv_direction' => $row->iv_direction,
                    'iv_strength'  => $row->iv_strength,

                    // ✅ Close Price (Average of 5 strikes)
                    'close' => $row->daily_close,
                    'close_change' => $row->daily_close_change,
                    'close_change_pct' => $row->daily_close_change_pct,

                    'direction' => $row->direction,
                    'strength' => $row->strength,
                ];
            } elseif ($row->strike_position === 'PE_MERGED') {
                $summary['pe'] = [
                    'oi' => $row->daily_oi,
                    'oi_change' => $row->daily_oi_change,
                    'oi_change_pct' => $row->daily_oi_change_pct,

                    'iv' => $row->daily_iv,
                    'iv_change' => $row->daily_iv_change,
                    'iv_change_pct' => $row->daily_iv_change_pct,
                    'iv_direction' => $row->iv_direction,
                    'iv_strength'  => $row->iv_strength,

                    // ✅ Close Price (Average of 5 strikes)
                    'close' => $row->daily_close,
                    'close_change' => $row->daily_close_change,
                    'close_change_pct' => $row->daily_close_change_pct,

                    'direction' => $row->direction,
                    'strength' => $row->strength,
                ];
            }
        }

        return $summary;
    }

    /**
     * ✅ NEW: Get PE/CE analysis summary for a specific date
     */
    public static function getPECEAnalysisSummary(string $underlying, string $date, int $brokerId)
    {
        $futRecord = self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('trading_date', $date)
            ->where('strike_position', 'FUT')
            ->first();

        if (!$futRecord) {
            return null;
        }

        return [
            'date' => $date,
            'underlying' => $underlying,
            'pe_ce_ratio' => $futRecord->pe_ce_ratio,
            'oi_interpretation' => $futRecord->oi_interpretation,
            'options_sentiment' => $futRecord->options_sentiment,
            'futures_oi_view' => $futRecord->futures_oi_view,
            'final_sentiment' => $futRecord->final_sentiment,
            'trade_action' => $futRecord->trade_action,
            'btst_signal' => $futRecord->btst_signal,
            'btst_confidence' => $futRecord->btst_confidence,
            'btst_reason' => $futRecord->btst_reason,
        ];
    }

    /**
     * ✅ NEW: Get PE/CE analysis trend for date range
     */
    public static function getPECEAnalysisTrend(string $underlying, string $fromDate, string $toDate, int $brokerId)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->where('strike_position', 'FUT')
            ->whereBetween('trading_date', [$fromDate, $toDate])
            ->whereNotNull('pe_ce_ratio')
            ->orderBy('trading_date', 'DESC')
            ->get()
            ->map(function($row) {
                return [
                    'date' => $row->trading_date,
                    'pe_ce_ratio' => $row->pe_ce_ratio,
                    'oi_interpretation' => $row->oi_interpretation,
                    'options_sentiment' => $row->options_sentiment,
                    'futures_oi_view' => $row->futures_oi_view,
                    'final_sentiment' => $row->final_sentiment,
                    'trade_action' => $row->trade_action,
                ];
            });
    }

    /**
     * ✅ NEW: Get all symbols with specific trade action for a date
     */
    public static function getSymbolsByTradeAction(string $action, string $date, int $brokerId)
    {
        return self::where('broker_api_id', $brokerId)
            ->where('trading_date', $date)
            ->where('strike_position', 'FUT')
            ->where('trade_action', strtoupper($action))
            ->whereNotNull('final_sentiment')
            ->orderBy('underlying_symbol')
            ->get()
            ->map(function($row) {
                return [
                    'underlying_symbol' => $row->underlying_symbol,
                    'pe_ce_ratio' => $row->pe_ce_ratio,
                    'final_sentiment' => $row->final_sentiment,
                    'trade_action' => $row->trade_action,
                    'btst_signal' => $row->btst_signal,
                ];
            });
    }

    /**
     * ✅ Get IV comparison (CE vs PE) for date range
     */
    public static function getIVComparison(string $underlying, string $fromDate, string $toDate, int $brokerId)
    {
        $data = self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->whereIn('strike_position', ['CE_MERGED', 'PE_MERGED'])
            ->whereBetween('trading_date', [$fromDate, $toDate])
            ->whereNotNull('daily_iv')
            ->orderBy('trading_date', 'DESC')
            ->get();

        // Group by date
        $comparison = [];
        foreach ($data as $row) {
            $date = $row->trading_date;
            
            if (!isset($comparison[$date])) {
                $comparison[$date] = [
                    'date' => $date,
                    'ce_iv' => null,
                    'pe_iv' => null,
                    'ce_iv_change_pct' => null,
                    'pe_iv_change_pct' => null,
                    'iv_spread' => null, // CE IV - PE IV
                ];
            }

            if ($row->strike_position === 'CE_MERGED') {
                $comparison[$date]['ce_iv'] = $row->daily_iv;
                $comparison[$date]['ce_iv_change_pct'] = $row->daily_iv_change_pct;
            } elseif ($row->strike_position === 'PE_MERGED') {
                $comparison[$date]['pe_iv'] = $row->daily_iv;
                $comparison[$date]['pe_iv_change_pct'] = $row->daily_iv_change_pct;
            }
        }

        // Calculate IV spread
        foreach ($comparison as &$day) {
            if ($day['ce_iv'] !== null && $day['pe_iv'] !== null) {
                $day['iv_spread'] = round($day['ce_iv'] - $day['pe_iv'], 4);
            }
        }

        return array_values($comparison);
    }

    /**
     * ✅ Get Close Price comparison (CE vs PE vs FUT) for date range
     */
    public static function getClosePriceComparison(string $underlying, string $fromDate, string $toDate, int $brokerId)
    {
        $data = self::where('broker_api_id', $brokerId)
            ->where('underlying_symbol', strtoupper($underlying))
            ->whereIn('strike_position', ['FUT', 'CE_MERGED', 'PE_MERGED'])
            ->whereBetween('trading_date', [$fromDate, $toDate])
            ->whereNotNull('daily_close')
            ->orderBy('trading_date', 'DESC')
            ->get();

        // Group by date
        $comparison = [];
        foreach ($data as $row) {
            $date = $row->trading_date;
            
            if (!isset($comparison[$date])) {
                $comparison[$date] = [
                    'date' => $date,
                    'fut_close' => null,
                    'fut_close_change_pct' => null,
                    'ce_close' => null,
                    'ce_close_change_pct' => null,
                    'pe_close' => null,
                    'pe_close_change_pct' => null,
                ];
            }

            if ($row->strike_position === 'FUT') {
                $comparison[$date]['fut_close'] = $row->daily_close;
                $comparison[$date]['fut_close_change_pct'] = $row->daily_close_change_pct;
            } elseif ($row->strike_position === 'CE_MERGED') {
                $comparison[$date]['ce_close'] = $row->daily_close;
                $comparison[$date]['ce_close_change_pct'] = $row->daily_close_change_pct;
            } elseif ($row->strike_position === 'PE_MERGED') {
                $comparison[$date]['pe_close'] = $row->daily_close;
                $comparison[$date]['pe_close_change_pct'] = $row->daily_close_change_pct;
            }
        }

        return array_values($comparison);
    }

    /**
     * ✅ Accessor: Get IV as percentage
     */
    public function getIvPercentageAttribute()
    {
        return $this->daily_iv ? round($this->daily_iv * 100, 2) : null;
    }

    /**
     * ✅ Accessor: Get previous IV as percentage
     */
    public function getIvPrevPercentageAttribute()
    {
        return $this->daily_iv_prev ? round($this->daily_iv_prev * 100, 2) : null;
    }

    /**
     * ✅ Check if IV increased
     */
    public function ivIncreased(): bool
    {
        return $this->daily_iv_change !== null && $this->daily_iv_change > 0;
    }

    /**
     * ✅ Check if IV decreased
     */
    public function ivDecreased(): bool
    {
        return $this->daily_iv_change !== null && $this->daily_iv_change < 0;
    }

    /**
     * ✅ Get IV trend label
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
     * ✅ Check if Close Price increased
     */
    public function closeIncreased(): bool
    {
        return $this->daily_close_change !== null && $this->daily_close_change > 0;
    }

    /**
     * ✅ Check if Close Price decreased
     */
    public function closeDecreased(): bool
    {
        return $this->daily_close_change !== null && $this->daily_close_change < 0;
    }

    /**
     * ✅ Get Close Price trend label
     */
    public function getCloseTrendAttribute(): string
    {
        if ($this->daily_close_change === null) {
            return 'NEUTRAL';
        }

        if ($this->daily_close_change > 0) {
            return 'RISING';
        } elseif ($this->daily_close_change < 0) {
            return 'FALLING';
        }

        return 'FLAT';
    }

    /**
     * ✅ Scope: Filter by IV change threshold
     */
    public function scopeIvChangedBy($query, float $percentageThreshold)
    {
        return $query->whereNotNull('daily_iv_change_pct')
            ->where(function($q) use ($percentageThreshold) {
                $q->where('daily_iv_change_pct', '>=', $percentageThreshold)
                  ->orWhere('daily_iv_change_pct', '<=', -$percentageThreshold);
            });
    }

    /**
     * ✅ Scope: Filter by IV rising
     */
    public function scopeIvRising($query)
    {
        return $query->whereNotNull('daily_iv_change')
            ->where('daily_iv_change', '>', 0);
    }

    /**
     * ✅ Scope: Filter by IV falling
     */
    public function scopeIvFalling($query)
    {
        return $query->whereNotNull('daily_iv_change')
            ->where('daily_iv_change', '<', 0);
    }

    /**
     * ✅ Scope: Filter by Close Price change threshold
     */
    public function scopeCloseChangedBy($query, float $percentageThreshold)
    {
        return $query->whereNotNull('daily_close_change_pct')
            ->where(function($q) use ($percentageThreshold) {
                $q->where('daily_close_change_pct', '>=', $percentageThreshold)
                  ->orWhere('daily_close_change_pct', '<=', -$percentageThreshold);
            });
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
     * Format OI change for display
     */
    public function getFormattedOiChangeAttribute(): string
    {
        if ($this->daily_oi_change === null) {
            return 'N/A';
        }

        $change = $this->daily_oi_change;
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
     * ✅ Format IV change for display
     */
    public function getFormattedIvChangeAttribute(): string
    {
        if ($this->daily_iv_change_pct === null) {
            return 'N/A';
        }

        $sign = $this->daily_iv_change_pct >= 0 ? '+' : '';
        return $sign . number_format($this->daily_iv_change_pct, 2) . '%';
    }

    /**
     * ✅ Format Close Price change for display
     */
    public function getFormattedCloseChangeAttribute(): string
    {
        if ($this->daily_close_change_pct === null) {
            return 'N/A';
        }

        $sign = $this->daily_close_change_pct >= 0 ? '+' : '';
        return $sign . number_format($this->daily_close_change_pct, 2) . '%';
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
     * ✅ Get IV color (green for falling, red for rising)
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
     * ✅ Get Close Price color (green for rising, red for falling)
     */
    public function getCloseColorAttribute(): string
    {
        if ($this->daily_close_change === null) {
            return 'secondary';
        }

        if ($this->daily_close_change > 0) {
            return 'success'; // Rising price
        } elseif ($this->daily_close_change < 0) {
            return 'danger'; // Falling price
        }

        return 'secondary';
    }

    /**
     * ✅ NEW: Get sentiment color
     */
    public function getSentimentColorAttribute(): string
    {
        if (!$this->final_sentiment) {
            return 'secondary';
        }

        switch ($this->final_sentiment) {
            case 'Strong Bullish':
                return 'success';
            case 'Bullish':
                return 'success';
            case 'Strong Bearish':
                return 'danger';
            case 'Bearish':
                return 'danger';
            case 'Neutral':
            default:
                return 'secondary';
        }
    }

    /**
     * ✅ NEW: Get trade action color
     */
    public function getTradeActionColorAttribute(): string
    {
        if (!$this->trade_action) {
            return 'secondary';
        }

        switch ($this->trade_action) {
            case 'BUY CE':
                return 'success';
            case 'BUY PE':
                return 'danger';
            case 'BOTH CE AND PE':
            default:
                return 'warning';
        }
    }

    /**
     * ✅ NEW: Check if has PE/CE analysis
     */
    public function hasPECEAnalysis(): bool
    {
        return $this->strike_position === 'FUT' && $this->pe_ce_ratio !== null;
    }

    /**
     * Check if has BTST signal
     */
    public function hasBtstSignal(): bool
    {
        return $this->strike_position === 'FUT' && $this->btst_signal !== null;
    }

    /**
     * ✅ NEW: Check if is strong signal
     */
    public function isStrongSignal(): bool
    {
        return in_array($this->final_sentiment, ['Strong Bullish', 'Strong Bearish']);
    }

    /**
     * ✅ NEW: Check if is bullish
     */
    public function isBullish(): bool
    {
        return in_array($this->final_sentiment, ['Bullish', 'Strong Bullish']) 
            || $this->trade_action === 'BUY CE';
    }

    /**
     * ✅ NEW: Check if is bearish
     */
    public function isBearish(): bool
    {
        return in_array($this->final_sentiment, ['Bearish', 'Strong Bearish']) 
            || $this->trade_action === 'BUY PE';
    }

    /**
     * ✅ NEW: Get formatted PE/CE ratio with interpretation
     */
    public function getFormattedPECERatioAttribute(): string
    {
        if (!$this->pe_ce_ratio) {
            return 'N/A';
        }

        $interpretation = '';
        if ($this->oi_interpretation) {
            $interpretation = " ({$this->oi_interpretation})";
        }

        return number_format($this->pe_ce_ratio, 2) . $interpretation;
    }

    /**
     * ✅ NEW: Get OI Condition badge color
     */
    public function getOiConditionColorAttribute(): string
    {
        if (!$this->oi_condition) {
            return 'secondary';
        }

        if (str_contains($this->oi_condition, 'CE ↑ + PE ↓')) {
            return 'danger'; // Bearish
        } elseif (str_contains($this->oi_condition, 'CE ↓ + PE ↑')) {
            return 'success'; // Bullish
        } elseif (str_contains($this->oi_condition, 'Both ↑')) {
            return str_contains($this->oi_condition, 'CE > PE') ? 'danger' : 'success';
        } elseif (str_contains($this->oi_condition, 'Both ↓')) {
            return str_contains($this->oi_condition, 'CE < PE') ? 'success' : 'danger';
        }

        return 'secondary';
    }

    /**
     * ✅ NEW: Check if prices are locked
     */
    public function arePricesLocked(): bool
    {
        return !empty($this->open_915_price) && !empty($this->close_315_price);
    }

    /**
     * ✅ NEW: Get lock status
     */
    public function getLockStatusAttribute(): array
    {
        return [
            'open_locked' => !empty($this->open_915_price),
            'close_locked' => !empty($this->close_315_price),
            'open_locked_at' => $this->open_915_locked_at ? $this->open_915_locked_at->format('Y-m-d H:i:s') : null,
            'close_locked_at' => $this->close_315_locked_at ? $this->close_315_locked_at->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * ✅ NEW: Scope - Has CE/PE OI analysis
     */
    public function scopeHasCEPEAnalysis($query)
    {
        return $query->whereNotNull('oi_condition')
            ->whereNotNull('ce_oi_change_pct')
            ->whereNotNull('pe_oi_change_pct');
    }
}