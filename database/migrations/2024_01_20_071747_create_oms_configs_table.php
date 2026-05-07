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
        Schema::create('oms_configs', function (Blueprint $table) {
            $table->id();
            $table->string('symbol_name',155)->nullable();
            $table->tinyInteger('signal_tf')->nullable();
            $table->string('ce_symbol_name',155)->nullable();
            $table->string('pe_symbol_name',155)->nullable();
            $table->integer('broker_api_id')->nullable();
            $table->string('entry_point',155)->nullable();
            $table->string('strategy_name',155)->nullable();
            $table->string('product',155)->nullable();
            $table->string('order_type',155)->nullable();
            $table->integer('pyramid_percent')->nullable();
            $table->integer('ce_pyramid_1')->nullable();
            $table->integer('ce_pyramid_2')->nullable();
            $table->integer('ce_pyramid_3')->nullable();
            $table->integer('pe_pyramid_1')->nullable();
            $table->integer('pe_pyramid_2')->nullable();
            $table->integer('pe_pyramid_3')->nullable();
            $table->string('txn_type',155)->nullable();
            $table->integer('ce_quantity')->nullable();
            $table->integer('pe_quantity')->nullable();
            $table->integer('pyramid_freq')->nullable();
            $table->integer('exit_1_qty')->nullable();
            $table->integer('exit_1_target')->nullable();
            $table->integer('exit_2_qty')->nullable();
            $table->integer('exit_2_target')->nullable();
            $table->bigInteger('user_id')->nullable();
            $table->dateTime('cron_run_at')->nullable();
            $table->tinyInteger('is_api_pushed')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('oms_configs');
    }
};
