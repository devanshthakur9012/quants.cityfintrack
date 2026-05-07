<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
       public function up(): void
    {
        Schema::create('mutual_fund_stock_ohlc', function (Blueprint $table) {
            $table->id();
 
            $table->string('symbol');    // e.g. HDFCBANK — one row per day regardless of how many funds hold it
            $table->string('exchange');  // NSE / BSE
 
            $table->unsignedBigInteger('instrument_token')->nullable();
            $table->string('trading_symbol')->nullable();
 
            $table->date('trade_date');
 
            $table->decimal('open',  10, 2)->default(0);
            $table->decimal('high',  10, 2)->default(0);
            $table->decimal('low',   10, 2)->default(0);
            $table->decimal('close', 10, 2)->default(0);
 
            $table->unsignedBigInteger('volume')->default(0);
 
            // OI intentionally excluded: Zerodha returns 0 for all equities.
            // Restore this column only when F&O instruments are added.
 
            $table->boolean('is_missing')->default(false);
 
            $table->timestamps();
 
            // Upsert key — one candle per symbol per exchange per day
            $table->unique(['symbol', 'exchange', 'trade_date'], 'mf_ohlc_symbol_date_unique');
 
            $table->index('trade_date');
            $table->index(['symbol', 'trade_date']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('mutual_fund_stock_ohlc');
    }
};
