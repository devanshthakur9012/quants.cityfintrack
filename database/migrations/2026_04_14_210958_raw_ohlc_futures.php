<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_ohlc_futures', function (Blueprint $table) {
            $table->id();

            // Base symbol, e.g. AUROPHARMA, NIFTY, BANKNIFTY
            $table->string('symbol', 30)->index();

            // Full Zerodha trading symbol, e.g. AUROPHARMA26APRFUT
            $table->string('trading_symbol', 50);

            // Zerodha instrument token
            $table->unsignedBigInteger('instrument_token');

            // Contract expiry date
            $table->date('expiry_date')->index();

            $table->date('trade_date')->index();

            // Full datetime of candle open: e.g. 2026-04-14 09:15:00
            $table->dateTime('candle_time')->index();

            $table->decimal('open',  12, 2);
            $table->decimal('high',  12, 2);
            $table->decimal('low',   12, 2);
            $table->decimal('close', 12, 2);

            $table->unsignedBigInteger('volume');
            $table->unsignedBigInteger('open_interest');

            // OI Change = current candle OI - previous candle OI (same day).
            // For the 09:15 candle: OI - previous trading day's 15:15 OI.
            // Can be negative (long unwinding / short covering) → signed bigint.
            $table->bigInteger('oi_change')->default(0);

            // Composite unique: one FUT candle per symbol per expiry per 15-min slot
            $table->unique(['symbol', 'expiry_date', 'candle_time'], 'uq_fut_sym_expiry_candle');

            $table->timestamps();
        });

        DB::statement('CREATE INDEX idx_fut_sym_date ON raw_ohlc_futures (symbol, trade_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_ohlc_futures');
    }
};