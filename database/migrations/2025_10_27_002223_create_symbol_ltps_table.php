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
        Schema::create('symbol_ltps', function (Blueprint $table) {
            $table->id();
            $table->string('symbol_token')->unique();
            $table->date('trade_date');
            $table->string('symbol_name');
            $table->decimal('ltp', 15, 2)->default(0);
            $table->decimal('highest_ltp', 15, 2)->default(0);
            $table->time('highest_time')->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamps();
            
            $table->index('symbol_token');
            $table->index('symbol_name');
            $table->index('trade_date');
            $table->index(['symbol_token', 'trade_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('symbol_ltps');
    }
};
