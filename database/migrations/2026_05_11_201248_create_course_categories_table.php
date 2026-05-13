<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('course_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();          // FontAwesome class e.g. fa-chart-line
            $table->string('color', 20)->nullable();     // hex color for badge
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(1);   // 1=active 0=inactive
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('course_categories');
    }
};
