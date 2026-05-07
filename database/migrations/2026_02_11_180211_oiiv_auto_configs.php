<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            // Add new CE/PE specific quantity columns
            $table->integer('index_ce_quantity')->default(0)->after('index_quantity');
            $table->integer('index_pe_quantity')->default(0)->after('index_ce_quantity');
            $table->integer('stock_ce_quantity')->default(0)->after('stock_quantity');
            $table->integer('stock_pe_quantity')->default(0)->after('stock_ce_quantity');
            
            // Keep old columns for backward compatibility (can be removed later)
            // $table->integer('index_quantity')->nullable()->change();
            // $table->integer('stock_quantity')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->dropColumn(['index_ce_quantity', 'index_pe_quantity', 'stock_ce_quantity', 'stock_pe_quantity']);
        });
    }
};
