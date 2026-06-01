{{-- FILE: resources/views/themes/{active_theme}/cp/subscription/my-subscription.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
.cps-wrap{ font-family:'Exo 2',sans-serif;background:#f7f8fc;min-height:80vh; }
.cps-wrap *{ box-sizing:border-box; }
.cps-wrap h1,.cps-wrap h2,.cps-wrap h3{ font-family:'Rajdhani',sans-serif; }
.cps-wrap a{ text-decoration:none; }
@keyframes cpsUp{ from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.cps-anim{ animation:cpsUp .5s ease both; }

/* Layout */
.cps-inner{ max-width:1100px;margin:0 auto;padding:40px 24px 72px; }

/* Page title */
.cps-page-title{
    font-size:clamp(24px,3vw,36px);font-weight:700;color:#1a1a2e;margin:0 0 6px;
}
.cps-page-sub{ font-size:14px;color:#888;margin:0 0 32px; }

/* Current plan card */
.cps-plan-card{
    border-radius:20px;padding:32px;margin-bottom:28px;
    position:relative;overflow:hidden;
    background:linear-gradient(135deg,#06101A 0%,#0F2848 100%);
    border:1px solid rgba(245,166,35,.2);
}
.cps-plan-card::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse 60% 80% at 90% 50%,rgba(245,166,35,.08),transparent 70%);
    pointer-events:none;
}
.cps-plan-card-inner{ position:relative;z-index:1;
    display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap; }
.cps-plan-info h2{ font-size:clamp(22px,3vw,34px);font-weight:700;color:#fff;margin:0 0 6px; }
.cps-plan-badge{
    display:inline-block;padding:4px 14px;border-radius:20px;
    font-size:12px;font-weight:700;letter-spacing:.06em;
    margin-bottom:10px;
}
.cps-plan-meta{ display:flex;flex-wrap:wrap;gap:16px;margin-top:12px; }
.cps-plan-meta-item{
    display:flex;align-items:center;gap:6px;
    font-size:13px;color:rgba(255,255,255,.65);
}
.cps-plan-meta-item i{ color:#7DFF00;font-size:14px; }
.cps-plan-actions{ display:flex;flex-direction:column;gap:10px;align-items:flex-end; }
.cps-btn-gold{
    display:inline-flex;align-items:center;gap:7px;
    background:#7DFF00;color:#000;padding:11px 24px;border-radius:9px;
    font-family:'Rajdhani',sans-serif;font-size:14px;font-weight:700;
    letter-spacing:.04em;transition:background .2s;white-space:nowrap;
}
.cps-btn-gold:hover{ background:#d4890e;color:#000; }
.cps-btn-ghost{
    display:inline-flex;align-items:center;gap:7px;
    background:transparent;color:rgba(255,255,255,.5);padding:10px 24px;border-radius:9px;
    font-size:13px;font-weight:600;transition:color .2s;white-space:nowrap;
    border:1px solid rgba(255,255,255,.15);
}
.cps-btn-ghost:hover{ color:#fff;border-color:rgba(255,255,255,.4); }

/* No subscription */
.cps-no-sub{
    background:#fff;border-radius:16px;border:1px solid #e8e8e8;
    padding:48px;text-align:center;margin-bottom:28px;
}
.cps-no-sub i{ font-size:52px;color:#ddd;display:block;margin-bottom:16px; }
.cps-no-sub h3{ font-size:24px;color:#1a1a2e;margin-bottom:8px; }
.cps-no-sub p{ font-size:14px;color:#888;margin-bottom:24px;line-height:1.7; }

/* Grid */
.cps-grid{ display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px; }
@media(max-width:768px){ .cps-grid{ grid-template-columns:1fr; } }

/* Card base */
.cps-card{
    background:#fff;border-radius:16px;border:1px solid #e8e8e8;padding:24px;
}
.cps-card-title{
    font-family:'Rajdhani',sans-serif;font-size:18px;font-weight:700;
    color:#1a1a2e;margin:0 0 16px;padding-bottom:10px;border-bottom:1px solid #f0f0f0;
}

/* Accessible analyses */
.cps-analyses-grid{ display:grid;grid-template-columns:1fr 1fr;gap:10px; }
.cps-analysis-item{
    display:flex;align-items:center;gap:10px;padding:8px 10px;
    background:#f7f8fc;border-radius:8px;font-size:13px;
}
.cps-analysis-item i{ color:#059669;font-size:14px;flex-shrink:0; }
.cps-analysis-item a{ color:#1a1a2e;font-weight:600; }
.cps-analysis-item a:hover{ color:#7DFF00; }

/* Payment history table */
.cps-payments-wrap{ background:#fff;border-radius:16px;border:1px solid #e8e8e8;overflow:hidden; }
.cps-payments-title{
    font-family:'Rajdhani',sans-serif;font-size:18px;font-weight:700;
    color:#1a1a2e;padding:20px 24px 16px;border-bottom:1px solid #f0f0f0;margin:0;
}
.cps-table{ width:100%;border-collapse:collapse; }
.cps-table th{
    background:#f7f8fc;padding:10px 16px;font-size:11px;font-weight:700;
    color:#aab;text-transform:uppercase;letter-spacing:.06em;text-align:left;
    border-bottom:1px solid #e8e8e8;
}
.cps-table td{
    padding:12px 16px;font-size:13px;color:#444;border-bottom:1px solid #f5f5f5;
}
.cps-table tr:last-child td{ border-bottom:none; }
.cps-table tr:hover td{ background:#fafafa; }
.cps-pay-badge{
    display:inline-block;padding:3px 10px;border-radius:4px;font-size:11px;
    font-weight:700;text-transform:uppercase;
}
.cps-pay-badge.paid    { background:#e8f5e9;color:#2e7d32; }
.cps-pay-badge.pending { background:#fff3e0;color:#e65100; }
.cps-pay-badge.failed  { background:#ffeaea;color:#c62828; }
.cps-pay-badge.refunded{ background:#eff6ff;color:#1e40af; }

/* Upgrade banner */
.cps-upgrade-banner{
    background:linear-gradient(90deg,#06101A,#0F2848);
    border-radius:14px;padding:28px 32px;
    display:flex;align-items:center;justify-content:space-between;gap:20px;
    flex-wrap:wrap;margin-bottom:28px;
    border:1px solid rgba(245,166,35,.2);
}
.cps-upgrade-banner-text h3{
    font-family:'Rajdhani',sans-serif;font-size:22px;font-weight:700;
    color:#fff;margin:0 0 5px;
}
.cps-upgrade-banner-text p{ font-size:13px;color:rgba(255,255,255,.55);margin:0; }

@media(max-width:768px){
    .cps-inner{ padding:24px 16px 56px; }
    .cps-plan-card-inner{ flex-direction:column;align-items:flex-start; }
    .cps-plan-actions{ align-items:flex-start; }
    .cps-analyses-grid{ grid-template-columns:1fr; }
}
</style>

<div class="cps-wrap">
<div class="cps-inner">

    <h1 class="cps-page-title cps-anim">My Subscription</h1>
    <p class="cps-page-sub">Manage your plan and view payment history</p>

    {{-- ══ CURRENT PLAN ══ --}}
    @if($userSubscription && $userSubscription->isActive())
    <div class="cps-plan-card cps-anim">
        <div class="cps-plan-card-inner">
            <div class="cps-plan-info">
                <span class="cps-plan-badge"
                      style="background:{{ $userSubscription->plan->badge_color }};color:#fff;">
                    {{ strtoupper($userSubscription->plan->name) }} PLAN
                </span>
                <h2>{{ $userSubscription->plan->name }} Subscription</h2>
                <div class="cps-plan-meta">
                    <div class="cps-plan-meta-item">
                        <i class="las la-calendar-check"></i>
                        Started {{ $userSubscription->starts_at->format('d M Y') }}
                    </div>
                    <div class="cps-plan-meta-item">
                        <i class="las la-calendar-times"></i>
                        Expires {{ $userSubscription->expires_at->format('d M Y') }}
                    </div>
                    <div class="cps-plan-meta-item">
                        <i class="las la-clock"></i>
                        {{ $userSubscription->days_remaining }} days remaining
                    </div>
                </div>
            </div>
            <div class="cps-plan-actions">
                <a href="{{ route('cp.pricing') }}" class="cps-btn-gold">
                    <i class="las la-arrow-up"></i> Upgrade Plan
                </a>
                <a href="{{ route('cp.analyses.index') }}" class="cps-btn-ghost">
                    <i class="las la-th"></i> Browse Analyses
                </a>
            </div>
        </div>
    </div>

    {{-- Upgrade banner for non-pro_plus --}}
    @if($userTier !== 'pro_plus')
    <div class="cps-upgrade-banner cps-anim">
        <div class="cps-upgrade-banner-text">
            <h3>Unlock Everything with Pro Plus</h3>
            <p>Get access to all analysis tools including advanced option strategies and OI analysis.</p>
        </div>
        <a href="{{ route('cp.pricing') }}" class="cps-btn-gold">
            <i class="las la-gem"></i> Upgrade to Pro Plus
        </a>
    </div>
    @endif

    @else
    <div class="cps-no-sub cps-anim">
        <i class="las la-crown"></i>
        <h3>No Active Subscription</h3>
        <p>You are on the free plan. Subscribe to unlock premium analysis tools.</p>
        <a href="{{ route('cp.pricing') }}" class="cps-btn-gold">
            <i class="las la-crown"></i> View Plans & Subscribe
        </a>
    </div>
    @endif

    {{-- ══ ACCESSIBLE ANALYSES + PLAN INFO ══ --}}
    <div class="cps-grid cps-anim">

        {{-- Accessible analyses --}}
        <div class="cps-card">
            <div class="cps-card-title">
                <i class="las la-brain" style="color:#7DFF00;"></i>
                Your Analysis Tools
                <span style="float:right;font-size:13px;font-weight:400;color:#aab;">
                    {{ $accessibleAnalyses->count() }} available
                </span>
            </div>
            @if($accessibleAnalyses->isEmpty())
            <div style="text-align:center;padding:20px;color:#aab;font-size:13px;">
                <i class="las la-info-circle"></i> No analyses available yet.
            </div>
            @else
            <div class="cps-analyses-grid">
                @foreach($accessibleAnalyses as $a)
                <div class="cps-analysis-item">
                    <i class="las la-check-circle"></i>
                    <div>
                        @if($a->route_name)
                        <a href="{{ route($a->route_name) }}">{{ $a->name }}</a>
                        @else
                        <a href="{{ route('cp.analyses.detail', $a->slug) }}">{{ $a->name }}</a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Plan comparison summary --}}
        <div class="cps-card">
            <div class="cps-card-title">
                <i class="las la-list" style="color:#7DFF00;"></i>
                Plan Comparison
            </div>
            @foreach($plans as $plan)
            @php $isActive = $userTier === $plan->slug; @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding:10px 0;border-bottom:1px solid #f5f5f5;font-size:13px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    @if($isActive)
                    <i class="las la-check-circle" style="color:#059669;"></i>
                    @else
                    <i class="las la-circle" style="color:#ddd;"></i>
                    @endif
                    <span style="font-weight:{{ $isActive ? '700' : '400' }};
                                 color:{{ $isActive ? '#1a1a2e' : '#888' }};">
                        {{ $plan->name }}
                    </span>
                    @if($isActive)
                    <span style="background:{{ $plan->badge_color }};color:#fff;
                                 border-radius:4px;padding:1px 7px;font-size:10px;
                                 font-weight:700;">Current</span>
                    @endif
                </div>
                <span style="color:{{ $isActive ? '#1a1a2e' : '#aab' }};font-weight:600;">
                    @if($plan->price_monthly == 0) Free
                    @else ₹{{ number_format($plan->price_monthly) }}/mo
                    @endif
                </span>
            </div>
            @endforeach
            <div style="margin-top:14px;">
                <a href="{{ route('cp.pricing') }}"
                   style="font-size:13px;color:#7DFF00;font-weight:600;">
                    View full plan details →
                </a>
            </div>
        </div>

    </div>

    {{-- ══ PAYMENT HISTORY ══ --}}
    <div class="cps-payments-wrap cps-anim">
        <h3 class="cps-payments-title">
            <i class="las la-receipt" style="color:#7DFF00;"></i>
            Payment History
        </h3>
        @if($payments->isEmpty())
        <div style="text-align:center;padding:40px;color:#aab;font-size:14px;">
            <i class="las la-inbox" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
            No payments yet.
        </div>
        @else
        <div style="overflow-x:auto;">
            <table class="cps-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order</th>
                        <th>Plan</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $pay)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><code style="font-size:12px;">{{ $pay->order_number }}</code></td>
                        <td>{{ $pay->plan->name ?? '—' }}</td>
                        <td><strong>₹{{ number_format($pay->amount) }}</strong></td>
                        <td>
                            <span class="cps-pay-badge {{ $pay->status }}">
                                {{ ucfirst($pay->status) }}
                            </span>
                        </td>
                        <td>
                            {{ $pay->paid_at
                                ? $pay->paid_at->format('d M Y')
                                : $pay->created_at->format('d M Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($payments->hasPages())
        <div style="padding:16px 24px;border-top:1px solid #f0f0f0;">
            {{ $payments->links() }}
        </div>
        @endif
        @endif
    </div>

</div>{{-- /.cps-inner --}}
</div>{{-- /.cps-wrap --}}
@endsection