{{-- FILE: resources/views/themes/{active_theme}/cp/analyses/detail.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
.cpd-wrap{ font-family:'Exo 2',sans-serif;background:#f7f8fc;min-height:80vh; }
.cpd-wrap *{ box-sizing:border-box; }
.cpd-wrap h1,.cpd-wrap h2,.cpd-wrap h3{ font-family:'Rajdhani',sans-serif; }
.cpd-wrap a{ text-decoration:none; }

/* Breadcrumb */
.cpd-breadcrumb{ background:#fff;border-bottom:1px solid #e8e8e8;padding:12px 48px; }
.cpd-breadcrumb-inner{ max-width:1200px;margin:0 auto;font-size:13px;color:#888;
    display:flex;align-items:center;gap:6px; }
.cpd-breadcrumb-inner a{ color:#F5A623;font-weight:600; }

/* Main layout */
.cpd-main{ max-width:1200px;margin:0 auto;padding:36px 48px 72px;
    display:grid;grid-template-columns:1fr 340px;gap:32px; }
@media(max-width:900px){ .cpd-main{ grid-template-columns:1fr;padding:20px 16px 56px; } }

/* Left: content */
.cpd-content-card{
    background:#fff;border-radius:16px;border:1px solid #e8e8e8;overflow:hidden;
}
.cpd-thumb{ aspect-ratio:16/6;overflow:hidden;background:linear-gradient(135deg,#06101A,#1a3050);
    position:relative;display:flex;align-items:center;justify-content:center; }
.cpd-thumb img{ width:100%;height:100%;object-fit:cover;display:block; }
.cpd-thumb-icon{ font-size:80px;color:rgba(245,166,35,.3); }
.cpd-tier-badge{
    position:absolute;top:16px;left:16px;
    font-size:12px;font-weight:700;letter-spacing:.06em;
    padding:5px 14px;border-radius:20px;color:#fff;text-transform:uppercase;
}
.tier-free    { background:#059669; }
.tier-pro     { background:#1a56db; }
.tier-pro_plus{ background:#7c3aed; }

.cpd-body{ padding:28px 32px; }
.cpd-title{ font-size:clamp(24px,3vw,36px);font-weight:700;color:#1a1a2e;margin:0 0 12px; }
.cpd-tags{ display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px; }
.cpd-tag{ background:#f4f6fb;border:1px solid #e5e9f2;border-radius:4px;
    padding:3px 10px;font-size:12px;color:#666;font-weight:600; }
.cpd-desc{ font-size:14px;color:#555;line-height:1.8;margin-bottom:24px; }

/* FAQs */
.cpd-faq-head{ font-size:20px;font-weight:700;color:#1a1a2e;margin:24px 0 12px; }
.cpd-faq-item{
    border:1px solid #e8e8e8;border-radius:8px;margin-bottom:8px;overflow:hidden;
}
.cpd-faq-q{
    display:flex;align-items:center;justify-content:space-between;
    padding:14px 18px;cursor:pointer;font-weight:600;color:#1a1a2e;font-size:14px;
    background:#fff;transition:background .2s;user-select:none;
}
.cpd-faq-q:hover{ background:#fafafa; }
.cpd-faq-q i{ color:#F5A623;transition:transform .3s;font-size:16px; }
.cpd-faq-q.open i{ transform:rotate(45deg); }
.cpd-faq-a{ display:none;padding:0 18px 14px;font-size:13px;color:#666;line-height:1.7; }
.cpd-faq-a.open{ display:block; }

/* Right: sidebar */
.cpd-sidebar{ display:flex;flex-direction:column;gap:20px; }
.cpd-access-card{
    background:#fff;border-radius:16px;border:1px solid #e8e8e8;
    padding:24px;position:sticky;top:80px;
}
.cpd-access-title{ font-size:18px;font-weight:700;color:#1a1a2e;margin:0 0 6px; }
.cpd-access-sub{ font-size:13px;color:#888;margin:0 0 20px;line-height:1.6; }

/* Access granted */
.cpd-access-granted{
    text-align:center;padding:20px 0;
}
.cpd-access-granted i{ font-size:40px;color:#059669;display:block;margin-bottom:10px; }
.cpd-access-granted p{ font-size:13px;color:#666;margin-bottom:16px; }
.cpd-open-btn{
    display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
    padding:13px;border-radius:9px;background:#F5A623;color:#000;border:none;
    font-family:'Rajdhani',sans-serif;font-size:16px;font-weight:700;letter-spacing:.04em;
    cursor:pointer;transition:background .2s;
}
.cpd-open-btn:hover{ background:#d4890e;color:#000; }

/* Access locked */
.cpd-locked-wrap{ text-align:center; }
.cpd-locked-wrap i{ font-size:40px;color:#F5A623;display:block;margin-bottom:10px; }
.cpd-locked-wrap p{ font-size:13px;color:#666;margin-bottom:16px;line-height:1.6; }
.cpd-upgrade-btn{
    display:flex;align-items:center;justify-content:center;gap:8px;width:100%;
    padding:13px;border-radius:9px;
    background:linear-gradient(90deg,#1a56db,#7c3aed);
    color:#fff;border:none;
    font-family:'Rajdhani',sans-serif;font-size:16px;font-weight:700;letter-spacing:.04em;
    cursor:pointer;transition:opacity .2s;
}
.cpd-upgrade-btn:hover{ opacity:.9;color:#fff; }

/* Info rows */
.cpd-info-rows{ margin-top:18px;border-top:1px solid #f0f0f0;padding-top:16px; }
.cpd-info-row{
    display:flex;align-items:center;justify-content:space-between;
    font-size:13px;padding:7px 0;border-bottom:1px solid #f8f8f8;
}
.cpd-info-row:last-child{ border-bottom:none; }
.cpd-info-row .label{ color:#999;font-weight:600; }
.cpd-info-row .value{ color:#333;font-weight:600; }

/* Related */
.cpd-related-card{
    background:#fff;border-radius:16px;border:1px solid #e8e8e8;padding:20px;
}
.cpd-related-title{ font-size:16px;font-weight:700;color:#1a1a2e;margin:0 0 14px; }
.cpd-related-item{
    display:flex;align-items:center;gap:12px;padding:8px 0;
    border-bottom:1px solid #f0f0f0;
}
.cpd-related-item:last-child{ border-bottom:none; }
.cpd-related-thumb{
    width:48px;height:36px;border-radius:6px;overflow:hidden;
    background:linear-gradient(135deg,#06101A,#1a3050);flex-shrink:0;
    display:flex;align-items:center;justify-content:center;color:rgba(245,166,35,.5);
}
.cpd-related-thumb img{ width:100%;height:100%;object-fit:cover; }
.cpd-related-name{ font-size:13px;font-weight:600;color:#1a1a2e;line-height:1.3; }
.cpd-related-tier{ font-size:11px;font-weight:700;margin-top:2px; }
.cpd-related-tier.tier-free{ color:#059669; }
.cpd-related-tier.tier-pro{ color:#1a56db; }
.cpd-related-tier.tier-pro_plus{ color:#7c3aed; }

@media(max-width:768px){
    .cpd-breadcrumb{ padding:10px 16px; }
    .cpd-body{ padding:20px; }
}
</style>

<div class="cpd-wrap">

    {{-- Breadcrumb --}}
    <div class="cpd-breadcrumb">
        <div class="cpd-breadcrumb-inner">
            <a href="{{ route('cp.analyses.index') }}">Analysis Tools</a>
            <i class="las la-angle-right" style="font-size:12px;"></i>
            <span>{{ $analysis->name }}</span>
        </div>
    </div>

    <div class="cpd-main">

        {{-- ══ LEFT — CONTENT ══ --}}
        <div class="cpd-content-card">
            <div class="cpd-thumb">
                @if($analysis->thumbnail)
                    <img src="{{ $analysis->thumbnail_url }}" alt="{{ $analysis->name }}">
                @else
                    <i class="las la-chart-bar cpd-thumb-icon"></i>
                @endif
                <span class="cpd-tier-badge tier-{{ $analysis->plan_tier }}">
                    {{ $analysis->plan_badge['label'] }}
                </span>
            </div>
            <div class="cpd-body">
                <h1 class="cpd-title">{{ $analysis->name }}</h1>

                @if(!empty($analysis->tags))
                <div class="cpd-tags">
                    @foreach($analysis->tags as $tag)
                    <span class="cpd-tag">{{ $tag }}</span>
                    @endforeach
                </div>
                @endif

                @if($analysis->description)
                <div class="cpd-desc">{!! nl2br(e($analysis->description)) !!}</div>
                @elseif($analysis->short_description)
                <div class="cpd-desc">{{ $analysis->short_description }}</div>
                @endif

                {{-- FAQs --}}
                @if(!empty($analysis->faqs))
                <div class="cpd-faq-head">Frequently Asked Questions</div>
                @foreach($analysis->faqs as $i => $faq)
                <div class="cpd-faq-item">
                    <div class="cpd-faq-q" onclick="toggleFaq({{ $i }})">
                        {{ $faq['question'] }}
                        <i class="las la-plus" id="faq-icon-{{ $i }}"></i>
                    </div>
                    <div class="cpd-faq-a" id="faq-a-{{ $i }}">
                        {{ $faq['answer'] }}
                    </div>
                </div>
                @endforeach
                @endif
            </div>
        </div>

        {{-- ══ RIGHT — SIDEBAR ══ --}}
        <div class="cpd-sidebar">

            {{-- Access card --}}
            <div class="cpd-access-card">
                <div class="cpd-access-title">
                    @if($hasAccess) Access This Tool @else Upgrade Required @endif
                </div>
                <div class="cpd-access-sub">
                    @if($hasAccess)
                        You have access to this analysis tool.
                    @else
                        This tool requires a
                        <strong>{{ $analysis->plan_badge['label'] }}</strong> plan.
                    @endif
                </div>

                @if($hasAccess)
                <div class="cpd-access-granted">
                    <i class="las la-check-circle"></i>
                    <p>You have full access to this analysis.</p>
                    @if($analysis->route_name)
                    <a href="{{ route($analysis->route_name) }}" class="cpd-open-btn">
                        <i class="las la-external-link-alt"></i> Open Tool
                    </a>
                    @else
                    <button class="cpd-open-btn">
                        <i class="las la-play-circle"></i> Launch Analysis
                    </button>
                    @endif
                </div>
                @else
                <div class="cpd-locked-wrap">
                    <i class="las la-lock"></i>
                    <p>
                        Subscribe to <strong>{{ $analysis->plan_badge['label'] }}</strong>
                        or higher to unlock this analysis tool.
                    </p>
                    <a href="{{ route('cp.pricing') }}" class="cpd-upgrade-btn">
                        <i class="las la-crown"></i> View Plans & Upgrade
                    </a>
                </div>
                @endif

                {{-- Info --}}
                <div class="cpd-info-rows">
                    <div class="cpd-info-row">
                        <span class="label">Data Source</span>
                        <span class="value">{{ strtoupper($analysis->data_source) }}</span>
                    </div>
                    <div class="cpd-info-row">
                        <span class="label">Timeframe</span>
                        <span class="value">15 MIN</span>
                    </div>
                    <div class="cpd-info-row">
                        <span class="label">Plan Required</span>
                        <span class="value"
                              style="color:{{ $analysis->plan_badge['color'] }};">
                            {{ $analysis->plan_badge['label'] }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Related analyses --}}
            @if($related->count())
            <div class="cpd-related-card">
                <div class="cpd-related-title">Related Analyses</div>
                @foreach($related as $r)
                <a href="{{ route('cp.analyses.detail', $r->slug) }}" class="cpd-related-item">
                    <div class="cpd-related-thumb">
                        @if($r->thumbnail)
                            <img src="{{ $r->thumbnail_url }}" alt="{{ $r->name }}">
                        @else
                            <i class="las la-chart-bar"></i>
                        @endif
                    </div>
                    <div>
                        <div class="cpd-related-name">{{ $r->name }}</div>
                        <div class="cpd-related-tier tier-{{ $r->plan_tier }}">
                            {{ $r->plan_badge['label'] }}
                        </div>
                    </div>
                </a>
                @endforeach
            </div>
            @endif

        </div>
    </div>
</div>

<script>
function toggleFaq(i) {
    var q = document.querySelector('.cpd-faq-q[onclick="toggleFaq(' + i + ')"]');
    var a = document.getElementById('faq-a-' + i);
    var ic = document.getElementById('faq-icon-' + i);
    var open = a.classList.contains('open');
    // Close all
    document.querySelectorAll('.cpd-faq-q').forEach(function(el){ el.classList.remove('open'); });
    document.querySelectorAll('.cpd-faq-a').forEach(function(el){ el.classList.remove('open'); });
    // Open clicked
    if (!open) {
        q.classList.add('open');
        a.classList.add('open');
    }
}
</script>
@endsection