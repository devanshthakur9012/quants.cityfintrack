{{-- FILE: resources/views/themes/{active_theme}/events.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
.qev * { box-sizing:border-box; }
:root { --gold:#7DFF00; --dark:#0D1B2A; --bg:#f4f6fb; --txt:#1a1a2e; --bdr:#e5e9f2; }
@keyframes evFadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
.ev-anim { animation:evFadeUp .6s ease both; }
@keyframes pulseDot { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(.6);opacity:.4} }

/* ── HERO ── */
.qev-hero { background:linear-gradient(135deg,#0D1B2A 0%,#162844 60%,#1a3560 100%); padding:56px 48px; position:relative; overflow:hidden; }
.qev-hero::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse at 70% 50%,rgba(245,166,35,.12),transparent 65%); pointer-events:none; }
.qev-hero-inner { max-width:1100px; margin:0 auto; text-align:center; position:relative; }
.qev-hero-eyebrow { display:inline-flex; align-items:center; gap:8px; background:rgba(245,166,35,.15); border:1px solid rgba(245,166,35,.35); border-radius:30px; padding:6px 16px; margin-bottom:20px; font-size:12px; font-weight:700; color:var(--gold); letter-spacing:.1em; text-transform:uppercase; }
.qev-hero-dot { width:7px; height:7px; border-radius:50%; background:var(--gold); animation:pulseDot 1.4s ease infinite; }
.qev-hero h1 { font-family:'Rajdhani',sans-serif; font-size:clamp(32px,5vw,56px); font-weight:700; color:#fff; margin:0 0 14px; line-height:1.05; }
.qev-hero h1 span { color:var(--gold); }
.qev-hero p  { font-size:15px; color:rgba(255,255,255,.6); line-height:1.75; max-width:560px; margin:0 auto; }
@media(max-width:768px){ .qev-hero { padding:40px 20px 36px; } }

/* ── FILTER BAR ── */
.qev-filter-bar { background:#fff; border-bottom:1px solid var(--bdr); padding:0 48px; box-shadow:0 2px 10px rgba(0,0,0,.05); }
.qev-tabs { display:flex; border-bottom:2px solid #f0f0f0; }
.qev-tab { padding:15px 22px; font-size:14px; font-weight:600; color:#888; cursor:pointer; border:none; background:none; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .2s; font-family:'Exo 2',sans-serif; white-space:nowrap; }
.qev-tab.on { color:var(--gold); border-bottom-color:var(--gold); }
.qev-tab:hover:not(.on) { color:#333; }
.qev-filter-row { display:flex; align-items:flex-end; gap:14px; padding:12px 0 11px; flex-wrap:wrap; }
.qev-filter-group { display:flex; flex-direction:column; gap:3px; }
.qev-filter-label { font-size:10.5px; color:#999; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
.qev-filter-select { border:1px solid #ddd; border-radius:6px; padding:7px 28px 7px 10px; font-size:13px; color:#333; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center; appearance:none; cursor:pointer; font-family:'Exo 2',sans-serif; outline:none; min-width:90px; }
.qev-filter-select:focus { border-color:var(--gold); }
.qev-search-wrap { display:flex; overflow:hidden; border:1px solid #ddd; border-radius:6px; margin-left:auto; }
.qev-search-input { border:none; padding:8px 14px; font-size:13px; color:#333; outline:none; width:200px; font-family:'Exo 2',sans-serif; }
.qev-search-btn { background:var(--gold); border:none; padding:0 16px; color:#fff; cursor:pointer; display:flex; align-items:center; font-size:13px; }
@media(max-width:768px){ .qev-filter-bar{padding:0 16px;} .qev-search-wrap{margin-left:0;width:100%;} .qev-search-input{width:100%;} }

/* ── CONTENT ── */
.qev-content { background:var(--bg); padding:36px 48px 72px; min-height:60vh; }
@media(max-width:768px){ .qev-content { padding:24px 16px 56px; } }
.qev-section-head { display:flex; align-items:center; gap:14px; margin-bottom:24px; }
.qev-section-head h2 { font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; color:var(--txt); margin:0; white-space:nowrap; }
.qev-section-head::after { content:''; flex:1; height:2px; background:linear-gradient(90deg,var(--gold),transparent); border-radius:2px; }

/* ── FEATURED CARD ── */
.qev-featured { background:linear-gradient(135deg,#0D1B2A,#1a3560); border-radius:16px; overflow:hidden; display:grid; grid-template-columns:1fr 340px; margin-bottom:36px; border:1px solid rgba(245,166,35,.2); box-shadow:0 8px 40px rgba(0,0,0,.12); position:relative; }
.qev-featured::before { content:''; position:absolute; inset:0; background:radial-gradient(ellipse at 80% 50%,rgba(245,166,35,.1),transparent 60%); pointer-events:none; }
.qev-feat-body { padding:40px 44px; display:flex; flex-direction:column; justify-content:center; position:relative; z-index:1; }
.qev-feat-badge { display:inline-flex; align-items:center; gap:7px; background:rgba(245,166,35,.18); border:1px solid rgba(245,166,35,.4); border-radius:30px; padding:5px 14px; margin-bottom:16px; font-size:11px; font-weight:700; color:var(--gold); width:fit-content; }
.qev-feat-body h3 { font-family:'Rajdhani',sans-serif; font-size:clamp(22px,3vw,32px); font-weight:700; color:#fff; margin:0 0 12px; line-height:1.15; }
.qev-feat-body p  { font-size:14px; color:rgba(255,255,255,.6); line-height:1.7; margin:0 0 22px; max-width:480px; }
.qev-feat-meta  { display:flex; flex-wrap:wrap; gap:16px; margin-bottom:26px; }
.qev-feat-meta-item { display:flex; align-items:center; gap:7px; font-size:13px; color:rgba(255,255,255,.75); }
.qev-feat-meta-item i { color:var(--gold); }
.qev-feat-btn { display:inline-flex; align-items:center; gap:8px; background:var(--gold); color:#000; font-weight:700; padding:13px 28px; border-radius:9px; font-size:14px; font-family:'Rajdhani',sans-serif; letter-spacing:.04em; transition:background .2s; text-decoration:none; }
.qev-feat-btn:hover { background:#d4890e; color:#000; }
.qev-feat-img { position:relative; overflow:hidden; min-height:280px; }
.qev-feat-img img { width:100%; height:100%; object-fit:cover; display:block; }
.qev-feat-price-tag { position:absolute; top:18px; right:18px; background:rgba(0,0,0,.7); backdrop-filter:blur(8px); border:1px solid rgba(245,166,35,.3); border-radius:10px; padding:10px 16px; text-align:center; }
.qev-feat-price-tag .price { font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; color:var(--gold); }
.qev-feat-price-tag .orig  { font-size:12px; text-decoration:line-through; color:rgba(255,255,255,.4); }
.qev-feat-price-tag .disc  { font-size:11px; color:#81c784; font-weight:700; }
@media(max-width:900px){ .qev-featured{grid-template-columns:1fr;} .qev-feat-img{height:220px;min-height:unset;} .qev-feat-body{padding:28px 24px;} }

/* ── GRID ── */
.qev-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; margin-bottom:44px; }
@media(max-width:1050px){ .qev-grid{grid-template-columns:repeat(2,1fr);} }
@media(max-width:580px) { .qev-grid{grid-template-columns:1fr;} }

/* ── CARD ── */
.qev-card { background:#fff; border-radius:12px; overflow:hidden; border:1px solid var(--bdr); display:flex; flex-direction:column; transition:transform .25s,box-shadow .25s; }
.qev-card:hover { transform:translateY(-5px); box-shadow:0 16px 40px rgba(0,0,0,.1); }
.qev-card-thumb { position:relative; aspect-ratio:16/9; overflow:hidden; background:#1a1a2e; }
.qev-card-thumb img { width:100%; height:100%; object-fit:cover; display:block; transition:transform .4s; }
.qev-card:hover .qev-card-thumb img { transform:scale(1.05); }
.qev-card-badge { position:absolute; top:10px; left:10px; font-size:10px; font-weight:700; letter-spacing:.06em; padding:4px 11px; border-radius:5px; text-transform:uppercase; }
.qev-card-badge.symposium  { background:#c62828; color:#fff; }
.qev-card-badge.workshop   { background:#e65100; color:#fff; }
.qev-card-badge.seminar    { background:#00695c; color:#fff; }
.qev-card-badge.bootcamp   { background:#37474f; color:#fff; }
.qev-card-badge.conference { background:#4527a0; color:#fff; }
.qev-card-badge.other      { background:#455a64; color:#fff; }
.qev-seats-badge { position:absolute; top:10px; right:10px; background:rgba(0,0,0,.65); backdrop-filter:blur(6px); border-radius:5px; padding:4px 10px; font-size:11px; color:#fff; font-weight:600; display:flex; align-items:center; gap:5px; }
.qev-seats-badge.low { color:#ef9a9a; }
.qev-card-price-ov { position:absolute; bottom:0; left:0; right:0; background:linear-gradient(to top,rgba(0,0,0,.8),transparent); padding:24px 12px 10px; display:flex; align-items:flex-end; justify-content:space-between; }
.qev-price-txt { font-family:'Rajdhani',sans-serif; font-size:15px; font-weight:700; color:#fff; }
.qev-price-txt .strike { text-decoration:line-through; color:rgba(255,255,255,.45); font-size:12px; margin:0 4px; font-weight:400; }
.qev-price-txt .disc   { font-size:11px; color:#a5d6a7; }
.qev-view-lnk { font-size:12px; color:var(--gold); font-weight:600; display:inline-flex; align-items:center; gap:4px; white-space:nowrap; transition:gap .2s; text-decoration:none; }
.qev-view-lnk:hover { gap:7px; }
.qev-card-body { padding:16px 18px; flex:1; display:flex; flex-direction:column; }
.qev-card-date-strip { display:flex; align-items:center; gap:8px; background:#fff8ed; border:1px solid #ffe0b2; border-radius:7px; padding:6px 12px; margin-bottom:12px; width:fit-content; }
.qev-card-date-strip i { color:var(--gold); font-size:12px; }
.qev-card-date-strip span { font-size:12.5px; font-weight:700; color:#b45309; }
.qev-card-title { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:var(--txt); margin-bottom:10px; line-height:1.35; flex:1; }
.qev-card-desc  { font-size:12.5px; color:#777; line-height:1.65; margin-bottom:12px; }
.qev-card-tags  { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.qev-card-tag   { font-size:11px; padding:3px 9px; border-radius:4px; font-weight:600; background:#f0f2ff; color:#3949ab; border:1px solid #c5cae9; }
.qev-card-meta  { display:grid; grid-template-columns:1fr 1fr; gap:5px 10px; }
.qev-card-meta-row { display:flex; align-items:center; gap:6px; font-size:12px; color:#666; }
.qev-card-meta-row i { color:var(--gold); font-size:11px; width:13px; text-align:center; }
.qev-card-meta-row .mv { color:#333; font-weight:600; }
.qev-card-footer { padding:11px 18px; border-top:1px solid var(--bdr); background:#fafafa; display:flex; align-items:center; justify-content:space-between; gap:8px; }
.qev-footer-price { font-family:'Rajdhani',sans-serif; font-size:18px; font-weight:700; color:var(--txt); }
.qev-footer-price .orig { text-decoration:line-through; color:#bbb; font-size:12px; margin-right:3px; font-weight:400; }
.qev-footer-price .pct  { font-size:11px; color:#43a047; font-weight:700; margin-left:4px; }
.qev-register-btn { display:inline-flex; align-items:center; gap:6px; background:var(--gold); color:#000; font-weight:700; font-size:13px; padding:8px 18px; border-radius:8px; transition:all .2s; font-family:'Exo 2',sans-serif; white-space:nowrap; text-decoration:none; }
.qev-register-btn:hover { background:#d4890e; color:#000; }
.qev-register-btn.past { background:#455a64; color:#fff; }

/* ── COUNTDOWN ── */
.qev-countdown { background:var(--dark); border-top:1px solid rgba(245,166,35,.2); padding:8px 18px; display:flex; align-items:center; gap:10px; }
.qev-countdown-label { font-size:11px; color:rgba(255,255,255,.5); font-weight:600; flex-shrink:0; }
.qev-countdown-boxes { display:flex; gap:6px; }
.qev-countdown-unit { display:flex; flex-direction:column; align-items:center; background:rgba(245,166,35,.12); border:1px solid rgba(245,166,35,.25); border-radius:5px; padding:4px 8px; min-width:36px; }
.qev-countdown-num { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:var(--gold); line-height:1; }
.qev-countdown-sub { font-size:9px; color:rgba(255,255,255,.4); letter-spacing:.05em; }

/* ── TABS ── */
.qev-tab-panel     { display:none; }
.qev-tab-panel.on  { display:block; animation:evFadeUp .4s ease both; }
.qev-empty { text-align:center; padding:50px 20px; color:#999; }
.qev-empty i { font-size:40px; color:#ddd; display:block; margin-bottom:10px; }
.qev-no-results { display:none; text-align:center; padding:60px 20px; color:#aaa; font-size:15px; }
.qev-no-results i { font-size:36px; color:#ddd; display:block; margin-bottom:12px; }

/* ── CTA STRIP ── */
.qev-strip { background:linear-gradient(90deg,#0D1B2A,#162844); border:1px solid rgba(245,166,35,.2); border-radius:14px; padding:32px 40px; margin-top:44px; display:flex; align-items:center; justify-content:space-between; gap:24px; flex-wrap:wrap; }
.qev-strip h3 { font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; color:#fff; margin:0 0 6px; }
.qev-strip p  { font-size:14px; color:rgba(255,255,255,.55); margin:0; }
.qev-strip-btn { display:inline-flex; align-items:center; gap:8px; background:var(--gold); color:#000; font-weight:700; padding:13px 28px; border-radius:9px; font-size:14px; font-family:'Rajdhani',sans-serif; transition:background .2s; white-space:nowrap; text-decoration:none; }
.qev-strip-btn:hover { background:#d4890e; color:#000; }
</style>

<div style="font-family:'Exo 2',sans-serif;">

{{-- ══════════════════════════════════════════════════════════
     HERO — fully dynamic from EventPageCms
══════════════════════════════════════════════════════════ --}}
<div class="qev-hero ev-anim">
    <div class="qev-hero-inner">

        {{-- Eyebrow pill --}}
        <div class="qev-hero-eyebrow">
            <span class="qev-hero-dot"></span>
            {{ $eventHero['eyebrow'] }}
        </div>

        {{-- Title: split so highlight portion renders in gold --}}
        @php
            $evTitle     = $eventHero['title'];
            $evHighlight = $eventHero['title_highlight'] ?? '';
            if ($evHighlight && str_contains($evTitle, $evHighlight)) {
                $evBefore = strstr($evTitle, $evHighlight, true);
                $evAfter  = substr($evTitle, strlen($evBefore) + strlen($evHighlight));
            } else {
                $evBefore    = $evTitle;
                $evHighlight = '';
                $evAfter     = '';
            }
        @endphp
        <h1>
            {{ $evBefore }}@if($evHighlight)<span>{{ $evHighlight }}</span>@endif{{ $evAfter }}
        </h1>

        <p>{{ $eventHero['subtitle'] }}</p>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     FILTER BAR
══════════════════════════════════════════════════════════ --}}
<div class="qev-filter-bar">
    <div class="qev-tabs">
        <button class="qev-tab on" onclick="evTab(0,this)">All Events</button>
        <button class="qev-tab"    onclick="evTab(1,this)">Upcoming</button>
        <button class="qev-tab"    onclick="evTab(2,this)">Past Events</button>
    </div>
    <div class="qev-filter-row">

        {{-- Price --}}
        <div class="qev-filter-group">
            <span class="qev-filter-label">Price</span>
            <select class="qev-filter-select" id="fType" onchange="evFilter()">
                <option value="">All</option>
                <option value="free">Free</option>
                <option value="paid">Paid</option>
            </select>
        </div>

        {{-- City — dynamic from EventPageCms --}}
        <div class="qev-filter-group">
            <span class="qev-filter-label">City</span>
            <select class="qev-filter-select" id="fCity" onchange="evFilter()">
                <option value="">All Cities</option>
                @foreach($citiesMap as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        {{-- Search --}}
        <div class="qev-search-wrap">
            <input class="qev-search-input" type="text" id="evSearch"
                   placeholder="Search events..." oninput="evFilter()">
            <button class="qev-search-btn" type="button">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     CONTENT
══════════════════════════════════════════════════════════ --}}
<div class="qev-content">

    {{-- ── TAB 0: ALL ── --}}
    <div class="qev-tab-panel on" id="evPanel0">

        {{-- Featured event --}}
        @php $featured = $upcomingEvents->firstWhere('is_featured', true); @endphp
        @if($featured)
        <div class="qev-section-head ev-anim"><h2>Featured Event</h2></div>
        <div class="qev-featured ev-anim">
            <div class="qev-feat-body">
                <div class="qev-feat-badge">
                    <span class="qev-hero-dot"></span> REGISTRATIONS OPEN
                </div>
                <h3>{{ $featured->title }}</h3>
                @if($featured->description)
                    <p>{{ Str::limit($featured->description, 180) }}</p>
                @endif
                <div class="qev-feat-meta">
                    @if($featured->formatted_date !== '—')
                        <div class="qev-feat-meta-item">
                            <i class="fas fa-calendar-alt"></i> {{ $featured->formatted_date }}
                        </div>
                    @endif
                    @if($featured->location)
                        <div class="qev-feat-meta-item">
                            <i class="fas fa-map-marker-alt"></i> {{ $featured->location }}
                        </div>
                    @endif
                    @if($featured->formatted_duration !== '—')
                        <div class="qev-feat-meta-item">
                            <i class="fas fa-clock"></i> {{ $featured->formatted_duration }}
                        </div>
                    @endif
                </div>
                <a href="{{ route('events.detail', $featured->slug) }}" class="qev-feat-btn">
                    <i class="fas fa-ticket-alt"></i> Register Now
                </a>
            </div>
            <div class="qev-feat-img">
                <img src="{{ $featured->thumbnail_url }}" alt="{{ $featured->title }}">
                <div class="qev-feat-price-tag">
                    @if($featured->type === 'free')
                        <div class="price">FREE</div>
                    @else
                        <div class="price">₹{{ number_format($featured->price) }}</div>
                        @if($featured->mrp > $featured->price)
                            <div class="orig">₹{{ number_format($featured->mrp) }}</div>
                        @endif
                        @if($featured->discount_label_auto)
                            <div class="disc">{{ $featured->discount_label_auto }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- Upcoming --}}
        <div class="qev-section-head"><h2>Upcoming Events</h2></div>
        @if($upcomingEvents->isEmpty())
            <div class="qev-empty" style="margin-bottom:32px;">
                <i class="fas fa-calendar-times"></i>
                No upcoming events at the moment.
            </div>
        @else
        <div class="qev-grid" id="evUpGrid">
            @foreach($upcomingEvents as $ev)
                @if(!$ev->is_featured)
                    @include($activeTemplate.'partials.event-card', ['ev' => $ev, 'isPast' => false])
                @endif
            @endforeach
        </div>
        @endif

        {{-- Past --}}
        <div class="qev-section-head" style="margin-top:8px;"><h2>Past Events</h2></div>
        @if($pastEvents->isEmpty())
            <div class="qev-empty">
                <i class="fas fa-history"></i> No past events yet.
            </div>
        @else
        <div class="qev-grid" id="evPastGrid">
            @foreach($pastEvents as $ev)
                @include($activeTemplate.'partials.event-card', ['ev' => $ev, 'isPast' => true])
            @endforeach
        </div>
        @endif

        <div class="qev-no-results" id="evNoResults">
            <i class="fas fa-calendar-times"></i> No events found matching your filters.
        </div>
    </div>

    {{-- ── TAB 1: UPCOMING ── --}}
    <div class="qev-tab-panel" id="evPanel1">
        <div class="qev-section-head"><h2>Upcoming Events</h2></div>
        @if($upcomingEvents->isEmpty())
            <div class="qev-empty">
                <i class="fas fa-calendar-times"></i> No upcoming events.
            </div>
        @else
        <div class="qev-grid">
            @foreach($upcomingEvents as $ev)
                @include($activeTemplate.'partials.event-card', ['ev' => $ev, 'isPast' => false])
            @endforeach
        </div>
        @endif
    </div>

    {{-- ── TAB 2: PAST ── --}}
    <div class="qev-tab-panel" id="evPanel2">
        <div class="qev-section-head"><h2>Past Events</h2></div>
        @if($pastEvents->isEmpty())
            <div class="qev-empty">
                <i class="fas fa-history"></i> No past events yet.
            </div>
        @else
        <div class="qev-grid">
            @foreach($pastEvents as $ev)
                @include($activeTemplate.'partials.event-card', ['ev' => $ev, 'isPast' => true])
            @endforeach
        </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════════════
         BOTTOM CTA STRIP — dynamic from EventPageCms
         Only renders when cta_title is set in admin
    ══════════════════════════════════════════════════════ --}}
    @if(!empty($eventCta['title']))
    <div class="qev-strip">
        <div>
            <h3>{{ $eventCta['title'] }}</h3>
            @if(!empty($eventCta['desc']))
                <p>{{ $eventCta['desc'] }}</p>
            @endif
        </div>
        <a href="{{ $eventCta['btn_url'] ?? '#' }}" class="qev-strip-btn">
            {{ $eventCta['btn_label'] ?? 'Learn More' }}
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    @endif

</div>{{-- /.qev-content --}}
</div>

<script>
/* ── TAB SWITCH ── */
function evTab(idx, btn) {
    document.querySelectorAll('.qev-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qev-tab-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === idx);
    });
}

/* ── FILTER ── */
function evFilter() {
    var type   = document.getElementById('fType').value;
    var city   = document.getElementById('fCity').value;
    var search = document.getElementById('evSearch').value.toLowerCase().trim();
    var visible = 0;

    ['evUpGrid', 'evPastGrid'].forEach(function(id) {
        var grid = document.getElementById(id);
        if (!grid) return;
        grid.querySelectorAll('.qev-card').forEach(function(card) {
            var ok = true;
            if (type   && (card.dataset.type  || '') !== type)                          ok = false;
            if (city   && (card.dataset.city  || '') !== city)                          ok = false;
            if (search && (card.dataset.title || '').toLowerCase().indexOf(search) === -1) ok = false;
            card.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
    });

    var noRes = document.getElementById('evNoResults');
    if (noRes) noRes.style.display = (visible === 0) ? 'block' : 'none';
}

/* ── COUNTDOWN ── */
function updateCountdowns() {
    document.querySelectorAll('.qev-countdown[data-ts]').forEach(function(el) {
        var diff = parseInt(el.dataset.ts) - Math.floor(Date.now() / 1000);
        if (diff <= 0) {
            el.querySelector('.qev-countdown-label').textContent = 'Started!';
            return;
        }
        var d  = Math.floor(diff / 86400);
        var h  = Math.floor((diff % 86400) / 3600);
        var m  = Math.floor((diff % 3600) / 60);
        var dn = el.querySelector('.cd-days');
        var hn = el.querySelector('.cd-hrs');
        var mn = el.querySelector('.cd-mins');
        if (dn) dn.textContent = String(d).padStart(2, '0');
        if (hn) hn.textContent = String(h).padStart(2, '0');
        if (mn) mn.textContent = String(m).padStart(2, '0');
    });
}
updateCountdowns();
setInterval(updateCountdowns, 30000);
</script>
@endsection