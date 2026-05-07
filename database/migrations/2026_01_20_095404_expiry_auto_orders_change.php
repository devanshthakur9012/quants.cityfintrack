<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::table('expiry_auto_orders', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('expiry_auto_orders', 'symbol')) {
                $table->string('symbol', 50)->after('broker_api_id')->index();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'instrument_token')) {
                $table->string('instrument_token', 50)->after('symbol')->nullable();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'signal_type')) {
                $table->enum('signal_type', ['BUY', 'SELL'])->after('instrument_token');
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'supertrend_signal')) {
                $table->string('supertrend_signal', 20)->after('signal_type')->nullable();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'signal_detected_at')) {
                $table->timestamp('signal_detected_at')->after('supertrend_signal')->nullable();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'option_symbol')) {
                $table->string('option_symbol', 100)->after('signal_detected_at');
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'option_token')) {
                $table->string('option_token', 50)->after('option_symbol')->nullable();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'option_type')) {
                $table->enum('option_type', ['CE', 'PE'])->after('option_token');
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'strike_price')) {
                $table->decimal('strike_price', 12, 2)->after('option_type');
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'index_price')) {
                $table->decimal('index_price', 12, 2)->after('strike_price')->nullable();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'entry_price')) {
                $table->decimal('entry_price', 12, 2)->after('index_price')->nullable();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'current_price')) {
                $table->decimal('current_price', 12, 2)->after('entry_price')->nullable();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'order_type')) {
                $table->enum('order_type', ['LIMIT', 'MARKET'])->after('current_price')->default('MARKET');
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'product')) {
                $table->enum('product', ['NRML', 'MIS'])->after('order_type')->default('MIS');
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'quantity')) {
                $table->integer('quantity')->after('product');
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'pyramid_1')) {
                $table->integer('pyramid_1')->after('quantity')->default(0);
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'pyramid_2')) {
                $table->integer('pyramid_2')->after('pyramid_1')->default(0);
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'pyramid_3')) {
                $table->integer('pyramid_3')->after('pyramid_2')->default(0);
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'is_order_placed')) {
                $table->boolean('is_order_placed')->after('pyramid_3')->default(false);
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'order_placed_at')) {
                $table->timestamp('order_placed_at')->after('is_order_placed')->nullable();
            }
            
            if (!Schema::hasColumn('expiry_auto_orders', 'status')) {
                $table->boolean('status')->after('order_placed_at')->default(true);
            }
        });
    }

    public function down()
    {
        Schema::table('expiry_auto_orders', function (Blueprint $table) {
            $columns = [
                'symbol', 'instrument_token', 'signal_type', 'supertrend_signal',
                'signal_detected_at', 'option_symbol', 'option_token', 'option_type',
                'strike_price', 'index_price', 'entry_price', 'current_price',
                'order_type', 'product', 'quantity', 'pyramid_1', 'pyramid_2',
                'pyramid_3', 'is_order_placed', 'order_placed_at', 'status'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('expiry_auto_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
