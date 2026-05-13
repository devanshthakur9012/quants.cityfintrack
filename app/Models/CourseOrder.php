<?php
// FILE: app/Models/CourseOrder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CourseOrder extends Model
{
    protected $fillable = [
        'order_number', 'user_id', 'course_id', 'gateway',
        'amount', 'original_price', 'currency', 'status',
        'gateway_order_id', 'gateway_payment_id', 'gateway_signature',
        'gateway_response', 'paid_at',
    ];

    protected $casts = [
        'paid_at'  => 'datetime',
        'amount'   => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function enrollment()
    {
        return $this->hasOne(CourseEnrollment::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    public static function generateOrderNumber(): string
    {
        do {
            $num = 'CQ-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (static::where('order_number', $num)->exists());

        return $num;
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'paid'     => '<span class="badge badge--success">Paid</span>',
            'pending'  => '<span class="badge badge--warning">Pending</span>',
            'failed'   => '<span class="badge badge--danger">Failed</span>',
            'refunded' => '<span class="badge badge--info">Refunded</span>',
            default    => '<span class="badge badge--secondary">' . ucfirst($this->status) . '</span>',
        };
    }
}