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
        Schema::create('instrument_15min_data', function (Blueprint $table) {
            $table->id();
            $table->string('underlying', 50)->index();
            $table->string('symbol', 100);
            $table->string('token', 50)->index();
            $table->enum('type', ['FUT', 'CE', 'PE'])->index();
            
            // Time information
            $table->date('data_date')->index();
            $table->dateTime('candle_time')->index(); // 15-min interval timestamp
            
            // OHLCV data
            $table->decimal('open', 12, 2)->nullable();
            $table->decimal('high', 12, 2)->nullable();
            $table->decimal('low', 12, 2)->nullable();
            $table->decimal('close', 12, 2)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->bigInteger('oi')->nullable();
            
            // Strike information
            $table->decimal('strike_price', 10, 2)->nullable();
            $table->integer('strike_position')->nullable()->comment('-3 to +3, 0 = ATM');
            
            $table->timestamps();
            
            // Composite unique index
            $table->unique(['token', 'candle_time'], 'token_candle_unique');
            
            // Additional indexes for faster queries
            $table->index(['underlying', 'data_date', 'type']);
            $table->index(['data_date', 'candle_time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('instrument15_min_data');
    }
};
