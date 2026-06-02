@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — ABOUT US  v2.0
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
    --c-lime-glo: rgba(125,255,0,.06);
    --c-blue:     #00B8D4;
    --c-red:      #EF5350;
    --c-teal:     #26A69A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.ab-wrap {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    display: block;
    overflow-x: hidden;
}
.ab-wrap * { box-sizing: border-box; }
.ab-wrap a { text-decoration: none; color: inherit; }

@keyframes abFadeUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: none; }
}
.ab-anim    { animation: abFadeUp .65s ease both; }
.ab-anim.d1 { animation-delay: .1s; }
.ab-anim.d2 { animation-delay: .2s; }
.ab-anim.d3 { animation-delay: .3s; }
.ab-anim.d4 { animation-delay: .4s; }

/* ── SHARED SECTION HELPERS ──────────────────── */
.ab-sec { padding: 88px 24px; }
.ab-container { max-width: 1200px; margin: 0 auto; }

.ab-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 14px;
}
.ab-eyebrow::before { content: ''; display: block; width: 20px; height: 1px; background: var(--c-lime); }

.ab-sec-title {
    font-family: var(--f-display);
    font-size: clamp(28px, 3.5vw, 42px);
    font-weight: 800; color: #fff;
    line-height: 1.1; letter-spacing: -.015em;
}
.ab-sec-title span { color: var(--c-lime); }

.ab-sec-sub {
    font-size: 15px; color: var(--c-muted);
    line-height: 1.75; max-width: 560px; margin-top: 14px;
}

/* Shared card base */
.ab-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px;
    position: relative; overflow: hidden;
}
.ab-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .3s;
}
.ab-card:hover::before { opacity: 1; }

/* ══ 1. BREADCRUMB ════════════════════════════ */
.ab-breadcrumb {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 12px 24px;
}
.ab-breadcrumb-inner {
    max-width: 1200px; margin: 0 auto;
    font-size: 12px; color: var(--c-muted);
    display: flex; align-items: center; gap: 7px;
    font-family: var(--f-mono);
}
.ab-breadcrumb-inner a { color: var(--c-lime); font-weight: 600; transition: opacity .2s; }
.ab-breadcrumb-inner a:hover { opacity: .75; }
.ab-breadcrumb-inner i { font-size: 10px; color: var(--c-border2); }

/* ══ 2. HERO ══════════════════════════════════ */
.ab-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 88px 24px 72px;
    border-bottom: 1px solid var(--c-border);
}
/* Grid texture */
.ab-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.025) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 85% 85% at 50% 50%, black, transparent);
    pointer-events: none;
}
/* Glow orbs */
.ab-hero-orb1 {
    position: absolute; width: 500px; height: 500px; border-radius: 50%;
    background: radial-gradient(circle, rgba(125,255,0,.07) 0%, transparent 70%);
    top: -120px; right: -80px; pointer-events: none;
}
.ab-hero-orb2 {
    position: absolute; width: 350px; height: 350px; border-radius: 50%;
    background: radial-gradient(circle, rgba(0,184,212,.05) 0%, transparent 70%);
    bottom: -80px; left: -60px; pointer-events: none;
}

.ab-hero-inner {
    position: relative; z-index: 1;
    max-width: 1200px; margin: 0 auto;
    text-align: center;
}

/* Pill badge */
.ab-hero-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-lime-dim);
    border: 1px solid rgba(125,255,0,.25);
    border-radius: 100px;
    padding: 6px 16px 6px 10px;
    font-size: 12px; font-weight: 500; color: var(--c-text);
    margin-bottom: 24px;
}
.ab-hero-pill-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--c-lime); flex-shrink: 0;
    animation: abPulse 2s infinite;
}
@keyframes abPulse { 0%,100%{opacity:1} 50%{opacity:.3} }

.ab-hero-h1 {
    font-family: var(--f-display);
    font-size: clamp(40px, 6vw, 72px);
    font-weight: 800; color: #fff;
    line-height: 1.05; letter-spacing: -.02em;
    margin-bottom: 20px;
}
.ab-hero-h1 span { color: var(--c-lime); }

.ab-hero-desc {
    font-size: 16px; color: var(--c-muted);
    max-width: 600px; margin: 0 auto 44px;
    line-height: 1.8;
}

/* Stats row */
.ab-hero-stats {
    display: inline-flex;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    border-radius: 12px; overflow: hidden; flex-wrap: wrap;
}
.ab-hstat {
    padding: 20px 32px; text-align: center;
    border-right: 1px solid var(--c-border);
    transition: background .2s;
}
.ab-hstat:last-child { border-right: none; }
.ab-hstat:hover { background: var(--c-faint); }
.ab-hstat-val {
    font-family: var(--f-display);
    font-size: clamp(22px, 2.5vw, 30px);
    font-weight: 800; color: var(--c-lime);
    display: block; line-height: 1; margin-bottom: 5px;
}
.ab-hstat-lbl {
    font-size: 10px; color: var(--c-muted);
    letter-spacing: .1em; text-transform: uppercase;
    font-family: var(--f-mono);
}
@media (max-width: 600px) {
    .ab-hstat { padding: 14px 20px; }
    .ab-hero-stats { border-radius: 10px; }
}

/* ══ 3. WHO WE ARE + MISSION ══════════════════ */
.ab-wm-sec {
    background: var(--c-surface);
    border-top: 1px solid var(--c-border);
    border-bottom: 1px solid var(--c-border);
}
.ab-wm-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 24px;
}
@media (max-width: 860px) { .ab-wm-grid { grid-template-columns: 1fr; } }

.ab-wm-card {
    background: var(--c-panel);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 40px 36px;
    position: relative; overflow: hidden;
    transition: border-color .3s;
}
.ab-wm-card::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .5;
}
.ab-wm-card:hover { border-color: rgba(125,255,0,.2); }

.ab-wm-icon {
    width: 48px; height: 48px; border-radius: 10px;
    background: var(--c-lime-dim);
    border: 1px solid rgba(125,255,0,.22);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: var(--c-lime); margin-bottom: 22px;
}
.ab-wm-card h3 {
    font-family: var(--f-display);
    font-size: clamp(20px, 2.2vw, 26px);
    font-weight: 700; color: #fff; margin-bottom: 14px;
}
.ab-wm-card > p { color: var(--c-muted); font-size: 14px; line-height: 1.85; margin-bottom: 26px; }

/* Pillar chips */
.ab-pillars { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ab-pillar {
    display: flex; align-items: center; gap: 9px;
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    border-radius: 8px; padding: 10px 13px;
    font-size: 12px; font-weight: 600; color: var(--c-text);
    transition: border-color .2s;
}
.ab-pillar:hover { border-color: rgba(125,255,0,.25); }
.ab-pillar i { color: var(--c-lime); font-size: 12px; flex-shrink: 0; }

/* Value items */
.ab-values { display: flex; flex-direction: column; gap: 10px; }
.ab-value {
    display: flex; align-items: flex-start; gap: 13px;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 9px; padding: 13px 14px;
    transition: border-color .2s, background .2s;
}
.ab-value:hover {
    border-color: rgba(125,255,0,.2);
    background: rgba(125,255,0,.03);
}
.ab-value-icon {
    width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0;
    background: var(--c-lime-dim);
    border: 1px solid rgba(125,255,0,.2);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: var(--c-lime);
}
.ab-value-label { font-size: 13px; font-weight: 700; color: var(--c-text); margin-bottom: 2px; }
.ab-value-desc  { font-size: 12px; color: var(--c-muted); line-height: 1.55; }

/* ══ 4. FOUNDERS ══════════════════════════════ */
.ab-founders-sec { background: var(--c-bg); }

.ab-founders-grid {
    max-width: 860px; margin: 48px auto 0;
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px;
}
@media (max-width: 580px) { .ab-founders-grid { grid-template-columns: 1fr; } }

.ab-founder-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
    display: flex; flex-direction: column;
    transition: border-color .25s, transform .25s, box-shadow .25s;
}
.ab-founder-card:hover {
    border-color: rgba(125,255,0,.22);
    transform: translateY(-6px);
    box-shadow: 0 20px 56px rgba(0,0,0,.5), 0 0 32px rgba(125,255,0,.08);
}

.ab-founder-top {
    padding: 32px 22px 22px; text-align: center;
    background: var(--c-panel);
    border-bottom: 1px solid var(--c-border);
    position: relative;
}
/* Top lime line */
.ab-founder-top::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .5;
}
.ab-founder-av {
    width: 88px; height: 88px; border-radius: 50%;
    border: 2px solid var(--c-lime);
    background: linear-gradient(135deg, #1a3a6e, #0d6e4e);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--f-display); font-size: 28px; font-weight: 800; color: #fff;
    margin: 0 auto 16px; overflow: hidden;
    box-shadow: 0 0 0 5px rgba(125,255,0,.1), 0 10px 28px rgba(0,0,0,.4);
    transition: transform .3s;
}
.ab-founder-card:hover .ab-founder-av { transform: scale(1.05); }
.ab-founder-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

.ab-founder-name {
    font-family: var(--f-display); font-size: 20px; font-weight: 700;
    color: #fff; margin-bottom: 4px;
}
.ab-founder-role { font-size: 12px; color: var(--c-lime); font-weight: 600; margin-bottom: 8px; }
.ab-founder-creds {
    display: inline-block; font-size: 10px; color: var(--c-muted);
    background: var(--c-surface); border: 1px solid var(--c-border2);
    border-radius: 100px; padding: 3px 12px; letter-spacing: .05em;
    font-family: var(--f-mono);
}

.ab-founder-body { padding: 20px 22px; flex: 1; display: flex; flex-direction: column; }
.ab-founder-bio {
    font-size: 13px; color: var(--c-muted); line-height: 1.8;
    flex: 1; margin-bottom: 18px;
}
.ab-founder-socials { display: flex; gap: 8px; }
.ab-founder-social {
    width: 32px; height: 32px; border-radius: 7px;
    border: 1px solid var(--c-border2);
    background: var(--c-panel);
    color: var(--c-muted); font-size: 13px;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s;
}
.ab-founder-social:hover {
    background: var(--c-lime-dim);
    border-color: rgba(125,255,0,.3);
    color: var(--c-lime); transform: translateY(-2px);
}

/* ══ 5. WORKSPACE ═════════════════════════════ */
.ab-workspace-sec {
    background: var(--c-surface);
    border-top: 1px solid var(--c-border);
    border-bottom: 1px solid var(--c-border);
}

/* Photo Slider */
.ab-ws-slider {
    max-width: 1100px; margin: 0 auto 48px;
    position: relative; border-radius: 12px; overflow: hidden;
    border: 1px solid var(--c-border2);
    box-shadow: 0 20px 60px rgba(0,0,0,.5);
}
.ab-ws-track { display: flex; transition: transform .6s cubic-bezier(.4,0,.2,1); }
.ab-ws-slide {
    min-width: 100%; position: relative;
    aspect-ratio: 16/6;
    background: var(--c-panel); flex-shrink: 0;
    overflow: hidden;
}
.ab-ws-slide img { width: 100%; height: 100%; object-fit: cover; display: block; }
.ab-ws-ph {
    width: 100%; height: 100%; display: flex;
    align-items: center; justify-content: center;
    flex-direction: column; gap: 10px;
}
.ab-ws-ph i    { font-size: 36px; color: rgba(125,255,0,.18); }
.ab-ws-ph span { font-size: 11px; color: var(--c-muted); letter-spacing: .1em; text-transform: uppercase; }

/* Gradient veil */
.ab-ws-veil {
    position: absolute; inset: 0; pointer-events: none;
    background: linear-gradient(to top, rgba(11,14,17,.85) 0%, transparent 55%);
}
/* Caption */
.ab-ws-caption {
    position: absolute; bottom: 0; left: 0; right: 0;
    padding: 20px 24px;
    display: flex; align-items: flex-end; justify-content: space-between;
    pointer-events: none;
}
.ab-ws-caption-h {
    font-family: var(--f-display); font-size: 18px;
    font-weight: 700; color: #fff; margin-bottom: 3px;
}
.ab-ws-caption-sub { font-size: 12px; color: rgba(255,255,255,.5); }
.ab-ws-badge {
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.3);
    color: var(--c-lime); font-size: 10px; font-weight: 700;
    padding: 5px 12px; border-radius: 100px; letter-spacing: .08em;
    backdrop-filter: blur(8px); white-space: nowrap; font-family: var(--f-mono);
}
/* Arrows */
.ab-ws-arrow {
    position: absolute; top: 50%; transform: translateY(-50%); z-index: 3;
    width: 38px; height: 38px; border-radius: 8px;
    background: rgba(11,14,17,.7); border: 1px solid var(--c-border2);
    color: var(--c-muted); font-size: 13px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all .2s; backdrop-filter: blur(8px);
}
.ab-ws-arrow:hover { background: var(--c-lime); color: #000; border-color: var(--c-lime); }
.ab-ws-arrow.prev { left: 14px; }
.ab-ws-arrow.next { right: 14px; }
/* Dots */
.ab-ws-dots {
    position: absolute; bottom: 14px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 6px; z-index: 3;
}
.ab-ws-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: rgba(255,255,255,.2); border: none; padding: 0;
    cursor: pointer; transition: all .3s;
}
.ab-ws-dot.on { background: var(--c-lime); width: 18px; border-radius: 3px; }
/* Counter */
.ab-ws-counter {
    position: absolute; top: 14px; right: 14px; z-index: 3;
    background: rgba(11,14,17,.65); border: 1px solid var(--c-border2);
    color: var(--c-muted); font-size: 11px; font-weight: 600;
    padding: 4px 12px; border-radius: 100px;
    backdrop-filter: blur(8px); font-family: var(--f-mono);
}

/* City office tabs */
.ab-city-tabs {
    display: flex; gap: 6px; flex-wrap: wrap;
    justify-content: center; margin-bottom: 24px;
}
.ab-city-tab {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 8px 20px; border-radius: 7px;
    border: 1px solid var(--c-border2);
    background: transparent; color: var(--c-muted);
    font-family: var(--f-sans); font-size: 12px; font-weight: 600;
    letter-spacing: .06em; text-transform: uppercase;
    cursor: pointer; transition: all .2s;
}
.ab-city-tab.on {
    background: var(--c-lime-dim);
    border-color: rgba(125,255,0,.3);
    color: var(--c-lime);
}
.ab-city-tab:hover:not(.on) { color: var(--c-text); border-color: var(--c-border2); }

.ab-city-panel { display: none; }
.ab-city-panel.on { display: block; animation: abFadeUp .4s ease both; }

.ab-city-card {
    max-width: 1100px; margin: 0 auto;
    background: var(--c-panel);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
    display: grid; grid-template-columns: 1fr 1fr;
    transition: border-color .3s;
}
.ab-city-card:hover { border-color: rgba(125,255,0,.18); }
@media (max-width: 760px) { .ab-city-card { grid-template-columns: 1fr; } }

.ab-city-photo {
    aspect-ratio: 4/3; position: relative; overflow: hidden;
    background: var(--c-surface);
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 10px;
}
.ab-city-photo img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .5s; }
.ab-city-card:hover .ab-city-photo img { transform: scale(1.04); }
.ab-city-ph-icon { font-size: 40px; color: rgba(125,255,0,.15); }
.ab-city-ph-txt  { font-size: 11px; color: var(--c-muted); letter-spacing: .1em; }

.ab-city-info { padding: 36px 32px; display: flex; flex-direction: column; justify-content: center; }
.ab-city-flag { font-size: 30px; margin-bottom: 12px; line-height: 1; }
.ab-city-name {
    font-family: var(--f-display);
    font-size: clamp(22px, 2.5vw, 30px);
    font-weight: 700; color: #fff; margin-bottom: 6px;
}
.ab-city-tag {
    display: inline-block;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 10px; font-weight: 700;
    padding: 4px 12px; border-radius: 100px; letter-spacing: .08em;
    margin-bottom: 16px;
}
.ab-city-desc { font-size: 13.5px; color: var(--c-muted); line-height: 1.8; margin-bottom: 20px; }
.ab-city-meta { display: flex; flex-direction: column; gap: 8px; }
.ab-city-meta-row {
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 13px; color: var(--c-text);
}
.ab-city-meta-row i { color: var(--c-lime); font-size: 12px; margin-top: 2px; flex-shrink: 0; width: 14px; }
@media (max-width: 560px) { .ab-city-info { padding: 24px 20px; } }

/* ══ 6. FOUNDER VISION ════════════════════════ */
.ab-vision-sec { background: var(--c-bg); }

.ab-vision-inner {
    max-width: 1100px; margin: 48px auto 0;
    display: grid; grid-template-columns: 280px 1fr;
    gap: 48px; align-items: start;
}
@media (max-width: 860px) {
    .ab-vision-inner { grid-template-columns: 1fr; gap: 32px; }
    .ab-ceo-card { position: static !important; }
}

/* CEO card */
.ab-ceo-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 36px 22px;
    text-align: center;
    position: sticky; top: 96px;
    overflow: hidden;
}
.ab-ceo-card::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .5;
}
.ab-ceo-av {
    width: 100px; height: 100px; border-radius: 50%;
    border: 2px solid var(--c-lime);
    background: linear-gradient(135deg, #1a3a6e, #0d6e4e);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--f-display); font-size: 34px; font-weight: 800; color: #fff;
    margin: 0 auto 18px; overflow: hidden;
    box-shadow: 0 0 0 6px rgba(125,255,0,.1), 0 12px 32px rgba(0,0,0,.45);
}
.ab-ceo-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }

.ab-ceo-name {
    font-family: var(--f-display); font-size: 20px;
    font-weight: 700; color: #fff; margin-bottom: 4px;
}
.ab-ceo-title { font-size: 12px; color: var(--c-lime); font-weight: 600; margin-bottom: 20px; }
.ab-ceo-divider { height: 1px; background: var(--c-border); margin: 18px 0; }
.ab-ceo-sig {
    font-family: var(--f-sans); font-style: italic;
    font-size: 20px; color: var(--c-lime); margin-bottom: 18px;
}
.ab-ceo-socials { display: flex; gap: 8px; justify-content: center; }
.ab-ceo-social {
    width: 34px; height: 34px; border-radius: 7px;
    border: 1px solid var(--c-border2);
    background: var(--c-panel); color: var(--c-muted);
    font-size: 13px; display: flex; align-items: center;
    justify-content: center; transition: all .2s;
}
.ab-ceo-social:hover {
    background: var(--c-lime-dim);
    border-color: rgba(125,255,0,.3);
    color: var(--c-lime); transform: translateY(-2px);
}

/* Vision text */
.ab-vision-text {}
.ab-vision-tag {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.25);
    color: var(--c-lime); font-size: 11px; font-weight: 700;
    padding: 6px 14px; border-radius: 100px; letter-spacing: .1em;
    text-transform: uppercase; margin-bottom: 22px;
}
.ab-vision-h {
    font-family: var(--f-display);
    font-size: clamp(24px, 3vw, 34px);
    font-weight: 800; color: #fff;
    line-height: 1.15; letter-spacing: -.01em; margin-bottom: 28px;
}
.ab-vision-h span { color: var(--c-lime); }
.ab-vision-body p {
    font-size: 14.5px; color: var(--c-muted); line-height: 1.9;
    margin-bottom: 16px;
    border-left: 2px solid var(--c-border);
    padding-left: 16px;
    transition: border-color .3s, color .2s;
}
.ab-vision-body p:hover { border-left-color: var(--c-lime); color: var(--c-text); }

/* ══ 7. CTA ═══════════════════════════════════ */
.ab-cta-sec {
    background: var(--c-surface);
    border-top: 1px solid var(--c-border);
    padding: 72px 24px;
    text-align: center; position: relative; overflow: hidden;
}
.ab-cta-sec::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 50% 60% at 50% 50%, rgba(125,255,0,.05), transparent 70%);
    pointer-events: none;
}
.ab-cta-sec::after {
    content: '';
    position: absolute; top: 0; left: 15%; right: 15%; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .4;
}
.ab-cta-h {
    font-family: var(--f-display);
    font-size: clamp(28px, 3.5vw, 42px);
    font-weight: 800; color: #fff; margin-bottom: 12px;
    position: relative;
}
.ab-cta-h span { color: var(--c-lime); }
.ab-cta-sub { font-size: 15px; color: var(--c-muted); margin-bottom: 36px; position: relative; }
.ab-cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; position: relative; }

.ab-cta-btn {
    display: inline-flex; align-items: center; gap: 9px;
    padding: 12px 26px; border-radius: 8px;
    border: 1px solid var(--c-border2);
    background: var(--c-panel);
    color: var(--c-text); font-family: var(--f-display);
    font-size: 14px; font-weight: 700; letter-spacing: .05em;
    transition: all .2s;
}
.ab-cta-btn i { font-size: 16px; }
.ab-cta-btn:hover {
    border-color: rgba(125,255,0,.35); color: var(--c-lime);
    background: var(--c-lime-dim);
    transform: translateY(-2px);
}
.ab-cta-btn.primary {
    background: var(--c-lime); border-color: var(--c-lime); color: #000;
    box-shadow: 0 0 20px rgba(125,255,0,.25);
}
.ab-cta-btn.primary:hover {
    background: #8FFF1A; border-color: #8FFF1A; color: #000;
    box-shadow: 0 0 32px rgba(125,255,0,.4);
}

/* Responsive */
@media (max-width: 768px) {
    .ab-sec { padding: 64px 20px; }
    .ab-wm-card { padding: 28px 22px; }
    .ab-hero { padding: 64px 20px 52px; }
}
</style>

<div class="ab-wrap">

{{-- ══ BREADCRUMB ══ --}}
<div class="ab-breadcrumb">
    <div class="ab-breadcrumb-inner">
        <a href="{{ route('home') }}">Home</a>
        <i class="las la-angle-right"></i>
        <span>About Us</span>
    </div>
</div>

{{-- ══════════════════════════════════════════
     1. HERO
══════════════════════════════════════════ --}}
<section class="ab-hero">
    <div class="ab-hero-orb1"></div>
    <div class="ab-hero-orb2"></div>
    <div class="ab-hero-inner">
        <div class="ab-hero-pill ab-anim">
            <span class="ab-hero-pill-dot"></span>
            Est. {{ $heroBanner['founded'] }} &nbsp;·&nbsp; {{ $heroBanner['hq'] }}
        </div>
        <h1 class="ab-hero-h1 ab-anim d1">
            About <span>CityQuants</span>
        </h1>
        <p class="ab-hero-desc ab-anim d2">
            {{ $heroBanner['tagline'] }}<br>
            {{ $heroBanner['subtitle'] }}
        </p>
        <div class="ab-hero-stats ab-anim d3">
            <div class="ab-hstat">
                <span class="ab-hstat-val">{{ $heroBanner['stat1_value'] ?? $heroBanner['users'] }}</span>
                <span class="ab-hstat-lbl">{{ $heroBanner['stat1_label'] ?? 'Active Traders' }}</span>
            </div>
            <div class="ab-hstat">
                <span class="ab-hstat-val">{{ $heroBanner['stat2_value'] ?? $heroBanner['experience'] }}</span>
                <span class="ab-hstat-lbl">{{ $heroBanner['stat2_label'] ?? 'Team Experience' }}</span>
            </div>
            <div class="ab-hstat">
                <span class="ab-hstat-val">{{ $heroBanner['stat3_value'] ?? '100+' }}</span>
                <span class="ab-hstat-lbl">{{ $heroBanner['stat3_label'] ?? 'Analytics Tools' }}</span>
            </div>
            <div class="ab-hstat">
                <span class="ab-hstat-val">{{ $heroBanner['stat4_value'] ?? '50K+' }}</span>
                <span class="ab-hstat-lbl">{{ $heroBanner['stat4_label'] ?? 'Students Trained' }}</span>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════
     2. WHO WE ARE + MISSION
══════════════════════════════════════════ --}}
<div class="ab-wm-sec ab-sec">
    <div class="ab-container">
        <div class="ab-wm-grid">

            {{-- Who We Are --}}
            <div class="ab-wm-card ab-anim d1">
                <div class="ab-wm-icon"><i class="fas fa-users"></i></div>
                <h3>{{ $whoWeAre['heading'] }}</h3>
                <p>{{ $whoWeAre['body'] }}</p>
                @if(!empty($whoWeAre['pillars']))
                <div class="ab-pillars">
                    @foreach($whoWeAre['pillars'] as $p)
                    <div class="ab-pillar">
                        <i class="fas {{ $p['icon'] }}"></i>
                        <span>{{ $p['label'] }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Mission & Vision --}}
            <div class="ab-wm-card ab-anim d2">
                <div class="ab-wm-icon"><i class="fas fa-bullseye"></i></div>
                <h3>{{ $mission['heading'] }}</h3>
                <p>{{ $mission['body'] }}</p>
                @if(!empty($mission['values']))
                <div class="ab-values">
                    @foreach($mission['values'] as $v)
                    <div class="ab-value">
                        <div class="ab-value-icon"><i class="fas {{ $v['icon'] }}"></i></div>
                        <div>
                            <div class="ab-value-label">{{ $v['label'] }}</div>
                            <div class="ab-value-desc">{{ $v['desc'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════
     3. FOUNDING MEMBERS
══════════════════════════════════════════ --}}
<section class="ab-sec ab-founders-sec">
    <div class="ab-container" style="text-align:center;">
        <div class="ab-anim">
            <div class="ab-eyebrow" style="justify-content:center;">Founding Members</div>
            <h2 class="ab-sec-title" style="text-align:center;">The Minds Behind <span>CityQuants</span></h2>
            <p class="ab-sec-sub" style="margin:14px auto 0;">
                Seasoned professionals who turned their passion for derivatives into India's most powerful options analytics platform.
            </p>
        </div>
    </div>

    <div class="ab-founders-grid">
        @foreach($ideators as $idx => $person)
        <div class="ab-founder-card ab-anim" style="animation-delay:{{ ($idx * 0.12 + 0.15) }}s">
            <div class="ab-founder-top">
                <div class="ab-founder-av">
                    @if(!empty($person['avatar']))
                        <img src="{{ $person['avatar'] }}" alt="{{ $person['name'] }}">
                    @else
                        {{ strtoupper(substr($person['name'], 0, 1)) }}
                    @endif
                </div>
                <div class="ab-founder-name">{{ $person['name'] }}</div>
                <div class="ab-founder-role">{{ $person['role'] }}</div>
                @if(!empty($person['creds']))
                <span class="ab-founder-creds">{{ $person['creds'] }}</span>
                @endif
            </div>
            <div class="ab-founder-body">
                <p class="ab-founder-bio">{{ $person['bio'] }}</p>
                <div class="ab-founder-socials">
                    <a href="{{ $person['linkedin'] ?? '#' }}" class="ab-founder-social" title="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="{{ $person['twitter'] ?? '#' }}" class="ab-founder-social" title="Twitter / X">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- ══════════════════════════════════════════
     4. OUR WORKSPACE
══════════════════════════════════════════ --}}
<section class="ab-sec ab-workspace-sec">
    <div class="ab-container" style="text-align:center;">
        <div class="ab-anim">
            <div class="ab-eyebrow" style="justify-content:center;">Our Workspace</div>
            <h2 class="ab-sec-title" style="text-align:center;">{{ $workspace['heading'] }}</h2>
            <p class="ab-sec-sub" style="margin:14px auto 0;">{{ $workspace['sub'] }}</p>
        </div>
    </div>

    {{-- Slide gallery --}}
    @if(!empty($workspace['slides']))
    <div class="ab-ws-slider ab-anim d2" style="margin-top:40px;">
        <div class="ab-ws-track" id="wsTrack">
            @foreach($workspace['slides'] as $slide)
            <div class="ab-ws-slide">
                @if(!empty($slide['src']))
                    <img src="{{ $slide['src'] }}" alt="{{ $slide['caption'] }}">
                @else
                    <div class="ab-ws-ph">
                        <i class="fas fa-image ab-ws-ph-icon"></i>
                        <span class="ab-ws-ph-txt">Workspace Photo</span>
                    </div>
                @endif
                <div class="ab-ws-veil"></div>
                <div class="ab-ws-caption">
                    <div>
                        <div class="ab-ws-caption-h">{{ $slide['caption'] }}</div>
                        <div class="ab-ws-caption-sub">{{ $slide['sub'] ?? '' }}</div>
                    </div>
                    <span class="ab-ws-badge">{{ $slide['tag'] ?? 'OFFICE' }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <button class="ab-ws-arrow prev" onclick="wsPrev()"><i class="fas fa-chevron-left"></i></button>
        <button class="ab-ws-arrow next" onclick="wsNext()"><i class="fas fa-chevron-right"></i></button>
        <div class="ab-ws-dots" id="wsDots">
            @foreach($workspace['slides'] as $i => $s)
            <button class="ab-ws-dot {{ $i===0?'on':'' }}" onclick="wsGo({{ $i }})"></button>
            @endforeach
        </div>
        <div class="ab-ws-counter" id="wsCounter">1 / {{ count($workspace['slides']) }}</div>
    </div>
    @endif

    {{-- City tabs --}}
    @if(!empty($workspace['offices']))
    <div class="ab-container" style="margin-top:40px;">
        <div class="ab-city-tabs ab-anim d3">
            @foreach($workspace['offices'] as $i => $office)
            <button class="ab-city-tab {{ $i===0?'on':'' }}" onclick="citySwitch({{ $i }},this)">
                <i class="fas fa-building"></i> {{ $office['city'] }}
            </button>
            @endforeach
        </div>
        @foreach($workspace['offices'] as $i => $office)
        <div class="ab-city-panel {{ $i===0?'on':'' }}" id="cityPanel{{ $i }}">
            <div class="ab-city-card">
                <div class="ab-city-photo">
                    @if(!empty($office['photo']))
                        <img src="{{ $office['photo'] }}" alt="{{ $office['city'] }} Office">
                    @else
                        <i class="fas fa-city ab-city-ph-icon"></i>
                        <span class="ab-city-ph-txt">{{ strtoupper($office['city']) }} OFFICE</span>
                    @endif
                </div>
                <div class="ab-city-info">
                    <div class="ab-city-flag">{{ $office['flag'] ?? '🏙️' }}</div>
                    <div class="ab-city-name">{{ $office['city'] }} Office</div>
                    <span class="ab-city-tag">{{ $office['tag'] }}</span>
                    <p class="ab-city-desc">{{ $office['desc'] }}</p>
                    <div class="ab-city-meta">
                        @if(!empty($office['address']))
                        <div class="ab-city-meta-row">
                            <i class="fas fa-location-dot"></i>
                            <span>{{ $office['address'] }}</span>
                        </div>
                        @endif
                        @if(!empty($office['team']))
                        <div class="ab-city-meta-row">
                            <i class="fas fa-users"></i>
                            <span>{{ $office['team'] }}</span>
                        </div>
                        @endif
                        @if(!empty($office['hours']))
                        <div class="ab-city-meta-row">
                            <i class="fas fa-clock"></i>
                            <span>{{ $office['hours'] }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</section>

{{-- ══════════════════════════════════════════
     5. VISION FROM FOUNDER
══════════════════════════════════════════ --}}
<section class="ab-sec ab-vision-sec">
    <div class="ab-container">
        <div class="ab-vision-inner">

            {{-- CEO card --}}
            <div class="ab-ceo-card ab-anim d1">
                <div class="ab-ceo-av">
                    @if(!empty($ceoVision['avatar']))
                        <img src="{{ $ceoVision['avatar'] }}" alt="{{ $ceoVision['name'] }}">
                    @else
                        <i class="fas fa-user-tie"></i>
                    @endif
                </div>
                <div class="ab-ceo-name">{{ $ceoVision['name'] }}</div>
                <div class="ab-ceo-title">{{ $ceoVision['title'] }}</div>
                <div class="ab-ceo-divider"></div>
                <div class="ab-ceo-sig">{{ $ceoVision['signature'] }}</div>
                <div class="ab-ceo-socials">
                    @if(!empty($ceoVision['linkedin']))
                    <a href="{{ $ceoVision['linkedin'] }}" class="ab-ceo-social" title="LinkedIn">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    @endif
                    @if(!empty($ceoVision['twitter']))
                    <a href="{{ $ceoVision['twitter'] }}" class="ab-ceo-social" title="Twitter / X">
                        <i class="fab fa-x-twitter"></i>
                    </a>
                    @endif
                    @if(!empty($ceoVision['youtube']))
                    <a href="{{ $ceoVision['youtube'] }}" class="ab-ceo-social" title="YouTube">
                        <i class="fab fa-youtube"></i>
                    </a>
                    @endif
                </div>
            </div>

            {{-- Vision text --}}
            <div class="ab-anim d2">
                <div class="ab-vision-tag">
                    <i class="fas fa-quote-left"></i> Vision From Founder
                </div>
                <h2 class="ab-vision-h">
                    The <span>Future</span> We Are<br>Building Together
                </h2>
                <div class="ab-vision-body">
                    @foreach($ceoVision['paras'] as $para)
                    <p>{{ $para }}</p>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</section>
</div>{{-- .ab-wrap --}}

{{-- ── JS: Workspace slider + city tabs — LOGIC IDENTICAL ── --}}
<script>
(function () {
    var track = document.getElementById('wsTrack');
    if (!track) return;
    var slides = track.querySelectorAll('.ab-ws-slide');
    var total  = slides.length, idx = 0, timer;

    function wsGo(i) {
        idx = (i + total) % total;
        track.style.transform = 'translateX(-' + idx + '00%)';
        document.querySelectorAll('.ab-ws-dot').forEach(function (d, j) {
            d.classList.toggle('on', j === idx);
        });
        var ctr = document.getElementById('wsCounter');
        if (ctr) ctr.textContent = (idx + 1) + ' / ' + total;
    }
    window.wsGo   = wsGo;
    window.wsNext = function () { wsGo(idx + 1); };
    window.wsPrev = function () { wsGo(idx - 1); };

    function start() { timer = setInterval(window.wsNext, 4200); }
    function stop()  { clearInterval(timer); }
    start();
    track.addEventListener('mouseenter', stop);
    track.addEventListener('mouseleave', start);

    var sx = 0;
    track.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; });
    track.addEventListener('touchend',   function (e) {
        var dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 40) { dx < 0 ? window.wsNext() : window.wsPrev(); }
    });
})();

function citySwitch(idx, btn) {
    document.querySelectorAll('.ab-city-tab').forEach(function (b) { b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.ab-city-panel').forEach(function (p, i) {
        p.classList.toggle('on', i === idx);
    });
}
</script>

@endsection