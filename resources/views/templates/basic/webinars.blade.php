{{-- FILE: resources/views/themes/{active_theme}/webinars.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
<style>
.qa-wrap {
    font-family: 'Exo 2', sans-serif; color: #1a1a2e;
    display: block; background: #f7f8fc;
}
.qa-wrap * { box-sizing: border-box; }
.qa-wrap h1,.qa-wrap h2,.qa-wrap h3,.qa-wrap h4 { font-family: 'Rajdhani', sans-serif; letter-spacing: .03em; }
.qa-wrap a { text-decoration: none; }
@keyframes qaFadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
.qa-anim { animation: qaFadeUp .6s ease both; }
@keyframes pulseDot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.65)} }

/* ── HERO ── */
.qa-wb-hero {
    background: #fff; border-bottom: 1px solid #e8e8e8;
    padding: 36px 48px; display: flex; align-items: center;
    justify-content: space-between; gap: 28px; overflow: hidden;
}
.qa-wb-hero-left h1 {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(30px,4vw,48px); font-weight: 700;
    color: #1a1a2e; margin: 0 0 14px; line-height: 1.05;
}
.qa-wb-hero-left p {
    font-size: 14px; color: #555; line-height: 1.78;
    max-width: 600px; margin: 0;
}
.qa-wb-hero-right   { flex-shrink: 0; width: 210px; }
.qa-wb-hero-right img { width: 100%; object-fit: contain; display: block; }
@media(max-width:768px){
    .qa-wb-hero { flex-direction: column; padding: 28px 20px; text-align: center; }
    .qa-wb-hero-right { width: 160px; }
}

/* ── FILTER BAR ── */
.qa-filter-bar {
    background: #fff; border-bottom: 1px solid #e8e8e8;
    padding: 0 48px; position: sticky; top: 0; z-index: 200;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.qa-main-tabs { display: flex; border-bottom: 2px solid #f0f0f0; }
.qa-main-tab {
    padding: 15px 22px; font-size: 14px; font-weight: 600; color: #666;
    cursor: pointer; border: none; background: none;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    transition: all .2s; font-family: 'Exo 2',sans-serif; white-space: nowrap;
}
.qa-main-tab.on { color: #F5A623; border-bottom-color: #F5A623; }
.qa-main-tab:hover:not(.on) { color: #333; }
.qa-dropdowns-row {
    display: flex; align-items: flex-end; gap: 14px;
    padding: 13px 0 12px; flex-wrap: wrap;
}
.qa-filter-group { display: flex; flex-direction: column; gap: 3px; }
.qa-filter-label {
    font-size: 10.5px; color: #999; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em;
}
.qa-filter-select {
    border: 1px solid #ddd; border-radius: 6px; padding: 7px 30px 7px 10px;
    font-size: 13px; color: #333;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance: none; -webkit-appearance: none; cursor: pointer;
    font-family: 'Exo 2',sans-serif; min-width: 90px;
}
.qa-filter-select:focus { outline: none; border-color: #F5A623; }
.qa-search-wrap {
    display: flex; align-items: stretch; overflow: hidden;
    border: 1px solid #ddd; border-radius: 6px; margin-left: auto;
}
.qa-search-input {
    border: none; padding: 8px 14px; font-size: 13px; color: #333;
    outline: none; width: 200px; font-family: 'Exo 2',sans-serif;
}
.qa-search-btn {
    background: #F5A623; border: none; padding: 0 16px;
    color: #fff; font-size: 14px; cursor: pointer;
    display: flex; align-items: center;
}
@media(max-width:768px){
    .qa-filter-bar { padding: 0 16px; }
    .qa-search-wrap { margin-left: 0; width: 100%; }
    .qa-search-input { width: 100%; }
}

/* ── CONTENT ── */
.qa-webinars-wrap {
    background: #f7f8fc; padding: 32px 48px 72px; min-height: 60vh;
}
@media(max-width:768px){ .qa-webinars-wrap { padding: 20px 16px 56px; } }
.qa-section-head {
    font-family: 'Rajdhani',sans-serif; font-size: 20px; font-weight: 700;
    color: #1a1a2e; margin: 0 0 20px; padding-bottom: 8px;
    border-bottom: 2px solid #F5A623; display: inline-block;
}
.qa-wgrid {
    display: grid; grid-template-columns: repeat(3,1fr);
    gap: 20px; margin-bottom: 40px;
}
@media(max-width:1000px){ .qa-wgrid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:580px)  { .qa-wgrid { grid-template-columns: 1fr; } }

/* ── CARD ── */
.qa-wcard {
    background: #fff; border-radius: 10px; overflow: hidden;
    border: 1px solid #e8e8e8; transition: transform .25s, box-shadow .25s;
    display: flex; flex-direction: column;
}
.qa-wcard:hover { transform: translateY(-4px); box-shadow: 0 14px 36px rgba(0,0,0,.1); }
.qa-wcard-thumb {
    position: relative; aspect-ratio: 16/9; overflow: hidden;
    background: #1a1a2e; flex-shrink: 0;
}
.qa-wcard-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .4s;
}
.qa-wcard:hover .qa-wcard-thumb img { transform: scale(1.04); }
.qa-badge {
    position: absolute; top: 10px; left: 10px;
    font-size: 10px; font-weight: 700; letter-spacing: .06em;
    padding: 4px 10px; border-radius: 4px; text-transform: uppercase;
    display: inline-flex; align-items: center; gap: 5px;
}
.qa-badge.live     { background: #e53935; color: #fff; }
.qa-badge.upcoming { background: #43a047; color: #fff; }
.qa-live-dot {
    width: 7px; height: 7px; border-radius: 50%; background: #fff;
    display: inline-block; animation: pulseDot 1.2s ease-in-out infinite;
}
.qa-enrolled-badge {
    position: absolute; top: 10px; right: 10px;
    background: #43a047; color: #fff; font-size: 10px; font-weight: 700;
    padding: 3px 8px; border-radius: 4px; letter-spacing: .04em;
}
.qa-thumb-price {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(0,0,0,.78) 0%, transparent 100%);
    padding: 22px 12px 9px;
    display: flex; align-items: flex-end; justify-content: space-between;
}
.qa-price-text { color: #fff; font-size: 13px; font-weight: 700; }
.qa-price-text .strike {
    text-decoration: line-through; color: rgba(255,255,255,.5);
    margin: 0 3px; font-weight: 400; font-size: 12px;
}
.qa-price-text .disc { font-size: 11px; color: #81c784; margin-left: 4px; }
.qa-view-link {
    font-size: 12px; color: #F5A623; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: gap .2s;
}
.qa-view-link:hover { gap: 7px; }
.qa-wcard-body {
    padding: 14px 16px; flex: 1; display: flex; flex-direction: column;
}
.qa-wcard-title {
    font-family: 'Rajdhani',sans-serif; font-size: 15px; font-weight: 700;
    color: #1a1a2e; line-height: 1.35; margin-bottom: 10px; flex: 1;
}
.qa-wcard-type {
    display: inline-block; font-size: 11px; font-weight: 700;
    padding: 3px 11px; border-radius: 20px; margin-bottom: 11px; letter-spacing: .03em;
}
.qa-wcard-type.free { background: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
.qa-wcard-type.paid { background: #fff3e0; color: #e65100; border: 1px solid #ffcc80; }
.qa-meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 10px; }
.qa-meta-row  { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #666; }
.qa-meta-row i { color: #F5A623; font-size: 11px; width: 14px; text-align: center; }
.qa-meta-row .meta-v { color: #333; font-weight: 500; }
.qa-meta-row .past-d { color: #e53935; font-weight: 500; }
.qa-wcard-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; border-top: 1px solid #f0f0f0;
    background: #fafafa; gap: 8px;
}
.qa-footer-price {
    font-family: 'Rajdhani',sans-serif; font-size: 17px; font-weight: 700;
    color: #1a1a2e; line-height: 1.2;
}
.qa-footer-price .orig {
    text-decoration: line-through; color: #aaa;
    font-size: 12px; margin-right: 3px; font-weight: 400;
}
.qa-footer-price .pct { font-size: 11px; color: #43a047; font-weight: 700; margin-left: 4px; }
.qa-footer-link {
    font-size: 12px; color: #F5A623; font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: gap .2s; flex-shrink: 0;
}
.qa-footer-link:hover { gap: 7px; }

/* ── TABS ── */
.qa-tab-panel    { display: none; }
.qa-tab-panel.on { display: block; animation: qaFadeUp .4s ease both; }
.qa-no-results {
    text-align: center; padding: 70px 20px; color: #999; font-size: 15px;
}
.qa-no-results i { font-size: 36px; color: #ddd; display: block; margin-bottom: 12px; }
.qa-empty { text-align: center; padding: 50px 20px; color: #999; }
.qa-empty i { font-size: 40px; color: #ddd; display: block; margin-bottom: 10px; }

/* ── LOGIN PROMPT ── */
.qa-login-prompt {
    text-align: center; padding: 90px 20px; background: #fff;
    border-radius: 14px; border: 1px solid #e8e8e8;
}
.qa-login-prompt i  { font-size: 52px; color: #ddd; display: block; margin-bottom: 18px; }
.qa-login-prompt h3 {
    font-family: 'Rajdhani',sans-serif; font-size: 26px;
    color: #1a1a2e; margin-bottom: 10px;
}
.qa-login-prompt p  { color: #888; font-size: 14px; margin-bottom: 26px; }
.qa-login-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: #F5A623; color: #000; font-weight: 700;
    padding: 13px 30px; border-radius: 9px;
    font-family: 'Rajdhani',sans-serif; font-size: 16px;
    letter-spacing: .04em; transition: background .2s;
}
.qa-login-btn:hover { background: #d4890e; }
</style>

<div class="qa-wrap">

{{-- ══════════════════════════════════════════════════════════
     HERO — fully dynamic from WebinarPageCms
══════════════════════════════════════════════════════════ --}}
<div class="qa-wb-hero">
    <div class="qa-wb-hero-left qa-anim">
        <h1>{{ $heroBanner['title'] }}</h1>
        <p>{{ $heroBanner['description'] }}</p>
    </div>
    @if(!empty($heroBanner['illustration']))
    <div class="qa-wb-hero-right qa-anim">
        <img src="{{ $heroBanner['illustration'] }}" alt="{{ $heroBanner['title'] }}">
    </div>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════
     FILTER BAR — languages & proficiency from WebinarPageCms
══════════════════════════════════════════════════════════ --}}
<div class="qa-filter-bar">
    <div class="qa-main-tabs">
        <button class="qa-main-tab on" onclick="qaSwitchTab(0,this)">All Webinars</button>
        <button class="qa-main-tab"    onclick="qaSwitchTab(1,this)">My Webinars</button>
    </div>
    <div class="qa-dropdowns-row">

        {{-- Language — dynamic from WebinarPageCms --}}
        <div class="qa-filter-group">
            <span class="qa-filter-label">Language</span>
            <select class="qa-filter-select" id="filterLang" onchange="qaApplyFilters()">
                <option value="">All</option>
                @foreach($languages as $lang)
                    <option value="{{ strtolower($lang) }}">{{ $lang }}</option>
                @endforeach
            </select>
        </div>

        {{-- Price --}}
        <div class="qa-filter-group">
            <span class="qa-filter-label">Price</span>
            <select class="qa-filter-select" id="filterPrice" onchange="qaApplyFilters()">
                <option value="">All</option>
                <option value="free">Free</option>
                <option value="paid">Paid</option>
            </select>
        </div>

        {{-- Proficiency — dynamic from WebinarPageCms --}}
        <div class="qa-filter-group">
            <span class="qa-filter-label">Proficiency</span>
            <select class="qa-filter-select" id="filterLevel" onchange="qaApplyFilters()">
                <option value="">All</option>
                @foreach($proficiency as $lvl)
                    <option value="{{ strtolower($lvl) }}">{{ $lvl }}</option>
                @endforeach
            </select>
        </div>

        {{-- Search --}}
        <div class="qa-search-wrap">
            <input class="qa-search-input" type="text" id="qaSearch"
                   placeholder="Search webinars..." oninput="qaApplyFilters()">
            <button class="qa-search-btn" type="button">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     CONTENT
══════════════════════════════════════════════════════════ --}}
<div class="qa-webinars-wrap">

    {{-- ── TAB 0: ALL WEBINARS ── --}}
    <div class="qa-tab-panel on" id="qaPanel0">

        {{-- Upcoming / Live --}}
        <div class="qa-section-head">Upcoming &amp; Live Webinars</div>

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
        <div class="qa-section-head" style="margin-top:10px;">Past Webinars</div>

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
                            <i class="fas fa-calendar-alt" style="color:#e53935;"></i>
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

        {{-- JS no-results message --}}
        <div class="qa-no-results" id="qaNoResults" style="display:none;">
            <i class="fas fa-search"></i>
            No webinars found matching your filters.
        </div>

    </div>{{-- /#qaPanel0 --}}

    {{-- ── TAB 1: MY WEBINARS ── --}}
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
                    <a href="{{ route('webinars.index') }}"
                       style="color:#F5A623;font-weight:600;">
                        Browse Webinars →
                    </a>
                </div>
            @else
            <div class="qa-section-head">My Registered Webinars</div>
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
                        <span style="font-size:12px;color:#43a047;font-weight:600;">
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
/* ── TAB SWITCH ── */
function qaSwitchTab(idx, btn) {
    document.querySelectorAll('.qa-main-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qa-tab-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === idx);
    });
}

/* ── FILTER ── */
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