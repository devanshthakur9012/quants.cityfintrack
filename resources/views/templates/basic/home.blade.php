@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Grotesk:wght@300;400;500;600;700&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* =============================================
   CITYQUANTS — MODERN DARK TRADING UI
   Inspired by TradingView / Bloomberg Terminal
   v2.0 — Production Grade
============================================= */

/* ── RESET & BASE ──────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    /* Core palette */
    --c-bg:       #0B0E11;
    --c-surface:  #131722;
    --c-panel:    #1C2030;
    --c-border:   rgba(255,255,255,.06);
    --c-border2:  rgba(255,255,255,.1);

    /* Accent */
    --c-green:    #26A69A;
    --c-lime:     #7DFF00;
    --c-lime-dim: rgba(125,255,0,.12);
    --c-lime-glow:rgba(125,255,0,.06);
    --c-blue:     #00B8D4;
    --c-red:      #EF5350;

    /* Text */
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.04);

    /* Shadows */
    --shadow-sm:  0 1px 3px rgba(0,0,0,.4);
    --shadow-md:  0 4px 16px rgba(0,0,0,.5);
    --shadow-lg:  0 12px 40px rgba(0,0,0,.6);

    /* Fonts */
    --f-sans:    'DM Sans', system-ui, sans-serif;
    --f-display: 'Syne', sans-serif;
    --f-mono:    'Space Grotesk', monospace;
}

.cq-root {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    font-size: 14px;
    line-height: 1.6;
    display: block;
    overflow-x: hidden;
}
.cq-root *, .cq-root *::before, .cq-root *::after { box-sizing: border-box; }
.cq-root a { text-decoration: none; color: inherit; }
.cq-root button { cursor: pointer; font-family: inherit; }
.cq-root img { max-width: 100%; display: block; }

/* ── UTILITY CLASSES ───────────────────────── */
.cq-container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
.cq-section    { padding: 96px 0; position: relative; }

.cq-sec-label {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 14px;
}
.cq-sec-label::before {
    content: ''; display: block; width: 20px; height: 1px;
    background: var(--c-lime);
}
.cq-sec-title {
    font-family: var(--f-display);
    font-size: clamp(28px, 3.5vw, 42px);
    font-weight: 700; color: #FFFFFF;
    line-height: 1.15; letter-spacing: -.01em;
}
.cq-sec-title span { color: var(--c-lime); }
.cq-sec-sub {
    font-size: 15px; color: var(--c-muted);
    max-width: 520px; line-height: 1.75; margin-top: 14px;
}

/* ── ANIMATIONS ────────────────────────────── */
@keyframes cq-fadeup {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: none; }
}
@keyframes cq-fadein {
    from { opacity: 0; } to { opacity: 1; }
}
@keyframes cq-pulse-ring {
    0%   { transform: scale(1); opacity: .6; }
    70%  { transform: scale(1.8); opacity: 0; }
    100% { transform: scale(1.8); opacity: 0; }
}
@keyframes cq-ticker {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}
@keyframes cq-bar-grow {
    from { transform: scaleY(0); }
    to   { transform: scaleY(1); }
}
@keyframes cq-scan {
    0%   { top: 0; opacity: 1; }
    100% { top: 100%; opacity: 0; }
}

.cq-anim { animation: cq-fadeup .7s ease both; }
.cq-anim.d1 { animation-delay: .1s; }
.cq-anim.d2 { animation-delay: .2s; }
.cq-anim.d3 { animation-delay: .3s; }
.cq-anim.d4 { animation-delay: .4s; }
.cq-anim.d5 { animation-delay: .5s; }

/* ══════════════════════════════════════════════
   § 1. HERO
══════════════════════════════════════════════ */
.cq-hero {
    position: relative;
    min-height: 100vh;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    overflow: hidden;
    background: var(--c-bg);
}

/* Grid bg */
.cq-hero-grid {
    position: absolute; inset: 0; z-index: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.03) 1px, transparent 1px);
    background-size: 60px 60px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black, transparent);
}

/* Video overlay */
.cq-hero-video {
    position: absolute; inset: 0; z-index: 1;
    width: 100%; height: 100%; object-fit: cover;
}
.cq-hero-veil {
    position: absolute; inset: 0; z-index: 2;
    background: linear-gradient(
        135deg,
        rgba(11,14,17,.92) 0%,
        rgba(11,14,17,.78) 45%,
        rgba(11,14,17,.95) 100%
    );
}

/* Glow orb */
.cq-hero-orb {
    position: absolute; z-index: 3;
    width: 600px; height: 600px;
    top: 50%; left: 60%; transform: translate(-50%, -60%);
    background: radial-gradient(circle, rgba(125,255,0,.07) 0%, transparent 65%);
    pointer-events: none;
}
.cq-hero-orb2 {
    position: absolute; z-index: 3;
    width: 400px; height: 400px;
    top: 20%; left: 10%;
    background: radial-gradient(circle, rgba(0,184,212,.05) 0%, transparent 65%);
    pointer-events: none;
}

.cq-hero-body {
    position: relative; z-index: 4;
    width: 100%; max-width: 1200px;
    padding: 120px 24px 80px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 48px; flex-wrap: wrap;
}

.cq-hero-left { flex: 1; min-width: 300px; }

/* Pill badge */
.cq-hero-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(125,255,0,.08);
    border: 1px solid rgba(125,255,0,.2);
    border-radius: 100px;
    padding: 6px 14px 6px 8px;
    font-size: 12px; font-weight: 500; color: #d1d4dc;
    margin-bottom: 24px;
}
.cq-hero-pill-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--c-lime);
    position: relative;
}
.cq-hero-pill-dot::after {
    content: '';
    position: absolute; inset: -3px;
    border-radius: 50%; border: 1px solid var(--c-lime);
    animation: cq-pulse-ring 2s infinite;
}

.cq-hero-h1 {
    font-family: var(--f-display);
    font-size: clamp(38px, 5.5vw, 68px);
    font-weight: 800;
    line-height: 1.05;
    letter-spacing: -.02em;
    color: #FFFFFF;
    margin-bottom: 22px;
}
.cq-hero-h1 .hl { color: var(--c-lime); }

.cq-hero-desc {
    font-size: 16px; line-height: 1.75;
    color: var(--c-muted); max-width: 460px;
    margin-bottom: 36px;
}

/* CTA row */
.cq-hero-cta { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.cq-btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display);
    font-size: 14px; font-weight: 700; letter-spacing: .04em;
    padding: 12px 28px; border-radius: 6px; border: none;
    transition: all .2s; box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.cq-btn-primary:hover {
    background: #8FFF1A;
    box-shadow: 0 0 30px rgba(125,255,0,.35);
    transform: translateY(-1px);
}
.cq-btn-outline {
    display: inline-flex; align-items: center; gap: 8px;
    background: transparent; color: var(--c-text);
    font-size: 14px; font-weight: 500;
    padding: 12px 24px; border-radius: 6px;
    border: 1px solid var(--c-border2);
    transition: all .2s;
}
.cq-btn-outline:hover { border-color: rgba(125,255,0,.4); color: var(--c-lime); }

/* Store chips */
.cq-hero-stores { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
.cq-store-chip {
    display: inline-flex; align-items: center; gap: 7px;
    background: rgba(255,255,255,.04);
    border: 1px solid var(--c-border);
    color: var(--c-muted); font-size: 12px; font-weight: 500;
    padding: 8px 16px; border-radius: 6px;
    transition: all .2s;
}
.cq-store-chip:hover { border-color: var(--c-border2); color: var(--c-text); }
.cq-store-chip i { font-size: 15px; }

/* Right side — mini dashboard card */
.cq-hero-right { flex: 0 0 420px; }
.cq-hero-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: var(--shadow-lg), 0 0 0 1px rgba(125,255,0,.04);
}
.cq-hero-card-bar {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px;
    background: rgba(255,255,255,.02);
    border-bottom: 1px solid var(--c-border);
}
.cq-hcb-dots { display: flex; gap: 6px; }
.cq-hcb-dots span {
    width: 10px; height: 10px; border-radius: 50%;
    background: rgba(255,255,255,.08);
}
.cq-hcb-dots span:nth-child(1) { background: #EF5350; }
.cq-hcb-dots span:nth-child(2) { background: #FFA726; }
.cq-hcb-dots span:nth-child(3) { background: var(--c-lime); }
.cq-hcb-title { font-size: 11px; color: var(--c-muted); letter-spacing: .06em; }
.cq-hcb-badge {
    font-size: 10px; font-weight: 600; color: var(--c-lime);
    background: var(--c-lime-dim); padding: 2px 8px; border-radius: 4px;
    letter-spacing: .08em;
}

/* Mini chart inside hero card */
.cq-hero-chart { padding: 16px; }
.cq-hchart-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    margin-bottom: 14px;
}
.cq-hchart-name { font-size: 12px; color: var(--c-muted); margin-bottom: 3px; }
.cq-hchart-price { font-family: var(--f-mono); font-size: 22px; font-weight: 600; color: #fff; }
.cq-hchart-change {
    font-size: 12px; font-weight: 600; color: var(--c-lime);
    background: var(--c-lime-dim); padding: 3px 8px; border-radius: 4px;
}
.cq-hchart-change.neg { color: var(--c-red); background: rgba(239,83,80,.12); }

/* SVG sparkline */
.cq-sparkline { width: 100%; height: 80px; }

/* Stats row in card */
.cq-hero-stats {
    display: grid; grid-template-columns: repeat(3, 1fr);
    border-top: 1px solid var(--c-border);
}
.cq-hstat {
    padding: 14px 16px; border-right: 1px solid var(--c-border);
    transition: background .2s;
}
.cq-hstat:last-child { border-right: none; }
.cq-hstat:hover { background: var(--c-faint); }
.cq-hstat-v {
    font-family: var(--f-mono); font-size: 15px;
    font-weight: 600; color: #fff; margin-bottom: 3px;
}
.cq-hstat-l { font-size: 10px; color: var(--c-muted); letter-spacing: .05em; text-transform: uppercase; }

/* Ticker bottom of card */
.cq-hero-ticker {
    border-top: 1px solid var(--c-border);
    padding: 10px 0; overflow: hidden;
    background: rgba(255,255,255,.01);
}
.cq-ticker-track {
    display: flex; gap: 32px;
    animation: cq-ticker 22s linear infinite;
    white-space: nowrap;
}
.cq-ticker-item {
    display: inline-flex; align-items: center; gap: 8px;
    font-family: var(--f-mono); font-size: 11px; color: var(--c-muted);
    flex-shrink: 0;
}
.cq-ticker-item .sym { color: var(--c-text); font-weight: 600; }
.cq-ticker-item .up  { color: var(--c-lime); }
.cq-ticker-item .dn  { color: var(--c-red); }

/* Scroll cue */
.cq-scroll-cue {
    position: absolute; bottom: 28px; left: 50%;
    transform: translateX(-50%); z-index: 4;
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    color: var(--c-muted); font-size: 10px; letter-spacing: .1em; text-transform: uppercase;
    animation: cq-fadeup 1s ease .8s both;
}
.cq-scroll-line {
    width: 1px; height: 40px;
    background: linear-gradient(to bottom, transparent, var(--c-muted));
    animation: cq-scan 2s ease-in-out infinite;
    position: relative; overflow: hidden;
}

@media (max-width: 860px) {
    .cq-hero-body { flex-direction: column; text-align: center; }
    .cq-hero-desc { max-width: 100%; }
    .cq-hero-cta  { justify-content: center; }
    .cq-hero-stores { justify-content: center; }
    .cq-hero-right { flex: 0 0 auto; width: 100%; }
    .cq-hero-pill  { margin: 0 auto 24px; }
}

/* ══════════════════════════════════════════════
   § 2. PLATFORM TICKER BANNER
══════════════════════════════════════════════ */
.cq-platform {
    background: var(--c-surface);
    border-top: 1px solid var(--c-border);
    border-bottom: 1px solid var(--c-border);
    padding: 64px 0 56px;
}
.cq-platform-header {
    text-align: center; margin-bottom: 48px;
}
.cq-platform-header .cq-sec-label { justify-content: center; }
.cq-platform-header .cq-sec-label::before { display: none; }

/* Cert slider — modern card style */
.cq-cert-wrap { max-width: 900px; margin: 0 auto; position: relative; }
.cq-cert-overflow { overflow: hidden; border-radius: 10px; }
.cq-cert-track {
    display: flex;
    transition: transform .55s cubic-bezier(.4, 0, .2, 1);
}
.cq-cert-slide { min-width: 100%; }

.cq-cert-card {
    position: relative;
    border-radius: 10px; overflow: hidden;
    aspect-ratio: 16 / 5.5;
    border: 1px solid var(--c-border);
    cursor: pointer;
    background: var(--c-panel);
}
.cq-cert-img {
    width: 100%; height: 100%; object-fit: cover;
    display: block; transition: transform .5s ease;
}
.cq-cert-card:hover .cq-cert-img { transform: scale(1.03); }

/* Placeholder when no image */
.cq-cert-ph {
    width: 100%; height: 100%; display: flex;
    align-items: center; justify-content: center;
    flex-direction: column; gap: 10px;
    background: linear-gradient(135deg, #131722, #1c2030);
}
.cq-cert-ph-icon { font-size: 36px; color: rgba(125,255,0,.2); }
.cq-cert-ph-txt  { font-size: 11px; color: rgba(120,123,134,.3); letter-spacing: .12em; }

/* Overlay always-dark gradient */
.cq-cert-veil {
    position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(
        to right,
        rgba(11,14,17,.9) 0%,
        rgba(11,14,17,.3) 50%,
        rgba(11,14,17,.5) 100%
    );
}
/* Hover tint */
.cq-cert-hover {
    position: absolute; inset: 0; pointer-events: none;
    background: rgba(11,14,17,.4);
    opacity: 0; transition: opacity .35s;
}
.cq-cert-card:hover .cq-cert-hover { opacity: 1; }

/* Text overlay */
.cq-cert-info {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: space-between;
    padding: 28px 40px; pointer-events: none;
}
.cq-cert-left {}
.cq-cert-eyebrow {
    font-size: 10px; font-weight: 600; letter-spacing: .16em;
    text-transform: uppercase; color: var(--c-muted);
    margin-bottom: 10px;
}
.cq-cert-title {
    font-family: var(--f-display); font-size: clamp(22px, 3vw, 34px);
    font-weight: 800; color: #fff; line-height: 1.1;
}
.cq-cert-title span { color: var(--c-lime); }
.cq-cert-badge-wrap { margin-top: 14px; }
.cq-cert-badge {
    display: inline-flex; align-items: center; gap: 7px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.14);
    color: var(--c-text); font-size: 12px; font-style: italic;
    padding: 8px 18px; border-radius: 100px;
    backdrop-filter: blur(8px);
}
.cq-cert-right {}
.cq-cert-lang {
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-weight: 700; font-size: 14px;
    padding: 10px 24px; border-radius: 6px; letter-spacing: .06em;
}

/* Dots */
.cq-cert-dots {
    display: flex; justify-content: center; gap: 6px; margin-top: 16px;
}
.cq-cert-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: rgba(255,255,255,.15);
    border: none; padding: 0; cursor: pointer;
    transition: all .3s;
}
.cq-cert-dot.on { background: var(--c-lime); width: 20px; border-radius: 3px; }

/* ══════════════════════════════════════════════
   § 3. ABOUT THE APP
══════════════════════════════════════════════ */
.cq-about { background: var(--c-bg); overflow: hidden; }

.cq-about-inner {
    max-width: 1200px; margin: 0 auto; padding: 0 24px;
    display: grid; grid-template-columns: 1.1fr 1fr;
    gap: 64px; align-items: center;
}
@media (max-width: 860px) {
    .cq-about-inner { grid-template-columns: 1fr; gap: 40px; }
}

/* Video box */
.cq-about-vid {
    position: relative; border-radius: 10px; overflow: hidden;
    aspect-ratio: 16/9;
    border: 1px solid var(--c-border);
    background: var(--c-surface);
    box-shadow: var(--shadow-lg);
}
.cq-about-vid iframe,
.cq-about-vid video {
    width: 100%; height: 100%; border: none; display: block;
}
/* Corner accents */
.cq-vid-corner {
    position: absolute; width: 18px; height: 18px;
    border-color: var(--c-lime); border-style: solid;
    pointer-events: none;
}
.cq-vid-corner.tl { top: -1px; left: -1px; border-width: 2px 0 0 2px; }
.cq-vid-corner.tr { top: -1px; right: -1px; border-width: 2px 2px 0 0; }
.cq-vid-corner.bl { bottom: -1px; left: -1px; border-width: 0 0 2px 2px; }
.cq-vid-corner.br { bottom: -1px; right: -1px; border-width: 0 2px 2px 0; }

/* Sub heading */
.cq-about-quote {
    font-family: var(--f-display);
    font-size: clamp(15px, 2vw, 19px); font-weight: 600; font-style: italic;
    color: var(--c-lime); margin-bottom: 32px; line-height: 1.4;
}

/* Stats grid */
.cq-stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.cq-stat-box {
    padding: 22px 20px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 8px;
    position: relative; overflow: hidden;
    transition: border-color .25s, transform .25s;
}
.cq-stat-box::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .25s;
}
.cq-stat-box:hover { border-color: rgba(125,255,0,.2); transform: translateY(-2px); }
.cq-stat-box:hover::before { opacity: 1; }

.cq-stat-val {
    font-family: var(--f-display); font-size: 30px;
    font-weight: 800; color: #fff; margin-bottom: 4px; line-height: 1;
}
.cq-stat-lbl { font-size: 12px; font-weight: 600; color: var(--c-text); margin-bottom: 4px; }
.cq-stat-sub { font-size: 11px; color: var(--c-muted); line-height: 1.45; }

/* ══════════════════════════════════════════════
   § 4. FEATURE TOOLS
══════════════════════════════════════════════ */
.cq-features {
    background: var(--c-surface);
    border-top: 1px solid var(--c-border);
    border-bottom: 1px solid var(--c-border);
}

.cq-feat-layout {
    display: grid;
    grid-template-columns: 220px 200px 1fr;
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    background: var(--c-panel);
    margin-top: 48px;
}
@media (max-width: 900px) {
    .cq-feat-layout { grid-template-columns: 1fr; }
    .cq-feat-phone-col { display: none; }
}

/* Utility column */
.cq-feat-util {
    background: rgba(0,0,0,.25);
    border-right: 1px solid var(--c-border);
    padding: 24px 0;
}
.cq-feat-util-head {
    font-size: 10px; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime);
    padding: 0 20px; margin-bottom: 16px;
}
.cq-feat-tab {
    display: flex; align-items: center; justify-content: space-between;
    width: 100%; padding: 11px 20px;
    background: transparent; border: none;
    border-left: 2px solid transparent;
    font-family: var(--f-sans); font-size: 12px;
    font-weight: 600; text-transform: uppercase; letter-spacing: .07em;
    color: var(--c-muted); transition: all .2s; text-align: left;
}
.cq-feat-tab.on {
    color: var(--c-lime);
    background: var(--c-lime-glow);
    border-left-color: var(--c-lime);
}
.cq-feat-tab:hover:not(.on) {
    color: var(--c-text); background: rgba(255,255,255,.02);
}
.cq-feat-count {
    font-size: 10px; font-weight: 700;
    padding: 2px 7px; border-radius: 4px;
    background: var(--c-lime); color: #000; min-width: 24px; text-align: center;
}
.cq-feat-tab:not(.on) .cq-feat-count {
    background: rgba(255,255,255,.08); color: var(--c-muted);
}

/* Phone col */
.cq-feat-phone-col {
    display: flex; align-items: center; justify-content: center;
    padding: 28px 16px;
    background: rgba(0,0,0,.1);
    border-right: 1px solid var(--c-border);
}
.cq-phone-frame {
    width: 150px; height: 290px;
    background: #0a1018;
    border-radius: 28px;
    border: 1.5px solid rgba(255,255,255,.1);
    box-shadow: 0 20px 60px rgba(0,0,0,.7), inset 0 0 0 1px rgba(255,255,255,.04);
    padding: 16px 8px 22px;
    display: flex; flex-direction: column; gap: 0;
    position: relative; overflow: hidden;
}
.cq-phone-notch {
    width: 44px; height: 5px; background: rgba(255,255,255,.06);
    border-radius: 3px; margin: 0 auto 12px; flex-shrink: 0;
}
/* Mini chart bars */
.cq-phone-chart {
    display: flex; align-items: flex-end; flex: 1;
    gap: 3px; padding: 0 2px;
    transform-origin: bottom;
}
.cq-pbar {
    flex: 1; border-radius: 2px 2px 0 0;
    transform-origin: bottom;
    animation: cq-bar-grow .8s ease both, cq-fadeup 0s;
}
/* Scan line effect */
.cq-phone-frame::after {
    content: '';
    position: absolute; left: 0; right: 0; height: 40px;
    background: linear-gradient(to bottom, transparent, rgba(125,255,0,.03), transparent);
    animation: cq-scan 3s linear infinite;
    pointer-events: none;
}

/* Tool detail */
.cq-feat-detail {
    padding: 36px 32px;
    display: flex; flex-direction: column; justify-content: center;
}
.cq-tool-name {
    font-family: var(--f-display); font-size: 26px;
    font-weight: 700; color: #fff; margin-bottom: 6px;
}
.cq-tool-rule {
    width: 32px; height: 2px;
    background: var(--c-lime); margin-bottom: 18px;
}
.cq-tool-icon {
    font-size: 40px; color: var(--c-lime);
    opacity: .8; margin-bottom: 18px;
}
.cq-tool-pt {
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 13.5px; color: var(--c-muted);
    line-height: 1.65; margin-bottom: 10px;
}
.cq-tool-pt-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--c-lime); margin-top: 7px; flex-shrink: 0;
}

/* ══════════════════════════════════════════════
   § 5. LEARNING
══════════════════════════════════════════════ */
.cq-learning { background: var(--c-bg); }

.cq-learn-card {
    max-width: 1000px; margin: 48px auto 0;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; overflow: hidden;
    display: flex;
}
@media (max-width: 860px) {
    .cq-learn-card { flex-direction: column; }
    .cq-ltabs { flex-direction: row !important; overflow-x: auto; border-right: none !important; border-bottom: 1px solid var(--c-border) !important; }
    .cq-ltab  { border-left: none !important; border-bottom: 2px solid transparent !important; white-space: nowrap; }
    .cq-ltab.on { border-bottom-color: var(--c-lime) !important; border-left: none !important; }
    .cq-learn-panel-body.on { flex-direction: column !important; }
    .cq-lyvid { flex: none !important; width: 100% !important; }
}

.cq-ltabs {
    flex: 0 0 140px;
    background: rgba(0,0,0,.25);
    border-right: 1px solid var(--c-border);
    display: flex; flex-direction: column; padding: 24px 0;
}
.cq-ltab {
    display: block; width: 100%; padding: 12px 20px;
    border: none; background: transparent;
    font-family: var(--f-display); font-size: 14px; font-weight: 600;
    color: var(--c-muted); text-align: left; cursor: pointer;
    border-left: 2px solid transparent;
    letter-spacing: .02em; transition: all .2s;
}
.cq-ltab.on {
    color: var(--c-lime);
    border-left-color: var(--c-lime);
    background: var(--c-lime-glow);
}
.cq-ltab:hover:not(.on) { color: var(--c-text); }

.cq-lpanels { flex: 1; }
.cq-learn-panel-body { display: none; gap: 28px; padding: 32px 28px; }
.cq-learn-panel-body.on { display: flex; align-items: center; }

.cq-ltext { flex: 1; min-width: 200px; }
.cq-lpanel-title {
    font-family: var(--f-display); font-size: 20px;
    font-weight: 700; color: #fff; margin-bottom: 8px;
}
.cq-lhl {
    display: inline-block; background: var(--c-lime-dim);
    border: 1px solid rgba(125,255,0,.25);
    color: var(--c-lime);
    font-size: 11px; font-weight: 700; letter-spacing: .08em;
    padding: 5px 14px; border-radius: 4px; margin-bottom: 14px;
    text-transform: uppercase;
}
.cq-ldesc { font-size: 13.5px; color: var(--c-muted); line-height: 1.78; margin-bottom: 20px; }
.cq-lbtn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    letter-spacing: .05em; padding: 10px 24px;
    border-radius: 6px; border: none;
    transition: all .2s; box-shadow: 0 0 16px rgba(125,255,0,.2);
}
.cq-lbtn:hover { background: #8FFF1A; transform: translateY(-1px); }

/* YouTube card in learning */
.cq-lyvid { flex: 0 0 280px; }
.cq-yt-card {
    background: var(--c-panel); border: 1px solid var(--c-border);
    border-radius: 8px; overflow: hidden;
}
.cq-yt-top {
    display: flex; align-items: center; gap: 8px;
    padding: 9px 12px;
    background: rgba(0,0,0,.2); border-bottom: 1px solid var(--c-border);
}
.cq-yt-logo {
    width: 26px; height: 26px; border-radius: 50%;
    background: var(--c-lime);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 800; color: #000; flex-shrink: 0;
}
.cq-yt-ch   { font-size: 11px; font-weight: 600; color: #fff; }
.cq-yt-sub  { font-size: 10px; color: var(--c-muted); }
.cq-yt-live {
    margin-left: auto; display: inline-flex; align-items: center; gap: 4px;
    background: #C00; color: #fff; font-size: 9px; font-weight: 700;
    padding: 3px 7px; border-radius: 3px; letter-spacing: .05em;
}
.cq-yt-thumb {
    position: relative; cursor: pointer;
    width: 100%; aspect-ratio: 16/9; overflow: hidden;
}
.cq-yt-thumb-bg {
    width: 100%; height: 100%;
    background: linear-gradient(135deg, #0d1015, #1a1030, #0a1020);
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 12px 14px;
}
.cq-yt-vtitle {
    font-family: var(--f-display); font-size: 14px;
    font-weight: 700; color: #fff; line-height: 1.2;
    text-transform: uppercase;
}
.cq-yt-vtitle span { color: var(--c-lime); }
.cq-yt-play {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 42px; height: 42px; border-radius: 50%;
    background: rgba(204,0,0,.9);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; color: #fff;
    box-shadow: 0 4px 14px rgba(204,0,0,.4);
    transition: transform .2s;
}
.cq-yt-thumb:hover .cq-yt-play { transform: translate(-50%, -50%) scale(1.12); }
.cq-yt-iframe-wrap { display: none; width: 100%; aspect-ratio: 16/9; }
.cq-yt-iframe-wrap iframe { width: 100%; height: 100%; border: none; display: block; }
.cq-yt-foot {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 12px; border-top: 1px solid var(--c-border);
}
.cq-yt-meta { font-size: 10px; color: var(--c-muted); display: flex; gap: 6px; align-items: center; }
.cq-yt-meta i { color: var(--c-lime); }
.cq-yt-watch { font-size: 10px; color: var(--c-muted); display: flex; align-items: center; gap: 4px; }
.cq-yt-watch i { color: #FF0000; font-size: 12px; }

/* ══════════════════════════════════════════════
   § 6. TESTIMONIALS
══════════════════════════════════════════════ */
.cq-testi {
    background: var(--c-surface);
    border-top: 1px solid var(--c-border);
}

.cq-tslider { max-width: 1100px; margin: 48px auto 0; position: relative; }
.cq-ttrack {
    display: flex; gap: 16px;
    transition: transform .5s cubic-bezier(.4, 0, .2, 1);
    padding: 44px 4px 4px;
}
.cq-tcard {
    flex-shrink: 0;
    max-width: calc(33.333% - 11px);
    background: var(--c-panel);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: 28px 22px 22px;
    position: relative;
    transition: border-color .25s, transform .25s;
}
.cq-tcard:hover {
    border-color: rgba(125,255,0,.18);
    transform: translateY(-4px);
}
/* Top border accent on hover */
.cq-tcard::before {
    content: '';
    position: absolute; top: 0; left: 20px; right: 20px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .25s;
}
.cq-tcard:hover::before { opacity: 1; }

/* Avatar */
.cq-tav {
    width: 56px; height: 56px; border-radius: 50%;
    border: 2px solid var(--c-lime);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--f-display); font-size: 18px; font-weight: 700;
    color: #fff; position: absolute;
    top: -28px; left: 50%; transform: translateX(-50%);
    overflow: hidden;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.cq-tav img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

.cq-tbody { margin-top: 16px; text-align: center; }
.cq-tquote { font-size: 13px; color: var(--c-muted); line-height: 1.75; margin-bottom: 14px; font-style: italic; }
.cq-tquote::before { content: '"'; color: var(--c-lime); font-size: 18px; }
.cq-tquote::after  { content: '"'; color: var(--c-lime); font-size: 18px; }
.cq-tname  { font-size: 14px; font-weight: 600; color: var(--c-text); margin-bottom: 3px; }
.cq-trole  { font-size: 11px; color: var(--c-muted); margin-bottom: 8px; }
.cq-tstars { color: var(--c-lime); font-size: 12px; letter-spacing: 2px; }

/* Nav */
.cq-tnav {
    display: flex; align-items: center; justify-content: center;
    gap: 12px; margin-top: 28px;
}
.cq-tarrow {
    width: 36px; height: 36px; border-radius: 6px;
    background: var(--c-panel); border: 1px solid var(--c-border);
    color: var(--c-muted); display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all .2s; font-size: 13px;
}
.cq-tarrow:hover { background: var(--c-lime-dim); border-color: rgba(125,255,0,.3); color: var(--c-lime); }
.cq-tdots { display: flex; gap: 6px; align-items: center; }
.cq-tdot {
    width: 6px; height: 6px; border-radius: 50%;
    border: none; padding: 0; background: rgba(255,255,255,.15);
    cursor: pointer; transition: all .3s;
}
.cq-tdot.on { background: var(--c-lime); width: 18px; border-radius: 3px; }

@media (max-width: 880px) { .cq-tcard { max-width: calc(50% - 8px); } }
@media (max-width: 560px) { .cq-tcard { max-width: 100%; } }

/* ══════════════════════════════════════════════
   RESPONSIVE GLOBAL
══════════════════════════════════════════════ */
@media (max-width: 640px) {
    .cq-section { padding: 64px 0; }
    .cq-hero-right { display: none; } /* hide card on small screens */
}
</style>

<div class="cq-root">

{{-- ═══════════════════════════════════════════
     § 1 — HERO
═══════════════════════════════════════════ --}}
<section class="cq-hero">
    {{-- Background --}}
    <div class="cq-hero-grid"></div>
    <video class="cq-hero-video" autoplay muted loop playsinline preload="auto">
        <source src="{{ $hero['video_url'] }}" type="video/mp4">
    </video>
    <div class="cq-hero-veil"></div>
    <div class="cq-hero-orb"></div>
    <div class="cq-hero-orb2"></div>

    {{-- Content --}}
    <div class="cq-hero-body">

        {{-- Left --}}
        <div class="cq-hero-left">
            <div class="cq-hero-pill cq-anim">
                <span class="cq-hero-pill-dot"></span>
                India's #1 Options Analytics Platform
            </div>
            <h1 class="cq-hero-h1 cq-anim d1">
                {{ $hero['heading_line1'] }}
                <span class="hl">{{ $hero['heading_highlight'] }}</span><br>
                {{ $hero['heading_line2'] }}
            </h1>
            <p class="cq-hero-desc cq-anim d2">
                Real-time OI analysis, strategy builders &amp; 47+ analytical tools — everything a serious options trader needs, in one platform.
            </p>
            <div class="cq-hero-cta cq-anim d3">
                <a href="{{ $hero['webapp'] ?? '#' }}" class="cq-btn-primary">
                    <i class="fas fa-chart-line"></i> Launch Platform
                </a>
                <a href="#about" class="cq-btn-outline">
                    <i class="fas fa-play-circle"></i> Watch Demo
                </a>
            </div>
            <div class="cq-hero-stores cq-anim d4">
                <a href="{{ $hero['appstore'] ?? '#' }}" class="cq-store-chip">
                    <i class="fab fa-apple"></i> App Store
                </a>
                <a href="{{ $hero['playstore'] ?? '#' }}" class="cq-store-chip">
                    <i class="fab fa-google-play"></i> Play Store
                </a>
                <a href="{{ $hero['webapp'] ?? '#' }}" class="cq-store-chip">
                    <i class="fas fa-globe"></i> Web App
                </a>
            </div>
        </div>

        {{-- Right — mini dashboard card --}}
        <div class="cq-hero-right cq-anim d3">
            <div class="cq-hero-card">
                {{-- Title bar --}}
                <div class="cq-hero-card-bar">
                    <div class="cq-hcb-dots">
                        <span></span><span></span><span></span>
                    </div>
                    <span class="cq-hcb-title">NIFTY 50 · Options Chain</span>
                    <span class="cq-hcb-badge">LIVE</span>
                </div>
                {{-- Chart area --}}
                <div class="cq-hero-chart">
                    <div class="cq-hchart-header">
                        <div>
                            <div class="cq-hchart-name">NIFTY 50 · CE 24500</div>
                            <div class="cq-hchart-price">₹ 248.65</div>
                        </div>
                        <span class="cq-hchart-change">▲ +3.42%</span>
                    </div>
                    {{-- SVG sparkline --}}
                    <svg class="cq-sparkline" viewBox="0 0 380 80" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="spkGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#7DFF00" stop-opacity=".25"/>
                                <stop offset="100%" stop-color="#7DFF00" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <path d="M0,58 C20,52 30,62 50,55 C70,47 80,58 100,48 C115,40 120,52 140,42 C160,32 170,44 190,36 C210,28 220,38 240,30 C255,23 265,34 285,26 C305,18 315,28 335,20 C350,14 360,22 380,15"
                              stroke="#7DFF00" stroke-width="1.5" fill="none"/>
                        <path d="M0,58 C20,52 30,62 50,55 C70,47 80,58 100,48 C115,40 120,52 140,42 C160,32 170,44 190,36 C210,28 220,38 240,30 C255,23 265,34 285,26 C305,18 315,28 335,20 C350,14 360,22 380,15 L380,80 L0,80 Z"
                              fill="url(#spkGrad)"/>
                        <circle cx="380" cy="15" r="3" fill="#7DFF00"/>
                    </svg>
                </div>
                {{-- Stats row --}}
                <div class="cq-hero-stats">
                    <div class="cq-hstat">
                        <div class="cq-hstat-v">2.4M</div>
                        <div class="cq-hstat-l">Open Interest</div>
                    </div>
                    <div class="cq-hstat">
                        <div class="cq-hstat-v">0.62</div>
                        <div class="cq-hstat-l">PCR Ratio</div>
                    </div>
                    <div class="cq-hstat">
                        <div class="cq-hstat-v">18.4</div>
                        <div class="cq-hstat-l">IV %</div>
                    </div>
                </div>
                {{-- Ticker --}}
                <div class="cq-hero-ticker">
                    <div class="cq-ticker-track">
                        @php
                        $tickers = [
                            ['sym'=>'NIFTY','price'=>'24,512','chg'=>'+0.38%','up'=>true],
                            ['sym'=>'BANKNIFTY','price'=>'52,144','chg'=>'+0.61%','up'=>true],
                            ['sym'=>'FINNIFTY','price'=>'23,872','chg'=>'-0.14%','up'=>false],
                            ['sym'=>'MIDCPNIFTY','price'=>'11,432','chg'=>'+0.22%','up'=>true],
                            ['sym'=>'VIX','price'=>'13.82','chg'=>'-2.10%','up'=>false],
                            ['sym'=>'SENSEX','price'=>'80,432','chg'=>'+0.41%','up'=>true],
                        ];
                        @endphp
                        @foreach(array_merge($tickers,$tickers) as $t)
                        <span class="cq-ticker-item">
                            <span class="sym">{{ $t['sym'] }}</span>
                            <span>{{ $t['price'] }}</span>
                            <span class="{{ $t['up'] ? 'up' : 'dn' }}">{{ $t['chg'] }}</span>
                        </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Scroll cue --}}
    <div class="cq-scroll-cue">
        <div class="cq-scroll-line"></div>
        <span>Scroll</span>
    </div>
</section>


{{-- ═══════════════════════════════════════════
     § 2 — PLATFORM BANNER + CERT SLIDER
═══════════════════════════════════════════ --}}
<div class="cq-platform">
    <div class="cq-platform-header cq-anim">
        <div class="cq-sec-label" style="justify-content:center;">
            <span>Platform</span>
        </div>
        <h2 class="cq-sec-title" style="text-align:center;">{{ $platform['title'] }}</h2>
        <p class="cq-sec-sub" style="text-align:center;margin:12px auto 0;">{{ $platform['subtitle'] }}</p>
    </div>

    @if(count($certBanners) > 0)
    <div class="cq-cert-wrap cq-anim d2">
        <div class="cq-cert-overflow">
            <div class="cq-cert-track" id="certTrack">
                @foreach($certBanners as $cert)
                <div class="cq-cert-slide">
                    <div class="cq-cert-card">
                        @if(!empty($cert['image']))
                            <img src="{{ $cert['image'] }}" alt="{{ strip_tags($cert['title'] ?? '') }}" class="cq-cert-img">
                        @else
                            <div class="cq-cert-ph">
                                <div class="cq-cert-ph-icon"><i class="fas fa-certificate"></i></div>
                                <span class="cq-cert-ph-txt">CERTIFICATION BANNER</span>
                            </div>
                        @endif
                        <div class="cq-cert-veil"></div>
                        <div class="cq-cert-hover"></div>
                        <div class="cq-cert-info">
                            <div class="cq-cert-left">
                                <div class="cq-cert-eyebrow">CityQuants · Options Trading</div>
                                <div class="cq-cert-title">{!! $cert['title'] !!}</div>
                                @if(!empty($cert['badge']))
                                <div class="cq-cert-badge-wrap">
                                    <div class="cq-cert-badge">{{ $cert['badge'] }}</div>
                                </div>
                                @endif
                            </div>
                            @if(!empty($cert['lang']))
                            <div class="cq-cert-right">
                                <div class="cq-cert-lang">{{ $cert['lang'] }}</div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @if(count($certBanners) > 1)
        <div class="cq-cert-dots" id="certDots">
            @foreach($certBanners as $i => $c)
            <button class="cq-cert-dot {{ $i===0?'on':'' }}" onclick="certGo({{ $i }})"></button>
            @endforeach
        </div>
        @endif
    </div>
    @endif
</div>


{{-- ═══════════════════════════════════════════
     § 3 — ABOUT THE APP
═══════════════════════════════════════════ --}}
<section class="cq-section cq-about" id="about">
    <div class="cq-about-inner">
        {{-- Video --}}
        <div class="cq-about-vid cq-anim d1">
            @if(isset($about['video_type']) && $about['video_type'] === 'upload')
                <video controls>
                    <source src="{{ $about['video_url'] }}" type="video/mp4">
                </video>
            @else
                <iframe src="{{ $about['video_url'] }}"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen title="About CityQuants"></iframe>
            @endif
            <div class="cq-vid-corner tl"></div>
            <div class="cq-vid-corner tr"></div>
            <div class="cq-vid-corner bl"></div>
            <div class="cq-vid-corner br"></div>
        </div>
        {{-- Right side --}}
        <div class="cq-anim d2">
            <div class="cq-sec-label">About the App</div>
            <h2 class="cq-sec-title">About the <span>Platform</span></h2>
            <div class="cq-about-quote">{!! $about['title'] !!}</div>
            <div class="cq-stat-grid">
                @foreach($about['stats'] as $stat)
                <div class="cq-stat-box">
                    <div class="cq-stat-val">{{ $stat['value'] }}</div>
                    <div class="cq-stat-lbl">{{ $stat['label'] }}</div>
                    @if(!empty($stat['sub']))
                    <div class="cq-stat-sub">{{ $stat['sub'] }}</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════════
     § 4 — FEATURE TOOLS
═══════════════════════════════════════════ --}}
<section class="cq-section cq-features">
    <div class="cq-container">
        <div class="cq-anim">
            <div class="cq-sec-label">Feature Tools</div>
            <h2 class="cq-sec-title">{{ $features['title'] }}</h2>
            <p class="cq-sec-sub">Analyze · Backtest · Optimize · Manage — all your option trades in one place.</p>
        </div>

        <div class="cq-feat-layout cq-anim d2">
            {{-- Utilities column --}}
            <div class="cq-feat-util">
                <div class="cq-feat-util-head">Utilities</div>
                @foreach($features['utilities'] as $i => $u)
                <button class="cq-feat-tab {{ $i===0?'on':'' }}" onclick="featSwitch({{ $i }},this)">
                    <span>{{ strtoupper($u['label']) }}</span>
                    <span class="cq-feat-count">{{ $u['count'] }}</span>
                </button>
                @endforeach
            </div>

            {{-- Phone mockup --}}
            <div class="cq-feat-phone-col">
                <div class="cq-phone-frame">
                    <div class="cq-phone-notch"></div>
                    <div class="cq-phone-chart">
                        <div class="cq-pbar" style="height:42%;background:#EF5350;animation-delay:.00s"></div>
                        <div class="cq-pbar" style="height:75%;background:#7DFF00;animation-delay:.10s"></div>
                        <div class="cq-pbar" style="height:57%;background:#26A69A;animation-delay:.20s"></div>
                        <div class="cq-pbar" style="height:91%;background:#00B8D4;animation-delay:.05s"></div>
                        <div class="cq-pbar" style="height:46%;background:#EF5350;animation-delay:.15s"></div>
                        <div class="cq-pbar" style="height:68%;background:#7DFF00;animation-delay:.25s"></div>
                        <div class="cq-pbar" style="height:95%;background:#26A69A;animation-delay:.10s"></div>
                        <div class="cq-pbar" style="height:54%;background:#9C27B0;animation-delay:.20s"></div>
                    </div>
                </div>
            </div>

            {{-- Detail column --}}
            <div class="cq-feat-detail" id="featDetailCol">
                @foreach($features['utilities'] as $i => $u)
                <div id="cqTool{{ $i }}" style="{{ $i===0?'display:flex':'display:none' }}" class="cq-feat-detail-inner" style="flex-direction:column;">
                    <div class="cq-tool-icon"><i class="fas {{ $u['tool_icon'] }}"></i></div>
                    <div class="cq-tool-name">{{ $u['tool_title'] }}</div>
                    <div class="cq-tool-rule"></div>
                    @foreach($u['tool_points'] as $pt)
                    <div class="cq-tool-pt">
                        <div class="cq-tool-pt-dot"></div>
                        <span>{{ $pt }}</span>
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════════
     § 5 — LEARNING
═══════════════════════════════════════════ --}}
<section class="cq-section cq-learning">
    <div class="cq-container">
        <div class="cq-anim">
            <div class="cq-sec-label">{{ $learning['title'] }}</div>
            <h2 class="cq-sec-title">Learn. <span>Grow.</span> Trade Better.</h2>
            <p class="cq-sec-sub">World-class options education from experienced traders — free webinars, certification courses, and more.</p>
        </div>

        <div class="cq-learn-card cq-anim d2">
            <div class="cq-ltabs">
                @foreach($learning['tabs'] as $i => $tab)
                <button class="cq-ltab {{ $i===0?'on':'' }}" onclick="learnSwitch({{ $i }},this)">
                    {{ $tab['tab'] }}
                </button>
                @endforeach
            </div>
            <div class="cq-lpanels">
                @foreach($learning['tabs'] as $i => $tab)
                <div class="cq-learn-panel-body {{ $i===0?'on':'' }}">
                    <div class="cq-ltext">
                        <div class="cq-lpanel-title">{{ $tab['tab'] }}</div>
                        @if(!empty($tab['highlight']))
                        <div class="cq-lhl">{{ $tab['highlight'] }}</div>
                        @endif
                        <p class="cq-ldesc">{{ $tab['description'] }}</p>
                        <a href="{{ $tab['btn_url'] }}" class="cq-lbtn">
                            {{ $tab['btn_label'] }} <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    @if(!empty($tab['video_id']))
                    <div class="cq-lyvid">
                        <div class="cq-yt-card">
                            <div class="cq-yt-top">
                                <div class="cq-yt-logo">Q</div>
                                <div>
                                    <div class="cq-yt-ch">Options Trading</div>
                                    <div class="cq-yt-sub">CityQuants</div>
                                </div>
                                <div class="cq-yt-live"><i class="fas fa-circle" style="font-size:6px"></i> LIVE</div>
                            </div>
                            <div class="cq-yt-thumb" onclick="ytPlay('{{ $tab['video_id'] }}',{{ $i }})">
                                <div class="cq-yt-thumb-bg">
                                    <div class="cq-yt-vtitle">
                                        {{ strtoupper($tab['video_title']) }}
                                        @if(!empty($tab['video_sub']))<br><span>{{ $tab['video_sub'] }}</span>@endif
                                    </div>
                                </div>
                                <div class="cq-yt-play"><i class="fab fa-youtube"></i></div>
                            </div>
                            <div class="cq-yt-iframe-wrap" id="ytframe{{ $i }}">
                                <iframe src="" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                            </div>
                            <div class="cq-yt-foot">
                                <div class="cq-yt-meta">
                                    @if(!empty($tab['video_date']))<i class="far fa-calendar-alt"></i> {{ $tab['video_date'] }}@endif
                                    @if(!empty($tab['video_time'])) · {{ $tab['video_time'] }}@endif
                                </div>
                                <div class="cq-yt-watch">Watch on <i class="fab fa-youtube"></i></div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════════
     § 6 — TESTIMONIALS
═══════════════════════════════════════════ --}}
<section class="cq-section cq-testi">
    <div class="cq-container">
        <div class="cq-anim" style="text-align:center;">
            <div class="cq-sec-label" style="justify-content:center;">Testimonials</div>
            <h2 class="cq-sec-title">Hear from our <span>Users</span></h2>
            <p class="cq-sec-sub" style="margin:14px auto 0;">Trusted by 6,500+ active traders across India.</p>
        </div>

        <div class="cq-tslider cq-anim d2">
            <div class="cq-ttrack" id="tTrack">
                @foreach($testimonials as $t)
                <div class="cq-tcard">
                    <div class="cq-tav" style="background:linear-gradient(135deg,#1a3a6e,#0d6e4e);">
                        @if(!empty($t['avatar']))
                            <img src="{{ $t['avatar'] }}" alt="{{ $t['name'] }}">
                        @else
                            {{ strtoupper(substr($t['name'],0,2)) }}
                        @endif
                    </div>
                    <div class="cq-tbody">
                        <p class="cq-tquote">{{ $t['review'] }}</p>
                        <div class="cq-tname">{{ $t['name'] }}</div>
                        <div class="cq-tstars">
                            @for($s=0;$s<($t['rating']??5);$s++)★@endfor
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="cq-tnav">
            <button class="cq-tarrow" onclick="tPrev()"><i class="fas fa-chevron-left"></i></button>
            <div class="cq-tdots" id="tDots"></div>
            <button class="cq-tarrow" onclick="tNext()"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</section>

</div>{{-- .cq-root --}}

<script>
/* ── CERT SLIDER ──────────────────────────── */
var _ci = 0;
var _ct = document.querySelectorAll('.cq-cert-slide').length;
function certGo(i) {
    _ci = i;
    document.getElementById('certTrack').style.transform = 'translateX(-' + i + '00%)';
    document.querySelectorAll('.cq-cert-dot').forEach(function(d,j){ d.classList.toggle('on', j===i); });
}
if (_ct > 1) { setInterval(function(){ certGo((_ci+1) % _ct); }, 5000); }

/* ── FEATURE SWITCH ───────────────────────── */
var _totalTools = {{ count($features['utilities']) }};
function featSwitch(idx, btn) {
    document.querySelectorAll('.cq-feat-tab').forEach(function(b){ b.classList.remove('on'); });
    btn.classList.add('on');
    for (var x = 0; x < _totalTools; x++) {
        var el = document.getElementById('cqTool' + x);
        if (el) el.style.display = (x === idx) ? 'flex' : 'none';
    }
}

/* ── LEARN SWITCH ─────────────────────────── */
function learnSwitch(idx, btn) {
    document.querySelectorAll('.cq-ltab').forEach(function(b){ b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.cq-learn-panel-body').forEach(function(p,i){
        p.classList.toggle('on', i === idx);
    });
}

/* ── YOUTUBE PLAY ─────────────────────────── */
function ytPlay(vid, idx) {
    var thumb = event.currentTarget;
    thumb.style.display = 'none';
    var fw = document.getElementById('ytframe' + idx);
    fw.style.display = 'block';
    fw.querySelector('iframe').src = 'https://www.youtube.com/embed/' + vid + '?autoplay=1&rel=0';
}

/* ── TESTIMONIALS SLIDER ──────────────────── */
(function () {
    var track  = document.getElementById('tTrack');
    if (!track) return;
    var cards  = track.querySelectorAll('.cq-tcard');
    var dotsEl = document.getElementById('tDots');
    var total  = cards.length, idx = 0, timer;
    var pv     = window.innerWidth > 880 ? 3 : window.innerWidth > 560 ? 2 : 1;
    var maxIdx = Math.max(0, total - pv);
    var dots   = [];

    for (var i = 0; i <= maxIdx; i++) {
        (function(ii){
            var d = document.createElement('button');
            d.className = 'cq-tdot' + (ii === 0 ? ' on' : '');
            d.onclick = function(){ tGoTo(ii); };
            dotsEl.appendChild(d);
            dots.push(d);
        })(i);
    }

    function tGoTo(i) {
        idx = Math.max(0, Math.min(i, maxIdx));
        var cw = (cards[0].offsetWidth + 16);
        track.style.transform = 'translateX(-' + (idx * cw) + 'px)';
        dots.forEach(function(d,j){ d.classList.toggle('on', j===idx); });
    }

    window.tNext = function(){ tGoTo(idx < maxIdx ? idx+1 : 0); };
    window.tPrev = function(){ tGoTo(idx > 0 ? idx-1 : maxIdx); };

    function startT(){ timer = setInterval(window.tNext, 4500); }
    function stopT() { clearInterval(timer); }
    startT();
    track.addEventListener('mouseenter', stopT);
    track.addEventListener('mouseleave', startT);

    var sx = 0;
    track.addEventListener('touchstart', function(e){ sx = e.touches[0].clientX; });
    track.addEventListener('touchend',   function(e){
        var dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 40) { dx < 0 ? window.tNext() : window.tPrev(); }
    });
    window.addEventListener('resize', function(){
        var npv = window.innerWidth > 880 ? 3 : window.innerWidth > 560 ? 2 : 1;
        if (npv !== pv) { pv = npv; maxIdx = Math.max(0, total-pv); tGoTo(0); }
    });
})();

/* ── INTERSECTION OBSERVER for .cq-anim ──── */
(function(){
    if (!('IntersectionObserver' in window)) return;
    var els = document.querySelectorAll('.cq-anim');
    var obs = new IntersectionObserver(function(entries){
        entries.forEach(function(e){
            if (e.isIntersecting) {
                e.target.style.animationPlayState = 'running';
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.1 });
    els.forEach(function(el){
        el.style.animationPlayState = 'paused';
        obs.observe(el);
    });
})();
</script>

@endsection