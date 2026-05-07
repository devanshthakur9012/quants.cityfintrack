<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_stock_patterns_table
 *
 * Stores detected chart pattern instances.
 *
 * Supported pattern_type values:
 *   DOUBLE_TOP         – two similar highs → bearish reversal signal
 *   DOUBLE_BOTTOM      – two similar lows  → bullish reversal signal
 *   BREAKOUT           – close above resistance pivot + volume confirmation
 *   BREAKDOWN          – close below support pivot + volume confirmation
 *   SUPPORT_BOUNCE     – price touched pivot low + bullish reversal candle
 *   RESISTANCE_REJECT  – price touched pivot high + bearish reversal candle
 *
 * meta_json stores pattern-specific data (prices, neckline, vol_ratio, candle type, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->string('pattern_type', 50)->comment('DOUBLE_TOP, DOUBLE_BOTTOM, BREAKOUT, etc.');
            $table->date('start_date')->comment('Date of the first pivot / start of pattern');
            $table->date('end_date')->comment('Date pattern was confirmed / completed');

            // 0–100: 80+ = high confidence, 60–79 = medium, <60 = low
            $table->unsignedTinyInteger('confidence')->default(0);

            // Pattern-specific payload
            // DOUBLE_TOP:    { p1_price, p2_price, pct_diff, neckline }
            // BREAKOUT:      { resistance, close, breakout_pct, vol_ratio, volume_spike }
            // SUPPORT_BOUNCE:{ support, close, dist_pct, candle_type }
            $table->json('meta_json')->nullable();

            $table->timestamps();

            // No duplicate pattern for same symbol + type + date range
            $table->unique(['symbol', 'pattern_type', 'start_date', 'end_date'], 'uq_pattern');

            $table->index(['symbol', 'pattern_type', 'end_date'], 'idx_symbol_pattern_end');
            $table->index(['symbol', 'end_date'], 'idx_symbol_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_patterns');
    }
};
