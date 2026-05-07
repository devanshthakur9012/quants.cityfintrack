<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('expiry_data', function (Blueprint $table) {
            $table->id();
            $table->string('symbol'); // NIFTY, BANKNIFTY, SENSEX
            $table->string('exchange')->default('NSE');
            $table->string('instrument_token');
            $table->timestamp('timestamp'); // 1-minute candle timestamp
            
            // OHLC Data
            $table->decimal('open', 10, 2);
            $table->decimal('high', 10, 2);
            $table->decimal('low', 10, 2);
            $table->decimal('close', 10, 2);
            $table->bigInteger('volume');
            
            // Supertrend ONLY (no other indicators)
            $table->decimal('atr', 10, 4)->nullable();
            $table->decimal('supertrend', 10, 2)->nullable();
            $table->enum('supertrend_direction', ['UP', 'DOWN'])->nullable();
            $table->enum('supertrend_signal', ['BUY', 'SELL', 'HOLD'])->default('HOLD');
            $table->decimal('upper_band', 10, 2)->nullable();
            $table->decimal('lower_band', 10, 2)->nullable();
            
            $table->timestamps();
            
            $table->unique(['symbol', 'timestamp']);
            $table->index(['symbol', 'timestamp']);
            $table->index(['symbol', 'supertrend_signal']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('expiry_data');
    }
};
