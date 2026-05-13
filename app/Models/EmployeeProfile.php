<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'employee_code',
        'department',
        'designation',
        'date_of_joining',
        'salary',
        'emergency_contact',
        'notes',
    ];

    protected $casts = [
        'date_of_joining' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}