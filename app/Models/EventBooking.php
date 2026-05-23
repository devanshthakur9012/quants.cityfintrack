<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventBooking extends Model
{
    use HasFactory;
     protected $fillable = [
        'event_id','user_id','name','email','phone','city','message',
        'payment_type','amount','payment_status',
        'order_number','gateway_order_id','gateway_payment_id',
        'gateway_signature','gateway_response','paid_at','status',
    ];
    protected $casts = ['paid_at'=>'datetime','amount'=>'integer'];
    public function event()  { return $this->belongsTo(Event::class); }
    public function user()   { return $this->belongsTo(User::class); }
    public function isPaid() { return $this->payment_status === 'paid'; }
    public static function generateOrderNumber(): string {
        return 'EVT-'.strtoupper(Str::random(4)).'-'.time();
    }
}