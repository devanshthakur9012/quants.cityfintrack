<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventSection extends Model
{
    use HasFactory;
    protected $fillable = ['event_id','section_title','content','sort_order'];
    public function event() { return $this->belongsTo(Event::class); }
}
