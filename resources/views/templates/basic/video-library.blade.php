@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   PAGE SHELL
========================================= */
.qvl-page {
    background: #f5f5f7;
    font-family: 'Exo 2', sans-serif;
    color: #1a1a2e;
    display: block;
    min-height: 100vh;
}
.qvl-page * { box-sizing: border-box; }
.qvl-page a { text-decoration: none; color: inherit; }

/* =========================================
   TOP FILTER BAR
========================================= */
.qvl-topbar {
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    padding: 0 28px;
    top: 0; z-index: 200;
    box-shadow: 0 2px 6px rgba(0,0,0,.05);
}
/* page heading row */
.qvl-heading-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 0 10px; gap: 16px; flex-wrap: wrap;
}
.qvl-heading {
    font-family: 'Rajdhani', sans-serif;
    font-size: 22px; font-weight: 700; color: #1a1a2e;
    margin: 0;
}
/* filter controls */
.qvl-filters {
    display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap;
}
.qvl-filter-group { display: flex; flex-direction: column; gap: 2px; }
.qvl-filter-label {
    font-size: 10px; color: #999; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
}
.qvl-filter-select {
    border: 1px solid #ddd; border-radius: 6px;
    padding: 6px 26px 6px 9px; font-size: 12.5px; color: #333;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='9' height='5'%3E%3Cpath d='M0 0l4.5 5L9 0z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 8px center;
    appearance: none; -webkit-appearance: none;
    cursor: pointer; font-family: 'Exo 2', sans-serif; min-width: 74px;
}
.qvl-filter-select:focus { outline: none; border-color: #F5A623; }

.qvl-search-wrap {
    display: flex; align-items: stretch; overflow: hidden;
    border: 1px solid #ddd; border-radius: 6px;
}
.qvl-search-input {
    border: none; padding: 7px 12px; font-size: 12.5px; color: #333;
    outline: none; width: 170px; font-family: 'Exo 2', sans-serif;
}
.qvl-search-btn {
    background: #F5A623; border: none; padding: 0 13px;
    color: #fff; font-size: 13px; cursor: pointer;
    display: flex; align-items: center; transition: background .2s;
}
.qvl-search-btn:hover { background: #d4890e; }

/* category pills row */
.qvl-pills-row {
    display: flex; flex-wrap: wrap; gap: 7px;
    padding: 9px 0 12px; border-top: 1px solid #f0f0f0;
    overflow-x: auto;
}
.qvl-pill {
    padding: 5px 13px; border-radius: 16px; font-size: 12px; font-weight: 500;
    border: 1px solid #e0e0e0; background: #fafafa; color: #444;
    cursor: pointer; transition: all .18s; white-space: nowrap;
    font-family: 'Exo 2', sans-serif;
}
.qvl-pill.on, .qvl-pill:hover {
    background: rgba(245,166,35,.12); border-color: rgba(245,166,35,.45); color: #b87800;
}
@media(max-width:768px){ .qvl-topbar { padding: 0 14px; } }

/* =========================================
   BODY: SIDEBAR + GRID
========================================= */
.qvl-body {
    display: flex;
    align-items: flex-start;
    max-width: 100%;
    padding: 0;
}

/* ── LEFT SIDEBAR ── */
.qvl-sidebar {
    width: 200px;
    flex-shrink: 0;
    background: #fff;
    border-right: 1px solid #e8e8e8;
    min-height: calc(100vh - 140px);
    position: sticky;
    top: 140px; /* offset for sticky topbar */
    padding: 20px 0 40px;
    overflow-y: auto;
}
.qvl-sidebar-section { margin-bottom: 8px; }
.qvl-sidebar-group-title {
    font-size: 12px; font-weight: 700; color: #1a1a2e;
    letter-spacing: .06em; text-transform: uppercase;
    padding: 10px 18px 6px; display: block;
}
.qvl-sidebar-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 18px; cursor: pointer; font-size: 13px; color: #555;
    transition: all .18s; user-select: none;
    border-left: 3px solid transparent;
}
.qvl-sidebar-item:hover { background: rgba(245,166,35,.07); color: #b87800; border-left-color: #F5A623; }
.qvl-sidebar-item.on  { background: rgba(245,166,35,.1); color: #b87800; border-left-color: #F5A623; font-weight: 600; }
.qvl-sidebar-item-label { flex: 1; }
.qvl-sidebar-chevron { font-size: 9px; color: #bbb; transition: transform .2s; }
.qvl-sidebar-item.open .qvl-sidebar-chevron { transform: rotate(90deg); color: #F5A623; }
.qvl-sidebar-item-count {
    font-size: 11px; color: #aaa; background: #f5f5f7;
    padding: 2px 7px; border-radius: 10px; margin-right: 6px;
}
@media(max-width:768px){
    .qvl-sidebar { display: none; }
}

/* ── MAIN CONTENT ── */
.qvl-main {
    flex: 1;
    padding: 20px 24px 60px;
    min-width: 0;
}
@media(max-width:768px){ .qvl-main { padding: 14px 14px 48px; } }

/* ── VIDEO GRID ── */
.qvl-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}
@media(max-width:1100px){ .qvl-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:580px)  { .qvl-grid { grid-template-columns: 1fr; } }

/* ── VIDEO CARD ── */
.qvl-card {
    background: #fff; border-radius: 12px; overflow: hidden;
    border: 1px solid #e8e8e8;
    transition: transform .25s, box-shadow .25s;
    display: flex; flex-direction: column;
}
.qvl-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,.1);
}

/* thumbnail */
.qvl-thumb {
    position: relative; aspect-ratio: 16/9;
    overflow: hidden; background: #0d1b2a; flex-shrink: 0;
}
.qvl-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .4s;
}
.qvl-card:hover .qvl-thumb img { transform: scale(1.05); }
/* play overlay */
.qvl-play {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    background: rgba(0,0,0,.25); opacity: 0; transition: opacity .25s;
}
.qvl-card:hover .qvl-play { opacity: 1; }
.qvl-play-icon {
    width: 48px; height: 48px; border-radius: 50%;
    background: rgba(245,166,35,.9); display: flex; align-items: center;
    justify-content: center; font-size: 18px; color: #fff;
    padding-left: 4px;
}
/* duration badge */
.qvl-duration {
    position: absolute; bottom: 7px; right: 8px;
    background: rgba(0,0,0,.7); color: #fff; font-size: 11px; font-weight: 600;
    padding: 2px 7px; border-radius: 4px; letter-spacing: .03em;
}

/* card body */
.qvl-card-body { padding: 12px 14px; flex: 1; display: flex; flex-direction: column; }

/* title row */
.qvl-card-title {
    font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700;
    color: #1a1a2e; line-height: 1.3; margin-bottom: 7px; flex: 1;
    display: flex; align-items: flex-start; gap: 5px;
}
.qvl-card-title .emoji { font-size: 14px; flex-shrink: 0; }

/* stats row */
.qvl-stats {
    display: flex; align-items: center; gap: 14px;
    font-size: 12px; color: #888; margin-bottom: 8px;
}
.qvl-stats span { display: flex; align-items: center; gap: 4px; }
.qvl-stats i { font-size: 11px; color: #bbb; }

/* description */
.qvl-desc {
    font-size: 12.5px; color: #666; line-height: 1.65;
    display: -webkit-box; -webkit-line-clamp: 3;
    -webkit-box-orient: vertical; overflow: hidden;
    margin-bottom: 12px;
}

/* meta bottom */
.qvl-card-meta {
    display: grid; grid-template-columns: 1fr 1fr; gap: 5px 10px;
    border-top: 1px solid #f5f5f5; padding-top: 10px;
}
.qvl-card-meta-row {
    display: flex; align-items: center; gap: 5px;
    font-size: 11.5px; color: #666;
}
.qvl-card-meta-row i   { color: #F5A623; font-size: 10px; width: 12px; text-align: center; }
.qvl-card-meta-row span { color: #333; font-weight: 500; }

/* no results */
.qvl-no-results {
    display: none; text-align: center;
    padding: 80px 20px; color: #999; font-size: 15px;
    grid-column: 1/-1;
}
.qvl-no-results i { font-size: 40px; color: #ddd; display: block; margin-bottom: 14px; }
</style>

<div class="qvl-page">

{{-- ══════════════════════════════════════════════
     STICKY TOP FILTER BAR
══════════════════════════════════════════════ --}}
<div class="qvl-topbar">

    {{-- Heading + Filter controls --}}
    <div class="qvl-heading-row">
        <h1 class="qvl-heading">Video Library</h1>
        <div class="qvl-filters">
            <div class="qvl-filter-group">
                <span class="qvl-filter-label">Language</span>
                <select class="qvl-filter-select" id="filterLang" onchange="qvlFilter()">
                    <option value="">All</option>
                    @foreach($languages as $l)
                        <option value="{{ strtolower($l) }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qvl-filter-group">
                <span class="qvl-filter-label">Proficiency</span>
                <select class="qvl-filter-select" id="filterLevel" onchange="qvlFilter()">
                    <option value="">All</option>
                    @foreach($proficiency as $p)
                        <option value="{{ strtolower($p) }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qvl-filter-group">
                <span class="qvl-filter-label">Speaker</span>
                <select class="qvl-filter-select" id="filterSpeaker" onchange="qvlFilter()">
                    <option value="">All</option>
                    @foreach($speakers as $sp)
                        <option value="{{ strtolower($sp) }}">{{ $sp }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qvl-filter-group">
                <span class="qvl-filter-label">Sort</span>
                <select class="qvl-filter-select" id="filterSort">
                    <option value="">All</option>
                    @foreach($sorts as $s)
                        <option value="{{ strtolower($s) }}">{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="qvl-search-wrap">
                <input  class="qvl-search-input" type="text" id="qvlSearch"
                        placeholder="Search..." oninput="qvlFilter()">
                <button class="qvl-search-btn"><i class="fas fa-search"></i></button>
            </div>
        </div>
    </div>

    {{-- Category pills --}}
    <div class="qvl-pills-row">
        @foreach($pills as $idx => $pill)
            <button class="qvl-pill {{ $idx === 0 ? 'on' : '' }}"
                    onclick="qvlTogglePill(this)">{{ $pill }}</button>
        @endforeach
    </div>
</div>

{{-- ══════════════════════════════════════════════
     BODY: SIDEBAR + MAIN
══════════════════════════════════════════════ --}}
<div class="qvl-body">

    {{-- LEFT SIDEBAR --}}
    <div class="qvl-sidebar">
        @foreach($sidebar as $groupName => $items)
        <div class="qvl-sidebar-section">
            <span class="qvl-sidebar-group-title">{{ $groupName }}</span>
            @foreach($items as $item)
            <div class="qvl-sidebar-item" onclick="qvlSidebarFilter('{{ strtolower($item['label']) }}', this)">
                <span class="qvl-sidebar-item-label">{{ $item['label'] }}</span>
                <span class="qvl-sidebar-item-count">{{ $item['count'] }}</span>
                <i class="fas fa-chevron-right qvl-sidebar-chevron"></i>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>

    {{-- MAIN VIDEO GRID --}}
    <div class="qvl-main">
        <div class="qvl-grid" id="qvlGrid">

            @foreach($videos as $v)
            <div class="qvl-card"
                 data-lang="{{ $v['lang_key'] }}"
                 data-level="{{ $v['level_key'] }}"
                 data-speaker="{{ strtolower($v['speaker']) }}"
                 data-title="{{ strtolower($v['title']) }}">

                {{-- Thumbnail --}}
                <div class="qvl-thumb">
                    <img src="{{ $v['thumbnail'] }}" alt="{{ $v['title'] }}" loading="lazy">
                    <div class="qvl-play">
                        <div class="qvl-play-icon"><i class="fas fa-play"></i></div>
                    </div>
                    @if(!empty($v['duration']))
                        <span class="qvl-duration">{{ $v['duration'] }}</span>
                    @endif
                </div>

                {{-- Body --}}
                <div class="qvl-card-body">
                    <div class="qvl-card-title">
                        <span class="emoji">{{ $v['emoji'] }}</span>
                        <span>{{ $v['title'] }}</span>
                    </div>

                    <div class="qvl-stats">
                        <span><i class="fas fa-thumbs-up"></i> {{ number_format($v['likes']) }} Likes</span>
                        <span><i class="fas fa-eye"></i> {{ number_format($v['views']) }} Views</span>
                        <span>· {{ $v['age'] }}</span>
                    </div>

                    <div class="qvl-desc">{{ $v['description'] }}</div>

                    <div class="qvl-card-meta">
                        <div class="qvl-card-meta-row">
                            <i class="fas fa-signal"></i>
                            <span>{{ $v['level'] }}</span>
                        </div>
                        <div class="qvl-card-meta-row">
                            <i class="fas fa-language"></i>
                            <span>{{ $v['language'] }}</span>
                        </div>
                        @if(!empty($v['duration']))
                        <div class="qvl-card-meta-row">
                            <i class="fas fa-clock"></i>
                            <span>{{ $v['duration'] }}</span>
                        </div>
                        @endif
                        @if(!empty($v['speaker']))
                        <div class="qvl-card-meta-row">
                            <i class="fas fa-user"></i>
                            <span>{{ $v['speaker'] }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach

            {{-- No results placeholder --}}
            <div class="qvl-no-results" id="qvlNoResults">
                <i class="fas fa-video-slash"></i>
                No videos found matching your filters.
            </div>

        </div>{{-- /#qvlGrid --}}
    </div>

</div>{{-- /.qvl-body --}}
</div>{{-- /.qvl-page --}}

<script>
/* ── PILL TOGGLE ── */
function qvlTogglePill(el) {
    el.classList.toggle('on');
    qvlFilter();
}

/* ── SIDEBAR FILTER ── */
function qvlSidebarFilter(keyword, el) {
    document.querySelectorAll('.qvl-sidebar-item').forEach(function(i){ i.classList.remove('on','open'); });
    el.classList.add('on', 'open');
    document.getElementById('qvlSearch').value = keyword;
    qvlFilter();
}

/* ── MAIN FILTER ENGINE ── */
function qvlFilter() {
    var lang    = document.getElementById('filterLang').value.toLowerCase();
    var level   = document.getElementById('filterLevel').value.toLowerCase();
    var speaker = document.getElementById('filterSpeaker').value.toLowerCase();
    var search  = document.getElementById('qvlSearch').value.toLowerCase().trim();

    var cards   = document.querySelectorAll('#qvlGrid .qvl-card');
    var visible = 0;

    cards.forEach(function(card) {
        var cLang    = (card.dataset.lang    || '').toLowerCase();
        var cLevel   = (card.dataset.level   || '').toLowerCase();
        var cSpeaker = (card.dataset.speaker || '').toLowerCase();
        var cTitle   = (card.dataset.title   || '').toLowerCase();

        var ok = true;
        if (lang    && cLang.indexOf(lang)       === -1) ok = false;
        if (level   && cLevel !== level)                  ok = false;
        if (speaker && cSpeaker.indexOf(speaker) === -1) ok = false;
        if (search  && cTitle.indexOf(search)    === -1) ok = false;

        card.style.display = ok ? '' : 'none';
        if (ok) visible++;
    });

    var noRes = document.getElementById('qvlNoResults');
    if (noRes) noRes.style.display = (visible === 0) ? 'block' : 'none';
}
</script>

@endsection