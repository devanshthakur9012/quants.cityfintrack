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
        Schema::create('options_chains', function (Blueprint $table) {
            $table->id();
            $table->string('underlying', 50)->index(); // RELIANCE, NIFTY, etc.
            $table->string('future_symbol', 100)->nullable();
            $table->string('future_token', 20)->nullable();
            $table->decimal('future_price', 10, 2)->nullable();
            $table->decimal('strike_price', 10, 2)->index();
            $table->integer('strike_position'); // -2, -1, 0, 1, 2 (ATM is 0)
            
            // CE Options
            $table->string('ce_symbol', 100)->nullable();
            $table->string('ce_token', 20)->nullable();
            $table->integer('ce_lotsize')->nullable();
            $table->string('ce_exch_seg', 10)->nullable();
            $table->date('ce_expiry')->nullable();
            $table->decimal('ce_tick_size', 8, 4)->nullable();
            
            // PE Options
            $table->string('pe_symbol', 100)->nullable();
            $table->string('pe_token', 20)->nullable();
            $table->integer('pe_lotsize')->nullable();
            $table->string('pe_exch_seg', 10)->nullable();
            $table->date('pe_expiry')->nullable();
            $table->decimal('pe_tick_size', 8, 4)->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['underlying', 'strike_position']);
            $table->index('future_token');
            $table->index('ce_token');
            $table->index('pe_token');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('options_chains');
    }
};
