<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioConfig extends Model
{
    protected $fillable = [
        'user_id',
        'old_position_profit_percent',
        'fresh_position_profit_percent',
        'old_position_sell_profit_percent',
        'fresh_position_sell_profit_percent',
    ];

    protected $casts = [
        'old_position_profit_percent' => 'decimal:2',
        'fresh_position_profit_percent' => 'decimal:2',
        'old_position_sell_profit_percent' => 'decimal:2',
        'fresh_position_sell_profit_percent' => 'decimal:2',
    ];

    /**
     * Get or create config for user
     */
    public static function getForUser($userId)
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            [
                'old_position_profit_percent' => 20.00,
                'fresh_position_profit_percent' => 10.00,
                'old_position_sell_profit_percent' => 20.00,
                'fresh_position_sell_profit_percent' => 10.00,
            ]
        );
    }

    /**
     * User relationship
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}