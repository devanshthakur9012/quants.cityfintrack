<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutual_funds', function (Blueprint $table) {
            $table->id();
 
            $table->string('name');                  // Full name e.g. Parag Parikh Flexi Cap Fund
            $table->string('code')->nullable();       // Short code e.g. PPFAS
            $table->string('category')->nullable();   // Flexi Cap, Midcap, Small Cap
            $table->string('amc')->nullable();        // Asset Management Company
            $table->string('plan_type')->nullable();  // Direct / Regular
            $table->string('option')->nullable();     // Growth / IDCW
 
            $table->boolean('status')->default(true);
 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutual_funds');
    }
};
