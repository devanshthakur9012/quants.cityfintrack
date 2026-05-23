<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── HOME PAGE ─────────────────────────────────────────────────────────

        // Hero section
        Schema::create('home_hero', function (Blueprint $table) {
            $table->id();
            $table->string('video_file')->nullable();           // uploaded video filename
            $table->string('heading_line1')->nullable();        // "Complex"
            $table->string('heading_highlight')->nullable();    // "Option"
            $table->string('heading_line2')->nullable();        // "Simplified"
            $table->timestamps();
        });

        // Platform banner
        Schema::create('home_platform', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->timestamps();
        });

        // Certification slider items
        Schema::create('home_cert_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image')->nullable();               // uploaded image (replaces title text)
            $table->string('badge_text')->nullable();          // "Intermediate >> Advance Course"
            $table->string('language')->nullable();            // "In Hindi"
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // About the App section
        Schema::create('home_about', function (Blueprint $table) {
            $table->id();
            $table->string('video_type')->default('youtube');   // youtube or upload
            $table->string('video_url')->nullable();            // youtube URL or uploaded file
            $table->string('section_heading')->nullable();      // 'Be a "Data Driven" Option Trader!'
            $table->timestamps();
        });

        // About stats (4 boxes)
        Schema::create('home_about_stats', function (Blueprint $table) {
            $table->id();
            $table->string('value');                           // "6500+"
            $table->string('label');                           // "Happy Clients"
            $table->string('sub')->nullable();                 // sub description
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Feature tools
        Schema::create('home_feature_tools', function (Blueprint $table) {
            $table->id();
            $table->string('section_title')->default('CityQuants App Feature Tools');
            $table->timestamps();
        });

        // Individual feature utility tabs
        Schema::create('home_feature_utilities', function (Blueprint $table) {
            $table->id();
            $table->string('count');                           // "14"
            $table->string('label');                           // "Intraday"
            $table->string('tool_title');                      // "Intraday"
            $table->string('tool_icon')->default('fa-bolt');   // fa icon
            $table->json('tool_points')->nullable();           // array of bullet points
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Learning tabs
        Schema::create('home_learning_tabs', function (Blueprint $table) {
            $table->id();
            $table->string('tab_label');                       // "Webinars"
            $table->string('highlight_text')->nullable();      // "200Hr of FREE videos"
            $table->text('description')->nullable();
            $table->string('btn_label')->default('View Now');
            $table->string('btn_url')->nullable();
            $table->string('video_id')->nullable();            // YouTube video ID
            $table->string('video_title')->nullable();
            $table->string('video_sub')->nullable();
            $table->string('video_date')->nullable();
            $table->string('video_time')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Testimonials
        Schema::create('home_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('review');
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // ── ABOUT PAGE ────────────────────────────────────────────────────────

        // Hero banner
        Schema::create('about_hero', function (Blueprint $table) {
            $table->id();
            $table->string('tagline')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('founded')->nullable();
            $table->string('hq')->nullable();
            $table->string('users')->nullable();
            $table->string('experience')->nullable();
            $table->string('stat1_value')->nullable();
            $table->string('stat1_label')->nullable();
            $table->string('stat2_value')->nullable();
            $table->string('stat2_label')->nullable();
            $table->string('stat3_value')->nullable();
            $table->string('stat3_label')->nullable();
            $table->string('stat4_value')->nullable();
            $table->string('stat4_label')->nullable();
            $table->timestamps();
        });

        // Who we are
        Schema::create('about_who_we_are', function (Blueprint $table) {
            $table->id();
            $table->string('heading')->nullable();
            $table->text('body')->nullable();
            $table->json('pillars')->nullable();               // [{icon, label}, ...]
            $table->timestamps();
        });

        // Mission
        Schema::create('about_mission', function (Blueprint $table) {
            $table->id();
            $table->string('heading')->nullable();
            $table->text('body')->nullable();
            $table->json('values')->nullable();                // [{icon, label, desc}, ...]
            $table->timestamps();
        });

        // Founding members
        Schema::create('about_founders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('credentials')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Workspace section heading
        Schema::create('about_workspace', function (Blueprint $table) {
            $table->id();
            $table->string('heading')->default('Our Workspace');
            $table->string('sub')->nullable();
            $table->timestamps();
        });

        // Workspace photo slides
        Schema::create('about_workspace_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image')->nullable();
            $table->string('caption')->nullable();
            $table->string('sub_caption')->nullable();
            $table->string('tag')->nullable();                 // "HEADQUARTERS"
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // City offices
        Schema::create('about_offices', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('flag')->nullable();
            $table->string('tag')->nullable();                 // "HEADQUARTERS"
            $table->string('photo')->nullable();
            $table->text('desc')->nullable();
            $table->string('address')->nullable();
            $table->string('team')->nullable();
            $table->string('hours')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Founder vision
        Schema::create('about_founder_vision', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('signature')->nullable();
            $table->string('avatar')->nullable();
            $table->json('paragraphs')->nullable();            // array of paragraph strings
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->string('youtube')->nullable();
            $table->timestamps();
        });

        // CTA section
        Schema::create('about_cta', function (Blueprint $table) {
            $table->id();
            $table->string('heading')->default('Get The App Here!');
            $table->string('appstore')->nullable();
            $table->string('playstore')->nullable();
            $table->string('webapp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_cta');
        Schema::dropIfExists('about_founder_vision');
        Schema::dropIfExists('about_offices');
        Schema::dropIfExists('about_workspace_slides');
        Schema::dropIfExists('about_workspace');
        Schema::dropIfExists('about_founders');
        Schema::dropIfExists('about_mission');
        Schema::dropIfExists('about_who_we_are');
        Schema::dropIfExists('about_hero');
        Schema::dropIfExists('home_testimonials');
        Schema::dropIfExists('home_learning_tabs');
        Schema::dropIfExists('home_feature_utilities');
        Schema::dropIfExists('home_feature_tools');
        Schema::dropIfExists('home_about_stats');
        Schema::dropIfExists('home_about');
        Schema::dropIfExists('home_cert_slides');
        Schema::dropIfExists('home_platform');
        Schema::dropIfExists('home_hero');
    }
};
