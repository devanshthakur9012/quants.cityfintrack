<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MCXHistorical extends Model
{
    use HasFactory;
    protected $table = "m_c_x_historicals";

    
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

        // Call Option Data
        'ce_symbol',
        'ce_token',
        'ce_open',
        'ce_high',
        'ce_low',
        'ce_close',
        'ce_volume',
        'ce_oi',

        // Put Option Data
        'pe_symbol',
        'pe_token',
        'pe_open',
        'pe_high',
        'pe_low',
        'pe_close',
        'pe_volume',
        'pe_oi',
    ];
    
}
