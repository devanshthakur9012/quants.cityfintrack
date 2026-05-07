<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->boolean('enable_quality_filter')->default(true)->after('status');
        });
    }

    public function down()
    {
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->dropColumn('enable_quality_filter');
        });
    }
};
