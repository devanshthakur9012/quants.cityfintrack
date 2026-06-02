{{-- FILE: resources/views/themes/{activeTemplate}/user/dashboard.blade.php --}}
@extends($activeTemplate.'layouts.frontend')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">

<style>
/* ══════════════════════════════════════════════
   CITYQUANTS — USER DASHBOARD  v2.0
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
    --c-amber:    #FFA726;
    --c-purple:   #AB47BC;
    --c-text:     #D1D4DC;
    --c-muted:    #787B86;
    --c-faint:    rgba(255,255,255,.03);
    --f-sans:     'DM Sans', system-ui, sans-serif;
    --f-display:  'Syne', sans-serif;
    --f-mono:     'Space Grotesk', monospace;
}

.ud {
    font-family: var(--f-sans);
    background: var(--c-bg);
    color: var(--c-text);
    min-height: 80vh;
}
.ud * { box-sizing: border-box; }
.ud a { text-decoration: none; color: inherit; }

@keyframes udFadeUp {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: none; }
}
.ud-anim    { animation: udFadeUp .45s ease both; }
.ud-anim.d1 { animation-delay: .06s; }
.ud-anim.d2 { animation-delay: .12s; }
.ud-anim.d3 { animation-delay: .18s; }

/* ══ PAGE HEADER ══════════════════════════════ */
.ud-page-header {
    position: relative; overflow: hidden;
    background: var(--c-bg);
    padding: 44px 24px 36px;
    border-bottom: 1px solid var(--c-border);
}
.ud-page-header::before {
    content: '';
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(125,255,0,.022) 1px, transparent 1px),
        linear-gradient(90deg, rgba(125,255,0,.022) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 90% 90% at 30% 50%, black, transparent);
    pointer-events: none;
}
.ud-page-header::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 45% 80% at 10% 50%, rgba(125,255,0,.05), transparent 70%);
    pointer-events: none;
}
.ud-page-header-inner {
    position: relative; z-index: 1;
    max-width: 1200px; margin: 0 auto;
    display: flex; align-items: center;
    justify-content: space-between; gap: 20px; flex-wrap: wrap;
}
.ud-welcome {
    font-family: var(--f-display);
    font-size: clamp(22px, 3.5vw, 32px);
    font-weight: 800; color: #fff;
    margin-bottom: 6px; letter-spacing: -.01em;
}
.ud-welcome span { color: var(--c-lime); }
.ud-welcome-sub { font-size: 13px; color: var(--c-muted); }

/* Live pill */
.ud-live-pill {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-lime-dim);
    border: 1px solid rgba(125,255,0,.22);
    color: var(--c-lime); font-size: 11px; font-weight: 700;
    letter-spacing: .1em; text-transform: uppercase;
    padding: 6px 14px; border-radius: 100px;
}
.ud-live-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--c-lime);
    animation: udPulse 2s infinite;
}
@keyframes udPulse { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ══ LAYOUT ════════════════════════════════════ */
.ud-layout {
    max-width: 1200px; margin: 0 auto;
    padding: 28px 24px 80px;
    display: flex; gap: 22px; align-items: flex-start;
}
.ud-sidebar { flex-shrink: 0; width: 252px; position: sticky; top: 96px; }
.ud-main    { flex: 1; min-width: 0; }

/* ══ SIDEBAR ═══════════════════════════════════ */

/* Profile card */
.ud-profile-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 24px 18px 18px;
    text-align: center; margin-bottom: 12px;
    position: relative; overflow: hidden;
}
.ud-profile-card::before {
    content: '';
    position: absolute; top: 0; left: 14px; right: 14px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: .5;
}
.ud-profile-card::after {
    content: '';
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 70% 50% at 50% 0%, rgba(125,255,0,.04), transparent 70%);
    pointer-events: none;
}
.ud-avatar-wrap {
    width: 68px; height: 68px; border-radius: 50%;
    margin: 0 auto 14px; position: relative; z-index: 1;
    border: 2px solid var(--c-lime);
    background: var(--c-lime);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--f-display); font-size: 24px;
    font-weight: 800; color: #000; overflow: hidden;
    box-shadow: 0 0 18px rgba(125,255,0,.2);
}
.ud-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
.ud-profile-name {
    font-family: var(--f-display); font-size: 16px;
    font-weight: 700; color: #fff; margin-bottom: 3px;
    position: relative; z-index: 1;
}
.ud-profile-email {
    font-size: 11px; color: var(--c-muted); margin-bottom: 12px;
    word-break: break-all; position: relative; z-index: 1;
}
.ud-profile-since {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 10px; color: var(--c-muted);
    background: var(--c-panel); border: 1px solid var(--c-border);
    padding: 4px 10px; border-radius: 100px;
    position: relative; z-index: 1;
}
.ud-profile-since i { color: var(--c-lime); }

/* Sidebar nav */
.ud-sidebar-nav {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; overflow: hidden; margin-bottom: 12px;
}
.ud-nav-item {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    font-size: 13px; font-weight: 500; color: var(--c-muted);
    border-bottom: 1px solid var(--c-border);
    border-left: 2px solid transparent;
    transition: all .2s; cursor: pointer;
}
.ud-nav-item:last-child { border-bottom: none; }
.ud-nav-item:hover {
    background: var(--c-faint);
    color: var(--c-text);
    border-left-color: var(--c-border2);
}
.ud-nav-item.active {
    background: var(--c-lime-dim);
    color: var(--c-lime);
    border-left-color: var(--c-lime);
}
.ud-nav-item i { width: 17px; text-align: center; font-size: 15px; flex-shrink: 0; }
.ud-nav-item.danger { color: rgba(239,83,80,.7); }
.ud-nav-item.danger:hover { background: rgba(239,83,80,.07); color: var(--c-red); border-left-color: var(--c-red); }

/* Sidebar mini stats */
.ud-sidebar-stats {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 12px; padding: 16px;
}
.ud-sidebar-stats-head {
    font-size: 10px; font-weight: 700; letter-spacing: .12em;
    text-transform: uppercase; color: var(--c-muted);
    margin-bottom: 12px;
    display: flex; align-items: center; gap: 8px;
}
.ud-sidebar-stats-head::after {
    content: ''; flex: 1; height: 1px; background: var(--c-border);
}
.ud-mini-stat {
    display: flex; align-items: center; justify-content: space-between;
    padding: 8px 0; border-bottom: 1px solid var(--c-border);
}
.ud-mini-stat:last-child { border-bottom: none; }
.ud-mini-stat-label {
    font-size: 12px; color: var(--c-muted);
    display: flex; align-items: center; gap: 7px;
}
.ud-mini-stat-label i { color: var(--c-lime); width: 14px; text-align: center; font-size: 12px; }
.ud-mini-stat-val {
    font-family: var(--f-mono); font-size: 14px;
    font-weight: 600; color: #fff;
}

/* ══ STAT CARDS ════════════════════════════════ */
.ud-stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px; margin-bottom: 20px;
}
.ud-stat-card {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-radius: 10px; padding: 18px 16px;
    display: flex; align-items: center; gap: 14px;
    transition: border-color .25s, transform .2s;
    position: relative; overflow: hidden;
}
.ud-stat-card::before {
    content: '';
    position: absolute; top: 0; left: 12px; right: 12px; height: 1px;
    background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
    opacity: 0; transition: opacity .25s;
}
.ud-stat-card:hover {
    border-color: rgba(125,255,0,.18);
    transform: translateY(-2px);
}
.ud-stat-card:hover::before { opacity: 1; }

.ud-stat-icon {
    width: 42px; height: 42px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ud-stat-icon.blue   { background: rgba(0,184,212,.12);  color: var(--c-blue); }
.ud-stat-icon.amber  { background: rgba(255,167,38,.1);  color: var(--c-amber); }
.ud-stat-icon.teal   { background: rgba(38,166,154,.1);  color: var(--c-teal); }
.ud-stat-icon.purple { background: rgba(171,71,188,.1);  color: var(--c-purple); }

.ud-stat-val {
    font-family: var(--f-display); font-size: 24px;
    font-weight: 700; color: #fff; line-height: 1;
}
.ud-stat-label { font-size: 11px; color: var(--c-muted); margin-top: 4px; letter-spacing: .03em; }

/* ══ TABS ══════════════════════════════════════ */
.ud-tabs-bar {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-bottom: none;
    border-radius: 10px 10px 0 0;
    display: flex; overflow-x: auto;
}
.ud-tabs-bar::-webkit-scrollbar { display: none; }

.ud-tab {
    padding: 13px 20px;
    font-size: 12px; font-weight: 600;
    letter-spacing: .05em; text-transform: uppercase;
    color: var(--c-muted); cursor: pointer;
    border: none; background: none;
    border-bottom: 2px solid transparent;
    transition: all .2s; font-family: var(--f-sans);
    white-space: nowrap;
    display: flex; align-items: center; gap: 7px;
}
.ud-tab-count {
    background: var(--c-panel); color: var(--c-muted);
    font-size: 10px; font-weight: 700;
    padding: 2px 7px; border-radius: 100px;
    transition: all .2s; font-family: var(--f-mono);
}
.ud-tab.active { color: var(--c-lime); border-bottom-color: var(--c-lime); }
.ud-tab.active .ud-tab-count { background: var(--c-lime-dim); color: var(--c-lime); }
.ud-tab:hover:not(.active) { color: var(--c-text); }

/* Tab content */
.ud-tab-content {
    background: var(--c-surface);
    border: 1px solid var(--c-border);
    border-top: 1px solid rgba(125,255,0,.1);
    border-radius: 0 0 10px 10px;
    min-height: 300px; padding: 22px;
}
.ud-panel     { display: none; }
.ud-panel.on  { display: block; animation: udFadeUp .35s ease both; }

/* ══ COURSE CARDS ══════════════════════════════ */
.ud-course-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
.ud-course-card {
    display: flex; gap: 14px;
    padding: 14px;
    background: var(--c-panel);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    position: relative; overflow: hidden;
    transition: border-color .25s, transform .2s;
}
.ud-course-card:hover {
    border-color: rgba(125,255,0,.18);
    transform: translateY(-2px);
}
.ud-course-thumb {
    width: 88px; height: 66px; border-radius: 7px;
    object-fit: cover; flex-shrink: 0;
    background: var(--c-surface);
    border: 1px solid var(--c-border);
}
.ud-course-info { flex: 1; min-width: 0; }
.ud-course-title {
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    color: #fff; margin-bottom: 6px; line-height: 1.3;
    display: -webkit-box; -webkit-line-clamp: 2;
    -webkit-box-orient: vertical; overflow: hidden;
}
.ud-course-meta { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 7px; }
.ud-course-tag {
    font-size: 10px; padding: 2px 8px; border-radius: 4px;
    font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    background: var(--c-surface); color: var(--c-muted);
    border: 1px solid var(--c-border2);
}
.ud-course-tag.free     { background: rgba(38,166,154,.1);  color: var(--c-teal);   border-color: rgba(38,166,154,.25); }
.ud-course-tag.paid     { background: rgba(255,167,38,.1);  color: var(--c-amber);  border-color: rgba(255,167,38,.25); }
.ud-course-tag.cert     { background: rgba(125,255,0,.1);   color: var(--c-lime);   border-color: rgba(125,255,0,.25); }
.ud-course-tag.ongoing  { background: rgba(0,184,212,.1);   color: var(--c-blue);   border-color: rgba(0,184,212,.25); }
.ud-course-tag.recorded { background: rgba(171,71,188,.1);  color: var(--c-purple); border-color: rgba(171,71,188,.25); }
.ud-course-date {
    font-size: 11px; color: var(--c-muted);
    display: flex; align-items: center; gap: 5px;
    font-family: var(--f-mono);
}
.ud-course-date i { color: var(--c-lime); font-size: 10px; }

/* Arrow overlay on hover */
.ud-course-link {
    position: absolute; inset: 0; border-radius: 10px;
    display: flex; align-items: center; justify-content: flex-end;
    padding-right: 16px;
    opacity: 0; transition: opacity .2s;
    background: linear-gradient(to left, rgba(11,14,17,.6), transparent 60%);
}
.ud-course-card:hover .ud-course-link { opacity: 1; }
.ud-course-link i { font-size: 17px; color: var(--c-lime); }

/* ══ ORDERS TABLE ══════════════════════════════ */
.ud-orders-table { width: 100%; border-collapse: collapse; }
.ud-orders-table th {
    font-size: 10px; font-weight: 700; color: var(--c-muted);
    text-transform: uppercase; letter-spacing: .1em;
    padding: 10px 12px;
    background: var(--c-panel);
    border-bottom: 1px solid var(--c-border);
    text-align: left; font-family: var(--f-mono);
}
.ud-orders-table td {
    padding: 12px; font-size: 13px; color: var(--c-text);
    border-bottom: 1px solid var(--c-border); vertical-align: middle;
}
.ud-orders-table tr:last-child td { border-bottom: none; }
.ud-orders-table tr:hover td { background: var(--c-faint); }

.ud-status-badge {
    font-size: 10px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; padding: 4px 10px; border-radius: 4px;
    display: inline-flex; align-items: center; gap: 5px;
    font-family: var(--f-mono);
}
.ud-status-badge.paid    { background: rgba(38,166,154,.12); color: #4DB6AC; }
.ud-status-badge.pending { background: rgba(255,167,38,.1);  color: var(--c-amber); }
.ud-status-badge.failed  { background: rgba(239,83,80,.1);   color: #EF9A9A; }

/* ══ EMPTY STATE ═══════════════════════════════ */
.ud-empty {
    text-align: center; padding: 56px 20px; color: var(--c-muted);
}
.ud-empty-icon {
    width: 64px; height: 64px; border-radius: 50%;
    background: var(--c-panel); border: 1px solid var(--c-border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px; font-size: 26px; color: var(--c-muted);
}
.ud-empty h4 {
    font-family: var(--f-display); font-size: 18px;
    color: var(--c-text); margin-bottom: 8px;
}
.ud-empty p  { font-size: 13px; margin-bottom: 20px; line-height: 1.65; }
.ud-empty-btn {
    display: inline-flex; align-items: center; gap: 7px;
    background: var(--c-lime); color: #000;
    font-family: var(--f-display); font-size: 13px; font-weight: 700;
    padding: 10px 22px; border-radius: 7px;
    transition: background .2s, box-shadow .2s;
    box-shadow: 0 0 16px rgba(125,255,0,.2);
}
.ud-empty-btn:hover { background: #8FFF1A; box-shadow: 0 0 24px rgba(125,255,0,.32); color: #000; }

/* ══ RESPONSIVE ════════════════════════════════ */
@media (max-width: 1024px) {
    .ud-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 900px) {
    .ud-layout  { flex-direction: column; padding: 20px 16px 60px; }
    .ud-sidebar { width: 100%; position: static; }
    .ud-stats-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .ud-course-grid { grid-template-columns: 1fr; }
}
@media (max-width: 480px) {
    .ud-stats-grid { grid-template-columns: 1fr 1fr; }
    .ud-page-header { padding: 32px 16px 26px; }
}
</style>

<div class="ud">

{{-- ══ PAGE HEADER ══ --}}
<div class="ud-page-header">
    <div class="ud-page-header-inner ud-anim">
        <div>
            <h1 class="ud-welcome">
                Welcome back, <span>{{ $user->firstname }}!</span>
            </h1>
            <p class="ud-welcome-sub">
                Track your courses, manage your account and pick up where you left off.
            </p>
        </div>
        <div class="ud-live-pill">
            <span class="ud-live-dot"></span>
            Dashboard Live
        </div>
    </div>
</div>

{{-- ══ LAYOUT ══ --}}
<div class="ud-layout">

    {{-- ════ SIDEBAR ════ --}}
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
                <i class="fas fa-calendar-alt"></i>
                Member since {{ $user->created_at->format('M Y') }}
            </div>
        </div>

        {{-- Nav --}}
        <div class="ud-sidebar-nav">
            <a href="{{ route('user.dashboard') }}"
               class="ud-nav-item {{ request()->routeIs('user.dashboard') ? 'active' : '' }}">
                <i class="fas fa-th-large"></i> My Dashboard
            </a>
            <a href="{{ route('cp.my-subscription') }}"
               class="ud-nav-item {{ request()->routeIs('cp.my-subscription') ? 'active' : '' }}">
                <i class="fas fa-crown"></i> My Subscription
            </a>
            <a href="{{ route('cp.analyses.index') }}"
               class="ud-nav-item {{ request()->routeIs('cp.analyses.*') ? 'active' : '' }}">
                <i class="fas fa-chart-line"></i> Option Analysis
            </a>
            <a href="{{ route('courses') }}" class="ud-nav-item">
                <i class="fas fa-book-open"></i> Browse Courses
            </a>
            <a href="{{ route('user.profile') }}"
               class="ud-nav-item {{ request()->routeIs('user.profile') ? 'active' : '' }}">
                <i class="fas fa-user-circle"></i> Profile Settings
            </a>
            <a href="{{ route('user.change-password') }}"
               class="ud-nav-item {{ request()->routeIs('user.change-password') ? 'active' : '' }}">
                <i class="fas fa-lock"></i> Change Password
            </a>
            <a href="{{ route('user.logout') }}" class="ud-nav-item danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        {{-- Mini stats --}}
        <div class="ud-sidebar-stats">
            <div class="ud-sidebar-stats-head">Quick Stats</div>
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

    </div>{{-- /sidebar --}}


    {{-- ════ MAIN CONTENT ════ --}}
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
                <div class="ud-stat-icon amber"><i class="fas fa-play-circle"></i></div>
                <div>
                    <div class="ud-stat-val">{{ $activeCourses->count() }}</div>
                    <div class="ud-stat-label">Active Courses</div>
                </div>
            </div>
            <div class="ud-stat-card">
                <div class="ud-stat-icon teal"><i class="fas fa-video"></i></div>
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
                <i class="fas fa-receipt"></i> Purchases
                <span class="ud-tab-count">{{ $recentOrders->count() }}</span>
            </button>
        </div>

        <div class="ud-tab-content ud-anim d3">

            {{-- ── TAB 0 : ALL COURSES ── --}}
            <div class="ud-panel on" id="udPanel0">
                @if($enrollments->count())
                <div class="ud-course-grid">
                    @foreach($enrollments as $enrollment)
                    @php $c = $enrollment->course; @endphp
                    @if($c)
                    <div class="ud-course-card">
                        <img class="ud-course-thumb"
                             src="{{ $c->thumbnail_url }}" alt="{{ $c->title }}"
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
                                <span class="ud-course-tag">{{ $c->category->name }}</span>
                                @endif
                            </div>
                            <div class="ud-course-date">
                                <i class="fas fa-calendar-check"></i>
                                {{ $enrollment->enrolled_at->format('d M Y') }}
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
                    <div class="ud-empty-icon"><i class="fas fa-book-open"></i></div>
                    <h4>No Courses Yet</h4>
                    <p>You haven't enrolled in any courses.<br>Browse our catalogue and start learning today.</p>
                    <a href="{{ route('courses') }}" class="ud-empty-btn">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
                @endif
            </div>

            {{-- ── TAB 1 : ACTIVE ── --}}
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
                                {{ $enrollment->enrolled_at->format('d M Y') }}
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
                    <div class="ud-empty-icon"><i class="fas fa-bolt"></i></div>
                    <h4>No Active Courses</h4>
                    <p>You don't have any active or upcoming courses right now.</p>
                    <a href="{{ route('courses') }}" class="ud-empty-btn">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
                @endif
            </div>

            {{-- ── TAB 2 : RECORDED ── --}}
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
                                <span class="ud-course-tag cert">
                                    <i class="fas fa-certificate"></i> Cert
                                </span>
                                @endif
                            </div>
                            <div class="ud-course-date">
                                <i class="fas fa-calendar-check"></i>
                                {{ $enrollment->enrolled_at->format('d M Y') }}
                            </div>
                        </div>
                        <a href="{{ route('courses.detail', $c->slug) }}" class="ud-course-link">
                            <i class="fas fa-play-circle"></i>
                        </a>
                    </div>
                    @endif
                    @endforeach
                </div>
                @else
                <div class="ud-empty">
                    <div class="ud-empty-icon"><i class="fas fa-video"></i></div>
                    <h4>No Recorded Courses</h4>
                    <p>You don't have access to any recorded content yet.</p>
                    <a href="{{ route('courses') }}" class="ud-empty-btn">
                        <i class="fas fa-search"></i> Browse Courses
                    </a>
                </div>
                @endif
            </div>

            {{-- ── TAB 3 : PURCHASE HISTORY ── --}}
            <div class="ud-panel" id="udPanel3">
                @if($recentOrders->count())
                <div style="overflow-x: auto;">
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
                                    <span style="font-family:var(--f-mono);font-size:11px;color:var(--c-muted);">
                                        {{ $order->order_number }}
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:13px;font-weight:600;color:#fff;max-width:190px;">
                                        {{ Str::limit(optional($order->course)->title, 35) }}
                                    </div>
                                    <div style="font-size:11px;color:var(--c-muted);margin-top:2px;font-family:var(--f-mono);">
                                        {{ ucfirst($order->gateway) }}
                                    </div>
                                </td>
                                <td>
                                    <span style="font-family:var(--f-mono);font-weight:600;color:#fff;">
                                        ₹{{ number_format($order->amount) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="ud-status-badge {{ $order->status }}">
                                        @if($order->status === 'paid')   <i class="fas fa-check-circle"></i>
                                        @elseif($order->status === 'pending') <i class="fas fa-clock"></i>
                                        @else <i class="fas fa-times-circle"></i>
                                        @endif
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-family:var(--f-mono);font-size:11px;color:var(--c-muted);">
                                        {{ $order->created_at->format('d M Y') }}
                                    </span>
                                </td>
                                <td>
                                    @if($order->course)
                                    <a href="{{ route('courses.detail', $order->course->slug) }}"
                                       style="font-size:12px;color:var(--c-lime);font-weight:600;
                                              display:flex;align-items:center;gap:4px;white-space:nowrap;">
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
                    <div class="ud-empty-icon"><i class="fas fa-receipt"></i></div>
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

{{-- ── JS: tab switch — LOGIC IDENTICAL ── --}}
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