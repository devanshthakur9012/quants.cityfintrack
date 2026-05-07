<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserEnquiry extends Model
{
    use HasFactory;
    protected $table = "user_enquiry";

    public function package(){
        return $this->hasOne(Package::class, 'id', 'package_id');
    }

    public function user(){
        return $this->hasOne(User::class, 'id', 'user_id');
    }
}
