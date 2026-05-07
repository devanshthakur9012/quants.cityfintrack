<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('nifty_option_1min_ohlc', function (Blueprint $table) {
 
            $table->id();
 
            // ── Broker / account ──────────────────────────────────────────────
            $table->unsignedBigInteger('broker_api_id')->comment('FK → broker_apis.id');
 
            // ── Time axes ─────────────────────────────────────────────────────
            $table->date('trade_date')->comment('Market date (IST)');
            $table->dateTime('interval_time')->comment('Candle open timestamp (IST), e.g. 09:15:00');
 
            // ── Symbol identifiers ────────────────────────────────────────────
            $table->string('trading_symbol', 32)->comment('Full Zerodha trading symbol, e.g. NIFTY2542124000CE');
            $table->string('base_symbol', 16)->default('NIFTY')->comment('Always NIFTY for this table');
            $table->string('future_symbol', 32)->nullable()->comment('Near-month FUT symbol used for ATM ref');
            $table->decimal('future_price', 12, 2)->nullable()->comment('FUT close at this interval (carry-forwarded if missing)');
 
            // ── ATM / strike metadata ─────────────────────────────────────────
            $table->decimal('atm_strike', 12, 2)->comment('ATM frozen at 09:15 FUT close, rounded to strike interval');
            $table->decimal('strike', 12, 2)->nullable()->comment('Strike price; NULL for FUT rows');
            $table->enum('instrument_type', ['FUT', 'CE', 'PE'])->comment('Row type');
            $table->unsignedBigInteger('instrument_token')->comment('Zerodha instrument token');
            $table->date('expiry_date')->comment('Contract expiry date');
 
            // ── Strike relative position ──────────────────────────────────────
            // ATM, ATM±1..±5; FUT rows use "N/A"
            $table->enum('strike_position', [
                'N/A',
                'ATM',
                'ATM+1', 'ATM+2', 'ATM+3', 'ATM+4', 'ATM+5',
                'ATM-1', 'ATM-2', 'ATM-3', 'ATM-4', 'ATM-5',
            ])->default('N/A');
 
            // ── OHLCV + OI ────────────────────────────────────────────────────
            $table->decimal('open',   12, 2)->default(0);
            $table->decimal('high',   12, 2)->default(0);
            $table->decimal('low',    12, 2)->default(0);
            $table->decimal('close',  12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0)->comment('Open Interest');
 
            // ── Data-quality flag ─────────────────────────────────────────────
            $table->tinyInteger('is_missing')->default(0)
                ->comment('1 = candle unavailable from broker; row zero-filled for continuity');
 
            $table->timestamps();
 
            // ── Indexes ───────────────────────────────────────────────────────
 
            // Primary business key — used for upsert dedup
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol'],
                'uq_nifty1m_broker_date_time_symbol'
            );
 
            // Fast lookup by date (most common filter)
            $table->index('trade_date', 'idx_nifty1m_trade_date');
 
            // Time-series queries: date + interval window
            $table->index(['trade_date', 'interval_time'], 'idx_nifty1m_date_interval');
 
            // Filter by instrument type within a date
            $table->index(['trade_date', 'instrument_type'], 'idx_nifty1m_date_type');
 
            // Strike + type + date (option chain view)
            $table->index(['trade_date', 'strike', 'instrument_type'], 'idx_nifty1m_date_strike_type');
 
            // Expiry filter
            $table->index('expiry_date', 'idx_nifty1m_expiry');
 
            // Token-based lookup (raw instrument fetch)
            $table->index(['instrument_token', 'trade_date'], 'idx_nifty1m_token_date');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('nifty_option_1min_ohlc');
    }
};
