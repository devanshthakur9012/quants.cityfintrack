<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_pivot_order_configs', function (Blueprint $table) {
            // Add new split layer columns
            $table->json('s1_ce_layers')->nullable()->after('product');
            $table->json('s1_pe_layers')->nullable()->after('s1_ce_layers');
            $table->json('r1_ce_layers')->nullable()->after('s1_pe_layers');
            $table->json('r1_pe_layers')->nullable()->after('r1_ce_layers');
        });

        // Migrate existing data: split old ce_quantity/pe_quantity into separate layer arrays
        \Illuminate\Support\Facades\DB::table('new_pivot_order_configs')->get()
            ->each(function ($cfg) {
                $s1Old = json_decode($cfg->s1_layers ?? '[]', true) ?: [];
                $r1Old = json_decode($cfg->r1_layers ?? '[]', true) ?: [];

                $s1Ce = array_map(fn($l) => [
                    'discount_direction' => $l['discount_direction'] ?? 'negative',
                    'discount_pct'       => $l['discount_pct'] ?? 0,
                    'quantity'           => $l['ce_quantity'] ?? 0,
                ], $s1Old);

                $s1Pe = array_map(fn($l) => [
                    'discount_direction' => $l['discount_direction'] ?? 'negative',
                    'discount_pct'       => $l['discount_pct'] ?? 0,
                    'quantity'           => $l['pe_quantity'] ?? 0,
                ], $s1Old);

                $r1Ce = array_map(fn($l) => [
                    'discount_direction' => $l['discount_direction'] ?? 'positive',
                    'discount_pct'       => $l['discount_pct'] ?? 0,
                    'quantity'           => $l['ce_quantity'] ?? 0,
                ], $r1Old);

                $r1Pe = array_map(fn($l) => [
                    'discount_direction' => $l['discount_direction'] ?? 'positive',
                    'discount_pct'       => $l['discount_pct'] ?? 0,
                    'quantity'           => $l['pe_quantity'] ?? 0,
                ], $r1Old);

                \Illuminate\Support\Facades\DB::table('new_pivot_order_configs')
                    ->where('id', $cfg->id)
                    ->update([
                        's1_ce_layers' => json_encode($s1Ce ?: [['discount_direction'=>'negative','discount_pct'=>2,'quantity'=>0]]),
                        's1_pe_layers' => json_encode($s1Pe ?: [['discount_direction'=>'negative','discount_pct'=>2,'quantity'=>0]]),
                        'r1_ce_layers' => json_encode($r1Ce ?: [['discount_direction'=>'positive','discount_pct'=>2,'quantity'=>0]]),
                        'r1_pe_layers' => json_encode($r1Pe ?: [['discount_direction'=>'positive','discount_pct'=>2,'quantity'=>0]]),
                    ]);
            });

        Schema::table('new_pivot_order_configs', function (Blueprint $table) {
            // Drop old combined columns + unused fields
            $table->dropColumn(['s1_layers', 'r1_layers', 'symbols', 'option_type']);
        });
    }

    public function down(): void
    {
        Schema::table('new_pivot_order_configs', function (Blueprint $table) {
            $table->json('s1_layers')->nullable();
            $table->json('r1_layers')->nullable();
            $table->string('symbols')->nullable();
            $table->string('option_type')->nullable();
            $table->dropColumn(['s1_ce_layers', 's1_pe_layers', 'r1_ce_layers', 'r1_pe_layers']);
        });
    }
};
