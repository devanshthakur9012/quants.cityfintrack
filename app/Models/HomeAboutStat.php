<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeAboutStat extends Model
{
    use HasFactory;
    protected $table = 'home_about_stats';
    protected $fillable = ['value','label','sub','sort_order'];
}
