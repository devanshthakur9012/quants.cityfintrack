<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::create('new_pivot_order_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');

            // Which symbols to trade
            $table->enum('symbols', ['BOTH', 'NIFTY', 'BANKNIFTY'])->default('BOTH');

            // Which option type to trade
            $table->enum('option_type', ['BOTH', 'CE', 'PE'])->default('BOTH');

            // Order settings
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('NRML');

            // Discount on LTP — positive = above LTP, negative = below LTP
            $table->enum('discount_direction', ['positive', 'negative'])->default('negative');
            $table->decimal('discount_pct', 5, 2)->default(0.00)->comment('% above or below LTP');

            // Quantities — 0 means skip that level
            // S1 → BUY order
            $table->unsignedInteger('s1_ce_quantity')->default(0)->comment('BUY CE @ S1');
            $table->unsignedInteger('s1_pe_quantity')->default(0)->comment('BUY PE @ S1');

            // R1 → SELL order
            $table->unsignedInteger('r1_ce_quantity')->default(0)->comment('SELL CE @ R1');
            $table->unsignedInteger('r1_pe_quantity')->default(0)->comment('SELL PE @ R1');

            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });

        Schema::create('new_pivot_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');

            $table->string('symbol');                        // NIFTY / BANKNIFTY
            $table->string('option_symbol')->nullable();     // e.g. NIFTY26MAR24000CE
            $table->unsignedBigInteger('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike_price', 10, 2)->nullable();

            $table->enum('trigger_level', ['S1', 'R1']);     // S1=BUY, R1=SELL
            $table->enum('transaction_type', ['BUY', 'SELL']);

            $table->decimal('level_price', 10, 2)->nullable(); // actual S1 or R1 value
            $table->decimal('entry_price', 10, 2)->nullable();  // LTP at order time
            $table->decimal('order_price', 10, 2)->nullable();  // final price sent to broker

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
