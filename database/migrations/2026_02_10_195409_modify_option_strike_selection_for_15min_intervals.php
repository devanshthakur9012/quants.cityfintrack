<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('option_strike_selections', function (Blueprint $table) {
            // Add timestamp field for 15-min intervals
            $table->timestamp('interval_time')->nullable()->after('trade_date');
            
            // Add index for faster queries
            $table->index(['trade_date', 'interval_time', 'future_symbol', 'option_series'], 'strike_selection_lookup');
            
            // Make the unique constraint include interval_time
            // $table->dropUnique(['trade_date', 'future_symbol', 'option_series']);
            $table->unique(['trade_date', 'interval_time', 'future_symbol', 'option_series'], 'strike_unique_interval');
        });
    }

    public function down()
    {
        Schema::table('option_strike_selections', function (Blueprint $table) {
            $table->dropIndex('strike_selection_lookup');
            // $table->dropUnique('strike_unique_interval');
            $table->unique(['trade_date', 'future_symbol', 'option_series']);
            $table->dropColumn('interval_time');
        });
    }
};
