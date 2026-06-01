{{-- FILE: resources/views/themes/{active_theme}/cp/pricing.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<style>
.cpp-wrap{ font-family:'Exo 2',sans-serif;background:#f7f8fc;min-height:80vh; }
.cpp-wrap *{ box-sizing:border-box; }
.cpp-wrap h1,.cpp-wrap h2,.cpp-wrap h3{ font-family:'Rajdhani',sans-serif; }
.cpp-wrap a{ text-decoration:none; }
@keyframes cppUp{ from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.cpp-anim{ animation:cppUp .5s ease both; }
@keyframes cppSpin{ to{ transform:rotate(360deg); } }

/* Hero */
.cpp-hero{
    background:linear-gradient(135deg,#06101A 0%,#0F2848 60%,#091828 100%);
    padding:56px 48px 48px;text-align:center;position:relative;overflow:hidden;
}
.cpp-hero::before{
    content:'';position:absolute;inset:0;
    background-image:linear-gradient(rgba(245,166,35,.04) 1px,transparent 1px),
        linear-gradient(90deg,rgba(245,166,35,.04) 1px,transparent 1px);
    background-size:48px 48px; pointer-events:none;
}
.cpp-hero h1{ font-size:clamp(28px,4vw,48px);color:#fff;margin:0 0 12px;
    position:relative;z-index:1; }
.cpp-hero h1 span{ color:#7DFF00; }
.cpp-hero p{ color:rgba(255,255,255,.6);font-size:15px;margin:0;
    position:relative;z-index:1;max-width:560px;margin:0 auto;line-height:1.7; }

/* Content */
.cpp-content{ max-width:1140px;margin:0 auto;padding:48px 24px 72px; }

/* Plans grid */
.cpp-plans{ display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-bottom:56px; }
@media(max-width:860px){ .cpp-plans{ grid-template-columns:1fr; } }

/* Plan card */
.cpp-plan{
    background:#fff;border-radius:20px;border:1px solid #e5e9f2;
    overflow:hidden;transition:transform .25s,box-shadow .25s;
    display:flex;flex-direction:column;
}
.cpp-plan:hover{ transform:translateY(-6px);box-shadow:0 20px 48px rgba(0,0,0,.1); }
.cpp-plan.featured{
    border-color:transparent;
    box-shadow:0 0 0 2px #7DFF00, 0 20px 48px rgba(245,166,35,.15);
    transform:translateY(-8px);
}

.cpp-plan-header{ padding:28px 28px 20px;position:relative; }
.cpp-plan-eyebrow{
    font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
    margin-bottom:8px;
}
.cpp-plan-name{
    font-family:'Rajdhani',sans-serif;font-size:28px;font-weight:700;
    color:#1a1a2e;margin:0 0 4px;
}
.cpp-plan-price{
    font-family:'Rajdhani',sans-serif;font-size:44px;font-weight:700;
    color:#1a1a2e;line-height:1;margin-bottom:4px;
}
.cpp-plan-price span{ font-size:18px;color:#888;font-weight:400; }
.cpp-plan-desc{ font-size:13px;color:#888;line-height:1.6;margin-top:8px; }
.cpp-featured-badge{
    position:absolute;top:16px;right:16px;
    background:#7DFF00;color:#000;font-size:10px;font-weight:700;
    padding:4px 10px;border-radius:20px;letter-spacing:.05em;text-transform:uppercase;
}

.cpp-plan-divider{ height:1px;background:#f0f0f0;margin:0 28px; }

.cpp-plan-features{ padding:20px 28px;flex:1; }
.cpp-plan-features ul{ list-style:none;padding:0;margin:0; }
.cpp-plan-features li{
    display:flex;align-items:flex-start;gap:10px;
    padding:7px 0;font-size:13.5px;color:#444;line-height:1.5;
    border-bottom:1px solid #f8f8f8;
}
.cpp-plan-features li:last-child{ border-bottom:none; }
.cpp-plan-features li i{ font-size:14px;margin-top:2px;flex-shrink:0; }

/* Analyses count */
.cpp-analyses-count{
    display:flex;align-items:center;gap:8px;margin:16px 28px 0;
    font-size:13px;color:#555;
    background:#f4f6fb;border-radius:8px;padding:10px 14px;
}
.cpp-analyses-count strong{ color:#1a1a2e; }

/* CTA */
.cpp-plan-footer{ padding:20px 28px 28px; }
.cpp-plan-btn{
    display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
    padding:14px;border-radius:10px;border:none;
    font-family:'Rajdhani',sans-serif;font-size:16px;font-weight:700;
    letter-spacing:.04em;cursor:pointer;transition:all .2s;
}
.cpp-plan-btn.gold{ background:#7DFF00;color:#000; }
.cpp-plan-btn.gold:hover{ background:#d4890e; }
.cpp-plan-btn.dark{
    background:linear-gradient(90deg,#0F2848,#1a3050);color:#fff;
}
.cpp-plan-btn.dark:hover{ opacity:.9; }
.cpp-plan-btn.outline{
    background:transparent;border:1.5px solid #e5e9f2;color:#666;
}
.cpp-plan-btn.outline:hover{ border-color:#7DFF00;color:#7DFF00; }
.cpp-plan-btn.current{
    background:#e8f5e9;color:#2e7d32;border:1.5px solid #c8e6c9;
    cursor:default;
}
.cpp-plan-btn .cpp-spinner{
    width:16px;height:16px;border:2px solid rgba(0,0,0,.2);
    border-top-color:currentColor;border-radius:50%;
    animation:cppSpin .7s linear infinite;display:none;
}
.cpp-plan-btn.loading .cpp-spinner{ display:block; }
.cpp-plan-btn.loading .cpp-btn-text{ display:none; }

/* FAQ section */
.cpp-faq-head{ font-size:28px;font-weight:700;color:#1a1a2e;text-align:center;margin-bottom:28px; }
.cpp-faq-item{ background:#fff;border:1px solid #e8e8e8;border-radius:10px;margin-bottom:8px; }
.cpp-faq-q{
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;cursor:pointer;font-weight:600;color:#1a1a2e;font-size:14px;
}
.cpp-faq-q i{ color:#7DFF00;transition:transform .3s; }
.cpp-faq-q.open i{ transform:rotate(45deg); }
.cpp-faq-a{ display:none;padding:0 20px 16px;font-size:13px;color:#666;line-height:1.7; }
.cpp-faq-a.open{ display:block; }

/* Toast */
.cpp-toast{
    position:fixed;bottom:28px;left:50%;transform:translateX(-50%);
    z-index:9999;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;
    font-family:'Exo 2',sans-serif;color:#fff;box-shadow:0 6px 24px rgba(0,0,0,.18);
    animation:cppUp .3s ease;
}
.cpp-toast.success{ background:#2e7d32; }
.cpp-toast.error  { background:#c62828; }
</style>

<div class="cpp-wrap">

{{-- HERO --}}
<div class="cpp-hero cpp-anim">
    <h1>Simple, <span>Transparent</span> Pricing</h1>
    <p>Choose the plan that fits your trading style. Upgrade or cancel anytime.</p>
</div>

<div class="cpp-content">

    {{-- PLANS --}}
    <div class="cpp-plans">
        @foreach($plans as $plan)
        @php
            $isCurrent = $userSubscription && $userSubscription->isActive()
                && $userSubscription->plan->slug === $plan->slug;
            $isFeatured = $plan->slug === 'pro';
            $btnStyle   = $plan->price_monthly == 0 ? 'outline' : ($isFeatured ? 'gold' : 'dark');
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
                <div class="cpp-plan-price">
                    @if($plan->price_monthly == 0)
                        Free
                    @else
                        ₹{{ number_format($plan->price_monthly) }}
                        <span>/month</span>
                    @endif
                </div>
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
                        <i class="las la-check-circle"
                           style="color:{{ $plan->badge_color }};"></i>
                        {{ $f }}
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>

            @if($plan->analyses->count())
            <div class="cpp-analyses-count">
                <i class="las la-brain" style="color:{{ $plan->badge_color }};"></i>
                <span>
                    <strong>{{ $plan->analyses->count() }}</strong> analysis tools included
                </span>
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
                    <span class="cpp-btn-text">
                        <i class="las la-gift"></i> Get Free Plan
                    </span>
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
    <div class="cpp-faq-head">Common Questions</div>
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
    <div class="cpp-faq-item">
        <div class="cpp-faq-q" onclick="toggleFaq({{ $i }})">
            {{ $faq['q'] }}
            <i class="las la-plus" id="faq-icon-{{ $i }}"></i>
        </div>
        <div class="cpp-faq-a" id="faq-a-{{ $i }}">{{ $faq['a'] }}</div>
    </div>
    @endforeach

</div>{{-- /.cpp-content --}}
</div>{{-- /.cpp-wrap --}}

<script>
var CSRF     = '{{ csrf_token() }}';
var PAY_URL  = '{{ route("cp.subscribe.pay", ":plan") }}';
var VRF_URL  = '{{ route("cp.subscribe.verify") }}';
var LOGIN_URL= '{{ route("user.login") }}';
var IS_AUTH  = {{ auth()->check() ? 'true' : 'false' }};

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
    btn.classList.add('loading');
    btn.disabled = true;

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

        // Free plan — just redirect
        if (data.free) {
            toast(data.message, 'success');
            setTimeout(function() { window.location.href = data.redirect; }, 1200);
            return;
        }

        // Razorpay checkout
        var options = {
            key:         data.key,
            amount:      data.amount,
            currency:    'INR',
            name:        'CityQuants',
            description: planName + ' Subscription',
            order_id:    data.order_id,
            prefill: {
                name:    data.user_name,
                email:   data.user_email,
                contact: data.user_mobile,
            },
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
    var q  = document.getElementById('faq-icon-' + i).closest('.cpp-faq-q');
    var a  = document.getElementById('faq-a-' + i);
    var ic = document.getElementById('faq-icon-' + i);
    var wasOpen = a.classList.contains('open');
    document.querySelectorAll('.cpp-faq-q').forEach(function(el){ el.classList.remove('open'); });
    document.querySelectorAll('.cpp-faq-a').forEach(function(el){ el.classList.remove('open'); });
    document.querySelectorAll('.cpp-faq-q i').forEach(function(el){ el.style.transform=''; });
    if (!wasOpen) {
        q.classList.add('open');
        a.classList.add('open');
        ic.style.transform = 'rotate(45deg)';
    }
}
</script>
@endsection