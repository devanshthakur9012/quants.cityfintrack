<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewPivotOrderConfig extends Model
{
    protected $table = 'new_pivot_order_configs';

    /**
     * Layer JSON structure (per signal level, separate for CE and PE):
     *
     * s1_ce_layers / s1_pe_layers / r1_ce_layers / r1_pe_layers
     * Each layer: {
     *   discount_direction : 'positive' | 'negative',
     *   discount_pct       : float,      // % away from S1/R1
     *   quantity           : int,        // lots/qty for this layer
     * }
     *
     * symbols: JSON array of base_symbol strings the user has opted into.
     * e.g. ["NIFTY", "BANKNIFTY"]
     * If null / empty → NO orders are placed (config is effectively idle).
     */
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'symbols',          // JSON — selected symbols (required, at least 1)
        'order_type',       // LIMIT | MARKET
        'product',          // NRML | MIS
        's1_ce_layers',     // JSON — S1 CE layers
        's1_pe_layers',     // JSON — S1 PE layers
        'r1_ce_layers',     // JSON — R1 CE layers
        'r1_pe_layers',     // JSON — R1 PE layers
        'status',
        'interval_type',   // ← add this
    ];

    protected $casts = [
        'symbols'      => 'array',
        's1_ce_layers' => 'array',
        's1_pe_layers' => 'array',
        'r1_ce_layers' => 'array',
        'r1_pe_layers' => 'array',
        'status'       => 'boolean',
        'interval_type' => 'string',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(NewPivotOrder::class, 'config_id');
    }

    // ── Symbol helpers ────────────────────────────────────────────────────────

    /**
     * Returns true if this config has at least one symbol selected.
     */
    public function hasSymbols(): bool
    {
        return !empty($this->symbols);
    }

    /**
     * Returns true if the given symbol is in this config's selected list.
     */
    public function hasSymbol(string $symbol): bool
    {
        return in_array(strtoupper($symbol), array_map('strtoupper', $this->symbols ?? []));
    }

    // ── Price helpers ─────────────────────────────────────────────────────────

    /**
     * Apply discount to a pivot level price for a given layer.
     *
     * @param float $price   Raw S1 or R1 value
     * @param array $layer   ['discount_direction' => 'positive|negative', 'discount_pct' => 2.5]
     */
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

    // ── Default layer seeds ───────────────────────────────────────────────────

    public static function defaultS1CeLayers(): array
    {
        return [
            ['discount_direction' => 'negative', 'discount_pct' => 2, 'quantity' => 0],
            ['discount_direction' => 'negative', 'discount_pct' => 4, 'quantity' => 0],
            ['discount_direction' => 'negative', 'discount_pct' => 6, 'quantity' => 0],
        ];
    }

    public static function defaultS1PeLayers(): array
    {
        return [
            ['discount_direction' => 'negative', 'discount_pct' => 2, 'quantity' => 0],
            ['discount_direction' => 'negative', 'discount_pct' => 4, 'quantity' => 0],
            ['discount_direction' => 'negative', 'discount_pct' => 6, 'quantity' => 0],
        ];
    }

    public static function defaultR1CeLayers(): array
    {
        return [
            ['discount_direction' => 'positive', 'discount_pct' => 2, 'quantity' => 0],
            ['discount_direction' => 'positive', 'discount_pct' => 4, 'quantity' => 0],
            ['discount_direction' => 'positive', 'discount_pct' => 6, 'quantity' => 0],
        ];
    }

    public static function defaultR1PeLayers(): array
    {
        return [
            ['discount_direction' => 'positive', 'discount_pct' => 2, 'quantity' => 0],
            ['discount_direction' => 'positive', 'discount_pct' => 4, 'quantity' => 0],
            ['discount_direction' => 'positive', 'discount_pct' => 6, 'quantity' => 0],
        ];
    }
}