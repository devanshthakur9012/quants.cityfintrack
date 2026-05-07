<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McxPivotOrderConfig extends Model
{
    protected $table = 'mcx_pivot_order_configs';

    /**
     * Layer structure (same as pivot configs — quantity per layer):
     * [{ discount_direction: 'positive|negative', discount_pct: float, quantity: int }]
     */
    protected $fillable = [
        'user_id', 'broker_api_id', 'order_type', 'product',
        's1_ce_layers', 's1_pe_layers', 'r1_ce_layers', 'r1_pe_layers',
        'status',
    ];

    protected $casts = [
        's1_ce_layers' => 'array',
        's1_pe_layers' => 'array',
        'r1_ce_layers' => 'array',
        'r1_pe_layers' => 'array',
        'status'       => 'boolean',
    ];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function broker(): BelongsTo { return $this->belongsTo(BrokerApi::class, 'broker_api_id'); }
    public function orders(): HasMany   { return $this->hasMany(McxPivotOrder::class, 'config_id'); }

    public function applyDiscount(float $price, array $layer): float
    {
        $pct = (float)($layer['discount_pct'] ?? 0);
        $dir = $layer['discount_direction'] ?? 'negative';
        if ($this->order_type === 'MARKET' || $pct == 0) return $price;
        $adjust = $price * ($pct / 100);
        return $dir === 'positive'
            ? round($price + $adjust, 2)
            : round($price - $adjust, 2);
    }

    public static function defaultS1Layers(): array
    {
        return [
            ['discount_direction' => 'negative', 'discount_pct' => 1, 'quantity' => 0],
            ['discount_direction' => 'negative', 'discount_pct' => 2, 'quantity' => 0],
            ['discount_direction' => 'negative', 'discount_pct' => 3, 'quantity' => 0],
        ];
    }

    public static function defaultR1Layers(): array
    {
        return [
            ['discount_direction' => 'positive', 'discount_pct' => 1, 'quantity' => 0],
            ['discount_direction' => 'positive', 'discount_pct' => 2, 'quantity' => 0],
            ['discount_direction' => 'positive', 'discount_pct' => 3, 'quantity' => 0],
        ];
    }
}