<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('broker_apis', function (Blueprint $table) {
            // Add access_token field if not exists
            if (!Schema::hasColumn('broker_apis', 'access_token')) {
                $table->text('access_token')->nullable()->after('totp');
            }
            
            // Add token_expires_at field
            if (!Schema::hasColumn('broker_apis', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->after('access_token');
            }
            
            // Add last_login_at field
            if (!Schema::hasColumn('broker_apis', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('token_expires_at');
            }
            
            // Add is_token_valid field
            if (!Schema::hasColumn('broker_apis', 'is_token_valid')) {
                $table->boolean('is_token_valid')->default(false)->after('last_login_at');
            }
        });
    }

    public function down()
    {
        Schema::table('broker_apis', function (Blueprint $table) {
            $table->dropColumn(['access_token', 'token_expires_at', 'last_login_at', 'is_token_valid']);
        });
    }
};
