<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('symbols_monitored', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broker_api_id')->constrained('broker_apis')->onDelete('cascade');
            $table->string('symbol', 50); // e.g., RELIANCE, TCS
            $table->string('underlying_name')->nullable(); // Full company name
            $table->string('exchange', 10)->default('NSE'); // NSE, BSE
            $table->string('instrument_type', 10)->default('EQ'); // EQ, FUT, CE, PE
             $table->string('interval', 20)->default('minute'); 
            $table->string('trading_symbol')->nullable(); // Full trading symbol from Zerodha
            $table->bigInteger('instrument_token')->nullable(); // Zerodha instrument token
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['broker_api_id', 'symbol']);
            $table->index('is_active');
            $table->unique([
                'broker_api_id',
                'symbol',
                'exchange',
                'interval'
            ]);
        });
    }

    public function down()
    {
        Schema::dropIfExists('symbols_monitored');
    }
};
