<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portfolio_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            
            // ONLY 2 SETTINGS - Profit percentages
            $table->decimal('old_position_profit_percent', 5, 2)->default(20.00)->comment('Profit % for positions before today');
            $table->decimal('fresh_position_profit_percent', 5, 2)->default(10.00)->comment('Profit % for today\'s positions');
            
            $table->timestamps();
            
            // Indexes
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_configs');
    }
};
