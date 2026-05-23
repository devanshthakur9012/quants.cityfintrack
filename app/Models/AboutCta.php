<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutCta extends Model
{
    use HasFactory;
    protected $table = 'about_cta';
    protected $fillable = ['heading','appstore','playstore','webapp'];
}
