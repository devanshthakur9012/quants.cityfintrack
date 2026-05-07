<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->integer('strong_ce_quantity')->default(0)->after('stock_pe_quantity');
            $table->integer('strong_pe_quantity')->default(0)->after('strong_ce_quantity');
        });
    }

    public function down()
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->dropColumn(['strong_ce_quantity', 'strong_pe_quantity']);
        });
    }
};
