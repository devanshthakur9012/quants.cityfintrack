<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── fut_ohl_auto_configs ──────────────────────────────────────────────
        Schema::create('fut_ohl_auto_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');

            // Signal detection settings
            $table->decimal('tolerance', 6, 2)->default(1.00); // Open=High/Low tolerance in points
            $table->enum('signal_mode', ['align', 'opposite'])->default('align');
            // align   → Open=High BUY PE, Open=Low BUY CE (default)
            // opposite→ Open=High BUY CE, Open=Low BUY PE (reverse)

            // Option series
            $table->enum('option_series', ['current', 'next'])->default('current');

            // Order execution settings
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('MIS');
            $table->decimal('disc_ltp', 5, 2)->default(0); // discount % below LTP for LIMIT

            // Quantities — separate for CE (Open=Low) and PE (Open=High)
            $table->integer('ce_quantity')->default(0); // lots to buy when Open=Low  signal
            $table->integer('pe_quantity')->default(0); // lots to buy when Open=High signal

            // Status
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            $table->index(['user_id', 'status']);
        });

        // ── fut_ohl_auto_orders ───────────────────────────────────────────────
        Schema::create('fut_ohl_auto_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');

            // Symbol details
            $table->string('symbol', 30);                  // e.g. NIFTY, BANKNIFTY
            $table->string('trading_symbol', 60)->nullable(); // FUT trading_symbol used for price
            $table->string('series_expiry', 12)->nullable();  // option expiry selected

            // Signal details
            $table->string('signal_type', 20)->nullable();    // OPEN=HIGH | OPEN=LOW
            $table->string('trade_action', 10)->nullable();   // BUY CE | BUY PE
            $table->decimal('tolerance_used', 6, 2)->default(1);

            // 9:15 candle values (stored for audit)
            $table->decimal('open_price', 12, 2)->default(0);
            $table->decimal('high_915',   12, 2)->default(0);
            $table->decimal('low_915',    12, 2)->default(0);
            $table->decimal('spot_price', 12, 2)->default(0); // FUT close at signal time

            $table->dateTime('signal_detected_at')->nullable();
            $table->date('signal_date')->nullable();           // trade date of the 9:15 candle

            // Option selected
            $table->string('option_symbol', 60)->nullable();
            $table->unsignedBigInteger('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE'])->nullable();
            $table->decimal('strike_price', 12, 2)->nullable();

            // Execution
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('MIS');
            $table->integer('quantity')->default(0);
            $table->decimal('entry_price', 12, 2)->default(0);  // LTP at order time
            $table->decimal('current_price', 12, 2)->default(0);

            // Status
            $table->boolean('is_order_placed')->default(false);
            $table->dateTime('order_placed_at')->nullable();
            $table->boolean('status')->default(true);
            $table->string('zerodha_order_id', 30)->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'signal_date']);
            $table->index(['config_id', 'symbol']);
            $table->index(['symbol', 'option_type']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('fut_ohl_auto_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fut_ohl_auto_orders');
        Schema::dropIfExists('fut_ohl_auto_configs');
    }
};
