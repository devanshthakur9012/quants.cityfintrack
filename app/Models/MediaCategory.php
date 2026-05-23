<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MediaCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    // ── Relations ────────────────────────────────────────────────────────────
    public function mediaItems()
    {
        return $this->hasMany(MediaItem::class)->orderBy('sort_order')->orderByDesc('created_at');
    }

    // ── Slug generation ──────────────────────────────────────────────────────
    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $slug  = Str::slug($name);
        $base  = $slug;
        $count = 1;
        while (
            static::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base . '-' . $count++;
        }
        return $slug;
    }
}