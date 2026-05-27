<?php
// FILE: database/migrations/2024_01_01_000020_create_cp_system_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. CP ANALYSES catalogue ──────────────────────────────────────────
        Schema::create('cp_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('route_name')->nullable()
                  ->comment('Laravel named route e.g. pivot-analysis.index');
            $table->string('thumbnail')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->json('faqs')->nullable();
            $table->json('tags')->nullable();
            $table->enum('plan_tier', ['free', 'pro', 'pro_plus'])->default('free');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('data_source', ['option', 'fut', 'stock', 'mixed'])->default('option');
            $table->timestamps();
        });

        // ── 2. CP SUBSCRIPTION PLANS ──────────────────────────────────────────
        Schema::create('cp_subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique()->comment('free | pro | pro_plus');
            $table->text('description')->nullable();
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->json('features')->nullable();
            $table->string('badge_color', 20)->default('#F5A623');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ── 3. CP PLAN ↔ ANALYSIS pivot ───────────────────────────────────────
        Schema::create('cp_plan_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_subscription_plan_id')
                  ->constrained('cp_subscription_plans')->cascadeOnDelete();
            $table->foreignId('cp_analysis_id')
                  ->constrained('cp_analyses')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['cp_subscription_plan_id', 'cp_analysis_id']);
        });

        // ── 4. CP USER SUBSCRIPTIONS ──────────────────────────────────────────
        Schema::create('cp_user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cp_subscription_plan_id')
                  ->constrained('cp_subscription_plans');
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])
                  ->default('pending');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('auto_renew')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });

        // ── 5. CP SUBSCRIPTION PAYMENTS ───────────────────────────────────────
        Schema::create('cp_subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('cp_subscription_plan_id')
                  ->constrained('cp_subscription_plans');
            $table->foreignId('cp_user_subscription_id')
                  ->nullable()->constrained('cp_user_subscriptions')->nullOnDelete();
            $table->string('gateway')->default('razorpay');
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_signature')->nullable();
            $table->json('gateway_response')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('INR');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // ── 6. CP PAYMENT GATEWAY config ──────────────────────────────────────
        Schema::create('analysis_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broker_api_id')
                  ->constrained('broker_apis')
                  ->cascadeOnDelete();
            $table->string('time_frame', 10)->default('15min')
                  ->comment('Always 15min — enforced by model boot, never change via UI');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
 
        // ── Pivot: analysis_config ↔ symbol_list ─────────────────────────────
        Schema::create('analysis_config_symbols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_config_id')
                  ->constrained('analysis_configs')
                  ->cascadeOnDelete();
            $table->foreignId('symbol_list_id')
                  ->constrained('symbol_lists')
                  ->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['analysis_config_id', 'symbol_list_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_subscription_payments');
        Schema::dropIfExists('cp_user_subscriptions');
        Schema::dropIfExists('cp_plan_analyses');
        Schema::dropIfExists('cp_analyses');
        Schema::dropIfExists('cp_subscription_plans');
        Schema::dropIfExists('cp_payment_gateways');
        Schema::dropIfExists('analysis_config_symbols');
        Schema::dropIfExists('analysis_configs');
    }
};