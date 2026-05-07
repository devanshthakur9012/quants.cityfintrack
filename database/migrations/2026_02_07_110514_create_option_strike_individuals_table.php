<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('option_strikes_individual', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broker_api_id');
            
            // Symbol and strike information
            $table->string('underlying_symbol', 50)->index();
            $table->string('trading_symbol', 100)->index();
            $table->enum('option_type', ['CE', 'PE', 'FUT'])->index();
            $table->decimal('strike_price', 12, 2);
            $table->string('strike_position', 20)->index(); // e.g., 'ATM-2', 'ATM-1', 'ATM', 'ATM+1', 'ATM+2', 'FUT'
            
            // Expiry information
            $table->string('expiry', 10)->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->date('trading_date')->index();
            
            // Instrument details
            $table->unsignedBigInteger('instrument_token')->nullable();
            $table->string('exchange', 10)->default('NFO');
            $table->integer('lot_size')->default(1);
            
            // ==================== OI FIELDS ====================
            $table->bigInteger('daily_oi')->default(0);
            $table->bigInteger('daily_oi_prev')->default(0);
            $table->bigInteger('daily_oi_change')->nullable();
            $table->decimal('daily_oi_change_pct', 8, 2)->nullable();
            
            // OI Signal
            $table->string('direction', 20)->nullable(); // BULLISH, BEARISH, NEUTRAL
            $table->string('strength', 20)->nullable(); // STRONG, MODERATE, WEAK
            $table->string('market_bias', 50)->nullable();
            
            // ==================== IV FIELDS ====================
            $table->decimal('daily_iv', 8, 4)->nullable();
            $table->decimal('daily_iv_prev', 8, 4)->nullable();
            $table->decimal('daily_iv_change', 8, 4)->nullable();
            $table->decimal('daily_iv_change_pct', 8, 2)->nullable();
            
            // IV Signal
            $table->string('iv_direction', 20)->nullable(); // RISING, FALLING, NEUTRAL
            $table->string('iv_strength', 20)->nullable(); // STRONG, MODERATE, WEAK
            
            // ==================== CLOSE PRICE FIELDS ====================
            $table->decimal('daily_close', 12, 2)->nullable();
            $table->decimal('daily_close_prev', 12, 2)->nullable();
            $table->decimal('daily_close_change', 12, 2)->nullable();
            $table->decimal('daily_close_change_pct', 8, 2)->nullable();
            
            // ==================== BTST SIGNAL (Only for FUT) ====================
            $table->string('btst_signal', 20)->nullable(); // BUY, SELL, NEUTRAL
            $table->integer('btst_confidence')->nullable(); // 0-100
            $table->text('btst_reason')->nullable();
            
            // ==================== OTHER FIELDS ====================
            $table->decimal('spot_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            
            // ==================== INDEXES WITH SHORT NAMES ====================
            // Composite indexes with custom short names
            $table->index(['broker_api_id', 'underlying_symbol', 'trading_date'], 'idx_broker_symbol_date');
            $table->index(['broker_api_id', 'underlying_symbol', 'strike_position', 'trading_date'], 'idx_broker_symbol_pos_date');
            $table->index(['broker_api_id', 'underlying_symbol', 'option_type', 'trading_date'], 'idx_broker_symbol_type_date');
            
            // Unique constraint with short name
            $table->unique(['broker_api_id', 'trading_symbol', 'trading_date'], 'unq_broker_tsymbol_date');
            
            // Foreign key
            $table->foreign('broker_api_id', 'fk_osi_broker')
                  ->references('id')
                  ->on('broker_apis')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_strikes_individual');
    }
};
