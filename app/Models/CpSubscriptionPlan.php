<?php
// FILE: app/Models/CpSubscriptionPlan.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpSubscriptionPlan extends Model
{
    protected $table = 'cp_subscription_plans';

    protected $fillable = [
        'name', 'slug', 'description', 'price_monthly',
        'features', 'badge_color', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'features'      => 'array',
        'is_active'     => 'boolean',
        'price_monthly' => 'decimal:2',
    ];

    public function analyses()
    {
        return $this->belongsToMany(
            CpAnalysis::class,
            'cp_plan_analyses',
            'cp_subscription_plan_id',
            'cp_analysis_id'
        );
    }

    public function subscriptions()
    {
        return $this->hasMany(CpUserSubscription::class, 'cp_subscription_plan_id');
    }

    public function getIsFreeAttribute(): bool
    {
        return $this->slug === 'free' || $this->price_monthly == 0;
    }

    public function getActiveSubscribersCountAttribute(): int
    {
        return $this->subscriptions()->active()->count();
    }
}