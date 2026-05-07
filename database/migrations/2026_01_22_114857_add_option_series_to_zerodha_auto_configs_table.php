<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->enum('option_series', ['current', 'next'])->default('current')->after('signal_strategy');
        });
    }

    public function down()
    {
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->dropColumn('option_series');
        });
    }
};
