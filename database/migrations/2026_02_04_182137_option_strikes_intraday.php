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
        Schema::create('option_strikes_intraday', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('broker_api_id');
            
            // Symbol info
            $table->string('underlying_symbol', 50)->index();
            $table->string('trading_symbol', 100)->nullable();
            $table->enum('option_type', ['FUT', 'CE', 'PE'])->index();
            $table->decimal('strike_price', 10, 2)->nullable();
            $table->enum('strike_position', ['FUT', 'CE_MERGED', 'PE_MERGED'])->index();
            
            // Expiry info
            $table->string('expiry', 10)->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->date('trading_date')->index();
            
            // Instrument info
            $table->string('instrument_token', 50)->nullable();
            $table->string('exchange', 10)->default('NFO');
            $table->integer('lot_size')->default(1);
            
            // OI Data (Previous day 12:15 to Current day 12:15)
            $table->bigInteger('intraday_oi')->default(0);
            $table->bigInteger('intraday_oi_prev')->default(0);
            $table->bigInteger('intraday_oi_change')->nullable();
            $table->decimal('intraday_oi_change_pct', 10, 2)->nullable();
            
            // IV Data (Intraday)
            $table->decimal('intraday_iv', 10, 4)->nullable();
            $table->decimal('intraday_iv_prev', 10, 4)->nullable();
            $table->decimal('intraday_iv_change', 10, 4)->nullable();
            $table->decimal('intraday_iv_change_pct', 10, 2)->nullable();
            
            // OI Signal fields
            $table->string('direction', 20)->nullable()->index();
            $table->string('strength', 20)->nullable();
            $table->string('market_bias', 50)->nullable();
            
            // IV Signal fields
            $table->string('iv_direction', 20)->nullable();
            $table->string('iv_strength', 20)->nullable();
            
            // BTST Signal fields (only for FUT)
            $table->string('btst_signal', 20)->nullable();
            $table->integer('btst_confidence')->nullable();
            $table->text('btst_reason')->nullable();
            
            // Other fields
            $table->decimal('spot_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(
                ['broker_api_id', 'underlying_symbol', 'trading_date'],
                'broker_symbol_date'
            );

            $table->index(
                ['broker_api_id', 'underlying_symbol', 'strike_position', 'trading_date'],
                'broker_symbol_position_date'
            );

            
            // Foreign key
            $table->foreign('broker_api_id')
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
        Schema::dropIfExists('option_strikes_intraday');
    }
};
