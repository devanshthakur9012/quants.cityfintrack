<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_daily_ohlc_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 50)->unique()->comment('NSE stock symbol e.g. RELIANCE, TCS');
            $table->string('exchange', 10)->default('NSE')->comment('Exchange: NSE or BSE');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable()->comment('Optional notes about the symbol');
            $table->timestamps();
 
            $table->index(['is_active', 'symbol']);
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('stock_daily_ohlc_symbols');
    }
};
