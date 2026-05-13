<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CourseLesson extends Model
{
    protected $fillable = [
        'course_id',
        'course_section_id',
        'title',
        'description',
        // Main lesson video
        'video_type',
        'video_url',
        'video_path',
        'video_disk',
        // Lesson overview / preview video (optional teaser shown before purchase)
        'preview_video_type',   // none | youtube | upload
        'preview_video_url',
        'preview_video_path',
        // Meta
        'duration_seconds',
        // is_free_preview & is_downloadable kept in DB but removed from admin UI
        'is_free_preview',
        'is_downloadable',
        'attachment',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'is_free_preview' => 'boolean',
        'is_downloadable' => 'boolean',
        'duration_seconds' => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function section()
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    // ── Duration Helpers ───────────────────────────────────────────────────

    /**
     * Human-readable lesson duration, e.g. "1:23:45" or "23:45".
     */
    public function getFormattedDurationAttribute(): string
    {
        $s   = (int) $this->duration_seconds;
        $h   = floor($s / 3600);
        $m   = floor(($s % 3600) / 60);
        $sec = $s % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $sec)
            : sprintf('%d:%02d', $m, $sec);
    }

    // ── Main Video Helpers ─────────────────────────────────────────────────

    public function getSecureVideoUrlAttribute(): ?string
    {
        if ($this->video_type === 'youtube') {
            return $this->video_url;
        }
        if ($this->video_path && Storage::disk('course_videos')->exists($this->video_path)) {
            return route('admin.courses.lesson.stream', $this->id);
        }
        return null;
    }

    public function getYoutubeEmbedIdAttribute(): ?string
    {
        if ($this->video_type !== 'youtube' || !$this->video_url) return null;
        preg_match(
            '/(?:youtube\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            $this->video_url,
            $m
        );
        return $m[1] ?? null;
    }

    // ── Lesson Preview / Overview Video Helpers ────────────────────────────

    /**
     * YouTube embed ID for the lesson overview video.
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
     * Whether this lesson has any overview video configured.
     */
    public function getHasPreviewAttribute(): bool
    {
        return $this->preview_video_type !== 'none'
            && ($this->preview_video_url || $this->preview_video_path);
    }
}