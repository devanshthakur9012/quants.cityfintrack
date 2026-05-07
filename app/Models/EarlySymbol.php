<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EarlySymbol extends Model
{
    use HasFactory;
    protected $table = 'early_symbols';

    protected $fillable = [
        'underlying',
        'symbol',
        'symbol_token',
        'step_value'
    ];
    
}
