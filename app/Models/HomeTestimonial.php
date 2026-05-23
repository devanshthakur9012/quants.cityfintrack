<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeTestimonial extends Model
{
    use HasFactory;
    protected $table = 'home_testimonials';
    protected $fillable = ['name','avatar','rating','review','status','sort_order'];
    protected $casts = ['rating' => 'integer'];
    public function getAvatarUrlAttribute(): ?string {
        if (!$this->avatar) return null;
        return asset('assets/images/cms/testimonials/'.$this->avatar);
    }
}
