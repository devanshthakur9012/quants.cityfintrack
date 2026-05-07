<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_broker_apis', function (Blueprint $table) {
            $table->id();
            $table->string('client_name');
            $table->string('broker_name')->default('Zerodha');
            $table->string('account_user_name')->index();
            $table->text('account_password');
            $table->string('api_key');
            $table->text('api_secret_key');
            $table->string('security_pin')->nullable();
            $table->text('totp')->nullable();
            $table->string('client_type')->default('Zerodha')->index();
            $table->text('access_token')->nullable();
            $table->boolean('is_token_valid')->default(false)->index();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
 
            // Prevent duplicate username per broker type
            $table->unique(['account_user_name', 'client_type'], 'uq_broker_username_type');
        });
    }
 
    public function down(): void
    {
        Schema::dropIfExists('admin_broker_apis');
    }
};