<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserParentLink extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'parent_id'];

    public function investor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function trader()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }
}
