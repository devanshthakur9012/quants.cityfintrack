<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('cp_order_configs', function (Blueprint $table) {
            $table->timestamp('last_run_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cp_order_configs', function (Blueprint $table) {
            $table->dropColumn('last_run_at');
            $table->dropColumn('last_run_date');
        });
    }
};