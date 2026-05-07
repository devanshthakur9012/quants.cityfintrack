<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Searchable;

class Transaction extends Model
{
    use Searchable;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function poolingAccountPortfolio()
    {
        return $this->belongsTo(PoolingAccountPortfolio::class, 'pooling_account_id', 'id');
    }
}
