@extends($activeTemplate.'layouts.master')
@section('content')

<link href="https://fonts.googleapis.com/css2?family=Rajdhani:wght@400;500;600;700&family=Exo+2:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

<style>
/* =========================================
   ROOT VARIABLES
========================================= */
:root {
    --gold:      #7DFF00;
    --gold2:     #FFD06A;
    --gold-dim:  rgba(245,166,35,.15);
    --dark:      #0D1B2A;
    --dark2:     #162844;
    --card-bg:   #ffffff;
    --bg-page:   #f0f3fa;
    --txt:       #1a1a2e;
    --muted:     #667788;
    --bdr:       #e5e9f2;
    --success:   #1ab87e;
    --danger:    #e74c3c;
    --sidebar-w: 260px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Exo 2', sans-serif; background: var(--bg-page); color: var(--txt); }

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: none; }
}
@keyframes pulseDot {
    0%,100% { transform: scale(1); opacity: 1; }
    50%      { transform: scale(.5); opacity: .4; }
}
@keyframes shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}
.fade-up     { animation: fadeUp .55s ease both; }
.fade-up.d1  { animation-delay: .05s; }
.fade-up.d2  { animation-delay: .1s; }
.fade-up.d3  { animation-delay: .15s; }
.fade-up.d4  { animation-delay: .2s; }
.fade-up.d5  { animation-delay: .25s; }
.fade-up.d6  { animation-delay: .3s; }

/* =========================================
   LAYOUT SHELL
========================================= */
.db-shell {
    display: flex;
    min-height: 100vh;
}

/* =========================================
   SIDEBAR
========================================= */
.db-sidebar {
    width: var(--sidebar-w);
    background: linear-gradient(180deg, #0D1B2A 0%, #0f2138 60%, #0D1B2A 100%);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 200;
    transition: transform .3s;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(245,166,35,.3) transparent;
}
.db-sidebar::-webkit-scrollbar { width: 4px; }
.db-sidebar::-webkit-scrollbar-thumb { background: rgba(245,166,35,.3); border-radius: 2px; }

.db-sidebar-logo {
    padding: 24px 22px 18px;
    border-bottom: 1px solid rgba(255,255,255,.07);
    display: flex; align-items: center; gap: 12px;
}
.db-sidebar-logo img { height: 36px; object-fit: contain; }
.db-sidebar-logo-txt {
    font-family: 'Rajdhani', sans-serif;
    font-size: 20px; font-weight: 700; color: #fff; line-height: 1;
}
.db-sidebar-logo-txt span { color: var(--gold); }

/* User Card */
.db-user-card {
    margin: 16px 14px;
    background: rgba(245,166,35,.1);
    border: 1px solid rgba(245,166,35,.2);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex; align-items: center; gap: 12px;
}
.db-user-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: var(--gold); display: flex; align-items: center; justify-content: center;
    font-family: 'Rajdhani', sans-serif; font-size: 18px; font-weight: 700; color: #000;
    flex-shrink: 0;
    overflow: hidden;
}
.db-user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.db-user-name {
    font-family: 'Rajdhani', sans-serif;
    font-size: 15px; font-weight: 700; color: #fff; line-height: 1.2;
}
.db-user-email { font-size: 11px; color: rgba(255,255,255,.45); margin-top: 2px; }

/* Nav */
.db-nav { padding: 10px 0; flex: 1; }
.db-nav-label {
    font-size: 10px; font-weight: 700; color: rgba(255,255,255,.3);
    text-transform: uppercase; letter-spacing: .12em;
    padding: 14px 22px 6px;
}
.db-nav-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 22px;
    font-size: 13.5px; font-weight: 600; color: rgba(255,255,255,.6);
    cursor: pointer; transition: all .2s;
    border-left: 3px solid transparent;
    text-decoration: none;
}
.db-nav-item i { width: 18px; text-align: center; font-size: 15px; color: rgba(255,255,255,.4); transition: color .2s; }
.db-nav-item:hover { color: #fff; background: rgba(255,255,255,.05); }
.db-nav-item:hover i { color: var(--gold); }
.db-nav-item.active { color: var(--gold); border-left-color: var(--gold); background: rgba(245,166,35,.08); }
.db-nav-item.active i { color: var(--gold); }

/* Logout bottom */
.db-sidebar-footer {
    padding: 16px 14px;
    border-top: 1px solid rgba(255,255,255,.07);
    display: flex; flex-direction: column; gap: 6px;
}
.db-sidebar-footer .db-nav-item { padding: 10px 8px; border-radius: 8px; border-left: none; }
.db-sidebar-footer .db-nav-item.danger { color: #ff6b6b; }
.db-sidebar-footer .db-nav-item.danger i { color: #ff6b6b; }
.db-sidebar-footer .db-nav-item.danger:hover { background: rgba(231,76,60,.12); }

/* =========================================
   MAIN CONTENT
========================================= */
.db-main {
    margin-left: var(--sidebar-w);
    flex: 1;
    display: flex; flex-direction: column;
    min-height: 100vh;
}

/* Topbar */
.db-topbar {
    background: #fff;
    border-bottom: 1px solid var(--bdr);
    padding: 0 32px;
    height: 64px;
    display: flex; align-items: center; justify-content: space-between;
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
}
.db-topbar-left { display: flex; align-items: center; gap: 14px; }
.db-hamburger {
    display: none;
    background: none; border: none; cursor: pointer;
    padding: 6px; border-radius: 6px;
}
.db-hamburger span {
    display: block; width: 22px; height: 2px;
    background: var(--txt); margin: 4px 0; border-radius: 2px; transition: all .3s;
}
.db-topbar-title {
    font-family: 'Rajdhani', sans-serif;
    font-size: 20px; font-weight: 700; color: var(--txt);
}
.db-topbar-date {
    font-size: 12px; color: var(--muted);
    background: var(--bg-page); padding: 4px 12px; border-radius: 20px;
    border: 1px solid var(--bdr);
}
.db-topbar-right { display: flex; align-items: center; gap: 12px; }
.db-topbar-btn {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--bg-page); border: 1px solid var(--bdr);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--muted); font-size: 14px;
    transition: all .2s; text-decoration: none; position: relative;
}
.db-topbar-btn:hover { border-color: var(--gold); color: var(--gold); }
.db-notif-dot {
    position: absolute; top: 6px; right: 6px;
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--gold); border: 2px solid #fff;
    animation: pulseDot 2s ease infinite;
}

/* Content Area */
.db-content { padding: 28px 32px 60px; }

/* =========================================
   WELCOME BANNER
========================================= */
.db-welcome {
    background: linear-gradient(135deg, #0D1B2A 0%, #162844 55%, #1e3d70 100%);
    border-radius: 16px;
    padding: 28px 36px;
    margin-bottom: 28px;
    display: flex; align-items: center; justify-content: space-between;
    gap: 20px;
    position: relative; overflow: hidden;
    border: 1px solid rgba(245,166,35,.2);
}
.db-welcome::before {
    content: '';
    position: absolute; right: -60px; top: -60px;
    width: 260px; height: 260px; border-radius: 50%;
    background: radial-gradient(ellipse, rgba(245,166,35,.15) 0%, transparent 70%);
    pointer-events: none;
}
.db-welcome-text h2 {
    font-family: 'Rajdhani', sans-serif;
    font-size: 26px; font-weight: 700; color: #fff; margin-bottom: 6px;
}
.db-welcome-text h2 span { color: var(--gold); }
.db-welcome-text p { font-size: 13px; color: rgba(255,255,255,.55); line-height: 1.6; max-width: 500px; }
.db-welcome-stats { display: flex; gap: 28px; flex-shrink: 0; }
.db-welstat { text-align: center; }
.db-welstat-val {
    font-family: 'Rajdhani', sans-serif;
    font-size: 24px; font-weight: 700; color: var(--gold); line-height: 1;
}
.db-welstat-lbl { font-size: 11px; color: rgba(255,255,255,.45); margin-top: 3px; }

/* =========================================
   SECTION HEADS
========================================= */
.db-section-head {
    display: flex; align-items: center; gap: 12px; margin-bottom: 16px;
}
.db-section-head h3 {
    font-family: 'Rajdhani', sans-serif;
    font-size: 18px; font-weight: 700; color: var(--txt); white-space: nowrap;
}
.db-section-head::after {
    content: ''; flex: 1; height: 2px;
    background: linear-gradient(90deg, var(--gold) 0%, transparent 100%);
    border-radius: 2px;
}

/* =========================================
   STAT CARDS
========================================= */
.db-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.db-stat-card {
    background: var(--card-bg);
    border-radius: 14px;
    padding: 20px 22px;
    border: 1px solid var(--bdr);
    display: flex; flex-direction: column; gap: 12px;
    transition: transform .25s, box-shadow .25s;
    position: relative; overflow: hidden;
}
.db-stat-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--gold2));
    opacity: 0; transition: opacity .25s;
}
.db-stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.1); }
.db-stat-card:hover::before { opacity: 1; }
.db-stat-card-top { display: flex; align-items: flex-start; justify-content: space-between; }
.db-stat-icon {
    width: 44px; height: 44px; border-radius: 12px;
    background: var(--gold-dim);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: var(--gold);
}
.db-stat-badge {
    font-size: 11px; font-weight: 700; padding: 3px 9px;
    border-radius: 20px;
}
.db-stat-badge.up   { background: rgba(26,184,126,.12); color: var(--success); }
.db-stat-badge.down { background: rgba(231,76,60,.12); color: var(--danger); }
.db-stat-badge.neu  { background: var(--bg-page); color: var(--muted); }
.db-stat-lbl { font-size: 12px; color: var(--muted); font-weight: 600; }
.db-stat-val {
    font-family: 'Rajdhani', sans-serif;
    font-size: 26px; font-weight: 700; color: var(--txt); line-height: 1;
    margin-top: 2px;
}
.db-stat-sub { font-size: 11.5px; color: var(--muted); }

/* =========================================
   PORTFOLIO CARDS GRID
========================================= */
.db-pf-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.db-pf-card {
    background: var(--card-bg);
    border-radius: 14px;
    border: 1px solid var(--bdr);
    overflow: hidden;
    transition: transform .25s, box-shadow .25s;
    text-decoration: none;
    display: block;
}
.db-pf-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.1); }
.db-pf-card-head {
    padding: 16px 18px 12px;
    display: flex; align-items: center; gap: 12px;
}
.db-pf-card-ico {
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 18px;
}
.db-pf-card-title {
    font-family: 'Rajdhani', sans-serif;
    font-size: 14.5px; font-weight: 700; color: var(--txt); line-height: 1.3;
}
.db-pf-card-vals {
    padding: 0 18px 16px;
    display: grid; grid-template-columns: 1fr 1fr; gap: 10px;
}
.db-pf-val-box {}
.db-pf-val-lbl { font-size: 10.5px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
.db-pf-val-num {
    font-family: 'Rajdhani', sans-serif;
    font-size: 17px; font-weight: 700; color: var(--txt); margin-top: 2px;
}
.db-pf-card-foot {
    padding: 10px 18px;
    border-top: 1px solid var(--bdr);
    background: var(--bg-page);
    display: flex; align-items: center; justify-content: space-between;
}
.db-pf-pnl {
    font-size: 13px; font-weight: 700;
    display: flex; align-items: center; gap: 5px;
}
.db-pf-pnl.pos { color: var(--success); }
.db-pf-pnl.neg { color: var(--danger); }
.db-pf-arrow {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--gold-dim); color: var(--gold);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
}

/* =========================================
   CHARTS ROW
========================================= */
.db-charts-row {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 20px;
    margin-bottom: 28px;
}
.db-chart-card {
    background: var(--card-bg);
    border-radius: 14px;
    border: 1px solid var(--bdr);
    overflow: hidden;
}
.db-chart-head {
    padding: 18px 22px 14px;
    border-bottom: 1px solid var(--bdr);
    display: flex; align-items: center; justify-content: space-between;
}
.db-chart-title {
    font-family: 'Rajdhani', sans-serif;
    font-size: 16px; font-weight: 700; color: var(--txt);
}
.db-chart-body { padding: 16px 10px; }

/* =========================================
   ACTIVITY ROW — courses, webinars, events
========================================= */
.db-activity-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 28px;
}
.db-act-card {
    background: var(--card-bg);
    border-radius: 14px;
    border: 1px solid var(--bdr);
    overflow: hidden;
}
.db-act-head {
    padding: 16px 20px 12px;
    border-bottom: 1px solid var(--bdr);
    display: flex; align-items: center; gap: 10px;
}
.db-act-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px;
}
.db-act-head-title {
    font-family: 'Rajdhani', sans-serif;
    font-size: 15px; font-weight: 700; color: var(--txt);
}
.db-act-head-count {
    margin-left: auto;
    font-family: 'Rajdhani', sans-serif;
    font-size: 22px; font-weight: 700; color: var(--gold);
}
.db-act-list { padding: 10px 0; max-height: 240px; overflow-y: auto; }
.db-act-list::-webkit-scrollbar { width: 3px; }
.db-act-list::-webkit-scrollbar-thumb { background: var(--bdr); }
.db-act-item {
    padding: 10px 20px;
    display: flex; align-items: center; gap: 12px;
    border-bottom: 1px solid #f5f7fc;
    transition: background .15s;
}
.db-act-item:last-child { border-bottom: none; }
.db-act-item:hover { background: var(--bg-page); }
.db-act-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.db-act-item-title { font-size: 12.5px; color: var(--txt); font-weight: 600; line-height: 1.3; flex: 1; }
.db-act-item-meta { font-size: 11px; color: var(--muted); white-space: nowrap; }
.db-act-empty { padding: 28px 20px; text-align: center; color: var(--muted); font-size: 13px; }
.db-act-empty i { display: block; font-size: 26px; color: #ccc; margin-bottom: 8px; }
.db-act-footer {
    padding: 12px 20px;
    border-top: 1px solid var(--bdr);
    background: var(--bg-page);
}
.db-act-footer a {
    font-size: 12.5px; font-weight: 700; color: var(--gold);
    text-decoration: none; display: flex; align-items: center; gap: 6px;
    transition: gap .2s;
}
.db-act-footer a:hover { gap: 10px; }

/* =========================================
   TOP GAINERS / LOSERS
========================================= */
.db-tables-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 28px;
}
.db-table-card {
    background: var(--card-bg);
    border-radius: 14px;
    border: 1px solid var(--bdr);
    overflow: hidden;
}
.db-table-head {
    padding: 16px 20px 12px;
    border-bottom: 1px solid var(--bdr);
    display: flex; align-items: center; gap: 8px;
}
.db-table-head-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.db-table-head-icon.gain { background: rgba(26,184,126,.12); color: var(--success); }
.db-table-head-icon.loss { background: rgba(231,76,60,.1); color: var(--danger); }
.db-table-head-title {
    font-family: 'Rajdhani', sans-serif;
    font-size: 15px; font-weight: 700; color: var(--txt);
}
.db-table-wrap { overflow-x: auto; }
table.db-mini-table { width: 100%; border-collapse: collapse; }
table.db-mini-table thead tr { background: var(--bg-page); }
table.db-mini-table th {
    font-size: 10.5px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .06em;
    padding: 9px 14px; text-align: left;
}
table.db-mini-table td {
    font-size: 13px; color: var(--txt); padding: 11px 14px;
    border-top: 1px solid #f4f6fb;
}
table.db-mini-table td:first-child { font-weight: 700; }
.chg-pos { color: var(--success); font-weight: 700; }
.chg-neg { color: var(--danger); font-weight: 700; }

/* =========================================
   GREEKS TABLE
========================================= */
.db-greeks-card {
    background: var(--card-bg);
    border-radius: 14px;
    border: 1px solid var(--bdr);
    overflow: hidden;
    margin-bottom: 28px;
}
.db-greeks-head {
    padding: 18px 24px;
    border-bottom: 1px solid var(--bdr);
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;
    background: linear-gradient(90deg, #0D1B2A, #162844);
}
.db-greeks-head-left { display: flex; align-items: center; gap: 10px; }
.db-greeks-head h3 {
    font-family: 'Rajdhani', sans-serif;
    font-size: 17px; font-weight: 700; color: #fff;
}
.db-greeks-head p { font-size: 12px; color: rgba(255,255,255,.5); margin-top: 2px; }
.db-greeks-icon {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--gold-dim); color: var(--gold);
    display: flex; align-items: center; justify-content: center; font-size: 16px;
}
form.db-greeks-form { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.db-greeks-form .fg { display: flex; flex-direction: column; gap: 3px; }
.db-greeks-form label { font-size: 10px; color: rgba(255,255,255,.5); font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
.db-greeks-form select {
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
    border-radius: 7px; padding: 7px 26px 7px 10px; font-size: 12.5px; color: #fff;
    font-family: 'Exo 2', sans-serif; outline: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23aaa'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    cursor: pointer; min-width: 120px; transition: border-color .2s;
}
.db-greeks-form select:focus { border-color: var(--gold); }
.db-greeks-form select option { background: #0D1B2A; color: #fff; }
.db-greeks-form .db-filter-btn {
    background: var(--gold); border: none; border-radius: 8px;
    padding: 8px 18px; font-size: 13px; font-weight: 700; color: #000;
    font-family: 'Exo 2', sans-serif; cursor: pointer; transition: background .2s;
    display: flex; align-items: center; gap: 6px;
}
.db-greeks-form .db-filter-btn:hover { background: #d4890e; }
.db-greeks-form .db-reset-btn {
    background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
    border-radius: 8px; padding: 8px 14px; font-size: 13px; color: rgba(255,255,255,.7);
    font-family: 'Exo 2', sans-serif; cursor: pointer; transition: all .2s; text-decoration: none;
    display: flex; align-items: center; gap: 6px;
}
.db-greeks-form .db-reset-btn:hover { background: rgba(255,255,255,.15); color: #fff; }
.db-greeks-table-wrap { overflow-x: auto; }
table.db-greeks-tbl { width: 100%; border-collapse: collapse; font-size: 12.5px; }
table.db-greeks-tbl thead tr { background: #f8faff; }
table.db-greeks-tbl th {
    font-size: 10px; font-weight: 700; color: var(--muted);
    text-transform: uppercase; letter-spacing: .06em;
    padding: 10px 12px; text-align: left; white-space: nowrap;
}
table.db-greeks-tbl td {
    padding: 9px 12px; border-top: 1px solid #f2f5fb;
    color: var(--txt); white-space: nowrap;
}
.smt-bull { background: rgba(26,184,126,.1); color: var(--success); font-weight: 700; border-radius: 4px; padding: 2px 8px; font-size: 11px; }
.smt-bear { background: rgba(231,76,60,.1); color: var(--danger); font-weight: 700; border-radius: 4px; padding: 2px 8px; font-size: 11px; }
.smt-neu  { background: var(--bg-page); color: var(--muted); font-weight: 700; border-radius: 4px; padding: 2px 8px; font-size: 11px; }
.db-greeks-pagination { padding: 14px 20px; border-top: 1px solid var(--bdr); display: flex; justify-content: flex-end; }

/* =========================================
   RESPONSIVE
========================================= */
@media(max-width:1100px) {
    .db-pf-grid { grid-template-columns: repeat(2,1fr); }
}
@media(max-width:960px) {
    .db-stat-grid { grid-template-columns: repeat(2,1fr); }
    .db-charts-row { grid-template-columns: 1fr; }
    .db-activity-row { grid-template-columns: 1fr 1fr; }
    .db-tables-row { grid-template-columns: 1fr; }
}
@media(max-width:768px) {
    :root { --sidebar-w: 0px; }
    .db-sidebar { transform: translateX(-260px); width: 260px; }
    .db-sidebar.open { transform: translateX(0); }
    .db-main { margin-left: 0; }
    .db-hamburger { display: block; }
    .db-content { padding: 20px 16px 50px; }
    .db-stat-grid { grid-template-columns: 1fr 1fr; }
    .db-pf-grid { grid-template-columns: 1fr; }
    .db-activity-row { grid-template-columns: 1fr; }
    .db-welcome { padding: 20px; flex-direction: column; }
    .db-welcome-stats { display: grid; grid-template-columns: repeat(3,1fr); width: 100%; }
    .db-welstat { text-align: left; }
}
@media(max-width:480px) {
    .db-stat-grid { grid-template-columns: 1fr; }
    .db-tables-row { grid-template-columns: 1fr; }
    .db-topbar { padding: 0 16px; }
}
/* Sidebar overlay */
.db-overlay {
    display: none;
    position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 190;
}
.db-overlay.show { display: block; }
</style>

<div class="db-shell">

{{-- ===================== SIDEBAR ===================== --}}
<aside class="db-sidebar" id="dbSidebar">
    <div class="db-sidebar-logo">
        {{-- <img src="{{ getImage('assets/images/logoIcon/logo.png') }}" alt="Logo"> --}}
        <div class="db-sidebar-logo-txt">City<span>Quants</span></div>
    </div>

    {{-- User Card --}}
    <div class="db-user-card">
        <div class="db-user-avatar">
            @if($user->image)
                <img src="{{ getImage(imagePath()['profile']['user']['path'].'/'.$user->image, imagePath()['profile']['user']['size']) }}" alt="{{ $user->fullname }}">
            @else
                {{ strtoupper(substr($user->firstname ?? $user->username, 0, 1)) }}
            @endif
        </div>
        <div>
            <div class="db-user-name">{{ $user->fullname ?? $user->username }}</div>
            <div class="db-user-email">{{ Str::limit($user->email, 24) }}</div>
        </div>
    </div>

    <nav class="db-nav">
        <div class="db-nav-label">Overview</div>
        <a href="{{ route('user.home') }}" class="db-nav-item active">
            <i class="las la-tachometer-alt"></i> Dashboard
        </a>
        <a href="{{ route('user.transactions') }}" class="db-nav-item">
            <i class="las la-exchange-alt"></i> Transactions
        </a>

        <div class="db-nav-label">Portfolios</div>
        <a href="{{ route('user.stock.portfolios') }}" class="db-nav-item">
            <i class="las la-chart-line"></i> Stock Portfolio
        </a>
        <a href="{{ route('user.global.stock.portfolio') }}" class="db-nav-item">
            <i class="las la-globe"></i> Global Stocks
        </a>
        <a href="{{ route('user.fo.portfolio.hedging') }}" class="db-nav-item">
            <i class="las la-shield-alt"></i> F&amp;O Hedging
        </a>
        <a href="{{ route('user.metals.portfolio') }}" class="db-nav-item">
            <i class="las la-coins"></i> Metals (Gold &amp; Silver)
        </a>
        <a href="{{ route('user.thematic.portfolios') }}" class="db-nav-item">
            <i class="las la-layer-group"></i> Thematic Portfolio
        </a>

        <div class="db-nav-label">Learning</div>
        <a href="{{ route('courses.index') }}" class="db-nav-item">
            <i class="las la-graduation-cap"></i> Courses
        </a>
        <a href="{{ route('webinars.index') }}" class="db-nav-item">
            <i class="las la-video"></i> Webinars
        </a>
        <a href="{{ route('events.index') }}" class="db-nav-item">
            <i class="las la-calendar-check"></i> Events
        </a>

        <div class="db-nav-label">Insights</div>
        <a href="{{ route('user.signals') }}" class="db-nav-item">
            <i class="las la-signal"></i> Signals
        </a>
    </nav>

    <div class="db-sidebar-footer">
        <a href="{{ route('user.profile.setting') }}" class="db-nav-item">
            <i class="las la-user-circle"></i> Profile Settings
        </a>
        <a href="{{ route('user.change.password') }}" class="db-nav-item">
            <i class="las la-lock"></i> Change Password
        </a>
        <a href="{{ route('user.logout') }}" class="db-nav-item danger">
            <i class="las la-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>
<div class="db-overlay" id="dbOverlay" onclick="closeSidebar()"></div>

{{-- ===================== MAIN ===================== --}}
<div class="db-main">

    {{-- TOPBAR --}}
    <div class="db-topbar">
        <div class="db-topbar-left">
            <button class="db-hamburger" id="dbHamburger" onclick="toggleSidebar()">
                <span></span><span></span><span></span>
            </button>
            <div class="db-topbar-title">Dashboard</div>
            <div class="db-topbar-date d-none d-md-block">{{ date('D, d M Y') }}</div>
        </div>
        <div class="db-topbar-right">
            <a href="{{ route('user.signals') }}" class="db-topbar-btn" title="Signals">
                <i class="las la-signal"></i>
                <span class="db-notif-dot"></span>
            </a>
            <a href="{{ route('user.transactions') }}" class="db-topbar-btn" title="Transactions">
                <i class="las la-exchange-alt"></i>
            </a>
            <a href="{{ route('user.profile.setting') }}" class="db-topbar-btn" title="Profile">
                <i class="las la-user-circle"></i>
            </a>
        </div>
    </div>

    {{-- CONTENT --}}
    <div class="db-content">

        {{-- WELCOME BANNER --}}
        <div class="db-welcome fade-up">
            <div class="db-welcome-text">
                <h2>Welcome back, <span>{{ $user->firstname ?? $user->username }}</span> 👋</h2>
                <p>Here's your complete investment overview. Track all portfolios, monitor market activity, and stay on top of your learning journey.</p>
            </div>
            <div class="db-welcome-stats">
                <div class="db-welstat">
                    <div class="db-welstat-val">{{ $general->cur_sym }}{{ showAmount($totalInvestedAmount, 0) }}</div>
                    <div class="db-welstat-lbl">Total Invested</div>
                </div>
                <div class="db-welstat">
                    <div class="db-welstat-val">{{ $general->cur_sym }}{{ showAmount($totalCurrentAmount, 0) }}</div>
                    <div class="db-welstat-lbl">Current Value</div>
                </div>
                @php
                    $overallPnl = $totalCurrentAmount - $totalInvestedAmount;
                    $overallPct = $totalInvestedAmount > 0 ? ($overallPnl / $totalInvestedAmount) * 100 : 0;
                @endphp
                <div class="db-welstat">
                    <div class="db-welstat-val" style="color:{{ $overallPnl >= 0 ? '#1ab87e' : '#e74c3c' }}">
                        {{ $overallPnl >= 0 ? '+' : '' }}{{ showAmount($overallPct, 2) }}%
                    </div>
                    <div class="db-welstat-lbl">Overall P&amp;L</div>
                </div>
            </div>
        </div>

        {{-- ── STAT CARDS ── --}}
        <div class="db-section-head fade-up d1"><h3>Learning Activity</h3></div>
        <div class="db-stat-grid fade-up d1">
            <div class="db-stat-card">
                <div class="db-stat-card-top">
                    <div class="db-stat-icon"><i class="las la-graduation-cap"></i></div>
                    <span class="db-stat-badge neu">Enrolled</span>
                </div>
                <div>
                    <div class="db-stat-lbl">Courses Attended</div>
                    <div class="db-stat-val">{{ $totalCourses ?? 0 }}</div>
                    <div class="db-stat-sub">Total enrolled courses</div>
                </div>
            </div>
            <div class="db-stat-card">
                <div class="db-stat-card-top">
                    <div class="db-stat-icon"><i class="las la-video"></i></div>
                    <span class="db-stat-badge neu">Registered</span>
                </div>
                <div>
                    <div class="db-stat-lbl">Webinars Completed</div>
                    <div class="db-stat-val">{{ $totalWebinars ?? 0 }}</div>
                    <div class="db-stat-sub">Past webinars attended</div>
                </div>
            </div>
            <div class="db-stat-card">
                <div class="db-stat-card-top">
                    <div class="db-stat-icon"><i class="las la-calendar-check"></i></div>
                    <span class="db-stat-badge neu">Booked</span>
                </div>
                <div>
                    <div class="db-stat-lbl">Events Attended</div>
                    <div class="db-stat-val">{{ $totalEvents ?? 0 }}</div>
                    <div class="db-stat-sub">Confirmed event bookings</div>
                </div>
            </div>
        </div>

        {{-- ── PORTFOLIO CARDS ── --}}
        <div class="db-section-head fade-up d2"><h3>My Portfolios</h3></div>
        <div class="db-pf-grid fade-up d2">

            @php
                $pfCards = [
                    [
                        'title'   => 'Stock Portfolio',
                        'route'   => route('user.stock.portfolios'),
                        'buy'     => $stockPortFolio->buy_value,
                        'curr'    => $stockPortFolio->current_value,
                        'ico_bg'  => 'rgba(21,101,192,.12)',
                        'ico_clr' => '#1565c0',
                        'icon'    => 'las la-chart-line',
                    ],
                    [
                        'title'   => 'Global Stocks',
                        'route'   => route('user.global.stock.portfolio'),
                        'buy'     => $globalStockPortFolio->buy_value,
                        'curr'    => $globalStockPortFolio->current_value,
                        'ico_bg'  => 'rgba(0,105,92,.12)',
                        'ico_clr' => '#00695c',
                        'icon'    => 'las la-globe',
                    ],
                    [
                        'title'   => 'F&O Portfolio - Hedging',
                        'route'   => route('user.fo.portfolio.hedging'),
                        'buy'     => $foglobalStockPortFolio->buy_value,
                        'curr'    => $foglobalStockPortFolio->current_value,
                        'ico_bg'  => 'rgba(106,27,154,.12)',
                        'ico_clr' => '#6a1b9a',
                        'icon'    => 'las la-shield-alt',
                    ],
                    [
                        'title'   => 'Metals (Gold & Silver)',
                        'route'   => route('user.metals.portfolio'),
                        'buy'     => $metalsPortFolio->buy_value,
                        'curr'    => $metalsPortFolio->current_value,
                        'ico_bg'  => 'rgba(245,166,35,.15)',
                        'ico_clr' => '#b45309',
                        'icon'    => 'las la-coins',
                    ],
                    [
                        'title'   => 'Thematic Portfolio',
                        'route'   => route('user.thematic.portfolios'),
                        'buy'     => 0,
                        'curr'    => 0,
                        'ico_bg'  => 'rgba(0,105,178,.1)',
                        'ico_clr' => '#0069b2',
                        'icon'    => 'las la-layer-group',
                    ],
                    [
                        'title'   => 'Networth',
                        'route'   => route('user.transactions'),
                        'buy'     => $totalInvestedAmount,
                        'curr'    => $totalCurrentAmount,
                        'ico_bg'  => 'rgba(26,184,126,.12)',
                        'ico_clr' => '#1ab87e',
                        'icon'    => 'las la-wallet',
                    ],
                ];
            @endphp

            @foreach($pfCards as $pfc)
            @php
                $pnl = $pfc['curr'] - $pfc['buy'];
                $pnlPct = $pfc['buy'] > 0 ? ($pnl / $pfc['buy']) * 100 : 0;
            @endphp
            <a href="{{ $pfc['route'] }}" class="db-pf-card">
                <div class="db-pf-card-head">
                    <div class="db-pf-card-ico" style="background:{{ $pfc['ico_bg'] }}; color:{{ $pfc['ico_clr'] }};">
                        <i class="{{ $pfc['icon'] }}"></i>
                    </div>
                    <div class="db-pf-card-title">{{ $pfc['title'] }}</div>
                </div>
                <div class="db-pf-card-vals">
                    <div class="db-pf-val-box">
                        <div class="db-pf-val-lbl">Invested</div>
                        <div class="db-pf-val-num">
                            @if($pfc['buy'] > 0)
                                {{ $general->cur_sym }}{{ showAmount($pfc['buy'], 2) }}
                            @else
                                <span style="color:var(--muted);font-size:14px;">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="db-pf-val-box">
                        <div class="db-pf-val-lbl">Current</div>
                        <div class="db-pf-val-num">
                            @if($pfc['curr'] > 0)
                                {{ $general->cur_sym }}{{ showAmount($pfc['curr'], 2) }}
                            @else
                                <span style="color:var(--muted);font-size:14px;">—</span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="db-pf-card-foot">
                    <div class="db-pf-pnl {{ $pnl >= 0 ? 'pos' : 'neg' }}">
                        <i class="las la-{{ $pnl >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                        @if($pfc['buy'] > 0)
                            {{ $pnl >= 0 ? '+' : '' }}{{ showAmount($pnlPct, 2) }}%
                        @else
                            N/A
                        @endif
                    </div>
                    <div class="db-pf-arrow"><i class="las la-angle-right"></i></div>
                </div>
            </a>
            @endforeach
        </div>

        {{-- ── CHARTS ── --}}
        <div class="db-section-head fade-up d3"><h3>Performance Charts</h3></div>
        <div class="db-charts-row fade-up d3">
            <div class="db-chart-card">
                <div class="db-chart-head">
                    <div class="db-chart-title">Networth Graph</div>
                    <span style="font-size:12px;color:var(--muted);">Buy vs Current Value</span>
                </div>
                <div class="db-chart-body">
                    <div id="apex-spline-chart"></div>
                </div>
            </div>
            <div class="db-chart-card">
                <div class="db-chart-head">
                    <div class="db-chart-title">Portfolio Allocation</div>
                </div>
                <div class="db-chart-body">
                    <div id="apex-polar-area-basic-chart"></div>
                </div>
            </div>
        </div>

        {{-- ── LEARNING ACTIVITY ── --}}
        <div class="db-section-head fade-up d3"><h3>Recent Learning</h3></div>
        <div class="db-activity-row fade-up d3">

            {{-- COURSES --}}
            <div class="db-act-card">
                <div class="db-act-head">
                    <div class="db-act-icon" style="background:rgba(21,101,192,.1);color:#1565c0;">
                        <i class="las la-graduation-cap"></i>
                    </div>
                    <div class="db-act-head-title">My Courses</div>
                    <div class="db-act-head-count">{{ $totalCourses ?? 0 }}</div>
                </div>
                <div class="db-act-list">
                    @forelse($recentCourses ?? [] as $ce)
                    <div class="db-act-item">
                        <div class="db-act-dot" style="background:#1565c0;"></div>
                        <div class="db-act-item-title">{{ Str::limit($ce->course->title ?? 'Course', 36) }}</div>
                        <div class="db-act-item-meta">{{ $ce->enrolled_at ? \Carbon\Carbon::parse($ce->enrolled_at)->format('d M') : '' }}</div>
                    </div>
                    @empty
                    <div class="db-act-empty">
                        <i class="las la-graduation-cap"></i>
                        No courses enrolled yet
                    </div>
                    @endforelse
                </div>
                <div class="db-act-footer">
                    <a href="{{ route('courses.index') }}">Browse Courses <i class="las la-arrow-right"></i></a>
                </div>
            </div>

            {{-- WEBINARS --}}
            <div class="db-act-card">
                <div class="db-act-head">
                    <div class="db-act-icon" style="background:rgba(106,27,154,.1);color:#6a1b9a;">
                        <i class="las la-video"></i>
                    </div>
                    <div class="db-act-head-title">My Webinars</div>
                    <div class="db-act-head-count">{{ $totalWebinars ?? 0 }}</div>
                </div>
                <div class="db-act-list">
                    @forelse($recentWebinars ?? [] as $we)
                    <div class="db-act-item">
                        <div class="db-act-dot" style="background:#6a1b9a;"></div>
                        <div class="db-act-item-title">{{ Str::limit($we->webinar->title ?? 'Webinar', 36) }}</div>
                        <div class="db-act-item-meta">{{ $we->enrolled_at ? \Carbon\Carbon::parse($we->enrolled_at)->format('d M') : '' }}</div>
                    </div>
                    @empty
                    <div class="db-act-empty">
                        <i class="las la-video"></i>
                        No webinars registered yet
                    </div>
                    @endforelse
                </div>
                <div class="db-act-footer">
                    <a href="{{ route('webinars.index') }}">Explore Webinars <i class="las la-arrow-right"></i></a>
                </div>
            </div>

            {{-- EVENTS --}}
            <div class="db-act-card">
                <div class="db-act-head">
                    <div class="db-act-icon" style="background:rgba(230,81,0,.1);color:#e65100;">
                        <i class="las la-calendar-check"></i>
                    </div>
                    <div class="db-act-head-title">My Events</div>
                    <div class="db-act-head-count">{{ $totalEvents ?? 0 }}</div>
                </div>
                <div class="db-act-list">
                    @forelse($recentEvents ?? [] as $eb)
                    <div class="db-act-item">
                        <div class="db-act-dot" style="background:#e65100;"></div>
                        <div class="db-act-item-title">{{ Str::limit($eb->event->title ?? 'Event', 36) }}</div>
                        <div class="db-act-item-meta">{{ $eb->created_at ? \Carbon\Carbon::parse($eb->created_at)->format('d M') : '' }}</div>
                    </div>
                    @empty
                    <div class="db-act-empty">
                        <i class="las la-calendar-check"></i>
                        No events booked yet
                    </div>
                    @endforelse
                </div>
                <div class="db-act-footer">
                    <a href="{{ route('events.index') }}">View Events <i class="las la-arrow-right"></i></a>
                </div>
            </div>

        </div>

        {{-- ── TOP GAINERS / LOSERS ── --}}
        <div class="db-section-head fade-up d4"><h3>Market Overview</h3></div>
        <div class="db-tables-row fade-up d4">

            {{-- TOP GAINERS --}}
            <div class="db-table-card">
                <div class="db-table-head">
                    <div class="db-table-head-icon gain"><i class="las la-arrow-up"></i></div>
                    <div class="db-table-head-title">Top Gainers</div>
                </div>
                <div class="db-table-wrap">
                    @php
                        $ltpGainers = [];
                        try {
                            $ltpGainers = \DB::connection('mysql_pr')->table('LTP')
                                ->whereIn('symbol', $symbolArray)->pluck('ltp', 'symbol')->toArray();
                        } catch (\Exception $e) {}
                    @endphp
                    <table class="db-mini-table">
                        <thead><tr>
                            <th>Stock</th><th>Avg Price</th><th>CMP</th><th>Change%</th>
                        </tr></thead>
                        <tbody>
                        @forelse($portfolioTopGainers as $g)
                            @php
                                $cmp = $ltpGainers[$g->stock_name.'.NS'] ?? 0;
                                $chg = $g->avg_buy_price > 0 ? (($cmp - $g->avg_buy_price) / $g->avg_buy_price) * 100 : 0;
                            @endphp
                            <tr>
                                <td>{{ $g->stock_name }}</td>
                                <td>{{ showAmount($g->avg_buy_price) }}</td>
                                <td>{{ showAmount($cmp) }}</td>
                                <td><span class="{{ $chg >= 0 ? 'chg-pos' : 'chg-neg' }}">{{ $chg >= 0 ? '+' : '' }}{{ showAmount($chg, 2) }}%</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:20px;">No data available</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- TOP LOSERS --}}
            <div class="db-table-card">
                <div class="db-table-head">
                    <div class="db-table-head-icon loss"><i class="las la-arrow-down"></i></div>
                    <div class="db-table-head-title">Top Losers</div>
                </div>
                <div class="db-table-wrap">
                    @php
                        $ltpLosers = [];
                        try {
                            $ltpLosers = \DB::connection('mysql_pr')->table('LTP')
                                ->whereIn('symbol', $symbolArray2)->pluck('ltp', 'symbol')->toArray();
                        } catch (\Exception $e) {}
                    @endphp
                    <table class="db-mini-table">
                        <thead><tr>
                            <th>Stock</th><th>Avg Price</th><th>CMP</th><th>Change%</th>
                        </tr></thead>
                        <tbody>
                        @forelse($portfolioTopLosers as $l)
                            @php
                                $cmp2 = $ltpLosers[$l->stock_name.'.NS'] ?? 0;
                                $chg2 = $l->avg_buy_price > 0 ? (($cmp2 - $l->avg_buy_price) / $l->avg_buy_price) * 100 : 0;
                            @endphp
                            <tr>
                                <td>{{ $l->stock_name }}</td>
                                <td>{{ showAmount($l->avg_buy_price) }}</td>
                                <td>{{ showAmount($cmp2) }}</td>
                                <td><span class="{{ $chg2 >= 0 ? 'chg-pos' : 'chg-neg' }}">{{ $chg2 >= 0 ? '+' : '' }}{{ showAmount($chg2, 2) }}%</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:20px;">No data available</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── GREEKS MARKET VIEW ── --}}
        <div class="db-greeks-card fade-up d5" id="pst_hre">
            <div class="db-greeks-head">
                <div class="db-greeks-head-left">
                    <div class="db-greeks-icon"><i class="las la-chart-bar"></i></div>
                    <div>
                        <h3>Greeks Market View</h3>
                        <p>IV &amp; Theta Sentiments — Live Market Data</p>
                    </div>
                </div>
                <form action="" method="GET" class="db-greeks-form">
                    <div class="fg">
                        <label>Symbol</label>
                        <select name="stock_name">
                            <option value="">All Symbols</option>
                            @foreach ($StrengthsymbolArr as $v)
                                @if(in_array($v,['CRUDEOIL','BANKNIFTY','FINNIFTY','SILVER','NIFTY','MIDCPNIFTY','NATURALGAS','GOLD']))
                                    <option value="{{ $v }}" {{ $v == $stock_name ? 'selected' : '' }}>{{ $v }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="fg">
                        <label>Timeframe</label>
                        <select name="timeframe">
                            <option value="5"  {{ $timeframe == "5"  ? "selected" : "" }}>5 min</option>
                            <option value="10" {{ $timeframe == "10" ? "selected" : "" }}>10 min</option>
                            <option value="15" {{ $timeframe == "15" ? "selected" : "" }}>15 min</option>
                        </select>
                    </div>
                    <button type="submit" class="db-filter-btn"><i class="las la-filter"></i> Filter</button>
                    <a href="{{ url('/user/dashboard') }}" class="db-reset-btn"><i class="las la-redo-alt"></i> Reset</a>
                </form>
            </div>

            @if ($stock_name == "")
                @isset($greekSentiments)
                <div class="db-greeks-table-wrap">
                    <table class="db-greeks-tbl">
                        <thead><tr>
                            <th>Date</th><th>Time</th><th>Symbol</th>
                            <th>CE IV Avg</th><th>CE IV Std</th>
                            <th>PE IV Avg</th><th>PE IV Std</th>
                            <th>IV Sentiment</th>
                            <th>CE θ Avg</th><th>CE θ Std</th>
                            <th>PE θ Avg</th><th>PE θ Std</th>
                            <th>Theta Sentiment</th>
                        </tr></thead>
                        <tbody>
                        @forelse($greekSentiments as $data)
                            <tr>
                                <td>{{ $data->date }}</td>
                                <td>{{ $data->timestamp }}</td>
                                <td><strong>{{ $data->symbol }}</strong></td>
                                <td>{{ $data->ce_iv_avg }}</td>
                                <td>{{ $data->ce_iv_std }}</td>
                                <td>{{ $data->pe_iv_avg }}</td>
                                <td>{{ $data->pe_iv_std }}</td>
                                <td>
                                    @php $s = strtolower($data->iv_sentiment ?? ''); @endphp
                                    <span class="{{ str_contains($s,'bull') ? 'smt-bull' : (str_contains($s,'bear') ? 'smt-bear' : 'smt-neu') }}">
                                        {{ $data->iv_sentiment }}
                                    </span>
                                </td>
                                <td>{{ $data->ce_theta_avg }}</td>
                                <td>{{ $data->ce_theta_std }}</td>
                                <td>{{ $data->pe_theta_avg }}</td>
                                <td>{{ $data->pe_theta_std }}</td>
                                <td>
                                    @php $ts = strtolower($data->theta_sentiment ?? ''); @endphp
                                    <span class="{{ str_contains($ts,'bull') ? 'smt-bull' : (str_contains($ts,'bear') ? 'smt-bear' : 'smt-neu') }}">
                                        {{ $data->theta_sentiment }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="13" style="text-align:center;padding:28px;color:var(--muted);">No data found</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="db-greeks-pagination">{{ paginateLinks($greekSentiments) }}</div>
                @endisset
            @else
                @isset($greekSentiments)
                <div class="db-greeks-table-wrap">
                    <table class="db-greeks-tbl">
                        <thead><tr>
                            <th>Date</th><th>Time</th><th>Symbol</th>
                            <th>CE IV Avg</th><th>CE IV Std</th>
                            <th>PE IV Avg</th><th>PE IV Std</th>
                            <th>IV Sentiment</th>
                            <th>CE θ Avg</th><th>CE θ Std</th>
                            <th>PE θ Avg</th><th>PE θ Std</th>
                            <th>Theta Sentiment</th>
                        </tr></thead>
                        <tbody>
                        @php
                            $totalItems  = 0;
                            $itemsPerPage = 100;
                            $currentPage = isset($_GET['pages']) ? (int)$_GET['pages'] : 1;
                        @endphp
                        @forelse($greekSentiments as $sentiment)
                            @php
                                $content      = json_decode($sentiment->data, true);
                                $totalItems   = count($content['date']);
                                $newArr       = array_reverse($content['date'], true);
                                $currentItems = array_slice($newArr, ($currentPage - 1) * $itemsPerPage, $itemsPerPage, true);
                                $timestamp    = $content['timestamp'];
                                $symbol       = $content['symbol'];
                                $ce_iv_avg    = $content['ce_iv_avg'];
                                $ce_iv_std    = $content['ce_iv_std'];
                                $pe_iv_avg    = $content['pe_iv_avg'];
                                $pe_iv_std    = $content['pe_iv_std'];
                                $iv_sentiment = $content['iv_sentiment'];
                                $ce_theta_avg  = $content['ce_theta_avg'];
                                $ce_theta_std  = $content['ce_theta_std'];
                                $pe_theta_avg  = $content['pe_theta_avg'];
                                $pe_theta_std  = $content['pe_theta_std'];
                                $theta_sentiment = $content['theta_sentiment'];
                            @endphp
                            @foreach($currentItems as $k => $item)
                            <tr>
                                <td>{{ date("d-m-Y", ($item/1000)) }}</td>
                                <td>{{ date("H:i:s", $timestamp[$k]) }}</td>
                                <td><strong>{{ $symbol[$k] }}</strong></td>
                                <td>{{ $ce_iv_avg[$k] }}</td>
                                <td>{{ $ce_iv_std[$k] }}</td>
                                <td>{{ $pe_iv_avg[$k] }}</td>
                                <td>{{ $pe_iv_std[$k] }}</td>
                                <td>
                                    @php $s2 = strtolower($iv_sentiment[$k] ?? ''); @endphp
                                    <span class="{{ str_contains($s2,'bull') ? 'smt-bull' : (str_contains($s2,'bear') ? 'smt-bear' : 'smt-neu') }}">
                                        {{ $iv_sentiment[$k] }}
                                    </span>
                                </td>
                                <td>{{ $ce_theta_avg[$k] }}</td>
                                <td>{{ $ce_theta_std[$k] }}</td>
                                <td>{{ $pe_theta_avg[$k] }}</td>
                                <td>{{ $pe_theta_std[$k] }}</td>
                                <td>
                                    @php $ts2 = strtolower($theta_sentiment[$k] ?? ''); @endphp
                                    <span class="{{ str_contains($ts2,'bull') ? 'smt-bull' : (str_contains($ts2,'bear') ? 'smt-bear' : 'smt-neu') }}">
                                        {{ $theta_sentiment[$k] }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="13" style="text-align:center;padding:28px;color:var(--muted);">No data found</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                @php $totalPages = ceil($totalItems / $itemsPerPage); @endphp
                <div class="db-greeks-pagination">
                    <nav><ul class="pagination mb-0">
                        @for($i = 1; $i <= $totalPages; $i++)
                            @if($i == $currentPage)
                                <li class="page-item active"><span class="page-link">{{ $i }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ url('user/dashboard?stock_name='.$stock_name.'&timeframe='.$timeframe.'&pages='.$i) }}">{{ $i }}</a></li>
                            @endif
                        @endfor
                    </ul></nav>
                </div>
                @endisset
            @endif
        </div>

    </div>{{-- /.db-content --}}
</div>{{-- /.db-main --}}
</div>{{-- /.db-shell --}}

@if($user->package_id)
<div class="modal fade cmn--modal" id="renewModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title method-name"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('user.renew.package') }}" method="post">
                @csrf
                <div class="modal-body pt-0">
                    <input type="hidden" name="id">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">@lang('Product') <span class="packageName"></span></li>
                        <li class="list-group-item d-flex justify-content-between">@lang('Price') <span class="packagePrice"></span></li>
                        <li class="list-group-item d-flex justify-content-between">@lang('Validity') <span class="packageValidity"></span></li>
                        <li class="list-group-item d-flex justify-content-between">@lang('Your Balance')
                            <span>{{ showAmount($user->balance, 2) }} {{ __($general->cur_text) }}</span>
                        </li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--danger btn-sm" data-bs-dismiss="modal">@lang('Close')</button>
                    <button type="submit" class="btn btn-sm btn--success">@lang('Confirm')</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection

@push('script')
<script src="{{ asset('assets/admin/js/vendor/apexcharts.min.js') }}"></script>
<script>
"use strict";

/* ── SIDEBAR TOGGLE ── */
function toggleSidebar() {
    document.getElementById('dbSidebar').classList.toggle('open');
    document.getElementById('dbOverlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('dbSidebar').classList.remove('open');
    document.getElementById('dbOverlay').classList.remove('show');
}

/* ── ACTIVE NAV ── */
(function(){
    var path = window.location.pathname;
    document.querySelectorAll('.db-nav-item').forEach(function(a){
        if(a.href && a.href.indexOf(path) > -1 && path !== '/') {
            document.querySelectorAll('.db-nav-item').forEach(function(x){ x.classList.remove('active'); });
            a.classList.add('active');
        }
    });
})();

/* ── RENEW MODAL ── */
@if($user->package_id != 0)
    document.querySelectorAll('.renewBtn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var pkg = this.dataset.package ? JSON.parse(this.dataset.package) : {};
            var modal = document.getElementById('renewModal');
            if(!modal) return;
            modal.querySelector('.modal-title').textContent = 'Renew ' + (pkg.name||'');
            modal.querySelector('.packageName').textContent = pkg.name||'';
            modal.querySelector('.packagePrice').textContent = (pkg.price||'') + ' ' + @json(__($general->cur_text));
            modal.querySelector('.packageValidity').textContent = (pkg.validity||'') + ' Days';
            modal.querySelector('input[name=id]').value = pkg.id||'';
            var bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        });
    });
@endif

/* ── APEX: NETWORTH SPLINE ── */
var splineOpts = {
    series: [
        { name: 'Buy Value',     data: [{!! implode(",", $buyArr)  !!}] },
        { name: 'Current Value', data: [{!! implode(",", $currArr) !!}] }
    ],
    chart: { type: 'area', height: 300, toolbar: { show: false }, background: 'transparent' },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
    colors: ['#7DFF00', '#1ab87e'],
    xaxis: {
        type: 'category',
        categories: [{!! "'" . implode("','", $datesArr) . "'" !!}],
        labels: { style: { colors: '#999', fontSize: '11px' } }
    },
    yaxis: { labels: { style: { colors: '#999', fontSize: '11px' } } },
    tooltip: { theme: 'dark', x: { format: 'MM yyyy' } },
    legend: { labels: { colors: '#666' } },
    grid: { borderColor: '#f0f0f0' }
};
new ApexCharts(document.querySelector("#apex-spline-chart"), splineOpts).render();

/* ── APEX: POLAR AREA ── */
var polarOpts = {
    series: [{!! implode(',', $chrtArr) !!}],
    chart: { type: 'polarArea', height: 300, toolbar: { show: false }, background: 'transparent' },
    labels: ['Stock', 'Metals', 'Global', 'F&O'],
    colors: ['#1565c0', '#b45309', '#00695c', '#6a1b9a'],
    stroke: { colors: ['#fff'], width: 2 },
    fill: { opacity: 0.85 },
    legend: { labels: { colors: '#666' } },
    responsive: [{ breakpoint: 480, options: { chart: { width: 220 }, legend: { position: 'bottom' } } }]
};
new ApexCharts(document.querySelector("#apex-polar-area-basic-chart"), polarOpts).render();
</script>
@endpush