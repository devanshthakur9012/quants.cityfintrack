<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Searchable;

class UserLogin extends Model
{
    use Searchable;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
