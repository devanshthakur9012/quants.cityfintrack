<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create indicator_configs table
        Schema::create('indicator_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique(); // 'default', or trading_symbol specific
            $table->enum('scope', ['global', 'symbol'])->default('global'); // global or symbol-specific
            $table->string('trading_symbol', 50)->nullable(); // null for global, specific symbol for symbol-level
            
            // Supertrend Config
            $table->integer('supertrend_atr_period')->default(10);
            $table->decimal('supertrend_multiplier', 5, 2)->default(3.00);
            
            // Donchian Config (Industry Standard: separate high/low periods)
            $table->integer('donchian_high_period')->default(20); // Period for upper channel
            $table->integer('donchian_low_period')->default(20);  // Period for lower channel
            $table->decimal('donchian_risk_reward', 5, 2)->default(2.00); // Risk:Reward ratio for targets
            
            // RSI Config
            $table->integer('rsi_period')->default(14);
            $table->decimal('rsi_overbought', 5, 2)->default(70.00);
            $table->decimal('rsi_oversold', 5, 2)->default(30.00);
            
            // MACD Config
            $table->integer('macd_fast_period')->default(12);
            $table->integer('macd_slow_period')->default(26);
            $table->integer('macd_signal_period')->default(9);
            
            // Metadata
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['scope', 'is_active']);
            $table->index('trading_symbol');
        });

        // Insert default global configuration
        DB::table('indicator_configs')->insert([
            'name' => 'default',
            'scope' => 'global',
            'trading_symbol' => null,
            // Supertrend
            'supertrend_atr_period' => 10,
            'supertrend_multiplier' => 3.00,
            // Donchian
            'donchian_high_period' => 20,
            'donchian_low_period' => 20,
            'donchian_risk_reward' => 2.00,
            // RSI
            'rsi_period' => 14,
            'rsi_overbought' => 70.00,
            'rsi_oversold' => 30.00,
            // MACD
            'macd_fast_period' => 12,
            'macd_slow_period' => 26,
            'macd_signal_period' => 9,
            // Meta
            'is_active' => true,
            'description' => 'Default global configuration for all symbols with industry-standard indicator parameters',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop indicator_configs table
        Schema::dropIfExists('indicator_configs');
    }
};