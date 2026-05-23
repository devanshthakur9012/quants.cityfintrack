<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutHero extends Model
{
    use HasFactory;
    protected $table = 'about_hero';
    protected $fillable = ['tagline','subtitle','founded','hq','users','experience','stat1_value','stat1_label','stat2_value','stat2_label','stat3_value','stat3_label','stat4_value','stat4_label'];
}
