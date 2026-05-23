<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeFeatureUtility extends Model
{
    use HasFactory;
    protected $table = 'home_feature_utilities';
    protected $fillable = ['count','label','tool_title','tool_icon','tool_points','status','sort_order'];
    protected $casts = ['tool_points' => 'array'];
}
