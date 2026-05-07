@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
.page-header {
    background: linear-gradient(135deg, #e65c00 0%, #f9d423 100%);
    color: white; padding: 18px 24px; border-radius: 12px;
    margin-bottom: 20px; box-shadow: 0 4px 15px rgba(230,92,0,0.4);
}
.page-header h4 { color: white; margin: 0; }
.page-header p  { color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 12px; }

.filter-bar {
    background: linear-gradient(135deg, #e65c00, #f9a825);
    padding: 12px 20px; border-radius: 12px; margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(230,92,0,0.4);
    display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
}
.filter-bar label { color: rgba(255,255,255,0.75) !important; font-size: 11px; font-weight: 700; margin: 0; }
.sym-select {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.35);
    color: white; border-radius: 8px; padding: 6px 12px; font-size: 12px;
    font-weight: 700; cursor: pointer; outline: none; min-width: 180px;
}
.sym-select option { background: #7a2e00; color: white; }
.sym-select:focus  { border-color: rgba(255,255,255,0.7); }
.date-input-wrap { display: flex; align-items: center; gap: 5px; }
.date-input-wrap input[type="date"] {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; color: white; padding: 5px 10px; font-size: 12px;
    font-weight: 600; cursor: pointer; outline: none;
}
.date-input-wrap input[type="date"]::-webkit-calendar-picker-indicator { filter: invert(1); cursor: pointer; }
.date-nav-btn {
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.3);
    color: white; border-radius: 6px; width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 14px; font-weight: 700; transition: .15s;
}
.date-nav-btn:hover { background: rgba(255,255,255,0.28); }
.date-nav-btn.today-btn { width: auto; padding: 0 10px; font-size: 10px; }
.btn-load {
    background: white; color: #e65c00; border: none; border-radius: 8px;
    padding: 7px 22px; font-weight: 800; font-size: 13px; cursor: pointer;
}
.btn-load:hover { background: #fff8f0; }
.auto-btn {
    background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3);
    border-radius: 8px; padding: 6px 14px; font-size: 11px; font-weight: 700; cursor: pointer;
}
.date-badge { font-size: 10px; font-weight: 700; padding: 2px 9px; border-radius: 10px; }
.badge-today { background: rgba(0,255,136,0.2); color: #00ff88; border: 1px solid rgba(0,255,136,0.3); }
.badge-hist  { background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.3); }
.last-upd  { font-size: 10px; color: rgba(255,255,255,0.6); margin-left: auto; }
.divider-v { width: 1px; height: 24px; background: rgba(255,255,255,0.2); flex-shrink: 0; }

.main-card {
    border-radius: 12px; overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
}
.table-scroll { overflow-x: auto; }
.sig-table { width: 100%; border-collapse: collapse; min-width: 1800px; }

.sig-table thead tr.hdr-group th {
    padding: 10px 10px 6px; text-align: center; font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .5px; white-space: nowrap;
    background: rgba(0,0,0,0.45); border-bottom: none;
}
.sig-table thead tr.hdr-cols th {
    padding: 6px 10px 9px; text-align: center; font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .3px; white-space: nowrap;
    background: rgba(0,0,0,0.35); color: rgba(255,255,255,0.55);
    border-bottom: 2px solid rgba(255,255,255,0.08);
}
.hdr-meta { color: rgba(255,255,255,0.5) !important; }
.hdr-ce   { color: #51cf66 !important; }
.hdr-pe   { color: #ff6b6b !important; }
.sub-ce   { color: #51cf66 !important; }
.sub-pe   { color: #ff6b6b !important; }
.sep-ce       { border-left: 2px solid rgba(81,207,102,0.35) !important; }
.sep-pe       { border-left: 2px solid rgba(255,107,107,0.35) !important; }
.sep-match-ce { border-left: 1px dashed rgba(81,207,102,0.25) !important; }
.sep-match-pe { border-left: 1px dashed rgba(255,107,107,0.25) !important; }

.sig-table tbody td {
    padding: 8px 10px; text-align: center; font-size: 11px;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    vertical-align: middle; white-space: nowrap;
}
.sig-table tbody tr:hover { background: rgba(255,255,255,0.05) !important; }
.sym-even { background: rgba(255,255,255,0.01); }
.sym-odd  { background: rgba(0,0,0,0.10); }

/* Slot badges */
.slot-badge {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 10px; font-weight: 800; font-family: monospace;
}
.s-0900 { background: rgba(255,243,205,0.2); color: #ffc107; border: 1px solid rgba(255,193,7,0.3); }
.s-1200 { background: rgba(212,237,218,0.15); color: #51cf66; border: 1px solid rgba(81,207,102,0.3); }
.s-1500 { background: rgba(248,215,218,0.15); color: #ff6b6b; border: 1px solid rgba(255,107,107,0.3); }
.s-1800 { background: rgba(209,236,241,0.15); color: #00d2ff; border: 1px solid rgba(0,210,255,0.3); }
.s-2100 { background: rgba(226,217,243,0.15); color: #b388ff; border: 1px solid rgba(179,136,255,0.3); }

.c-num    { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.35); }
.c-time   { font-size: 12px; font-weight: 800; color: #ffa94d; }
.c-strike { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 600; }
.c-o      { color: rgba(255,255,255,.55); }
.c-h      { color: #ff9f7f; font-weight: 600; }
.c-l      { color: #7fff9f; font-weight: 600; }
.c-close  { color: #17a2b8; font-weight: 700; }
.c-pp     { color: #ffd43b; font-weight: 800; }
.c-s1     { color: #51cf66; font-weight: 800; }
.c-r1     { color: #ff6b6b; font-weight: 800; }
.badge-sym {
    display: inline-block; padding: 2px 8px; border-radius: 6px;
    font-size: 10px; font-weight: 800;
    background: rgba(255,165,0,0.15); color: #ffa94d;
    border: 1px solid rgba(255,165,0,0.3);
}
.match-yes {
    display: inline-block; background: rgba(40,167,69,0.25); color: #51cf66;
    border: 1px solid rgba(40,167,69,0.5); border-radius: 6px;
    padding: 2px 7px; font-size: 9px; font-weight: 800; letter-spacing: .3px;
}
.match-no {
    display: inline-block; background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.2);
    border: 1px solid rgba(255,255,255,0.08); border-radius: 6px;
    padding: 2px 7px; font-size: 9px; font-weight: 600;
}
.spinner { width:36px; height:36px; border:4px solid rgba(255,255,255,0.12); border-top:4px solid #ffa94d; border-radius:50%; animation:spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.loading-wrap { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:70px; }
.no-data { text-align:center; padding:60px; color:rgba(255,255,255,0.3); font-size:13px; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>&#128202; MCX Pivot Analysis
                    <span style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;margin-left:6px;">ATM &middot; 3-Hr Candle</span>
                </h4>
                <p>
                    Symbols: CRUDEOIL &middot; CRUDEOILM &middot; NATURALGAS &nbsp;|&nbsp;
                    PP = (H+L+C)/3 &nbsp;&middot;&nbsp; S1 = 2P&minus;H &nbsp;&middot;&nbsp; R1 = 2P&minus;L &nbsp;&middot;&nbsp;
                    <strong style="color:#b2ffda;">S1 Match = S1 &ge; Low</strong> &nbsp;&middot;&nbsp;
                    <strong style="color:#ffb2b2;">R1 Match = R1 &ge; High</strong>
                </p>
            </div>
            <a href="{{ route('mcx-pivot.config.index') }}" class="btn btn-light btn-sm">&#9881; Manage Configs</a>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="filter-bar">
        <label>DATE:</label>
        <div class="date-input-wrap">
            <button class="date-nav-btn" onclick="shiftDate(-1)" title="Previous day">&#8249;</button>
            <input type="date" id="date-picker"
                value="{{ date('Y-m-d') }}"
                max="{{ date('Y-m-d') }}"
                onchange="loadData()">
            <button class="date-nav-btn" onclick="shiftDate(1)" title="Next day">&#8250;</button>
            <button class="date-nav-btn today-btn" onclick="goToday()">Today</button>
            <span id="date-badge"></span>
        </div>
        <div class="divider-v"></div>
        <label>SYMBOL:</label>
        <select id="sym-select" class="sym-select" onchange="loadData()">
            <option value="ALL">&#8212; All Symbols &#8212;</option>
            <option value="CRUDEOIL">CRUDEOIL</option>
            <option value="CRUDEOILM">CRUDEOILM</option>
            <option value="NATURALGAS">NATURALGAS</option>
        </select>
        <button class="btn-load" onclick="loadData()">&#8635; Load</button>
        <button class="auto-btn" id="auto-btn" onclick="toggleAuto()">&#9654; Auto 3min</button>
        <span id="auto-tag" style="font-size:10px;color:rgba(255,255,255,0.6);"></span>
        <span class="last-upd" id="last-upd"></span>
    </div>

    {{-- Signal table --}}
    <div class="main-card">
        <div class="table-scroll">
            <table class="sig-table">
                <thead>
                    <tr class="hdr-group">
                        <th colspan="3" class="hdr-meta">Meta</th>
                        <th colspan="10" class="hdr-ce sep-ce">&#128200; CE &mdash; ATM (own pivot)</th>
                        <th colspan="10" class="hdr-pe sep-pe">&#128201; PE &mdash; ATM (own pivot)</th>
                    </tr>
                    <tr class="hdr-cols">
                        <th class="hdr-meta">#</th>
                        <th class="hdr-meta">Slot</th>
                        <th class="hdr-meta">Symbol</th>

                        <th class="sub-ce sep-ce">Strike</th>
                        <th class="sub-ce">O</th>
                        <th class="sub-ce">H</th>
                        <th class="sub-ce">L</th>
                        <th class="sub-ce">C</th>
                        <th class="sub-ce">PP</th>
                        <th class="sub-ce" style="color:#51cf66 !important;font-weight:900;">S1 &#129001;</th>
                        <th class="sub-ce" style="color:#ff9f7f !important;font-weight:900;">R1 &#128997;</th>
                        <th class="sub-ce sep-match-ce" style="color:#51cf66 !important;">S1 Match<br><span style="font-size:8px;opacity:.6;font-weight:400;">S1 &ge; Low</span></th>
                        <th class="sub-ce" style="color:#ff9f7f !important;">R1 Match<br><span style="font-size:8px;opacity:.6;font-weight:400;">R1 &ge; High</span></th>

                        <th class="sub-pe sep-pe">Strike</th>
                        <th class="sub-pe">O</th>
                        <th class="sub-pe">H</th>
                        <th class="sub-pe">L</th>
                        <th class="sub-pe">C</th>
                        <th class="sub-pe">PP</th>
                        <th class="sub-pe" style="color:#51cf66 !important;font-weight:900;">S1 &#129001;</th>
                        <th class="sub-pe" style="color:#ff6b6b !important;font-weight:900;">R1 &#128997;</th>
                        <th class="sub-pe sep-match-pe" style="color:#51cf66 !important;">S1 Match<br><span style="font-size:8px;opacity:.6;font-weight:400;">S1 &ge; Low</span></th>
                        <th class="sub-pe" style="color:#ff6b6b !important;">R1 Match<br><span style="font-size:8px;opacity:.6;font-weight:400;">R1 &ge; High</span></th>
                    </tr>
                </thead>
                <tbody id="sig-tbody">
                    <tr><td colspan="23">
                        <div class="loading-wrap">
                            <div class="spinner"></div>
                            <div style="color:white;margin-top:14px;font-size:13px;">Loading MCX data...</div>
                        </div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>

@push('script')
<script>
let autoTimer        = null;
let availableSymbols = [];
const todayStr       = '{{ date("Y-m-d") }}';

$(document).ready(function () { loadData(); });

function getSelectedDate() { return document.getElementById('date-picker').value; }
function getSelectedSym()  { return document.getElementById('sym-select').value; }

function slotClass(t) {
    if (!t) return '';
    const h = parseInt(t.split(':')[0]);
    if (h < 12) return 's-0900';
    if (h < 15) return 's-1200';
    if (h < 18) return 's-1500';
    if (h < 21) return 's-1800';
    return 's-2100';
}

function shiftDate(days) {
    const picker = document.getElementById('date-picker');
    const d = new Date(picker.value);
    d.setDate(d.getDate() + days);
    const s = d.toISOString().split('T')[0];
    if (s > todayStr) return;
    picker.value = s;
    loadData();
}

function goToday() {
    document.getElementById('date-picker').value = todayStr;
    loadData();
}

function updateDateBadge(isToday) {
    const el = document.getElementById('date-badge');
    el.innerHTML = isToday
        ? '<span class="date-badge badge-today">&#9679; Live</span>'
        : '<span class="date-badge badge-hist">&#128197; Historical</span>';
}

function rebuildSymDropdown(symbols) {
    if (JSON.stringify(availableSymbols) === JSON.stringify(symbols)) return;
    availableSymbols = symbols;
    const sel  = document.getElementById('sym-select');
    const prev = sel.value;
    sel.innerHTML = '<option value="ALL">&#8212; All Symbols &#8212;</option>';
    symbols.forEach(function(s) {
        const opt = document.createElement('option');
        opt.value = s; opt.textContent = s;
        if (s === prev) opt.selected = true;
        sel.appendChild(opt);
    });
}

function toggleAuto() {
    if (autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        $('#auto-btn').text('\u25b6 Auto 3min'); $('#auto-tag').text('');
    } else {
        autoTimer = setInterval(loadData, 180000);
        $('#auto-btn').text('\u25a0 Stop');
        $('#auto-tag').css('color','#51cf66').text('\u25cf live');
        loadData();
    }
}

function loadData() {
    const date = getSelectedDate();
    const sym  = getSelectedSym();

    if (date !== todayStr && autoTimer) {
        clearInterval(autoTimer); autoTimer = null;
        $('#auto-btn').text('\u25b6 Auto 3min'); $('#auto-tag').text('');
    }

    $('#sig-tbody').html('<tr><td colspan="23"><div class="loading-wrap"><div class="spinner"></div><div style="color:white;margin-top:12px;font-size:13px;">Fetching ' + date + '&hellip;</div></div></td></tr>');

    $.ajax({
        url : '{{ route("mcx-pivot.signals") }}',
        data: { symbol: sym, date: date },
        success: function(res) {
            updateDateBadge(res.is_today);
            if (res.available_symbols && res.available_symbols.length) {
                rebuildSymDropdown(res.available_symbols);
            }
            if (!res.success || !res.data || !res.data.length) {
                $('#sig-tbody').html('<tr><td colspan="23"><div class="no-data"><i class="fas fa-oil-can" style="font-size:2.5rem;opacity:.3;"></i><p style="margin-top:14px;">' + (res.message || 'No MCX data for ' + date) + '</p><small style="color:rgba(255,255,255,.2);">Check if market was open on this date.</small></div></td></tr>');
                return;
            }
            renderTable(res.data);
            $('#last-upd').text('Updated: ' + new Date().toLocaleTimeString());
        },
        error: function(xhr) {
            $('#sig-tbody').html('<tr><td colspan="23"><div class="no-data">\u26a0 ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'Server error') + '</div></td></tr>');
        }
    });
}

function renderTable(dataArr) {
    let rows   = '';
    let rowNum = 1;

    dataArr.forEach(function(d, si) {
        const signals = d.signals || [];
        if (!signals.length) return;

        // Group CE and PE signals by time slot
        const times = {};
        signals.forEach(function(s) {
            if (!times[s.time]) times[s.time] = { ce: null, pe: null };
            times[s.time][s.type.toLowerCase()] = s;
        });

        const zebraClass = si % 2 === 0 ? 'sym-even' : 'sym-odd';

        Object.entries(times).forEach(function([time, row]) {
            const ce = row.ce;
            const pe = row.pe;

            const ceS1Match = ce ? ce.s1_match : null;
            const ceR1Match = ce ? ce.r1_match : null;
            const peS1Match = pe ? pe.s1_match : null;
            const peR1Match = pe ? pe.r1_match : null;

            const ceCells = ce
                ? '<td class="sep-ce c-strike">\u20b9' + n(ce.strike) + '</td>'
                + '<td class="c-o">\u20b9'     + n(ce.open)  + '</td>'
                + '<td class="c-h">\u20b9'     + n(ce.high)  + '</td>'
                + '<td class="c-l">\u20b9'     + n(ce.low)   + '</td>'
                + '<td class="c-close">\u20b9' + n(ce.close) + '</td>'
                + '<td class="c-pp">\u20b9'    + n(ce.PP)    + '</td>'
                + '<td class="c-s1" title="CE S1 - BUY">\u20b9'  + n(ce.S1) + '</td>'
                + '<td class="c-r1" title="CE R1 - SELL">\u20b9' + n(ce.R1) + '</td>'
                + '<td class="sep-match-ce">' + matchPill(ceS1Match) + '</td>'
                + '<td>'                      + matchPill(ceR1Match) + '</td>'
                : '<td colspan="10" class="sep-ce" style="color:rgba(255,255,255,.12);font-size:9px;">&mdash; no CE data &mdash;</td>';

            const peCells = pe
                ? '<td class="sep-pe c-strike">\u20b9' + n(pe.strike) + '</td>'
                + '<td class="c-o">\u20b9'     + n(pe.open)  + '</td>'
                + '<td class="c-h">\u20b9'     + n(pe.high)  + '</td>'
                + '<td class="c-l">\u20b9'     + n(pe.low)   + '</td>'
                + '<td class="c-close">\u20b9' + n(pe.close) + '</td>'
                + '<td class="c-pp">\u20b9'    + n(pe.PP)    + '</td>'
                + '<td class="c-s1" title="PE S1 - BUY">\u20b9'  + n(pe.S1) + '</td>'
                + '<td class="c-r1" title="PE R1 - SELL">\u20b9' + n(pe.R1) + '</td>'
                + '<td class="sep-match-pe">' + matchPill(peS1Match) + '</td>'
                + '<td>'                      + matchPill(peR1Match) + '</td>'
                : '<td colspan="10" class="sep-pe" style="color:rgba(255,255,255,.12);font-size:9px;">&mdash; no PE data &mdash;</td>';

            const slotCls = slotClass(time);

            rows += '<tr class="' + zebraClass + '">'
                + '<td class="c-num">' + rowNum++ + '</td>'
                + '<td class="c-time"><span class="slot-badge ' + slotCls + '">' + time + '</span></td>'
                + '<td><span class="badge-sym">' + d.symbol + '</span></td>'
                + ceCells + peCells
                + '</tr>';
        });
    });

    if (!rows) {
        rows = '<tr><td colspan="23"><div class="no-data">No candle data found.</div></td></tr>';
    }
    $('#sig-tbody').html(rows);
}

function matchPill(matched) {
    if (matched === null || matched === undefined)
        return '<span style="color:rgba(255,255,255,.15);font-size:9px;">&mdash;</span>';
    return matched
        ? '<span class="match-yes">\u2713 YES</span>'
        : '<span class="match-no">\u2717 NO</span>';
}

function n(v) {
    if (v == null || v === '' || v === undefined) return '\u2014';
    return Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>
@endpush
@endsection