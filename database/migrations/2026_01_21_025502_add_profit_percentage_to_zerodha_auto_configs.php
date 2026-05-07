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
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->decimal('profit_percent', 5, 2)
                  ->after('disc_ltp')
                  ->default(5.00)
                  ->comment('Target profit % for auto SELL orders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->dropColumn('profit_percent');
        });
    }
};
