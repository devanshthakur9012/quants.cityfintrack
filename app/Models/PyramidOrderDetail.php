<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PyramidOrderDetail extends Model
{
    use HasFactory;
    public $timestamps = false;

    protected $fillable = [
        'pyramid_order_id',
        'pyramid_index',
        'effective_discount_pct',
        'order_price',
        'quantity',
        'angel_order_id',
        'angel_symbol',
        'angel_token',
        'order_status',
        'status_message',
        'placed_at',
        'updated_at',
    ];

    protected $casts = [
        'pyramid_index' => 'integer',
        'effective_discount_pct' => 'decimal:4',
        'order_price' => 'decimal:2',
        'quantity' => 'integer',
        'placed_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function pyramidOrder(): BelongsTo
    {
        return $this->belongsTo(PyramidOrder::class);
    }
}