<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Chunk upload temp tracking ────────────────────────────────────
        Schema::create('course_video_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id')->index();          // unique upload session id
            $table->foreignId('course_lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('temp_path')->nullable();       // assembled temp file
            $table->string('final_path')->nullable();      // moved to secure folder
            $table->bigInteger('file_size')->default(0);   // bytes
            $table->integer('total_chunks')->default(1);
            $table->integer('uploaded_chunks')->default(0);
            $table->string('status')->default('pending');  // pending | processing | done | failed
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('course_video_uploads');
    }
};
