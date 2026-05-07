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
        Schema::table('futures_data', function (Blueprint $table) {
            // VWAP columns
            $table->decimal('vwap', 10, 4)->nullable()->after('macd_signal');
            $table->string('vwap_signal', 20)->default('HOLD')->after('vwap');
            $table->decimal('vwap_upper_band', 10, 4)->nullable()->after('vwap_signal');
            $table->decimal('vwap_lower_band', 10, 4)->nullable()->after('vwap_upper_band');
            
            // Add index for faster queries
            $table->index(['trading_symbol', 'interval', 'vwap_signal'], 'idx_vwap_signal');
        });

        Schema::table('indicator_configs', function (Blueprint $table) {
            // VWAP configuration
            $table->boolean('vwap_reset_daily')->default(true)->after('macd_signal_period')->comment('Reset VWAP at market open each day');
            $table->decimal('vwap_band_multiplier', 4, 2)->default(1.0)->after('vwap_reset_daily')->comment('Standard deviation multiplier for VWAP bands');
            $table->integer('vwap_band_period')->default(20)->after('vwap_band_multiplier')->comment('Period for calculating VWAP band standard deviation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('futures_data', function (Blueprint $table) {
            $table->dropIndex('idx_vwap_signal');
            $table->dropColumn([
                'vwap',
                'vwap_signal',
                'vwap_upper_band',
                'vwap_lower_band'
            ]);
        });

        Schema::table('indicator_configs', function (Blueprint $table) {
            $table->dropColumn([
                'vwap_reset_daily',
                'vwap_band_multiplier',
                'vwap_band_period'
            ]);
        });
    }
};
