<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutFounder extends Model
{
    use HasFactory;
    protected $table = 'about_founders';
    protected $fillable = ['name','role','credentials','bio','avatar','linkedin','twitter','status','sort_order'];
    public function getAvatarUrlAttribute(): ?string {
        if (!$this->avatar) return null;
        return asset('assets/images/cms/founders/'.$this->avatar);
    }
}
