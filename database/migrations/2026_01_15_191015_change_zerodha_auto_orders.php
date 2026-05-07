<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zerodha_auto_orders', function (Blueprint $table) {
            $table->enum('signal_strategy', ['SUPERTREND', 'DONCHIAN', 'BOTH'])
                  ->default('BOTH')
                  ->after('signal_type');
        });
    }

    public function down()
    {
        Schema::table('zerodha_auto_orders', function (Blueprint $table) {
            $table->dropColumn('signal_strategy');
        });
    }
};