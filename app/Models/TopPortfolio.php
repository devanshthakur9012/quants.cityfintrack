<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopPortfolio extends Model
{
    use HasFactory;
    protected $table = "angel_top_portfolio";

    protected $fillable = [
        'token',
        'symbol',
        'ltp',
        'net_change',
        'per_change',
        'type',
    ];
}
