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
        Schema::table('option_price_cache', function (Blueprint $table) {
            // Add composite index for faster lookups
            $table->index(['trading_symbol', 'price_datetime'], 'idx_symbol_datetime');
            
            // Add index on cached_at for cleanup queries
            $table->index('cached_at', 'idx_cached_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('option_price_cache', function (Blueprint $table) {
            $table->dropIndex('idx_symbol_datetime');
            $table->dropIndex('idx_cached_at');
        });
    }
};
