<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->string('config_type', 10)->default('eod')->after('status');
        });

        // ✅ Mark all existing records as 'eod' so nothing breaks
        DB::table('oiiv_auto_configs')->whereNull('config_type')->update(['config_type' => 'eod']);
        DB::table('oiiv_auto_configs')->where('config_type', '')->update(['config_type' => 'eod']);
    }

    public function down()
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->dropColumn('config_type');
        });
    }
};
