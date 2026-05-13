<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up(): void
    {
        // ── course_sections ──────────────────────────────────────────────────
        Schema::table('course_sections', function (Blueprint $table) {
            $table->string('preview_video_type')->default('none')->after('description');  // none | youtube | upload
            $table->string('preview_video_url', 500)->nullable()->after('preview_video_type');
            $table->string('preview_video_path', 500)->nullable()->after('preview_video_url');
        });
 
        // ── course_lessons ───────────────────────────────────────────────────
        Schema::table('course_lessons', function (Blueprint $table) {
            // Remove is_free_preview & is_downloadable (replaced by per-section free access)
            // We keep them nullable so old data isn't lost — just stop using them in UI
            // If you want hard removal, uncomment:
            // $table->dropColumn(['is_free_preview', 'is_downloadable']);
 
            // Add lesson preview/overview video (separate from the main lesson video)
            $table->string('preview_video_type')->default('none')->after('description');  // none | youtube | upload
            $table->string('preview_video_url', 500)->nullable()->after('preview_video_type');
            $table->string('preview_video_path', 500)->nullable()->after('preview_video_url');
        });
    }
 
    public function down(): void
    {
        Schema::table('course_sections', function (Blueprint $table) {
            $table->dropColumn(['preview_video_type', 'preview_video_url', 'preview_video_path']);
        });
 
        Schema::table('course_lessons', function (Blueprint $table) {
            $table->dropColumn(['preview_video_type', 'preview_video_url', 'preview_video_path']);
        });
    }
};
