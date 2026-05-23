<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebinarTool extends Model
{
    use HasFactory;
    protected $fillable = ['webinar_id', 'title', 'description', 'image', 'sort_order'];

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }

    public function getImageUrlAttribute(): string
    {
        if (!$this->image) return '';
        if (filter_var($this->image, FILTER_VALIDATE_URL)) return $this->image;
        return asset('assets/images/webinar/tools/' . $this->image);
    }

}
