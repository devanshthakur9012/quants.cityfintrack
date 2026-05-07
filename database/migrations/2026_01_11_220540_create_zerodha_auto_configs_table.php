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
        Schema::create('zerodha_auto_configs', function (Blueprint $table) {
             $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            
            // Order Configuration
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('NRML');
            $table->decimal('disc_ltp', 8, 2)->default(0)->comment('Discount % from LTP for LIMIT orders');
            $table->integer('quantity')->comment('Base quantity per order');
            
            // Pyramid Configuration
            $table->enum('pyramid_percent', ['100', '50', '33'])->default('100');
            $table->integer('pyramid_freq')->default(0)->comment('Minutes delay between pyramid levels');
            
            // Status
            $table->boolean('status')->default(true);
            
            // Tracking
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zerodha_auto_configs');
    }
};
