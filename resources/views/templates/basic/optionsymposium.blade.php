@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   GLOBAL
========================================= */
.qos-wrap { font-family:'Exo 2',sans-serif; color:#1a1a2e; display:block; }
.qos-wrap * { box-sizing:border-box; }
.qos-wrap a { text-decoration:none; }
.qos-wrap h2,.qos-wrap h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.02em; }

@keyframes qosFadeUp {
    from { opacity:0; transform:translateY(24px); }
    to   { opacity:1; transform:none; }
}
.qos-anim    { animation: qosFadeUp .7s ease both; }
.qos-anim.d1 { animation-delay:.15s; }
.qos-anim.d2 { animation-delay:.3s; }
.qos-anim.d3 { animation-delay:.45s; }

/* sticky top nav */
.qos-topnav {
    position: sticky; top:0; z-index:300;
    background:#1a1a2e;
    display:flex; align-items:center; justify-content:center;
    gap:0; border-bottom:2px solid #F5A623;
    overflow-x:auto;
}
.qos-nav-link {
    padding:14px 20px; font-size:13px; font-weight:600;
    color:rgba(255,255,255,.65); cursor:pointer;
    white-space:nowrap; transition:all .2s; border:none; background:none;
    font-family:'Exo 2',sans-serif; letter-spacing:.04em;
}
.qos-nav-link.on, .qos-nav-link:hover { color:#F5A623; }
.qos-nav-cta {
    margin-left:auto; padding:10px 22px; background:#F5A623;
    color:#000; font-weight:700; font-size:13px; border:none; cursor:pointer;
    font-family:'Exo 2',sans-serif; white-space:nowrap; flex-shrink:0;
    transition:background .2s;
}
.qos-nav-cta:hover { background:#d4890e; }

/* =========================================
   1. HERO
========================================= */
.qos-hero {
    position:relative; min-height:62vh;
    background:linear-gradient(135deg,#0d1b2a 0%,#1a2f50 55%,#0d1b2a 100%);
    overflow:hidden;
    display:flex; align-items:center;
}
.qos-hero::before {
    content:'';position:absolute;inset:0;
    background-image:
        linear-gradient(rgba(245,166,35,.04) 1px,transparent 1px),
        linear-gradient(90deg,rgba(245,166,35,.04) 1px,transparent 1px);
    background-size:56px 56px;
    pointer-events:none;
}
.qos-hero-inner {
    position:relative;z-index:1;
    width:100%;max-width:1160px;margin:0 auto;
    padding:60px 40px;
    display:grid;grid-template-columns:1fr 380px;gap:48px;align-items:center;
}
@media(max-width:860px){ .qos-hero-inner{grid-template-columns:1fr;gap:32px;padding:40px 24px;} }

.qos-hero-badge {
    display:inline-flex;align-items:center;gap:8px;
    background:rgba(245,166,35,.15);border:1px solid rgba(245,166,35,.35);
    color:#F5A623;font-size:11px;font-weight:700;padding:6px 16px;
    border-radius:30px;letter-spacing:.1em;text-transform:uppercase;margin-bottom:18px;
}
.qos-hero-sub {
    font-size:clamp(13px,1.6vw,16px);color:rgba(255,255,255,.65);
    font-weight:500;letter-spacing:.05em;margin-bottom:10px;
    text-transform:uppercase;
}
.qos-hero-title {
    font-family:'Rajdhani',sans-serif;
    font-size:clamp(36px,6vw,72px);font-weight:700;
    line-height:1.0;margin-bottom:16px;
}
.qos-hero-title .white{color:#fff;}
.qos-hero-title .gold {color:#F5A623;}
.qos-hero-date {
    font-size:16px;font-weight:600;color:#fff;margin-bottom:6px;
}
.qos-hero-loc {
    font-size:14px;color:rgba(255,255,255,.5);margin-bottom:36px;
}
.qos-hero-btns { display:flex;gap:14px;flex-wrap:wrap; }
.qos-btn-primary {
    display:inline-flex;align-items:center;gap:8px;
    background:#F5A623;color:#000;font-weight:700;font-size:15px;
    padding:14px 32px;border-radius:10px;border:none;cursor:pointer;
    font-family:'Rajdhani',sans-serif;letter-spacing:.05em;transition:background .2s,transform .15s;
}
.qos-btn-primary:hover{background:#d4890e;}
.qos-btn-primary:active{transform:scale(.97);}
.qos-btn-ghost {
    display:inline-flex;align-items:center;gap:8px;
    border:1px solid rgba(255,255,255,.3);color:#fff;font-weight:600;font-size:14px;
    padding:13px 28px;border-radius:10px;cursor:pointer;background:transparent;
    font-family:'Exo 2',sans-serif;transition:all .2s;
}
.qos-btn-ghost:hover{border-color:#F5A623;color:#F5A623;}

/* right: promo card */
.qos-hero-card {
    background:rgba(255,255,255,.06);backdrop-filter:blur(12px);
    border:1px solid rgba(245,166,35,.2);border-radius:18px;
    padding:28px;position:relative;
}
.qos-hero-card-img {
    width:100%;border-radius:10px;overflow:hidden;aspect-ratio:4/3;margin-bottom:18px;
}
.qos-hero-card-img img{width:100%;height:100%;object-fit:cover;display:block;}
.qos-hero-card-title {
    font-family:'Rajdhani',sans-serif;font-size:22px;font-weight:700;color:#F5A623;margin-bottom:6px;
}
.qos-hero-card-tag {
    font-size:11px;color:rgba(255,255,255,.5);letter-spacing:.08em;margin-bottom:16px;
}
.qos-hero-card-cta {
    width:100%;padding:12px;background:#F5A623;color:#000;font-weight:700;
    font-size:14px;border:none;border-radius:8px;cursor:pointer;
    font-family:'Rajdhani',sans-serif;letter-spacing:.05em;transition:background .2s;
}
.qos-hero-card-cta:hover{background:#d4890e;}

/* scroll hint */
.qos-scroll-hint {
    position:absolute;bottom:22px;right:40px;
    font-size:11px;color:rgba(255,255,255,.4);letter-spacing:.06em;
    display:flex;align-items:center;gap:6px;
}

/* =========================================
   SECTION COMMONS
========================================= */
.qos-sec { padding:70px 0; display:block; }
.qos-sec-inner { max-width:1160px;margin:0 auto;padding:0 40px; }
@media(max-width:768px){ .qos-sec-inner{padding:0 20px;} }

.qos-sec-title {
    font-family:'Rajdhani',sans-serif;font-size:clamp(28px,3.5vw,42px);font-weight:700;
    text-align:center;margin:0 0 10px;color:#000;
}
.qos-sec-line {
    width:56px;height:3px;background:#F5A623;border-radius:2px;
    margin:0 auto 48px;
}

/* =========================================
   2. ABOUT THE CONFERENCE
========================================= */
.qos-about-sec { background:#fff; }
.qos-about-inner {
    display:grid;grid-template-columns:1fr 340px;gap:52px;align-items:center;
}
@media(max-width:860px){.qos-about-inner{grid-template-columns:1fr;}}
.qos-about-body p {
    font-size:14.5px;color:#555;line-height:1.85;margin-bottom:14px;
}
.qos-about-card {
    background:linear-gradient(135deg,#0d1b2a,#1a2f50);
    border:1px solid rgba(245,166,35,.3);border-radius:16px;
    overflow:hidden;padding:0;
}
.qos-about-card-img{width:100%;aspect-ratio:4/3;overflow:hidden;}
.qos-about-card-img img{width:100%;height:100%;object-fit:cover;display:block;}
.qos-about-card-body{padding:20px 22px;}
.qos-about-card-title{font-family:'Rajdhani',sans-serif;font-size:20px;font-weight:700;color:#F5A623;margin-bottom:6px;}
.qos-about-card-sub{font-size:12px;color:rgba(255,255,255,.5);margin-bottom:14px;}
.qos-about-card-btn{
    width:100%;padding:11px;background:#F5A623;color:#000;font-weight:700;
    font-size:13px;border:none;border-radius:8px;cursor:pointer;
    font-family:'Rajdhani',sans-serif;letter-spacing:.04em;
}

/* =========================================
   3. BENEFITS
========================================= */
.qos-benefits-sec { background:#f5f5f7; }
.qos-benefits-grid {
    display:grid;grid-template-columns:repeat(4,1fr);gap:28px;
}
@media(max-width:900px){.qos-benefits-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.qos-benefits-grid{grid-template-columns:1fr;}}
.qos-benefit-card {
    background:#fff;border-radius:14px;padding:28px 22px;text-align:center;
    border:1px solid #e8e8e8;transition:box-shadow .25s,transform .25s;
}
.qos-benefit-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(0,0,0,.08);}
.qos-benefit-icon {
    width:72px;height:72px;border-radius:50%;margin:0 auto 16px;overflow:hidden;
    background:linear-gradient(135deg,#0d1b2a,#1a2f50);
    display:flex;align-items:center;justify-content:center;
}
.qos-benefit-icon img{width:100%;height:100%;object-fit:cover;}
.qos-benefit-icon i{font-size:26px;color:#F5A623;}
.qos-benefit-val{font-family:'Rajdhani',sans-serif;font-size:22px;font-weight:700;color:#F5A623;margin-bottom:4px;}
.qos-benefit-label{font-size:13px;color:#555;font-weight:500;line-height:1.4;}

/* =========================================
   4. SPEAKERS
========================================= */
.qos-speakers-sec { background:#fff; }
.qos-speakers-grid {
    display:grid;grid-template-columns:repeat(5,1fr);gap:24px;
}
@media(max-width:1000px){.qos-speakers-grid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:600px) {.qos-speakers-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:380px) {.qos-speakers-grid{grid-template-columns:1fr;}}
.qos-speaker-card {
    text-align:center;padding:20px 14px;
    border:1px solid #eee;border-radius:14px;
    transition:box-shadow .25s,transform .25s;background:#fff;
}
.qos-speaker-card:hover{transform:translateY(-4px);box-shadow:0 12px 28px rgba(0,0,0,.08);}
.qos-speaker-av {
    width:88px;height:88px;border-radius:50%;margin:0 auto 12px;overflow:hidden;
    border:3px solid #F5A623;background:#f0efed;
    display:flex;align-items:center;justify-content:center;font-size:28px;color:#ccc;
}
.qos-speaker-av img{width:100%;height:100%;object-fit:cover;display:block;}
.qos-speaker-name{font-family:'Rajdhani',sans-serif;font-size:16px;font-weight:700;color:#1a1a2e;margin-bottom:3px;}
.qos-speaker-role{font-size:11.5px;color:#F5A623;font-weight:600;margin-bottom:4px;}
.qos-speaker-creds{font-size:11px;color:#888;background:#f5f5f7;border-radius:20px;padding:3px 10px;display:inline-block;}
.qos-speaker-topic{font-size:11.5px;color:#555;margin-top:8px;line-height:1.45;}

/* =========================================
   5. SCHEDULE
========================================= */
.qos-schedule-sec { background:#f5f5f7; }
.qos-sched-tabs {
    display:flex;gap:0;border:1px solid #e0e0e0;border-radius:10px;
    overflow:hidden;width:fit-content;margin:0 auto 32px;
}
.qos-sched-tab {
    padding:12px 32px;font-size:14px;font-weight:600;
    cursor:pointer;border:none;background:#fff;color:#666;
    transition:all .2s;font-family:'Exo 2',sans-serif;
}
.qos-sched-tab.on{background:#1a1a2e;color:#F5A623;}
.qos-sched-panel{display:none;}
.qos-sched-panel.on{display:block;animation:qosFadeUp .4s ease both;}

.qos-sched-table{width:100%;border-collapse:collapse;}
.qos-sched-table th{
    background:#1a1a2e;color:#F5A623;font-family:'Rajdhani',sans-serif;
    font-size:14px;font-weight:700;letter-spacing:.05em;
    padding:14px 18px;text-align:left;
}
.qos-sched-table td{
    padding:13px 18px;border-bottom:1px solid #eee;
    font-size:13.5px;color:#333;vertical-align:top;
}
.qos-sched-table tr:last-child td{border-bottom:none;}
.qos-sched-table tr:hover td{background:#fafafa;}
.qos-sched-break td{background:#fff8e8 !important;color:#F5A623;font-weight:600;}
.qos-sched-time{font-weight:700;color:#1a1a2e;white-space:nowrap;}
.qos-sched-dur {color:#888;white-space:nowrap;}
.qos-sched-spk {font-weight:600;color:#1a1a2e;}
.qos-sched-topic{color:#555;}

/* =========================================
   6. PRICING
========================================= */
.qos-pricing-sec{background:#fff;}
.qos-pricing-wrap{
    display:grid;grid-template-columns:280px 1fr;gap:48px;align-items:start;
}
@media(max-width:760px){.qos-pricing-wrap{grid-template-columns:1fr;}}
.qos-price-options{}
.qos-price-option{
    display:flex;align-items:center;gap:12px;padding:14px 0;
    border-bottom:1px solid #eee;font-size:14px;color:#333;cursor:pointer;
}
.qos-price-option:last-child{border-bottom:none;}
.qos-price-option i{color:#F5A623;font-size:16px;flex-shrink:0;}
.qos-price-card{
    background:linear-gradient(135deg,#0d1b2a,#1a2f50);
    border:1px solid rgba(245,166,35,.3);border-radius:18px;
    padding:36px 32px;text-align:center;
    box-shadow:0 16px 48px rgba(0,0,0,.15);
    max-width:360px;margin:0 auto;
}
.qos-price-card-title{
    font-family:'Rajdhani',sans-serif;font-size:22px;font-weight:700;
    color:#F5A623;margin-bottom:6px;
}
.qos-price-card-sub{font-size:12px;color:rgba(255,255,255,.45);margin-bottom:28px;}
.qos-price-amount{
    font-family:'Rajdhani',sans-serif;font-size:52px;font-weight:700;
    color:#fff;line-height:1;margin-bottom:4px;
}
.qos-price-amount sup{font-size:24px;vertical-align:super;}
.qos-price-strike{
    text-decoration:line-through;color:rgba(255,255,255,.35);
    font-size:18px;margin-bottom:28px;
}
.qos-price-pay-btn{
    width:100%;padding:16px;background:#F5A623;color:#000;
    font-family:'Rajdhani',sans-serif;font-size:18px;font-weight:700;
    border:none;border-radius:10px;cursor:pointer;letter-spacing:.05em;
    transition:background .2s,transform .15s;
}
.qos-price-pay-btn:hover{background:#d4890e;}
.qos-price-pay-btn:active{transform:scale(.97);}
.qos-tnc-link{font-size:12px;color:#888;margin-top:20px;text-align:center;display:block;}
.qos-tnc-link a{color:#F5A623;text-decoration:underline;}

/* =========================================
   7. KNOW MORE / DOWNLOAD
========================================= */
.qos-know-sec{
    background:linear-gradient(135deg,#0d1b2a,#1a2f50);
    padding:60px 0;
}
.qos-know-inner{
    max-width:1160px;margin:0 auto;padding:0 40px;
    display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;
}
@media(max-width:760px){.qos-know-inner{grid-template-columns:1fr;gap:28px;padding:0 20px;}}
.qos-know-title{
    font-family:'Rajdhani',sans-serif;font-size:clamp(24px,3vw,36px);
    font-weight:700;color:#fff;margin-bottom:20px;line-height:1.2;
}
.qos-know-title span{color:#F5A623;}
.qos-know-cta-btn{
    display:inline-flex;align-items:center;gap:10px;
    background:#F5A623;color:#000;font-weight:700;font-size:15px;
    padding:14px 28px;border-radius:10px;border:none;cursor:pointer;
    font-family:'Rajdhani',sans-serif;letter-spacing:.05em;transition:background .2s;
}
.qos-know-cta-btn:hover{background:#d4890e;}
.qos-download-title{
    font-family:'Rajdhani',sans-serif;font-size:28px;font-weight:700;
    color:#fff;margin-bottom:20px;
}
.qos-download-btns{display:flex;gap:14px;flex-wrap:wrap;}
.qos-dl-btn{
    display:inline-flex;align-items:center;gap:10px;
    border:1px solid rgba(255,255,255,.25);color:#fff;
    padding:12px 22px;border-radius:10px;cursor:pointer;background:transparent;
    font-size:22px;transition:all .2s;
}
.qos-dl-btn:hover{border-color:#F5A623;color:#F5A623;}

/* =========================================
   8. PAST EXPERTS ATTENDEE
========================================= */
.qos-past-sec{background:#fff;}
.qos-past-scroll{
    display:flex;gap:20px;overflow-x:auto;padding-bottom:8px;
    scrollbar-width:thin;scrollbar-color:#ddd transparent;
}
.qos-past-scroll::-webkit-scrollbar{height:5px;}
.qos-past-scroll::-webkit-scrollbar-thumb{background:#ddd;border-radius:4px;}
.qos-past-person{
    flex-shrink:0;width:130px;text-align:center;
}
.qos-past-av{
    width:80px;height:80px;border-radius:50%;margin:0 auto 8px;overflow:hidden;
    border:2px solid #e8e8e8;background:#f0efed;
    display:flex;align-items:center;justify-content:center;font-size:22px;color:#ccc;
}
.qos-past-av img{width:100%;height:100%;object-fit:cover;display:block;}
.qos-past-name{font-size:12px;font-weight:700;color:#1a1a2e;margin-bottom:2px;}
.qos-past-role{font-size:11px;color:#888;line-height:1.35;}

/* =========================================
   9. GALLERY
========================================= */
.qos-gallery-sec{background:#f5f5f7;}
.qos-gallery-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    grid-template-rows:repeat(2,180px);
    gap:12px;
}
@media(max-width:768px){
    .qos-gallery-grid{grid-template-columns:repeat(2,1fr);grid-template-rows:repeat(4,140px);}
}
.qos-gallery-item{border-radius:12px;overflow:hidden;background:#1a1a2e;position:relative;}
.qos-gallery-item.wide{grid-column:span 2;}
.qos-gallery-item img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s;}
.qos-gallery-item:hover img{transform:scale(1.06);}
.qos-gallery-year{
    position:absolute;bottom:10px;left:12px;
    font-family:'Rajdhani',sans-serif;font-size:20px;font-weight:700;color:#fff;
    background:rgba(0,0,0,.4);padding:3px 10px;border-radius:6px;
}

/* =========================================
   STICKY SIDE CTA (right side icons — matches screenshot)
========================================= */
.qos-side-cta {
    position:fixed;right:0;top:50%;transform:translateY(-50%);
    z-index:200;display:flex;flex-direction:column;gap:0;
}
.qos-side-btn {
    width:44px;padding:10px 0;background:#F5A623;color:#000;
    text-align:center;font-size:16px;cursor:pointer;transition:background .2s;
    border-bottom:1px solid rgba(0,0,0,.1);
}
.qos-side-btn:hover{background:#d4890e;}
.qos-side-btn:first-child{border-radius:8px 0 0 0;}
.qos-side-btn:last-child {border-radius:0 0 0 8px;border-bottom:none;}
@media(max-width:768px){.qos-side-cta{display:none;}}
</style>

<div class="qos-wrap">

{{-- ── STICKY TOP NAV ── --}}
{{-- <nav class="qos-topnav">
    <button class="qos-nav-link on" onclick="qosScrollTo('about')">About</button>
    <button class="qos-nav-link"    onclick="qosScrollTo('benefits')">Benefits</button>
    <button class="qos-nav-link"    onclick="qosScrollTo('speakers')">Speakers</button>
    <button class="qos-nav-link"    onclick="qosScrollTo('schedule')">Schedule</button>
    <button class="qos-nav-link"    onclick="qosScrollTo('pricing')">Pricing</button>
    <button class="qos-nav-link"    onclick="qosScrollTo('network')">Network with Distinguished Experts</button>
    <button class="qos-nav-link"    onclick="qosScrollTo('gallery')">Gallery</button>
    <button class="qos-nav-cta"     onclick="qosScrollTo('pricing')">Pay Now</button>
</nav> --}}

{{-- ══════════════════════════════════════════════
     1. HERO
══════════════════════════════════════════════ --}}
<section class="qos-hero">
    <div class="qos-hero-inner">
        <div class="qos-anim">
            <div class="qos-hero-badge">India's Largest Option Conference</div>
            <div class="qos-hero-sub">India's Largest Option Conference</div>
            <h1 class="qos-hero-title">
                <span class="white">Option </span><span class="gold">{{ $symposium['title'] }}</span>
            </h1>
            <div class="qos-hero-date">
                <i class="fas fa-calendar-alt" style="color:#F5A623;margin-right:8px;"></i>
                {{ $symposium['date'] }}
            </div>
            <div class="qos-hero-loc">
                <i class="fas fa-map-marker-alt" style="color:#F5A623;margin-right:8px;"></i>
                {{ $symposium['location'] }}
            </div>
            <div class="qos-hero-btns">
                <button class="qos-btn-primary" onclick="qosScrollTo('pricing')">
                    <i class="fas fa-ticket-alt"></i> Register Now
                </button>
                <button class="qos-btn-ghost" onclick="qosScrollTo('schedule')">
                    <i class="fas fa-calendar-check"></i> View Schedule
                </button>
            </div>
        </div>

        {{-- right card --}}
        <div class="qos-hero-card qos-anim d2">
            <div class="qos-hero-card-img">
                <img src="{{ $symposium['hero_image'] }}" alt="{{ $symposium['title'] }}">
            </div>
            <div class="qos-hero-card-title">{{ $symposium['title'] }}</div>
            <div class="qos-hero-card-tag">India's Largest Option Conference &nbsp;|&nbsp; {{ $symposium['date'] }}</div>
            <button class="qos-hero-card-cta" onclick="qosScrollTo('pricing')">Request a callback</button>
        </div>
    </div>
    <div class="qos-scroll-hint">
        Scroll down to discover more <i class="fas fa-chevron-down"></i>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     2. ABOUT
══════════════════════════════════════════════ --}}
<section class="qos-sec qos-about-sec" id="about">
    <div class="qos-sec-inner">
        <h2 class="qos-sec-title">About The Conference</h2>
        <div class="qos-sec-line"></div>
        <div class="qos-about-inner">
            <div class="qos-about-body">
                @foreach($symposium['about'] as $para)
                <p>{{ $para }}</p>
                @endforeach
            </div>
            <div class="qos-about-card">
                <div class="qos-about-card-img">
                    <img src="{{ $symposium['about_image'] }}" alt="Symposium">
                </div>
                <div class="qos-about-card-body">
                    <div class="qos-about-card-title">{{ $symposium['title'] }}</div>
                    <div class="qos-about-card-sub">India's Largest Option Conference &nbsp;|&nbsp; {{ $symposium['date'] }}</div>
                    <button class="qos-about-card-btn" onclick="qosScrollTo('pricing')">Request a callback</button>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     3. BENEFITS
══════════════════════════════════════════════ --}}
<section class="qos-sec qos-benefits-sec" id="benefits">
    <div class="qos-sec-inner">
        <h2 class="qos-sec-title">Benefits of Attending</h2>
        <div class="qos-sec-line"></div>
        <div class="qos-benefits-grid">
            @foreach($symposium['benefits'] as $b)
            <div class="qos-benefit-card">
                <div class="qos-benefit-icon">
                    <i class="fas {{ $b['icon'] }}"></i>
                </div>
                <div class="qos-benefit-val">{{ $b['value'] }}</div>
                <div class="qos-benefit-label">{{ $b['label'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     4. SPEAKERS
══════════════════════════════════════════════ --}}
<section class="qos-sec qos-speakers-sec" id="speakers">
    <div class="qos-sec-inner">
        <h2 class="qos-sec-title">Speakers</h2>
        <div class="qos-sec-line"></div>
        <div class="qos-speakers-grid">
            @foreach($symposium['speakers'] as $sp)
            <div class="qos-speaker-card">
                <div class="qos-speaker-av">
                    @if(!empty($sp['avatar']))
                        <img src="{{ $sp['avatar'] }}" alt="{{ $sp['name'] }}">
                    @else
                        <i class="fas fa-user"></i>
                    @endif
                </div>
                <div class="qos-speaker-name">{{ $sp['name'] }}</div>
                <div class="qos-speaker-role">{{ $sp['role'] }}</div>
                @if(!empty($sp['creds']))<span class="qos-speaker-creds">{{ $sp['creds'] }}</span>@endif
                @if(!empty($sp['topic']))<div class="qos-speaker-topic"><strong>Topic:</strong> {{ $sp['topic'] }}</div>@endif
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     5. SCHEDULE
══════════════════════════════════════════════ --}}
<section class="qos-sec qos-schedule-sec" id="schedule">
    <div class="qos-sec-inner">
        <h2 class="qos-sec-title">Schedule</h2>
        <div class="qos-sec-line"></div>

        <div class="qos-sched-tabs">
            @foreach($symposium['schedule'] as $di => $day)
            <button class="qos-sched-tab {{ $di === 0 ? 'on' : '' }}"
                    onclick="qosSwitchDay({{ $di }},this)">{{ $day['label'] }}</button>
            @endforeach
        </div>

        @foreach($symposium['schedule'] as $di => $day)
        <div class="qos-sched-panel {{ $di === 0 ? 'on' : '' }}" id="schedDay{{ $di }}">
            <div style="overflow-x:auto;">
                <table class="qos-sched-table">
                    <thead>
                        <tr>
                            <th>TIME</th>
                            <th>DURATION</th>
                            <th>SPEAKER</th>
                            <th>TOPIC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($day['sessions'] as $sess)
                        <tr class="{{ $sess['is_break'] ? 'qos-sched-break' : '' }}">
                            <td class="qos-sched-time">{{ $sess['time'] }}</td>
                            <td class="qos-sched-dur">{{ $sess['duration'] }}</td>
                            <td class="qos-sched-spk">{{ $sess['speaker'] }}</td>
                            <td class="qos-sched-topic">{{ $sess['topic'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
    </div>
</section>

{{-- ══════════════════════════════════════════════
     6. PRICING
══════════════════════════════════════════════ --}}
{{-- <section class="qos-sec qos-pricing-sec" id="pricing">
    <div class="qos-sec-inner">
        <h2 class="qos-sec-title">Pricing</h2>
        <div class="qos-sec-line"></div>

        <div class="qos-pricing-wrap">
            <div class="qos-price-options">
                @foreach($symposium['pricing_options'] as $po)
                <div class="qos-price-option">
                    <i class="fas {{ $po['selected'] ? 'fa-check-circle' : 'fa-circle' }}"
                       style="{{ $po['selected'] ? 'color:#F5A623' : 'color:#ccc' }}"></i>
                    <span>{{ $po['label'] }}</span>
                </div>
                @endforeach
            </div>

            <div class="qos-price-card">
                <div class="qos-price-card-title">{{ $symposium['pricing']['title'] }}</div>
                <div class="qos-price-card-sub">{{ $symposium['pricing']['subtitle'] }}</div>
                <div class="qos-price-amount">
                    <sup>₹</sup>{{ number_format($symposium['pricing']['price']) }}
                </div>
                @if(!empty($symposium['pricing']['mrp']))
                <div class="qos-price-strike">₹{{ number_format($symposium['pricing']['mrp']) }}/-</div>
                @endif
                <button class="qos-price-pay-btn">Pay Now</button>
                <span class="qos-tnc-link"><a href="#">Terms &amp; Conditions</a></span>
            </div>
        </div>
    </div>
</section> --}}

{{-- ══════════════════════════════════════════════
     7. KNOW MORE + DOWNLOAD
══════════════════════════════════════════════ --}}
<section class="qos-know-sec" id="network">
    <div class="qos-know-inner">
        <div>
            <div class="qos-know-title">
                Know More about <span>{{ $symposium['title'] }}</span>
            </div>
            <button class="qos-know-cta-btn">
                <i class="fas fa-phone-alt"></i> Request a callback
            </button>
        </div>
        <div>
            <div class="qos-download-title">DOWNLOAD NOW</div>
            <div class="qos-download-btns">
                <a href="#" class="qos-dl-btn"><i class="fab fa-android"></i></a>
                <a href="#" class="qos-dl-btn"><i class="fab fa-apple"></i></a>
                <a href="#" class="qos-dl-btn"><i class="fas fa-desktop"></i></a>
            </div>
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     8. PAST DISTINGUISHED EXPERTS
══════════════════════════════════════════════ --}}
<section class="qos-sec qos-past-sec">
    <div class="qos-sec-inner">
        <h2 class="qos-sec-title">Past Distinguished Experts Attendee Symposium</h2>
        <div class="qos-sec-line"></div>
        <div class="qos-past-scroll">
            @foreach($symposium['past_experts'] as $pe)
            <div class="qos-past-person">
                <div class="qos-past-av">
                    @if(!empty($pe['avatar']))
                        <img src="{{ $pe['avatar'] }}" alt="{{ $pe['name'] }}">
                    @else
                        <i class="fas fa-user"></i>
                    @endif
                </div>
                <div class="qos-past-name">{{ $pe['name'] }}</div>
                <div class="qos-past-role">{{ $pe['role'] }}</div>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- ══════════════════════════════════════════════
     9. GALLERY
══════════════════════════════════════════════ --}}
<section class="qos-sec qos-gallery-sec" id="gallery">
    <div class="qos-sec-inner">
        <h2 class="qos-sec-title">Gallery</h2>
        <div class="qos-sec-line"></div>
        <div class="qos-gallery-grid">
            @foreach($symposium['gallery'] as $gi => $img)
            <div class="qos-gallery-item {{ $gi === 0 || $gi === 3 ? 'wide' : '' }}">
                <img src="{{ $img['src'] }}" alt="{{ $img['year'] }}" loading="lazy">
                <span class="qos-gallery-year">{{ $img['year'] }}</span>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- sticky side CTA --}}
<div class="qos-side-cta">
    <div class="qos-side-btn" title="Call"><i class="fas fa-phone-alt"></i></div>
    <div class="qos-side-btn" title="WhatsApp"><i class="fab fa-whatsapp"></i></div>
    <div class="qos-side-btn" title="Email"><i class="fas fa-envelope"></i></div>
    <div class="qos-side-btn" title="Chat"><i class="fas fa-comment-dots"></i></div>
</div>

</div>{{-- /.qos-wrap --}}

<script>
/* ── smooth scroll ── */
function qosScrollTo(id) {
    var el = document.getElementById(id);
    if (el) el.scrollIntoView({ behavior:'smooth', block:'start' });
}

/* ── schedule day tabs ── */
function qosSwitchDay(idx, btn) {
    document.querySelectorAll('.qos-sched-tab').forEach(function(b){ b.classList.remove('on'); });
    btn.classList.add('on');
    document.querySelectorAll('.qos-sched-panel').forEach(function(p,i){
        p.classList.toggle('on', i === idx);
    });
}

/* ── active nav on scroll ── */
window.addEventListener('scroll', function(){
    var sections = ['about','benefits','speakers','schedule','pricing','network','gallery'];
    var links    = document.querySelectorAll('.qos-nav-link');
    var scrollY  = window.scrollY + 100;
    sections.forEach(function(id, i){
        var el = document.getElementById(id);
        if (el && el.offsetTop <= scrollY && el.offsetTop + el.offsetHeight > scrollY) {
            links.forEach(function(l){ l.classList.remove('on'); });
            if (links[i]) links[i].classList.add('on');
        }
    });
});
</script>

@endsection