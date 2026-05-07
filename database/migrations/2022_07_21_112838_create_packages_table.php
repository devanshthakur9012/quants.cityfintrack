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
        // Schema::create('packages', function (Blueprint $table) {
        //     $table->id();

        //     $table->string('name', 255);
        //     $table->decimal('price', 18, 2);
        //     $table->integer('validity')->default(0);
        //     $table->text('features');
        //     $table->tinyInteger('status')->default(1);

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
        // Schema::dropIfExists('packages');
    }
};
