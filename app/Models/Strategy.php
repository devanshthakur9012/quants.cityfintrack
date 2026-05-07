<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Strategy extends Model
{
    use HasFactory;

    protected $table = "strategy";
    protected $fillable = [
        'strategy_name',
        'legs',
        'risk',
        'profit',
        'strategy_image',
        'market_trend',
        'strategy_status',
        'description',
        'is_deleted'
    ];
}
