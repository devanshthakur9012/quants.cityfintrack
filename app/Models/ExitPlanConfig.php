<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExitPlanConfig extends Model
{
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'order_type',
        'product',
        'disc_ltp',
        'signal_mode',
        'index_ce_quantity',
        'index_pe_quantity',
        'stock_ce_quantity',
        'stock_pe_quantity',
        'status',
    ];

    protected $casts = [
        'status'   => 'boolean',
        'disc_ltp' => 'float',
    ];

    // ── Relationships ─────────────────────────────────────────

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(ExitPlanOrder::class, 'config_id');
    }

    // ── Helpers ───────────────────────────────────────────────

    /**
     * signal_mode = 'align'    → place SELL on EXIT decision (normal)
     * signal_mode = 'opposite' → place SELL on HOLD decision (contrarian)
     */
    public function shouldReverseSignal(): bool
    {
        return $this->signal_mode === 'opposite';
    }

    /**
     * Resolve SELL quantity based on option type and whether symbol is index or stock.
     */
    public function resolveQuantity(string $optionType, bool $isIndex): int
    {
        if ($isIndex) {
            return $optionType === 'CE'
                ? (int) $this->index_ce_quantity
                : (int) $this->index_pe_quantity;
        }

        return $optionType === 'CE'
            ? (int) $this->stock_ce_quantity
            : (int) $this->stock_pe_quantity;
    }
}