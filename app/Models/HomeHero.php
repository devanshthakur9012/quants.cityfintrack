<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeHero extends Model
{
    use HasFactory;
    protected $table = 'home_hero';
    protected $fillable = ['video_file','heading_line1','heading_highlight','heading_line2'];
    public function getVideoUrlAttribute(): ?string {
        if (!$this->video_file) return null;
        return asset('assets/video/'.$this->video_file);
    }
}
