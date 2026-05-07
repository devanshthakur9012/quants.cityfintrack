<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * OiivOrderBook
 *
 * Represents a single order (or freeze-lot chunk) placed by the OIIV
 * auto-trading system.  Status vocabulary mirrors Zerodha exactly:
 *
 *   TRIGGER_PENDING  → AMO order waiting for market open
 *   OPEN             → Order live on exchange, fully or partially unfilled
 *   COMPLETE         → Fully executed
 *   CANCELLED        → Cancelled by user or system
 *   REJECTED         → Rejected by exchange / RMS
 *
 * Internal status (our own state machine):
 *   pending   → created, not yet sent to Zerodha
 *   placed    → placeOrder() called, zerodha_order_id assigned
 *   synced    → polled at least once from Zerodha
 *   completed → COMPLETE from Zerodha; position created
 *   cancelled → CANCELLED from Zerodha
 *   failed    → placeOrder() threw an exception
 *   modified  → price/qty changed after placement
 */
class OiivOrderBook extends Model
{
    protected $table = 'oiiv_order_book';

    protected $fillable = [
        'user_id', 'broker_api_id', 'oiiv_auto_order_id',
        'zerodha_order_id', 'zerodha_parent_order_id', 'zerodha_exchange_order_id',
        'trading_symbol', 'base_symbol', 'exchange', 'option_type',
        'strike_price', 'expiry_date', 'instrument_token',
        'signal_date', 'signal_type', 'oi_condition', 'sentiment',
        'spot_price_at_signal', 'ce_oi_change_pct', 'pe_oi_change_pct',
        'transaction_type', 'order_type', 'product', 'validity',
        'quantity', 'quantity_units', 'lot_size',
        'trigger_price', 'placed_price', 'average_price',
        'last_modified_price', 'filled_quantity', 'pending_quantity', 'cancelled_quantity',
        'status', 'status_message', 'internal_status',
        'lot_chunk_number', 'lot_chunk_total',
        'modify_count', 'modification_history',
        'signal_detected_at', 'placed_at', 'filled_at', 'cancelled_at', 'last_synced_at',
    ];

    protected $casts = [
        'modification_history' => 'array',
        'signal_detected_at'   => 'datetime',
        'placed_at'            => 'datetime',
        'filled_at'            => 'datetime',
        'cancelled_at'         => 'datetime',
        'last_synced_at'       => 'datetime',
        'expiry_date'          => 'date',
        'strike_price'         => 'decimal:2',
        'trigger_price'        => 'decimal:2',
        'placed_price'         => 'decimal:2',
        'average_price'        => 'decimal:2',
        'last_modified_price'  => 'decimal:2',
        'spot_price_at_signal' => 'decimal:2',
        'ce_oi_change_pct'     => 'decimal:4',
        'pe_oi_change_pct'     => 'decimal:4',
    ];

    // ── Zerodha status constants ──────────────────────────────────────────
    const STATUS_TRIGGER_PENDING = 'TRIGGER_PENDING';
    const STATUS_OPEN            = 'OPEN';
    const STATUS_COMPLETE        = 'COMPLETE';
    const STATUS_CANCELLED       = 'CANCELLED';
    const STATUS_REJECTED        = 'REJECTED';

    // ── Internal status constants ─────────────────────────────────────────
    const INT_PENDING   = 'pending';
    const INT_PLACED    = 'placed';
    const INT_SYNCED    = 'synced';
    const INT_COMPLETED = 'completed';
    const INT_CANCELLED = 'cancelled';
    const INT_FAILED    = 'failed';
    const INT_MODIFIED  = 'modified';

    // ── Relationships ─────────────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    public function oiivAutoOrder()
    {
        return $this->belongsTo(OIIVAutoOrder::class, 'oiiv_auto_order_id');
    }

    public function position()
    {
        return $this->hasOne(OiivPosition::class, 'entry_order_book_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeForBroker(Builder $q, int $brokerId): Builder
    {
        return $q->where('broker_api_id', $brokerId);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_OPEN);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('internal_status', self::INT_PENDING);
    }

    public function scopeNeedsSync(Builder $q): Builder
    {
        // Orders that are live and haven't been synced in the last 30 seconds
        return $q->whereIn('status', [self::STATUS_OPEN, self::STATUS_TRIGGER_PENDING])
                 ->where('internal_status', '!=', self::INT_COMPLETED)
                 ->where('internal_status', '!=', self::INT_CANCELLED)
                 ->where(function ($sq) {
                     $sq->whereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<', now()->subSeconds(30));
                 });
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereDate('created_at', today());
    }

    public function scopeForDate(Builder $q, string $date): Builder
    {
        return $q->where('signal_date', $date);
    }

    // ── Computed attributes ───────────────────────────────────────────────

    public function getIsLiveAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_TRIGGER_PENDING]);
    }

    public function getIsFilledAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETE;
    }

    public function getUnrealizedPnlAttribute(): ?float
    {
        // Requires last_price to be hydrated externally (by sync command)
        return null;
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_COMPLETE        => 'success',
            self::STATUS_OPEN            => 'primary',
            self::STATUS_TRIGGER_PENDING => 'warning',
            self::STATUS_CANCELLED       => 'secondary',
            self::STATUS_REJECTED        => 'danger',
            default                      => 'secondary',
        };
    }

    public function getStatusIconAttribute(): string
    {
        return match($this->status) {
            self::STATUS_COMPLETE        => '✅',
            self::STATUS_OPEN            => '🔵',
            self::STATUS_TRIGGER_PENDING => '⏳',
            self::STATUS_CANCELLED       => '❌',
            self::STATUS_REJECTED        => '🚫',
            default                      => '❓',
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Record a price modification in the history log.
     */
    public function recordModification(float $newPrice, string $source = 'USER'): void
    {
        $history = $this->modification_history ?? [];
        $history[] = [
            'from'   => $this->placed_price,
            'to'     => $newPrice,
            'source' => $source,
            'at'     => now()->toIso8601String(),
        ];

        $this->update([
            'placed_price'         => $newPrice,   // always keep placed_price = current active price
            'last_modified_price'  => $newPrice,   // also track as last_modified_price for display
            'modification_history' => $history,
            'modify_count'         => ($this->modify_count ?? 0) + 1,
            'internal_status'      => self::INT_MODIFIED,
        ]);
    }

    /**
     * Apply a raw Zerodha order object (stdClass or array) to this record.
     */
    public function applyZerodhaStatus(object|array $z): void
    {
        $z = is_array($z) ? (object) $z : $z;

        $data = [
            'status'                   => $z->status              ?? $this->status,
            'status_message'           => $z->status_message      ?? null,
            'average_price'            => $z->average_price       ?? $this->average_price,
            'filled_quantity'          => $z->filled_quantity      ?? $this->filled_quantity,
            'pending_quantity'         => $z->pending_quantity     ?? null,
            'cancelled_quantity'       => $z->cancelled_quantity   ?? 0,
            'zerodha_exchange_order_id'=> $z->exchange_order_id   ?? $this->zerodha_exchange_order_id,
            'last_synced_at'           => now(),
        ];

        $newStatus = $z->status ?? $this->status;

        if ($newStatus === self::STATUS_COMPLETE) {
            $data['internal_status'] = self::INT_COMPLETED;
            $data['filled_at']       = now();
        } elseif ($newStatus === self::STATUS_CANCELLED) {
            $data['internal_status'] = self::INT_CANCELLED;
            $data['cancelled_at']    = now();
        } elseif (in_array($newStatus, [self::STATUS_OPEN, self::STATUS_TRIGGER_PENDING])) {
            $data['internal_status'] = self::INT_SYNCED;
        }

        $this->update($data);
    }
}