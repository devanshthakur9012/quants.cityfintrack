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
       Schema::create('futures_trading_signals', function (Blueprint $table) {
            $table->id();
            
            // Instrument identifiers
            $table->string('underlying', 50)->index();
            $table->string('symbol', 100)->index();
            $table->string('token', 50)->index();
            
            // Time identifiers
            $table->date('data_date')->index();
            $table->dateTime('candle_time')->index();
            $table->integer('candle_index');
            
            // Raw OHLCV data
            $table->decimal('open', 10, 2)->nullable();
            $table->decimal('high', 10, 2)->nullable();
            $table->decimal('low', 10, 2)->nullable();
            $table->decimal('close', 10, 2)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('oi')->nullable();
            
            // Heikin Ashi values
            $table->decimal('ha_open', 10, 2)->nullable();
            $table->decimal('ha_close', 10, 2)->nullable();
            $table->decimal('ha_high', 10, 2)->nullable();
            $table->decimal('ha_low', 10, 2)->nullable();
            $table->enum('ha_color', ['GREEN', 'RED'])->nullable();
            $table->decimal('ha_strength', 6, 4)->nullable();
            
            // Futures structure
            $table->enum('structure_type', [
                'LONG_BUILDUP',
                'SHORT_BUILDUP',
                'SHORT_COVERING',
                'LONG_UNWINDING',
                'NEUTRAL'
            ])->nullable();
            $table->decimal('structure_vol_change', 8, 4)->nullable();
            
            // Signals
            $table->enum('raw_signal', ['BUY', 'SELL', 'NO TRADE'])->nullable()->index();
            $table->enum('oi_signal', ['BULLISH', 'BEARISH', 'NO SIGNAL'])->nullable();
            $table->enum('final_signal', ['BUY', 'SELL', 'NO TRADE'])->nullable()->index();
            
            $table->timestamps();
            
            // Composite indexes for performance
            $table->index(['underlying', 'data_date']);
            $table->index(['token', 'data_date']);
            $table->index(['data_date', 'final_signal']);
            
            // Unique constraint
            $table->unique(['token', 'candle_time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('futures_trading_signals');
    }
};
