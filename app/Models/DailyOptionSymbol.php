<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyOptionSymbol extends Model
{
    protected $table = 'daily_option_symbols';

    protected $fillable = ['symbol', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Scope for active symbols
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
