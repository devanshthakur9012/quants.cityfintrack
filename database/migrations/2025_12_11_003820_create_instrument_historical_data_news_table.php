<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('instrument_historical_data_news', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('instrument_chain_id');
            $table->date('date');
            $table->string('underlying', 50)->index();
            $table->string('symbol', 100)->index();
            $table->enum('type', ['FUT', 'CE', 'PE'])->index();
            $table->decimal('strike_price', 10, 2)->nullable()->index();
            
            // OHLCV Data
            $table->decimal('open', 12, 2)->nullable();
            $table->decimal('high', 12, 2)->nullable();
            $table->decimal('low', 12, 2)->nullable();
            $table->decimal('close', 12, 2)->nullable();
            $table->decimal('ltp', 12, 2)->nullable();
            $table->bigInteger('volume')->default(0);
            $table->bigInteger('oi')->nullable(); // Open Interest
            
            // Additional metrics
            $table->decimal('price_change', 12, 2)->nullable();
            $table->decimal('price_change_percent', 8, 4)->nullable();
            $table->bigInteger('oi_change')->nullable();
            $table->decimal('oi_change_percent', 8, 4)->nullable();
            
            // Greeks (can be calculated separately)
            $table->decimal('iv', 8, 4)->nullable(); // Implied Volatility
            $table->decimal('delta', 8, 4)->nullable();
            $table->decimal('gamma', 8, 6)->nullable();
            $table->decimal('theta', 8, 4)->nullable();
            $table->decimal('vega', 8, 4)->nullable();
            
            // Data quality
            $table->decimal('data_quality_score', 5, 2)->default(100.00);
            $table->json('missing_fields')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('instrument_historical_data_news');
    }
};
