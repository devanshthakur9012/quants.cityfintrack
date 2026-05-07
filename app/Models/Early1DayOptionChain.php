<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Early1DayOptionChain extends Model
{
    use HasFactory;
    protected $table = "early1_day_option_chains";
    
    protected $fillable = [
        'underlying',
        'future_symbol',
        'future_token',
        'future_price',
        'strike_price',
        'strike_position',
        'ce_symbol',
        'ce_token',
        'ce_lotsize',
        'ce_exch_seg',
        'ce_expiry',
        'ce_tick_size',
        'pe_symbol',
        'pe_token',
        'pe_lotsize',
        'pe_exch_seg',
        'pe_expiry',
        'pe_tick_size',
    ];

    protected $casts = [
        'future_price' => 'decimal:2',
        'strike_price' => 'decimal:2',
        'ce_tick_size' => 'decimal:4',
        'pe_tick_size' => 'decimal:4',
        'ce_expiry' => 'date',
        'pe_expiry' => 'date',
    ];

    public static function getByUnderlying($underlying)
    {
        return self::where('underlying', $underlying)->orderBy('strike_position')->get();
    }

    public static function getATM($underlying)
    {
        return self::where('underlying', $underlying)->where('strike_position', 0)->first();
    }

    public static function getStrikesGrouped($underlying)
    {
        return self::where('underlying', $underlying)->orderBy('strike_position')->get()->groupBy('strike_position');
    }
}
