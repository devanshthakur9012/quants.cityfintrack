@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   VARIABLES
========================================= */
.qev-wrap { box-sizing: border-box; }
.qev-wrap * { box-sizing: border-box; }

:root {
    --gold:    #F5A623;
    --gold2:   #FFD06A;
    --dark:    #0D1B2A;
    --card-bg: #ffffff;
    --bg-page: #f4f6fb;
    --txt:     #1a1a2e;
    --muted:   #667;
    --bdr:     #e5e9f2;
}

@keyframes evFadeUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: none; }
}
.ev-anim     { animation: evFadeUp .65s ease both; }
.ev-anim.d1  { animation-delay: .1s; }
.ev-anim.d2  { animation-delay: .2s; }
.ev-anim.d3  { animation-delay: .3s; }
.ev-anim.d4  { animation-delay: .4s; }

@keyframes pulseDot2 {
    0%,100% { transform: scale(1);   opacity: 1; }
    50%     { transform: scale(.6);  opacity: .4; }
}

/* =========================================
   HERO
========================================= */
.qev-hero {
    background: linear-gradient(135deg, #0D1B2A 0%, #162844 60%, #1a3560 100%);
    padding: 64px 48px 52px;
    position: relative; overflow: hidden;
}
.qev-hero::before {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse at 70% 50%, rgba(245,166,35,.13) 0%, transparent 65%);
    pointer-events: none;
}
.qev-hero-inner {
    max-width: 1200px; margin: 0 auto;
    display: flex; align-items: center; gap: 40px;
}
.qev-hero-text { flex: 1; }
.qev-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(245,166,35,.15); border: 1px solid rgba(245,166,35,.35);
    border-radius: 30px; padding: 6px 16px; margin-bottom: 20px;
    font-size: 12px; font-weight: 700; color: var(--gold);
    letter-spacing: .1em; text-transform: uppercase;
}
.qev-hero-dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--gold);
    animation: pulseDot2 1.4s ease infinite;
}
.qev-hero h1 {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(34px, 5vw, 58px); font-weight: 700;
    color: #fff; margin: 0 0 16px; line-height: 1.05;
}
.qev-hero h1 span { color: var(--gold); }
.qev-hero p {
    font-size: 15px; color: rgba(255,255,255,.62);
    line-height: 1.75; max-width: 560px; margin: 0 0 28px;
}
.qev-hero-stats {
    display: flex; gap: 32px; flex-wrap: wrap;
}
.qev-stat-box {
    display: flex; flex-direction: column;
}
.qev-stat-val {
    font-family: 'Rajdhani', sans-serif;
    font-size: 28px; font-weight: 700; color: var(--gold); line-height: 1;
}
.qev-stat-lbl { font-size: 12px; color: rgba(255,255,255,.5); margin-top: 2px; }

.qev-hero-img { flex-shrink: 0; width: 280px; }
.qev-hero-img img { width: 100%; object-fit: contain; display: block; filter: drop-shadow(0 12px 40px rgba(0,0,0,.4)); }

@media(max-width:768px){
    .qev-hero { padding: 40px 20px 36px; }
    .qev-hero-inner { flex-direction: column; }
    .qev-hero-img { width: 180px; }
    .qev-hero-stats { gap: 20px; }
}

/* =========================================
   FILTER BAR
========================================= */
.qev-filter-bar {
    background: #fff; border-bottom: 1px solid var(--bdr);
    padding: 0 48px;
    box-shadow: 0 2px 10px rgba(0,0,0,.05);
     top: 0; z-index: 100;
}
.qev-tabs-row { display: flex; gap: 0; border-bottom: 2px solid #f0f0f0; }
.qev-tab {
    padding: 15px 22px; font-size: 14px; font-weight: 600; color: #888;
    cursor: pointer; border: none; background: none;
    border-bottom: 3px solid transparent; margin-bottom: -2px;
    transition: all .2s; font-family: 'Exo 2', sans-serif; white-space: nowrap;
}
.qev-tab.on { color: var(--gold); border-bottom-color: var(--gold); }
.qev-tab:hover:not(.on) { color: #333; }

.qev-filter-row {
    display: flex; align-items: flex-end; gap: 14px;
    padding: 13px 0 12px; flex-wrap: wrap;
}
.qev-filter-group { display: flex; flex-direction: column; gap: 3px; }
.qev-filter-label { font-size: 10.5px; color: #999; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
.qev-filter-select {
    border: 1px solid #ddd; border-radius: 6px;
    padding: 7px 28px 7px 10px; font-size: 13px; color: #333;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23bbb'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance: none; cursor: pointer; font-family: 'Exo 2', sans-serif;
    outline: none; min-width: 90px; transition: border-color .2s;
}
.qev-filter-select:focus { border-color: var(--gold); }

.qev-search-wrap {
    display: flex; overflow: hidden; border: 1px solid #ddd;
    border-radius: 6px; margin-left: auto;
}
.qev-search-input {
    border: none; padding: 8px 14px; font-size: 13px; color: #333;
    outline: none; width: 200px; font-family: 'Exo 2', sans-serif;
}
.qev-search-btn {
    background: var(--gold); border: none; padding: 0 16px;
    color: #fff; cursor: pointer; display: flex; align-items: center; font-size: 13px;
}

.qev-pills-row {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding: 10px 0 14px; border-top: 1px solid #f5f5f5;
}
.qev-pill {
    padding: 5px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    border: 1px solid #e0e0e0; background: #fafafa; color: #555;
    cursor: pointer; transition: all .2s; font-family: 'Exo 2', sans-serif; white-space: nowrap;
}
.qev-pill.on, .qev-pill:hover {
    background: rgba(245,166,35,.12); border-color: rgba(245,166,35,.5); color: #b87800;
}
@media(max-width:768px){ .qev-filter-bar { padding: 0 16px; } }

/* =========================================
   CONTENT AREA
========================================= */
.qev-content { background: var(--bg-page); padding: 36px 48px 72px; min-height: 60vh; }
@media(max-width:768px){ .qev-content { padding: 24px 16px 56px; } }

.qev-section-head {
    display: flex; align-items: center; gap: 14px; margin-bottom: 24px;
}
.qev-section-head h2 {
    font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700;
    color: var(--txt); margin: 0; white-space: nowrap;
}
.qev-section-head::after {
    content: ''; flex: 1; height: 2px;
    background: linear-gradient(90deg, var(--gold) 0%, transparent 100%);
    border-radius: 2px;
}

/* =========================================
   FEATURED EVENT (big card top)
========================================= */
.qev-featured {
    background: linear-gradient(135deg, #0D1B2A, #1a3560);
    border-radius: 16px; overflow: hidden;
    display: grid; grid-template-columns: 1fr 340px;
    margin-bottom: 36px; border: 1px solid rgba(245,166,35,.2);
    box-shadow: 0 8px 40px rgba(0,0,0,.12);
    position: relative;
}
.qev-featured::before {
    content: ''; position: absolute; inset: 0;
    background: radial-gradient(ellipse at 80% 50%, rgba(245,166,35,.1), transparent 60%);
    pointer-events: none;
}
.qev-feat-body {
    padding: 40px 44px; display: flex; flex-direction: column; justify-content: center;
    position: relative; z-index: 1;
}
.qev-feat-badge {
    display: inline-flex; align-items: center; gap: 7px;
    background: rgba(245,166,35,.18); border: 1px solid rgba(245,166,35,.4);
    border-radius: 30px; padding: 5px 14px; margin-bottom: 16px;
    font-size: 11px; font-weight: 700; color: var(--gold); letter-spacing: .08em;
    width: fit-content;
}
.qev-feat-body h3 {
    font-family: 'Rajdhani', sans-serif; font-size: clamp(22px, 3vw, 32px);
    font-weight: 700; color: #fff; margin: 0 0 12px; line-height: 1.15;
}
.qev-feat-body p { font-size: 14px; color: rgba(255,255,255,.6); line-height: 1.7; margin: 0 0 22px; max-width: 480px; }
.qev-feat-meta { display: flex; flex-wrap: wrap; gap: 18px; margin-bottom: 28px; }
.qev-feat-meta-item { display: flex; align-items: center; gap: 7px; font-size: 13px; color: rgba(255,255,255,.75); }
.qev-feat-meta-item i { color: var(--gold); font-size: 13px; }
.qev-feat-btns { display: flex; gap: 12px; flex-wrap: wrap; }
.qev-feat-btn-primary {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--gold); color: #000; font-weight: 700;
    padding: 13px 28px; border-radius: 9px; font-size: 14px;
    font-family: 'Rajdhani', sans-serif; letter-spacing: .04em; transition: all .2s;
}
.qev-feat-btn-primary:hover { background: #d4890e; transform: translateY(-1px); }
.qev-feat-btn-outline {
    display: inline-flex; align-items: center; gap: 8px;
    border: 1px solid rgba(255,255,255,.3); color: #fff; font-weight: 600;
    padding: 12px 22px; border-radius: 9px; font-size: 14px;
    font-family: 'Exo 2', sans-serif; transition: all .2s;
}
.qev-feat-btn-outline:hover { border-color: var(--gold); color: var(--gold); }

.qev-feat-img {
    position: relative; overflow: hidden; min-height: 280px;
}
.qev-feat-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
.qev-feat-price-tag {
    position: absolute; top: 18px; right: 18px;
    background: rgba(0,0,0,.7); backdrop-filter: blur(8px);
    border: 1px solid rgba(245,166,35,.3); border-radius: 10px;
    padding: 10px 16px; text-align: center;
}
.qev-feat-price-tag .price { font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: var(--gold); }
.qev-feat-price-tag .orig  { font-size: 12px; text-decoration: line-through; color: rgba(255,255,255,.4); }
.qev-feat-price-tag .disc  { font-size: 11px; color: #81c784; font-weight: 700; margin-top: 2px; }

@media(max-width:900px){
    .qev-featured { grid-template-columns: 1fr; }
    .qev-feat-img  { height: 220px; min-height: unset; }
    .qev-feat-body { padding: 28px 24px; }
}

/* =========================================
   EVENT GRID
========================================= */
.qev-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 44px;
}
@media(max-width:1050px){ .qev-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:580px)  { .qev-grid { grid-template-columns: 1fr; } }

/* CARD */
.qev-card {
    background: var(--card-bg); border-radius: 12px; overflow: hidden;
    border: 1px solid var(--bdr); display: flex; flex-direction: column;
    transition: transform .25s, box-shadow .25s;
}
.qev-card:hover { transform: translateY(-5px); box-shadow: 0 16px 40px rgba(0,0,0,.1); }

.qev-card-thumb {
    position: relative; aspect-ratio: 16/9; overflow: hidden; background: #1a1a2e;
}
.qev-card-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .4s;
}
.qev-card:hover .qev-card-thumb img { transform: scale(1.05); }

.qev-card-badge {
    position: absolute; top: 10px; left: 10px;
    font-size: 10px; font-weight: 700; letter-spacing: .06em;
    padding: 4px 11px; border-radius: 5px; text-transform: uppercase;
}
.qev-card-badge.online    { background: #1565c0; color: #fff; }
.qev-card-badge.offline   { background: #2e7d32; color: #fff; }
.qev-card-badge.hybrid    { background: #6a1b9a; color: #fff; }
.qev-card-badge.workshop  { background: #e65100; color: #fff; }
.qev-card-badge.symposium { background: #c62828; color: #fff; }
.qev-card-badge.seminar   { background: #00695c; color: #fff; }
.qev-card-badge.bootcamp  { background: #37474f; color: #fff; }

.qev-seats-badge {
    position: absolute; top: 10px; right: 10px;
    background: rgba(0,0,0,.65); backdrop-filter: blur(6px);
    border-radius: 5px; padding: 4px 10px;
    font-size: 11px; color: #fff; font-weight: 600; display: flex; align-items: center; gap: 5px;
}
.qev-seats-badge.low { color: #ef9a9a; }
.qev-seats-badge.low i { color: #e53935; }

.qev-card-price-ov {
    position: absolute; bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(0,0,0,.8), transparent);
    padding: 24px 12px 10px;
    display: flex; align-items: flex-end; justify-content: space-between;
}
.qev-price-txt { font-family: 'Rajdhani', sans-serif; font-size: 15px; font-weight: 700; color: #fff; }
.qev-price-txt .strike { text-decoration: line-through; color: rgba(255,255,255,.45); font-size: 12px; margin: 0 4px; font-weight: 400; }
.qev-price-txt .disc   { font-size: 11px; color: #a5d6a7; margin-left: 3px; }
.qev-view-lnk {
    font-size: 12px; color: var(--gold); font-weight: 600;
    display: inline-flex; align-items: center; gap: 4px; white-space: nowrap; transition: gap .2s;
}
.qev-view-lnk:hover { gap: 7px; }

.qev-card-body { padding: 16px 18px; flex: 1; display: flex; flex-direction: column; }

.qev-card-date-strip {
    display: flex; align-items: center; gap: 8px;
    background: #fff8ed; border: 1px solid #ffe0b2; border-radius: 7px;
    padding: 6px 12px; margin-bottom: 12px; width: fit-content;
}
.qev-card-date-strip i { color: var(--gold); font-size: 12px; }
.qev-card-date-strip span { font-size: 12.5px; font-weight: 700; color: #b45309; }

.qev-card-title {
    font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700;
    color: var(--txt); margin-bottom: 10px; line-height: 1.35; flex: 1;
}
.qev-card-desc { font-size: 12.5px; color: #777; line-height: 1.65; margin-bottom: 12px; }

.qev-card-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
.qev-card-tag {
    font-size: 11px; padding: 3px 9px; border-radius: 4px; font-weight: 600;
    background: #f0f2ff; color: #3949ab; border: 1px solid #c5cae9;
}

.qev-card-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 5px 10px; }
.qev-card-meta-row { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #666; }
.qev-card-meta-row i { color: var(--gold); font-size: 11px; width: 13px; text-align: center; }
.qev-card-meta-row .mv { color: #333; font-weight: 600; }

.qev-card-footer {
    padding: 11px 18px; border-top: 1px solid var(--bdr); background: #fafafa;
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
}
.qev-card-footer-price {
    font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700; color: var(--txt);
}
.qev-card-footer-price .orig { text-decoration: line-through; color: #bbb; font-size: 12px; margin-right: 3px; font-weight: 400; }
.qev-card-footer-price .pct  { font-size: 11px; color: #43a047; font-weight: 700; margin-left: 4px; }
.qev-register-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--gold); color: #000; font-weight: 700; font-size: 13px;
    padding: 8px 18px; border-radius: 8px; transition: all .2s;
    font-family: 'Exo 2', sans-serif; white-space: nowrap;
}
.qev-register-btn:hover { background: #d4890e; transform: translateY(-1px); }

.qev-countdown {
    background: var(--dark); border-top: 1px solid rgba(245,166,35,.2);
    padding: 8px 18px; display: flex; align-items: center; gap: 10px;
}
.qev-countdown-label { font-size: 11px; color: rgba(255,255,255,.5); font-weight: 600; flex-shrink: 0; }
.qev-countdown-boxes { display: flex; gap: 6px; }
.qev-countdown-unit {
    display: flex; flex-direction: column; align-items: center;
    background: rgba(245,166,35,.12); border: 1px solid rgba(245,166,35,.25);
    border-radius: 5px; padding: 4px 8px; min-width: 36px;
}
.qev-countdown-num { font-family: 'Rajdhani', sans-serif; font-size: 16px; font-weight: 700; color: var(--gold); line-height: 1; }
.qev-countdown-sub { font-size: 9px; color: rgba(255,255,255,.4); letter-spacing: .05em; }

.qev-tab-panel     { display: none; }
.qev-tab-panel.on  { display: block; animation: evFadeUp .4s ease both; }

.qev-no-results { display: none; text-align: center; padding: 60px 20px; color: #aaa; font-size: 15px; }
.qev-no-results i { font-size: 36px; color: #ddd; display: block; margin-bottom: 12px; }

.qev-card.past .qev-card-thumb img { filter: grayscale(.35); }

.qev-webinar-strip {
    background: linear-gradient(90deg, #0D1B2A, #162844);
    border: 1px solid rgba(245,166,35,.2); border-radius: 14px;
    padding: 32px 40px; margin-top: 44px;
    display: flex; align-items: center; justify-content: space-between; gap: 24px; flex-wrap: wrap;
}
.qev-webinar-strip h3 {
    font-family: 'Rajdhani', sans-serif; font-size: 22px; font-weight: 700; color: #fff; margin: 0 0 6px;
}
.qev-webinar-strip p { font-size: 14px; color: rgba(255,255,255,.55); margin: 0; }
.qev-strip-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--gold); color: #000; font-weight: 700;
    padding: 13px 28px; border-radius: 9px; font-size: 14px;
    font-family: 'Rajdhani', sans-serif; letter-spacing: .04em; transition: background .2s; white-space: nowrap;
}
.qev-strip-btn:hover { background: #d4890e; }
</style>

<div style="font-family:'Exo 2',sans-serif; background:#f4f6fb;">

{{-- =====================
     HERO
===================== --}}
<div class="qev-hero ev-anim">
    <div class="qev-hero-inner">
        <div class="qev-hero-text text-center">
            <div class="qev-hero-eyebrow">
                <span class="qev-hero-dot"></span> Live Events &amp; Conferences
            </div>
            <h1>Options Trading <span>Events</span> &amp; Workshops</h1>
            <div class="d-flex justify-content-center">
                <p class="text-center">From live online symposiums to in-person workshops — join India's top options traders, analysts &amp; educators at CityQuants events across the country.</p>
            </div>
            {{-- <div class="qev-hero-stats">
                <div class="qev-stat-box">
                    <div class="qev-stat-val">7+</div>
                    <div class="qev-stat-lbl">Symposiums Held</div>
                </div>
                <div class="qev-stat-box">
                    <div class="qev-stat-val">15,000+</div>
                    <div class="qev-stat-lbl">Attendees</div>
                </div>
                <div class="qev-stat-box">
                    <div class="qev-stat-val">80+</div>
                    <div class="qev-stat-lbl">Expert Speakers</div>
                </div>
                <div class="qev-stat-box">
                    <div class="qev-stat-val">5</div>
                    <div class="qev-stat-lbl">Cities</div>
                </div>
            </div> --}}
        </div>
        {{-- <div class="qev-hero-img ev-anim d2">
            <img src="https://img.freepik.com/free-vector/conference-concept-illustration_114360-1088.jpg?w=500" alt="Events">
        </div> --}}
    </div>
</div>

{{-- =====================
     FILTER BAR
===================== --}}
<div class="qev-filter-bar">
    <div class="qev-tabs-row">
        <button class="qev-tab on" onclick="evSwitchTab(0,this)">All Events</button>
        <button class="qev-tab"    onclick="evSwitchTab(1,this)">Upcoming</button>
        <button class="qev-tab"    onclick="evSwitchTab(2,this)">Past Events</button>
    </div>
    <div class="qev-filter-row">
        <div class="qev-filter-group">
            <span class="qev-filter-label">Type</span>
            <select class="qev-filter-select" id="fType" onchange="evApplyFilters()">
                <option value="">All</option>
                <option value="online">Online</option>
                <option value="offline">Offline</option>
                <option value="hybrid">Hybrid</option>
            </select>
        </div>
        <div class="qev-filter-group">
            <span class="qev-filter-label">Price</span>
            <select class="qev-filter-select" id="fPrice" onchange="evApplyFilters()">
                <option value="">All</option>
                <option value="free">Free</option>
                <option value="paid">Paid</option>
            </select>
        </div>
        <div class="qev-filter-group">
            <span class="qev-filter-label">City</span>
            <select class="qev-filter-select" id="fCity" onchange="evApplyFilters()">
                <option value="">All Cities</option>
                <option value="online">Online</option>
                <option value="mumbai">Mumbai</option>
                <option value="delhi">Delhi</option>
                <option value="bangalore">Bangalore</option>
                <option value="pune">Pune</option>
                <option value="hyderabad">Hyderabad</option>
            </select>
        </div>
        <div class="qev-search-wrap">
            <input class="qev-search-input" type="text" id="evSearch" placeholder="Search events..." oninput="evApplyFilters()">
            <button class="qev-search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
    <div class="qev-pills-row">
        <button class="qev-pill on" onclick="evTogglePill(this)">All</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Symposium</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Workshop</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Seminar</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Bootcamp</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Options Buying</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Options Selling</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Algo Trading</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Live Market</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Derivatives</button>
        <button class="qev-pill" onclick="evTogglePill(this)">Technical Analysis</button>
    </div>
</div>

{{-- =====================
     CONTENT
===================== --}}
<div class="qev-content">

    {{-- ALL EVENTS PANEL --}}
    <div class="qev-tab-panel on" id="evPanel0">

        {{-- FEATURED EVENT — pulled from first $upcomingEvents item with featured=true --}}
        @php $featuredEvent = collect($upcomingEvents)->firstWhere('featured', true); @endphp
        @if($featuredEvent)
        <div class="qev-section-head ev-anim"><h2>Featured Event</h2></div>
        <div class="qev-featured ev-anim d1">
            <div class="qev-feat-body">
                <div class="qev-feat-badge">
                    <span class="qev-hero-dot"></span> REGISTRATIONS OPEN
                </div>
                <h3>{{ $featuredEvent['title'] }}</h3>
                <p>{{ $featuredEvent['desc'] }}</p>
                <div class="qev-feat-meta">
                    <div class="qev-feat-meta-item"><i class="fas fa-calendar-alt"></i> {{ $featuredEvent['date'] }}</div>
                    <div class="qev-feat-meta-item"><i class="fas fa-laptop"></i> {{ $featuredEvent['location'] }}</div>
                    <div class="qev-feat-meta-item"><i class="fas fa-users"></i> {{ $featuredEvent['speakers'] }}</div>
                    <div class="qev-feat-meta-item"><i class="fas fa-clock"></i> {{ $featuredEvent['duration'] }}, {{ $featuredEvent['time'] }}</div>
                </div>
                <div class="qev-feat-btns">
                    <a href="{{ $featuredEvent['url'] }}" class="qev-feat-btn-primary">
                        <i class="fas fa-ticket-alt"></i> Register Now
                    </a>
                    <a href="{{ $featuredEvent['url'] }}" class="qev-feat-btn-outline">
                        View Details <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="qev-feat-img">
                <img src="{{ $featuredEvent['thumbnail'] }}" alt="{{ $featuredEvent['title'] }}">
                <div class="qev-feat-price-tag">
                    @if($featuredEvent['price'] == 0)
                        <div class="price">FREE</div>
                    @else
                        <div class="price">&#8377;{{ number_format($featuredEvent['price']) }}</div>
                        <div class="orig">&#8377;{{ number_format($featuredEvent['mrp']) }}</div>
                        <div class="disc">{{ $featuredEvent['disc'] }}</div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- UPCOMING EVENTS GRID --}}
        <div class="qev-section-head ev-anim d2"><h2>Upcoming Events</h2></div>
        <div class="qev-grid ev-anim d2" id="evUpGrid">
            @foreach($upcomingEvents as $ev)
                @if(!$ev['featured'])  {{-- skip the featured one already shown above --}}
                <div class="qev-card"
                     data-mode="{{ $ev['mode'] }}"
                     data-type="{{ $ev['type'] }}"
                     data-city="{{ $ev['city'] }}"
                     data-title="{{ strtolower($ev['title']) }}">

                    <div class="qev-card-thumb">
                        <img src="{{ $ev['thumbnail'] }}" alt="{{ $ev['title'] }}" loading="lazy">
                        <span class="qev-card-badge {{ $ev['badge'] }}">{{ $ev['badge_label'] }}</span>
                        <span class="qev-seats-badge {{ $ev['seats_low'] ? 'low' : '' }}">
                            <i class="fas fa-chair"></i> {{ $ev['seats'] }}
                        </span>
                        <div class="qev-card-price-ov">
                            <span class="qev-price-txt">
                                @if($ev['price'] == 0)
                                    FREE
                                @else
                                    &#8377;{{ number_format($ev['price']) }}/-
                                    @if($ev['mrp'] > $ev['price'])
                                        <span class="strike">&#8377;{{ number_format($ev['mrp']) }}/-</span>
                                        <span class="disc">{{ $ev['disc'] }}</span>
                                    @endif
                                @endif
                            </span>
                            <a href="{{ $ev['url'] }}" class="qev-view-lnk">Details <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>

                    <div class="qev-card-body">
                        <div class="qev-card-date-strip">
                            <i class="fas fa-calendar-alt"></i>
                            <span>{{ $ev['date'] }} &nbsp;&middot;&nbsp; {{ $ev['time'] }}</span>
                        </div>
                        <div class="qev-card-title">{{ $ev['title'] }}</div>
                        <div class="qev-card-desc">{{ $ev['desc'] }}</div>
                        <div class="qev-card-tags">
                            @foreach($ev['tags'] as $tag)
                                <span class="qev-card-tag">{{ $tag }}</span>
                            @endforeach
                        </div>
                        <div class="qev-card-meta">
                            <div class="qev-card-meta-row"><i class="fas fa-map-marker-alt"></i><span class="mv">{{ $ev['location'] }}</span></div>
                            <div class="qev-card-meta-row"><i class="fas fa-user-tie"></i><span class="mv">{{ $ev['speakers'] }}</span></div>
                            <div class="qev-card-meta-row"><i class="fas fa-clock"></i><span class="mv">{{ $ev['duration'] }}</span></div>
                            <div class="qev-card-meta-row"><i class="fas fa-wifi"></i><span class="mv" style="text-transform:capitalize">{{ $ev['mode'] }}</span></div>
                        </div>
                    </div>

                    @if($ev['countdown'])
                    <div class="qev-countdown" data-target="{{ $ev['countdown'] }}">
                        <span class="qev-countdown-label">Starts in</span>
                        <div class="qev-countdown-boxes">
                            <div class="qev-countdown-unit">
                                <div class="qev-countdown-num cd-days">--</div>
                                <div class="qev-countdown-sub">Days</div>
                            </div>
                            <div class="qev-countdown-unit">
                                <div class="qev-countdown-num cd-hrs">--</div>
                                <div class="qev-countdown-sub">Hrs</div>
                            </div>
                            <div class="qev-countdown-unit">
                                <div class="qev-countdown-num cd-mins">--</div>
                                <div class="qev-countdown-sub">Mins</div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div class="qev-card-footer">
                        <div class="qev-card-footer-price">
                            @if($ev['price'] == 0)
                                FREE
                            @else
                                &#8377;{{ number_format($ev['price']) }}/-
                                @if($ev['mrp'] > $ev['price'])
                                    <span class="orig">&#8377;{{ number_format($ev['mrp']) }}/-</span>
                                    <span class="pct">{{ $ev['disc'] }}</span>
                                @endif
                            @endif
                        </div>
                        <a href="{{ $ev['url'] }}" class="qev-register-btn">
                            Register <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                </div>
                @endif
            @endforeach
        </div>

        {{-- PAST EVENTS GRID --}}
        <div class="qev-section-head ev-anim d3" style="margin-top:8px;"><h2>Past Events</h2></div>
        <div class="qev-grid ev-anim d3" id="evPastGrid">
            @foreach($pastEvents as $ev)
            <div class="qev-card past"
                 data-mode="{{ $ev['mode'] }}"
                 data-type="{{ $ev['type'] }}"
                 data-city="{{ $ev['city'] }}"
                 data-title="{{ strtolower($ev['title']) }}">

                <div class="qev-card-thumb">
                    <img src="{{ $ev['thumbnail'] }}" alt="{{ $ev['title'] }}" loading="lazy">
                    <span class="qev-card-badge {{ $ev['badge'] }}">{{ $ev['badge_label'] }}</span>
                    <span class="qev-seats-badge">
                        <i class="fas fa-users"></i> {{ $ev['seats'] }}
                    </span>
                    <div class="qev-card-price-ov">
                        <span class="qev-price-txt">&#8377;{{ number_format($ev['price']) }}/-</span>
                        <a href="{{ $ev['url'] }}" class="qev-view-lnk">Recording <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <div class="qev-card-body">
                    <div class="qev-card-date-strip" style="background:#fce4ec;border-color:#f48fb1;">
                        <i class="fas fa-calendar-check" style="color:#c62828;"></i>
                        <span style="color:#c62828;">{{ $ev['date'] }}</span>
                    </div>
                    <div class="qev-card-title">{{ $ev['title'] }}</div>
                    <div class="qev-card-desc">{{ $ev['desc'] }}</div>
                    <div class="qev-card-tags">
                        @foreach($ev['tags'] as $tag)
                            <span class="qev-card-tag">{{ $tag }}</span>
                        @endforeach
                    </div>
                    <div class="qev-card-meta">
                        <div class="qev-card-meta-row"><i class="fas fa-map-marker-alt"></i><span class="mv">{{ $ev['location'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-user-tie"></i><span class="mv">{{ $ev['speakers'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-clock"></i><span class="mv">{{ $ev['duration'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-wifi"></i><span class="mv" style="text-transform:capitalize">{{ $ev['mode'] }}</span></div>
                    </div>
                </div>

                <div class="qev-card-footer">
                    <div class="qev-card-footer-price">
                        &#8377;{{ number_format($ev['price']) }}/-
                        <span class="orig">&#8377;{{ number_format($ev['mrp']) }}/-</span>
                    </div>
                    <a href="{{ $ev['url'] }}" class="qev-register-btn" style="background:#455a64;">
                        Buy Recording <i class="fas fa-play"></i>
                    </a>
                </div>

            </div>
            @endforeach
        </div>

        <div class="qev-no-results" id="evNoResults">
            <i class="fas fa-calendar-times"></i>
            No events found matching your filters.
        </div>

        {{-- BOOK A DEMO STRIP --}}
        <div class="qev-webinar-strip ev-anim d4">
            <div>
                <h3>Want a personalised platform demo?</h3>
                <p>Book a free 1-on-1 demo session with our options analytics expert - designed for serious traders.</p>
            </div>
            <a href="{{ route('book.demo') }}" class="qev-strip-btn">
                <i class="fas fa-calendar-check"></i> Book a Free Demo
            </a>
        </div>

    </div>{{-- /#evPanel0 --}}

    {{-- UPCOMING TAB --}}
    <div class="qev-tab-panel" id="evPanel1">
        <div class="qev-section-head"><h2>Upcoming Events</h2></div>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">Showing all future events - register before seats fill up!</p>
        <div class="qev-grid" id="evUpGrid2">
            @foreach($upcomingEvents as $ev)
            <div class="qev-card"
                 data-mode="{{ $ev['mode'] }}"
                 data-type="{{ $ev['type'] }}"
                 data-city="{{ $ev['city'] }}"
                 data-title="{{ strtolower($ev['title']) }}">

                <div class="qev-card-thumb">
                    <img src="{{ $ev['thumbnail'] }}" alt="{{ $ev['title'] }}" loading="lazy">
                    <span class="qev-card-badge {{ $ev['badge'] }}">{{ $ev['badge_label'] }}</span>
                    <span class="qev-seats-badge {{ $ev['seats_low'] ? 'low' : '' }}">
                        <i class="fas fa-chair"></i> {{ $ev['seats'] }}
                    </span>
                    <div class="qev-card-price-ov">
                        <span class="qev-price-txt">
                            @if($ev['price'] == 0) FREE
                            @else &#8377;{{ number_format($ev['price']) }}/-
                                @if($ev['mrp'] > $ev['price'])
                                    <span class="strike">&#8377;{{ number_format($ev['mrp']) }}/-</span>
                                    <span class="disc">{{ $ev['disc'] }}</span>
                                @endif
                            @endif
                        </span>
                        <a href="{{ $ev['url'] }}" class="qev-view-lnk">Details <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <div class="qev-card-body">
                    <div class="qev-card-date-strip">
                        <i class="fas fa-calendar-alt"></i>
                        <span>{{ $ev['date'] }} &nbsp;&middot;&nbsp; {{ $ev['time'] }}</span>
                    </div>
                    <div class="qev-card-title">{{ $ev['title'] }}</div>
                    <div class="qev-card-desc">{{ $ev['desc'] }}</div>
                    <div class="qev-card-tags">
                        @foreach($ev['tags'] as $tag)
                            <span class="qev-card-tag">{{ $tag }}</span>
                        @endforeach
                    </div>
                    <div class="qev-card-meta">
                        <div class="qev-card-meta-row"><i class="fas fa-map-marker-alt"></i><span class="mv">{{ $ev['location'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-user-tie"></i><span class="mv">{{ $ev['speakers'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-clock"></i><span class="mv">{{ $ev['duration'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-wifi"></i><span class="mv" style="text-transform:capitalize">{{ $ev['mode'] }}</span></div>
                    </div>
                </div>

                @if($ev['countdown'])
                <div class="qev-countdown" data-target="{{ $ev['countdown'] }}">
                    <span class="qev-countdown-label">Starts in</span>
                    <div class="qev-countdown-boxes">
                        <div class="qev-countdown-unit">
                            <div class="qev-countdown-num cd-days">--</div>
                            <div class="qev-countdown-sub">Days</div>
                        </div>
                        <div class="qev-countdown-unit">
                            <div class="qev-countdown-num cd-hrs">--</div>
                            <div class="qev-countdown-sub">Hrs</div>
                        </div>
                        <div class="qev-countdown-unit">
                            <div class="qev-countdown-num cd-mins">--</div>
                            <div class="qev-countdown-sub">Mins</div>
                        </div>
                    </div>
                </div>
                @endif

                <div class="qev-card-footer">
                    <div class="qev-card-footer-price">
                        @if($ev['price'] == 0) FREE
                        @else &#8377;{{ number_format($ev['price']) }}/-
                            @if($ev['mrp'] > $ev['price'])
                                <span class="orig">&#8377;{{ number_format($ev['mrp']) }}/-</span>
                                <span class="pct">{{ $ev['disc'] }}</span>
                            @endif
                        @endif
                    </div>
                    <a href="{{ $ev['url'] }}" class="qev-register-btn">
                        Register <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

            </div>
            @endforeach
        </div>
    </div>{{-- /#evPanel1 --}}

    {{-- PAST TAB --}}
    <div class="qev-tab-panel" id="evPanel2">
        <div class="qev-section-head"><h2>Past Events</h2></div>
        <p style="color:#888;font-size:14px;margin-bottom:24px;">Browse past events and purchase recordings.</p>
        <div class="qev-grid" id="evPastGrid2">
            @foreach($pastEvents as $ev)
            <div class="qev-card past"
                 data-mode="{{ $ev['mode'] }}"
                 data-type="{{ $ev['type'] }}"
                 data-city="{{ $ev['city'] }}"
                 data-title="{{ strtolower($ev['title']) }}">

                <div class="qev-card-thumb">
                    <img src="{{ $ev['thumbnail'] }}" alt="{{ $ev['title'] }}" loading="lazy">
                    <span class="qev-card-badge {{ $ev['badge'] }}">{{ $ev['badge_label'] }}</span>
                    <span class="qev-seats-badge">
                        <i class="fas fa-users"></i> {{ $ev['seats'] }}
                    </span>
                    <div class="qev-card-price-ov">
                        <span class="qev-price-txt">&#8377;{{ number_format($ev['price']) }}/-</span>
                        <a href="{{ $ev['url'] }}" class="qev-view-lnk">Recording <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                <div class="qev-card-body">
                    <div class="qev-card-date-strip" style="background:#fce4ec;border-color:#f48fb1;">
                        <i class="fas fa-calendar-check" style="color:#c62828;"></i>
                        <span style="color:#c62828;">{{ $ev['date'] }}</span>
                    </div>
                    <div class="qev-card-title">{{ $ev['title'] }}</div>
                    <div class="qev-card-desc">{{ $ev['desc'] }}</div>
                    <div class="qev-card-tags">
                        @foreach($ev['tags'] as $tag)
                            <span class="qev-card-tag">{{ $tag }}</span>
                        @endforeach
                    </div>
                    <div class="qev-card-meta">
                        <div class="qev-card-meta-row"><i class="fas fa-map-marker-alt"></i><span class="mv">{{ $ev['location'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-user-tie"></i><span class="mv">{{ $ev['speakers'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-clock"></i><span class="mv">{{ $ev['duration'] }}</span></div>
                        <div class="qev-card-meta-row"><i class="fas fa-wifi"></i><span class="mv" style="text-transform:capitalize">{{ $ev['mode'] }}</span></div>
                    </div>
                </div>

                <div class="qev-card-footer">
                    <div class="qev-card-footer-price">
                        &#8377;{{ number_format($ev['price']) }}/-
                        <span class="orig">&#8377;{{ number_format($ev['mrp']) }}/-</span>
                    </div>
                    <a href="{{ $ev['url'] }}" class="qev-register-btn" style="background:#455a64;">
                        Buy Recording <i class="fas fa-play"></i>
                    </a>
                </div>

            </div>
            @endforeach
        </div>
    </div>{{-- /#evPanel2 --}}

</div>{{-- /.qev-content --}}
</div>

<script>
/* TAB SWITCH */
function evSwitchTab(idx, btn) {
    document.querySelectorAll('.qev-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qev-tab-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === idx);
    });
}

/* PILL TOGGLE */
function evTogglePill(el) {
    document.querySelectorAll('.qev-pill').forEach(function(p) { p.classList.remove('on'); });
    el.classList.add('on');
    evApplyFilters();
}

/* FILTER */
function evApplyFilters() {
    var mode   = document.getElementById('fType').value.toLowerCase();
    var price  = document.getElementById('fPrice').value.toLowerCase();
    var city   = document.getElementById('fCity').value.toLowerCase();
    var search = document.getElementById('evSearch').value.toLowerCase().trim();

    var grids   = ['evUpGrid','evPastGrid','evUpGrid2','evPastGrid2'].map(function(id){
        return document.getElementById(id);
    });
    var visible = 0;

    grids.forEach(function(grid) {
        if (!grid) return;
        grid.querySelectorAll('.qev-card').forEach(function(card) {
            var cMode  = (card.dataset.mode  || '').toLowerCase();
            var cType  = (card.dataset.type  || '').toLowerCase();
            var cCity  = (card.dataset.city  || '').toLowerCase();
            var cTitle = (card.dataset.title || '').toLowerCase();
            var ok = true;
            if (mode   && cMode  !== mode)               ok = false;
            if (price  && cType  !== price)              ok = false;
            if (city   && cCity  !== city)               ok = false;
            if (search && cTitle.indexOf(search) === -1) ok = false;
            card.style.display = ok ? '' : 'none';
            if (ok) visible++;
        });
    });

    var noRes = document.getElementById('evNoResults');
    if (noRes) noRes.style.display = (visible === 0) ? 'block' : 'none';
}

/* COUNTDOWN */
function updateCountdowns() {
    document.querySelectorAll('.qev-countdown[data-target]').forEach(function(el) {
        var target = new Date(el.dataset.target).getTime();
        var now    = new Date().getTime();
        var diff   = target - now;
        if (diff <= 0) {
            el.querySelector('.qev-countdown-label').textContent = 'Event started!';
            return;
        }
        var days = Math.floor(diff / 86400000);
        var hrs  = Math.floor((diff % 86400000) / 3600000);
        var mins = Math.floor((diff % 3600000)  / 60000);
        var d = el.querySelector('.cd-days');
        var h = el.querySelector('.cd-hrs');
        var m = el.querySelector('.cd-mins');
        if (d) d.textContent = String(days).padStart(2,'0');
        if (h) h.textContent = String(hrs).padStart(2,'0');
        if (m) m.textContent = String(mins).padStart(2,'0');
    });
}
updateCountdowns();
setInterval(updateCountdowns, 30000);
</script>

@endsection