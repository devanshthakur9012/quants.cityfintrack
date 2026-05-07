<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AutoTargetOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'broker_api_id',
        'broker_name',
        'tradingsymbol',
        'exchange',
        'product',
        'instrument_token',
        'quantity',
        'buy_price',
        'entry_value',
        'target_percentage',
        'target_price',
        'current_price',
        'current_profit',
        'current_profit_percentage',
        'target_order_id',
        'exchange_order_id',
        'order_status',
        'position_entry_at',
        'target_placed_at',
        'target_triggered_at',
        'completed_at',
        'last_checked_at',
        'error_message',
        'retry_count',
        'is_active',
        'is_frozen',
    ];

    protected $casts = [
        'buy_price' => 'decimal:2',
        'target_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'entry_value' => 'decimal:2',
        'current_profit' => 'decimal:2',
        'target_percentage' => 'decimal:2',
        'current_profit_percentage' => 'decimal:2',
        'position_entry_at' => 'datetime',
        'target_placed_at' => 'datetime',
        'target_triggered_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'is_active' => 'boolean',
        'is_frozen' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function brokerApi()
    {
        return $this->belongsTo(BrokerApi::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePending($query)
    {
        return $query->where('order_status', 'PENDING');
    }

    public function scopePlaced($query)
    {
        return $query->where('order_status', 'PLACED');
    }

    public function scopeForMonitoring($query)
    {
        return $query->whereIn('order_status', ['PENDING', 'PLACED'])
                    ->where('is_active', true);
    }

    /**
     * Calculate target price based on buy price and percentage
     */
    public function calculateTargetPrice(): float
    {
        return round($this->buy_price * (1 + ($this->target_percentage / 100)), 2);
    }

    /**
     * Calculate current profit
     */
    public function calculateCurrentProfit(): array
    {
        if (!$this->current_price) {
            return [
                'profit' => 0,
                'profit_percentage' => 0
            ];
        }

        $profit = ($this->current_price - $this->buy_price) * $this->quantity;
        $profitPercentage = (($this->current_price - $this->buy_price) / $this->buy_price) * 100;

        return [
            'profit' => round($profit, 2),
            'profit_percentage' => round($profitPercentage, 2)
        ];
    }

    /**
     * Check if target is reached
     */
    public function isTargetReached(): bool
    {
        if (!$this->current_price) {
            return false;
        }

        return $this->current_price >= $this->target_price;
    }

    /**
     * Update current price and profit
     */
    public function updateCurrentMetrics(float $currentPrice): void
    {
        $this->current_price = $currentPrice;
        
        $metrics = $this->calculateCurrentProfit();
        $this->current_profit = $metrics['profit'];
        $this->current_profit_percentage = $metrics['profit_percentage'];
        
        $this->last_checked_at = now();
        $this->save();
    }

    /**
     * Mark as target placed
     */
    public function markAsPlaced(string $orderId, ?string $exchangeOrderId = null): void
    {
        $this->target_order_id = $orderId;
        $this->exchange_order_id = $exchangeOrderId;
        $this->order_status = 'PLACED';
        $this->target_placed_at = now();
        $this->error_message = null;
        $this->save();
    }

    /**
     * Mark as triggered
     */
    public function markAsTriggered(): void
    {
        $this->order_status = 'TRIGGERED';
        $this->target_triggered_at = now();
        $this->save();
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->order_status = 'COMPLETED';
        $this->completed_at = now();
        $this->is_active = false;
        $this->save();
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->order_status = 'FAILED';
        $this->error_message = $errorMessage;
        $this->retry_count++;
        $this->save();
    }

    /**
     * Mark as cancelled
     */
    public function markAsCancelled(string $reason = null): void
    {
        $this->order_status = 'CANCELLED';
        $this->is_active = false;
        if ($reason) {
            $this->error_message = $reason;
        }
        $this->save();
    }

    /**
     * Mark as expired
     */
    public function markAsExpired(string $reason = 'Position closed before target'): void
    {
        $this->order_status = 'EXPIRED';
        $this->is_active = false;
        $this->error_message = $reason;
        $this->save();
    }
}