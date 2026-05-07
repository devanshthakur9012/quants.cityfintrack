<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_ohlc_spot', function (Blueprint $table) {
            $table->id();

            // e.g. AUROPHARMA, NIFTY, SENSEX
            $table->string('symbol', 30)->index();

            // NSE or BSE
            $table->string('exchange', 10);

            // Zerodha instrument token for this spot instrument
            $table->unsignedBigInteger('instrument_token');

            $table->date('trade_date')->index();

            // Full datetime of candle open: e.g. 2026-04-14 09:15:00
            $table->dateTime('candle_time')->index();

            $table->decimal('open',  12, 2);
            $table->decimal('high',  12, 2);
            $table->decimal('low',   12, 2);
            $table->decimal('close', 12, 2);
            $table->unsignedBigInteger('volume');

            // Composite unique: one candle per symbol per 15-min slot
            $table->unique(['symbol', 'candle_time'], 'uq_spot_symbol_candle');

            $table->timestamps();
        });

        // Fast lookups: symbol + date range queries
        DB::statement('CREATE INDEX idx_spot_sym_date ON raw_ohlc_spot (symbol, trade_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_ohlc_spot');
    }
};