<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_stock_features_table
 *
 * Stores the normalised feature vector for every symbol × trading day.
 * This is the core input for the SimilarityService ("astrology engine").
 *
 * Column notes:
 *   distance_from_high  SIGNED %: 0 means AT the 52w high; -5 means 5% below it.
 *   distance_from_low   SIGNED %: 0 means AT the 52w low;  +15 means 15% above it.
 *   volume_spike        true when today's volume > 1.5× 20-day average
 *   rsi_zone            OVERBOUGHT(≥70) | OVERSOLD(≤30) | NEUTRAL
 *   features_json       extra indicators: sma_20, sma_50, day_range_pct, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_features', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->date('trade_date');

            // UP    = close within 10% of 52-week high
            // DOWN  = close within 10% above 52-week low
            // SIDEWAYS = everything in between
            $table->string('trend', 12)->comment('UP | DOWN | SIDEWAYS');

            // HIGH = daily range > 2% of close; LOW otherwise
            $table->string('volatility', 8)->comment('HIGH | LOW');

            // Negative value means close is BELOW the 52w high (normal)
            $table->decimal('distance_from_high', 8, 4)->default(0);

            // Positive value means close is ABOVE the 52w low (normal)
            $table->decimal('distance_from_low', 8, 4)->default(0);

            // true when volume > 1.5x 20-day average volume
            $table->boolean('volume_spike')->default(false);

            // OVERBOUGHT | OVERSOLD | NEUTRAL (Wilder 14-period RSI)
            $table->string('rsi_zone', 12)->nullable()->default('NEUTRAL');

            // Actual RSI value stored for reference / debugging
            $table->decimal('rsi_value', 6, 2)->nullable();

            // JSON bag of extra computed indicators (forward-compatible)
            $table->json('features_json')->nullable();

            $table->timestamps();

            $table->unique(['symbol', 'trade_date'], 'uq_feature');

            // Similarity engine: WHERE symbol=? AND trend=? AND volatility=? AND dist_high BETWEEN
            $table->index(['symbol', 'trend', 'volatility'], 'idx_sim_base');
            $table->index(['symbol', 'trade_date'], 'idx_symbol_date');
            $table->index(['symbol', 'rsi_zone'], 'idx_symbol_rsi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_features');
    }
};
