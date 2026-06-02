{{-- FILE: resources/views/themes/{active_theme}/cp/analyses/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — ANALYSIS INDEX  v2.0
   Dark terminal · Same design system as homepage
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
    --c-green-t:  #26A69A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.cp-wrap {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    min-height: 80vh;
}
.cp-wrap * { box-sizing: border-box; }
.cp-wrap a { text-decoration: none; color: inherit; }

@keyframes cpFadeUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: none; }
}
.cp-anim    { animation: cpFadeUp .55s ease both; }
.cp-anim.d1 { animation-delay: .1s; }
.cp-anim.d2 { animation-delay: .2s; }
.cp-anim.d3 { animation-delay: .3s; }

/* ── SHARED BUTTONS ──────────────────────────── */
.cp-btn-lime {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-lime); color: #000 !important;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    letter-spacing: .05em; padding: 10px 22px; border-radius: 7px;
    border: none; cursor: pointer; transition: all .2s;
    box-shadow: 0 0 18px rgba(125,255,0,.2);
    white-space: nowrap;
}
.cp-btn-lime:hover {
    background: #8FFF1A; color: #000 !important;
    box-shadow: 0 0 28px rgba(125,255,0,.35);
    transform: translateY(-1px);
}
.cp-btn-ghost {
    display: inline-flex; align-items: center; gap: 7px;
    background: transparent; color: var(--c-text);
    font-size: 13px; font-weight: 500;
    padding: 10px 20px; border-radius: 7px;
    border: 1px solid var(--c-border2);
    cursor: pointer; transition: all .2s;
    white-space: nowrap;
}
.cp-btn-ghost:hover { border-color: rgba(125,255,0,.35); color: var(--c-lime); }

/* ══ HERO ════════════════════════════════════ */
.cp-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 72px 24px 56px;
    border-bottom: 1px solid var(--c-border);
}
/* Grid texture */
.cp-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.025) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black, transparent);
    pointer-events: none;
}
/* Glow orb */
.cp-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 55% 65% at 80% 50%, rgba(125,255,0,.06), transparent 70%);
    pointer-events: none;
}
.cp-hero-inner {
    position: relative; z-index: 1;
    max-width: 1200px; margin: 0 auto;
}
.cp-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 16px;
}
.cp-hero-eyebrow::before { content: ''; display: block; width: 20px; height: 1px; background: var(--c-lime); }
.cp-hero-h1 {
    font-family: var(--f-display);
    font-size: clamp(30px, 4.5vw, 52px);
    font-weight: 800; color: #fff;
    line-height: 1.08; letter-spacing: -.02em;
    margin-bottom: 16px;
}
.cp-hero-h1 span { color: var(--c-lime); }
.cp-hero-desc {
    font-size: 15px; color: var(--c-muted);
    line-height: 1.75; max-width: 520px; margin-bottom: 28px;
}
.cp-hero-btns { display: flex; gap: 10px; flex-wrap: wrap; }

/* ── SUBSCRIPTION BANNER ─────────────────────── */
.cp-sub-banner {
    max-width: 1200px; margin: 28px auto 0;
    position: relative; z-index: 1;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    border-radius: 10px; padding: 14px 20px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 14px; flex-wrap: wrap;
}
.cp-sub-banner.active-sub { border-color: rgba(125,255,0,.25); }
.cp-sub-banner-left {
    display: flex; align-items: center; gap: 10px;
    font-size: 13px; color: var(--c-muted);
}
.cp-sub-banner-left i { font-size: 16px; color: var(--c-lime); flex-shrink: 0; }
.cp-sub-badge {
    display: inline-block;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 10px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    padding: 3px 10px; border-radius: 100px;
}
.cp-sub-badge.pro  { background: #00B8D4; color: #000; }
.cp-sub-badge.free { background: var(--c-muted); color: #fff; }

/* ══ FILTER BAR ══════════════════════════════ */
.cp-filter-bar {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 24px;
    position: sticky; top: 92px; z-index: 200;
}
.cp-filter-inner {
    max-width: 1200px; margin: 0 auto;
    display: flex; align-items: center;
    gap: 12px; padding: 12px 0; flex-wrap: wrap;
}
/* Tier tabs */
.cp-tier-tabs { display: flex; gap: 4px; }
.cp-tier-tab {
    padding: 6px 16px; border-radius: 6px;
    border: 1px solid var(--c-border2);
    font-size: 12px; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
    color: var(--c-muted); cursor: pointer;
    background: transparent; font-family: var(--f-sans);
    transition: all .2s;
}
.cp-tier-tab.on {
    background: var(--c-lime-dim);
    border-color: rgba(125,255,0,.3);
    color: var(--c-lime);
}
.cp-tier-tab:hover:not(.on) { color: var(--c-text); border-color: var(--c-border2); }

/* Search */
.cp-search-wrap {
    margin-left: auto;
    display: flex; align-items: stretch;
    background: var(--c-panel);
    border: 1px solid var(--c-border2);
    border-radius: 7px; overflow: hidden;
}
.cp-search-input {
    background: transparent; border: none;
    padding: 8px 14px; font-size: 13px;
    color: var(--c-text); font-family: var(--f-sans);
    outline: none; width: 220px;
}
.cp-search-input::placeholder { color: var(--c-muted); }
.cp-search-btn {
    background: var(--c-lime-dim); border: none;
    padding: 0 13px; color: var(--c-lime);
    cursor: pointer; font-size: 13px;
    border-left: 1px solid var(--c-border2);
    transition: background .2s;
}
.cp-search-btn:hover { background: rgba(125,255,0,.2); }

/* ══ MAIN CONTENT ════════════════════════════ */
.cp-content {
    max-width: 1200px; margin: 0 auto;
    padding: 40px 24px 80px;
}

/* Stats row (top of grid) */
.cp-stats-row {
    display: flex; gap: 12px; margin-bottom: 32px; flex-wrap: wrap;
}
.cp-stat-chip {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 8px; padding: 10px 16px;
    font-size: 13px; color: var(--c-muted);
}
.cp-stat-chip strong { color: var(--c-text); font-weight: 600; }
.cp-stat-chip i { color: var(--c-lime); font-size: 13px; }

/* ── GRID ────────────────────────────────────── */
.cp-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px; margin-bottom: 48px;
}
@media (max-width: 1024px) { .cp-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 580px)  { .cp-grid { grid-template-columns: 1fr; } }

/* ── CARD ────────────────────────────────────── */
.cp-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    display: flex; flex-direction: column;
    transition: border-color .25s, transform .25s, box-shadow .25s;
    position: relative;
}
/* Top green sweep on hover */
.cp-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .25s; z-index: 1;
}
.cp-card:hover {
    border-color: rgba(125,255,0,.18);
    transform: translateY(-3px);
    box-shadow: 0 12px 36px rgba(0,0,0,.4);
}
.cp-card:hover::before { opacity: 1; }

/* Thumbnail */
.cp-card-thumb {
    position: relative; aspect-ratio: 16/9; overflow: hidden;
    background: linear-gradient(135deg, #0d1520, #1a2540);
    flex-shrink: 0;
}
.cp-card-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .45s ease;
}
.cp-card:hover .cp-card-thumb img { transform: scale(1.04); }
.cp-thumb-icon {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 44px; color: rgba(125,255,0,.18);
}

/* Tier badge */
.cp-tier-badge {
    position: absolute; top: 10px; left: 10px; z-index: 2;
    font-size: 9px; font-weight: 700; letter-spacing: .1em;
    padding: 4px 10px; border-radius: 100px; text-transform: uppercase;
    font-family: var(--f-display);
}
.tier-free     { background: rgba(38,166,154,.85); color: #fff; }
.tier-pro      { background: rgba(0,184,212,.85);  color: #000; }
.tier-pro_plus { background: rgba(125,255,0,.9);   color: #000; }

/* Lock overlay */
.cp-lock-overlay {
    position: absolute; inset: 0; z-index: 3;
    background: rgba(11,14,17,.72);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 6px;
}
.cp-lock-overlay i    { font-size: 26px; color: var(--c-lime); }
.cp-lock-overlay span { font-size: 11px; font-weight: 700; color: var(--c-text); letter-spacing: .06em; }

/* Body */
.cp-card-body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; }
.cp-card-title {
    font-family: var(--f-display); font-size: 15px; font-weight: 700;
    color: #fff; line-height: 1.3; margin-bottom: 7px; flex: 1;
}
.cp-card-desc  { font-size: 12.5px; color: var(--c-muted); line-height: 1.65; margin-bottom: 10px; }
.cp-card-tags  { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 10px; }
.cp-tag {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 4px; padding: 2px 8px;
    font-size: 10px; color: var(--c-muted); font-weight: 600;
    letter-spacing: .04em; text-transform: uppercase;
}

/* Card footer */
.cp-card-footer {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px;
    border-top: 1px solid var(--c-border);
    background: rgba(0,0,0,.15); gap: 8px;
}
.cp-data-src {
    display: flex; align-items: center; gap: 5px;
    font-family: var(--f-mono); font-size: 10px;
    font-weight: 600; color: var(--c-muted); letter-spacing: .06em;
}
.cp-data-src i { color: rgba(125,255,0,.4); }

/* Action link */
.cp-card-action {
    display: inline-flex; align-items: center; gap: 5px;
    font-family: var(--f-display); font-size: 12px; font-weight: 700;
    color: var(--c-lime); letter-spacing: .04em;
    transition: gap .2s;
}
.cp-card-action:hover { gap: 9px; }
.cp-card-action.locked {
    background: rgba(125,255,0,.1);
    border: 1px solid rgba(125,255,0,.25);
    color: var(--c-lime);
    padding: 5px 12px; border-radius: 6px;
    font-size: 11px;
}
.cp-card-action.locked:hover { background: rgba(125,255,0,.18); }

/* ── NO RESULTS ─────────────────────────────── */
.cp-no-results {
    grid-column: 1 / -1;
    text-align: center; padding: 72px 20px;
    color: var(--c-muted);
}
.cp-no-results i { font-size: 38px; color: var(--c-border2); display: block; margin-bottom: 14px; }
.cp-no-results p { font-size: 14px; }

/* ── BOTTOM CTA ──────────────────────────────── */
.cp-bottom-cta {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 52px 40px;
    text-align: center; position: relative; overflow: hidden;
    margin-top: 8px;
}
.cp-bottom-cta::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 55% 55% at 50% 50%, rgba(125,255,0,.05), transparent 70%);
    pointer-events: none;
}
.cp-bottom-cta::after {
    content: '';
    position: absolute; top: 0; left: 15%; right: 15%; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .4;
}
.cp-bottom-cta h2 {
    font-family: var(--f-display);
    font-size: clamp(22px, 3vw, 36px);
    font-weight: 800; color: #fff;
    margin-bottom: 10px; position: relative;
}
.cp-bottom-cta h2 span { color: var(--c-lime); }
.cp-bottom-cta p {
    color: var(--c-muted); margin-bottom: 26px;
    font-size: 14px; line-height: 1.7; position: relative;
}

/* Responsive */
@media (max-width: 768px) {
    .cp-hero { padding: 52px 20px 40px; }
    .cp-filter-bar { padding: 0 16px; top: 64px; }
    .cp-search-wrap { margin-left: 0; width: 100%; }
    .cp-search-input { width: 100%; }
    .cp-content { padding: 28px 16px 60px; }
    .cp-bottom-cta { padding: 36px 20px; }
}
</style>

<div class="cp-wrap">

{{-- ══ HERO ══ --}}
<div class="cp-hero">
    <div class="cp-hero-inner">
        <div class="cp-anim">
            <div class="cp-hero-eyebrow">Analysis Tools</div>
            <h1 class="cp-hero-h1">Options Trading <span>Analysis</span> Tools</h1>
            <p class="cp-hero-desc">
                Professional-grade analysis built on live 15-min option &amp; futures data.
                Unlock powerful insights with a Pro or Pro Plus subscription.
            </p>
            <div class="cp-hero-btns">
                <a href="{{ route('cp.pricing') }}" class="cp-btn-lime">
                    <i class="las la-crown"></i> View Plans
                </a>
                @guest
                <a href="{{ route('user.login') }}" class="cp-btn-ghost">
                    <i class="las la-sign-in-alt"></i> Login
                </a>
                @endguest
            </div>
        </div>

        {{-- Subscription status banner --}}
        @if($userSubscription && $userSubscription->isActive())
        <div class="cp-sub-banner active-sub cp-anim d1">
            <div class="cp-sub-banner-left">
                <i class="las la-check-circle"></i>
                You are on the
                <span class="cp-sub-badge">{{ strtoupper($userSubscription->plan->name) }}</span>
                &nbsp;plan — expires {{ $userSubscription->expires_at->format('d M Y') }}
                &nbsp;({{ $userSubscription->days_remaining }} days remaining)
            </div>
            <a href="{{ route('cp.my-subscription') }}" class="cp-btn-lime" style="padding:8px 18px;font-size:12px;">
                My Subscription <i class="las la-arrow-right"></i>
            </a>
        </div>
        @elseif(auth()->check())
        <div class="cp-sub-banner cp-anim d1">
            <div class="cp-sub-banner-left">
                <i class="las la-info-circle"></i>
                You are on the <span class="cp-sub-badge free">FREE</span>
                &nbsp;plan. Upgrade to unlock all analysis tools.
            </div>
            <a href="{{ route('cp.pricing') }}" class="cp-btn-lime" style="padding:8px 18px;font-size:12px;">
                Upgrade Now <i class="las la-arrow-right"></i>
            </a>
        </div>
        @endif
    </div>
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="cp-filter-bar">
    <div class="cp-filter-inner">
        <div class="cp-tier-tabs">
            <button class="cp-tier-tab on" onclick="cpFilter('all',this)">All</button>
            <button class="cp-tier-tab"    onclick="cpFilter('free',this)">Free</button>
            <button class="cp-tier-tab"    onclick="cpFilter('pro',this)">Pro</button>
            <button class="cp-tier-tab"    onclick="cpFilter('pro_plus',this)">Pro Plus</button>
        </div>
        <div class="cp-search-wrap">
            <input class="cp-search-input" type="text" id="cpSearch"
                   placeholder="Search analyses…" oninput="cpApplyFilter()">
            <button class="cp-search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
</div>

{{-- ══ ANALYSES GRID ══ --}}
<div class="cp-content">

    @if($analyses->isEmpty())
    <div style="text-align:center;padding:80px 20px;color:var(--c-muted);">
        <i class="las la-brain" style="font-size:4rem;color:var(--c-border2);display:block;margin-bottom:16px;"></i>
        <h4 style="font-family:var(--f-display);color:var(--c-text);">No analyses available yet</h4>
    </div>
    @else

    {{-- Stats chips --}}
    <div class="cp-stats-row cp-anim">
        <div class="cp-stat-chip"><i class="las la-chart-bar"></i> <strong>{{ $analyses->count() }}</strong> Tools Available</div>
        <div class="cp-stat-chip"><i class="las la-clock"></i> <strong>15 MIN</strong> Data Refresh</div>
        <div class="cp-stat-chip"><i class="las la-database"></i> <strong>Live</strong> Market Feed</div>
    </div>

    <div class="cp-grid" id="cpGrid">
        @foreach($analyses as $a)
        @php
            $tierRank = ['free'=>0,'pro'=>1,'pro_plus'=>2];
            $hasAccess = ($tierRank[$userTier ?? 'free'] ?? 0) >= ($tierRank[$a->plan_tier] ?? 0);
        @endphp
        <div class="cp-card"
             data-tier="{{ $a->plan_tier }}"
             data-title="{{ strtolower($a->name) }}"
             data-tags="{{ strtolower(implode(' ', $a->tags ?? [])) }}">

            <div class="cp-card-thumb">
                @if($a->thumbnail)
                    <img src="{{ $a->thumbnail_url }}" alt="{{ $a->name }}" loading="lazy">
                @else
                    <div class="cp-thumb-icon"><i class="las la-chart-bar"></i></div>
                @endif

                <span class="cp-tier-badge tier-{{ $a->plan_tier }}">
                    {{ $a->plan_badge['label'] }}
                </span>

                @if(!$hasAccess)
                <div class="cp-lock-overlay">
                    <i class="las la-lock"></i>
                    <span>{{ strtoupper($a->plan_tier === 'pro_plus' ? 'Pro Plus' : 'Pro') }} Required</span>
                </div>
                @endif
            </div>

            <div class="cp-card-body">
                <div class="cp-card-title">{{ $a->name }}</div>
                @if($a->short_description)
                <div class="cp-card-desc">{{ Str::limit($a->short_description, 90) }}</div>
                @endif
                @if(!empty($a->tags))
                <div class="cp-card-tags">
                    @foreach(array_slice($a->tags, 0, 3) as $tag)
                    <span class="cp-tag">{{ $tag }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="cp-card-footer">
                <div class="cp-data-src">
                    <i class="las la-database"></i>
                    {{ strtoupper($a->data_source) }}
                </div>

                @if($hasAccess && $a->route_name)
                    <a href="{{ route($a->route_name) }}" class="cp-card-action">
                        Open Tool <i class="las la-arrow-right"></i>
                    </a>
                @elseif($hasAccess)
                    <a href="{{ route('cp.analyses.detail', $a->slug) }}" class="cp-card-action">
                        View Details <i class="las la-arrow-right"></i>
                    </a>
                @else
                    <a href="{{ route('cp.pricing') }}" class="cp-card-action locked">
                        <i class="las la-crown"></i> Upgrade
                    </a>
                @endif
            </div>
        </div>
        @endforeach

        <div class="cp-no-results" id="cpNoResults" style="display:none;">
            <i class="las la-search"></i>
            <p>No analyses match your search.</p>
        </div>
    </div>

    @endif

    {{-- Bottom CTA — only when not subscribed --}}
    @if(!$userSubscription || !$userSubscription->isActive())
    <div class="cp-bottom-cta">
        <h2>Unlock <span>All Analysis Tools</span></h2>
        <p>Subscribe to Pro or Pro Plus and get full access to every analysis module.</p>
        <a href="{{ route('cp.pricing') }}" class="cp-btn-lime">
            <i class="las la-crown"></i> See Plans &amp; Pricing
        </a>
    </div>
    @endif

</div>
</div>

{{-- ── JS: filter + search — LOGIC UNCHANGED ── --}}
<script>
var currentTier = 'all';

function cpFilter(tier, btn) {
    currentTier = tier;
    document.querySelectorAll('.cp-tier-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    cpApplyFilter();
}

function cpApplyFilter() {
    var search  = document.getElementById('cpSearch').value.toLowerCase().trim();
    var cards   = document.querySelectorAll('#cpGrid .cp-card');
    var visible = 0;

    cards.forEach(function(card) {
        var tierOk   = currentTier === 'all' || card.dataset.tier === currentTier;
        var searchOk = !search
            || card.dataset.title.includes(search)
            || (card.dataset.tags || '').includes(search);
        var show = tierOk && searchOk;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    var noRes = document.getElementById('cpNoResults');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}
</script>

@endsection