<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BrokerApi extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_name',
        'broker_name',
        'account_user_name',
        'account_password',
        'api_key',
        'api_secret_key',
        'security_pin',
        'totp',
        'request_token',
        'client_type',
        'access_token',
        'token_expires_at',
        'last_login_at',
        'is_token_valid'
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_token_valid' => 'boolean'
    ];

    protected $hidden = [
        'account_password',
        'api_secret_key',
        'access_token'
    ];

    /**
     * Relationship: User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: Monitored Symbols
     */
    public function monitoredSymbols()
    {
        return $this->hasMany(SymbolMonitored::class, 'broker_api_id');
    }

    /**
     * Relationship: Zerodha Orders
     */
    public function zerodhaOrders()
    {
        return $this->hasMany(ZerodhaOrder::class, 'broker_api_id');
    }

    /**
     * Relationship: Zerodha Auto Configs
     */
    public function zerodhaAutoConfigs()
    {
        return $this->hasMany(ZerodhaAutoConfig::class, 'broker_api_id');
    }

    /**
     * Scope: Zerodha brokers only
     */
    public function scopeZerodha($query)
    {
        return $query->where('client_type', 'Zerodha');
    }

    /**
     * Scope: Valid token
     */
    public function scopeValidToken($query)
    {
        return $query->where('is_token_valid', true)
            ->where('token_expires_at', '>', now());
    }

    /**
     * Check if token is valid
     */
    public function hasValidToken()
    {
        return $this->is_token_valid && 
               $this->token_expires_at && 
               $this->token_expires_at->isFuture();
    }

    /**
     * Get token status
     */
    public function getTokenStatusAttribute()
    {
        if (!$this->access_token) {
            return 'Not Set';
        }

        if ($this->hasValidToken()) {
            return 'Valid';
        }

        return 'Expired';
    }

    /**
     * Get token expiry time remaining
     */
    public function getTokenExpiryRemainingAttribute()
    {
        if (!$this->token_expires_at || !$this->hasValidToken()) {
            return null;
        }

        return $this->token_expires_at->diffForHumans();
    }

    /**
     * AMO configurations relationship
     */
    public function amoConfigs()
    {
        return $this->hasMany(BrokerAmoConfig::class, 'broker_api_id');
    }

    /**
     * Get active AMO configs for specific date
     */
    public function getActiveAmoConfigs($date = null)
    {
        $date = $date ?? \Carbon\Carbon::today();
        
        return $this->amoConfigs()
            ->where('config_date', $date)
            ->where('is_active', true)
            ->get();
    }
    
}