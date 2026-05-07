<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * oiiv_btst_configs
     *
     * The ONLY new table needed for BTST exit logic.
     *
     * All SELL / SL orders placed by the BTST command are stored in the
     * EXISTING oiiv_order_book table — same table as BUY orders, just
     * with transaction_type = 'SELL' and signal_type = 'BTST_SL' |
     * 'BTST_PROFIT' | 'BTST_SWEEP' | 'BTST_COSTCOST'.
     *
     * This is consistent with how square-off already works — you can see
     * all orders (BUY + SELL) in one place in the Order Book UI.
     *
     * FLOW:
     *   T   3PM  → PECEAutoTradingHelper places BUY → oiiv_order_book (BUY)
     *            → SyncOiivOrders creates oiiv_positions row when COMPLETE
     *   T+1 9:15 → OiivBtstExitCommand reads oiiv_positions (open, yesterday)
     *              For each: place SL order + profit order → oiiv_order_book (SELL)
     *   T+1 10AM → Sweep: modify/replace → oiiv_order_book (SELL, BTST_SWEEP)
     */
    public function up(): void
    {
        Schema::create('oiiv_btst_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
 
            // ── Fresh positions (signal_date = yesterday) ─────────────────
            $table->decimal('sl_percent', 8, 2)->default(15.00)
                  ->comment('SL% below AVG entry. SL trigger = AVG × (1 - sl_percent/100)');
            $table->decimal('profit_percent', 8, 2)->default(20.00)
                  ->comment('Profit% above AVG. Limit sell = AVG × (1 + profit_percent/100). If LTP > target at 9:15 → use LTP instead');
 
            // ── 10 AM sweep ───────────────────────────────────────────────
            $table->decimal('min_profit_percent', 8, 2)->default(0.00)
                  ->comment('Sweep threshold: only close at 10AM if profit ≥ this%. 0 = close all in profit');
            $table->boolean('enable_10am_sweep')->default(true);
            $table->time('sweep_time')->default('10:00:00');
 
            // ── Old positions (signal_date ≤ T-2) ─────────────────────────
            $table->decimal('old_position_sl_percent', 8, 2)->default(20.00)
                  ->comment('Wider SL for old positions');
            $table->string('old_position_action', 16)->default('cost_to_cost')
                  ->comment('cost_to_cost | close_profit');
 
            // ── Symbol filter ─────────────────────────────────────────────
            $table->string('symbol_type', 8)->default('BOTH');
            $table->boolean('is_active')->default(true);
 
            $table->timestamps();
 
            $table->unique(['broker_api_id', 'symbol_type'], 'btst_cfg_broker_sym_unique');
            $table->index('user_id');
            $table->index('broker_api_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('oiiv_btst_configs');
    }
};
