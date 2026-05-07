<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oiiv_auto_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            
            // Signal Configuration
            $table->integer('min_confidence')->default(70)->comment('Minimum BTST confidence % (0-100)');
            
            // Order Configuration
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('MIS');
            $table->decimal('disc_ltp', 8, 2)->default(0.50)->comment('Discount % from LTP for LIMIT orders');
            
            // Quantity Configuration
            $table->integer('index_quantity')->default(1)->comment('Lots for index futures (NIFTY, BANKNIFTY, etc.)');
            $table->integer('stock_quantity')->default(1)->comment('Lots for stock futures');
            
            // Status
            $table->boolean('status')->default(true);
            
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
            
            // Indexes
            $table->index('user_id');
            $table->index('broker_api_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oiiv_auto_configs');
    }
};
