<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores INTRADAY OI + IV + Close Price data
     * captured from 9:15 AM to 12:15 PM (3-hour window) on the SAME day.
     *
     * open_oi    = 9:15 AM candle OI
     * current_oi = 12:15 PM candle OI
     */
    public function up(): void
    {
        Schema::create('option_strike_9to12', function (Blueprint $table) {
            $table->id();

            // Broker & Symbol identification
            $table->unsignedBigInteger('broker_api_id');
            $table->string('underlying_symbol', 50);
            $table->string('trading_symbol', 255)->nullable();
            $table->string('strike_position', 20);  // FUT, CE_MERGED, PE_MERGED
            $table->date('trading_date');

            // Option metadata
            $table->string('option_type', 10)->nullable();    // FUT, CE, PE
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->string('expiry', 10)->nullable();
            $table->date('expiry_date')->nullable();
            $table->bigInteger('instrument_token')->nullable();
            $table->string('exchange', 10)->nullable()->default('NFO');
            $table->integer('lot_size')->nullable()->default(1);
            $table->decimal('spot_price', 12, 2)->nullable();

            // ── OI Fields (9:15 AM open vs 12:15 PM current) ──
            $table->bigInteger('open_oi')->nullable()->comment('OI at 9:15 AM');
            $table->bigInteger('current_oi')->nullable()->comment('OI at 12:15 PM');
            $table->bigInteger('oi_change')->nullable()->comment('current_oi - open_oi');
            $table->decimal('oi_change_pct', 10, 4)->nullable()->comment('% change 9:15 → 12:15');
            $table->string('direction', 20)->nullable();      // BULLISH, BEARISH, NEUTRAL
            $table->string('strength', 20)->nullable();       // HIGH, MEDIUM, LOW
            $table->string('market_bias', 50)->nullable();    // FUT only

            // ── IV Fields (CE_MERGED / PE_MERGED only) ──
            $table->decimal('open_iv', 10, 4)->nullable()->comment('IV at 9:15 AM');
            $table->decimal('current_iv', 10, 4)->nullable()->comment('IV at 12:15 PM');
            $table->decimal('iv_change', 10, 4)->nullable();
            $table->decimal('iv_change_pct', 10, 4)->nullable();
            $table->string('iv_direction', 20)->nullable();
            $table->string('iv_strength', 20)->nullable();

            // ── Close Price Fields ──
            $table->decimal('open_close', 12, 4)->nullable()->comment('Close at 9:15 AM candle');
            $table->decimal('current_close', 12, 4)->nullable()->comment('Close at 12:15 PM candle');
            $table->decimal('close_change', 12, 4)->nullable();
            $table->decimal('close_change_pct', 10, 4)->nullable();

            // ── Signals (stored on FUT row) ──
            $table->string('options_sentiment', 20)->nullable();   // BULLISH, BEARISH, NEUTRAL
            $table->string('final_sentiment', 20)->nullable();
            $table->string('trade_action', 30)->nullable();        // BUY CE, BUY PE, WAIT
            $table->decimal('pe_ce_ratio', 10, 4)->nullable();
            $table->string('futures_oi_view', 50)->nullable();

            // OI interpretation
            $table->text('oi_interpretation')->nullable();
            $table->string('oi_condition', 50)->nullable();
            $table->decimal('ce_oi_change_pct', 10, 4)->nullable();
            $table->decimal('pe_oi_change_pct', 10, 4)->nullable();

            // ── BTST Signals (FUT row) ──
            $table->string('btst_signal', 20)->nullable();
            $table->decimal('btst_confidence', 5, 2)->nullable();
            $table->text('btst_reason')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // One row per broker + symbol + position + date
            $table->unique(
                ['broker_api_id', 'underlying_symbol', 'strike_position', 'trading_date'],
                'uq_9to12_broker_symbol_pos_date'
            );

            $table->index(['broker_api_id', 'underlying_symbol', 'trading_date'], 'idx_9to12_broker_sym_date');
            $table->index(['underlying_symbol', 'trading_date'], 'idx_9to12_sym_date');
            $table->index('trading_date', 'idx_9to12_date');
            $table->index('strike_position', 'idx_9to12_position');

            $table->foreign('broker_api_id')
                  ->references('id')
                  ->on('broker_apis')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_strike_9to12');
    }
};