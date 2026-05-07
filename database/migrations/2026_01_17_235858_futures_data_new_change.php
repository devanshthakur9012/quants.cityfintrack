<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('futures_data', function (Blueprint $table) {
            // RSI fields
            $table->decimal('rsi', 8, 2)->nullable()->after('donchian_target');
            $table->string('rsi_signal', 20)->nullable()->after('rsi')
                  ->comment('OVERBOUGHT, OVERSOLD, NEUTRAL');
            
            // MACD fields
            $table->decimal('macd_line', 10, 4)->nullable()->after('rsi_signal');
            $table->decimal('macd_signal_line', 10, 4)->nullable()->after('macd_line');
            $table->decimal('macd_histogram', 10, 4)->nullable()->after('macd_signal_line');
            $table->string('macd_signal', 20)->nullable()->after('macd_histogram')
                  ->comment('BUY, SELL, HOLD');
            
            // Add index for better query performance
            $table->index(['trading_symbol', 'interval', 'timestamp'], 'idx_symbol_interval_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('futures_data', function (Blueprint $table) {
            $table->dropIndex('idx_symbol_interval_time');
            
            $table->dropColumn([
                'rsi',
                'rsi_signal',
                'macd_line',
                'macd_signal_line',
                'macd_histogram',
                'macd_signal',
                'donchian_middle'
            ]);
        });
    }
};
