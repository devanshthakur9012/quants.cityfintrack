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
        Schema::create('option_oi_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('option_strike_id')->constrained('option_strikes')->onDelete('cascade');
            
            // Timestamp for 1-minute candles
            $table->timestamp('timestamp')->index();
            
            // Open Interest data
            $table->bigInteger('oi')->default(0); // Current OI
            $table->bigInteger('oi_change')->default(0); // Change from previous candle
            $table->decimal('oi_change_percent', 10, 2)->default(0); // % change
            
            // Volume
            $table->bigInteger('volume')->default(0);
            
            // Price data (for reference, not used in OI analysis)
            $table->decimal('ltp', 10, 2)->default(0); // Last Traded Price
            $table->decimal('open', 10, 2)->nullable();
            $table->decimal('high', 10, 2)->nullable();
            $table->decimal('low', 10, 2)->nullable();
            $table->decimal('close', 10, 2)->nullable();
            
            $table->timestamps();
            
            // Indexes for faster queries
            $table->index(['option_strike_id', 'timestamp']);
            $table->unique(['option_strike_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('option_oi_data');
    }
};
