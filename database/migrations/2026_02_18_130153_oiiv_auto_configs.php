<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            // Rank 1 (Strongest: diff > 40)
            $table->integer('rank1_ce_quantity')->default(0)->after('strong_pe_quantity');
            $table->integer('rank1_pe_quantity')->default(0)->after('rank1_ce_quantity');

            // Rank 2 (diff > 25)
            $table->integer('rank2_ce_quantity')->default(0)->after('rank1_pe_quantity');
            $table->integer('rank2_pe_quantity')->default(0)->after('rank2_ce_quantity');

            // Rank 3 (diff > 10)
            $table->integer('rank3_ce_quantity')->default(0)->after('rank2_pe_quantity');
            $table->integer('rank3_pe_quantity')->default(0)->after('rank3_ce_quantity');

            // Rank 4 (diff > 5)
            $table->integer('rank4_ce_quantity')->default(0)->after('rank3_pe_quantity');
            $table->integer('rank4_pe_quantity')->default(0)->after('rank4_ce_quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('oiiv_auto_configs', function (Blueprint $table) {
            $table->dropColumn([
                'rank1_ce_quantity', 'rank1_pe_quantity',
                'rank2_ce_quantity', 'rank2_pe_quantity',
                'rank3_ce_quantity', 'rank3_pe_quantity',
                'rank4_ce_quantity', 'rank4_pe_quantity',
            ]);
        });
    }
};
