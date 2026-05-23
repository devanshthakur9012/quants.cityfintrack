<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webinars', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('status', ['upcoming', 'live', 'past'])->default('upcoming');
            $table->enum('type', ['free', 'paid'])->default('free');
            $table->enum('mode', ['online', 'offline', 'hybrid'])->default('online');
            $table->string('address')->nullable();
            $table->string('language')->default('Hindi');
            $table->string('level')->default('Beginner Level');
            $table->datetime('webinar_date')->nullable();
            $table->string('duration')->nullable();
            $table->unsignedInteger('total_seats')->nullable();
            $table->unsignedInteger('total_enrolled')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('mrp')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('discount_label')->nullable();
            $table->string('youtube_url')->nullable();
            $table->string('thumbnail')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
 
        Schema::create('webinar_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
 
        Schema::create('webinar_speaker_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['webinar_id', 'user_id']);
        });
 
        Schema::create('webinar_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
 
        // ── webinar_orders MUST come before webinar_enrollments ──────────
        Schema::create('webinar_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webinar_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('razorpay');
            $table->unsignedInteger('amount');
            $table->string('currency')->default('INR');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_signature')->nullable();
            $table->text('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
 
        // ── webinar_enrollments references webinar_orders (must be after) ─
        Schema::create('webinar_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webinar_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('webinar_order_id')->nullable();
            $table->foreign('webinar_order_id')->references('id')->on('webinar_orders')->nullOnDelete();
            $table->enum('access_type', ['free', 'paid', 'manual'])->default('free');
            $table->timestamp('enrolled_at')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
            $table->unique(['webinar_id', 'user_id']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('webinar_enrollments');
        Schema::dropIfExists('webinar_orders');
        Schema::dropIfExists('webinar_tools');
        Schema::dropIfExists('webinar_speaker_pivot');
        Schema::dropIfExists('webinar_faqs');
        Schema::dropIfExists('webinars');
    }
};
