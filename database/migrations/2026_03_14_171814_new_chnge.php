<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_daily_ohlc_data', function (Blueprint $table) {
            // Already in create migration — skip if present
            if (!$this->indexExists('stock_daily_ohlc_data', 'stock_daily_ohlc_data_symbol_trade_date_index')) {
                $table->index(['symbol', 'trade_date'], 'idx_sdohlc_symbol_date');
            }
 
            if (!$this->indexExists('stock_daily_ohlc_data', 'stock_daily_ohlc_data_trade_date_index')) {
                $table->index(['trade_date'], 'idx_sdohlc_trade_date');
            }
 
            if (!$this->indexExists('stock_daily_ohlc_data', 'stock_daily_ohlc_data_instrument_token_trade_date_index')) {
                $table->index(['instrument_token', 'trade_date'], 'idx_sdohlc_token_date');
            }
 
            // New indexes not in original create migration:
 
            // Covers the live-mode "already stored today?" pre-check:
            //   WHERE broker_api_id = ? AND trade_date = ? AND is_missing = 0
            $table->index(['broker_api_id', 'trade_date', 'is_missing'], 'idx_sdohlc_broker_date_missing');
 
            // Covers the historical smart-range MAX(trade_date) GROUP BY query:
            //   WHERE broker_api_id = ? AND is_missing = 0 GROUP BY symbol
            $table->index(['broker_api_id', 'is_missing', 'symbol', 'trade_date'], 'idx_sdohlc_broker_missing_sym_date');
        });
    }
 
    public function down(): void
    {
        Schema::table('stock_daily_ohlc_data', function (Blueprint $table) {
            $table->dropIndex('idx_sdohlc_broker_date_missing');
            $table->dropIndex('idx_sdohlc_broker_missing_sym_date');
        });
    }
 
    /** Check if an index already exists to avoid duplicate-key errors. */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
