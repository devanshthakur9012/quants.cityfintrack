<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AboutFounderVision extends Model
{
    use HasFactory;
    protected $table = 'about_founder_vision';
    protected $fillable = ['name','title','signature','avatar','paragraphs','linkedin','twitter','youtube'];
    protected $casts = ['paragraphs' => 'array'];
    public function getAvatarUrlAttribute(): ?string {
        if (!$this->avatar) return null;
        return asset('assets/images/cms/founders/'.$this->avatar);
    }
    public function getParagraphsArrayAttribute(): array {
        return $this->paragraphs ?? [];
    }
}
