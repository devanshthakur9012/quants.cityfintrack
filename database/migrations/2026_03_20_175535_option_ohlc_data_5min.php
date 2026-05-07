<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * option_ohlc_data_5min
 *
 * Stores FUT + CE + PE 5-minute OHLC data in ONE table.
 * instrument_type discriminates: 'FUT' | 'CE' | 'PE'
 *
 * ── KEY DESIGN DECISIONS ─────────────────────────────────────────────────
 *
 *  series        — 'MAR' | 'APR' | 'MAY' etc.  (month name from expiry date)
 *
 *  series_type   — 'MAR25' | 'APR25' | 'MAY25' etc.  (month+year from expiry date)
 *                  ABSOLUTE — derived from the actual expiry, never relative.
 *                  Old labels like 'current'/'next' changed meaning every month,
 *                  making backtesting queries fragile. 'MAR25' always means MAR25.
 *                  Signal engine filter: WHERE series_type = 'MAR25'
 *
 *  future_price  — REAL FUT close at this exact interval. NOT frozen at 09:15.
 *                  Signal engine reads this to compute rolling ATM per candle.
 *                  0 = FUT candle was missing at this interval (is_missing = 1).
 *
 *  atm_strike    — Nearest valid strike to future_price at this interval.
 *                  Stored as metadata only. Signal engine recomputes dynamically.
 *
 *  strike_position — ATM, ATM+1, ATM-2 etc. relative to this interval's ATM.
 *                    Metadata only. VARCHAR(10), no ENUM restriction.
 *
 *  Strike range stored:
 *    Nearest expiry  → ATM±15  (31 strikes — full intraday range)
 *    Further expiry  → ATM±10  (21 strikes — enough for next series)
 *    Signal engine can roll ATM freely throughout the day without re-fetching.
 *
 * ── WHAT IS NOT STORED HERE ──────────────────────────────────────────────
 *  Stock EQ data → stock_ohlc_data_5min (separate table, separate command)
 * ─────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_ohlc_data_5min', function (Blueprint $table) {

            $table->id();

            // ── Identity ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('broker_api_id');
            $table->date('trade_date');
            $table->dateTime('interval_time');

            // ── Symbol ───────────────────────────────────────────────────────
            $table->string('base_symbol', 20);
            $table->string('trading_symbol', 50);
            $table->string('future_symbol', 50)->nullable();

            // ── Instrument ───────────────────────────────────────────────────
            $table->string('instrument_type', 5);       // FUT | CE | PE
            $table->decimal('strike', 12, 2)->nullable();
            $table->string('exchange', 10)->default('NFO');
            $table->unsignedBigInteger('instrument_token');

            // ── Expiry & Series ───────────────────────────────────────────────
            $table->date('expiry_date')->nullable();
            $table->string('series', 10)->default('');      // MAR | APR | MAY …  (month only)
            $table->string('series_type', 10)->default(''); // MAR25 | APR25 …    (month+year, ABSOLUTE)

            // ── ATM reference (per-interval, not frozen) ─────────────────────
            $table->decimal('future_price', 12, 2)->default(0); // real FUT close; 0 = missing candle
            $table->decimal('atm_strike', 12, 2)->nullable()->default(null); // nearest strike to future_price

            // ── Strike position (metadata — signal engine recomputes) ─────────
            $table->string('strike_position', 10)->default('N/A');

            // ── OHLCV + OI ───────────────────────────────────────────────────
            $table->decimal('open',  12, 2)->default(0);
            $table->decimal('high',  12, 2)->default(0);
            $table->decimal('low',   12, 2)->default(0);
            $table->decimal('close', 12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->nullable()->default(null);
            // null = API did not return OI for this candle
            // 0    = API returned OI explicitly as zero

            // ── Gap flag ─────────────────────────────────────────────────────
            $table->tinyInteger('is_missing')->default(0);
            // 0 = real data   1 = candle was missing from API response

            // ── Timestamps ───────────────────────────────────────────────────
            $table->timestamps();

            // ── Unique constraint ─────────────────────────────────────────────
            // expiry_date included so the key is broker-format-independent.
            // trading_symbol encodes expiry in Zerodha format (e.g. NIFTY250327000CE)
            // but formats vary across brokers; expiry_date makes it explicit.
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'trading_symbol', 'expiry_date'],
                'uq_opt5m_broker_time_sym_expiry'
            );

            // ── Query indexes ─────────────────────────────────────────────────
            //
            // Primary lookup: symbol + date + time  (collector gap-fill queries)
            $table->index(['base_symbol', 'trade_date', 'interval_time'],
                'idx_sym_date_time');

            // Instrument type filter  (FUT-only or CE/PE-only queries)
            $table->index(['base_symbol', 'trade_date', 'instrument_type'],
                'idx_sym_date_type');

            // series_type filter — most important signal engine query
            // e.g. WHERE base_symbol='NIFTY' AND trade_date='2025-03-20' AND series_type='MAR25'
            $table->index(['base_symbol', 'trade_date', 'series_type'],
                'idx_sym_date_series_type');

            // Strike lookup within a type  (signal engine: CE/PE at specific strike)
            $table->index(['base_symbol', 'trade_date', 'instrument_type', 'strike'],
                'idx_sym_date_type_strike');

            // Cross-symbol date+series  (e.g. all symbols for MAR25 on a date)
            $table->index(['trade_date', 'series_type'],
                'idx_date_series_type');

            // instrument_token lookup  (signal engine candle fetch + dedup)
            $table->index(['instrument_token', 'trade_date'],
                'idx_token_date');

            // is_missing filter  (gap-fill: WHERE is_missing = 1 AND trade_date = ?)
            $table->index(['base_symbol', 'trade_date', 'is_missing'],
                'idx_sym_date_missing');

            // expiry_date  (cross-series backtesting range queries)
            $table->index('expiry_date', 'idx_expiry_date');

            // broker_api_id  (multi-broker setups)
            $table->index('broker_api_id', 'idx_broker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_ohlc_data_5min');
    }
};