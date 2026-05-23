<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutWorkspaceSlide extends Model
{
    use HasFactory;
    protected $table = 'about_workspace_slides';
    protected $fillable = ['image','caption','sub_caption','tag','status','sort_order'];
    public function getImageUrlAttribute(): ?string {
        if (!$this->image) return null;
        return asset('assets/images/cms/workspace/'.$this->image);
    }
}
