<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->decimal('close_315_price', 10, 2)->nullable()->after('spot_price');
            $table->timestamp('close_315_locked_at')->nullable()->after('close_315_price');
        });
    }

    public function down(): void
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->dropColumn(['close_315_price', 'close_315_locked_at']);
        });
    }
};
