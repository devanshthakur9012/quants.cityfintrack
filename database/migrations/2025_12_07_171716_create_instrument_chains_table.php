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
        Schema::create('instrument_chains', function (Blueprint $table) {
            $table->id();
            
            // Common fields
            $table->string('underlying', 50)->index();
            $table->string('symbol', 100);
            $table->string('token', 50)->index();
            $table->enum('type', ['FUT', 'CE', 'PE'])->index();
            $table->string('exchange', 10)->default('NFO');
            
            // Strike info (null for FUT)
            $table->decimal('strike_price', 12, 2)->nullable()->index();
            $table->integer('strike_position')->nullable()->comment('0=ATM, +ve=ITM, -ve=OTM');
            $table->boolean('is_atm')->default(false)->index();
            
            // Contract details
            $table->date('expiry_date')->nullable();
            $table->integer('lot_size')->nullable();
            $table->decimal('tick_size', 8, 4)->nullable();
            
            // Price info
            $table->decimal('current_price', 12, 2)->nullable();
            $table->decimal('step_value', 10, 2)->nullable()->comment('Strike interval for underlying');
            
            // Metadata
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();
            
            // Indexes
            $table->index(['underlying', 'type', 'is_active']);
            $table->index(['underlying', 'strike_price', 'type']);
            $table->index(['underlying', 'strike_position', 'type']);
            $table->unique(['underlying', 'type', 'token', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('instrument_chains');
    }
};
