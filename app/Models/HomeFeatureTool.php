<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeFeatureTool extends Model
{
    use HasFactory;
    protected $table = 'home_feature_tools';
    protected $fillable = ['section_title'];
}
