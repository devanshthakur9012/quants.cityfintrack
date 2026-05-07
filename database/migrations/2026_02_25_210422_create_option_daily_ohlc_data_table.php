<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up(): void
    {
        Schema::create('option_daily_ohlc_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broker_api_id');
            $table->date('trade_date');
            $table->string('trading_symbol');
            $table->string('base_symbol');
            $table->string('future_symbol');            // FUT trading symbol for reference
            $table->decimal('future_price', 14, 2);     // FUT close price (carry‑forward)
            $table->decimal('atm_strike', 14, 2);
            $table->enum('instrument_type', ['FUT', 'CE', 'PE']);
            $table->decimal('strike', 14, 2)->nullable();
            $table->bigInteger('instrument_token');
            $table->decimal('open', 14, 2);
            $table->decimal('high', 14, 2);
            $table->decimal('low', 14, 2);
            $table->decimal('close', 14, 2);
            $table->bigInteger('volume');
            $table->bigInteger('oi');                   // open interest
            $table->string('strike_position', 10);      // ATM, ATM+1, ATM-1, N/A
            $table->date('expiry_date');
            $table->boolean('is_missing')->default(0);
            $table->timestamps();

            $table->unique(['broker_api_id', 'trade_date', 'trading_symbol'], 'daily_unique_key');
            $table->index('trade_date');
            $table->index('base_symbol');
            $table->index('expiry_date');

            $table->foreign('broker_api_id')
                  ->references('id')
                  ->on('broker_apis')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_daily_ohlc_data');
    }
};
