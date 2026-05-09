@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   VARIABLES
========================================= */
:root {
    --gold:    #F5A623;
    --gold2:   #FFD06A;
    --dark:    #0D1B2A;
    --card-bg: #ffffff;
    --bg-page: #f4f6fb;
    --txt:     #1a1a2e;
    --muted:   #667788;
    --bdr:     #e5e9f2;
}

*, *::before, *::after { box-sizing: border-box; }

@keyframes mdFadeUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: none; }
}
.md-anim     { animation: mdFadeUp .65s ease both; }
.md-anim.d1  { animation-delay: .1s; }
.md-anim.d2  { animation-delay: .2s; }
.md-anim.d3  { animation-delay: .3s; }
.md-anim.d4  { animation-delay: .4s; }

@keyframes pulseDot {
    0%,100% { transform: scale(1);  opacity: 1; }
    50%     { transform: scale(.6); opacity: .4; }
}

/* =========================================
   HERO
========================================= */
.qmd-hero {
    background: linear-gradient(135deg, #0D1B2A 0%, #162844 60%, #1a3560 100%);
    padding: 64px 48px 52px;
    position: relative; overflow: hidden;
}
.qmd-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 70% 50%, rgba(245,166,35,.13) 0%, transparent 65%);
    pointer-events: none;
}
.qmd-hero-inner {
    max-width: 1200px; margin: 0 auto;
    display: flex; align-items: center; gap: 40px;
}
.qmd-hero-text { flex: 1; }
.qmd-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(245,166,35,.15); border: 1px solid rgba(245,166,35,.35);
    border-radius: 30px; padding: 6px 16px; margin-bottom: 20px;
    font-size: 12px; font-weight: 700; color: var(--gold);
    letter-spacing: .1em; text-transform: uppercase;
}
.qmd-hero-dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--gold);
    animation: pulseDot 1.4s ease infinite;
}
.qmd-hero h1 {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(34px, 5vw, 58px); font-weight: 700;
    color: #fff; margin: 0 0 16px; line-height: 1.05;
}
.qmd-hero h1 span { color: var(--gold); }
.qmd-hero p {
    font-size: 15px; color: rgba(255,255,255,.62);
    line-height: 1.75; max-width: 560px; margin: 0 0 28px;
}
.qmd-hero-stats { display: flex; gap: 32px; flex-wrap: wrap; }
.qmd-stat-box   { display: flex; flex-direction: column; }
.qmd-stat-val {
    font-family: 'Rajdhani', sans-serif;
    font-size: 28px; font-weight: 700; color: var(--gold); line-height: 1;
}
.qmd-stat-lbl { font-size: 12px; color: rgba(255,255,255,.5); margin-top: 2px; }

.qmd-hero-img { flex-shrink: 0; width: 260px; }
.qmd-hero-img img {
    width: 100%; object-fit: contain; display: block;
    filter: drop-shadow(0 12px 40px rgba(0,0,0,.4));
}

@media(max-width:768px){
    .qmd-hero { padding: 40px 20px 36px; }
    .qmd-hero-inner { flex-direction: column; }
    .qmd-hero-img { width: 160px; }
    .qmd-hero-stats { gap: 20px; }
}

/* =========================================
   PRESS LOGOS STRIP
========================================= */
.qmd-press-strip {
    background: #fff; border-bottom: 1px solid var(--bdr);
    padding: 18px 48px;
}
.qmd-press-inner {
    max-width: 1200px; margin: 0 auto;
    display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
}
.qmd-press-label {
    font-size: 11px; font-weight: 700; color: #aaa;
    text-transform: uppercase; letter-spacing: .1em; white-space: nowrap; flex-shrink: 0;
}
.qmd-press-logos {
    display: flex; align-items: center; gap: 28px; flex-wrap: wrap; flex: 1;
}
.qmd-press-logo {
    height: 22px; object-fit: contain;
    filter: grayscale(1) opacity(.45);
    transition: filter .25s;
}
.qmd-press-logo:hover { filter: grayscale(0) opacity(1); }

@media(max-width:768px){
    .qmd-press-strip { padding: 16px 16px; }
    .qmd-press-logo  { height: 18px; }
    .qmd-press-logos { gap: 18px; }
}

/* =========================================
   FILTER BAR
========================================= */
.qmd-filter-bar {
    background: #fff; border-bottom: 1px solid var(--bdr);
    padding: 0 48px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
     top: 0; z-index: 100;
}
.qmd-tabs-row { display: flex; gap: 0; border-bottom: 2px solid #f0f0f0; }
.qmd-tab {
    padding: 15px 22px; font-size: 14px; font-weight: 600; color: #888;
    cursor: pointer; border: none; background: none;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    transition: all .2s; font-family: 'Exo 2', sans-serif; white-space: nowrap;
}
.qmd-tab.on { color: var(--gold); border-bottom-color: var(--gold); }
.qmd-tab:hover:not(.on) { color: #333; }

.qmd-filter-row {
    display: flex; align-items: flex-end; gap: 14px;
    padding: 13px 0 12px; flex-wrap: wrap;
}
.qmd-filter-group { display: flex; flex-direction: column; gap: 3px; }
.qmd-filter-label {
    font-size: 10.5px; color: #999; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
}
.qmd-filter-select {
    border: 1px solid #ddd; border-radius: 6px;
    padding: 7px 28px 7px 10px; font-size: 13px; color: #333;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance: none; cursor: pointer; font-family: 'Exo 2', sans-serif;
    outline: none; min-width: 110px; transition: border-color .2s;
}
.qmd-filter-select:focus { border-color: var(--gold); }

.qmd-search-wrap {
    display: flex; overflow: hidden; border: 1px solid #ddd;
    border-radius: 6px; margin-left: auto;
}
.qmd-search-input {
    border: none; padding: 8px 14px; font-size: 13px; color: #333;
    outline: none; width: 200px; font-family: 'Exo 2', sans-serif;
}
.qmd-search-btn {
    background: var(--gold); border: none; padding: 0 16px;
    color: #fff; cursor: pointer; display: flex; align-items: center; font-size: 13px;
}

.qmd-pills-row {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding: 10px 0 14px; border-top: 1px solid #f5f5f5;
}
.qmd-pill {
    padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    border: 1px solid #e0e0e0; background: #fafafa; color: #555;
    cursor: pointer; transition: all .2s; font-family: 'Exo 2', sans-serif; white-space: nowrap;
}
.qmd-pill.on, .qmd-pill:hover {
    background: rgba(245,166,35,.12); border-color: rgba(245,166,35,.5); color: #b87800;
}

@media(max-width:768px){ .qmd-filter-bar { padding: 0 16px; } }

/* =========================================
   CONTENT AREA
========================================= */
.qmd-content { background: var(--bg-page); padding: 36px 48px 72px; min-height: 60vh; }
@media(max-width:768px){ .qmd-content { padding: 24px 16px 56px; } }

.qmd-section-head {
    display: flex; align-items: center; gap: 14px; margin-bottom: 24px;
}
.qmd-section-head h2 {
    font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700;
    color: var(--txt); margin: 0; white-space: nowrap;
}
.qmd-section-head::after {
    content: ''; flex: 1; height: 2px;
    background: linear-gradient(90deg, var(--gold) 0%, transparent 100%);
    border-radius: 2px;
}

/* =========================================
   FEATURED MEDIA CARD
========================================= */
.qmd-featured {
    background: linear-gradient(135deg, #0D1B2A, #1a3560);
    border-radius: 16px; overflow: hidden;
    display: grid; grid-template-columns: 1fr 360px;
    margin-bottom: 36px; border: 1px solid rgba(245,166,35,.2);
    box-shadow: 0 8px 40px rgba(0,0,0,.12);
    position: relative;
}
.qmd-featured::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 80% 50%, rgba(245,166,35,.1), transparent 60%);
    pointer-events: none;
}
.qmd-feat-body {
    padding: 40px 44px; display: flex; flex-direction: column; justify-content: center;
    position: relative; z-index: 1;
}
.qmd-feat-badge {
    display: inline-flex; align-items: center; gap: 7px;
    background: rgba(245,166,35,.18); border: 1px solid rgba(245,166,35,.4);
    border-radius: 30px; padding: 5px 14px; margin-bottom: 16px;
    font-size: 11px; font-weight: 700; color: var(--gold); letter-spacing: .08em;
    width: fit-content;
}
.qmd-feat-body h3 {
    font-family: 'Rajdhani', sans-serif; font-size: clamp(20px, 2.5vw, 30px);
    font-weight: 700; color: #fff; margin: 0 0 12px; line-height: 1.2;
}
.qmd-feat-body p {
    font-size: 14px; color: rgba(255,255,255,.6);
    line-height: 1.7; margin: 0 0 22px; max-width: 480px;
}
.qmd-feat-meta { display: flex; flex-wrap: wrap; gap: 18px; margin-bottom: 24px; }
.qmd-feat-meta-item {
    display: flex; align-items: center; gap: 7px;
    font-size: 13px; color: rgba(255,255,255,.75);
}
.qmd-feat-meta-item i { color: var(--gold); font-size: 13px; }
.qmd-feat-tags { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 28px; }
.qmd-feat-tag {
    font-size: 11px; padding: 4px 11px; border-radius: 4px; font-weight: 600;
    background: rgba(255,255,255,.08); color: rgba(255,255,255,.7);
    border: 1px solid rgba(255,255,255,.15);
}
.qmd-feat-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--gold); color: #000; font-weight: 700;
    padding: 13px 28px; border-radius: 9px; font-size: 14px;
    font-family: 'Rajdhani', sans-serif; letter-spacing: .04em;
    transition: all .2s; width: fit-content;
}
.qmd-feat-btn:hover { background: #d4890e; transform: translateY(-1px); }

.qmd-feat-img { position: relative; overflow: hidden; min-height: 280px; }
.qmd-feat-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.qmd-feat-cat-tag {
    position: absolute; top: 18px; left: 18px;
    padding: 6px 14px; border-radius: 7px; font-size: 12px; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase;
}

@media(max-width:900px){
    .qmd-featured { grid-template-columns: 1fr; }
    .qmd-feat-img  { height: 220px; min-height: unset; }
    .qmd-feat-body { padding: 28px 24px; }
}

/* =========================================
   MEDIA GRID
========================================= */
.qmd-grid {
    display: grid; grid-template-columns: repeat(3,1fr); gap: 22px; margin-bottom: 44px;
}
@media(max-width:1050px){ .qmd-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:580px)  { .qmd-grid { grid-template-columns: 1fr; } }

/* CARD */
.qmd-card {
    background: var(--card-bg); border-radius: 12px; overflow: hidden;
    border: 1px solid var(--bdr); display: flex; flex-direction: column;
    transition: transform .25s, box-shadow .25s;
}
.qmd-card:hover { transform: translateY(-5px); box-shadow: 0 16px 40px rgba(0,0,0,.1); }

.qmd-card-thumb {
    position: relative; aspect-ratio: 16/9; overflow: hidden; background: #1a1a2e;
}
.qmd-card-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s;
}
.qmd-card:hover .qmd-card-thumb img { transform: scale(1.05); }

/* category badge colours */
.qmd-cat-badge {
    position: absolute; top: 10px; left: 10px;
    font-size: 10px; font-weight: 700; letter-spacing: .06em;
    padding: 4px 11px; border-radius: 5px; text-transform: uppercase;
}
.qmd-cat-badge.tv      { background: #b71c1c; color: #fff; }
.qmd-cat-badge.podcast { background: #6a1b9a; color: #fff; }
.qmd-cat-badge.press   { background: #1565c0; color: #fff; }
.qmd-cat-badge.webinar { background: #00695c; color: #fff; }
.qmd-cat-badge.award   { background: #e65100; color: #fff; }

/* play overlay on thumb hover */
.qmd-play-ov {
    position: absolute; inset: 0;
    background: rgba(0,0,0,.3); display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity .3s;
}
.qmd-card:hover .qmd-play-ov { opacity: 1; }
.qmd-play-ov i { font-size: 38px; color: #fff; text-shadow: 0 2px 12px rgba(0,0,0,.5); }

/* duration badge bottom-right of thumb */
.qmd-dur-badge {
    position: absolute; bottom: 10px; right: 10px;
    background: rgba(0,0,0,.7); backdrop-filter: blur(6px);
    border-radius: 5px; padding: 3px 9px;
    font-size: 11px; color: #fff; font-weight: 600;
    display: flex; align-items: center; gap: 4px;
}
.qmd-dur-badge i { color: var(--gold); font-size: 10px; }

/* card body */
.qmd-card-body { padding: 16px 18px; flex: 1; display: flex; flex-direction: column; }

.qmd-card-meta-top {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 10px;
}
.qmd-channel-pill {
    font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 4px;
    background: #fff8ed; color: #b45309; border: 1px solid #ffe0b2;
}
.qmd-card-date {
    font-size: 11.5px; color: #999; display: flex; align-items: center; gap: 4px;
}
.qmd-card-date i { color: var(--gold); font-size: 10px; }

.qmd-card-title {
    font-family: 'Rajdhani', sans-serif; font-size: 15.5px; font-weight: 700;
    color: var(--txt); margin-bottom: 9px; line-height: 1.35; flex: 1;
}
.qmd-card-desc { font-size: 12.5px; color: #777; line-height: 1.65; margin-bottom: 12px; }

.qmd-card-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
.qmd-card-tag {
    font-size: 11px; padding: 3px 9px; border-radius: 4px; font-weight: 600;
    background: #f0f2ff; color: #3949ab; border: 1px solid #c5cae9;
}

/* card footer */
.qmd-card-footer {
    padding: 11px 18px; border-top: 1px solid var(--bdr); background: #fafafa;
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.qmd-source-txt { font-size: 12px; color: #888; display: flex; align-items: center; gap: 5px; }
.qmd-source-txt i { color: var(--gold); }
.qmd-watch-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--gold); color: #000; font-weight: 700; font-size: 12px;
    padding: 7px 16px; border-radius: 8px; transition: all .2s;
    font-family: 'Exo 2', sans-serif; white-space: nowrap;
}
.qmd-watch-btn:hover { background: #d4890e; transform: translateY(-1px); }

/* tab panels */
.qmd-tab-panel    { display: none; }
.qmd-tab-panel.on { display: block; animation: mdFadeUp .4s ease both; }

/* no results */
.qmd-no-results { display: none; text-align: center; padding: 60px 20px; color: #aaa; font-size: 15px; }
.qmd-no-results i { font-size: 36px; color: #ddd; display: block; margin-bottom: 12px; }

/* ── PRESS ENQUIRY CTA STRIP ── */
.qmd-cta-strip {
    background: linear-gradient(90deg, #0D1B2A, #162844);
    border: 1px solid rgba(245,166,35,.2); border-radius: 14px;
    padding: 32px 40px; margin-top: 44px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 24px; flex-wrap: wrap;
}
.qmd-cta-strip h3 {
    font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700;
    color: #fff; margin: 0 0 6px;
}
.qmd-cta-strip p { font-size: 14px; color: rgba(255,255,255,.55); margin: 0; }
.qmd-cta-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--gold); color: #000; font-weight: 700;
    padding: 13px 28px; border-radius: 9px; font-size: 14px;
    font-family: 'Rajdhani', sans-serif; letter-spacing: .04em;
    transition: background .2s; white-space: nowrap;
}
.qmd-cta-btn:hover { background: #d4890e; }

/* ── AWARD CARD SPECIAL STYLE ── */
.qmd-card.award-card .qmd-card-thumb {
    background: linear-gradient(135deg, #1a1200, #3d2800);
}
.qmd-card.award-card .qmd-card-thumb img { opacity: .85; }
</style>

<div style="font-family:'Exo 2',sans-serif; background:#f4f6fb;">

{{-- =====================
     HERO
===================== --}}
<div class="qmd-hero md-anim">
    <div class="qmd-hero-inner">
        <div class="qmd-hero-text text-center">
            <div class="qmd-hero-eyebrow">
                <span class="qmd-hero-dot"></span> Press, Media &amp; Recognition
            </div>
            <h1>CityQuants <span>In The Media</span></h1>
            <div class="d-flex justify-content-center">
                <p>TV interviews, podcast appearances, press features and webinar recordings - follow CityQuants across India's top financial media channels and trading publications.</p>
            </div>
            {{-- <div class="qmd-hero-stats">
                <div class="qmd-stat-box">
                    <div class="qmd-stat-val">50+</div>
                    <div class="qmd-stat-lbl">Media Features</div>
                </div>
                <div class="qmd-stat-box">
                    <div class="qmd-stat-val">15+</div>
                    <div class="qmd-stat-lbl">TV Appearances</div>
                </div>
                <div class="qmd-stat-box">
                    <div class="qmd-stat-val">30+</div>
                    <div class="qmd-stat-lbl">Podcast Episodes</div>
                </div>
                <div class="qmd-stat-box">
                    <div class="qmd-stat-val">8</div>
                    <div class="qmd-stat-lbl">Awards Won</div>
                </div>
            </div> --}}
        </div>
        {{-- <div class="qmd-hero-img md-anim d2">
            <img src="https://img.freepik.com/free-vector/news-concept-illustration_114360-5279.jpg?w=500" alt="Media">
        </div> --}}
    </div>
</div>

{{-- =====================
     AS SEEN IN STRIP
===================== --}}
{{-- <div class="qmd-press-strip md-anim d1">
    <div class="qmd-press-inner">
        <span class="qmd-press-label">As Seen In</span>
        <div class="qmd-press-logos">
            @foreach($pressLogos as $pl)
                <img class="qmd-press-logo" src="{{ $pl['logo'] }}" alt="{{ $pl['name'] }}" title="{{ $pl['name'] }}">
            @endforeach
        </div>
    </div>
</div> --}}

{{-- =====================
     FILTER BAR
===================== --}}
<div class="qmd-filter-bar">
    <div class="qmd-tabs-row">
        <button class="qmd-tab on" onclick="mdSwitchTab(0,this)">All Media</button>
        <button class="qmd-tab"   onclick="mdSwitchTab(1,this)">TV &amp; Video</button>
        <button class="qmd-tab"   onclick="mdSwitchTab(2,this)">Podcasts</button>
        <button class="qmd-tab"   onclick="mdSwitchTab(3,this)">Press</button>
        <button class="qmd-tab"   onclick="mdSwitchTab(4,this)">Webinars</button>
        <button class="qmd-tab"   onclick="mdSwitchTab(5,this)">Awards</button>
    </div>
    <div class="qmd-filter-row">
        <div class="qmd-filter-group">
            <span class="qmd-filter-label">Category</span>
            <select class="qmd-filter-select" id="mCat" onchange="mdApplyFilters()">
                <option value="">All</option>
                <option value="tv">TV &amp; Video</option>
                <option value="podcast">Podcast</option>
                <option value="press">Press</option>
                <option value="webinar">Webinar</option>
                <option value="award">Award</option>
            </select>
        </div>
        <div class="qmd-filter-group">
            <span class="qmd-filter-label">Year</span>
            <select class="qmd-filter-select" id="mYear" onchange="mdApplyFilters()">
                <option value="">All Years</option>
                <option value="2026">2026</option>
                <option value="2025">2025</option>
                <option value="2024">2024</option>
            </select>
        </div>
        <div class="qmd-search-wrap">
            <input class="qmd-search-input" type="text" id="mdSearch" placeholder="Search media..." oninput="mdApplyFilters()">
            <button class="qmd-search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="qmd-pills-row">
        <button class="qmd-pill on" onclick="mdTogglePill(this,'')">All</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'options')">Options</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'algo trading')">Algo Trading</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'volatility')">Volatility</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'oi data')">OI Data</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'intraday')">Intraday</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'sebi')">SEBI F&amp;O</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'quant')">Quant Trading</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'greek')">Greeks</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'python')">Python</button>
        <button class="qmd-pill" onclick="mdTogglePill(this,'backtesting')">Backtesting</button>
    </div>
</div>

{{-- =====================
     CONTENT
===================== --}}
<div class="qmd-content">

    {{-- ── ALL MEDIA PANEL ── --}}
    <div class="qmd-tab-panel on" id="mdPanel0">

        {{-- FEATURED --}}
        <div class="qmd-section-head md-anim"><h2>Featured</h2></div>
        <div class="qmd-featured md-anim d1">
            <div class="qmd-feat-body">
                <div class="qmd-feat-badge">
                    <span class="qmd-hero-dot"></span> LATEST FEATURE
                </div>
                <h3>{{ $featuredMedia['title'] }}</h3>
                <p>{{ $featuredMedia['desc'] }}</p>
                <div class="qmd-feat-meta">
                    <div class="qmd-feat-meta-item">
                        <i class="fas fa-tv"></i> {{ $featuredMedia['channel'] }}
                    </div>
                    <div class="qmd-feat-meta-item">
                        <i class="fas fa-calendar-alt"></i> {{ $featuredMedia['date'] }}
                    </div>
                    <div class="qmd-feat-meta-item">
                        <i class="fas fa-clock"></i> {{ $featuredMedia['duration'] }}
                    </div>
                </div>
                <div class="qmd-feat-tags">
                    @foreach($featuredMedia['tags'] as $tag)
                        <span class="qmd-feat-tag">{{ $tag }}</span>
                    @endforeach
                </div>
                <a href="{{ $featuredMedia['url'] }}" class="qmd-feat-btn">
                    <i class="fas fa-play-circle"></i> Watch Now
                </a>
            </div>
            <div class="qmd-feat-img">
                <img src="{{ $featuredMedia['thumbnail'] }}" alt="{{ $featuredMedia['title'] }}">
                <span class="qmd-feat-cat-tag qmd-cat-badge tv">TV Interview</span>
            </div>
        </div>

        {{-- ALL MEDIA GRID --}}
        <div class="qmd-section-head md-anim d2"><h2>All Media</h2></div>
        <div class="qmd-grid md-anim d2" id="mdAllGrid">
            @foreach($mediaItems as $item)
            <div class="qmd-card {{ $item['category'] === 'award' ? 'award-card' : '' }}"
                 data-cat="{{ $item['category'] }}"
                 data-title="{{ strtolower($item['title']) }}"
                 data-tags="{{ strtolower(implode(' ', $item['tags'])) }}"
                 data-year="{{ \Carbon\Carbon::createFromFormat('d M Y', $item['date'])->year }}">

                <div class="qmd-card-thumb">
                    <img src="{{ $item['thumbnail'] }}" alt="{{ $item['title'] }}" loading="lazy">
                    <span class="qmd-cat-badge {{ $item['category'] }}">{{ $item['cat_label'] }}</span>
                    @if(in_array($item['category'], ['tv','webinar','podcast']))
                    <div class="qmd-play-ov"><i class="fas fa-play-circle"></i></div>
                    @endif
                    <div class="qmd-dur-badge">
                        <i class="fas fa-clock"></i> {{ $item['duration'] }}
                    </div>
                </div>

                <div class="qmd-card-body">
                    <div class="qmd-card-meta-top">
                        <span class="qmd-channel-pill">{{ $item['channel'] }}</span>
                        <span class="qmd-card-date">
                            <i class="fas fa-calendar-alt"></i> {{ $item['date'] }}
                        </span>
                    </div>
                    <div class="qmd-card-title">{{ $item['title'] }}</div>
                    <div class="qmd-card-desc">{{ $item['desc'] }}</div>
                    <div class="qmd-card-tags">
                        @foreach($item['tags'] as $tag)
                            <span class="qmd-card-tag">{{ $tag }}</span>
                        @endforeach
                    </div>
                </div>

                <div class="qmd-card-footer">
                    <span class="qmd-source-txt">
                        <i class="fas fa-external-link-alt"></i> {{ $item['channel'] }}
                    </span>
                    <a href="{{ $item['url'] }}" class="qmd-watch-btn">
                        @if($item['category'] === 'press')
                            Read <i class="fas fa-arrow-right"></i>
                        @elseif($item['category'] === 'award')
                            View <i class="fas fa-trophy"></i>
                        @else
                            Watch <i class="fas fa-play"></i>
                        @endif
                    </a>
                </div>

            </div>
            @endforeach
        </div>

        <div class="qmd-no-results" id="mdNoResults">
            <i class="fas fa-photo-video"></i>
            No media found matching your filters.
        </div>

        {{-- PRESS ENQUIRY CTA --}}
        <div class="qmd-cta-strip md-anim d4">
            <div>
                <h3>Press &amp; Media Enquiries</h3>
                <p>For interviews, features, podcast invitations or press kits - reach out to our media team directly.</p>
            </div>
            <a href="mailto:media@cityquants.com" class="qmd-cta-btn">
                <i class="fas fa-envelope"></i> Contact Media Team
            </a>
        </div>

    </div>{{-- /#mdPanel0 --}}

    {{-- ── TV & VIDEO TAB ── --}}
    <div class="qmd-tab-panel" id="mdPanel1">
        <div class="qmd-section-head"><h2>TV &amp; Video Interviews</h2></div>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">Watch our experts on India's top financial news channels.</p>
        <div class="qmd-grid" id="mdTvGrid">
            @foreach($mediaItems as $item)
                @if($item['category'] === 'tv')
                    @include($activeTemplate.'partials.media-card', ['item' => $item])
                @endif
            @endforeach
        </div>
    </div>

    {{-- ── PODCASTS TAB ── --}}
    <div class="qmd-tab-panel" id="mdPanel2">
        <div class="qmd-section-head"><h2>Podcast Appearances</h2></div>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">Listen to in-depth conversations on trading, quant strategies and markets.</p>
        <div class="qmd-grid" id="mdPodGrid">
            @foreach($mediaItems as $item)
                @if($item['category'] === 'podcast')
                    @include($activeTemplate.'partials.media-card', ['item' => $item])
                @endif
            @endforeach
        </div>
    </div>

    {{-- ── PRESS TAB ── --}}
    <div class="qmd-tab-panel" id="mdPanel3">
        <div class="qmd-section-head"><h2>Press &amp; Publications</h2></div>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">CityQuants coverage in India's leading financial newspapers and magazines.</p>
        <div class="qmd-grid" id="mdPressGrid">
            @foreach($mediaItems as $item)
                @if($item['category'] === 'press')
                    @include($activeTemplate.'partials.media-card', ['item' => $item])
                @endif
            @endforeach
        </div>
    </div>

    {{-- ── WEBINARS TAB ── --}}
    <div class="qmd-tab-panel" id="mdPanel4">
        <div class="qmd-section-head"><h2>Webinar Recordings</h2></div>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">Full recordings of past CityQuants webinars - free to watch.</p>
        <div class="qmd-grid" id="mdWebGrid">
            @foreach($mediaItems as $item)
                @if($item['category'] === 'webinar')
                    @include($activeTemplate.'partials.media-card', ['item' => $item])
                @endif
            @endforeach
        </div>
    </div>

    {{-- ── AWARDS TAB ── --}}
    <div class="qmd-tab-panel" id="mdPanel5">
        <div class="qmd-section-head"><h2>Awards &amp; Recognition</h2></div>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">Industry recognition for platform excellence and innovation.</p>
        <div class="qmd-grid" id="mdAwardGrid">
            @foreach($mediaItems as $item)
                @if($item['category'] === 'award')
                    @include($activeTemplate.'partials.media-card', ['item' => $item])
                @endif
            @endforeach
        </div>
    </div>

</div>{{-- /.qmd-content --}}
</div>

<script>
/* TAB SWITCH */
function mdSwitchTab(idx, btn) {
    document.querySelectorAll('.qmd-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qmd-tab-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === idx);
    });
    /* sync category dropdown to tab */
    var catMap = ['','tv','podcast','press','webinar','award'];
    var sel = document.getElementById('mCat');
    if (sel) { sel.value = catMap[idx] || ''; }
    mdApplyFilters();
}

/* PILL TOGGLE */
var mdActivePill = '';
function mdTogglePill(el, keyword) {
    document.querySelectorAll('.qmd-pill').forEach(function(p) { p.classList.remove('on'); });
    el.classList.add('on');
    mdActivePill = keyword;
    mdApplyFilters();
}

/* FILTER */
function mdApplyFilters() {
    var cat    = document.getElementById('mCat').value.toLowerCase();
    var year   = document.getElementById('mYear').value;
    var search = document.getElementById('mdSearch').value.toLowerCase().trim();
    var pill   = mdActivePill.toLowerCase();

    var grids = ['mdAllGrid','mdTvGrid','mdPodGrid','mdPressGrid','mdWebGrid','mdAwardGrid']
        .map(function(id){ return document.getElementById(id); });

    var visible = 0;

    grids.forEach(function(grid) {
        if (!grid) return;
        grid.querySelectorAll('.qmd-card').forEach(function(card) {
            var cCat   = (card.dataset.cat   || '').toLowerCase();
            var cTitle = (card.dataset.title || '').toLowerCase();
            var cTags  = (card.dataset.tags  || '').toLowerCase();
            var cYear  = (card.dataset.year  || '');
            var ok = true;
            if (cat    && cCat !== cat)                         ok = false;
            if (year   && cYear !== year)                       ok = false;
            if (search && cTitle.indexOf(search) === -1)        ok = false;
            if (pill   && cTags.indexOf(pill) === -1
                       && cTitle.indexOf(pill) === -1)          ok = false;
            card.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
    });

    var noRes = document.getElementById('mdNoResults');
    if (noRes) noRes.style.display = (visible === 0) ? 'block' : 'none';
}
</script>

@endsection