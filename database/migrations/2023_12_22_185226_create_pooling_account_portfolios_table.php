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
        Schema::create('pooling_account_portfolios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('broker_name', 255);
            $table->string('broker_logo', 255);
            $table->unsignedBigInteger('client_id');
            $table->string('scrip_name', 255);
            $table->date('bought_date');
            $table->integer('quantity');
            $table->decimal('bought_rate', 10, 2);
            $table->decimal('bought_amount', 10, 2);
            $table->decimal('market_price', 10, 2);
            $table->decimal('current_value', 10, 2);
            $table->decimal('change_percentage', 5, 2);
            $table->integer('no_of_pms_clients');

            $table->foreign('user_id')->references('id')->on('users');
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
        Schema::dropIfExists('pooling_account_portfolios');
    }
};
