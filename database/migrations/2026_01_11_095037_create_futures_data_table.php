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
                Schema::create('futures_data', function (Blueprint $table) {
            $table->id();
            $table->string('trading_symbol')->index();
            $table->string('exchange')->default('NFO');
            $table->string('instrument_token');
            $table->string('interval')->default('15minute');
            $table->timestamp('timestamp')->index();
            $table->decimal('open', 10, 2);
            $table->decimal('high', 10, 2);
            $table->decimal('low', 10, 2);
            $table->decimal('close', 10, 2);
            $table->bigInteger('volume')->default(0);
            $table->bigInteger('oi')->default(0);
            
            // Supertrend columns
            $table->decimal('atr', 10, 4)->nullable();
            $table->decimal('supertrend', 10, 2)->nullable();
            $table->string('supertrend_direction')->nullable(); // UP, DOWN
            $table->string('supertrend_signal')->nullable(); // BUY, SELL, HOLD
            $table->decimal('upper_band', 10, 2)->nullable();
            $table->decimal('lower_band', 10, 2)->nullable();
            
            $table->timestamps();

            $table->unique(['trading_symbol', 'exchange', 'interval', 'timestamp'], 'futures_unique_candle');
            $table->index(['trading_symbol', 'timestamp']);
            $table->index(['supertrend_signal', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('futures_data');
    }
};
