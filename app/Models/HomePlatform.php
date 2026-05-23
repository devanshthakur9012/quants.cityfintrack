<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomePlatform extends Model
{
    use HasFactory;
    protected $table = 'home_platform';
    protected $fillable = ['title','subtitle'];
}
