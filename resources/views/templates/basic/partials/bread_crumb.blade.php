@php $breadCrumb = getContent('bread_crumb.content', true); @endphp

<style>
/* =========================================
   INNER HERO — matches qh-wrap / qa-wrap
========================================= */
.qin-hero {
    position: relative;
    padding: 120px 0 60px;
    background: linear-gradient(135deg, #06101A 0%, #0F2848 55%, #06101A 100%);
    overflow: hidden;
    font-family: 'Exo 2', sans-serif;
}

/* animated grid */
.qin-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(245,166,35,.045) 1px, transparent 1px),
        linear-gradient(90deg, rgba(245,166,35,.045) 1px, transparent 1px);
    background-size: 56px 56px;
    animation: qinGridScroll 20s linear infinite;
    pointer-events: none;
}
@keyframes qinGridScroll {
    from { background-position: 0 0; }
    to   { background-position: 56px 56px; }
}

/* radial glow */
.qin-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 60% 80% at 50% 50%, rgba(245,166,35,.06) 0%, transparent 70%);
    pointer-events: none;
}

/* gold top line */
.qin-hero-bar {
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, transparent 0%, #F5A623 30%, #FFD06A 50%, #F5A623 70%, transparent 100%);
}

/* gold bottom fade */
.qin-hero-fade {
    position: absolute; bottom: 0; left: 0; right: 0; height: 40px;
    background: linear-gradient(to bottom, transparent, rgba(6,16,26,.6));
    pointer-events: none;
}

/* corner accents */
.qin-corner {
    position: absolute;
    width: 60px; height: 60px;
    pointer-events: none; z-index: 0;
}
.qin-corner.tl { top: 0; left: 0;
    border-top: 2px solid rgba(245,166,35,.22);
    border-left: 2px solid rgba(245,166,35,.22); }
.qin-corner.tr { top: 0; right: 0;
    border-top: 2px solid rgba(245,166,35,.22);
    border-right: 2px solid rgba(245,166,35,.22); }
.qin-corner.bl { bottom: 0; left: 0;
    border-bottom: 2px solid rgba(245,166,35,.12);
    border-left: 2px solid rgba(245,166,35,.12); }
.qin-corner.br { bottom: 0; right: 0;
    border-bottom: 2px solid rgba(245,166,35,.12);
    border-right: 2px solid rgba(245,166,35,.12); }

/* inner content */
.qin-inner {
    position: relative; z-index: 1;
    display: flex; flex-direction: column; align-items: center;
    gap: 14px; text-align: center;
}

/* welcome strip */
.qin-welcome {
    width: 100%;
    display: flex; align-items: center; justify-content: flex-start;
    margin-bottom: 4px;
}
.qin-welcome-pill {
    display: inline-flex; align-items: center; gap: 8px;
    background: rgba(245,166,35,.1);
    border: 1px solid rgba(245,166,35,.25);
    border-radius: 30px; padding: 6px 18px;
    font-family: 'Rajdhani', sans-serif; font-size: 14px;
    font-weight: 600; color: #E4EBF5; letter-spacing: .04em;
}
.qin-welcome-pill i { color: #F5A623; font-size: 13px; }
.qin-welcome-pill span { color: #F5A623; }

/* page title */
.qin-title {
    font-family: 'Rajdhani', sans-serif;
    font-size: clamp(32px, 5vw, 56px);
    font-weight: 700; color: #fff;
    line-height: 1.08; margin: 0;
    letter-spacing: .03em;
}
.qin-title-line {
    display: block; width: 60px; height: 3px;
    margin: 8px auto 0; border-radius: 2px;
    background: linear-gradient(90deg, rgba(255,255,255,.5), #F5A623);
}

/* breadcrumb */
.qin-breadcrumb {
    display: inline-flex; align-items: center; gap: 0;
    background: rgba(0,0,0,.25);
    border: 1px solid rgba(245,166,35,.18);
    border-radius: 30px; padding: 8px 20px;
    backdrop-filter: blur(8px);
    list-style: none; margin: 6px 0 0; flex-wrap: wrap;
    justify-content: center;
}
.qin-breadcrumb li {
    display: flex; align-items: center;
    font-family: 'Exo 2', sans-serif;
    font-size: 13px; font-weight: 600;
    letter-spacing: .04em; color: #7A90B5;
}
.qin-breadcrumb li a {
    color: rgba(255,255,255,.65);
    text-decoration: none;
    display: flex; align-items: center; gap: 5px;
    transition: color .2s;
}
.qin-breadcrumb li a:hover { color: #F5A623; }
.qin-breadcrumb li a i { font-size: 12px; }
/* separator */
.qin-breadcrumb li + li::before {
    content: '';
    display: inline-block;
    width: 5px; height: 5px; border-radius: 50%;
    background: rgba(245,166,35,.4);
    margin: 0 10px;
}
/* active crumb */
.qin-breadcrumb li:last-child {
    color: #F5A623; font-weight: 700;
}

@media (max-width: 576px) {
    .qin-hero { padding: 56px 0 44px; }
    .qin-welcome { justify-content: center; }
}
</style>

<!-- inner hero section -->
<section class="qin-hero">
    <div class="qin-hero-bar"></div>
    <div class="qin-corner tl"></div>
    <div class="qin-corner tr"></div>
    <div class="qin-corner bl"></div>
    <div class="qin-corner br"></div>
    <div class="qin-hero-fade"></div>

    <div class="container">
        <div class="qin-inner">

            {{-- Welcome strip (only when logged in) --}}
            @if(isset($userGlblNameData) && $userGlblNameData != null)
            <div class="qin-welcome">
                <div class="qin-welcome-pill">
                    <i class="fas fa-wave-square"></i>
                    @lang('Welcome back'),
                    <span>{{ $userGlblNameData->firstname . ' ' . $userGlblNameData->lastname }}</span>
                </div>
            </div>
            @endif

            {{-- Page title --}}
            <h2 class="qin-title">{{ __($pageTitle) }}</h2>
            <span class="qin-title-line"></span>

            {{-- Breadcrumb --}}
            <ul class="qin-breadcrumb">
                <li>
                    <a href="{{ route('home') }}">
                        <i class="fas fa-house"></i>
                        @lang('Home')
                    </a>
                </li>
                <li>{{ __($pageTitle) }}</li>
            </ul>

        </div>
    </div>
</section>
<!-- inner hero section end -->