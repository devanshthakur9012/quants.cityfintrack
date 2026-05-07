<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('signal_time_performance', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->index();
            $table->string('time_slot', 5)->index();   // e.g. "09:25"
            $table->unsignedSmallInteger('lookback_days')->default(30);
            $table->unsignedInteger('total_signals')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->decimal('accuracy', 5, 2)->default(0);  // e.g. 72.50
            $table->unsignedInteger('ce_wins')->default(0);
            $table->unsignedInteger('pe_wins')->default(0);
            $table->string('zone', 10)->default('WEAK');    // STRONG | MODERATE | WEAK
            $table->timestamps();
 
            $table->unique(['symbol', 'time_slot', 'lookback_days'], 'uq_stp_symbol_slot_days');
            $table->index(['symbol', 'lookback_days'], 'idx_stp_symbol_days');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('signal_time_performance');
    }
};
