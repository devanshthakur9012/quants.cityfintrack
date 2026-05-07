@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg-primary:#0a0e17; --bg-card:#0f1520; --bg-panel:#151d2e; --bg-row:#111827;
    --border:#1e2d45; --border-bright:#2a3f5f;
    --text-primary:#e2e8f0; --text-muted:#64748b; --text-dim:#94a3b8;
    --accent:#3b82f6; --accent-glow:rgba(59,130,246,0.18);
    --bullish:#10b981; --bullish-bg:rgba(16,185,129,0.10);
    --bearish:#ef4444; --bearish-bg:rgba(239,68,68,0.10);
    --mono:'JetBrains Mono',monospace;
}
*{box-sizing:border-box;} body{background:var(--bg-primary);color:var(--text-primary);}
.tb-page{min-height:100vh;padding:24px 20px 60px;background:radial-gradient(ellipse 80% 40% at 50% -10%,rgba(59,130,246,0.07) 0%,transparent 70%),var(--bg-primary);}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;}
.page-title{font-weight:800;font-size:1.4rem;letter-spacing:-0.5px;}
.page-title span{color:var(--accent);}
.filter-bar{background:var(--bg-panel);border:1px solid var(--border);border-radius:12px;padding:16px 20px;display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;margin-bottom:22px;}
.filter-group{display:flex;flex-direction:column;gap:5px;min-width:200px;}
.filter-group label{font-size:0.66rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:500;}
.filter-control{background:var(--bg-row);border:1px solid var(--border);border-radius:7px;color:var(--text-primary);padding:8px 12px;font-size:0.82rem;outline:none;transition:border-color .2s;}
.filter-control:focus{border-color:var(--accent);}
.filter-control option{background:var(--bg-row);}
.btn-apply{background:var(--accent);color:white;border:none;border-radius:7px;padding:8px 22px;font-size:0.82rem;font-weight:700;cursor:pointer;align-self:flex-end;transition:opacity .2s;}
.btn-apply:disabled{opacity:.5;cursor:not-allowed;}
.btn-upload-link{background:transparent;border:1px solid var(--border);color:var(--text-muted);border-radius:7px;padding:8px 16px;font-size:0.82rem;cursor:pointer;align-self:flex-end;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.btn-upload-link:hover{border-color:var(--accent);color:var(--accent);}
.summary-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px;margin-bottom:22px;}
.sum-pill{background:var(--bg-panel);border:1px solid var(--border);border-radius:10px;padding:14px 16px;text-align:center;}
.sum-pill .num{font-weight:800;font-size:1.3rem;line-height:1;margin-bottom:4px;}
.sum-pill .lbl{font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.8px;}
.sum-pill.profit .num{color:var(--bullish);}
.sum-pill.loss   .num{color:var(--bearish);}
.sum-pill.accent .num{color:var(--accent);}
.sum-pill.warn   .num{color:#f59e0b;}
.tab-bar{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;}
.tab-btn{background:var(--bg-panel);border:1px solid var(--border);border-radius:7px;color:var(--text-muted);padding:7px 18px;font-size:0.8rem;cursor:pointer;transition:all .2s;}
.tab-btn.active{background:var(--accent);border-color:var(--accent);color:white;font-weight:700;}
.tbl-wrap{background:var(--bg-card);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:24px;}
.tbl-head{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid var(--border);background:var(--bg-panel);}
.tbl-head h3{font-weight:700;font-size:0.9rem;margin:0;}
.tbl-count{background:var(--accent-glow);color:var(--accent);border:1px solid rgba(59,130,246,0.3);border-radius:20px;padding:2px 11px;font-size:0.7rem;font-weight:700;}
.data-table{width:100%;border-collapse:collapse;font-size:0.77rem;}
.data-table thead tr{background:var(--bg-panel);border-bottom:1px solid var(--border-bright);}
.data-table th{padding:9px 12px;font-size:0.62rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);white-space:nowrap;text-align:center;font-weight:500;}
.data-table th.left{text-align:left;}
.data-table tbody tr{border-bottom:1px solid rgba(30,45,69,0.5);transition:background .12s;}
.data-table tbody tr:hover{background:var(--bg-panel);}
.data-table tbody tr:last-child{border-bottom:none;}
.data-table tbody tr.profit-row{background:rgba(16,185,129,0.03);}
.data-table tbody tr.loss-row{background:rgba(239,68,68,0.03);}
.data-table tbody tr.open-row{background:rgba(245,158,11,0.03);}
.data-table td{padding:9px 12px;text-align:center;vertical-align:middle;white-space:nowrap;}
.data-table td.left{text-align:left;}
.sym-cell{font-weight:700;font-size:0.83rem;color:var(--text-primary);}
.sym-sub{font-size:0.65rem;color:var(--text-muted);display:block;margin-top:1px;}
.buy-cell{color:var(--bullish);}
.sell-cell{color:var(--bearish);}
.pnl-pos{color:var(--bullish);font-weight:700;}
.pnl-neg{color:var(--bearish);font-weight:700;}
.pnl-open{color:#f59e0b;font-weight:700;}
.na-val{color:var(--text-muted);opacity:.4;font-size:.7rem;}
.badge{display:inline-block;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:4px;}
.badge-intra{background:rgba(59,130,246,.15);color:var(--accent);}
.badge-pos{background:rgba(139,92,246,.15);color:#a78bfa;}
.badge-open{background:rgba(245,158,11,.15);color:#f59e0b;}
.badge-seg{background:rgba(56,189,248,.12);color:#38bdf8;font-size:.62rem;padding:1px 6px;}
.mono{font-family:var(--mono);}
.time-small{font-family:var(--mono);font-size:.7rem;color:var(--text-muted);}
.price-val{font-weight:600;}
.day-profit{color:var(--bullish);font-weight:700;}
.day-loss{color:var(--bearish);font-weight:700;}
.chart-bar-bg{background:var(--bg-row);border-radius:4px;overflow:hidden;height:8px;}
.chart-bar-fill{height:100%;border-radius:4px;transition:width .4s;}
.search-wrap{display:flex;align-items:center;gap:8px;margin-left:auto;}
.search-input{background:var(--bg-row);border:1px solid var(--border);border-radius:7px;color:var(--text-primary);padding:6px 12px;font-size:0.78rem;outline:none;width:180px;}
.search-input::placeholder{color:var(--text-muted);}
.alert{padding:12px 16px;border-radius:8px;font-size:0.83rem;margin-bottom:20px;}
.alert-success{background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:var(--bullish);}
.alert-error{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:var(--bearish);}
.empty-state{text-align:center;padding:50px 20px;color:var(--text-muted);}
.empty-state i{font-size:2.5rem;display:block;margin-bottom:12px;}

/* ── SKELETON LOADER ── */
@keyframes shimmer{0%{background-position:-600px 0}100%{background-position:600px 0}}
.skeleton{
    background:linear-gradient(90deg,var(--bg-panel) 25%,var(--bg-row) 50%,var(--bg-panel) 75%);
    background-size:1200px 100%;
    animation:shimmer 1.4s infinite linear;
    border-radius:6px;
}
.sk-pill{height:88px;border-radius:10px;}
.sk-row{height:38px;border-radius:0;}
.sk-row:nth-child(even){opacity:.7;}

/* ── LOADING SPINNER ── */
.spinner-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;padding:60px 20px;}
.spinner{width:38px;height:38px;border:3px solid var(--border-bright);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}
.spinner-text{color:var(--text-muted);font-size:.83rem;}

/* ── TIMING BADGE ── */
.load-time{font-size:.65rem;color:var(--text-muted);margin-left:auto;}
</style>
@endpush

<div class="tb-page">
<div style="max-width:1600px;margin:0 auto;">

    <div class="page-header">
        <h1 class="page-title">Trade Book — <span>P&L Report</span></h1>
        <span id="brokerMonthBadge" style="background:var(--bg-panel);border:1px solid var(--border);border-radius:20px;padding:5px 14px;font-size:0.75rem;color:var(--text-dim);">
            @if($selectedBrokerApiId && $selectedMonth)
                {{ $selectedBrokerName }} &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($selectedMonth.'-01')->format('F Y') }}
            @endif
        </span>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    <div id="ajaxError" class="alert alert-error" style="display:none;"></div>

    {{-- ── FILTER ── --}}
    <div class="filter-bar">
        <div class="filter-group">
            <label>Broker Account</label>
            <select id="brokerSel" class="filter-control">
                @foreach($brokerOptions as $bId => $bData)
                    <option value="{{ $bId }}" {{ $selectedBrokerApiId == $bId ? 'selected' : '' }}>
                        {{ $bData['label'] }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label>Report Month</label>
            <select id="monthSel" class="filter-control">
                @foreach($brokerOptions as $bId => $bData)
                    @foreach($bData['months'] as $m)
                        <option value="{{ $m }}"
                            data-broker="{{ $bId }}"
                            {{ $selectedMonth == $m && $selectedBrokerApiId == $bId ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($m.'-01')->format('M Y') }}
                        </option>
                    @endforeach
                @endforeach
            </select>
        </div>
        <button id="loadBtn" class="btn-apply" onclick="loadReport()">▶ Load Report</button>
        <a href="{{ route('trade-book.upload') }}" class="btn-upload-link">⬆ Upload New</a>
        <span id="loadTime" class="load-time"></span>
    </div>

    {{-- ── DYNAMIC REPORT AREA ── --}}
    <div id="reportArea">
        @if($selectedBrokerApiId && $selectedMonth)
            {{-- Show skeleton on first load if params present --}}
            <div id="skeletonLoader">
                <div class="summary-grid" style="margin-bottom:22px;">
                    @for($i=0;$i<12;$i++) <div class="sum-pill skeleton sk-pill"></div> @endfor
                </div>
                <div class="tbl-wrap">
                    <div class="tbl-head" style="background:var(--bg-panel);"><div class="skeleton" style="height:18px;width:220px;border-radius:4px;"></div></div>
                    <div style="padding:0;">
                        @for($i=0;$i<10;$i++) <div class="skeleton sk-row" style="margin:1px 0;"></div> @endfor
                    </div>
                </div>
            </div>
        @else
            <div class="empty-state" id="emptyPrompt">
                @if(empty($brokerOptions))
                    <i class="las la-inbox"></i>
                    <h5>No Trade Data Found</h5>
                    <p>No trade books have been uploaded yet.</p>
                    <a href="{{ route('trade-book.upload') }}"
                       style="display:inline-block;margin-top:12px;background:var(--accent);color:white;border-radius:8px;padding:9px 22px;text-decoration:none;font-size:0.84rem;font-weight:700;">
                        ⬆ Upload Now
                    </a>
                @else
                    <i class="las la-chart-bar"></i>
                    <h5>Select Broker &amp; Month</h5>
                    <p>Choose a broker account and report month above, then click Load Report.</p>
                @endif
            </div>
        @endif
    </div>

</div>
</div>

@push('script')
<script>
// ─────────────────────────────────────────────────────────
//  CONFIG
// ─────────────────────────────────────────────────────────
const AJAX_URL  = '{{ route("trade-book.ajax-pnl") }}';
const CSRF      = '{{ csrf_token() }}';

// ─────────────────────────────────────────────────────────
//  BROKER → MONTH CASCADE
// ─────────────────────────────────────────────────────────
const brokerSel = document.getElementById('brokerSel');
const monthSel  = document.getElementById('monthSel');

brokerSel.addEventListener('change', () => {
    const broker   = brokerSel.value;
    let firstVis   = null;
    Array.from(monthSel.options).forEach(opt => {
        const show = !opt.dataset.broker || opt.dataset.broker === broker;
        opt.style.display = show ? '' : 'none';
        if (show && !firstVis) firstVis = opt;
    });
    if (firstVis) monthSel.value = firstVis.value;
});

// ─────────────────────────────────────────────────────────
//  LOAD REPORT (AJAX)
// ─────────────────────────────────────────────────────────
function loadReport() {
    const broker = brokerSel.value;
    const month  = monthSel.value;
    if (!broker || !month) return;

    document.getElementById('ajaxError').style.display = 'none';
    document.getElementById('loadBtn').disabled = true;
    document.getElementById('loadTime').textContent = '';

    // Update URL without reload
    const url = new URL(window.location.href);
    url.searchParams.set('broker_api_id', broker);
    url.searchParams.set('upload_month',  month);
    window.history.replaceState({}, '', url);

    // Show spinner
    document.getElementById('reportArea').innerHTML = `
        <div class="spinner-wrap">
            <div class="spinner"></div>
            <div class="spinner-text">Calculating P&amp;L…</div>
        </div>`;

    const t0 = performance.now();

    fetch(AJAX_URL + '?broker_api_id=' + encodeURIComponent(broker) + '&upload_month=' + encodeURIComponent(month), {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF }
    })
    .then(r => {
        if (!r.ok) return r.json().then(e => { throw new Error(e.error || 'Server error ' + r.status); });
        return r.json();
    })
    .then(data => {
        const ms = Math.round(performance.now() - t0);
        document.getElementById('loadTime').textContent = `⚡ loaded in ${ms}ms`;
        renderReport(data);
    })
    .catch(err => {
        document.getElementById('reportArea').innerHTML = '';
        const el = document.getElementById('ajaxError');
        el.textContent = '❌ ' + err.message;
        el.style.display = '';
    })
    .finally(() => {
        document.getElementById('loadBtn').disabled = false;
    });
}

// ─────────────────────────────────────────────────────────
//  RENDER HELPERS
// ─────────────────────────────────────────────────────────
const fmt  = n => new Intl.NumberFormat('en-IN').format(n);
const fmtR = (n, d=2) => new Intl.NumberFormat('en-IN', {minimumFractionDigits:d,maximumFractionDigits:d}).format(n);

function pnlColor(v)  { return v >= 0 ? 'var(--bullish)' : 'var(--bearish)'; }
function pnlArrow(v)  { return v >= 0 ? '▲' : '▼'; }

function renderSummary(s) {
    const pills = [
        { cls: s.total_pnl >= 0 ? 'profit' : 'loss', num: `${pnlArrow(s.total_pnl)} ₹${fmt(Math.abs(s.total_pnl))}`, lbl: 'Total P&L' },
        { cls: 'accent', num: s.total_trades,  lbl: 'Closed Trades' },
        { cls: 'profit', num: s.winners,        lbl: 'Winners' },
        { cls: 'loss',   num: s.losers,         lbl: 'Losers' },
        { cls: s.win_rate >= 50 ? 'profit' : 'loss', num: s.win_rate + '%', lbl: 'Win Rate' },
        { cls: 'profit', num: '₹' + fmt(s.avg_win),  lbl: 'Avg Win' },
        { cls: 'loss',   num: '₹' + fmt(Math.abs(s.avg_loss)), lbl: 'Avg Loss' },
        ...(s.reward_risk !== null ? [{ cls:'accent', num: s.reward_risk, lbl:'Reward:Risk' }] : []),
        { cls: 'profit', num: '₹' + fmt(s.best_trade),  lbl: 'Best Trade' },
        { cls: 'loss',   num: '₹' + fmt(Math.abs(s.worst_trade)), lbl: 'Worst Trade' },
        { cls: 'accent', num: s.intraday,        lbl: 'Intraday' },
        { cls: 'warn',   num: s.open_positions,  lbl: 'Open Pos.' },
    ];

    return `<div class="summary-grid">${
        pills.map(p => `<div class="sum-pill ${p.cls}"><div class="num">${p.num}</div><div class="lbl">${p.lbl}</div></div>`).join('')
    }</div>`;
}

function renderTradeTable(trades) {
    const rows = trades.map((t, i) => {
        const isOpen   = t.pnl === null;
        const rowCls   = isOpen ? 'open-row' : (t.pnl >= 0 ? 'profit-row' : 'loss-row');
        const typeBadge = t.trade_type_label === 'Intraday'
            ? `<span class="badge badge-intra">Intraday</span>`
            : t.trade_type_label === 'Positional'
                ? `<span class="badge badge-pos">Positional</span>`
                : `<span class="badge badge-open">Open</span>`;

        const exSeg = [
            t.exchange ? `<span class="badge-seg badge">${esc(t.exchange)}</span>` : '',
            t.segment  ? `<span class="badge-seg badge" style="margin-left:3px;background:rgba(139,92,246,.12);color:#a78bfa;">${esc(t.segment)}</span>` : '',
            t.expiry_date ? `<span style="margin-left:5px;font-size:0.61rem;color:var(--text-muted);">Exp: ${esc(t.expiry_date)}</span>` : '',
        ].join('');

        const pnlCell = isOpen
            ? `<span class="pnl-open">OPEN</span>`
            : `<span class="${t.pnl >= 0 ? 'pnl-pos' : 'pnl-neg'}">${pnlArrow(t.pnl)} ₹${fmt(Math.abs(t.pnl))}</span>`;

        const pnlPctCell = t.pnl_pct !== null
            ? `<span class="${t.pnl_pct >= 0 ? 'pnl-pos' : 'pnl-neg'}" style="font-size:.75rem;">${t.pnl_pct > 0 ? '+' : ''}${t.pnl_pct}%</span>`
            : `<span class="na-val">—</span>`;

        return `<tr class="${rowCls}" data-symbol="${esc(t.symbol.toLowerCase())}">
            <td style="color:var(--text-muted);font-size:.68rem;">${i+1}</td>
            <td class="left"><span class="sym-cell">${esc(t.symbol)}</span><span class="sym-sub">${exSeg}</span></td>
            <td>${typeBadge}</td>
            <td class="buy-cell mono" style="border-left:2px solid rgba(16,185,129,.15);">${esc(t.buy_date)}</td>
            <td class="time-small">${t.buy_time || '—'}</td>
            <td class="buy-cell">${fmt(t.buy_qty)}</td>
            <td class="buy-cell price-val">₹${fmtR(t.buy_price)}</td>
            <td class="buy-cell">₹${fmt(t.buy_value)}</td>
            <td class="sell-cell mono" style="border-left:2px solid rgba(239,68,68,.15);">${t.sell_date || '—'}</td>
            <td class="time-small">${t.sell_time || '—'}</td>
            <td class="sell-cell">${t.sell_qty !== null ? fmt(t.sell_qty) : '—'}</td>
            <td class="sell-cell price-val">${t.sell_price !== null ? '₹'+fmtR(t.sell_price) : '—'}</td>
            <td class="sell-cell">${t.sell_value !== null ? '₹'+fmt(t.sell_value) : '—'}</td>
            <td style="border-left:2px solid var(--border-bright);color:var(--text-muted);font-size:.73rem;">${t.holding_days !== null ? t.holding_days+'d' : '—'}</td>
            <td>${pnlCell}</td>
            <td>${pnlPctCell}</td>
        </tr>`;
    }).join('');

    return `<div class="tbl-wrap">
        <div class="tbl-head">
            <h3>Trade-wise P&L — Buy → Sell (FIFO)</h3>
            <span class="tbl-count" id="tradeCount">${trades.length} pairs</span>
            <div class="search-wrap"><input type="text" class="search-input" id="tradeSearch" placeholder="🔍 Search symbol…"></div>
        </div>
        <div style="overflow-x:auto;">
        <table class="data-table" id="tradeTable">
            <thead><tr>
                <th class="left">#</th>
                <th class="left">Symbol</th>
                <th>Type</th>
                <th style="color:var(--bullish);border-left:2px solid rgba(16,185,129,.25);">Buy Date</th>
                <th style="color:var(--bullish);">Buy Time</th>
                <th style="color:var(--bullish);">Buy Qty</th>
                <th style="color:var(--bullish);">Buy Price</th>
                <th style="color:var(--bullish);">Buy Value</th>
                <th style="color:var(--bearish);border-left:2px solid rgba(239,68,68,.25);">Sell Date</th>
                <th style="color:var(--bearish);">Sell Time</th>
                <th style="color:var(--bearish);">Sell Qty</th>
                <th style="color:var(--bearish);">Sell Price</th>
                <th style="color:var(--bearish);">Sell Value</th>
                <th style="border-left:2px solid var(--border-bright);">Hold Days</th>
                <th>P&L ₹</th>
                <th>P&L %</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table></div></div>`;
}

function renderDayTable(days) {
    let running = 0;
    const absMax = Math.max(...days.map(d => Math.abs(d.realized)), 1);

    const rows = days.map((d, i) => {
        running += d.realized;
        const wr     = (d.winners + d.losers) > 0 ? Math.round(d.winners / (d.winners + d.losers) * 100) : 0;
        const barPct = Math.round(Math.abs(d.realized) / absMax * 100);
        const rCls   = d.realized >= 0 ? 'profit-row' : 'loss-row';
        const rColor = d.realized >= 0 ? 'var(--bullish)' : 'var(--bearish)';
        return `<tr class="${rCls}">
            <td style="color:var(--text-muted);font-size:.68rem;">${i+1}</td>
            <td class="left mono" style="font-weight:700;color:#38bdf8;">${esc(d.date)}</td>
            <td style="font-weight:600;">${d.trades}</td>
            <td class="buy-cell">${d.winners}</td>
            <td class="sell-cell">${d.losers}</td>
            <td><span style="font-size:.73rem;color:${wr>=50?'var(--bullish)':'var(--bearish)'};font-weight:700;">${wr}%</span></td>
            <td><span class="${d.realized>=0?'pnl-pos':'pnl-neg'}" style="font-size:.82rem;">${pnlArrow(d.realized)} ₹${fmt(Math.abs(d.realized))}</span></td>
            <td><span style="font-size:.73rem;font-weight:700;color:${running>=0?'var(--bullish)':'var(--bearish)'};">${running>=0?'+':''}₹${fmt(running)}</span></td>
            <td><div class="chart-bar-bg" style="width:90px;"><div class="chart-bar-fill" style="width:${barPct}%;background:${rColor};"></div></div></td>
        </tr>`;
    }).join('');

    const total = days.reduce((s, d) => s + d.realized, 0);
    return `<div class="tbl-wrap">
        <div class="tbl-head">
            <h3>Day-wise P&L</h3>
            <span class="tbl-count">${days.length} trading days</span>
            <span style="margin-left:auto;font-size:.78rem;font-weight:700;color:${pnlColor(total)};">
                Month Total: ${pnlArrow(total)} ₹${fmt(Math.abs(total))}
            </span>
        </div>
        <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr>
                <th class="left">#</th><th class="left">Date</th><th>Trades</th>
                <th>Winners</th><th>Losers</th><th>Win Rate</th>
                <th>Realized P&L</th><th>Running Total</th><th>Bar</th>
            </tr></thead>
            <tbody>${rows}</tbody>
        </table></div></div>`;
}

function renderReport(data) {
    const { paired_trades, day_wise_pnl, summary } = data;

    document.getElementById('reportArea').innerHTML = `
        ${renderSummary(summary)}
        <div class="tab-bar">
            <button class="tab-btn active" id="tabBtnTrades" onclick="showTab('trades',this)">
                📋 Trade-wise P&L (${paired_trades.length})
            </button>
            <button class="tab-btn" id="tabBtnDay" onclick="showTab('daywise',this)">
                📅 Day-wise P&L (${day_wise_pnl.length} days)
            </button>
        </div>
        <div id="tab-trades">${renderTradeTable(paired_trades)}</div>
        <div id="tab-daywise" style="display:none;">${renderDayTable(day_wise_pnl)}</div>
    `;

    // Wire up search
    const searchInput = document.getElementById('tradeSearch');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const q = searchInput.value.toLowerCase().trim();
            let vis = 0;
            document.querySelectorAll('#tradeTable tbody tr').forEach(row => {
                const show = !q || (row.dataset.symbol || '').includes(q);
                row.style.display = show ? '' : 'none';
                if (show) vis++;
            });
            const countEl = document.getElementById('tradeCount');
            if (countEl) countEl.textContent = vis + ' pairs';
        });
    }
}

function showTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-trades').style.display  = tab === 'trades'  ? '' : 'none';
    document.getElementById('tab-daywise').style.display = tab === 'daywise' ? '' : 'none';
}

function esc(s) {
    if (!s && s !== 0) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─────────────────────────────────────────────────────────
//  AUTO-LOAD on page open if params present
// ─────────────────────────────────────────────────────────
(function() {
    const broker = brokerSel.value;
    const month  = monthSel.value;
    if (broker && month) {
        // Tiny delay so page renders visibly before fetch starts
        setTimeout(loadReport, 80);
    }
})();
</script>
@endpush
@endsection