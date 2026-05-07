<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::create('option_iv_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broker_api_id');
            
            // Option Identification
            $table->string('symbol', 50); // NIFTY, BANKNIFTY
            $table->string('trading_symbol', 100); // NIFTY26JAN25100CE
            $table->date('expiry');
            $table->decimal('strike', 10, 2);
            $table->enum('option_type', ['CE', 'PE']);
            
            // Market Data
            $table->dateTime('timestamp');
            $table->decimal('ltp', 10, 2)->nullable();
            $table->decimal('iv', 10, 4)->nullable(); // IMPLIED VOLATILITY - KEY FIELD
            $table->bigInteger('oi')->default(0);
            $table->bigInteger('volume')->default(0);
            $table->decimal('bid', 10, 2)->nullable();
            $table->decimal('ask', 10, 2)->nullable();
            
            // Greeks (if available)
            $table->decimal('delta', 8, 6)->nullable();
            $table->decimal('gamma', 8, 6)->nullable();
            $table->decimal('theta', 8, 6)->nullable();
            $table->decimal('vega', 8, 6)->nullable();
            
            // ATM Classification
            $table->enum('atm_position', ['ATM-1', 'ATM', 'ATM+1', 'OTHER'])->default('OTHER');
            $table->decimal('future_price', 10, 2)->nullable(); // Reference future price at that time
            
            $table->timestamps();
            
            // Indexes for fast queries
            $table->index(['symbol', 'expiry', 'timestamp'], 'idx_symbol_expiry_time');
            $table->index(['strike', 'option_type'], 'idx_strike_type');
            $table->index('timestamp', 'idx_timestamp');
            $table->index('atm_position', 'idx_atm_position');
            $table->unique(['broker_api_id', 'trading_symbol', 'timestamp'], 'unique_option_timestamp');
            
            $table->foreign('broker_api_id')
                  ->references('id')
                  ->on('broker_apis')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('option_iv_data');
    }
};
