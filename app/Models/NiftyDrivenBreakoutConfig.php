<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NiftyDrivenBreakoutConfig extends Model
{
    protected $table = 'nifty_driven_breakout_configs';

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'threshold',
        'filter',
        'signal_mode',
        'order_type',
        'product',
        'disc_ltp',
        'quantity_mode',
        // lots mode
        'index_ce_quantity',
        'index_pe_quantity',
        'stock_ce_quantity',
        'stock_pe_quantity',
        // investment mode
        'index_ce_investment',
        'index_pe_investment',
        'stock_ce_investment',
        'stock_pe_investment',
        // stop-loss (downside)
        'enable_stoploss',
        'stoploss_type',        // 'pct' | 'points'
        'stoploss_value',       // numeric — % or absolute points BELOW entry
        'stoploss_order_type',  // 'SL-M' | 'SL'
        // profit target (upside)
        'enable_target',
        'target_type',          // 'pct' | 'points'
        'target_value',         // numeric — % or absolute points ABOVE entry
        'target_order_type',    // 'SL-M' | 'SL' (limit sell at target)
        // misc
        'allowed_symbols',
        'status',
    ];

    protected $casts = [
        'threshold'           => 'decimal:2',
        'disc_ltp'            => 'decimal:2',
        'stoploss_value'      => 'decimal:2',
        'target_value'        => 'decimal:2',
        'index_ce_investment' => 'decimal:2',
        'index_pe_investment' => 'decimal:2',
        'stock_ce_investment' => 'decimal:2',
        'stock_pe_investment' => 'decimal:2',
        'enable_stoploss'     => 'boolean',
        'enable_target'       => 'boolean',
        'status'              => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

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
        return $this->hasMany(NiftyDrivenBreakoutOrder::class, 'config_id');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function shouldReverseSignal(): bool
    {
        return $this->signal_mode === 'opposite';
    }

    /**
     * Return allowed symbols as a clean array.
     * Empty array means ALL symbols.
     */
    public function getAllowedSymbols(): array
    {
        if (empty($this->allowed_symbols)) return [];
        return array_values(
            array_filter(
                array_map('trim', explode(',', strtoupper($this->allowed_symbols)))
            )
        );
    }

    /**
     * Resolve the number of LOTS to buy for a given symbol / option type.
     *
     * ── quantity_mode = 'lots' ──────────────────────────────────────────────
     *   Returns the configured lot count directly.
     *
     * ── quantity_mode = 'investment' ─────────────────────────────────────────
     *   investment ÷ (ltp × lot_size) → floor'd to whole lots.
     *   Investment is PER SYMBOL PER ORDER — not shared across symbols.
     *
     *   Example with 10 symbols and Index CE = ₹1,00,000:
     *     Each of the 10 symbols gets its own ₹1,00,000 budget independently.
     *     NIFTY: floor(1,00,000 ÷ (250 × 25)) = 16 lots
     *     BANKNIFTY: floor(1,00,000 ÷ (180 × 15)) = 37 lots
     *     ...each symbol calculated separately.
     *
     * @param  string $baseSymbol  e.g. 'NIFTY', 'BANKNIFTY', 'RELIANCE'
     * @param  string $optionType  'CE' | 'PE'
     * @param  float  $ltp         current LTP of the option
     * @param  int    $lotSize     lot size for the symbol
     * @return int                 number of LOTS (0 = skip this symbol)
     */
    public function resolveQuantity(
        string $baseSymbol,
        string $optionType,
        float  $ltp     = 0,
        int    $lotSize = 1
    ): int {
        $isIndex = in_array(
            strtoupper($baseSymbol),
            ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY', 'SENSEX', 'BANKEX']
        );

        // ── Investment mode ───────────────────────────────────────────────────
        if ($this->quantity_mode === 'investment') {
            if ($isIndex) {
                $investment = $optionType === 'CE'
                    ? (float) $this->index_ce_investment
                    : (float) $this->index_pe_investment;
            } else {
                $investment = $optionType === 'CE'
                    ? (float) $this->stock_ce_investment
                    : (float) $this->stock_pe_investment;
            }

            if ($investment <= 0 || $ltp <= 0 || $lotSize <= 0) return 0;

            $costPerLot = $ltp * $lotSize;
            return (int) floor($investment / $costPerLot);
        }

        // ── Fixed lots mode ───────────────────────────────────────────────────
        if ($isIndex) {
            return $optionType === 'CE'
                ? (int) ($this->index_ce_quantity ?? 0)
                : (int) ($this->index_pe_quantity ?? 0);
        }

        return $optionType === 'CE'
            ? (int) ($this->stock_ce_quantity ?? 0)
            : (int) ($this->stock_pe_quantity ?? 0);
    }

    /**
     * Compute the stop-loss TRIGGER price (BELOW entry — protects against loss).
     *
     * stoploss_type = 'pct'    → trigger = entry × (1 − stoploss_value / 100)
     *   e.g. entry=₹200, value=30% → trigger = 200 × 0.70 = ₹140.00
     *
     * stoploss_type = 'points' → trigger = entry − stoploss_value
     *   e.g. entry=₹200, value=50 pts → trigger = 200 − 50 = ₹150.00
     *
     * Returns null if stop-loss is disabled or entry price is 0.
     */
    public function computeStoplossPrice(float $entryPrice): ?float
    {
        if (!$this->enable_stoploss || $entryPrice <= 0) return null;

        $value = (float) $this->stoploss_value;
        if ($value <= 0) return null;

        if ($this->stoploss_type === 'pct') {
            $trigger = $entryPrice * (1 - $value / 100);
        } else {
            $trigger = $entryPrice - $value;
        }

        if ($trigger <= 0) return null;

        return round($trigger, 2);
    }

    /**
     * Compute the profit-target TRIGGER price (ABOVE entry — locks in profit).
     *
     * target_type = 'pct'    → trigger = entry × (1 + target_value / 100)
     *   e.g. entry=₹200, value=50% → trigger = 200 × 1.50 = ₹300.00
     *
     * target_type = 'points' → trigger = entry + target_value
     *   e.g. entry=₹200, value=80 pts → trigger = 200 + 80 = ₹280.00
     *
     * Returns null if target is disabled or entry price is 0.
     */
    public function computeTargetPrice(float $entryPrice): ?float
    {
        if (!$this->enable_target || $entryPrice <= 0) return null;

        $value = (float) $this->target_value;
        if ($value <= 0) return null;

        if ($this->target_type === 'pct') {
            $trigger = $entryPrice * (1 + $value / 100);
        } else {
            $trigger = $entryPrice + $value;
        }

        return round($trigger, 2);
    }
}