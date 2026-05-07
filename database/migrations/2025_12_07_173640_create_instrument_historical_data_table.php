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
        Schema::create('instrument_historical_data', function (Blueprint $table) {
            $table->id();
            
            // Common fields
            $table->string('underlying', 50)->index();
            $table->string('symbol', 100);
            $table->string('token', 50)->index();
            $table->enum('type', ['FUT', 'CE', 'PE'])->index();
            $table->date('data_date')->index();
            
            // OHLCV data
            $table->decimal('open', 12, 2)->nullable();
            $table->decimal('high', 12, 2)->nullable();
            $table->decimal('low', 12, 2)->nullable();
            $table->decimal('close', 12, 2)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('oi')->nullable();
            
            // Change calculations
            $table->decimal('price_change', 12, 2)->nullable()->comment('Close price change from previous day');
            $table->decimal('oi_change', 20, 0)->nullable()->comment('OI change from previous day');
            $table->decimal('oi_change_pct', 8, 2)->nullable()->comment('OI change percentage');
            
            // Strike info (only for options)
            $table->decimal('strike_price', 12, 2)->nullable()->index();
            $table->integer('strike_position')->nullable();
            
            // Trend analysis (only for ATM options)
            $table->string('trend', 50)->nullable();
            $table->integer('futures_score')->nullable();
            $table->integer('options_score')->nullable();
            $table->integer('final_score')->nullable();
            
            $table->timestamps();
            
            // Composite indexes for performance
            $table->index(['underlying', 'type', 'data_date'], 'idx_ud_type_date');
            $table->index(['underlying', 'strike_price', 'data_date'], 'idx_ud_strike_date');
            $table->index(['token', 'data_date'], 'idx_token_date');
            $table->unique(['token', 'type', 'data_date'], 'uniq_token_type_date');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('instrument_historical_data');
    }
};
