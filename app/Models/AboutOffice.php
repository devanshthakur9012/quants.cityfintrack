<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutOffice extends Model
{
    use HasFactory;
    protected $table = 'about_offices';
    protected $fillable = ['city','flag','tag','photo','desc','address','team','hours','status','sort_order'];
    public function getPhotoUrlAttribute(): ?string {
        if (!$this->photo) return null;
        return asset('assets/images/cms/offices/'.$this->photo);
    }
}
