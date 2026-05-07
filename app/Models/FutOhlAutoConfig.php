<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FutOhlAutoConfig extends Model
{
    protected $table = 'fut_ohl_auto_configs';

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'tolerance',
        'signal_mode',
        'option_series',
        'order_type',
        'product',
        'disc_ltp',
        'ce_quantity',
        'pe_quantity',
        'status',
    ];

    protected $casts = [
        'tolerance' => 'decimal:2',
        'disc_ltp'  => 'decimal:2',
        'status'    => 'boolean',
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
        return $this->hasMany(FutOhlAutoOrder::class, 'config_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function shouldReverseSignal(): bool
    {
        return $this->signal_mode === 'opposite';
    }

    public function useNextSeries(): bool
    {
        return ($this->option_series ?? 'current') === 'next';
    }

    /**
     * Given an Open=High or Open=Low signal, return the actual option type to trade.
     *
     * Default (align):
     *   OPEN=HIGH → BUY PE
     *   OPEN=LOW  → BUY CE
     *
     * Opposite:
     *   OPEN=HIGH → BUY CE
     *   OPEN=LOW  → BUY PE
     */
    public function resolveOptionType(string $signalType): string
    {
        // signalType is 'OPEN=HIGH' or 'OPEN=LOW'
        $defaultMap = [
            'OPEN=HIGH' => 'PE',
            'OPEN=LOW'  => 'CE',
        ];

        $raw = $defaultMap[$signalType] ?? 'CE';

        if ($this->shouldReverseSignal()) {
            return $raw === 'CE' ? 'PE' : 'CE';
        }

        return $raw;
    }

    public function getQuantityForType(string $optionType): int
    {
        return $optionType === 'CE'
            ? (int)($this->ce_quantity ?? 0)
            : (int)($this->pe_quantity ?? 0);
    }
}