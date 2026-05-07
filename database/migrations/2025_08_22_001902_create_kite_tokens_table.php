<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kite_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique()->index();
            $table->text('access_token');
            $table->json('user_data')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            
            // Index for cleanup of expired tokens
            $table->index(['username', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kite_tokens');
    }
};
