<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cp_multi_time_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('cp_multi_time_order_config_id');
            $table->foreignId('broker_api_id');
            $table->enum('broker_type', ['Zerodha', 'AngelOne']);
            $table->string('symbol');
            $table->string('option_symbol')->nullable();
            $table->string('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike', 12, 2)->nullable();
            $table->date('signal_date');
            $table->string('signal_time', 8); // '10:15' / '11:15' / '12:15'
            $table->string('signal_action');
            $table->string('transaction_type')->default('BUY');
            $table->string('order_type');
            $table->string('product');
            $table->unsignedTinyInteger('lots');
            $table->integer('quantity')->default(0);
            $table->decimal('order_price', 12, 2)->nullable();
            $table->string('broker_order_id')->nullable();
            $table->string('broker_status')->nullable();
            $table->boolean('is_order_placed')->default(false);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('order_placed_at')->nullable();
            $table->timestamps();

            $table->index(['cp_multi_time_order_config_id', 'symbol', 'signal_date', 'option_type', 'signal_time'], 'cp_mt_orders_dedupe_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_multi_time_orders');
    }
};