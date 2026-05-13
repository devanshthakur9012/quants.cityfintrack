<?php
// FILE: app/Models/CoursePaymentGateway.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class CoursePaymentGateway extends Model
{
    protected $fillable = [
        'name', 'alias', 'description', 'logo',
        'credentials', 'status', 'test_mode',
    ];

    protected $casts = [
        'status'    => 'integer',
        'test_mode' => 'integer',
    ];

    // ── Credential helpers ─────────────────────────────────────────────────
    public function getCredential(string $key): ?string
    {
        $creds = $this->getRawCredentials();
        return $creds[$key] ?? null;
    }

    public function getRawCredentials(): array
    {
        if (!$this->credentials) return [];
        try {
            return json_decode(Crypt::decryptString($this->credentials), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function setCredentialsAttribute(array $value): void
    {
        $this->attributes['credentials'] = Crypt::encryptString(json_encode($value));
    }

    // ── Scopes ─────────────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public static function activeGateway(): ?self
    {
        return static::active()->first();
    }
}