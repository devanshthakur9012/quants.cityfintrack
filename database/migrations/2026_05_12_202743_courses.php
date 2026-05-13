<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        // Add certificate flag + discount_percent to courses table
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('has_certificate')->default(false)->after('is_featured');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_label');
        });
 
        // FAQs table — per course, optional
        Schema::create('course_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
 
        // Course trainers pivot — links courses to Users who have the 'employee' role
        Schema::create('course_trainer_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // ← User model
            $table->integer('sort_order')->default(0);
            $table->timestamps();
 
            $table->unique(['course_id', 'user_id']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('course_trainer_pivot');
        Schema::dropIfExists('course_faqs');
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['has_certificate', 'discount_percent']);
        });
    }
};
