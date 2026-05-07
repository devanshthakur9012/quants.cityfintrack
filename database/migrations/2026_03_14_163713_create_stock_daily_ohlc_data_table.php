<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('stock_daily_ohlc_data', function (Blueprint $table) {
            $table->id();
 
            // ── Broker ────────────────────────────────────────────────────────
            $table->unsignedBigInteger('broker_api_id')->comment('FK → broker_apis.id');
 
            // ── Symbol / Instrument ───────────────────────────────────────────
            $table->string('symbol', 50)->comment('NSE stock symbol e.g. RELIANCE');
            $table->string('exchange', 10)->default('NSE')->comment('NSE | BSE');
            $table->string('trading_symbol', 50)->comment('Full trading symbol from Zerodha');
            $table->unsignedBigInteger('instrument_token')->comment('Zerodha instrument token');
 
            // ── Date ──────────────────────────────────────────────────────────
            $table->date('trade_date')->comment('The trading date for this candle');
 
            // ── OHLCV ─────────────────────────────────────────────────────────
            $table->decimal('open',   12, 2)->default(0);
            $table->decimal('high',   12, 2)->default(0);
            $table->decimal('low',    12, 2)->default(0);
            $table->decimal('close',  12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
 
            // ── Quality flags ─────────────────────────────────────────────────
            $table->tinyInteger('is_missing')->default(0)->comment('1 = API returned no data for this day');
 
            $table->timestamps();
 
            // ── Unique constraint (one row per broker × symbol × date) ────────
            $table->unique(['broker_api_id', 'symbol', 'trade_date'], 'uq_stock_daily_ohlc');
 
            // ── Indexes ───────────────────────────────────────────────────────
            $table->index(['symbol', 'trade_date']);
            $table->index(['trade_date']);
            $table->index(['instrument_token', 'trade_date']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('stock_daily_ohlc_data');
    }
};
