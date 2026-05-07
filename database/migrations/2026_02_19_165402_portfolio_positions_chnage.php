<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
      public function up(): void
    {
        Schema::table('portfolio_positions', function (Blueprint $table) {
            // Exit tracking columns (add only if they don't already exist)
            $table->decimal('exit_price', 12, 2)->nullable()->after('last_price');
            $table->timestamp('exit_time')->nullable()->after('exit_price');
            $table->decimal('realized_pnl', 15, 2)->nullable()->after('exit_time');
            $table->integer('holding_days')->nullable()->after('realized_pnl');
            $table->string('exit_source')->nullable()->after('holding_days'); // 'MANUAL_ZERODHA' or 'SYSTEM'
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_positions', function (Blueprint $table) {
            $table->dropColumn(['exit_price', 'exit_time', 'realized_pnl', 'holding_days', 'exit_source']);
        });
    }
};
