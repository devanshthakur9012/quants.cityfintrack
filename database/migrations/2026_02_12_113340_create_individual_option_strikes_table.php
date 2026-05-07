<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_option_strikes', function (Blueprint $table) {

            $table->id();

            // Broker & Symbol Info
            $table->unsignedBigInteger('broker_api_id');
            $table->string('underlying_symbol', 50);
            $table->string('trading_symbol', 100);
            $table->date('trading_date');

            // Strike Info
            $table->enum('option_type', ['CE', 'PE']);
            $table->decimal('strike_price', 10, 2);
            $table->enum('strike_position', ['ATM-2', 'ATM-1', 'ATM', 'ATM+1', 'ATM+2']);

            // Expiry Info
            $table->string('expiry', 10);
            $table->date('expiry_date')->nullable();

            // Instrument Info
            $table->bigInteger('instrument_token')->nullable();
            $table->string('exchange', 10)->default('NFO');
            $table->integer('lot_size')->default(1);

            // OI Data
            $table->bigInteger('daily_oi')->default(0);
            $table->bigInteger('daily_oi_prev')->default(0);
            $table->bigInteger('daily_oi_change')->default(0);
            $table->decimal('daily_oi_change_pct', 10, 2)->default(0);

            // IV Data
            $table->decimal('daily_iv', 10, 4)->nullable();
            $table->decimal('daily_iv_prev', 10, 4)->nullable();
            $table->decimal('daily_iv_change', 10, 4)->nullable();
            $table->decimal('daily_iv_change_pct', 10, 2)->nullable();

            // Price Data
            $table->decimal('daily_close', 10, 2)->nullable();
            $table->decimal('daily_close_prev', 10, 2)->nullable();
            $table->decimal('daily_close_change', 10, 2)->nullable();
            $table->decimal('daily_close_change_pct', 10, 2)->nullable();

            // Analysis Fields
            $table->string('direction', 20)->nullable();
            $table->string('strength', 20)->nullable();

            // IV Analysis Fields
            $table->string('iv_direction', 20)->nullable();
            $table->string('iv_strength', 20)->nullable();

            // Reference Data
            $table->decimal('spot_price', 10, 2)->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();

            $table->timestamps();

            // Foreign Key
            $table->foreign('broker_api_id')
                ->references('id')
                ->on('broker_apis')
                ->onDelete('cascade');

            /*
            |--------------------------------------------------------------------------
            | INDEXES (Short Names to Avoid MySQL 64 Char Limit)
            |--------------------------------------------------------------------------
            */

            $table->index('underlying_symbol', 'ios_symbol_idx');
            $table->index('trading_symbol', 'ios_trading_symbol_idx');
            $table->index('trading_date', 'ios_date_idx');
            $table->index('option_type', 'ios_type_idx');
            $table->index('strike_position', 'ios_position_idx');

            // Composite Indexes
            $table->index(
                ['broker_api_id', 'underlying_symbol', 'trading_date'],
                'ios_main_idx'
            );

            $table->index(
                ['broker_api_id', 'underlying_symbol', 'trading_date', 'strike_position'],
                'ios_strike_idx'
            );

            $table->index(
                ['trading_date', 'option_type', 'strike_position'],
                'ios_filter_idx'
            );

            // Unique Constraint
            $table->unique(
                ['broker_api_id', 'underlying_symbol', 'trading_symbol', 'trading_date'],
                'ios_unique_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_option_strikes');
    }
};