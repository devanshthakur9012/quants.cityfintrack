<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::create('one_percent_auto_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');
            
            // Strategy Settings
            $table->decimal('move_threshold', 5, 2)->default(1.0)->comment('% move threshold (0.5, 1.0, 1.5, etc.)');
            $table->enum('option_series', ['current', 'next'])->default('current');
            
            // Order Settings
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('MIS');
            $table->decimal('disc_ltp', 5, 2)->default(0.5)->comment('Discount % from LTP for LIMIT orders');
            
            // Quantity Settings
            $table->integer('index_quantity')->default(1)->comment('Lots for index futures');
            $table->integer('stock_quantity')->default(1)->comment('Lots for stock futures');
            
            // Pyramid Settings
            $table->enum('pyramid_percent', ['100', '50', '33'])->default('100');
            $table->integer('pyramid_freq')->default(0)->comment('Minutes between pyramid levels');
            
            // Profit Target
            $table->decimal('profit_percent', 10, 2)->default(5.0)->comment('Target profit % for auto-exit');
            
            // Status
            $table->boolean('status')->default(true);
            $table->timestamp('last_checked_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('broker_api_id');
            $table->index('status');
            
            // Foreign keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('one_percent_auto_configs');
    }
};
