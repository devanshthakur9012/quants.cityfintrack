<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricalOneHour extends Model
{
    use HasFactory;

    protected $table = "historical_one_hours";
    protected $fillable = [
        'underlying',
        'date',

        // Future Data
        'future_symbol',
        'future_token',
        'future_open',
        'future_high',
        'future_low',
        'future_close',
        'future_volume',
        'future_oi',
        'future_oi_change',
        'future_oi_chg_pct',

        // Call Option Data
        'ce_symbol',
        'ce_token',
        'ce_open',
        'ce_high',
        'ce_low',
        'ce_close',
        'ce_volume',
        'ce_oi',
        'ce_oi_change',
        'ce_oi_chg_pct',

        // Put Option Data
        'pe_symbol',
        'pe_token',
        'pe_open',
        'pe_high',
        'pe_low',
        'pe_close',
        'pe_volume',
        'pe_oi',
        'pe_oi_change',
        'pe_oi_chg_pct',
    ];
}
