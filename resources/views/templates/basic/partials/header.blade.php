<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — HEADER  v2.0
   Dark terminal style · TradingView-inspired
══════════════════════════════════════════════ */

:root {
    --h-bg:        #0B0E11;
    --h-surface:   #131722;
    --h-border:    rgba(255,255,255,.07);
    --h-border2:   rgba(255,255,255,.12);
    --h-lime:      #7DFF00;
    --h-lime-dim:  rgba(125,255,0,.1);
    --h-lime-glow: rgba(125,255,0,.18);
    --h-text:      #D1D4DC;
    --h-muted:     #787B86;
    --h-f-sans:    'DM Sans', system-ui, sans-serif;
    --h-f-display: 'Syne', sans-serif;
    --h-f-mono:    'Space Grotesk', monospace;
    --h-height:    64px;
}

/* ── BASE ───────────────────────────────────── */
.cq-header {
    position: fixed; top: 0; left: 0; right: 0;
    z-index: 1000;
    height: var(--h-height);
    background: rgba(11,14,17,.82);
    border-bottom: 1px solid var(--h-border);
    backdrop-filter: blur(20px) saturate(180%);
    -webkit-backdrop-filter: blur(20px) saturate(180%);
    font-family: var(--h-f-sans);
    transition: background .3s, box-shadow .3s;
}
.cq-header.scrolled {
    background: rgba(11,14,17,.96);
    box-shadow: 0 1px 0 var(--h-border), 0 4px 32px rgba(0,0,0,.4);
}

.cq-header-inner {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 24px;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 0;
}

/* ── LOGO ───────────────────────────────────── */
.cq-logo {
    display: flex; align-items: center;
    flex-shrink: 0; margin-right: 32px;
    text-decoration: none;
}
.cq-logo img {
    height: 34px; width: auto;
    display: block;
    transition: opacity .2s;
}
.cq-logo:hover img { opacity: .85; }

/* ── OPTION ANALYSIS PILL ───────────────────── */
.cq-oa-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--h-lime);
    color: #000;
    font-family: var(--h-f-display);
    font-size: 11px; font-weight: 700; letter-spacing: .08em;
    text-transform: uppercase;
    padding: 6px 14px; border-radius: 100px;
    text-decoration: none; flex-shrink: 0;
    margin-right: 28px;
    transition: all .2s;
    box-shadow: 0 0 16px rgba(125,255,0,.2);
}
.cq-oa-pill:hover {
    background: #8FFF1A;
    box-shadow: 0 0 24px rgba(125,255,0,.35);
    transform: translateY(-1px);
    color: #000;
}
.cq-oa-pill-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #000; opacity: .5;
    flex-shrink: 0;
}

/* ── NAV LINKS ──────────────────────────────── */
.cq-nav {
    display: flex; align-items: center; gap: 2px;
    justify-content:end;margin-right: 15px;
    list-style: none; margin: 0; padding: 0;
    flex: 1;max-width: max-content;
}
.cq-nav li { position: relative; }
.cq-nav a {
    display: block;
    padding: 8px 12px;
    font-size: 12px; font-weight: 600;
    letter-spacing: .07em; text-transform: uppercase;
    color: var(--h-muted);
    text-decoration: none;
    border-radius: 6px;
    transition: color .2s, background .2s;
    position: relative;
    white-space: nowrap;
}
.cq-nav a::after {
    content: '';
    position: absolute; bottom: 4px; left: 12px; right: 12px;
    height: 1px; border-radius: 1px;
    background: var(--h-lime);
    transform: scaleX(0); transform-origin: center;
    transition: transform .25s ease;
}
.cq-nav a:hover,
.cq-nav a.active-link {
    color: #fff;
    background: rgba(255,255,255,.04);
}
.cq-nav a:hover::after,
.cq-nav a.active-link::after { transform: scaleX(1); }

/* ── RIGHT SIDE ─────────────────────────────── */
.cq-header-right {
    display: flex; align-items: center;
    gap: 6px; margin-left: auto; flex-shrink: 0;
}

/* Divider */
.cq-hdr-divider {
    width: 1px; height: 20px;
    background: var(--h-border2);
    margin: 0 6px;
}

/* Language partial wrapper */
.cq-header-right .language-select-area,
.cq-header-right .lang-dropdown-wrap,
.cq-header-right select {
    background: transparent !important;
    border: none !important;
    color: var(--h-muted) !important;
    font-size: 12px !important;
}

/* User icon button */
.cq-user-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px; border-radius: 7px;
    border: 1px solid var(--h-border2);
    background: rgba(255,255,255,.04);
    color: var(--h-muted);
    font-size: 16px;
    text-decoration: none;
    transition: all .2s;
}
.cq-user-btn:hover {
    background: var(--h-lime-dim);
    border-color: rgba(125,255,0,.3);
    color: var(--h-lime);
}

/* ── MOBILE TOGGLE ──────────────────────────── */
.cq-toggler {
    display: none;
    flex-direction: column; justify-content: center; align-items: center;
    width: 36px; height: 36px; gap: 5px;
    background: rgba(255,255,255,.04);
    border: 1px solid var(--h-border2);
    border-radius: 7px; cursor: pointer;
    margin-left: auto;
    transition: all .2s;
}
.cq-toggler:hover { border-color: rgba(125,255,0,.3); background: var(--h-lime-dim); }
.cq-toggler span {
    display: block; width: 18px; height: 1.5px;
    background: var(--h-text); border-radius: 2px;
    transition: all .3s;
}
.cq-toggler.open span:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
.cq-toggler.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.cq-toggler.open span:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

/* ── MOBILE DRAWER ──────────────────────────── */
.cq-mobile-nav {
    position: fixed;
    top: var(--h-height); left: 0; right: 0;
    z-index: 999;
    background: rgba(11,14,17,.98);
    border-bottom: 1px solid var(--h-border);
    backdrop-filter: blur(24px);
    padding: 16px 24px 24px;
    transform: translateY(-110%);
    opacity: 0;
    transition: transform .35s cubic-bezier(.4,0,.2,1), opacity .35s;
    pointer-events: none;
}
.cq-mobile-nav.open {
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
}
.cq-mobile-nav ul {
    list-style: none; margin: 0; padding: 0;
    display: flex; flex-direction: column; gap: 2px;
}
.cq-mobile-nav a {
    display: block; padding: 12px 14px;
    font-size: 13px; font-weight: 600; letter-spacing: .07em;
    text-transform: uppercase; color: var(--h-muted);
    text-decoration: none; border-radius: 7px;
    border-left: 2px solid transparent;
    transition: all .2s;
}
.cq-mobile-nav a:hover,
.cq-mobile-nav a.active-link {
    color: var(--h-lime);
    background: var(--h-lime-dim);
    border-left-color: var(--h-lime);
}
.cq-mobile-nav-bottom {
    margin-top: 16px; padding-top: 16px;
    border-top: 1px solid var(--h-border);
    display: flex; align-items: center; gap: 10px;
}
.cq-mobile-oa {
    display: inline-flex; align-items: center; gap: 6px;
    background: var(--h-lime); color: #000;
    font-family: var(--h-f-display);
    font-size: 12px; font-weight: 700; letter-spacing: .07em;
    padding: 9px 18px; border-radius: 100px; text-decoration: none;
    flex: 1; justify-content: center;
    transition: all .2s;
}
.cq-mobile-oa:hover { background: #8FFF1A; color: #000; }

/* ── TICKER BAR (optional — below header) ───── */
.cq-ticker-bar {
    position: fixed; top: var(--h-height); left: 0; right: 0;
    z-index: 998;
    height: 28px;
    background: var(--h-surface);
    border-bottom: 1px solid var(--h-border);
    overflow: hidden;
    display: flex; align-items: center;
}
.cq-ticker-track {
    display: flex; gap: 36px; align-items: center;
    white-space: nowrap;
    animation: cqTickerRoll 28s linear infinite;
}
@keyframes cqTickerRoll {
    from { transform: translateX(0); }
    to   { transform: translateX(-50%); }
}
.cq-ticker-item {
    display: inline-flex; align-items: center; gap: 7px;
    font-family: var(--h-f-mono); font-size: 11px;
    color: var(--h-muted); flex-shrink: 0;
}
.cq-ticker-item .t-sym { color: var(--h-text); font-weight: 600; }
.cq-ticker-item .t-up  { color: #7DFF00; }
.cq-ticker-item .t-dn  { color: #EF5350; }
.cq-ticker-sep { color: rgba(255,255,255,.08); }

/* Push page content below fixed header + ticker */
body.has-cq-header { padding-top: calc(var(--h-height) + 28px); }
body.has-cq-header.no-ticker { padding-top: var(--h-height); }

/* Responsive */
@media (max-width: 1199px) {
    .cq-nav { display: none; }
    .cq-oa-pill { display: none; }
    .cq-toggler { display: flex; }
}
@media (max-width: 600px) {
    .cq-header-inner { padding: 0 16px; }
}
</style>

<!-- ═══════════════════════════ HEADER ═══════════════════════════ -->
<header class="cq-header" id="cqHeader">
    <div class="cq-header-inner">

        {{-- Logo --}}
        <a class="cq-logo" href="{{ route('home') }}">
            <img src="{{ getImage(getFilePath('logoIcon') . '/logo.png') }}" alt="CityQuants">
        </a>

        <div class="d-flex justify-content-end align-items-center">
            {{-- Option Analysis pill (desktop) --}}
            <a href="{{ route('cp.analyses.index') }}" class="cq-oa-pill d-none d-xl-inline-flex">
                <span class="cq-oa-pill-dot"></span>
                Option Analysis
            </a>

            {{-- Nav links (desktop) --}}
            <ul class="cq-nav d-none d-xl-flex">
                <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) class="active-link" @endif>@lang('Home')</a></li>
                <li><a href="{{ route('webinars.index') }}" @if(request()->routeIs('webinars.*')) class="active-link" @endif>@lang('Webinars')</a></li>
                <li><a href="{{ route('courses') }}" @if(request()->routeIs('courses')) class="active-link" @endif>@lang('Courses')</a></li>
                <li><a href="{{ route('events.index') }}" @if(request()->routeIs('events.*')) class="active-link" @endif>@lang('Event')</a></li>
                <li><a href="{{ route('media') }}" @if(request()->routeIs('media')) class="active-link" @endif>@lang('Media')</a></li>
                <li><a href="{{ route('cp.pricing') }}" @if(request()->routeIs('cp.pricing')) class="active-link" @endif>@lang('Pricing')</a></li>
                <li><a href="{{ route('about') }}" @if(request()->routeIs('about')) class="active-link" @endif>@lang('About')</a></li>
            </ul>
        </div>

        {{-- Right side --}}
        <div class="cq-header-right">

            {{-- Auth icon --}}
            @auth
                <a href="{{ route('user.dashboard') }}" class="cq-user-btn" title="Dashboard">
                    <i class="las la-home"></i>
                </a>
            @else
                <a href="{{ route('user.login') }}" class="cq-user-btn" title="Login">
                    <i class="las la-user"></i>
                </a>
            @endauth
        </div>

        {{-- Mobile toggle --}}
        <button class="cq-toggler d-xl-none" id="cqToggler" aria-label="Menu" type="button">
            <span></span><span></span><span></span>
        </button>

    </div>
</header>

{{-- Ticker bar --}}
<div class="cq-ticker-bar" id="cqTicker">
    <div class="cq-ticker-track">
        @php
        $tickers = [
            ['sym'=>'NIFTY 50', 'price'=>'24,512.30', 'chg'=>'+92.15', 'pct'=>'+0.38%', 'up'=>true],
            ['sym'=>'BANKNIFTY','price'=>'52,144.80', 'chg'=>'+318.60','pct'=>'+0.61%', 'up'=>true],
            ['sym'=>'FINNIFTY', 'price'=>'23,872.45', 'chg'=>'-33.20', 'pct'=>'-0.14%', 'up'=>false],
            ['sym'=>'MIDCPNIFTY','price'=>'11,432.10','chg'=>'+25.30', 'pct'=>'+0.22%', 'up'=>true],
            ['sym'=>'INDIA VIX', 'price'=>'13.82',    'chg'=>'-0.29',  'pct'=>'-2.10%', 'up'=>false],
            ['sym'=>'SENSEX',   'price'=>'80,432.55', 'chg'=>'+330.40','pct'=>'+0.41%', 'up'=>true],
        ];
        @endphp
        {{-- Double the array so the scroll looks seamless --}}
        @foreach(array_merge($tickers,$tickers) as $t)
        <span class="cq-ticker-item">
            <span class="t-sym">{{ $t['sym'] }}</span>
            <span>{{ $t['price'] }}</span>
            <span class="{{ $t['up'] ? 't-up' : 't-dn' }}">{{ $t['up'] ? '▲' : '▼' }} {{ $t['pct'] }}</span>
        </span>
        <span class="cq-ticker-sep">·</span>
        @endforeach
    </div>
</div>

{{-- Mobile nav drawer --}}
<nav class="cq-mobile-nav" id="cqMobileNav" aria-label="Mobile navigation">
    <ul>
        <li><a href="{{ route('home') }}" @if(request()->routeIs('home')) class="active-link" @endif>@lang('Home')</a></li>
        <li><a href="{{ route('webinars.index') }}" @if(request()->routeIs('webinars.*')) class="active-link" @endif>@lang('Webinars')</a></li>
        <li><a href="{{ route('courses') }}" @if(request()->routeIs('courses')) class="active-link" @endif>@lang('Courses')</a></li>
        <li><a href="{{ route('events.index') }}" @if(request()->routeIs('events.*')) class="active-link" @endif>@lang('Event')</a></li>
        <li><a href="{{ route('media') }}" @if(request()->routeIs('media')) class="active-link" @endif>@lang('Media')</a></li>
        <li><a href="{{ route('cp.pricing') }}" @if(request()->routeIs('cp.pricing')) class="active-link" @endif>@lang('Pricing')</a></li>
        <li><a href="{{ route('about') }}" @if(request()->routeIs('about')) class="active-link" @endif>@lang('About')</a></li>
    </ul>
    <div class="cq-mobile-nav-bottom">
        <a href="{{ route('cp.analyses.index') }}" class="cq-mobile-oa">
            <i class="fas fa-chart-line" style="font-size:13px;"></i>
            Option Analysis
        </a>
        @auth
            <a href="{{ route('user.dashboard') }}" class="cq-user-btn">
                <i class="las la-home"></i>
            </a>
        @else
            <a href="{{ route('user.login') }}" class="cq-user-btn">
                <i class="las la-user"></i>
            </a>
        @endauth
    </div>
</nav>

<script>
(function () {
    var header   = document.getElementById('cqHeader');
    var toggler  = document.getElementById('cqToggler');
    var mobileNav = document.getElementById('cqMobileNav');
    var open = false;

    /* Scroll → add .scrolled class */
    window.addEventListener('scroll', function () {
        header.classList.toggle('scrolled', window.scrollY > 10);
    }, { passive: true });

    /* Toggle mobile drawer */
    if (toggler) {
        toggler.addEventListener('click', function () {
            open = !open;
            toggler.classList.toggle('open', open);
            mobileNav.classList.toggle('open', open);
            document.body.style.overflow = open ? 'hidden' : '';
        });
    }

    /* Close drawer on link click */
    if (mobileNav) {
        mobileNav.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                open = false;
                toggler.classList.remove('open');
                mobileNav.classList.remove('open');
                document.body.style.overflow = '';
            });
        });
    }

    /* body padding so content isn't behind fixed header + ticker */
    document.body.classList.add('has-cq-header');
})();
</script>