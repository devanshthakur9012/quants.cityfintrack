<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('portfolio_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('broker_api_id')->constrained('broker_apis')->onDelete('cascade');
            $table->string('tradingsymbol');
            $table->string('exchange');
            $table->string('instrument_token')->nullable();
            $table->string('product'); // MIS, NRML, CNC
            $table->integer('quantity');
            $table->integer('overnight_quantity')->default(0);
            $table->decimal('average_price', 10, 2);
            $table->decimal('last_price', 10, 2);
            $table->decimal('pnl', 15, 2);
            $table->decimal('value', 15, 2);
            $table->string('buy_sell'); // BUY or SELL
            $table->timestamp('fetched_at');
            $table->timestamps();
            
            $table->index(['user_id', 'broker_api_id', 'tradingsymbol']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('portfolio_positions');
    }
};
