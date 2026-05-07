<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::table('symbol_data', function (Blueprint $table) {
            // Add 50 MA column for Supertrend+MA strategy
            $table->decimal('ma50', 10, 2)->nullable()->after('supertrend_direction')
                ->comment('50-period Moving Average for signal filtering');
            
            // Add index for better performance
            $table->index('ma50');
        });
    }

    public function down(): void
    {
        Schema::table('symbol_data', function (Blueprint $table) {
            $table->dropIndex(['ma50']);
            $table->dropColumn('ma50');
        });
    }
};
