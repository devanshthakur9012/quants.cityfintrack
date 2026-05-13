<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        // ── Payment Gateway Settings ──────────────────────────────────────────
        Schema::create('course_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // e.g. Razorpay
            $table->string('alias')->unique();               // razorpay
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->json('credentials')->nullable();         // key_id, key_secret etc (encrypted)
            $table->tinyInteger('status')->default(0);       // 0=inactive 1=active
            $table->tinyInteger('test_mode')->default(1);    // 1=test 0=live
            $table->timestamps();
        });
 
        // ── Course Orders ─────────────────────────────────────────────────────
        Schema::create('course_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();        // CQ-2024-0001
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('razorpay');  // which gateway used
            $table->decimal('amount', 10, 2);                // final amount paid
            $table->decimal('original_price', 10, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('status')->default('pending');    // pending|paid|failed|refunded
            // gateway response fields
            $table->string('gateway_order_id')->nullable();  // Razorpay order id
            $table->string('gateway_payment_id')->nullable();// Razorpay payment id
            $table->string('gateway_signature')->nullable(); // Razorpay signature
            $table->text('gateway_response')->nullable();    // raw JSON response
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
 
        // ── Course Enrollments ────────────────────────────────────────────────
        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('access_type')->default('paid');  // paid | free | manual
            $table->timestamp('enrolled_at');
            $table->timestamp('expires_at')->nullable();     // null = lifetime
            $table->tinyInteger('status')->default(1);       // 1=active 0=revoked
            $table->timestamps();
 
            $table->unique(['user_id', 'course_id']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_orders');
        Schema::dropIfExists('course_payment_gateways');
    }
};
