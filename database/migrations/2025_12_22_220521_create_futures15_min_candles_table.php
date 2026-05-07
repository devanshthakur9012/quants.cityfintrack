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
        Schema::create('futures_15min_candles', function (Blueprint $table) {
            $table->id();
            
            // Instrument reference
            $table->string('underlying', 50)->index();
            $table->string('symbol', 100)->index();
            $table->string('token', 50)->index();
            
            // Time data
            $table->date('data_date')->index();
            $table->dateTime('candle_time')->index();
            
            // OHLCV + OI
            $table->decimal('open', 10, 2)->nullable();
            $table->decimal('high', 10, 2)->nullable();
            $table->decimal('low', 10, 2)->nullable();
            $table->decimal('close', 10, 2)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('oi')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['underlying', 'data_date']);
            $table->index(['token', 'data_date']);
            $table->unique(['token', 'candle_time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('futures15_min_candles');
    }
};
