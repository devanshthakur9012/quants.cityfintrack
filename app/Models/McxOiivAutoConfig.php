<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class McxOiivAutoConfig extends Model
{
    protected $table = 'mcx_oiiv_auto_configs';

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'order_type',
        'product',
        'disc_ltp',
        'signal_mode',
        'status',
        'ce_quantity',
        'pe_quantity',
        'rank1_ce_quantity',
        'rank1_pe_quantity',
        'rank2_ce_quantity',
        'rank2_pe_quantity',
        'rank3_ce_quantity',
        'rank3_pe_quantity',
        'rank4_ce_quantity',
        'rank4_pe_quantity',
        'option_series',
    ];

    protected $casts = [
        'disc_ltp' => 'decimal:2',
        'status'   => 'boolean',
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
        return $this->hasMany(McxOiivAutoOrder::class, 'config_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function getActiveConfigs()
    {
        return self::where('status', true)->get();
    }

    public function shouldReverseSignal(): bool
    {
        return $this->signal_mode === 'opposite';
    }

    public function useNextSeries(): bool
    {
        return ($this->option_series ?? 'current') === 'next';
    }

    /**
     * Rank = |CE% − PE%| — mirrors OIIVAutoConfig::computeStrengthRank()
     */
    public static function computeStrengthRank(float $ceChangePct, float $peChangePct): ?int
    {
        $diff = abs($ceChangePct - $peChangePct);
        if ($diff > 40) return 1;
        if ($diff > 25) return 2;
        if ($diff > 10) return 3;
        if ($diff > 5)  return 4;
        return null; // Normal — skip
    }

    /**
     * Signal direction — same logic as NFO version, commodity-agnostic.
     */
    public static function computeSignalDirection(float $ce, float $pe): string
    {
        $diff = abs($ce - $pe);
        if ($ce < 0 && $pe > 0) return 'BULLISH';
        if ($ce > 0 && $pe < 0) return 'BEARISH';
        if ($ce > 0 && $pe > 0) {
            if ($diff <= 5) return 'NORMAL';
            return $ce > $pe ? 'BEARISH' : 'BULLISH';
        }
        if ($ce < 0 && $pe < 0) {
            if ($diff <= 5) return 'NORMAL';
            return $ce < $pe ? 'BULLISH' : 'BEARISH';
        }
        return 'NORMAL';
    }

    /**
     * Get configured lots for a given rank + option type.
     * Returns 0 for null rank (Normal) — caller must skip.
     */
    public function getQuantityForRank(?int $rank, string $optionType): int
    {
        if ($rank === null) return 0;
        $field = "rank{$rank}_" . strtolower($optionType) . "_quantity";
        return (int) ($this->$field ?? 0);
    }
}