<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::create('auto_target_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            $table->string('broker_name');
            
            // Position details
            $table->string('tradingsymbol');
            $table->string('exchange');
            $table->string('product'); // MIS, NRML, CNC
            $table->bigInteger('instrument_token')->nullable();
            
            // Entry details
            $table->integer('quantity');
            $table->decimal('buy_price', 10, 2);
            $table->decimal('entry_value', 15, 2);
            
            // Target details
            $table->decimal('target_percentage', 5, 2)->default(20.00);
            $table->decimal('target_price', 10, 2);
            $table->decimal('current_price', 10, 2)->nullable();
            $table->decimal('current_profit', 15, 2)->nullable();
            $table->decimal('current_profit_percentage', 5, 2)->nullable();
            
            // Order details
            $table->string('target_order_id')->nullable();
            $table->string('exchange_order_id')->nullable();
            $table->enum('order_status', [
                'PENDING',      // Target order not placed yet
                'PLACED',       // Target order placed at exchange
                'TRIGGERED',    // Target price reached, order triggered
                'COMPLETED',    // Order executed successfully
                'CANCELLED',    // Order cancelled
                'FAILED',       // Order placement failed
                'EXPIRED'       // Position closed before target
            ])->default('PENDING');
            
            // Tracking
            $table->timestamp('position_entry_at')->nullable();
            $table->timestamp('target_placed_at')->nullable();
            $table->timestamp('target_triggered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            
            // Error handling
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            
            // Status flags
            $table->boolean('is_active')->default(true);
            $table->boolean('is_frozen')->default(false); // For freeze quantity handling
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('broker_api_id');
            $table->index('tradingsymbol');
            $table->index('order_status');
            $table->index('is_active');
            $table->index(['user_id', 'tradingsymbol', 'order_status']);
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_target_orders');
    }
};
