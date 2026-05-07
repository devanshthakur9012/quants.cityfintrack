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
        Schema::create('option_price_cache', function (Blueprint $table) {
            $table->id();
            $table->string('trading_symbol');
            $table->integer('instrument_token');
            $table->dateTime('price_datetime');
            $table->decimal('price', 10, 2);
            $table->timestamp('cached_at');
            $table->timestamps();
            
            $table->unique(['trading_symbol', 'price_datetime'], 'symbol_datetime_unique');
            $table->index('instrument_token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('option_price_cache');
    }
};
