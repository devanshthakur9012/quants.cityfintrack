<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oiiv_auto_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');
            
            // Future Details
            $table->string('symbol', 50);
            $table->string('trading_symbol', 100);
            $table->bigInteger('instrument_token')->nullable();
            
            // Signal Details (from OptionStrike table)
            $table->enum('btst_signal', ['BUY_CE', 'BUY_PE'])->comment('Final BTST recommendation');
            $table->integer('btst_confidence')->comment('Confidence score 0-100');
            $table->string('btst_reason', 500)->nullable();
            $table->timestamp('signal_detected_at');
            
            // OI Signals
            $table->string('fut_oi_signal', 20)->nullable()->comment('FUT OI direction');
            $table->string('fut_oi_strength', 20)->nullable();
            $table->string('ce_oi_signal', 20)->nullable();
            $table->string('pe_oi_signal', 20)->nullable();
            
            // IV Signals  
            $table->string('ce_iv_signal', 20)->nullable();
            $table->string('ce_iv_strength', 20)->nullable();
            $table->string('pe_iv_signal', 20)->nullable();
            $table->string('pe_iv_strength', 20)->nullable();
            
            // Price Info
            $table->decimal('spot_price', 10, 2);
            
            // Option Details
            $table->string('option_symbol', 100);
            $table->bigInteger('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike_price', 10, 2);
            $table->decimal('entry_price', 10, 2)->nullable()->comment('Option LTP at signal time');
            $table->decimal('current_price', 10, 2)->nullable();
            
            // Order Details
            $table->enum('order_type', ['LIMIT', 'MARKET']);
            $table->enum('product', ['NRML', 'MIS']);
            $table->integer('quantity')->comment('Number of lots');
            
            // Order Status
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->boolean('status')->default(true);
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('oiiv_auto_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            
            // Indexes
            $table->index('user_id');
            $table->index('config_id');
            $table->index('broker_api_id');
            $table->index('btst_signal');
            $table->index('is_order_placed');
            $table->index('status');
            $table->index('signal_detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oiiv_auto_orders');
    }
};
