<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('mcx_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 50)->unique()
                  ->comment('Base MCX symbol — must match `name` column in zerodha_instruments');
            $table->string('exchange', 10)->default('MCX')
                  ->comment('MCX — must match `exchange` in zerodha_instruments');
            $table->decimal('strike_interval', 10, 2)
                  ->comment('Custom ATM rounding step — NOT in zerodha_instruments');
            $table->string('unit', 20)->nullable()
                  ->comment('Commodity unit e.g. BBL, MMBTU, KG, MT — NOT in zerodha_instruments');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcx_symbols');
    }
};
