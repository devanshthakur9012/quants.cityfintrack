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
        Schema::create('early_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol_token', 100)->index();
            $table->string('underlying', 150);
            $table->string('symbol', 50)->unique()->index();
            $table->integer('step_value');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('early_symbols');
    }
};
