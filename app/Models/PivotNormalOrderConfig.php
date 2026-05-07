<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PivotNormalOrderConfig
 *
 * Identical to PivotOrderConfig but for normal (regular) intraday orders
 * placed during market hours instead of AMO.
 *
 * Extra fields vs AMO:
 *   order_variety  — MARKET | LIMIT
 *   product        — MIS | NRML
 */
class PivotNormalOrderConfig extends Model
{
    protected $table = 'pivot_normal_order_configs';

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'model_type',
        'instrument_type',
        'order_variety',
        'product',
        's1_qty', 's1_discount', 's1_discount_type',
        's2_qty', 's2_discount', 's2_discount_type',
        's3_qty', 's3_buffer',   's3_buffer_type',
        'is_active',
    ];

    protected $casts = [
        's1_qty'      => 'integer',
        's2_qty'      => 'integer',
        's3_qty'      => 'integer',
        's1_discount' => 'float',
        's2_discount' => 'float',
        's3_buffer'   => 'float',
        'is_active'   => 'boolean',
    ];

    public function brokerApi()
    {
        return $this->belongsTo(BrokerApi::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Compute effective order price for a given level.
     *
     * S1 / S2 → price - discount
     * S3      → price + buffer
     */
    public function effectivePrice(float $rawPrice, string $level): float
    {
        if ($level === 'S3') {
            $delta = $this->s3_buffer_type === 'percent'
                ? ($rawPrice * $this->s3_buffer / 100)
                : $this->s3_buffer;
            return round($rawPrice + $delta, 2);
        }

        $discount = $level === 'S1' ? $this->s1_discount : $this->s2_discount;
        $type     = $level === 'S1' ? $this->s1_discount_type : $this->s2_discount_type;

        $delta = $type === 'percent'
            ? ($rawPrice * $discount / 100)
            : $discount;

        return round(max(0.05, $rawPrice - $delta), 2);
    }

    /**
     * Returns lot qty for a level.
     */
    public function qtyFor(string $level): int
    {
        return match ($level) {
            'S1'    => $this->s1_qty,
            'S2'    => $this->s2_qty,
            'S3'    => $this->s3_qty,
            default => 0,
        };
    }
}