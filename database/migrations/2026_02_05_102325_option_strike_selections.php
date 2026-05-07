// database/migrations/YYYY_MM_DD_create_option_strike_selections_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('option_strike_selections', function (Blueprint $table) {
            $table->id();
            $table->date('trade_date');
            $table->string('future_symbol', 50); // e.g., NIFTY26JANFUT
            $table->string('base_symbol', 30); // e.g., NIFTY
            $table->decimal('future_price', 10, 2);
            
            // ATM Strike Info
            $table->decimal('atm_strike', 10, 2);
            
            // CE Strikes (ATM, ATM+1, ATM+2)
            $table->decimal('ce_atm_strike', 10, 2);
            $table->string('ce_atm_symbol', 50);
            $table->integer('ce_atm_oi')->default(0);
            $table->decimal('ce_atm_fair_price', 10, 2)->nullable();
            $table->decimal('ce_atm_ltp', 10, 2)->nullable();
            
            $table->decimal('ce_atm1_strike', 10, 2);
            $table->string('ce_atm1_symbol', 50);
            $table->integer('ce_atm1_oi')->default(0);
            $table->decimal('ce_atm1_fair_price', 10, 2)->nullable();
            $table->decimal('ce_atm1_ltp', 10, 2)->nullable();
            
            $table->decimal('ce_atm2_strike', 10, 2);
            $table->string('ce_atm2_symbol', 50);
            $table->integer('ce_atm2_oi')->default(0);
            $table->decimal('ce_atm2_fair_price', 10, 2)->nullable();
            $table->decimal('ce_atm2_ltp', 10, 2)->nullable();
            
            // PE Strikes (ATM, ATM-1, ATM-2)
            $table->decimal('pe_atm_strike', 10, 2);
            $table->string('pe_atm_symbol', 50);
            $table->integer('pe_atm_oi')->default(0);
            $table->decimal('pe_atm_fair_price', 10, 2)->nullable();
            $table->decimal('pe_atm_ltp', 10, 2)->nullable();
            
            $table->decimal('pe_atm1_strike', 10, 2);
            $table->string('pe_atm1_symbol', 50);
            $table->integer('pe_atm1_oi')->default(0);
            $table->decimal('pe_atm1_fair_price', 10, 2)->nullable();
            $table->decimal('pe_atm1_ltp', 10, 2)->nullable();
            
            $table->decimal('pe_atm2_strike', 10, 2);
            $table->string('pe_atm2_symbol', 50);
            $table->integer('pe_atm2_oi')->default(0);
            $table->decimal('pe_atm2_fair_price', 10, 2)->nullable();
            $table->decimal('pe_atm2_ltp', 10, 2)->nullable();
            
            // Selected Strikes (Highest OI)
            $table->string('selected_ce_symbol', 50)->nullable();
            $table->decimal('selected_ce_strike', 10, 2)->nullable();
            $table->integer('selected_ce_oi')->default(0);
            $table->decimal('selected_ce_fair_price', 10, 2)->nullable();
            
            $table->string('selected_pe_symbol', 50)->nullable();
            $table->decimal('selected_pe_strike', 10, 2)->nullable();
            $table->integer('selected_pe_oi')->default(0);
            $table->decimal('selected_pe_fair_price', 10, 2)->nullable();
            
            $table->string('option_series', 20)->default('current'); // current or next
            $table->date('expiry_date')->nullable();

            $table->string('ce_atm_valuation', 20)->nullable();
            $table->string('ce_atm1_valuation', 20)->nullable();
            $table->string('ce_atm2_valuation', 20)->nullable();
            
            $table->string('pe_atm_valuation', 20)->nullable();
            $table->string('pe_atm1_valuation', 20)->nullable();
            $table->string('pe_atm2_valuation', 20)->nullable();
            
            // Add recommendation fields
            $table->string('selected_ce_valuation', 20)->nullable();
            $table->string('selected_ce_recommendation', 50)->nullable();
            
            $table->string('selected_pe_valuation', 20)->nullable();
            $table->string('selected_pe_recommendation', 50)->nullable();
            
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            
            $table->timestamps();
            
            $table->unique(['trade_date', 'future_symbol', 'option_series'], 'unique_daily_selection');
            $table->index(['trade_date', 'base_symbol']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('option_strike_selections');
    }
};