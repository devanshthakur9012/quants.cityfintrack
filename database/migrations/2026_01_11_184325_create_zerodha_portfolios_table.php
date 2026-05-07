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
        Schema::create('zerodha_portfolios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id'); // Links to historical_orders
            $table->unsignedBigInteger('broker_api_id');
            
            // Symbol Info
            $table->string('symbol_name'); // DRREDDY, BHARTIARTL etc
            $table->string('trading_symbol'); // Full trading symbol from Zerodha
            $table->bigInteger('instrument_token');
            $table->integer('lot_size')->default(1);
            
            // Signal Info
            $table->enum('supertrend_signal', ['BUY', 'SELL', 'HOLD'])->nullable();
            $table->enum('donchian_signal', ['BUY', 'SELL', 'NO_TRADE'])->nullable();
            $table->enum('combined_signal', ['BUY', 'SELL', 'HOLD']); // When both match
            
            // Order Details
            $table->string('txn_type'); // BUY/SELL
            $table->string('order_type'); // LIMIT/MARKET
            $table->string('product'); // NRML/MIS
            $table->integer('quantity');
            $table->decimal('disc_ltp', 10, 2)->nullable();
            
            // Pyramid Info
            $table->integer('pyramid_1')->nullable();
            $table->integer('pyramid_2')->nullable();
            $table->integer('pyramid_3')->nullable();
            $table->integer('pyramid_percent')->nullable();
            $table->integer('pyramid_freq'); // Minutes
            
            // Pricing
            $table->decimal('entry_price', 10, 2)->nullable();
            $table->decimal('current_price', 10, 2)->nullable();
            
            // Status
            $table->boolean('is_order_placed')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamp('signal_detected_at')->nullable();
            $table->timestamp('order_placed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('historical_orders')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            
            $table->index(['config_id', 'symbol_name', 'is_order_placed']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zerodha_portfolios');
    }
};
