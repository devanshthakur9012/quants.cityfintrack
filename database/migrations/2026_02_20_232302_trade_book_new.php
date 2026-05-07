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

            // ── Who uploaded & which broker account ──────────────
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');        // FK → broker_apis.id
            $table->string('broker_name', 50);                  // e.g. "Zerodha"  (denormalized for easy display)
            $table->string('upload_month', 7);                  // YYYY-MM  e.g. "2026-02"

            // ── Trade data ───────────────────────────────────────
            $table->string('symbol', 60);                       // e.g. NIFTY26FEB24500CE
            $table->date('trade_date');
            $table->string('trade_time', 8)->nullable();        // HH:MM:SS extracted from execution time
            $table->enum('trade_type', ['buy', 'sell']);
            $table->decimal('quantity', 15, 2);
            $table->decimal('price', 15, 4);

            // ── Extra info ───────────────────────────────────────
            $table->string('exchange', 10)->nullable();         // NSE / BSE / NFO
            $table->string('segment', 10)->nullable();          // FO / EQ / CDS
            $table->string('trade_id', 50)->nullable();
            $table->string('order_id', 60)->nullable();
            $table->string('order_execution_time', 30)->nullable(); // raw ISO string e.g. 2026-02-01T09:49:08
            $table->date('expiry_date')->nullable();            // for F&O contracts (Zerodha last col)

            $table->timestamps();

            // ── Foreign keys ─────────────────────────────────────
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->foreign('broker_api_id')
                  ->references('id')->on('broker_apis')
                  ->onDelete('cascade');

            // ── Indexes ──────────────────────────────────────────
            $table->index(['user_id', 'broker_api_id', 'upload_month'], 'tb_user_broker_month');
            $table->index(['symbol', 'trade_date', 'trade_type'],       'tb_symbol_date_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_book');
    }
};
