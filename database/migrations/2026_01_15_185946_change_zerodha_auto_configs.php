<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {

            // 1. Add new strategy column
            $table->enum('signal_strategy', ['SUPERTREND', 'DONCHIAN', 'BOTH'])
                  ->default('BOTH')
                  ->after('product');

            // 2. Add new index_quantity column
            $table->integer('index_quantity')
                  ->default(1)
                  ->after('disc_ltp');

            // 3. Add stock_quantity
            $table->integer('stock_quantity')
                  ->default(1)
                  ->after('index_quantity');
        });

        // 4. Copy old quantity data → index_quantity
        DB::statement('UPDATE zerodha_auto_configs SET index_quantity = quantity');

        // 5. Drop old quantity column
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }

    public function down()
    {
        // 1. Restore quantity column
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->integer('quantity')->default(1)->after('disc_ltp');
        });

        // 2. Copy back data
        DB::statement('UPDATE zerodha_auto_configs SET quantity = index_quantity');

        // 3. Drop new columns
        Schema::table('zerodha_auto_configs', function (Blueprint $table) {
            $table->dropColumn(['index_quantity', 'stock_quantity', 'signal_strategy']);
        });
    }
};