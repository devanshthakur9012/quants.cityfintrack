<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * OiivPosition
 *
 * Tracks open and closed positions that originated exclusively from
 * the OIIV auto-trading system.
 *
 * Created automatically by the order-sync command when an
 * oiiv_order_book row transitions to COMPLETE.
 *
 * Closed when:
 *   a) A SELL order (placed via our UI) completes, OR
 *   b) Zerodha positions API shows qty = 0 (manual square-off detected), OR
 *   c) Expiry date passes
 */
class OiivPosition extends Model
{
    protected $table = 'oiiv_positions';

    protected $fillable = [
        'user_id', 'broker_api_id',
        'oiiv_auto_order_id', 'entry_order_book_id', 'exit_order_book_id',
        'trading_symbol', 'base_symbol', 'exchange', 'option_type',
        'strike_price', 'expiry_date', 'instrument_token',
        'signal_date', 'signal_type', 'sentiment', 'oi_condition',
        'spot_price_at_signal', 'ce_oi_change_pct', 'pe_oi_change_pct',
        'position_type', 'product',
        'quantity', 'quantity_units', 'lot_size',
        'entry_price', 'entry_ltp_at_signal', 'entry_at',
        'exit_price', 'exit_at', 'exit_source',
        'last_price', 'unrealized_pnl', 'realized_pnl', 'pnl_percentage',
        'status', 'is_btst', 'last_synced_at',
    ];

    protected $casts = [
        'entry_at'           => 'datetime',
        'exit_at'            => 'datetime',
        'last_synced_at'     => 'datetime',
        'expiry_date'        => 'date',
        'signal_date'        => 'date',
        'entry_price'        => 'decimal:2',
        'exit_price'         => 'decimal:2',
        'last_price'         => 'decimal:2',
        'unrealized_pnl'     => 'decimal:2',
        'realized_pnl'       => 'decimal:2',
        'pnl_percentage'     => 'decimal:4',
        'spot_price_at_signal' => 'decimal:2',
        'strike_price'       => 'decimal:2',
        'ce_oi_change_pct'   => 'decimal:4',
        'pe_oi_change_pct'   => 'decimal:4',
        'is_btst'            => 'boolean',
    ];

    // ── Status constants ──────────────────────────────────────────────────
    const STATUS_OPEN    = 'open';
    const STATUS_CLOSED  = 'closed';
    const STATUS_EXPIRED = 'expired';

    // ── Relationships ─────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function entryOrder()
    {
        return $this->belongsTo(OiivOrderBook::class, 'entry_order_book_id');
    }

    public function exitOrder()
    {
        return $this->belongsTo(OiivOrderBook::class, 'exit_order_book_id');
    }

    public function oiivAutoOrder()
    {
        return $this->belongsTo(OIIVAutoOrder::class, 'oiiv_auto_order_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_CLOSED);
    }

    public function scopeForBroker(Builder $q, int $brokerId): Builder
    {
        return $q->where('broker_api_id', $brokerId);
    }

    public function scopeForDate(Builder $q, string $date): Builder
    {
        return $q->where('signal_date', $date);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Compute and store unrealized P&L from a live LTP.
     */
    public function updateLtp(float $ltp): void
    {
        if ($ltp <= 0 || $this->status !== self::STATUS_OPEN) return;

        $entry    = (float) $this->entry_price;
        $units    = $this->quantity_units ?? ($this->quantity * $this->lot_size);
        $pnl      = ($ltp - $entry) * $units;
        $pnlPct   = $entry > 0 ? (($ltp - $entry) / $entry) * 100 : 0;

        $this->update([
            'last_price'      => $ltp,
            'unrealized_pnl'  => round($pnl, 2),
            'pnl_percentage'  => round($pnlPct, 4),
            'last_synced_at'  => now(),
        ]);
    }

    /**
     * Close this position with the given exit data.
     */
    public function close(float $exitPrice, string $source, ?int $exitOrderId = null): void
    {
        $entry    = (float) $this->entry_price;
        $units    = $this->quantity_units ?? ($this->quantity * $this->lot_size);
        $realized = round(($exitPrice - $entry) * $units, 2);
        $pnlPct   = $entry > 0 ? round((($exitPrice - $entry) / $entry) * 100, 4) : 0;

        $this->update([
            'status'           => self::STATUS_CLOSED,
            'exit_price'       => $exitPrice,
            'exit_at'          => now(),
            'exit_source'      => $source,
            'exit_order_book_id' => $exitOrderId,
            'realized_pnl'     => $realized,
            'unrealized_pnl'   => 0,
            'pnl_percentage'   => $pnlPct,
            'last_price'       => $exitPrice,
        ]);
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getHoldingDaysAttribute(): int
    {
        $from = $this->entry_at ?? $this->created_at;
        $to   = $this->exit_at  ?? now();
        return (int) $from->diffInDays($to);
    }

    public function getPnlColorAttribute(): string
    {
        $pnl = $this->status === self::STATUS_OPEN
            ? $this->unrealized_pnl
            : $this->realized_pnl;
        return ((float)$pnl) >= 0 ? 'success' : 'danger';
    }

    public function getActivePnlAttribute(): float
    {
        return (float) ($this->status === self::STATUS_OPEN
            ? $this->unrealized_pnl
            : $this->realized_pnl);
    }
}