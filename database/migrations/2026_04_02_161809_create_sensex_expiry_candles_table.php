<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sensex_expiry_candles', function (Blueprint $table) {
            $table->id();

            // ── Identity ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('broker_api_id');
            $table->date('trade_date');
            $table->date('expiry_date');
            $table->dateTime('interval_time');          // e.g. 2026-04-02 09:15:00

            // ── Instrument ───────────────────────────────────────────────────
            $table->string('base_symbol', 20);          // SENSEX
            $table->string('future_symbol', 40);        // SENSEXAPRFUT
            $table->decimal('future_price', 12, 2)->nullable();
            $table->decimal('atm_strike', 12, 2);       // Frozen at 09:15 close
            $table->enum('instrument_type', ['CE', 'PE']);
            $table->decimal('strike', 12, 2);
            $table->string('strike_position', 10);      // ATM, ATM+1 … N/A
            $table->string('trading_symbol', 60);       // e.g. SENSEX2640280000CE
            $table->unsignedBigInteger('instrument_token');

            // ── OHLCV + OI ───────────────────────────────────────────────────
            $table->decimal('open',   12, 2)->default(0);
            $table->decimal('high',   12, 2)->default(0);
            $table->decimal('low',    12, 2)->default(0);
            $table->decimal('close',  12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);

            // ── Quality flag ─────────────────────────────────────────────────
            $table->tinyInteger('is_missing')->default(0); // 1 = no data from broker

            $table->timestamps();

            // ── Unique constraint (deduplication / upsert key) ───────────────
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                'sensex_expiry_candles_unique'
            );

            // ── Query indexes ────────────────────────────────────────────────
            $table->index(['trade_date', 'expiry_date', 'instrument_type'], 'sec_date_expiry_type_idx');
            $table->index(['trade_date', 'strike', 'instrument_type'], 'sec_date_strike_type_idx');
            $table->index('interval_time', 'sec_interval_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sensex_expiry_candles');
    }
};