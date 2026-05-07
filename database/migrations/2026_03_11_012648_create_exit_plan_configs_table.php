<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_plan_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');

            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('MARKET');
            $table->enum('product', ['NRML', 'MIS'])->default('NRML');
            $table->decimal('disc_ltp', 5, 2)->default(0)->comment('Discount % from LTP for LIMIT orders');

            $table->enum('signal_mode', ['align', 'opposite'])->default('align')
                  ->comment('align = place SELL on EXIT decision | opposite = place SELL on HOLD decision');

            // Index instruments (NIFTY, BANKNIFTY, FINNIFTY, MIDCPNIFTY, SENSEX, BANKEX)
            $table->integer('index_ce_quantity')->default(0);
            $table->integer('index_pe_quantity')->default(0);

            // Stock instruments (all others)
            $table->integer('stock_ce_quantity')->default(0);
            $table->integer('stock_pe_quantity')->default(0);

            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_plan_configs');
    }
};
