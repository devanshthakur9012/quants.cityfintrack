<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds `option_series` to oiiv_auto_configs:
     *   'current' → use same expiry as FUT  (e.g. JAN FUT → JAN CE/PE)
     *   'next'    → skip to the next expiry  (e.g. JAN FUT → FEB CE/PE)
     */
    public function up(): void
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->enum('option_series', ['current', 'next'])
                  ->default('current')
                  ->after('signal_mode')
                  ->comment('current = same expiry as FUT; next = skip to next monthly expiry');
        });
    }

    public function down(): void
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->dropColumn('option_series');
        });
    }
};
