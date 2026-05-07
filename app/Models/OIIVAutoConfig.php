<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OIIVAutoConfig extends Model
{
    protected $table = 'oiiv_auto_configs';

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'order_type',
        'product',
        'disc_ltp',
        'index_quantity',
        'stock_quantity',
        'signal_mode',
        'status',
        'index_ce_quantity',
        'index_pe_quantity',
        'stock_ce_quantity',
        'stock_pe_quantity',
        'strong_ce_quantity',
        'strong_pe_quantity',
        'option_series',
        'rank1_ce_quantity',
        'rank1_pe_quantity',
        'rank2_ce_quantity',
        'rank2_pe_quantity',
        'rank3_ce_quantity',
        'rank3_pe_quantity',
        'rank4_ce_quantity',
        'rank4_pe_quantity',
        'config_type',
        'allowed_symbols',      // ← NEW: JSON array or null
    ];

    protected $casts = [
        'disc_ltp'        => 'decimal:2',
        'index_quantity'  => 'integer',
        'stock_quantity'  => 'integer',
        'status'          => 'boolean',
        'allowed_symbols' => 'array',  // ← auto encode/decode JSON
    ];

    // =========================================================
    //  RELATIONSHIPS
    // =========================================================

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
        return $this->hasMany(OIIVAutoOrder::class, 'config_id');
    }

    // =========================================================
    //  SYMBOL FILTER HELPERS
    // =========================================================

    /**
     * Returns true if this config should trade the given symbol.
     *
     * Logic:
     *   - allowed_symbols is NULL  → trade everything (no filter)
     *   - allowed_symbols is []    → trade nothing
     *   - allowed_symbols has items → only trade if symbol is in the list
     */
    public function isSymbolAllowed(string $symbol): bool
    {
        $allowed = $this->allowed_symbols;

        // NULL means no restriction — allow all
        if ($allowed === null) {
            return true;
        }

        // Empty array = effectively block all
        if (empty($allowed)) {
            return false;
        }

        return in_array(strtoupper(trim($symbol)), array_map('strtoupper', $allowed), true);
    }

    /**
     * Whether this config has an active symbol filter.
     */
    public function hasSymbolFilter(): bool
    {
        return $this->allowed_symbols !== null;
    }

    /**
     * Count of allowed symbols (null = unlimited).
     */
    public function allowedSymbolCount(): ?int
    {
        return $this->allowed_symbols === null ? null : count($this->allowed_symbols);
    }

    // =========================================================
    //  EXISTING HELPERS (unchanged)
    // =========================================================

    public static function getActiveConfigs()
    {
        return self::where('status', true)->get();
    }

    public function getQuantityForSymbol(string $tradingSymbol): int
    {
        $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);

        return in_array($baseSymbol, ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY'])
            ? $this->index_quantity
            : $this->stock_quantity;
    }

    public function getQuantityForSymbolAndType(string $tradingSymbol, string $optionType): int
    {
        $baseSymbol = preg_replace('/\d{2}[A-Z]{3}(FUT)?$/i', '', $tradingSymbol);

        if (in_array($baseSymbol, ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY'])) {
            return $optionType === 'CE'
                ? ($this->index_ce_quantity ?? $this->index_quantity ?? 0)
                : ($this->index_pe_quantity ?? $this->index_quantity ?? 0);
        }

        return $optionType === 'CE'
            ? ($this->stock_ce_quantity ?? $this->stock_quantity ?? 0)
            : ($this->stock_pe_quantity ?? $this->stock_quantity ?? 0);
    }

    public function shouldReverseSignal(): bool
    {
        return $this->signal_mode === 'opposite';
    }

    // =========================================================
    //  RANK-BASED METHODS
    // =========================================================

    public static function computeStrengthRank(float $ceChangePct, float $peChangePct): ?int
    {
        $diff = abs($ceChangePct - $peChangePct);

        if ($diff > 40) return 1;
        if ($diff > 25) return 2;
        if ($diff > 10) return 3;
        if ($diff > 5)  return 4;

        return null;
    }

    public static function computeSignalDirection(float $ce, float $pe): string
    {
        $diff           = abs($ce - $pe);
        $closeThreshold = 5;

        if ($ce < 0 && $pe > 0) return 'BULLISH';
        if ($ce > 0 && $pe < 0) return 'BEARISH';

        if ($ce > 0 && $pe > 0) {
            if ($diff <= $closeThreshold) return 'NORMAL';
            return $ce > $pe ? 'BEARISH' : 'BULLISH';
        }

        if ($ce < 0 && $pe < 0) {
            if ($diff <= $closeThreshold) return 'NORMAL';
            return $ce < $pe ? 'BULLISH' : 'BEARISH';
        }

        return 'NORMAL';
    }

    public function getQuantityForRank(?int $rank, string $optionType): int
    {
        if ($rank === null) {
            return 0;
        }

        $field = "rank{$rank}_" . strtolower($optionType) . "_quantity";

        return (int) ($this->$field ?? 0);
    }

    public function useNextSeries(): bool
    {
        return ($this->option_series ?? 'current') === 'next';
    }
}