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
        Schema::table('users', function (Blueprint $table) {
            $table->string('dob')->nullable();
            $table->string('passport_id')->nullable();
            $table->string('investment_amount')->nullable();
            $table->string('scheme_name')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_amount_no')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->text('bank_address')->nullable();
            $table->string('tds')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dob');
            $table->dropColumn('passport_id');
            $table->dropColumn('investment_amount');
            $table->dropColumn('scheme_name');
            $table->dropColumn('pan_number');
            $table->dropColumn('bank_name');
            $table->dropColumn('bank_amount_no');
            $table->dropColumn('ifsc_code');
            $table->dropColumn('bank_address');
            $table->dropColumn('tds');
        });
    }
};
