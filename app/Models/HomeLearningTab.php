<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeLearningTab extends Model
{
    use HasFactory;
    protected $table = 'home_learning_tabs';
    protected $fillable = ['tab_label','highlight_text','description','btn_label','btn_url','video_id','video_title','video_sub','video_date','video_time','status','sort_order'];
}
