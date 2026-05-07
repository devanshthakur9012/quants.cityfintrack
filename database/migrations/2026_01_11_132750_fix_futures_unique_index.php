<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop the problematic unique index if it exists
        try {
            DB::statement('ALTER TABLE futures_data DROP INDEX futures_unique_candle');
        } catch (\Exception $e) {
            // Index might not exist
        }

        // Add the correct unique index
        Schema::table('futures_data', function (Blueprint $table) {
            $table->unique(['trading_symbol', 'exchange', 'interval', 'timestamp'], 'futures_unique_candle');
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
            $table->dropUnique('futures_unique_candle');
        });
    }
};