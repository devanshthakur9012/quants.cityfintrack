<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cp_multi_time_order_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('broker_api_id');
            $table->enum('broker_type', ['Zerodha', 'AngelOne']);
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['MIS', 'NRML'])->default('MIS');
            $table->decimal('disc_ltp', 5, 2)->default(0);
            $table->enum('signal_mode', ['align', 'opposite'])->default('align');
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->decimal('max_price_pct_of_underlying', 5, 2)->nullable();
            $table->decimal('reentry_min_drop_pct', 5, 2)->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_multi_time_order_configs');
    }
};