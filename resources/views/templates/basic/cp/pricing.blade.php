{{-- FILE: resources/views/themes/{active_theme}/cp/pricing.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — PRICING  v2.0
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
    --c-lime-glo: rgba(125,255,0,.06);
    --c-blue:     #00B8D4;
    --c-red:      #EF5350;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.cpp-wrap {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    min-height: 80vh;
}
.cpp-wrap * { box-sizing: border-box; }
.cpp-wrap a { text-decoration: none; color: inherit; }

@keyframes cppFadeUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.cpp-anim    { animation: cppFadeUp .55s ease both; }
.cpp-anim.d1 { animation-delay: .1s; }
.cpp-anim.d2 { animation-delay: .2s; }
.cpp-anim.d3 { animation-delay: .3s; }
@keyframes cppSpin { to { transform: rotate(360deg); } }

/* ── BREADCRUMB ────────────────────────────── */
.cpp-breadcrumb {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 12px 24px;
}
.cpp-breadcrumb-inner {
    max-width: 1200px; margin: 0 auto;
    font-size: 12px; color: var(--c-muted);
    display: flex; align-items: center; gap: 7px;
    font-family: var(--f-mono);
}
.cpp-breadcrumb-inner a { color: var(--c-lime); font-weight: 600; transition: opacity .2s; }
.cpp-breadcrumb-inner a:hover { opacity: .75; }
.cpp-breadcrumb-inner i { font-size: 10px; color: var(--c-border2); }

/* ── HERO ──────────────────────────────────── */
.cpp-hero {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 80px 24px 64px;
    border-bottom: 1px solid var(--c-border);
    text-align: center;
}
.cpp-hero::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.025) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.025) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 50% 50%, black, transparent);
    pointer-events: none;
}
.cpp-hero::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 50% 60% at 50% 50%, rgba(125,255,0,.05), transparent 70%);
    pointer-events: none;
}
.cpp-hero-inner { position: relative; z-index: 1; max-width: 700px; margin: 0 auto; }
.cpp-hero-eyebrow {
    display: inline-flex; align-items: center; gap: 8px;
    font-size: 11px; font-weight: 600; letter-spacing: .14em;
    text-transform: uppercase; color: var(--c-lime); margin-bottom: 16px;
}
.cpp-hero-eyebrow::before { content: ''; display: block; width: 20px; height: 1px; background: var(--c-lime); }
.cpp-hero-eyebrow::after  { content: ''; display: block; width: 20px; height: 1px; background: var(--c-lime); }
.cpp-hero-h1 {
    font-family: var(--f-display);
    font-size: clamp(32px, 5vw, 54px);
    font-weight: 800; color: #fff;
    line-height: 1.08; letter-spacing: -.02em;
    margin-bottom: 16px;
}
.cpp-hero-h1 span { color: var(--c-lime); }
.cpp-hero-desc { font-size: 15px; color: var(--c-muted); line-height: 1.75; }

/* ── CONTENT ───────────────────────────────── */
.cpp-content { max-width: 1160px; margin: 0 auto; padding: 56px 24px 80px; }

/* ── PLANS GRID ────────────────────────────── */
.cpp-plans {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px; margin-bottom: 64px;
    align-items: start;
}
@media (max-width: 900px) { .cpp-plans { grid-template-columns: 1fr; max-width: 480px; margin-left: auto; margin-right: auto; } }

/* ── PLAN CARD ─────────────────────────────── */
.cpp-plan {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
    display: flex; flex-direction: column;
    transition: border-color .25s, transform .25s, box-shadow .25s;
    position: relative;
}
.cpp-plan::before {
    content: '';
    position: absolute; top: 0; left: 16px; right: 16px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .3s;
}
.cpp-plan:hover { border-color: rgba(125,255,0,.18); transform: translateY(-3px); box-shadow: 0 16px 48px rgba(0,0,0,.45); }
.cpp-plan:hover::before { opacity: 1; }

/* Featured plan */
.cpp-plan.featured {
    border-color: rgba(125,255,0,.35);
    box-shadow: 0 0 0 1px rgba(125,255,0,.2), 0 20px 56px rgba(0,0,0,.5);
    transform: translateY(-6px);
}
.cpp-plan.featured::before { opacity: 1; }

/* Featured badge */
.cpp-featured-badge {
    position: absolute; top: 14px; right: 14px;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 9px; font-weight: 700;
    padding: 4px 10px; border-radius: 100px; letter-spacing: .1em; text-transform: uppercase;
}

/* Plan header */
.cpp-plan-header { padding: 28px 24px 20px; position: relative; }
.cpp-plan-eyebrow {
    font-size: 10px; font-weight: 700; letter-spacing: .14em;
    text-transform: uppercase; margin-bottom: 8px;
    font-family: var(--f-mono);
}
.cpp-plan-name {
    font-family: var(--f-display); font-size: 26px; font-weight: 700;
    color: #fff; margin-bottom: 4px;
}
.cpp-plan-price {
    font-family: var(--f-display); font-size: 42px; font-weight: 800;
    color: #fff; line-height: 1; margin-bottom: 4px;
    display: flex; align-items: baseline; gap: 4px;
}
.cpp-plan-price .rupee { font-size: 22px; font-weight: 600; color: var(--c-muted); align-self: flex-start; margin-top: 6px; }
.cpp-plan-price .period { font-size: 14px; color: var(--c-muted); font-weight: 400; }
.cpp-plan-price.free-price { font-size: 34px; color: var(--c-lime); }
.cpp-plan-desc { font-size: 13px; color: var(--c-muted); line-height: 1.65; margin-top: 8px; }

/* Plan divider */
.cpp-plan-divider { height: 1px; background: var(--c-border); margin: 0 24px; }

/* Features list */
.cpp-plan-features { padding: 18px 24px; flex: 1; }
.cpp-plan-features ul { list-style: none; }
.cpp-plan-features li {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 7px 0; font-size: 13px; color: var(--c-muted); line-height: 1.5;
    border-bottom: 1px solid var(--c-border);
}
.cpp-plan-features li:last-child { border-bottom: none; }
.cpp-plan-features li i { font-size: 14px; margin-top: 1px; flex-shrink: 0; }

/* Analyses count chip */
.cpp-analyses-count {
    display: flex; align-items: center; gap: 8px;
    margin: 0 24px 4px;
    background: var(--c-panel);
    border: 1px solid var(--c-border);
    border-radius: 7px; padding: 10px 14px;
    font-size: 12px; color: var(--c-muted);
    font-family: var(--f-mono);
}
.cpp-analyses-count strong { color: var(--c-text); }

/* Plan footer / CTA */
.cpp-plan-footer { padding: 18px 24px 24px; }
.cpp-plan-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 13px;
    border-radius: 8px; border: none;
    font-family: var(--f-display); font-size: 14px; font-weight: 700;
    letter-spacing: .05em; cursor: pointer; transition: all .2s;
}

/* Button variants */
.cpp-plan-btn.lime {
    background: var(--c-lime); color: #000;
    box-shadow: 0 0 20px rgba(125,255,0,.2);
}
.cpp-plan-btn.lime:hover { background: #8FFF1A; box-shadow: 0 0 30px rgba(125,255,0,.35); transform: translateY(-1px); }

.cpp-plan-btn.dark {
    background: var(--c-panel); color: var(--c-text);
    border: 1px solid var(--c-border2);
}
.cpp-plan-btn.dark:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

.cpp-plan-btn.outline {
    background: transparent; color: var(--c-muted);
    border: 1px solid var(--c-border2);
}
.cpp-plan-btn.outline:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

.cpp-plan-btn.current {
    background: rgba(38,166,154,.1); color: #4DB6AC;
    border: 1px solid rgba(38,166,154,.25); cursor: default;
}

/* Spinner */
.cpp-spinner {
    width: 15px; height: 15px;
    border: 2px solid rgba(0,0,0,.2); border-top-color: currentColor;
    border-radius: 50%; animation: cppSpin .65s linear infinite;
    display: none; flex-shrink: 0;
}
.cpp-plan-btn.loading .cpp-spinner { display: block; }
.cpp-plan-btn.loading .cpp-btn-text { display: none; }

/* ── FAQ ───────────────────────────────────── */
.cpp-faq-section { margin-top: 8px; }
.cpp-faq-title {
    font-family: var(--f-display); font-size: 28px;
    font-weight: 700; color: #fff;
    text-align: center; margin-bottom: 28px;
}
.cpp-faq-item {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 9px; margin-bottom: 8px;
    overflow: hidden; transition: border-color .2s;
}
.cpp-faq-item.open { border-color: rgba(125,255,0,.2); }
.cpp-faq-q {
    display: flex; align-items: center; justify-content: space-between;
    padding: 15px 20px; cursor: pointer;
    font-size: 14px; font-weight: 600; color: var(--c-text);
    background: transparent; border: none; width: 100%;
    text-align: left; font-family: var(--f-sans);
    transition: color .2s;
}
.cpp-faq-q:hover { color: #fff; }
.cpp-faq-icon {
    width: 22px; height: 22px; border-radius: 5px; flex-shrink: 0;
    background: var(--c-lime-dim); border: 1px solid rgba(125,255,0,.2);
    display: flex; align-items: center; justify-content: center;
    color: var(--c-lime); font-size: 11px;
    transition: transform .3s, background .2s;
}
.cpp-faq-q.open .cpp-faq-icon { transform: rotate(45deg); background: var(--c-lime); color: #000; }
.cpp-faq-a {
    display: none; padding: 0 20px 16px;
    font-size: 13px; color: var(--c-muted); line-height: 1.75;
}
.cpp-faq-a.open { display: block; }

/* ── TOAST ─────────────────────────────────── */
.cpp-toast {
    position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%);
    z-index: 9999; padding: 12px 24px; border-radius: 9px;
    font-size: 14px; font-weight: 600; color: #fff;
    font-family: var(--f-display); letter-spacing: .03em;
    box-shadow: 0 8px 28px rgba(0,0,0,.4);
    animation: cppFadeUp .3s ease;
    white-space: nowrap;
}
.cpp-toast.success { background: rgba(38,166,154,.95); }
.cpp-toast.error   { background: rgba(239,83,80,.95); }
</style>

<div class="cpp-wrap">

{{-- BREADCRUMB --}}
<div class="cpp-breadcrumb">
    <div class="cpp-breadcrumb-inner">
        <a href="{{ route('home') }}">Home</a>
        <i class="las la-angle-right"></i>
        <span>Pricing</span>
    </div>
</div>

{{-- HERO --}}
<div class="cpp-hero">
    <div class="cpp-hero-inner cpp-anim">
        <div class="cpp-hero-eyebrow">Pricing</div>
        <h1 class="cpp-hero-h1">Simple, <span>Transparent</span> Pricing</h1>
        <p class="cpp-hero-desc">Choose the plan that fits your trading style. Upgrade or cancel anytime. No hidden fees.</p>
    </div>
</div>

<div class="cpp-content">

    {{-- PLANS --}}
    <div class="cpp-plans">
        @foreach($plans as $plan)
        @php
            $isCurrent  = $userSubscription && $userSubscription->isActive() && $userSubscription->plan->slug === $plan->slug;
            $isFeatured = $plan->slug === 'pro';
            $btnStyle   = $plan->price_monthly == 0 ? 'outline' : ($isFeatured ? 'lime' : 'dark');
        @endphp

        <div class="cpp-plan {{ $isFeatured ? 'featured' : '' }} cpp-anim">

            @if($isFeatured)
            <div class="cpp-featured-badge">Most Popular</div>
            @endif

            <div class="cpp-plan-header">
                <div class="cpp-plan-eyebrow" style="color:{{ $plan->badge_color }};">
                    {{ strtoupper($plan->name) }}
                </div>
                <div class="cpp-plan-name">{{ $plan->name }}</div>
                @if($plan->price_monthly == 0)
                    <div class="cpp-plan-price free-price">Free</div>
                @else
                    <div class="cpp-plan-price">
                        <span class="rupee">₹</span>
                        {{ number_format($plan->price_monthly) }}
                        <span class="period">/mo</span>
                    </div>
                @endif
                @if($plan->description)
                <div class="cpp-plan-desc">{{ $plan->description }}</div>
                @endif
            </div>

            <div class="cpp-plan-divider"></div>

            <div class="cpp-plan-features">
                @if(!empty($plan->features))
                <ul>
                    @foreach($plan->features as $f)
                    <li>
                        <i class="las la-check-circle" style="color:{{ $plan->badge_color }};"></i>
                        {{ $f }}
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>

            @if($plan->analyses->count())
            <div class="cpp-analyses-count">
                <i class="las la-brain" style="color:{{ $plan->badge_color }};"></i>
                <span><strong>{{ $plan->analyses->count() }}</strong> analysis tools included</span>
            </div>
            @endif

            <div class="cpp-plan-footer">
                @if($isCurrent)
                <button class="cpp-plan-btn current" disabled>
                    <i class="las la-check-circle"></i>
                    <span class="cpp-btn-text">Current Plan</span>
                </button>
                @elseif($plan->price_monthly == 0)
                <button class="cpp-plan-btn outline"
                        onclick="subscribePlan({{ $plan->id }}, '{{ $plan->name }}', 0)">
                    <span class="cpp-spinner"></span>
                    <span class="cpp-btn-text"><i class="las la-gift"></i> Get Free Plan</span>
                </button>
                @else
                <button class="cpp-plan-btn {{ $btnStyle }}"
                        onclick="subscribePlan({{ $plan->id }}, '{{ $plan->name }}', {{ $plan->price_monthly }})">
                    <span class="cpp-spinner"></span>
                    <span class="cpp-btn-text">
                        <i class="las la-crown"></i> Subscribe — ₹{{ number_format($plan->price_monthly) }}/mo
                    </span>
                </button>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- FAQ --}}
    <div class="cpp-faq-section">
        <div class="cpp-faq-title">Common Questions</div>
        @foreach([
            ['q'=>'Can I cancel anytime?',
             'a'=>'Yes. You can cancel your subscription at any time from your account page. Your access continues until the end of the billing period.'],
            ['q'=>'What payment methods are accepted?',
             'a'=>'We accept all major credit/debit cards, UPI, net banking, and wallets through Razorpay.'],
            ['q'=>'What happens when my subscription expires?',
             'a'=>'Your account automatically drops to the Free plan and you retain access to free analyses only.'],
            ['q'=>'Is the data real-time?',
             'a'=>'All analysis tools run on 15-minute interval data collected directly from Zerodha during market hours (9:15 AM – 3:30 PM, Mon–Fri).'],
            ['q'=>'Can I upgrade my plan mid-month?',
             'a'=>'Yes. Upgrading creates a new 30-day subscription from the upgrade date.'],
        ] as $i => $faq)
        <div class="cpp-faq-item" id="faqItem{{ $i }}">
            <button class="cpp-faq-q" onclick="toggleFaq({{ $i }})">
                {{ $faq['q'] }}
                <span class="cpp-faq-icon"><i class="las la-plus"></i></span>
            </button>
            <div class="cpp-faq-a" id="faq-a-{{ $i }}">{{ $faq['a'] }}</div>
        </div>
        @endforeach
    </div>

</div>
</div>

{{-- ── JS: ALL PAYMENT LOGIC IDENTICAL ── --}}
<script>
var CSRF      = '{{ csrf_token() }}';
var PAY_URL   = '{{ route("cp.subscribe.pay", ":plan") }}';
var VRF_URL   = '{{ route("cp.subscribe.verify") }}';
var LOGIN_URL = '{{ route("user.login") }}';
var IS_AUTH   = {{ auth()->check() ? 'true' : 'false' }};

function toast(msg, type) {
    var el = document.createElement('div');
    el.className = 'cpp-toast ' + (type || 'success');
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(function() { el.remove(); }, 3500);
}

function subscribePlan(planId, planName, price) {
    if (!IS_AUTH) {
        window.location.href = LOGIN_URL + '?redirect=' + encodeURIComponent(window.location.href);
        return;
    }
    var btn = event.currentTarget;
    btn.classList.add('loading'); btn.disabled = true;

    fetch(PAY_URL.replace(':plan', planId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) {
            toast(data.message || 'Error', 'error');
            btn.classList.remove('loading'); btn.disabled = false;
            return;
        }
        if (data.free) {
            toast(data.message, 'success');
            setTimeout(function() { window.location.href = data.redirect; }, 1200);
            return;
        }
        var options = {
            key:         data.key,
            amount:      data.amount,
            currency:    'INR',
            name:        'CityQuants',
            description: planName + ' Subscription',
            order_id:    data.order_id,
            prefill: { name: data.user_name, email: data.user_email, contact: data.user_mobile },
            theme: { color: '#7DFF00' },
            modal: {
                ondismiss: function() {
                    btn.classList.remove('loading'); btn.disabled = false;
                    toast('Payment cancelled.', 'error');
                }
            },
            handler: function(response) {
                toast('Verifying payment...', 'success');
                fetch(VRF_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({
                        razorpay_order_id:   response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature:  response.razorpay_signature,
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        toast('Subscription activated! Redirecting...', 'success');
                        setTimeout(function() { window.location.href = d.redirect; }, 1500);
                    } else {
                        toast(d.message || 'Verification failed.', 'error');
                        btn.classList.remove('loading'); btn.disabled = false;
                    }
                })
                .catch(function() {
                    toast('Network error. Please contact support.', 'error');
                    btn.classList.remove('loading'); btn.disabled = false;
                });
            }
        };
        var rzp = new Razorpay(options);
        rzp.open();
        btn.classList.remove('loading'); btn.disabled = false;
    })
    .catch(function() {
        toast('Something went wrong.', 'error');
        btn.classList.remove('loading'); btn.disabled = false;
    });
}

function toggleFaq(i) {
    var item  = document.getElementById('faqItem' + i);
    var q     = item.querySelector('.cpp-faq-q');
    var a     = document.getElementById('faq-a-' + i);
    var wasOpen = a.classList.contains('open');
    document.querySelectorAll('.cpp-faq-item').forEach(function(el) { el.classList.remove('open'); });
    document.querySelectorAll('.cpp-faq-q').forEach(function(el) { el.classList.remove('open'); });
    document.querySelectorAll('.cpp-faq-a').forEach(function(el) { el.classList.remove('open'); });
    if (!wasOpen) { item.classList.add('open'); q.classList.add('open'); a.classList.add('open'); }
}
</script>
@endsection