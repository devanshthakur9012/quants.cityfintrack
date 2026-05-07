<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcx_ohlc_data', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->unsignedBigInteger('broker_api_id');
            $table->date('trade_date');
            $table->dateTime('interval_time');
            $table->string('trading_symbol', 50)->comment('Full symbol e.g. CRUDEOIL24JANFUT');

            // Symbol info
            $table->string('base_symbol', 30)->comment('e.g. CRUDEOIL — matches mcx_symbols.symbol');
            $table->string('future_symbol', 50)->nullable();
            $table->decimal('future_price', 12, 2)->default(0);
            $table->decimal('atm_strike',   12, 2)->default(0)->comment('Frozen ATM at session open');

            // Instrument details (sourced from zerodha_instruments at collection time)
            $table->string('instrument_type', 10)->comment('FUT, CE, PE');
            $table->decimal('strike', 12, 2)->nullable();
            $table->unsignedBigInteger('instrument_token');
            $table->string('strike_position', 10)->nullable()->comment('ATM, ATM+1, ATM-1 …');
            $table->date('expiry_date');

            // OHLCV + OI
            $table->decimal('open',   14, 2)->default(0);
            $table->decimal('high',   14, 2)->default(0);
            $table->decimal('low',    14, 2)->default(0);
            $table->decimal('close',  14, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);

            // Quality flag
            $table->tinyInteger('is_missing')->default(0)
                  ->comment('1 = broker returned no candle for this interval');

            $table->timestamps();

            // Upsert unique key
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                'mcx_ohlc_unique'
            );

            // Performance indexes
            $table->index(['base_symbol', 'trade_date', 'instrument_type'], 'mcx_sym_date_type');
            $table->index(['trade_date', 'interval_time'],                  'mcx_date_interval');
            $table->index('instrument_token',                               'mcx_token');
            $table->index('expiry_date',                                    'mcx_expiry');

            $table->foreign('broker_api_id')
                  ->references('id')->on('broker_apis')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcx_ohlc_data');
    }
};
