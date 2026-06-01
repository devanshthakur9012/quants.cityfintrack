{{-- FILE: resources/views/themes/{active_theme}/event-detail.blade.php --}}
@extends($activeTemplate.'layouts.frontend')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
.ed { font-family:'Exo 2',sans-serif; background:#fff; color:#1a1a2e; }
.ed * { box-sizing:border-box; }
.ed h1,.ed h2,.ed h3 { font-family:'Rajdhani',sans-serif; letter-spacing:.02em; }
.ed a { text-decoration:none; }
/* Crumb */
.ed-crumb { background:#f8f9fc; border-bottom:1px solid #eee; padding:12px 48px; font-size:13px; color:#888; }
.ed-crumb a { color:#7DFF00; }
.ed-crumb span { margin:0 6px; }
@media(max-width:768px){ .ed-crumb { padding:12px 16px; } }
/* Top */
.ed-top { padding:48px; display:grid; grid-template-columns:1fr 360px; gap:40px; align-items:start; }
@media(max-width:940px){ .ed-top { grid-template-columns:1fr; padding:24px 16px; } }
.ed-thumb { border-radius:14px; overflow:hidden; margin-bottom:24px; }
.ed-thumb img { width:100%; display:block; }
.ed-badges { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
.ed-badge { font-size:11px; font-weight:700; letter-spacing:.05em; padding:4px 12px; border-radius:20px; text-transform:uppercase; display:inline-flex; align-items:center; gap:6px; }
.ed-badge.upcoming   { background:#43a047; color:#fff; }
.ed-badge.ongoing    { background:#1565c0; color:#fff; }
.ed-badge.past       { background:#607d8b; color:#fff; }
.ed-badge.free       { background:#e8f5e9; color:#2e7d32; }
.ed-badge.paid       { background:#fff3e0; color:#e65100; }
.ed-badge.symposium  { background:#c62828; color:#fff; }
.ed-badge.workshop   { background:#e65100; color:#fff; }
.ed-badge.seminar    { background:#00695c; color:#fff; }
.ed-badge.bootcamp   { background:#37474f; color:#fff; }
.ed-badge.conference { background:#4527a0; color:#fff; }
.ed-title { font-size:clamp(24px,3.5vw,36px); font-weight:700; color:#1a1a2e; margin:0 0 16px; line-height:1.1; }
.ed-meta-row { display:flex; flex-wrap:wrap; gap:18px; margin-bottom:18px; }
.ed-meta-item { display:flex; align-items:center; gap:8px; font-size:14px; color:#555; }
.ed-meta-item i { color:#7DFF00; width:16px; text-align:center; }
/* Seats */
.ed-seats-bar { background:#f7f8fc; border:1px solid #e8e8e8; border-radius:9px; padding:12px 16px; margin-bottom:20px; }
.ed-seats-label { font-size:13px; color:#555; margin-bottom:6px; }
.ed-seats-progress { height:6px; background:#e0e0e0; border-radius:3px; }
.ed-seats-fill { height:100%; border-radius:3px; background:#7DFF00; }
/* Countdown */
.ed-countdown-bar { background:#0D1B2A; border-radius:10px; padding:14px 18px; margin-bottom:20px; display:flex; align-items:center; gap:12px; }
.ed-countdown-label { font-size:12px; color:rgba(255,255,255,.5); font-weight:600; flex-shrink:0; }
.ed-countdown-boxes { display:flex; gap:8px; }
.ed-countdown-unit { display:flex; flex-direction:column; align-items:center; background:rgba(245,166,35,.12); border:1px solid rgba(245,166,35,.25); border-radius:6px; padding:6px 10px; min-width:44px; }
.ed-countdown-num { font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; color:#7DFF00; line-height:1; }
.ed-countdown-sub { font-size:9px; color:rgba(255,255,255,.4); letter-spacing:.05em; }
/* Reg card */
.ed-reg-card { background:#fff; border-radius:14px; border:1px solid #e8e8e8; box-shadow:0 8px 30px rgba(0,0,0,.08); overflow:hidden; position:sticky; top:80px; }
.ed-reg-price-wrap { padding:22px 22px 0; }
.ed-reg-price { font-family:'Rajdhani',sans-serif; font-size:32px; font-weight:700; color:#1a1a2e; line-height:1; }
.ed-reg-price .orig { text-decoration:line-through; color:#aaa; font-size:18px; margin-right:6px; font-weight:400; }
.ed-reg-price .disc { font-size:13px; color:#43a047; font-weight:700; margin-left:6px; }
.ed-reg-date-label { font-size:13px; color:#888; padding:6px 22px 14px; }
.ed-reg-body { padding:0 22px 22px; }
.ed-reg-btn { display:block; width:100%; text-align:center; font-weight:700; font-size:17px; padding:15px; border-radius:10px; font-family:'Rajdhani',sans-serif; letter-spacing:.04em; border:none; cursor:pointer; transition:background .2s; }
.ed-reg-btn.paid-btn     { background:#7DFF00; color:#000; }
.ed-reg-btn.paid-btn:hover { background:#d4890e; }
.ed-reg-btn.free-btn     { background:#43a047; color:#fff; }
.ed-reg-btn.free-btn:hover { background:#2e7d32; }
.ed-reg-btn.booked-btn   { background:#e8f5e9; color:#2e7d32; cursor:default; }
.ed-reg-btn.closed-btn   { background:#f5f5f5; color:#aaa; cursor:not-allowed; }
.ed-reg-meta { margin-top:16px; display:flex; flex-direction:column; gap:9px; }
.ed-reg-meta-row { display:flex; align-items:center; gap:10px; font-size:13px; color:#555; }
.ed-reg-meta-row i { color:#7DFF00; width:16px; text-align:center; }
/* Body */
.ed-body { padding:0 48px 60px; }
@media(max-width:940px){ .ed-body { padding:0 16px 48px; } }
.ed-section { border-bottom:1px solid #f0f0f0; padding:32px 0; }
.ed-section:last-child { border-bottom:none; }
.ed-section-title { font-size:26px; font-weight:700; color:#1a1a2e; margin:0 0 22px; }
/* Video */
.ed-video-wrap { position:relative; padding-bottom:56.25%; height:0; overflow:hidden; border-radius:12px; }
.ed-video-wrap iframe,.ed-video-wrap video { position:absolute; top:0; left:0; width:100%; height:100%; border:none; border-radius:12px; }
/* Gallery */
.ed-gallery-section { background:#f7f8fc; padding:40px 48px; }
@media(max-width:940px){ .ed-gallery-section { padding:28px 16px; } }
.ed-gallery-title { font-family:'Rajdhani',sans-serif; font-size:28px; font-weight:700; color:#1a1a2e; text-align:center; margin:0 0 30px; }
.ed-gallery-grid  { display:grid; grid-template-columns:repeat(2,1fr); gap:20px; max-width:900px; margin:0 auto; }
@media(max-width:700px){ .ed-gallery-grid { grid-template-columns:1fr; } }
.ed-gallery-card  { background:#fff; border:1px solid #e8e8e8; border-radius:10px; overflow:hidden; text-align:center; padding-bottom:12px; }
.ed-gallery-card img { width:100%; display:block; border-bottom:1px solid #f0f0f0; aspect-ratio:16/9; object-fit:cover; }
.ed-gallery-card-title { font-size:14px; font-weight:600; color:#1a1a2e; margin:10px 10px 0; }
/* Speakers */
.ed-speaker-cards { display:flex; flex-wrap:wrap; gap:20px; }
.ed-speaker-card  { background:#f7f8fc; border:1px solid #e8e8e8; border-radius:12px; padding:20px; display:flex; gap:16px; align-items:flex-start; flex:1 1 calc(50% - 10px); min-width:260px; }
@media(max-width:600px){ .ed-speaker-card { flex:1 1 100%; } }
.ed-speaker-avatar { width:72px; height:72px; border-radius:50%; flex-shrink:0; background:#1a56db; color:#fff; font-size:24px; font-weight:700; display:flex; align-items:center; justify-content:center; overflow:hidden; }
.ed-speaker-avatar img { width:100%; height:100%; object-fit:cover; }
.ed-speaker-name { font-family:'Rajdhani',sans-serif; font-size:18px; font-weight:700; color:#1a1a2e; margin-bottom:2px; }
.ed-speaker-role { font-size:12px; color:#7DFF00; font-weight:600; margin-bottom:8px; text-transform:uppercase; }
.ed-speaker-bio  { font-size:13px; color:#666; line-height:1.6; }
/* FAQ */
.ed-faq-item { border:1px solid #e8e8e8; border-radius:9px; margin-bottom:10px; overflow:hidden; }
.ed-faq-q { width:100%; text-align:left; background:#fff; border:none; padding:16px 20px; font-size:14px; font-weight:600; color:#1a1a2e; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-family:'Exo 2',sans-serif; transition:background .2s; }
.ed-faq-q:hover { background:#f8f9fc; }
.ed-faq-a { display:none; padding:0 20px 16px; font-size:13px; color:#555; line-height:1.7; }
.ed-faq-a.open { display:block; }
.ed-faq-icon { transition:transform .25s; flex-shrink:0; margin-left:10px; }
.ed-faq-q.faq-open .ed-faq-icon { transform:rotate(45deg); }
/* Related */
.ed-related-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
@media(max-width:700px){ .ed-related-grid { grid-template-columns:1fr; } }
.ed-rel-card { background:#fff; border-radius:9px; overflow:hidden; border:1px solid #e8e8e8; transition:box-shadow .2s; display:block; }
.ed-rel-card:hover { box-shadow:0 6px 20px rgba(0,0,0,.08); }
.ed-rel-card img { width:100%; aspect-ratio:16/9; object-fit:cover; display:block; }
.ed-rel-card-body { padding:10px 12px; }
.ed-rel-card-title { font-size:13px; font-weight:600; color:#1a1a2e; margin-bottom:4px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.ed-rel-card-price { padding:0 12px 10px; font-size:13px; font-weight:700; color:#7DFF00; }
/* Popup */
.ed-popup-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9999; align-items:center; justify-content:center; padding:20px; }
.ed-popup-overlay.open { display:flex; }
.ed-popup { background:#fff; border-radius:16px; width:100%; max-width:480px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.25); animation:popIn .3s ease; }
@keyframes popIn { from{opacity:0;transform:scale(.95) translateY(20px)} to{opacity:1;transform:none} }
.ed-popup-header { background:linear-gradient(135deg,#0D1B2A,#1a3560); padding:22px 24px; display:flex; align-items:center; justify-content:space-between; }
.ed-popup-header h3 { font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; color:#fff; margin:0; }
.ed-popup-close { background:none; border:none; color:rgba(255,255,255,.6); font-size:22px; cursor:pointer; }
.ed-popup-close:hover { color:#fff; }
.ed-popup-body { padding:24px; }
.ed-popup-price { background:#f7f8fc; border-radius:9px; padding:12px 16px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; }
.ed-popup-price-val { font-family:'Rajdhani',sans-serif; font-size:24px; font-weight:700; color:#1a1a2e; }
.ed-popup-price-val .orig { text-decoration:line-through; color:#aaa; font-size:14px; margin-right:4px; font-weight:400; }
.ed-popup-event-name { font-size:13px; color:#888; max-width:200px; text-align:right; }
.ed-form-group { margin-bottom:14px; }
.ed-form-group label { font-size:13px; font-weight:600; color:#333; display:block; margin-bottom:5px; }
.ed-form-group input,.ed-form-group textarea { width:100%; border:1px solid #ddd; border-radius:8px; padding:10px 13px; font-size:14px; font-family:'Exo 2',sans-serif; color:#333; outline:none; transition:border .2s; }
.ed-form-group input:focus,.ed-form-group textarea:focus { border-color:#7DFF00; }
.ed-form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.ed-submit-btn { display:block; width:100%; background:#7DFF00; color:#000; font-weight:700; font-size:16px; padding:14px; border-radius:10px; border:none; cursor:pointer; font-family:'Rajdhani',sans-serif; letter-spacing:.04em; transition:background .2s; }
.ed-submit-btn:hover { background:#d4890e; }
.ed-submit-btn.free { background:#43a047; color:#fff; }
.ed-submit-btn:disabled { background:#ccc; cursor:not-allowed; }
.ed-popup-msg { text-align:center; padding:14px 0 0; font-size:14px; min-height:20px; }
.ed-popup-msg.success { color:#43a047; font-weight:600; }
.ed-popup-msg.error   { color:#e53935; }
/* Mobile sticky */
.ed-sticky-bar { display:none; position:fixed; bottom:0; left:0; right:0; z-index:500; background:#1a1a2e; padding:12px 16px; align-items:center; justify-content:space-between; gap:12px; box-shadow:0 -4px 20px rgba(0,0,0,.2); }
@media(max-width:940px){ .ed-sticky-bar { display:flex; } }
.ed-sticky-price { color:#7DFF00; font-family:'Rajdhani',sans-serif; font-size:22px; font-weight:700; }
.ed-sticky-btn { background:#7DFF00; color:#000; font-weight:700; padding:11px 24px; border-radius:9px; border:none; cursor:pointer; font-size:15px; font-family:'Rajdhani',sans-serif; }
</style>

<div class="ed">

{{-- BREADCRUMB --}}
<div class="ed-crumb">
    <a href="{{ route('home') }}">Home</a><span>/</span>
    <a href="{{ route('events.index') }}">Events</a><span>/</span>
    {{ Str::limit($event->title, 55) }}
</div>

{{-- TOP --}}
<div class="ed-top">
    {{-- LEFT --}}
    <div>
        <div class="ed-thumb"><img src="{{ $event->thumbnail_url }}" alt="{{ $event->title }}"></div>

        <div class="ed-badges">
            <span class="ed-badge {{ $event->status }}">{{ ucfirst($event->status) }}</span>
            <span class="ed-badge {{ $event->badge }}">{{ ucfirst($event->badge) }}</span>
            <span class="ed-badge {{ $event->type }}">{{ strtoupper($event->type) }}</span>
        </div>

        <h1 class="ed-title">{{ $event->title }}</h1>

        <div class="ed-meta-row">
            @if($event->formatted_date !== '—')
            <div class="ed-meta-item"><i class="fas fa-calendar-alt"></i><strong>{{ $event->formatted_date }}</strong></div>
            @endif
            @if($event->formatted_time !== '—')
            <div class="ed-meta-item"><i class="fas fa-clock"></i><span>{{ $event->formatted_time }}</span></div>
            @endif
            @if($event->formatted_duration !== '—')
            <div class="ed-meta-item"><i class="fas fa-hourglass-half"></i><span>{{ $event->formatted_duration }}</span></div>
            @endif
            @if($event->location)
            <div class="ed-meta-item"><i class="fas fa-map-marker-alt"></i><span>{{ $event->location }}</span></div>
            @endif
        </div>

        {{-- Countdown --}}
        @if($event->countdown_seconds > 0 && in_array($event->status, ['upcoming','ongoing']))
        <div class="ed-countdown-bar" data-ts="{{ time() + $event->countdown_seconds }}">
            <span class="ed-countdown-label">Starts in</span>
            <div class="ed-countdown-boxes">
                <div class="ed-countdown-unit"><div class="ed-countdown-num cd-days">--</div><div class="ed-countdown-sub">Days</div></div>
                <div class="ed-countdown-unit"><div class="ed-countdown-num cd-hrs">--</div><div class="ed-countdown-sub">Hrs</div></div>
                <div class="ed-countdown-unit"><div class="ed-countdown-num cd-mins">--</div><div class="ed-countdown-sub">Mins</div></div>
            </div>
        </div>
        @endif

        {{-- Seats bar --}}
        @if($event->total_seats)
        @php $pct = min(100, ($event->total_booked / $event->total_seats) * 100); @endphp
        <div class="ed-seats-bar">
            <div class="ed-seats-label">
                <strong>{{ $event->total_booked }}</strong> registered &nbsp;·&nbsp;
                <span style="{{ $event->seats_low ? 'color:#e53935;font-weight:700;' : '' }}">
                    {{ $event->seats_left }} seats left
                </span>
            </div>
            <div class="ed-seats-progress"><div class="ed-seats-fill" style="width:{{ $pct }}%"></div></div>
        </div>
        @else
        <div class="ed-seats-bar">
            <div class="ed-seats-label"><i class="fas fa-users" style="color:#7DFF00;"></i> <strong>{{ $event->total_booked }}</strong> people registered</div>
        </div>
        @endif
    </div>

    {{-- RIGHT: Reg Card (desktop) --}}
    <div class="d-none d-lg-block">
        @include($activeTemplate.'partials.event-reg-card')
    </div>
</div>

{{-- Mobile reg card --}}
<div class="d-lg-none" style="padding:0 16px 24px;">
    @include($activeTemplate.'partials.event-reg-card')
</div>

{{-- BODY --}}
<div class="ed-body">

    {{-- Video --}}
    @if($event->video_embed_url)
    <div class="ed-section">
        <h2 class="ed-section-title">Event Video</h2>
        <div class="ed-video-wrap">
            @if($event->video_type === 'youtube')
                <iframe src="{{ $event->video_embed_url }}" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
            @else
                <video controls><source src="{{ $event->video_embed_url }}"></video>
            @endif
        </div>
    </div>
    @endif

    {{-- Description --}}
    @if($event->description)
    <div class="ed-section">
        <h2 class="ed-section-title">About This Event</h2>
        <div style="font-size:15px;color:#555;line-height:1.8;">{{ $event->description }}</div>
    </div>
    @endif

    {{-- Speakers --}}
    @if($event->speakers->isNotEmpty())
    <div class="ed-section">
        <h2 class="ed-section-title">Speaker{{ $event->speakers->count() > 1 ? 's' : '' }}</h2>
        <div class="ed-speaker-cards">
            @foreach($event->speakers as $sp)
            @php
                $spName = trim($sp->firstname.' '.$sp->lastname);
                $spRole = optional($sp->employeeProfile)->designation ?? 'Speaker';
                $spBio  = optional($sp->employeeProfile)->bio ?? '';
                $spImg  = $sp->profile_pic ? asset(getFilePath('userProfile').'/'.$sp->profile_pic) : null;
            @endphp
            <div class="ed-speaker-card">
                <div class="ed-speaker-avatar">
                    @if($spImg)<img src="{{ $spImg }}" alt="{{ $spName }}">@else{{ strtoupper(substr($sp->firstname,0,1)) }}@endif
                </div>
                <div>
                    <div class="ed-speaker-name">{{ $spName }}</div>
                    <div class="ed-speaker-role">{{ $spRole }}</div>
                    @if($spBio)<div class="ed-speaker-bio">{{ $spBio }}</div>@endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- FAQs --}}
    @if($event->faqs->isNotEmpty())
    <div class="ed-section">
        <h2 class="ed-section-title">FAQ's</h2>
        @foreach($event->faqs as $faq)
        <div class="ed-faq-item">
            <button class="ed-faq-q" type="button" onclick="toggleFaq(this)">
                {{ $faq->question }}
                <i class="fas fa-plus ed-faq-icon"></i>
            </button>
            <div class="ed-faq-a">{{ $faq->answer }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Related --}}
    @if($relatedEvents->isNotEmpty())
    <div class="ed-section">
        <h2 class="ed-section-title">Related Events</h2>
        <div class="ed-related-grid">
            @foreach($relatedEvents as $r)
            <a href="{{ route('events.detail', $r->slug) }}" class="ed-rel-card">
                <img src="{{ $r->thumbnail_url }}" alt="{{ $r->title }}" loading="lazy">
                <div class="ed-rel-card-body">
                    <div class="ed-rel-card-title">{{ $r->title }}</div>
                    <div style="font-size:11px;color:#999;">{{ $r->formatted_date }} · {{ ucfirst($r->badge) }}</div>
                </div>
                <div class="ed-rel-card-price">{{ $r->type === 'free' ? 'FREE' : '₹'.number_format($r->price) }}</div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

</div>{{-- /.ed-body --}}

</div>{{-- /.ed --}}

{{-- GALLERY SECTION (full width, dynamic title) --}}
@if($event->galleryItems->isNotEmpty())
<div class="ed-gallery-section">
    {{-- Dynamic title set by admin --}}
    <div class="ed-gallery-title">{{ $event->gallery_section_title ?? 'Event Gallery' }}</div>
    <div class="ed-gallery-grid">
        @foreach($event->galleryItems as $item)
        <div class="ed-gallery-card">
            <img src="{{ $item->image_url }}" alt="{{ $item->title }}" loading="lazy">
            @if($item->title)
            <div class="ed-gallery-card-title">{{ $item->title }}</div>
            @endif
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- MOBILE STICKY --}}
<div class="ed-sticky-bar">
    <div class="ed-sticky-price">
        @if($isBooked) Registered ✓
        @elseif($event->type === 'free') FREE
        @else ₹{{ number_format($event->price) }}
        @endif
    </div>
    @if($event->canBook() && !$isBooked)
    <button class="ed-sticky-btn" onclick="openBookingPopup()" type="button">
        {{ $event->type === 'free' ? 'Register FREE' : 'Book Seat' }}
    </button>
    @endif
</div>

{{-- BOOKING POPUP --}}
<div class="ed-popup-overlay" id="bookingOverlay" onclick="closeOnOverlay(event)">
    <div class="ed-popup">
        <div class="ed-popup-header">
            <h3>{{ $event->type === 'free' ? 'Register for Event' : 'Book Your Seat' }}</h3>
            <button class="ed-popup-close" onclick="closeBookingPopup()" type="button">✕</button>
        </div>
        <div class="ed-popup-body">
            <div class="ed-popup-price">
                <div class="ed-popup-price-val">
                    @if($event->type === 'free') FREE
                        @if($event->mrp > 0)<span class="orig">₹{{ number_format($event->mrp) }}</span>@endif
                    @else
                        ₹{{ number_format($event->price) }}
                        @if($event->mrp > $event->price)<span class="orig">₹{{ number_format($event->mrp) }}</span>@endif
                    @endif
                </div>
                <div class="ed-popup-event-name">{{ Str::limit($event->title, 40) }}</div>
            </div>

            <form id="bookingForm" onsubmit="submitBooking(event)">
                <div class="ed-form-row">
                    <div class="ed-form-group">
                        <label>Full Name *</label>
                        <input type="text" name="name" required placeholder="Your name"
                               value="{{ $user ? trim($user->firstname.' '.$user->lastname) : '' }}">
                    </div>
                    <div class="ed-form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required placeholder="your@email.com"
                               value="{{ $userEmail ?? '' }}" {{ $userEmail ? 'readonly' : '' }}>
                    </div>
                </div>
                <div class="ed-form-row">
                    <div class="ed-form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" placeholder="+91 98765 43210"
                               value="{{ $user?->mobile ?? '' }}">
                    </div>
                    <div class="ed-form-group">
                        <label>Your City</label>
                        <input type="text" name="city" placeholder="e.g. Bangalore">
                    </div>
                </div>
                <div class="ed-form-group">
                    <label>Message <small style="color:#aaa;">(optional)</small></label>
                    <textarea name="message" rows="2" placeholder="Any questions or requirements..."></textarea>
                </div>

                <div class="ed-popup-msg" id="bookingMsg"></div>

                <button type="submit" class="ed-submit-btn {{ $event->type === 'free' ? 'free' : '' }}" id="bookingSubmitBtn">
                    {{ $event->type === 'free' ? '✓ Confirm Registration' : 'Proceed to Payment — ₹'.number_format($event->price) }}
                </button>
            </form>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
var isFree     = {{ $event->type === 'free' ? 'true' : 'false' }};
var bookUrl    = '{{ route("events.book", $event) }}';
var verifyUrl  = '{{ route("events.payment.verify") }}';
var csrfToken  = '{{ csrf_token() }}';
var appName    = '{{ config("app.name") }}';
var isBooked   = {{ $isBooked ? 'true' : 'false' }};
var canBook    = {{ $event->canBook() ? 'true' : 'false' }};

function openBookingPopup() {
    if (isBooked) { alert('You have already registered.'); return; }
    if (!canBook) { alert('Bookings are currently closed.'); return; }
    document.getElementById('bookingOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeBookingPopup() {
    document.getElementById('bookingOverlay').classList.remove('open');
    document.body.style.overflow = '';
}
function closeOnOverlay(e) {
    if (e.target === document.getElementById('bookingOverlay')) closeBookingPopup();
}

function submitBooking(e) {
    e.preventDefault();
    var btn   = document.getElementById('bookingSubmitBtn');
    var msgEl = document.getElementById('bookingMsg');
    var data  = new FormData(document.getElementById('bookingForm'));

    btn.disabled = true;
    btn.textContent = 'Processing...';
    msgEl.textContent = '';
    msgEl.className = 'ed-popup-msg';

    fetch(bookUrl, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: data
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res.success) {
            msgEl.textContent = res.message || 'Something went wrong.';
            msgEl.className = 'ed-popup-msg error';
            btn.disabled = false;
            btn.textContent = isFree ? '✓ Confirm Registration' : 'Proceed to Payment';
            return;
        }
        if (isFree) {
            msgEl.textContent = res.message || 'Registration confirmed!';
            msgEl.className = 'ed-popup-msg success';
            btn.textContent = '✓ Registered!';
            isBooked = true;
            setTimeout(function() { closeBookingPopup(); window.location.reload(); }, 1800);
            return;
        }
        // Razorpay
        var rzp = new Razorpay({
            key: res.key, amount: res.amount, currency: res.currency,
            order_id: res.order_id, name: appName, description: res.event_name,
            prefill: { name: res.user_name, email: res.user_email, contact: res.user_phone },
            theme: { color: '#7DFF00' },
            handler: function(response) {
                fetch(verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        razorpay_order_id:   response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature:  response.razorpay_signature,
                        booking_id:          res.booking_id,
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(vRes) {
                    if (vRes.success) {
                        msgEl.textContent = vRes.message || 'Payment successful!';
                        msgEl.className = 'ed-popup-msg success';
                        btn.textContent = '✓ Registered!';
                        isBooked = true;
                        setTimeout(function() { closeBookingPopup(); window.location.reload(); }, 1800);
                    } else {
                        msgEl.textContent = vRes.message || 'Payment failed.';
                        msgEl.className = 'ed-popup-msg error';
                        btn.disabled = false;
                        btn.textContent = 'Try Again';
                    }
                });
            },
            modal: { ondismiss: function() { btn.disabled = false; btn.textContent = 'Proceed to Payment'; } }
        });
        rzp.open();
    })
    .catch(function() {
        msgEl.textContent = 'Something went wrong. Please try again.';
        msgEl.className = 'ed-popup-msg error';
        btn.disabled = false;
    });
}

function toggleFaq(btn) {
    btn.classList.toggle('faq-open');
    var a = btn.nextElementSibling; if (a) a.classList.toggle('open');
}

// Countdown
function runCountdown() {
    document.querySelectorAll('[data-ts]').forEach(function(el) {
        var diff = parseInt(el.dataset.ts) - Math.floor(Date.now()/1000);
        if (diff <= 0) { var lbl = el.querySelector('.ed-countdown-label,.qev-countdown-label'); if(lbl) lbl.textContent='Started!'; return; }
        var d = Math.floor(diff/86400);
        var h = Math.floor((diff%86400)/3600);
        var m = Math.floor((diff%3600)/60);
        var dn = el.querySelector('.cd-days'), hn = el.querySelector('.cd-hrs'), mn = el.querySelector('.cd-mins');
        if (dn) dn.textContent = String(d).padStart(2,'0');
        if (hn) hn.textContent = String(h).padStart(2,'0');
        if (mn) mn.textContent = String(m).padStart(2,'0');
    });
}
runCountdown();
setInterval(runCountdown, 30000);
</script>

@endsection
