<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        
        // ── Lessons (individual videos inside a section) ──────────────────
        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
 
            $table->string('title');
            $table->text('description')->nullable();
 
            // video source
            $table->string('video_type')->default('youtube');   // youtube | upload
            $table->string('video_url')->nullable();            // youtube URL
            $table->string('video_path')->nullable();           // uploaded file path (secure storage)
            $table->string('video_disk')->default('local');     // local | s3
            $table->integer('duration_seconds')->default(0);    // video length in seconds
 
            // state / meta
            $table->boolean('is_free_preview')->default(false); // available without purchase?
            $table->boolean('is_downloadable')->default(false);
            $table->string('attachment')->nullable();            // PDF / notes attachment
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('status')->default(1);          // 1=active 0=inactive
 
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
    }
};
