<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('zerodha_auto_orders', function (Blueprint $table) {
            // Drop old columns if they exist
            if (Schema::hasColumn('zerodha_auto_orders', 'future_symbol')) {
                $table->dropColumn(['future_symbol', 'future_token']);
            }
            
            // Add correct columns matching symbol_data
            if (!Schema::hasColumn('zerodha_auto_orders', 'symbol')) {
                $table->string('symbol')->after('broker_api_id'); // ADANIPORTS
            }
            if (!Schema::hasColumn('zerodha_auto_orders', 'trading_symbol')) {
                $table->string('trading_symbol')->after('symbol'); // ADANIPORTS26JANFUT
            }
            if (!Schema::hasColumn('zerodha_auto_orders', 'instrument_token')) {
                $table->string('instrument_token')->after('trading_symbol');
            }
        });
    }

    public function down()
    {
        Schema::table('zerodha_auto_orders', function (Blueprint $table) {
            $table->dropColumn(['symbol', 'trading_symbol', 'instrument_token']);
            $table->string('future_symbol')->after('broker_api_id');
            $table->string('future_token')->after('future_symbol');
        });
    }
};
