<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $timeframes = ['15min', '30min', '1hr'];

    public function up(): void
    {
        foreach ($this->timeframes as $tf) {
            $this->createStockTable($tf);
            $this->createFutTable($tf);
            $this->createOptionTable($tf);
        }
    }

    public function down(): void
    {
        foreach ($this->timeframes as $tf) {
            Schema::dropIfExists("cp_option_ohlc_{$tf}");
            Schema::dropIfExists("cp_fut_ohlc_{$tf}");
            Schema::dropIfExists("cp_stock_ohlc_{$tf}");
        }
    }

    // ── Stock OHLC (EQ only — no OI column) ──────────────────────────────────
    private function createStockTable(string $tf): void
    {
        Schema::create("cp_stock_ohlc_{$tf}", function (Blueprint $table) use ($tf) {
            $table->id();
            $table->unsignedBigInteger('analysis_config_id');
            $table->unsignedBigInteger('broker_api_id');

            $table->string('symbol', 50);
            $table->string('trading_symbol', 100);
            $table->unsignedBigInteger('instrument_token');

            $table->date('trade_date');
            $table->dateTime('interval_time');

            // Stock EQ: OHLC + Volume only (NO OI column)
            $table->decimal('open',   12, 2)->default(0);
            $table->decimal('high',   12, 2)->default(0);
            $table->decimal('low',    12, 2)->default(0);
            $table->decimal('close',  12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);

            $table->boolean('is_missing')->default(false);
            $table->boolean('is_backfill')->default(false);
            $table->timestamps();

            $table->unique(
                ['analysis_config_id', 'symbol', 'trade_date', 'interval_time'],
                "cp_stock_{$tf}_unique"
            );
            $table->index(['symbol', 'trade_date'], "cp_stock_{$tf}_sym_date");
            $table->index('analysis_config_id',     "cp_stock_{$tf}_config");

            $table->foreign('analysis_config_id')
                  ->references('id')->on('analysis_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')
                  ->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    // ── FUT OHLC (OHLC + Volume + OI) ────────────────────────────────────────
    private function createFutTable(string $tf): void
    {
        Schema::create("cp_fut_ohlc_{$tf}", function (Blueprint $table) use ($tf) {
            $table->id();
            $table->unsignedBigInteger('analysis_config_id');
            $table->unsignedBigInteger('broker_api_id');

            $table->string('base_symbol', 50);
            $table->string('trading_symbol', 100);
            $table->unsignedBigInteger('instrument_token');
            $table->date('expiry_date');
            $table->decimal('atm_strike', 12, 2)->default(0);

            $table->date('trade_date');
            $table->dateTime('interval_time');

            // FUT: OHLC + Volume + OI
            $table->decimal('open',   12, 2)->default(0);
            $table->decimal('high',   12, 2)->default(0);
            $table->decimal('low',    12, 2)->default(0);
            $table->decimal('close',  12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);

            $table->boolean('is_missing')->default(false);
            $table->boolean('is_backfill')->default(false);
            $table->timestamps();

            $table->unique(
                ['analysis_config_id', 'base_symbol', 'expiry_date', 'trade_date', 'interval_time'],
                "cp_fut_{$tf}_unique"
            );
            $table->index(['base_symbol', 'trade_date'],  "cp_fut_{$tf}_sym_date");
            $table->index(['base_symbol', 'expiry_date'], "cp_fut_{$tf}_sym_exp");
            $table->index('analysis_config_id',           "cp_fut_{$tf}_config");

            $table->foreign('analysis_config_id')
                  ->references('id')->on('analysis_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')
                  ->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    // ── Option OHLC (OHLC + Volume + OI + strike info) ───────────────────────
    private function createOptionTable(string $tf): void
    {
        Schema::create("cp_option_ohlc_{$tf}", function (Blueprint $table) use ($tf) {
            $table->id();
            $table->unsignedBigInteger('analysis_config_id');
            $table->unsignedBigInteger('broker_api_id');

            $table->string('base_symbol', 50);
            $table->string('fut_trading_symbol', 100);
            $table->decimal('future_price', 12, 2)->default(0);
            $table->string('trading_symbol', 100);
            $table->unsignedBigInteger('instrument_token');

            $table->date('expiry_date');
            $table->enum('instrument_type', ['CE', 'PE']);
            $table->decimal('atm_strike', 12, 2)->default(0);
            $table->decimal('strike',     12, 2)->default(0);
            $table->string('strike_position', 10)->default('N/A');

            $table->date('trade_date');
            $table->dateTime('interval_time');

            // Option: OHLC + Volume + OI
            $table->decimal('open',   12, 2)->default(0);
            $table->decimal('high',   12, 2)->default(0);
            $table->decimal('low',    12, 2)->default(0);
            $table->decimal('close',  12, 2)->default(0);
            $table->unsignedBigInteger('volume')->default(0);
            $table->unsignedBigInteger('oi')->default(0);

            $table->boolean('is_missing')->default(false);
            $table->boolean('is_backfill')->default(false);
            $table->timestamps();

            $table->unique(
                ['analysis_config_id', 'trading_symbol', 'trade_date', 'interval_time'],
                "cp_opt_{$tf}_unique"
            );
            $table->index(['base_symbol', 'trade_date'],                              "cp_opt_{$tf}_sym_date");
            $table->index(['base_symbol', 'expiry_date'],                             "cp_opt_{$tf}_sym_exp");
            $table->index(['base_symbol', 'instrument_type', 'strike', 'trade_date'], "cp_opt_{$tf}_strike");
            $table->index('analysis_config_id',                                       "cp_opt_{$tf}_config");

            $table->foreign('analysis_config_id')
                  ->references('id')->on('analysis_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')
                  ->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }
};