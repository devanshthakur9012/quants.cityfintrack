<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::create('expiry_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('default');
            $table->string('scope')->default('global'); // global or symbol-specific
            $table->string('symbol')->nullable(); // NULL for global, specific for symbol
            
            // Supertrend Configuration ONLY
            $table->integer('supertrend_atr_period')->default(10);
            $table->decimal('supertrend_multiplier', 4, 2)->default(3.0);
            
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['scope', 'symbol']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('expiry_configs');
    }
};
