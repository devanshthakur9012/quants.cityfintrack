<?php

namespace App\Models\Cp;
 
use Illuminate\Database\Eloquent\Model;
use App\Models\AnalysisConfig;
use App\Models\BrokerApi;
 
class CpFutOhlc extends Model
{
    protected $fillable = [
        'analysis_config_id', 'broker_api_id',
        'base_symbol', 'trading_symbol', 'instrument_token',
        'expiry_date', 'atm_strike',
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
 
    protected $table = 'cp_fut_ohlc_15min';
 
    public static function forTimeframe(string $timeframe): self
    {
        $instance = new static();
        $instance->setTable("cp_fut_ohlc_{$timeframe}");
        return $instance;
    }
 
    public static function tf(string $timeframe)
    {
        return (new static())->setTable("cp_fut_ohlc_{$timeframe}")->newQuery();
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