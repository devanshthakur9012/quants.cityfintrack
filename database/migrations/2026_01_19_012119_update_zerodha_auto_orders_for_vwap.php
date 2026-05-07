<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Update zerodha_auto_orders to use VWAP instead of Donchian
     */
    public function up(): void
    {
        Schema::table('zerodha_auto_orders', function (Blueprint $table) {
            // Drop Donchian column if exists
            if (Schema::hasColumn('zerodha_auto_orders', 'donchian_signal')) {
                $table->dropColumn('donchian_signal');
            }
            
            // Add VWAP signal column
            if (!Schema::hasColumn('zerodha_auto_orders', 'vwap_signal')) {
                $table->string('vwap_signal', 20)->nullable()->after('supertrend_signal');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zerodha_auto_orders', function (Blueprint $table) {
            // Restore Donchian column
            if (!Schema::hasColumn('zerodha_auto_orders', 'donchian_signal')) {
                $table->string('donchian_signal', 20)->nullable()->after('supertrend_signal');
            }
            
            // Drop VWAP column
            if (Schema::hasColumn('zerodha_auto_orders', 'vwap_signal')) {
                $table->dropColumn('vwap_signal');
            }
        });
    }
};
