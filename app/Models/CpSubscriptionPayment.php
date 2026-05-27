<?php
// FILE: app/Models/CpSubscriptionPayment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CpSubscriptionPayment extends Model
{
    protected $table = 'cp_subscription_payments';

    protected $fillable = [
        'order_number', 'user_id', 'cp_subscription_plan_id',
        'cp_user_subscription_id', 'gateway',
        'gateway_order_id', 'gateway_payment_id', 'gateway_signature',
        'gateway_response', 'amount', 'currency',
        'status', 'paid_at', 'failure_reason',
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
        'amount'           => 'decimal:2',
    ];

    public function user()         { return $this->belongsTo(User::class); }
    public function plan()         { return $this->belongsTo(CpSubscriptionPlan::class, 'cp_subscription_plan_id'); }
    public function subscription() { return $this->belongsTo(CpUserSubscription::class, 'cp_user_subscription_id'); }

    public function isPaid(): bool { return $this->status === 'paid'; }

    public static function generateOrderNumber(): string
    {
        return 'CP-' . strtoupper(substr(uniqid(), -6)) . '-' . time();
    }
}