@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ═══════════════════════════════════════════════════════════════════
   AUROPHARMA QUANT ENGINE v3
   Forces light background everywhere — overrides dark theme
═══════════════════════════════════════════════════════════════════ */

/* ── Force light base on the content area ────────────────────── */
.content-container, .content-area, section.pt-50 {
    background: #f4f6fb !important;
}

/* ── Global text fix: ensure everything is readable ─────────── */
.aq-page { font-family: 'DM Sans', -apple-system, sans-serif; color: #1e293b; }
.aq-page * { box-sizing: border-box; }

/* ── Page header ──────────────────────────────────────────────── */
.aq-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 14px; padding: 20px 24px; margin-bottom: 18px;
    box-shadow: 0 8px 32px rgba(102,126,234,.3);
}
.aq-header h4 { color: #fff !important; margin: 0 0 4px; font-size: 19px; font-weight: 700; }
.aq-header p  { color: rgba(255,255,255,.8) !important; margin: 0; font-size: 12px; }
.new-badge { background: linear-gradient(135deg,#f093fb,#f5576c); color: #fff !important; padding: 2px 7px; border-radius: 4px; font-size: 9px; font-weight: 700; margin-left: 6px; vertical-align: middle; }

/* ── Logic strip ──────────────────────────────────────────────── */
.aq-logic {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px; padding: 14px 18px; margin-bottom: 16px;
}
.aq-logic h6 { color: #fff !important; font-size: 12px; font-weight: 700; margin-bottom: 8px; }
.aq-logic ul  { font-size: 10px; color: rgba(255,255,255,.85) !important; margin: 4px 0 0; padding-left: 14px; }
.aq-logic small { color: #fff !important; font-size: 11px; font-weight: 700; display: block; margin-bottom: 3px; }

/* ── Stats strip ──────────────────────────────────────────────── */
.aq-stat {
    background: #fff; border-radius: 12px; padding: 14px 16px;
    border-left: 4px solid #667eea;
    box-shadow: 0 2px 12px rgba(0,0,0,.08);
    margin-bottom: 14px;
}
.aq-stat small  { display: block; color: #64748b; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.aq-stat strong { display: block; font-size: 1.4rem; font-weight: 700; color: #1e293b; }
.aq-stat span.sub { display: block; font-size: 10px; color: #94a3b8; margin-top: 2px; }

/* ── Two-panel main row ───────────────────────────────────────── */
.aq-card {
    background: #fff; border-radius: 14px;
    box-shadow: 0 2px 16px rgba(0,0,0,.08);
    overflow: hidden; margin-bottom: 18px;
}
.aq-card-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 18px; border-bottom: 1px solid #f1f5f9;
    background: #fafbff;
}
.aq-card-title {
    font-size: 11px; font-weight: 700; color: #64748b;
    text-transform: uppercase; letter-spacing: .6px; margin: 0;
}
.aq-card-body { padding: 18px; }

/* ── Verdict card ──────────────────────────────────────────────── */
.verdict-direction { font-size: 34px; font-weight: 800; line-height: 1; margin-bottom: 6px; }
.verdict-direction.bull { color: #16a34a; }
.verdict-direction.bear { color: #dc2626; }
.verdict-direction.wait { color: #64748b; }
.verdict-card-wrap { border: 2px solid #e2e8f0; border-radius: 12px; }
.verdict-card-wrap.bull { border-color: #16a34a; box-shadow: 0 4px 20px rgba(22,163,74,.12); }
.verdict-card-wrap.bear { border-color: #dc2626; box-shadow: 0 4px 20px rgba(220,38,38,.12); }

.verdict-score-bar { height: 8px; background: #f1f5f9; border-radius: 4px; margin: 10px 0; overflow: hidden; }
.verdict-score-fill { height: 100%; border-radius: 4px; transition: width .7s ease; }

/* ── Signal bars ──────────────────────────────────────────────── */
.sig-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f8fafc; }
.sig-row:last-child { border-bottom: none; }
.sig-label { font-size: 11px; color: #64748b; width: 140px; flex-shrink: 0; font-weight: 600; }
.sig-track { flex: 1; height: 6px; background: #f1f5f9; border-radius: 3px; position: relative; overflow: hidden; }
.sig-center { position: absolute; left: 50%; top: 0; width: 1px; height: 100%; background: #cbd5e1; }
.sig-fill-pos { position: absolute; left: 50%; top: 0; height: 100%; background: #16a34a; border-radius: 0 3px 3px 0; transition: width .5s; }
.sig-fill-neg { position: absolute; right: 50%; top: 0; height: 100%; background: #dc2626; border-radius: 3px 0 0 3px; transition: width .5s; }
.sig-score { font-size: 12px; font-weight: 700; width: 38px; text-align: right; flex-shrink: 0; }
.sig-score.pos { color: #16a34a; }
.sig-score.neg { color: #dc2626; }
.sig-score.neu { color: #94a3b8; }
.sig-verdict { font-size: 9px; color: #94a3b8; width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-shrink: 0; }

/* ── Context mini blocks ───────────────────────────────────────── */
.ctx-blk { background: #f8fafc; border-radius: 8px; padding: 8px 10px; text-align: center; border: 1px solid #e2e8f0; }
.ctx-blk small { display: block; font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 2px; }
.ctx-blk strong { display: block; font-size: 11px; font-weight: 700; color: #1e293b; }

/* ── Risk box ──────────────────────────────────────────────────── */
.risk-blk { background: #fffbeb; border: 1px solid #fbbf24; border-radius: 8px; padding: 10px 12px; margin-top: 10px; }
.risk-blk small { font-size: 9px; color: #92400e; text-transform: uppercase; letter-spacing: .4px; display: block; }
.risk-blk strong { font-size: 16px; font-weight: 800; color: #92400e; }

/* ── Filter bar ────────────────────────────────────────────────── */
.aq-filter {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px; padding: 18px 20px; margin-bottom: 16px;
    box-shadow: 0 4px 20px rgba(102,126,234,.3);
}
.aq-filter label { color: #fff !important; font-size: 12px; font-weight: 600; display: block; margin-bottom: 5px; }
.aq-filter .form-control {
    background: rgba(255,255,255,.93) !important;
    border: 1.5px solid rgba(255,255,255,.4) !important;
    color: #1e293b !important; font-size: 12px; border-radius: 7px;
}
.aq-filter-stats { display: flex; gap: 20px; font-size: 12px; color: rgba(255,255,255,.8); margin-top: 10px; flex-wrap: wrap; }
.aq-filter-stats b { color: #fff; }

/* ── Badges ────────────────────────────────────────────────────── */
.b-buy-ce  { background: linear-gradient(135deg,#16a34a,#15803d); color:#fff; padding:3px 9px; border-radius:5px; font-weight:700; font-size:10px; display:inline-block; white-space:nowrap; }
.b-buy-pe  { background: linear-gradient(135deg,#dc2626,#b91c1c); color:#fff; padding:3px 9px; border-radius:5px; font-weight:700; font-size:10px; display:inline-block; white-space:nowrap; }
.b-wait    { background: linear-gradient(135deg,#d97706,#b45309); color:#fff; padding:3px 9px; border-radius:5px; font-weight:700; font-size:10px; display:inline-block; white-space:nowrap; }
.b-vh      { background: #14532d; color:#fff; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-h       { background: #16a34a; color:#fff; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-m       { background: #d97706; color:#fff; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-l       { background: #e2e8f0; color:#475569; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-nt      { background: #f1f5f9; color:#94a3b8; padding:2px 7px; border-radius:4px; font-weight:600; font-size:9px; display:inline-block; }
.b-win     { background: #dcfce7; color:#15803d; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-loss    { background: #fee2e2; color:#b91c1c; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-veto    { background: #fffbeb; color:#92400e; border:1px solid #fbbf24; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-miss    { background: #fef3c7; color:#92400e; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-oilb    { background: #dcfce7; color:#15803d; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-oisc    { background: #dbeafe; color:#1d4ed8; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-oisb    { background: #fee2e2; color:#b91c1c; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-oilu    { background: #fef3c7; color:#92400e; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

/* ── NEW: Flow / Spike / Score / Conf badges ───────────────────── */
.flow-strong-bull { background:linear-gradient(135deg,#1b5e20,#2e7d32); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
.flow-strong-bear { background:linear-gradient(135deg,#7f0000,#b71c1c); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
.flow-continuation{ background:linear-gradient(135deg,#1565c0,#1976d2); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
.flow-reversal    { background:linear-gradient(135deg,#e65100,#f57c00); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
.flow-trap        { background:linear-gradient(135deg,#4a148c,#6a1b9a); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
.flow-mixed       { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

.spike-dual { background:linear-gradient(135deg,#880e4f,#ad1457); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
.spike-ce   { background:linear-gradient(135deg,#bf360c,#d84315); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }
.spike-pe   { background:linear-gradient(135deg,#1a237e,#283593); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; white-space:nowrap; }

.conf-high     { background:linear-gradient(135deg,#1b5e20,#2e7d32); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.conf-medium   { background:linear-gradient(135deg,#e65100,#f57c00); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.conf-low      { background:#e9ecef; color:#495057; padding:3px 8px; border-radius:4px; font-size:9px; display:inline-block; }
.conf-conflict { background:linear-gradient(135deg,#4a148c,#6a1b9a); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.conf-none     { color:#aaa; font-size:9px; }

.score-cell-pos { color:#1b5e20; font-weight:800; font-size:12px; }
.score-cell-neg { color:#7f0000; font-weight:800; font-size:12px; }
.score-cell-neu { color:#888; }

.flow-t-data { font-size:9px; color:#888; white-space:nowrap; }
.flow-t-pos  { color:#28a745; font-weight:600; }
.flow-t-neg  { color:#dc3545; font-weight:600; }

/* ── Score text ────────────────────────────────────────────────── */
.s-pos { color: #16a34a; font-weight: 700; }
.s-neg { color: #dc2626; font-weight: 700; }
.s-neu { color: #94a3b8; }
.p-pos { color: #16a34a; font-weight: 700; }
.p-neg { color: #dc2626; font-weight: 700; }

/* ── History table ─────────────────────────────────────────────── */
.aq-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.aq-table { width: 100%; border-collapse: collapse; font-size: 11px; min-width: 1800px; }
.aq-table thead th {
    background: #f8fafc; color: #475569; font-size: 9.5px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    padding: 9px 8px; border-bottom: 2px solid #e2e8f0; text-align: center;
    white-space: nowrap; position: sticky; top: 0; z-index: 5;
}
.aq-table thead th:nth-child(1) { position: sticky; left:0; z-index:15; }
.aq-table thead th:nth-child(2) { position: sticky; left:36px; z-index:15; }
.aq-table thead th:nth-child(3) { position: sticky; left:110px; z-index:15; }
.aq-table tbody td {
    padding: 7px 8px; border-bottom: 1px solid #f1f5f9;
    text-align: center; color: #1e293b; background: #fff; vertical-align: middle; white-space: nowrap;
}
.aq-table tbody td:nth-child(1) { position: sticky; left:0; z-index:5; background:#fff; }
.aq-table tbody td:nth-child(2) { position: sticky; left:36px; z-index:5; background:#fff; }
.aq-table tbody td:nth-child(3) { position: sticky; left:110px; z-index:5; background:#fff; }
.aq-table tbody tr:hover td { background: #f8fafc !important; }
.aq-table tbody tr.row-ce td  { background: #f0fdf4 !important; }
.aq-table tbody tr.row-ce td:nth-child(1),
.aq-table tbody tr.row-ce td:nth-child(2),
.aq-table tbody tr.row-ce td:nth-child(3) { background: #f0fdf4 !important; }
.aq-table tbody tr.row-pe td  { background: #fff5f5 !important; }
.aq-table tbody tr.row-pe td:nth-child(1),
.aq-table tbody tr.row-pe td:nth-child(2),
.aq-table tbody tr.row-pe td:nth-child(3) { background: #fff5f5 !important; }

/* ── Action btns (modal triggers) ─────────────────────────────── */
.aq-action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer;
    font-size: 12px; font-weight: 600; transition: all .2s;
}
.aq-action-btn.green  { background: #16a34a; color: #fff; }
.aq-action-btn.purple { background: linear-gradient(135deg,#667eea,#764ba2); color: #fff; }
.aq-action-btn.blue   { background: #2563eb; color: #fff; }
.aq-action-btn.amber  { background: #d97706; color: #fff; }
.aq-action-btn:hover  { opacity: .88; transform: translateY(-1px); }

/* ── Modal overrides (force light) ────────────────────────────── */
.modal-content { background: #fff !important; color: #1e293b !important; border-radius: 14px !important; border: none !important; }
.modal-header  { background: linear-gradient(135deg,#667eea,#764ba2) !important; border-radius: 13px 13px 0 0 !important; padding: 14px 20px !important; border: none !important; }
.modal-header .modal-title, .modal-header button { color: #fff !important; }
.modal-header button.close { opacity: 1 !important; text-shadow: none !important; }
.modal-body   { padding: 20px !important; background: #fff !important; }
.modal-footer { background: #f8fafc !important; border-top: 1px solid #e2e8f0 !important; border-radius: 0 0 13px 13px !important; }

/* ── Smart money ───────────────────────────────────────────────── */
.sm-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.sm-table th { background: #f8fafc; color: #475569; font-size: 9px; font-weight: 700; text-transform: uppercase; padding: 6px 8px; border-bottom: 2px solid #e2e8f0; text-align: center; }
.sm-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #1e293b; }
.sm-table tr.bear-row { background: rgba(220,38,38,.05); border-left: 3px solid #dc2626; }
.sm-table tr.bull-row { background: rgba(22,163,74,.05); border-left: 3px solid #16a34a; }
.b-accum-b { background: #fee2e2; color: #b91c1c; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; }
.b-accum-g { background: #dcfce7; color: #15803d; padding: 2px 6px; border-radius: 3px; font-size: 9px; font-weight: 700; }

/* ── OI snapshot ───────────────────────────────────────────────── */
.oi-snap-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.oi-snap-table th { background: #f8fafc; color: #475569; font-size: 9px; font-weight: 700; text-transform: uppercase; padding: 7px 8px; border-bottom: 2px solid #e2e8f0; text-align: center; }
.oi-snap-table td { padding: 5px 8px; border-bottom: 1px solid #f1f5f9; text-align: center; color: #1e293b; }
.oi-snap-table tr.atm-row td { background: rgba(102,126,234,.08); font-weight: 700; color: #667eea; }
.oi-bar { height: 5px; border-radius: 2px; display: inline-block; vertical-align: middle; max-width: 80px; }

/* ── Backtest stats ────────────────────────────────────────────── */
.bt-stat { background: #f8fafc; border-radius: 10px; padding: 14px; text-align: center; border-top: 3px solid #667eea; }
.bt-stat small { display: block; font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
.bt-stat strong { display: block; font-size: 1.3rem; font-weight: 700; color: #1e293b; }

/* ── Enter result form ─────────────────────────────────────────── */
.re-label { font-size: 11px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; display: block; }
.re-input { border: 1.5px solid #e2e8f0; border-radius: 7px; padding: 8px 12px; width: 100%; font-size: 12px; color: #1e293b; background: #fff; margin-bottom: 12px; outline: none; transition: border-color .2s; }
.re-input:focus { border-color: #667eea; }

/* ── Spinner / loading ─────────────────────────────────────────── */
.aq-loader { display: flex; align-items: center; justify-content: center; flex-direction: column; padding: 30px; }
.aq-spinner { width: 36px; height: 36px; border: 3px solid #e2e8f0; border-top-color: #667eea; border-radius: 50%; animation: aqspin .8s linear infinite; }
@keyframes aqspin { to { transform: rotate(360deg); } }
.aq-loader-text { color: #667eea; font-size: 12px; font-weight: 600; margin-top: 10px; }

/* ── Date inputs in cards ──────────────────────────────────────── */
.aq-date { border: 1.5px solid #e2e8f0; border-radius: 7px; padding: 5px 10px; font-size: 11px; color: #1e293b; background: #fff; outline: none; }
.aq-date:focus { border-color: #667eea; }
.aq-select { border: 1.5px solid #e2e8f0; border-radius: 7px; padding: 5px 10px; font-size: 11px; color: #1e293b; background: #fff; }

/* ── Section header ────────────────────────────────────────────── */
.aq-sec-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.aq-sec-title { font-size: 13px; font-weight: 700; color: #1e293b; }

/* ── Responsive ────────────────────────────────────────────────── */
@media(max-width:768px) {
    .aq-stat strong { font-size: 1.1rem; }
    .modal-dialog { margin: 10px; }
}

/* ── v4 additions ─────────────────────────────────────────────── */
.b-range-wait { background: #E6F1FB; color: #0C447C; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-recovery   { background: linear-gradient(135deg,#639922,#3B6D11); color:#fff; padding:2px 7px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
.b-normal-c   { background: #F1EFE8; color:#444441; padding:2px 7px; border-radius:4px; font-size:9px; display:inline-block; }
 
.range-banner {
    background: #E6F1FB; border: 1px solid #378ADD; border-radius:8px;
    padding:8px 12px; margin-top:8px; font-size:11px; color:#0C447C;
    display:flex; align-items:center; gap:8px;
}
.range-banner strong { color:#0C447C; }
 
.recovery-banner {
    background: #EAF3DE; border: 1px solid #639922; border-radius:8px;
    padding:8px 12px; margin-top:8px; font-size:11px; color:#3B6D11;
    display:flex; align-items:center; gap:8px;
}
 
.divergence-pos { color:#16a34a; font-weight:700; font-size:10px; }
.divergence-neg { color:#dc2626; font-weight:700; font-size:10px; }
.divergence-neu { color:#94a3b8; font-size:10px; }

</style>
@endpush

@section('content')
<div class="aq-page">
<section class="pt-30 pb-50">
<div class="container-fluid content-container">

    {{-- ── HEADER ────────────────────────────────────────────────── --}}
    <div class="aq-header d-flex justify-content-between align-items-center flex-wrap" style="gap:12px">
        <div>
            <h4>Auropharma Quant Engine <span class="new-badge">v2 FIXED</span></h4>
            <p>5-Signal System · 3PM Entry · Morning Exit · Price-Confirmed OI · Dynamic Threshold · Risk Layer</p>
        </div>
        <div class="d-flex" style="gap:8px">
            <button class="aq-action-btn green" onclick="refreshAll()"><i class="fas fa-sync-alt"></i> Refresh</button>
            <a href="{{ route('oiiv-auto.index') }}" class="aq-action-btn purple"><i class="fas fa-chart-line"></i> OI+IV</a>
        </div>
    </div>

    {{-- ── LOGIC STRIP ────────────────────────────────────────────── --}}
    <div class="aq-logic">
        <h6><i class="fas fa-info-circle"></i> v2 Fixed Signal Logic</h6>
        <div class="row">
            <div class="col-md-3"><small>📊 Signal A — OI + PRICE (Fixed)</small><ul>
                <li>PE↑ + Price↑ = Put Writing = BULLISH</li>
                <li>PE↑ + Price↓ = Fear Buying = BEARISH</li>
                <li>CE↑ + Price↓ = Call Writing = BEARISH</li>
            </ul></div>
            <div class="col-md-3"><small>🕵️ Signal B — Smart Money</small><ul>
                <li>ATM-3/4/5 PE accumulating 2+ days</li>
                <li>Volume ≥ 1.3× avg required</li>
                <li>Confidence boost: +15%</li>
            </ul></div>
            <div class="col-md-3"><small>⚖️ Signal Weights</small><ul>
                <li>D (Market align) × 1.5</li>
                <li>B (Smart money) × 1.3</li>
                <li>A (OI pressure) × 1.0</li>
                <li>C (Price struct) × 0.8</li>
            </ul></div>
            <div class="col-md-3"><small>📏 Dynamic Threshold</small><ul>
                <li>Vol > 35% → threshold 8</li>
                <li>Vol 25-35% → threshold 7</li>
                <li>Vol 15-25% → threshold 6</li>
                <li>Vol < 15% → threshold 5</li>
            </ul></div>
        </div>
    </div>

    {{-- ── STATS ROW ──────────────────────────────────────────────── --}}
    <div class="row mb-2">
        <div class="col-6 col-md-2"><div class="aq-stat" style="border-left-color:#16a34a">
            <small>Win Rate (60d)</small>
            <strong style="color:#16a34a">{{ $stats['winRate'] }}%</strong>
            <span class="sub">{{ $stats['wins'] }}W / {{ $stats['losses'] }}L</span>
        </div></div>
        <div class="col-6 col-md-2"><div class="aq-stat" style="border-left-color:#667eea">
            <small>Trades Taken</small>
            <strong style="color:#667eea">{{ $stats['total'] }}</strong>
            <span class="sub">NO TRADE: {{ $stats['noTrade'] }}</span>
        </div></div>
        <div class="col-6 col-md-2"><div class="aq-stat" style="border-left-color:#d97706">
            <small>Avg Win %</small>
            <strong style="color:#d97706">{{ number_format($stats['avgWin'],1) }}%</strong>
            <span class="sub">Loss: {{ number_format($stats['avgLoss'],1) }}%</span>
        </div></div>
        <div class="col-6 col-md-2"><div class="aq-stat" style="border-left-color:#7c3aed">
            <small>Risk / Reward</small>
            <strong style="color:#7c3aed">{{ $stats['rr'] }}x</strong>
        </div></div>
        <div class="col-6 col-md-2"><div class="aq-stat" style="border-left-color:#0891b2">
            <small>Regime</small>
            <strong style="color:#0891b2;font-size:1rem" id="chip-regime">—</strong>
        </div></div>
        <div class="col-6 col-md-2"><div class="aq-stat" style="border-left-color:#ea580c">
            <small>Vol 5d</small>
            <strong style="color:#ea580c;font-size:1rem" id="chip-vol">—</strong>
        </div></div>
    </div>

    {{-- ── MAIN PANEL: Verdict + Signals ─────────────────────────── --}}
    <div class="row mb-3">
        {{-- TODAY'S VERDICT --}}
        <div class="col-md-4">
            <div class="aq-card" style="height:100%">
                <div class="aq-card-head">
                    <span class="aq-card-title">Today's Verdict</span>
                    <div class="d-flex align-items-center" style="gap:8px">
                        <small id="verdict-time" style="color:#94a3b8;font-size:10px">—</small>
                        <button class="btn btn-outline-secondary btn-sm" style="padding:2px 8px;font-size:10px" onclick="loadTodayVerdict()">↻</button>
                    </div>
                </div>
                <div class="aq-card-body" id="verdict-body">
                    <div class="aq-loader"><div class="aq-spinner"></div><div class="aq-loader-text">Loading...</div></div>
                </div>
            </div>
        </div>

        {{-- SIGNAL BREAKDOWN --}}
        <div class="col-md-8">
            <div class="aq-card" style="height:100%">
                <div class="aq-card-head">
                    <span class="aq-card-title">Signal Breakdown</span>
                    <input type="date" id="signal-date" class="aq-date" value="{{ now()->format('Y-m-d') }}" onchange="loadSignals(this.value)">
                </div>
                <div class="aq-card-body" id="signals-body">
                    <div class="aq-loader"><div class="aq-spinner"></div></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── FILTER + ACTION BUTTONS ────────────────────────────────── --}}
    <div class="aq-filter">
        <div class="row mb-2 align-items-end">
            <div class="col-md-3">
                <label>From Date</label>
                <input type="date" id="h-from" class="form-control" value="{{ now()->subDays(60)->format('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label>To Date</label>
                <input type="date" id="h-to" class="form-control" value="{{ now()->format('Y-m-d') }}">
            </div>
            <div class="col-md-3">
                <label>Filter</label>
                <select id="h-filter" class="form-control">
                    <option value="ALL">All Verdicts</option>
                    <option value="TRADED">Traded Only</option>
                    <option value="NO_TRADE">No Trade</option>
                    <option value="CORRECT">Correct</option>
                    <option value="WRONG">Wrong</option>
                    <option value="HIGH_CONF">High Confidence</option>
                </select>
            </div>
            <div class="col-md-3 d-flex" style="gap:8px">
                <button class="btn btn-light btn-lg flex-fill" onclick="loadHistory()"><i class="fas fa-search"></i> Load</button>
                <button class="btn btn-outline-light btn-lg flex-fill" onclick="resetFilters()"><i class="fas fa-undo"></i> Reset</button>
            </div>
        </div>
        <div class="d-flex" style="gap:10px;margin-top:10px;flex-wrap:wrap">
            <button class="aq-action-btn green" onclick="showModal('smart-money')"><i class="fas fa-eye"></i> Smart Money</button>
            <button class="aq-action-btn blue"  onclick="showModal('oi-snapshot')"><i class="fas fa-table"></i> OI Snapshot</button>
            <button class="aq-action-btn amber" onclick="showModal('backtest')"><i class="fas fa-chart-bar"></i> Backtest Stats</button>
            <button class="aq-action-btn purple" onclick="showModal('enter-result')"><i class="fas fa-edit"></i> Enter Result</button>
        </div>
        <div class="aq-filter-stats">
            <span>Trades: <b id="h-total">—</b></span>
            <span>Win Rate: <b id="h-wr">—</b></span>
            <span>Avg Win: <b id="h-aw">—</b></span>
            <span>R/R: <b id="h-rr">—</b></span>
        </div>
    </div>

    {{-- ── HISTORY TABLE ───────────────────────────────────────────── --}}
    <div class="aq-card" style="position:relative">
        <div class="aq-card-head">
            <span class="aq-card-title">Verdict History — click any row to load signal breakdown</span>
            <small style="color:#94a3b8;font-size:10px" id="hist-count">0 records</small>
        </div>
        <div id="history-loading" style="display:none" class="aq-loader"><div class="aq-spinner"></div><div class="aq-loader-text">Loading history...</div></div>
        <div class="aq-table-wrap">
            <table class="aq-table">
                <thead><tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Direction</th>
                    <th>Score</th>
                    <th>Confidence</th>
                    <th>Strike</th>
                    <th>Entry ₹</th>
                    <th>Sig A</th>
                    <th>A Verdict</th>
                    <th>Sig B</th>
                    <th>B Days</th>
                    <th>Sig C</th>
                    <th>OI Type</th>
                    <th>Sig D</th>
                    <th>Nifty</th>
                    <th>Regime</th>
                    <th>Veto</th>
                    {{-- NEW COLUMNS --}}
                    <th>Flow<br><small style="font-weight:400;opacity:.7">T-2→T</small></th>
                    <th>Spike<br><small style="font-weight:400;opacity:.7">CE/PE</small></th>
                    <th>Score<br><small style="font-weight:400;opacity:.7">±</small></th>
                    <th>Conf</th>
                    <th>T-1 CE%<br><small style="font-weight:400;opacity:.7">prev day</small></th>
                    <th>T-1 PE%<br><small style="font-weight:400;opacity:.7">prev day</small></th>
                    <th>Gap<br><small style="font-weight:400;opacity:.7">|CE%-PE%|</small></th>
                    {{-- END NEW COLUMNS --}}
                    <th>Result %</th>
                    <th>✓/✗</th>
                    <th>Miss</th>
                    <th>SL %</th>
                    <th>TGT %</th>
                </tr></thead>
                <tbody id="history-tbody"><tr>
                    <td colspan="29" style="padding:40px;text-align:center;color:#94a3b8">
                        <i class="fas fa-chart-bar" style="font-size:2rem;opacity:.3;display:block;margin-bottom:10px"></i>
                        Click <strong>Load</strong> to fetch history
                    </td>
                </tr></tbody>
            </table>
        </div>
    </div>

</div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     MODAL: SMART MONEY MONITOR
══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modal-smart-money" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">🕵️ Smart Money Monitor — Far OTM Accumulation</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center" style="gap:12px;margin-bottom:16px">
                    <label style="margin:0;font-size:12px;font-weight:600;color:#475569">Date:</label>
                    <input type="date" id="sm-date" class="aq-date" value="{{ now()->format('Y-m-d') }}" onchange="loadSmartMoney(this.value)">
                    <button class="aq-action-btn green" onclick="loadSmartMoney(document.getElementById('sm-date').value)">Load</button>
                </div>
                <div id="sm-alerts" class="mb-3"></div>
                <div class="aq-table-wrap"><div id="sm-table-wrap"></div></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     MODAL: OI SNAPSHOT
══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modal-oi-snapshot" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📊 OI Snapshot — All Strikes</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center" style="gap:12px;margin-bottom:16px">
                    <label style="margin:0;font-size:12px;font-weight:600;color:#475569">Date:</label>
                    <input type="date" id="oi-date" class="aq-date" value="{{ now()->format('Y-m-d') }}" onchange="loadOISnapshot(this.value)">
                    <button class="aq-action-btn blue" onclick="loadOISnapshot(document.getElementById('oi-date').value)">Load</button>
                </div>
                <div id="oi-snapshot-body">
                    <div class="aq-loader"><div class="aq-spinner"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     MODAL: BACKTEST STATS
══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modal-backtest" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📈 Backtest Performance Analysis</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-center" style="gap:12px;margin-bottom:16px;flex-wrap:wrap">
                    <label style="margin:0;font-size:12px;font-weight:600;color:#475569">From:</label>
                    <input type="date" id="bt-from" class="aq-date" value="{{ now()->subDays(90)->format('Y-m-d') }}">
                    <label style="margin:0;font-size:12px;font-weight:600;color:#475569">To:</label>
                    <input type="date" id="bt-to" class="aq-date" value="{{ now()->format('Y-m-d') }}">
                    <button class="aq-action-btn amber" onclick="loadBacktest()"><i class="fas fa-chart-bar"></i> Analyse</button>
                </div>
                <div id="bt-body">
                    <div style="text-align:center;padding:30px;color:#94a3b8">Select date range and click Analyse</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     MODAL: ENTER RESULT
══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="modal-enter-result" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">✏️ Enter Next-Day Result</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <label class="re-label">Trade Date</label>
                <input type="date" id="re-date" class="re-input" value="{{ now()->subDay()->format('Y-m-d') }}">

                <label class="re-label">Option Open Price (09:20 next morning)</label>
                <input type="number" id="re-option-open" class="re-input" step="0.05" placeholder="e.g. 48.50">

                <label class="re-label">PnL % (positive = profit)</label>
                <input type="number" id="re-pnl" class="re-input" step="0.01" placeholder="e.g. 45.5 or -30.0">

                <label class="re-label">Was Correct?</label>
                <select id="re-correct" class="re-input" onchange="document.getElementById('re-miss-wrap').style.display=this.value==='0'?'block':'none'">
                    <option value="1">Yes — Won ✓</option>
                    <option value="0">No — Lost ✗</option>
                </select>

                <div id="re-miss-wrap" style="display:none">
                    <label class="re-label">Miss Reason</label>
                    <select id="re-miss" class="re-input">
                        <option value="MARKET_REVERSED">Market reversed</option>
                        <option value="SECTOR_DRAG">Pharma sector drag</option>
                        <option value="NEWS_EVENT">Unexpected news</option>
                        <option value="LOW_LIQUIDITY">Low liquidity at exit</option>
                        <option value="SIGNAL_A_WRONG">Signal A mismatch (OI logic)</option>
                        <option value="SIGNAL_B_WRONG">Smart money was wrong</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>

                <label class="re-label">Notes (optional)</label>
                <textarea id="re-notes" class="re-input" rows="3" placeholder="What happened? What to improve?"></textarea>

                <div id="re-msg" style="display:none;padding:8px;border-radius:7px;text-align:center;font-size:12px;font-weight:600;margin-bottom:8px"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitResult()" style="background:linear-gradient(135deg,#667eea,#764ba2);border:none;font-weight:700">
                    <i class="fas fa-save"></i> Save Result
                </button>
            </div>
        </div>
    </div>
</div>

</div>{{-- .aq-page --}}
@endsection

@push('script')
<script>
/* ══════════════════════════════════════════════════════════════════
   AUROPHARMA QUANT ENGINE v3 — JS
   All modals, all endpoints, full error handling
══════════════════════════════════════════════════════════════════ */
const R = {
    today:      '{{ route("auro.verdict.today") }}',
    detail:     '{{ route("auro.verdict.detail") }}',
    history:    '{{ route("auro.history") }}',
    liveOI:     '{{ route("auro.oi.live") }}',
    smartMoney: '{{ route("auro.smart-money") }}',
    signals:    '{{ route("auro.signals") }}',
    backtest:   '{{ route("auro.backtest") }}',
    result:     '{{ route("auro.result.update") }}',
    csrf:       '{{ csrf_token() }}',
};

// ── Modal opener ─────────────────────────────────────────────────
function showModal(name) {
    $('#modal-'+name).modal('show');
    if (name === 'smart-money') {
        loadSmartMoney(document.getElementById('sm-date').value);
    } else if (name === 'oi-snapshot') {
        loadOISnapshot(document.getElementById('oi-date').value);
    }
}

// ── Badge helpers ─────────────────────────────────────────────────
function dirBadge(d) {
    if (d==='BUY_CE') return '<span class="b-buy-ce">▲ BUY CE</span>';
    if (d==='BUY_PE') return '<span class="b-buy-pe">▼ BUY PE</span>';
    return '<span class="b-wait">⏸ NO TRADE</span>';
}
function dirRowCls(d) { return d==='BUY_CE'?'row-ce':d==='BUY_PE'?'row-pe':''; }
function confBadge(c) {
    const m={VERY_HIGH:'b-vh',HIGH:'b-h',MEDIUM:'b-m',LOW:'b-l',NO_TRADE:'b-nt'};
    const l={VERY_HIGH:'🔥 V.HIGH',HIGH:'✅ HIGH',MEDIUM:'⚡ MED',LOW:'💧 LOW',NO_TRADE:'— NT'};
    return `<span class="${m[c]||'b-nt'}">${l[c]||c||'—'}</span>`;
}
function sc(v) {
    const n=parseFloat(v)||0;
    const c=n>0?'s-pos':n<0?'s-neg':'s-neu';
    return `<span class="${c}">${n>0?'+':''}${n.toFixed(1)}</span>`;
}
function pctBadge(v) {
    const n=parseFloat(v)||0;
    const c=n>=0?'p-pos':'p-neg';
    return `<span class="${c}">${n>=0?'+':''}${n.toFixed(1)}%</span>`;
}
function oiBadge(t) {
    const m={LONG_BUILDUP:'<span class="b-oilb">↑ LB</span>',SHORT_COVERING:'<span class="b-oisc">↑ SC</span>',SHORT_BUILDUP:'<span class="b-oisb">↓ SB</span>',LONG_UNWINDING:'<span class="b-oilu">↓ LU</span>'};
    return m[t]||`<span style="color:#94a3b8;font-size:9px">${t||'—'}</span>`;
}
function sigBar(score, max) {
    const n=Math.max(-max, Math.min(max, parseFloat(score)||0));
    const w=Math.abs(n)/max*50;
    if (n>=0) return `<div class="sig-track"><div class="sig-center"></div><div class="sig-fill-pos" style="width:${w}%"></div></div>`;
    return `<div class="sig-track"><div class="sig-center"></div><div class="sig-fill-neg" style="width:${w}%"></div></div>`;
}
function parseRisk(raw) {
    try {
        if (!raw) return {};
        return typeof raw==='string' ? JSON.parse(raw) : raw;
    } catch(e) { return {}; }
}

// ── NEW: Flow / Spike / Score / Conf helpers ──────────────────────
function getFlowBadge(f) {
    const map = {
        'STRONG_BULL':  '<span class="flow-strong-bull">🟢🟢 S.BULL</span>',
        'STRONG_BEAR':  '<span class="flow-strong-bear">🔴🔴 S.BEAR</span>',
        'CONTINUATION': '<span class="flow-continuation">→ CONT</span>',
        'REVERSAL':     '<span class="flow-reversal">↩ REVR</span>',
        'TRAP':         '<span class="flow-trap">⚠️ TRAP</span>',
        'MIXED':        '<span class="flow-mixed">~ MIXED</span>',
    };
    return `<td>${map[f] || '<span class="flow-mixed">—</span>'}</td>`;
}

function getSpikeBadge(s) {
    const map = {
        'DUAL_SPIKE': '<span class="spike-dual">⚡⚡ DUAL</span>',
        'CE_SPIKE':   '<span class="spike-ce">⚡ CE</span>',
        'PE_SPIKE':   '<span class="spike-pe">⚡ PE</span>',
        'NONE':       '<span style="color:#aaa;font-size:10px">—</span>',
    };
    return `<td>${map[s] || '—'}</td>`;
}

function getScoreCell(score) {
    const n = parseFloat(score) || 0;
    const cls = n >= 4 ? 'score-cell-pos' : n <= -4 ? 'score-cell-neg' : 'score-cell-neu';
    const sign = n > 0 ? '+' : '';
    return `<td><span class="${cls}">${sign}${n.toFixed(1)}</span></td>`;
}

function getConfBadge(c) {
    const map = {
        'HIGH':     '<span class="conf-high">🔥 HIGH</span>',
        'MEDIUM':   '<span class="conf-medium">⚡ MED</span>',
        'LOW':      '<span class="conf-low">💧 LOW</span>',
        'CONFLICT': '<span class="conf-conflict">⚠️ CONFLICT</span>',
        'NONE':     '<span class="conf-none">—</span>',
    };
    return `<td>${map[c] || '—'}</td>`;
}

// ══════════════════════════════════════════════════════════════════
// TODAY VERDICT
// ══════════════════════════════════════════════════════════════════
function loadTodayVerdict() {
    document.getElementById('verdict-body').innerHTML='<div class="aq-loader"><div class="aq-spinner"></div><div class="aq-loader-text">Loading...</div></div>';
    fetch(R.today).then(r=>r.json()).then(res=>{
        if (!res.success) {
            document.getElementById('verdict-body').innerHTML=`
                <div style="text-align:center;padding:24px;color:#94a3b8">
                    <i class="fas fa-info-circle" style="font-size:2rem;color:#667eea;display:block;margin-bottom:8px"></i>
                    <div style="font-size:12px">${res.message}</div>
                    <code style="font-size:10px;color:#94a3b8;display:block;margin-top:8px">php artisan auro:daily-verdict</code>
                </div>`;
            return;
        }
        renderVerdict(res.data);
    }).catch(()=>{
        document.getElementById('verdict-body').innerHTML='<div class="aq-loader" style="color:#dc2626">Error loading verdict</div>';
    });
}

function renderVerdict(d) {
    document.getElementById('verdict-time').textContent = d.generated_at ? d.generated_at.slice(11,16) : '—';
    if (d.market_regime) document.getElementById('chip-regime').textContent = d.market_regime;
    if (d.vol_5d) document.getElementById('chip-vol').textContent = parseFloat(d.vol_5d).toFixed(1)+'%';

    const isDirCls = d.direction==='BUY_CE'?'bull':d.direction==='BUY_PE'?'bear':'';
    const dirText  = d.direction==='BUY_CE'?'▲ BUY CE':d.direction==='BUY_PE'?'▼ BUY PE':'⏸ NO TRADE';
    const score    = parseFloat(d.net_score)||0;
    const scorePct = Math.min(100, Math.max(0, (score+12)/24*100));
    const barClr   = score>=0?'#16a34a':'#dc2626';
    const risk     = parseRisk(d.post_notes);

    const vetoItems=[];
    if (d.veto_market)   vetoItems.push('Market opposing');
    if (d.veto_volume)   vetoItems.push('Low volume');
    if (d.veto_expiry)   vetoItems.push('Expiry risk');
    if (d.veto_conflict) vetoItems.push('Signal conflict');

    document.getElementById('verdict-body').innerHTML=`
        <div class="verdict-card-wrap ${isDirCls}" style="padding:16px;border-radius:10px">
            <div class="verdict-direction ${isDirCls}">${dirText}</div>
            <div class="mt-1" style="display:flex;gap:8px;align-items:center">
                ${confBadge(d.confidence)}
                <span style="font-size:12px;color:#475569">Score: ${sc(d.net_score)}</span>
            </div>
            <div class="verdict-score-bar">
                <div class="verdict-score-fill" style="width:${scorePct}%;background:${barClr}"></div>
            </div>
        </div>
        <div class="row mt-3" style="gap:0">
            <div class="col-6"><div class="ctx-blk">
                <small>Recommended Strike</small>
                <strong style="color:#667eea;font-size:16px">${d.recommended_strike||'—'}</strong>
                <small style="font-size:9px;color:#94a3b8;display:block">${d.recommended_position||''}</small>
            </div></div>
            <div class="col-6"><div class="ctx-blk">
                <small>Entry LTP (3PM)</small>
                <strong style="font-size:16px">₹${d.recommended_ltp?parseFloat(d.recommended_ltp).toFixed(2):'—'}</strong>
                <small style="font-size:9px;color:#94a3b8;display:block">ATM: ${d.atm_strike||'—'}</small>
            </div></div>
        </div>
        ${risk.stop_loss_pct || risk.target_pct ? `
        <div class="risk-blk">
            <div class="d-flex justify-content-between align-items-center">
                <div><small>Stop Loss</small><strong>${risk.stop_loss_pct||'—'}%</strong></div>
                <div style="text-align:center"><small>Threshold used</small><strong>${risk.threshold_used||'—'}</strong></div>
                <div style="text-align:right"><small>Target</small><strong>${risk.target_pct||'—'}%</strong></div>
            </div>
        </div>` : ''}
        <div class="mt-2 d-flex" style="gap:16px;font-size:10px;color:#64748b;flex-wrap:wrap">
            <span>CE OI: ${pctBadge(d.ce_oi_pct)}</span>
            <span>PE OI: ${pctBadge(d.pe_oi_pct)}</span>
            ${d.sig_e_verdict?`<span style="color:#7c3aed">Sig E: ${d.sig_e_verdict}</span>`:''}
        </div>
        ${vetoItems.length ? `<div class="b-veto mt-2 d-inline-block"><i class="fas fa-exclamation-triangle"></i> ${vetoItems.join(' · ')}</div>` : '<div style="color:#16a34a;font-size:10px;margin-top:6px">✅ No vetos active</div>'}
        ${d.sig_e_reason?`<div style="font-size:10px;color:#7c3aed;margin-top:5px;font-style:italic">${d.sig_e_reason}</div>`:''}
    `;
}

// ══════════════════════════════════════════════════════════════════
// SIGNAL BREAKDOWN
// ══════════════════════════════════════════════════════════════════
function loadSignals(date) {
    document.getElementById('signals-body').innerHTML='<div class="aq-loader"><div class="aq-spinner"></div></div>';
    fetch(R.signals+'?date='+date).then(r=>r.json()).then(res=>{
        if (!res.success) {
            document.getElementById('signals-body').innerHTML=`<div class="aq-loader" style="color:#94a3b8">${res.message}</div>`;
            return;
        }
        const d=res.data;
        let html='';
        d.signals.forEach(s=>{
            const n=parseFloat(s.score);
            const c=n>0?'pos':n<0?'neg':'neu';
            html+=`<div class="sig-row">
                <div class="sig-label">${s.name}</div>
                ${sigBar(s.score,s.max)}
                <div class="sig-score ${c}">${n>0?'+':''}${n.toFixed(1)}</div>
                <div class="sig-verdict">${s.verdict||'—'}</div>
            </div>`;
        });

        const v=d.vetos, vC=Object.values(v).filter(Boolean).length;
        const vetoHtml=vC
            ? `<div class="b-veto mt-2 d-inline-block"><i class="fas fa-exclamation-triangle"></i> ${vC} veto(s) active${v.market_opposing?' · Market':''}${v.low_volume?' · Volume':''}${v.expiry_week?' · Expiry':''}${v.conflicting?' · Conflict':''}</div>`
            : '<div style="color:#16a34a;font-size:10px;margin-top:8px">✅ No vetos active</div>';

        const ctx=d.context, strike=d.strike;
        const ctxHtml=`<div class="row mt-3" style="gap:0">
            <div class="col-4 col-md-2 mb-2"><div class="ctx-blk"><small>Regime</small><strong>${ctx.regime||'—'}</strong></div></div>
            <div class="col-4 col-md-2 mb-2"><div class="ctx-blk"><small>Vol 5d</small><strong>${ctx.vol_5d?parseFloat(ctx.vol_5d).toFixed(1)+'%':'—'}</strong></div></div>
            <div class="col-4 col-md-2 mb-2"><div class="ctx-blk"><small>OI Type</small><strong style="font-size:9px">${ctx.oi_type||'—'}</strong></div></div>
            <div class="col-4 col-md-2 mb-2"><div class="ctx-blk"><small>Support</small><strong>${ctx.support?'₹'+ctx.support:'—'}</strong></div></div>
            <div class="col-4 col-md-2 mb-2"><div class="ctx-blk"><small>Resist</small><strong>${ctx.resistance?'₹'+ctx.resistance:'—'}</strong></div></div>
            <div class="col-4 col-md-2 mb-2"><div class="ctx-blk"><small>Sig E</small><strong style="font-size:9px;color:#667eea">${strike?strike.sig_e:'—'}</strong></div></div>
        </div>`;

        document.getElementById('signals-body').innerHTML=html+vetoHtml+ctxHtml;
    }).catch(()=>{
        document.getElementById('signals-body').innerHTML='<div class="aq-loader" style="color:#dc2626">Error loading signals</div>';
    });
}

// ══════════════════════════════════════════════════════════════════
// HISTORY TABLE
// ══════════════════════════════════════════════════════════════════
function loadHistory() {
    const from=document.getElementById('h-from').value;
    const to=document.getElementById('h-to').value;
    const filter=document.getElementById('h-filter').value;

    document.getElementById('history-loading').style.display='flex';
    document.getElementById('history-tbody').innerHTML='';

    fetch(`${R.history}?from=${from}&to=${to}&filter=${filter}`)
        .then(r=>r.json())
        .then(res=>{
            document.getElementById('history-loading').style.display='none';
            if (!res.success) {
                document.getElementById('history-tbody').innerHTML='<tr><td colspan="29" style="text-align:center;padding:30px;color:#94a3b8">'+res.message+'</td></tr>';
                return;
            }
            const s=res.stats;
            document.getElementById('h-total').textContent=s.total;
            document.getElementById('h-wr').textContent=s.winRate+'%';
            document.getElementById('h-aw').textContent=s.avgWin?('+'+parseFloat(s.avgWin).toFixed(1)+'%'):'—';
            document.getElementById('h-rr').textContent=s.rr?(s.rr+'x'):'—';
            document.getElementById('hist-count').textContent=res.data.length+' records';

            let html='';
            if (!res.data.length) {
                html='<tr><td colspan="29" style="text-align:center;padding:30px;color:#94a3b8">No records found</td></tr>';
            } else {
                res.data.forEach((d,i)=>{
                    const risk=parseRisk(d.post_notes);
                    const slPct=risk.stop_loss_pct||'—';
                    const tgtPct=risk.target_pct||'—';

                    const vetoHtml=d.veto_any
                        ?'<span class="b-veto"><i class="fas fa-exclamation-triangle"></i></span>'
                        :'<span style="color:#94a3b8;font-size:9px">—</span>';

                    const correctHtml=d.was_correct===null||d.was_correct===undefined
                        ?'<span style="color:#94a3b8;font-size:9px">—</span>'
                        :d.was_correct
                            ?'<span class="b-win">✓ Win</span>'
                            :'<span class="b-loss">✗ Loss</span>';

                    const bDays=(()=>{
                        const bear=parseInt(d.sig_b_bear_days)||0;
                        const bull=parseInt(d.sig_b_bull_days)||0;
                        if (bear>=2) return `<span style="color:#dc2626;font-weight:700">B${bear}d</span>`;
                        if (bull>=2) return `<span style="color:#16a34a;font-weight:700">B${bull}d</span>`;
                        if (bear>=1) return `<span style="color:#94a3b8;font-size:9px">${bear}d</span>`;
                        return '<span style="color:#94a3b8;font-size:9px">—</span>';
                    })();

                    // T-1 CE% flow cell
                    const cet1 = parseFloat(d.ce_flow_t1_t2) || 0;
                    const cet1Cls = cet1 > 0 ? 'flow-t-pos' : cet1 < 0 ? 'flow-t-neg' : '';
                    const cet1Str = `<span class="${cet1Cls}">${cet1 > 0 ? '+' : ''}${cet1.toFixed(1)}%</span>`;

                    // T-1 PE% flow cell
                    const pet1 = parseFloat(d.pe_flow_t1_t2) || 0;
                    const pet1Cls = pet1 > 0 ? 'flow-t-pos' : pet1 < 0 ? 'flow-t-neg' : '';
                    const pet1Str = `<span class="${pet1Cls}">${pet1 > 0 ? '+' : ''}${pet1.toFixed(1)}%</span>`;

                    // Gap cell
                    const oiDiff = parseFloat(d.oi_diff) || 0;
                    const gapClr = oiDiff > 25 ? '#1b5e20' : oiDiff < 10 ? '#dc3545' : '#333';
                    const gapStr = `<strong style="color:${gapClr}">${oiDiff.toFixed(1)}</strong>`;

                    html+=`<tr class="${dirRowCls(d.direction)}" style="cursor:pointer"
                        onclick="loadSignals('${d.trade_date}');document.getElementById('signal-date').value='${d.trade_date}'">
                        <td style="color:#94a3b8">${i+1}</td>
                        <td style="font-weight:700;color:#667eea">${d.trade_date||'—'}</td>
                        <td>${dirBadge(d.direction)}</td>
                        <td>${sc(d.net_score)}</td>
                        <td>${confBadge(d.confidence)}</td>
                        <td style="font-weight:700;color:#667eea">${d.recommended_strike||'—'}
                            <br><span style="color:#94a3b8;font-size:9px">${d.recommended_position||''}</span></td>
                        <td style="color:#1e293b">₹${d.recommended_ltp?parseFloat(d.recommended_ltp).toFixed(2):'—'}</td>
                        <td>${sc(d.sig_a_score)}</td>
                        <td style="max-width:110px;overflow:hidden;text-overflow:ellipsis;font-size:9px;color:#475569" title="${d.sig_a_verdict||''}">${(d.sig_a_verdict||'—').slice(0,18)}</td>
                        <td>${sc(d.sig_b_score)}</td>
                        <td>${bDays}</td>
                        <td>${sc(d.sig_c_score)}</td>
                        <td>${oiBadge(d.fut_oi_type)}</td>
                        <td>${sc(d.sig_d_score)}</td>
                        <td style="font-size:9px;color:#475569">${d.nifty_trend||'—'}</td>
                        <td style="font-size:9px;color:#64748b">${d.market_regime||'—'}</td>
                        <td>${vetoHtml}</td>
                        ${getFlowBadge(d.flow_signal)}
                        ${getSpikeBadge(d.spike_type)}
                        ${getScoreCell(d.oi_score)}
                        ${getConfBadge(d.confidence)}
                        <td>${cet1Str}</td>
                        <td>${pet1Str}</td>
                        <td>${gapStr}</td>
                        <td>${d.actual_pnl_pct!==null&&d.actual_pnl_pct!==undefined?pctBadge(d.actual_pnl_pct):'—'}</td>
                        <td>${correctHtml}</td>
                        <td>${d.miss_reason?`<span class="b-miss">${d.miss_reason.replace('_',' ')}</span>`:'—'}</td>
                        <td style="font-weight:700;color:#92400e">${slPct!=='—'?slPct+'%':slPct}</td>
                        <td style="font-weight:700;color:#16a34a">${tgtPct!=='—'?tgtPct+'%':tgtPct}</td>
                    </tr>`;
                });
            }
            document.getElementById('history-tbody').innerHTML=html;
        })
        .catch(err=>{
            document.getElementById('history-loading').style.display='none';
            document.getElementById('history-tbody').innerHTML='<tr><td colspan="29" style="text-align:center;padding:30px;color:#dc2626">Error loading history: '+err.message+'</td></tr>';
        });
}

function resetFilters() {
    document.getElementById('h-from').value='{{ now()->subDays(60)->format("Y-m-d") }}';
    document.getElementById('h-to').value='{{ now()->format("Y-m-d") }}';
    document.getElementById('h-filter').value='ALL';
    loadHistory();
}

// ══════════════════════════════════════════════════════════════════
// SMART MONEY MONITOR (modal)
// ══════════════════════════════════════════════════════════════════
function loadSmartMoney(date) {
    document.getElementById('sm-alerts').innerHTML='<div class="aq-loader"><div class="aq-spinner"></div><div class="aq-loader-text">Scanning far OTM strikes...</div></div>';
    document.getElementById('sm-table-wrap').innerHTML='';

    fetch(R.smartMoney+'?date='+date).then(r=>r.json()).then(res=>{
        if (!res.success) {
            document.getElementById('sm-alerts').innerHTML=`<div style="color:#94a3b8;text-align:center;padding:16px">${res.message}</div>`;
            return;
        }

        let alertHtml='', hasAlert=false;
        res.strikes.forEach(s=>{
            if (s.alert_level==='NONE') return;
            hasAlert=true;
            const bear=s.type==='PE';
            const cls=bear?'alert-danger':'alert-success';
            const icon=s.alert_level==='HIGH'?'🔴':'🟡';
            alertHtml+=`<div class="alert ${cls} py-2 mb-2" style="font-size:11px;border-radius:8px">
                ${icon} <strong>${s.position} ${s.type} @ ₹${s.strike}</strong> —
                accumulating for <strong>${s.consecutive_growth} consecutive days</strong>
                <span class="float-right badge badge-${s.alert_level==='HIGH'?'danger':'warning'}">${s.alert_level}</span>
            </div>`;
        });
        if (!hasAlert) alertHtml='<div class="alert alert-success py-2" style="font-size:12px;border-radius:8px"><i class="fas fa-check-circle"></i> No unusual far OTM accumulation detected today</div>';
        document.getElementById('sm-alerts').innerHTML=alertHtml;

        const dates=res.dates;
        let thd='<tr><th>Strike</th><th>Type</th><th>Position</th>';
        dates.forEach(d=>{ thd+=`<th>${d.slice(5)}</th>`; });
        thd+='<th>Consecutive</th><th>Status</th></tr>';

        let rows='';
        res.strikes.forEach(s=>{
            const rowCls=s.alert_level==='HIGH'?(s.type==='PE'?'bear-row':'bull-row'):s.alert_level==='MEDIUM'?(s.type==='PE'?'bear-row':'bull-row'):'';
            let tds='';
            dates.forEach(d=>{ const oi=s.oi_by_date[d]||0; tds+=`<td>${oi>0?Number(oi).toLocaleString('en-IN'):'—'}</td>`; });
            const statusBadge=s.is_accumulating
                ?`<span class="${s.type==='PE'?'b-accum-b':'b-accum-g'}">${s.consecutive_growth}d ${s.type==='PE'?'BEAR':'BULL'}</span>`
                :'<span style="color:#94a3b8;font-size:10px">—</span>';
            rows+=`<tr class="${rowCls}"><td style="font-weight:700">₹${s.strike}</td><td style="color:${s.type==='PE'?'#dc2626':'#16a34a'};font-weight:700">${s.type}</td><td style="color:#667eea">${s.position}</td>${tds}<td style="font-weight:700">${s.consecutive_growth||0}</td><td>${statusBadge}</td></tr>`;
        });

        document.getElementById('sm-table-wrap').innerHTML=`<table class="sm-table"><thead>${thd}</thead><tbody>${rows||'<tr><td colspan="20" style="text-align:center;padding:16px;color:#94a3b8">No data</td></tr>'}</tbody></table>`;
    }).catch(e=>{
        document.getElementById('sm-alerts').innerHTML=`<div class="alert alert-danger py-2" style="font-size:11px">Error: ${e.message}</div>`;
    });
}

// ══════════════════════════════════════════════════════════════════
// OI SNAPSHOT (modal)
// ══════════════════════════════════════════════════════════════════
function loadOISnapshot(date) {
    document.getElementById('oi-snapshot-body').innerHTML='<div class="aq-loader"><div class="aq-spinner"></div><div class="aq-loader-text">Loading OI...</div></div>';

    fetch(R.liveOI+'?date='+date).then(r=>r.json()).then(res=>{
        if (!res.success) {
            document.getElementById('oi-snapshot-body').innerHTML=`<div style="text-align:center;padding:20px;color:#94a3b8">${res.message}</div>`;
            return;
        }

        const strikes=Object.keys(res.strikes).map(Number).sort((a,b)=>a-b);
        const interval=strikes.length>1?strikes[1]-strikes[0]:10;
        const atm=parseFloat(res.fut_close||0);
        const allOI=Object.values(res.strikes).flatMap(s=>[(s.CE?.oi||0),(s.PE?.oi||0)]);
        const maxOI=Math.max(...allOI,1);

        let rows='';
        strikes.forEach(strike=>{
            const s=res.strikes[String(strike)]||{};
            const ce=s.CE||{}, pe=s.PE||{};
            const isAtm=Math.abs(strike-atm)<interval;
            const ceW=Math.min(80,(ce.oi||0)/maxOI*80);
            const peW=Math.min(80,(pe.oi||0)/maxOI*80);
            rows+=`<tr class="${isAtm?'atm-row':''}">
                <td style="text-align:right;font-size:10px">${(ce.oi||0).toLocaleString('en-IN')}</td>
                <td style="width:90px;text-align:right">
                    ${ceW>0?`<div class="oi-bar" style="width:${ceW}px;background:#d97706;float:right"></div>`:'-'}
                </td>
                <td style="text-align:right;font-size:9px;color:#94a3b8">${ce.volume?(ce.volume||0).toLocaleString('en-IN'):'—'}</td>
                <td style="font-weight:700;font-size:12px">${strike}${isAtm?' ◆':''}</td>
                <td style="font-size:9px;color:#94a3b8">${pe.volume?(pe.volume||0).toLocaleString('en-IN'):'—'}</td>
                <td style="width:90px">
                    ${peW>0?`<div class="oi-bar" style="width:${peW}px;background:#16a34a"></div>`:'-'}
                </td>
                <td style="font-size:10px">${(pe.oi||0).toLocaleString('en-IN')}</td>
            </tr>`;
        });

        document.getElementById('oi-snapshot-body').innerHTML=`
            <div style="font-size:11px;color:#64748b;margin-bottom:12px;padding:8px;background:#f8fafc;border-radius:7px">
                <strong>FUT Close:</strong> <span style="color:#667eea;font-weight:700">₹${parseFloat(res.fut_close||0).toFixed(2)}</span> &nbsp;|&nbsp;
                <strong>Expiry:</strong> ${res.expiry||'—'} &nbsp;|&nbsp;
                <strong>As of:</strong> ${res.time||'—'}
            </div>
            <div style="overflow-x:auto">
            <table class="oi-snap-table" style="min-width:500px">
                <thead><tr>
                    <th style="text-align:right;color:#d97706">CE OI</th>
                    <th style="text-align:right;color:#d97706">CE Bar</th>
                    <th style="text-align:right;color:#d97706">CE Vol</th>
                    <th>Strike</th>
                    <th style="color:#16a34a">PE Vol</th>
                    <th style="color:#16a34a">PE Bar</th>
                    <th style="color:#16a34a">PE OI</th>
                </tr></thead>
                <tbody>${rows||'<tr><td colspan="7" style="text-align:center;padding:20px;color:#94a3b8">No strike data</td></tr>'}</tbody>
            </table></div>`;
    }).catch(e=>{
        document.getElementById('oi-snapshot-body').innerHTML=`<div style="color:#dc2626;padding:16px">Error: ${e.message}</div>`;
    });
}

// ══════════════════════════════════════════════════════════════════
// BACKTEST STATS (modal)
// ══════════════════════════════════════════════════════════════════
function loadBacktest() {
    const from=document.getElementById('bt-from').value;
    const to=document.getElementById('bt-to').value;
    document.getElementById('bt-body').innerHTML='<div class="aq-loader"><div class="aq-spinner"></div><div class="aq-loader-text">Analysing...</div></div>';

    fetch(`${R.backtest}?from=${from}&to=${to}`).then(r=>r.json()).then(res=>{
        if (!res.success) {
            document.getElementById('bt-body').innerHTML=`<div style="text-align:center;padding:20px;color:#94a3b8">${res.message}</div>`;
            return;
        }
        const s=res.stats;
        const wCls=p=>parseFloat(p)>=50?'p-pos':'p-neg';

        let scoreRows='';
        if (res.by_score && res.by_score.length) {
            res.by_score.forEach(b=>{
                scoreRows+=`<tr>
                    <td style="text-align:center;font-weight:700;color:#667eea">${b.score>0?'+':''}${b.score}</td>
                    <td style="text-align:center">${b.total}</td>
                    <td style="text-align:center;color:#16a34a">${b.wins}</td>
                    <td style="text-align:center"><span class="${wCls(b.win_rate)}">${b.win_rate}%</span></td>
                </tr>`;
            });
        } else {
            scoreRows='<tr><td colspan="4" style="text-align:center;padding:16px;color:#94a3b8">Enter actual trade results to see score-bucket analysis</td></tr>';
        }

        document.getElementById('bt-body').innerHTML=`
            <div class="row mb-3">
                <div class="col"><div class="bt-stat" style="border-top-color:#16a34a">
                    <small>Win Rate</small><strong class="${wCls(s.winRate)}">${s.winRate}%</strong></div></div>
                <div class="col"><div class="bt-stat">
                    <small>Trades</small><strong>${s.total}</strong></div></div>
                <div class="col"><div class="bt-stat" style="border-top-color:#16a34a">
                    <small>Wins</small><strong class="p-pos">${s.wins}</strong></div></div>
                <div class="col"><div class="bt-stat" style="border-top-color:#dc2626">
                    <small>Losses</small><strong class="p-neg">${s.losses}</strong></div></div>
                <div class="col"><div class="bt-stat" style="border-top-color:#7c3aed">
                    <small>R/R</small><strong style="color:#7c3aed">${s.rr}x</strong></div></div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <div style="background:#f8fafc;border-radius:8px;padding:12px;font-size:12px">
                        <div style="margin-bottom:4px">Avg Win: <strong class="p-pos">+${parseFloat(s.avgWin||0).toFixed(1)}%</strong> &nbsp;|&nbsp; Avg Loss: <strong class="p-neg">${parseFloat(s.avgLoss||0).toFixed(1)}%</strong></div>
                        <div>No-Trade Days: <strong>${s.noTrade}</strong></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="background:#fffbeb;border-radius:8px;padding:12px;font-size:11px;color:#92400e">
                        <strong>💡 Note:</strong> Backtest accuracy improves as you enter more actual results using "Enter Result" button.
                    </div>
                </div>
            </div>
            <div style="font-size:10px;color:#64748b;margin-bottom:8px;text-transform:uppercase;font-weight:700;letter-spacing:.5px">Win Rate by Score Bucket</div>
            <table class="sm-table" style="max-width:400px">
                <thead><tr><th>Score</th><th>Trades</th><th>Wins</th><th>Win %</th></tr></thead>
                <tbody>${scoreRows}</tbody>
            </table>`;
    }).catch(e=>{
        document.getElementById('bt-body').innerHTML=`<div style="color:#dc2626;padding:16px">Error: ${e.message}</div>`;
    });
}

// ══════════════════════════════════════════════════════════════════
// ENTER RESULT
// ══════════════════════════════════════════════════════════════════
function submitResult() {
    const data={
        trade_date:         document.getElementById('re-date').value,
        actual_option_open: document.getElementById('re-option-open').value,
        actual_pnl_pct:     document.getElementById('re-pnl').value,
        was_correct:        parseInt(document.getElementById('re-correct').value),
        miss_reason:        document.getElementById('re-miss').value,
        post_trade_notes:   document.getElementById('re-notes').value,
        _token:             R.csrf,
    };
    const msg=document.getElementById('re-msg');
    msg.style.display='none';

    if (!data.trade_date||!data.actual_pnl_pct) {
        msg.style.display='block'; msg.style.background='#fee2e2'; msg.style.color='#b91c1c';
        msg.textContent='Please fill in Trade Date and PnL %'; return;
    }

    fetch(R.result,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':R.csrf},body:JSON.stringify(data)})
    .then(r=>r.json()).then(res=>{
        msg.style.display='block';
        if (res.success) {
            msg.style.background='#dcfce7'; msg.style.color='#15803d';
            msg.textContent='✓ Result saved! Click Load History to see updated stats.';
            setTimeout(()=>loadHistory(), 1500);
        } else {
            msg.style.background='#fee2e2'; msg.style.color='#b91c1c';
            msg.textContent='✗ '+res.message;
        }
    }).catch(e=>{
        msg.style.display='block'; msg.style.background='#fee2e2'; msg.style.color='#b91c1c';
        msg.textContent='Error: '+e.message;
    });
}

// ── Refresh all ──────────────────────────────────────────────────
function refreshAll() {
    loadTodayVerdict();
    loadSignals(document.getElementById('signal-date').value);
    loadHistory();
}

// ── Init ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', ()=>{
    loadTodayVerdict();
    loadSignals('{{ now()->format("Y-m-d") }}');
    loadHistory();
});
</script>
@endpush