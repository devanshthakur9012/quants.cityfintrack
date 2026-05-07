<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->decimal('supertrend_multiplier', 5, 2)->default(1.70);
            
            // Donchian Config
            $table->integer('donchian_period')->default(10);
            $table->decimal('donchian_risk_reward', 5, 2)->default(2.00); // 1:2 risk-reward
            
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
            'supertrend_atr_period' => 10,
            'supertrend_multiplier' => 1.70,
            'donchian_period' => 10,
            'donchian_risk_reward' => 2.00,
            'is_active' => true,
            'description' => 'Default global configuration for all symbols',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Add Donchian columns to futures_data table
        Schema::table('futures_data', function (Blueprint $table) {
            $table->string('donchian_signal', 20)->nullable()->after('supertrend_signal'); // BUY, SELL, NO_TRADE
            $table->decimal('donchian_upper', 12, 2)->nullable()->after('donchian_signal');
            $table->decimal('donchian_lower', 12, 2)->nullable()->after('donchian_upper');
            $table->decimal('donchian_middle', 12, 2)->nullable()->after('donchian_lower');
            $table->decimal('donchian_entry', 12, 2)->nullable()->after('donchian_middle');
            $table->decimal('donchian_sl', 12, 2)->nullable()->after('donchian_entry');
            $table->decimal('donchian_target', 12, 2)->nullable()->after('donchian_sl');
            
            // Index for filtering
            $table->index('donchian_signal');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop Donchian columns
        Schema::table('futures_data', function (Blueprint $table) {
            $table->dropIndex(['donchian_signal']);
            $table->dropColumn([
                'donchian_signal',
                'donchian_upper',
                'donchian_lower',
                'donchian_middle',
                'donchian_entry',
                'donchian_sl',
                'donchian_target'
            ]);
        });

        Schema::dropIfExists('indicator_configs');
    }
};
