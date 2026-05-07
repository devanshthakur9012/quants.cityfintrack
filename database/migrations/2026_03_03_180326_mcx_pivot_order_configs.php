<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcx_pivot_order_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            $table->string('order_type', 10)->default('LIMIT'); // LIMIT | MARKET
            $table->string('product', 10)->default('NRML');     // NRML | MIS
            // Separate CE/PE layers for S1 and R1
            $table->json('s1_ce_layers')->nullable();
            $table->json('s1_pe_layers')->nullable();
            $table->json('r1_ce_layers')->nullable();
            $table->json('r1_pe_layers')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->index('user_id');
            $table->index('broker_api_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcx_pivot_order_configs');
    }
};
