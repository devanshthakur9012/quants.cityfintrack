<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Course extends Model
{
    protected $fillable = [
        'course_category_id', 'title', 'slug', 'short_description', 'description',
        'thumbnail', 'preview_video_type', 'preview_video_url',
        'mode', 'level', 'language', 'status',
        'batch_name', 'total_sessions', 'session_duration_hrs',
        'schedule_days', 'duration_label', 'start_date', 'end_date', 'class_time',
        'type', 'price', 'mrp', 'discount_label',
        'trainer_name', 'trainer_designation', 'trainer_avatar',
        'total_enrolled', 'rating', 'sort_order', 'is_featured',
        'meta_title', 'meta_description', 'meta_keywords',
    ];

    protected $casts = [
        'start_date'   => 'datetime',
        'end_date'     => 'datetime',
        'is_featured'  => 'boolean',
        'price'        => 'decimal:2',
        'mrp'          => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    public function category()
    {
        return $this->belongsTo(CourseCategory::class, 'course_category_id');
    }

    public function sections()
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort_order');
    }

    public function lessons()
    {
        return $this->hasMany(CourseLesson::class)->orderBy('sort_order');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['draft']);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    public static function generateSlug(string $title, ?int $ignoreId = null): string
    {
        $slug  = Str::slug($title);
        $count = static::where('slug', 'LIKE', "{$slug}%")
                        ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                        ->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }

    public function getThumbnailUrlAttribute(): string
    {
        if ($this->thumbnail && file_exists(public_path('assets/courses/thumbnails/' . $this->thumbnail))) {
            return asset('assets/courses/thumbnails/' . $this->thumbnail);
        }
        return asset('assets/images/course-default.jpg');
    }

    public function getFormattedPriceAttribute(): string
    {
        return '₹' . number_format($this->price);
    }

    public function getFormattedMrpAttribute(): string
    {
        return $this->mrp ? '₹' . number_format($this->mrp) : '';
    }

    public function getTotalLessonsCountAttribute(): int
    {
        return $this->relationLoaded('lessons')
            ? $this->lessons->count()
            : $this->lessons()->count();
    }

    public function getTotalDurationAttribute(): string
    {
        // Use already-loaded relation if available — prevents N+1
        $secs = $this->relationLoaded('lessons')
            ? $this->lessons->sum('duration_seconds')
            : $this->lessons()->sum('duration_seconds');
 
        $h = floor($secs / 3600);
        $m = floor(($secs % 3600) / 60);
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'upcoming' => '<span class="badge badge--success">Upcoming</span>',
            'ongoing'  => '<span class="badge badge--warning">Ongoing</span>',
            'recorded' => '<span class="badge badge--info">Recorded</span>',
            'draft'    => '<span class="badge badge--danger">Draft</span>',
            default    => '<span class="badge badge--secondary">' . ucfirst($this->status) . '</span>',
        };
    }

    // ── Additional Relationships ───────────────────────────────────────────
    public function enrollments()
    {
        return $this->hasMany(CourseEnrollment::class);
    }
 
    public function orders()
    {
        return $this->hasMany(CourseOrder::class);
    }
 
    // ── Enrollment check helper ────────────────────────────────────────────
    // Usage: $course->isEnrolledBy(auth()->user())
    public function isEnrolledBy(?User $user): bool
    {
        if (!$user) return false;
        return $this->enrollments()
                    ->where('user_id', $user->id)
                    ->where('status', 1)
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    })
                    ->exists();
    }
 
    // ── YouTube embed ID from preview url ─────────────────────────────────
    public function getPreviewEmbedIdAttribute(): ?string
    {
        if ($this->preview_video_type !== 'youtube' || !$this->preview_video_url) return null;
        preg_match(
            '/(?:youtube\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            $this->preview_video_url,
            $m
        );
        return $m[1] ?? null;
    }

     // ── FAQs ──────────────────────────────────────────────────────────────────
    public function faqs()
    {
        return $this->hasMany(\App\Models\CourseFaq::class)->orderBy('sort_order');
    }
 
    // ── Trainers — pivot to User model (employees) ────────────────────────────
    public function trainers()
    {
        return $this->belongsToMany(
            \App\Models\User::class,       // User model — employees have 'employee' role
            'course_trainer_pivot',        // pivot table
            'course_id',                   // FK on pivot pointing to courses
            'user_id'                      // FK on pivot pointing to users
        )
        ->withPivot('sort_order')
        ->with('employeeProfile')          // eager-load designation etc.
        ->orderBy('course_trainer_pivot.sort_order');
    }
 
    // ── Auto-calculate price from MRP + discount_percent ──────────────────────
    // Used in controller before saving
    public function recalculatePrice(): void
    {
        if ($this->mrp > 0 && $this->discount_percent > 0) {
            $this->price          = round($this->mrp * (1 - $this->discount_percent / 100));
            $this->discount_label = round($this->discount_percent) . '% off';
        }
    }
}