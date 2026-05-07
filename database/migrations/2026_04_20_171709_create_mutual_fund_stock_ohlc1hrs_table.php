<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutual_fund_stock_ohlc_1hr', function (Blueprint $table) {
            $table->id();
 
            $table->string('symbol');           // e.g. HDFCBANK
            $table->string('exchange');         // NSE / BSE
 
            $table->unsignedBigInteger('instrument_token')->nullable();
            $table->string('trading_symbol')->nullable();
 
            $table->date('trade_date');                     // just the date part (for easy filtering)
            $table->dateTime('candle_time');                // exact candle open time e.g. 2024-01-01 09:15:00
 
            $table->decimal('open',  10, 2)->default(0);
            $table->decimal('high',  10, 2)->default(0);
            $table->decimal('low',   10, 2)->default(0);
            $table->decimal('close', 10, 2)->default(0);
 
            $table->unsignedBigInteger('volume')->default(0);
 
            $table->boolean('is_missing')->default(false);
 
            $table->timestamps();
 
            // One row per symbol per candle timestamp
            $table->unique(['symbol', 'exchange', 'candle_time'], 'mf_1hr_symbol_candle_unique');
 
            $table->index('candle_time');
            $table->index('trade_date');
            $table->index(['symbol', 'candle_time']);
            $table->index(['symbol', 'trade_date']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('mutual_fund_stock_ohlc_1hr');
    }
};
