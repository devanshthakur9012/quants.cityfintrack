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

            // Status: upcoming | live | past
            $table->enum('status', ['upcoming', 'live', 'past'])->default('upcoming');

            // Type: free | paid
            $table->enum('type', ['free', 'paid'])->default('free');

            // Filters
            $table->string('language')->default('Hindi');     // Hindi | English | Gujarati
            $table->string('level')->default('Beginner Level'); // Beginner Level | Intermediate Level | Advanced Level

            // Schedule
            $table->datetime('webinar_date')->nullable();
            $table->string('duration')->nullable();           // e.g. "2 hr", "60 min"

            // Pricing
            $table->unsignedInteger('price')->default(0);
            $table->unsignedInteger('mrp')->default(0);
            $table->string('discount_label')->nullable();     // e.g. "100% off"

            // Recording (for past webinars)
            // available | buy | null
            $table->enum('recording', ['available', 'buy'])->nullable();

            // Media
            $table->string('thumbnail')->nullable();
            $table->string('url')->nullable();

            // Sort & visibility
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webinars');
    }
};
