<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndexOptionStrike extends Model
{
    protected $table = 'index_option_strikes';

    protected $fillable = [
        'broker_api_id',
        'underlying_symbol',
        'trading_symbol',
        'strike_position',
        'trading_date',
        'option_type',
        'strike_price',
        'expiry',
        'expiry_date',
        'instrument_token',
        'exchange',
        'lot_size',
        'is_active',
        'spot_price',
        // OI
        'daily_oi',
        'daily_oi_prev',
        'daily_oi_change',
        'daily_oi_change_pct',
        'direction',
        'strength',
        'market_bias',
        // IV
        'daily_iv',
        'daily_iv_prev',
        'daily_iv_change',
        'daily_iv_change_pct',
        'iv_direction',
        'iv_strength',
        // Close
        'daily_close',
        'daily_close_prev',
        'daily_close_change',
        'daily_close_change_pct',
        // Signals
        'options_sentiment',
        'final_sentiment',
        'trade_action',
        'futures_oi_view',
        'oi_interpretation',
        'oi_condition',
        'ce_oi_change_pct',
        'pe_oi_change_pct',
        'pe_ce_ratio',
        // BTST
        'btst_signal',
        'btst_confidence',
        'btst_reason',
        'last_synced_at',
    ];

    protected $casts = [
        'trading_date' => 'date',
        'expiry_date'  => 'date',
        'is_active'    => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function brokerApi()
    {
        return $this->belongsTo(BrokerApi::class);
    }
}