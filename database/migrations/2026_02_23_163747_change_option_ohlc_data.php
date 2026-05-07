<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('option_ohlc_data', function (Blueprint $table) {
            $table->dropColumn([
                'fair_price',
                'iv',
                'valuation',
                'recommendation',
                'days_to_expiry',
            ]);
            $table->tinyInteger('is_missing')->default(0)->after('expiry_date')
                ->comment('1 = no API data for this interval; zeros stored to prevent gaps');
        });
    }

    public function down(): void
    {
        Schema::table('option_ohlc_data', function (Blueprint $table) {
            $table->dropColumn('is_missing');

            // Restore dropped columns
            $table->decimal('fair_price', 10, 2)->nullable();
            $table->decimal('iv', 10, 4)->nullable();
            $table->string('valuation', 20)->nullable();
            $table->string('recommendation', 50)->nullable();
            $table->integer('days_to_expiry')->nullable();
        });
    }
};
