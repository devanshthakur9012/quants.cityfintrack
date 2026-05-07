<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_pivot_order_configs', function (Blueprint $table) {
            // JSON array of selected symbols e.g. ["NIFTY","BANKNIFTY"]
            // NULL or empty array = no symbols selected = no orders placed
            $table->json('symbols')->nullable()->after('broker_api_id')
                  ->comment('Selected symbols for this config. Orders only placed for listed symbols.');
        });
    }

    public function down(): void
    {
        Schema::table('new_pivot_order_configs', function (Blueprint $table) {
            $table->dropColumn('symbols');
        });
    }
};
