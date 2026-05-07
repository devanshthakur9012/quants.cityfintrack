<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('option_ohlc_data', function (Blueprint $table) {
            $table->id();
            
            // Identifiers
            $table->unsignedBigInteger('broker_api_id');
            $table->date('trade_date');
            $table->datetime('interval_time'); // 15-min interval timestamp
            
            // Base Symbol Info
            $table->string('base_symbol', 50); // BHEL, NIFTY, etc.
            $table->string('future_symbol', 100); // BHEL25FEBFUT
            $table->decimal('future_price', 12, 2); // ATM reference
            $table->decimal('atm_strike', 12, 2);
            
            // Instrument Type & Strike
            $table->enum('instrument_type', ['FUT', 'CE', 'PE']);
            $table->decimal('strike', 12, 2)->nullable(); // NULL for FUT
            $table->string('trading_symbol', 100); // Full symbol
            $table->string('instrument_token', 50)->nullable();
            
            // OHLC Data
            $table->decimal('open', 12, 2);
            $table->decimal('high', 12, 2);
            $table->decimal('low', 12, 2);
            $table->decimal('close', 12, 2);
            $table->bigInteger('volume')->default(0);
            $table->bigInteger('oi')->default(0);
            
            // Option Specific
            $table->decimal('fair_price', 12, 2)->nullable(); // Only for CE/PE
            $table->decimal('iv', 8, 4)->nullable(); // Implied Volatility
            $table->enum('valuation', ['UNDERPRICED', 'FAIR', 'OVERPRICED', 'N/A'])->default('N/A');
            $table->string('recommendation', 50)->nullable(); // BUY/SELL/WAIT
            
            // Strike Position (for options)
            $table->enum('strike_position', ['ATM', 'ATM+1', 'ATM-1', 'N/A'])->default('N/A');
            
            // Metadata
            $table->date('expiry_date')->nullable(); // For options
            $table->integer('days_to_expiry')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['broker_api_id', 'trade_date', 'interval_time']);
            $table->index(['base_symbol', 'trade_date', 'interval_time']);
            $table->index(['trading_symbol', 'trade_date', 'interval_time']);
            $table->index('instrument_type');
            
            // Unique constraint
            $table->unique([
                'broker_api_id',
                'trade_date',
                'interval_time',
                'trading_symbol'
            ], 'unique_ohlc_entry');
            
            // Foreign key
            $table->foreign('broker_api_id')
                  ->references('id')
                  ->on('broker_apis')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_ohlc_data');
    }
};