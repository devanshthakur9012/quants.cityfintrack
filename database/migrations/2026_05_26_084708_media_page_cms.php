<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── MEDIA PAGE CMS ────────────────────────────────────────────────
        Schema::create('media_page_cms', function (Blueprint $table) {
            $table->id();
            // Hero
            $table->string('hero_eyebrow')->default('Press, Media & Recognition');
            $table->string('hero_title')->default('CityQuants In The Media');
            $table->string('hero_title_highlight')->default('In The Media')->comment('Portion of title rendered in gold');
            $table->text('hero_subtitle')->nullable();
            // CTA strip
            $table->string('cta_title')->default('Press & Media Enquiries');
            $table->text('cta_description')->nullable();
            $table->string('cta_email')->default('media@cityquants.com');
            $table->string('cta_btn_label')->default('Contact Media Team');
            $table->timestamps();
        });
 
        // ── WEBINAR PAGE CMS ──────────────────────────────────────────────
        Schema::create('webinar_page_cms', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title')->default('Webinar');
            $table->text('hero_description')->nullable();
            $table->string('hero_illustration_url')->nullable()->comment('Full URL or path to illustration image');
            // JSON arrays for filter dropdowns
            $table->json('languages')->nullable()->comment('["Hindi","English","Gujarati"]');
            $table->json('proficiency_levels')->nullable()->comment('["Beginner","Intermediate","Advanced"]');
            $table->timestamps();
        });
 
        // ── COURSES PAGE CMS ──────────────────────────────────────────────
        Schema::create('course_page_cms', function (Blueprint $table) {
            $table->id();
            $table->string('hero_title')->default('Learn Option');
            $table->text('hero_description')->nullable();
            // Up to 4 banner images stored as JSON paths
            $table->json('hero_banners')->nullable();
            // Filter options
            $table->json('languages')->nullable();
            $table->json('levels')->nullable();
            $table->json('modes')->nullable();
            $table->timestamps();
        });
 
        // ── EVENTS PAGE CMS ───────────────────────────────────────────────
        Schema::create('event_page_cms', function (Blueprint $table) {
            $table->id();
            $table->string('hero_eyebrow')->default('Offline Events & Workshops');
            $table->string('hero_title')->default('Options Trading Events & Workshops');
            $table->string('hero_title_highlight')->default('Events')->comment('Portion wrapped in <span> for gold color');
            $table->text('hero_subtitle')->nullable();
            // JSON array of {key, label} city objects
            $table->json('cities')->nullable();
            // Bottom CTA strip
            $table->string('cta_title')->nullable();
            $table->text('cta_description')->nullable();
            $table->string('cta_btn_label')->nullable();
            $table->string('cta_btn_url')->nullable();
            $table->timestamps();
        });
 
        // ── LOGIN / REGISTER PAGE CMS ─────────────────────────────────────
        Schema::create('auth_page_cms', function (Blueprint $table) {
            $table->id();
            // Left panel promo video
            $table->string('promo_video_url')->nullable();
            // Feature bullet points (JSON array of strings)
            $table->json('features')->nullable();
            // Broker logos (JSON array of {name, letter, bg})
            $table->json('brokers')->nullable();
            // Login page texts
            $table->string('login_heading')->default('Welcome Back');
            $table->string('login_subheading')->nullable();
            // Register page texts
            $table->string('register_heading')->default('Create Account');
            $table->string('register_subheading')->nullable();
            $table->timestamps();
        });
 
        // ── ABOUT PAGE — WHO WE ARE ───────────────────────────────────────
        // (already handled by AboutWhoWeAre model, but add pillars JSON column if missing)
        // This table may already exist; migration is idempotent via hasColumn checks.
 
        // ── GLOBAL SITE SETTINGS ─────────────────────────────────────────
        // Key-value store for miscellaneous settings not worth a full table
        if (!Schema::hasTable('site_settings')) {
            Schema::create('site_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('group')->default('general');
                $table->timestamps();
            });
        }
    }
 
    public function down(): void
    {
        Schema::dropIfExists('media_page_cms');
        Schema::dropIfExists('webinar_page_cms');
        Schema::dropIfExists('course_page_cms');
        Schema::dropIfExists('event_page_cms');
        Schema::dropIfExists('auth_page_cms');
        Schema::dropIfExists('site_settings');
    }
};
