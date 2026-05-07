<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exit_plan_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');

            $table->unsignedBigInteger('oiiv_order_id')->nullable()
                  ->comment('Reference to original entry order in oiiv_auto_orders');

            // Symbol info
            $table->string('symbol', 30)->comment('Base symbol e.g. NIFTY, ADANIPORTS');
            $table->string('trading_symbol', 60)->nullable()->comment('FUT trading symbol');

            // Date context
            $table->string('signal_date', 10)->comment('Trade entry date Y-m-d (previous trading day)');
            $table->string('exit_check_date', 10)->comment('Exit OI check date Y-m-d (next trading day 09:30)');

            // Original trade direction
            $table->string('original_sentiment', 20)->nullable()->comment('BULLISH / BEARISH');
            $table->string('original_trade_action', 20)->nullable()->comment('BUY CE / BUY PE');
            $table->string('original_oi_condition', 60)->nullable();

            // Exit OI signal (exit_check_date 09:30 vs signal_date 15:15)
            $table->string('exit_sentiment', 20)->nullable()->comment('BULLISH / BEARISH / NEUTRAL');
            $table->string('exit_oi_condition', 60)->nullable();
            $table->decimal('exit_ce_oi_pct', 10, 4)->default(0);
            $table->decimal('exit_pe_oi_pct', 10, 4)->default(0);

            // Decision
            $table->enum('exit_decision', ['HOLD', 'EXIT', 'MONITOR'])->default('MONITOR');
            $table->text('exit_reason')->nullable();

            // The SELL option contract
            $table->string('option_symbol', 60)->nullable();
            $table->unsignedBigInteger('option_token')->nullable();
            $table->string('option_type', 5)->nullable()->comment('CE or PE');
            $table->decimal('strike_price', 10, 2)->nullable();
            $table->decimal('exit_price', 10, 2)->default(0)->comment('LTP at time of exit signal');
            $table->decimal('current_price', 10, 2)->default(0);

            // Order settings
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('MARKET');
            $table->enum('product', ['NRML', 'MIS'])->default('NRML');
            $table->integer('quantity')->default(0);

            // Status tracking
            $table->boolean('is_order_placed')->default(false);
            $table->timestamp('order_placed_at')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamp('signal_detected_at')->nullable();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('exit_plan_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');

            $table->index(['user_id', 'config_id', 'exit_check_date']);
            $table->index(['is_order_placed', 'status']);
            $table->index(['symbol', 'exit_check_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exit_plan_orders');
    }
};
