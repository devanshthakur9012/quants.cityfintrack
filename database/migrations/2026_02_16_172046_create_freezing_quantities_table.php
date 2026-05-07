<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('freezing_quantities', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 50)->comment('Trading symbol base name (e.g., NIFTY, BANKNIFTY, RELIANCE)');
            $table->integer('freezing_quantity')->comment('Max quantity per order for this symbol');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique('symbol');
            $table->index('is_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('freezing_quantities');
    }
};
