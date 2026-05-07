<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');

            // Symbol details
            $table->string('symbol');                          // e.g. NIFTY24FEB22000CE
            $table->string('exchange')->default('NFO');
            $table->string('product')->nullable();             // NRML, MIS, CNC
            $table->string('instrument_token')->nullable();
            $table->string('position_type')->nullable();       // LONG or SHORT

            // Quantity
            $table->integer('qty');
            $table->integer('buy_qty')->default(0);
            $table->integer('sell_qty')->default(0);

            // Prices — THIS IS THE KEY DATA YOU WANT
            $table->decimal('entry_price', 12, 2);            // avg buy price
            $table->decimal('exit_price', 12, 2);             // avg sell price
            $table->decimal('buy_value', 15, 2)->default(0);  // total buy amount
            $table->decimal('sell_value', 15, 2)->default(0); // total sell amount

            // P&L
            $table->decimal('realized_pnl', 15, 2);           // sell_value - buy_value

            // Dates
            $table->date('entry_date');                        // when first bought
            $table->date('exit_date');                         // when fully closed
            $table->integer('holding_days');                   // how many days held

            // How was it closed
            $table->string('exit_source')->default('MANUAL_ZERODHA'); // MANUAL_ZERODHA or SYSTEM

            // Link back to original portfolio_position (optional, for audit)
            $table->unsignedBigInteger('portfolio_position_id')->nullable();

            $table->timestamps();

            // Indexes for fast querying
            $table->index(['user_id', 'broker_api_id']);
            $table->index(['user_id', 'exit_date']);
            $table->index('symbol');
        });
    }

    public function down(): void
    {
        Schema::drop('position_history');
    }
};
