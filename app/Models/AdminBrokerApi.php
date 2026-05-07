<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AdminBrokerApi extends Model
{
    protected $table = 'admin_broker_apis';

    protected $fillable = [
        'client_name',
        'broker_name',
        'account_user_name',
        'account_password',
        'api_key',
        'api_secret_key',
        'security_pin',
        'totp',
        'client_type',
        'access_token',
        'is_token_valid',
        'token_expires_at',
        'last_login_at',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_token_valid' => 'boolean',
        'is_active'      => 'boolean',
        'token_expires_at' => 'datetime',
        'last_login_at'    => 'datetime',
    ];

    protected $hidden = [
        'account_password',
        'api_secret_key',
        'totp',
        'access_token',
    ];

    /**
     * Check whether the stored access token is currently valid.
     */
    public function hasValidToken(): bool
    {
        return $this->is_token_valid
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }

    /**
     * Human-readable time remaining on the token.
     */
    public function getTokenExpiryRemainingAttribute(): string
    {
        if (!$this->hasValidToken()) {
            return 'Expired';
        }

        $diff = now()->diff($this->token_expires_at);

        if ($diff->h > 0) {
            return "Expires in {$diff->h}h {$diff->i}m";
        }

        return "Expires in {$diff->i}m";
    }

    /**
     * Masked API key for display.
     */
    public function getMaskedApiKeyAttribute(): string
    {
        return substr($this->api_key, 0, 12) . '...';
    }

    /**
     * Scope: only Zerodha brokers.
     */
    public function scopeZerodha($query)
    {
        return $query->where('client_type', 'Zerodha');
    }

    /**
     * Scope: only active brokers.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}