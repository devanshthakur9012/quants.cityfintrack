<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('expiry_monitored', function (Blueprint $table) {
            $table->id();
            $table->string('symbol'); // NIFTY, BANKNIFTY, SENSEX
            $table->string('exchange')->default('NSE');
            $table->string('instrument_token');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();
            
            $table->index(['symbol', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('expiry_monitored');
    }
};
