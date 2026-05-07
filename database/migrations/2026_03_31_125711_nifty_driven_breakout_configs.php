<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Configs: add profit-target columns ───────────────────────────────
        Schema::table('nifty_driven_breakout_configs', function (Blueprint $table) {
            $table->boolean('enable_target')->default(false)->after('stoploss_order_type');
            $table->string('target_type')->default('pct')->after('enable_target');   // 'pct' | 'points'
            $table->decimal('target_value', 10, 2)->default(50)->after('target_type'); // % or pts above entry
            $table->string('target_order_type')->default('SL-M')->after('target_value'); // always limit sell
        });

        // ── Orders: add profit-target tracking columns ────────────────────────
        Schema::table('nifty_driven_breakout_orders', function (Blueprint $table) {
            $table->decimal('target_price', 10, 2)->nullable()->after('stoploss_price');
            $table->boolean('target_enabled')->default(false)->after('target_price');
            $table->boolean('target_placed')->default(false)->after('target_enabled');
            $table->string('target_order_id')->nullable()->after('target_placed');
        });
    }

    public function down(): void
    {
        Schema::table('nifty_driven_breakout_configs', function (Blueprint $table) {
            $table->dropColumn(['enable_target', 'target_type', 'target_value', 'target_order_type']);
        });

        Schema::table('nifty_driven_breakout_orders', function (Blueprint $table) {
            $table->dropColumn(['target_price', 'target_enabled', 'target_placed', 'target_order_id']);
        });
    }
};