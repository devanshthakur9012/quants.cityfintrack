{{-- FILE: resources/views/themes/{active_theme}/webinar-detail.blade.php --}}

@extends($activeTemplate.'layouts.frontend')

@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
.wd { font-family:'Exo 2',sans-serif; background:#fff; color:#1a1a2e; }
.wd * { box-sizing:border-box; }
.wd h1,.wd h2,.wd h3,.wd h4 { font-family:'Rajdhani',sans-serif; letter-spacing:.02em; }
.wd a { text-decoration:none; }

/* BREADCRUMB */
.wd-crumb { background:#f8f9fc; border-bottom:1px solid #eee; padding:12px 48px; font-size:13px; color:#888; }
.wd-crumb a { color:#F5A623; }
.wd-crumb span { margin:0 6px; }
@media(max-width:768px){ .wd-crumb { padding:12px 16px; } }

/* TOP SECTION */
.wd-top {
    padding:48px; display:grid;
    grid-template-columns:1fr 360px; gap:40px; align-items:start;
}
@media(max-width:940px){ .wd-top { grid-template-columns:1fr; padding:24px 16px; } }

/* Thumb */
.wd-thumb-wrap { border-radius:14px; overflow:hidden; background:#1a1a2e; margin-bottom:24px; }
.wd-thumb-wrap img { width:100%; display:block; }

/* Badges */
.wd-badges { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
.wd-badge {
    font-size:11px; font-weight:700; letter-spacing:.05em;
    padding:4px 12px; border-radius:20px; text-transform:uppercase;
    display:inline-flex; align-items:center; gap:6px;
}
.wd-badge.live     { background:#e53935; color:#fff; }
.wd-badge.upcoming { background:#43a047; color:#fff; }
.wd-badge.past     { background:#607d8b; color:#fff; }
.wd-badge.free     { background:#e8f5e9; color:#2e7d32; }
.wd-badge.paid     { background:#fff3e0; color:#e65100; }
.wd-badge.online   { background:#e3f2fd; color:#1565c0; }
.wd-badge.offline  { background:#fce4ec; color:#c62828; }
.wd-badge.hybrid   { background:#f3e5f5; color:#6a1b9a; }
@keyframes pulseDot { 0%,100%{opacity:1} 50%{opacity:.4} }
.wd-live-dot { width:7px; height:7px; border-radius:50%; background:#fff; animation:pulseDot 1.2s infinite; }

.wd-title { font-size:clamp(24px,3.5vw,36px); font-weight:700; color:#1a1a2e; margin:0 0 16px; line-height:1.1; }

.wd-speaker-line { font-size:14px; color:#555; margin-bottom:16px; }
.wd-speaker-line strong { color:#1a1a2e; }

.wd-meta-row  { display:flex; flex-wrap:wrap; gap:18px; margin-bottom:18px; }
.wd-meta-item { display:flex; align-items:center; gap:8px; font-size:14px; color:#555; }
.wd-meta-item i { color:#F5A623; width:16px; text-align:center; }
.wd-meta-item strong { color:#1a1a2e; }

/* Seats bar */
.wd-seats-bar {
    background:#f7f8fc; border:1px solid #e8e8e8; border-radius:9px;
    padding:12px 16px; margin-bottom:20px;
}
.wd-seats-label { font-size:13px; color:#555; margin-bottom:6px; }
.wd-seats-progress { height:6px; background:#e0e0e0; border-radius:3px; }
.wd-seats-fill { height:100%; border-radius:3px; background:#F5A623; }
.wd-seats-low { color:#e53935; font-weight:700; }

/* Address box */
.wd-address-box {
    display:flex; align-items:flex-start; gap:10px;
    background:#f8f9fc; border:1px solid #e8e8e8; border-radius:8px;
    padding:12px 14px; margin-bottom:16px; font-size:13px; color:#555;
}
.wd-address-box i { color:#F5A623; margin-top:2px; flex-shrink:0; }

/* REG CARD */
.wd-reg-card {
    background:#fff; border-radius:14px; border:1px solid #e8e8e8;
    box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden;
    position:sticky; top:80px;
}
.wd-reg-price-wrap { padding:22px 22px 0; }
.wd-reg-price { font-family:'Rajdhani',sans-serif; font-size:32px; font-weight:700; color:#1a1a2e; line-height:1; }
.wd-reg-price .orig { text-decoration:line-through; color:#aaa; font-size:18px; margin-right:6px; font-weight:400; }
.wd-reg-price .disc { font-size:13px; color:#43a047; font-weight:700; margin-left:6px; }
.wd-reg-date-label { font-size:13px; color:#888; padding:6px 22px 14px; }
.wd-reg-body { padding:0 22px 22px; }

.wd-reg-btn {
    display:block; width:100%; text-align:center;
    font-weight:700; font-size:17px; padding:15px; border-radius:10px;
    font-family:'Rajdhani',sans-serif; letter-spacing:.04em;
    border:none; cursor:pointer; transition:background .2s;
}
.wd-reg-btn.paid-btn     { background:#F5A623; color:#000; }
.wd-reg-btn.paid-btn:hover { background:#d4890e; }
.wd-reg-btn.free-btn     { background:#43a047; color:#fff; }
.wd-reg-btn.free-btn:hover { background:#2e7d32; }
.wd-reg-btn.enrolled-btn { background:#e8f5e9; color:#2e7d32; cursor:default; }
.wd-reg-btn.full-btn     { background:#f5f5f5; color:#aaa; cursor:not-allowed; }
.wd-reg-btn.past-btn     { background:#f5f5f5; color:#aaa; cursor:not-allowed; }

.wd-seats-note { text-align:center; font-size:12px; margin-top:8px; }
.wd-reg-meta { margin-top:16px; display:flex; flex-direction:column; gap:9px; }
.wd-reg-meta-row { display:flex; align-items:center; gap:10px; font-size:13px; color:#555; }
.wd-reg-meta-row i { color:#F5A623; width:16px; text-align:center; }

/* BODY SECTIONS */
.wd-body { padding:0 48px 60px; }
@media(max-width:940px){ .wd-body { padding:0 16px 48px; } }

.wd-section { border-bottom:1px solid #f0f0f0; padding:32px 0; }
.wd-section:last-child { border-bottom:none; }
.wd-section-title { font-size:26px; font-weight:700; color:#1a1a2e; margin:0 0 22px; }

/* YouTube */
.wd-video-wrap { position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:12px; }
.wd-video-wrap iframe { position:absolute; top:0; left:0; width:100%; height:100%; border:none; }

/* Speakers */
.wd-speaker-cards { display:flex; flex-wrap:wrap; gap:20px; }
.wd-speaker-card  {
    background:#f7f8fc; border:1px solid #e8e8e8; border-radius:12px;
    padding:20px; display:flex; gap:16px; align-items:flex-start;
    flex:1 1 calc(50% - 10px); min-width:260px;
}
@media(max-width:600px){ .wd-speaker-card { flex:1 1 100%; } }
.wd-speaker-avatar {
    width:72px; height:72px; border-radius:50%; flex-shrink:0;
    background:#1a56db; color:#fff; font-size:24px; font-weight:700;
    display:flex; align-items:center; justify-content:center; overflow:hidden;
}
.wd-speaker-avatar img { width:100%; height:100%; object-fit:cover; }
.wd-speaker-name { font-family:'Rajdhani',sans-serif; font-size:18px; font-weight:700; color:#1a1a2e; margin-bottom:2px; }
.wd-speaker-role { font-size:12px; color:#F5A623; font-weight:600; margin-bottom:8px; text-transform:uppercase; letter-spacing:.04em; }
.wd-speaker-bio  { font-size:13px; color:#666; line-height:1.6; }

/* FAQ */
.wd-faq-item { border:1px solid #e8e8e8; border-radius:9px; margin-bottom:10px; overflow:hidden; }
.wd-faq-q {
    width:100%; text-align:left; background:#fff; border:none; padding:16px 20px;
    font-size:14px; font-weight:600; color:#1a1a2e; cursor:pointer;
    display:flex; justify-content:space-between; align-items:center;
    font-family:'Exo 2',sans-serif; transition:background .2s;
}
.wd-faq-q:hover { background:#f8f9fc; }
.wd-faq-a { display:none; padding:0 20px 16px; font-size:13px; color:#555; line-height:1.7; }
.wd-faq-a.open { display:block; }
.wd-faq-icon { transition:transform .25s; flex-shrink:0; margin-left:10px; }
.wd-faq-q.faq-open .wd-faq-icon { transform:rotate(45deg); }

/* Tools */
.wd-tools-section { background:#f7f8fc; padding:40px 48px; }
@media(max-width:940px){ .wd-tools-section { padding:28px 16px; } }
.wd-tools-title { font-size:28px; font-weight:700; color:#1a1a2e; text-align:center; margin:0 0 6px; }
.wd-tools-sub   { font-size:14px; color:#888; text-align:center; margin:0 0 30px; }
.wd-tools-grid  { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; max-width:900px; margin:0 auto; }
@media(max-width:700px){ .wd-tools-grid { grid-template-columns:1fr; } }
.wd-tool-card   { background:#fff; border:1px solid #e8e8e8; border-radius:10px; overflow:hidden; text-align:center; padding-bottom:12px; }
.wd-tool-card img { width:100%; display:block; border-bottom:1px solid #f0f0f0; }
.wd-tool-card-title { font-size:14px; font-weight:600; color:#1a1a2e; margin:10px 10px 4px; }
.wd-tool-card-desc  { font-size:12px; color:#888; margin:0 10px; }

/* Related */
.wd-related-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
@media(max-width:700px){ .wd-related-grid { grid-template-columns:1fr; } }
.wd-rel-card { background:#fff; border-radius:9px; overflow:hidden; border:1px solid #e8e8e8; transition:box-shadow .2s; display:block; }
.wd-rel-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.08); }
.wd-rel-card img { width:100%; aspect-ratio:16/9; object-fit:cover; display:block; }
.wd-rel-card-body { padding:10px 12px; }
.wd-rel-card-title { font-size:13px; font-weight:600; color:#1a1a2e; line-height:1.3; margin-bottom:4px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.wd-rel-card-meta  { font-size:11px; color:#999; }
.wd-rel-card-price { padding:0 12px 10px; font-size:13px; font-weight:700; color:#F5A623; }

/* Mobile sticky bar */
.wd-sticky-bar {
    display:none; position:fixed; bottom:0; left:0; right:0; z-index:500;
    background:#1a1a2e; padding:12px 16px;
    align-items:center; justify-content:space-between; gap:12px;
    box-shadow:0 -4px 20px rgba(0,0,0,.2);
}
@media(max-width:940px){ .wd-sticky-bar { display:flex; } }
.wd-sticky-price { color:#F5A623; font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; }
.wd-sticky-btn {
    background:#F5A623; color:#000; font-weight:700; padding:11px 24px;
    border-radius:9px; border:none; cursor:pointer; font-size:15px;
    font-family:'Rajdhani',sans-serif; white-space:nowrap; transition:background .2s;
}
.wd-sticky-btn:hover { background:#d4890e; }
.wd-sticky-btn.free  { background:#43a047; color:#fff; }
.wd-sticky-btn.enrolled { background:#e8f5e9; color:#2e7d32; cursor:default; }
</style>

<div class="wd">

{{-- BREADCRUMB --}}
<div class="wd-crumb">
    <a href="{{ route('home') }}">Home</a><span>/</span>
    <a href="{{ route('webinars.index') }}">Webinars</a><span>/</span>
    {{ Str::limit($webinar->title, 55) }}
</div>

{{-- TOP SECTION --}}
<div class="wd-top">

    {{-- LEFT: Info --}}
    <div>
        {{-- Thumbnail --}}
        <div class="wd-thumb-wrap">
            <img src="{{ $webinar->thumbnail_url }}" alt="{{ $webinar->title }}">
        </div>

        {{-- Badges --}}
        <div class="wd-badges">
            @if($webinar->status === 'live')
                <span class="wd-badge live"><span class="wd-live-dot"></span> LIVE</span>
            @elseif($webinar->status === 'upcoming')
                <span class="wd-badge upcoming">UPCOMING</span>
            @else
                <span class="wd-badge past">PAST</span>
            @endif
            <span class="wd-badge {{ $webinar->type }}">{{ strtoupper($webinar->type) }}</span>
            <span class="wd-badge {{ $webinar->mode }}">{{ ucfirst($webinar->mode) }}</span>
        </div>

        {{-- Title --}}
        <h1 class="wd-title">{{ $webinar->title }}</h1>

        {{-- Speaker line (first speaker shown inline) --}}
        @if($webinar->speakers->isNotEmpty())
        <div class="wd-speaker-line">
            Speaker —
            @foreach($webinar->speakers as $sp)
                <strong>{{ trim($sp->firstname . ' ' . $sp->lastname) }}</strong>{{ !$loop->last ? ', ' : '' }}
            @endforeach
        </div>
        @endif

        {{-- Meta --}}
        <div class="wd-meta-row">
            <div class="wd-meta-item"><i class="fas fa-signal"></i> <span>{{ $webinar->level }}</span></div>
            <div class="wd-meta-item"><i class="fas fa-language"></i> <span>{{ $webinar->language }}</span></div>
            @if($webinar->webinar_date)
            <div class="wd-meta-item">
                <i class="fas fa-calendar-alt" @if($webinar->status==='past') style="color:#e53935" @endif></i>
                <strong>{{ $webinar->webinar_date->format('d M Y, h:i A') }}</strong>
            </div>
            @endif
            @if($webinar->duration)
            <div class="wd-meta-item"><i class="fas fa-clock"></i> <span>{{ $webinar->duration }}</span></div>
            @endif
        </div>

        {{-- Offline address --}}
        @if(in_array($webinar->mode, ['offline','hybrid']) && $webinar->address)
        <div class="wd-address-box">
            <i class="fas fa-map-marker-alt"></i>
            <span><strong>Venue:</strong> {{ $webinar->address }}</span>
        </div>
        @endif

        {{-- Seats --}}
        @if($webinar->total_seats)
        @php $pct = min(100, ($webinar->total_enrolled / $webinar->total_seats) * 100); @endphp
        <div class="wd-seats-bar">
            <div class="wd-seats-label">
                <strong>{{ $webinar->total_enrolled }}</strong> registered
                &nbsp;·&nbsp;
                <span class="{{ $webinar->seats_left < 20 ? 'wd-seats-low' : '' }}">
                    {{ $webinar->seats_left }} seats left out of {{ $webinar->total_seats }}
                </span>
            </div>
            <div class="wd-seats-progress">
                <div class="wd-seats-fill" style="width:{{ $pct }}%"></div>
            </div>
        </div>
        @else
        <div class="wd-seats-bar">
            <div class="wd-seats-label">
                <i class="fas fa-users" style="color:#F5A623;"></i>
                <strong>{{ $webinar->total_enrolled }}</strong> people registered
            </div>
        </div>
        @endif
    </div>

    {{-- RIGHT: Reg Card (desktop sticky) --}}
    <div id="regCardDesktop" class="d-none d-lg-block">
        @include($activeTemplate . 'partials.webinar-reg-card')
    </div>

</div>{{-- /.wd-top --}}

{{-- Mobile: reg card below info --}}
<div class="d-lg-none" style="padding:0 16px 24px;">
    @include($activeTemplate . 'partials.webinar-reg-card')
</div>

{{-- BODY SECTIONS --}}
<div class="wd-body">

    {{-- YouTube Video --}}
    @if($webinar->youtube_url)
    @php
        preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_\-]{11})/', $webinar->youtube_url, $m);
        $ytId = $m[1] ?? null;
    @endphp
    @if($ytId)
    <div class="wd-section">
        <h2 class="wd-section-title">Preview Video</h2>
        <div class="wd-video-wrap">
            <iframe src="https://www.youtube.com/embed/{{ $ytId }}?rel=0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen loading="lazy"></iframe>
        </div>
    </div>
    @endif
    @endif

    {{-- Speakers --}}
    @if($webinar->speakers->isNotEmpty())
    <div class="wd-section">
        <h2 class="wd-section-title">Speaker{{ $webinar->speakers->count() > 1 ? 's' : '' }}</h2>
        <div class="wd-speaker-cards">
            @foreach($webinar->speakers as $sp)
            @php
                $spName = trim($sp->firstname . ' ' . $sp->lastname);
                $spRole = optional($sp->employeeProfile)->designation ?? 'Trainer';
                $spBio  = optional($sp->employeeProfile)->bio ?? '';
                $spImg  = $sp->profile_pic ? asset(getFilePath('userProfile') . '/' . $sp->profile_pic) : null;
            @endphp
            <div class="wd-speaker-card">
                <div class="wd-speaker-avatar">
                    @if($spImg)
                        <img src="{{ $spImg }}" alt="{{ $spName }}">
                    @else
                        {{ strtoupper(substr($sp->firstname, 0, 1)) }}
                    @endif
                </div>
                <div>
                    <div class="wd-speaker-name">{{ $spName }}</div>
                    <div class="wd-speaker-role">{{ $spRole }}</div>
                    @if($spBio)
                        <div class="wd-speaker-bio">{{ $spBio }}</div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- FAQs --}}
    @if($webinar->faqs->isNotEmpty())
    <div class="wd-section">
        <h2 class="wd-section-title">FAQ's</h2>
        @foreach($webinar->faqs as $faq)
        <div class="wd-faq-item">
            <button class="wd-faq-q" type="button" onclick="toggleFaq(this)">
                {{ $faq->question }}
                <i class="fas fa-plus wd-faq-icon"></i>
            </button>
            <div class="wd-faq-a">{{ $faq->answer }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Related Webinars --}}
    @if($relatedWebinars->isNotEmpty())
    <div class="wd-section">
        <h2 class="wd-section-title">Related Webinars</h2>
        <div class="wd-related-grid">
            @foreach($relatedWebinars as $r)
            <a href="{{ route('webinars.detail', $r->slug) }}" class="wd-rel-card">
                <img src="{{ $r->thumbnail_url }}" alt="{{ $r->title }}" loading="lazy">
                <div class="wd-rel-card-body">
                    <div class="wd-rel-card-title">{{ $r->title }}</div>
                    <div class="wd-rel-card-meta">{{ $r->language }} · {{ $r->level }}</div>
                </div>
                <div class="wd-rel-card-price">
                    {{ $r->type === 'free' ? 'FREE' : '₹' . number_format($r->price) }}
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

</div>{{-- /.wd-body --}}

{{-- TOOL SHOWCASE (full width) --}}
@if($webinar->tools->isNotEmpty())
<div class="wd-tools-section">
    <div class="wd-tools-title">Advanced Trading Tools</div>
    <div class="wd-tools-sub">Tools and concepts covered in this webinar</div>
    <div class="wd-tools-grid">
        @foreach($webinar->tools as $tool)
        <div class="wd-tool-card">
            @if($tool->image)
                <img src="{{ $tool->image_url }}" alt="{{ $tool->title }}" loading="lazy">
            @endif
            <div class="wd-tool-card-title">{{ $tool->title }}</div>
            @if($tool->description)
                <div class="wd-tool-card-desc">{{ $tool->description }}</div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

</div>{{-- /.wd --}}

{{-- MOBILE STICKY ENROLL BAR --}}
<div class="wd-sticky-bar" id="stickyBar">
    <div class="wd-sticky-price">
        @if($isEnrolled)
            Registered ✓
        @elseif($webinar->type === 'free')
            FREE
        @else
            ₹{{ number_format($webinar->price) }}
        @endif
    </div>
    <button class="wd-sticky-btn {{ $isEnrolled ? 'enrolled' : ($webinar->type === 'free' ? 'free' : '') }}"
            onclick="handleEnroll()" type="button">
        @if($isEnrolled)
            ✓ Registered
        @elseif($webinar->type === 'free')
            Register FREE
        @else
            Register Now
        @endif
    </button>
</div>

{{-- RAZORPAY --}}
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
var isLoggedIn    = {{ $user ? 'true' : 'false' }};
var isEnrolled    = {{ $isEnrolled ? 'true' : 'false' }};
var isFree        = {{ $webinar->type === 'free' ? 'true' : 'false' }};
var isPast        = {{ $webinar->status === 'past' ? 'true' : 'false' }};
var seatsLeft     = {{ $webinar->seats_left !== null ? $webinar->seats_left : 'null' }};
var initiateUrl   = '{{ route("webinars.payment.initiate", $webinar) }}';
var verifyUrl     = '{{ route("webinars.payment.verify") }}';
var loginUrl      = '{{ route("user.login") }}';
var csrfToken     = '{{ csrf_token() }}';
var appName       = '{{ config("app.name") }}';

function handleEnroll() {
    if (isEnrolled)  { return; }
    if (isPast)      { return; }
    if (!isLoggedIn) { window.location.href = loginUrl; return; }
    if (seatsLeft !== null && seatsLeft <= 0) { alert('Sorry, this webinar is full.'); return; }

    fetch(initiateUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.redirect) { window.location.href = data.redirect; return; }
        if (!data.success) { alert(data.message || 'Something went wrong.'); return; }

        // Free webinar enrolled directly
        if (isFree) {
            alert(data.message || 'Registered successfully!');
            window.location.reload();
            return;
        }

        // Razorpay paid flow
        var rzp = new Razorpay({
            key:         data.key,
            amount:      data.amount,
            currency:    data.currency,
            order_id:    data.order_id,
            name:        appName,
            description: data.webinar_name,
            prefill:     { name: data.user_name, email: data.user_email, contact: data.user_phone },
            theme:       { color: '#F5A623' },
            handler: function(response) {
                fetch(verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        razorpay_order_id:   response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature:  response.razorpay_signature,
                        our_order_id:        data.our_order_id,
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        alert(res.message || 'Payment successful!');
                        if (res.redirect) window.location.href = res.redirect;
                        else window.location.reload();
                    } else {
                        alert(res.message || 'Payment verification failed. Please contact support.');
                    }
                })
                .catch(function() { alert('Something went wrong during verification.'); });
            },
            modal: { ondismiss: function() {} }
        });
        rzp.open();
    })
    .catch(function(err) {
        console.error(err);
        alert('Something went wrong. Please try again.');
    });
}

function toggleFaq(btn) {
    btn.classList.toggle('faq-open');
    var answer = btn.nextElementSibling;
    if (answer) answer.classList.toggle('open');
}
</script>

@endsection