<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutWorkspace extends Model
{
    use HasFactory;
    protected $table = 'about_workspace';
    protected $fillable = ['heading','sub'];
}
