<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  
    /**
     * timeframe_symbols
     *
     * A single, unified symbol table.  Each row ties a trading symbol to a
     * specific timeframe so the corresponding collector command knows which
     * symbols to process.
     *
     * Replaces the per-timeframe symbol tables (thirty_min_ohlc_symbols, etc.)
     * with one shared table, keyed by timeframe.
     */
    public function up(): void
    {
        Schema::create('timeframe_symbols', function (Blueprint $table) {
            $table->id();
 
            // e.g. "NIFTY", "BANKNIFTY", "SENSEX"
            $table->string('symbol', 50)->index();
 
            // e.g. "15min", "30min", "1hr"
            $table->string('timeframe', 10)->index();
 
            // NSE / NFO / BFO
            $table->string('exchange', 10)->default('NFO');
 
            $table->boolean('is_active')->default(true)->index();
 
            $table->text('notes')->nullable();
            $table->timestamps();
 
            // One entry per symbol+timeframe combination
            $table->unique(['symbol', 'timeframe'], 'uq_symbol_timeframe');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('timeframe_symbols');
    }
};
