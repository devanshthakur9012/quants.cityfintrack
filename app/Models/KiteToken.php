<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KiteToken extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'username',
        'access_token',
        'user_data',
        'expires_at'
    ];

    protected $casts = [
        'user_data' => 'array',
        'expires_at' => 'datetime'
    ];

    /**
     * Scope to get only valid (non-expired) tokens
     */
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired tokens
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Check if the token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * Check if the token will expire soon (within 30 minutes)
     */
    public function expiresSoon(): bool
    {
        return $this->expires_at <= now()->addMinutes(30);
    }

    /**
     * Get the user data as array
     */
    public function getUserDataArray(): array
    {
        return $this->user_data ?? [];
    }

    /**
     * Clean up expired tokens (can be run via scheduled task)
     */
    public static function cleanupExpired(): int
    {
        return static::expired()->delete();
    }

    /**
     * Get token for a specific username
     */
    public static function getValidTokenForUser(string $username): ?self
    {
        return static::where('username', $username)
            ->valid()
            ->first();
    }
}
