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
        Schema::create('futures_monitored', function (Blueprint $table) {
            $table->id();
            $table->string('trading_symbol')->index(); // e.g., AXISBANK26JANFUT
            $table->string('exchange')->default('NFO');
            $table->string('instrument_token');
            $table->string('intervals')->default('15minute'); // comma-separated
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->date('expiry_date')->nullable();
            $table->integer('lot_size')->nullable();
            $table->timestamps();

            $table->unique(['trading_symbol', 'exchange']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('futures_monitored');
    }
};
