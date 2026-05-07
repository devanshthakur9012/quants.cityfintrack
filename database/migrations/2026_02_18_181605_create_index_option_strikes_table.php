<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('index_option_strikes', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('broker_api_id');
            $table->string('underlying_symbol', 50);   // e.g. BANKNIFTY
            $table->string('trading_symbol', 255)->nullable(); // FUT symbol or CE/PE symbols (comma separated for merged)
            $table->string('strike_position', 20);     // FUT | CE_MERGED | PE_MERGED
            $table->date('trading_date');

            // Option info
            $table->string('option_type', 10)->nullable();  // FUT | CE | PE
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->string('expiry', 20)->nullable();       // e.g. 26FEB
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('instrument_token')->nullable();
            $table->string('exchange', 20)->default('NFO');
            $table->integer('lot_size')->default(1);
            $table->boolean('is_active')->default(true);
            $table->decimal('spot_price', 12, 2)->nullable();

            // ── OI fields ─────────────────────────────────────────────────
            $table->unsignedBigInteger('daily_oi')->default(0);
            $table->unsignedBigInteger('daily_oi_prev')->default(0);
            $table->bigInteger('daily_oi_change')->default(0);
            $table->decimal('daily_oi_change_pct', 10, 4)->nullable();
            $table->string('direction', 20)->nullable();   // BULLISH | BEARISH | NEUTRAL
            $table->string('strength', 20)->nullable();    // STRONG | MODERATE | WEAK
            $table->string('market_bias', 50)->nullable();

            // ── IV fields ─────────────────────────────────────────────────
            $table->decimal('daily_iv', 10, 4)->nullable();
            $table->decimal('daily_iv_prev', 10, 4)->nullable();
            $table->decimal('daily_iv_change', 10, 4)->nullable();
            $table->decimal('daily_iv_change_pct', 10, 4)->nullable();
            $table->string('iv_direction', 20)->nullable();
            $table->string('iv_strength', 20)->nullable();

            // ── Close price fields ────────────────────────────────────────
            $table->decimal('daily_close', 12, 4)->nullable();
            $table->decimal('daily_close_prev', 12, 4)->nullable();
            $table->decimal('daily_close_change', 12, 4)->nullable();
            $table->decimal('daily_close_change_pct', 10, 4)->nullable();

            // ── Signal fields (on FUT row) ────────────────────────────────
            $table->string('options_sentiment', 20)->nullable();   // BULLISH | BEARISH | NEUTRAL
            $table->string('final_sentiment', 20)->nullable();
            $table->string('trade_action', 20)->nullable();        // BUY CE | BUY PE | WAIT
            $table->string('futures_oi_view', 50)->nullable();
            $table->text('oi_interpretation')->nullable();
            $table->string('oi_condition', 50)->nullable();        // CE ↑ + PE ↓ etc.
            $table->decimal('ce_oi_change_pct', 10, 4)->nullable();
            $table->decimal('pe_oi_change_pct', 10, 4)->nullable();
            $table->decimal('pe_ce_ratio', 10, 4)->nullable();

            // ── BTST fields (on FUT row) ──────────────────────────────────
            $table->string('btst_signal', 20)->nullable();
            $table->decimal('btst_confidence', 6, 2)->nullable();
            $table->text('btst_reason')->nullable();

            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // ── Indexes ───────────────────────────────────────────────────
            $table->unique(['broker_api_id', 'underlying_symbol', 'strike_position', 'trading_date'], 'idx_unique_strike');
            $table->index(['underlying_symbol', 'trading_date']);
            $table->index(['broker_api_id', 'trading_date']);
            $table->index('strike_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_option_strikes');
    }
};
