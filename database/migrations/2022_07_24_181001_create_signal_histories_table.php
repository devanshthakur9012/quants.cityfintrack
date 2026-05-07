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
        // Schema::create('signal_histories', function (Blueprint $table) {
        //     $table->id();

        //     $table->unsignedInteger('user_id')->default(0);
        //     $table->string('name', 255);
        //     $table->text('signal');

        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('signal_histories');
    }
};
