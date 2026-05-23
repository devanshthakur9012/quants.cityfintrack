{{-- FILE: resources/views/themes/{activeTemplate}/user/dashboard.blade.php --}}
@extends($activeTemplate.'layouts.frontend')

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ─── BASE ─────────────────────────────────────────────────────────────── */
.ud { font-family:'Exo 2',sans-serif; background:#f4f6fb; min-height:80vh; }
.ud *,.ud *::before,.ud *::after { box-sizing:border-box; }
.ud h1,.ud h2,.ud h3,.ud h4,.ud h5 { font-family:'Rajdhani',sans-serif; letter-spacing:.02em; }
.ud a { text-decoration:none; color:inherit; }
@keyframes udUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.ud-anim    { animation:udUp .45s ease both; }
.ud-anim.d1 { animation-delay:.05s; }
.ud-anim.d2 { animation-delay:.1s; }
.ud-anim.d3 { animation-delay:.15s; }

/* ─── LAYOUT ────────────────────────────────────────────────────────────── */
.ud-layout {
    max-width: 1200px; margin: 0 auto;
    padding: 32px 24px 72px;
    display: flex; gap: 28px; align-items: flex-start;
}
.ud-sidebar { flex-shrink: 0; width: 260px; position: sticky; top: 20px; }
.ud-main    { flex: 1; min-width: 0; }

/* ─── SIDEBAR ───────────────────────────────────────────────────────────── */
.ud-profile-card {
    background: linear-gradient(135deg, #0f1b2d, #1a3050);
    border-radius: 14px; padding: 28px 20px 20px;
    text-align: center; margin-bottom: 14px;
    border: 1px solid rgba(245,166,35,.2);
    position: relative; overflow: hidden;
}
.ud-profile-card::before {
    content:''; position:absolute; inset:0;
    background: radial-gradient(ellipse at 60% 0%, rgba(245,166,35,.12), transparent 60%);
    pointer-events:none;
}
.ud-avatar-wrap {
    width: 76px; height: 76px; border-radius: 50%;
    margin: 0 auto 14px; position: relative;
    border: 3px solid #f5a623; overflow: hidden;
    background: #f5a623;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Rajdhani',sans-serif; font-size: 28px;
    font-weight: 700; color: #0f1b2d;
}
.ud-avatar-wrap img { width:100%; height:100%; object-fit:cover; }
.ud-profile-name  { font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 3px; }
.ud-profile-email { font-size: 12px; color: rgba(255,255,255,.45); margin-bottom: 16px; word-break:break-all; }
.ud-profile-since { font-size: 11px; color: rgba(255,255,255,.35); }

/* sidebar nav */
.ud-sidebar-nav { background: #fff; border-radius: 12px; overflow: hidden; border: 1px solid #e5e9f2; margin-bottom: 14px; }
.ud-nav-item {
    display: flex; align-items: center; gap: 11px;
    padding: 13px 18px; font-size: 14px; font-weight: 500; color: #5a6678;
    border-bottom: 1px solid #f0f2f7; transition: all .2s; cursor: pointer;
}
.ud-nav-item:last-child { border-bottom: none; }
.ud-nav-item:hover, .ud-nav-item.active { background: #f0f5ff; color: #1a56db; }
.ud-nav-item i { width: 20px; text-align: center; font-size: 16px; flex-shrink: 0; }
.ud-nav-item.danger       { color: #e53935; }
.ud-nav-item.danger:hover { background: #fff5f5; color: #b71c1c; }

/* sidebar stat mini */
.ud-sidebar-stats { background: #fff; border-radius: 12px; border: 1px solid #e5e9f2; padding: 16px 18px; }
.ud-sidebar-stats h6 { font-size: 12px; font-weight: 700; color: #8a94a6; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 12px; }
.ud-mini-stat { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-bottom: 1px solid #f5f6fb; }
.ud-mini-stat:last-child { border-bottom: none; }
.ud-mini-stat-label { font-size: 13px; color: #5a6678; display: flex; align-items: center; gap: 7px; }
.ud-mini-stat-label i { color: #f5a623; width: 16px; text-align: center; }
.ud-mini-stat-val { font-size: 15px; font-weight: 700; color: #0f1b2d; font-family: 'Rajdhani',sans-serif; }

/* ─── STAT CARDS ROW ────────────────────────────────────────────────────── */
.ud-stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 24px; }
.ud-stat-card {
    background: #fff; border-radius: 12px; border: 1px solid #e5e9f2;
    padding: 18px 18px 16px; display: flex; align-items: center; gap: 14px;
    transition: box-shadow .2s;
}
.ud-stat-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.07); }
.ud-stat-icon {
    width: 46px; height: 46px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.ud-stat-icon.blue   { background: #dbeafe; color: #1a56db; }
.ud-stat-icon.gold   { background: #fff8e1; color: #f57f17; }
.ud-stat-icon.green  { background: #e8f5e9; color: #2e7d32; }
.ud-stat-icon.purple { background: #f3e5f5; color: #7b1fa2; }
.ud-stat-val  { font-size: 26px; font-weight: 700; color: #0f1b2d; font-family: 'Rajdhani',sans-serif; line-height: 1; }
.ud-stat-label{ font-size: 12px; color: #8a94a6; margin-top: 3px; }

/* ─── TABS ──────────────────────────────────────────────────────────────── */
.ud-tabs-bar {
    background: #fff; border-radius: 12px 12px 0 0;
    border: 1px solid #e5e9f2; border-bottom: none;
    display: flex; overflow-x: auto;
}
.ud-tabs-bar::-webkit-scrollbar { display: none; }
.ud-tab {
    padding: 14px 22px; font-size: 13.5px; font-weight: 600; color: #8a94a6;
    cursor: pointer; border: none; background: none;
    border-bottom: 3px solid transparent;
    transition: all .2s; font-family: 'Exo 2',sans-serif; white-space: nowrap;
    display: flex; align-items: center; gap: 7px;
}
.ud-tab-count {
    background: #f0f2f7; color: #8a94a6; font-size: 11px; font-weight: 700;
    padding: 2px 7px; border-radius: 10px; transition: all .2s;
}
.ud-tab.active { color: #1a56db; border-bottom-color: #1a56db; }
.ud-tab.active .ud-tab-count { background: #dbeafe; color: #1a56db; }
.ud-tab:hover:not(.active) { color: #333; }

.ud-tab-content {
    background: #fff; border-radius: 0 0 12px 12px;
    border: 1px solid #e5e9f2; min-height: 300px; padding: 24px;
}
.ud-panel     { display: none; }
.ud-panel.on  { display: block; animation: udUp .35s ease both; }

/* ─── COURSE CARDS ──────────────────────────────────────────────────────── */
.ud-course-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 16px; }
.ud-course-card {
    display: flex; gap: 14px; padding: 14px; border: 1px solid #e5e9f2;
    border-radius: 10px; background: #fafbff; transition: box-shadow .2s, transform .2s;
    position: relative;
}
.ud-course-card:hover { box-shadow: 0 6px 24px rgba(0,0,0,.08); transform: translateY(-2px); }
.ud-course-thumb {
    width: 90px; height: 68px; border-radius: 7px; object-fit: cover;
    flex-shrink: 0; background: #e5e9f2;
}
.ud-course-info { flex: 1; min-width: 0; }
.ud-course-title {
    font-family: 'Rajdhani',sans-serif; font-size: 14px; font-weight: 700;
    color: #0f1b2d; margin-bottom: 5px; line-height: 1.3;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.ud-course-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
.ud-course-tag  {
    font-size: 11px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: #f0f4ff; color: #1a56db; border: 1px solid #dbeafe;
}
.ud-course-tag.free    { background: #e8f5e9; color: #2e7d32; border-color: #c8e6c9; }
.ud-course-tag.paid    { background: #fff8e1; color: #f57f17; border-color: #ffe082; }
.ud-course-tag.cert    { background: #fff3e0; color: #e65100; border-color: #ffcc80; }
.ud-course-tag.ongoing { background: #fce4ec; color: #c62828; border-color: #f48fb1; }
.ud-course-tag.recorded{ background: #ede7f6; color: #4527a0; border-color: #d1c4e9; }
.ud-course-date { font-size: 11.5px; color: #9aa3b5; display: flex; align-items: center; gap: 4px; }
.ud-course-date i { color: #f5a623; font-size: 10px; }
.ud-course-link {
    position: absolute; inset: 0; border-radius: 10px;
    display: flex; align-items: center; justify-content: flex-end;
    padding-right: 14px; opacity: 0; transition: opacity .2s;
}
.ud-course-card:hover .ud-course-link { opacity: 1; }
.ud-course-link i { font-size: 18px; color: #1a56db; }

/* ─── ORDERS TABLE ──────────────────────────────────────────────────────── */
.ud-orders-table { width: 100%; border-collapse: collapse; }
.ud-orders-table th {
    font-size: 11px; font-weight: 700; color: #8a94a6; text-transform: uppercase;
    letter-spacing: .06em; padding: 10px 12px; background: #f8f9fd;
    border-bottom: 1px solid #e5e9f2; text-align: left;
}
.ud-orders-table td {
    padding: 12px 12px; font-size: 13px; color: #2d3a4e;
    border-bottom: 1px solid #f0f2f7; vertical-align: middle;
}
.ud-orders-table tr:last-child td { border-bottom: none; }
.ud-orders-table tr:hover td { background: #fafbff; }
.ud-status-badge {
    font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 4px;
    display: inline-flex; align-items: center; gap: 4px;
}
.ud-status-badge.paid    { background: #e8f5e9; color: #2e7d32; }
.ud-status-badge.pending { background: #fff8e1; color: #f57f17; }
.ud-status-badge.failed  { background: #fce4ec; color: #c62828; }

/* ─── EMPTY STATE ───────────────────────────────────────────────────────── */
.ud-empty {
    text-align: center; padding: 50px 20px; color: #9aa3b5;
}
.ud-empty i { font-size: 48px; opacity: .25; display: block; margin-bottom: 14px; }
.ud-empty h4 { font-family: 'Rajdhani',sans-serif; font-size: 20px; color: #2d3a4e; margin-bottom: 6px; }
.ud-empty p  { font-size: 13.5px; margin-bottom: 18px; }
.ud-empty-btn {
    display: inline-flex; align-items: center; gap: 7px;
    background: #f5a623; color: #0f1b2d; font-weight: 700;
    padding: 11px 24px; border-radius: 8px; font-family: 'Exo 2',sans-serif; font-size: 14px;
    transition: background .2s;
}
.ud-empty-btn:hover { background: #d4890e; }

/* ─── PAGE HEADER ───────────────────────────────────────────────────────── */
.ud-page-header {
    background: linear-gradient(135deg, #0f1b2d 0%, #1a3050 100%);
    padding: 32px 24px;
}
.ud-page-header-inner { max-width: 1200px; margin: 0 auto; }
.ud-welcome { font-size: 24px; font-weight: 700; color: #fff; margin: 0 0 4px; }
.ud-welcome span { color: #f5a623; }
.ud-welcome-sub { font-size: 13.5px; color: rgba(255,255,255,.5); }

/* ─── RESPONSIVE ────────────────────────────────────────────────────────── */
@media(max-width:1000px) {
    .ud-layout   { flex-direction: column; padding: 20px 14px 56px; }
    .ud-sidebar  { width: 100%; position: static; }
    .ud-stats-grid { grid-template-columns: repeat(2,1fr); }
}
@media(max-width:640px) {
    .ud-course-grid { grid-template-columns: 1fr; }
    .ud-stats-grid  { grid-template-columns: 1fr 1fr; }
}
@media(max-width:400px) {
    .ud-stats-grid { grid-template-columns: 1fr; }
}
</style>

<div class="ud">

{{-- ── PAGE HEADER ── --}}
<div class="ud-page-header">
    <div class="ud-page-header-inner">
        <h1 class="ud-welcome">
            Welcome back, <span>{{ $user->firstname }}!</span>
        </h1>
        <p class="ud-welcome-sub">
            Track your courses, manage your account and pick up where you left off.
        </p>
    </div>
</div>

{{-- ── LAYOUT ── --}}
<div class="ud-layout">

    {{-- ════════════ SIDEBAR ════════════ --}}
    <div class="ud-sidebar ud-anim">

        {{-- Profile card --}}
        <div class="ud-profile-card">
            <div class="ud-avatar-wrap">
                @if($user->profile_pic)
                    <img src="{{ asset(getFilePath('userProfile') . '/' . $user->profile_pic) }}"
                         alt="{{ $user->firstname }}">
                @else
                    {{ strtoupper(substr($user->firstname, 0, 1)) }}
                @endif
            </div>
            <div class="ud-profile-name">{{ $user->firstname }} {{ $user->lastname }}</div>
            <div class="ud-profile-email">{{ $user->email }}</div>
            <div class="ud-profile-since">
                <i class="fas fa-calendar-alt" style="color:#f5a623;margin-right:4px;"></i>
                Member since {{ $user->created_at->format('M Y') }}
            </div>
        </div>

        {{-- Nav links --}}
        <div class="ud-sidebar-nav">
            <a href="{{ route('user.dashboard') }}"
               class="ud-nav-item {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                <i class="fas fa-th-large"></i> My Dashboard
            </a>
            <a href="{{ route('courses') }}" class="ud-nav-item">
                <i class="fas fa-book-open"></i> Browse Courses
            </a>
            <a href="{{ route('user.profile') }}" class="ud-nav-item">
                <i class="fas fa-user-circle"></i> Profile Settings
            </a>
            <a href="{{ route('user.change-password') }}" class="ud-nav-item">
                <i class="fas fa-lock"></i> Change Password
            </a>
            <a href="{{ route('user.logout') }}" class="ud-nav-item danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        {{-- Mini stats --}}
        <div class="ud-sidebar-stats">
            <h6>Quick Stats</h6>
            <div class="ud-mini-stat">
                <span class="ud-mini-stat-label"><i class="fas fa-graduation-cap"></i> Enrolled</span>
                <span class="ud-mini-stat-val">{{ $totalEnrolled }}</span>
            </div>
            <div class="ud-mini-stat">
                <span class="ud-mini-stat-label"><i class="fas fa-bolt"></i> Paid Courses</span>
                <span class="ud-mini-stat-val">{{ $paidCourses }}</span>
            </div>
            <div class="ud-mini-stat">
                <span class="ud-mini-stat-label"><i class="fas fa-gift"></i> Free Courses</span>
                <span class="ud-mini-stat-val">{{ $freeCourses }}</span>
            </div>
            <div class="ud-mini-stat">
                <span class="ud-mini-stat-label"><i class="fas fa-rupee-sign"></i> Total Spent</span>
                <span class="ud-mini-stat-val">₹{{ number_format($totalSpent) }}</span>
            </div>
        </div>
    </div>

    {{-- ════════════ MAIN CONTENT ════════════ --}}
    <div class="ud-main">

        {{-- ── STAT CARDS ── --}}
        <div class="ud-stats-grid ud-anim d1">
            <div class="ud-stat-card">
                <div class="ud-stat-icon blue"><i class="fas fa-book-open"></i></div>
                <div>
                    <div class="ud-stat-val">{{ $totalEnrolled }}</div>
                    <div class="ud-stat-label">Total Enrolled</div>
                </div>
            </div>
            <div class="ud-stat-card">
                <div class="ud-stat-icon gold"><i class="fas fa-play-circle"></i></div>
                <div>
                    <div class="ud-stat-val">{{ $activeCourses->count() }}</div>
                    <div class="ud-stat-label">Active Courses</div>
                </div>
            </div>
            <div class="ud-stat-card">
                <div class="ud-stat-icon green"><i class="fas fa-video"></i></div>
                <div>
                    <div class="ud-stat-val">{{ $recordedCourses->count() }}</div>
                    <div class="ud-stat-label">Recorded Access</div>
                </div>
            </div>
            <div class="ud-stat-card">
                <div class="ud-stat-icon purple"><i class="fas fa-rupee-sign"></i></div>
                <div>
                    <div class="ud-stat-val">₹{{ number_format($totalSpent) }}</div>
                    <div class="ud-stat-label">Total Invested</div>
                </div>
            </div>
        </div>

        {{-- ── TABS ── --}}
        <div class="ud-tabs-bar ud-anim d2">
            <button class="ud-tab active" onclick="udSwitchTab(0,this)">
                <i class="fas fa-layer-group"></i> All Courses
                <span class="ud-tab-count">{{ $totalEnrolled }}</span>
            </button>
            <button class="ud-tab" onclick="udSwitchTab(1,this)">
                <i class="fas fa-bolt"></i> Active
                <span class="ud-tab-count">{{ $activeCourses->count() }}</span>
            </button>
            <button class="ud-tab" onclick="udSwitchTab(2,this)">
                <i class="fas fa-video"></i> Recorded
                <span class="ud-tab-count">{{ $recordedCourses->count() }}</span>
            </button>
            <button class="ud-tab" onclick="udSwitchTab(3,this)">
                <i class="fas fa-receipt"></i> Purchase History
                <span class="ud-tab-count">{{ $recentOrders->count() }}</span>
            </button>
        </div>

        <div class="ud-tab-content ud-anim d3">

            {{-- ── TAB 0: ALL COURSES ── --}}
            <div class="ud-panel on" id="udPanel0">
                @if($enrollments->count())
                <div class="ud-course-grid">
                    @foreach($enrollments as $enrollment)
                    @php $c = $enrollment->course; @endphp
                    @if($c)
                    <div class="ud-course-card">
                        <img class="ud-course-thumb"
                             src="{{ $c->thumbnail_url }}"
                             alt="{{ $c->title }}"
                             onerror="this.src='https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=400'">
                        <div class="ud-course-info">
                            <div class="ud-course-title">{{ $c->title }}</div>
                            <div class="ud-course-meta">
                                <span class="ud-course-tag {{ $enrollment->access_type }}">
                                    {{ $enrollment->access_type === 'free' ? 'Free' : 'Paid' }}
                                </span>
                                <span class="ud-course-tag {{ $c->status }}">
                                    {{ ucfirst($c->status) }}
                                </span>
                                @if($c->has_certificate)
                                <span class="ud-course-tag cert">
                                    <i class="fas fa-certificate"></i> Cert
                                </span>
                                @endif
                                @if($c->category)
                                <span class="ud-course-tag" style="background:#f0f4ff;color:#3949ab;">
                                    {{ $c->category->name }}
                                </span>
                                @endif
                            </div>
                            <div class="ud-course-date">
                                <i class="fas fa-calendar-check"></i>
                                Enrolled {{ $enrollment->enrolled_at->format('d M Y') }}
                            </div>
                        </div>
                        <a href="{{ route('courses.detail', $c->slug) }}" class="ud-course-link">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    @endif
                    @endforeach
                </div>
                @else
                <div class="ud-empty">
                    <i class="fas fa-book-open"></i>
                    <h4>No Courses Yet</h4>
                    <p>You haven't enrolled in any courses. Browse our catalogue and start learning today.</p>
                    <a href="{{ route('courses') }}" class="ud-empty-btn">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
                @endif
            </div>

            {{-- ── TAB 1: ACTIVE ── --}}
            <div class="ud-panel" id="udPanel1">
                @if($activeCourses->count())
                <div class="ud-course-grid">
                    @foreach($activeCourses as $enrollment)
                    @php $c = $enrollment->course; @endphp
                    @if($c)
                    <div class="ud-course-card">
                        <img class="ud-course-thumb"
                             src="{{ $c->thumbnail_url }}" alt="{{ $c->title }}"
                             onerror="this.src='https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=400'">
                        <div class="ud-course-info">
                            <div class="ud-course-title">{{ $c->title }}</div>
                            <div class="ud-course-meta">
                                <span class="ud-course-tag {{ $c->status }}">{{ ucfirst($c->status) }}</span>
                                <span class="ud-course-tag {{ $enrollment->access_type }}">
                                    {{ $enrollment->access_type === 'free' ? 'Free' : 'Paid' }}
                                </span>
                            </div>
                            <div class="ud-course-date">
                                <i class="fas fa-calendar-check"></i>
                                Enrolled {{ $enrollment->enrolled_at->format('d M Y') }}
                            </div>
                        </div>
                        <a href="{{ route('courses.detail', $c->slug) }}" class="ud-course-link">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                    @endif
                    @endforeach
                </div>
                @else
                <div class="ud-empty">
                    <i class="fas fa-bolt"></i>
                    <h4>No Active Courses</h4>
                    <p>You don't have any active (upcoming or ongoing) courses right now.</p>
                    <a href="{{ route('courses') }}" class="ud-empty-btn">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
                @endif
            </div>

            {{-- ── TAB 2: RECORDED ── --}}
            <div class="ud-panel" id="udPanel2">
                @if($recordedCourses->count())
                <div class="ud-course-grid">
                    @foreach($recordedCourses as $enrollment)
                    @php $c = $enrollment->course; @endphp
                    @if($c)
                    <div class="ud-course-card">
                        <img class="ud-course-thumb"
                             src="{{ $c->thumbnail_url }}" alt="{{ $c->title }}"
                             onerror="this.src='https://img.freepik.com/free-vector/stock-market-analysis-concept-illustration_114360-5440.jpg?w=400'">
                        <div class="ud-course-info">
                            <div class="ud-course-title">{{ $c->title }}</div>
                            <div class="ud-course-meta">
                                <span class="ud-course-tag recorded">Recorded</span>
                                @if($c->has_certificate)
                                <span class="ud-course-tag cert"><i class="fas fa-certificate"></i> Cert</span>
                                @endif
                            </div>
                            <div class="ud-course-date">
                                <i class="fas fa-calendar-check"></i>
                                Enrolled {{ $enrollment->enrolled_at->format('d M Y') }}
                            </div>
                        </div>
                        <a href="{{ route('courses.detail', $c->slug) }}" class="ud-course-link">
                            <i class="fas fa-play-circle" style="color:#5c6bc0;"></i>
                        </a>
                    </div>
                    @endif
                    @endforeach
                </div>
                @else
                <div class="ud-empty">
                    <i class="fas fa-video"></i>
                    <h4>No Recorded Courses</h4>
                    <p>You don't have access to any recorded course content yet.</p>
                    <a href="{{ route('courses') }}" class="ud-empty-btn">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
                @endif
            </div>

            {{-- ── TAB 3: PURCHASE HISTORY ── --}}
            <div class="ud-panel" id="udPanel3">
                @if($recentOrders->count())
                <div style="overflow-x:auto;">
                    <table class="ud-orders-table">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Course</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentOrders as $order)
                            <tr>
                                <td>
                                    <strong style="font-size:12px;">{{ $order->order_number }}</strong>
                                </td>
                                <td>
                                    <div style="font-size:13px;font-weight:600;color:#0f1b2d;max-width:200px;">
                                        {{ Str::limit(optional($order->course)->title, 35) }}
                                    </div>
                                    <div style="font-size:11.5px;color:#9aa3b5;">
                                        {{ ucfirst($order->gateway) }}
                                    </div>
                                </td>
                                <td>
                                    <strong>₹{{ number_format($order->amount) }}</strong>
                                </td>
                                <td>
                                    <span class="ud-status-badge {{ $order->status }}">
                                        @if($order->status === 'paid') <i class="fas fa-check-circle"></i>
                                        @elseif($order->status === 'pending') <i class="fas fa-clock"></i>
                                        @else <i class="fas fa-times-circle"></i>
                                        @endif
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td style="font-size:12.5px;color:#7a8499;">
                                    {{ $order->created_at->format('d M Y') }}
                                </td>
                                <td>
                                    @if($order->course)
                                    <a href="{{ route('courses.detail', $order->course->slug) }}"
                                       style="font-size:12px;color:#f5a623;font-weight:600;display:flex;align-items:center;gap:4px;white-space:nowrap;">
                                        View <i class="fas fa-arrow-right"></i>
                                    </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <div class="ud-empty">
                    <i class="fas fa-receipt"></i>
                    <h4>No Purchase History</h4>
                    <p>You haven't made any course purchases yet.</p>
                    <a href="{{ route('courses') }}" class="ud-empty-btn">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
                @endif
            </div>

        </div>{{-- /.ud-tab-content --}}
    </div>{{-- /.ud-main --}}
</div>{{-- /.ud-layout --}}

</div>{{-- /.ud --}}

<script>
function udSwitchTab(idx, btn) {
    document.querySelectorAll('.ud-tab').forEach(function(b) { b.classList.remove('active'); });
    btn.classList.add('active');
    document.querySelectorAll('.ud-panel').forEach(function(p, i) {
        p.classList.toggle('on', i === idx);
    });
}
</script>

@endsection