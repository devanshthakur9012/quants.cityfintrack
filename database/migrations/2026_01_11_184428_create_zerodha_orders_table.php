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
        Schema::create('zerodha_orders', function (Blueprint $table) {
              $table->id();
            $table->string('buildup_type');
            $table->unsignedBigInteger('broker_api_id');
            $table->decimal('disc_ltp', 5, 2);
            $table->enum('order_type', ['LIMIT', 'MARKET']);
            $table->date('order_date');
            $table->enum('pyramid_percent', ['33', '50', '100'])->nullable();
            $table->enum('product', ['NRML', 'MIS']);
            $table->integer('quantity');
            $table->integer('pyramid_freq');
            $table->decimal('exit_1_qty', 8, 2)->default(0);
            $table->decimal('exit_1_target', 8, 2)->default(0);
            $table->decimal('exit_2_qty', 8, 2)->default(0);
            $table->decimal('exit_2_target', 8, 2)->default(0);
            $table->unsignedBigInteger('user_id');
            $table->tinyInteger('status')->default(1);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->foreign('broker_api_id')->references('id')->on('broker_apis');
            $table->foreign('user_id')->references('id')->on('users');
            
            // Prevent duplicate configurations
            $table->unique(['buildup_type', 'broker_api_id', 'user_id'], 'unique_historical_config');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zerodha_orders');
    }
};
