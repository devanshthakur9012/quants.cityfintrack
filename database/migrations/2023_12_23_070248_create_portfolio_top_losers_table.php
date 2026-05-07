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
        Schema::create('portfolio_top_losers', function (Blueprint $table) {
            $table->id();
            $table->string('stock_name', 255);
            $table->decimal('avg_buy_price', 10, 2);
            $table->decimal('cmp', 10, 2);
            $table->decimal('change_percentage', 5, 2);
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
        Schema::dropIfExists('portfolio_top_losers');
    }
};
