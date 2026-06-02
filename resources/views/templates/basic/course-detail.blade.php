{{-- FILE: resources/views/themes/{activeTemplate}/course-detail.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — COURSE DETAIL  v2.0
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
    --c-teal:     #26A69A;
    --c-amber:    #FFA726;
    --c-purple:   #AB47BC;
    --c-green:    #66BB6A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.cd { font-family: var(--f-sans); background: var(--c-bg); min-height: 80vh; color: var(--c-text); }
.cd *, .cd *::before, .cd *::after { box-sizing: border-box; }
.cd a { text-decoration: none; color: inherit; }

@keyframes cdUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.cd-anim    { animation: cdUp .5s ease both; }
.cd-anim.d1 { animation-delay: .1s; }
.cd-anim.d2 { animation-delay: .2s; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── HERO ── */
.cd-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 0 60px; border-bottom: 1px solid var(--c-border);
}
.cd-hero::before {
    content: ''; position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
    pointer-events: none;
}
.cd-hero::after {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse 40% 80% at 5% 50%, rgba(125,255,0,.035), transparent 70%);
    pointer-events: none;
}
.cd-hero-inner {
    display: flex; gap: 48px; align-items: flex-start;
    max-width: 1200px; margin: 0 auto;
    position: relative; z-index: 1;
    padding: 44px 0 0;
}

.cd-hero-left { flex: 1; min-width: 0; padding-bottom: 40px; }

/* breadcrumb */
.cd-breadcrumb {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: var(--c-muted); margin-bottom: 16px;
    flex-wrap: wrap; font-family: var(--f-mono);
}
.cd-breadcrumb a { color: var(--c-lime); transition: opacity .2s; font-weight: 600; }
.cd-breadcrumb a:hover { opacity: .75; }
.cd-breadcrumb i { font-size: 10px; color: var(--c-border2); }
.cd-breadcrumb span { color: rgba(255,255,255,.25); }

/* hero badges */
.cd-hero-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 14px; }
.cd-hbadge {
    font-size: 10px; font-weight: 700; padding: 4px 11px; border-radius: 100px;
    letter-spacing: .07em; text-transform: uppercase;
    display: inline-flex; align-items: center; gap: 5px; font-family: var(--f-mono);
}
.cd-hbadge.ongoing  { background: rgba(255,167,38,.15); color: var(--c-amber); border: 1px solid rgba(255,167,38,.3); }
.cd-hbadge.upcoming { background: rgba(38,166,154,.12); color: #4DB6AC;        border: 1px solid rgba(38,166,154,.25); }
.cd-hbadge.recorded { background: rgba(0,184,212,.12);  color: var(--c-blue);  border: 1px solid rgba(0,184,212,.25); }
.cd-hbadge.cat      { background: var(--c-lime-dim);    color: var(--c-lime);   border: 1px solid rgba(125,255,0,.25); }
.cd-hbadge.featured { background: var(--c-lime);        color: #000; }
.cd-hbadge.cert     { background: rgba(255,167,38,.1);  color: var(--c-amber);  border: 1px solid rgba(255,167,38,.2); }

.cd-hero-title {
    font-family: var(--f-display); font-size: clamp(22px,3.5vw,38px);
    font-weight: 800; color: #fff; line-height: 1.12; margin: 0 0 14px; letter-spacing: -.015em;
}
.cd-hero-sub {
    font-size: 14px; color: var(--c-muted); line-height: 1.78;
    margin-bottom: 20px; max-width: 560px;
}

/* meta strip */
.cd-meta-strip { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 22px; }
.cd-meta-item  { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--c-muted); }
.cd-meta-item i { color: var(--c-lime); font-size: 12px; }
.cd-meta-item strong { color: var(--c-text); }

/* trainer chips in hero */
.cd-hero-trainer {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px;
    background: rgba(255,255,255,.04); border: 1px solid var(--c-border2);
    border-radius: 8px;
}

/* ── BUY CARD ── */
.cd-hero-right { flex-shrink: 0; width: 340px; }
.cd-buy-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border2);
    border-radius: 12px 12px 0 0;
    overflow: hidden; position: sticky; top: 16px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
}
.cd-buy-thumb {
    width: 100%; height: 186px; object-fit: cover; display: block;
    background: var(--c-panel);
}
.cd-buy-body { padding: 20px; }
.cd-buy-price-row { display: flex; align-items: baseline; gap: 8px; margin-bottom: 6px; }
.cd-buy-price-main { font-size: 28px; font-weight: 800; color: #fff; font-family: var(--f-display); }
.cd-buy-price-free { font-size: 24px; font-weight: 800; color: #80CBC4; font-family: var(--f-display); }
.cd-buy-price-orig { font-size: 14px; color: var(--c-muted); text-decoration: line-through; }
.cd-buy-price-disc {
    font-size: 11px; font-weight: 700; color: #80CBC4;
    background: rgba(38,166,154,.1); padding: 3px 8px; border-radius: 4px; font-family: var(--f-mono);
}
.cd-buy-cta {
    width: 100%; padding: 13px; border-radius: 9px; border: none;
    font-size: 14px; font-weight: 700; cursor: pointer; font-family: var(--f-display);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: all .25s; margin-bottom: 10px; letter-spacing: .04em;
}
.cd-buy-cta.primary {
    background: var(--c-lime); color: #000;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.cd-buy-cta.primary:hover { background: #8FFF1A; box-shadow: 0 0 30px rgba(125,255,0,.35); transform: translateY(-1px); }
.cd-buy-cta.enrolled { background: rgba(38,166,154,.12); color: #4DB6AC; cursor: default; border: 1px solid rgba(38,166,154,.25); }
.cd-buy-cta.go-watch { background: var(--c-blue); color: #000; }
.cd-buy-cta.go-watch:hover { opacity: .88; }
.cd-buy-cta.login-req { background: var(--c-blue); color: #000; }
.cd-buy-cta.login-req:hover { opacity: .88; }
.cd-buy-note {
    font-size: 11.5px; color: var(--c-muted); text-align: center;
    margin-bottom: 14px; display: flex; align-items: center; justify-content: center; gap: 5px;
    font-family: var(--f-mono);
}
.cd-buy-note i { color: var(--c-lime); }
.cd-buy-includes { border-top: 1px solid var(--c-border); padding-top: 14px; }
.cd-buy-includes h6 {
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    color: var(--c-text); margin-bottom: 10px;
}
.cd-buy-include-item {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; color: var(--c-muted); padding: 4px 0; font-family: var(--f-mono);
}
.cd-buy-include-item i { color: var(--c-blue); width: 16px; text-align: center; font-size: 12px; }

/* ── TABS BAR ── */
.cd-tabs-bar {
    background: var(--c-surface); border-bottom: 1px solid var(--c-border);
    position: sticky; top: 0; z-index: 200;
    box-shadow: 0 4px 20px rgba(0,0,0,.3);
}
.cd-tabs { display: flex; padding: 0 60px; overflow-x: auto; scrollbar-width: none; }
.cd-tabs::-webkit-scrollbar { display: none; }
.cd-tab {
    padding: 14px 22px; font-size: 13px; font-weight: 600; color: var(--c-muted);
    cursor: pointer; border: none; background: none;
    border-bottom: 2px solid transparent; margin-bottom: -1px;
    transition: all .2s; font-family: var(--f-mono); white-space: nowrap; letter-spacing: .04em;
}
.cd-tab.active { color: var(--c-lime); border-bottom-color: var(--c-lime); }
.cd-tab:hover:not(.active) { color: var(--c-text); }

/* ── MAIN LAYOUT ── */
.cd-main {
    max-width: 1200px; margin: 0 auto;
    padding: 32px 60px 72px;
    display: flex; gap: 32px;
}
.cd-left  { flex: 1; min-width: 0; }
.cd-right { flex-shrink: 0; width: 340px; }

/* section cards */
.cd-card {
    background: var(--c-surface); border-radius: 10px;
    border: 1px solid var(--c-border); margin-bottom: 20px;
    overflow: hidden; position: relative;
}
.cd-card::before {
    content: ''; position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent); opacity: .3;
}
.cd-card-header {
    padding: 16px 20px; border-bottom: 1px solid var(--c-border);
    display: flex; align-items: center; gap: 10px; background: rgba(0,0,0,.2);
}
.cd-card-header h3 { font-family: var(--f-display); font-size: 16px; font-weight: 700; color: #fff; margin: 0; }
.cd-card-header i  { color: var(--c-lime); font-size: 16px; }
.cd-card-body { padding: 20px; }

/* stats */
.cd-stats { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 20px; }
.cd-stat {
    background: var(--c-panel); border-radius: 8px; padding: 14px 12px;
    text-align: center; border: 1px solid var(--c-border);
}
.cd-stat-val { font-size: 22px; font-weight: 800; color: #fff; font-family: var(--f-display); display: block; }
.cd-stat-lbl { font-size: 11px; color: var(--c-muted); margin-top: 2px; font-family: var(--f-mono); }

/* description */
.cd-description { font-size: 14px; color: var(--c-muted); line-height: 1.85; }
.cd-description p { margin-bottom: 12px; }

/* certificate banner */
.cd-cert-banner {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 18px;
    background: rgba(255,167,38,.08); border: 1px solid rgba(255,167,38,.2);
    border-radius: 10px; margin-bottom: 20px;
}
.cd-cert-icon { font-size: 28px; color: var(--c-amber); flex-shrink: 0; }
.cd-cert-text strong { font-size: 14px; font-family: var(--f-display); color: #fff; display: block; }
.cd-cert-text span   { font-size: 12px; color: var(--c-muted); font-family: var(--f-mono); }

/* ── CURRICULUM ── */
.cd-cur-summary {
    display: flex; gap: 18px; flex-wrap: wrap; font-size: 12px;
    color: var(--c-muted); margin-bottom: 16px; padding-bottom: 14px;
    border-bottom: 1px solid var(--c-border); font-family: var(--f-mono);
}
.cd-cur-summary span { display: flex; align-items: center; gap: 5px; }
.cd-cur-summary i { color: var(--c-lime); }

/* Section accordion */
.cd-section {
    border: 1px solid var(--c-border); border-radius: 10px;
    margin-bottom: 8px; overflow: hidden; background: var(--c-surface);
}
.cd-section-header {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 16px; background: var(--c-panel);
    cursor: pointer; user-select: none; transition: background .2s;
}
.cd-section-header:hover { background: rgba(125,255,0,.04); }
.cd-section-toggle { color: var(--c-muted); font-size: 12px; transition: transform .25s; flex-shrink: 0; }
.cd-section-header.open .cd-section-toggle { transform: rotate(90deg); color: var(--c-lime); }
.cd-section-title { font-family: var(--f-display); font-size: 14px; font-weight: 700; color: var(--c-text); flex: 1; }
.cd-section-pills { display: flex; align-items: center; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
.cd-spill {
    display: inline-flex; align-items: center; gap: 4px; font-size: 10px;
    font-weight: 700; padding: 3px 9px; border-radius: 100px; white-space: nowrap;
    font-family: var(--f-mono);
}
.cd-spill.lessons  { background: rgba(0,184,212,.1);  color: var(--c-blue);  border: 1px solid rgba(0,184,212,.22); }
.cd-spill.duration { background: rgba(38,166,154,.1); color: #80CBC4;        border: 1px solid rgba(38,166,154,.22); }
.cd-spill.preview  { background: rgba(255,167,38,.1); color: var(--c-amber); border: 1px solid rgba(255,167,38,.22); cursor: pointer; transition: all .2s; }
.cd-spill.preview:hover { background: rgba(255,167,38,.2); }

/* section overview strip */
.cd-sec-preview-strip {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px;
    background: rgba(255,167,38,.06); border-bottom: 1px solid rgba(255,167,38,.15);
    cursor: pointer; transition: background .2s;
}
.cd-sec-preview-strip:hover { background: rgba(255,167,38,.1); }
.cd-sec-preview-strip .ps-icon {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--c-amber); color: #000;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0;
}
.cd-sec-preview-strip .ps-text strong { font-size: 12px; color: var(--c-amber); display: block; font-family: var(--f-display); }
.cd-sec-preview-strip .ps-text span  { font-size: 11px; color: var(--c-muted); font-family: var(--f-mono); }
.cd-sec-preview-strip .ps-arrow { margin-left: auto; color: var(--c-amber); font-size: 12px; }

.cd-section-body { display: none; border-top: 1px solid var(--c-border); }
.cd-section-body.open { display: block; }

/* lesson rows */
.cd-lesson {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 16px; border-bottom: 1px solid var(--c-border);
    transition: background .15s; position: relative;
}
.cd-lesson:last-child { border-bottom: none; }
.cd-lesson.clickable { cursor: pointer; }
.cd-lesson.clickable:hover { background: rgba(125,255,0,.03); }
.cd-lesson:not(.clickable):hover { background: rgba(255,255,255,.015); }

.cd-lesson-icon {
    width: 30px; height: 30px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 12px;
}
.cd-lesson-icon.youtube      { background: rgba(239,83,80,.12);  color: var(--c-red);  }
.cd-lesson-icon.upload       { background: rgba(0,184,212,.1);   color: var(--c-blue); }
.cd-lesson-icon.lock         { background: var(--c-panel);       color: var(--c-muted); }
.cd-lesson-icon.enrolled-play{ background: rgba(38,166,154,.12); color: #4DB6AC; }

.cd-lesson-info { flex: 1; min-width: 0; }
.cd-lesson-title {
    font-size: 13px; color: var(--c-text); line-height: 1.4;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cd-lesson-sub {
    display: flex; align-items: center; gap: 8px;
    margin-top: 3px; flex-wrap: wrap;
}
.cd-lesson-dur { font-size: 11px; color: var(--c-muted); font-family: var(--f-mono); }
.cd-lesson-preview-btn {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; color: var(--c-amber);
    background: rgba(255,167,38,.1); border: 1px solid rgba(255,167,38,.22);
    padding: 2px 8px; border-radius: 100px; cursor: pointer; transition: all .2s; font-family: var(--f-mono);
}
.cd-lesson-preview-btn:hover { background: rgba(255,167,38,.2); }
.cd-lesson-preview-btn i { font-size: 9px; }
.cd-lesson-watch-btn {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 10px; font-weight: 700; color: #4DB6AC;
    background: rgba(38,166,154,.1); border: 1px solid rgba(38,166,154,.22);
    padding: 2px 8px; border-radius: 100px; transition: all .2s;
    text-decoration: none; font-family: var(--f-mono);
}
.cd-lesson-watch-btn:hover { background: rgba(38,166,154,.2); }

/* ── TRAINERS ── */
.cd-trainers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px,1fr)); gap: 12px; }
.cd-trainer-card {
    display: flex; align-items: center; gap: 12px; padding: 14px;
    border: 1px solid var(--c-border2); border-radius: 9px; background: var(--c-panel);
    transition: border-color .2s;
}
.cd-trainer-card:hover { border-color: rgba(125,255,0,.2); }
.cd-trainer-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-weight: 700; font-size: 17px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; overflow: hidden;
}
.cd-trainer-avatar img { width: 100%; height: 100%; object-fit: cover; }
.cd-trainer-name { font-size: 13px; font-weight: 600; color: var(--c-text); }
.cd-trainer-role { font-size: 11px; color: var(--c-muted); margin-top: 2px; font-family: var(--f-mono); }

/* ── FAQs ── */
.cd-faq { border: 1px solid var(--c-border); border-radius: 8px; margin-bottom: 8px; overflow: hidden; background: var(--c-surface); }
.cd-faq-q {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 13px 18px; cursor: pointer; transition: background .2s;
}
.cd-faq-q:hover { background: rgba(255,255,255,.025); }
.cd-faq-q-text { font-size: 13px; font-weight: 600; color: var(--c-text); flex: 1; line-height: 1.4; }
.cd-faq-icon   { color: var(--c-muted); font-size: 13px; flex-shrink: 0; transition: transform .25s; }
.cd-faq.open .cd-faq-icon { transform: rotate(45deg); color: var(--c-lime); }
.cd-faq-a {
    display: none; padding: 13px 18px 15px;
    font-size: 13px; color: var(--c-muted); line-height: 1.75;
    border-top: 1px solid var(--c-border);
}
.cd-faq.open .cd-faq-a { display: block; }

/* ── includes list ── */
.cd-includes-list { list-style: none; padding: 0; margin: 0; }
.cd-includes-list li {
    display: flex; align-items: center; gap: 10px; padding: 8px 0;
    border-bottom: 1px solid var(--c-border); font-size: 12px;
    color: var(--c-muted); font-family: var(--f-mono);
}
.cd-includes-list li:last-child { border-bottom: none; }
.cd-includes-list li i { color: var(--c-blue); width: 18px; text-align: center; }

/* ── related courses ── */
.cd-related-card {
    display: flex; gap: 12px; padding: 12px;
    border: 1px solid var(--c-border); border-radius: 8px; margin-bottom: 10px;
    transition: border-color .25s; background: var(--c-surface);
}
.cd-related-card:hover { border-color: rgba(125,255,0,.2); }
.cd-related-thumb {
    width: 74px; height: 54px; border-radius: 6px; object-fit: cover;
    flex-shrink: 0; background: var(--c-panel);
}
.cd-related-title {
    font-size: 13px; font-weight: 600; color: var(--c-text); line-height: 1.35; margin-bottom: 4px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.cd-related-meta { font-size: 11px; color: var(--c-muted); display: flex; align-items: center; gap: 6px; font-family: var(--f-mono); }
.cd-related-price { font-size: 13px; color: var(--c-lime); font-weight: 700; margin-top: 4px; font-family: var(--f-display); }
.cd-right-head {
    font-family: var(--f-display); font-size: 15px; font-weight: 700;
    color: var(--c-text); margin-bottom: 14px;
    display: flex; align-items: center; gap: 8px;
}
.cd-right-head i { color: var(--c-blue); }

/* enrolled badge in curriculum */
.cd-enrolled-chip {
    background: rgba(38,166,154,.1); color: #4DB6AC; font-size: 10px; font-weight: 700;
    padding: 3px 10px; border-radius: 100px; border: 1px solid rgba(38,166,154,.22);
    font-family: var(--f-mono); display: inline-flex; align-items: center; gap: 4px;
}

/* ── VIDEO PREVIEW MODAL ── */
.cdv-overlay {
    display: none; position: fixed; inset: 0; z-index: 10000;
    background: rgba(0,0,0,.92); backdrop-filter: blur(12px);
    align-items: center; justify-content: center; padding: 20px;
}
.cdv-overlay.show { display: flex; animation: cdUp .25s ease; }
.cdv-modal {
    position: relative; width: 90%; max-width: 900px;
    background: #000; border-radius: 12px; overflow: hidden;
    box-shadow: 0 32px 80px rgba(0,0,0,.7); border: 1px solid var(--c-border2);
}
.cdv-modal.fullscreen { width: 100%; max-width: 100%; height: 100vh; border-radius: 0; }
.cdv-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 11px 16px; background: var(--c-panel); border-bottom: 1px solid var(--c-border);
}
.cdv-header-title {
    font-family: var(--f-display); font-size: 14px; font-weight: 700; color: #fff;
    display: flex; align-items: center; gap: 8px;
    max-width: calc(100% - 80px); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.cdv-badge {
    font-size: 10px; background: var(--c-amber); color: #000;
    padding: 2px 8px; border-radius: 3px; letter-spacing: .05em; flex-shrink: 0; font-family: var(--f-mono);
}
.cdv-controls { display: flex; align-items: center; gap: 6px; }
.cdv-btn {
    width: 30px; height: 30px; border-radius: 6px; border: none;
    background: rgba(255,255,255,.08); color: var(--c-text);
    font-size: 12px; cursor: pointer; display: flex; align-items: center;
    justify-content: center; transition: background .2s;
}
.cdv-btn:hover { background: rgba(255,255,255,.16); }
.cdv-btn.close { background: rgba(239,83,80,.15); color: #EF9A9A; }
.cdv-btn.close:hover { background: var(--c-red); color: #fff; }
.cdv-video-wrap { position: relative; padding-bottom: 56.25%; height: 0; background: #000; }
.cdv-modal.fullscreen .cdv-video-wrap { padding-bottom: 0; height: calc(100vh - 50px); }
.cdv-video-wrap iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
.cdv-label {
    padding: 9px 16px 12px; background: var(--c-panel);
    font-size: 11px; color: var(--c-muted); display: flex; align-items: center; gap: 6px; font-family: var(--f-mono);
}
.cdv-label i { color: var(--c-lime); }

/* ── PAYMENT OVERLAY ── */
.cd-pay-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.7); backdrop-filter: blur(8px);
    z-index: 9999; align-items: center; justify-content: center;
}
.cd-pay-overlay.show { display: flex; }
.cd-pay-modal {
    background: var(--c-surface); border: 1px solid var(--c-border2);
    border-radius: 14px; padding: 36px; max-width: 440px; width: 90%;
    text-align: center; box-shadow: 0 24px 60px rgba(0,0,0,.5);
    animation: cdUp .3s ease; position: relative;
}
.cd-pay-modal::before {
    content: ''; position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent); opacity: .4;
}
.cd-pay-modal .cd-pay-icon { font-size: 48px; margin-bottom: 16px; }
.cd-pay-modal h3 { font-family: var(--f-display); font-size: 22px; color: #fff; margin-bottom: 8px; }
.cd-pay-modal p  { font-size: 13px; color: var(--c-muted); margin-bottom: 22px; font-family: var(--f-mono); }
.cd-pay-spinner { display: none; flex-direction: column; align-items: center; gap: 12px; }
.cd-pay-spinner .spinner {
    width: 44px; height: 44px; border: 3px solid var(--c-border2);
    border-top-color: var(--c-lime); border-radius: 50%; animation: spin .8s linear infinite;
}

/* ── RESPONSIVE ── */
@media(max-width:1000px) {
    .cd-hero { padding: 0 20px; }
    .cd-hero-inner { flex-direction: column; padding: 32px 0 0; }
    .cd-hero-right { width: 100%; }
    .cd-buy-card { border-radius: 12px; margin-bottom: 24px; }
    .cd-tabs { padding: 0 16px; }
    .cd-main { flex-direction: column; padding: 20px 16px 56px; }
    .cd-right { width: 100%; }
    .cd-stats { grid-template-columns: repeat(2,1fr); }
}
@media(max-width:600px) { .cd-stats { grid-template-columns: 1fr 1fr; } .cd-trainers-grid { grid-template-columns: 1fr; } }
</style>

<div class="cd">

{{-- ═══════════════ HERO ═══════════════ --}}
<div class="cd-hero">
    <div class="cd-hero-inner">

        {{-- LEFT --}}
        <div class="cd-hero-left cd-anim">

            <div class="cd-breadcrumb">
                <a href="{{ url('/') }}">Home</a>
                <i class="fas fa-chevron-right"></i>
                <a href="{{ route('courses') }}">Courses</a>
                @if($course->category)
                <i class="fas fa-chevron-right"></i>
                <a href="{{ route('courses', ['category' => $course->category->id]) }}">{{ $course->category->name }}</a>
                @endif
                <i class="fas fa-chevron-right"></i>
                <span>{{ Str::limit($course->title, 40) }}</span>
            </div>

            <div class="cd-hero-badges">
                @if($course->is_featured)
                    <span class="cd-hbadge featured"><i class="fas fa-star"></i> Featured</span>
                @endif
                <span class="cd-hbadge {{ $course->status }}">
                    @if($course->status==='ongoing')<i class="fas fa-circle" style="font-size:8px;"></i> Live
                    @elseif($course->status==='upcoming')<i class="fas fa-clock"></i> Upcoming
                    @else<i class="fas fa-video"></i> Recorded
                    @endif
                </span>
                @if($course->category)
                    <span class="cd-hbadge cat">{{ $course->category->name }}</span>
                @endif
                @if($course->has_certificate)
                    <span class="cd-hbadge cert"><i class="fas fa-certificate"></i> Certificate Provided</span>
                @endif
            </div>

            <h1 class="cd-hero-title">{{ $course->title }}</h1>

            @if($course->short_description)
            <p class="cd-hero-sub">{{ $course->short_description }}</p>
            @endif

            <div class="cd-meta-strip">
                <div class="cd-meta-item"><i class="fas fa-signal"></i> <span>{{ ucfirst($course->level) }}</span></div>
                <div class="cd-meta-item"><i class="fas fa-globe"></i> <span>{{ ucfirst($course->mode) }}</span></div>
                <div class="cd-meta-item"><i class="fas fa-language"></i> <span>{{ ucfirst($course->language) }}</span></div>
                @if($course->sections->count())
                <div class="cd-meta-item"><i class="fas fa-layer-group"></i> <strong>{{ $course->sections->count() }}</strong>&nbsp;Sections</div>
                @endif
                @if($totalLessons)
                <div class="cd-meta-item"><i class="fas fa-play-circle"></i> <strong>{{ $totalLessons }}</strong>&nbsp;Lessons</div>
                @endif
                @if($totalDuration !== '0m')
                <div class="cd-meta-item"><i class="fas fa-clock"></i> <span>{{ $totalDuration }}</span></div>
                @endif
                <div class="cd-meta-item"><i class="fas fa-users"></i> <strong>{{ number_format($course->total_enrolled) }}</strong>&nbsp;Enrolled</div>
            </div>

            @if($course->trainers->count())
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:4px;">
                @foreach($course->trainers as $trainer)
                @php
                    $tName   = trim($trainer->firstname . ' ' . $trainer->lastname);
                    $tRole   = $trainer->employeeProfile->designation ?? 'Trainer';
                    $tAvatar = $trainer->profile_pic ? asset(getFilePath('userProfile') . '/' . $trainer->profile_pic) : null;
                    $tInit   = strtoupper(substr($trainer->firstname, 0, 1));
                @endphp
                <div class="cd-hero-trainer">
                    <div style="width:34px;height:34px;border-radius:50%;background:var(--c-lime);color:#000;font-family:var(--f-display);font-weight:700;font-size:14px;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                        @if($tAvatar)<img src="{{ $tAvatar }}" style="width:100%;height:100%;object-fit:cover;" alt="{{ $tName }}">@else{{ $tInit }}@endif
                    </div>
                    <div>
                        <div style="font-size:13px;color:var(--c-text);font-weight:600;">{{ $tName }}</div>
                        <div style="font-size:11px;color:var(--c-muted);font-family:var(--f-mono);">{{ $tRole }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- BUY CARD --}}
        <div class="cd-hero-right cd-anim d2">
            <div class="cd-buy-card">
                <img class="cd-buy-thumb"
                     src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}"
                     onerror="this.src='https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=600'">
                <div class="cd-buy-body">

                    <div class="cd-buy-price-row">
                        @if($course->type === 'free')
                            <span class="cd-buy-price-free">FREE</span>
                        @else
                            <span class="cd-buy-price-main">₹{{ number_format($course->price) }}</span>
                            @if($course->mrp && $course->mrp > $course->price)
                            <span class="cd-buy-price-orig">₹{{ number_format($course->mrp) }}</span>
                            @endif
                            @if($course->discount_label)
                            <span class="cd-buy-price-disc">{{ $course->discount_label }}</span>
                            @endif
                        @endif
                    </div>

                    @if($isEnrolled)
                        <button class="cd-buy-cta enrolled" disabled>
                            <i class="fas fa-check-circle"></i> You're Enrolled
                        </button>
                        @php
                            $firstLesson = $course->sections->first()?->lessons->sortBy('sort_order')->first()
                                        ?? $course->lessons->sortBy('sort_order')->first();
                        @endphp
                        @if($firstLesson)
                        <a href="{{ route('video.player', ['lesson' => encrypt($firstLesson->id)]) }}"
                           class="cd-buy-cta go-watch" style="text-decoration:none;">
                            <i class="fas fa-play-circle"></i> Go to Course
                        </a>
                        @endif
                        <p class="cd-buy-note"><i class="fas fa-lock-open"></i> You have full access</p>

                    @elseif(!$user)
                        <a href="{{ route('user.login') }}?redirect={{ urlencode(route('courses.detail', $course->slug)) }}"
                           class="cd-buy-cta login-req" style="text-decoration:none;">
                            <i class="fas fa-sign-in-alt"></i>
                            Login to {{ $course->type === 'free' ? 'Enroll Free' : 'Buy Now' }}
                        </a>
                        <p class="cd-buy-note"><i class="fas fa-info-circle"></i> Login required to access this course</p>

                    @elseif($course->type === 'free')
                        <button class="cd-buy-cta primary" id="enrollFreeBtn" onclick="enrollFree({{ $course->id }})">
                            <i class="fas fa-graduation-cap"></i> Enroll for Free
                        </button>
                        <p class="cd-buy-note"><i class="fas fa-gift"></i> 100% Free — No payment needed</p>

                    @elseif($gateway)
                        <button class="cd-buy-cta primary" id="buyNowBtn" onclick="initPayment({{ $course->id }})">
                            <i class="fas fa-bolt"></i> Buy Now
                        </button>
                        <p class="cd-buy-note"><i class="fas fa-shield-alt"></i> Secure payment via Razorpay</p>

                    @else
                        <button class="cd-buy-cta primary" disabled style="opacity:.5;cursor:not-allowed;">
                            <i class="fas fa-exclamation-circle"></i> Payment Unavailable
                        </button>
                        <p class="cd-buy-note" style="color:var(--c-red);"><i class="fas fa-info-circle"></i> Contact us to enroll</p>
                    @endif

                    <div class="cd-buy-includes">
                        <h6>This Course Includes:</h6>
                        @if($course->sections->count())
                        <div class="cd-buy-include-item"><i class="fas fa-layer-group"></i> {{ $course->sections->count() }} sections</div>
                        @endif
                        @if($totalLessons)
                        <div class="cd-buy-include-item"><i class="fas fa-play-circle"></i> {{ $totalLessons }} video lessons</div>
                        @endif
                        @if($totalDuration !== '0m')
                        <div class="cd-buy-include-item"><i class="fas fa-clock"></i> {{ $totalDuration }} total content</div>
                        @endif
                        <div class="cd-buy-include-item"><i class="fas fa-infinity"></i> Lifetime access</div>
                        <div class="cd-buy-include-item"><i class="fas fa-mobile-alt"></i> Mobile + Desktop</div>
                        @if($course->has_certificate)
                        <div class="cd-buy-include-item"><i class="fas fa-certificate" style="color:var(--c-amber);"></i> Certificate of completion</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ═══════════════ TABS BAR ═══════════════ --}}
<div class="cd-tabs-bar">
    <div class="cd-tabs">
        <button class="cd-tab active" onclick="cdScrollTo('overview')">Overview</button>
        @if($course->sections->count())
        <button class="cd-tab" onclick="cdScrollTo('curriculum')">Curriculum</button>
        @endif
        @if($course->trainers->count())
        <button class="cd-tab" onclick="cdScrollTo('trainers')">Trainers</button>
        @endif
        @if($course->faqs->count())
        <button class="cd-tab" onclick="cdScrollTo('faqs')">FAQs</button>
        @endif
        @if($relatedCourses->count())
        <button class="cd-tab" onclick="cdScrollTo('related')">Related</button>
        @endif
    </div>
</div>

{{-- ═══════════════ MAIN CONTENT ═══════════════ --}}
<div class="cd-main">
    <div class="cd-left">

        {{-- ── OVERVIEW ── --}}
        <div id="overview">
            <div class="cd-card cd-anim">
                <div class="cd-card-header"><i class="fas fa-chart-bar"></i><h3>Course Overview</h3></div>
                <div class="cd-card-body">
                    <div class="cd-stats">
                        @if($course->sections->count())
                        <div class="cd-stat">
                            <span class="cd-stat-val">{{ $course->sections->count() }}</span>
                            <div class="cd-stat-lbl">Sections</div>
                        </div>
                        @endif
                        <div class="cd-stat">
                            <span class="cd-stat-val">{{ $totalLessons ?: '—' }}</span>
                            <div class="cd-stat-lbl">Total Lessons</div>
                        </div>
                        <div class="cd-stat">
                            <span class="cd-stat-val">{{ $totalDuration !== '0m' ? $totalDuration : '—' }}</span>
                            <div class="cd-stat-lbl">Duration</div>
                        </div>
                        <div class="cd-stat">
                            <span class="cd-stat-val">{{ number_format($course->total_enrolled) }}</span>
                            <div class="cd-stat-lbl">Enrolled</div>
                        </div>
                    </div>

                    @if($course->has_certificate)
                    <div class="cd-cert-banner">
                        <i class="fas fa-certificate cd-cert-icon"></i>
                        <div class="cd-cert-text">
                            <strong>Certificate of Completion Available</strong>
                            <span>Complete all lessons and receive an official certificate from CityQuants.</span>
                        </div>
                    </div>
                    @endif

                    @if($course->preview_embed_id)
                    <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:10px;margin-bottom:20px;border:1px solid var(--c-border);">
                        <iframe src="https://www.youtube.com/embed/{{ $course->preview_embed_id }}?rel=0"
                                style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
                                allowfullscreen loading="lazy" title="Course Preview"></iframe>
                    </div>
                    @endif

                    @if($course->description)
                    <div class="cd-description">{!! $course->description !!}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── CURRICULUM ── --}}
        @if($course->sections->count())
        <div id="curriculum">
            <div class="cd-card cd-anim">
                <div class="cd-card-header"><i class="fas fa-layer-group"></i><h3>Course Curriculum</h3></div>
                <div class="cd-card-body">
                    <div class="cd-cur-summary">
                        <span><i class="fas fa-layer-group"></i> {{ $course->sections->count() }} Sections</span>
                        @if($totalLessons)
                        <span><i class="fas fa-play-circle"></i> {{ $totalLessons }} Lessons</span>
                        @endif
                        @if($totalDuration !== '0m')
                        <span><i class="fas fa-clock"></i> {{ $totalDuration }} Total</span>
                        @endif
                        @if($isEnrolled)
                        <span style="margin-left:auto;">
                            <span class="cd-enrolled-chip"><i class="fas fa-check-circle"></i> Enrolled — Full Access</span>
                        </span>
                        @endif
                    </div>

                    @foreach($course->sections as $sIdx => $section)
                    @php
                        $sLessons    = $section->lessons->count();
                        $sDuration   = $section->total_duration;
                        $sDurSecs    = $section->total_duration_seconds;
                        $sHasPreview = $section->has_preview
                                    && $section->preview_video_type === 'youtube'
                                    && $section->preview_embed_id;
                    @endphp
                    <div class="cd-section">
                        <div class="cd-section-header {{ $sIdx === 0 ? 'open' : '' }}" onclick="cdToggleSection(this)">
                            <i class="fas fa-chevron-right cd-section-toggle"></i>
                            <span class="cd-section-title">{{ $section->title }}</span>
                            <div class="cd-section-pills" onclick="event.stopPropagation()">
                                @if($sLessons)
                                <span class="cd-spill lessons"><i class="fas fa-play-circle"></i> {{ $sLessons }} {{ Str::plural('lesson', $sLessons) }}</span>
                                @endif
                                @if($sDurSecs > 0)
                                <span class="cd-spill duration"><i class="fas fa-clock"></i> {{ $sDuration }}</span>
                                @endif
                            </div>
                        </div>

                        @if($sHasPreview)
                        <div class="cd-sec-preview-strip"
                             onclick="openPreviewVideo('{{ $section->preview_embed_id }}','{{ addslashes($section->title) }}','Section Overview')">
                            <div class="ps-icon"><i class="fab fa-youtube"></i></div>
                            <div class="ps-text">
                                <strong>Watch Section Overview</strong>
                                <span>Free preview — see what's covered in this section</span>
                            </div>
                            <i class="fas fa-external-link-alt ps-arrow"></i>
                        </div>
                        @endif

                        <div class="cd-section-body {{ $sIdx === 0 ? 'open' : '' }}">
                            @foreach($section->lessons as $lesson)
                            @php
                                $lHasPreview = $lesson->has_preview && $lesson->preview_video_type === 'youtube' && $lesson->preview_embed_id;
                                $isWatchable = $isEnrolled;
                                $playerRoute = $isWatchable ? route('video.player', ['lesson' => encrypt($lesson->id)]) : null;
                            @endphp
                            <div class="cd-lesson {{ $isWatchable ? 'clickable' : '' }}"
                                 @if($isWatchable) onclick="window.location='{{ $playerRoute }}'" @endif>

                                @if($isWatchable)
                                    <div class="cd-lesson-icon enrolled-play">
                                        <i class="{{ $lesson->video_type === 'youtube' ? 'fab fa-youtube' : 'fas fa-play' }}"></i>
                                    </div>
                                @else
                                    <div class="cd-lesson-icon lock"><i class="fas fa-lock"></i></div>
                                @endif

                                <div class="cd-lesson-info">
                                    <div class="cd-lesson-title">{{ $lesson->title }}</div>
                                    <div class="cd-lesson-sub">
                                        @if($lesson->duration_seconds)
                                        <span class="cd-lesson-dur"><i class="fas fa-clock" style="font-size:9px;margin-right:2px;"></i> {{ $lesson->formatted_duration }}</span>
                                        @endif
                                        @if(!$isEnrolled && $lHasPreview)
                                        <span class="cd-lesson-preview-btn"
                                              onclick="event.stopPropagation();openPreviewVideo('{{ $lesson->preview_embed_id }}','{{ addslashes($lesson->title) }}','Free Preview')">
                                            <i class="fas fa-play-circle"></i> Free Preview
                                        </span>
                                        @endif
                                        @if($isWatchable)
                                        <a href="{{ $playerRoute }}" class="cd-lesson-watch-btn" onclick="event.stopPropagation()">
                                            <i class="fas fa-play"></i> Watch
                                        </a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- ── TRAINERS ── --}}
        @if($course->trainers->count())
        <div id="trainers">
            <div class="cd-card cd-anim">
                <div class="cd-card-header"><i class="fas fa-chalkboard-teacher"></i><h3>Meet the Trainers</h3></div>
                <div class="cd-card-body">
                    <div class="cd-trainers-grid">
                        @foreach($course->trainers as $trainer)
                        @php
                            $tName   = trim($trainer->firstname . ' ' . $trainer->lastname);
                            $tRole   = $trainer->employeeProfile->designation ?? 'Trainer';
                            $tAvatar = $trainer->profile_pic ? asset(getFilePath('userProfile') . '/' . $trainer->profile_pic) : null;
                            $tInit   = strtoupper(substr($trainer->firstname, 0, 1));
                        @endphp
                        <div class="cd-trainer-card">
                            <div class="cd-trainer-avatar">
                                @if($tAvatar)<img src="{{ $tAvatar }}" alt="{{ $tName }}">@else{{ $tInit }}@endif
                            </div>
                            <div>
                                <div class="cd-trainer-name">{{ $tName }}</div>
                                <div class="cd-trainer-role">{{ $tRole }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ── FAQs ── --}}
        @if($course->faqs->count())
        <div id="faqs">
            <div class="cd-card cd-anim">
                <div class="cd-card-header"><i class="fas fa-question-circle"></i><h3>Frequently Asked Questions</h3></div>
                <div class="cd-card-body" style="padding:12px 10px;">
                    @foreach($course->faqs as $faq)
                    <div class="cd-faq">
                        <div class="cd-faq-q" onclick="cdToggleFaq(this.parentElement)">
                            <span class="cd-faq-q-text">{{ $faq->question }}</span>
                            <i class="fas fa-plus cd-faq-icon"></i>
                        </div>
                        <div class="cd-faq-a">{{ $faq->answer }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </div>{{-- /.cd-left --}}

    {{-- ── RIGHT SIDEBAR ── --}}
    <div class="cd-right">
        <div class="cd-card" style="margin-bottom:20px;">
            <div class="cd-card-header"><i class="fas fa-check-circle"></i><h3>What You Get</h3></div>
            <div class="cd-card-body" style="padding:14px 18px;">
                <ul class="cd-includes-list">
                    @if($course->sections->count())
                    <li><i class="fas fa-layer-group"></i> {{ $course->sections->count() }} Course Sections</li>
                    @endif
                    @if($totalLessons)
                    <li><i class="fas fa-video"></i> {{ $totalLessons }} Video Lessons</li>
                    @endif
                    @if($totalDuration !== '0m')
                    <li><i class="fas fa-clock"></i> {{ $totalDuration }} Total Content</li>
                    @endif
                    <li><i class="fas fa-infinity"></i> Lifetime Access</li>
                    <li><i class="fas fa-mobile-alt"></i> Mobile + Desktop</li>
                    <li><i class="fas fa-file-download"></i> Downloadable Resources</li>
                    @if($course->has_certificate)
                    <li><i class="fas fa-certificate" style="color:var(--c-amber);"></i> <span style="color:var(--c-amber);font-weight:600;">Certificate of Completion</span></li>
                    @endif
                </ul>
            </div>
        </div>

        @if($relatedCourses->count())
        <div id="related">
            <div class="cd-right-head"><i class="fas fa-book-open"></i> Related Courses</div>
            @foreach($relatedCourses as $rc)
            @php
                $rcSecs = $rc->lessons->sum('duration_seconds');
                $rcH    = floor($rcSecs / 3600);
                $rcM    = floor(($rcSecs % 3600) / 60);
                $rcDur  = $rcSecs > 0 ? ($rcH > 0 ? "{$rcH}h {$rcM}m" : "{$rcM}m") : null;
            @endphp
            <a href="{{ route('courses.detail', $rc->slug) }}" class="cd-related-card">
                <img class="cd-related-thumb" src="{{ $rc->thumbnail_url }}" alt="{{ $rc->title }}"
                     onerror="this.src='https://img.freepik.com/free-vector/online-trading-concept-illustration_114360-4766.jpg?w=400'">
                <div>
                    <div class="cd-related-title">{{ $rc->title }}</div>
                    <div class="cd-related-meta">
                        <span>{{ ucfirst($rc->level) }}</span>
                        @if($rc->lessons->count())<span>&bull; {{ $rc->lessons->count() }} lessons</span>@endif
                        @if($rcDur)<span>&bull; {{ $rcDur }}</span>@endif
                        @if($rc->has_certificate)<span style="color:var(--c-amber);"><i class="fas fa-certificate"></i></span>@endif
                    </div>
                    <div class="cd-related-price">
                        @if($rc->type==='free')
                            <span style="color:#80CBC4;">FREE</span>
                        @else
                            ₹{{ number_format($rc->price) }}
                            @if($rc->discount_label)<span style="font-size:11px;color:#80CBC4;">{{ $rc->discount_label }}</span>@endif
                        @endif
                    </div>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>
</div>

</div>{{-- /.cd --}}

{{-- ═══════════════ VIDEO PREVIEW MODAL ═══════════════ --}}
<div class="cdv-overlay" id="cdvOverlay" onclick="cdvClickOutside(event)">
    <div class="cdv-modal" id="cdvModal">
        <div class="cdv-header">
            <div class="cdv-header-title">
                <span class="cdv-badge" id="cdvBadge">Preview</span>
                <span id="cdvTitle">Video Preview</span>
            </div>
            <div class="cdv-controls">
                <button class="cdv-btn" onclick="cdvToggleFs()" title="Fullscreen">
                    <i class="fas fa-expand" id="cdvFsIcon"></i>
                </button>
                <button class="cdv-btn close" onclick="cdvClose()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="cdv-video-wrap" id="cdvWrap">
            <iframe id="cdvIframe" src="" allowfullscreen allow="autoplay; encrypted-media"></iframe>
        </div>
        <div class="cdv-label">
            <i class="fas fa-lock-open"></i> Free preview — available before purchase
        </div>
    </div>
</div>

{{-- ═══════════════ PAYMENT OVERLAY ═══════════════ --}}
<div class="cd-pay-overlay" id="payOverlay">
    <div class="cd-pay-modal">
        <div id="payContent">
            <div class="cd-pay-icon">💳</div>
            <h3>Processing Payment</h3>
            <p>Connecting to payment gateway…</p>
        </div>
        <div class="cd-pay-spinner" id="paySpinner">
            <div class="spinner"></div>
            <p style="color:var(--c-muted);font-size:13px;margin:0;font-family:var(--f-mono);">Verifying your payment…</p>
        </div>
    </div>
</div>

@if($gateway)
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endif

<script>
// ── Tab scroll ────────────────────────────────────────────────────────────
function cdScrollTo(id) {
    var el = document.getElementById(id);
    if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 80, behavior: 'smooth' });
}

// ── Curriculum section toggle ─────────────────────────────────────────────
function cdToggleSection(header) {
    header.classList.toggle('open');
    var body = header.parentElement.querySelector('.cd-section-body');
    if (body) body.classList.toggle('open');
}

// ── FAQ toggle ────────────────────────────────────────────────────────────
function cdToggleFaq(el) { el.classList.toggle('open'); }

// ── Video Preview Modal ───────────────────────────────────────────────────
var cdvFs = false;
function openPreviewVideo(embedId, title, badge) {
    document.getElementById('cdvTitle').textContent = title;
    document.getElementById('cdvBadge').textContent = badge || 'Preview';
    document.getElementById('cdvIframe').src = 'https://www.youtube.com/embed/' + embedId + '?autoplay=1&rel=0&modestbranding=1';
    cdvFs = false;
    document.getElementById('cdvModal').classList.remove('fullscreen');
    document.getElementById('cdvFsIcon').className = 'fas fa-expand';
    document.getElementById('cdvOverlay').classList.add('show');
    document.body.style.overflow = 'hidden';
}
function cdvClose() {
    document.getElementById('cdvOverlay').classList.remove('show');
    document.getElementById('cdvIframe').src = '';
    document.body.style.overflow = '';
}
function cdvToggleFs() {
    cdvFs = !cdvFs;
    document.getElementById('cdvModal').classList.toggle('fullscreen', cdvFs);
    document.getElementById('cdvFsIcon').className = cdvFs ? 'fas fa-compress' : 'fas fa-expand';
}
function cdvClickOutside(e) { if (e.target === document.getElementById('cdvOverlay')) cdvClose(); }
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('cdvOverlay').classList.contains('show')) cdvClose();
});

// ── Free enroll ───────────────────────────────────────────────────────────
function enrollFree(courseId) {
    var btn = document.getElementById('enrollFreeBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enrolling…';
    fetch('/courses/' + courseId + '/pay', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showPayResult('success', '🎉 Enrolled!', data.message, data.redirect);
        } else if (data.redirect) {
            window.location.href = data.redirect;
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-graduation-cap"></i> Enroll for Free';
            alert(data.message || 'Something went wrong.');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-graduation-cap"></i> Enroll for Free';
    });
}

// ── Razorpay payment ──────────────────────────────────────────────────────
function initPayment(courseId) {
    var btn = document.getElementById('buyNowBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Please wait…'; }

    fetch('/courses/' + courseId + '/pay', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({})
    })
    .then(function(r) {
        if (r.status === 401) { return r.json().then(function(d) { window.location.href = d.redirect || '/login'; }); }
        return r.json();
    })
    .then(function(data) {
        if (!data || !data.success) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; }
            alert(data ? (data.message || 'Could not initiate payment.') : 'Error occurred.');
            return;
        }
        var rzp = new Razorpay({
            key: data.key, amount: data.amount, currency: data.currency,
            name: 'CityQuants', description: data.course_name, order_id: data.order_id,
            prefill: { name: data.user_name, email: data.user_email, contact: data.user_phone },
            theme: { color: '#7DFF00' },
            modal: {
                ondismiss: function() {
                    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; }
                }
            },
            handler: function(response) {
                document.getElementById('payContent').style.display = 'none';
                document.getElementById('paySpinner').style.display = 'flex';
                document.getElementById('payOverlay').classList.add('show');
                fetch(data.callback_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({
                        razorpay_order_id:   response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature:  response.razorpay_signature,
                        our_order_id:        data.our_order_id,
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    document.getElementById('paySpinner').style.display = 'none';
                    document.getElementById('payContent').style.display = 'block';
                    showPayResult(res.success ? 'success' : 'error',
                        res.success ? '🎉 Payment Successful!' : '❌ Verification Failed',
                        res.message, res.redirect);
                })
                .catch(function() {
                    document.getElementById('paySpinner').style.display = 'none';
                    document.getElementById('payContent').style.display = 'block';
                    showPayResult('error', 'Error', 'Could not verify payment. Please contact support.');
                });
            }
        });
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; }
        rzp.on('payment.failed', function(resp) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; }
            showPayResult('error', '❌ Payment Failed', 'Reason: ' + (resp.error.description || 'Unknown error'));
        });
        rzp.open();
    })
    .catch(function() {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; }
    });
}

function showPayResult(type, title, message, redirectUrl) {
    var payContent = document.getElementById('payContent');
    var icon       = type === 'success' ? '✅' : '❌';
    var btnStyle   = 'display:inline-block;margin-top:12px;padding:12px 28px;font-weight:700;border-radius:8px;font-size:13px;font-family:var(--f-display);letter-spacing:.04em;';
    var btnHtml    = redirectUrl
        ? '<a href="' + redirectUrl + '" style="' + btnStyle + 'background:var(--c-lime);color:#000;text-decoration:none;">Go to Course</a>'
        : '<button onclick="document.getElementById(\'payOverlay\').classList.remove(\'show\')" style="' + btnStyle + 'background:var(--c-panel);color:var(--c-text);border:1px solid var(--c-border2);cursor:pointer;">Close</button>';
    payContent.innerHTML = '<div class="cd-pay-icon">' + icon + '</div><h3>' + title + '</h3><p>' + message + '</p>' + btnHtml;
    document.getElementById('payOverlay').classList.add('show');
    if (redirectUrl) setTimeout(function() { window.location.href = redirectUrl; }, 2500);
}

document.getElementById('payOverlay').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show');
});
</script>

@endsection