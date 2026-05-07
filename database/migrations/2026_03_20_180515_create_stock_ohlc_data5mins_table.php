<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stock_ohlc_data_5min
 *
 * Stores NSE EQ (spot) 5-minute OHLC candles for stock option underlyings.
 * Collected by StockOhlcCollector5min — runs independently from derivatives.
 *
 * ── KEY DESIGN DECISIONS ─────────────────────────────────────────────────
 *
 *  Why separate from option_ohlc_data_5min?
 *    - Failure isolation: stock issue won't block options collection
 *    - No FUT/strike overhead — just one EQ row per symbol per interval
 *    - INDEX symbols (NIFTY, BANKNIFTY etc.) are NOT stored here —
 *      they use FUT price from option_ohlc_data_5min as their underlying
 *
 *  series      — 'MAR' | 'APR' etc.  (month of nearest active expiry)
 *                Tells the signal engine which option cycle this spot row
 *                corresponds to. Derived from the stock's nearest option expiry.
 *
 *  series_type — 'MAR25' | 'APR25' etc.  (month+year, ABSOLUTE)
 *                Matches series_type convention in option_ohlc_data_5min.
 *                Never relative ('current'/'next') — unambiguous in backtesting.
 *                Signal engine JOIN: stock.series_type = option.series_type
 *
 *  is_missing  — 0 = real candle from API
 *                1 = candle was absent in API response (gap filled as zero)
 *
 * ── SIGNAL ENGINE USAGE ──────────────────────────────────────────────────
 *  SELECT s.close AS spot_price, o.*
 *  FROM stock_ohlc_data_5min s
 *  JOIN option_ohlc_data_5min o
 *    ON s.symbol = o.base_symbol
 *   AND s.trade_date = o.trade_date
 *   AND s.interval_time = o.interval_time
 *   AND s.series_type = o.series_type
 *  WHERE s.symbol = 'RELIANCE' AND s.trade_date = '2025-03-20'
 * ─────────────────────────────────────────────────────────────────────────
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ohlc_data_5min', function (Blueprint $table) {

            $table->id();

            // ── Identity ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('broker_api_id');
            $table->date('trade_date');
            $table->dateTime('interval_time');

            // ── Symbol ───────────────────────────────────────────────────────
            $table->string('symbol', 20);           // base symbol: RELIANCE, TCS etc.
            $table->string('trading_symbol', 30);   // Zerodha trading_symbol (same as symbol for EQ)
            $table->unsignedBigInteger('instrument_token');
            $table->string('exchange', 10)->default('NSE');

            // ── Series context (expiry-aware tagging) ─────────────────────────
            $table->string('series', 10)->default('');       // MAR | APR | MAY …  (month only)
            $table->string('series_type', 10)->default('');  // MAR25 | APR25 …    (month+year, ABSOLUTE)

            // ── OHLCV ────────────────────────────────────────────────────────
            // No OI here — EQ spot data does not have OI
            $table->decimal('open',  12, 2)->default(0);
            $table->decimal('high',  12, 2)->default(0);
            $table->decimal('low',   12, 2)->default(0);
            $table->decimal('close', 12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);

            // ── Gap flag ─────────────────────────────────────────────────────
            $table->tinyInteger('is_missing')->default(0);
            // 0 = real candle from API   1 = absent in API response

            // ── Timestamps ───────────────────────────────────────────────────
            $table->timestamps();

            // ── Unique constraint ─────────────────────────────────────────────
            // One EQ row per broker × date × interval × symbol.
            // No expiry_date needed here — EQ has no expiry.
            $table->unique(
                ['broker_api_id', 'trade_date', 'interval_time', 'symbol'],
                'uq_stock5m_broker_date_time_sym'
            );

            // ── Query indexes ─────────────────────────────────────────────────
            //
            // Primary lookup: symbol + date + time  (most common query)
            $table->index(['symbol', 'trade_date', 'interval_time'],
                'idx_sym_date_time');

            // series_type filter — JOIN with option_ohlc_data_5min
            // e.g. WHERE symbol='RELIANCE' AND trade_date=? AND series_type='MAR25'
            $table->index(['symbol', 'trade_date', 'series_type'],
                'idx_sym_date_series_type');

            // Date-only queries  (e.g. all stocks for a given date)
            $table->index('trade_date',
                'idx_trade_date');

            // instrument_token  (dedup, re-fetch by token)
            $table->index(['instrument_token', 'trade_date'],
                'idx_token_date');

            // Gap-fill queries  (WHERE is_missing = 1 AND trade_date = ?)
            $table->index(['symbol', 'trade_date', 'is_missing'],
                'idx_sym_date_missing');

            // broker_api_id  (multi-broker setups)
            $table->index('broker_api_id',
                'idx_broker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ohlc_data_5min');
    }
};