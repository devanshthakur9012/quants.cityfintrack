<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualOptionData extends Model
{
    use HasFactory;

    
    protected $table = 'manual_option_data';

    protected $fillable = [
        'underlying',
        'date',
        'atm_minus_2_ce_oi',
        'atm_minus_2_pe_oi',
        'atm_minus_1_ce_oi',
        'atm_minus_1_pe_oi',
        'atm_ce_oi',
        'atm_pe_oi',
        'atm_plus_1_ce_oi',
        'atm_plus_1_pe_oi',
        'atm_plus_2_ce_oi',
        'atm_plus_2_pe_oi',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}
