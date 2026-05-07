<?php
// ============================================================
//  app/Models/FutContrarianConfig.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FutContrarianConfig extends Model
{
    protected $table = 'fut_contrarian_configs';

    protected $fillable = [
        'user_id', 'broker_api_id',
        'trade_30min', 'trade_1hr',
        'order_type', 'product', 'disc_ltp',
        'index_ce_quantity', 'index_pe_quantity',
        'stock_ce_quantity', 'stock_pe_quantity',
        'allowed_symbols', 'status',
        // alignment_mode removed — logic is now:
        //   trade_30min only  → FUT + 30min OI must match
        //   trade_1hr only    → FUT + 1hr OI must match
        //   both enabled      → FUT + 30min + 1hr ALL must match
    ];

    protected $casts = [
        'trade_30min'        => 'boolean',
        'trade_1hr'          => 'boolean',
        'status'             => 'boolean',
        'disc_ltp'           => 'decimal:2',
        'allowed_symbols'    => 'array',
        'index_ce_quantity'  => 'integer',
        'index_pe_quantity'  => 'integer',
        'stock_ce_quantity'  => 'integer',
        'stock_pe_quantity'  => 'integer',
    ];

    private const INDEX_SYMBOLS = ['NIFTY','BANKNIFTY','FINNIFTY','MIDCPNIFTY','SENSEX','BANKEX'];

    // ── Relationships ──────────────────────────────────────────

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
        return $this->hasMany(FutContrarianOrder::class, 'config_id');
    }

    // ── Helpers ────────────────────────────────────────────────

    public function isSymbolAllowed(string $symbol): bool
    {
        $allowed = $this->allowed_symbols;
        if ($allowed === null) return true;
        if (empty($allowed)) return false;
        return in_array(strtoupper(trim($symbol)), array_map('strtoupper', $allowed), true);
    }

    public function isIndex(string $symbol): bool
    {
        return in_array(strtoupper($symbol), self::INDEX_SYMBOLS, true);
    }

    public function getQuantityForSymbol(string $symbol, string $optionType): int
    {
        $isIndex = $this->isIndex($symbol);
        return $optionType === 'CE'
            ? ($isIndex ? $this->index_ce_quantity : $this->stock_ce_quantity)
            : ($isIndex ? $this->index_pe_quantity : $this->stock_pe_quantity);
    }

    /**
     * Human-readable label for the window mode (used in logs and UI).
     * 30min only / 1hr only / Both required
     */
    public function windowModeLabel(): string
    {
        if ($this->trade_30min && $this->trade_1hr)  return 'Both (30min + 1HR all must match)';
        if ($this->trade_30min)                       return '30-min only';
        if ($this->trade_1hr)                         return '1-HR only';
        return 'None';
    }
}