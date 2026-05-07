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

            $table->enum('symbols', ['BOTH', 'NIFTY', 'BANKNIFTY'])->default('BOTH');
            $table->enum('option_type', ['BOTH', 'CE', 'PE'])->default('BOTH');
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('NRML');

            // S1 discount — applied directly on S1 pivot price (NOT on LTP)
            $table->enum('s1_discount_direction', ['positive', 'negative'])->default('negative')
                ->comment('positive = add % to S1, negative = subtract % from S1');
            $table->decimal('s1_discount_pct', 5, 2)->default(0.00)
                ->comment('% to add/subtract from S1 price to get final order price');

            // R1 discount — applied directly on R1 pivot price (NOT on LTP)
            $table->enum('r1_discount_direction', ['positive', 'negative'])->default('positive')
                ->comment('positive = add % to R1, negative = subtract % from R1');
            $table->decimal('r1_discount_pct', 5, 2)->default(0.00)
                ->comment('% to add/subtract from R1 price to get final order price');

            // Quantities — 0 = skip that order
            $table->unsignedInteger('s1_ce_quantity')->default(0)->comment('BUY CE lots @ S1');
            $table->unsignedInteger('s1_pe_quantity')->default(0)->comment('BUY PE lots @ S1');
            $table->unsignedInteger('r1_ce_quantity')->default(0)->comment('SELL CE lots @ R1');
            $table->unsignedInteger('r1_pe_quantity')->default(0)->comment('SELL PE lots @ R1');

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

            $table->string('symbol');                           // NIFTY / BANKNIFTY
            $table->string('option_symbol')->nullable();        // e.g. NIFTY26MAR24000CE
            $table->unsignedBigInteger('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike_price', 10, 2)->nullable();

            $table->enum('trigger_level', ['S1', 'R1']);
            $table->enum('transaction_type', ['BUY', 'SELL']);

            $table->decimal('raw_level_price', 10, 2)->nullable()
                ->comment('S1 or R1 value before discount');
            $table->decimal('order_price', 10, 2)->nullable()
                ->comment('Final price sent to broker = raw_level_price ± discount');

            $table->string('candle_time', 5)->nullable()
                ->comment('HH:MM of the 15-min candle that was used — for dedup');

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
