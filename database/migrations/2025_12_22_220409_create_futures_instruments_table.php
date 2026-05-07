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
        Schema::create('futures_instruments', function (Blueprint $table) {
            $table->id();
            
            // Instrument info
            $table->string('underlying', 50)->index();
            $table->string('symbol', 100)->index();
            $table->string('token', 50)->unique();
            $table->string('exchange', 20)->default('NFO');
            
            // Contract details
            $table->date('expiry_date')->nullable();
            $table->integer('lot_size')->nullable();
            $table->decimal('tick_size', 10, 4)->nullable();
            $table->string('instrument_type', 20)->default('FUTSTK');
            
            // Status
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['underlying', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('futures_instruments');
    }
};
