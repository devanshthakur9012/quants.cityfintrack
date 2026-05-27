<?php
// FILE: app/Models/CoursePageCms.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursePageCms extends Model
{
    protected $table = 'course_page_cms';

    protected $fillable = [
        'hero_title', 'hero_description',
        'hero_banners', 'languages', 'levels', 'modes',
    ];

    protected $casts = [
        'hero_banners' => 'array',
        'languages'    => 'array',
        'levels'       => 'array',
        'modes'        => 'array',
    ];

    public static function getData(): self
    {
        return static::first() ?? new static();
    }

    public function getBannersAttribute(): array
    {
        $banners = $this->hero_banners ?? [];
        return array_filter(array_map(function ($b) {
            return is_string($b) && $b
                ? (str_starts_with($b, 'http') ? $b : asset('assets/images/cms/course_banners/' . $b))
                : null;
        }, $banners));
    }

    public function getLanguagesListAttribute(): array
    {
        return $this->languages ?? ['Hindi', 'English', 'Gujarati'];
    }

    public function getLevelsListAttribute(): array
    {
        return $this->levels ?? ['Beginner', 'Intermediate', 'Advanced'];
    }

    public function getModesListAttribute(): array
    {
        return $this->modes ?? ['Online', 'Offline', 'Hybrid'];
    }
}