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
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->enum('option_filter', ['CE', 'PE', 'BOTH'])
                  ->default('BOTH')
                  ->after('option_series')
                  ->comment('Filter which option types to trade: CE only, PE only, or BOTH');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->dropColumn('option_filter');
        });
    }
};
