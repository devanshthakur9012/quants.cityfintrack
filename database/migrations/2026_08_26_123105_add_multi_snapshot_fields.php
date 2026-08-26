<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('cp_order_configs', function (Blueprint $table) {
            $table->decimal('max_price_pct_of_underlying', 5, 2)->nullable()->after('quantity');
            $table->decimal('reentry_min_drop_pct', 5, 2)->nullable()->after('max_price_pct_of_underlying');
        });

        Schema::table('cp_orders', function (Blueprint $table) {
            $table->string('signal_time', 8)->nullable()->after('signal_action');
        });
    }

    public function down(): void
    {
        Schema::table('cp_order_configs', function (Blueprint $table) {
            $table->dropColumn(['max_price_pct_of_underlying', 'reentry_min_drop_pct']);
        });
        Schema::table('cp_orders', function (Blueprint $table) {
            $table->dropColumn('signal_time');
        });
    }
};