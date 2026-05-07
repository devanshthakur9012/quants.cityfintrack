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
        Schema::create('oms_configs_db', function (Blueprint $table) {
            $table->id();

            // Add master_config_id column first
            $table->foreignId('master_config_id')
                ->constrained('oms_config_masters')
                ->onDelete('cascade');

            // Foreign keys
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('broker_api_id')->nullable()->constrained('broker_apis')->onDelete('set null');
            
            // Columns
            $table->string('symbol_name')->nullable();
            $table->string('token')->nullable();
            $table->string('symbol_type')->nullable();
            $table->string('disc_ltp')->nullable();
            $table->string('portfolio_type')->nullable();
            $table->string('buildup_type')->nullable();
            $table->string('product')->nullable();
            $table->string('order_type')->nullable();
            $table->decimal('pyramid_percent', 8, 2)->nullable();
            $table->integer('pyramid_1')->nullable();
            $table->integer('pyramid_2')->nullable();
            $table->integer('pyramid_3')->nullable();
            $table->string('txn_type')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('pyramid_freq')->nullable();
            $table->integer('exit_1_qty')->default(0);
            $table->decimal('exit_1_target', 8, 2)->default(0);
            $table->integer('exit_2_qty')->default(0);
            $table->decimal('exit_2_target', 8, 2)->default(0);
            $table->dateTime('cron_run_at')->nullable();
            $table->dateTime('last_time')->nullable();
            $table->boolean('is_api_pushed')->default(false);
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['master_config_id', 'symbol_name', 'token'], 'unique_symbol_per_master');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('oms_configs_db');
    }
};
