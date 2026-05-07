<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StengthTb extends Model
{
    use HasFactory;
    protected $table = "strength_tb";

    protected $fillable = [
        'symbol_name',
        'ce_iv',
        'pe_iv',
        'ce_delta',
        'pe_delta',
        'ce_theta',
        'pe_theta',
        'ce_vega',
        'pe_vega',
        'ce_gamma',
        'pe_gamma',
        'strength',
        'timestamp'
    ];
}
