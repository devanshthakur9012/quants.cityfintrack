<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signal_predictions', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 30)->index();
            $table->date('signal_date')->index();          // the day signal was generated (EOD)
            $table->date('trade_date')->index();           // the next trading day = entry day
            $table->string('action', 20);                  // BUY_CE | BUY_PE | AVOID
            $table->string('bias', 20)->nullable();        // BULLISH | BEARISH | null
            $table->unsignedTinyInteger('confidence');     // 0–100
            $table->string('strength', 20)->nullable();   // STRONG | MODERATE | WEAK | AVOID

            // PCR indicator
            $table->decimal('pcr_eod', 6, 3)->nullable();          // final PCR at 14:45
            $table->decimal('pcr_change', 8, 4)->nullable();        // PCR change vs prev day
            $table->string('pcr_bias', 20)->nullable();            // BULLISH|BEARISH|NEUTRAL

            // OI indicator
            $table->decimal('oi_long_buildup', 8, 2)->nullable();
            $table->decimal('oi_short_buildup', 8, 2)->nullable();
            $table->decimal('oi_short_covering', 8, 2)->nullable();
            $table->decimal('oi_long_unwind', 8, 2)->nullable();
            $table->string('oi_bias', 20)->nullable();             // BULLISH|BEARISH|MIXED

            // Price indicator
            $table->decimal('price_change_pct', 6, 3)->nullable();
            $table->string('price_direction', 10)->nullable();      // UP|DOWN|FLAT
            $table->string('price_strength', 15)->nullable();       // STRONG|MODERATE|WEAK
            $table->decimal('last_hour_change_pct', 6, 3)->nullable();
            $table->string('last_hour_direction', 10)->nullable();  // UP|DOWN|FLAT

            // Alignment check — were all 3 indicators agreeing?
            $table->unsignedTinyInteger('indicators_aligned')->default(0); // 0|1|2|3

            // ATM reference
            $table->decimal('atm_strike', 10, 2)->nullable();
            $table->decimal('fut_close', 10, 2)->nullable();       // FUT close at signal time

            // Outcome (filled next day by cron)
            $table->enum('outcome', ['WIN', 'LOSS', 'FLAT', 'PENDING'])->default('PENDING');
            $table->decimal('next_day_open', 10, 2)->nullable();
            $table->decimal('next_day_close', 10, 2)->nullable();
            $table->decimal('next_day_high', 10, 2)->nullable();
            $table->decimal('next_day_low', 10, 2)->nullable();
            $table->decimal('next_day_change_pct', 6, 3)->nullable();
            $table->boolean('hit_t1')->default(false);  // +1% target
            $table->boolean('hit_t2')->default(false);  // +2% target
            $table->boolean('hit_sl')->default(false);  // SL hit (−0.5%)

            // Meta
            $table->json('reasons')->nullable();
            $table->json('indicators_detail')->nullable();  // full dump for debugging
            $table->string('version', 10)->default('v4');
            $table->timestamps();

            $table->unique(['symbol', 'signal_date', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_predictions');
    }
};