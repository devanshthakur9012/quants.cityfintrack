<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Searchable;
use App\Traits\UserNotify;
use App\Constants\Status;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Searchable, UserNotify, HasRoles;

    protected $fillable = [
        'firstname',
        'lastname',
        'username',
        'email',
        'mobile',
        'mobile_code',
        'country_code',
        'country',
        'password',
        'user_code',
        'telegram_username',
        'ref_by',
        'address',
        'status',
        'ev',
        'sv',
        'ts',
        'tv',
        'ban_reason',
        'profile_pic', 
    ];

    protected $hidden = [
        'password', 'remember_token', 'ver_code',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'address'           => 'object',
        'ver_code_send_at'  => 'datetime',
    ];

    // ─────────────────────────────────────────────
    //  RELATIONS
    // ─────────────────────────────────────────────

    public function loginLogs()
    {
        return $this->hasMany(UserLogin::class);
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    // ─────────────────────────────────────────────
    //  ACCESSORS
    // ─────────────────────────────────────────────

    public function fullname(): Attribute
    {
        return new Attribute(
            get: fn () => $this->firstname . ' ' . $this->lastname,
        );
    }

    public function roleNames(): Attribute
    {
        return new Attribute(
            get: fn () => $this->getRoleNames()->implode(', '),
        );
    }

    // ─────────────────────────────────────────────
    //  SCOPES
    // ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', Status::USER_ACTIVE)
                     ->where('ev', Status::VERIFIED)
                     ->where('sv', Status::VERIFIED);
    }

    public function scopeBanned($query)
    {
        return $query->where('status', Status::USER_BAN);
    }

    public function scopeEmailVerified($query)
    {
        return $query->where('ev', Status::VERIFIED);
    }

    public function scopeEmailUnverified($query)
    {
        return $query->where('ev', Status::UNVERIFIED);
    }

    public function scopeMobileVerified($query)
    {
        return $query->where('sv', Status::VERIFIED);
    }

    public function scopeMobileUnverified($query)
    {
        return $query->where('sv', Status::UNVERIFIED);
    }
}