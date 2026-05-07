<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pivot_order_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('broker_api_id');  // which broker to place orders on

            // Pivot model
            $table->enum('model_type',      ['Standard', 'Camarilla'])->default('Standard');
            $table->enum('instrument_type', ['CE', 'PE', 'Both'])->default('Both');

            // S1 config
            $table->unsignedInteger('s1_qty')->default(0);
            $table->decimal('s1_discount',      8, 2)->default(0);
            $table->enum('s1_discount_type',    ['points', 'percent'])->default('points');

            // S2 config
            $table->unsignedInteger('s2_qty')->default(0);
            $table->decimal('s2_discount',      8, 2)->default(0);
            $table->enum('s2_discount_type',    ['points', 'percent'])->default('points');

            // S3 config — buffer means ADD to S3 (safer entry above S3)
            $table->unsignedInteger('s3_qty')->default(0);
            $table->decimal('s3_buffer',        8, 2)->default(0);
            $table->enum('s3_buffer_type',      ['points', 'percent'])->default('points');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('broker_api_id')->references('id')->on('broker_apis')->onDelete('cascade');

            // One active config per user+broker combo
            $table->unique(['user_id', 'broker_api_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pivot_order_configs');
    }
};
