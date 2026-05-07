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
        Schema::create('angel_buildups', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->index(); // e.g., NIFTY, BANKNIFTY
            $table->decimal('ltp', 10, 2)->nullable(); // Last Traded Price
            $table->decimal('net_change', 10, 2)->nullable(); // Net change
            $table->decimal('per_change', 8, 2)->nullable(); // Percentage change
            $table->bigInteger('oi')->nullable(); // Open interest
            $table->bigInteger('oi_change')->nullable(); // OI change
            $table->string('type', 50)->nullable(); // long, short, covering, unwinding
            $table->string('oi_signal', 100)->nullable(); // Optional signal
            $table->decimal('price_diff', 10, 2)->nullable();
            $table->decimal('oi_diff', 10, 2)->nullable();
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
        Schema::dropIfExists('angel_buildups');
    }
};
