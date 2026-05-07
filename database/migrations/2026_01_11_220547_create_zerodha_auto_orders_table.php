<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('zerodha_auto_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');
            
            // Future Details
            $table->string('future_symbol');
            $table->string('future_token');
            
            // Signal Info
            $table->enum('signal_type', ['BUY', 'SELL']);
            $table->string('supertrend_signal');
            $table->string('donchian_signal');
            $table->timestamp('signal_detected_at');
            
            // Option Details (CE/PE)
            $table->string('option_symbol')->nullable();
            $table->string('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE'])->nullable();
            $table->decimal('strike_price', 10, 2)->nullable();
            $table->decimal('atm_price', 10, 2)->nullable();
            
            // Order Details
            $table->decimal('entry_price', 10, 2)->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            $table->enum('order_type', ['LIMIT', 'MARKET']);
            $table->enum('product', ['NRML', 'MIS']);
            $table->integer('quantity');
            
            // Pyramid Details
            $table->integer('pyramid_1')->nullable();
            $table->integer('pyramid_2')->nullable();
            $table->integer('pyramid_3')->nullable();
            
            // Status
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->boolean('status')->default(true);
            
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('zerodha_auto_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            
            $table->index(['config_id', 'future_symbol', 'signal_type']);
            $table->index(['is_order_placed', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zerodha_auto_orders');
    }
};
