{{-- FILE: resources/views/themes/{active_theme}/cp/subscription/my-subscription.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — MY SUBSCRIPTION  v2.0
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
    --c-teal:     #26A69A;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.cps-wrap {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    min-height: 80vh;
}
.cps-wrap * { box-sizing: border-box; }
.cps-wrap a { text-decoration: none; color: inherit; }

@keyframes cpsFadeUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.cps-anim    { animation: cpsFadeUp .5s ease both; }
.cps-anim.d1 { animation-delay: .08s; }
.cps-anim.d2 { animation-delay: .16s; }
.cps-anim.d3 { animation-delay: .24s; }

/* ── BREADCRUMB ────────────────────────────── */
.cps-breadcrumb {
    background: var(--c-surface);
    border-bottom: 1px solid var(--c-border);
    padding: 12px 24px;
}
.cps-breadcrumb-inner {
    max-width: 1160px; margin: 0 auto;
    font-size: 12px; color: var(--c-muted);
    display: flex; align-items: center; gap: 7px;
    font-family: var(--f-mono);
}
.cps-breadcrumb-inner a { color: var(--c-lime); font-weight: 600; transition: opacity .2s; }
.cps-breadcrumb-inner a:hover { opacity: .75; }
.cps-breadcrumb-inner i { font-size: 10px; color: var(--c-border2); }

/* ── PAGE HEADER ───────────────────────────── */
.cps-header {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 44px 24px 36px;
    border-bottom: 1px solid var(--c-border);
}
.cps-header::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 80% at 30% 50%, black, transparent);
    pointer-events: none;
}
.cps-header::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 40% 70% at 10% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.cps-header-inner {
    position: relative; z-index: 1;
    max-width: 1160px; margin: 0 auto;
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.cps-page-title {
    font-family: var(--f-display);
    font-size: clamp(24px, 3.5vw, 34px);
    font-weight: 800; color: #fff; margin-bottom: 5px;
    letter-spacing: -.01em;
}
.cps-page-sub { font-size: 13px; color: var(--c-muted); }

/* ── LAYOUT ────────────────────────────────── */
.cps-inner { max-width: 1160px; margin: 0 auto; padding: 32px 24px 80px; }

/* ── SHARED BUTTONS ────────────────────────── */
.cps-btn-lime {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    letter-spacing: .05em; padding: 10px 22px; border-radius: 7px;
    border: none; cursor: pointer; transition: all .2s;
    box-shadow: 0 0 16px rgba(125,255,0,.2); white-space: nowrap;
}
.cps-btn-lime:hover { background: #8FFF1A; color: #000; box-shadow: 0 0 26px rgba(125,255,0,.35); transform: translateY(-1px); }
.cps-btn-ghost {
    display: inline-flex; align-items: center; gap: 7px;
    background: transparent; color: var(--c-muted);
    font-size: 13px; font-weight: 500;
    padding: 10px 20px; border-radius: 7px;
    border: 1px solid var(--c-border2); transition: all .2s; white-space: nowrap;
}
.cps-btn-ghost:hover { border-color: rgba(125,255,0,.3); color: var(--c-lime); }

/* ── ACTIVE PLAN HERO CARD ─────────────────── */
.cps-plan-card {
    position: relative; overflow: hidden;
    background: var(--c-surface);
    border: 1px solid rgba(125,255,0,.25);
    border-radius: 12px; padding: 32px 28px;
    margin-bottom: 20px;
    box-shadow: 0 0 0 1px rgba(125,255,0,.08), 0 16px 48px rgba(0,0,0,.4);
}
.cps-plan-card::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .6;
}
.cps-plan-card::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 55% 65% at 85% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.cps-plan-card-inner {
    position: relative; z-index: 1;
    display: flex; align-items: center;
    justify-content: space-between; gap: 20px; flex-wrap: wrap;
}
.cps-plan-badge {
    display: inline-block; padding: 4px 14px; border-radius: 100px;
    font-family: var(--f-display); font-size: 10px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase; margin-bottom: 10px;
}
.cps-plan-info-title {
    font-family: var(--f-display);
    font-size: clamp(20px, 3vw, 30px);
    font-weight: 700; color: #fff; margin-bottom: 14px;
}
.cps-plan-meta {
    display: flex; flex-wrap: wrap; gap: 14px;
}
.cps-plan-meta-item {
    display: flex; align-items: center; gap: 7px;
    font-size: 12px; color: var(--c-muted); font-family: var(--f-mono);
}
.cps-plan-meta-item i { color: var(--c-lime); font-size: 13px; }
.cps-plan-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }

/* Days remaining progress */
.cps-days-bar {
    margin-top: 18px; padding-top: 16px;
    border-top: 1px solid var(--c-border);
    position: relative; z-index: 1;
}
.cps-days-label {
    display: flex; justify-content: space-between;
    font-size: 11px; color: var(--c-muted); margin-bottom: 8px;
    font-family: var(--f-mono);
}
.cps-days-track {
    height: 4px; background: var(--c-panel);
    border-radius: 2px; overflow: hidden;
}
.cps-days-fill {
    height: 100%; border-radius: 2px;
    background: linear-gradient(90deg, var(--c-lime), #8FFF1A);
    transition: width .5s ease;
}

/* ── NO SUBSCRIPTION ───────────────────────── */
.cps-no-sub {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 52px 32px;
    text-align: center; margin-bottom: 20px;
    position: relative; overflow: hidden;
}
.cps-no-sub::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-border2), transparent);
}
.cps-no-sub-icon {
    width: 64px; height: 64px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border2);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 18px; font-size: 26px; color: var(--c-muted);
}
.cps-no-sub h3 {
    font-family: var(--f-display); font-size: 22px;
    font-weight: 700; color: var(--c-text); margin-bottom: 8px;
}
.cps-no-sub p { font-size: 14px; color: var(--c-muted); margin-bottom: 24px; line-height: 1.7; }

/* ── UPGRADE BANNER ────────────────────────── */
.cps-upgrade-banner {
    background: var(--c-surface);
    border: 1px solid rgba(125,255,0,.2);
    border-radius: 12px; padding: 22px 28px;
    display: flex; align-items: center;
    justify-content: space-between; gap: 16px;
    flex-wrap: wrap; margin-bottom: 20px;
    position: relative; overflow: hidden;
}
.cps-upgrade-banner::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 40% 70% at 95% 50%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.cps-upgrade-banner h3 {
    font-family: var(--f-display); font-size: 18px;
    font-weight: 700; color: #fff; margin-bottom: 4px;
}
.cps-upgrade-banner p { font-size: 13px; color: var(--c-muted); }

/* ── GRID (analyses + plans comparison) ────── */
.cps-grid {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: 20px; margin-bottom: 20px;
}
@media (max-width: 768px) { .cps-grid { grid-template-columns: 1fr; } }

/* Cards */
.cps-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
    position: relative;
}
.cps-card::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .35;
}
.cps-card-title {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--c-border);
    font-family: var(--f-display); font-size: 15px; font-weight: 700; color: var(--c-text);
}
.cps-card-title i { color: var(--c-lime); margin-right: 6px; }
.cps-card-title-count {
    font-family: var(--f-mono); font-size: 11px; font-weight: 600;
    color: var(--c-muted);
    background: var(--c-panel); border: 1px solid var(--c-border);
    padding: 2px 9px; border-radius: 100px;
}
.cps-card-body { padding: 16px 20px; }

/* Analysis items */
.cps-analyses-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
@media (max-width: 480px) { .cps-analyses-grid { grid-template-columns: 1fr; } }
.cps-analysis-item {
    display: flex; align-items: center; gap: 9px;
    padding: 8px 12px;
    background: var(--c-panel);
    border: 1px solid var(--c-border);
    border-radius: 7px; font-size: 12px;
    transition: border-color .2s;
}
.cps-analysis-item:hover { border-color: rgba(125,255,0,.2); }
.cps-analysis-item i { color: var(--c-teal); font-size: 13px; flex-shrink: 0; }
.cps-analysis-item a { color: var(--c-text); font-weight: 600; transition: color .2s; }
.cps-analysis-item a:hover { color: var(--c-lime); }

/* Plan comparison rows */
.cps-plan-row {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 10px 0; border-bottom: 1px solid var(--c-border);
    font-size: 13px;
}
.cps-plan-row:last-of-type { border-bottom: none; }
.cps-plan-row-left { display: flex; align-items: center; gap: 8px; }
.cps-plan-row-left i { font-size: 14px; }
.cps-plan-row-name { font-weight: 500; }
.cps-plan-row-name.active { font-weight: 700; color: #fff; }
.cps-plan-row-tag {
    font-family: var(--f-mono); font-size: 9px; font-weight: 700;
    padding: 2px 8px; border-radius: 100px; letter-spacing: .08em;
}
.cps-plan-row-price {
    font-family: var(--f-mono); font-weight: 600; color: var(--c-muted); font-size: 12px;
}
.cps-plan-row-price.active-price { color: var(--c-lime); }
.cps-see-plans {
    display: block; margin-top: 14px;
    font-size: 13px; color: var(--c-lime); font-weight: 600;
    transition: opacity .2s;
}
.cps-see-plans:hover { opacity: .75; }

/* ── PAYMENT HISTORY ───────────────────────── */
.cps-payments-wrap {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden;
    position: relative;
}
.cps-payments-wrap::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .35;
}
.cps-payments-title {
    display: flex; align-items: center; gap: 8px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--c-border);
    font-family: var(--f-display); font-size: 16px;
    font-weight: 700; color: var(--c-text);
}
.cps-payments-title i { color: var(--c-lime); }

/* Table */
.cps-table { width: 100%; border-collapse: collapse; }
.cps-table th {
    background: var(--c-panel);
    padding: 10px 16px; font-size: 10px; font-weight: 700;
    color: var(--c-muted); text-transform: uppercase; letter-spacing: .1em;
    text-align: left; border-bottom: 1px solid var(--c-border);
    font-family: var(--f-mono);
}
.cps-table td {
    padding: 13px 16px; font-size: 13px;
    color: var(--c-text); border-bottom: 1px solid var(--c-border);
    vertical-align: middle;
}
.cps-table tr:last-child td { border-bottom: none; }
.cps-table tr:hover td { background: var(--c-faint); }

.cps-table code {
    font-family: var(--f-mono); font-size: 11px; color: var(--c-muted);
    background: var(--c-panel); border: 1px solid var(--c-border);
    padding: 2px 7px; border-radius: 4px;
}

/* Status badges */
.cps-pay-badge {
    display: inline-flex; align-items: center; gap: 4px;
    font-family: var(--f-mono); font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .07em;
    padding: 4px 10px; border-radius: 4px;
}
.cps-pay-badge.paid     { background: rgba(38,166,154,.12); color: #4DB6AC; }
.cps-pay-badge.pending  { background: rgba(255,167,38,.1);  color: #FFA726; }
.cps-pay-badge.failed   { background: rgba(239,83,80,.1);   color: #EF9A9A; }
.cps-pay-badge.refunded { background: rgba(0,184,212,.1);   color: var(--c-blue); }

/* Empty */
.cps-empty {
    text-align: center; padding: 52px 20px; color: var(--c-muted);
}
.cps-empty-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 14px; font-size: 22px;
}
.cps-empty p { font-size: 13px; margin-top: 6px; }

/* Responsive */
@media (max-width: 768px) {
    .cps-plan-card-inner { flex-direction: column; align-items: flex-start; }
    .cps-plan-actions { align-items: flex-start; }
    .cps-inner { padding: 24px 16px 60px; }
}
</style>

<div class="cps-wrap">

{{-- BREADCRUMB --}}
<div class="cps-breadcrumb">
    <div class="cps-breadcrumb-inner">
        <a href="{{ route('home') }}">Home</a>
        <i class="las la-angle-right"></i>
        <a href="{{ route('user.dashboard') }}">Dashboard</a>
        <i class="las la-angle-right"></i>
        <span>My Subscription</span>
    </div>
</div>

{{-- PAGE HEADER --}}
<div class="cps-header">
    <div class="cps-header-inner cps-anim">
        <div>
            <h1 class="cps-page-title">My Subscription</h1>
            <p class="cps-page-sub">Manage your plan and view payment history</p>
        </div>
        <a href="{{ route('cp.pricing') }}" class="cps-btn-lime">
            <i class="las la-crown"></i> View All Plans
        </a>
    </div>
</div>

<div class="cps-inner">

    {{-- ══ CURRENT PLAN CARD ══ --}}
    @if($userSubscription && $userSubscription->isActive())
    <div class="cps-plan-card cps-anim">
        <div class="cps-plan-card-inner">
            <div>
                <span class="cps-plan-badge"
                      style="background:{{ $userSubscription->plan->badge_color }};color:#000;">
                    {{ strtoupper($userSubscription->plan->name) }} PLAN
                </span>
                <div class="cps-plan-info-title">{{ $userSubscription->plan->name }} Subscription</div>
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
                <a href="{{ route('cp.pricing') }}" class="cps-btn-lime">
                    <i class="las la-arrow-up"></i> Upgrade Plan
                </a>
                <a href="{{ route('cp.analyses.index') }}" class="cps-btn-ghost">
                    <i class="las la-th"></i> Browse Analyses
                </a>
            </div>
        </div>
        {{-- Days remaining bar --}}
        <div class="cps-days-bar">
            <div class="cps-days-label">
                <span>Subscription progress</span>
                <span>{{ $userSubscription->days_remaining }} / 30 days left</span>
            </div>
            <div class="cps-days-track">
                <div class="cps-days-fill"
                     style="width:{{ min(100, ($userSubscription->days_remaining / 30) * 100) }}%">
                </div>
            </div>
        </div>
    </div>

    {{-- Upgrade banner for non-pro_plus --}}
    @if($userTier !== 'pro_plus')
    <div class="cps-upgrade-banner cps-anim d1">
        <div>
            <h3>Unlock Everything with Pro Plus</h3>
            <p>Get access to all analysis tools including advanced option strategies and OI analysis.</p>
        </div>
        <a href="{{ route('cp.pricing') }}" class="cps-btn-lime">
            <i class="las la-gem"></i> Upgrade to Pro Plus
        </a>
    </div>
    @endif

    @else
    {{-- No subscription --}}
    <div class="cps-no-sub cps-anim">
        <div class="cps-no-sub-icon"><i class="las la-crown"></i></div>
        <h3>No Active Subscription</h3>
        <p>You are on the free plan. Subscribe to unlock premium analysis tools.</p>
        <a href="{{ route('cp.pricing') }}" class="cps-btn-lime">
            <i class="las la-crown"></i> View Plans &amp; Subscribe
        </a>
    </div>
    @endif

    {{-- ══ ANALYSES + PLAN COMPARISON ══ --}}
    <div class="cps-grid cps-anim d2">

        {{-- Accessible analyses --}}
        <div class="cps-card">
            <div class="cps-card-title">
                <span><i class="las la-brain"></i> Your Analysis Tools</span>
                <span class="cps-card-title-count">{{ $accessibleAnalyses->count() }} available</span>
            </div>
            <div class="cps-card-body">
                @if($accessibleAnalyses->isEmpty())
                <div class="cps-empty">
                    <div class="cps-empty-icon"><i class="las la-info-circle"></i></div>
                    <p>No analyses available yet.</p>
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
        </div>

        {{-- Plan comparison --}}
        <div class="cps-card">
            <div class="cps-card-title">
                <span><i class="las la-list"></i> Plan Comparison</span>
            </div>
            <div class="cps-card-body">
                @foreach($plans as $plan)
                @php $isActive = $userTier === $plan->slug; @endphp
                <div class="cps-plan-row">
                    <div class="cps-plan-row-left">
                        @if($isActive)
                            <i class="las la-check-circle" style="color:var(--c-teal);"></i>
                        @else
                            <i class="las la-circle" style="color:var(--c-border2);"></i>
                        @endif
                        <span class="cps-plan-row-name {{ $isActive ? 'active' : '' }}">
                            {{ $plan->name }}
                        </span>
                        @if($isActive)
                        <span class="cps-plan-row-tag"
                              style="background:{{ $plan->badge_color }};color:#000;">
                            Current
                        </span>
                        @endif
                    </div>
                    <span class="cps-plan-row-price {{ $isActive ? 'active-price' : '' }}">
                        @if($plan->price_monthly == 0) Free
                        @else ₹{{ number_format($plan->price_monthly) }}/mo
                        @endif
                    </span>
                </div>
                @endforeach
                <a href="{{ route('cp.pricing') }}" class="cps-see-plans">
                    View full plan details <i class="las la-arrow-right"></i>
                </a>
            </div>
        </div>

    </div>

    {{-- ══ PAYMENT HISTORY ══ --}}
    <div class="cps-payments-wrap cps-anim d3">
        <div class="cps-payments-title">
            <i class="las la-receipt"></i> Payment History
        </div>
        @if($payments->isEmpty())
        <div class="cps-empty">
            <div class="cps-empty-icon"><i class="las la-inbox"></i></div>
            <p>No payments yet.</p>
        </div>
        @else
        <div style="overflow-x: auto;">
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
                        <td style="font-family:var(--f-mono);font-size:11px;color:var(--c-muted);">
                            {{ $loop->iteration }}
                        </td>
                        <td><code>{{ $pay->order_number }}</code></td>
                        <td style="font-weight:600;color:#fff;">{{ $pay->plan->name ?? '—' }}</td>
                        <td>
                            <span style="font-family:var(--f-mono);font-weight:600;color:#fff;">
                                ₹{{ number_format($pay->amount) }}
                            </span>
                        </td>
                        <td>
                            <span class="cps-pay-badge {{ $pay->status }}">
                                @if($pay->status === 'paid')    <i class="las la-check-circle"></i>
                                @elseif($pay->status === 'pending') <i class="las la-clock"></i>
                                @else <i class="las la-times-circle"></i>
                                @endif
                                {{ ucfirst($pay->status) }}
                            </span>
                        </td>
                        <td style="font-family:var(--f-mono);font-size:11px;color:var(--c-muted);">
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
        <div style="padding:16px 22px;border-top:1px solid var(--c-border);">
            {{ $payments->links() }}
        </div>
        @endif
        @endif
    </div>

</div>
</div>

@endsection