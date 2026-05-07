<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_stock_signals_table
 *
 * Stores the final output of the analysis pipeline.
 * One row per symbol per day — updated on each pipeline run.
 *
 * Confidence interpretation:
 *   ≥ 65  → BUY
 *   36–64 → HOLD
 *   ≤ 35  → SELL
 *
 * score_json breakdown example:
 * {
 *   "pattern_score": 24,
 *   "similarity_score": 25,
 *   "pivot_score": 20,
 *   "volume_score": 10,
 *   "trend_score": 10,
 *   "total": 89,
 *   "confidence": 95,
 *   "pattern": "DOUBLE_BOTTOM",
 *   "pattern_confidence": 80,
 *   "similar_count": 12,
 *   "similar_bullish_pct": 75.0,
 *   "avg_return_5d": 2.11,
 *   "nearest_support": 2450.00,
 *   "nearest_resistance": 2620.00
 * }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_signals', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20);
            $table->date('signal_date');
            $table->enum('signal_type', ['BUY', 'SELL', 'HOLD']);

            // 0–100 confidence score
            $table->unsignedTinyInteger('confidence')->default(0);

            // Human-readable pipe-separated reason string
            // e.g. "Pattern: DOUBLE_BOTTOM (conf: 80%, bullish) | Similarity: 12 matches → 75% bullish..."
            $table->text('reason')->nullable();

            // Full score breakdown for debugging/frontend display
            $table->json('score_json')->nullable();

            $table->timestamps();

            $table->unique(['symbol', 'signal_date'], 'uq_signal');

            // Dashboard queries: "all BUY signals today with confidence ≥ 70"
            $table->index(['signal_date', 'signal_type', 'confidence'], 'idx_date_type_conf');
            $table->index(['symbol', 'signal_date'], 'idx_symbol_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_signals');
    }
};
