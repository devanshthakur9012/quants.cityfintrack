{{-- FILE: resources/views/themes/{activeTemplate}/courses.blade.php --}}
@extends($activeTemplate.'layouts.frontend')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ─── BASE ─────────────────────────────────────────────────────────────── */
.cr-page { font-family:'Exo 2',sans-serif; background:#f4f6fb; min-height:80vh; }
.cr-page *,.cr-page *::before,.cr-page *::after { box-sizing:border-box; }
.cr-page h1,.cr-page h2,.cr-page h3,.cr-page h4,.cr-page h5 { font-family:'Rajdhani',sans-serif; letter-spacing:.02em; }
.cr-page a { text-decoration:none; color:inherit; }
@keyframes crFadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.cr-anim    { animation:crFadeUp .5s ease both; }
.cr-anim.d1 { animation-delay:.1s; }
.cr-anim.d2 { animation-delay:.2s; }

/* ─── HERO ──────────────────────────────────────────────────────────────── */
.cr-hero { background:#fff; border-bottom:1px solid #e5e9f2; padding:36px 60px; display:flex; align-items:center; gap:32px; }
.cr-hero-left { flex:1; min-width:0; }
.cr-hero-left h1 { font-size:clamp(26px,3.5vw,42px); font-weight:700; color:#0f1b2d; margin:0 0 10px; line-height:1.1; }
.cr-hero-left p  { font-size:14px; color:#5a6678; line-height:1.8; max-width:600px; margin:0; }
.cr-hero-right { flex-shrink:0; display:grid; grid-template-columns:1fr 1fr; gap:8px; width:280px; }
.cr-hero-right img { width:100%; height:82px; object-fit:cover; border-radius:8px; border:1px solid #eaecf2; display:block; }

/* ─── FILTER BAR ─────────────────────────────────────────────────────────── */
.cr-filter-bar { background:#fff; border-bottom:1px solid #e5e9f2; box-shadow:0 2px 10px rgba(0,0,0,.05); position:sticky; top:0; z-index:300; }
.cr-filter-inner { padding:0 60px; }
.cr-tabs { display:flex; border-bottom:2px solid #f0f2f7; overflow-x:auto; }
.cr-tabs::-webkit-scrollbar { display:none; }
.cr-tab { padding:15px 24px; font-size:13.5px; font-weight:600; color:#8a94a6; cursor:pointer; border:none; background:none; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .2s; font-family:'Exo 2',sans-serif; white-space:nowrap; display:flex; align-items:center; gap:7px; }
.cr-tab-count { background:#f0f2f7; color:#8a94a6; font-size:11px; font-weight:700; padding:2px 7px; border-radius:10px; transition:all .2s; }
.cr-tab.active { color:#1a56db; border-bottom-color:#1a56db; }
.cr-tab.active .cr-tab-count { background:#dbeafe; color:#1a56db; }
.cr-tab:hover:not(.active) { color:#333; }
.cr-filters-row { display:flex; align-items:center; gap:12px; padding:12px 0; flex-wrap:wrap; }
.cr-fgroup { display:flex; flex-direction:column; gap:3px; }
.cr-flabel { font-size:10px; font-weight:700; color:#aab; text-transform:uppercase; letter-spacing:.07em; }
.cr-fselect { border:1px solid #dde2ee; border-radius:7px; padding:7px 28px 7px 11px; font-size:13px; color:#2d3a4e; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center; appearance:none; cursor:pointer; font-family:'Exo 2',sans-serif; min-width:110px; transition:border-color .2s; }
.cr-fselect:focus { outline:none; border-color:#1a56db; }
.cr-fselect.active-filter { border-color:#1a56db; background-color:#f0f5ff; color:#1a56db; }
.cr-search-wrap { display:flex; align-items:stretch; border:1px solid #dde2ee; border-radius:7px; overflow:hidden; margin-left:auto; transition:border-color .2s; }
.cr-search-wrap:focus-within { border-color:#1a56db; }
.cr-search-input { border:none; padding:8px 14px; font-size:13px; color:#2d3a4e; outline:none; width:210px; font-family:'Exo 2',sans-serif; background:#fff; }
.cr-search-btn { background:#f5a623; border:none; padding:0 16px; color:#fff; font-size:14px; cursor:pointer; transition:background .2s; display:flex; align-items:center; }
.cr-search-btn:hover { background:#d4890e; }
.cr-reset-btn { font-size:12px; color:#e53935; font-weight:600; background:none; border:none; cursor:pointer; padding:0; display:flex; align-items:center; gap:4px; white-space:nowrap; }
.cr-reset-btn:hover { color:#b71c1c; }
.cr-pills-wrap { display:flex; flex-wrap:wrap; gap:7px; padding:9px 0 13px; border-top:1px solid #f0f2f7; }
.cr-pill { padding:5px 14px; border-radius:20px; font-size:12px; font-weight:500; border:1px solid #e0e4f0; background:#f8f9fd; color:#5a6678; cursor:pointer; transition:all .2s; white-space:nowrap; font-family:'Exo 2',sans-serif; display:inline-flex; align-items:center; gap:5px; }
.cr-pill.active,.cr-pill:hover { background:#dbeafe; border-color:#93c5fd; color:#1a56db; }

/* ─── CONTENT ────────────────────────────────────────────────────────────── */
.cr-content { padding:28px 60px 72px; }
.cr-result-bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.cr-result-count { font-size:13px; color:#7a8499; }
.cr-result-count strong { color:#0f1b2d; }
.cr-sort-select { border:1px solid #dde2ee; border-radius:7px; padding:6px 28px 6px 11px; font-size:13px; color:#2d3a4e; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center; appearance:none; cursor:pointer; font-family:'Exo 2',sans-serif; }

/* ─── GRID ────────────────────────────────────────────────────────────────── */
.cr-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; }

/* ─── CARD ────────────────────────────────────────────────────────────────── */
.cr-card { background:#fff; border-radius:12px; overflow:hidden; border:1px solid #e5e9f2; transition:transform .25s,box-shadow .25s; display:flex; flex-direction:column; position:relative; }
.cr-card:hover { transform:translateY(-5px); box-shadow:0 16px 40px rgba(0,0,0,.1); }

/* thumbnail */
.cr-thumb { position:relative; aspect-ratio:16/9; overflow:hidden; background:#0f1b2d; flex-shrink:0; }
.cr-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .45s; }
.cr-card:hover .cr-thumb img { transform:scale(1.05); }
.cr-featured-star { position:absolute; top:10px; left:10px; background:#f5a623; color:#fff; font-size:10px; font-weight:700; padding:3px 9px; border-radius:4px; letter-spacing:.05em; text-transform:uppercase; display:flex; align-items:center; gap:4px; }
.cr-status-badge { position:absolute; top:10px; right:10px; font-size:10px; font-weight:700; letter-spacing:.06em; padding:4px 10px; border-radius:4px; text-transform:uppercase; }
.cr-status-badge.ongoing  { background:#fb8c00; color:#fff; }
.cr-status-badge.upcoming { background:#43a047; color:#fff; }
.cr-status-badge.recorded { background:#5c6bc0; color:#fff; }
.cr-cat-chip { position:absolute; bottom:9px; left:10px; font-size:10px; font-weight:600; padding:3px 10px; border-radius:4px; background:rgba(0,0,0,.55); color:#fff; backdrop-filter:blur(4px); max-width:160px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* body */
.cr-body { padding:14px 16px 10px; flex:1; display:flex; flex-direction:column; }
.cr-title { font-family:'Rajdhani',sans-serif; font-size:15.5px; font-weight:700; color:#0f1b2d; line-height:1.3; margin-bottom:8px; flex:1; }
.cr-title a { color:inherit; transition:color .2s; }
.cr-title a:hover { color:#1a56db; }

/* meta tags row */
.cr-tags { display:flex; flex-wrap:wrap; gap:5px; margin-bottom:10px; }
.cr-tag { display:inline-flex; align-items:center; gap:4px; font-size:11.5px; color:#5a6678; background:#f4f6fb; padding:3px 9px; border-radius:5px; border:1px solid #eaecf2; white-space:nowrap; }
.cr-tag i { font-size:10px; color:#f5a623; }
.cr-tag.cert { background:#fff8e1; border-color:#ffe082; color:#f57f17; }
.cr-tag.cert i { color:#f57f17; }
.cr-tag.dur  { background:#e8f5e9; border-color:#c8e6c9; color:#2e7d32; }
.cr-tag.dur i { color:#2e7d32; }

/* short desc */
.cr-short-desc { font-size:12px; color:#7a8499; line-height:1.6; margin-bottom:8px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }

/* footer */
.cr-footer { display:flex; align-items:center; justify-content:space-between; padding:10px 16px 12px; border-top:1px solid #f0f2f7; background:#fafbff; gap:8px; margin-top:auto; }
.cr-price { font-family:'Rajdhani',sans-serif; line-height:1.2; }
.cr-price .cr-price-main { font-size:19px; font-weight:700; color:#0f1b2d; }
.cr-price .cr-price-free { font-size:17px; font-weight:700; color:#43a047; }
.cr-price .cr-price-orig { text-decoration:line-through; color:#b0b8c9; font-size:12px; margin-left:4px; }
.cr-price .cr-price-disc { font-size:11px; font-weight:700; color:#43a047; background:#e8f5e9; padding:2px 6px; border-radius:4px; margin-left:4px; }
.cr-enroll-btn { font-size:12.5px; font-weight:700; color:#f5a623; display:inline-flex; align-items:center; gap:5px; white-space:nowrap; transition:gap .2s,color .2s; border:none; background:none; padding:0; cursor:pointer; font-family:'Exo 2',sans-serif; }
.cr-enroll-btn:hover { gap:9px; color:#d4890e; }

/* curriculum mini-strip on card */
.cr-curriculum-strip { display:flex; gap:10px; align-items:center; padding:7px 16px 9px; background:#f8f9fd; border-top:1px solid #eef0f8; }
.cr-cs-item { display:flex; align-items:center; gap:4px; font-size:11.5px; color:#7a8499; }
.cr-cs-item i { font-size:10px; color:#1a56db; }
.cr-cs-sep { color:#dde2ee; font-size:11px; }

/* empty */
.cr-empty { grid-column:1/-1; text-align:center; padding:70px 20px; color:#9aa3b5; }
.cr-empty i { font-size:52px; opacity:.3; display:block; margin-bottom:14px; }
.cr-empty h4 { font-family:'Rajdhani',sans-serif; font-size:22px; color:#2d3a4e; margin-bottom:6px; }
.cr-no-results-js { display:none; grid-column:1/-1; text-align:center; padding:60px 20px; color:#9aa3b5; }
.cr-no-results-js i { font-size:42px; opacity:.3; display:block; margin-bottom:12px; }

/* ─── RESPONSIVE ─────────────────────────────────────────────────────────── */
@media(max-width:1100px) { .cr-grid{grid-template-columns:repeat(2,1fr);} }
@media(max-width:860px)  {
    .cr-hero{flex-direction:column;padding:24px 20px;}
    .cr-hero-right{width:100%;grid-template-columns:repeat(4,1fr);}
    .cr-filter-inner{padding:0 16px;}
    .cr-content{padding:18px 14px 56px;}
    .cr-search-wrap{margin-left:0;width:100%;}
    .cr-search-input{width:100%;flex:1;}
}
@media(max-width:600px)  {
    .cr-grid{grid-template-columns:1fr;}
    .cr-hero-right{grid-template-columns:1fr 1fr;}
    .cr-tab{padding:12px 14px;font-size:12.5px;}
}
</style>

<div class="cr-page">

{{-- ── HERO ──────────────────────────────────────────────────────────────── --}}
<div class="cr-hero">
    <div class="cr-hero-left cr-anim">
        <h1>{{ $heroBanner['title'] }}</h1>
        <p>{{ $heroBanner['description'] }}</p>
    </div>
    <div class="cr-hero-right cr-anim d2">
        @foreach($heroBanner['banners'] as $banner)
            <img src="{{ $banner }}" alt="Course" loading="lazy"
                 onerror="this.src='https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=400'">
        @endforeach
    </div>
</div>

{{-- ── STICKY FILTER BAR ────────────────────────────────────────────────── --}}
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

        {{-- Filter dropdowns --}}
        <div class="cr-filters-row">
            <div class="cr-fgroup">
                <span class="cr-flabel">Language</span>
                <select class="cr-fselect {{ $filterLang ? 'active-filter' : '' }}" id="crLang" onchange="crFilter()">
                    <option value="">All Languages</option>
                    <option value="hindi"    @selected($filterLang==='hindi')>Hindi</option>
                    <option value="english"  @selected($filterLang==='english')>English</option>
                    <option value="gujarati" @selected($filterLang==='gujarati')>Gujarati</option>
                </select>
            </div>
            <div class="cr-fgroup">
                <span class="cr-flabel">Level</span>
                <select class="cr-fselect {{ $filterLevel ? 'active-filter' : '' }}" id="crLevel" onchange="crFilter()">
                    <option value="">All Levels</option>
                    <option value="beginner"     @selected($filterLevel==='beginner')>Beginner</option>
                    <option value="intermediate" @selected($filterLevel==='intermediate')>Intermediate</option>
                    <option value="advanced"     @selected($filterLevel==='advanced')>Advanced</option>
                </select>
            </div>
            <div class="cr-fgroup">
                <span class="cr-flabel">Price</span>
                <select class="cr-fselect {{ $filterType ? 'active-filter' : '' }}" id="crType" onchange="crFilter()">
                    <option value="">All Prices</option>
                    <option value="free" @selected($filterType==='free')>Free</option>
                    <option value="paid" @selected($filterType==='paid')>Paid</option>
                </select>
            </div>
            <div class="cr-fgroup">
                <span class="cr-flabel">Mode</span>
                <select class="cr-fselect {{ $filterMode ? 'active-filter' : '' }}" id="crMode" onchange="crFilter()">
                    <option value="">All Modes</option>
                    <option value="online"  @selected($filterMode==='online')>Online</option>
                    <option value="offline" @selected($filterMode==='offline')>Offline</option>
                    <option value="hybrid"  @selected($filterMode==='hybrid')>Hybrid</option>
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
                       placeholder="Search courses…" value="{{ $filterSearch }}" oninput="crFilter()">
                <button class="cr-search-btn"><i class="fas fa-search"></i></button>
            </div>
        </div>

        {{-- Category pills --}}
        @if($categories->count())
        <div class="cr-pills-wrap">
            <button class="cr-pill {{ !$filterCategory ? 'active' : '' }}"
                    data-cat="" onclick="crSetCategory('',this)">
                <i class="fas fa-th-large"></i> All Categories
            </button>
            @foreach($categories as $cat)
            <button class="cr-pill {{ $filterCategory == $cat->id ? 'active' : '' }}"
                    data-cat="{{ $cat->id }}" onclick="crSetCategory('{{ $cat->id }}',this)">
                @if($cat->icon)<i class="fas {{ $cat->icon }}"></i>@endif
                {{ $cat->name }}
                <span style="opacity:.6;font-size:11px;">({{ $cat->courses_count }})</span>
            </button>
            @endforeach
        </div>
        @endif

    </div>
</div>

{{-- ── CONTENT ──────────────────────────────────────────────────────────── --}}
<div class="cr-content">

    <div class="cr-result-bar cr-anim">
        <p class="cr-result-count">
            Showing <strong id="crVisibleCount">{{ $allCourses->count() }}</strong> course(s)
            @if($filterSearch) for "<strong>{{ $filterSearch }}</strong>" @endif
        </p>
        <select class="cr-sort-select" id="crSort" onchange="crSortCards()">
            <option value="default">Sort: Default</option>
            <option value="price_asc">Price: Low to High</option>
            <option value="price_desc">Price: High to Low</option>
            <option value="title_asc">Name: A – Z</option>
            <option value="title_desc">Name: Z – A</option>
        </select>
    </div>

    <div class="cr-grid cr-anim d2" id="crGrid">

        @forelse($allCourses as $c)
        @php
            $lessonCount = $c->lessons->count();
            $sectionCount = $c->sections ? $c->sections->count() : 0;
            // Compute total duration from loaded lessons
            $totalSecs = $c->lessons->sum('duration_seconds');
            $dH = floor($totalSecs / 3600);
            $dM = floor(($totalSecs % 3600) / 60);
            $courseDuration = $totalSecs > 0 ? ($dH > 0 ? "{$dH}h {$dM}m" : "{$dM}m") : null;
        @endphp
        <div class="cr-card"
             data-status="{{ $c->status }}"
             data-lang="{{ $c->language }}"
             data-level="{{ $c->level }}"
             data-type="{{ $c->type }}"
             data-mode="{{ $c->mode }}"
             data-cat="{{ $c->course_category_id }}"
             data-title="{{ strtolower($c->title) }}"
             data-price="{{ $c->price }}"
             data-cert="{{ $c->has_certificate ? '1' : '0' }}"
             data-url="{{ route('courses.detail', $c->slug) }}"
             onclick="window.location=this.dataset.url"
             style="cursor:pointer;">

            {{-- Thumbnail --}}
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

            {{-- Body --}}
            <div class="cr-body">

                <div class="cr-title">
                    <a href="{{ route('courses.detail', $c->slug) }}" onclick="event.stopPropagation()">
                        {{ $c->title }}
                    </a>
                </div>

                {{-- Tags: level · language · mode · duration · certificate --}}
                <div class="cr-tags">
                    <span class="cr-tag">
                        <i class="fas fa-signal"></i> {{ ucfirst($c->level) }}
                    </span>
                    <span class="cr-tag">
                        <i class="fas fa-language"></i> {{ ucfirst($c->language) }}
                    </span>
                    <span class="cr-tag">
                        <i class="fas fa-globe"></i> {{ ucfirst($c->mode) }}
                    </span>
                    @if($courseDuration)
                    <span class="cr-tag dur">
                        <i class="fas fa-clock"></i> {{ $courseDuration }}
                    </span>
                    @endif
                    @if($c->has_certificate)
                    <span class="cr-tag cert">
                        <i class="fas fa-certificate"></i> Certificate
                    </span>
                    @endif
                </div>

                {{-- Short description --}}
                @if($c->short_description)
                <div class="cr-short-desc">{{ $c->short_description }}</div>
                @endif

            </div>

            {{-- Curriculum mini-strip: sections + lessons count --}}
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

            {{-- Footer --}}
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
            <i class="fas fa-book-open"></i>
            <h4>No Courses Available Yet</h4>
            <p>Check back soon — new courses are added regularly.</p>
        </div>
        @endforelse

        <div class="cr-no-results-js" id="crNoResults">
            <i class="fas fa-search"></i>
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
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('crSearch').value = '';
        document.getElementById('crSort').value   = 'default';
        activeTab = 'all';
        activeCat = '';
        document.querySelectorAll('.cr-tab').forEach(function(b, i) { b.classList.toggle('active', i === 0); });
        document.querySelectorAll('.cr-pill').forEach(function(p, i) { p.classList.toggle('active', i === 0); });
        crFilter();
    }
    window.crResetAll = crResetAll;

    // Init
    (function() {
        @if($filterStatus)
        activeTab = '{{ $filterStatus }}';
        var tab = document.querySelector('[data-tab="{{ $filterStatus }}"]');
        if (tab) { document.querySelectorAll('.cr-tab').forEach(function(b){b.classList.remove('active');}); tab.classList.add('active'); }
        @endif
        crFilter();
    })();
})();
</script>

@endsection