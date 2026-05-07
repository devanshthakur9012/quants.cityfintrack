<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('indicator_configs', function (Blueprint $table) {
            // Add VWAP distance percent configuration
            $table->decimal('vwap_distance_percent', 5, 2)->default(0.4)->after('vwap_band_period');
        });
    }

    public function down()
    {
        Schema::table('indicator_configs', function (Blueprint $table) {
            $table->dropColumn('vwap_distance_percent');
        });
    }
};
