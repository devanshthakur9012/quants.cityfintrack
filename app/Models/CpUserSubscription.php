<?php
// FILE: app/Models/CpUserSubscription.php
namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class CpUserSubscription extends Model
{
    protected $table = 'cp_user_subscriptions';

    protected $fillable = [
        'user_id', 'cp_subscription_plan_id', 'status',
        'starts_at', 'expires_at', 'auto_renew', 'meta',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
        'meta'       => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(CpSubscriptionPlan::class, 'cp_subscription_plan_id');
    }

    public function payments()
    {
        return $this->hasMany(CpSubscriptionPayment::class, 'cp_user_subscription_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function getDaysRemainingAttribute(): int
    {
        if (!$this->expires_at) return 0;
        return max(0, (int) Carbon::now()->diffInDays($this->expires_at, false));
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active')
                 ->where(fn($q) => $q->whereNull('expires_at')
                     ->orWhere('expires_at', '>', now()));
    }
}