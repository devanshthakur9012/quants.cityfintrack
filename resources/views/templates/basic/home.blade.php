@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   VARIABLES
========================================= */
.qh-wrap {
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
    --sgold: 0 0 28px rgba(245,166,35,.18);
    font-family: 'Exo 2', sans-serif;
    color: #E4EBF5;
    display: block;
    background: #06101A;
}
.qh-wrap * { box-sizing: border-box; }
.qh-wrap h1,.qh-wrap h2,.qh-wrap h3,.qh-wrap h4 {
    font-family: 'Rajdhani', sans-serif;
    letter-spacing: .03em;
}
.qh-wrap a { text-decoration: none; }

/* === ANIMATIONS: start visible, animate in === */
.qh-anim {
    animation: qhFadeUp .7s ease both;
}
.qh-anim.d1 { animation-delay: .1s; }
.qh-anim.d2 { animation-delay: .2s; }
.qh-anim.d3 { animation-delay: .3s; }
.qh-anim.d4 { animation-delay: .4s; }
@keyframes qhFadeUp {
    from { opacity:0; transform:translateY(28px); }
    to   { opacity:1; transform:none; }
}

/* section commons */
.qh-sec { padding: 72px 24px; display:block; }
.qh-title {
    display:block; text-align:center;
    font-family:'Rajdhani',sans-serif;
    font-size: clamp(26px,3.2vw,36px);
    font-weight:700; color:#F5A623; margin-bottom:10px;
}
.qh-line {
    display:block; width:60px; height:3px; margin:0 auto 22px; border-radius:2px;
    background: linear-gradient(90deg, rgba(255,255,255,.6), #F5A623);
}

/* =========================================
   1. HERO
========================================= */
.qh-hero {
    position:relative; min-height:90vh;
    display:flex; align-items:center; justify-content:center;
    overflow:hidden; background:#06101A;
}
.qh-hero-video {
    position:absolute; inset:0;
    width:100%; height:100%; object-fit:cover; z-index:0;
}
.qh-hero-overlay {
    position:absolute; inset:0; z-index:1;
    background: linear-gradient(135deg, rgba(6,16,26,.7) 0%, rgba(6,16,26,.55) 40%, rgba(6,16,26,.88) 100%);
}
.qh-hero-inner {
    position:relative; z-index:2;
    display:flex; align-items:center; justify-content:space-between;
    max-width:1200px; width:100%; padding:0 48px; gap:40px; flex-wrap:wrap;
}
/* device mockup */
.qh-hero-devices {
    flex:1; min-width:280px; max-width:520px;
    background:rgba(255,255,255,.03);
    border:1px solid rgba(255,255,255,.06);
    border-radius:16px; aspect-ratio:16/9;
    display:flex; align-items:center; justify-content:center;
    overflow:hidden; position:relative; padding:20px;
}
.qh-dev-row { display:flex; gap:6px; align-items:flex-end; margin-bottom:6px; }
.qh-dev-bar { border-radius:3px 3px 0 0; animation: devBarPulse 2.2s infinite alternate; }
@keyframes devBarPulse { from{opacity:.4} to{opacity:.9} }

/* right text */
.qh-hero-right { flex:1; min-width:280px; text-align:left; }
.qh-hero-h1 {
    font-family:'Rajdhani',sans-serif;
    font-size:clamp(42px,5.5vw,72px);
    font-weight:700; line-height:1.05; color:#fff; margin-bottom:24px;
}
.qh-hero-h1 span { color:#F5A623; }
.qh-hero-avail {
    display:inline-block; background:#F5A623; color:#000;
    font-family:'Rajdhani',sans-serif; font-size:18px; font-weight:700;
    padding:8px 28px; border-radius:7px; letter-spacing:.06em;
    margin-bottom:6px; box-shadow:0 4px 18px rgba(245,166,35,.35);
}
.qh-hero-url { color:rgba(255,255,255,.5); font-size:14px; margin-bottom:28px; letter-spacing:.04em; }
.qh-store-row { display:flex; gap:12px; flex-wrap:wrap; }
.qh-store-btn {
    display:inline-flex; align-items:center; gap:8px;
    padding:10px 20px; border-radius:9px;
    border:1px solid rgba(255,255,255,.18);
    background:rgba(255,255,255,.06);
    color:#E4EBF5; font-size:13px; font-weight:600;
    letter-spacing:.05em; transition:all .25s; backdrop-filter:blur(8px);
}
.qh-store-btn i { font-size:16px; }
.qh-store-btn:hover {
    background:rgba(245,166,35,.15); border-color:#F5A623;
    color:#F5A623; transform:translateY(-2px);
}
.qh-scroll-cue {
    position:absolute; bottom:28px; left:50%; transform:translateX(-50%);
    z-index:2; color:rgba(255,255,255,.3); font-size:20px;
    animation: scb 2s infinite;
}
@keyframes scb {
    0%,100%{ transform:translateX(-50%) translateY(0); }
    50%    { transform:translateX(-50%) translateY(8px); }
}
@media(max-width:768px){
    .qh-hero-inner { flex-direction:column; padding:70px 24px 50px; }
    .qh-hero-right { text-align:center; }
    .qh-store-row  { justify-content:center; }
}

/* =========================================
   2. PLATFORM BANNER + CERT SLIDER
========================================= */
.qh-platform {
    display:block;
    background: linear-gradient(105deg, #091828 0%, #0F2848 50%, #091828 100%);
    border-top:1px solid rgba(245,166,35,.2);
    border-bottom:1px solid rgba(245,166,35,.2);
    padding:56px 24px 48px; text-align:center; position:relative; overflow:hidden;
}
.qh-platform::before {
    content:''; position:absolute; inset:0;
    background:radial-gradient(ellipse 55% 60% at 50% 50%, rgba(245,166,35,.06) 0%, transparent 70%);
    pointer-events:none;
}
.qh-platform h2 {
    font-size:clamp(20px,3vw,32px); font-weight:700;
    color:#F5A623; margin-bottom:10px; position:relative;
}
.qh-platform-line {
    display:block; width:60px; height:3px; margin:0 auto 14px; border-radius:2px;
    background:linear-gradient(90deg,rgba(255,255,255,.6),#F5A623);
}
.qh-platform > p { color:#7A90B5; font-size:16px; margin-bottom:40px; position:relative; }

/* cert slider */
.qh-cert-slider { max-width:900px; margin:0 auto; position:relative; }
.qh-cert-overflow { overflow:hidden; border-radius:18px; }
.qh-cert-track { display:flex; transition:transform .55s cubic-bezier(.4,0,.2,1); }
.qh-cert-slide { min-width:100%; }
.qh-cert-card {
    background: linear-gradient(120deg, rgba(12,24,44,.98), rgba(18,36,68,.98));
    border:1px solid rgba(245,166,35,.25); border-radius:18px;
    padding:36px 44px; display:flex; align-items:center; gap:40px; flex-wrap:wrap;
    box-shadow:0 0 28px rgba(245,166,35,.18); position:relative; overflow:hidden;
}
.qh-cert-left { flex:1; min-width:220px; text-align:left; }
.qh-cert-left h3 {
    font-size:clamp(24px,3vw,30px); font-weight:700;
    color:#fff; line-height:1.2; margin-bottom:18px;
}
.qh-cert-left h3 span { color:#F5A623; }
.qh-cert-badge-pill {
    display:inline-flex; align-items:center;
    background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.2);
    color:rgba(255,255,255,.8); font-size:13px;
    font-style:italic; font-weight:600;
    padding:8px 20px; border-radius:30px; letter-spacing:.03em;
}
.qh-cert-right { text-align:center; flex-shrink:0; }
.qh-lang-pill {
    display:inline-block; background:#F5A623; color:#000;
    font-family:'Rajdhani',sans-serif; font-weight:700; font-size:16px;
    padding:8px 26px; border-radius:24px; margin-bottom:24px;
    letter-spacing:.05em; box-shadow:0 4px 14px rgba(245,166,35,.32);
}
.qh-trainers { display:flex; gap:36px; justify-content:center; flex-wrap:wrap; }
.qh-trainer { text-align:center; }
.qh-trainer-av {
    width:80px; height:80px; border-radius:50%;
    border:3px solid #F5A623;
    background:linear-gradient(135deg,#1a3a6e,#d4840e);
    display:flex; align-items:center; justify-content:center;
    margin:0 auto 10px; font-size:28px; color:#fff; overflow:hidden;
    box-shadow:0 0 0 5px rgba(245,166,35,.14), 0 8px 24px rgba(0,0,0,.4);
    transition:transform .3s;
}
.qh-trainer-av:hover { transform:scale(1.06); }
.qh-trainer-av img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.qh-trainer-name { font-size:14px; font-weight:700; color:#fff; margin-bottom:3px; }
.qh-trainer-role { font-size:12px; color:#7A90B5; }
.qh-cert-dots { display:flex; justify-content:center; gap:8px; margin-top:22px; }
.qh-cert-dot {
    width:8px; height:8px; border-radius:50%; border:none; padding:0;
    background:rgba(255,255,255,.2); cursor:pointer; transition:all .3s;
}
.qh-cert-dot.on { background:#F5A623; width:26px; border-radius:4px; }

/* =========================================
   3. ABOUT THE APP
========================================= */
.qh-about { background:#091828; }
.qh-about-sub {
    display:block; text-align:center;
    font-family:'Rajdhani',sans-serif;
    font-size:clamp(18px,2.5vw,24px); font-weight:600;
    color:#F5A623; margin-bottom:44px; font-style:italic;
}
.qh-about-inner {
    max-width:1100px; margin:0 auto;
    display:grid; grid-template-columns:1.1fr 1fr; gap:52px; align-items:center;
}
@media(max-width:820px){ .qh-about-inner{ grid-template-columns:1fr; gap:36px; } }
.qh-about-video {
    position:relative; border-radius:16px; overflow:hidden;
    aspect-ratio:16/9; border:1px solid rgba(255,255,255,.07);
    box-shadow:0 20px 60px rgba(0,0,0,.55); display:block;
}
.qh-about-video iframe { width:100%; height:100%; border:none; display:block; }
.qh-about-video-ring {
    position:absolute; inset:-3px; border-radius:19px;
    border:2px solid rgba(245,166,35,.2); pointer-events:none;
}
.qh-stat-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.qh-stat-box {
    background:linear-gradient(135deg,#0E1E35,#142540);
    border:1px solid rgba(255,255,255,.07); border-radius:14px;
    padding:24px 18px; text-align:center;
    transition:transform .25s, box-shadow .25s, border-color .25s;
}
.qh-stat-box:hover {
    transform:translateY(-4px);
    box-shadow:0 0 28px rgba(245,166,35,.18);
    border-color:rgba(245,166,35,.28);
}
.qh-stat-val {
    font-family:'Rajdhani',sans-serif;
    font-size:clamp(26px,3vw,34px); font-weight:700;
    color:#E4EBF5; line-height:1; margin-bottom:6px;
}
.qh-stat-lbl { font-size:13px; font-weight:600; color:#7A90B5; margin-bottom:3px; }
.qh-stat-sub { font-size:11px; color:rgba(122,144,181,.7); line-height:1.4; }

/* =========================================
   4. FEATURE TOOLS
========================================= */
.qh-features { background:linear-gradient(180deg,#06101A 0%,#0C2040 100%); }
.qh-feat-tagline {
    display:block; text-align:center;
    font-family:'Rajdhani',sans-serif;
    font-size:clamp(18px,2.5vw,26px); font-weight:600;
    color:#E4EBF5; margin-bottom:52px;
}
.qh-feat-tagline span { color:#F5A623; }
.qh-feat-3col {
    max-width:1080px; margin:0 auto;
    display:grid; grid-template-columns:230px 1fr 1fr;
    border:1px solid rgba(255,255,255,.07); border-radius:20px; overflow:hidden;
    background:linear-gradient(135deg,#0E1E35,#142540);
}
@media(max-width:900px){ .qh-feat-3col{ grid-template-columns:1fr; } }
/* util col */
.qh-util-col { background:rgba(0,0,0,.22); border-right:1px solid rgba(255,255,255,.07); padding:28px 0; }
.qh-util-head {
    font-family:'Rajdhani',sans-serif; font-size:14px; font-weight:700;
    color:#F5A623; letter-spacing:.12em; text-transform:uppercase; padding:0 22px; margin-bottom:18px;
}
.qh-util-btn {
    display:flex; align-items:center; justify-content:space-between;
    width:100%; padding:13px 22px; background:transparent; border:none;
    border-left:3px solid transparent; cursor:pointer;
    font-family:'Exo 2',sans-serif; font-size:13px; font-weight:700;
    text-transform:uppercase; letter-spacing:.06em; color:#7A90B5; transition:all .22s;
}
.qh-util-btn.on { background:rgba(245,166,35,.1); color:#F5A623; border-left-color:#F5A623; }
.qh-util-btn:hover { background:rgba(245,166,35,.06); color:rgba(245,166,35,.8); }
.qh-util-cnt {
    font-size:12px; font-weight:700; padding:2px 9px; border-radius:10px;
    background:#F5A623; color:#000; min-width:28px; text-align:center;
}
.qh-util-btn:not(.on) .qh-util-cnt { background:rgba(255,255,255,.1); color:#7A90B5; }
/* phone col */
.qh-phone-col {
    display:flex; align-items:center; justify-content:center;
    padding:36px 20px; background:rgba(0,0,0,.14);
    border-right:1px solid rgba(255,255,255,.07);
}
.qh-phone-frame {
    width:185px; height:350px;
    background:linear-gradient(180deg,#081220,#0d1e35);
    border-radius:32px; border:2px solid rgba(255,255,255,.12);
    box-shadow:0 28px 72px rgba(0,0,0,.7);
    overflow:hidden; padding:18px 10px 26px;
    display:flex; flex-direction:column; gap:4px;
}
.qh-phone-notch { width:56px; height:6px; background:rgba(255,255,255,.07); border-radius:3px; margin:0 auto 14px; flex-shrink:0; }
.qh-phone-chart { display:flex; align-items:flex-end; flex:1; gap:4px; padding:0 4px; }
.qh-pbar { flex:1; border-radius:2px 2px 0 0; animation:pBarAni 2s infinite alternate; }
@keyframes pBarAni { from{opacity:.45} to{opacity:1} }
/* tool col */
.qh-tool-col { padding:40px 36px; min-height:350px; display:flex; flex-direction:column; justify-content:center; }
.qh-tool-name { font-family:'Rajdhani',sans-serif; font-size:30px; font-weight:700; color:#F5A623; margin-bottom:8px; }
.qh-tool-divider { width:44px; height:3px; background:linear-gradient(90deg,rgba(255,255,255,.5),#F5A623); border-radius:2px; margin-bottom:22px; }
.qh-tool-icon { font-size:56px; color:#F5A623; margin-bottom:22px; opacity:.85; display:block; }
.qh-tool-pt {
    display:flex; align-items:flex-start; gap:10px;
    font-size:14px; color:#7A90B5; line-height:1.65; margin-bottom:12px;
}
.qh-tool-pt i { color:#F5A623; margin-top:3px; font-size:12px; flex-shrink:0; }

/* =========================================
   5. LEARNING
========================================= */
.qh-learning { background:linear-gradient(135deg,#06101A 0%,#0C2040 100%); }
.qh-learn-card {
    max-width:1040px; margin:0 auto;
    background:linear-gradient(135deg,#0E1E35,#142540);
    border:1px solid rgba(255,255,255,.07); border-radius:20px;
    display:flex; overflow:hidden; min-height:320px;
}
.qh-ltabs {
    flex:0 0 155px; background:rgba(0,0,0,.28);
    border-right:1px solid rgba(255,255,255,.07); padding:32px 0;
}
.qh-ltab {
    display:block; width:100%; padding:14px 22px; border:none;
    background:transparent; color:#7A90B5; text-align:left;
    font-family:'Rajdhani',sans-serif; font-size:15px; font-weight:600;
    cursor:pointer; transition:all .22s; letter-spacing:.04em;
    border-left:3px solid transparent;
}
.qh-ltab.on { color:#F5A623; border-left-color:#F5A623; background:rgba(245,166,35,.07); }
.qh-ltab:hover { color:rgba(245,166,35,.8); }
.qh-lpanels { flex:1; padding:36px 32px; }
.qh-lpanel { display:none; }
.qh-lpanel.on { display:flex; gap:32px; align-items:center; flex-wrap:wrap; }
.qh-ltext { flex:1; min-width:220px; }
.qh-lpanel-title {
    font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700;
    color:#E4EBF5; margin-bottom:6px;
    border-bottom:2px solid #F5A623; display:inline-block; padding-bottom:4px;
}
.qh-lhl {
    display:inline-block; background:#F5A623; color:#000;
    font-weight:700; font-size:13px; padding:7px 18px;
    border-radius:5px; margin:14px 0; letter-spacing:.04em;
}
.qh-ltext p { color:#7A90B5; font-size:14.5px; line-height:1.78; margin-bottom:22px; }
.qh-lbtn {
    display:inline-block; background:#F5A623; color:#000; border:none;
    padding:11px 32px; border-radius:7px;
    font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700;
    cursor:pointer; letter-spacing:.06em; transition:all .25s;
    box-shadow:0 4px 14px rgba(245,166,35,.25);
}
.qh-lbtn:hover { background:#FFD06A; transform:translateY(-2px); color:#000; }
/* youtube card */
.qh-lyvid { flex:0 0 310px; }
.qh-yt-card { background:#0a0a0f; border-radius:14px; overflow:hidden; box-shadow:0 12px 40px rgba(0,0,0,.55); border:1px solid rgba(255,255,255,.07); }
.qh-yt-hd { display:flex; align-items:center; gap:8px; padding:10px 14px; background:#111118; border-bottom:1px solid rgba(255,255,255,.06); }
.qh-yt-hd-logo { width:30px; height:30px; border-radius:50%; background:#F5A623; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; color:#000; flex-shrink:0; }
.qh-yt-hd-info { flex:1; }
.qh-yt-hd-ch { font-size:12px; font-weight:600; color:#fff; }
.qh-yt-hd-sub { font-size:10px; color:rgba(255,255,255,.35); }
.qh-yt-hd-badge { background:#C00; color:#fff; font-size:9px; font-weight:700; padding:3px 7px; border-radius:3px; letter-spacing:.06em; display:flex; align-items:center; gap:3px; }
.qh-yt-thumb { width:100%; aspect-ratio:16/9; position:relative; cursor:pointer; overflow:hidden; background:#0d1015; }
.qh-yt-thumb-bg { width:100%; height:100%; background:linear-gradient(135deg,#120a18,#1e1030,#0a1020); display:flex; flex-direction:column; justify-content:flex-end; padding:14px 16px; position:relative; }
.qh-yt-vtitle { font-family:'Rajdhani',sans-serif; font-size:17px; font-weight:700; color:#fff; line-height:1.2; margin-bottom:3px; text-transform:uppercase; }
.qh-yt-vtitle span { color:#F5A623; }
.qh-yt-play { position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:48px; height:48px; border-radius:50%; background:rgba(204,0,0,.9); display:flex; align-items:center; justify-content:center; color:#fff; font-size:17px; box-shadow:0 4px 16px rgba(204,0,0,.45); transition:transform .2s; }
.qh-yt-thumb:hover .qh-yt-play { transform:translate(-50%,-50%) scale(1.1); }
.qh-yt-iframe-wrap { display:none; width:100%; aspect-ratio:16/9; }
.qh-yt-iframe-wrap iframe { width:100%; height:100%; border:none; display:block; }
.qh-yt-ft { display:flex; align-items:center; justify-content:space-between; padding:9px 14px; background:#111118; border-top:1px solid rgba(255,255,255,.06); }
.qh-yt-ft-meta { display:flex; align-items:center; gap:6px; font-size:10px; color:rgba(255,255,255,.4); }
.qh-yt-ft-meta i { color:#F5A623; }
.qh-yt-ft-watch { display:flex; align-items:center; gap:5px; font-size:10px; color:rgba(255,255,255,.45); }
.qh-yt-ft-watch i { color:#FF0000; font-size:13px; }
@media(max-width:860px){
    .qh-learn-card { flex-direction:column; }
    .qh-ltabs { flex:none; display:flex; border-right:none; border-bottom:1px solid rgba(255,255,255,.07); }
    .qh-ltab { border-left:none; border-bottom:3px solid transparent; padding:12px 18px; }
    .qh-ltab.on { border-bottom-color:#F5A623; border-left:none; }
    .qh-lpanel.on { flex-direction:column; }
    .qh-lyvid { flex:none; width:100%; }
}

/* =========================================
   6. TESTIMONIALS
========================================= */
.qh-testimonials { background:linear-gradient(180deg,#0C2040 0%,#091828 100%); }
.qh-tslider { max-width:1120px; margin:0 auto; position:relative; overflow:hidden; }
.qh-ttrack { display:flex; gap:24px; transition:transform .5s cubic-bezier(.4,0,.2,1); padding:54px 8px 8px; }
.qh-tcard {
    max-width:calc(33.333% - 16px); flex-shrink:0;
    background:#fff; border-radius:20px; padding:28px 24px 24px;
    position:relative; box-shadow:0 6px 28px rgba(0,0,0,.22);
    transition:transform .3s, box-shadow .3s;
}
.qh-tcard:hover { transform:translateY(-6px); box-shadow:0 16px 44px rgba(0,0,0,.35); }
.qh-tcard-av {
    width:70px; height:70px; border-radius:50%;
    border:3px solid #F5A623;
    background:linear-gradient(135deg,#1a3a6e,#d4840e);
    display:flex; align-items:center; justify-content:center;
    font-size:24px; color:#fff;
    position:absolute; top:-35px; left:50%; transform:translateX(-50%);
    overflow:hidden; box-shadow:0 6px 18px rgba(0,0,0,.3);
}
.qh-tcard-av img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.qh-tcard-body { margin-top:24px; text-align:center; }
.qh-tcard-text { font-size:13.5px; color:#3a4a68; line-height:1.76; margin-bottom:18px; }
.qh-tcard-name { font-size:16px; font-weight:700; color:#1a2a48; margin-bottom:5px; }
.qh-tcard-stars { color:#F5A623; font-size:14px; }
.qh-tnav { display:flex; justify-content:center; align-items:center; gap:14px; margin-top:32px; }
.qh-tarrow { width:40px; height:40px; border-radius:50%; background:rgba(245,166,35,.1); border:1px solid rgba(245,166,35,.28); color:#F5A623; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all .25s; font-size:14px; }
.qh-tarrow:hover { background:#F5A623; color:#000; }
.qh-tdots { display:flex; gap:8px; align-items:center; }
.qh-tdot { width:8px; height:8px; border-radius:50%; border:none; padding:0; background:rgba(255,255,255,.2); cursor:pointer; transition:all .3s; }
.qh-tdot.on { background:#F5A623; width:24px; border-radius:4px; }
@media(max-width:880px){ .qh-tcard{ max-width:calc(50% - 12px); } }
@media(max-width:560px){ .qh-tcard{ max-width:100%; } }
</style>

<div class="qh-wrap">

{{-- ═══════════════════════════════════════
     1. HERO
═══════════════════════════════════════ --}}
<section class="qh-hero">
    <video class="qh-hero-video" autoplay muted loop playsinline preload="auto">
        <source src="{{ $hero['video_url'] }}" type="video/mp4">
    </video>
    <div class="qh-hero-overlay"></div>
    <div class="qh-hero-inner">
        {{-- <div class="qh-hero-devices qh-anim">
            <div style="width:100%">
                @php
                $rows = [
                    [['#27AE60','70%'],['#E74C3C','45%'],['#F5A623','80%'],['#3498DB','55%'],['#27AE60','65%'],['#E74C3C','38%'],['#F5A623','90%']],
                    [['#3498DB','50%'],['#27AE60','85%'],['#E74C3C','30%'],['#F5A623','72%'],['#9B59B6','60%'],['#27AE60','40%'],['#3498DB','88%']],
                    [['#F5A623','62%'],['#E74C3C','78%'],['#27AE60','44%'],['#3498DB','95%'],['#F5A623','55%'],['#E74C3C','70%'],['#27AE60','35%']],
                ];
                @endphp
                @foreach($rows as $ri => $row)
                <div class="qh-dev-row">
                    @foreach($row as $bi => $b)
                    <div class="qh-dev-bar" style="background:{{ $b[0] }};height:{{ $b[1] }};flex:1;animation-delay:{{ ($ri*7+$bi)*0.14 }}s;"></div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </div> --}}
        <div class="qh-hero-right qh-anim d2">
            <h1 class="qh-hero-h1">Complex <span>Option</span><br>Simplified</h1>
            <div class="qh-hero-avail">Available on</div>
            <p class="qh-hero-url">{{ $hero['app_url'] }}</p>
            <div class="qh-store-row">
                <a href="{{ $hero['appstore'] }}" class="qh-store-btn"><i class="fab fa-apple"></i> APP STORE</a>
                <a href="{{ $hero['playstore'] }}" class="qh-store-btn"><i class="fab fa-google-play"></i> PLAY STORE</a>
                <a href="{{ $hero['webapp'] }}" class="qh-store-btn"><i class="fas fa-globe"></i> WEB APP</a>
            </div>
        </div>
    </div>
    <div class="qh-scroll-cue"><i class="fas fa-chevron-down"></i></div>
</section>


{{-- ═══════════════════════════════════════
     2. PLATFORM BANNER + CERT SLIDER
═══════════════════════════════════════ --}}
<div class="qh-platform qh-anim">
    <h2>{{ $platform['title'] }}</h2>
    <span class="qh-platform-line"></span>
    <p>{{ $platform['subtitle'] }}</p>
    <div class="qh-cert-slider">
        <div class="qh-cert-overflow">
            <div class="qh-cert-track" id="certTrack">
                @foreach($certBanners as $cert)
                <div class="qh-cert-slide">
                    <div class="qh-cert-card">
                        <div class="qh-cert-left">
                            <h3>{!! $cert['title'] !!}</h3>
                            <div class="qh-cert-badge-pill">{{ $cert['badge'] }}</div>
                        </div>
                        <div class="qh-cert-right">
                            <div class="qh-lang-pill">{{ $cert['lang'] }}</div>
                            <div class="qh-trainers">
                                @foreach($cert['trainers'] as $trainer)
                                <div class="qh-trainer">
                                    <div class="qh-trainer-av">
                                        @if(!empty($trainer['avatar']))<img src="{{ $trainer['avatar'] }}" alt="{{ $trainer['name'] }}">@else<i class="fas fa-user"></i>@endif
                                    </div>
                                    <div class="qh-trainer-name">{{ $trainer['name'] }}</div>
                                    <div class="qh-trainer-role">{{ $trainer['role'] }}</div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @if(count($certBanners) > 1)
        <div class="qh-cert-dots" id="certDots">
            @foreach($certBanners as $i => $c)
            <button class="qh-cert-dot {{ $i===0?'on':'' }}" onclick="certGo({{ $i }})"></button>
            @endforeach
        </div>
        @endif
    </div>
</div>


{{-- ═══════════════════════════════════════
     3. ABOUT THE APP
═══════════════════════════════════════ --}}
<section class="qh-sec qh-about">
    <span class="qh-title qh-anim">About the App</span>
    <span class="qh-line qh-anim d1"></span>
    <span class="qh-about-sub qh-anim d2">{!! $about['title'] !!}</span>
    <div class="qh-about-inner">
        <div class="qh-about-video qh-anim d1">
            <iframe src="{{ $about['video_url'] }}"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen title="About Quantsapp"></iframe>
            <div class="qh-about-video-ring"></div>
        </div>
        <div class="qh-stat-grid qh-anim d2">
            @foreach($about['stats'] as $stat)
            <div class="qh-stat-box">
                <div class="qh-stat-val">{{ $stat['value'] }}</div>
                <div class="qh-stat-lbl">{{ $stat['label'] }}</div>
                <div class="qh-stat-sub">{{ $stat['sub'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════
     4. FEATURE TOOLS
═══════════════════════════════════════ --}}
<section class="qh-sec qh-features">
    <span class="qh-title qh-anim">{{ $features['title'] }}</span>
    <span class="qh-line qh-anim d1"></span>
    <span class="qh-feat-tagline qh-anim d2">
        Analyze | Backtest | Optimize | Manage your <span>Option</span> Trades
    </span>
    <div class="qh-feat-3col qh-anim d3">
        <div class="qh-util-col">
            <div class="qh-util-head">Utilities</div>
            @foreach($features['utilities'] as $i => $u)
            <button class="qh-util-btn {{ $i===0?'on':'' }}" onclick="featSwitch({{ $i }},this)">
                <span>{{ $u['count'] }} {{ strtoupper($u['label']) }}</span>
                <span class="qh-util-cnt">{{ $u['count'] }}</span>
            </button>
            @endforeach
        </div>
        <div class="qh-phone-col">
            <div class="qh-phone-frame">
                <div class="qh-phone-notch"></div>
                <div class="qh-phone-chart">
                    <div class="qh-pbar" style="height:42%;background:#E74C3C;animation-delay:0s"></div>
                    <div class="qh-pbar" style="height:75%;background:#F5A623;animation-delay:.18s"></div>
                    <div class="qh-pbar" style="height:57%;background:#27AE60;animation-delay:.36s"></div>
                    <div class="qh-pbar" style="height:91%;background:#3498DB;animation-delay:.09s"></div>
                    <div class="qh-pbar" style="height:46%;background:#E74C3C;animation-delay:.27s"></div>
                    <div class="qh-pbar" style="height:68%;background:#F5A623;animation-delay:.45s"></div>
                    <div class="qh-pbar" style="height:95%;background:#27AE60;animation-delay:.18s"></div>
                    <div class="qh-pbar" style="height:54%;background:#9B59B6;animation-delay:.36s"></div>
                </div>
            </div>
        </div>
        <div class="qh-tool-col">
            @foreach($features['utilities'] as $i => $u)
            <div id="qhTool{{ $i }}" style="{{ $i===0?'display:block':'display:none' }}">
                <div class="qh-tool-name">{{ $u['tool_title'] }}</div>
                <div class="qh-tool-divider"></div>
                <span class="qh-tool-icon"><i class="fas {{ $u['tool_icon'] }}"></i></span>
                @foreach($u['tool_points'] as $pt)
                <div class="qh-tool-pt"><i class="fas fa-circle-dot"></i><span>{{ $pt }}</span></div>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════
     5. LEARNING
═══════════════════════════════════════ --}}
<section class="qh-sec qh-learning">
    <span class="qh-title qh-anim">{{ $learning['title'] }}</span>
    <span class="qh-line qh-anim d1"></span>
    <div class="qh-learn-card qh-anim d2">
        <div class="qh-ltabs">
            @foreach($learning['tabs'] as $i => $tab)
            <button class="qh-ltab {{ $i===0?'on':'' }}" onclick="learnSwitch({{ $i }},this)">{{ $tab['tab'] }}</button>
            @endforeach
        </div>
        <div class="qh-lpanels">
            @foreach($learning['tabs'] as $i => $tab)
            <div class="qh-lpanel {{ $i===0?'on':'' }}">
                <div class="qh-ltext">
                    <div class="qh-lpanel-title">{{ $tab['tab'] }}</div>
                    <div class="qh-lhl">{{ $tab['highlight'] }}</div>
                    <p>{{ $tab['description'] }}</p>
                    <a href="{{ $tab['btn_url'] }}" class="qh-lbtn">{{ $tab['btn_label'] }}</a>
                </div>
                <div class="qh-lyvid">
                    <div class="qh-yt-card">
                        <div class="qh-yt-hd">
                            <div class="qh-yt-hd-logo">Q</div>
                            <div class="qh-yt-hd-info">
                                <div class="qh-yt-hd-ch">Positional Options Trading</div>
                                <div class="qh-yt-hd-sub">Quantsapp</div>
                            </div>
                            <div class="qh-yt-hd-badge"><i class="fas fa-circle" style="font-size:6px"></i> LIVE WEBINAR</div>
                        </div>
                        <div class="qh-yt-thumb" onclick="ytPlay('{{ $tab['video_id'] }}',{{ $i }})">
                            <div class="qh-yt-thumb-bg">
                                <div class="qh-yt-vtitle">{{ strtoupper($tab['video_title']) }}<br><span>{{ $tab['video_sub'] }}</span></div>
                            </div>
                            <div class="qh-yt-play"><i class="fab fa-youtube"></i></div>
                        </div>
                        <div class="qh-yt-iframe-wrap" id="ytframe{{ $i }}">
                            <iframe src="" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                        </div>
                        <div class="qh-yt-ft">
                            <div class="qh-yt-ft-meta"><i class="far fa-calendar-alt"></i> {{ $tab['video_date'] }} &nbsp; {{ $tab['video_time'] }}</div>
                            <div class="qh-yt-ft-watch">Watch on <i class="fab fa-youtube"></i> YouTube</div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════
     6. TESTIMONIALS
═══════════════════════════════════════ --}}
<section class="qh-sec qh-testimonials">
    <span class="qh-title qh-anim">Hear from our Users!</span>
    <span class="qh-line qh-anim d1"></span>
    <div class="qh-tslider qh-anim d2">
        <div class="qh-ttrack" id="tTrack">
            @foreach($testimonials as $t)
            <div class="qh-tcard">
                <div class="qh-tcard-av">
                    @if(!empty($t['avatar']))<img src="{{ $t['avatar'] }}" alt="{{ $t['name'] }}">@else<i class="fas fa-user"></i>@endif
                </div>
                <div class="qh-tcard-body">
                    <p class="qh-tcard-text">{{ $t['review'] }}</p>
                    <div class="qh-tcard-name">{{ $t['name'] }}</div>
                    <div class="qh-tcard-stars">@for($s=0;$s<($t['rating']??5);$s++)<i class="fas fa-star"></i>@endfor</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    <div class="qh-tnav">
        <button class="qh-tarrow" onclick="tPrev()"><i class="fas fa-chevron-left"></i></button>
        <div class="qh-tdots" id="tDots"></div>
        <button class="qh-tarrow" onclick="tNext()"><i class="fas fa-chevron-right"></i></button>
    </div>
</section>

</div>{{-- .qh-wrap --}}

<script>
/* CERT SLIDER */
var _ci=0, _ct=document.querySelectorAll('.qh-cert-slide').length;
function certGo(i){
    _ci=i;
    document.getElementById('certTrack').style.transform='translateX(-'+i+'00%)';
    document.querySelectorAll('.qh-cert-dot').forEach(function(d,j){d.classList.toggle('on',j===i);});
}
if(_ct>1){setInterval(function(){certGo((_ci+1)%_ct);},4500);}

/* FEATURE SWITCH */
var _totalTools = {{ count($features['utilities']) }};
function featSwitch(idx,btn){
    document.querySelectorAll('.qh-util-btn').forEach(function(b){b.classList.remove('on');});
    btn.classList.add('on');
    for(var x=0;x<_totalTools;x++){
        var el=document.getElementById('qhTool'+x);
        if(el) el.style.display=(x===idx)?'block':'none';
    }
}

/* LEARN SWITCH */
function learnSwitch(idx,btn){
    document.querySelectorAll('.qh-ltab').forEach(function(b){b.classList.remove('on');});
    btn.classList.add('on');
    document.querySelectorAll('.qh-lpanel').forEach(function(p,i){p.classList.toggle('on',i===idx);});
}

/* YOUTUBE PLAY */
function ytPlay(vid,idx){
    var thumb=event.currentTarget;
    thumb.style.display='none';
    var fw=document.getElementById('ytframe'+idx);
    fw.style.display='block';
    fw.querySelector('iframe').src='https://www.youtube.com/embed/'+vid+'?autoplay=1&rel=0';
}

/* TESTIMONIALS */
(function(){
    var track=document.getElementById('tTrack');
    if(!track)return;
    var cards=track.querySelectorAll('.qh-tcard');
    var dotsEl=document.getElementById('tDots');
    var total=cards.length, idx=0, timer;
    var pv=window.innerWidth>880?3:window.innerWidth>560?2:1;
    var maxIdx=Math.max(0,total-pv);
    var dots=[];
    for(var i=0;i<=maxIdx;i++){
        (function(ii){
            var d=document.createElement('button');
            d.className='qh-tdot'+(ii===0?' on':'');
            d.onclick=function(){tGoTo(ii);};
            dotsEl.appendChild(d); dots.push(d);
        })(i);
    }
    function tGoTo(i){
        idx=Math.max(0,Math.min(i,maxIdx));
        var cw=cards[0].offsetWidth+24;
        track.style.transform='translateX(-'+(idx*cw)+'px)';
        dots.forEach(function(d,j){d.classList.toggle('on',j===idx);});
    }
    window.tNext=function(){tGoTo(idx<maxIdx?idx+1:0);};
    window.tPrev=function(){tGoTo(idx>0?idx-1:maxIdx);};
    function startT(){timer=setInterval(window.tNext,4200);}
    function stopT(){clearInterval(timer);}
    startT();
    track.addEventListener('mouseenter',stopT);
    track.addEventListener('mouseleave',startT);
    var sx=0;
    track.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;});
    track.addEventListener('touchend',function(e){var dx=e.changedTouches[0].clientX-sx;if(Math.abs(dx)>40){dx<0?window.tNext():window.tPrev();}});
    window.addEventListener('resize',function(){
        var npv=window.innerWidth>880?3:window.innerWidth>560?2:1;
        if(npv!==pv){pv=npv;maxIdx=Math.max(0,total-pv);tGoTo(0);}
    });
})();
</script>

@endsection