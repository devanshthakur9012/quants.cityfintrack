<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Symbol_mcx extends Model
{
    use HasFactory;
    protected $table = 'symbol_mcxes';

    protected $fillable = [
        'underlying',
        'symbol'
    ];
}
