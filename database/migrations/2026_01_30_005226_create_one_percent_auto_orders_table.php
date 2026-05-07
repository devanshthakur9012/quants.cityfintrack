<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('one_percent_auto_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');
            
            // Future Symbol Info
            $table->string('symbol')->comment('Base symbol e.g., NIFTY');
            $table->string('trading_symbol')->comment('Future symbol e.g., NIFTY26JANFUT');
            $table->string('instrument_token')->nullable();
            
            // Signal Info
            $table->enum('signal_type', ['BUY_CE', 'BUY_PE'])->comment('CE or PE signal');
            $table->decimal('move_threshold', 5, 2)->comment('% threshold that triggered');
            $table->timestamp('signal_detected_at');
            $table->decimal('day_open_price', 10, 2)->comment('Day opening price');
            $table->decimal('signal_price', 10, 2)->comment('Price when signal triggered');
            $table->decimal('change_pct', 10, 2)->comment('% change from open');
            
            // OI Signals (from previous day)
            $table->string('fut_signal')->default('NEUTRAL')->comment('FUT OI signal');
            $table->string('fut_strength')->nullable()->comment('FUT signal strength');
            $table->string('ce_signal')->default('NEUTRAL')->comment('CE OI signal');
            $table->string('pe_signal')->default('NEUTRAL')->comment('PE OI signal');
            $table->string('market_bias')->nullable()->comment('Overall market bias');
            
            // Option Details
            $table->string('option_symbol');
            $table->string('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike_price', 10, 2);
            $table->decimal('entry_price', 10, 2)->nullable()->comment('Option LTP');
            $table->decimal('current_price', 10, 2)->nullable();
            
            // Order Config
            $table->enum('order_type', ['LIMIT', 'MARKET']);
            $table->enum('product', ['NRML', 'MIS']);
            $table->integer('quantity')->comment('Total lots');
            $table->integer('pyramid_1')->nullable();
            $table->integer('pyramid_2')->nullable();
            $table->integer('pyramid_3')->nullable();
            
            // Order Status
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->boolean('status')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('config_id');
            $table->index('broker_api_id');
            $table->index('trading_symbol');
            $table->index('signal_detected_at');
            $table->index(['is_order_placed', 'status']);
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('one_percent_auto_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('one_percent_auto_orders');
    }
};
