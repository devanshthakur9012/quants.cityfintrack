@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   VARIABLES — same as home / about / webinars
========================================= */
.qa-wrap {
    --gold:    #F5A623;
    --gold2:   #FFD06A;
    --golddim: rgba(245,166,35,.1);
    font-family: 'Exo 2', sans-serif;
    color: #E4EBF5;
    display: block;
    background: #06101A;
}
.qa-wrap * { box-sizing: border-box; }
.qa-wrap h1,.qa-wrap h2,.qa-wrap h3,.qa-wrap h4 {
    font-family: 'Rajdhani', sans-serif; letter-spacing:.03em;
}
.qa-wrap a { text-decoration: none; }

@keyframes qaFadeUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:none; }
}
.qa-anim     { animation: qaFadeUp .65s ease both; }
.qa-anim.d1  { animation-delay:.1s; }
.qa-anim.d2  { animation-delay:.2s; }
.qa-anim.d3  { animation-delay:.3s; }

/* =========================================
   1. HERO  (white bg — matches screenshot)
========================================= */
.qa-cr-hero {
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    padding: 32px 48px;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    overflow: hidden;
}
.qa-cr-hero-left { flex: 1; }
.qa-cr-hero-left h1 {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(28px, 3.8vw, 44px);
    font-weight: 700; color: #1a1a2e;
    margin: 0 0 12px; line-height: 1.08;
}
.qa-cr-hero-left p {
    font-size: 13.5px; color: #555; line-height: 1.78;
    max-width: 580px; margin: 0;
}
/* right side: small banner mosaic */
.qa-cr-hero-right {
    flex-shrink: 0; width: 260px;
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 6px;
}
.qa-cr-hero-right img {
    width: 100%; height: 80px; object-fit: cover;
    border-radius: 6px; display: block;
    border: 1px solid #eee;
}
@media(max-width:860px){
    .qa-cr-hero { flex-direction: column; padding: 24px 20px; }
    .qa-cr-hero-right { width: 100%; grid-template-columns: repeat(4,1fr); }
    .qa-cr-hero-right img { height: 60px; }
}
@media(max-width:500px){
    .qa-cr-hero-right { grid-template-columns: 1fr 1fr; }
}

/* =========================================
   2. STICKY FILTER BAR  (white)
========================================= */
.qa-filter-bar {
    background: #fff; border-bottom: 1px solid #e8e8e8;
    padding: 0 48px; position: sticky; top: 0; z-index: 200;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
/* main tabs */
.qa-main-tabs { display: flex; border-bottom: 2px solid #f0f0f0; }
.qa-main-tab {
    padding: 14px 22px; font-size: 14px; font-weight: 600; color: #666;
    cursor: pointer; border: none; background: none;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    transition: all .2s; font-family: 'Exo 2', sans-serif; white-space: nowrap;
}
.qa-main-tab.on { color: #2196F3; border-bottom-color: #2196F3; }
.qa-main-tab:hover:not(.on) { color: #333; }

/* dropdowns */
.qa-dropdowns-row {
    display: flex; align-items: flex-end; gap: 14px;
    padding: 12px 0 11px; flex-wrap: wrap;
}
.qa-filter-group { display: flex; flex-direction: column; gap: 3px; }
.qa-filter-label {
    font-size: 10.5px; color: #999; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
}
.qa-filter-select {
    border: 1px solid #ddd; border-radius: 6px;
    padding: 7px 28px 7px 10px; font-size: 13px; color: #333;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 9px center;
    appearance: none; -webkit-appearance: none;
    cursor: pointer; font-family: 'Exo 2', sans-serif; min-width: 80px;
}
.qa-filter-select:focus { outline: none; border-color: #2196F3; }
.qa-search-wrap {
    display: flex; align-items: stretch; overflow: hidden;
    border: 1px solid #ddd; border-radius: 6px; margin-left: auto;
}
.qa-search-input {
    border: none; padding: 8px 14px; font-size: 13px; color: #333;
    outline: none; width: 200px; font-family: 'Exo 2', sans-serif;
}
.qa-search-btn {
    background: #F5A623; border: none; padding: 0 15px;
    color: #fff; font-size: 14px; cursor: pointer;
    display: flex; align-items: center; transition: background .2s;
}
.qa-search-btn:hover { background: #d4890e; }

/* pills */
.qa-category-pills {
    display: flex; flex-wrap: wrap; gap: 7px;
    padding: 9px 0 13px; border-top: 1px solid #f5f5f5;
}
.qa-pill {
    padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 500;
    border: 1px solid #e0e0e0; background: #fafafa; color: #444;
    cursor: pointer; transition: all .2s; white-space: nowrap;
    font-family: 'Exo 2', sans-serif;
}
.qa-pill.on, .qa-pill:hover {
    background: rgba(33,150,243,.08); border-color: rgba(33,150,243,.4); color: #1565c0;
}
@media(max-width:768px){
    .qa-filter-bar { padding: 0 16px; }
    .qa-search-wrap { margin-left: 0; width: 100%; }
    .qa-search-input { width: 100%; }
}

/* =========================================
   3. CONTENT AREA
========================================= */
.qa-courses-wrap {
    background: #f7f8fc;
    padding: 28px 48px 72px;
    min-height: 60vh;
}
@media(max-width:768px){ .qa-courses-wrap { padding: 18px 14px 56px; } }

/* tab panels */
.qa-tab-panel    { display: none; }
.qa-tab-panel.on { display: block; animation: qaFadeUp .4s ease both; }

/* 3-col grid */
.qa-cr-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}
@media(max-width:1020px){ .qa-cr-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:580px) { .qa-cr-grid { grid-template-columns: 1fr; } }

/* ── CARD ── */
.qa-cr-card {
    background: #fff;
    border-radius: 10px; overflow: hidden;
    border: 1px solid #e8e8e8;
    transition: transform .25s, box-shadow .25s;
    display: flex; flex-direction: column;
}
.qa-cr-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 36px rgba(0,0,0,.1);
}

/* thumbnail */
.qa-cr-thumb {
    position: relative; aspect-ratio: 16/9;
    overflow: hidden; background: #1a1a2e; flex-shrink: 0;
}
.qa-cr-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .4s;
}
.qa-cr-card:hover .qa-cr-thumb img { transform: scale(1.04); }

/* status badge on thumb */
.qa-cr-badge {
    position: absolute; top: 10px; right: 10px;
    font-size: 10px; font-weight: 700; letter-spacing: .06em;
    padding: 4px 10px; border-radius: 4px; text-transform: uppercase;
}
.qa-cr-badge.upcoming { background: #43a047; color: #fff; }
.qa-cr-badge.ongoing  { background: #fb8c00; color: #fff; }

/* card body */
.qa-cr-body {
    padding: 14px 16px; flex: 1; display: flex; flex-direction: column;
}
.qa-cr-title {
    font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700;
    color: #1a1a2e; line-height: 1.3; margin-bottom: 4px; flex: 1;
}
.qa-cr-mode {
    font-size: 12px; color: #2196F3; font-weight: 600; margin-bottom: 10px;
}

/* 2-col meta grid */
.qa-cr-meta {
    display: grid; grid-template-columns: 1fr 1fr; gap: 5px 8px;
    margin-bottom: 10px;
}
.qa-cr-meta-row {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px; color: #666;
}
.qa-cr-meta-row i      { color: #F5A623; font-size: 11px; width: 13px; text-align: center; }
.qa-cr-meta-row span   { color: #333; font-weight: 500; }

/* date row (full-width, red icon) */
.qa-cr-date {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: #e53935; font-weight: 500;
    margin-bottom: 10px;
}
.qa-cr-date i { font-size: 11px; }

/* recording row */
.qa-cr-rec {
    font-size: 12px; color: #888; margin-bottom: 8px;
}
.qa-cr-rec .avail { color: #43a047; font-weight: 700; }

/* card footer */
.qa-cr-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px;
    border-top: 1px solid #f0f0f0;
    background: #fafafa; gap: 8px;
}
.qa-cr-price {
    font-family: 'Rajdhani', sans-serif;
    font-size: 17px; font-weight: 700; color: #1a1a2e; line-height: 1.2;
}
.qa-cr-price .orig { text-decoration: line-through; color: #aaa; font-size: 12px; font-weight: 400; margin-right: 3px; }
.qa-cr-price .disc { font-size: 11px; color: #43a047; font-weight: 700; margin-left: 4px; }
.qa-cr-view {
    font-size: 12px; color: #F5A623; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: gap .2s; flex-shrink: 0;
}
.qa-cr-view:hover { gap: 7px; }

/* no results */
.qa-no-results {
    display: none; text-align: center; padding: 70px 20px;
    color: #999; font-size: 15px;
}
.qa-no-results i { font-size: 36px; color: #ddd; display: block; margin-bottom: 12px; }

/* my courses empty */
.qa-login-prompt {
    text-align: center; padding: 90px 20px;
    background: #fff; border-radius: 14px; border: 1px solid #e8e8e8;
}
.qa-login-prompt i  { font-size: 52px; color: #ddd; display: block; margin-bottom: 18px; }
.qa-login-prompt h3 { font-family:'Rajdhani',sans-serif; font-size:26px; color:#1a1a2e; margin-bottom:10px; }
.qa-login-prompt p  { color:#888; font-size:14px; margin-bottom:26px; }
.qa-login-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: #F5A623; color: #000; font-weight: 700;
    padding: 13px 30px; border-radius: 9px;
    font-family: 'Rajdhani', sans-serif; font-size: 16px;
    letter-spacing: .04em; transition: background .2s;
}
.qa-login-btn:hover { background: #d4890e; }
</style>

<div class="qa-wrap">

{{-- ══════════════════════════════════════════════
     1. HERO BANNER
══════════════════════════════════════════════ --}}
<div class="qa-cr-hero">
    <div class="qa-cr-hero-left qa-anim">
        <h1>{{ $heroBanner['title'] }}</h1>
        <p>{{ $heroBanner['description'] }}</p>
    </div>
    <div class="qa-cr-hero-right qa-anim d2">
        @foreach($heroBanner['banners'] as $banner)
            <img src="{{ $banner }}" alt="Course Banner" loading="lazy">
        @endforeach
    </div>
</div>

{{-- ══════════════════════════════════════════════
     2. STICKY FILTER BAR
══════════════════════════════════════════════ --}}
<div class="qa-filter-bar">

    <div class="qa-main-tabs">
        <button class="qa-main-tab on" onclick="qaSwitchTab(0,this)">Online</button>
        <button class="qa-main-tab"    onclick="qaSwitchTab(1,this)">My Courses</button>
    </div>

    <div class="qa-dropdowns-row">
        <div class="qa-filter-group">
            <span class="qa-filter-label">Language</span>
            <select class="qa-filter-select" id="filterLang" onchange="qaFilter()">
                <option value="">All</option>
                @foreach($languages as $lang)
                    <option value="{{ strtolower($lang) }}">{{ $lang }}</option>
                @endforeach
            </select>
        </div>

        <div class="qa-filter-group">
            <span class="qa-filter-label">Price</span>
            <select class="qa-filter-select" id="filterPrice" onchange="qaFilter()">
                <option value="">All</option>
                <option value="free">Free</option>
                <option value="paid">Paid</option>
            </select>
        </div>

        <div class="qa-filter-group">
            <span class="qa-filter-label">Proficiency</span>
            <select class="qa-filter-select" id="filterLevel" onchange="qaFilter()">
                <option value="">All</option>
                @foreach($proficiency as $lvl)
                    <option value="{{ strtolower($lvl) }}">{{ $lvl }}</option>
                @endforeach
            </select>
        </div>

        <div class="qa-search-wrap">
            <input  class="qa-search-input" type="text" id="qaSearch"
                    placeholder="Search..." oninput="qaFilter()">
            <button class="qa-search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>

    <div class="qa-category-pills">
        @foreach($categories as $idx => $cat)
            <button class="qa-pill {{ $idx === 0 ? 'on' : '' }}"
                    onclick="qaTogglePill(this)">{{ $cat }}</button>
        @endforeach
    </div>
</div>

{{-- ══════════════════════════════════════════════
     3. CONTENT
══════════════════════════════════════════════ --}}
<div class="qa-courses-wrap">

    {{-- ─ ONLINE COURSES ─ --}}
    <div class="qa-tab-panel on" id="qaPanel0">
        <div class="qa-cr-grid qa-anim d1" id="qaCrGrid">

            @foreach($courses as $c)
            <div class="qa-cr-card"
                 data-type="{{ $c['type'] }}"
                 data-lang="{{ strtolower($c['language']) }}"
                 data-level="{{ strtolower($c['level']) }}"
                 data-title="{{ strtolower($c['title']) }}">

                {{-- Thumbnail --}}
                <div class="qa-cr-thumb">
                    <img src="{{ $c['thumbnail'] }}" alt="{{ $c['title'] }}" loading="lazy">
                    @if($c['status'] === 'upcoming')
                        <span class="qa-cr-badge upcoming">UPCOMING</span>
                    @elseif($c['status'] === 'ongoing')
                        <span class="qa-cr-badge ongoing">ONGOING</span>
                    @endif
                </div>

                {{-- Body --}}
                <div class="qa-cr-body">
                    <div class="qa-cr-title">{{ $c['title'] }}</div>
                    <div class="qa-cr-mode">{{ $c['mode'] }}</div>

                    <div class="qa-cr-meta">
                        <div class="qa-cr-meta-row">
                            <i class="fas fa-signal"></i>
                            <span>{{ $c['level'] }}</span>
                        </div>
                        <div class="qa-cr-meta-row">
                            <i class="fas fa-language"></i>
                            <span>{{ $c['language'] }}</span>
                        </div>
                        <div class="qa-cr-meta-row">
                            <i class="fas fa-calendar-alt"></i>
                            <span>{{ $c['duration'] }}</span>
                        </div>
                        <div class="qa-cr-meta-row">
                            <i class="fas fa-play-circle"></i>
                            <span>{{ $c['sessions'] }}</span>
                        </div>
                    </div>

                    @if(!empty($c['date']))
                    <div class="qa-cr-date">
                        <i class="fas fa-calendar-check"></i>
                        {{ $c['date'] }}
                    </div>
                    @endif

                    @if(!empty($c['recording']))
                    <div class="qa-cr-rec">
                        Recording — <span class="avail">{{ $c['recording'] }}</span>
                    </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="qa-cr-footer">
                    <div class="qa-cr-price">
                        ₹{{ number_format($c['price']) }}/-
                        @if(!empty($c['mrp']))
                            &nbsp;<span class="orig">₹{{ number_format($c['mrp']) }}/-</span>
                        @endif
                        @if(!empty($c['discount']))
                            <span class="disc">{{ $c['discount'] }}</span>
                        @endif
                    </div>
                    <a href="{{ $c['url'] }}" class="qa-cr-view">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            @endforeach

        </div>{{-- /#qaCrGrid --}}

        <div class="qa-no-results" id="qaNoResults">
            <i class="fas fa-search"></i>
            No courses found matching your filters.
        </div>
    </div>

    {{-- ─ MY COURSES ─ --}}
    <div class="qa-tab-panel" id="qaPanel1">
        <div class="qa-login-prompt">
            <i class="fas fa-lock"></i>
            <h3>Login to View Your Courses</h3>
            <p>Please login to access the courses you have enrolled in or purchased.</p>
            <a href="{{ route('user.login') }}" class="qa-login-btn">
                <i class="fas fa-sign-in-alt"></i> Login Now
            </a>
        </div>
    </div>

</div>{{-- /.qa-courses-wrap --}}
</div>{{-- /.qa-wrap --}}

<script>
/* ── TAB SWITCH ── */
function qaSwitchTab(idx, btn) {
    document.querySelectorAll('.qa-main-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qa-tab-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === idx);
    });
}

/* ── PILL TOGGLE ── */
function qaTogglePill(el) {
    el.classList.toggle('on');
    qaFilter();
}

/* ── FILTER ENGINE ── */
function qaFilter() {
    var lang   = document.getElementById('filterLang').value.toLowerCase();
    var price  = document.getElementById('filterPrice').value.toLowerCase();
    var level  = document.getElementById('filterLevel').value.toLowerCase();
    var search = document.getElementById('qaSearch').value.toLowerCase().trim();

    var cards   = document.querySelectorAll('#qaCrGrid .qa-cr-card');
    var visible = 0;

    cards.forEach(function(card) {
        var cLang  = (card.dataset.lang  || '').toLowerCase();
        var cType  = (card.dataset.type  || '').toLowerCase();
        var cLevel = (card.dataset.level || '').toLowerCase();
        var cTitle = (card.dataset.title || '').toLowerCase();

        var ok = true;
        if (lang   && cLang.indexOf(lang)    === -1) ok = false;
        if (price  && cType !== price)                ok = false;
        if (level  && cLevel !== level)               ok = false;
        if (search && cTitle.indexOf(search)  === -1) ok = false;

        card.style.display = ok ? '' : 'none';
        if (ok) visible++;
    });

    var noRes = document.getElementById('qaNoResults');
    if (noRes) noRes.style.display = (visible === 0) ? 'block' : 'none';
}
</script>

@endsection