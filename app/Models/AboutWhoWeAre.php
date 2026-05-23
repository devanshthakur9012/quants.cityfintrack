<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutWhoWeAre extends Model
{
    use HasFactory;
    protected $table = 'about_who_we_are';
    protected $fillable = ['heading','body','pillars'];
    protected $casts = ['pillars' => 'array'];
}
