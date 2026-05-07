<?php

namespace App\Models\Cp;
 
use Illuminate\Database\Eloquent\Model;
use App\Models\AnalysisConfig;
use App\Models\BrokerApi;
 
class CpOptionOhlc extends Model
{
    protected $fillable = [
        'analysis_config_id', 'broker_api_id',
        'base_symbol', 'fut_trading_symbol', 'future_price',
        'trading_symbol', 'instrument_token',
        'expiry_date', 'instrument_type',
        'atm_strike', 'strike', 'strike_position',
        'trade_date', 'interval_time',
        'open', 'high', 'low', 'close', 'volume', 'oi',
        'is_missing', 'is_backfill',
    ];
 
    protected $casts = [
        'trade_date'    => 'date',
        'interval_time' => 'datetime',
        'expiry_date'   => 'date',
        'is_missing'    => 'boolean',
        'is_backfill'   => 'boolean',
    ];
 
    protected $table = 'cp_option_ohlc_15min';
 
    public static function forTimeframe(string $timeframe): self
    {
        $instance = new static();
        $instance->setTable("cp_option_ohlc_{$timeframe}");
        return $instance;
    }
 
    public static function tf(string $timeframe)
    {
        return (new static())->setTable("cp_option_ohlc_{$timeframe}")->newQuery();
    }
 
    public function config()
    {
        return $this->belongsTo(AnalysisConfig::class, 'analysis_config_id');
    }
 
    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }
}