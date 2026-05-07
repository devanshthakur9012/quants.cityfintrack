<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates next_series_option_ohlc_data table.
 *
 * Identical structure to option_ohlc_data.
 * Stores 15-min OHLC candles for the NEXT expiry series
 * (e.g. April data while March is currently live).
 *
 * strike_position ENUM includes ATM±5 to match the 11-strike range
 * used by NextSeriesLiveOptionOhlcCollector.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_series_option_ohlc_data', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('broker_api_id');
            $table->dateTime('trade_date');
            $table->dateTime('interval_time');
            $table->string('trading_symbol', 50);
            $table->string('base_symbol', 30);
            $table->string('future_symbol', 50)->nullable();
            $table->decimal('future_price', 12, 2)->nullable();
            $table->decimal('atm_strike', 12, 2)->nullable();

            $table->enum('instrument_type', ['FUT', 'CE', 'PE']);
            $table->decimal('strike', 12, 2)->nullable();
            $table->unsignedBigInteger('instrument_token')->nullable();

            $table->decimal('open',  12, 2)->default(0);
            $table->decimal('high',  12, 2)->default(0);
            $table->decimal('low',   12, 2)->default(0);
            $table->decimal('close', 12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);

            // ATM±5 range to match the 11-strike buildStrikeList()
            $table->enum('strike_position', [
                'ATM',
                'ATM+1', 'ATM-1',
                'ATM+2', 'ATM-2',
                'ATM+3', 'ATM-3',
                'ATM+4', 'ATM-4',
                'ATM+5', 'ATM-5',
                'N/A',
            ])->default('N/A');

            $table->date('expiry_date')->nullable();
            $table->tinyInteger('is_missing')->default(0);

            $table->timestamps();

            // ── Unique constraint ─────────────────────────────────────────────
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                'next_series_ohlc_unique'
            );

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index(['base_symbol', 'trade_date', 'instrument_type'], 'idx_ns_symbol_date_type');
            $table->index(['base_symbol', 'expiry_date'],                   'idx_ns_symbol_expiry');
            $table->index(['trade_date', 'instrument_type'],                'idx_ns_date_type');
            $table->index('instrument_token',                               'idx_ns_token');
            $table->index('strike_position',                                'idx_ns_strike_pos');
            $table->index(['base_symbol', 'strike', 'instrument_type'],     'idx_ns_symbol_strike_type');

            // ── Foreign key ───────────────────────────────────────────────────
            $table->foreign('broker_api_id')
                  ->references('id')->on('broker_apis')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_series_option_ohlc_data');
    }
};