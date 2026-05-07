<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_ohlc_options', function (Blueprint $table) {
            $table->id();

            // Base symbol, e.g. AUROPHARMA, NIFTY
            $table->string('symbol', 30)->index();

            // Full Zerodha trading symbol, e.g. AUROPHARMA26APR1400CE
            $table->string('trading_symbol', 60);

            // Zerodha instrument token
            $table->unsignedBigInteger('instrument_token');

            // Contract expiry date
            $table->date('expiry_date')->index();

            $table->date('trade_date')->index();

            // Full datetime of candle open: e.g. 2026-04-14 09:15:00
            $table->dateTime('candle_time')->index();

            // Strike price
            $table->decimal('strike', 12, 2)->index();

            // CE or PE
            $table->enum('option_type', ['CE', 'PE']);

            $table->decimal('open',  12, 2);
            $table->decimal('high',  12, 2);
            $table->decimal('low',   12, 2);
            $table->decimal('close', 12, 2);

            $table->unsignedBigInteger('volume');
            $table->unsignedBigInteger('open_interest');

            // OI Change = current candle OI - previous candle OI (same day).
            // For the 09:15 candle: OI - previous trading day's 15:15 OI.
            // Signed — negative means OI fell (unwinding).
            $table->bigInteger('oi_change')->default(0);

            // ATM strike frozen at 09:15 — determines WHICH strikes were collected today.
            // Used as the fixed reference for the entire day's strike chain.
            $table->decimal('atm_at_open', 12, 2)->nullable();

            // ATM strike recalculated from FUT close at THIS candle's time.
            // round(fut_close_at_candle / interval) * interval
            // Tells you the TRUE ATM at the time of this candle.
            // Use this for strike_distance calculation in analytics.
            $table->decimal('atm_at_candle', 12, 2)->nullable();

            // Strike distance relative to atm_at_candle (NOT atm_at_open).
            // = (strike - atm_at_candle) / interval
            // Signed decimal: +2 = 2 strikes above live ATM, -1 = 1 strike below.
            // CE ATM = 0, CE ATM+1 = +1, PE ATM-2 = -2, etc.
            $table->decimal('strike_distance', 8, 2)->default(0);

            // Composite unique: one candle per option contract per 15-min slot
            $table->unique(
                ['symbol', 'expiry_date', 'strike', 'option_type', 'candle_time'],
                'uq_opt_sym_expiry_strike_type_candle'
            );

            $table->timestamps();
        });

        // Fast ATM chain queries: give me all CE/PE for AUROPHARMA on date X
        DB::statement('CREATE INDEX idx_opt_sym_date_type ON raw_ohlc_options (symbol, trade_date, option_type)');
        // Fast strike lookup
        DB::statement('CREATE INDEX idx_opt_sym_expiry_strike ON raw_ohlc_options (symbol, expiry_date, strike)');
        // Fast strike_distance queries: "give me all ATM options across time"
        DB::statement('CREATE INDEX idx_opt_sym_date_dist ON raw_ohlc_options (symbol, trade_date, strike_distance)');
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_ohlc_options');
    }
};