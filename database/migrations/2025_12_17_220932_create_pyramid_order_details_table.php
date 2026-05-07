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
        Schema::create('pyramid_order_details', function (Blueprint $table) {
                        $table->id();
            $table->unsignedBigInteger('pyramid_order_id');
            $table->integer('pyramid_index');
            
            // Calculated Values
            $table->decimal('effective_discount_pct', 8, 4);
            $table->decimal('order_price', 10, 2);
            $table->integer('quantity');
            
            // Angel Order Response
            $table->string('angel_order_id')->nullable();
            $table->string('angel_symbol')->nullable();
            $table->string('angel_token')->nullable();
            $table->enum('order_status', ['pending', 'placed', 'rejected', 'complete', 'failed'])->default('pending');
            $table->text('status_message')->nullable();
            
            // Order Timestamps
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            
            $table->foreign('pyramid_order_id')->references('id')->on('pyramid_orders')->onDelete('cascade');
            
            $table->index('pyramid_order_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pyramid_order_details');
    }
};
