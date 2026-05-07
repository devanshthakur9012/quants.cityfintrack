<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('pattern_stats', function (Blueprint $table) {
            $table->id();
 
            // Pattern identifier — format: TIME_BUCKET|STR{n}|DIRECTION|VOL_BUCKET|RANGE_BUCKET|ZONE
            // e.g. "OPENING|STR3|BULLISH|SPIKE|HIGH|AGGRESSIVE"
            $table->string('pattern_key', 100)->unique()->index();
 
            // Outcome counters
            $table->unsignedInteger('total_trades')->default(0);
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
            $table->unsignedInteger('expired')->default(0);
 
            // Performance averages (running average, updated on each new trade)
            $table->decimal('avg_rr', 10, 4)->default(0);        // average exit_roi_pct
            $table->decimal('avg_move_pct', 10, 4)->default(0);  // average fut_range_pct
            $table->decimal('total_pl', 12, 2)->default(0);       // cumulative P/L in ₹
 
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('pattern_stats');
    }
};
