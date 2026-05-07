<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            // Daily IV data (captured at 3:15 PM)
            $table->decimal('daily_iv', 10, 4)->nullable()->after('daily_oi_change_pct')
                  ->comment('Current day IV at 3:15 PM');
            
            $table->decimal('daily_iv_prev', 10, 4)->nullable()->after('daily_iv')
                  ->comment('Previous trading day IV at 3:15 PM');
            
            $table->decimal('daily_iv_change', 10, 4)->nullable()->after('daily_iv_prev')
                  ->comment('IV change from previous day');
            
            $table->decimal('daily_iv_change_pct', 8, 2)->nullable()->after('daily_iv_change')
                  ->comment('IV change % from previous day');
            
            // Index for faster queries
            $table->index(['underlying_symbol', 'trading_date', 'strike_position'], 'idx_symbol_date_position_iv');
        });
    }

    public function down()
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->dropIndex('idx_symbol_date_position_iv');
            $table->dropColumn(['daily_iv', 'daily_iv_prev', 'daily_iv_change', 'daily_iv_change_pct']);
        });
    }
};
