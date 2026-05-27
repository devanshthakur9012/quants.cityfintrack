{{-- FILE: resources/views/themes/{active_theme}/media.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
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
.md-anim    { animation: mdFadeUp .65s ease both; }
.md-anim.d1 { animation-delay: .1s; }
.md-anim.d2 { animation-delay: .2s; }
.md-anim.d3 { animation-delay: .3s; }
.md-anim.d4 { animation-delay: .4s; }

@keyframes pulseDot {
    0%,100% { transform: scale(1);  opacity: 1; }
    50%     { transform: scale(.6); opacity: .4; }
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── HERO ── */
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
    line-height: 1.75; max-width: 560px; margin: 0;
}
@media(max-width:768px){
    .qmd-hero { padding: 40px 20px 36px; }
    .qmd-hero-inner { flex-direction: column; }
}

/* ── CATEGORY TABS ── */
.qmd-cat-tabs-wrap {
    background: #fff;
    border-bottom: 1px solid var(--bdr);
    padding: 0 48px;
    overflow-x: auto;
    scrollbar-width: none;
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 2px 10px rgba(0,0,0,.06);
}
.qmd-cat-tabs-wrap::-webkit-scrollbar { display: none; }
.qmd-cat-tabs {
    display: flex; gap: 0;
    border-bottom: 2px solid #f0f0f0;
    min-width: max-content;
}
.qmd-cat-tab {
    padding: 16px 24px;
    font-size: 14px; font-weight: 600; color: #888;
    cursor: pointer; border: none; background: none;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    transition: all .2s; font-family: 'Exo 2', sans-serif;
    white-space: nowrap; display: flex; align-items: center; gap: 7px;
}
.qmd-cat-tab.active { color: var(--gold); border-bottom-color: var(--gold); }
.qmd-cat-tab:hover:not(.active) { color: #333; }
.qmd-cat-tab .cnt {
    background: #f0f0f0; color: #888;
    font-size: 11px; font-weight: 700;
    padding: 1px 7px; border-radius: 20px;
    transition: all .2s;
}
.qmd-cat-tab.active .cnt { background: rgba(245,166,35,.2); color: var(--gold); }
@media(max-width:768px){ .qmd-cat-tabs-wrap { padding: 0 16px; } }

/* ── CONTENT ── */
.qmd-content {
    background: var(--bg-page);
    padding: 36px 48px 72px;
    min-height: 60vh;
    max-width: 1400px;
    margin: 0 auto;
}
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

/* ── GALLERY PANELS ── */
.qmd-gallery-panel { display: none; }
.qmd-gallery-panel.active { display: block; animation: mdFadeUp .4s ease both; }

.qmd-gallery-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 32px;
}
@media(max-width:1200px) { .qmd-gallery-grid { grid-template-columns: repeat(3,1fr); } }
@media(max-width:768px)  { .qmd-gallery-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px)  { .qmd-gallery-grid { grid-template-columns: 1fr; } }

/* ── GALLERY ITEM ── */
.qmd-gallery-item {
    position: relative; border-radius: 12px; overflow: hidden;
    aspect-ratio: 4/3; background: #1a1a2e; cursor: pointer;
    box-shadow: 0 4px 18px rgba(0,0,0,.10);
    transition: transform .28s, box-shadow .28s;
}
.qmd-gallery-item:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 16px 40px rgba(0,0,0,.18);
}
.qmd-gallery-item img,
.qmd-gallery-item video {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .4s; pointer-events: none;
}
.qmd-gallery-item:hover img,
.qmd-gallery-item:hover video { transform: scale(1.06); }

.qmd-vid-badge {
    position: absolute; top: 10px; left: 10px;
    background: rgba(0,0,0,.55); backdrop-filter: blur(4px);
    border-radius: 6px; padding: 4px 10px;
    font-size: 11px; font-weight: 700; color: #fff;
    display: flex; align-items: center; gap: 5px;
    pointer-events: none; z-index: 2;
}
.qmd-vid-badge i { color: var(--gold); }

.qmd-item-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(13,27,42,.92) 0%, rgba(13,27,42,.35) 55%, transparent 100%);
    opacity: 0; transition: opacity .3s;
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 16px; z-index: 3;
}
.qmd-gallery-item:hover .qmd-item-overlay { opacity: 1; }
.qmd-overlay-title {
    font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700;
    color: #fff; line-height: 1.3; margin-bottom: 5px;
}
.qmd-overlay-desc {
    font-size: 12px; color: rgba(255,255,255,.72); line-height: 1.55;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* ── LOAD MORE ── */
.qmd-loadmore-wrap { text-align: center; margin: 8px 0 44px; }
.qmd-loadmore-btn {
    display: inline-flex; align-items: center; gap: 9px;
    padding: 13px 36px; border-radius: 10px;
    border: 2px solid var(--gold); background: transparent;
    color: var(--gold); font-weight: 700; font-size: 14px;
    font-family: 'Rajdhani', sans-serif; letter-spacing: .04em;
    cursor: pointer; transition: all .22s;
}
.qmd-loadmore-btn:hover { background: var(--gold); color: #000; }
.qmd-loadmore-btn.loading { opacity: .65; pointer-events: none; }
.qmd-loadmore-btn .lb-spinner {
    width: 16px; height: 16px; border: 2px solid currentColor;
    border-top-color: transparent; border-radius: 50%;
    animation: spin .7s linear infinite; display: none; flex-shrink: 0;
}
.qmd-loadmore-btn.loading .lb-spinner { display: block; }
.qmd-loadmore-btn.loading .lb-icon    { display: none; }
.qmd-remaining { font-weight: 400; opacity: .7; font-size: 13px; }

/* ── LIGHTBOX ── */
.qmd-lightbox {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.93); backdrop-filter: blur(8px);
    align-items: center; justify-content: center; padding: 20px;
}
.qmd-lightbox.open { display: flex; animation: mdFadeUp .25s ease both; }
.qmd-lb-inner {
    position: relative; max-width: 960px; width: 100%;
    display: flex; flex-direction: column; align-items: center;
}
.qmd-lb-media-wrap {
    width: 100%; max-height: 72vh;
    display: flex; align-items: center; justify-content: center;
}
.qmd-lb-media-wrap img,
.qmd-lb-media-wrap video {
    max-width: 100%; max-height: 72vh; object-fit: contain;
    border-radius: 10px; display: block;
}
.qmd-lb-info { margin-top: 18px; text-align: center; }
.qmd-lb-title   { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: #fff; margin: 0 0 6px; }
.qmd-lb-desc    { font-size: 13px; color: rgba(255,255,255,.6); margin: 0; }
.qmd-lb-counter { font-size: 12px; color: rgba(255,255,255,.4); margin-top: 8px; letter-spacing: .05em; }
.qmd-lb-close {
    position: absolute; top: -16px; right: -16px;
    width: 40px; height: 40px; border-radius: 50%;
    background: #fff; border: none; cursor: pointer;
    font-size: 20px; color: #333; box-shadow: 0 4px 16px rgba(0,0,0,.4);
    transition: all .2s; display: flex; align-items: center; justify-content: center;
}
.qmd-lb-close:hover { background: var(--gold); color: #000; }
.qmd-lb-nav {
    position: absolute; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
    color: #fff; width: 46px; height: 46px; border-radius: 50%;
    font-size: 18px; cursor: pointer; transition: all .2s;
    display: flex; align-items: center; justify-content: center;
}
.qmd-lb-nav:hover { background: var(--gold); border-color: var(--gold); color: #000; }
.qmd-lb-prev { left: -62px; }
.qmd-lb-next { right: -62px; }
@media(max-width:860px) {
    .qmd-lb-prev { left: 0; bottom: -58px; top: auto; transform: none; }
    .qmd-lb-next { right: 0; bottom: -58px; top: auto; transform: none; }
}

/* ── CTA STRIP ── */
.qmd-cta-strip {
    background: linear-gradient(90deg, #0D1B2A, #162844);
    border: 1px solid rgba(245,166,35,.2); border-radius: 14px;
    padding: 32px 40px; margin-top: 16px;
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
    transition: background .2s; white-space: nowrap; text-decoration: none;
}
.qmd-cta-btn:hover { background: #d4890e; color: #000; }

/* ── EMPTY STATE ── */
.qmd-empty-state { text-align: center; padding: 80px 20px; color: #aaa; }
.qmd-empty-state i { font-size: 52px; color: #ddd; display: block; margin-bottom: 16px; }
.qmd-empty-state p { font-size: 15px; margin: 0; }
</style>

<div style="font-family:'Exo 2',sans-serif; background:#f4f6fb;">

{{-- ══════════════════════════════════════════════════════════
     HERO — fully dynamic from MediaPageCms
══════════════════════════════════════════════════════════ --}}
<div class="qmd-hero md-anim">
    <div class="qmd-hero-inner">
        <div class="qmd-hero-text text-center w-100">

            {{-- Eyebrow pill --}}
            <div class="qmd-hero-eyebrow">
                <span class="qmd-hero-dot"></span>
                {{ $mediaCms->hero_eyebrow ?? 'Press, Media & Recognition' }}
            </div>

            {{-- Title: split so the "highlight" portion renders in gold --}}
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
            <div class="d-flex justify-content-center">
                <p>{{ $mediaCms->hero_subtitle ?? 'TV interviews, podcast appearances, press features and webinar recordings — follow CityQuants across India\'s top financial media channels and trading publications.' }}</p>
            </div>

        </div>
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
        <div class="qmd-empty-state md-anim">
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
            <div class="qmd-section-head md-anim">
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
                        <div class="qmd-vid-badge"><i class="fas fa-play"></i> Video</div>
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
    <div class="qmd-cta-strip md-anim d4">
        <div>
            <h3>{{ $mediaCms->cta_title ?? 'Press &amp; Media Enquiries' }}</h3>
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

</div>{{-- /wrapper --}}

<script>
/* ══════════════════════════════════════════════════════════
   CATEGORY TAB SWITCH
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
   LOAD MORE
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
            '<div class="qmd-vid-badge"><i class="fas fa-play"></i> Video</div>' +
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
   LIGHTBOX
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
            'style="max-width:100%;max-height:72vh;border-radius:10px;"></video>';
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