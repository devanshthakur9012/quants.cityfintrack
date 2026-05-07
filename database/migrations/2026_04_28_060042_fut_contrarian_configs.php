<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────
        //  CONFIG TABLE
        // ─────────────────────────────────────────────────────────────
        Schema::create('fut_contrarian_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
 
            // Which signal windows this config trades
            $table->boolean('trade_30min')->default(true)
                  ->comment('Trade OI-30min aligned signals (buy at 10:00)');
            $table->boolean('trade_1hr')->default(true)
                  ->comment('Trade OI-1HR aligned signals (buy at 10:30)');
 
            // Order execution settings
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('NRML');
            $table->decimal('disc_ltp', 5, 2)->default(0)
                  ->comment('Discount % below LTP for LIMIT orders');
 
            // Quantity settings — separate for Index vs Stock, CE vs PE
            $table->integer('index_ce_quantity')->default(0);
            $table->integer('index_pe_quantity')->default(0);
            $table->integer('stock_ce_quantity')->default(0);
            $table->integer('stock_pe_quantity')->default(0);
 
            // Optional symbol whitelist (JSON). NULL = trade all symbols
            $table->json('allowed_symbols')->nullable()
                  ->comment('NULL = all symbols, [] = none, [...] = whitelist');
 
            // Signal alignment requirement
            // both   = require FULL MATCH (FUT + both OI windows agree)
            // any    = require PARTIAL MATCH (FUT + at least one OI window)
            $table->enum('alignment_mode', ['both', 'any'])->default('any');
 
            $table->boolean('status')->default(true);
            $table->timestamps();
 
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
 
        // ─────────────────────────────────────────────────────────────
        //  ORDERS TABLE  (one row per signal per symbol per date)
        // ─────────────────────────────────────────────────────────────
        Schema::create('fut_contrarian_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');
 
            // Signal info
            $table->date('signal_date');
            $table->string('base_symbol', 32);            // e.g. RELIANCE
            $table->string('trading_symbol', 64)->nullable(); // FUT trading symbol
 
            // FUT direction
            $table->decimal('fut_prev_close', 12, 2)->default(0);
            $table->decimal('fut_today_open', 12, 2)->default(0);
            $table->decimal('fut_change_pct', 8, 4)->default(0);
            $table->enum('fut_direction', ['UP', 'DOWN', 'FLAT'])->default('FLAT');
 
            // Contrarian action derived from FUT direction
            $table->enum('trade_action', ['BUY CE', 'BUY PE', 'WAIT'])->default('WAIT');
 
            // Option selected
            $table->string('option_type', 2)->nullable();         // CE or PE
            $table->decimal('best_strike', 12, 2)->nullable();
            $table->string('option_symbol', 64)->nullable();
            $table->unsignedBigInteger('option_instrument_token')->nullable();
            $table->decimal('entry_price', 12, 2)->nullable()
                  ->comment('LTP at signal time');
            $table->integer('lot_size')->default(1);
            $table->integer('quantity')->default(0)->comment('Lots to trade');
            $table->string('expiry_date', 12)->nullable();
 
            // OI signal windows
            $table->string('oi_30min_signal', 16)->nullable();  // BULLISH/BEARISH/NEUTRAL
            $table->string('oi_1hr_signal', 16)->nullable();    // BULLISH/BEARISH/NEUTRAL
            $table->string('alignment_30min', 20)->nullable();  // FULL MATCH/PARTIAL/CONFLICT
            $table->string('alignment_1hr', 20)->nullable();
 
            // Which windows were actually traded
            $table->boolean('traded_30min')->default(false);
            $table->boolean('traded_1hr')->default(false);
 
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->timestamp('signal_detected_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
 
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('fut_contrarian_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
 
            // Prevent duplicate signals per config per symbol per date
            $table->unique(['config_id', 'base_symbol', 'signal_date'], 'uq_fc_config_symbol_date');
        });
 
        // ─────────────────────────────────────────────────────────────
        //  ORDER BOOK TABLE  (one row per API call / freeze chunk)
        // ─────────────────────────────────────────────────────────────
        Schema::create('fut_contrarian_order_book', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            $table->unsignedBigInteger('fc_order_id')
                  ->comment('FK to fut_contrarian_orders');
 
            // Broker order IDs
            $table->string('zerodha_order_id', 64)->nullable();
            $table->string('exchange_order_id', 64)->nullable();
 
            // Instrument
            $table->string('trading_symbol', 64);
            $table->string('base_symbol', 32);
            $table->string('exchange', 8)->default('NFO');
            $table->string('option_type', 2)->nullable();
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->unsignedBigInteger('instrument_token')->nullable();
 
            // Signal context
            $table->date('signal_date')->nullable();
            $table->string('signal_window', 8)->nullable()
                  ->comment('30min or 1hr');
            $table->string('oi_signal', 16)->nullable();
            $table->string('fut_direction', 8)->nullable();
            $table->string('sentiment', 16)->nullable();
            $table->decimal('spot_price_at_signal', 12, 2)->nullable();
 
            // Order parameters
            $table->string('transaction_type', 8)->default('BUY');
            $table->string('order_type', 8)->default('LIMIT');
            $table->string('product', 8)->default('NRML');
            $table->string('validity', 8)->default('DAY');
            $table->integer('quantity')->default(0)->comment('Lots');
            $table->integer('quantity_units')->default(0)->comment('Lots × lot_size');
            $table->integer('lot_size')->default(1);
            $table->decimal('trigger_price', 12, 2)->nullable()->comment('LTP at order time');
            $table->decimal('placed_price', 12, 2)->nullable()->comment('Actual order price sent');
            $table->decimal('average_price', 12, 2)->nullable()->comment('Fill price from broker');
            $table->integer('filled_quantity')->default(0);
            $table->integer('pending_quantity')->nullable();
            $table->integer('cancelled_quantity')->default(0);
 
            // Status
            $table->string('status', 32)->default('OPEN')
                  ->comment('OPEN/COMPLETE/CANCELLED/REJECTED/TRIGGER_PENDING');
            $table->string('status_message', 500)->nullable();
            $table->string('internal_status', 20)->default('pending')
                  ->comment('pending/placed/synced/completed/cancelled/failed');
 
            // Chunking
            $table->integer('lot_chunk_number')->default(1);
            $table->integer('lot_chunk_total')->default(1);
 
            // Broker type (zerodha or angel)
            $table->string('broker_type', 16)->default('zerodha');
 
            // Timestamps
            $table->timestamp('signal_detected_at')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
 
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            $table->foreign('fc_order_id')->references('id')->on('fut_contrarian_orders')->onDelete('cascade');
 
            $table->index(['signal_date', 'base_symbol']);
            $table->index(['status', 'internal_status']);
            $table->index('zerodha_order_id');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('fut_contrarian_order_book');
        Schema::dropIfExists('fut_contrarian_orders');
        Schema::dropIfExists('fut_contrarian_configs');
    }
};
