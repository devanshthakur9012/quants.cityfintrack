<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('original_30min_ohlc_data', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('broker_api_id')->index();
            $table->date('trade_date')->index();
            $table->dateTime('interval_time')->index()
                  ->comment('30-min bar start: 09:15, 09:45, 10:15 … 15:15');

            $table->string('base_symbol',   20)->index();
            $table->string('trading_symbol', 40)->index();
            $table->string('future_symbol',  40)->nullable();
            $table->decimal('future_price', 10, 2)->nullable();
            $table->decimal('atm_strike',   10, 2)->nullable();

            $table->string('instrument_type', 5)->index()
                  ->comment('FUT | CE | PE');
            $table->decimal('strike', 10, 2)->nullable();
            $table->unsignedBigInteger('instrument_token')->index();

            $table->decimal('open',  10, 2)->default(0);
            $table->decimal('high',  10, 2)->default(0);
            $table->decimal('low',   10, 2)->default(0);
            $table->decimal('close', 10, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);

            $table->string('strike_position', 10)->nullable()
                  ->comment('ATM, ATM+1 … ATM-5, N/A for FUT');
            $table->date('expiry_date')->nullable()->index();
            $table->tinyInteger('is_missing')->default(0)
                  ->comment('1 = candle was absent from API, zeros stored');

            $table->timestamps();

            // ── Unique constraint (upsert key) ────────────────────────────────
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                'orig30_upsert_key'
            );

            // ── Composite query indexes ───────────────────────────────────────
            $table->index(['base_symbol', 'trade_date', 'interval_time'],   'orig30_sym_date_time');
            $table->index(['base_symbol', 'trade_date', 'instrument_type'], 'orig30_sym_date_type');
            $table->index(['base_symbol', 'expiry_date'],                   'orig30_sym_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('original_30min_ohlc_data');
    }
};
