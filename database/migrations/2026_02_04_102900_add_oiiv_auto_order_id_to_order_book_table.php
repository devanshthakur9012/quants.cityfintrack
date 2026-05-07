<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('order_books', function (Blueprint $table) {
            $table->unsignedBigInteger('oiiv_auto_order_id')->nullable()->after('order_datetime');
            
            $table->foreign('oiiv_auto_order_id')
                  ->references('id')
                  ->on('oiiv_auto_orders')
                  ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('order_books', function (Blueprint $table) {
            $table->dropForeign(['oiiv_auto_order_id']);
            $table->dropColumn('oiiv_auto_order_id');
        });
    }
};
