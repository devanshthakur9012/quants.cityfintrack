<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutual_fund_stocks', function (Blueprint $table) {
            $table->id();
 
            $table->foreignId('mutual_fund_id')
                  ->constrained('mutual_funds')
                  ->cascadeOnDelete();
 
            $table->string('stock_name');                          // e.g. HDFC Bank Ltd
            $table->string('stock_symbol');                        // e.g. HDFCBANK
            $table->string('sector')->nullable();                  // e.g. Financial Services
            $table->decimal('allocation_percentage', 5, 2);        // e.g. 8.25
 
            $table->date('holding_date')->nullable();               // Month of holding snapshot e.g. 2025-03-01
 
            $table->boolean('status')->default(true);
 
            $table->timestamps();
 
            // Prevent duplicate stock in same fund for same holding month
            $table->unique(['mutual_fund_id', 'stock_symbol', 'holding_date'], 'fund_stock_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutual_fund_stocks');
    }
};
