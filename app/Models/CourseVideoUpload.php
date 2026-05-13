<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseVideoUpload extends Model
{
    protected $fillable = [
        'upload_id', 'course_lesson_id', 'original_name',
        'temp_path', 'final_path', 'file_size',
        'total_chunks', 'uploaded_chunks', 'status', 'mime_type',
    ];

    public function lesson()
    {
        return $this->belongsTo(CourseLesson::class, 'course_lesson_id');
    }

    public function getProgressAttribute(): int
    {
        if ($this->total_chunks === 0) return 0;
        return (int) round(($this->uploaded_chunks / $this->total_chunks) * 100);
    }
}