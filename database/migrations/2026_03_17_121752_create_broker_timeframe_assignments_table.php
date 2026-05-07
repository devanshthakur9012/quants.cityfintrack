<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * broker_timeframe_assignments
     *
     * Links an admin broker to a specific data-collection timeframe.
     * One broker can serve multiple timeframes; one timeframe can have
     * only ONE active broker assigned at a time (enforced by unique index).
     *
     * Supported timeframes: 1min, 3min, 5min, 10min, 15min, 30min, 1hr, 1day
     */
    public function up(): void
    {
        Schema::create('broker_timeframe_assignments', function (Blueprint $table) {
            $table->id();
 
            $table->foreignId('admin_broker_api_id')
                ->constrained('admin_broker_apis')
                ->onDelete('cascade');
 
            // e.g. "15min", "30min", "1hr"
            $table->string('timeframe', 10)->index();
 
            // Human-readable label for the assignment
            $table->string('label')->nullable()
                ->comment('Optional label, e.g. "Primary 1HR collector"');
 
            // Linked symbol table — which set of symbols does this assignment use?
            // e.g. "thirty_min_ohlc_symbols", "fifteen_min_ohlc_symbols"
            $table->string('symbol_table')->nullable()
                ->comment('DB table name that holds active symbols for this timeframe');
 
            $table->boolean('is_active')->default(true)->index();
 
            $table->text('notes')->nullable();
            $table->timestamps();
 
            // Only one active broker per timeframe at a time
            $table->unique(['timeframe', 'is_active'], 'uq_timeframe_active');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('broker_timeframe_assignments');
    }
};
