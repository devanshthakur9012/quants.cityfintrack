<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broker_api_id')->constrained('broker_apis')->onDelete('cascade');
            $table->enum('time_frame', ['15min', '30min', '1hr']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
 
            // One timeframe globally — no two brokers can share the same timeframe
            $table->unique('time_frame');
        });
 
        Schema::create('analysis_config_symbols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_config_id')->constrained('analysis_configs')->onDelete('cascade');
            $table->foreignId('symbol_list_id')->constrained('symbol_lists')->onDelete('cascade');
            $table->timestamps();
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('analysis_config_symbols');
        Schema::dropIfExists('analysis_configs');
    }
};
