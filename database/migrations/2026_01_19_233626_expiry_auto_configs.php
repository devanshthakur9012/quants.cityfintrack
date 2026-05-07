<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('expiry_auto_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('broker_api_id')->constrained('broker_apis')->onDelete('cascade');
            
            // Order Configuration
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('MIS');
            $table->decimal('disc_ltp', 5, 2)->default(0.5); // Discount for LIMIT orders
            
            // Quantity Configuration
            $table->integer('nifty_quantity')->default(1); // Lots for NIFTY
            $table->integer('banknifty_quantity')->default(1); // Lots for BANKNIFTY
            $table->integer('sensex_quantity')->default(1); // Lots for SENSEX
            
            // Pyramid Configuration
            $table->integer('pyramid_percent')->default(100); // 33, 50, or 100
            $table->integer('pyramid_freq')->default(0); // Minutes between pyramid levels
            
            // Status
            $table->boolean('status')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('expiry_auto_configs');
    }
};
