<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cp_analyses', function (Blueprint $table) {
            // e.g. '14:45:00' for OI Flow Sentiment, '10:30:00' for LICHSGFIN.
            // NULL = analysis exists but isn't wired into the auto-order cron yet.
            $table->time('trigger_time')->nullable()->after('route_name');
        });
    }
 
    public function down(): void
    {
        Schema::table('cp_analyses', function (Blueprint $table) {
            $table->dropColumn('trigger_time');
        });
    }
};