<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw SQL — no Doctrine DBAL needed
        DB::statement('ALTER TABLE course_payment_gateways MODIFY COLUMN credentials TEXT NULL');
    }
 
    public function down(): void
    {
        DB::statement('ALTER TABLE course_payment_gateways MODIFY COLUMN credentials TEXT NOT NULL');
    }
};
