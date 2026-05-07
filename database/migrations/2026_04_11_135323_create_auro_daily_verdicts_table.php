<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('auro_daily_verdicts', function (Blueprint $table) {
 
            $table->id();
 
            // ── Date & Identity ───────────────────────────────────────────────
            $table->date('trade_date')->unique()->comment('Date the verdict was generated (3PM)');
            $table->string('expiry_date', 20)->nullable()->comment('Active expiry used for analysis');
 
            // ── Final Decision ────────────────────────────────────────────────
            $table->enum('direction', ['BUY_CE', 'BUY_PE', 'NO_TRADE'])->comment('System verdict');
            $table->decimal('net_score', 6, 2)->default(0)->comment('Final net score: positive=bullish, negative=bearish');
            $table->enum('confidence', ['VERY_HIGH', 'HIGH', 'MEDIUM', 'LOW', 'NO_TRADE'])->default('LOW');
 
            // ── Recommended Strike ────────────────────────────────────────────
            $table->decimal('atm_strike', 10, 2)->nullable()->comment('Frozen ATM at 09:15 FUT close');
            $table->decimal('recommended_strike', 10, 2)->nullable()->comment('Recommended entry strike');
            $table->string('recommended_strike_position', 15)->nullable()->comment('ATM, ATM-1, ATM-3 etc');
            $table->decimal('recommended_strike_ltp', 10, 2)->nullable()->comment('Option LTP at 3PM for entry');
 
            // ── Signal A: OI Pressure ─────────────────────────────────────────
            $table->decimal('sig_a_score', 5, 2)->default(0)->comment('Signal A score (-3 to +3)');
            $table->decimal('ce_oi_today', 15, 0)->default(0);
            $table->decimal('ce_oi_5day_avg', 15, 2)->default(0);
            $table->decimal('ce_oi_change_pct', 8, 2)->default(0);
            $table->decimal('pe_oi_today', 15, 0)->default(0);
            $table->decimal('pe_oi_5day_avg', 15, 2)->default(0);
            $table->decimal('pe_oi_change_pct', 8, 2)->default(0);
            $table->decimal('ce_pe_ratio', 8, 4)->default(0)->comment('CE OI / PE OI');
            $table->string('sig_a_verdict', 30)->nullable()->comment('AGGRESSIVE_WRITING / ACCUMULATION / UNWINDING etc');
 
            // ── Signal B: Smart Money / Hidden Bet ───────────────────────────
            $table->decimal('sig_b_score', 5, 2)->default(0)->comment('Signal B score (-3 to +3)');
            $table->text('sig_b_far_otm_detail')->nullable()->comment('JSON: far OTM strike OI changes over 5 days');
            $table->tinyInteger('sig_b_hidden_bear_days')->default(0)->comment('How many consecutive days ATM-3/4/5 PE grew');
            $table->tinyInteger('sig_b_hidden_bull_days')->default(0)->comment('How many consecutive days ATM+3/4/5 CE grew');
            $table->string('sig_b_verdict', 40)->nullable()->comment('HIDDEN_BEAR_ACCUMULATION / HIDDEN_BULL_ACCUMULATION / NONE');
 
            // ── Signal C: Price Structure ─────────────────────────────────────
            $table->decimal('sig_c_score', 5, 2)->default(0)->comment('Signal C score (-2 to +2)');
            $table->decimal('fut_close_3pm', 10, 2)->nullable()->comment('FUT close at 14:45 (proxy for 3PM)');
            $table->decimal('support_20d', 10, 2)->nullable()->comment('20-day low (support)');
            $table->decimal('resistance_20d', 10, 2)->nullable()->comment('20-day high (resistance)');
            $table->decimal('dist_to_support_pct', 6, 2)->nullable()->comment('% above support');
            $table->decimal('dist_to_resistance_pct', 6, 2)->nullable()->comment('% below resistance');
            $table->string('fut_oi_type', 30)->nullable()->comment('LONG_BUILDUP/SHORT_BUILDUP/SHORT_COVER/LONG_UNWIND');
            $table->string('sig_c_verdict', 30)->nullable();
 
            // ── Signal D: Market Alignment ────────────────────────────────────
            $table->decimal('sig_d_score', 5, 2)->default(0)->comment('Signal D score (-3 to +3)');
            $table->string('nifty_5d_trend', 15)->nullable()->comment('BULLISH / BEARISH / SIDEWAYS');
            $table->string('pharma_5d_trend', 15)->nullable()->comment('BULLISH / BEARISH / SIDEWAYS');
            $table->decimal('nifty_close_3pm', 10, 2)->nullable();
            $table->string('sig_d_verdict', 40)->nullable();
 
            // ── Signal E: Strike Intent ───────────────────────────────────────
            $table->string('sig_e_verdict', 50)->nullable()->comment('Which far strike is accumulating');
            $table->text('sig_e_detail')->nullable()->comment('JSON: per-strike accumulation detail');
            $table->decimal('sig_e_suggested_strike', 10, 2)->nullable()->comment('Strike suggested by smart money detection');
 
            // ── Market Context ────────────────────────────────────────────────
            $table->decimal('auro_volatility_5d', 8, 4)->nullable()->comment('5-day historical vol of Auro');
            $table->string('market_regime', 20)->nullable()->comment('TRENDING / RANGING / VOLATILE');
 
            // ── Veto Conditions ───────────────────────────────────────────────
            // If any veto fires, direction is forced to NO_TRADE regardless of score
            $table->tinyInteger('veto_market_opposing')->default(0)->comment('1 = market strongly opposes signal');
            $table->tinyInteger('veto_low_volume')->default(0)->comment('1 = option volume too thin for reliable signal');
            $table->tinyInteger('veto_expiry_week')->default(0)->comment('1 = expiry within 2 days (gamma risk)');
            $table->tinyInteger('veto_conflicting_signals')->default(0)->comment('1 = signals A/B/C all disagree');
 
            // ── Actual Result (filled next day) ──────────────────────────────
            $table->decimal('actual_open_next_day', 10, 2)->nullable()->comment('Stock open next day 09:20');
            $table->decimal('actual_option_open', 10, 2)->nullable()->comment('Option open next day 09:20');
            $table->decimal('actual_option_high', 10, 2)->nullable()->comment('Option high by 10:00AM');
            $table->decimal('actual_pnl_pct', 8, 2)->nullable()->comment('% P&L if exited at open spike');
            $table->tinyInteger('was_correct')->nullable()->comment('1=won, 0=lost, NULL=no trade taken');
            $table->string('miss_reason', 100)->nullable()->comment('Why it failed: MARKET_REVERSED / SECTOR_DRAG / BAD_SIGNAL etc');
            $table->text('post_trade_notes')->nullable()->comment('Manual notes after reviewing the day');
 
            // ── Meta ──────────────────────────────────────────────────────────
            $table->string('generated_at', 30)->nullable()->comment('Timestamp verdict was generated');
            $table->tinyInteger('is_backtest')->default(0)->comment('1 = backtest, 0 = live');
 
            $table->timestamps();
 
            // Indexes
            $table->index('trade_date');
            $table->index('direction');
            $table->index('confidence');
            $table->index(['trade_date', 'direction']);
            $table->index('was_correct');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('auro_daily_verdicts');
    }
};
