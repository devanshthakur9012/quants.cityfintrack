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
            // Add new CE/PE OI analysis fields
            $table->string('oi_condition')->nullable()->after('oi_interpretation');
            $table->decimal('ce_oi_change_pct', 10, 2)->nullable()->after('oi_condition');
            $table->decimal('pe_oi_change_pct', 10, 2)->nullable()->after('ce_oi_change_pct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->dropColumn(['oi_condition', 'ce_oi_change_pct', 'pe_oi_change_pct']);
        });
    }
};
