<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broker_stop_loss_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');

            // CE, PE, or BOTH
            $table->enum('symbol_type', ['CE', 'PE', 'BOTH'])->default('BOTH');

            // Base price for SL calculation: AVG or LTP at time of run
            $table->enum('price_type', ['AVG', 'LTP'])->default('AVG');

            // SL percent — can be negative (loss) or positive (trailing profit lock)
            // e.g. -30 means exit if LTP drops 30% below base price
            $table->decimal('stop_loss_percent', 8, 2)->default(-30.00);

            // What % of qty to sell when SL triggers
            $table->unsignedTinyInteger('quantity_percent')->default(100);

            // Only trigger SL on PROFIT positions, LOSS positions, or BOTH
            $table->enum('position_filter', ['PROFIT', 'LOSS', 'BOTH'])->default('BOTH');

            // Skip old (before T-1) or fresh (today/T-1) positions
            $table->boolean('skip_old_positions')->default(false);
            $table->boolean('skip_fresh_positions')->default(false);

            $table->boolean('is_active')->default(true);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');

            // One config per broker per symbol_type
            $table->unique(['broker_api_id', 'symbol_type']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broker_stop_loss_configs');
    }
};