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
        // Schema::create('signals', function (Blueprint $table) {
        //     $table->id();

        //     $table->unsignedInteger('package_id');
        //     $table->string('send_via', 40);
        //     $table->string('name', 255);
        //     $table->text('signal');
        //     $table->integer('minute')->default(0);
        //     $table->tinyInteger('send')->default(0)->comment('0=> Not Send, 1=> Send');
        //     $table->tinyInteger('status')->default(0);
        //     $table->dateTime('send_signal_at');

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
        // Schema::dropIfExists('signals');
    }
};
