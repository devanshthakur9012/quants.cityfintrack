<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
         Schema::create('expiry_auto_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');
            
            // Index/Future details
            $table->string('symbol')->comment('NIFTY, BANKNIFTY, SENSEX');
            $table->string('instrument_token');
            
            // Signal details
            $table->enum('signal_type', ['BUY', 'SELL']);
            $table->string('supertrend_signal')->nullable();
            $table->timestamp('signal_detected_at');
            
            // Option details
            $table->string('option_symbol');
            $table->string('option_token');
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike_price', 10, 2);
            $table->decimal('index_price', 10, 2)->comment('Index price at signal');
            $table->decimal('entry_price', 10, 2)->comment('Option LTP at signal');
            $table->decimal('current_price', 10, 2)->nullable();
            
            // Order settings
            $table->enum('order_type', ['LIMIT', 'MARKET']);
            $table->enum('product', ['NRML', 'MIS']);
            $table->integer('quantity')->comment('Total lots');
            $table->integer('pyramid_1')->nullable()->comment('First pyramid lots');
            $table->integer('pyramid_2')->nullable()->comment('Second pyramid lots');
            $table->integer('pyramid_3')->nullable()->comment('Third pyramid lots');
            
            // Order status
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->boolean('status')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('expiry_auto_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            
            $table->index(['user_id', 'status']);
            $table->index(['symbol', 'signal_detected_at']);
            $table->index('is_order_placed');
        });
    }

    public function down()
    {
        Schema::dropIfExists('expiry_auto_orders');
    }
};
