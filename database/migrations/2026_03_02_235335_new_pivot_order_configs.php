<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        // ── new_pivot_order_configs ───────────────────────────────────────────
        Schema::create('new_pivot_order_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');

            $table->string('symbols')->default('BOTH')
                ->comment('BOTH | NIFTY | BANKNIFTY | comma-separated e.g. NIFTY,FINNIFTY');
            $table->enum('option_type', ['BOTH', 'CE', 'PE'])->default('BOTH');
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('NRML');

            // Layer-wise S1 BUY orders
            // JSON array of: [{discount_direction, discount_pct, ce_quantity, pe_quantity}, ...]
            $table->json('s1_layers')->nullable()
                ->comment('S1 BUY layers — each layer places a separate BUY order at different discount depth');

            // Layer-wise R1 SELL orders
            $table->json('r1_layers')->nullable()
                ->comment('R1 SELL layers — each layer places a separate SELL order at different discount depth');

            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });

        // ── new_pivot_orders ──────────────────────────────────────────────────
        Schema::create('new_pivot_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');

            $table->string('symbol')
                ->comment('e.g. NIFTY, BANKNIFTY, FINNIFTY');
            $table->string('option_symbol')->nullable()
                ->comment('e.g. NIFTY26MAR24000CE');
            $table->unsignedBigInteger('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike_price', 10, 2)->nullable();

            $table->enum('trigger_level', ['S1', 'R1']);
            $table->tinyInteger('layer_index')->default(1)
                ->comment('Which layer fired this order — 1, 2, 3 ...');
            $table->enum('transaction_type', ['BUY', 'SELL']);

            $table->decimal('raw_level_price', 10, 2)->nullable()
                ->comment('S1 or R1 value before discount');
            $table->decimal('order_price', 10, 2)->nullable()
                ->comment('Final price sent to broker = raw_level_price ± discount');

            $table->string('candle_time', 5)->nullable()
                ->comment('HH:MM of the 30-min candle used for this order — used for dedup');

            $table->enum('order_type', ['LIMIT', 'MARKET']);
            $table->enum('product', ['NRML', 'MIS']);
            $table->unsignedInteger('quantity');

            $table->string('kite_order_id')->nullable();
            $table->string('kite_status')->nullable();
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('new_pivot_order_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_pivot_orders');
        Schema::dropIfExists('new_pivot_order_configs');
    }
};
