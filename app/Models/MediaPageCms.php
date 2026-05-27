<?php
// FILE: app/Models/MediaPageCms.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaPageCms extends Model
{
    protected $table = 'media_page_cms';

    protected $fillable = [
        'hero_eyebrow', 'hero_title', 'hero_title_highlight', 'hero_subtitle',
        'cta_title', 'cta_description', 'cta_email', 'cta_btn_label',
    ];

    /** Convenience: fetch or return a new default instance */
    public static function getData(): self
    {
        return static::first() ?? new static();
    }
}