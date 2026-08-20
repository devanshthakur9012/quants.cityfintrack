<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cp_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cp_order_config_id')->constrained('cp_order_configs')->cascadeOnDelete();
            $table->foreignId('cp_analysis_id')->constrained('cp_analyses')->cascadeOnDelete();
            $table->foreignId('broker_api_id')->constrained('broker_apis')->cascadeOnDelete();

            $table->string('broker_type', 20);
            $table->string('symbol', 32);
            $table->string('option_symbol', 64)->nullable();
            $table->string('option_token', 32)->nullable();
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike', 12, 2)->nullable();

            $table->date('signal_date');
            $table->string('signal_action', 20);          // BUY_CE | BUY_PE (what the analysis said)
            $table->string('transaction_type', 10)->default('BUY');
            $table->string('order_type', 10);
            $table->string('product', 10);

            $table->unsignedInteger('lots');
            $table->unsignedInteger('quantity');           // lots × lot_size
            $table->decimal('order_price', 12, 2)->nullable();

            $table->string('broker_order_id')->nullable();
            $table->string('broker_status', 20)->default('PENDING'); // PENDING|OPEN|COMPLETE|ERROR
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();               // raw signal payload, for debugging

            $table->timestamps();

            // one order per config+symbol+day+side — this IS your idempotency guard
            $table->unique(['cp_order_config_id', 'symbol', 'signal_date', 'option_type'], 'cp_orders_idempotency');

            $table->index(['user_id', 'signal_date']);
            $table->index(['broker_status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cp_orders');
    }
};
