<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::create('symbol_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broker_api_id')->constrained('broker_apis')->onDelete('cascade');
            $table->string('trading_symbol', 100);
            $table->string('symbol', 50); // Base symbol (RELIANCE, TCS, etc)
            $table->string('exchange', 10)->default('NFO');
            $table->bigInteger('instrument_token');
            $table->string('interval', 20); // minute, 5minute, 15minute, etc.
            $table->timestamp('timestamp');
            
            // OHLCV Data
            $table->decimal('open', 12, 2);
            $table->decimal('high', 12, 2);
            $table->decimal('low', 12, 2);
            $table->decimal('close', 12, 2);
            $table->bigInteger('volume')->default(0);
            $table->bigInteger('oi')->default(0); // Open Interest
            
            // Supertrend Indicators
            $table->decimal('atr', 12, 4)->nullable();
            $table->decimal('supertrend', 12, 2)->nullable();
            $table->string('supertrend_direction', 10)->nullable(); // UP, DOWN
            $table->string('supertrend_signal', 10)->nullable(); // BUY, SELL, HOLD
            $table->decimal('upper_band', 12, 2)->nullable();
            $table->decimal('lower_band', 12, 2)->nullable();
            
            // Donchian Channel
            $table->string('donchian_signal', 20)->nullable();
            $table->decimal('donchian_upper', 12, 2)->nullable();
            $table->decimal('donchian_lower', 12, 2)->nullable();
            $table->decimal('donchian_middle', 12, 2)->nullable();
            $table->decimal('donchian_entry', 12, 2)->nullable();
            $table->decimal('donchian_sl', 12, 2)->nullable();
            $table->decimal('donchian_target', 12, 2)->nullable();
            
            // RSI Indicator
            $table->decimal('rsi', 12, 2)->nullable();
            $table->string('rsi_signal', 10)->nullable(); // BUY, SELL, NEUTRAL
            
            // MACD Indicator
            $table->decimal('macd_line', 12, 4)->nullable();
            $table->decimal('macd_signal_line', 12, 4)->nullable();
            $table->decimal('macd_histogram', 12, 4)->nullable();
            $table->string('macd_signal', 10)->nullable(); // BUY, SELL, HOLD
            
            // VWAP Indicator
            $table->decimal('vwap', 12, 2)->nullable();
            $table->string('vwap_signal', 20)->nullable(); // BUY, SELL, HOLD, GAP_UP, GAP_DOWN
            $table->decimal('vwap_upper_band', 12, 2)->nullable();
            $table->decimal('vwap_lower_band', 12, 2)->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['broker_api_id', 'symbol', 'interval', 'timestamp']);
            $table->index(['trading_symbol', 'interval', 'timestamp']);
            $table->index('timestamp');
            
            // Unique constraint
            $table->unique(['broker_api_id', 'trading_symbol', 'interval', 'timestamp'], 'unique_symbol_data');
        });
    }

    public function down()
    {
        Schema::dropIfExists('symbol_data');
    }
};
