<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('cp_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Razorpay');
            $table->string('alias')->default('razorpay');
            $table->json('credentials')->nullable()
                  ->comment('{"key_id":"rzp_live_...","key_secret":"..."}');
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('cp_payment_gateways');
    }
};
