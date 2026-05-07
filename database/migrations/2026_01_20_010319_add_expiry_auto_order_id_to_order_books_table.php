<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('order_books', function (Blueprint $table) {
            $table->foreignId('expiry_auto_order_id')->nullable()
                ->after('zerodha_auto_order_id')
                ->constrained('expiry_auto_orders')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('order_books', function (Blueprint $table) {
            $table->dropForeign(['expiry_auto_order_id']);
            $table->dropColumn('expiry_auto_order_id');
        });
    }
};
