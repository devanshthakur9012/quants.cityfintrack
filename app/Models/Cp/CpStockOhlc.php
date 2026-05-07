<?php
// ─────────────────────────────────────────────────────────────────────────────
// FILE: app/Models/Cp/CpStockOhlc.php
// Usage: CpStockOhlc::forTimeframe('15min')->where(...)
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Models\Cp;

use Illuminate\Database\Eloquent\Model;
use App\Models\AnalysisConfig;
use App\Models\BrokerApi;

class CpStockOhlc extends Model
{
    protected $fillable = [
        'analysis_config_id', 'broker_api_id',
        'symbol', 'trading_symbol', 'instrument_token',
        'trade_date', 'interval_time',
        'open', 'high', 'low', 'close', 'volume',
        'is_missing', 'is_backfill',
    ];

    protected $casts = [
        'trade_date'    => 'date',
        'interval_time' => 'datetime',
        'is_missing'    => 'boolean',
        'is_backfill'   => 'boolean',
    ];

    /** @var string Set dynamically via forTimeframe() */
    protected $table = 'cp_stock_ohlc_15min';

    /**
     * Return a new instance scoped to the given timeframe table.
     * Usage: CpStockOhlc::forTimeframe('30min')
     */
    public static function forTimeframe(string $timeframe): self
    {
        $instance = new static();
        $instance->setTable("cp_stock_ohlc_{$timeframe}");
        return $instance;
    }

    /**
     * Static query builder shortcut.
     * Usage: CpStockOhlc::tf('15min')->where('symbol', 'RELIANCE')->get()
     */
    public static function tf(string $timeframe)
    {
        return (new static())->setTable("cp_stock_ohlc_{$timeframe}")->newQuery();
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