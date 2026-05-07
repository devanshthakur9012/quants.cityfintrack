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
        Schema::create('broker_apis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('client_name',155);
            $table->string('broker_name',155);
            $table->string('account_user_name',155);
            $table->string('account_password',155);
            $table->string('api_key',155);
            $table->string('api_secret_key',155);
            $table->string('security_pin',155)->nullable();
            $table->string('totp',155);
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
        Schema::dropIfExists('broker_apis');
    }
};
