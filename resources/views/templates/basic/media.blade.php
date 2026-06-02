{{-- FILE: resources/views/themes/{active_theme}/media.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — MEDIA PAGE
   Dark terminal · Matches pricing design system
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
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.qmd-wrap {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    min-height: 80vh;
}
.qmd-wrap * { box-sizing: border-box; }

@keyframes qmdFadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.qmd-anim    { animation: qmdFadeUp .55s ease both; }
.qmd-anim.d1 { animation-delay: .1s; }
.qmd-anim.d2 { animation-delay: .2s; }
.qmd-anim.d3 { animation-delay: .3s; }
.qmd-anim.d4 { animation-delay: .4s; }

@keyframes pulseDot { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(.55);opacity:.35} }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── BREADCRUMB ────────────────────────────── */
.qmd-breadcrumb {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 12px 24px;
}
.qmd-breadcrumb-inner {
    max-width: 1200px; margin: 0 auto;
    font-size: 12px; color: var(--c-muted);
    display: flex; align-items: center; gap: 7px;
    font-family: var(--f-mono);
}
.qmd-breadcrumb-inner a { color: var(--c-lime); font-weight: 600; transition: opacity .2s; }
.qmd-breadcrumb-inner a:hover { opacity: .75; }
.qmd-breadcrumb-inner i { font-size: 10px; color: var(--c-border2); }

/* ── HERO ──────────────────────────────────── */
.qmd-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 80px 24px 64px;
    border-bottom: 1px solid var(--c-border);
    text-align: center;
}
.qmd-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.025) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black, transparent);
    pointer-events: none;
}
.qmd-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 50% 60% at 50% 50%, rgba(125,255,0,.05), transparent 70%);
    pointer-events: none;
}
.qmd-hero-inner { position: relative; z-index: 1; max-width: 700px; margin: 0 auto; }

.qmd-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 16px;
    font-family: var(--f-mono);
}
.qmd-hero-eyebrow::before,
.qmd-hero-eyebrow::after { content: ''; display: block; width: 20px; height: 1px; background: var(--c-lime); }

.qmd-hero-dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--c-lime);
    animation: pulseDot 1.4s ease infinite; flex-shrink: 0;
}

.qmd-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(32px, 5vw, 54px);
    font-weight: 800; color: #fff;
    line-height: 1.08; letter-spacing: -.02em;
    margin-bottom: 16px;
}
.qmd-hero h1 span { color: var(--c-lime); }
.qmd-hero p {
    font-size: 15px; color: var(--c-muted); line-height: 1.75;
    max-width: 560px; margin: 0 auto;
}

/* ── CATEGORY TABS ─────────────────────────── */
.qmd-cat-tabs-wrap {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 24px;
    overflow-x: auto; scrollbar-width: none;
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.qmd-cat-tabs-wrap::-webkit-scrollbar { display: none; }
.qmd-cat-tabs {
    display: flex; gap: 0;
    min-width: max-content;
    max-width: 1200px; margin: 0 auto;
}
.qmd-cat-tab {
    padding: 16px 22px;
    font-size: 13px; font-weight: 600; color: var(--c-muted);
    cursor: pointer; border: none; background: none;
    border-bottom: 2px solid transparent;
    transition: all .2s; font-family: var(--f-mono);
    white-space: nowrap; display: flex; align-items: center; gap: 8px;
}
.qmd-cat-tab.active { color: var(--c-lime); border-bottom-color: var(--c-lime); }
.qmd-cat-tab:hover:not(.active) { color: var(--c-text); }
.qmd-cat-tab .cnt {
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    color: var(--c-muted);
    font-size: 10px; font-weight: 700;
    padding: 1px 7px; border-radius: 20px;
    transition: all .2s;
}
.qmd-cat-tab.active .cnt {
    background: var(--c-lime-dim);
    border-color: rgba(125,255,0,.2);
    color: var(--c-lime);
}

/* ── CONTENT ───────────────────────────────── */
.qmd-content {
    max-width: 1200px; margin: 0 auto;
    padding: 40px 24px 80px;
    min-height: 60vh;
}

/* ── SECTION HEAD ──────────────────────────── */
.qmd-section-head {
    display: flex; align-items: center; gap: 14px; margin-bottom: 24px;
}
.qmd-section-head h2 {
    font-family: var(--f-display); font-size: 20px; font-weight: 700;
    color: #fff; margin: 0; white-space: nowrap;
}
.qmd-section-head::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, rgba(125,255,0,.4) 0%, transparent 100%);
}

/* ── GALLERY PANELS ────────────────────────── */
.qmd-gallery-panel { display: none; }
.qmd-gallery-panel.active { display: block; animation: qmdFadeUp .4s ease both; }

.qmd-gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 32px;
}
@media(max-width:1100px) { .qmd-gallery-grid { grid-template-columns: repeat(3,1fr); } }
@media(max-width:768px)  { .qmd-gallery-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px)  { .qmd-gallery-grid { grid-template-columns: 1fr; } }

/* ── GALLERY ITEM ──────────────────────────── */
.qmd-gallery-item {
    position: relative; border-radius: 10px; overflow: hidden;
    aspect-ratio: 4/3;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    cursor: pointer;
    transition: border-color .25s, transform .25s, box-shadow .25s;
}
.qmd-gallery-item::before {
    content: '';
    position: absolute; top: 0; left: 12px; right: 12px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .3s; z-index: 4;
}
.qmd-gallery-item:hover {
    border-color: rgba(125,255,0,.25);
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(0,0,0,.5), 0 0 0 1px rgba(125,255,0,.12);
}
.qmd-gallery-item:hover::before { opacity: 1; }

.qmd-gallery-item img,
.qmd-gallery-item video {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .4s; pointer-events: none;
}
.qmd-gallery-item:hover img,
.qmd-gallery-item:hover video { transform: scale(1.06); }

/* Video badge */
.qmd-vid-badge {
    position: absolute; top: 10px; left: 10px;
    background: rgba(11,14,17,.75); backdrop-filter: blur(6px);
    border: 1px solid var(--c-border2);
    border-radius: 6px; padding: 4px 10px;
    font-size: 10px; font-weight: 700; color: var(--c-text);
    font-family: var(--f-mono);
    display: flex; align-items: center; gap: 5px;
    pointer-events: none; z-index: 2; letter-spacing: .06em;
}
.qmd-vid-badge i { color: var(--c-lime); }

/* Overlay */
.qmd-item-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(11,14,17,.96) 0%, rgba(11,14,17,.4) 50%, transparent 100%);
    opacity: 0; transition: opacity .3s;
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 14px; z-index: 3;
}
.qmd-gallery-item:hover .qmd-item-overlay { opacity: 1; }
.qmd-overlay-title {
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    color: #fff; line-height: 1.3; margin-bottom: 4px;
}
.qmd-overlay-desc {
    font-size: 11px; color: var(--c-muted); line-height: 1.55;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* ── LOAD MORE ─────────────────────────────── */
.qmd-loadmore-wrap { text-align: center; margin: 4px 0 44px; }
.qmd-loadmore-btn {
    display: inline-flex; align-items: center; gap: 9px;
    padding: 12px 32px; border-radius: 8px;
    border: 1px solid var(--c-border2);
    background: var(--c-surface);
    color: var(--c-muted);
    font-weight: 700; font-size: 13px;
    font-family: var(--f-display); letter-spacing: .05em;
    cursor: pointer; transition: all .22s;
}
.qmd-loadmore-btn:hover {
    border-color: rgba(125,255,0,.3);
    color: var(--c-lime);
    box-shadow: 0 0 20px rgba(125,255,0,.08);
}
.qmd-loadmore-btn.loading { opacity: .6; pointer-events: none; }
.qmd-loadmore-btn .lb-spinner {
    width: 14px; height: 14px;
    border: 2px solid var(--c-border2); border-top-color: var(--c-lime);
    border-radius: 50%; animation: spin .7s linear infinite;
    display: none; flex-shrink: 0;
}
.qmd-loadmore-btn.loading .lb-spinner { display: block; }
.qmd-loadmore-btn.loading .lb-icon    { display: none; }
.qmd-remaining { font-weight: 400; opacity: .6; font-size: 12px; font-family: var(--f-mono); }

/* ── LIGHTBOX ──────────────────────────────── */
.qmd-lightbox {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.92); backdrop-filter: blur(12px);
    align-items: center; justify-content: center; padding: 20px;
}
.qmd-lightbox.open { display: flex; animation: qmdFadeUp .25s ease both; }
.qmd-lb-inner {
    position: relative; max-width: 960px; width: 100%;
    display: flex; flex-direction: column; align-items: center;
}
.qmd-lb-media-wrap {
    width: 100%; max-height: 72vh;
    display: flex; align-items: center; justify-content: center;
    border: 1px solid var(--c-border2); border-radius: 12px; overflow: hidden;
}
.qmd-lb-media-wrap img,
.qmd-lb-media-wrap video {
    max-width: 100%; max-height: 72vh; object-fit: contain;
    display: block;
}
.qmd-lb-info { margin-top: 18px; text-align: center; }
.qmd-lb-title   { font-family: var(--f-display); font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 6px; }
.qmd-lb-desc    { font-size: 13px; color: var(--c-muted); margin: 0; }
.qmd-lb-counter { font-size: 11px; color: rgba(255,255,255,.3); margin-top: 8px; letter-spacing: .08em; font-family: var(--f-mono); }

.qmd-lb-close {
    position: absolute; top: -16px; right: -16px;
    width: 38px; height: 38px; border-radius: 50%;
    background: var(--c-surface); border: 1px solid var(--c-border2);
    cursor: pointer; font-size: 18px; color: var(--c-muted);
    box-shadow: 0 4px 16px rgba(0,0,0,.5);
    transition: all .2s; display: flex; align-items: center; justify-content: center;
}
.qmd-lb-close:hover { background: var(--c-lime); color: #000; border-color: var(--c-lime); }

.qmd-lb-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: var(--c-surface); border: 1px solid var(--c-border2);
    color: var(--c-muted); width: 44px; height: 44px; border-radius: 50%;
    font-size: 16px; cursor: pointer; transition: all .2s;
    display: flex; align-items: center; justify-content: center;
}
.qmd-lb-nav:hover { background: var(--c-lime); border-color: var(--c-lime); color: #000; }
.qmd-lb-prev { left: -60px; }
.qmd-lb-next { right: -60px; }
@media(max-width:860px) {
    .qmd-lb-prev { left: 0; bottom: -58px; top: auto; transform: none; }
    .qmd-lb-next { right: 0; bottom: -58px; top: auto; transform: none; }
}

/* ── CTA STRIP ─────────────────────────────── */
.qmd-cta-strip {
    background: var(--c-surface);
    border: 1px solid rgba(125,255,0,.12);
    border-radius: 12px;
    padding: 32px 36px; margin-top: 20px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 24px; flex-wrap: wrap;
    position: relative; overflow: hidden;
}
.qmd-cta-strip::before {
    content: '';
    position: absolute; top: 0; left: 24px; right: 24px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
}
.qmd-cta-strip h3 {
    font-family: var(--f-display); font-size: 20px; font-weight: 700;
    color: #fff; margin: 0 0 6px;
}
.qmd-cta-strip p { font-size: 13px; color: var(--c-muted); margin: 0; line-height: 1.65; }
.qmd-cta-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-lime); color: #000; font-weight: 700;
    padding: 12px 26px; border-radius: 8px; font-size: 13px;
    font-family: var(--f-display); letter-spacing: .05em;
    transition: all .2s; white-space: nowrap; text-decoration: none;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.qmd-cta-btn:hover { background: #8FFF1A; box-shadow: 0 0 30px rgba(125,255,0,.35); transform: translateY(-1px); color: #000; }

/* ── EMPTY STATE ───────────────────────────── */
.qmd-empty-state { text-align: center; padding: 80px 20px; color: var(--c-muted); }
.qmd-empty-state i { font-size: 48px; color: var(--c-border2); display: block; margin-bottom: 16px; }
.qmd-empty-state p { font-size: 14px; font-family: var(--f-mono); }
</style>

<div class="qmd-wrap">

{{-- ══════════════════════════════════════════════════════════
     BREADCRUMB
══════════════════════════════════════════════════════════ --}}
<div class="qmd-breadcrumb">
    <div class="qmd-breadcrumb-inner">
        <a href="{{ route('home') }}">Home</a>
        <i class="las la-angle-right"></i>
        <span>Media</span>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     HERO — fully dynamic from MediaPageCms
══════════════════════════════════════════════════════════ --}}
<div class="qmd-hero qmd-anim">
    <div class="qmd-hero-inner">

        {{-- Eyebrow --}}
        <div class="qmd-hero-eyebrow">
            <span class="qmd-hero-dot"></span>
            {{ $mediaCms->hero_eyebrow ?? 'Press, Media & Recognition' }}
        </div>

        {{-- Title --}}
        @php
            $mdTitle     = $mediaCms->hero_title ?? 'CityQuants In The Media';
            $mdHighlight = $mediaCms->hero_title_highlight ?? '';
            if ($mdHighlight && str_contains($mdTitle, $mdHighlight)) {
                $mdBefore = strstr($mdTitle, $mdHighlight, true);
                $mdAfter  = substr($mdTitle, strlen($mdBefore) + strlen($mdHighlight));
            } else {
                $mdBefore = $mdTitle;
                $mdHighlight = '';
                $mdAfter  = '';
            }
        @endphp
        <h1>
            {{ $mdBefore }}@if($mdHighlight)<span>{{ $mdHighlight }}</span>@endif{{ $mdAfter }}
        </h1>

        {{-- Subtitle --}}
        <p>{{ $mediaCms->hero_subtitle ?? 'TV interviews, podcast appearances, press features and webinar recordings — follow CityQuants across India\'s top financial media channels and trading publications.' }}</p>

    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     CATEGORY TABS BAR
══════════════════════════════════════════════════════════ --}}
@if($categories->isNotEmpty())
<div class="qmd-cat-tabs-wrap">
    <div class="qmd-cat-tabs" id="catTabsRow">
        @foreach($categories as $idx => $cat)
        <button class="qmd-cat-tab {{ $idx === 0 ? 'active' : '' }}"
                onclick="switchCatTab({{ $cat->id }}, this)">
            {{ $cat->name }}
            <span class="cnt">{{ $cat->media_items_count }}</span>
        </button>
        @endforeach
    </div>
</div>
@endif

{{-- ══════════════════════════════════════════════════════════
     CONTENT
══════════════════════════════════════════════════════════ --}}
<div class="qmd-content">

    @if($categories->isEmpty())
        <div class="qmd-empty-state qmd-anim">
            <i class="fas fa-photo-video"></i>
            <p>No media available yet. Check back soon!</p>
        </div>

    @else

        @foreach($categories as $idx => $cat)
        <div class="qmd-gallery-panel {{ $idx === 0 ? 'active' : '' }}"
             id="catPanel{{ $cat->id }}"
             data-category-id="{{ $cat->id }}"
             data-total="{{ $cat->media_items_count }}"
             data-loadmore-url="{{ route('media.loadmore', $cat) }}">

            {{-- Section heading --}}
            <div class="qmd-section-head qmd-anim">
                <h2>{{ $cat->name }}</h2>
            </div>

            {{-- Grid --}}
            <div class="qmd-gallery-grid" id="grid_{{ $cat->id }}">
                @foreach($cat->mediaItems->take(20) as $item)
                <div class="qmd-gallery-item"
                     data-type="{{ $item->file_type }}"
                     data-url="{{ $item->file_url }}"
                     data-title="{{ $item->title }}"
                     data-desc="{{ $item->description }}"
                     onclick="openLightbox(this, {{ $cat->id }})">

                    @if($item->is_image)
                        <img src="{{ $item->file_url }}" alt="{{ $item->title }}" loading="lazy">
                    @else
                        <video src="{{ $item->file_url }}#t=0.5" preload="metadata" muted playsinline></video>
                        <div class="qmd-vid-badge"><i class="fas fa-play"></i> VIDEO</div>
                    @endif

                    <div class="qmd-item-overlay">
                        <div class="qmd-overlay-title">{{ $item->title }}</div>
                        @if($item->description)
                            <div class="qmd-overlay-desc">{{ $item->description }}</div>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Load More button — only when there are more than 20 items --}}
            @if($cat->media_items_count > 20)
            <div class="qmd-loadmore-wrap" id="loadmorewrap_{{ $cat->id }}">
                <button class="qmd-loadmore-btn"
                        onclick="loadMore({{ $cat->id }}, this)"
                        data-offset="20">
                    <span class="lb-spinner"></span>
                    <i class="fas fa-plus-circle lb-icon"></i>
                    Load More
                    <span class="qmd-remaining">({{ $cat->media_items_count - 20 }} more)</span>
                </button>
            </div>
            @endif

        </div>
        @endforeach

    @endif

    {{-- ══════════════════════════════════════════════════════
         CTA STRIP — fully dynamic from MediaPageCms
    ══════════════════════════════════════════════════════ --}}
    <div class="qmd-cta-strip qmd-anim d4">
        <div>
            <h3>{{ $mediaCms->cta_title ?? 'Press & Media Enquiries' }}</h3>
            <p>{{ $mediaCms->cta_description ?? 'For interviews, features, podcast invitations or press kits — reach out to our media team directly.' }}</p>
        </div>
        <a href="mailto:{{ $mediaCms->cta_email ?? 'media@cityquants.com' }}" class="qmd-cta-btn">
            <i class="fas fa-envelope"></i>
            {{ $mediaCms->cta_btn_label ?? 'Contact Media Team' }}
        </a>
    </div>

</div>{{-- /.qmd-content --}}

{{-- ══════════════════════════════════════════════════════════
     LIGHTBOX
══════════════════════════════════════════════════════════ --}}
<div class="qmd-lightbox" id="qmdLightbox">
    <div class="qmd-lb-inner">
        <button class="qmd-lb-close" onclick="closeLightbox()" title="Close">&times;</button>
        <button class="qmd-lb-nav qmd-lb-prev" onclick="lbNav(-1)" title="Previous">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="qmd-lb-nav qmd-lb-next" onclick="lbNav(1)" title="Next">
            <i class="fas fa-chevron-right"></i>
        </button>
        <div class="qmd-lb-media-wrap" id="lbMediaWrap"></div>
        <div class="qmd-lb-info">
            <div class="qmd-lb-title"   id="lbTitle"></div>
            <div class="qmd-lb-desc"    id="lbDesc"></div>
            <div class="qmd-lb-counter" id="lbCounter"></div>
        </div>
    </div>
</div>

</div>{{-- /.qmd-wrap --}}

<script>
/* ══════════════════════════════════════════════════════════
   CATEGORY TAB SWITCH  (logic unchanged)
══════════════════════════════════════════════════════════ */
function switchCatTab(catId, btn) {
    document.querySelectorAll('.qmd-cat-tab').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.qmd-gallery-panel').forEach(function(p) { p.classList.remove('active'); });
    var panel = document.getElementById('catPanel' + catId);
    if (panel) panel.classList.add('active');
    lbItems   = [];
    lbCurrent = 0;
}

/* ══════════════════════════════════════════════════════════
   LOAD MORE  (logic unchanged)
══════════════════════════════════════════════════════════ */
function loadMore(catId, btn) {
    var panel  = document.getElementById('catPanel' + catId);
    var grid   = document.getElementById('grid_' + catId);
    var wrap   = document.getElementById('loadmorewrap_' + catId);
    var offset = parseInt(btn.dataset.offset);
    var total  = parseInt(panel.dataset.total);
    var url    = panel.dataset.loadmoreUrl + '?offset=' + offset;

    btn.classList.add('loading');

    fetch(url)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.items || !data.items.length) { wrap.remove(); return; }

            data.items.forEach(function(item) {
                grid.appendChild(buildItem(item, catId));
            });

            var newOffset = offset + data.items.length;
            btn.dataset.offset = newOffset;

            var remaining = total - newOffset;
            if (remaining <= 0) {
                wrap.remove();
            } else {
                var lbl = btn.querySelector('.qmd-remaining');
                if (lbl) lbl.textContent = '(' + remaining + ' more)';
            }
        })
        .catch(function() { alert('Failed to load more items. Please try again.'); })
        .finally(function() { btn.classList.remove('loading'); });
}

function buildItem(item, catId) {
    var div = document.createElement('div');
    div.className = 'qmd-gallery-item';
    div.dataset.type  = item.file_type;
    div.dataset.url   = item.file_url;
    div.dataset.title = item.title || '';
    div.dataset.desc  = item.description || '';
    div.onclick = function() { openLightbox(this, catId); };

    var overlay = '<div class="qmd-item-overlay">' +
        '<div class="qmd-overlay-title">' + escHtml(item.title) + '</div>' +
        (item.description ? '<div class="qmd-overlay-desc">' + escHtml(item.description) + '</div>' : '') +
        '</div>';

    if (item.file_type === 'image') {
        div.innerHTML = '<img src="' + escHtml(item.file_url) + '" alt="' + escHtml(item.title) + '" loading="lazy">' + overlay;
    } else {
        div.innerHTML =
            '<video src="' + escHtml(item.file_url) + '#t=0.5" preload="metadata" muted playsinline></video>' +
            '<div class="qmd-vid-badge"><i class="fas fa-play"></i> VIDEO</div>' +
            overlay;
    }
    return div;
}

function escHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* ══════════════════════════════════════════════════════════
   LIGHTBOX  (logic unchanged)
══════════════════════════════════════════════════════════ */
var lbItems   = [];
var lbCurrent = 0;

function openLightbox(el, catId) {
    var grid = document.getElementById('grid_' + catId);
    if (!grid) return;

    lbItems = Array.from(grid.querySelectorAll('.qmd-gallery-item')).map(function(card) {
        return {
            type:  card.dataset.type,
            url:   card.dataset.url,
            title: card.dataset.title,
            desc:  card.dataset.desc
        };
    });

    var allCards = Array.from(grid.querySelectorAll('.qmd-gallery-item'));
    lbCurrent = allCards.indexOf(el);
    if (lbCurrent === -1) lbCurrent = 0;

    renderLbSlide();
    document.getElementById('qmdLightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function renderLbSlide() {
    var item = lbItems[lbCurrent];
    var wrap = document.getElementById('lbMediaWrap');

    if (item.type === 'image') {
        wrap.innerHTML = '<img src="' + escHtml(item.url) + '" alt="' + escHtml(item.title) + '">';
    } else {
        wrap.innerHTML = '<video src="' + escHtml(item.url) + '" controls autoplay muted ' +
            'style="max-width:100%;max-height:72vh;"></video>';
    }

    document.getElementById('lbTitle').textContent   = item.title || '';
    document.getElementById('lbDesc').textContent    = item.desc  || '';
    document.getElementById('lbCounter').textContent = (lbCurrent + 1) + ' / ' + lbItems.length;

    document.querySelector('.qmd-lb-prev').style.visibility = lbCurrent === 0 ? 'hidden' : 'visible';
    document.querySelector('.qmd-lb-next').style.visibility = lbCurrent === lbItems.length - 1 ? 'hidden' : 'visible';
}

function lbNav(dir) {
    var next = lbCurrent + dir;
    if (next < 0 || next >= lbItems.length) return;
    var vid = document.querySelector('#lbMediaWrap video');
    if (vid) vid.pause();
    lbCurrent = next;
    renderLbSlide();
}

function closeLightbox() {
    var vid = document.querySelector('#lbMediaWrap video');
    if (vid) vid.pause();
    document.getElementById('qmdLightbox').classList.remove('open');
    document.getElementById('lbMediaWrap').innerHTML = '';
    document.body.style.overflow = '';
}

/* Click backdrop to close */
document.getElementById('qmdLightbox').addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
});

/* Keyboard navigation */
document.addEventListener('keydown', function(e) {
    if (!document.getElementById('qmdLightbox').classList.contains('open')) return;
    if (e.key === 'ArrowLeft')  lbNav(-1);
    if (e.key === 'ArrowRight') lbNav(1);
    if (e.key === 'Escape')     closeLightbox();
});
</script>
@endsection