<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'sort_order',
        'status',
        // Preview / overview video for this section
        'preview_video_type',   // none | youtube | upload
        'preview_video_url',
        'preview_video_path',
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function lessons()
    {
        return $this->hasMany(CourseLesson::class)->orderBy('sort_order');
    }

    // ── Duration Helpers ───────────────────────────────────────────────────

    /**
     * Total duration in seconds — uses already-loaded relation to avoid N+1.
     */
    public function getTotalDurationSecondsAttribute(): int
    {
        return $this->relationLoaded('lessons')
            ? (int) $this->lessons->sum('duration_seconds')
            : (int) $this->lessons()->sum('duration_seconds');
    }

    /**
     * Human-readable total duration string, e.g. "1h 23m" or "45m".
     */
    public function getTotalDurationAttribute(): string
    {
        $secs = $this->total_duration_seconds;
        $h    = floor($secs / 3600);
        $m    = floor(($secs % 3600) / 60);
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    // ── Preview Video Helpers ──────────────────────────────────────────────

    /**
     * YouTube embed ID extracted from the section preview URL.
     */
    public function getPreviewEmbedIdAttribute(): ?string
    {
        if ($this->preview_video_type !== 'youtube' || !$this->preview_video_url) {
            return null;
        }
        preg_match(
            '/(?:youtube\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            $this->preview_video_url,
            $m
        );
        return $m[1] ?? null;
    }

    /**
     * Whether this section has any preview video configured.
     */
    public function getHasPreviewAttribute(): bool
    {
        return $this->preview_video_type !== 'none'
            && ($this->preview_video_url || $this->preview_video_path);
    }
}