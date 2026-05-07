<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up()
    {
        Schema::create('broker_amo_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            $table->enum('symbol_type', ['CE', 'PE', 'BOTH'])->comment('CE=Call, PE=Put, BOTH=CE+PE');
            $table->decimal('old_position_profit_percent', 8, 2)->default(20.00);
            $table->decimal('fresh_position_profit_percent', 8, 2)->default(10.00);
            $table->boolean('skip_old_positions')->default(false)->comment('Skip old positions if true');
            $table->boolean('skip_fresh_positions')->default(false)->comment('Skip fresh positions if true');
            $table->date('config_date')->comment('Date for which this config is valid');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('user_id');
            $table->index('broker_api_id');
            $table->index('config_date');
            $table->unique(['broker_api_id', 'symbol_type', 'config_date'], 'unique_broker_symbol_date');

            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('broker_amo_configs');
    }
};
