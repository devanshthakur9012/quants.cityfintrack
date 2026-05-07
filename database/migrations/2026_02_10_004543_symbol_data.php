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
            // Event-based signal (only on Supertrend flip)
            $table->string('supertrend_event_signal', 10)
                ->nullable()
                ->after('supertrend_signal')
                ->comment('BUY/SELL only when Supertrend flips + MA condition met');

            // Current trading position (ongoing state)
            $table->string('trade_position', 10)
                ->nullable()
                ->after('supertrend_event_signal')
                ->comment('LONG/SHORT/FLAT - derived from event signals');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('symbol_data', function (Blueprint $table) {
            $table->dropColumn(['supertrend_event_signal', 'trade_position']);
        });
    }
};
