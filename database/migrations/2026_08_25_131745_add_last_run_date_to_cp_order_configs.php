<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('cp_order_configs', function (Blueprint $table) {
            // Set to today's date the moment this config has been processed
            // for today, so the cron (which runs every minute) never fires
            // the same config twice in one trading day even if it overlaps
            // the trigger minute more than once.
            $table->date('last_run_date')->nullable()->after('status');
        });
    }
 
    public function down(): void
    {
        Schema::table('cp_order_configs', function (Blueprint $table) {
            $table->dropColumn('last_run_date');
        });
    }
};