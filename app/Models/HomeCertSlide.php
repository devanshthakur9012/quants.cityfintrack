<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeCertSlide extends Model
{
    use HasFactory;
    protected $table = 'home_cert_slides';
    protected $fillable = ['image','badge_text','language','status','sort_order'];
    public function getImageUrlAttribute(): ?string {
        if (!$this->image) return null;
        if (filter_var($this->image, FILTER_VALIDATE_URL)) return $this->image;
        return asset('assets/images/cms/cert/'.$this->image);
    }
}
