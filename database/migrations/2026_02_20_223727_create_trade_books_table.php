<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_book', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id')->nullable();
            $table->string('broker_name', 50)->default('Zerodha');
            $table->string('report_month', 7);          // "2025-02"
            $table->string('original_filename')->nullable();

            // Parsed from the symbol string (e.g. ADANIPORTS26FEB1520CE)
            $table->string('symbol');                   // full raw: ADANIPORTS26FEB1520CE
            $table->string('base_symbol', 50);          // ADANIPORTS
            $table->string('instrument_type', 10);      // CE / PE / FUT / EQ
            $table->decimal('strike', 12, 2)->nullable();
            $table->string('expiry_raw', 20)->nullable(); // e.g. 26FEB

            // Values from the P&L report
            $table->integer('quantity')->default(0);
            $table->decimal('buy_value', 16, 4)->default(0);
            $table->decimal('sell_value', 16, 4)->default(0);
            $table->decimal('realized_pnl', 14, 4)->default(0);
            $table->decimal('realized_pnl_pct', 10, 4)->default(0);
            $table->decimal('prev_closing_price', 12, 4)->default(0);

            // Open position data
            $table->integer('open_quantity')->default(0);
            $table->string('open_quantity_type', 10)->nullable(); // buy / sell
            $table->decimal('open_value', 16, 4)->default(0);
            $table->decimal('unrealized_pnl', 14, 4)->default(0);
            $table->decimal('unrealized_pnl_pct', 10, 4)->default(0);

            $table->boolean('is_open_position')->default(false);

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'report_month']);
            $table->index(['user_id', 'broker_name', 'report_month']);
            $table->index(['user_id', 'report_month', 'base_symbol']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_book');
    }
};
