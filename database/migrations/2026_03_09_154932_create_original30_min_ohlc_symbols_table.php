<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('original_30min_ohlc_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 30)->unique()->comment('Base symbol e.g. NIFTY, BANKNIFTY');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default symbols
        DB::table('original_30min_ohlc_symbols')->insert([
            ['symbol' => 'NIFTY',      'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['symbol' => 'SENSEX',     'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('original_30min_ohlc_symbols');
    }
};
