{{-- FILE: resources/views/themes/{active_theme}/cp/analyses/detail.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — ANALYSIS DETAIL  v2.0
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
    --c-green-t:  #26A69A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.cpd-wrap {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    min-height: 80vh;
}
.cpd-wrap * { box-sizing: border-box; }
.cpd-wrap a { text-decoration: none; color: inherit; }

/* ── BREADCRUMB ──────────────────────────────── */
.cpd-breadcrumb {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 12px 24px;
}
.cpd-breadcrumb-inner {
    max-width: 1200px; margin: 0 auto;
    font-size: 12px; color: var(--c-muted);
    display: flex; align-items: center; gap: 7px;
    font-family: var(--f-mono);
}
.cpd-breadcrumb-inner a {
    color: var(--c-lime); font-weight: 600;
    transition: opacity .2s;
}
.cpd-breadcrumb-inner a:hover { opacity: .75; }
.cpd-breadcrumb-inner i { font-size: 11px; color: var(--c-border2); }
.cpd-breadcrumb-inner span { color: var(--c-text); }

/* ── LAYOUT ──────────────────────────────────── */
.cpd-main {
    max-width: 1200px; margin: 0 auto;
    padding: 32px 24px 80px;
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 24px;
}
@media (max-width: 900px) {
    .cpd-main { grid-template-columns: 1fr; padding: 20px 16px 60px; }
    .cpd-sidebar { order: -1; }
}

/* ══ LEFT — MAIN CONTENT ══════════════════════ */
.cpd-content-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
}

/* Hero thumbnail */
.cpd-thumb {
    position: relative; aspect-ratio: 16/6;
    overflow: hidden; flex-shrink: 0;
    background: linear-gradient(135deg, #0d1520, #1a2540);
    display: flex; align-items: center; justify-content: center;
}
.cpd-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .5s ease;
}
.cpd-content-card:hover .cpd-thumb img { transform: scale(1.03); }
.cpd-thumb-icon { font-size: 72px; color: rgba(125,255,0,.15); }
/* Gradient veil on thumbnail */
.cpd-thumb-veil {
    position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(to top, rgba(11,14,17,.85) 0%, transparent 55%);
}
/* Tier badge */
.cpd-tier-badge {
    position: absolute; top: 14px; left: 14px; z-index: 2;
    font-size: 10px; font-weight: 700; letter-spacing: .1em;
    padding: 5px 14px; border-radius: 100px;
    text-transform: uppercase; font-family: var(--f-display);
}
.tier-free     { background: rgba(38,166,154,.9);  color: #fff; }
.tier-pro      { background: rgba(0,184,212,.9);   color: #000; }
.tier-pro_plus { background: rgba(125,255,0,.92);  color: #000; }

/* Title overlay on thumbnail */
.cpd-thumb-title-wrap {
    position: absolute; bottom: 0; left: 0; right: 0;
    padding: 20px 28px; z-index: 2;
}
.cpd-thumb-title {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 36px);
    font-weight: 800; color: #fff; line-height: 1.1;
}

/* Content body */
.cpd-body { padding: 28px 28px; }

/* Tags */
.cpd-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 18px; }
.cpd-tag {
    background: var(--c-panel); border: 1px solid var(--c-border2);
    border-radius: 4px; padding: 3px 10px;
    font-size: 11px; color: var(--c-muted); font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
}

/* Description */
.cpd-desc {
    font-size: 14px; color: var(--c-muted);
    line-height: 1.8; margin-bottom: 28px;
    border-left: 2px solid var(--c-lime);
    padding-left: 16px;
}

/* ── FAQ SECTION ──────────────────────────────── */
.cpd-faq-section { margin-top: 8px; }
.cpd-faq-head {
    display: flex; align-items: center; gap: 10px;
    font-family: var(--f-display); font-size: 18px;
    font-weight: 700; color: #fff; margin-bottom: 16px;
}
.cpd-faq-head::after {
    content: ''; flex: 1; height: 1px; background: var(--c-border);
}

.cpd-faq-item {
    background: var(--c-panel);
    border: 1px solid var(--c-border);
    border-radius: 8px; margin-bottom: 8px;
    overflow: hidden; transition: border-color .2s;
}
.cpd-faq-item.open { border-color: rgba(125,255,0,.2); }

.cpd-faq-q {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; cursor: pointer;
    font-weight: 600; color: var(--c-text);
    font-size: 14px; background: transparent;
    transition: background .2s; user-select: none;
    border: none; width: 100%; text-align: left;
    font-family: var(--f-sans);
}
.cpd-faq-q:hover { background: rgba(255,255,255,.02); color: #fff; }

.cpd-faq-icon {
    width: 22px; height: 22px; border-radius: 5px; flex-shrink: 0;
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.2);
    display: flex; align-items: center; justify-content: center;
    color: var(--c-lime); font-size: 11px;
    transition: transform .3s, background .2s;
}
.cpd-faq-q.open .cpd-faq-icon { transform: rotate(45deg); background: var(--c-lime); color: #000; }

.cpd-faq-a {
    display: none; padding: 0 18px 16px;
    font-size: 13px; color: var(--c-muted); line-height: 1.75;
}
.cpd-faq-a.open { display: block; }

/* ══ RIGHT — SIDEBAR ══════════════════════════ */
.cpd-sidebar { display: flex; flex-direction: column; gap: 16px; }

/* Access card */
.cpd-access-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 22px;
    position: sticky; top: 96px;
    overflow: hidden;
}
/* Top lime accent line */
.cpd-access-card::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .5;
}
.cpd-access-title {
    font-family: var(--f-display); font-size: 17px;
    font-weight: 700; color: #fff; margin-bottom: 6px;
}
.cpd-access-sub {
    font-size: 13px; color: var(--c-muted); line-height: 1.6; margin-bottom: 20px;
}
.cpd-access-sub strong { color: var(--c-text); }

/* Granted state */
.cpd-access-granted { text-align: center; padding: 10px 0 6px; }
.cpd-granted-icon {
    width: 52px; height: 52px; border-radius: 50%;
    background: rgba(38,166,154,.12); border: 1px solid rgba(38,166,154,.3);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px; font-size: 22px; color: #4DB6AC;
}
.cpd-granted-msg { font-size: 13px; color: var(--c-muted); margin-bottom: 18px; line-height: 1.6; }
.cpd-open-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 13px;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 15px; font-weight: 700;
    letter-spacing: .05em; border: none; border-radius: 8px;
    cursor: pointer; transition: all .2s;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.cpd-open-btn:hover {
    background: #8FFF1A; color: #000;
    box-shadow: 0 0 30px rgba(125,255,0,.35);
    transform: translateY(-1px);
}

/* Locked state */
.cpd-locked-wrap { text-align: center; padding: 10px 0 6px; }
.cpd-lock-icon {
    width: 52px; height: 52px; border-radius: 50%;
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.25);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px; font-size: 22px; color: var(--c-lime);
}
.cpd-locked-msg { font-size: 13px; color: var(--c-muted); margin-bottom: 18px; line-height: 1.6; }
.cpd-locked-msg strong { color: var(--c-text); }
.cpd-upgrade-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 13px;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 15px; font-weight: 700;
    letter-spacing: .05em; border: none; border-radius: 8px;
    cursor: pointer; transition: all .2s;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.cpd-upgrade-btn:hover {
    background: #8FFF1A; color: #000;
    box-shadow: 0 0 30px rgba(125,255,0,.35);
    transform: translateY(-1px);
}

/* Info rows */
.cpd-info-rows {
    margin-top: 18px; padding-top: 16px;
    border-top: 1px solid var(--c-border);
}
.cpd-info-row {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid var(--c-border);
    font-size: 12px;
}
.cpd-info-row:last-child { border-bottom: none; }
.cpd-info-row .lbl { color: var(--c-muted); font-weight: 500; }
.cpd-info-row .val {
    font-family: var(--f-mono); font-weight: 600;
    color: var(--c-text); font-size: 12px;
}
.cpd-info-row .val.lime { color: var(--c-lime); }

/* Related card */
.cpd-related-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 18px;
}
.cpd-related-title {
    font-family: var(--f-display); font-size: 14px;
    font-weight: 700; color: #fff;
    margin-bottom: 14px; letter-spacing: .02em;
}
.cpd-related-item {
    display: flex; align-items: center; gap: 12px;
    padding: 9px 0; border-bottom: 1px solid var(--c-border);
    transition: opacity .2s;
}
.cpd-related-item:last-child { border-bottom: none; }
.cpd-related-item:hover { opacity: .75; }

.cpd-related-thumb {
    width: 48px; height: 36px; border-radius: 6px; overflow: hidden; flex-shrink: 0;
    background: linear-gradient(135deg, #0d1520, #1a2540);
    display: flex; align-items: center; justify-content: center;
    color: rgba(125,255,0,.25); font-size: 16px;
}
.cpd-related-thumb img { width: 100%; height: 100%; object-fit: cover; }
.cpd-related-name {
    font-size: 13px; font-weight: 600; color: var(--c-text); line-height: 1.3;
}
.cpd-related-tier { font-size: 10px; font-weight: 700; margin-top: 3px; letter-spacing: .06em; }
.cpd-related-tier.tier-free     { color: #4DB6AC; }
.cpd-related-tier.tier-pro      { color: var(--c-blue); }
.cpd-related-tier.tier-pro_plus { color: var(--c-lime); }

@media (max-width: 768px) {
    .cpd-breadcrumb { padding: 10px 16px; }
    .cpd-body { padding: 20px; }
    .cpd-thumb-title-wrap { padding: 14px 20px; }
}
</style>

<div class="cpd-wrap">

    {{-- ── BREADCRUMB ── --}}
    <div class="cpd-breadcrumb">
        <div class="cpd-breadcrumb-inner">
            <a href="{{ route('cp.analyses.index') }}">Analysis Tools</a>
            <i class="las la-angle-right"></i>
            <span>{{ $analysis->name }}</span>
        </div>
    </div>

    <div class="cpd-main">

        {{-- ══ LEFT — MAIN CONTENT ══ --}}
        <div class="cpd-content-card">

            {{-- Thumbnail with overlaid title --}}
            <div class="cpd-thumb">
                @if($analysis->thumbnail)
                    <img src="{{ $analysis->thumbnail_url }}" alt="{{ $analysis->name }}">
                @else
                    <div class="cpd-thumb-icon"><i class="las la-chart-bar"></i></div>
                @endif
                <div class="cpd-thumb-veil"></div>
                <span class="cpd-tier-badge tier-{{ $analysis->plan_tier }}">
                    {{ $analysis->plan_badge['label'] }}
                </span>
                <div class="cpd-thumb-title-wrap">
                    <h1 class="cpd-thumb-title">{{ $analysis->name }}</h1>
                </div>
            </div>

            {{-- Body --}}
            <div class="cpd-body">

                @if(!empty($analysis->tags))
                <div class="cpd-tags">
                    @foreach($analysis->tags as $tag)
                    <span class="cpd-tag">{{ $tag }}</span>
                    @endforeach
                </div>
                @endif

                @if($analysis->description)
                <div class="cpd-desc">{!! nl2br(e($analysis->description)) !!}</div>
                @elseif($analysis->short_description)
                <div class="cpd-desc">{{ $analysis->short_description }}</div>
                @endif

                {{-- FAQ Section — logic identical to original --}}
                @if(!empty($analysis->faqs))
                <div class="cpd-faq-section">
                    <div class="cpd-faq-head">Frequently Asked Questions</div>
                    @foreach($analysis->faqs as $i => $faq)
                    <div class="cpd-faq-item" id="faqItem{{ $i }}">
                        <button class="cpd-faq-q" onclick="toggleFaq({{ $i }})">
                            {{ $faq['question'] }}
                            <span class="cpd-faq-icon" id="faqIcon{{ $i }}">
                                <i class="las la-plus"></i>
                            </span>
                        </button>
                        <div class="cpd-faq-a" id="faq-a-{{ $i }}">
                            {{ $faq['answer'] }}
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

            </div>
        </div>

        {{-- ══ RIGHT — SIDEBAR ══ --}}
        <div class="cpd-sidebar">

            {{-- Access card --}}
            <div class="cpd-access-card">
                <div class="cpd-access-title">
                    @if($hasAccess) Access This Tool @else Upgrade Required @endif
                </div>
                <div class="cpd-access-sub">
                    @if($hasAccess)
                        You have full access to this analysis tool.
                    @else
                        This tool requires a
                        <strong>{{ $analysis->plan_badge['label'] }}</strong> plan or higher.
                    @endif
                </div>

                @if($hasAccess)
                {{-- Granted --}}
                <div class="cpd-access-granted">
                    <div class="cpd-granted-icon">
                        <i class="las la-check-circle"></i>
                    </div>
                    <p class="cpd-granted-msg">You have full access to this analysis.</p>
                    @if($analysis->route_name)
                    <a href="{{ route($analysis->route_name) }}" class="cpd-open-btn">
                        <i class="las la-external-link-alt"></i> Open Tool
                    </a>
                    @else
                    <button class="cpd-open-btn">
                        <i class="las la-play-circle"></i> Launch Analysis
                    </button>
                    @endif
                </div>
                @else
                {{-- Locked --}}
                <div class="cpd-locked-wrap">
                    <div class="cpd-lock-icon">
                        <i class="las la-lock"></i>
                    </div>
                    <p class="cpd-locked-msg">
                        Subscribe to <strong>{{ $analysis->plan_badge['label'] }}</strong>
                        or higher to unlock this analysis tool.
                    </p>
                    <a href="{{ route('cp.pricing') }}" class="cpd-upgrade-btn">
                        <i class="las la-crown"></i> View Plans &amp; Upgrade
                    </a>
                </div>
                @endif

                {{-- Info rows --}}
                <div class="cpd-info-rows">
                    <div class="cpd-info-row">
                        <span class="lbl">Data Source</span>
                        <span class="val">{{ strtoupper($analysis->data_source) }}</span>
                    </div>
                    <div class="cpd-info-row">
                        <span class="lbl">Timeframe</span>
                        <span class="val">15 MIN</span>
                    </div>
                    <div class="cpd-info-row">
                        <span class="lbl">Plan Required</span>
                        <span class="val lime">{{ $analysis->plan_badge['label'] }}</span>
                    </div>
                </div>
            </div>

            {{-- Related analyses --}}
            @if($related->count())
            <div class="cpd-related-card">
                <div class="cpd-related-title">Related Analyses</div>
                @foreach($related as $r)
                <a href="{{ route('cp.analyses.detail', $r->slug) }}" class="cpd-related-item">
                    <div class="cpd-related-thumb">
                        @if($r->thumbnail)
                            <img src="{{ $r->thumbnail_url }}" alt="{{ $r->name }}">
                        @else
                            <i class="las la-chart-bar"></i>
                        @endif
                    </div>
                    <div>
                        <div class="cpd-related-name">{{ $r->name }}</div>
                        <div class="cpd-related-tier tier-{{ $r->plan_tier }}">
                            {{ $r->plan_badge['label'] }}
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            @endif

        </div>
    </div>

</div>

{{-- ── JS: FAQ toggle — LOGIC IDENTICAL ── --}}
<script>
function toggleFaq(i) {
    var item = document.getElementById('faqItem' + i);
    var q    = item.querySelector('.cpd-faq-q');
    var a    = document.getElementById('faq-a-' + i);
    var open = a.classList.contains('open');

    /* Close all */
    document.querySelectorAll('.cpd-faq-q').forEach(function(el) { el.classList.remove('open'); });
    document.querySelectorAll('.cpd-faq-a').forEach(function(el) { el.classList.remove('open'); });
    document.querySelectorAll('.cpd-faq-item').forEach(function(el) { el.classList.remove('open'); });

    /* Open clicked */
    if (!open) {
        q.classList.add('open');
        a.classList.add('open');
        item.classList.add('open');
    }
}
</script>

@endsection