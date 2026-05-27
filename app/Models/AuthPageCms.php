<?php

// ─────────────────────────────────────────────────────────────────────────────
// FILE: app/Models/AuthPageCms.php
// ─────────────────────────────────────────────────────────────────────────────

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthPageCms extends Model
{
    protected $table = 'auth_page_cms';

    protected $fillable = [
        'promo_video_url',
        'features',
        'brokers',
        'login_heading',
        'login_subheading',
        'register_heading',
        'register_subheading',
    ];

    protected $casts = [
        'features' => 'array',
        'brokers'  => 'array',
    ];

    public static function getData(): self
    {
        return static::first() ?? new static();
    }

    public function getFeaturesListAttribute(): array
    {
        return $this->features ?? [
            '25 Free Real Time Tools',
            '59 Premium Real Time Tools',
            '2 Option Algorithm',
        ];
    }

    public function getBrokersListAttribute(): array
    {
        return $this->brokers ?? [
            ['name' => 'Zerodha',       'letter' => 'Z', 'bg' => '#e53935'],
            ['name' => 'Upstox',        'letter' => 'U', 'bg' => '#7b1fa2'],
            ['name' => 'Dhan',          'letter' => 'D', 'bg' => '#00897b'],
            ['name' => '5Paisa',        'letter' => '5', 'bg' => '#455a64'],
            ['name' => 'Motilal Oswal', 'letter' => 'M', 'bg' => '#f57f17'],
            ['name' => 'Fyers',         'letter' => 'F', 'bg' => '#1565c0'],
            ['name' => 'Choice',        'letter' => 'C', 'bg' => '#6a1b9a'],
            ['name' => 'Aliceblue',     'letter' => 'A', 'bg' => '#00838f'],
            ['name' => 'Sharekhan',     'letter' => 'S', 'bg' => '#bf360c'],
            ['name' => 'Angel',         'letter' => 'A', 'bg' => '#2e7d32'],
            ['name' => 'Groww',         'letter' => 'G', 'bg' => '#00695c'],
            ['name' => 'ICICI',         'letter' => 'I', 'bg' => '#b71c1c'],
            ['name' => 'HDFC Sky',      'letter' => 'H', 'bg' => '#1a237e'],
            ['name' => 'Kotak',         'letter' => 'K', 'bg' => '#e65100'],
        ];
    }
}