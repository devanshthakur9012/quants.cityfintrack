{{-- FILE: resources/views/themes/{active_theme}/cp/analyses/index.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
.cp-wrap { font-family:'Exo 2',sans-serif; background:#f7f8fc; min-height:80vh; }
.cp-wrap *{ box-sizing:border-box; }
.cp-wrap h1,.cp-wrap h2,.cp-wrap h3,.cp-wrap h4{ font-family:'Rajdhani',sans-serif; }
.cp-wrap a{ text-decoration:none; }
@keyframes cpUp{ from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.cp-anim{ animation:cpUp .5s ease both; }

/* ── HERO ── */
.cp-hero{
    background:linear-gradient(135deg,#06101A 0%,#0F2848 60%,#091828 100%);
    padding:60px 48px 48px; position:relative; overflow:hidden;
}
.cp-hero::before{
    content:'';position:absolute;inset:0;
    background-image:linear-gradient(rgba(245,166,35,.04) 1px,transparent 1px),
        linear-gradient(90deg,rgba(245,166,35,.04) 1px,transparent 1px);
    background-size:48px 48px; pointer-events:none;
}
.cp-hero::after{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse 60% 60% at 80% 50%,rgba(245,166,35,.08),transparent 70%);
    pointer-events:none;
}
.cp-hero-inner{ position:relative;z-index:1;max-width:1200px;margin:0 auto;
    display:flex;align-items:center;justify-content:space-between;gap:32px;flex-wrap:wrap; }
.cp-hero-left h1{ font-size:clamp(28px,4vw,48px);font-weight:700;color:#fff;margin:0 0 12px;line-height:1.1; }
.cp-hero-left h1 span{ color:#F5A623; }
.cp-hero-left p{ font-size:15px;color:rgba(255,255,255,.6);margin:0 0 24px;line-height:1.7;max-width:540px; }
.cp-hero-btns{ display:flex;gap:12px;flex-wrap:wrap; }
.cp-btn-gold{
    display:inline-flex;align-items:center;gap:8px;
    background:#F5A623;color:#000;padding:12px 28px;border-radius:9px;
    font-family:'Rajdhani',sans-serif;font-size:15px;font-weight:700;letter-spacing:.04em;
    transition:background .2s;border:none;cursor:pointer;
}
.cp-btn-gold:hover{ background:#d4890e;color:#000; }
.cp-btn-outline{
    display:inline-flex;align-items:center;gap:8px;
    background:transparent;color:#fff;padding:12px 28px;border-radius:9px;
    border:1px solid rgba(255,255,255,.25);
    font-family:'Rajdhani',sans-serif;font-size:15px;font-weight:600;letter-spacing:.04em;
    transition:all .2s;cursor:pointer;
}
.cp-btn-outline:hover{ border-color:#F5A623;color:#F5A623; }

/* Subscription status banner */
.cp-sub-banner{
    max-width:1200px;margin:0 auto 0;position:relative;z-index:1;
    background:rgba(245,166,35,.12);border:1px solid rgba(245,166,35,.3);
    border-radius:10px;padding:12px 20px;
    display:flex;align-items:center;justify-content:space-between;gap:12px;
    flex-wrap:wrap; margin-top:28px;
}
.cp-sub-banner-text{ font-size:14px;color:rgba(255,255,255,.85);
    display:flex;align-items:center;gap:10px; }
.cp-sub-badge{ background:#F5A623;color:#000;border-radius:20px;
    padding:3px 12px;font-size:12px;font-weight:700; }

/* ── FILTER BAR ── */
.cp-filter-bar{
    background:#fff;border-bottom:1px solid #e8e8e8;
    padding:0 48px;position:sticky;top:0;z-index:200;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.cp-filter-inner{ max-width:1200px;margin:0 auto;
    display:flex;align-items:center;gap:16px;padding:12px 0;flex-wrap:wrap; }
.cp-tier-tabs{ display:flex;gap:6px; }
.cp-tier-tab{
    padding:7px 18px;border-radius:20px;border:1.5px solid #e5e9f2;
    font-size:13px;font-weight:600;color:#666;cursor:pointer;
    background:#fff;transition:all .2s;font-family:'Exo 2',sans-serif;
}
.cp-tier-tab.on{ border-color:#F5A623;background:rgba(245,166,35,.08);color:#d4890e; }
.cp-search-wrap{
    display:flex;align-items:stretch;border:1.5px solid #e5e9f2;border-radius:8px;
    overflow:hidden;margin-left:auto;
}
.cp-search-input{
    border:none;padding:8px 14px;font-size:13px;color:#333;outline:none;
    width:220px;font-family:'Exo 2',sans-serif;
}
.cp-search-btn{
    background:#F5A623;border:none;padding:0 14px;color:#000;cursor:pointer;font-size:14px;
}

/* ── CONTENT ── */
.cp-content{ max-width:1200px;margin:0 auto;padding:36px 48px 72px; }
@media(max-width:768px){ .cp-content{ padding:20px 16px 56px; } }
.cp-section-head{
    font-family:'Rajdhani',sans-serif;font-size:20px;font-weight:700;
    color:#1a1a2e;margin:0 0 20px;padding-bottom:8px;
    border-bottom:2px solid #F5A623;display:inline-block;
}
.cp-grid{
    display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:40px;
}
@media(max-width:1000px){ .cp-grid{ grid-template-columns:repeat(2,1fr); } }
@media(max-width:580px)  { .cp-grid{ grid-template-columns:1fr; } }

/* ── CARD ── */
.cp-card{
    background:#fff;border-radius:12px;overflow:hidden;
    border:1px solid #e8e8e8;transition:transform .25s,box-shadow .25s;
    display:flex;flex-direction:column;
}
.cp-card:hover{ transform:translateY(-4px);box-shadow:0 14px 36px rgba(0,0,0,.1); }
.cp-card-thumb{
    position:relative;aspect-ratio:16/9;overflow:hidden;
    background:linear-gradient(135deg,#06101A,#1a3050);flex-shrink:0;
}
.cp-card-thumb img{ width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s; }
.cp-card:hover .cp-card-thumb img{ transform:scale(1.04); }

/* default thumb icon */
.cp-thumb-icon{
    position:absolute;inset:0;display:flex;align-items:center;
    justify-content:center;font-size:48px;color:rgba(245,166,35,.4);
}

/* tier badge */
.cp-tier-badge{
    position:absolute;top:10px;left:10px;
    font-size:10px;font-weight:700;letter-spacing:.06em;
    padding:4px 10px;border-radius:4px;text-transform:uppercase;color:#fff;
}
.tier-free    { background:#059669; }
.tier-pro     { background:#1a56db; }
.tier-pro_plus{ background:#7c3aed; }

/* lock overlay */
.cp-lock-overlay{
    position:absolute;inset:0;
    background:rgba(6,16,26,.75);
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    gap:8px;color:#fff;
}
.cp-lock-overlay i{ font-size:28px;color:#F5A623; }
.cp-lock-overlay span{ font-size:12px;font-weight:600; }

.cp-card-body{ padding:14px 16px;flex:1;display:flex;flex-direction:column; }
.cp-card-title{
    font-family:'Rajdhani',sans-serif;font-size:16px;font-weight:700;
    color:#1a1a2e;line-height:1.3;margin-bottom:8px;flex:1;
}
.cp-card-desc{ font-size:13px;color:#777;line-height:1.6;margin-bottom:12px; }
.cp-card-tags{ display:flex;flex-wrap:wrap;gap:5px;margin-bottom:12px; }
.cp-tag{
    background:#f4f6fb;border:1px solid #e5e9f2;border-radius:4px;
    padding:2px 8px;font-size:11px;color:#666;font-weight:600;
}
.cp-card-footer{
    display:flex;align-items:center;justify-content:space-between;
    padding:10px 16px;border-top:1px solid #f0f0f0;background:#fafafa;gap:8px;
}
.cp-card-action{
    display:inline-flex;align-items:center;gap:5px;
    font-size:13px;font-weight:700;color:#F5A623;
    font-family:'Rajdhani',sans-serif;letter-spacing:.03em;transition:gap .2s;
}
.cp-card-action:hover{ gap:9px; }
.cp-card-action.locked{
    background:linear-gradient(90deg,#1a56db,#7c3aed);color:#fff;
    padding:6px 14px;border-radius:7px;font-size:12px;
}
.cp-card-action.locked:hover{ gap:7px;opacity:.9; }
.cp-data-src{
    font-size:11px;color:#aab;font-weight:600;
    display:flex;align-items:center;gap:4px;
}

/* ── NO RESULTS ── */
.cp-no-results{
    text-align:center;padding:70px 20px;color:#999;font-size:15px;grid-column:1/-1;
}
.cp-no-results i{ font-size:40px;color:#ddd;display:block;margin-bottom:14px; }

/* ── BOTTOM CTA ── */
.cp-bottom-cta{
    background:linear-gradient(135deg,#06101A,#0F2848);
    border-radius:16px;padding:48px 40px;text-align:center;margin-top:20px;
    position:relative;overflow:hidden;
}
.cp-bottom-cta::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(ellipse 60% 60% at 50% 50%,rgba(245,166,35,.1),transparent 70%);
    pointer-events:none;
}
.cp-bottom-cta h2{
    font-size:clamp(22px,3vw,36px);color:#fff;margin:0 0 10px;
    position:relative;
}
.cp-bottom-cta h2 span{ color:#F5A623; }
.cp-bottom-cta p{ color:rgba(255,255,255,.6);margin:0 0 24px;font-size:14px;position:relative; }

@media(max-width:768px){
    .cp-hero{ padding:36px 20px 32px; }
    .cp-filter-bar{ padding:0 16px; }
    .cp-search-wrap{ margin-left:0;width:100%; }
    .cp-search-input{ width:100%; }
}
</style>

<div class="cp-wrap">

{{-- ══ HERO ══ --}}
<div class="cp-hero">
    <div class="cp-hero-inner cp-anim">
        <div class="cp-hero-left">
            <h1>Options Trading <span>Analysis</span> Tools</h1>
            <p>
                Professional-grade analysis built on live 15min option &amp; futures data.
                Unlock powerful insights with a Pro or Pro Plus subscription.
            </p>
            <div class="cp-hero-btns">
                <a href="{{ route('cp.pricing') }}" class="cp-btn-gold">
                    <i class="las la-crown"></i> View Plans
                </a>
                @guest
                <a href="{{ route('user.login') }}" class="cp-btn-outline">
                    <i class="las la-sign-in-alt"></i> Login
                </a>
                @endguest
            </div>
        </div>
    </div>

    {{-- Subscription status --}}
    @if($userSubscription && $userSubscription->isActive())
    <div class="cp-sub-banner cp-anim">
        <div class="cp-sub-banner-text">
            <i class="las la-check-circle" style="color:#F5A623;font-size:18px;"></i>
            You are on the
            <span class="cp-sub-badge">{{ strtoupper($userSubscription->plan->name) }}</span>
            plan — expires {{ $userSubscription->expires_at->format('d M Y') }}
            ({{ $userSubscription->days_remaining }} days remaining)
        </div>
        <a href="{{ route('cp.my-subscription') }}" class="cp-btn-gold" style="padding:8px 18px;font-size:13px;">
            My Subscription →
        </a>
    </div>
    @elseif(auth()->check())
    <div class="cp-sub-banner cp-anim">
        <div class="cp-sub-banner-text">
            <i class="las la-info-circle" style="color:#F5A623;font-size:18px;"></i>
            You are on the <span class="cp-sub-badge">FREE</span> plan.
            Upgrade to unlock all analysis tools.
        </div>
        <a href="{{ route('cp.pricing') }}" class="cp-btn-gold" style="padding:8px 18px;font-size:13px;">
            Upgrade Now →
        </a>
    </div>
    @endif
</div>

{{-- ══ FILTER BAR ══ --}}
<div class="cp-filter-bar">
    <div class="cp-filter-inner">
        <div class="cp-tier-tabs">
            <button class="cp-tier-tab on" onclick="cpFilter('all',this)">All</button>
            <button class="cp-tier-tab" onclick="cpFilter('free',this)">Free</button>
            <button class="cp-tier-tab" onclick="cpFilter('pro',this)">Pro</button>
            <button class="cp-tier-tab" onclick="cpFilter('pro_plus',this)">Pro Plus</button>
        </div>
        <div class="cp-search-wrap">
            <input class="cp-search-input" type="text" id="cpSearch"
                   placeholder="Search analyses..." oninput="cpApplyFilter()">
            <button class="cp-search-btn"><i class="fas fa-search"></i></button>
        </div>
    </div>
</div>

{{-- ══ ANALYSES GRID ══ --}}
<div class="cp-content">

    @if($analyses->isEmpty())
    <div style="text-align:center;padding:80px 20px;color:#999;">
        <i class="las la-brain" style="font-size:4rem;color:#ddd;display:block;margin-bottom:16px;"></i>
        <h4>No analyses available yet</h4>
    </div>
    @else

    @php
        $tierOrder = ['free','pro','pro_plus'];
        $grouped   = $analyses->groupBy('plan_tier');
    @endphp

    {{-- All analyses in a flat searchable grid --}}
    <div class="cp-grid" id="cpGrid">
        @foreach($analyses as $a)
        @php $hasAccess = collect(['free'=>0,'pro'=>1,'pro_plus'=>2])[$userTier??'free'] >= collect(['free'=>0,'pro'=>1,'pro_plus'=>2])[$a->plan_tier]; @endphp
        <div class="cp-card"
             data-tier="{{ $a->plan_tier }}"
             data-title="{{ strtolower($a->name) }}"
             data-tags="{{ strtolower(implode(' ', $a->tags ?? [])) }}">

            <div class="cp-card-thumb">
                @if($a->thumbnail)
                    <img src="{{ $a->thumbnail_url }}" alt="{{ $a->name }}" loading="lazy">
                @else
                    <div class="cp-thumb-icon">
                        <i class="las la-chart-bar"></i>
                    </div>
                @endif

                {{-- Tier badge --}}
                <span class="cp-tier-badge tier-{{ $a->plan_tier }}">
                    {{ $a->plan_badge['label'] }}
                </span>

                {{-- Lock overlay for inaccessible --}}
                @if(!$hasAccess)
                <div class="cp-lock-overlay">
                    <i class="las la-lock"></i>
                    <span>{{ strtoupper($a->plan_tier === 'pro_plus' ? 'Pro Plus' : 'Pro') }} Required</span>
                </div>
                @endif
            </div>

            <div class="cp-card-body">
                <div class="cp-card-title">{{ $a->name }}</div>
                @if($a->short_description)
                <div class="cp-card-desc">{{ Str::limit($a->short_description, 90) }}</div>
                @endif
                @if(!empty($a->tags))
                <div class="cp-card-tags">
                    @foreach(array_slice($a->tags, 0, 3) as $tag)
                    <span class="cp-tag">{{ $tag }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="cp-card-footer">
                <div class="cp-data-src">
                    <i class="las la-database"></i>
                    {{ strtoupper($a->data_source) }}
                </div>

                @if($hasAccess && $a->route_name)
                    <a href="{{ route($a->route_name) }}" class="cp-card-action">
                        Open Tool <i class="las la-arrow-right"></i>
                    </a>
                @elseif($hasAccess)
                    <a href="{{ route('cp.analyses.detail', $a->slug) }}" class="cp-card-action">
                        View Details <i class="las la-arrow-right"></i>
                    </a>
                @else
                    <a href="{{ route('cp.pricing') }}" class="cp-card-action locked">
                        <i class="las la-crown"></i> Upgrade
                    </a>
                @endif
            </div>
        </div>
        @endforeach

        <div class="cp-no-results" id="cpNoResults" style="display:none;">
            <i class="las la-search"></i>
            No analyses match your search.
        </div>
    </div>

    @endif

    {{-- Bottom CTA --}}
    @if(!$userSubscription || !$userSubscription->isActive())
    <div class="cp-bottom-cta">
        <h2>Unlock <span>All Analysis Tools</span></h2>
        <p>Subscribe to Pro or Pro Plus and get full access to every analysis module.</p>
        <a href="{{ route('cp.pricing') }}" class="cp-btn-gold">
            <i class="las la-crown"></i> See Plans & Pricing
        </a>
    </div>
    @endif

</div>{{-- /.cp-content --}}
</div>{{-- /.cp-wrap --}}

<script>
var currentTier = 'all';

function cpFilter(tier, btn) {
    currentTier = tier;
    document.querySelectorAll('.cp-tier-tab').forEach(function(b) { b.classList.remove('on'); });
    btn.classList.add('on');
    cpApplyFilter();
}

function cpApplyFilter() {
    var search  = document.getElementById('cpSearch').value.toLowerCase().trim();
    var cards   = document.querySelectorAll('#cpGrid .cp-card');
    var visible = 0;

    cards.forEach(function(card) {
        var tierOk   = currentTier === 'all' || card.dataset.tier === currentTier;
        var searchOk = !search
            || card.dataset.title.includes(search)
            || (card.dataset.tags || '').includes(search);
        var show = tierOk && searchOk;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    var noRes = document.getElementById('cpNoResults');
    if (noRes) noRes.style.display = visible === 0 ? 'block' : 'none';
}
</script>
@endsection