<?php
// ============================================================
//  FILE 2: app/Models/FutContrarianOrder.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FutContrarianOrder extends Model
{
    protected $table = 'fut_contrarian_orders';

    protected $fillable = [
        'user_id', 'config_id', 'broker_api_id',
        'signal_date', 'base_symbol', 'trading_symbol',
        'fut_prev_close', 'fut_today_open', 'fut_change_pct', 'fut_direction',
        'trade_action', 'option_type', 'best_strike',
        'option_symbol', 'option_instrument_token',
        'entry_price', 'lot_size', 'quantity', 'expiry_date',
        'oi_30min_signal', 'oi_1hr_signal',
        'alignment_30min', 'alignment_1hr',
        'traded_30min', 'traded_1hr',
        'is_order_placed', 'order_placed_at',
        'signal_detected_at', 'status',
    ];

    protected $casts = [
        'signal_date'            => 'date',
        'fut_prev_close'         => 'decimal:2',
        'fut_today_open'         => 'decimal:2',
        'fut_change_pct'         => 'decimal:4',
        'best_strike'            => 'decimal:2',
        'entry_price'            => 'decimal:2',
        'lot_size'               => 'integer',
        'quantity'               => 'integer',
        'option_instrument_token'=> 'integer',
        'traded_30min'           => 'boolean',
        'traded_1hr'             => 'boolean',
        'is_order_placed'        => 'boolean',
        'status'                 => 'boolean',
        'signal_detected_at'     => 'datetime',
        'order_placed_at'        => 'datetime',
    ];

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function config(): BelongsTo  { return $this->belongsTo(FutContrarianConfig::class, 'config_id'); }
    public function broker(): BelongsTo  { return $this->belongsTo(BrokerApi::class, 'broker_api_id'); }
    public function orderBook(): HasMany { return $this->hasMany(FutContrarianOrderBook::class, 'fc_order_id'); }
}


// ============================================================
//  FILE 3: app/Models/FutContrarianOrderBook.php
// ============================================================

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FutContrarianOrderBook extends Model
{
    protected $table = 'fut_contrarian_order_book';

    // Zerodha status
    const STATUS_OPEN     = 'OPEN';
    const STATUS_COMPLETE = 'COMPLETE';
    const STATUS_CANCELLED= 'CANCELLED';
    const STATUS_REJECTED = 'REJECTED';

    // Internal status
    const INT_PENDING   = 'pending';
    const INT_PLACED    = 'placed';
    const INT_SYNCED    = 'synced';
    const INT_COMPLETED = 'completed';
    const INT_CANCELLED = 'cancelled';
    const INT_FAILED    = 'failed';

    protected $fillable = [
        'user_id', 'broker_api_id', 'fc_order_id',
        'zerodha_order_id', 'exchange_order_id',
        'trading_symbol', 'base_symbol', 'exchange',
        'option_type', 'strike_price', 'expiry_date', 'instrument_token',
        'signal_date', 'signal_window', 'oi_signal', 'fut_direction',
        'sentiment', 'spot_price_at_signal',
        'transaction_type', 'order_type', 'product', 'validity',
        'quantity', 'quantity_units', 'lot_size',
        'trigger_price', 'placed_price', 'average_price',
        'filled_quantity', 'pending_quantity', 'cancelled_quantity',
        'status', 'status_message', 'internal_status',
        'lot_chunk_number', 'lot_chunk_total', 'broker_type',
        'signal_detected_at', 'placed_at', 'filled_at',
        'cancelled_at', 'last_synced_at',
    ];

    protected $casts = [
        'signal_date'          => 'date',
        'expiry_date'          => 'date',
        'signal_detected_at'   => 'datetime',
        'placed_at'            => 'datetime',
        'filled_at'            => 'datetime',
        'cancelled_at'         => 'datetime',
        'last_synced_at'       => 'datetime',
        'strike_price'         => 'decimal:2',
        'trigger_price'        => 'decimal:2',
        'placed_price'         => 'decimal:2',
        'average_price'        => 'decimal:2',
        'spot_price_at_signal' => 'decimal:2',
    ];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function broker(): BelongsTo { return $this->belongsTo(BrokerApi::class, 'broker_api_id'); }
    public function fcOrder(): BelongsTo{ return $this->belongsTo(FutContrarianOrder::class, 'fc_order_id'); }

    public function scopeNeedsSync(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_OPEN])
                 ->whereNotIn('internal_status', [self::INT_COMPLETED, self::INT_CANCELLED])
                 ->where(function ($sq) {
                     $sq->whereNull('last_synced_at')
                        ->orWhere('last_synced_at', '<', now()->subSeconds(30));
                 });
    }

    public function applyZerodhaStatus(object|array $z): void
    {
        $z    = is_array($z) ? (object) $z : $z;
        $data = [
            'status'          => $z->status           ?? $this->status,
            'status_message'  => $z->status_message   ?? null,
            'average_price'   => $z->average_price    ?? $this->average_price,
            'filled_quantity' => $z->filled_quantity  ?? $this->filled_quantity,
            'last_synced_at'  => now(),
        ];
        $new = $z->status ?? $this->status;
        if ($new === self::STATUS_COMPLETE)  { $data['internal_status'] = self::INT_COMPLETED; $data['filled_at']    = now(); }
        if ($new === self::STATUS_CANCELLED) { $data['internal_status'] = self::INT_CANCELLED; $data['cancelled_at'] = now(); }
        if ($new === self::STATUS_OPEN)      { $data['internal_status'] = self::INT_SYNCED; }
        $this->update($data);
    }
}