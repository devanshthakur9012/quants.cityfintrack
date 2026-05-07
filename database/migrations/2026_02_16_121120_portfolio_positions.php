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
        Schema::table('portfolio_positions', function (Blueprint $table) {
            // Add purchase date and time tracking
            $table->timestamp('purchase_date')->nullable()->after('product');
            $table->decimal('purchase_price', 10, 2)->nullable()->after('purchase_date');
            
            // Add profit target fields
            $table->decimal('target_profit_percent', 5, 2)->nullable()->after('purchase_price');
            $table->decimal('target_sell_price', 10, 2)->nullable()->after('target_profit_percent');
            
            // Add order tracking
            $table->string('square_off_order_id')->nullable()->after('target_sell_price');
            $table->enum('square_off_status', ['pending', 'placed', 'executed', 'failed'])->default('pending')->after('square_off_order_id');
            
            // Add status to track if position is closed
            $table->enum('position_status', ['open', 'closed'])->default('open')->after('square_off_status');
            
            // Add index for date filtering and status
            $table->index('purchase_date');
            $table->index(['user_id', 'purchase_date']);
            $table->index('position_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('portfolio_positions', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_date',
                'purchase_price',
                'target_profit_percent',
                'target_sell_price',
                'square_off_order_id',
                'square_off_status',
                'position_status'
            ]);
        });
    }
};
