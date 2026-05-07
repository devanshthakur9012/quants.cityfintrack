<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        // ── Symbol config table ───────────────────────────────────────────────
        Schema::create('next_series_daily_ohlc_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 30)->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
 
        // ── OHLC data table ───────────────────────────────────────────────────
        Schema::create('next_series_daily_ohlc_data', function (Blueprint $table) {
            $table->id();
 
            $table->unsignedBigInteger('broker_api_id')->index();
            $table->date('trade_date')->index();
 
            $table->string('base_symbol', 30)->index();
            $table->string('future_symbol', 50);
            $table->decimal('future_price', 12, 2)->nullable();
            $table->decimal('atm_strike', 12, 2)->nullable();
 
            // FUT | CE | PE
            $table->string('instrument_type', 5)->index();
 
            $table->decimal('strike', 12, 2)->nullable();
            $table->string('trading_symbol', 60)->index();
            $table->unsignedBigInteger('instrument_token')->index();
 
            $table->decimal('open',  12, 2)->default(0);
            $table->decimal('high',  12, 2)->default(0);
            $table->decimal('low',   12, 2)->default(0);
            $table->decimal('close', 12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);
 
            $table->string('strike_position', 15)->nullable();
            $table->date('expiry_date')->nullable()->index();
 
            // 1 = candle was missing; zero-filled
            $table->tinyInteger('is_missing')->default(0);
 
            $table->timestamps();
 
            // ── Unique: one row per broker × date × trading_symbol ────────────
            $table->unique(
                ['broker_api_id', 'trade_date', 'trading_symbol'],
                'next_series_daily_uq'
            );
 
            // ── Composite indexes ─────────────────────────────────────────────
            $table->index(['base_symbol', 'trade_date'], 'next_series_daily_bsd_idx');
            $table->index(['base_symbol', 'trade_date', 'instrument_type'], 'next_series_daily_bsdt_idx');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('next_series_daily_ohlc_data');
        Schema::dropIfExists('next_series_daily_ohlc_symbols');
    }
};
