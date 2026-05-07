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
        Schema::create('monitored_stocks', function (Blueprint $table) {
            $table->id();
            $table->string('trading_symbol', 50)->unique();
            $table->string('exchange', 10)->default('NSE');
            $table->bigInteger('instrument_token')->nullable();
            $table->string('intervals')->default('15minute'); // Comma-separated: 15minute,30minute,day
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
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
        Schema::dropIfExists('monitored_stocks');
    }
};
