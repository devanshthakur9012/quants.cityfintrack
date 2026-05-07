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
        Schema::create('market_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('market_name');          
            $table->string('holiday_name');
            $table->date('holiday_date');   
            $table->enum('status', ['active', 'inactive'])->default('active');
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
        Schema::dropIfExists('market_holidays');
    }
};
