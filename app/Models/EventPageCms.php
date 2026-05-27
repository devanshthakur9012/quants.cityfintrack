<?php
// FILE: app/Models/EventPageCms.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPageCms extends Model
{
    protected $table = 'event_page_cms';

    protected $fillable = [
        'hero_eyebrow', 'hero_title', 'hero_title_highlight', 'hero_subtitle',
        'cities', 'cta_title', 'cta_description', 'cta_btn_label', 'cta_btn_url',
    ];

    protected $casts = [
        'cities' => 'array',
    ];

    public static function getData(): self
    {
        return static::first() ?? new static();
    }

    /**
     * Returns cities as an associative array: ['key' => 'Display Label']
     * Falls back to the hardcoded list from the original blade.
     */
    public function getCitiesMapAttribute(): array
    {
        if (!empty($this->cities)) {
            $map = [];
            foreach ($this->cities as $c) {
                if (is_array($c) && isset($c['key'], $c['label'])) {
                    $map[$c['key']] = $c['label'];
                }
            }
            if ($map) return $map;
        }
        return [
            'bangalore'   => 'Bangalore',
            'koramangala' => 'Koramangala',
            'indiranagar' => 'Indiranagar',
            'whitefield'  => 'Whitefield',
            'delhi'       => 'Delhi',
            'noida'       => 'Noida',
            'gurgaon'     => 'Gurgaon',
            'mumbai'      => 'Mumbai',
            'pune'        => 'Pune',
            'hyderabad'   => 'Hyderabad',
            'chennai'     => 'Chennai',
        ];
    }
}

