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
        Schema::create('stock_data', function (Blueprint $table) {
            $table->id();
            $table->string('trading_symbol', 50)->index(); // AXISBANK
            $table->string('exchange', 10)->default('NSE'); // NSE, BSE
            $table->bigInteger('instrument_token')->index();
            $table->string('interval', 20); // 15minute, 30minute, day
            $table->datetime('timestamp')->index(); // Candle timestamp
            $table->decimal('open', 10, 2);
            $table->decimal('high', 10, 2);
            $table->decimal('low', 10, 2);
            $table->decimal('close', 10, 2);
            $table->bigInteger('volume')->default(0);
            $table->integer('oi')->default(0)->nullable(); // Open Interest (for F&O)
            $table->timestamps();

            // Composite unique index to prevent duplicate entries
            $table->unique(['trading_symbol', 'exchange', 'interval', 'timestamp'], 'stock_data_unique');
            
            // Index for faster queries
            $table->index(['trading_symbol', 'timestamp']);
            $table->index(['trading_symbol', 'interval', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_data');
    }
};
