<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pyramid_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            
            // Contract Details
            $table->string('symbol');
            $table->date('expiry_date');
            $table->decimal('strike_price', 10, 2);
            $table->enum('option_type', ['CE', 'PE']);
            $table->enum('transaction_type', ['BUY', 'SELL']);
            
            // Pricing Parameters
            $table->decimal('manual_ltp', 10, 2);
            $table->decimal('base_discount_pct', 8, 4);
            $table->decimal('discount_increment_pct', 8, 4);
            
            // Quantity Parameters
            $table->integer('lots_per_order');
            $table->integer('num_pyramids');
            $table->integer('lot_size');
            
            // Status
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'partial'])->default('pending');
            $table->integer('orders_placed')->default(0);
            $table->text('error_message')->nullable();
            
            // Metadata
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            
            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pyramid_orders');
    }
};
