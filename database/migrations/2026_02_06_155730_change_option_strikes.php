<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->decimal('daily_close', 10, 2)->nullable()->after('daily_iv_change_pct');
            $table->decimal('daily_close_prev', 10, 2)->nullable()->after('daily_close');
            $table->decimal('daily_close_change', 10, 2)->nullable()->after('daily_close_prev');
            $table->decimal('daily_close_change_pct', 10, 2)->nullable()->after('daily_close_change');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->dropColumn([
                'daily_close',
                'daily_close_prev',
                'daily_close_change',
                'daily_close_change_pct'
            ]);
        });
    }
};
