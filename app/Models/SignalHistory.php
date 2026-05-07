<?php

namespace App\Models;

use App\Traits\Searchable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SignalHistory extends Model{

    use HasFactory, Searchable;

    public function user(){
        return $this->belongsTo(User::class)->withDefault();
    }

    public function signal(){
        return $this->belongsTo(Signal::class)->withDefault();
    }

}
