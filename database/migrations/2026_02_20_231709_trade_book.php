<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trade_book', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('broker', 50)->default('Zerodha');
            $table->string('upload_month', 7);          // YYYY-MM  e.g. 2026-02
            $table->string('symbol', 60);               // e.g. NIFTY26FEB24500CE
            $table->date('trade_date');
            $table->string('trade_time', 20)->nullable(); // HH:MM:SS from Order Execution Time
            $table->enum('trade_type', ['buy', 'sell']);
            $table->decimal('quantity', 15, 2);
            $table->decimal('price', 15, 4);
            $table->string('exchange', 10)->nullable();   // NSE / BSE
            $table->string('segment', 10)->nullable();    // FO / EQ / CDS
            $table->string('trade_id', 50)->nullable();
            $table->string('order_id', 60)->nullable();
            $table->string('order_execution_time', 30)->nullable(); // raw ISO string
            $table->date('expiry_date')->nullable();      // Zerodha col 15
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'broker', 'upload_month']);
            $table->index(['symbol', 'trade_date', 'trade_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_book');
    }
};
