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
        Schema::create('portfolio_sell_order_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('symbol_type', 10); // 'CE' or 'PE' or 'EQUITY' etc.
            $table->decimal('old_position_profit_percent', 8, 2)->default(20.00);
            $table->decimal('fresh_position_profit_percent', 8, 2)->default(10.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'symbol_type']); // One config per symbol type per user
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_sell_order_configs');
    }
};
