<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebinarOrder extends Model
{
    use HasFactory;
     protected $fillable = [
        'order_number', 'user_id', 'webinar_id', 'gateway',
        'amount', 'currency', 'status',
        'gateway_order_id', 'gateway_payment_id', 'gateway_signature', 'gateway_response',
        'paid_at',
    ];

    protected $casts = ['paid_at' => 'datetime'];

    public function webinar()    { return $this->belongsTo(Webinar::class); }
    public function user()       { return $this->belongsTo(User::class); }
    public function enrollment() { return $this->hasOne(WebinarEnrollment::class); }

    public function isPaid(): bool { return $this->status === 'paid'; }
}
