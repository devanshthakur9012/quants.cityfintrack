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
        Schema::create('cp_order_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cp_analysis_id')->constrained('cp_analyses')->cascadeOnDelete();

            $table->enum('broker_type', ['Zerodha', 'AngelOne']);
            $table->foreignId('broker_api_id')->constrained('broker_apis')->cascadeOnDelete();

            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('MARKET');
            $table->enum('product', ['MIS', 'NRML'])->default('MIS');
            $table->decimal('disc_ltp', 5, 2)->default(0); // % off LTP, LIMIT orders only

            $table->enum('signal_mode', ['align', 'opposite'])->default('align');

            $table->unsignedInteger('ce_quantity')->default(0); // lots
            $table->unsignedInteger('pe_quantity')->default(0); // lots

            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index(['cp_analysis_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cp_order_configs');
    }
};
