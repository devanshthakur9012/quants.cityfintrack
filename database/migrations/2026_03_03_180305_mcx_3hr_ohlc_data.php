<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcx_3hr_ohlc_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broker_api_id');
            $table->date('trade_date');
            $table->dateTime('interval_time');          // 3Hr slot start: 09:00, 12:00, 15:00
            $table->string('base_symbol', 30);          // CRUDEOIL | CRUDEOILM | NATGAS
            $table->string('future_symbol', 50)->nullable();
            $table->decimal('future_price', 12, 2)->nullable();
            $table->decimal('atm_strike',   12, 2)->nullable();
            $table->string('instrument_type', 10);      // FUT | CE | PE
            $table->decimal('strike', 12, 2)->nullable();
            $table->string('trading_symbol', 60);
            $table->unsignedBigInteger('instrument_token');
            $table->decimal('open',  12, 2)->default(0);
            $table->decimal('high',  12, 2)->default(0);
            $table->decimal('low',   12, 2)->default(0);
            $table->decimal('close', 12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);
            $table->string('strike_position', 10)->nullable(); // ATM, ATM+1, ATM-1, N/A
            $table->date('expiry_date')->nullable();
            $table->tinyInteger('is_missing')->default(0);
            $table->timestamps();

            // Upsert unique key
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                'mcx_3hr_ohlc_unique'
            );

            // Query indexes
            $table->index(['trade_date', 'base_symbol', 'instrument_type'], 'mcx_3hr_date_sym_type');
            $table->index(['base_symbol', 'strike_position', 'trade_date'],  'mcx_3hr_atm_lookup');
            $table->index('expiry_date', 'mcx_3hr_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcx_3hr_ohlc_data');
    }
};
