<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nifty_driven_breakout_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');

            // ── Strategy params ────────────────────────────────────────────
            $table->decimal('threshold', 8, 2)->default(30); // NIFTY pts threshold
            $table->string('filter', 10)->default('BOTH');   // CE | PE | BOTH
            $table->string('signal_mode', 10)->default('align'); // align | opposite

            // ── Order settings ─────────────────────────────────────────────
            $table->string('order_type', 10)->default('LIMIT');  // LIMIT | MARKET
            $table->string('product', 10)->default('NRML');      // NRML | MIS
            $table->decimal('disc_ltp', 5, 2)->default(0);       // % discount on LTP for LIMIT

            // ── Investment mode ────────────────────────────────────────────
            // 'lots'       → fixed number of lots per trade
            // 'investment' → calculate lots from fixed rupee amount
            $table->string('quantity_mode', 15)->default('lots'); // lots | investment

            // ── Per-symbol quantities (lots mode) ─────────────────────────
            $table->integer('index_ce_quantity')->default(0);
            $table->integer('index_pe_quantity')->default(0);
            $table->integer('stock_ce_quantity')->default(0);
            $table->integer('stock_pe_quantity')->default(0);

            // ── Per-symbol investment (investment mode, in ₹) ─────────────
            $table->decimal('index_ce_investment', 12, 2)->default(0);
            $table->decimal('index_pe_investment', 12, 2)->default(0);
            $table->decimal('stock_ce_investment', 12, 2)->default(0);
            $table->decimal('stock_pe_investment', 12, 2)->default(0);

            // ── Stop-loss settings ─────────────────────────────────────────
            $table->boolean('enable_stoploss')->default(false);
            $table->string('stoploss_type', 10)->default('pct'); // pct | points
            $table->decimal('stoploss_value', 8, 2)->default(30); // e.g. 30% or 30 pts
            $table->string('stoploss_order_type', 10)->default('SL-M'); // SL | SL-M

            // ── Symbol filter ──────────────────────────────────────────────
            // Comma-separated base symbols, or empty = ALL
            $table->text('allowed_symbols')->nullable();

            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });

        Schema::create('nifty_driven_breakout_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');

            // ── Signal context ─────────────────────────────────────────────
            $table->date('signal_date');
            $table->string('symbol');              // base symbol e.g. NIFTY, BANKNIFTY
            $table->string('signal_type', 5);      // CE | PE
            $table->decimal('nifty_open', 10, 2);
            $table->decimal('nifty_trigger', 10, 2);
            $table->string('trigger_time', 10);    // H:i of NIFTY trigger candle
            $table->decimal('nifty_move', 10, 2);
            $table->decimal('threshold', 8, 2);

            // ── Option details ─────────────────────────────────────────────
            $table->string('option_symbol')->nullable();
            $table->unsignedBigInteger('option_token')->nullable();
            $table->string('option_type', 5);      // CE | PE (may differ from signal_type in opposite mode)
            $table->decimal('strike_price', 10, 2)->nullable();
            $table->string('expiry_date', 15)->nullable();

            // ── Trade prices ───────────────────────────────────────────────
            $table->decimal('entry_price', 10, 2)->default(0);   // LTP at order time
            $table->decimal('current_price', 10, 2)->default(0);
            $table->decimal('spot_price', 10, 2)->default(0);    // NIFTY spot at entry

            // ── Quantity & investment ──────────────────────────────────────
            $table->integer('lot_size')->default(0);
            $table->integer('quantity')->default(0);             // lots
            $table->decimal('investment', 12, 2)->default(0);   // ₹ invested

            // ── Stop-loss ──────────────────────────────────────────────────
            $table->boolean('stoploss_enabled')->default(false);
            $table->decimal('stoploss_price', 10, 2)->nullable();
            $table->string('stoploss_order_id')->nullable();
            $table->boolean('stoploss_placed')->default(false);

            // ── Order status ───────────────────────────────────────────────
            $table->string('order_type', 10)->default('LIMIT');
            $table->string('product', 10)->default('NRML');
            $table->string('kite_order_id')->nullable();
            $table->string('kite_order_status')->nullable();
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->timestamp('signal_detected_at')->nullable();
            $table->string('signal_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('nifty_driven_breakout_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');

            $table->index(['signal_date', 'symbol', 'signal_type'], 'ndb_orders_date_sym_type');
            $table->index(['config_id', 'is_order_placed'], 'ndb_orders_config_placed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nifty_driven_breakout_orders');
        Schema::dropIfExists('nifty_driven_breakout_configs');
    }
};