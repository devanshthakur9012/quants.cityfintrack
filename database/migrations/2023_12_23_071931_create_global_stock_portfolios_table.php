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
        Schema::create('global_stock_portfolios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('broker_name', 255);
            $table->string('stock_name', 255);
            $table->integer('quantity');
            $table->date('buy_date');
            $table->decimal('buy_price', 10, 2);
            $table->decimal('cmp', 10, 2);
            $table->decimal('current_value', 10, 2);
            $table->decimal('profit_loss', 10, 2);
            $table->string('sector', 255);
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
        Schema::dropIfExists('global_stock_portfolios');
    }
};
