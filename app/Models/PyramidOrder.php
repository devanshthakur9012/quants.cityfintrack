<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PyramidOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'symbol',
        'expiry_date',
        'strike_price',
        'option_type',
        'transaction_type',
        'manual_ltp',
        'base_discount_pct',
        'discount_increment_pct',
        'lots_per_order',
        'num_pyramids',
        'lot_size',
        'status',
        'orders_placed',
        'error_message',
        'executed_at',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'manual_ltp' => 'decimal:2',
        'strike_price' => 'decimal:2',
        'base_discount_pct' => 'decimal:4',
        'discount_increment_pct' => 'decimal:4',
        'lots_per_order' => 'integer',
        'num_pyramids' => 'integer',
        'lot_size' => 'integer',
        'orders_placed' => 'integer',
        'executed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PyramidOrderDetail::class);
    }

    public function getContractNameAttribute(): string
    {
        return sprintf(
            '%s %s %s %s',
            $this->symbol,
            $this->expiry_date->format('dMy'),
            $this->strike_price,
            $this->option_type
        );
    }
}
