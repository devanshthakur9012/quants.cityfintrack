<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     /**
     * OIIV Order Book — tracks every order placed by the OIIV auto-trading system.
     *
     * This table mirrors Zerodha's order model exactly so we can reconcile
     * our records against Zerodha's API at any time.
     *
     * Lifecycle:  TRIGGER → PENDING → OPEN (partial) → COMPLETE / CANCELLED / REJECTED
     *
     * A single "signal" may produce multiple child orders (freeze-lot splitting),
     * so oiiv_auto_order_id groups them under the parent signal row.
     */
    public function up(): void
    {
        Schema::create('oiiv_order_book', function (Blueprint $table) {
            $table->id();
 
            // ── Ownership ──────────────────────────────────────────────────
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            $table->unsignedBigInteger('oiiv_auto_order_id')->nullable()
                  ->comment('FK → oiiv_auto_orders.id (the signal row that triggered this order)');
 
            // ── Zerodha order identity ─────────────────────────────────────
            $table->string('zerodha_order_id', 64)->nullable()
                  ->comment('Returned by Zerodha placeOrder() — null until placed');
            $table->string('zerodha_parent_order_id', 64)->nullable()
                  ->comment('For bracket/CO legs');
            $table->string('zerodha_exchange_order_id', 64)->nullable()
                  ->comment('NSE/BSE exchange order id — set after execution');
 
            // ── Instrument ────────────────────────────────────────────────
            $table->string('trading_symbol', 64);
            $table->string('base_symbol', 32)->nullable()
                  ->comment('e.g. RELIANCE (stripped from trading_symbol)');
            $table->string('exchange', 8)->default('NFO');
            $table->string('option_type', 4)->nullable()->comment('CE / PE / FUT');
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('instrument_token')->nullable();
 
            // ── Signal context ────────────────────────────────────────────
            $table->string('signal_date', 10)->nullable()
                  ->comment('Y-m-d of the OI signal that generated this order');
            $table->string('signal_type', 32)->nullable()
                  ->comment('e.g. EOD_RANK1_BULLISH_ALIGN_CE');
            $table->string('oi_condition', 64)->nullable()
                  ->comment('CE↑+PE↓ etc.');
            $table->string('sentiment', 16)->nullable()
                  ->comment('BULLISH / BEARISH');
            $table->decimal('spot_price_at_signal', 12, 2)->nullable();
            $table->decimal('ce_oi_change_pct', 10, 4)->nullable();
            $table->decimal('pe_oi_change_pct', 10, 4)->nullable();
 
            // ── Order parameters ──────────────────────────────────────────
            $table->string('transaction_type', 8)->default('BUY')
                  ->comment('BUY / SELL');
            $table->string('order_type', 16)->default('LIMIT')
                  ->comment('LIMIT / MARKET / SL / SL-M');
            $table->string('product', 8)->default('NRML')
                  ->comment('NRML / MIS / CNC');
            $table->string('validity', 8)->default('DAY')
                  ->comment('DAY / IOC / TTL');
            $table->unsignedInteger('quantity')
                  ->comment('Number of LOTS (our unit)');
            $table->unsignedInteger('quantity_units')->nullable()
                  ->comment('quantity × lot_size = actual shares to send Zerodha');
            $table->unsignedInteger('lot_size')->default(1);
 
            // ── Prices ────────────────────────────────────────────────────
            $table->decimal('trigger_price', 12, 2)->nullable()
                  ->comment('LTP at signal detection time (our intended entry)');
            $table->decimal('placed_price', 12, 2)->nullable()
                  ->comment('Price we sent to Zerodha in the order request');
            $table->decimal('average_price', 12, 2)->nullable()
                  ->comment('Actual average fill price returned by Zerodha');
            $table->decimal('last_modified_price', 12, 2)->nullable()
                  ->comment('Latest price after user modification');
            $table->unsignedInteger('filled_quantity')->default(0);
            $table->unsignedInteger('pending_quantity')->nullable();
            $table->unsignedInteger('cancelled_quantity')->default(0);
 
            // ── Status ────────────────────────────────────────────────────
            // Mirrors Zerodha status strings exactly so we can SET value directly from API.
            $table->string('status', 32)->default('TRIGGER_PENDING')
                  ->comment('TRIGGER_PENDING | OPEN | COMPLETE | CANCELLED | REJECTED | AMO REQ RECEIVED');
            $table->string('status_message', 512)->nullable()
                  ->comment('Rejection / cancellation reason from Zerodha');
            $table->string('internal_status', 32)->default('pending')
                  ->comment('pending | placed | synced | completed | cancelled | failed | modified');
 
            // ── Freeze-lot splitting ──────────────────────────────────────
            $table->unsignedInteger('lot_chunk_number')->default(1)
                  ->comment('Which chunk this is when order was split across freeze limit');
            $table->unsignedInteger('lot_chunk_total')->default(1)
                  ->comment('Total chunks for this signal');
 
            // ── Modification tracking ──────────────────────────────────────
            $table->unsignedInteger('modify_count')->default(0)
                  ->comment('How many times price was modified');
            $table->json('modification_history')->nullable()
                  ->comment('Array of {price, time, source} changes');
 
            // ── Timestamps ───────────────────────────────────────────────
            $table->timestamp('signal_detected_at')->nullable();
            $table->timestamp('placed_at')->nullable()
                  ->comment('When we called Zerodha placeOrder()');
            $table->timestamp('filled_at')->nullable()
                  ->comment('When status became COMPLETE (from Zerodha)');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable()
                  ->comment('Last time we polled Zerodha for this order');
            $table->timestamps();
 
            // ── Indexes ───────────────────────────────────────────────────
            $table->index('user_id');
            $table->index('broker_api_id');
            $table->index('oiiv_auto_order_id');
            $table->index('zerodha_order_id');
            $table->index('status');
            $table->index('internal_status');
            $table->index('signal_date');
            $table->index(['user_id', 'broker_api_id', 'status']);
            $table->index(['trading_symbol', 'signal_date']);
 
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('oiiv_order_book');
    }
};
