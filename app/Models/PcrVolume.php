<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PcrVolume extends Model
{
    use HasFactory;
    protected $table = "angel_pcr_volume";
    
    protected $fillable = ['symbol', 'pcr','name'];
}
