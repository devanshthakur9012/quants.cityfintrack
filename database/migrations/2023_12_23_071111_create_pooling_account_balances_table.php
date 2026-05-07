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
        Schema::create('pooling_account_balances', function (Blueprint $table) {
            $table->id();
            $table->string('broker_name', 255);
            $table->string('broker_logo', 255);
            $table->unsignedBigInteger('user_id');
            $table->decimal('cash_balance', 10, 2);
            $table->decimal('unrealised_amount', 10, 2);
            $table->decimal('withdrawable_amount', 10, 2);
            $table->decimal('margin_amount', 10, 2);
            $table->unsignedBigInteger('pooling_account_id');

            $table->foreign('user_id')->references('id')->on('users');
            // Assuming 'pooling_account_id' references some other table, add the foreign key accordingly.
            $table->foreign('pooling_account_id')->references('id')->on('pooling_account_portfolios');
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
        Schema::dropIfExists('pooling_account_balances');
    }
};
