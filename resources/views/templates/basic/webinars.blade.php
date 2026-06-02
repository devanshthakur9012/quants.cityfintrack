{{-- FILE: resources/views/themes/{active_theme}/webinars.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — WEBINARS PAGE  v2.0
   Dark terminal · Matches homepage design system
══════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --c-bg:       #0B0E11;
    --c-surface:  #131722;
    --c-panel:    #1C2030;
    --c-border:   rgba(255,255,255,.06);
    --c-border2:  rgba(255,255,255,.11);
    --c-lime:     #7DFF00;
    --c-lime-dim: rgba(125,255,0,.1);
    --c-lime-glo: rgba(125,255,0,.06);
    --c-blue:     #00B8D4;
    --c-red:      #EF5350;
    --c-teal:     #26A69A;
    --c-amber:    #FFA726;
    --c-green:    #66BB6A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.qa-wrap { font-family: var(--f-sans); color: var(--c-text); background: var(--c-bg); display: block; }
.qa-wrap * { box-sizing: border-box; }
.qa-wrap a { text-decoration: none; }

@keyframes qaFadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.qa-anim    { animation: qaFadeUp .55s ease both; }
.qa-anim.d1 { animation-delay: .08s; }
.qa-anim.d2 { animation-delay: .16s; }
@keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.65)} }

/* ── BREADCRUMB ── */
.qa-breadcrumb {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 12px 32px;
}
.qa-breadcrumb-inner {
    font-size: 12px; color: var(--c-muted);
    display: flex; align-items: center; gap: 7px; font-family: var(--f-mono);
}
.qa-breadcrumb-inner a { color: var(--c-lime); font-weight: 600; transition: opacity .2s; }
.qa-breadcrumb-inner a:hover { opacity: .75; }
.qa-breadcrumb-inner i { font-size: 10px; color: var(--c-border2); }

/* ── HERO ── */
.qa-wb-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    border-bottom: 1px solid var(--c-border);
    padding: 64px 48px 56px;
    display: flex; align-items: center; justify-content: space-between; gap: 32px;
}
.qa-wb-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.qa-wb-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 45% 80% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.qa-wb-hero-left { position: relative; z-index: 1; flex: 1; }
.qa-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 14px;
    font-family: var(--f-mono);
}
.qa-hero-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.qa-hero-dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--c-lime);
    display: inline-block; animation: pulseDot 1.4s ease infinite; flex-shrink: 0;
}
.qa-wb-hero-left h1 {
    font-family: var(--f-display);
    font-size: clamp(26px, 4vw, 46px);
    font-weight: 800; color: #fff;
    margin: 0 0 14px; line-height: 1.08; letter-spacing: -.02em;
}
.qa-wb-hero-left p {
    font-size: 14px; color: var(--c-muted); line-height: 1.78;
    max-width: 580px; margin: 0;
}
.qa-wb-hero-right {
    position: relative; z-index: 1;
    flex-shrink: 0; width: 200px;
    background: var(--c-surface); border: 1px solid var(--c-border2);
    border-radius: 14px; padding: 8px; overflow: hidden;
    box-shadow: 0 0 40px rgba(125,255,0,.06);
}
.qa-wb-hero-right img { width: 100%; border-radius: 8px; object-fit: contain; display: block; }

@media(max-width:768px) {
    .qa-wb-hero { flex-direction: column; padding: 36px 20px 32px; text-align: center; }
    .qa-hero-eyebrow { justify-content: center; }
    .qa-wb-hero-right { width: 140px; }
}

/* ── FILTER BAR (sticky tabs + dropdowns) ── */
.qa-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 48px;
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
}
.qa-main-tabs { display: flex; border-bottom: 1px solid var(--c-border); }
.qa-main-tab {
    padding: 14px 22px; font-size: 13px; font-weight: 600; color: var(--c-muted);
    cursor: pointer; border: none; background: none;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: all .2s; font-family: var(--f-mono); white-space: nowrap;
    letter-spacing: .04em;
}
.qa-main-tab.on { color: var(--c-lime); border-bottom-color: var(--c-lime); }
.qa-main-tab:hover:not(.on) { color: var(--c-text); }

.qa-dropdowns-row {
    display: flex; align-items: flex-end; gap: 14px;
    padding: 12px 0 11px; flex-wrap: wrap;
}
.qa-filter-group { display: flex; flex-direction: column; gap: 4px; }
.qa-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em; font-family: var(--f-mono);
}
.qa-filter-select {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 7px 26px 7px 11px;
    font-size: 12px; color: var(--c-text); font-family: var(--f-mono);
    font-weight: 600;
    appearance: none; cursor: pointer; outline: none; min-width: 90px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 9px center;
    transition: border-color .2s;
}
.qa-filter-select:focus { border-color: rgba(125,255,0,.45); }
.qa-filter-select option { background: var(--c-panel); }

/* search */
.qa-search-wrap {
    display: flex; align-items: stretch; overflow: hidden;
    border: 1px solid var(--c-border2); border-radius: 7px;
    margin-left: auto; background: var(--c-panel);
}
.qa-search-input {
    border: none; padding: 8px 14px; font-size: 12px;
    color: var(--c-text); outline: none; width: 200px;
    font-family: var(--f-mono); background: transparent;
}
.qa-search-input::placeholder { color: var(--c-muted); }
.qa-search-btn {
    background: var(--c-lime); border: none; padding: 0 14px;
    color: #000; font-size: 13px; cursor: pointer;
    display: flex; align-items: center; transition: background .2s;
}
.qa-search-btn:hover { background: #8FFF1A; }

@media(max-width:768px) {
    .qa-filter-bar { padding: 0 16px; }
    .qa-search-wrap { margin-left: 0; width: 100%; }
    .qa-search-input { width: 100%; }
}

/* ── CONTENT AREA ── */
.qa-webinars-wrap {
    background: var(--c-bg); padding: 32px 48px 72px; min-height: 60vh;
}
@media(max-width:768px) { .qa-webinars-wrap { padding: 20px 16px 56px; } }

/* section heading — lime underline style */
.qa-section-head {
    display: flex; align-items: center; gap: 14px; margin-bottom: 20px; margin-top: 4px;
}
.qa-section-head h2 {
    font-family: var(--f-display); font-size: 16px; font-weight: 700;
    color: #fff; margin: 0; white-space: nowrap;
}
.qa-section-head::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, rgba(125,255,0,.4) 0%, transparent 100%);
}

/* ── CARD GRID ── */
.qa-wgrid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px; margin-bottom: 40px;
}
@media(max-width:1050px) { .qa-wgrid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:580px)  { .qa-wgrid { grid-template-columns: 1fr; } }

/* ── WEBINAR CARD ── */
.qa-wcard {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    display: flex; flex-direction: column;
    position: relative;
    transition: border-color .25s, transform .25s, box-shadow .25s;
}
.qa-wcard::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .3s; z-index: 2;
}
.qa-wcard:hover {
    border-color: rgba(125,255,0,.2);
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(0,0,0,.45);
}
.qa-wcard:hover::before { opacity: 1; }

/* thumbnail */
.qa-wcard-thumb {
    position: relative; aspect-ratio: 16/9; overflow: hidden;
    background: var(--c-panel); flex-shrink: 0;
}
.qa-wcard-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .4s;
}
.qa-wcard:hover .qa-wcard-thumb img { transform: scale(1.04); }

/* status badge */
.qa-badge {
    position: absolute; top: 10px; left: 10px;
    font-size: 10px; font-weight: 700; letter-spacing: .08em;
    padding: 4px 10px; border-radius: 5px; text-transform: uppercase;
    display: inline-flex; align-items: center; gap: 5px;
    font-family: var(--f-mono); backdrop-filter: blur(6px);
}
.qa-badge.live     { background: rgba(239,83,80,.85); color: #fff; border: 1px solid rgba(239,83,80,.5); }
.qa-badge.upcoming { background: rgba(38,166,154,.85); color: #fff; border: 1px solid rgba(38,166,154,.5); }
.qa-live-dot {
    width: 7px; height: 7px; border-radius: 50%; background: #fff; display: inline-block;
    animation: pulseDot 1.2s ease-in-out infinite;
}

/* enrolled badge */
.qa-enrolled-badge {
    position: absolute; top: 10px; right: 10px;
    background: rgba(38,166,154,.85); color: #fff;
    font-size: 10px; font-weight: 700; padding: 3px 8px;
    border-radius: 4px; letter-spacing: .05em;
    font-family: var(--f-mono); backdrop-filter: blur(6px);
}

/* thumb price overlay */
.qa-thumb-price {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(11,14,17,.92) 0%, transparent 100%);
    padding: 22px 12px 10px;
    display: flex; align-items: flex-end; justify-content: space-between;
}
.qa-price-text { color: #fff; font-size: 13px; font-weight: 700; font-family: var(--f-mono); }
.qa-price-text .strike {
    text-decoration: line-through; color: rgba(255,255,255,.45);
    margin: 0 3px; font-weight: 400; font-size: 12px;
}
.qa-price-text .disc { font-size: 11px; color: #80CBC4; margin-left: 4px; }
.qa-view-link {
    font-size: 11px; color: var(--c-lime); font-weight: 700;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: gap .2s; font-family: var(--f-mono);
    letter-spacing: .04em;
}
.qa-view-link:hover { gap: 7px; }

/* card body */
.qa-wcard-body {
    padding: 14px 16px; flex: 1; display: flex; flex-direction: column; gap: 10px;
}
.qa-wcard-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: #fff; line-height: 1.35; flex: 1;
}

/* type pill */
.qa-wcard-type {
    display: inline-block; font-size: 10px; font-weight: 700;
    padding: 3px 11px; border-radius: 100px; letter-spacing: .06em;
    font-family: var(--f-mono);
}
.qa-wcard-type.free { background: rgba(38,166,154,.12); color: #4DB6AC; border: 1px solid rgba(38,166,154,.25); }
.qa-wcard-type.paid { background: rgba(255,167,38,.1);  color: var(--c-amber); border: 1px solid rgba(255,167,38,.25); }

/* meta grid */
.qa-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 10px; }
.qa-meta-row  { display: flex; align-items: center; gap: 7px; font-size: 11px; color: var(--c-muted); }
.qa-meta-row i { color: var(--c-lime); font-size: 11px; width: 14px; text-align: center; flex-shrink: 0; }
.qa-meta-row .meta-v { color: var(--c-text); font-weight: 500; }
.qa-meta-row .past-d  { color: var(--c-red); font-weight: 600; }
.text-danger { color: var(--c-red) !important; }
.fw-bold { font-weight: 700 !important; }

/* card footer */
.qa-wcard-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; border-top: 1px solid var(--c-border);
    background: rgba(0,0,0,.2); gap: 8px;
}
.qa-footer-price {
    font-family: var(--f-display); font-size: 16px; font-weight: 700;
    color: #fff; line-height: 1.2;
}
.qa-footer-price .orig { text-decoration: line-through; color: var(--c-muted); font-size: 11px; margin-right: 3px; font-weight: 400; }
.qa-footer-price .pct  { font-size: 11px; color: #80CBC4; font-weight: 700; margin-left: 4px; font-family: var(--f-mono); }
.qa-footer-link {
    font-size: 11px; color: var(--c-lime); font-weight: 700;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: gap .2s; flex-shrink: 0;
    font-family: var(--f-mono); letter-spacing: .04em;
}
.qa-footer-link:hover { gap: 7px; }

/* ── TAB PANELS ── */
.qa-tab-panel    { display: none; }
.qa-tab-panel.on { display: block; animation: qaFadeUp .4s ease both; }

/* ── EMPTY / NO RESULTS ── */
.qa-no-results {
    text-align: center; padding: 64px 20px; color: var(--c-muted); font-size: 14px;
}
.qa-no-results i { font-size: 36px; color: var(--c-border2); display: block; margin-bottom: 14px; }

.qa-empty {
    text-align: center; padding: 50px 20px; color: var(--c-muted);
}
.qa-empty i { font-size: 38px; color: var(--c-border2); display: block; margin-bottom: 12px; }
.qa-empty a { color: var(--c-lime); font-weight: 600; }

/* ── LOGIN PROMPT ── */
.qa-login-prompt {
    text-align: center; padding: 80px 20px;
    background: var(--c-surface); border: 1px solid var(--c-border);
    border-radius: 12px; position: relative; overflow: hidden;
}
.qa-login-prompt::before {
    content: ''; position: absolute; top: 0; left: 24px; right: 24px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent); opacity: .35;
}
.qa-login-prompt i  { font-size: 48px; color: var(--c-border2); display: block; margin-bottom: 18px; }
.qa-login-prompt h3 { font-family: var(--f-display); font-size: 24px; font-weight: 700; color: #fff; margin-bottom: 10px; }
.qa-login-prompt p  { color: var(--c-muted); font-size: 14px; margin-bottom: 26px; }
.qa-login-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-lime); color: #000; font-weight: 700;
    padding: 13px 28px; border-radius: 9px;
    font-family: var(--f-display); font-size: 14px;
    letter-spacing: .05em; transition: all .2s;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.qa-login-btn:hover { background: #8FFF1A; box-shadow: 0 0 30px rgba(125,255,0,.35); color: #000; }
</style>

<div class="qa-wrap">

{{-- ── BREADCRUMB ── --}}
<div class="qa-breadcrumb">
    <div class="qa-breadcrumb-inner">
        <a href="{{ route('home') }}">Home</a>
        <i class="las la-angle-right"></i>
        <span>Webinars</span>
    </div>
</div>

{{-- ── HERO ── --}}
<div class="qa-wb-hero">
    <div class="qa-wb-hero-left qa-anim">
        <div class="qa-hero-eyebrow">
            <span class="qa-hero-dot"></span>
            Live &amp; Recorded Sessions
        </div>
        <h1>{{ $heroBanner['title'] }}</h1>
        <p>{{ $heroBanner['description'] }}</p>
    </div>
    @if(!empty($heroBanner['illustration']))
    <div class="qa-wb-hero-right qa-anim d1">
        <img src="{{ $heroBanner['illustration'] }}" alt="{{ $heroBanner['title'] }}">
    </div>
    @endif
</div>

{{-- ── FILTER BAR ── --}}
<div class="qa-filter-bar">
    <div class="qa-main-tabs">
        <button class="qa-main-tab on" onclick="qaSwitchTab(0,this)">All Webinars</button>
        <button class="qa-main-tab"    onclick="qaSwitchTab(1,this)">My Webinars</button>
    </div>
    <div class="qa-dropdowns-row">

        <div class="qa-filter-group">
            <span class="qa-filter-label">Language</span>
            <select class="qa-filter-select" id="filterLang" onchange="qaApplyFilters()">
                <option value="">All</option>
                @foreach($languages as $lang)
                    <option value="{{ strtolower($lang) }}">{{ $lang }}</option>
                @endforeach
            </select>
        </div>

        <div class="qa-filter-group">
            <span class="qa-filter-label">Price</span>
            <select class="qa-filter-select" id="filterPrice" onchange="qaApplyFilters()">
                <option value="">All</option>
                <option value="free">Free</option>
                <option value="paid">Paid</option>
            </select>
        </div>

        <div class="qa-filter-group">
            <span class="qa-filter-label">Proficiency</span>
            <select class="qa-filter-select" id="filterLevel" onchange="qaApplyFilters()">
                <option value="">All</option>
                @foreach($proficiency as $lvl)
                    <option value="{{ strtolower($lvl) }}">{{ $lvl }}</option>
                @endforeach
            </select>
        </div>

        <div class="qa-search-wrap">
            <input class="qa-search-input" type="text" id="qaSearch"
                   placeholder="Search webinars…" oninput="qaApplyFilters()">
            <button class="qa-search-btn" type="button">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
</div>

{{-- ── CONTENT ── --}}
<div class="qa-webinars-wrap">

    {{-- ────────────────────────────────────────
         TAB 0 — ALL WEBINARS
    ──────────────────────────────────────── --}}
    <div class="qa-tab-panel on" id="qaPanel0">

        {{-- Upcoming / Live --}}
        <div class="qa-section-head"><h2>Upcoming &amp; Live Webinars</h2></div>

        @if($upcomingWebinars->isEmpty())
            <div class="qa-empty" style="margin-bottom:40px;">
                <i class="fas fa-calendar-times"></i>
                No upcoming webinars at the moment. Check back soon!
            </div>
        @else
        <div class="qa-wgrid" id="qaUpcomingGrid">
            @foreach($upcomingWebinars as $w)
            @php $isReg = $enrolledWebinarIds->contains($w->id); @endphp
            <div class="qa-wcard"
                 data-type="{{ $w->type }}"
                 data-lang="{{ strtolower($w->language) }}"
                 data-level="{{ $w->level_key }}"
                 data-title="{{ strtolower($w->title) }}">

                <div class="qa-wcard-thumb">
                    <img src="{{ $w->thumbnail_url }}" alt="{{ $w->title }}" loading="lazy">

                    @if($w->status === 'live')
                        <span class="qa-badge live">
                            <span class="qa-live-dot"></span> LIVE
                        </span>
                    @else
                        <span class="qa-badge upcoming">UPCOMING</span>
                    @endif

                    @if($isReg)
                        <span class="qa-enrolled-badge">✓ Registered</span>
                    @endif

                    <div class="qa-thumb-price">
                        <span class="qa-price-text">
                            @if($w->type === 'free')
                                FREE
                                @if($w->mrp > 0)
                                    <span class="strike">₹{{ $w->mrp }}/-</span>
                                @endif
                            @else
                                ₹{{ number_format($w->price) }}/-
                                @if($w->mrp > $w->price)
                                    <span class="strike">₹{{ $w->mrp }}/-</span>
                                @endif
                            @endif
                        </span>
                        <a href="{{ route('webinars.detail', $w->slug) }}" class="qa-view-link">
                            View Details <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="qa-wcard-body">
                    <div class="qa-wcard-title">{{ $w->title }}</div>
                    <span class="qa-wcard-type {{ $w->type }}">{{ strtoupper($w->type) }}</span>
                    <div class="qa-meta-grid">
                        <div class="qa-meta-row">
                            <i class="fas fa-signal"></i>
                            <span class="meta-v">{{ $w->level }}</span>
                        </div>
                        <div class="qa-meta-row">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="meta-v">
                                {{ $w->webinar_date ? $w->webinar_date->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div class="qa-meta-row">
                            <i class="fas fa-clock"></i>
                            <span class="meta-v">{{ $w->duration ?? '—' }}</span>
                        </div>
                        <div class="qa-meta-row">
                            <i class="fas fa-language"></i>
                            <span class="meta-v">{{ $w->language }}</span>
                        </div>
                        @if($w->total_seats)
                        <div class="qa-meta-row" style="grid-column:span 2;">
                            <i class="fas fa-users"></i>
                            <span class="{{ $w->seats_left < 20 ? 'text-danger fw-bold' : 'meta-v' }}">
                                {{ $w->total_enrolled }} registered &nbsp;·&nbsp; {{ $w->seats_left }} seats left
                            </span>
                        </div>
                        @else
                        <div class="qa-meta-row">
                            <i class="fas fa-users"></i>
                            <span class="meta-v">{{ $w->total_enrolled }} registered</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Past Webinars --}}
        <div class="qa-section-head" style="margin-top:12px;"><h2>Past Webinars</h2></div>

        @if($pastWebinars->isEmpty())
            <div class="qa-empty">
                <i class="fas fa-history"></i>
                No past webinars yet.
            </div>
        @else
        <div class="qa-wgrid" id="qaPastGrid">
            @foreach($pastWebinars as $w)
            @php $isReg = $enrolledWebinarIds->contains($w->id); @endphp
            <div class="qa-wcard"
                 data-type="{{ $w->type }}"
                 data-lang="{{ strtolower($w->language) }}"
                 data-level="{{ $w->level_key }}"
                 data-title="{{ strtolower($w->title) }}">

                <div class="qa-wcard-thumb">
                    <img src="{{ $w->thumbnail_url }}" alt="{{ $w->title }}" loading="lazy">
                    @if($isReg)
                        <span class="qa-enrolled-badge">✓ Registered</span>
                    @endif
                </div>

                <div class="qa-wcard-body">
                    <div class="qa-wcard-title">{{ $w->title }}</div>
                    <span class="qa-wcard-type {{ $w->type }}">{{ strtoupper($w->type) }}</span>
                    <div class="qa-meta-grid">
                        <div class="qa-meta-row">
                            <i class="fas fa-signal"></i>
                            <span class="meta-v">{{ $w->level }}</span>
                        </div>
                        <div class="qa-meta-row">
                            <i class="fas fa-calendar-alt" style="color:var(--c-red);"></i>
                            <span class="past-d">
                                {{ $w->webinar_date ? $w->webinar_date->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div class="qa-meta-row">
                            <i class="fas fa-clock"></i>
                            <span class="meta-v">{{ $w->duration ?? '—' }}</span>
                        </div>
                        <div class="qa-meta-row">
                            <i class="fas fa-language"></i>
                            <span class="meta-v">{{ $w->language }}</span>
                        </div>
                    </div>
                </div>

                <div class="qa-wcard-footer">
                    <div class="qa-footer-price">
                        @if($w->type === 'free')
                            FREE
                            @if($w->mrp > 0)
                                <span class="orig">₹{{ $w->mrp }}/-</span>
                                @if($w->discount_label)
                                    <span class="pct">{{ $w->discount_label }}</span>
                                @endif
                            @endif
                        @else
                            ₹{{ number_format($w->price) }}/-
                            @if($w->mrp > $w->price)
                                <span class="orig">₹{{ $w->mrp }}/-</span>
                            @endif
                        @endif
                    </div>
                    <a href="{{ route('webinars.detail', $w->slug) }}" class="qa-footer-link">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- No results message (JS-controlled) --}}
        <div class="qa-no-results" id="qaNoResults" style="display:none;">
            <i class="fas fa-search"></i>
            No webinars found matching your filters.
        </div>

    </div>{{-- /#qaPanel0 --}}

    {{-- ────────────────────────────────────────
         TAB 1 — MY WEBINARS
    ──────────────────────────────────────── --}}
    <div class="qa-tab-panel" id="qaPanel1">

        @if($authUser)
            @php
                $myWebinars = $enrolledWebinarIds->isNotEmpty()
                    ? \App\Models\Webinar::whereIn('id', $enrolledWebinarIds)
                        ->orderByDesc('webinar_date')
                        ->get()
                    : collect();
            @endphp

            @if($myWebinars->isEmpty())
                <div class="qa-empty">
                    <i class="fas fa-video-slash"></i>
                    You have not registered for any webinars yet.
                    <br><br>
                    <a href="{{ route('webinars.index') }}">Browse Webinars →</a>
                </div>
            @else
            <div class="qa-section-head"><h2>My Registered Webinars</h2></div>
            <div class="qa-wgrid">
                @foreach($myWebinars as $w)
                <div class="qa-wcard">
                    <div class="qa-wcard-thumb">
                        <img src="{{ $w->thumbnail_url }}" alt="{{ $w->title }}" loading="lazy">
                        <span class="qa-enrolled-badge">✓ Registered</span>
                        @if($w->status === 'live')
                            <span class="qa-badge live">
                                <span class="qa-live-dot"></span> LIVE
                            </span>
                        @elseif($w->status === 'upcoming')
                            <span class="qa-badge upcoming">UPCOMING</span>
                        @endif
                    </div>
                    <div class="qa-wcard-body">
                        <div class="qa-wcard-title">{{ $w->title }}</div>
                        <span class="qa-wcard-type {{ $w->type }}">{{ strtoupper($w->type) }}</span>
                        <div class="qa-meta-grid">
                            <div class="qa-meta-row">
                                <i class="fas fa-calendar-alt"></i>
                                <span class="meta-v">
                                    {{ $w->webinar_date ? $w->webinar_date->format('d M Y') : '—' }}
                                </span>
                            </div>
                            <div class="qa-meta-row">
                                <i class="fas fa-language"></i>
                                <span class="meta-v">{{ $w->language }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="qa-wcard-footer">
                        <span style="font-size:12px;color:#4DB6AC;font-weight:700;font-family:var(--f-mono);">
                            <i class="fas fa-check-circle"></i> Registered
                        </span>
                        <a href="{{ route('webinars.detail', $w->slug) }}" class="qa-footer-link">
                            View <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

        @else
            <div class="qa-login-prompt">
                <i class="fas fa-lock"></i>
                <h3>Login to View Your Webinars</h3>
                <p>Please login to see the webinars you have registered for.</p>
                <a href="{{ route('user.login') }}" class="qa-login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login Now
                </a>
            </div>
        @endif

    </div>{{-- /#qaPanel1 --}}

</div>{{-- /.qa-webinars-wrap --}}
</div>{{-- /.qa-wrap --}}

<script>
/* ── TAB SWITCH — identical logic ── */
function qaSwitchTab(idx, btn) {
    document.querySelectorAll('.qa-main-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qa-tab-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === idx);
    });
}

/* ── FILTER — identical logic ── */
function qaApplyFilters() {
    var lang   = document.getElementById('filterLang').value.toLowerCase();
    var price  = document.getElementById('filterPrice').value.toLowerCase();
    var level  = document.getElementById('filterLevel').value.toLowerCase();
    var search = document.getElementById('qaSearch').value.toLowerCase().trim();

    var allCards = document.querySelectorAll(
        '#qaUpcomingGrid .qa-wcard, #qaPastGrid .qa-wcard'
    );
    var visible = 0;

    allCards.forEach(function(card) {
        var ok = true;
        if (lang   && (card.dataset.lang  || '').indexOf(lang)   === -1) ok = false;
        if (price  && (card.dataset.type  || '') !== price)               ok = false;
        if (level  && (card.dataset.level || '') !== level)               ok = false;
        if (search && (card.dataset.title || '').indexOf(search)  === -1) ok = false;
        card.style.display = ok ? '' : 'none';
        if (ok) visible++;
    });

    var noRes = document.getElementById('qaNoResults');
    if (noRes) noRes.style.display = (visible === 0) ? 'block' : 'none';
}
</script>

@endsection