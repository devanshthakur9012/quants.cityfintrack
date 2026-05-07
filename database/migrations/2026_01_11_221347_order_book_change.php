<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('order_books', function (Blueprint $table) {
            $table->unsignedBigInteger('zerodha_auto_order_id')->nullable()->after('id');
            $table->foreign('zerodha_auto_order_id')->references('id')->on('zerodha_auto_orders')->onDelete('set null');
            $table->index('zerodha_auto_order_id');
        });
    }

    public function down()
    {
        Schema::table('order_books', function (Blueprint $table) {
            $table->dropForeign(['zerodha_auto_order_id']);
            $table->dropColumn('zerodha_auto_order_id');
        });
    }
};