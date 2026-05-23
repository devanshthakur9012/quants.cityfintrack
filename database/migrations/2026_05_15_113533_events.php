<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->enum('badge', ['symposium','workshop','seminar','bootcamp','conference','other'])->default('seminar');

            // Date & Time
            $table->date('event_date')->nullable();
            $table->time('event_time_start')->nullable();
            $table->time('event_time_end')->nullable();
            $table->unsignedSmallInteger('duration_hours')->nullable();

            // Location
            $table->string('location')->nullable();
            $table->string('city')->nullable();

            // Pricing
            $table->enum('type', ['free','paid'])->default('paid');
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('mrp')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->string('discount_label')->nullable();

            // Seats & Booking
            $table->unsignedInteger('total_seats')->nullable();
            $table->unsignedInteger('total_booked')->default(0);
            $table->boolean('booking_open')->default(true);

            // Video
            $table->enum('video_type', ['youtube','upload'])->nullable();
            $table->string('video_url')->nullable();

            // Content
            $table->text('description')->nullable();
            $table->string('thumbnail')->nullable();
            $table->json('tags')->nullable();

            // Gallery section title (dynamic — admin sets it e.g. "Event Gallery", "Tools Covered")
            $table->string('gallery_section_title')->nullable()->default('Event Gallery');

            // Status
            $table->enum('status', ['upcoming','ongoing','past','draft'])->default('upcoming');
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        // Speakers pivot
        Schema::create('event_speaker_pivot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unique(['event_id','user_id']);
            $table->timestamps();
        });

        // Gallery items (image + caption per item)
        Schema::create('event_gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('image');
            $table->string('title')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // FAQs
        Schema::create('event_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('question');
            $table->text('answer');
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Bookings
        Schema::create('event_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->text('message')->nullable();
            $table->enum('payment_type', ['free','paid'])->default('free');
            $table->unsignedInteger('amount')->default(0);
            $table->enum('payment_status', ['pending','paid','failed','free'])->default('free');
            $table->string('order_number')->unique()->nullable();
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_signature')->nullable();
            $table->text('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->enum('status', ['confirmed','cancelled','waitlisted'])->default('confirmed');
            $table->timestamps();
            $table->unique(['event_id','email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_bookings');
        Schema::dropIfExists('event_faqs');
        Schema::dropIfExists('event_gallery_items');
        Schema::dropIfExists('event_speaker_pivot');
        Schema::dropIfExists('events');
    }
};
