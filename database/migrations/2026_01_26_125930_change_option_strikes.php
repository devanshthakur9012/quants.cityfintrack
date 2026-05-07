<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add daily OI summary columns to option_strikes table
     * This allows storing: 1 FUT row + 1 CE row + 1 PE row per day per symbol
     */
    public function up(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            // Add trading_date to store which day this data belongs to
            $table->date('trading_date')->nullable()->after('expiry_date');
            
            // Daily OI metrics
            $table->bigInteger('daily_oi')->default(0)->after('lot_size');
            $table->bigInteger('daily_oi_prev')->default(0)->after('daily_oi');
            $table->bigInteger('daily_oi_change')->default(0)->after('daily_oi_prev');
            $table->decimal('daily_oi_change_pct', 10, 2)->default(0)->after('daily_oi_change');
            
            // Direction & Strength analysis
            $table->string('direction', 20)->nullable()->after('daily_oi_change_pct'); // BUILDUP/UNWINDING/NEUTRAL or BULLISH/BEARISH
            $table->string('strength', 20)->nullable()->after('direction'); // WEAK/MODERATE/STRONG/VERY_STRONG
            
            // Price at which ATM was calculated (for reference)
            $table->decimal('spot_price', 12, 2)->nullable()->after('strength');
            
            // Market bias (only for summary view)
            $table->string('market_bias', 50)->nullable()->after('spot_price');
            
            // Modify strike_position to support 'FUT', 'CE_MERGED', 'PE_MERGED'
            // Existing: ATM-1, ATM, ATM+1
            // New: FUT, CE_MERGED, PE_MERGED
            
            // Add index for daily queries
            $table->index(['underlying_symbol', 'trading_date', 'strike_position'], 'idx_daily_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->dropIndex('idx_daily_summary');
            $table->dropColumn([
                'trading_date',
                'daily_oi',
                'daily_oi_prev',
                'daily_oi_change',
                'daily_oi_change_pct',
                'direction',
                'strength',
                'spot_price',
                'market_bias'
            ]);
        });
    }
};
