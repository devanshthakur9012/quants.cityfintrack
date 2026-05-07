<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcx_pivot_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');
            $table->string('symbol', 30);               // CRUDEOIL | CRUDEOILM | NATGAS
            $table->string('option_symbol', 60)->nullable();
            $table->unsignedBigInteger('option_token')->nullable();
            $table->string('option_type', 5);           // CE | PE
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->string('trigger_level', 5);         // S1 | R1
            $table->integer('layer_index')->default(1);
            $table->string('transaction_type', 5);      // BUY
            $table->decimal('raw_level_price', 12, 2);
            $table->decimal('order_price', 12, 2);
            $table->string('candle_time', 30)->nullable(); // "2026-03-03 09:00:00"
            $table->string('order_type', 10);
            $table->string('product', 10);
            $table->integer('quantity');
            $table->string('kite_order_id', 30)->nullable();
            $table->string('kite_status', 20)->nullable(); // OPEN | TEST | ERROR | DRY_RUN
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['config_id', 'symbol', 'candle_time'], 'mcx_ord_dedup');
            $table->index('user_id');
            $table->index('kite_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcx_pivot_orders');
    }
};
