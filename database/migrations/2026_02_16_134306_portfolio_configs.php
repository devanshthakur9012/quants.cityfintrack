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
        Schema::table('portfolio_configs', function (Blueprint $table) {
            // Normal Sell Order Config (for market hours)
            $table->decimal('old_position_sell_profit_percent', 8, 2)->default(20.00)->after('fresh_position_profit_percent');
            $table->decimal('fresh_position_sell_profit_percent', 8, 2)->default(10.00)->after('old_position_sell_profit_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_configs', function (Blueprint $table) {
            $table->dropColumn(['old_position_sell_profit_percent', 'fresh_position_sell_profit_percent']);
        });
    }
};
