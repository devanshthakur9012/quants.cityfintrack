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
            // IV Signal columns (parallel to OI signal columns)
            $table->enum('iv_direction', ['BULLISH', 'BEARISH', 'NEUTRAL', 'N/A'])
                ->after('daily_iv_change_pct')
                ->nullable()
                ->comment('IV-based signal direction');
            
            $table->enum('iv_strength', ['WEAK', 'MODERATE', 'STRONG', 'VERY_STRONG', 'N/A'])
                ->after('iv_direction')
                ->nullable()
                ->comment('IV signal strength');
            
            // Final BTST signal (combination of OI + IV)
            $table->enum('btst_signal', [
                'BUY_CE',
                'BUY_PE', 
                'MODERATE_BUY_CE',
                'MODERATE_BUY_PE',
                'NO_TRADE'
            ])
                ->after('iv_strength')
                ->nullable()
                ->comment('Final BTST trading signal');
            
            $table->integer('btst_confidence')
                ->after('btst_signal')
                ->nullable()
                ->comment('BTST signal confidence (0-100)');
            
            $table->string('btst_reason', 500)
                ->after('btst_confidence')
                ->nullable()
                ->comment('Reason for BTST signal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->dropColumn([
                'iv_direction',
                'iv_strength',
                'btst_signal',
                'btst_confidence',
                'btst_reason'
            ]);
        });
    }
};
