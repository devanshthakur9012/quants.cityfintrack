<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_symbols', function (Blueprint $table) {
            $table->id();

            // e.g. AUROPHARMA, NIFTY, SENSEX, BANKNIFTY
            $table->string('symbol', 30)->unique();

            // NSE, BSE, NFO, BFO
            $table->string('exchange', 10);

            // stock | index | futures | options
            // stock  → collect spot OHLC only
            // index  → collect spot OHLC only (NIFTY, SENSEX)
            // futures→ collect FUT OHLC+OI only
            // options→ collect CE/PE OHLC+OI for strikes around ATM
            // Typically you add one row per base symbol with collect_spot/fut/options flags:
            $table->boolean('collect_spot')->default(true);
            $table->boolean('collect_futures')->default(true);
            $table->boolean('collect_options')->default(true);

            // Spot instrument token (NSE equity token for the symbol)
            // NULL for pure-index if not needed
            $table->unsignedBigInteger('spot_instrument_token')->nullable();

            // How many strikes above/below ATM to collect for options
            // Default 5 → ATM-5 to ATM+5 = 11 strikes × CE + PE = 22 instruments
            $table->tinyInteger('strikes_depth')->default(5);

            // 1 = active (command processes this), 0 = paused
            $table->tinyInteger('status')->default(1)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_symbols');
    }
};