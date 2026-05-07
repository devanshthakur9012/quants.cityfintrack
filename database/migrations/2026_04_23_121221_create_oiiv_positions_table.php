<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   /**
     * OIIV Positions — tracks open and closed positions that originated
     * from the OIIV auto-trading system.
     *
     * A position is created when an oiiv_order_book order reaches COMPLETE.
     * It is closed when a SELL order for the same instrument completes,
     * OR when Zerodha's positions API reports qty = 0 for that symbol.
     *
     * Only OIIV-originated trades are tracked here — manual Zerodha trades
     * are deliberately excluded.
     */
    public function up(): void
    {
        Schema::create('oiiv_positions', function (Blueprint $table) {
            $table->id();
 
            // ── Ownership ──────────────────────────────────────────────────
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            $table->unsignedBigInteger('oiiv_auto_order_id')->nullable()
                  ->comment('Original signal that opened this position');
            $table->unsignedBigInteger('entry_order_book_id')->nullable()
                  ->comment('FK → oiiv_order_book.id (the BUY order)');
            $table->unsignedBigInteger('exit_order_book_id')->nullable()
                  ->comment('FK → oiiv_order_book.id (the SELL order, if any)');
 
            // ── Instrument ────────────────────────────────────────────────
            $table->string('trading_symbol', 64);
            $table->string('base_symbol', 32)->nullable();
            $table->string('exchange', 8)->default('NFO');
            $table->string('option_type', 4)->nullable()->comment('CE / PE');
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('instrument_token')->nullable();
 
            // ── Signal context ────────────────────────────────────────────
            $table->date('signal_date')->nullable();
            $table->string('signal_type', 64)->nullable();
            $table->string('sentiment', 16)->nullable()->comment('BULLISH / BEARISH');
            $table->string('oi_condition', 64)->nullable();
            $table->decimal('spot_price_at_signal', 12, 2)->nullable();
            $table->decimal('ce_oi_change_pct', 10, 4)->nullable();
            $table->decimal('pe_oi_change_pct', 10, 4)->nullable();
 
            // ── Position details ──────────────────────────────────────────
            $table->string('position_type', 8)->default('LONG')
                  ->comment('LONG (BUY) / SHORT (SELL)');
            $table->string('product', 8)->default('NRML');
            $table->unsignedInteger('quantity')
                  ->comment('Number of lots');
            $table->unsignedInteger('quantity_units')->nullable()
                  ->comment('Actual shares (qty × lot_size)');
            $table->unsignedInteger('lot_size')->default(1);
 
            // ── Entry ─────────────────────────────────────────────────────
            $table->decimal('entry_price', 12, 2)
                  ->comment('Average fill price of the BUY order');
            $table->decimal('entry_ltp_at_signal', 12, 2)->nullable()
                  ->comment('LTP at the time signal was generated');
            $table->timestamp('entry_at')->nullable();
 
            // ── Exit ──────────────────────────────────────────────────────
            $table->decimal('exit_price', 12, 2)->nullable()
                  ->comment('Average fill price of the SELL order');
            $table->timestamp('exit_at')->nullable();
            $table->string('exit_source', 32)->nullable()
                  ->comment('SYSTEM_SELL | MANUAL_ZERODHA | EXPIRED | SQUARE_OFF_CMD');
 
            // ── Live data (updated by sync command) ───────────────────────
            $table->decimal('last_price', 12, 2)->nullable()
                  ->comment('Latest LTP from Zerodha (updated by sync)');
            $table->decimal('unrealized_pnl', 14, 2)->nullable();
            $table->decimal('realized_pnl', 14, 2)->nullable()
                  ->comment('Booked P&L after close');
            $table->decimal('pnl_percentage', 8, 4)->nullable();
 
            // ── Status ────────────────────────────────────────────────────
            $table->string('status', 16)->default('open')
                  ->comment('open | closed | expired');
            $table->boolean('is_btst')->default(true)
                  ->comment('BTST = Buy Today Sell Tomorrow');
 
            // ── Sync ─────────────────────────────────────────────────────
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
 
            // ── Indexes ───────────────────────────────────────────────────
            $table->index('user_id');
            $table->index('broker_api_id');
            $table->index('status');
            $table->index('signal_date');
            $table->index(['user_id', 'broker_api_id', 'status']);
            $table->index(['trading_symbol', 'status']);
 
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('oiiv_positions');
    }
};
