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
        Schema::create('option_strikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broker_api_id')->constrained('broker_apis')->onDelete('cascade');
            
            // Symbol information
            $table->string('underlying_symbol', 50)->index(); // NIFTY, BANKNIFTY, etc
            $table->string('trading_symbol', 100)->index(); // NIFTY25JAN24000CE
            $table->string('option_type', 2); // CE or PE
            $table->decimal('strike_price', 10, 2)->index();
            $table->string('strike_position', 10); // ATM-1, ATM, ATM+1
            
            // Expiry information
            $table->string('expiry', 20); // 25JAN, 30JAN, etc
            $table->date('expiry_date');
            
            // Zerodha specific
            $table->bigInteger('instrument_token')->nullable();
            $table->string('exchange', 10)->default('NFO');
            $table->integer('lot_size')->default(1);
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            
            // Composite indexes for faster queries
            $table->index(
                ['underlying_symbol', 'expiry', 'option_type'],
                'idx_os_symbol_expiry_type'
            );

            $table->index(
                ['underlying_symbol', 'strike_position', 'option_type'],
                'idx_os_symbol_strike_type'
            );

            $table->unique(['trading_symbol', 'broker_api_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_strikes');
    }
};
