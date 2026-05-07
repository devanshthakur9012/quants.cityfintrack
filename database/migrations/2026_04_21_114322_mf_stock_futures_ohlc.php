<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        // ── TABLE 1: mf_stock_futures_ohlc ────────────────────────────────────
        Schema::create('mf_stock_futures_ohlc', function (Blueprint $table) {
            $table->id();
 
            $table->string('symbol');                    // e.g. HDFCBANK
            $table->string('exchange')->default('NFO');  // NFO / BFO
            $table->string('trading_symbol');            // e.g. HDFCBANK25JUNFUT
            $table->unsignedBigInteger('instrument_token');
 
            $table->date('trade_date');
            $table->dateTime('interval_time');           // 15-min candle open time e.g. 2024-01-10 09:15:00
            $table->date('expiry_date');                 // FUT expiry e.g. 2024-01-25
            $table->unsignedInteger('lot_size')->default(0);
 
            $table->decimal('open',  10, 2)->default(0);
            $table->decimal('high',  10, 2)->default(0);
            $table->decimal('low',   10, 2)->default(0);
            $table->decimal('close', 10, 2)->default(0);
 
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);     // Open Interest — very important for FUT
 
            $table->decimal('spot_price', 10, 2)->nullable(); // underlying spot price at this interval
            $table->decimal('atm_strike', 10, 2)->nullable(); // frozen ATM strike for this day
 
            $table->boolean('is_missing')->default(false);
 
            $table->timestamps();
 
            // Unique: one FUT row per symbol per expiry per 15-min candle
            $table->unique(['symbol', 'expiry_date', 'interval_time'], 'mf_fut_symbol_expiry_interval_unique');
 
            $table->index('trade_date');
            $table->index(['symbol', 'trade_date']);
            $table->index(['symbol', 'expiry_date']);
            $table->index('interval_time');
        });
 
        // ── TABLE 2: mf_stock_options_ohlc ────────────────────────────────────
        Schema::create('mf_stock_options_ohlc', function (Blueprint $table) {
            $table->id();
 
            $table->string('symbol');                    // e.g. HDFCBANK
            $table->string('exchange')->default('NFO');  // NFO / BFO
            $table->string('trading_symbol');            // e.g. HDFCBANK25JUN1550CE
            $table->unsignedBigInteger('instrument_token');
 
            $table->string('option_type', 2);            // CE / PE
            $table->decimal('strike_price', 10, 2);      // e.g. 1550.00
            $table->string('strike_position', 10)        // ATM, ATM+1, ATM-1, OTM+2 etc.
                  ->default('N/A');
 
            $table->date('trade_date');
            $table->dateTime('interval_time');           // 15-min candle open time
            $table->date('expiry_date');                 // Option expiry
 
            $table->decimal('open',  10, 2)->default(0);
            $table->decimal('high',  10, 2)->default(0);
            $table->decimal('low',   10, 2)->default(0);
            $table->decimal('close', 10, 2)->default(0);
 
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);     // OI crucial for option strategy
 
            // Reference prices at this interval
            $table->decimal('fut_price',  10, 2)->nullable(); // FUT close at same interval
            $table->decimal('spot_price', 10, 2)->nullable(); // Spot price (if available)
            $table->decimal('atm_strike', 10, 2)->nullable(); // Frozen ATM for this day
 
            $table->boolean('is_missing')->default(false);
 
            $table->timestamps();
 
            // Unique: one row per symbol+strike+option_type+expiry+interval
            $table->unique(
                ['symbol', 'option_type', 'strike_price', 'expiry_date', 'interval_time'],
                'mf_opt_symbol_strike_type_expiry_interval_unique'
            );
 
            $table->index('trade_date');
            $table->index(['symbol', 'trade_date']);
            $table->index(['symbol', 'option_type', 'expiry_date']);
            $table->index(['symbol', 'strike_price', 'expiry_date']);
            $table->index('interval_time');
            $table->index('oi');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('mf_stock_options_ohlc');
        Schema::dropIfExists('mf_stock_futures_ohlc');
    }
};
