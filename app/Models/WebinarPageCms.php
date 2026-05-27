<?php
// FILE: app/Models/WebinarPageCms.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebinarPageCms extends Model
{
    protected $table = 'webinar_page_cms';

    protected $fillable = [
        'hero_title', 'hero_description', 'hero_illustration_url',
        'languages', 'proficiency_levels',
    ];

    protected $casts = [
        'languages'          => 'array',
        'proficiency_levels' => 'array',
    ];

    public static function getData(): self
    {
        return static::first() ?? new static();
    }

    public function getLanguagesListAttribute(): array
    {
        return $this->languages ?? ['Hindi', 'English', 'Gujarati'];
    }

    public function getProficiencyListAttribute(): array
    {
        return $this->proficiency_levels ?? ['Beginner', 'Intermediate', 'Advanced'];
    }
}