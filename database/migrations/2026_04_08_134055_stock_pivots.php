<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_stock_pivots_table
 *
 * Stores detected swing pivot highs and lows.
 * - PIVOT HIGH: a bar whose high is strictly greater than N bars on each side
 * - PIVOT LOW : a bar whose low  is strictly less    than N bars on each side
 *
 * One row per symbol + date + type (upsert-safe via unique key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_pivots', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->comment('e.g. BSE, RELIANCE, NIFTY');
            $table->date('trade_date')->comment('Date of the pivot bar');
            $table->enum('pivot_type', ['HIGH', 'LOW'])->comment('Swing high or swing low');
            $table->decimal('price', 12, 2)->comment('High price for HIGH pivot, Low price for LOW pivot');
            $table->unsignedTinyInteger('strength')->default(2)
                  ->comment('Number of confirming bars on each side (2=standard, 3=strong)');
            $table->timestamps();

            // Prevent duplicate: one pivot type per symbol per date
            $table->unique(['symbol', 'trade_date', 'pivot_type'], 'uq_pivot');

            // Fast queries: "all HIGH pivots for RELIANCE ordered by date"
            $table->index(['symbol', 'pivot_type', 'trade_date'], 'idx_symbol_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_pivots');
    }
};