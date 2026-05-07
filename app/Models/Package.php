<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Package extends Model
{
    use HasFactory, GlobalStatus;

    protected $casts = ['features'=>'array'];

    public function user(){
        return $this->hasMany(User::class);
    }

    public function scopeActive(){
        return $this->where('status', 1);
    }

}
