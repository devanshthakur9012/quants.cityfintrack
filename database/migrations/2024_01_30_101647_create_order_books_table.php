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
        Schema::create('order_books', function (Blueprint $table) {
            $table->id();
            $table->string('broker_username',155);
            $table->string('order_id',155);
            $table->string('status',155);
            $table->string('trading_symbol',155);
            $table->string('order_type',155);
            $table->string('transaction_type',155);
            $table->string('product',155);
            $table->decimal('price',8,2);
            $table->string('quantity',20);
            $table->string('status_message',500);
            $table->dateTime('order_datetime');
            $table->integer('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_books');
    }
};
