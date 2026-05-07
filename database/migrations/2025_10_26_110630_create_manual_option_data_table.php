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
        Schema::create('manual_option_data', function (Blueprint $table) {
            $table->id();
            $table->string('underlying'); // e.g., NIFTY, BANKNIFTY
            $table->date('date');

            // ATM-2 Strike
            $table->decimal('atm_minus_2_ce_oi', 15, 2)->nullable();
            $table->decimal('atm_minus_2_pe_oi', 15, 2)->nullable();

            // ATM-1 Strike
            $table->decimal('atm_minus_1_ce_oi', 15, 2)->nullable();
            $table->decimal('atm_minus_1_pe_oi', 15, 2)->nullable();

            // ATM Strike
            $table->decimal('atm_ce_oi', 15, 2)->nullable();
            $table->decimal('atm_pe_oi', 15, 2)->nullable();

            // ATM+1 Strike
            $table->decimal('atm_plus_1_ce_oi', 15, 2)->nullable();
            $table->decimal('atm_plus_1_pe_oi', 15, 2)->nullable();

            // ATM+2 Strike
            $table->decimal('atm_plus_2_ce_oi', 15, 2)->nullable();
            $table->decimal('atm_plus_2_pe_oi', 15, 2)->nullable();

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
        Schema::dropIfExists('manual_option_data');
    }
};
