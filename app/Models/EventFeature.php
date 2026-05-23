<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventFeature extends Model
{
    use HasFactory;
    protected $fillable = ['event_id', 'icon', 'value', 'label', 'sort_order'];
    public function event() { return $this->belongsTo(Event::class); }
}
