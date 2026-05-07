<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('30min_ohlc_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broker_api_id');
            $table->date('trade_date');
            $table->dateTime('interval_time');
            $table->string('base_symbol', 30);
            $table->string('future_symbol', 50)->nullable();
            $table->decimal('future_price', 10, 2)->nullable();
            $table->decimal('atm_strike', 10, 2)->nullable();
            $table->string('instrument_type', 10);   // FUT | CE | PE
            $table->decimal('strike', 10, 2)->nullable();
            $table->string('trading_symbol', 50);
            $table->unsignedBigInteger('instrument_token');
            $table->decimal('open',  10, 2)->default(0);
            $table->decimal('high',  10, 2)->default(0);
            $table->decimal('low',   10, 2)->default(0);
            $table->decimal('close', 10, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);
            $table->string('strike_position', 10)->nullable();
            $table->date('expiry_date')->nullable();
            $table->tinyInteger('is_missing')->default(0);
            $table->timestamps();

            // Unique constraint for upsert
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                '30min_ohlc_unique'
            );

            // Query indexes
            $table->index(['broker_api_id', 'base_symbol', 'trade_date']);
            $table->index(['trade_date', 'base_symbol', 'instrument_type']);
            $table->index('expiry_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('30min_ohlc_data');
    }
};
