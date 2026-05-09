@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   VARIABLES — mirrors home.blade.php
========================================= */
.qa-wrap {
    --gold:    #F5A623;
    --gold2:   #FFD06A;
    --golddim: rgba(245,166,35,.1);
    --d1: #06101A;
    --d2: #091828;
    --d3: #0C2040;
    --d4: #0F2848;
    --card:  #0E1E35;
    --card2: #142540;
    --txt:   #E4EBF5;
    --muted: #7A90B5;
    --bdr:   rgba(255,255,255,.07);
    font-family: 'Exo 2', sans-serif;
    color: #E4EBF5;
    display: block;
    background: #06101A;
}
.qa-wrap * { box-sizing: border-box; }
.qa-wrap h1,.qa-wrap h2,.qa-wrap h3,.qa-wrap h4 {
    font-family: 'Rajdhani', sans-serif;
    letter-spacing: .03em;
}
.qa-wrap a { text-decoration: none; }

/* animations */
@keyframes qaFadeUp {
    from { opacity: 0; transform: translateY(32px); }
    to   { opacity: 1; transform: none; }
}
.qa-anim { animation: qaFadeUp .7s ease both; }
.qa-anim.d1 { animation-delay: .1s; }
.qa-anim.d2 { animation-delay: .2s; }
.qa-anim.d3 { animation-delay: .3s; }
.qa-anim.d4 { animation-delay: .4s; }
.qa-anim.d5 { animation-delay: .55s; }

/* section commons */
.qa-sec { padding: 80px 24px; display: block; }
.qa-title {
    display: block; text-align: center;
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(26px, 3.2vw, 38px);
    font-weight: 700; color: #F5A623; margin-bottom: 10px;
}
.qa-line {
    display: block; width: 60px; height: 3px; margin: 0 auto 14px;
    border-radius: 2px;
    background: linear-gradient(90deg, rgba(255,255,255,.6), #F5A623);
}
.qa-sub {
    display: block; text-align: center;
    color: #7A90B5; font-size: 15px; max-width: 600px;
    margin: 0 auto 52px; line-height: 1.8;
}

/* =========================================
   1. HERO BANNER
========================================= */
.qa-hero {
    position: relative; min-height: 52vh;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
    background: linear-gradient(135deg, #06101A 0%, #0F2848 60%, #06101A 100%);
}
/* animated grid lines */
.qa-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(245,166,35,.055) 1px, transparent 1px),
        linear-gradient(90deg, rgba(245,166,35,.055) 1px, transparent 1px);
    background-size: 64px 64px;
    animation: gridScroll 18s linear infinite;
    pointer-events: none;
}
@keyframes gridScroll {
    from { background-position: 0 0; }
    to   { background-position: 64px 64px; }
}
.qa-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 70% 65% at 50% 50%, rgba(245,166,35,.07) 0%, transparent 72%);
    pointer-events: none;
}
/* gold top bar */
.qa-hero-bar {
    position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, transparent, #F5A623 30%, #FFD06A 50%, #F5A623 70%, transparent);
}
.qa-hero-inner {
    position: relative; z-index: 1;
    text-align: center; padding: 60px 24px;
}
.qa-hero-badge {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(245,166,35,.12); border: 1px solid rgba(245,166,35,.3);
    color: #F5A623; font-size: 12px; font-weight: 700;
    padding: 7px 18px; border-radius: 30px; letter-spacing: .1em;
    text-transform: uppercase; margin-bottom: 22px;
}
.qa-hero-badge i { font-size: 10px; }
.qa-hero-h1 {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(38px, 6vw, 70px);
    font-weight: 700; color: #fff; line-height: 1.05;
    margin-bottom: 20px;
}
.qa-hero-h1 span { color: #F5A623; }
.qa-hero-p {
    color: #7A90B5; font-size: clamp(14px, 1.6vw, 17px);
    max-width: 640px; margin: 0 auto 44px; line-height: 1.85;
}
/* stat strip */
.qa-hero-stats {
    display: inline-flex; gap: 0; border: 1px solid rgba(245,166,35,.2);
    border-radius: 14px; overflow: hidden;
    background: rgba(0,0,0,.25); backdrop-filter: blur(10px);
    flex-wrap: wrap;
}
.qa-hstat {
    padding: 18px 32px; text-align: center; position: relative;
    border-right: 1px solid rgba(245,166,35,.15);
}
.qa-hstat:last-child { border-right: none; }
.qa-hstat-val {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(22px, 2.5vw, 30px);
    font-weight: 700; color: #F5A623; display: block; line-height: 1;
    margin-bottom: 4px;
}
.qa-hstat-lbl { font-size: 11px; color: #7A90B5; letter-spacing: .06em; text-transform: uppercase; }
@media(max-width:600px){
    .qa-hstat { padding: 14px 20px; }
    .qa-hero-stats { border-radius: 10px; }
}

/* =========================================
   2. WHO WE ARE + MISSION  (side by side)
========================================= */
.qa-wm-sec {
    background: #091828;
    padding: 80px 24px;
}
.qa-wm-inner {
    max-width: 1160px; margin: 0 auto;
    display: grid; grid-template-columns: 1fr 1fr; gap: 40px;
}
@media(max-width:860px){ .qa-wm-inner { grid-template-columns: 1fr; gap: 32px; } }
.qa-wm-card {
    background: linear-gradient(135deg, #0E1E35, #142540);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 22px; padding: 44px 38px;
    position: relative; overflow: hidden;
    transition: border-color .3s, box-shadow .3s;
}
.qa-wm-card:hover {
    border-color: rgba(245,166,35,.25);
    box-shadow: 0 0 40px rgba(245,166,35,.1);
}
.qa-wm-card::before {
    content: ''; position: absolute; top: -1px; left: 24px; right: 24px; height: 2px;
    background: linear-gradient(90deg, transparent, #F5A623, transparent);
    border-radius: 2px;
}
.qa-wm-icon {
    width: 52px; height: 52px; border-radius: 14px;
    background: rgba(245,166,35,.12); border: 1px solid rgba(245,166,35,.25);
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #F5A623; margin-bottom: 22px;
}
.qa-wm-card h3 {
    font-size: clamp(22px, 2.5vw, 28px); font-weight: 700;
    color: #fff; margin-bottom: 16px;
}
.qa-wm-card p { color: #7A90B5; font-size: 14.5px; line-height: 1.88; margin-bottom: 28px; }
/* pillars */
.qa-pillars { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.qa-pillar {
    display: flex; align-items: center; gap: 10px;
    background: rgba(245,166,35,.06); border: 1px solid rgba(245,166,35,.14);
    border-radius: 9px; padding: 10px 14px; font-size: 12.5px;
    font-weight: 600; color: #E4EBF5; letter-spacing: .03em;
}
.qa-pillar i { color: #F5A623; font-size: 13px; }
/* values */
.qa-values { display: flex; flex-direction: column; gap: 12px; }
.qa-value {
    display: flex; align-items: flex-start; gap: 14px;
    background: rgba(245,166,35,.05); border: 1px solid rgba(245,166,35,.1);
    border-radius: 10px; padding: 14px 16px;
    transition: background .25s, border-color .25s;
}
.qa-value:hover { background: rgba(245,166,35,.1); border-color: rgba(245,166,35,.25); }
.qa-value-icon {
    width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
    background: rgba(245,166,35,.15); display: flex; align-items: center;
    justify-content: center; font-size: 14px; color: #F5A623;
}
.qa-value-label { font-size: 13px; font-weight: 700; color: #E4EBF5; margin-bottom: 2px; }
.qa-value-desc  { font-size: 12px; color: #7A90B5; line-height: 1.6; }

/* =========================================
   3. FOUNDING MEMBERS
========================================= */
.qa-ideators-sec {
    background: linear-gradient(180deg, #06101A 0%, #0C2040 50%, #06101A 100%);
    padding: 80px 24px;
}
.qa-ideators-grid {
    max-width: 860px; margin: 0 auto;
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 28px;
}
@media(max-width:580px){ .qa-ideators-grid { grid-template-columns: 1fr; } }
.qa-ideator-card {
    background: linear-gradient(160deg, #0E1E35, #142540);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 22px; overflow: hidden;
    transition: transform .28s, box-shadow .28s, border-color .28s;
    display: flex; flex-direction: column;
}
.qa-ideator-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 24px 64px rgba(0,0,0,.45), 0 0 40px rgba(245,166,35,.12);
    border-color: rgba(245,166,35,.3);
}
/* avatar band */
.qa-ideator-top {
    position: relative; padding: 36px 24px 24px; text-align: center;
    background: rgba(0,0,0,.2);
    border-bottom: 1px solid rgba(255,255,255,.06);
}
.qa-ideator-top::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, transparent, #F5A623, transparent);
}
.qa-id-av {
    width: 96px; height: 96px; border-radius: 50%;
    border: 3px solid #F5A623;
    background: linear-gradient(135deg, #1a3a6e, #d4840e);
    display: flex; align-items: center; justify-content: center;
    font-size: 32px; color: #fff; margin: 0 auto 16px; overflow: hidden;
    box-shadow: 0 0 0 6px rgba(245,166,35,.12), 0 12px 32px rgba(0,0,0,.4);
    transition: transform .3s;
}
.qa-ideator-card:hover .qa-id-av { transform: scale(1.06); }
.qa-id-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.qa-id-name { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; margin-bottom: 4px; }
.qa-id-role { font-size: 13px; color: #F5A623; font-weight: 600; margin-bottom: 6px; }
.qa-id-creds {
    display: inline-block; font-size: 11px; color: #7A90B5;
    background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
    border-radius: 20px; padding: 4px 14px; letter-spacing: .05em;
}
/* bio */
.qa-ideator-body { padding: 24px 26px; flex: 1; display: flex; flex-direction: column; }
.qa-id-bio { font-size: 13.5px; color: #7A90B5; line-height: 1.85; flex: 1; margin-bottom: 20px; }
.qa-id-socials { display: flex; gap: 10px; }
.qa-id-social {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid rgba(255,255,255,.1);
    background: rgba(255,255,255,.04); color: #7A90B5; font-size: 13px;
    display: flex; align-items: center; justify-content: center;
    transition: all .22s;
}
.qa-id-social:hover { background: rgba(245,166,35,.15); border-color: #F5A623; color: #F5A623; }

/* =========================================
   4. WORKSPACE
========================================= */
.qa-workspace-sec {
    background: #091828;
    padding: 80px 24px;
}
/* ── MAIN PHOTO SLIDER ── */
.qa-ws-slider-wrap {
    max-width: 1100px; margin: 0 auto 52px;
    position: relative; border-radius: 20px; overflow: hidden;
    border: 1px solid rgba(245,166,35,.18);
    box-shadow: 0 24px 72px rgba(0,0,0,.55), 0 0 0 1px rgba(245,166,35,.08);
}
.qa-ws-track {
    display: flex;
    transition: transform .6s cubic-bezier(.4,0,.2,1);
}
.qa-ws-slide {
    min-width: 100%; position: relative;
    aspect-ratio: 16/7;
    background: linear-gradient(135deg, #0E1E35, #142540);
    flex-shrink: 0;
}
.qa-ws-slide img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}
/* gradient overlay on slide */
.qa-ws-slide-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to top, rgba(6,16,26,.85) 0%, transparent 55%);
    pointer-events: none;
}
/* slide caption */
.qa-ws-caption {
    position: absolute; bottom: 24px; left: 28px; right: 28px;
    display: flex; align-items: flex-end; justify-content: space-between;
    pointer-events: none;
}
.qa-ws-caption-left h4 {
    font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700;
    color: #fff; margin-bottom: 4px; line-height: 1.2;
}
.qa-ws-caption-left p { font-size: 13px; color: rgba(255,255,255,.55); margin: 0; }
.qa-ws-caption-badge {
    background: rgba(245,166,35,.15); border: 1px solid rgba(245,166,35,.35);
    color: #F5A623; font-size: 11px; font-weight: 700;
    padding: 5px 14px; border-radius: 20px; letter-spacing: .07em;
    backdrop-filter: blur(6px); white-space: nowrap;
}
/* placeholder slide */
.qa-ws-ph {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
    flex-direction: column; gap: 10px;
}
.qa-ws-ph i   { font-size: 36px; color: rgba(245,166,35,.25); }
.qa-ws-ph span { font-size: 12px; color: rgba(122,144,181,.35); letter-spacing: .08em; }
/* prev / next arrows */
.qa-ws-arrow {
    position: absolute; top: 50%; transform: translateY(-50%);
    z-index: 3; width: 44px; height: 44px; border-radius: 50%;
    background: rgba(6,16,26,.7); border: 1px solid rgba(245,166,35,.3);
    color: #F5A623; font-size: 15px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all .25s; backdrop-filter: blur(8px);
}
.qa-ws-arrow:hover { background: #F5A623; color: #000; border-color: #F5A623; }
.qa-ws-arrow.prev { left: 16px; }
.qa-ws-arrow.next { right: 16px; }
/* dot strip */
.qa-ws-dots {
    position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%);
    display: flex; gap: 7px; z-index: 3;
}
.qa-ws-dot {
    width: 7px; height: 7px; border-radius: 50%; border: none; padding: 0;
    background: rgba(255,255,255,.25); cursor: pointer; transition: all .3s;
}
.qa-ws-dot.on { background: #F5A623; width: 22px; border-radius: 4px; }
/* counter pill */
.qa-ws-counter {
    position: absolute; top: 16px; right: 16px; z-index: 3;
    background: rgba(6,16,26,.65); border: 1px solid rgba(255,255,255,.1);
    color: rgba(255,255,255,.6); font-size: 12px; font-weight: 600;
    padding: 5px 13px; border-radius: 20px; backdrop-filter: blur(8px);
    letter-spacing: .05em;
}

/* ── CITY OFFICE TABS ── */
.qa-city-wrap { max-width: 1100px; margin: 0 auto; }
.qa-city-tabs {
    display: flex; gap: 10px; flex-wrap: wrap;
    justify-content: center; margin-bottom: 28px;
}
.qa-city-tab {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 22px; border-radius: 30px; cursor: pointer;
    border: 1px solid rgba(255,255,255,.1);
    background: rgba(255,255,255,.04);
    color: #7A90B5; font-family: 'Rajdhani', sans-serif;
    font-size: 14px; font-weight: 700; letter-spacing: .05em;
    transition: all .25s; white-space: nowrap;
}
.qa-city-tab i { font-size: 13px; }
.qa-city-tab.on {
    background: rgba(245,166,35,.15); border-color: rgba(245,166,35,.4);
    color: #F5A623;
    box-shadow: 0 0 18px rgba(245,166,35,.15);
}
.qa-city-tab:hover:not(.on) { border-color: rgba(245,166,35,.25); color: rgba(245,166,35,.7); }
/* city panels */
.qa-city-panel { display: none; }
.qa-city-panel.on { display: block; animation: qaFadeUp .45s ease both; }
.qa-city-card {
    background: linear-gradient(135deg, #0E1E35, #142540);
    border: 1px solid rgba(245,166,35,.18);
    border-radius: 20px; overflow: hidden;
    display: grid; grid-template-columns: 1fr 1fr; gap: 0;
    box-shadow: 0 12px 48px rgba(0,0,0,.4), 0 0 0 1px rgba(245,166,35,.06);
}
@media(max-width:760px){ .qa-city-card { grid-template-columns: 1fr; } }
/* photo side */
.qa-city-photo {
    aspect-ratio: 4/3; position: relative;
    background: linear-gradient(135deg, #081220, #1a2f50);
    overflow: hidden;
}
.qa-city-photo img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .5s; }
.qa-city-card:hover .qa-city-photo img { transform: scale(1.04); }
.qa-city-ph {
    width: 100%; height: 100%; display: flex; align-items: center;
    justify-content: center; flex-direction: column; gap: 12px;
}
.qa-city-ph i   { font-size: 40px; color: rgba(245,166,35,.2); }
.qa-city-ph span { font-size: 12px; color: rgba(122,144,181,.3); letter-spacing: .08em; }
/* info side */
.qa-city-info { padding: 40px 36px; display: flex; flex-direction: column; justify-content: center; }
.qa-city-flag {
    font-size: 32px; margin-bottom: 14px; line-height: 1;
    filter: drop-shadow(0 2px 8px rgba(245,166,35,.2));
}
.qa-city-name {
    font-family: 'Rajdhani', sans-serif; font-size: clamp(24px, 2.8vw, 32px);
    font-weight: 700; color: #fff; margin-bottom: 6px;
}
.qa-city-tag {
    display: inline-block; background: #F5A623; color: #000;
    font-size: 11px; font-weight: 700; padding: 4px 12px;
    border-radius: 20px; letter-spacing: .08em; margin-bottom: 18px;
}
.qa-city-desc { font-size: 14px; color: #7A90B5; line-height: 1.82; margin-bottom: 22px; }
.qa-city-meta { display: flex; flex-direction: column; gap: 9px; }
.qa-city-meta-row {
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 13px; color: #B0C0D8;
}
.qa-city-meta-row i { color: #F5A623; font-size: 12px; margin-top: 2px; flex-shrink: 0; }
@media(max-width:560px){ .qa-city-info { padding: 28px 22px; } }

/* =========================================
   5. FOUNDER VISION
========================================= */
.qa-ceo-sec {
    background: linear-gradient(135deg, #06101A 0%, #0C2040 50%, #06101A 100%);
    padding: 80px 24px; position: relative; overflow: hidden;
}
.qa-ceo-sec::before {
    content: '"';
    position: absolute; top: -20px; left: 50%; transform: translateX(-50%);
    font-family: 'Rajdhani', sans-serif;
    font-size: 320px; color: rgba(245,166,35,.03);
    line-height: 1; pointer-events: none; user-select: none;
}
.qa-ceo-inner {
    max-width: 1060px; margin: 0 auto;
    display: grid; grid-template-columns: 320px 1fr; gap: 56px; align-items: start;
}
@media(max-width:860px){ .qa-ceo-inner { grid-template-columns: 1fr; gap: 36px; } }
/* sticky profile card */
.qa-ceo-card {
    background: linear-gradient(160deg, #0E1E35, #142540);
    border: 1px solid rgba(245,166,35,.22);
    border-radius: 22px; padding: 40px 28px; text-align: center;
    position: sticky; top: 90px;
    box-shadow: 0 0 40px rgba(245,166,35,.1);
}
.qa-ceo-av {
    width: 120px; height: 120px; border-radius: 50%;
    border: 4px solid #F5A623;
    background: linear-gradient(135deg, #1a3a6e, #d4840e);
    display: flex; align-items: center; justify-content: center;
    font-size: 40px; color: #fff; margin: 0 auto 20px; overflow: hidden;
    box-shadow: 0 0 0 8px rgba(245,166,35,.1), 0 16px 40px rgba(0,0,0,.5);
}
.qa-ceo-av img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.qa-ceo-name {
    font-family: 'Rajdhani', sans-serif; font-size: 24px;
    font-weight: 700; color: #fff; margin-bottom: 6px;
}
.qa-ceo-title { font-size: 13px; color: #F5A623; font-weight: 600; margin-bottom: 20px; }
.qa-ceo-sig {
    font-family: 'Exo 2', sans-serif; font-style: italic;
    font-size: 22px; color: #F5A623; margin-top: 22px;
    border-top: 1px solid rgba(255,255,255,.07); padding-top: 18px;
}
/* vision text */
.qa-vision-label {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(245,166,35,.1); border: 1px solid rgba(245,166,35,.25);
    color: #F5A623; font-size: 12px; font-weight: 700;
    padding: 6px 16px; border-radius: 20px; letter-spacing: .08em;
    text-transform: uppercase; margin-bottom: 28px;
}
.qa-vision-h {
    font-family: 'Rajdhani', sans-serif; font-size: clamp(26px, 3vw, 36px);
    font-weight: 700; color: #fff; margin-bottom: 28px; line-height: 1.2;
}
.qa-vision-h span { color: #F5A623; }
.qa-vision-body p {
    color: #7A90B5; font-size: 15px; line-height: 1.92;
    margin-bottom: 18px; padding-left: 18px;
    border-left: 2px solid rgba(245,166,35,.2);
    transition: border-color .3s;
}
.qa-vision-body p:hover { border-left-color: #F5A623; color: #B0C0D8; }

/* =========================================
   6. CTA
========================================= */
.qa-cta-sec {
    background: linear-gradient(105deg, #091828 0%, #0F2848 50%, #091828 100%);
    border-top: 1px solid rgba(245,166,35,.18);
    padding: 72px 24px; text-align: center; position: relative; overflow: hidden;
}
.qa-cta-sec::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 55% 70% at 50% 50%, rgba(245,166,35,.07) 0%, transparent 70%);
    pointer-events: none;
}
.qa-cta-h {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(28px, 3.5vw, 42px); font-weight: 700;
    color: #fff; margin-bottom: 10px;
}
.qa-cta-h span { color: #F5A623; }
.qa-cta-p { color: #7A90B5; font-size: 15px; margin-bottom: 38px; }
.qa-cta-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }
.qa-cta-btn {
    display: inline-flex; align-items: center; gap: 10px;
    padding: 13px 28px; border-radius: 10px;
    border: 1px solid rgba(255,255,255,.15);
    background: rgba(255,255,255,.06);
    color: #E4EBF5; font-family: 'Rajdhani', sans-serif;
    font-size: 15px; font-weight: 700; letter-spacing: .06em;
    transition: all .25s; backdrop-filter: blur(8px);
}
.qa-cta-btn i { font-size: 18px; }
.qa-cta-btn:hover {
    background: rgba(245,166,35,.18); border-color: #F5A623;
    color: #F5A623; transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(245,166,35,.2);
}
.qa-cta-btn.primary {
    background: #F5A623; border-color: #F5A623; color: #000;
    box-shadow: 0 4px 18px rgba(245,166,35,.35);
}
.qa-cta-btn.primary:hover { background: #FFD06A; border-color: #FFD06A; color: #000; }
</style>

<div class="qa-wrap">

{{-- ═══════════════════════════════════════
     1. HERO BANNER
═══════════════════════════════════════ --}}
<section class="qa-hero">
    <div class="qa-hero-bar"></div>
    <div class="qa-hero-inner qa-anim">
        <div class="qa-hero-badge"><i class="fas fa-circle" style="font-size:7px"></i> Est. {{ $heroBanner['founded'] }} &nbsp;·&nbsp; {{ $heroBanner['hq'] }}</div>
        <h1 class="qa-hero-h1">About <span>CityQuants</span></h1>
        <p class="qa-hero-p">{{ $heroBanner['tagline'] }}<br>{{ $heroBanner['subtitle'] }}</p>
        <div class="qa-hero-stats qa-anim d3">
            <div class="qa-hstat">
                <span class="qa-hstat-val">{{ $heroBanner['users'] }}</span>
                <span class="qa-hstat-lbl">Active Traders</span>
            </div>
            <div class="qa-hstat">
                <span class="qa-hstat-val">{{ $heroBanner['experience'] }}</span>
                <span class="qa-hstat-lbl">Team Experience</span>
            </div>
            <div class="qa-hstat">
                <span class="qa-hstat-val">100+</span>
                <span class="qa-hstat-lbl">Analytics Tools</span>
            </div>
            <div class="qa-hstat">
                <span class="qa-hstat-val">50K+</span>
                <span class="qa-hstat-lbl">Students Trained</span>
            </div>
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════
     2. WHO WE ARE + MISSION
═══════════════════════════════════════ --}}
<div class="qa-wm-sec">
    <div class="qa-wm-inner">
        {{-- Who Are We --}}
        <div class="qa-wm-card qa-anim d1">
            <div class="qa-wm-icon"><i class="fas fa-users"></i></div>
            <h3>{{ $whoWeAre['heading'] }}</h3>
            <p>{{ $whoWeAre['body'] }}</p>
            <div class="qa-pillars">
                @foreach($whoWeAre['pillars'] as $p)
                <div class="qa-pillar">
                    <i class="fas {{ $p['icon'] }}"></i>
                    <span>{{ $p['label'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
        {{-- Our Mission --}}
        <div class="qa-wm-card qa-anim d2">
            <div class="qa-wm-icon"><i class="fas fa-bullseye"></i></div>
            <h3>{{ $mission['heading'] }}</h3>
            <p>{{ $mission['body'] }}</p>
            <div class="qa-values">
                @foreach($mission['values'] as $v)
                <div class="qa-value">
                    <div class="qa-value-icon"><i class="fas {{ $v['icon'] }}"></i></div>
                    <div>
                        <div class="qa-value-label">{{ $v['label'] }}</div>
                        <div class="qa-value-desc">{{ $v['desc'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>


{{-- ═══════════════════════════════════════
     3. FOUNDING MEMBERS
═══════════════════════════════════════ --}}
<section class="qa-ideators-sec">
    <span class="qa-title qa-anim">Our Founding Members</span>
    <span class="qa-line qa-anim d1"></span>
    <span class="qa-sub qa-anim d2">The visionaries behind CityQuants — seasoned professionals who turned their passion for derivatives into India's most powerful options analytics platform.</span>

    <div class="qa-ideators-grid">
        @foreach($ideators as $idx => $person)
        <div class="qa-ideator-card qa-anim" style="animation-delay:{{ ($idx * 0.12 + 0.2) }}s">
            <div class="qa-ideator-top">
                <div class="qa-id-av">
                    @if(!empty($person['avatar']))
                        <img src="{{ $person['avatar'] }}" alt="{{ $person['name'] }}">
                    @else
                        <i class="fas fa-user"></i>
                    @endif
                </div>
                <div class="qa-id-name">{{ $person['name'] }}</div>
                <div class="qa-id-role">{{ $person['role'] }}</div>
                <span class="qa-id-creds">{{ $person['creds'] }}</span>
            </div>
            <div class="qa-ideator-body">
                <p class="qa-id-bio">{{ $person['bio'] }}</p>
                <div class="qa-id-socials">
                    <a href="{{ $person['linkedin'] }}" class="qa-id-social" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="{{ $person['twitter'] }}"  class="qa-id-social" title="Twitter / X"><i class="fab fa-x-twitter"></i></a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>


{{-- ═══════════════════════════════════════
     4. OUR WORKSPACE
═══════════════════════════════════════ --}}
<section class="qa-workspace-sec">
    <span class="qa-title qa-anim">{{ $workspace['heading'] }}</span>
    <span class="qa-line qa-anim d1"></span>
    <span class="qa-sub qa-anim d2">{{ $workspace['sub'] }}</span>

    {{-- ── MAIN PHOTO SLIDER ── --}}
    <div class="qa-ws-slider-wrap qa-anim d3">
        <div class="qa-ws-track" id="wsTrack">
            @foreach($workspace['slides'] as $slide)
            <div class="qa-ws-slide">
                @if(!empty($slide['src']))
                    <img src="{{ $slide['src'] }}" alt="{{ $slide['caption'] }}">
                @else
                    <div class="qa-ws-ph">
                        <i class="fas fa-image"></i>
                        <span>WORKSPACE PHOTO</span>
                    </div>
                @endif
                <div class="qa-ws-slide-overlay"></div>
                <div class="qa-ws-caption">
                    <div class="qa-ws-caption-left">
                        <h4>{{ $slide['caption'] }}</h4>
                        <p>{{ $slide['sub'] ?? '' }}</p>
                    </div>
                    <span class="qa-ws-caption-badge">{{ $slide['tag'] ?? 'HQ' }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <button class="qa-ws-arrow prev" onclick="wsPrev()"><i class="fas fa-chevron-left"></i></button>
        <button class="qa-ws-arrow next" onclick="wsNext()"><i class="fas fa-chevron-right"></i></button>
        <div class="qa-ws-dots" id="wsDots">
            @foreach($workspace['slides'] as $i => $slide)
            <button class="qa-ws-dot {{ $i===0?'on':'' }}" onclick="wsGo({{ $i }})"></button>
            @endforeach
        </div>
        <div class="qa-ws-counter" id="wsCounter">1 / {{ count($workspace['slides']) }}</div>
    </div>

    {{-- ── CITY OFFICE TABS ── --}}
    <div class="qa-city-wrap qa-anim d4">
        <div class="qa-city-tabs">
            @foreach($workspace['offices'] as $i => $office)
            <button class="qa-city-tab {{ $i===0?'on':'' }}" onclick="citySwitch({{ $i }},this)">
                <i class="fas fa-building"></i> {{ $office['city'] }}
            </button>
            @endforeach
        </div>
        @foreach($workspace['offices'] as $i => $office)
        <div class="qa-city-panel {{ $i===0?'on':'' }}" id="cityPanel{{ $i }}">
            <div class="qa-city-card">
                <div class="qa-city-photo">
                    @if(!empty($office['photo']))
                        <img src="{{ $office['photo'] }}" alt="{{ $office['city'] }} Office">
                    @else
                        <div class="qa-city-ph">
                            <i class="fas fa-city"></i>
                            <span>{{ strtoupper($office['city']) }} OFFICE</span>
                        </div>
                    @endif
                </div>
                <div class="qa-city-info">
                    <div class="qa-city-flag">{{ $office['flag'] }}</div>
                    <div class="qa-city-name">{{ $office['city'] }} Office</div>
                    <span class="qa-city-tag">{{ $office['tag'] }}</span>
                    <p class="qa-city-desc">{{ $office['desc'] }}</p>
                    <div class="qa-city-meta">
                        <div class="qa-city-meta-row"><i class="fas fa-location-dot"></i><span>{{ $office['address'] }}</span></div>
                        <div class="qa-city-meta-row"><i class="fas fa-users"></i><span>{{ $office['team'] }}</span></div>
                        <div class="qa-city-meta-row"><i class="fas fa-clock"></i><span>{{ $office['hours'] }}</span></div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>


{{-- ═══════════════════════════════════════
     5. VISION FROM FOUNDER
═══════════════════════════════════════ --}}
<section class="qa-ceo-sec">
    <div class="qa-ceo-inner">
        {{-- Profile card --}}
        <div class="qa-ceo-card qa-anim d1">
            <div class="qa-ceo-av">
                @if(!empty($ceoVision['avatar']))
                    <img src="{{ $ceoVision['avatar'] }}" alt="{{ $ceoVision['name'] }}">
                @else
                    <i class="fas fa-user-tie"></i>
                @endif
            </div>
            <div class="qa-ceo-name">{{ $ceoVision['name'] }}</div>
            <div class="qa-ceo-title">{{ $ceoVision['title'] }}</div>
            <div style="display:flex;gap:10px;justify-content:center;margin-top:14px;">
                <a href="#" class="qa-id-social" style="width:36px;height:36px;border-radius:9px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#7A90B5;font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .22s;">
                    <i class="fab fa-linkedin-in"></i></a>
                <a href="#" class="qa-id-social" style="width:36px;height:36px;border-radius:9px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#7A90B5;font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .22s;">
                    <i class="fab fa-x-twitter"></i></a>
                <a href="#" class="qa-id-social" style="width:36px;height:36px;border-radius:9px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#7A90B5;font-size:13px;display:flex;align-items:center;justify-content:center;transition:all .22s;">
                    <i class="fab fa-youtube"></i></a>
            </div>
            <div class="qa-ceo-sig">{{ $ceoVision['signature'] }}</div>
        </div>

        {{-- Vision text --}}
        <div class="qa-anim d2">
            <div class="qa-vision-label"><i class="fas fa-quote-left"></i> Vision From Founder</div>
            <h2 class="qa-vision-h">The <span>Future</span> We Are Building Together</h2>
            <div class="qa-vision-body">
                @foreach($ceoVision['paras'] as $para)
                <p>{{ $para }}</p>
                @endforeach
            </div>
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════
     6. GET THE APP CTA
═══════════════════════════════════════ --}}
<section class="qa-cta-sec">
    <h2 class="qa-cta-h qa-anim">Get The <span>App</span> Here!</h2>
    <p class="qa-cta-p qa-anim d1">Available on all major platforms — trade smarter, anywhere.</p>
    <div class="qa-cta-btns qa-anim d2">
        <a href="{{ $cta['appstore'] }}" class="qa-cta-btn primary">
            <i class="fab fa-apple"></i> App Store
        </a>
        <a href="{{ $cta['playstore'] }}" class="qa-cta-btn">
            <i class="fab fa-google-play"></i> Play Store
        </a>
        <a href="{{ $cta['webapp'] }}" class="qa-cta-btn">
            <i class="fas fa-globe"></i> Web App
        </a>
    </div>
</section>

</div>{{-- .qa-wrap --}}

<script>
/* ── WORKSPACE SLIDER ── */
(function(){
    var track = document.getElementById('wsTrack');
    if(!track) return;
    var slides = track.querySelectorAll('.qa-ws-slide');
    var dots   = document.querySelectorAll('.qa-ws-dot');
    var ctr    = document.getElementById('wsCounter');
    var total  = slides.length, idx = 0, timer;

    function wsGo(i){
        idx = (i + total) % total;
        track.style.transform = 'translateX(-' + idx + '00%)';
        dots.forEach(function(d,j){ d.classList.toggle('on', j===idx); });
        if(ctr) ctr.textContent = (idx+1) + ' / ' + total;
    }
    window.wsGo   = wsGo;
    window.wsNext = function(){ wsGo(idx+1); };
    window.wsPrev = function(){ wsGo(idx-1); };

    function start(){ timer = setInterval(window.wsNext, 4000); }
    function stop() { clearInterval(timer); }
    start();
    track.addEventListener('mouseenter', stop);
    track.addEventListener('mouseleave', start);

    /* touch swipe */
    var sx = 0;
    track.addEventListener('touchstart', function(e){ sx = e.touches[0].clientX; });
    track.addEventListener('touchend',   function(e){
        var dx = e.changedTouches[0].clientX - sx;
        if(Math.abs(dx) > 40){ dx < 0 ? window.wsNext() : window.wsPrev(); }
    });
})();

/* ── CITY OFFICE TABS ── */
function citySwitch(idx, btn){
    document.querySelectorAll('.qa-city-tab').forEach(function(b){ b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qa-city-panel').forEach(function(p, i){
        p.classList.toggle('on', i === idx);
    });
}
</script>

@endsection