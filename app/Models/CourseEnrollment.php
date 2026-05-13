<?php
// FILE: app/Models/CourseEnrollment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    protected $fillable = [
        'user_id', 'course_id', 'course_order_id',
        'access_type', 'enrolled_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
        'expires_at'  => 'datetime',
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

    public function order()
    {
        return $this->belongsTo(CourseOrder::class, 'course_order_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────
    public function isActive(): bool
    {
        if ($this->status !== 1) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}