<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeAbout extends Model
{
    use HasFactory;
    protected $table = 'home_about';
    protected $fillable = ['video_type','video_url','section_heading'];
    public function getVideoEmbedUrlAttribute(): ?string {
        if (!$this->video_url) return null;
        if ($this->video_type === 'youtube') {
            preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_\-]{11})/', $this->video_url, $m);
            return isset($m[1]) ? 'https://www.youtube.com/embed/'.$m[1].'?rel=0' : null;
        }
        return asset('assets/videos/cms/'.$this->video_url);
    }
}
