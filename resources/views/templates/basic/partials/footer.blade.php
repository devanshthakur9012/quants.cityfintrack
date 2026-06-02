@php
    $policyPages = getContent('policy_pages.element', orderById:true);
    $socialIcons = getContent('social_icon.element', orderById:true);
@endphp

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — FOOTER  v2.0
   Dark terminal · TradingView-inspired
══════════════════════════════════════════════ */

.cq-footer {
    font-family: var(--h-f-sans, 'DM Sans', system-ui, sans-serif);
    background: #0B0E11;
    border-top: 1px solid rgba(255,255,255,.06);
    position: relative;
    overflow: hidden;
}

/* ── GRID TEXTURE ───────────────────────────── */
.cq-footer::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.025) 1px, transparent 1px);
    background-size: 48px 48px;
    mask-image: linear-gradient(to bottom, transparent, rgba(0,0,0,.5) 40%, transparent);
    pointer-events: none;
}
/* Green glow at top */
.cq-footer::after {
    content: '';
    position: absolute; top: 0; left: 50%; transform: translateX(-50%);
    width: 600px; height: 200px;
    background: radial-gradient(ellipse, rgba(125,255,0,.05) 0%, transparent 70%);
    pointer-events: none;
}

/* ── MAIN FOOTER BODY ───────────────────────── */
.cq-footer-body {
    position: relative; z-index: 1;
    max-width: 1280px; margin: 0 auto;
    padding: 64px 24px 0;
}

.cq-footer-grid {
    display: grid;
    grid-template-columns: 280px 1fr 1fr 1fr;
    gap: 48px;
    padding-bottom: 56px;
    border-bottom: 1px solid rgba(255,255,255,.06);
}
@media (max-width: 1024px) {
    .cq-footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
}
@media (max-width: 600px) {
    .cq-footer-grid { grid-template-columns: 1fr; gap: 32px; }
}

/* Brand column */
.cq-footer-brand {}
.cq-footer-logo {
    display: block; margin-bottom: 18px;
}
.cq-footer-logo img { height: 36px; width: auto; }
.cq-footer-tagline {
    font-size: 13px; color: #787B86; line-height: 1.7;
    margin-bottom: 22px; max-width: 220px;
}
.cq-footer-pill {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(125,255,0,.1);
    border: 1px solid rgba(125,255,0,.22);
    color: #7DFF00;
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; padding: 5px 12px;
    border-radius: 100px;
}
.cq-footer-pill-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: #7DFF00;
    animation: cqFooterPulse 2s infinite;
}
@keyframes cqFooterPulse {
    0%,100% { opacity: 1; } 50% { opacity: .3; }
}

/* Nav columns */
.cq-footer-col-label {
    font-family: var(--h-f-display, 'Syne', sans-serif);
    font-size: 11px; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; color: #7DFF00;
    margin-bottom: 18px;
    display: flex; align-items: center; gap: 8px;
}
.cq-footer-col-label::after {
    content: ''; flex: 1; height: 1px;
    background: rgba(125,255,0,.18);
}

.cq-footer-links {
    list-style: none; margin: 0; padding: 0;
    display: flex; flex-direction: column; gap: 4px;
}
.cq-footer-links a {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: 13px; color: #787B86; text-decoration: none;
    padding: 5px 0;
    border-bottom: 1px solid transparent;
    transition: color .2s, border-color .2s;
}
.cq-footer-links a:hover {
    color: #D1D4DC;
    border-bottom-color: rgba(125,255,0,.2);
}
.cq-footer-links a i {
    font-size: 12px; color: rgba(125,255,0,.5);
    transition: color .2s;
}
.cq-footer-links a:hover i { color: #7DFF00; }

/* App download column */
.cq-footer-apps { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; }
.cq-app-btn {
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 8px; padding: 10px 14px;
    color: #D1D4DC; text-decoration: none;
    transition: all .2s;
}
.cq-app-btn:hover {
    background: rgba(125,255,0,.08);
    border-color: rgba(125,255,0,.22);
    color: #fff;
}
.cq-app-btn i { font-size: 18px; color: #7DFF00; flex-shrink: 0; }
.cq-app-btn-text { display: flex; flex-direction: column; line-height: 1.2; }
.cq-app-btn-sub  { font-size: 10px; color: #787B86; }
.cq-app-btn-name { font-size: 13px; font-weight: 600; color: inherit; }

/* ── MINI STATS ROW ─────────────────────────── */
.cq-footer-stats {
    position: relative; z-index: 1;
    max-width: 1280px; margin: 0 auto;
    padding: 28px 24px;
    display: flex; gap: 0;
    border-bottom: 1px solid rgba(255,255,255,.06);
    flex-wrap: wrap;
}
.cq-fstat {
    flex: 1; min-width: 120px;
    padding: 8px 24px;
    border-right: 1px solid rgba(255,255,255,.06);
    text-align: center;
}
.cq-fstat:first-child { padding-left: 0; }
.cq-fstat:last-child  { border-right: none; }
.cq-fstat-val {
    font-family: var(--h-f-display, 'Syne', sans-serif);
    font-size: 22px; font-weight: 700; color: #fff;
    line-height: 1; margin-bottom: 4px;
}
.cq-fstat-lbl { font-size: 11px; color: #787B86; letter-spacing: .05em; text-transform: uppercase; }

/* ── BOTTOM BAR ─────────────────────────────── */
.cq-footer-bottom {
    position: relative; z-index: 1;
    max-width: 1280px; margin: 0 auto;
    padding: 20px 24px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; flex-wrap: wrap;
}

.cq-footer-copy {
    font-size: 12px; color: #787B86;
}
.cq-footer-copy a { color: #7DFF00; text-decoration: none; }
.cq-footer-copy a:hover { text-decoration: underline; }

/* Policy links */
.cq-footer-policy {
    display: flex; gap: 20px; flex-wrap: wrap;
    list-style: none; margin: 0; padding: 0;
}
.cq-footer-policy a {
    font-size: 12px; color: #787B86; text-decoration: none;
    transition: color .2s;
}
.cq-footer-policy a:hover { color: #D1D4DC; }

/* Social icons */
.cq-footer-social {
    display: flex; gap: 8px; list-style: none; margin: 0; padding: 0;
}
.cq-footer-social li a {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 7px;
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    color: #787B86; font-size: 14px;
    text-decoration: none; transition: all .2s;
}
.cq-footer-social li a:hover {
    background: rgba(125,255,0,.1);
    border-color: rgba(125,255,0,.25);
    color: #7DFF00;
    transform: translateY(-2px);
}

@media (max-width: 640px) {
    .cq-footer-bottom { flex-direction: column; text-align: center; }
    .cq-footer-policy { justify-content: center; }
    .cq-footer-social  { justify-content: center; }
    .cq-footer-stats   { gap: 16px; }
    .cq-fstat { border-right: none; }
}
</style>

<!-- ══════════════════════════════ FOOTER ══════════════════════════════ -->
<footer class="cq-footer">

    {{-- Main grid --}}
    <div class="cq-footer-body">
        <div class="cq-footer-grid">

            {{-- Brand --}}
            <div class="cq-footer-brand">
                <a href="{{ route('home') }}" class="cq-footer-logo">
                    <img src="{{ getImage(getFilePath('logoIcon') . '/logo.png') }}" alt="CityQuants">
                </a>
                <p class="cq-footer-tagline">
                    India's largest options trading analytics platform — built by traders, for traders.
                </p>
                <div class="cq-footer-pill">
                    <span class="cq-footer-pill-dot"></span>
                    Platform Live
                </div>
            </div>

            {{-- Platform links --}}
            <div>
                <div class="cq-footer-col-label">Platform</div>
                <ul class="cq-footer-links">
                    <li><a href="{{ route('cp.analyses.index') }}"><i class="fas fa-chart-line"></i> Option Analysis</a></li>
                    <li><a href="{{ route('webinars.index') }}"><i class="fas fa-video"></i> Webinars</a></li>
                    <li><a href="{{ route('courses') }}"><i class="fas fa-graduation-cap"></i> Courses</a></li>
                    <li><a href="{{ route('events.index') }}"><i class="fas fa-calendar-alt"></i> Events</a></li>
                    <li><a href="{{ route('cp.pricing') }}"><i class="fas fa-tags"></i> Pricing</a></li>
                    <li><a href="{{ route('media') }}"><i class="fas fa-photo-video"></i> Media</a></li>
                </ul>
            </div>

            {{-- Company links --}}
            <div>
                <div class="cq-footer-col-label">Company</div>
                <ul class="cq-footer-links">
                    <li><a href="{{ route('about') }}"><i class="fas fa-building"></i> About Us</a></li>
                    <li><a href="{{ route('home') }}"><i class="fas fa-home"></i> Home</a></li>
                    @foreach($policyPages as $policyPage)
                    <li>
                        <a href="{{ route('policy.pages', ['slug' => slug($policyPage->data_values->title), 'id' => $policyPage->id]) }}">
                            <i class="fas fa-file-alt"></i>
                            {{ __($policyPage->data_values->title) }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Download column --}}
            <div>
                <div class="cq-footer-col-label">Get the App</div>
                <div class="cq-footer-apps">
                    <a href="#" class="cq-app-btn">
                        <i class="fab fa-apple"></i>
                        <div class="cq-app-btn-text">
                            <span class="cq-app-btn-sub">Download on the</span>
                            <span class="cq-app-btn-name">App Store</span>
                        </div>
                    </a>
                    <a href="#" class="cq-app-btn">
                        <i class="fab fa-google-play"></i>
                        <div class="cq-app-btn-text">
                            <span class="cq-app-btn-sub">Get it on</span>
                            <span class="cq-app-btn-name">Google Play</span>
                        </div>
                    </a>
                    <a href="{{ route('cp.analyses.index') }}" class="cq-app-btn">
                        <i class="fas fa-globe"></i>
                        <div class="cq-app-btn-text">
                            <span class="cq-app-btn-sub">Open in browser</span>
                            <span class="cq-app-btn-name">Web Platform</span>
                        </div>
                    </a>
                </div>
            </div>

        </div>
    </div>

    {{-- Stats row --}}
    <div class="cq-footer-stats">
        <div class="cq-fstat">
            <div class="cq-fstat-val">6,500+</div>
            <div class="cq-fstat-lbl">Active Traders</div>
        </div>
        <div class="cq-fstat">
            <div class="cq-fstat-val">47+</div>
            <div class="cq-fstat-lbl">Analytics Tools</div>
        </div>
        <div class="cq-fstat">
            <div class="cq-fstat-val">200Hr+</div>
            <div class="cq-fstat-lbl">Free Education</div>
        </div>
        <div class="cq-fstat">
            <div class="cq-fstat-val">78.3%</div>
            <div class="cq-fstat-lbl">Retention Rate</div>
        </div>
        <div class="cq-fstat">
            <div class="cq-fstat-val">23Yr</div>
            <div class="cq-fstat-lbl">Team Experience</div>
        </div>
    </div>

    {{-- Bottom bar --}}
    <div class="cq-footer-bottom">

        {{-- Copyright --}}
        <p class="cq-footer-copy">
            &copy; {{ date('Y') }} <a href="{{ route('home') }}">CityQuants</a>. All rights reserved. &nbsp;·&nbsp; cityquants.com
        </p>

        {{-- Policy links --}}
        <ul class="cq-footer-policy">
            @foreach($policyPages as $policyPage)
            <li>
                <a href="{{ route('policy.pages', ['slug' => slug($policyPage->data_values->title), 'id' => $policyPage->id]) }}">
                    {{ __($policyPage->data_values->title) }}
                </a>
            </li>
            @endforeach
        </ul>

        {{-- Social icons --}}
        <ul class="cq-footer-social">
            @foreach($socialIcons as $icon)
            <li>
                <a href="{{ $icon->data_values->url }}" target="_blank" rel="noopener">
                    @php echo $icon->data_values->social_icon; @endphp
                </a>
            </li>
            @endforeach
        </ul>

    </div>

</footer>