<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventGalleryItem extends Model
{
    use HasFactory;
    protected $fillable = ['event_id','image','title','sort_order'];
    public function event() { return $this->belongsTo(Event::class); }
    public function getImageUrlAttribute(): string {
        if (!$this->image) return '';
        if (filter_var($this->image, FILTER_VALIDATE_URL)) return $this->image;
        return asset('assets/images/events/gallery/'.$this->image);
    }
}
