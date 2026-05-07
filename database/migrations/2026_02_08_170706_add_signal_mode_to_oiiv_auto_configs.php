<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up()
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->enum('signal_mode', ['align', 'opposite'])
                ->default('align')
                ->after('status')
                ->comment('align = Follow system signal, opposite = Trade opposite signal');
        });
    }

    public function down()
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->dropColumn('signal_mode');
        });
    }
};
