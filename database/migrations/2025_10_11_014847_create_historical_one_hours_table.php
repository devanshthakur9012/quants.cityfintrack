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
        // Schema::create('historical_one_hours', function (Blueprint $table) {
        //     $table->id();
        //     $table->string('underlying');
        //     $table->date('date');
            
        //     // Future Data
        //     $table->string('future_symbol');
        //     $table->string('future_token');
        //     $table->decimal('future_open', 10, 2)->nullable();
        //     $table->decimal('future_high', 10, 2)->nullable();
        //     $table->decimal('future_low', 10, 2)->nullable();
        //     $table->decimal('future_close', 10, 2)->nullable();
        //     $table->bigInteger('future_volume')->nullable();
        //     $table->bigInteger('future_oi')->nullable();
        //     $table->decimal('future_price_change', 10, 2)->nullable();
        //     $table->decimal('future_oi_change', 10, 2)->nullable();
        //     $table->decimal('future_oi_chg_pct', 10, 2)->nullable();
            
        //     // Call Option Data
        //     $table->string('ce_symbol');
        //     $table->string('ce_token');
        //     $table->decimal('ce_open', 10, 2)->nullable();
        //     $table->decimal('ce_high', 10, 2)->nullable();
        //     $table->decimal('ce_low', 10, 2)->nullable();
        //     $table->decimal('ce_close', 10, 2)->nullable();
        //     $table->bigInteger('ce_volume')->nullable();
        //     $table->bigInteger('ce_oi')->nullable();
        //     $table->decimal('ce_price_change', 10, 2)->nullable();
        //     $table->decimal('ce_oi_change', 10, 2)->nullable();
        //     $table->decimal('ce_oi_chg_pct', 10, 2)->nullable();
            
        //     // Put Option Data
        //     $table->string('pe_symbol');
        //     $table->string('pe_token');
        //     $table->decimal('pe_open', 10, 2)->nullable();
        //     $table->decimal('pe_high', 10, 2)->nullable();
        //     $table->decimal('pe_low', 10, 2)->nullable();
        //     $table->decimal('pe_close', 10, 2)->nullable();
        //     $table->bigInteger('pe_volume')->nullable();
        //     $table->bigInteger('pe_oi')->nullable();
        //     $table->decimal('pe_price_change', 10, 2)->nullable();
        //     $table->decimal('pe_oi_change', 10, 2)->nullable();
        //     $table->decimal('pe_oi_chg_pct', 10, 2)->nullable();
            
        //     // Put Option Data
        //     $table->string('trend');
        //     $table->string('futures_score');
        //     $table->string('options_score');
        //     $table->string('final_score');
            
        //     // Indexes for better performance
        //     $table->index(['underlying', 'date']);
        //     $table->index('future_token');
        //     $table->index('ce_token');
        //     $table->index('pe_token');
        //     $table->timestamps();
        // });

        Schema::create('historical_one_hours', function (Blueprint $table) {
            $table->id();
            $table->string('underlying', 50)->index();
            $table->date('date')->index();
            $table->string('symbol', 100)->index();
            $table->string('token', 50);
            $table->enum('type', ['future', 'ce', 'pe'])->index();
            
            // OHLCV Data
            $table->decimal('open', 12, 2)->nullable();
            $table->decimal('high', 12, 2)->nullable();
            $table->decimal('low', 12, 2)->nullable();
            $table->decimal('close', 12, 2)->nullable();
            $table->decimal('volume', 18, 2)->nullable();
            $table->decimal('oi', 18, 2)->nullable();
            
            // Change Metrics
            $table->decimal('oi_change', 12, 2)->nullable();
            $table->decimal('oi_chg_pct', 8, 2)->nullable();
            $table->decimal('price_change', 10, 2)->nullable();

            $table->decimal('atr', 12, 4)->nullable();
            $table->decimal('supertrend', 12, 2)->nullable();
            $table->enum('supertrend_direction', ['UP', 'DOWN'])->nullable();
            $table->enum('supertrend_signal', ['BUY', 'SELL', 'HOLD'])->nullable();
            $table->decimal('upper_band', 12, 2)->nullable();
            $table->decimal('lower_band', 12, 2)->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Unique constraint on date + symbol
            $table->unique(['date', 'symbol']);
            
            // Composite indexes for common queries
            $table->index(['underlying', 'date']);
            $table->index(['symbol', 'type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('historical_one_hours');
    }
};
