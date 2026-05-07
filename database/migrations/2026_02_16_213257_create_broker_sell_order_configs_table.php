<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('broker_sell_order_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            $table->enum('symbol_type', ['CE', 'PE', 'BOTH'])->comment('CE=Call, PE=Put, BOTH=CE+PE');
            $table->decimal('old_position_profit_percent', 8, 2)->default(20.00);
            $table->decimal('fresh_position_profit_percent', 8, 2)->default(10.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('broker_api_id');
            $table->unique(['broker_api_id', 'symbol_type'], 'unique_broker_symbol');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('broker_sell_order_configs');
    }
};
