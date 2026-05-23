<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebinarFaq extends Model
{
    use HasFactory;
    protected $fillable = ['webinar_id', 'question', 'answer', 'sort_order', 'status'];

    public function webinar()
    {
        return $this->belongsTo(Webinar::class);
    }
}
