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
        Schema::table('option_iv_data', function (Blueprint $table) {
            // Add calculated IV column (this is our Black-Scholes calculated IV)
            // The existing 'iv' column will store our calculated IV
            
            // Add Zerodha's IV if they provide it (for comparison)
            $table->decimal('zerodha_iv', 10, 6)->nullable()->after('iv');
            
            // Add calculation metadata
            $table->integer('days_to_expiry')->nullable()->after('future_price');
            $table->decimal('risk_free_rate', 5, 4)->nullable()->after('days_to_expiry')
                  ->comment('Risk-free rate used in IV calculation');
            
            // Add index for faster queries
            $table->index(['symbol', 'expiry', 'timestamp'], 'idx_symbol_expiry_timestamp');
            $table->index(['atm_position', 'timestamp'], 'idx_atm_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('option_iv_data', function (Blueprint $table) {
            $table->dropColumn(['zerodha_iv', 'days_to_expiry', 'risk_free_rate']);
            $table->dropIndex('idx_symbol_expiry_timestamp');
            $table->dropIndex('idx_atm_timestamp');
        });
    }
};
