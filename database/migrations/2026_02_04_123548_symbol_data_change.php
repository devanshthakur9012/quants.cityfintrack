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
        Schema::table('symbol_data', function (Blueprint $table) {
            // Previous OI for comparison
            $table->bigInteger('previous_oi')->nullable()->after('oi');
            
            // OI change metrics
            $table->bigInteger('oi_change')->nullable()->after('previous_oi')->comment('Change in OI from previous candle');
            $table->decimal('oi_change_percent', 10, 2)->nullable()->after('oi_change')->comment('Percentage change in OI');
            
            // OI Signal
            $table->enum('oi_signal', ['BULLISH', 'BEARISH', 'NEUTRAL'])->default('NEUTRAL')->after('oi_change_percent');
            
            // Add index for better query performance
            $table->index(['broker_api_id', 'symbol', 'interval', 'timestamp'], 'idx_oi_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('symbol_data', function (Blueprint $table) {
            $table->dropIndex('idx_oi_lookup');
            $table->dropColumn([
                'previous_oi',
                'oi_change',
                'oi_change_percent',
                'oi_signal'
            ]);
        });
    }
};
