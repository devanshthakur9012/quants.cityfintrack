{{-- FILE: resources/views/themes/{activeTemplate}/course-detail.blade.php --}}
@extends($activeTemplate.'layouts.frontend')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ─── BASE ─────────────────────────────────────────────────────────────── */
.cd { font-family:'Exo 2',sans-serif; background:#f4f6fb; min-height:80vh; color:#2d3a4e; }
.cd *,.cd *::before,.cd *::after { box-sizing:border-box; }
.cd h1,.cd h2,.cd h3,.cd h4,.cd h5 { font-family:'Rajdhani',sans-serif; letter-spacing:.02em; }
.cd a { text-decoration:none; color:inherit; }
@keyframes cdUp { from{opacity:0;transform:translateY(18px)} to{opacity:1;transform:none} }
.cd-anim    { animation:cdUp .5s ease both; }
.cd-anim.d1 { animation-delay:.1s; }
.cd-anim.d2 { animation-delay:.2s; }

/* ─── HERO ──────────────────────────────────────────────────────────────── */
.cd-hero { background:linear-gradient(135deg,#0f1b2d 0%,#1a3050 60%,#0d2137 100%); padding:48px 60px 0; position:relative; overflow:hidden; }
.cd-hero::before { content:''; position:absolute; inset:0; background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.02'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E"); }
.cd-hero-inner { display:flex; gap:48px; align-items:flex-start; max-width:1200px; margin:0 auto; position:relative; }
.cd-hero-left { flex:1; min-width:0; padding-bottom:36px; }

.cd-breadcrumb { display:flex; align-items:center; gap:6px; font-size:12px; color:rgba(255,255,255,.5); margin-bottom:16px; flex-wrap:wrap; }
.cd-breadcrumb a { color:rgba(255,255,255,.6); transition:color .2s; }
.cd-breadcrumb a:hover { color:#f5a623; }

.cd-hero-badges { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
.cd-hbadge { font-size:11px; font-weight:700; padding:4px 12px; border-radius:4px; letter-spacing:.05em; text-transform:uppercase; display:inline-flex; align-items:center; gap:5px; }
.cd-hbadge.ongoing  { background:#e65100; color:#fff; }
.cd-hbadge.upcoming { background:#2e7d32; color:#fff; }
.cd-hbadge.recorded { background:#4527a0; color:#fff; }
.cd-hbadge.cat      { background:rgba(245,166,35,.15); color:#f5a623; border:1px solid rgba(245,166,35,.3); }
.cd-hbadge.featured { background:#f5a623; color:#0f1b2d; }
.cd-hbadge.cert     { background:rgba(255,224,130,.15); color:#ffe082; border:1px solid rgba(255,224,130,.3); }

.cd-hero-title { font-size:clamp(24px,3.5vw,38px); font-weight:700; color:#fff; line-height:1.15; margin:0 0 14px; }
.cd-hero-sub   { font-size:14px; color:rgba(255,255,255,.72); line-height:1.75; margin-bottom:20px; max-width:580px; }

.cd-meta-strip { display:flex; flex-wrap:wrap; gap:18px; margin-bottom:20px; }
.cd-meta-item  { display:flex; align-items:center; gap:6px; font-size:13px; color:rgba(255,255,255,.75); }
.cd-meta-item i { color:#f5a623; font-size:13px; }
.cd-meta-item strong { color:#fff; }

/* ─── BUY CARD ──────────────────────────────────────────────────────────── */
.cd-hero-right { flex-shrink:0; width:340px; }
.cd-buy-card { background:#fff; border-radius:14px 14px 0 0; box-shadow:0 8px 40px rgba(0,0,0,.25); overflow:hidden; position:sticky; top:16px; }
.cd-buy-thumb { width:100%; height:190px; object-fit:cover; display:block; background:#1a3050; }
.cd-buy-body  { padding:20px; }
.cd-buy-price-row { display:flex; align-items:baseline; gap:8px; margin-bottom:6px; }
.cd-buy-price-main { font-size:28px; font-weight:700; color:#0f1b2d; font-family:'Rajdhani',sans-serif; }
.cd-buy-price-free { font-size:24px; font-weight:700; color:#43a047; font-family:'Rajdhani',sans-serif; }
.cd-buy-price-orig { font-size:15px; color:#b0b8c9; text-decoration:line-through; }
.cd-buy-price-disc { font-size:12px; font-weight:700; color:#43a047; background:#e8f5e9; padding:3px 8px; border-radius:4px; }
.cd-buy-cta { width:100%; padding:14px; border-radius:9px; border:none; font-size:15px; font-weight:700; cursor:pointer; font-family:'Exo 2',sans-serif; display:flex; align-items:center; justify-content:center; gap:8px; transition:all .25s; margin-bottom:10px; }
.cd-buy-cta.primary  { background:#f5a623; color:#0f1b2d; }
.cd-buy-cta.primary:hover { background:#d4890e; transform:translateY(-1px); box-shadow:0 6px 20px rgba(245,166,35,.4); }
.cd-buy-cta.enrolled { background:#e8f5e9; color:#2e7d32; cursor:default; }
.cd-buy-cta.login-req { background:#1a56db; color:#fff; }
.cd-buy-cta.login-req:hover { background:#1446b8; }
.cd-buy-note { font-size:11.5px; color:#9aa3b5; text-align:center; margin-bottom:14px; display:flex; align-items:center; justify-content:center; gap:5px; }
.cd-buy-note i { color:#f5a623; }
.cd-buy-includes { border-top:1px solid #f0f2f7; padding-top:14px; }
.cd-buy-includes h6 { font-family:'Rajdhani',sans-serif; font-size:14px; font-weight:700; color:#0f1b2d; margin-bottom:10px; }
.cd-buy-include-item { display:flex; align-items:center; gap:8px; font-size:12.5px; color:#5a6678; padding:4px 0; }
.cd-buy-include-item i { color:#1a56db; width:16px; text-align:center; font-size:12px; }

/* ─── TABS ───────────────────────────────────────────────────────────────── */
.cd-tabs-bar { background:#fff; border-bottom:1px solid #e5e9f2; position:sticky; top:0; z-index:200; box-shadow:0 2px 8px rgba(0,0,0,.05); }
.cd-tabs { display:flex; padding:0 60px; overflow-x:auto; }
.cd-tabs::-webkit-scrollbar { display:none; }
.cd-tab { padding:16px 22px; font-size:13.5px; font-weight:600; color:#8a94a6; cursor:pointer; border:none; background:none; border-bottom:3px solid transparent; margin-bottom:-1px; transition:all .2s; font-family:'Exo 2',sans-serif; white-space:nowrap; }
.cd-tab.active { color:#1a56db; border-bottom-color:#1a56db; }
.cd-tab:hover:not(.active) { color:#333; }

/* ─── LAYOUT ─────────────────────────────────────────────────────────────── */
.cd-main  { max-width:1200px; margin:0 auto; padding:36px 60px 72px; display:flex; gap:36px; }
.cd-left  { flex:1; min-width:0; }
.cd-right { flex-shrink:0; width:340px; }

/* card */
.cd-card { background:#fff; border-radius:12px; border:1px solid #e5e9f2; margin-bottom:24px; overflow:hidden; }
.cd-card-header { padding:18px 22px; border-bottom:1px solid #f0f2f7; display:flex; align-items:center; gap:10px; }
.cd-card-header h3 { font-size:18px; font-weight:700; color:#0f1b2d; margin:0; }
.cd-card-header i  { color:#1a56db; font-size:18px; }
.cd-card-body { padding:20px 22px; }

/* overview stats */
.cd-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:20px; }
.cd-stat   { background:#f8f9fd; border-radius:8px; padding:14px 12px; text-align:center; border:1px solid #eef0f8; }
.cd-stat-val { font-size:22px; font-weight:700; color:#0f1b2d; font-family:'Rajdhani',sans-serif; display:block; }
.cd-stat-lbl { font-size:11px; color:#8a94a6; margin-top:2px; }

/* description */
.cd-description { font-size:14px; color:#5a6678; line-height:1.85; }
.cd-description p { margin-bottom:12px; }

/* certificate banner */
.cd-cert-banner { display:flex; align-items:center; gap:14px; padding:14px 18px; background:linear-gradient(135deg,#fff8e1,#fffde7); border:1px solid #ffe082; border-radius:10px; margin-bottom:20px; }
.cd-cert-icon   { font-size:32px; color:#f57f17; flex-shrink:0; }
.cd-cert-text strong { font-size:15px; font-family:'Rajdhani',sans-serif; color:#0f1b2d; display:block; }
.cd-cert-text span   { font-size:12.5px; color:#7a8499; }

/* ─── CURRICULUM ─────────────────────────────────────────────────────────── */
.cd-cur-summary { display:flex; gap:20px; flex-wrap:wrap; font-size:13px; color:#7a8499; margin-bottom:16px; padding-bottom:14px; border-bottom:1px solid #f0f2f7; }
.cd-cur-summary span { display:flex; align-items:center; gap:5px; }
.cd-cur-summary i    { color:#f5a623; }

/* Section */
.cd-section { border:1px solid #e5e9f2; border-radius:10px; margin-bottom:10px; overflow:hidden; background:#fff; }
.cd-section-header { display:flex; align-items:center; gap:10px; padding:14px 16px; background:#f8f9fd; cursor:pointer; user-select:none; transition:background .2s; }
.cd-section-header:hover { background:#f0f4ff; }
.cd-section-toggle { color:#1a56db; font-size:13px; transition:transform .25s; flex-shrink:0; }
.cd-section-header.open .cd-section-toggle { transform:rotate(90deg); }
.cd-section-title { font-family:'Rajdhani',sans-serif; font-size:15px; font-weight:700; color:#0f1b2d; flex:1; }
.cd-section-pills { display:flex; align-items:center; gap:6px; flex-shrink:0; flex-wrap:wrap; }
.cd-spill { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; white-space:nowrap; }
.cd-spill.lessons  { background:#e8eeff; color:#1a56db; border:1px solid #c7d4fb; }
.cd-spill.duration { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }
.cd-spill.preview  { background:#fff3e0; color:#e65100; border:1px solid #ffcc80; cursor:pointer; transition:all .2s; }
.cd-spill.preview:hover { background:#ffe0b2; transform:scale(1.05); }

/* Section overview video strip */
.cd-section-preview-strip { display:flex; align-items:center; gap:10px; padding:10px 16px; background:linear-gradient(135deg,#fff8f0,#fff3e0); border-bottom:1px solid #ffe0b2; cursor:pointer; transition:background .2s; }
.cd-section-preview-strip:hover { background:linear-gradient(135deg,#ffe0b2,#ffcc80); }
.cd-section-preview-strip .ps-icon { width:36px; height:36px; border-radius:50%; background:#e65100; color:#fff; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
.cd-section-preview-strip .ps-text strong { font-size:12.5px; color:#bf360c; display:block; font-family:'Rajdhani',sans-serif; }
.cd-section-preview-strip .ps-text span   { font-size:11px; color:#8d6e63; }
.cd-section-preview-strip .ps-arrow { margin-left:auto; color:#e65100; font-size:13px; }

/* Section body / Lessons */
.cd-section-body  { display:none; border-top:1px solid #eef0f8; }
.cd-section-body.open { display:block; }

.cd-lesson { display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:1px solid #f5f6fb; transition:background .15s; position:relative; }
.cd-lesson:last-child { border-bottom:none; }
.cd-lesson:hover { background:#fafbff; }

.cd-lesson-icon { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:12px; }
.cd-lesson-icon.youtube { background:#ffebee; color:#e53935; }
.cd-lesson-icon.upload  { background:#e3f2fd; color:#1a56db; }
.cd-lesson-icon.lock    { background:#f5f5f5; color:#bbb; }

.cd-lesson-info  { flex:1; min-width:0; }
.cd-lesson-title { font-size:13px; color:#2d3a4e; line-height:1.4; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cd-lesson-sub   { display:flex; align-items:center; gap:8px; margin-top:3px; flex-wrap:wrap; }

.cd-lesson-dur   { font-size:11px; color:#9aa3b5; }
.cd-lesson-preview-btn { display:inline-flex; align-items:center; gap:4px; font-size:10.5px; font-weight:700; color:#e65100; background:#fff3e0; border:1px solid #ffcc80; padding:2px 8px; border-radius:12px; cursor:pointer; transition:all .2s; }
.cd-lesson-preview-btn:hover { background:#ffe0b2; transform:scale(1.04); }
.cd-lesson-preview-btn i { font-size:9px; }

.cd-lesson-actions { display:flex; align-items:center; gap:6px; flex-shrink:0; }

/* ─── TRAINERS ─────────────────────────────────────────────────────────── */
.cd-trainers-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:14px; }
.cd-trainer-card  { display:flex; align-items:center; gap:12px; padding:14px; border:1px solid #e5e9f2; border-radius:9px; background:#fafbff; }
.cd-trainer-avatar { width:46px; height:46px; border-radius:50%; background:#f5a623; color:#0f1b2d; font-family:'Rajdhani',sans-serif; font-weight:700; font-size:18px; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
.cd-trainer-avatar img { width:100%; height:100%; object-fit:cover; }
.cd-trainer-name  { font-size:14px; font-weight:600; color:#0f1b2d; }
.cd-trainer-role  { font-size:12px; color:#7a8499; margin-top:2px; }

/* ─── FAQs ───────────────────────────────────────────────────────────────── */
.cd-faq { border:1px solid #e5e9f2; border-radius:8px; margin-bottom:8px; overflow:hidden; background:#fff; }
.cd-faq-q { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; cursor:pointer; transition:background .2s; }
.cd-faq-q:hover { background:#f8f9fd; }
.cd-faq-q-text { font-size:14px; font-weight:600; color:#0f1b2d; flex:1; line-height:1.4; }
.cd-faq-icon   { color:#1a56db; font-size:14px; flex-shrink:0; transition:transform .25s; }
.cd-faq.open .cd-faq-icon { transform:rotate(45deg); }
.cd-faq-a { display:none; padding:0 18px 16px; font-size:13.5px; color:#5a6678; line-height:1.75; border-top:1px solid #f0f2f7; padding-top:14px; }
.cd-faq.open .cd-faq-a { display:block; }

/* ─── includes list ─────────────────────────────────────────────────────── */
.cd-includes-list { list-style:none; padding:0; margin:0; }
.cd-includes-list li { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid #f5f6fb; font-size:13px; color:#5a6678; }
.cd-includes-list li:last-child { border-bottom:none; }
.cd-includes-list li i { color:#1a56db; width:18px; text-align:center; }

/* ─── related ────────────────────────────────────────────────────────────── */
.cd-related-card { display:flex; gap:12px; padding:12px; border:1px solid #e5e9f2; border-radius:8px; margin-bottom:10px; transition:box-shadow .2s; background:#fff; }
.cd-related-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.07); }
.cd-related-thumb { width:76px; height:56px; border-radius:6px; object-fit:cover; flex-shrink:0; background:#e5e9f2; }
.cd-related-title { font-size:13px; font-weight:600; color:#0f1b2d; line-height:1.35; margin-bottom:4px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
.cd-related-meta  { font-size:11.5px; color:#7a8499; display:flex; align-items:center; gap:6px; }
.cd-related-price { font-size:13px; color:#f5a623; font-weight:700; margin-top:4px; }

/* ─── PAYMENT OVERLAY ───────────────────────────────────────────────────── */
.cd-pay-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:center; justify-content:center; }
.cd-pay-overlay.show { display:flex; }
.cd-pay-modal { background:#fff; border-radius:16px; padding:36px; max-width:440px; width:90%; text-align:center; box-shadow:0 24px 60px rgba(0,0,0,.25); animation:cdUp .3s ease; }
.cd-pay-modal .cd-pay-icon { font-size:52px; margin-bottom:16px; }
.cd-pay-modal h3 { font-family:'Rajdhani',sans-serif; font-size:24px; color:#0f1b2d; margin-bottom:8px; }
.cd-pay-modal p  { font-size:14px; color:#7a8499; margin-bottom:24px; }
.cd-pay-spinner  { display:none; flex-direction:column; align-items:center; gap:12px; }
.cd-pay-spinner .spinner { width:44px; height:44px; border:4px solid #e5e9f2; border-top-color:#1a56db; border-radius:50%; animation:spin .8s linear infinite; }
@keyframes spin { to{transform:rotate(360deg);} }

/* ─── VIDEO PREVIEW MODAL ────────────────────────────────────────────────── */
.cdv-overlay { display:none; position:fixed; inset:0; z-index:10000; background:rgba(0,0,0,.9); align-items:center; justify-content:center; }
.cdv-overlay.show { display:flex; }
@keyframes cdvIn { from{opacity:0;transform:scale(.95)} to{opacity:1;transform:none} }
.cdv-modal { position:relative; width:90%; max-width:900px; background:#000; border-radius:12px; overflow:hidden; box-shadow:0 32px 80px rgba(0,0,0,.6); animation:cdvIn .3s ease; }
.cdv-modal.fullscreen { width:100%; max-width:100%; height:100vh; border-radius:0; }
.cdv-header { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; background:#0f1b2d; }
.cdv-header-title { font-family:'Rajdhani',sans-serif; font-size:16px; font-weight:700; color:#fff; display:flex; align-items:center; gap:8px; max-width:calc(100% - 80px); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.cdv-header-title .cdv-badge { font-size:10px; background:#e65100; color:#fff; padding:2px 8px; border-radius:3px; letter-spacing:.05em; flex-shrink:0; }
.cdv-controls { display:flex; align-items:center; gap:6px; }
.cdv-btn { width:32px; height:32px; border-radius:6px; border:none; background:rgba(255,255,255,.1); color:#fff; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .2s; }
.cdv-btn:hover { background:rgba(255,255,255,.2); }
.cdv-btn.close { background:rgba(229,57,53,.2); color:#ef9a9a; }
.cdv-btn.close:hover { background:#e53935; color:#fff; }
.cdv-video-wrap { position:relative; padding-bottom:56.25%; height:0; background:#000; }
.cdv-modal.fullscreen .cdv-video-wrap { padding-bottom:0; height:calc(100vh - 56px); }
.cdv-video-wrap iframe { position:absolute; top:0; left:0; width:100%; height:100%; border:none; }
.cdv-label { padding:10px 18px 14px; background:#0f1b2d; font-size:12px; color:rgba(255,255,255,.45); display:flex; align-items:center; gap:6px; }
.cdv-label i { color:#f5a623; }

/* ─── RESPONSIVE ─────────────────────────────────────────────────────────── */
@media(max-width:1000px) {
    .cd-hero{padding:32px 20px 0;}
    .cd-hero-inner{flex-direction:column;}
    .cd-hero-right{width:100%;}
    .cd-buy-card{border-radius:14px;margin-bottom:24px;}
    .cd-tabs{padding:0 16px;}
    .cd-main{flex-direction:column;padding:20px 16px 56px;}
    .cd-right{width:100%;}
    .cd-stats{grid-template-columns:repeat(2,1fr);}
}
@media(max-width:600px) {
    .cd-stats{grid-template-columns:1fr 1fr;}
    .cd-trainers-grid{grid-template-columns:1fr;}
    .cd-section-pills .cd-spill.duration { display:none; }
}
</style>

<div class="cd">

{{-- ═══════════════════════ HERO ═══════════════════════ --}}
<div class="cd-hero">
    <div class="cd-hero-inner">

        {{-- LEFT --}}
        <div class="cd-hero-left cd-anim">

            <div class="cd-breadcrumb">
                <a href="{{ url('/') }}">Home</a>
                <i class="fas fa-chevron-right"></i>
                <a href="{{ route('courses') }}">Courses</a>
                @if($course->category)
                <i class="fas fa-chevron-right"></i>
                <a href="{{ route('courses', ['category' => $course->category->id]) }}">{{ $course->category->name }}</a>
                @endif
                <i class="fas fa-chevron-right"></i>
                <span style="color:rgba(255,255,255,.35);">{{ Str::limit($course->title, 40) }}</span>
            </div>

            <div class="cd-hero-badges">
                @if($course->is_featured)
                    <span class="cd-hbadge featured"><i class="fas fa-star"></i> Featured</span>
                @endif
                <span class="cd-hbadge {{ $course->status }}">
                    @if($course->status==='ongoing')<i class="fas fa-circle" style="font-size:8px;"></i> Live
                    @elseif($course->status==='upcoming')<i class="fas fa-clock"></i> Upcoming
                    @else<i class="fas fa-video"></i> Recorded
                    @endif
                </span>
                @if($course->category)
                    <span class="cd-hbadge cat">{{ $course->category->name }}</span>
                @endif
                @if($course->has_certificate)
                    <span class="cd-hbadge cert"><i class="fas fa-certificate"></i> Certificate Provided</span>
                @endif
            </div>

            <h1 class="cd-hero-title">{{ $course->title }}</h1>

            @if($course->short_description)
            <p class="cd-hero-sub">{{ $course->short_description }}</p>
            @endif

            <div class="cd-meta-strip">
                <div class="cd-meta-item"><i class="fas fa-signal"></i> <span>{{ ucfirst($course->level) }}</span></div>
                <div class="cd-meta-item"><i class="fas fa-globe"></i> <span>{{ ucfirst($course->mode) }}</span></div>
                <div class="cd-meta-item"><i class="fas fa-language"></i> <span>{{ ucfirst($course->language) }}</span></div>
                @if($course->sections->count())
                <div class="cd-meta-item"><i class="fas fa-layer-group"></i> <strong>{{ $course->sections->count() }}</strong>&nbsp;Sections</div>
                @endif
                @if($totalLessons)
                <div class="cd-meta-item"><i class="fas fa-play-circle"></i> <strong>{{ $totalLessons }}</strong>&nbsp;Lessons</div>
                @endif
                @if($totalDuration !== '0m')
                <div class="cd-meta-item"><i class="fas fa-clock"></i> <span>{{ $totalDuration }}</span></div>
                @endif
                <div class="cd-meta-item"><i class="fas fa-users"></i> <strong>{{ number_format($course->total_enrolled) }}</strong>&nbsp;Enrolled</div>
            </div>

            @if($course->trainers->count())
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:4px;">
                @foreach($course->trainers as $trainer)
                @php
                    $tName   = trim($trainer->firstname . ' ' . $trainer->lastname);
                    $tRole   = $trainer->employeeProfile->designation ?? 'Trainer';
                    $tAvatar = $trainer->profile_pic ? asset(getFilePath('userProfile') . '/' . $trainer->profile_pic) : null;
                    $tInit   = strtoupper(substr($trainer->firstname, 0, 1));
                @endphp
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:8px;">
                    <div style="width:36px;height:36px;border-radius:50%;background:#f5a623;color:#0f1b2d;font-weight:700;font-size:15px;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                        @if($tAvatar)<img src="{{ $tAvatar }}" style="width:100%;height:100%;object-fit:cover;" alt="{{ $tName }}">@else{{ $tInit }}@endif
                    </div>
                    <div>
                        <div style="font-size:13.5px;color:#fff;font-weight:600;">{{ $tName }}</div>
                        <div style="font-size:11.5px;color:rgba(255,255,255,.5);">{{ $tRole }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- BUY CARD --}}
        <div class="cd-hero-right cd-anim d2">
            <div class="cd-buy-card">
                <img class="cd-buy-thumb" src="{{ $course->thumbnail_url }}" alt="{{ $course->title }}"
                     onerror="this.src='https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=600'">
                <div class="cd-buy-body">

                    <div class="cd-buy-price-row">
                        @if($course->type === 'free')
                            <span class="cd-buy-price-free">FREE</span>
                        @else
                            <span class="cd-buy-price-main">₹{{ number_format($course->price) }}</span>
                            @if($course->mrp && $course->mrp > $course->price)
                            <span class="cd-buy-price-orig">₹{{ number_format($course->mrp) }}</span>
                            @endif
                            @if($course->discount_label)
                            <span class="cd-buy-price-disc">{{ $course->discount_label }}</span>
                            @endif
                        @endif
                    </div>

                    @if($isEnrolled)
                        <button class="cd-buy-cta enrolled" disabled>
                            <i class="fas fa-check-circle"></i> You're Enrolled
                        </button>
                        <p class="cd-buy-note"><i class="fas fa-lock-open"></i> You have full access</p>

                    @elseif(!$user)
                        <a href="{{ route('user.login') }}?redirect={{ urlencode(route('courses.detail', $course->slug)) }}"
                           class="cd-buy-cta login-req">
                            <i class="fas fa-sign-in-alt"></i>
                            Login to {{ $course->type === 'free' ? 'Enroll Free' : 'Buy Now' }}
                        </a>
                        <p class="cd-buy-note"><i class="fas fa-info-circle"></i> Login required to access this course</p>

                    @elseif($course->type === 'free')
                        <button class="cd-buy-cta primary" id="enrollFreeBtn" onclick="enrollFree({{ $course->id }})">
                            <i class="fas fa-graduation-cap"></i> Enroll for Free
                        </button>
                        <p class="cd-buy-note"><i class="fas fa-gift"></i> 100% Free — No payment needed</p>

                    @elseif($gateway)
                        <button class="cd-buy-cta primary" id="buyNowBtn" onclick="initPayment({{ $course->id }})">
                            <i class="fas fa-bolt"></i> Buy Now
                        </button>
                        <p class="cd-buy-note"><i class="fas fa-shield-alt"></i> Secure payment via Razorpay</p>

                    @else
                        <button class="cd-buy-cta primary" disabled style="opacity:.6;cursor:not-allowed;">
                            <i class="fas fa-exclamation-circle"></i> Payment Unavailable
                        </button>
                        <p class="cd-buy-note" style="color:#e53935;"><i class="fas fa-info-circle"></i> Contact us to enroll</p>
                    @endif

                    <div class="cd-buy-includes">
                        <h6>This Course Includes:</h6>
                        @if($course->sections->count())
                        <div class="cd-buy-include-item"><i class="fas fa-layer-group"></i> {{ $course->sections->count() }} sections</div>
                        @endif
                        @if($totalLessons)
                        <div class="cd-buy-include-item"><i class="fas fa-play-circle"></i> {{ $totalLessons }} video lessons</div>
                        @endif
                        @if($totalDuration !== '0m')
                        <div class="cd-buy-include-item"><i class="fas fa-clock"></i> {{ $totalDuration }} total content</div>
                        @endif
                        <div class="cd-buy-include-item"><i class="fas fa-infinity"></i> Lifetime access</div>
                        <div class="cd-buy-include-item"><i class="fas fa-mobile-alt"></i> Mobile + Desktop</div>
                        @if($course->has_certificate)
                        <div class="cd-buy-include-item"><i class="fas fa-certificate"></i> Certificate of completion</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- ═══════════════════ TABS BAR ═══════════════════ --}}
<div class="cd-tabs-bar">
    <div class="cd-tabs">
        <button class="cd-tab active" onclick="cdScrollTo('overview')">Overview</button>
        @if($course->sections->count())
        <button class="cd-tab" onclick="cdScrollTo('curriculum')">Curriculum</button>
        @endif
        @if($course->trainers->count())
        <button class="cd-tab" onclick="cdScrollTo('trainers')">Trainers</button>
        @endif
        @if($course->faqs->count())
        <button class="cd-tab" onclick="cdScrollTo('faqs')">FAQs</button>
        @endif
        @if($relatedCourses->count())
        <button class="cd-tab" onclick="cdScrollTo('related')">Related</button>
        @endif
    </div>
</div>

{{-- ═══════════════════ MAIN CONTENT ═══════════════════ --}}
<div class="cd-main">
    <div class="cd-left">

        {{-- ── OVERVIEW ── --}}
        <div id="overview">
            <div class="cd-card cd-anim">
                <div class="cd-card-header"><i class="fas fa-chart-bar"></i><h3>Course Overview</h3></div>
                <div class="cd-card-body">

                    <div class="cd-stats">
                        @if($course->sections->count())
                        <div class="cd-stat">
                            <span class="cd-stat-val">{{ $course->sections->count() }}</span>
                            <div class="cd-stat-lbl">Sections</div>
                        </div>
                        @endif
                        <div class="cd-stat">
                            <span class="cd-stat-val">{{ $totalLessons ?: '—' }}</span>
                            <div class="cd-stat-lbl">Total Lessons</div>
                        </div>
                        <div class="cd-stat">
                            <span class="cd-stat-val">{{ $totalDuration !== '0m' ? $totalDuration : '—' }}</span>
                            <div class="cd-stat-lbl">Duration</div>
                        </div>
                        <div class="cd-stat">
                            <span class="cd-stat-val">{{ number_format($course->total_enrolled) }}</span>
                            <div class="cd-stat-lbl">Enrolled</div>
                        </div>
                    </div>

                    @if($course->has_certificate)
                    <div class="cd-cert-banner">
                        <i class="fas fa-certificate cd-cert-icon"></i>
                        <div class="cd-cert-text">
                            <strong>Certificate of Completion Available</strong>
                            <span>Complete all lessons and receive an official certificate from CityQuants.</span>
                        </div>
                    </div>
                    @endif

                    @if($course->preview_embed_id)
                    <div style="position:relative;padding-bottom:56.25%;height:0;overflow:hidden;border-radius:10px;margin-bottom:20px;">
                        <iframe src="https://www.youtube.com/embed/{{ $course->preview_embed_id }}?rel=0"
                                style="position:absolute;top:0;left:0;width:100%;height:100%;border:0;"
                                allowfullscreen loading="lazy" title="Course Preview"></iframe>
                    </div>
                    @endif

                    @if($course->description)
                    <div class="cd-description">{!! $course->description !!}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── CURRICULUM ── --}}
        @if($course->sections->count())
        <div id="curriculum">
            <div class="cd-card cd-anim">
                <div class="cd-card-header"><i class="fas fa-layer-group"></i><h3>Course Curriculum</h3></div>
                <div class="cd-card-body">

                    <div class="cd-cur-summary">
                        <span><i class="fas fa-layer-group"></i> {{ $course->sections->count() }} Sections</span>
                        @if($totalLessons)
                        <span><i class="fas fa-play-circle"></i> {{ $totalLessons }} Lessons</span>
                        @endif
                        @if($totalDuration !== '0m')
                        <span><i class="fas fa-clock"></i> {{ $totalDuration }} Total Duration</span>
                        @endif
                    </div>

                    @foreach($course->sections as $sIdx => $section)
                    @php
                        $sLessons  = $section->lessons->count();
                        $sDuration = $section->total_duration;   // from accessor
                        $sDurSecs  = $section->total_duration_seconds;
                        $sHasPreview = $section->has_preview && $section->preview_video_type === 'youtube' && $section->preview_embed_id;
                    @endphp
                    <div class="cd-section">

                        {{-- Section header --}}
                        <div class="cd-section-header {{ $sIdx === 0 ? 'open' : '' }}" onclick="cdToggleSection(this)">
                            <i class="fas fa-chevron-right cd-section-toggle"></i>
                            <span class="cd-section-title">{{ $section->title }}</span>
                            <div class="cd-section-pills" onclick="event.stopPropagation()">
                                @if($sLessons)
                                <span class="cd-spill lessons"><i class="fas fa-play-circle"></i> {{ $sLessons }} {{ Str::plural('lesson', $sLessons) }}</span>
                                @endif
                                @if($sDurSecs > 0)
                                <span class="cd-spill duration"><i class="fas fa-clock"></i> {{ $sDuration }}</span>
                                @endif
                                @if($sHasPreview)
                                <span class="cd-spill preview" onclick="openPreviewVideo('{{ $section->preview_embed_id }}', '{{ addslashes($section->title) }}', 'Section Overview')">
                                    <i class="fas fa-play"></i> Overview
                                </span>
                                @endif
                            </div>
                        </div>

                        {{-- Section overview video strip (only if YouTube preview exists) --}}
                        @if($sHasPreview)
                        <div class="cd-section-preview-strip" onclick="openPreviewVideo('{{ $section->preview_embed_id }}', '{{ addslashes($section->title) }}', 'Section Overview')">
                            <div class="ps-icon"><i class="fab fa-youtube"></i></div>
                            <div class="ps-text">
                                <strong>Watch Section Overview</strong>
                                <span>Free preview — see what's covered in this section</span>
                            </div>
                            <i class="fas fa-external-link-alt ps-arrow"></i>
                        </div>
                        @endif

                        {{-- Lessons --}}
                        <div class="cd-section-body {{ $sIdx === 0 ? 'open' : '' }}">
                            @foreach($section->lessons as $lesson)
                            @php
                                $lHasPreview = $lesson->has_preview && $lesson->preview_video_type === 'youtube' && $lesson->preview_embed_id;
                            @endphp
                            <div class="cd-lesson">

                                {{-- Lesson icon --}}
                                @if($isEnrolled)
                                    <div class="cd-lesson-icon {{ $lesson->video_type }}">
                                        <i class="{{ $lesson->video_type === 'youtube' ? 'fab fa-youtube' : 'fas fa-play' }}"></i>
                                    </div>
                                @else
                                    <div class="cd-lesson-icon lock"><i class="fas fa-lock"></i></div>
                                @endif

                                {{-- Lesson info --}}
                                <div class="cd-lesson-info">
                                    <div class="cd-lesson-title">{{ $lesson->title }}</div>
                                    <div class="cd-lesson-sub">
                                        @if($lesson->duration_seconds)
                                        <span class="cd-lesson-dur"><i class="fas fa-clock" style="font-size:9px;margin-right:2px;"></i> {{ $lesson->formatted_duration }}</span>
                                        @endif
                                        @if($lHasPreview)
                                        <span class="cd-lesson-preview-btn" onclick="openPreviewVideo('{{ $lesson->preview_embed_id }}', '{{ addslashes($lesson->title) }}', 'Lesson Overview')">
                                            <i class="fas fa-play-circle"></i> Free Preview
                                        </span>
                                        @endif
                                    </div>
                                </div>

                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endforeach

                </div>
            </div>
        </div>
        @endif

        {{-- ── TRAINERS ── --}}
        @if($course->trainers->count())
        <div id="trainers">
            <div class="cd-card cd-anim">
                <div class="cd-card-header"><i class="fas fa-chalkboard-teacher"></i><h3>Meet the Trainers</h3></div>
                <div class="cd-card-body">
                    <div class="cd-trainers-grid">
                        @foreach($course->trainers as $trainer)
                        @php
                            $tName   = trim($trainer->firstname . ' ' . $trainer->lastname);
                            $tRole   = $trainer->employeeProfile->designation ?? 'Trainer';
                            $tAvatar = $trainer->profile_pic ? asset(getFilePath('userProfile') . '/' . $trainer->profile_pic) : null;
                            $tInit   = strtoupper(substr($trainer->firstname, 0, 1));
                        @endphp
                        <div class="cd-trainer-card">
                            <div class="cd-trainer-avatar">
                                @if($tAvatar)<img src="{{ $tAvatar }}" alt="{{ $tName }}">@else{{ $tInit }}@endif
                            </div>
                            <div>
                                <div class="cd-trainer-name">{{ $tName }}</div>
                                <div class="cd-trainer-role">{{ $tRole }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ── FAQs ── --}}
        @if($course->faqs->count())
        <div id="faqs">
            <div class="cd-card cd-anim">
                <div class="cd-card-header"><i class="fas fa-question-circle"></i><h3>Frequently Asked Questions</h3></div>
                <div class="cd-card-body" style="padding:12px 10px;">
                    @foreach($course->faqs as $faq)
                    <div class="cd-faq">
                        <div class="cd-faq-q" onclick="cdToggleFaq(this.parentElement)">
                            <span class="cd-faq-q-text">{{ $faq->question }}</span>
                            <i class="fas fa-plus cd-faq-icon"></i>
                        </div>
                        <div class="cd-faq-a">{{ $faq->answer }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

    </div>{{-- /.cd-left --}}

    {{-- ── RIGHT SIDEBAR ── --}}
    <div class="cd-right">

        <div class="cd-card" style="margin-bottom:20px;">
            <div class="cd-card-header"><i class="fas fa-check-circle"></i><h3>What You Get</h3></div>
            <div class="cd-card-body" style="padding:14px 18px;">
                <ul class="cd-includes-list">
                    @if($course->sections->count())
                    <li><i class="fas fa-layer-group"></i> {{ $course->sections->count() }} Course Sections</li>
                    @endif
                    @if($totalLessons)
                    <li><i class="fas fa-video"></i> {{ $totalLessons }} Video Lessons</li>
                    @endif
                    @if($totalDuration !== '0m')
                    <li><i class="fas fa-clock"></i> {{ $totalDuration }} Total Content</li>
                    @endif
                    <li><i class="fas fa-infinity"></i> Lifetime Access</li>
                    <li><i class="fas fa-mobile-alt"></i> Mobile + Desktop</li>
                    <li><i class="fas fa-file-download"></i> Downloadable Resources</li>
                    @if($course->has_certificate)
                    <li><i class="fas fa-certificate" style="color:#f57f17;"></i>
                        <span style="color:#f57f17;font-weight:600;">Certificate of Completion</span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        @if($relatedCourses->count())
        <div id="related">
            <h5 style="font-family:'Rajdhani',sans-serif;font-size:17px;font-weight:700;color:#0f1b2d;margin-bottom:14px;">
                <i class="fas fa-book-open" style="color:#1a56db;margin-right:6px;"></i> Related Courses
            </h5>
            @foreach($relatedCourses as $rc)
            @php
                $rcSecs = $rc->lessons->sum('duration_seconds');
                $rcH = floor($rcSecs / 3600); $rcM = floor(($rcSecs % 3600) / 60);
                $rcDur = $rcSecs > 0 ? ($rcH > 0 ? "{$rcH}h {$rcM}m" : "{$rcM}m") : null;
            @endphp
            <a href="{{ route('courses.detail', $rc->slug) }}" class="cd-related-card">
                <img class="cd-related-thumb" src="{{ $rc->thumbnail_url }}" alt="{{ $rc->title }}"
                     onerror="this.src='https://img.freepik.com/free-vector/online-trading-concept-illustration_114360-4766.jpg?w=400'">
                <div>
                    <div class="cd-related-title">{{ $rc->title }}</div>
                    <div class="cd-related-meta">
                        <span>{{ ucfirst($rc->level) }}</span>
                        @if($rc->lessons->count())
                            <span>&bull; {{ $rc->lessons->count() }} lessons</span>
                        @endif
                        @if($rcDur)
                            <span>&bull; {{ $rcDur }}</span>
                        @endif
                        @if($rc->has_certificate)
                            <span style="color:#f57f17;"><i class="fas fa-certificate"></i></span>
                        @endif
                    </div>
                    <div class="cd-related-price">
                        @if($rc->type==='free') <span style="color:#43a047;">FREE</span>
                        @else ₹{{ number_format($rc->price) }}
                            @if($rc->discount_label) <span style="font-size:11px;color:#43a047;">{{ $rc->discount_label }}</span> @endif
                        @endif
                    </div>
                </div>
            </a>
            @endforeach
        </div>
        @endif

    </div>
</div>

</div>{{-- /.cd --}}

{{-- ═══════════════════ VIDEO PREVIEW MODAL ═══════════════════ --}}
<div class="cdv-overlay" id="cdvOverlay" onclick="cdvClickOutside(event)">
    <div class="cdv-modal" id="cdvModal">
        <div class="cdv-header">
            <div class="cdv-header-title">
                <span class="cdv-badge" id="cdvBadge">Overview</span>
                <span id="cdvTitle">Video Preview</span>
            </div>
            <div class="cdv-controls">
                <button class="cdv-btn" onclick="cdvToggleFullscreen()" title="Toggle fullscreen">
                    <i class="fas fa-expand" id="cdvFsIcon"></i>
                </button>
                <button class="cdv-btn close" onclick="cdvClose()" title="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="cdv-video-wrap" id="cdvVideoWrap">
            <iframe id="cdvIframe" src="" allowfullscreen allow="autoplay; encrypted-media" title="Video Preview"></iframe>
        </div>
        <div class="cdv-label">
            <i class="fas fa-lock-open"></i>
            Free preview — available to everyone before purchase
        </div>
    </div>
</div>

{{-- ═══════════════════ PAYMENT OVERLAY ═══════════════════ --}}
<div class="cd-pay-overlay" id="payOverlay">
    <div class="cd-pay-modal">
        <div id="payContent">
            <div class="cd-pay-icon">💳</div>
            <h3>Processing Payment</h3>
            <p>Connecting to payment gateway…</p>
        </div>
        <div class="cd-pay-spinner" id="paySpinner">
            <div class="spinner"></div>
            <p style="color:#7a8499;font-size:14px;margin:0;">Verifying your payment…</p>
        </div>
    </div>
</div>

@if($gateway)
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endif

<script>
// ── Tab scroll ──────────────────────────────────────────────────────────────
function cdScrollTo(id) {
    var el = document.getElementById(id);
    if (el) window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 80, behavior:'smooth' });
}

// ── Curriculum section toggle ───────────────────────────────────────────────
function cdToggleSection(header) {
    header.classList.toggle('open');
    var body = header.parentElement.querySelector('.cd-section-body');
    if (body) body.classList.toggle('open');
}

// ── FAQ toggle ──────────────────────────────────────────────────────────────
function cdToggleFaq(el) {
    el.classList.toggle('open');
}

// ── Video Preview Modal ──────────────────────────────────────────────────────
var cdvIsFullscreen = false;

function openPreviewVideo(embedId, title, badge) {
    var overlay = document.getElementById('cdvOverlay');
    var modal   = document.getElementById('cdvModal');
    var iframe  = document.getElementById('cdvIframe');
    var titleEl = document.getElementById('cdvTitle');
    var badgeEl = document.getElementById('cdvBadge');

    titleEl.textContent = title;
    badgeEl.textContent = badge || 'Overview';
    iframe.src = 'https://www.youtube.com/embed/' + embedId + '?autoplay=1&rel=0&modestbranding=1';

    cdvIsFullscreen = false;
    modal.classList.remove('fullscreen');
    document.getElementById('cdvFsIcon').className = 'fas fa-expand';

    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function cdvClose() {
    var overlay = document.getElementById('cdvOverlay');
    var iframe  = document.getElementById('cdvIframe');

    overlay.classList.remove('show');
    iframe.src = '';  // stop video
    document.body.style.overflow = '';

    if (cdvIsFullscreen) {
        document.getElementById('cdvModal').classList.remove('fullscreen');
        document.getElementById('cdvFsIcon').className = 'fas fa-expand';
        cdvIsFullscreen = false;
    }
}

function cdvToggleFullscreen() {
    var modal  = document.getElementById('cdvModal');
    var icon   = document.getElementById('cdvFsIcon');
    cdvIsFullscreen = !cdvIsFullscreen;
    modal.classList.toggle('fullscreen', cdvIsFullscreen);
    icon.className = cdvIsFullscreen ? 'fas fa-compress' : 'fas fa-expand';
}

function cdvClickOutside(e) {
    if (e.target === document.getElementById('cdvOverlay')) {
        cdvClose();
    }
}

// Keyboard: Escape closes, F toggles fullscreen
document.addEventListener('keydown', function(e) {
    var overlay = document.getElementById('cdvOverlay');
    if (!overlay.classList.contains('show')) return;
    if (e.key === 'Escape') cdvClose();
    if (e.key === 'f' || e.key === 'F') cdvToggleFullscreen();
});

// ── Free enroll ─────────────────────────────────────────────────────────────
function enrollFree(courseId) {
    var btn = document.getElementById('enrollFreeBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enrolling…';
    fetch('/courses/' + courseId + '/pay', {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}' },
        body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showPayResult('success', '🎉 Enrolled!', data.message, data.redirect);
        } else if (data.redirect) {
            window.location.href = data.redirect;
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-graduation-cap"></i> Enroll for Free';
            alert(data.message || 'Something went wrong.');
        }
    })
    .catch(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-graduation-cap"></i> Enroll for Free'; });
}

// ── Razorpay ────────────────────────────────────────────────────────────────
function initPayment(courseId) {
    var btn = document.getElementById('buyNowBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Please wait…'; }

    fetch('/courses/' + courseId + '/pay', {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}' },
        body: JSON.stringify({})
    })
    .then(function(r) {
        if (r.status === 401) { return r.json().then(function(d) { window.location.href = d.redirect || '/login'; }); }
        return r.json();
    })
    .then(function(data) {
        if (!data || !data.success) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; }
            alert(data ? (data.message || 'Could not initiate payment.') : 'Error occurred.');
            return;
        }
        var rzp = new Razorpay({
            key: data.key, amount: data.amount, currency: data.currency,
            name: 'CityQuants', description: data.course_name, order_id: data.order_id,
            prefill: { name: data.user_name, email: data.user_email, contact: data.user_phone },
            theme: { color: '#f5a623' },
            modal: { ondismiss: function() { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; } } },
            handler: function(response) {
                document.getElementById('payContent').style.display = 'none';
                document.getElementById('paySpinner').style.display = 'flex';
                document.getElementById('payOverlay').classList.add('show');

                fetch(data.callback_url, {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}' },
                    body: JSON.stringify({
                        razorpay_order_id:   response.razorpay_order_id,
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature:  response.razorpay_signature,
                        our_order_id:        data.our_order_id,
                    })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    document.getElementById('paySpinner').style.display = 'none';
                    document.getElementById('payContent').style.display = 'block';
                    showPayResult(res.success ? 'success' : 'error', res.success ? '🎉 Payment Successful!' : '❌ Verification Failed', res.message, res.redirect);
                })
                .catch(function() {
                    document.getElementById('paySpinner').style.display = 'none';
                    document.getElementById('payContent').style.display = 'block';
                    showPayResult('error', 'Error', 'Could not verify payment. Please contact support.');
                });
            }
        });
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; }
        rzp.on('payment.failed', function(resp) {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; }
            showPayResult('error', '❌ Payment Failed', 'Reason: ' + (resp.error.description || 'Unknown error'));
        });
        rzp.open();
    })
    .catch(function() { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-bolt"></i> Buy Now'; } });
}

function showPayResult(type, title, message, redirectUrl) {
    var payContent = document.getElementById('payContent');
    var icon       = type === 'success' ? '✅' : '❌';
    var btnHtml    = redirectUrl
        ? '<a href="' + redirectUrl + '" style="display:inline-block;margin-top:12px;padding:12px 30px;background:#f5a623;color:#0f1b2d;font-weight:700;border-radius:8px;font-family:\'Exo 2\',sans-serif;font-size:14px;">Go to Course</a>'
        : '<button onclick="document.getElementById(\'payOverlay\').classList.remove(\'show\')" style="display:inline-block;margin-top:12px;padding:12px 30px;background:#e5e9f2;color:#2d3a4e;font-weight:700;border-radius:8px;border:none;cursor:pointer;font-family:\'Exo 2\',sans-serif;font-size:14px;">Close</button>';

    payContent.innerHTML = '<div class="cd-pay-icon">' + icon + '</div><h3>' + title + '</h3><p>' + message + '</p>' + btnHtml;
    document.getElementById('payOverlay').classList.add('show');
    if (redirectUrl) setTimeout(function() { window.location.href = redirectUrl; }, 2500);
}

document.getElementById('payOverlay').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('show');
});
</script>

@endsection