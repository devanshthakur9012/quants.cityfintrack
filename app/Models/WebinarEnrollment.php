<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebinarEnrollment extends Model
{
    use HasFactory;
    protected $fillable = [
        'webinar_id', 'user_id', 'webinar_order_id',
        'access_type', 'enrolled_at', 'status',
    ];

    protected $casts = ['enrolled_at' => 'datetime'];

    public function webinar() { return $this->belongsTo(Webinar::class); }
    public function user()    { return $this->belongsTo(User::class); }
    public function order()   { return $this->belongsTo(WebinarOrder::class, 'webinar_order_id'); }
}
