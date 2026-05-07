<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add allowed_symbols (JSON) to oiiv_auto_configs.
     *
     * NULL  = no filter → trade ALL available symbols (existing behaviour).
     * []    = empty array → trade nothing (effectively paused by symbol list).
     * ["RELIANCE","HDFCBANK",...] = only trade these symbols.
     */
    public function up(): void
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->json('allowed_symbols')
                  ->nullable()
                  ->after('config_type')
                  ->comment('NULL = all symbols | JSON array of base_symbol strings to whitelist');
        });
    }
 
    public function down(): void
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->dropColumn('allowed_symbols');
        });
    }
};
