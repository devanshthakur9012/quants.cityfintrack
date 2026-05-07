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
        Schema::create('zerodha_instruments', function (Blueprint $table) {
            $table->id();
            $table->string('instrument_token',155)->nullable();
            $table->string('exchange_token',155)->nullable();
            $table->string('trading_symbol',155)->nullable();
            $table->string('name',155)->nullable();
            $table->string('last_price',155)->nullable();
            $table->string('strike',155)->nullable();
            $table->string('expiry',155)->nullable();
            $table->string('tick_size',155)->nullable();
            $table->string('lot_size',155)->nullable();
            $table->string('instrument_type',155)->nullable();
            $table->string('segment',155)->nullable();
            $table->string('exchange',155)->nullable();
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
        Schema::dropIfExists('zerodha_instruments');
    }
};
