<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\OptionStrike9to12
 *
 * Stores INTRADAY OI + IV + Close data from 9:15 AM → 12:15 PM (same day).
 * open_oi    = 9:15 AM candle OI
 * current_oi = 12:15 PM candle OI
 */
class OptionStrike9to12 extends Model
{
    protected $table = 'option_strike_9to12';

    protected $fillable = [
        'broker_api_id',
        'underlying_symbol',
        'trading_symbol',
        'strike_position',
        'trading_date',

        // Metadata
        'option_type',
        'strike_price',
        'expiry',
        'expiry_date',
        'instrument_token',
        'exchange',
        'lot_size',
        'spot_price',

        // OI
        'open_oi',
        'current_oi',
        'oi_change',
        'oi_change_pct',
        'direction',
        'strength',
        'market_bias',

        // IV
        'open_iv',
        'current_iv',
        'iv_change',
        'iv_change_pct',
        'iv_direction',
        'iv_strength',

        // Close
        'open_close',
        'current_close',
        'close_change',
        'close_change_pct',

        // Signals
        'options_sentiment',
        'final_sentiment',
        'trade_action',
        'pe_ce_ratio',
        'futures_oi_view',
        'oi_interpretation',
        'oi_condition',
        'ce_oi_change_pct',
        'pe_oi_change_pct',

        // BTST
        'btst_signal',
        'btst_confidence',
        'btst_reason',

        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'trading_date'     => 'date',
        'expiry_date'      => 'date',
        'strike_price'     => 'float',
        'spot_price'       => 'float',

        'open_oi'          => 'integer',
        'current_oi'       => 'integer',
        'oi_change'        => 'integer',
        'oi_change_pct'    => 'float',

        'open_iv'          => 'float',
        'current_iv'       => 'float',
        'iv_change'        => 'float',
        'iv_change_pct'    => 'float',

        'open_close'       => 'float',
        'current_close'    => 'float',
        'close_change'     => 'float',
        'close_change_pct' => 'float',

        'pe_ce_ratio'      => 'float',
        'ce_oi_change_pct' => 'float',
        'pe_oi_change_pct' => 'float',
        'btst_confidence'  => 'float',

        'lot_size'         => 'integer',
        'is_active'        => 'boolean',
        'last_synced_at'   => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeForDate($query, string $date)
    {
        return $query->where('trading_date', $date);
    }

    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('underlying_symbol', $symbol);
    }

    public function scopeFut($query)
    {
        return $query->where('strike_position', 'FUT');
    }

    public function scopeCeMerged($query)
    {
        return $query->where('strike_position', 'CE_MERGED');
    }

    public function scopePeMerged($query)
    {
        return $query->where('strike_position', 'PE_MERGED');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}