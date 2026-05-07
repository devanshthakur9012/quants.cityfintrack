<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('options_ohlc_data', function (Blueprint $table) {
            $table->id();

            // ==============================
            // Broker & Symbol Info
            // ==============================
            $table->unsignedBigInteger('broker_api_id');
            $table->string('underlying_symbol', 50);      // NIFTY, BANKNIFTY
            $table->string('trading_symbol', 100);       // NIFTY26FEB26000CE
            $table->string('option_type', 10);           // FUT, CE, PE
            $table->string('strike_position', 30)->nullable(); // CE_ATM, PE_ITM1 etc.

            // ==============================
            // Strike & Expiry Details
            // ==============================
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->string('expiry', 10)->nullable();    
            $table->date('expiry_date')->nullable();

            // ==============================
            // Instrument Details
            // ==============================
            $table->bigInteger('instrument_token');
            $table->string('exchange', 10)->default('NFO');
            $table->integer('lot_size')->default(1);

            // ==============================
            // Timestamp & Date
            // ==============================
            $table->dateTime('timestamp');
            $table->date('trading_date');
            $table->string('interval', 20)->default('15minute');

            // ==============================
            // OHLC Data
            // ==============================
            $table->decimal('open', 12, 2);
            $table->decimal('high', 12, 2);
            $table->decimal('low', 12, 2);
            $table->decimal('close', 12, 2);
            $table->bigInteger('volume')->default(0);

            // ==============================
            // OI Data
            // ==============================
            $table->bigInteger('oi')->default(0);
            $table->bigInteger('previous_oi')->nullable();
            $table->bigInteger('oi_change')->default(0);
            $table->decimal('oi_change_percent', 8, 2)->default(0);
            $table->string('oi_signal', 20)->default('NEUTRAL'); // BUILDUP, UNWINDING

            // ==============================
            // Spot Price Reference
            // ==============================
            $table->decimal('spot_price', 12, 2)->nullable();

            // ==============================
            // Metadata
            // ==============================
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // =====================================================
            // INDEXES (SHORT NAMES - FIX FOR MYSQL 64 CHAR LIMIT)
            // =====================================================

            // Basic indexes
            $table->index('broker_api_id', 'idx_broker');
            $table->index('underlying_symbol', 'idx_underlying');
            $table->index('trading_symbol', 'idx_trading_symbol');
            $table->index('strike_position', 'idx_strike_pos');
            $table->index('instrument_token', 'idx_instrument');
            $table->index('timestamp', 'idx_timestamp');
            $table->index('trading_date', 'idx_trading_date');
            $table->index('interval', 'idx_interval');

            // Composite indexes (IMPORTANT for performance)
            $table->index(
                ['broker_api_id', 'underlying_symbol', 'trading_date'],
                'idx_broker_symbol_date'
            );

            $table->index(
                ['broker_api_id', 'trading_symbol', 'interval', 'timestamp'],
                'idx_broker_symbol_int_time'
            );

            $table->index(
                ['underlying_symbol', 'strike_position', 'trading_date', 'interval'],
                'idx_symbol_strike_date_int'
            );

            // Unique constraint (prevents duplicate candles)
            $table->unique(
                ['broker_api_id', 'trading_symbol', 'interval', 'timestamp'],
                'unique_ohlc_record'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('options_ohlc_data');
    }
};