<?php
// FILE: app/Models/Webinar.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Webinar extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'slug', 'status', 'type', 'mode', 'address', 'language', 'level',
        'webinar_date', 'duration', 'total_seats', 'total_enrolled',
        'price', 'mrp', 'discount_percent', 'discount_label',
        'youtube_url', 'thumbnail', 'sort_order', 'is_featured',
    ];

    protected $casts = [
        'webinar_date'     => 'datetime',
        'is_featured'      => 'boolean',
        'price'            => 'integer',
        'mrp'              => 'integer',
        'total_seats'      => 'integer',
        'total_enrolled'   => 'integer',
        'discount_percent' => 'decimal:2',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function faqs()
    {
        return $this->hasMany(WebinarFaq::class)->orderBy('sort_order');
    }

    public function speakers()
    {
        return $this->belongsToMany(User::class, 'webinar_speaker_pivot', 'webinar_id', 'user_id')
                    ->withPivot('sort_order')
                    ->orderBy('webinar_speaker_pivot.sort_order')
                    ->with('employeeProfile');
    }

    public function tools()
    {
        return $this->hasMany(WebinarTool::class)->orderBy('sort_order');
    }

    public function enrollments()
    {
        return $this->hasMany(WebinarEnrollment::class);
    }

    public function orders()
    {
        return $this->hasMany(WebinarOrder::class);
    }

    // ── Scopes ───────────────────────────────────────────────────────────────

    public function scopeUpcoming($q) { return $q->where('status', 'upcoming'); }
    public function scopeLive($q)     { return $q->where('status', 'live'); }
    public function scopePast($q)     { return $q->where('status', 'past'); }

    public function scopeFilter($q, array $filters)
    {
        $q->when($filters['language'] ?? null, fn($q, $v) => $q->where('language', $v))
        ->when($filters['type']     ?? null, fn($q, $v) => $q->where('type', $v))
        ->when($filters['level']    ?? null, fn($q, $v) => $q->where('level', 'like', $v . '%'))
        ->when($filters['search']   ?? null, fn($q, $v) => $q->where('title', 'like', '%' . $v . '%'));
        return $q;  // ← this line is mandatory
    }

    // ── Accessors ────────────────────────────────────────────────────────────

    public function getThumbnailUrlAttribute(): string
    {
        if (!$this->thumbnail) return asset('assets/images/webinar/placeholder.jpg');
        if (filter_var($this->thumbnail, FILTER_VALIDATE_URL)) return $this->thumbnail;
        return asset('assets/images/webinar/' . $this->thumbnail);
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->webinar_date ? $this->webinar_date->format('d-M-y H:i') : '—';
    }

    public function getLevelKeyAttribute(): string
    {
        return strtolower(explode(' ', $this->level)[0]);
    }

    public function getSeatsLeftAttribute(): ?int
    {
        if ($this->total_seats === null) return null;
        return max(0, $this->total_seats - $this->total_enrolled);
    }

    public function getFormattedPriceAttribute(): string
    {
        return $this->type === 'free' ? 'FREE' : '₹' . number_format($this->price);
    }

    public function getDiscountLabelAutoAttribute(): string
    {
        if ($this->discount_percent > 0) {
            return round($this->discount_percent) . '% off';
        }
        return $this->discount_label ?? '';
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isEnrolledBy($user): bool
    {
        if (!$user) return false;
        return $this->enrollments()
            ->where('user_id', $user->id)
            ->where('status', 1)
            ->exists();
    }

    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $slug = $base = Str::slug($title);
        $i = 1;
        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) { $slug = $base . '-' . $i++; }
        return $slug;
    }

    public static function generateOrderNumber(): string
    {
        return 'WEB-' . strtoupper(Str::random(3)) . '-' . time();
    }
}
