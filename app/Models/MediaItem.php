<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaItem extends Model
{
    protected $fillable = [
        'media_category_id', 'title', 'description',
        'file_name', 'file_original_name', 'file_type',
        'mime_type', 'file_size', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    // ── Relations ────────────────────────────────────────────────────────────
    public function category()
    {
        return $this->belongsTo(MediaCategory::class, 'media_category_id');
    }

    // ── Accessors ────────────────────────────────────────────────────────────
    public function getFileUrlAttribute(): string
    {
        return asset('assets/images/media/' . $this->file_name);
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function getIsVideoAttribute(): bool
    {
        return $this->file_type === 'video';
    }

    public function getIsImageAttribute(): bool
    {
        return $this->file_type === 'image';
    }
}