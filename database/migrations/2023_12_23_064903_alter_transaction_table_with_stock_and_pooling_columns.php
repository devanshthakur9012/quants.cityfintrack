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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string("stock_name")->nullable();
            // add the foreign key with pooling account table.
            $table->unsignedBigInteger('pooling_account_id')->nullable();
            $table->foreign('pooling_account_id')->references('id')->on('pooling_account_portfolios');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['pooling_account_id']);
                $table->dropColumn('pooling_account_id');
                $table->dropColumn('stock_name');
        });
    }
};
