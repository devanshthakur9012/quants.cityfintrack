<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OiBuildUp extends Model
{
    use HasFactory;
    protected $table = "angel_buildups";

    protected $fillable = [
        'symbol',
        'ltp',
        'net_change',
        'per_change',
        'oi',
        'oi_change',
        'type',
        'oi_signal',
        'price_diff',
        'oi_diff',
    ];
}
