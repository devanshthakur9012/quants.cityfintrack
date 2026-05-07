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
        Schema::create('thematic_portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('stock_name', 255);
            $table->date('reco_date');
            $table->decimal('buy_price', 10, 2);
            $table->decimal('cmp', 10, 2);
            $table->decimal('pnl', 10, 2);
            $table->string('sector', 255);
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
        Schema::dropIfExists('thematic_portfolios');
    }
};
