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
        Schema::create('historical_options_data', function (Blueprint $table) {
            $table->id();
            $table->string('underlying');
            $table->date('date');
            
            // Future Data
            $table->string('future_symbol');
            $table->string('future_token');
            $table->decimal('future_open', 10, 2)->nullable();
            $table->decimal('future_high', 10, 2)->nullable();
            $table->decimal('future_low', 10, 2)->nullable();
            $table->decimal('future_close', 10, 2)->nullable();
            $table->bigInteger('future_volume')->nullable();
            $table->bigInteger('future_oi')->nullable();
            
            // Call Option Data
            $table->string('ce_symbol');
            $table->string('ce_token');
            $table->decimal('ce_open', 10, 2)->nullable();
            $table->decimal('ce_high', 10, 2)->nullable();
            $table->decimal('ce_low', 10, 2)->nullable();
            $table->decimal('ce_close', 10, 2)->nullable();
            $table->bigInteger('ce_volume')->nullable();
            $table->bigInteger('ce_oi')->nullable();
            
            // Put Option Data
            $table->string('pe_symbol');
            $table->string('pe_token');
            $table->decimal('pe_open', 10, 2)->nullable();
            $table->decimal('pe_high', 10, 2)->nullable();
            $table->decimal('pe_low', 10, 2)->nullable();
            $table->decimal('pe_close', 10, 2)->nullable();
            $table->bigInteger('pe_volume')->nullable();
            $table->bigInteger('pe_oi')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['underlying', 'date']);
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
        Schema::dropIfExists('historical_options_data');
    }
};
