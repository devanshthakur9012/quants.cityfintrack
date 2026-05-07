<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('broker_sell_order_configs', function (Blueprint $table) {
            // What % of total quantity to sell (1–100, default 100)
            $table->unsignedTinyInteger('quantity_percent')
                  ->default(100)
                  ->after('price_type')
                  ->comment('Percentage of total quantity to sell (1-100)');

            // Filter: only sell PROFIT positions, LOSS positions, or BOTH
            // Determined by comparing AVG price vs LTP
            $table->enum('position_filter', ['PROFIT', 'LOSS', 'BOTH'])
                  ->default('PROFIT')
                  ->after('quantity_percent')
                  ->comment('PROFIT: LTP > AVG | LOSS: LTP < AVG | BOTH: all positions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broker_sell_order_configs', function (Blueprint $table) {
            $table->dropColumn(['quantity_percent', 'position_filter']);
        });
    }
};
