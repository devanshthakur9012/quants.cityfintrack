<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            // Add columns without specifying position
            $table->decimal('open_915_price', 10, 2)->nullable();
            $table->timestamp('open_915_locked_at')->nullable();
            
            // Add index for faster queries
            $table->index('open_915_locked_at');
        });
    }

    public function down()
    {
        Schema::table('option_strikes', function (Blueprint $table) {
            $table->dropColumn(['open_915_price', 'open_915_locked_at']);
            $table->dropIndex(['open_915_locked_at']);
        });
    }
};
