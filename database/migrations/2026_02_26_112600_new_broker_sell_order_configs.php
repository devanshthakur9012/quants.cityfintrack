<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('broker_sell_order_configs', function (Blueprint $table) {
            $table->enum('price_type', ['AVG', 'LTP'])->default('AVG')->after('symbol_type');
        });
    }

    public function down(): void
    {
        Schema::table('broker_sell_order_configs', function (Blueprint $table) {
            $table->dropColumn('price_type');
        });
    }
};
