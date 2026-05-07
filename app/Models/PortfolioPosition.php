<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PortfolioPosition extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'broker_api_id',
        'broker_name',
        'tradingsymbol',
        'exchange',
        'instrument_token',
        'product',
        'purchase_date',
        'purchase_price',
        'quantity',
        'overnight_quantity',
        'average_price',
        'last_price',
        'pnl',
        'value',
        'buy_sell',
        'position_status',
        'target_profit_percent',
        'target_sell_price',
        'square_off_order_id',
        'square_off_status',
        'fetched_at'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'overnight_quantity' => 'integer',
        'purchase_price' => 'decimal:2',
        'average_price' => 'decimal:2',
        'last_price' => 'decimal:2',
        'pnl' => 'decimal:2',
        'value' => 'decimal:2',
        'target_profit_percent' => 'decimal:2',
        'target_sell_price' => 'decimal:2',
        'purchase_date' => 'datetime',
        'fetched_at' => 'datetime'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function broker()
    {
        return $this->belongsTo(BrokerApi::class, 'broker_api_id');
    }

    // Check if position is profitable
    public function isProfitable()
    {
        return $this->pnl > 0;
    }

    // Get P&L percentage
    public function getPnlPercentage()
    {
        if ($this->value == 0) return 0;
        return round(($this->pnl / abs($this->value)) * 100, 2);
    }

    // Check if position is from today
    public function isPurchasedToday()
    {
        if (!$this->purchase_date) return false;
        return Carbon::parse($this->purchase_date)->isToday();
    }

    // Check if position is old (before today)
    public function isOldPosition()
    {
        if (!$this->purchase_date) return false;
        return Carbon::parse($this->purchase_date)->lt(Carbon::today());
    }

    // Get purchase age in days
    public function getPurchaseAgeInDays()
    {
        if (!$this->purchase_date) return 0;
        return Carbon::parse($this->purchase_date)->diffInDays(Carbon::now());
    }

    // Check if position is open
    public function isOpen()
    {
        return $this->position_status === 'open';
    }

    // Check if position is closed
    public function isClosed()
    {
        return $this->position_status === 'closed';
    }

    // Get recommended profit target based on age
    public function getRecommendedProfitTarget()
    {
        return $this->isPurchasedToday() ? 10 : 20; // 10% for today, 20% for old
    }

    // Calculate target sell price
    public function calculateTargetSellPrice($profitPercent = null)
    {
        if ($profitPercent === null) {
            $profitPercent = $this->getRecommendedProfitTarget();
        }
        
        return round($this->average_price * (1 + ($profitPercent / 100)), 2);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('position_status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('position_status', 'closed');
    }

    public function scopePurchasedToday($query)
    {
        return $query->whereDate('purchase_date', Carbon::today());
    }

    public function scopeOldPositions($query)
    {
        return $query->where('purchase_date', '<', Carbon::today());
    }

    public function scopeForBroker($query, $brokerId)
    {
        return $query->where('broker_api_id', $brokerId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeProfitable($query)
    {
        return $query->where('pnl', '>', 0);
    }

    public function scopeLossMaking($query)
    {
        return $query->where('pnl', '<', 0);
    }

    // Format purchase date for display
    public function getFormattedPurchaseDateAttribute()
    {
        if (!$this->purchase_date) return 'N/A';
        
        return Carbon::parse($this->purchase_date)->format('d M Y, h:i A');
    }

    // Get position type badge class
    public function getPositionTypeBadgeClassAttribute()
    {
        return $this->buy_sell === 'LONG' ? 'badge--success' : 'badge--danger';
    }

    // Get P&L badge class
    public function getPnlBadgeClassAttribute()
    {
        return $this->isProfitable() ? 'text--success' : 'text--danger';
    }

    // Get position status badge class
    public function getStatusBadgeClassAttribute()
    {
        return $this->isOpen() ? 'badge--primary' : 'badge--secondary';
    }
}