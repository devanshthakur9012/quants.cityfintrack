<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::create('mcx_3hr_ohlc_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 50)->unique();   // CRUDEOIL, CRUDEOILM, NATGAS
            $table->integer('strike_interval')->default(50); // strike spacing
            $table->string('exchange', 10)->default('MCX');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('mcx_3hr_ohlc_symbols')->insert([
            ['symbol' => 'CRUDEOIL',  'strike_interval' => 50,  'exchange' => 'MCX', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['symbol' => 'CRUDEOILM', 'strike_interval' => 10,  'exchange' => 'MCX', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['symbol' => 'NATGAS',    'strike_interval' => 5,   'exchange' => 'MCX', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mcx_3hr_ohlc_symbols');
    }
};
