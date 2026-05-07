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
        Schema::create('pooling_account_ledgers', function (Blueprint $table) {
            $table->id();
            $table->string('broker_name', 255);
            $table->string('broker_logo', 255);
            $table->unsignedBigInteger('user_id');
            $table->string('scrip_name', 255);
            $table->date('bought_date');
            $table->decimal('bought_rate', 10, 2);
            $table->integer('quantity');
            $table->date('sold_date')->nullable();
            $table->decimal('sold_rate', 10, 2)->nullable();
            $table->decimal('profit_loss', 10, 2);
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
        Schema::dropIfExists('pooling_account_ledgers');
    }
};
