<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CourseCategory extends Model
{
    protected $fillable = [
        'name', 'slug', 'icon', 'color', 'description', 'status', 'sort_order',
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    public static function generateSlug(string $name, ?int $ignoreId = null): string
    {
        $slug  = Str::slug($name);
        $count = static::where('slug', 'LIKE', "{$slug}%")
                        ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                        ->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }
}