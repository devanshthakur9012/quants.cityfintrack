@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .page-header {
        background: linear-gradient(135deg, #11998e, #38ef7d);
        color: white; padding: 18px 24px; border-radius: 12px;
        margin-bottom: 20px; box-shadow: 0 4px 15px rgba(17,153,142,0.4);
    }
    .page-header h4 { color: white; margin: 0; }
    .page-header p  { color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 12px; }

    /* Symbol tabs */
    .sym-filter-wrap { display:flex; align-items:center; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
    .sym-tab { padding:5px 18px; border-radius:20px; font-size:12px; font-weight:700; cursor:pointer;
        border:2px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.05);
        color:rgba(255,255,255,0.5); transition:.15s; }
    .sym-tab:hover { color:white; border-color:rgba(255,255,255,0.3); }
    .s-all   { background:rgba(0,210,255,0.15)!important; color:#00d2ff!important; border-color:#00d2ff!important; }
    .s-nifty { background:rgba(40,167,69,0.2)!important;  color:#51cf66!important; border-color:#51cf66!important; }
    .s-bank  { background:rgba(255,193,7,0.15)!important; color:#ffc107!important; border-color:#ffc107!important; }

    /* Info bar */
    .info-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        background:rgba(0,210,255,0.07); border:1px solid rgba(0,210,255,0.2);
        border-radius:10px; padding:10px 16px; margin-bottom:16px; font-size:11px; }
    .info-bar .lbl { color:rgba(255,255,255,0.4); }
    .info-bar .val { color:#00d2ff; font-weight:700; }
    .info-bar .sep { color:rgba(255,255,255,0.15); }

    /* Symbol section */
    .sym-section { margin-bottom:28px; }
    .sym-section-header { display:flex; align-items:center; gap:10px; flex-wrap:wrap;
        padding:12px 16px; border-radius:10px 10px 0 0; border:2px solid; border-bottom:none;
        font-size:14px; font-weight:800; }
    .sym-section-header.nifty { background:linear-gradient(135deg,rgba(40,167,69,0.25),rgba(40,167,69,0.1));
        color:#51cf66; border-color:rgba(40,167,69,0.5); }
    .sym-section-header.bank  { background:linear-gradient(135deg,rgba(255,193,7,0.2),rgba(255,193,7,0.08));
        color:#ffc107; border-color:rgba(255,193,7,0.4); }

    /* ATM strip */
    .atm-strip { display:grid; grid-template-columns:1fr 1fr; border:2px solid; border-top:none; border-bottom:none; }
    .atm-strip.nifty { border-color:rgba(40,167,69,0.5); }
    .atm-strip.bank  { border-color:rgba(255,193,7,0.4); }
    .atm-cell { padding:10px 16px; border-right:1px solid rgba(255,255,255,0.07); background:rgba(0,0,0,0.2); }
    .atm-cell:last-child { border-right:none; }
    .atm-type { font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; margin-bottom:4px; }
    .atm-type.ce { color:#51cf66; }
    .atm-type.pe { color:#ff6b6b; }
    .atm-sym { font-size:11px; font-weight:700; color:white; }
    .atm-ltp { font-size:20px; font-weight:800; color:white; margin-top:2px; }
    .atm-strike { font-size:9px; color:rgba(255,255,255,0.4); margin-top:1px; }

    /* Table */
    .table-card { border:2px solid; border-top:none; border-radius:0 0 12px 12px;
        overflow:hidden; background:rgba(255,255,255,0.02); }
    .table-card.nifty { border-color:rgba(40,167,69,0.5); }
    .table-card.bank  { border-color:rgba(255,193,7,0.4); }
    .table-scroll { overflow-x:auto; }
    .sig-table { width:100%; border-collapse:collapse; min-width:1100px; }
    .sig-table thead th { padding:9px 10px; text-align:center; font-size:10px; font-weight:700;
        text-transform:uppercase; letter-spacing:.4px; white-space:nowrap;
        background:rgba(0,0,0,0.3); color:rgba(255,255,255,0.6);
        border-bottom:1px solid rgba(255,255,255,0.1); }
    .sig-table tbody td { padding:9px 10px; text-align:center; font-size:11px;
        border-bottom:1px solid rgba(255,255,255,0.04); vertical-align:middle; white-space:nowrap; }
    .sig-table tbody tr:hover { background:rgba(255,255,255,0.04); }
    .sig-table tbody tr:last-child td { border-bottom:none; }

    /* Row tints */
    .ce-row { background:rgba(40,167,69,0.04); }
    .ce-row:hover { background:rgba(40,167,69,0.09)!important; }
    .pe-row { background:rgba(220,53,69,0.04); }
    .pe-row:hover { background:rgba(220,53,69,0.09)!important; }

    .type-ce { color:#51cf66; font-weight:800; font-size:12px; }
    .type-pe { color:#ff6b6b; font-weight:800; font-size:12px; }

    /* Pivot level cells */
    .pp-val  { color:#00d2ff; font-weight:800; font-size:12px; }
    .r1-val  { color:#ff6b6b; font-weight:800; font-size:12px; }
    .s1-val  { color:#51cf66; font-weight:800; font-size:12px; }
    .tgt-val { color:#ffc107; font-weight:700; font-size:11px; }
    .time-col { color:rgba(255,255,255,0.5); font-size:10px; font-weight:700; letter-spacing:.3px; }

    /* Count pills */
    .count-pill { background:rgba(255,255,255,0.18); color:white; padding:2px 9px;
        border-radius:10px; font-size:10px; font-weight:700; }
    .active-pill { background:rgba(0,229,255,0.2); color:#00d2ff;
        border:1px solid rgba(0,229,255,0.35); padding:2px 9px; border-radius:10px; font-size:10px; font-weight:700; }
    .expiry-tag { font-size:10px; font-weight:600; color:rgba(255,255,255,0.45);
        background:rgba(255,255,255,0.07); padding:2px 8px; border-radius:6px; }

    /* filter bar */
    .filter-bar { background:linear-gradient(135deg,#667eea,#764ba2); padding:12px 20px;
        border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4);
        display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .filter-bar button.btn-load { background:white; color:#667eea; border:none; border-radius:8px;
        padding:7px 24px; font-weight:800; font-size:13px; cursor:pointer; transition:.15s; }
    .filter-bar button.btn-load:hover { background:#f0f0ff; }
    .filter-bar .auto-btn { background:rgba(255,255,255,0.15); color:white; border:1px solid rgba(255,255,255,0.3);
        border-radius:8px; padding:6px 14px; font-size:11px; font-weight:700; cursor:pointer; }
    .filter-bar .last-upd { font-size:10px; color:rgba(255,255,255,0.5); margin-left:auto; }

    /* loading */
    .loading-overlay { position:absolute; top:0; left:0; right:0; bottom:0;
        background:rgba(15,32,39,0.93); display:flex; flex-direction:column;
        justify-content:center; align-items:center; z-index:100; border-radius:12px; }
    .spinner { width:40px; height:40px; border:4px solid rgba(255,255,255,0.15);
        border-top:4px solid #00d2ff; border-radius:50%; animation:spin 1s linear infinite; }
    .loading-text { color:white; margin-top:14px; font-size:14px; font-weight:600; }
    @keyframes spin { to { transform:rotate(360deg); } }

    .no-data { text-align:center; padding:50px; color:rgba(255,255,255,0.3); font-size:13px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>⚡ Pivot Signal — NIFTY &amp; BANKNIFTY &nbsp;
                    <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;">ATM Options · 15-min Candle</span>
                </h4>
                <p>
                    Pivot = Prev 15-min candle (H+L+C)/3 &nbsp;·&nbsp;
                    R1 = 2P−L &nbsp;·&nbsp; S1 = 2P−H &nbsp;·&nbsp;
                    <strong>CE: BUY @ S1 · SELL @ P+2%</strong> &nbsp;|&nbsp;
                    <strong>PE: BUY @ R1 · SELL @ P−2%</strong>
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('9to12.pece-analysis') }}" class="btn btn-light btn-sm">OI Analysis</a>
                <a href="{{ route('oiiv-auto.index') }}"     class="btn btn-light btn-sm">OI+IV</a>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="filter-bar">
        <span style="color:white;font-size:11px;font-weight:700;">SYMBOL:</span>
        <div class="sym-filter-wrap" style="margin:0;">
            <button class="sym-tab s-all"  data-sym="ALL"       onclick="setSym(this)">ALL</button>
            <button class="sym-tab"        data-sym="NIFTY"     onclick="setSym(this)">NIFTY</button>
            <button class="sym-tab"        data-sym="BANKNIFTY" onclick="setSym(this)">BANKNIFTY</button>
        </div>
        <button class="btn-load" onclick="loadData()">⟳ Load Signals</button>
        <button class="auto-btn" id="auto-btn" onclick="toggleAuto()">▶ Auto Refresh</button>
        <span id="auto-tag"  style="font-size:10px;color:rgba(255,255,255,0.5);"></span>
        <span class="last-upd" id="last-upd"></span>
    </div>

    {{-- Content --}}
    <div style="position:relative;min-height:300px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Loading ATM option signals...</div>
        </div>
        <div id="pv-content">
            <div class="no-data">
                <i class="fas fa-chart-line" style="font-size:3rem;opacity:.3;"></i>
                <p style="margin-top:16px;">Click <strong style="color:#00d2ff;">Load Signals</strong> to start</p>
            </div>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
let activeSym  = 'ALL';
let autoTimer  = null;

$(document).ready(function() {
    loadData();
});

// ── Symbol ────────────────────────────────────────────────────────────────────
function setSym(el) {
    $('.sym-tab').removeClass('s-all s-nifty s-bank');
    activeSym = $(el).data('sym');
    $(el).addClass(activeSym==='ALL' ? 's-all' : activeSym==='NIFTY' ? 's-nifty' : 's-bank');
    loadData();
}

// ── Auto ──────────────────────────────────────────────────────────────────────
function toggleAuto() {
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        $('#auto-btn').text('▶ Auto Refresh'); $('#auto-tag').text('');
    } else {
        autoTimer = setInterval(loadData, 60000);
        $('#auto-btn').text('■ Stop');
        $('#auto-tag').css('color','#51cf66').text('● 60s');
        loadData();
    }
}

// ── Load ──────────────────────────────────────────────────────────────────────
function loadData() {
    $('#loading-overlay').show();
    $.ajax({
        url : '{{ route("pivot.signals") }}',
        data: { symbol: activeSym },
        success(res) {
            $('#loading-overlay').hide();
            if (res.success && res.data?.length) {
                renderAll(res.data);
                $('#last-upd').text('Updated: ' + new Date().toLocaleTimeString());
            } else {
                $('#pv-content').html(`<div class="no-data">⚠ ${res.message || 'No ATM option data found for today'}</div>`);
            }
        },
        error(xhr) {
            $('#loading-overlay').hide();
            $('#pv-content').html(`<div class="no-data">⚠ ${xhr.responseJSON?.message || 'Server error'}</div>`);
        }
    });
}

// ── Render ────────────────────────────────────────────────────────────────────
function renderAll(data) {
    $('#pv-content').html(data.map(renderCard).join(''));
}

function renderCard(d) {
    const cls  = d.symbol === 'NIFTY' ? 'nifty' : 'bank';
    const icon = d.symbol === 'NIFTY' ? '⚡' : '🏦';

    return `
    <div class="sym-section">
        <div class="sym-section-header ${cls}">
            ${icon} ${d.symbol}
            <span class="expiry-tag">Expiry: ${d.expiry}</span>
            <span class="count-pill">${d.signal_count} rows</span>
            <span class="active-pill">🎯 ${d.active_count} signals</span>
            <span style="margin-left:auto;font-size:12px;color:rgba(255,255,255,.5);">${d.date} &nbsp; ${d.latest_time||''}</span>
        </div>

        {{-- ATM CE / PE strip --}}
        <div class="atm-strip ${cls}">
            <div class="atm-cell">
                <div class="atm-type ce">📈 ATM CE</div>
                <div class="atm-sym">${d.ce_symbol || '—'}</div>
                <div class="atm-strike">Strike: ${d.ce_strike || '—'}</div>
                <div class="atm-ltp">₹${n(d.ce_ltp)}</div>
            </div>
            <div class="atm-cell">
                <div class="atm-type pe">📉 ATM PE</div>
                <div class="atm-sym">${d.pe_symbol || '—'}</div>
                <div class="atm-strike">Strike: ${d.pe_strike || '—'}</div>
                <div class="atm-ltp">₹${n(d.pe_ltp)}</div>
            </div>
        </div>

        {{-- Signal table --}}
        <div class="table-card ${cls}">
            <div class="table-scroll">
                <table class="sig-table">
                    <thead>
                        <tr>
                            <th rowspan="2">#</th>
                            <th rowspan="2">Date &amp; Time</th>
                            <th rowspan="2">Symbol</th>
                            <th colspan="7" style="color:#51cf66;border-bottom:1px solid rgba(40,167,69,0.4);">📈 CE — ATM</th>
                            <th colspan="7" style="color:#ff6b6b;border-bottom:1px solid rgba(220,53,69,0.4);">📉 PE — ATM</th>
                        </tr>
                        <tr>
                            <th style="color:#51cf66;font-size:9px;">Open</th>
                            <th style="color:#51cf66;font-size:9px;">High</th>
                            <th style="color:#51cf66;font-size:9px;">Low</th>
                            <th style="color:#51cf66;font-size:9px;">Close</th>
                            <th style="color:#00d2ff;font-size:9px;">PP</th>
                            <th style="color:#51cf66;font-size:9px;">S1</th>
                            <th style="color:#ff6b6b;font-size:9px;">R1</th>
                            <th style="color:#ff6b6b;font-size:9px;">Open</th>
                            <th style="color:#ff6b6b;font-size:9px;">High</th>
                            <th style="color:#ff6b6b;font-size:9px;">Low</th>
                            <th style="color:#ff6b6b;font-size:9px;">Close</th>
                            <th style="color:#00d2ff;font-size:9px;">PP</th>
                            <th style="color:#51cf66;font-size:9px;">S1</th>
                            <th style="color:#ff6b6b;font-size:9px;">R1</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${buildRows(d.signals, d.date)}
                    </tbody>
                </table>
            </div>
        </div>
    </div>`;
}

function buildRows(signals, date) {
    if (!signals?.length)
        return `<tr><td colspan="17" style="text-align:center;padding:28px;color:rgba(255,255,255,.3);font-style:italic;">No candle data yet today</td></tr>`;

    // Group by time — each time slot = 1 row with CE side and PE side
    const times = {};
    signals.forEach(s => {
        if (!times[s.time]) times[s.time] = { ce: null, pe: null };
        if (s.type === 'CE') times[s.time].ce = s;
        else                  times[s.time].pe = s;
    });

    const f = (val, color) => val !== null && val !== undefined
        ? `<span style="font-size:9px;color:${color};font-weight:700;">₹${n(val)}</span>`
        : `<span style="color:rgba(255,255,255,.2);font-size:9px;">—</span>`;

    return Object.entries(times).map(([time, row], i) => {
        const ce = row.ce, pe = row.pe;
        const ceSym = ce?.option_symbol || '—';
        const peSym = pe?.option_symbol || '—';

        return `<tr style="border-bottom:1px solid rgba(255,255,255,0.06);">
            <td style="font-size:10px;font-weight:700;">${i+1}</td>
            <td style="white-space:nowrap;">
                <div style="font-size:9px;color:rgba(255,255,255,.5);">${date||''}</div>
                <div style="font-size:12px;font-weight:800;color:#00d2ff;">${time}</div>
            </td>
            <td style="font-size:8px;line-height:2;">
                <div style="color:#51cf66;">${ceSym}</div>
                <div style="color:#ff6b6b;">${peSym}</div>
            </td>
            ${ce ? `
            <td>${f(ce.open,  'rgba(255,255,255,.55)')}</td>
            <td>${f(ce.high,  '#ff6b6b')}</td>
            <td>${f(ce.low,   '#51cf66')}</td>
            <td>${f(ce.close, '#17a2b8')}</td>
            <td style="font-size:9px;font-weight:800;color:#00d2ff;">₹${n(ce.PP)}</td>
            <td style="font-size:9px;font-weight:800;color:#51cf66;">₹${n(ce.S1)}</td>
            <td style="font-size:9px;font-weight:800;color:#ff6b6b;">₹${n(ce.R1)}</td>
            ` : '<td colspan="7" style="color:rgba(255,255,255,.2);font-size:9px;">—</td>'}
            ${pe ? `
            <td>${f(pe.open,  'rgba(255,255,255,.55)')}</td>
            <td>${f(pe.high,  '#ff6b6b')}</td>
            <td>${f(pe.low,   '#51cf66')}</td>
            <td>${f(pe.close, '#17a2b8')}</td>
            <td style="font-size:9px;font-weight:800;color:#00d2ff;">₹${n(pe.PP)}</td>
            <td style="font-size:9px;font-weight:800;color:#51cf66;">₹${n(pe.S1)}</td>
            <td style="font-size:9px;font-weight:800;color:#ff6b6b;">₹${n(pe.R1)}</td>
            ` : '<td colspan="7" style="color:rgba(255,255,255,.2);font-size:9px;">—</td>'}
        </tr>`;
    }).join('');
}

function n(v) {
    if (v === null || v === undefined || v === '') return '—';
    return Number(v).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
}
</script>
@endpush