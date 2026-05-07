<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{/**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            // PE/CE Ratio Analysis columns
            $table->decimal('pe_ce_ratio', 10, 2)->nullable()->after('btst_reason')
                ->comment('PE OI / CE OI Ratio');
            
            $table->string('oi_interpretation', 50)->nullable()->after('pe_ce_ratio')
                ->comment('Put Writing / Call Writing / Balanced OI');
            
            $table->string('options_sentiment', 50)->nullable()->after('oi_interpretation')
                ->comment('Bullish / Bearish / Neutral based on PE/CE ratio');
            
            $table->string('futures_oi_view', 50)->nullable()->after('options_sentiment')
                ->comment('Strong Build-up / Position Unwinding / Normal');
            
            $table->string('final_sentiment', 50)->nullable()->after('futures_oi_view')
                ->comment('Strong Bullish / Bullish / Strong Bearish / Bearish / Neutral');
            
            $table->string('trade_action', 50)->nullable()->after('final_sentiment')
                ->comment('BUY CE / BUY PE / BOTH CE AND PE');
            
            // Add index for faster queries
            $table->index(['underlying_symbol', 'trading_date', 'final_sentiment'], 'idx_symbol_date_sentiment');
            $table->index(['trade_action', 'trading_date'], 'idx_trade_action_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_symbol_date_sentiment');
            $table->dropIndex('idx_trade_action_date');
            
            // Drop columns
            $table->dropColumn([
                'pe_ce_ratio',
                'oi_interpretation',
                'options_sentiment',
                'futures_oi_view',
                'final_sentiment',
                'trade_action'
            ]);
        });
    }
};
