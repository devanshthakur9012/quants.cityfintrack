<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('option_ohlc_data_5min', function (Blueprint $table) {
 
            $table->id();
 
            // ── Broker / date / time ─────────────────────────────────────────
            $table->unsignedBigInteger('broker_api_id')->index();
            $table->date('trade_date')->index();
            $table->dateTime('interval_time')->index();    // e.g. 2026-03-19 09:15:00
 
            // ── Symbol identifiers ───────────────────────────────────────────
            $table->string('base_symbol', 20)->index();   // NIFTY, BANKNIFTY, etc.
            $table->string('trading_symbol', 40)->index();// NIFTY26MARFUT / NIFTY2631924500CE
            $table->string('future_symbol', 40)->nullable();
 
            // ── ATM reference ────────────────────────────────────────────────
            $table->decimal('future_price', 12, 2)->nullable();
            $table->decimal('atm_strike', 12, 2)->nullable();
 
            // ── Instrument details ───────────────────────────────────────────
            $table->string('instrument_type', 5)->index(); // FUT | CE | PE
            $table->decimal('strike', 12, 2)->nullable();
            $table->unsignedBigInteger('instrument_token')->index();
 
            // ── OHLCV + OI ───────────────────────────────────────────────────
            $table->decimal('open',   12, 2)->default(0);
            $table->decimal('high',   12, 2)->default(0);
            $table->decimal('low',    12, 2)->default(0);
            $table->decimal('close',  12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);
 
            // ── Strike position ──────────────────────────────────────────────
            // VARCHAR(10) — no ENUM, supports ATM+5 / ATM-5 / N/A freely
            $table->string('strike_position', 10)->default('N/A');
 
            // ── Expiry ───────────────────────────────────────────────────────
            $table->date('expiry_date')->nullable()->index();
 
            // ── Gap / missing flag ───────────────────────────────────────────
            $table->tinyInteger('is_missing')->default(0);
 
            // ── Timestamps ───────────────────────────────────────────────────
            $table->timestamps();
 
            // ── Unique constraint ────────────────────────────────────────────
            // Prevents duplicate rows for same broker + time slot + symbol
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                'uq_5min_ohlc_broker_time_symbol'
            );
 
            // ── Additional composite indexes for fast queries ────────────────
            $table->index(['base_symbol', 'trade_date', 'interval_time'], 'idx_5min_symbol_date_time');
            $table->index(['base_symbol', 'trade_date', 'instrument_type'], 'idx_5min_symbol_date_type');
            $table->index(['trade_date', 'base_symbol', 'strike_position'], 'idx_5min_date_symbol_pos');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('option_ohlc_data_5min');
    }
};
