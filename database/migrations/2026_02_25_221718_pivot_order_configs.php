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
            $table->unsignedBigInteger('user_id')->index();

            // Core model selection
            $table->enum('model_type',      ['Standard', 'Camarilla'])->default('Standard');
            $table->enum('instrument_type', ['CE', 'PE', 'Both'])->default('Both');

            // Quantities per level
            $table->unsignedInteger('s1_qty')->default(0);
            $table->unsignedInteger('s2_qty')->default(0);
            $table->unsignedInteger('s3_qty')->default(0);

            // S1 discount (subtracted from S1 price)
            $table->decimal('s1_discount',      8, 2)->default(0);
            $table->enum('s1_discount_type',    ['points', 'percent'])->default('points');

            // S2 discount (subtracted from S2 price)
            $table->decimal('s2_discount',      8, 2)->default(0);
            $table->enum('s2_discount_type',    ['points', 'percent'])->default('points');

            // S3 buffer (added to S3 price — so order is placed a little above S3)
            $table->decimal('s3_buffer',        8, 2)->default(0);
            $table->enum('s3_buffer_type',      ['points', 'percent'])->default('points');

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pivot_order_configs');
    }
};
