<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up(): void
    {
        // ── Table 1: mf_fund_investments ──────────────────────────────────────
        // Stores how much money is invested per fund (configurable by admin)
        // e.g. PPFAS → ₹10,00,000
        Schema::create('mf_fund_investments', function (Blueprint $table) {
            $table->id();
 
            $table->foreignId('mutual_fund_id')
                  ->constrained('mutual_funds')
                  ->cascadeOnDelete();
 
            $table->decimal('invested_amount', 15, 2)->default(1000000); // default ₹10L
            $table->boolean('is_active')->default(true);
            $table->timestamps();
 
            $table->unique('mutual_fund_id');
        });
 
        // ── Table 2: mf_positions ─────────────────────────────────────────────
        // One row per BUY signal entry. Updated when SELL signal fires.
        // Carries forward until closed.
        Schema::create('mf_positions', function (Blueprint $table) {
            $table->id();
 
            $table->string('symbol');       // e.g. HDFCBANK
            $table->string('exchange');     // NSE / BSE
 
            // Which fund this position belongs to
            // A stock like HDFCBANK can be in 2 funds → 2 separate position rows
            $table->foreignId('mutual_fund_id')
                  ->constrained('mutual_funds')
                  ->cascadeOnDelete();
 
            // Allocation % from mutual_fund_stocks at time of entry
            $table->decimal('allocation_pct', 5, 2)->default(0);
 
            // Invested amount for this stock = fund_invested_amount × (allocation_pct / 100)
            // Stored so it doesn't change if allocation changes later
            $table->decimal('invested_amount', 15, 2)->default(0);
 
            // Quantity = invested_amount / buy_price
            $table->decimal('quantity', 10, 4)->default(0);
 
            // ── Entry ─────────────────────────────────────────────────────────
            $table->decimal('buy_price', 10, 2)->default(0);
            $table->dateTime('buy_time')->nullable();
            $table->string('buy_signal_reason')->nullable(); // e.g. "EMA20>EMA50 + Pullback + RSI40-60"
 
            // ── Exit ──────────────────────────────────────────────────────────
            $table->decimal('sell_price', 10, 2)->nullable();
            $table->dateTime('sell_time')->nullable();
            $table->string('sell_signal_reason')->nullable();
 
            // ── P&L ──────────────────────────────────────────────────────────
            // booked_profit = (sell_price - buy_price) × quantity  [filled on close]
            $table->decimal('booked_profit', 15, 2)->nullable();
            $table->decimal('booked_profit_pct', 8, 4)->nullable(); // %
 
            // ── Status ───────────────────────────────────────────────────────
            // OPEN = position is live (no sell signal yet)
            // CLOSED = sell signal fired, profit booked
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
 
            $table->timestamps();
 
            // Indexes for fast queries
            $table->index(['symbol', 'mutual_fund_id', 'status']);
            $table->index(['mutual_fund_id', 'status']);
            $table->index('status');
            $table->index('buy_time');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('mf_positions');
        Schema::dropIfExists('mf_fund_investments');
    }
};
