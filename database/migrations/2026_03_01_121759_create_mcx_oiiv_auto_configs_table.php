<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── mcx_oiiv_auto_configs ─────────────────────────────────────────────
        Schema::create('mcx_oiiv_auto_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');

            // Order settings
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product',    ['NRML', 'MIS'])->default('NRML');
            $table->decimal('disc_ltp', 5, 2)->default(0);  // discount % below LTP for LIMIT

            // Signal mode
            $table->enum('signal_mode', ['align', 'opposite'])->default('align');

            // Status
            $table->boolean('status')->default(true);

            // Base quantities (fallback if no rank config)
            $table->integer('ce_quantity')->default(0);
            $table->integer('pe_quantity')->default(0);

            // Rank-based quantities — mirroring NFO oiiv_auto_configs
            $table->integer('rank1_ce_quantity')->default(0);
            $table->integer('rank1_pe_quantity')->default(0);
            $table->integer('rank2_ce_quantity')->default(0);
            $table->integer('rank2_pe_quantity')->default(0);
            $table->integer('rank3_ce_quantity')->default(0);
            $table->integer('rank3_pe_quantity')->default(0);
            $table->integer('rank4_ce_quantity')->default(0);
            $table->integer('rank4_pe_quantity')->default(0);

            // Option series: 'current' = nearest expiry, 'next' = next expiry
            $table->enum('option_series', ['current', 'next'])->default('current');

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });

        // ── mcx_oiiv_auto_orders ──────────────────────────────────────────────
        Schema::create('mcx_oiiv_auto_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('config_id');
            $table->unsignedBigInteger('broker_api_id');

            // MCX commodity identifier
            $table->string('symbol', 30);            // e.g. CRUDEOIL
            $table->string('trading_symbol', 60)->nullable(); // e.g. CRUDEOIL26MARFUT
            $table->unsignedBigInteger('instrument_token')->nullable();

            // Signal details
            $table->string('btst_signal', 20)->nullable();       // BUY_CE | BUY_PE
            $table->unsignedTinyInteger('btst_confidence')->default(0);
            $table->text('btst_reason')->nullable();
            $table->dateTime('signal_detected_at')->nullable();

            // OI sub-signals
            $table->string('fut_oi_signal', 20)->nullable();
            $table->string('fut_oi_strength', 20)->nullable();
            $table->string('ce_oi_signal', 20)->nullable();
            $table->string('pe_oi_signal', 20)->nullable();

            // Price at signal time
            $table->decimal('spot_price', 12, 2)->default(0);

            // Option selected
            $table->string('option_symbol', 60)->nullable();
            $table->unsignedBigInteger('option_token')->nullable();
            $table->enum('option_type', ['CE', 'PE'])->nullable();
            $table->decimal('strike_price', 12, 2)->nullable();
            $table->string('strike_position', 10)->nullable();   // ATM, ATM+1, etc.

            // Unit (MCX specific: BBL, KG, MMBTU, etc.)
            $table->string('unit', 10)->nullable();

            // Execution
            $table->enum('order_type', ['LIMIT', 'MARKET'])->default('LIMIT');
            $table->enum('product', ['NRML', 'MIS'])->default('NRML');
            $table->integer('quantity')->default(0);
            $table->decimal('entry_price', 12, 2)->default(0);
            $table->decimal('current_price', 12, 2)->default(0);

            // Rank that triggered this order
            $table->unsignedTinyInteger('strength_rank')->nullable(); // 1-4

            // Order status
            $table->boolean('is_order_placed')->default(false);
            $table->dateTime('order_placed_at')->nullable();
            $table->boolean('status')->default(true);

            // Zerodha order_id for tracking
            $table->string('zerodha_order_id', 30)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'signal_detected_at']);
            $table->index(['config_id', 'symbol']);
            $table->index(['symbol', 'option_type']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('config_id')->references('id')->on('mcx_oiiv_auto_configs')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcx_oiiv_auto_orders');
        Schema::dropIfExists('mcx_oiiv_auto_configs');
    }
};
