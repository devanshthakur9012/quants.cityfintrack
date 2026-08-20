<?php
// FILE: app/Models/CpAnalysis.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpAnalysis extends Model
{
    protected $table = 'cp_analyses';

    protected $fillable = [
        'name', 'slug', 'route_name', 'thumbnail',
        'short_description', 'description', 'faqs', 'tags',
        'plan_tier', 'is_active', 'is_featured', 'sort_order', 'data_source',
    ];

    protected $casts = [
        'faqs'        => 'array',
        'tags'        => 'array',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function plans()
    {
        return $this->belongsToMany(
            CpSubscriptionPlan::class,
            'cp_plan_analyses',
            'cp_analysis_id',
            'cp_subscription_plan_id'
        );
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail
            ? asset('assets/images/cms/analyses/' . $this->thumbnail)
            : asset('assets/images/default-analysis.jpg');
    }

    public function getPlanBadgeAttribute(): array
    {
        return match ($this->plan_tier) {
            'pro'      => ['label' => 'Pro',      'color' => '#1a56db'],
            'pro_plus' => ['label' => 'Pro Plus',  'color' => '#7c3aed'],
            default    => ['label' => 'Free',      'color' => '#059669'],
        };
    }

    public function scopeActive($q)   { return $q->where('is_active', true); }
    public function scopeFeatured($q) { return $q->where('is_featured', true); }

    public function scopeForTier($q, string $tier)
    {
        $allowed = match ($tier) {
            'pro_plus' => ['free', 'pro', 'pro_plus'],
            'pro'      => ['free', 'pro'],
            default    => ['free'],
        };
        return $q->whereIn('plan_tier', $allowed);
    }

    public function orderConfigs()
    {
        return $this->hasMany(CpOrderConfig::class, 'cp_analysis_id');
    }
}