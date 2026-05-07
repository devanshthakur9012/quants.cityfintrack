<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('30min_ohlc_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default symbols
        DB::table('30min_ohlc_symbols')->insert([
            ['symbol' => 'NIFTY',     'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['symbol' => 'BANKNIFTY', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['symbol' => 'MCX',       'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['symbol' => 'BSE',       'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('30min_ohlc_symbols');
    }
};
