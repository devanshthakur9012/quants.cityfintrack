<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_category_id')->constrained()->cascadeOnDelete();
 
            // ── Core Info ──────────────────────────────────────────────────
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
 
            // ── Media ──────────────────────────────────────────────────────
            $table->string('thumbnail')->nullable();             // stored path
            $table->string('preview_video_type')->default('youtube'); // youtube | upload
            $table->string('preview_video_url')->nullable();    // youtube URL or stored path
 
            // ── Classification ─────────────────────────────────────────────
            $table->string('mode')->default('online');           // online | offline | hybrid
            $table->string('level')->default('beginner');        // beginner | intermediate | advanced
            $table->string('language')->default('hindi');        // hindi | english | gujarati
            $table->string('status')->default('upcoming');       // upcoming | ongoing | recorded | draft
 
            // ── Batch / Schedule ───────────────────────────────────────────
            $table->string('batch_name')->nullable();            // e.g. "Batch 7"
            $table->integer('total_sessions')->default(0);
            $table->decimal('session_duration_hrs', 5, 1)->default(0);  // per session in hrs
            $table->string('schedule_days')->nullable();         // e.g. "Mon, Wed"
            $table->string('duration_label')->nullable();        // e.g. "3 Months"
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('class_time')->nullable();            // e.g. "19:00-21:00"
 
            // ── Pricing ────────────────────────────────────────────────────
            $table->string('type')->default('paid');             // free | paid
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('mrp', 10, 2)->nullable();
            $table->string('discount_label')->nullable();        // e.g. "40% off"
 
            // ── Trainer / Meta ─────────────────────────────────────────────
            $table->string('trainer_name')->nullable();
            $table->string('trainer_designation')->nullable();
            $table->string('trainer_avatar')->nullable();
            $table->integer('total_enrolled')->default(0);
            $table->decimal('rating', 3, 1)->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
 
            // ── SEO ────────────────────────────────────────────────────────
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
 
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
