{{-- FILE: resources/views/themes/{active_theme}/events.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — EVENTS PAGE
   Dark terminal · Matches media design system
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

.qev-wrap {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    min-height: 80vh;
}
.qev-wrap * { box-sizing: border-box; }

@keyframes qevFadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.qev-anim    { animation: qevFadeUp .55s ease both; }
.qev-anim.d1 { animation-delay: .1s; }
.qev-anim.d2 { animation-delay: .2s; }
.qev-anim.d3 { animation-delay: .3s; }
.qev-anim.d4 { animation-delay: .4s; }

@keyframes pulseDot { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(.55);opacity:.35} }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── BREADCRUMB ────────────────────────────── */
.qev-breadcrumb {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 12px 24px;
}
.qev-breadcrumb-inner {
    max-width: 1200px; margin: 0 auto;
    font-size: 12px; color: var(--c-muted);
    display: flex; align-items: center; gap: 7px;
    font-family: var(--f-mono);
}
.qev-breadcrumb-inner a { color: var(--c-lime); font-weight: 600; transition: opacity .2s; }
.qev-breadcrumb-inner a:hover { opacity: .75; }
.qev-breadcrumb-inner i { font-size: 10px; color: var(--c-border2); }

/* ── HERO ──────────────────────────────────── */
.qev-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 80px 24px 64px;
    border-bottom: 1px solid var(--c-border);
    text-align: center;
}
.qev-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.025) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black, transparent);
    pointer-events: none;
}
.qev-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 50% 60% at 50% 50%, rgba(125,255,0,.05), transparent 70%);
    pointer-events: none;
}
.qev-hero-inner { position: relative; z-index: 1; max-width: 700px; margin: 0 auto; }

.qev-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 16px;
    font-family: var(--f-mono);
}
.qev-hero-eyebrow::before,
.qev-hero-eyebrow::after { content: ''; display: block; width: 20px; height: 1px; background: var(--c-lime); }

.qev-hero-dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--c-lime);
    animation: pulseDot 1.4s ease infinite; flex-shrink: 0;
}

.qev-hero h1 {
    font-family: var(--f-display);
    font-size: clamp(32px, 5vw, 54px);
    font-weight: 800; color: #fff;
    line-height: 1.08; letter-spacing: -.02em;
    margin-bottom: 16px;
}
.qev-hero h1 span { color: var(--c-lime); }
.qev-hero p {
    font-size: 15px; color: var(--c-muted); line-height: 1.75;
    max-width: 560px; margin: 0 auto;
}

/* ── FILTER / TAB BAR ──────────────────────── */
.qev-filter-bar-wrap {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 0 24px;
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.qev-tabs-row {
    display: flex; gap: 0;
    max-width: 1200px; margin: 0 auto;
    border-bottom: 1px solid var(--c-border);
    overflow-x: auto; scrollbar-width: none;
}
.qev-tabs-row::-webkit-scrollbar { display: none; }
.qev-tab {
    padding: 16px 22px;
    font-size: 13px; font-weight: 600; color: var(--c-muted);
    cursor: pointer; border: none; background: none;
    border-bottom: 2px solid transparent;
    transition: all .2s; font-family: var(--f-mono);
    white-space: nowrap;
}
.qev-tab.on { color: var(--c-lime); border-bottom-color: var(--c-lime); }
.qev-tab:hover:not(.on) { color: var(--c-text); }

.qev-filter-row {
    display: flex; align-items: center; gap: 12px;
    max-width: 1200px; margin: 0 auto;
    padding: 10px 0;
    flex-wrap: wrap;
}
.qev-filter-group { display: flex; flex-direction: column; gap: 3px; }
.qev-filter-label {
    font-size: 10px; color: var(--c-muted); font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    font-family: var(--f-mono);
}
.qev-filter-select {
    border: 1px solid var(--c-border2);
    border-radius: 6px; padding: 7px 28px 7px 10px;
    font-size: 12px; color: var(--c-text);
    background: var(--c-panel) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance: none; cursor: pointer;
    font-family: var(--f-mono); outline: none; min-width: 100px;
    transition: border-color .2s;
}
.qev-filter-select:focus { border-color: rgba(125,255,0,.35); }

.qev-search-wrap {
    display: flex; overflow: hidden;
    border: 1px solid var(--c-border2); border-radius: 6px;
    margin-left: auto;
    transition: border-color .2s;
}
.qev-search-wrap:focus-within { border-color: rgba(125,255,0,.35); }
.qev-search-input {
    border: none; padding: 8px 14px;
    font-size: 12px; color: var(--c-text);
    background: var(--c-panel); outline: none; width: 200px;
    font-family: var(--f-mono);
}
.qev-search-input::placeholder { color: var(--c-muted); }
.qev-search-btn {
    background: var(--c-lime-dim); border: none;
    padding: 0 14px; color: var(--c-lime); cursor: pointer;
    display: flex; align-items: center; font-size: 13px;
    transition: background .2s;
}
.qev-search-btn:hover { background: rgba(125,255,0,.2); }

@media(max-width:768px){
    .qev-search-wrap { margin-left: 0; width: 100%; }
    .qev-search-input { width: 100%; }
}

/* ── CONTENT ───────────────────────────────── */
.qev-content {
    max-width: 1200px; margin: 0 auto;
    padding: 40px 24px 80px;
    min-height: 60vh;
}

/* ── SECTION HEAD ──────────────────────────── */
.qev-section-head {
    display: flex; align-items: center; gap: 14px; margin-bottom: 24px;
}
.qev-section-head h2 {
    font-family: var(--f-display); font-size: 20px; font-weight: 700;
    color: #fff; margin: 0; white-space: nowrap;
}
.qev-section-head::after {
    content: ''; flex: 1; height: 1px;
    background: linear-gradient(90deg, rgba(125,255,0,.4) 0%, transparent 100%);
}

/* ── TAB PANELS ────────────────────────────── */
.qev-tab-panel { display: none; }
.qev-tab-panel.on { display: block; animation: qevFadeUp .4s ease both; }

/* ── FEATURED CARD ─────────────────────────── */
.qev-featured {
    display: grid; grid-template-columns: 1fr 360px;
    border-radius: 12px; overflow: hidden;
    border: 1px solid rgba(125,255,0,.15);
    background: var(--c-surface);
    margin-bottom: 40px;
    position: relative;
}
.qev-featured::before {
    content: '';
    position: absolute; top: 0; left: 24px; right: 24px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
}
.qev-feat-body {
    padding: 40px 44px; display: flex; flex-direction: column;
    justify-content: center; position: relative; z-index: 1;
}
.qev-feat-badge {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.25);
    border-radius: 30px; padding: 5px 14px; margin-bottom: 18px;
    font-size: 11px; font-weight: 700; color: var(--c-lime);
    width: fit-content; font-family: var(--f-mono); letter-spacing: .08em;
    text-transform: uppercase;
}
.qev-feat-body h3 {
    font-family: var(--f-display); font-size: clamp(22px,3vw,32px);
    font-weight: 800; color: #fff; margin: 0 0 12px; line-height: 1.1;
}
.qev-feat-body > p {
    font-size: 14px; color: var(--c-muted); line-height: 1.75;
    margin: 0 0 24px; max-width: 480px;
}
.qev-feat-meta { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 28px; }
.qev-feat-meta-item {
    display: flex; align-items: center; gap: 7px;
    font-size: 13px; color: var(--c-text); font-family: var(--f-mono);
}
.qev-feat-meta-item i { color: var(--c-lime); }
.qev-feat-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-lime); color: #000; font-weight: 700;
    padding: 13px 28px; border-radius: 8px; font-size: 13px;
    font-family: var(--f-display); letter-spacing: .06em;
    transition: all .2s; text-decoration: none;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.qev-feat-btn:hover { background: #8FFF1A; color: #000; box-shadow: 0 0 30px rgba(125,255,0,.35); transform: translateY(-1px); }
.qev-feat-img {
    position: relative; overflow: hidden; min-height: 280px;
    background: var(--c-panel);
}
.qev-feat-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.qev-feat-price-tag {
    position: absolute; top: 16px; right: 16px;
    background: rgba(11,14,17,.82); backdrop-filter: blur(8px);
    border: 1px solid rgba(125,255,0,.2); border-radius: 10px;
    padding: 10px 16px; text-align: center;
}
.qev-feat-price-tag .price {
    font-family: var(--f-display); font-size: 22px; font-weight: 800; color: var(--c-lime);
}
.qev-feat-price-tag .orig { font-size: 12px; text-decoration: line-through; color: var(--c-muted); }
.qev-feat-price-tag .disc { font-size: 11px; color: #81c784; font-weight: 700; font-family: var(--f-mono); }
@media(max-width:900px){
    .qev-featured { grid-template-columns: 1fr; }
    .qev-feat-img { height: 220px; min-height: unset; }
    .qev-feat-body { padding: 28px 24px; }
}

/* ── EVENT GRID ────────────────────────────── */
.qev-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px; margin-bottom: 40px;
}
@media(max-width:1050px){ .qev-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:580px)  { .qev-grid { grid-template-columns: 1fr; } }

/* ── EVENT CARD ────────────────────────────── */
.qev-card {
    position: relative; border-radius: 10px; overflow: hidden;
    background: var(--c-surface); border: 1px solid var(--c-border);
    display: flex; flex-direction: column;
    transition: border-color .25s, transform .25s, box-shadow .25s;
}
.qev-card::before {
    content: '';
    position: absolute; top: 0; left: 12px; right: 12px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .3s; z-index: 4;
}
.qev-card:hover {
    border-color: rgba(125,255,0,.25);
    transform: translateY(-4px);
    box-shadow: 0 16px 40px rgba(0,0,0,.5), 0 0 0 1px rgba(125,255,0,.12);
}
.qev-card:hover::before { opacity: 1; }

.qev-card-thumb {
    position: relative; aspect-ratio: 16/9; overflow: hidden;
    background: var(--c-panel);
}
.qev-card-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: transform .4s;
}
.qev-card:hover .qev-card-thumb img { transform: scale(1.06); }

/* Type badge */
.qev-card-badge {
    position: absolute; top: 10px; left: 10px;
    background: rgba(11,14,17,.75); backdrop-filter: blur(6px);
    border: 1px solid var(--c-border2);
    border-radius: 6px; padding: 4px 10px;
    font-size: 10px; font-weight: 700;
    color: var(--c-text); font-family: var(--f-mono);
    letter-spacing: .06em; text-transform: uppercase; z-index: 2;
}
.qev-card-badge.symposium  { border-color: rgba(198,40,40,.4);  color: #ef9a9a; }
.qev-card-badge.workshop   { border-color: rgba(230,81,0,.4);   color: #ffcc80; }
.qev-card-badge.seminar    { border-color: rgba(0,105,92,.4);   color: #80cbc4; }
.qev-card-badge.bootcamp   { border-color: rgba(55,71,79,.5);   color: #b0bec5; }
.qev-card-badge.conference { border-color: rgba(69,39,160,.4);  color: #ce93d8; }

/* Seats badge */
.qev-seats-badge {
    position: absolute; top: 10px; right: 10px;
    background: rgba(11,14,17,.75); backdrop-filter: blur(6px);
    border: 1px solid var(--c-border2); border-radius: 6px;
    padding: 4px 10px; font-size: 11px; color: var(--c-text);
    font-weight: 600; font-family: var(--f-mono);
    display: flex; align-items: center; gap: 5px; z-index: 2;
}
.qev-seats-badge.low { color: #ef9a9a; border-color: rgba(239,154,154,.3); }

/* Price overlay strip */
.qev-card-price-ov {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(11,14,17,.92), transparent);
    padding: 24px 12px 10px;
    display: flex; align-items: flex-end; justify-content: space-between;
}
.qev-price-txt {
    font-family: var(--f-display); font-size: 15px;
    font-weight: 700; color: var(--c-lime);
}
.qev-price-txt .strike { text-decoration: line-through; color: var(--c-muted); font-size: 12px; margin: 0 4px; font-weight: 400; }
.qev-price-txt .disc   { font-size: 11px; color: #a5d6a7; font-family: var(--f-mono); }
.qev-view-lnk {
    font-size: 12px; color: var(--c-lime); font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px;
    white-space: nowrap; transition: gap .2s; text-decoration: none;
    font-family: var(--f-mono);
}
.qev-view-lnk:hover { gap: 7px; }

/* Card body */
.qev-card-body { padding: 16px 18px; flex: 1; display: flex; flex-direction: column; }

.qev-card-date-strip {
    display: flex; align-items: center; gap: 8px;
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.15);
    border-radius: 6px; padding: 5px 12px; margin-bottom: 12px; width: fit-content;
}
.qev-card-date-strip i { color: var(--c-lime); font-size: 11px; }
.qev-card-date-strip span { font-size: 12px; font-weight: 700; color: var(--c-lime); font-family: var(--f-mono); }

.qev-card-title {
    font-family: var(--f-display); font-size: 16px; font-weight: 700;
    color: #fff; margin-bottom: 10px; line-height: 1.3; flex: 1;
}
.qev-card-desc { font-size: 12.5px; color: var(--c-muted); line-height: 1.65; margin-bottom: 12px; }

.qev-card-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.qev-card-tag {
    font-size: 11px; padding: 3px 9px; border-radius: 4px;
    font-weight: 600; background: var(--c-panel);
    color: var(--c-muted); border: 1px solid var(--c-border2);
    font-family: var(--f-mono);
}

.qev-card-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 10px; }
.qev-card-meta-row { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--c-muted); }
.qev-card-meta-row i { color: var(--c-lime); font-size: 11px; width: 13px; text-align: center; }
.qev-card-meta-row .mv { color: var(--c-text); font-weight: 600; font-family: var(--f-mono); }

/* Card footer */
.qev-card-footer {
    padding: 11px 18px;
    border-top: 1px solid var(--c-border);
    background: rgba(0,0,0,.15);
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.qev-footer-price {
    font-family: var(--f-display); font-size: 18px; font-weight: 700; color: var(--c-lime);
}
.qev-footer-price .orig { text-decoration: line-through; color: var(--c-muted); font-size: 12px; margin-right: 3px; font-weight: 400; }
.qev-footer-price .pct  { font-size: 11px; color: #a5d6a7; font-weight: 700; margin-left: 4px; font-family: var(--f-mono); }

.qev-register-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--c-lime); color: #000; font-weight: 700; font-size: 12px;
    padding: 8px 18px; border-radius: 7px;
    transition: all .2s; font-family: var(--f-display);
    white-space: nowrap; text-decoration: none;
    box-shadow: 0 0 14px rgba(125,255,0,.15);
}
.qev-register-btn:hover { background: #8FFF1A; color: #000; box-shadow: 0 0 22px rgba(125,255,0,.3); }
.qev-register-btn.past { background: var(--c-panel); color: var(--c-muted); border: 1px solid var(--c-border2); box-shadow: none; }

/* ── COUNTDOWN ─────────────────────────────── */
.qev-countdown {
    background: rgba(0,0,0,.2);
    border-top: 1px solid var(--c-border);
    padding: 8px 18px; display: flex; align-items: center; gap: 10px;
}
.qev-countdown-label { font-size: 11px; color: var(--c-muted); font-weight: 600; flex-shrink: 0; font-family: var(--f-mono); }
.qev-countdown-boxes { display: flex; gap: 6px; }
.qev-countdown-unit {
    display: flex; flex-direction: column; align-items: center;
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.2);
    border-radius: 5px; padding: 4px 8px; min-width: 36px;
}
.qev-countdown-num { font-family: var(--f-display); font-size: 16px; font-weight: 800; color: var(--c-lime); line-height: 1; }
.qev-countdown-sub { font-size: 9px; color: var(--c-muted); letter-spacing: .05em; font-family: var(--f-mono); }

/* ── EMPTY STATE ───────────────────────────── */
.qev-empty { text-align: center; padding: 60px 20px; color: var(--c-muted); }
.qev-empty i { font-size: 44px; color: var(--c-border2); display: block; margin-bottom: 14px; }
.qev-empty p { font-size: 14px; font-family: var(--f-mono); }

.qev-no-results { display: none; text-align: center; padding: 60px 20px; color: var(--c-muted); }
.qev-no-results i { font-size: 38px; color: var(--c-border2); display: block; margin-bottom: 12px; }
.qev-no-results p { font-size: 14px; font-family: var(--f-mono); }

/* ── CTA STRIP ─────────────────────────────── */
.qev-cta-strip {
    background: var(--c-surface);
    border: 1px solid rgba(125,255,0,.12);
    border-radius: 12px;
    padding: 32px 36px; margin-top: 20px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 24px; flex-wrap: wrap;
    position: relative; overflow: hidden;
}
.qev-cta-strip::before {
    content: '';
    position: absolute; top: 0; left: 24px; right: 24px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
}
.qev-cta-strip h3 {
    font-family: var(--f-display); font-size: 20px; font-weight: 700;
    color: #fff; margin: 0 0 6px;
}
.qev-cta-strip p { font-size: 13px; color: var(--c-muted); margin: 0; line-height: 1.65; }
.qev-cta-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-lime); color: #000; font-weight: 700;
    padding: 12px 26px; border-radius: 8px; font-size: 13px;
    font-family: var(--f-display); letter-spacing: .05em;
    transition: all .2s; white-space: nowrap; text-decoration: none;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.qev-cta-btn:hover { background: #8FFF1A; box-shadow: 0 0 30px rgba(125,255,0,.35); transform: translateY(-1px); color: #000; }
</style>

<div class="qev-wrap">

{{-- ══════════════════════════════════════════════════════════
     BREADCRUMB
══════════════════════════════════════════════════════════ --}}
<div class="qev-breadcrumb">
    <div class="qev-breadcrumb-inner">
        <a href="{{ route('home') }}">Home</a>
        <i class="las la-angle-right"></i>
        <span>Events</span>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     HERO — fully dynamic from EventPageCms
══════════════════════════════════════════════════════════ --}}
<div class="qev-hero qev-anim">
    <div class="qev-hero-inner">

        <div class="qev-hero-eyebrow">
            <span class="qev-hero-dot"></span>
            {{ $eventHero['eyebrow'] }}
        </div>

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
     STICKY FILTER / TAB BAR
══════════════════════════════════════════════════════════ --}}
<div class="qev-filter-bar-wrap">
    <div class="qev-tabs-row">
        <button class="qev-tab on" onclick="evTab(0,this)">All Events</button>
        <button class="qev-tab"    onclick="evTab(1,this)">Upcoming</button>
        <button class="qev-tab"    onclick="evTab(2,this)">Past Events</button>
    </div>
    <div class="qev-filter-row">

        <div class="qev-filter-group">
            <span class="qev-filter-label">Price</span>
            <select class="qev-filter-select" id="fType" onchange="evFilter()">
                <option value="">All</option>
                <option value="free">Free</option>
                <option value="paid">Paid</option>
            </select>
        </div>

        <div class="qev-filter-group">
            <span class="qev-filter-label">City</span>
            <select class="qev-filter-select" id="fCity" onchange="evFilter()">
                <option value="">All Cities</option>
                @foreach($citiesMap as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

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
        <div class="qev-section-head qev-anim"><h2>Featured Event</h2></div>
        <div class="qev-featured qev-anim">
            <div class="qev-feat-body">
                <div class="qev-feat-badge">
                    <span class="qev-hero-dot"></span> Registrations Open
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
                <p>No upcoming events at the moment.</p>
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
                <i class="fas fa-history"></i>
                <p>No past events yet.</p>
            </div>
        @else
        <div class="qev-grid" id="evPastGrid">
            @foreach($pastEvents as $ev)
                @include($activeTemplate.'partials.event-card', ['ev' => $ev, 'isPast' => true])
            @endforeach
        </div>
        @endif

        <div class="qev-no-results" id="evNoResults">
            <i class="fas fa-calendar-times"></i>
            <p>No events found matching your filters.</p>
        </div>
    </div>

    {{-- ── TAB 1: UPCOMING ── --}}
    <div class="qev-tab-panel" id="evPanel1">
        <div class="qev-section-head"><h2>Upcoming Events</h2></div>
        @if($upcomingEvents->isEmpty())
            <div class="qev-empty">
                <i class="fas fa-calendar-times"></i>
                <p>No upcoming events.</p>
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
                <i class="fas fa-history"></i>
                <p>No past events yet.</p>
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
    ══════════════════════════════════════════════════════ --}}
    @if(!empty($eventCta['title']))
    <div class="qev-cta-strip qev-anim d4">
        <div>
            <h3>{{ $eventCta['title'] }}</h3>
            @if(!empty($eventCta['desc']))
                <p>{{ $eventCta['desc'] }}</p>
            @endif
        </div>
        <a href="{{ $eventCta['btn_url'] ?? '#' }}" class="qev-cta-btn">
            {{ $eventCta['btn_label'] ?? 'Learn More' }}
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    @endif

</div>{{-- /.qev-content --}}

</div>{{-- /.qev-wrap --}}

<script>
/* ── TAB SWITCH — logic unchanged ── */
function evTab(idx, btn) {
    document.querySelectorAll('.qev-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qev-tab-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === idx);
    });
}

/* ── FILTER — logic unchanged ── */
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
            if (type   && (card.dataset.type  || '') !== type)                               ok = false;
            if (city   && (card.dataset.city  || '') !== city)                               ok = false;
            if (search && (card.dataset.title || '').toLowerCase().indexOf(search) === -1)   ok = false;
            card.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
    });

    var noRes = document.getElementById('evNoResults');
    if (noRes) noRes.style.display = (visible === 0) ? 'block' : 'none';
}

/* ── COUNTDOWN — logic unchanged ── */
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