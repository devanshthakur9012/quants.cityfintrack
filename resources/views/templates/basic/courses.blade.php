{{-- FILE: resources/views/themes/{active_theme}/courses.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — COURSES LISTING  v2.0
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
    --c-blue:     #00B8D4;
    --c-red:      #EF5350;
    --c-teal:     #26A69A;
    --c-amber:    #FFA726;
    --c-purple:   #AB47BC;
    --c-green:    #66BB6A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.cr-page { font-family: var(--f-sans); background: var(--c-bg); min-height: 80vh; color: var(--c-text); }
.cr-page *, .cr-page *::before, .cr-page *::after { box-sizing: border-box; }
.cr-page a { text-decoration: none; color: inherit; }

@keyframes crFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.cr-anim    { animation: crFadeUp .5s ease both; }
.cr-anim.d1 { animation-delay: .08s; }
.cr-anim.d2 { animation-delay: .16s; }

/* ── BREADCRUMB ── */
.cr-breadcrumb {
    background: var(--c-surface); border-bottom: 1px solid var(--c-border); padding: 12px 32px;
}
.cr-breadcrumb-inner {
    font-size: 12px; color: var(--c-muted); display: flex; align-items: center;
    gap: 7px; font-family: var(--f-mono);
}
.cr-breadcrumb-inner a { color: var(--c-lime); font-weight: 600; transition: opacity .2s; }
.cr-breadcrumb-inner a:hover { opacity: .75; }
.cr-breadcrumb-inner i { font-size: 10px; color: var(--c-border2); }

/* ── HERO ── */
.cr-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg); border-bottom: 1px solid var(--c-border);
    padding: 52px 60px 44px;
    display: flex; align-items: center; gap: 36px;
}
.cr-hero::before {
    content: ''; position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.cr-hero::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 40% 80% at 5% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.cr-hero-left { flex: 1; min-width: 0; position: relative; z-index: 1; }
.cr-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 12px; font-family: var(--f-mono);
}
.cr-eyebrow::before { content: ''; display: block; width: 16px; height: 1px; background: var(--c-lime); }
.cr-hero-left h1 {
    font-family: var(--f-display); font-size: clamp(24px, 3.5vw, 42px);
    font-weight: 800; color: #fff; margin: 0 0 12px; line-height: 1.08; letter-spacing: -.015em;
}
.cr-hero-left p { font-size: 14px; color: var(--c-muted); line-height: 1.8; max-width: 580px; margin: 0; }

/* hero image grid */
.cr-hero-right {
    position: relative; z-index: 1;
    flex-shrink: 0; display: grid; grid-template-columns: 1fr 1fr;
    gap: 8px; width: 260px;
}
.cr-hero-right img {
    width: 100%; height: 78px; object-fit: cover; border-radius: 8px;
    border: 1px solid var(--c-border2); display: block;
    transition: transform .3s;
}
.cr-hero-right img:hover { transform: scale(1.04); }

@media(max-width:860px) {
    .cr-hero { flex-direction: column; padding: 32px 20px 28px; }
    .cr-hero-right { width: 100%; grid-template-columns: repeat(4,1fr); }
    .cr-hero-right img { height: 60px; }
}
@media(max-width:500px) { .cr-hero-right { grid-template-columns: 1fr 1fr; } }

/* ── FILTER BAR ── */
.cr-filter-bar {
    background: var(--c-surface); border-bottom: 1px solid var(--c-border);
    box-shadow: 0 4px 24px rgba(0,0,0,.3);
    position: sticky; top: 0; z-index: 300;
}
.cr-filter-inner { padding: 0 60px; }

/* Status tabs */
.cr-tabs {
    display: flex; border-bottom: 1px solid var(--c-border);
    overflow-x: auto; scrollbar-width: none;
}
.cr-tabs::-webkit-scrollbar { display: none; }
.cr-tab {
    padding: 14px 22px; font-size: 13px; font-weight: 600; color: var(--c-muted);
    cursor: pointer; border: none; background: none;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: all .2s; font-family: var(--f-mono); white-space: nowrap;
    display: flex; align-items: center; gap: 7px; letter-spacing: .04em;
}
.cr-tab-count {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    color: var(--c-muted); font-size: 10px; font-weight: 700;
    padding: 2px 7px; border-radius: 100px; transition: all .2s;
}
.cr-tab.active { color: var(--c-lime); border-bottom-color: var(--c-lime); }
.cr-tab.active .cr-tab-count { background: var(--c-lime-dim); border-color: rgba(125,255,0,.25); color: var(--c-lime); }
.cr-tab:hover:not(.active) { color: var(--c-text); }

/* Filter dropdowns row */
.cr-filters-row {
    display: flex; align-items: flex-end; gap: 12px;
    padding: 11px 0 10px; flex-wrap: wrap;
}
.cr-fgroup { display: flex; flex-direction: column; gap: 4px; }
.cr-flabel {
    font-size: 10px; font-weight: 700; color: var(--c-muted);
    text-transform: uppercase; letter-spacing: .1em; font-family: var(--f-mono);
}
.cr-fselect {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 7px 26px 7px 11px;
    font-size: 12px; color: var(--c-text); font-family: var(--f-mono); font-weight: 600;
    appearance: none; cursor: pointer; outline: none; min-width: 110px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 9px center;
    transition: border-color .2s;
}
.cr-fselect:focus { border-color: rgba(125,255,0,.45); }
.cr-fselect.active-filter { border-color: rgba(125,255,0,.4); color: var(--c-lime); }
.cr-fselect option { background: var(--c-panel); }

/* reset button */
.cr-reset-btn {
    font-size: 11px; color: var(--c-red); font-weight: 700; background: none; border: none;
    cursor: pointer; padding: 0; display: flex; align-items: center; gap: 4px;
    white-space: nowrap; font-family: var(--f-mono); letter-spacing: .04em;
    transition: opacity .2s;
}
.cr-reset-btn:hover { opacity: .75; }

/* search */
.cr-search-wrap {
    display: flex; align-items: stretch;
    border: 1px solid var(--c-border2); border-radius: 7px;
    overflow: hidden; margin-left: auto;
    background: var(--c-panel); transition: border-color .2s;
}
.cr-search-wrap:focus-within { border-color: rgba(125,255,0,.45); }
.cr-search-input {
    border: none; padding: 8px 13px; font-size: 12px; color: var(--c-text);
    outline: none; width: 200px; font-family: var(--f-mono); background: transparent;
}
.cr-search-input::placeholder { color: var(--c-muted); }
.cr-search-btn {
    background: var(--c-lime); border: none; padding: 0 14px;
    color: #000; font-size: 13px; cursor: pointer;
    transition: background .2s; display: flex; align-items: center;
}
.cr-search-btn:hover { background: #8FFF1A; }

/* Category pills row */
.cr-pills-wrap {
    display: flex; flex-wrap: wrap; gap: 7px;
    padding: 10px 0 12px; border-top: 1px solid var(--c-border);
}
.cr-pill {
    padding: 5px 13px; border-radius: 100px; font-size: 11px; font-weight: 600;
    border: 1px solid var(--c-border2); background: transparent; color: var(--c-muted);
    cursor: pointer; transition: all .2s; white-space: nowrap;
    font-family: var(--f-mono); display: inline-flex; align-items: center; gap: 5px;
}
.cr-pill.active, .cr-pill:hover { background: var(--c-lime-dim); border-color: rgba(125,255,0,.3); color: var(--c-lime); }

@media(max-width:860px) {
    .cr-filter-inner { padding: 0 16px; }
    .cr-search-wrap  { margin-left: 0; width: 100%; }
    .cr-search-input { width: 100%; flex: 1; }
}

/* ── CONTENT ── */
.cr-content { padding: 24px 60px 72px; }
.cr-result-bar {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 20px; flex-wrap: wrap; gap: 10px;
}
.cr-result-count { font-size: 12px; color: var(--c-muted); font-family: var(--f-mono); }
.cr-result-count strong { color: var(--c-text); }
.cr-sort-select {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 7px; padding: 7px 26px 7px 11px; font-size: 12px;
    color: var(--c-text); font-family: var(--f-mono); font-weight: 600;
    appearance: none; cursor: pointer; outline: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 9px center;
    transition: border-color .2s;
}
.cr-sort-select:focus { border-color: rgba(125,255,0,.45); }
.cr-sort-select option { background: var(--c-panel); }

/* ── GRID ── */
.cr-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 18px; }
@media(max-width:1100px) { .cr-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:600px)  { .cr-grid { grid-template-columns: 1fr; } }
@media(max-width:860px)  { .cr-content { padding: 18px 16px 56px; } }

/* ── COURSE CARD ── */
.cr-card {
    background: var(--c-surface); border-radius: 10px;
    border: 1px solid var(--c-border);
    overflow: hidden; display: flex; flex-direction: column;
    position: relative; cursor: pointer;
    transition: border-color .25s, transform .25s, box-shadow .25s;
}
.cr-card::before {
    content: ''; position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .3s; z-index: 2;
}
.cr-card:hover {
    border-color: rgba(125,255,0,.2); transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(0,0,0,.45);
}
.cr-card:hover::before { opacity: 1; }

/* thumbnail */
.cr-thumb {
    position: relative; aspect-ratio: 16/9; overflow: hidden;
    background: var(--c-panel); flex-shrink: 0;
}
.cr-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s;
}
.cr-card:hover .cr-thumb img { transform: scale(1.05); }

/* thumb badges */
.cr-featured-star {
    position: absolute; top: 10px; left: 10px;
    background: var(--c-lime); color: #000;
    font-size: 10px; font-weight: 700; padding: 3px 9px;
    border-radius: 5px; letter-spacing: .06em; text-transform: uppercase;
    display: flex; align-items: center; gap: 4px; font-family: var(--f-mono);
}
.cr-status-badge {
    position: absolute; top: 10px; right: 10px;
    font-size: 10px; font-weight: 700; letter-spacing: .07em;
    padding: 4px 10px; border-radius: 5px; text-transform: uppercase;
    font-family: var(--f-mono); backdrop-filter: blur(6px);
}
.cr-status-badge.ongoing  { background: rgba(255,167,38,.85); color: #000; }
.cr-status-badge.upcoming { background: rgba(38,166,154,.85); color: #fff; }
.cr-status-badge.recorded { background: rgba(0,184,212,.85);  color: #000; }

.cr-cat-chip {
    position: absolute; bottom: 9px; left: 10px;
    font-size: 10px; font-weight: 600; padding: 3px 10px; border-radius: 4px;
    background: rgba(11,14,17,.75); color: var(--c-text); backdrop-filter: blur(4px);
    border: 1px solid var(--c-border2); font-family: var(--f-mono);
    max-width: 160px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* card body */
.cr-body { padding: 14px 16px 10px; flex: 1; display: flex; flex-direction: column; gap: 8px; }
.cr-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    color: #fff; line-height: 1.3; flex: 1;
}
.cr-title a { color: inherit; transition: color .2s; }
.cr-title a:hover { color: var(--c-lime); }

/* tags */
.cr-tags { display: flex; flex-wrap: wrap; gap: 5px; }
.cr-tag {
    display: inline-flex; align-items: center; gap: 4px; font-size: 11px;
    color: var(--c-muted); background: var(--c-panel); padding: 3px 9px;
    border-radius: 5px; border: 1px solid var(--c-border2); white-space: nowrap;
    font-family: var(--f-mono);
}
.cr-tag i { font-size: 10px; color: var(--c-lime); }
.cr-tag.cert { background: rgba(255,167,38,.08); border-color: rgba(255,167,38,.2); color: var(--c-amber); }
.cr-tag.cert i { color: var(--c-amber); }
.cr-tag.dur  { background: rgba(38,166,154,.08); border-color: rgba(38,166,154,.2); color: #80CBC4; }
.cr-tag.dur i { color: #80CBC4; }

.cr-short-desc {
    font-size: 12px; color: var(--c-muted); line-height: 1.6;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* curriculum strip */
.cr-curriculum-strip {
    display: flex; gap: 10px; align-items: center;
    padding: 7px 16px 8px; background: rgba(0,0,0,.2);
    border-top: 1px solid var(--c-border); flex-wrap: wrap;
}
.cr-cs-item { display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--c-muted); font-family: var(--f-mono); }
.cr-cs-item i { font-size: 10px; color: var(--c-blue); }
.cr-cs-sep { color: var(--c-border2); font-size: 11px; }

/* card footer */
.cr-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px 12px; border-top: 1px solid var(--c-border);
    background: rgba(0,0,0,.25); gap: 8px; margin-top: auto;
}
.cr-price { font-family: var(--f-display); line-height: 1.2; }
.cr-price .cr-price-main { font-size: 18px; font-weight: 700; color: #fff; }
.cr-price .cr-price-free { font-size: 16px; font-weight: 700; color: #80CBC4; }
.cr-price .cr-price-orig { text-decoration: line-through; color: var(--c-muted); font-size: 11px; margin-left: 4px; }
.cr-price .cr-price-disc { font-size: 10px; font-weight: 700; color: #80CBC4; background: rgba(38,166,154,.1); padding: 2px 6px; border-radius: 4px; margin-left: 4px; font-family: var(--f-mono); }

.cr-enroll-btn {
    font-size: 12px; font-weight: 700; color: var(--c-lime);
    display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
    transition: gap .2s; border: none; background: none; padding: 0;
    cursor: pointer; font-family: var(--f-mono); letter-spacing: .04em;
}
.cr-enroll-btn:hover { gap: 9px; }

/* empty states */
.cr-empty {
    grid-column: 1/-1; text-align: center; padding: 70px 20px; color: var(--c-muted);
}
.cr-empty-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 18px; font-size: 28px;
}
.cr-empty h4 { font-family: var(--f-display); font-size: 20px; color: var(--c-text); margin-bottom: 6px; }
.cr-empty p  { font-size: 13px; font-family: var(--f-mono); }

.cr-no-results-js {
    display: none; grid-column: 1/-1; text-align: center; padding: 60px 20px; color: var(--c-muted);
}
.cr-no-results-js-icon {
    width: 60px; height: 60px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px;
}
.cr-no-results-js p { font-size: 12px; font-family: var(--f-mono); }
</style>

<div class="cr-page">

{{-- ── BREADCRUMB ── --}}
<div class="cr-breadcrumb">
    <div class="cr-breadcrumb-inner">
        <a href="{{ url('/') }}">Home</a>
        <i class="las la-angle-right"></i>
        <span>Courses</span>
    </div>
</div>

{{-- ── HERO ── --}}
<div class="cr-hero">
    <div class="cr-hero-left cr-anim">
        <div class="cr-eyebrow">Trading Education</div>
        <h1>{{ $heroBanner['title'] }}</h1>
        <p>{{ $heroBanner['description'] }}</p>
    </div>
    <div class="cr-hero-right cr-anim d2">
        @forelse($heroBanner['banners'] as $banner)
            <img src="{{ $banner }}" alt="Course" loading="lazy"
                 onerror="this.src='https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=400'">
        @empty
            @for($b = 0; $b < 4; $b++)
            <img src="https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=400"
                 alt="Course" loading="lazy">
            @endfor
        @endforelse
    </div>
</div>

{{-- ── STICKY FILTER BAR ── --}}
<div class="cr-filter-bar">
    <div class="cr-filter-inner">

        {{-- Status tabs --}}
        <div class="cr-tabs">
            <button class="cr-tab active" data-tab="all" onclick="crSwitchTab('all',this)">
                All Courses <span class="cr-tab-count">{{ $totalCounts['all'] }}</span>
            </button>
            <button class="cr-tab" data-tab="ongoing" onclick="crSwitchTab('ongoing',this)">
                Ongoing <span class="cr-tab-count">{{ $totalCounts['ongoing'] }}</span>
            </button>
            <button class="cr-tab" data-tab="upcoming" onclick="crSwitchTab('upcoming',this)">
                Upcoming <span class="cr-tab-count">{{ $totalCounts['upcoming'] }}</span>
            </button>
            <button class="cr-tab" data-tab="recorded" onclick="crSwitchTab('recorded',this)">
                Recorded <span class="cr-tab-count">{{ $totalCounts['recorded'] }}</span>
            </button>
        </div>

        {{-- Dropdowns --}}
        <div class="cr-filters-row">
            <div class="cr-fgroup">
                <span class="cr-flabel">Language</span>
                <select class="cr-fselect {{ $filterLang ? 'active-filter' : '' }}" id="crLang" onchange="crFilter()">
                    <option value="">All Languages</option>
                    @foreach($filterLanguages as $lang)
                        <option value="{{ strtolower($lang) }}" @selected($filterLang === strtolower($lang))>{{ $lang }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cr-fgroup">
                <span class="cr-flabel">Level</span>
                <select class="cr-fselect {{ $filterLevel ? 'active-filter' : '' }}" id="crLevel" onchange="crFilter()">
                    <option value="">All Levels</option>
                    @foreach($filterLevels as $lvl)
                        <option value="{{ strtolower($lvl) }}" @selected($filterLevel === strtolower($lvl))>{{ $lvl }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cr-fgroup">
                <span class="cr-flabel">Price</span>
                <select class="cr-fselect {{ $filterType ? 'active-filter' : '' }}" id="crType" onchange="crFilter()">
                    <option value="">All Prices</option>
                    <option value="free" @selected($filterType === 'free')>Free</option>
                    <option value="paid" @selected($filterType === 'paid')>Paid</option>
                </select>
            </div>
            <div class="cr-fgroup">
                <span class="cr-flabel">Mode</span>
                <select class="cr-fselect {{ $filterMode ? 'active-filter' : '' }}" id="crMode" onchange="crFilter()">
                    <option value="">All Modes</option>
                    @foreach($filterModes as $mode)
                        <option value="{{ strtolower($mode) }}" @selected($filterMode === strtolower($mode))>{{ $mode }}</option>
                    @endforeach
                </select>
            </div>
            <div class="cr-fgroup">
                <span class="cr-flabel">Certificate</span>
                <select class="cr-fselect" id="crCert" onchange="crFilter()">
                    <option value="">All</option>
                    <option value="1">With Certificate</option>
                    <option value="0">Without Certificate</option>
                </select>
            </div>
            @if($filterLang || $filterLevel || $filterType || $filterMode || $filterCategory || $filterSearch)
            <button class="cr-reset-btn" onclick="crResetAll()">
                <i class="fas fa-times-circle"></i> Reset
            </button>
            @endif
            <div class="cr-search-wrap">
                <input class="cr-search-input" type="text" id="crSearch"
                       placeholder="Search courses…"
                       value="{{ $filterSearch }}"
                       oninput="crFilter()">
                <button class="cr-search-btn" type="button"><i class="fas fa-search"></i></button>
            </div>
        </div>

        {{-- Category pills --}}
        @if($categories->count())
        <div class="cr-pills-wrap">
            <button class="cr-pill {{ !$filterCategory ? 'active' : '' }}"
                    data-cat="" onclick="crSetCategory('',this)">
                <i class="fas fa-th-large"></i> All
            </button>
            @foreach($categories as $cat)
            <button class="cr-pill {{ $filterCategory == $cat->id ? 'active' : '' }}"
                    data-cat="{{ $cat->id }}" onclick="crSetCategory('{{ $cat->id }}',this)">
                @if($cat->icon)<i class="fas {{ $cat->icon }}"></i>@endif
                {{ $cat->name }}
                <span style="opacity:.5;font-size:10px;">({{ $cat->courses_count }})</span>
            </button>
            @endforeach
        </div>
        @endif

    </div>
</div>

{{-- ── CONTENT ── --}}
<div class="cr-content">
    <div class="cr-result-bar cr-anim">
        <p class="cr-result-count">
            Showing <strong id="crVisibleCount">{{ $allCourses->count() }}</strong> course(s)
            @if($filterSearch) for "<strong>{{ $filterSearch }}</strong>" @endif
        </p>
        <select class="cr-sort-select" id="crSort" onchange="crSortCards()">
            <option value="default">Sort: Default</option>
            <option value="price_asc">Price: Low → High</option>
            <option value="price_desc">Price: High → Low</option>
            <option value="title_asc">Name: A – Z</option>
            <option value="title_desc">Name: Z – A</option>
        </select>
    </div>

    <div class="cr-grid cr-anim d2" id="crGrid">
        @forelse($allCourses as $c)
        @php
            $lessonCount  = $c->lessons->count();
            $sectionCount = $c->sections ? $c->sections->count() : 0;
            $totalSecs    = $c->lessons->sum('duration_seconds');
            $dH           = floor($totalSecs / 3600);
            $dM           = floor(($totalSecs % 3600) / 60);
            $courseDuration = $totalSecs > 0 ? ($dH > 0 ? "{$dH}h {$dM}m" : "{$dM}m") : null;
        @endphp
        <div class="cr-card"
             data-status="{{ $c->status }}"
             data-lang="{{ strtolower($c->language) }}"
             data-level="{{ strtolower($c->level) }}"
             data-type="{{ $c->type }}"
             data-mode="{{ strtolower($c->mode) }}"
             data-cat="{{ $c->course_category_id }}"
             data-title="{{ strtolower($c->title) }}"
             data-price="{{ $c->price }}"
             data-cert="{{ $c->has_certificate ? '1' : '0' }}"
             data-url="{{ route('courses.detail', $c->slug) }}"
             onclick="window.location=this.dataset.url">

            <div class="cr-thumb">
                <img src="{{ $c->thumbnail_url }}" alt="{{ $c->title }}" loading="lazy"
                     onerror="this.src='https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=600'">
                @if($c->is_featured)
                    <span class="cr-featured-star"><i class="fas fa-star"></i> Featured</span>
                @endif
                @if($c->status === 'ongoing')
                    <span class="cr-status-badge ongoing">● Live</span>
                @elseif($c->status === 'upcoming')
                    <span class="cr-status-badge upcoming">Upcoming</span>
                @elseif($c->status === 'recorded')
                    <span class="cr-status-badge recorded">Recorded</span>
                @endif
                @if($c->category)
                    <span class="cr-cat-chip">{{ $c->category->name }}</span>
                @endif
            </div>

            <div class="cr-body">
                <div class="cr-title">
                    <a href="{{ route('courses.detail', $c->slug) }}" onclick="event.stopPropagation()">
                        {{ $c->title }}
                    </a>
                </div>
                <div class="cr-tags">
                    <span class="cr-tag"><i class="fas fa-signal"></i> {{ ucfirst($c->level) }}</span>
                    <span class="cr-tag"><i class="fas fa-language"></i> {{ ucfirst($c->language) }}</span>
                    <span class="cr-tag"><i class="fas fa-globe"></i> {{ ucfirst($c->mode) }}</span>
                    @if($courseDuration)
                        <span class="cr-tag dur"><i class="fas fa-clock"></i> {{ $courseDuration }}</span>
                    @endif
                    @if($c->has_certificate)
                        <span class="cr-tag cert"><i class="fas fa-certificate"></i> Certificate</span>
                    @endif
                </div>
                @if($c->short_description)
                    <div class="cr-short-desc">{{ $c->short_description }}</div>
                @endif
            </div>

            @if($sectionCount || $lessonCount)
            <div class="cr-curriculum-strip">
                @if($sectionCount)
                    <div class="cr-cs-item"><i class="fas fa-layer-group"></i> {{ $sectionCount }} Sections</div>
                @endif
                @if($sectionCount && $lessonCount)<span class="cr-cs-sep">·</span>@endif
                @if($lessonCount)
                    <div class="cr-cs-item"><i class="fas fa-play-circle"></i> {{ $lessonCount }} Lessons</div>
                @endif
            </div>
            @endif

            <div class="cr-footer">
                <div class="cr-price">
                    @if($c->type === 'free')
                        <span class="cr-price-free">FREE</span>
                    @else
                        <span class="cr-price-main">₹{{ number_format($c->price) }}/-</span>
                        @if($c->mrp && $c->mrp > $c->price)
                            <span class="cr-price-orig">₹{{ number_format($c->mrp) }}/-</span>
                        @endif
                        @if($c->discount_label)
                            <span class="cr-price-disc">{{ $c->discount_label }}</span>
                        @endif
                    @endif
                </div>
                <a href="{{ route('courses.detail', $c->slug) }}"
                   class="cr-enroll-btn" onclick="event.stopPropagation()">
                    View Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        @empty
        <div class="cr-empty">
            <div class="cr-empty-icon"><i class="fas fa-book-open"></i></div>
            <h4>No Courses Available Yet</h4>
            <p>Check back soon — new courses are added regularly.</p>
        </div>
        @endforelse

        <div class="cr-no-results-js" id="crNoResults">
            <div class="cr-no-results-js-icon"><i class="fas fa-search"></i></div>
            <p>No courses match your filters. Try adjusting or resetting them.</p>
        </div>
    </div>
</div>

</div>{{-- /.cr-page --}}

<script>
(function () {
    var activeTab = 'all';
    var activeCat = '{{ $filterCategory }}';

    function crSwitchTab(tab, btn) {
        activeTab = tab;
        document.querySelectorAll('.cr-tab').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        crFilter();
    }
    window.crSwitchTab = crSwitchTab;

    function crSetCategory(catId, el) {
        activeCat = catId;
        document.querySelectorAll('.cr-pill').forEach(function(p) { p.classList.remove('active'); });
        el.classList.add('active');
        crFilter();
    }
    window.crSetCategory = crSetCategory;

    function crFilter() {
        var lang   = document.getElementById('crLang').value;
        var level  = document.getElementById('crLevel').value;
        var type   = document.getElementById('crType').value;
        var mode   = document.getElementById('crMode').value;
        var cert   = document.getElementById('crCert').value;
        var search = document.getElementById('crSearch').value.toLowerCase().trim();
        var cards  = document.querySelectorAll('#crGrid .cr-card');
        var visible = 0;

        cards.forEach(function(card) {
            var ok = true;
            if (activeTab !== 'all' && card.dataset.status !== activeTab) ok = false;
            if (lang   && card.dataset.lang  !== lang)  ok = false;
            if (level  && card.dataset.level !== level) ok = false;
            if (type   && card.dataset.type  !== type)  ok = false;
            if (mode   && card.dataset.mode  !== mode)  ok = false;
            if (cert   && card.dataset.cert  !== cert)  ok = false;
            if (activeCat && card.dataset.cat !== activeCat) ok = false;
            if (search && card.dataset.title.indexOf(search) === -1) ok = false;
            card.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });

        var countEl = document.getElementById('crVisibleCount');
        if (countEl) countEl.textContent = visible;

        var noRes = document.getElementById('crNoResults');
        if (noRes) noRes.style.display = (visible === 0 && cards.length > 0) ? 'block' : 'none';

        ['crLang','crLevel','crType','crMode'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.classList.toggle('active-filter', el.value !== '');
        });
    }
    window.crFilter = crFilter;

    function crSortCards() {
        var sort  = document.getElementById('crSort').value;
        var grid  = document.getElementById('crGrid');
        var cards = Array.from(grid.querySelectorAll('.cr-card'));
        cards.sort(function(a, b) {
            if (sort === 'price_asc')  return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
            if (sort === 'price_desc') return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
            if (sort === 'title_asc')  return a.dataset.title.localeCompare(b.dataset.title);
            if (sort === 'title_desc') return b.dataset.title.localeCompare(a.dataset.title);
            return 0;
        });
        cards.forEach(function(card) { grid.appendChild(card); });
    }
    window.crSortCards = crSortCards;

    function crResetAll() {
        ['crLang','crLevel','crType','crMode','crCert'].forEach(function(id) {
            var el = document.getElementById(id); if (el) el.value = '';
        });
        document.getElementById('crSearch').value = '';
        document.getElementById('crSort').value   = 'default';
        activeTab = 'all'; activeCat = '';
        document.querySelectorAll('.cr-tab').forEach(function(b, i) { b.classList.toggle('active', i === 0); });
        document.querySelectorAll('.cr-pill').forEach(function(p, i) { p.classList.toggle('active', i === 0); });
        crFilter();
    }
    window.crResetAll = crResetAll;

    (function() {
        @if($filterStatus)
        activeTab = '{{ $filterStatus }}';
        var tab = document.querySelector('[data-tab="{{ $filterStatus }}"]');
        if (tab) {
            document.querySelectorAll('.cr-tab').forEach(function(b) { b.classList.remove('active'); });
            tab.classList.add('active');
        }
        @endif
        crFilter();
    })();
})();
</script>
@endsection