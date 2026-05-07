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
        'ref_by',
        'address',
        'status',
        'ev',
        'sv',
        'ts',
        'tv',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */ 
    protected $hidden = [
        'password', 'remember_token','ver_code','balance'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'address' => 'object',
        'ver_code_send_at' => 'datetime'
    ];

    public function referrals(){
        return $this->hasMany(self::class, 'ref_by');
    }

    public function referrer()
    {
        return $this->belongsTo(User::class,'ref_by');
    }

    public function loginLogs()
    {
        return $this->hasMany(UserLogin::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class)->orderBy('id','desc');
    }

    public function deposits()
    {
        return $this->hasMany(Deposit::class)->where('status','!=',Status::PAYMENT_INITIATE);
    }

    public function deviceTokens(){
        return $this->hasMany(DeviceToken::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function fullname(): Attribute
    {
        return new Attribute(
            get: fn () => $this->firstname . ' ' . $this->lastname,
        );
    }

    // SCOPES
    public function scopeActive($query)
    {
        return $query->where('status', Status::USER_ACTIVE)->where('ev',Status::VERIFIED)->where('sv',Status::VERIFIED);
    }

    public function scopeBanned($query)
    {
        return $query->where('status', Status::USER_BAN);
    }

    public function scopeEmailUnverified($query)
    {
        return $query->where('ev', Status::UNVERIFIED);
    }

    public function scopeMobileUnverified($query)
    {
        return $query->where('sv', Status::UNVERIFIED);
    }

    public function scopeEmailVerified($query)
    {
        return $query->where('ev', Status::VERIFIED);
    }

    public function scopeMobileVerified($query)
    {
        return $query->where('sv', Status::VERIFIED);
    }

    public function scopeWithBalance($query)
    {
        return $query->where('balance','>', 0);
    }

    // If user is an investor and has a trader
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id', 'id')->using('App\Models\UserParentLink');
    }

    // If user is a trader and has many investors
    public function investors()
    {
        return $this->hasManyThrough(User::class, UserParentLink::class, 'parent_id', 'id', 'id', 'user_id');
    }


}
