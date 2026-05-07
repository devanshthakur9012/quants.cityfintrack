@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: center !important;
        padding: 8px 10px !important;
        font-size: 12px !important;
        vertical-align: middle;
    }
    .custom--table thead th:nth-child(1),
    .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2),
    .custom--table tbody td:nth-child(2) { text-align: left !important; }
    .table-responsive { overflow-x: auto; }

    .loading-overlay {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(10,28,60,0.96);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 1000; border-radius: 0 0 12px 12px;
    }
    .spinner { width:55px;height:55px;border:5px solid #f3f3f3;border-top:5px solid #3b82f6;border-radius:50%;animation:spin 1s linear infinite; }
    .loading-text { color:white;margin-top:20px;font-size:15px;font-weight:600; }
    .loading-sub  { color:#aaa;margin-top:6px;font-size:12px; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    .page-header {
        background: linear-gradient(135deg,#0a1c3c,#1a3a6c,#2563eb);
        color:white;padding:22px;border-radius:12px;
        margin-bottom:20px;box-shadow:0 4px 20px rgba(37,99,235,0.35);
    }
    .filter-section {
        background: linear-gradient(135deg,#1e3a5f,#1a3a6c);
        padding:20px;border-radius:12px;margin-bottom:20px;
        box-shadow:0 4px 15px rgba(37,99,235,0.25);
    }
    .filter-section label { color:white !important;font-weight:600;margin-bottom:6px;font-size:13px; }
    .filter-section .form-control,
    .filter-section .form-select { border:2px solid rgba(255,255,255,0.3);background:rgba(255,255,255,0.93);color:#333;font-size:12px;padding:6px 10px; }

    .stats-box {
        background:#fff;padding:14px;border-radius:10px;
        text-align:center;border-left:4px solid #2563eb;
        margin-bottom:12px;box-shadow:0 3px 10px rgba(0,0,0,.1);transition:transform .2s;
    }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block;color:#666;font-size:10px;text-transform:uppercase;letter-spacing:.3px; }
    .stats-box strong { display:block;font-size:1.5rem;font-weight:700;margin-top:3px; }

    .sig-buy  { background:#dcfce7;color:#15803d;padding:3px 10px;border-radius:5px;font-weight:800;font-size:12px; }
    .sig-sell { background:#fee2e2;color:#b91c1c;padding:3px 10px;border-radius:5px;font-weight:800;font-size:12px; }
    .sig-hold { background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:5px;font-weight:800;font-size:12px; }

    .conf-bar-wrap { background:#e5e7eb;border-radius:20px;height:8px;width:70px;display:inline-block;vertical-align:middle;margin-left:5px; }
    .conf-bar-fill { height:8px;border-radius:20px; }
    .conf-excellent { color:#15803d;font-weight:800;font-size:13px; }
    .conf-good      { color:#22c55e;font-weight:700;font-size:13px; }
    .conf-ok        { color:#f59e0b;font-weight:700;font-size:13px; }
    .conf-low       { color:#ef4444;font-weight:700;font-size:13px; }

    .rank-badge { display:inline-block;width:28px;height:28px;line-height:28px;border-radius:50%;font-size:11px;font-weight:700;color:white;text-align:center; }
    .rank-1 { background:linear-gradient(135deg,#f59e0b,#d97706); }
    .rank-2 { background:linear-gradient(135deg,#9ca3af,#6b7280); }
    .rank-3 { background:linear-gradient(135deg,#cd7f32,#a0522d); }
    .rank-n { background:linear-gradient(135deg,#2563eb,#1d4ed8);font-size:10px; }

    .symbol-name { color:#1e3a6c;font-weight:800;font-size:13px;cursor:pointer; }
    .symbol-name:hover { text-decoration:underline;color:#2563eb; }

    .badge-ob  { background:#fee2e2;color:#b91c1c;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700; }
    .badge-os  { background:#dcfce7;color:#15803d;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700; }
    .badge-neu { background:#f3f4f6;color:#6b7280;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:600; }
    .badge-pat { background:#ede9fe;color:#5b21b6;padding:2px 7px;border-radius:4px;font-size:10px;font-weight:700; }

    .trend-up   { color:#15803d;font-weight:700;font-size:12px; }
    .trend-down { color:#b91c1c;font-weight:700;font-size:12px; }
    .trend-side { color:#f59e0b;font-weight:600;font-size:12px; }

    .vol-dot { display:inline-block;width:8px;height:8px;border-radius:50%;background:#f59e0b;margin-left:3px;vertical-align:middle; }

    .table-card { border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12);position:relative;min-height:400px; }
    .table-card-header { background:linear-gradient(135deg,#0a1c3c,#1a3a6c);color:white;padding:14px 20px;font-weight:700;font-size:14px;display:flex;align-items:center;gap:10px; }

    .info-pill   { background:rgba(255,255,255,0.15);color:white;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600; }
    .quant-badge { background:linear-gradient(135deg,#22c55e,#16a34a);color:white;padding:2px 7px;border-radius:3px;font-size:9px;font-weight:700; }
    .compare-btn { background:rgba(255,255,255,0.15);color:white;border:1px solid rgba(255,255,255,0.3);padding:4px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none; }
    .compare-btn:hover { background:rgba(255,255,255,0.25);color:white;text-decoration:none; }

    .logic-strip {
        background:linear-gradient(135deg,#0a1c3c,#0f2a52);
        color:#93c5fd;font-size:11px;padding:10px 20px;
        display:flex;gap:18px;flex-wrap:wrap;align-items:center;
        border-bottom:1px solid rgba(255,255,255,0.06);
    }
    .logic-strip span { color:#bfdbfe;font-weight:700; }

    #symbol_search { max-width:220px;font-size:12px;background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.3);color:white; }
    #symbol_search::placeholder { color:rgba(255,255,255,0.5); }

    /* ── Detail Modal ─────────────────────────────────────── */
    .modal-header  { background:linear-gradient(135deg,#0a1c3c,#1a3a6c);color:white;border:none;padding:16px 20px; }
    .modal-header .btn-close { filter:invert(1); }
    .detail-section { background:#f8fafc;border-radius:10px;padding:14px;margin-bottom:14px; }
    .detail-section h6 { font-weight:700;color:#1e3a6c;margin-bottom:10px;font-size:12px;text-transform:uppercase;letter-spacing:.5px; }
    .score-row { display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #e5e7eb;font-size:12px; }
    .score-row:last-child { border-bottom:none; }
    .score-pos { color:#15803d;font-weight:700; }
    .score-neg { color:#b91c1c;font-weight:700; }
    .score-neu { color:#64748b;font-weight:700; }
    .history-row { display:flex;gap:10px;align-items:center;padding:5px 0;font-size:12px;border-bottom:1px solid #e5e7eb; }
    .history-row:last-child { border-bottom:none; }
    .detail-label { font-size:10px;text-transform:uppercase;color:#94a3b8;letter-spacing:.3px; }
    .detail-val   { font-size:14px;font-weight:700;color:#1e293b; }
    .pivot-tag { display:inline-block;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;margin:2px; }
    .pivot-high { background:#fee2e2;color:#b91c1c; }
    .pivot-low  { background:#dcfce7;color:#15803d; }
    .pat-bull { display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;margin:2px;background:#ede9fe;color:#5b21b6; }
    .pat-bear { display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;margin:2px;background:#fee2e2;color:#b91c1c; }
    .sim-bar-outer { background:#e5e7eb;border-radius:20px;height:12px;overflow:hidden;margin-top:5px; }
    .sim-bar-inner { height:12px;border-radius:20px;transition:width .5s; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── HEADER ──────────────────────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
            <div>
                <h4 style="margin:0;">
                    📈 Stock Signal Scanner
                    <span class="quant-badge">QUANT ENGINE</span>
                </h4>
                <p style="margin:4px 0 0;font-size:11px;opacity:.8;line-height:2;">
                    🟢 <strong>BUY</strong>: confidence ≥ 65% &nbsp;|&nbsp;
                    🔴 <strong>SELL</strong>: confidence ≤ 35% &nbsp;|&nbsp;
                    🟡 <strong>HOLD</strong>: 36–64% &nbsp;|&nbsp;
                    Score = Pattern(±30) + Similarity(±30) + Pivot(±20) + Volume(±10) + Trend(±10) &nbsp;|&nbsp;
                    🔮 Similarity = Statistical past-state matching
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('universal-btst.index') }}"         class="compare-btn">📊 BTST Scanner</a>
                <a href="{{ route('universal-btst-reverse.index') }}" class="compare-btn">🔄 Reverse BTST</a>
                <a href="{{ route('oiiv-auto.index') }}"              class="compare-btn"><i class="fas fa-chart-line"></i> OI+IV</a>
            </div>
        </div>
    </div>

    {{-- ── FILTERS ──────────────────────────────────────────────────────────── --}}
    <div class="filter-section">
        <div class="row align-items-end">
            <div class="col-md-2 col-sm-6">
                <label><i class="fas fa-calendar-alt"></i> From Date</label>
                <input type="date" id="ss_from" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2 col-sm-6">
                <label><i class="fas fa-calendar-alt"></i> To Date</label>
                <input type="date" id="ss_to" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-2 col-sm-6">
                <label><i class="fas fa-filter"></i> Signal Type</label>
                <select id="ss_signal" class="form-control">
                    <option value="ALL">All Signals</option>
                    <option value="BUY">🟢 BUY only</option>
                    <option value="SELL">🔴 SELL only</option>
                    <option value="HOLD">🟡 HOLD only</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label><i class="fas fa-tachometer-alt"></i> Min Confidence</label>
                <select id="ss_minconf" class="form-control">
                    <option value="0">All (0%+)</option>
                    <option value="50">50%+</option>
                    <option value="60">60%+</option>
                    <option value="65" selected>65%+ (Strong)</option>
                    <option value="70">70%+</option>
                    <option value="80">80%+</option>
                </select>
            </div>
            <div class="col-md-4 col-sm-12 text-center mt-3 mt-md-0" style="padding-top:22px;">
                <button type="button" id="ss_run" class="btn btn-light btn-lg" style="min-width:190px;font-size:13px;font-weight:700;">
                    <i class="fas fa-search"></i> Scan Signals
                </button>
                <button type="button" id="ss_reset" class="btn btn-outline-light btn-lg ml-2" style="min-width:100px;font-size:13px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ── SUMMARY STATS ────────────────────────────────────────────────────── --}}
    <div class="row mb-3" id="summary_stats" style="display:none;">
        <div class="col-6 col-md-2">
            <div class="stats-box">
                <small>Symbols Found</small>
                <strong id="st_symbols" class="text-dark">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#15803d;">
                <small>🟢 BUY Today</small>
                <strong id="st_today_buys" style="color:#15803d;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#b91c1c;">
                <small>🔴 SELL Today</small>
                <strong id="st_today_sells" style="color:#b91c1c;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#22c55e;">
                <small>Conf ≥ 70%</small>
                <strong id="st_high" style="color:#16a34a;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#f59e0b;">
                <small>Conf 50–69%</small>
                <strong id="st_med" style="color:#f59e0b;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#2563eb;">
                <small>Total Signals</small>
                <strong id="st_total" style="color:#2563eb;">0</strong>
            </div>
        </div>
    </div>

    {{-- ── TABLE ────────────────────────────────────────────────────────────── --}}
    <div class="table-card">
        <div class="table-card-header">
            <span>📈 Signal Results</span>
            <span id="result_pill" class="info-pill" style="display:none;"></span>
            <div style="margin-left:auto;">
                <input type="text" id="symbol_search" class="form-control form-control-sm"
                       placeholder="🔍 Filter symbol…" style="display:none;">
            </div>
        </div>

        <div class="logic-strip">
            📊 Score:
            <span>Pattern ±30</span> +
            <span>Similarity ±30</span> +
            <span>Pivot ±20</span> +
            <span>Volume ±10</span> +
            <span>Trend ±10</span>
            &nbsp;→&nbsp; Confidence = raw + 50 &nbsp;|&nbsp;
            🟢 <span>BUY ≥ 65</span> &nbsp; 🟡 <span>HOLD 36–64</span> &nbsp; 🔴 <span>SELL ≤ 35</span>
        </div>

        <div class="loading-overlay" id="ss_loading" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Running signal engine…</div>
            <div class="loading-sub">Scanning patterns, pivots &amp; similarity matches</div>
        </div>

        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th style="width:46px;">#</th>
                        <th>Symbol</th>
                        <th>Signal</th>
                        <th>Confidence</th>
                        <th>Date</th>
                        <th>CMP ₹</th>
                        <th>Trend</th>
                        <th>RSI</th>
                        <th>Pattern</th>
                        <th>Support ₹</th>
                        <th>Resistance ₹</th>
                        <th>Signals</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody id="ss_tbody">
                    <tr>
                        <td colspan="13" class="text-center py-5">
                            <i class="fas fa-chart-line" style="font-size:3rem;opacity:.2;color:#2563eb;"></i>
                            <p style="font-size:1.1rem;margin-top:20px;color:#999;">
                                Select a date range and click <strong>"Scan Signals"</strong>
                            </p>
                            <p style="font-size:11px;color:#bbb;">
                                Ensure you have run: <code>php artisan stocks:generate-signals</code>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>

{{-- ── DETAIL MODAL ──────────────────────────────────────────────────────── --}}
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal_title">Symbol Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modal_body">
                <div class="text-center py-5">
                    <div class="spinner" style="margin:auto;border-top-color:#2563eb;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
$(function () {

    let allRows = [];

    // ── Helpers ───────────────────────────────────────────────────────────────

    function confClass(c) {
        if (c >= 70) return 'conf-excellent';
        if (c >= 55) return 'conf-good';
        if (c >= 40) return 'conf-ok';
        return 'conf-low';
    }
    function confColor(c) {
        if (c >= 70) return '#15803d';
        if (c >= 55) return '#22c55e';
        if (c >= 40) return '#f59e0b';
        return '#ef4444';
    }
    function rankBadge(i) {
        if (i === 1) return `<span class="rank-badge rank-1">1</span>`;
        if (i === 2) return `<span class="rank-badge rank-2">2</span>`;
        if (i === 3) return `<span class="rank-badge rank-3">3</span>`;
        return `<span class="rank-badge rank-n">${i}</span>`;
    }
    function sigBadge(t) {
        if (t === 'BUY')  return `<span class="sig-buy">🟢 BUY</span>`;
        if (t === 'SELL') return `<span class="sig-sell">🔴 SELL</span>`;
        return `<span class="sig-hold">🟡 HOLD</span>`;
    }
    function rsiBadge(zone, val) {
        const v = val ? parseFloat(val).toFixed(1) : '—';
        if (zone === 'OVERBOUGHT') return `<span class="badge-ob">OB ${v}</span>`;
        if (zone === 'OVERSOLD')   return `<span class="badge-os">OS ${v}</span>`;
        return `<span class="badge-neu">${v}</span>`;
    }
    function trendHtml(t) {
        if (t === 'UP')       return `<span class="trend-up">↑ UP</span>`;
        if (t === 'DOWN')     return `<span class="trend-down">↓ DOWN</span>`;
        if (t === 'SIDEWAYS') return `<span class="trend-side">→ SIDE</span>`;
        return `<span style="color:#ccc;">—</span>`;
    }
    function patHtml(p) {
        if (!p) return `<span style="color:#d1d5db;font-size:11px;">—</span>`;
        return `<span class="badge-pat">${p.replace(/_/g,' ')}</span>`;
    }
    function priceHtml(p) {
        if (!p) return `<span style="color:#d1d5db;">—</span>`;
        return `<strong style="font-size:12px;">₹${Number(p).toLocaleString('en-IN',{minimumFractionDigits:2})}</strong>`;
    }

    // ── Build row ─────────────────────────────────────────────────────────────

    function buildRow(row, i) {
        const conf = Number(row.latest_conf) || 0;
        const volDot = row.volume_spike ? `<span class="vol-dot" title="Volume Spike"></span>` : '';
        return `
        <tr data-symbol="${(row.symbol||'').toLowerCase()}">
            <td>${rankBadge(i)}</td>
            <td>
                <span class="symbol-name" onclick="openDetail('${row.symbol}','${row.latest_date||''}')">
                    ${row.symbol}
                </span>${volDot}
            </td>
            <td>${sigBadge(row.latest_signal)}</td>
            <td>
                <span class="${confClass(conf)}">${conf}%</span>
                <span class="conf-bar-wrap">
                    <span class="conf-bar-fill" style="width:${Math.min(conf,100)}%;background:${confColor(conf)};"></span>
                </span>
            </td>
            <td><small class="text-muted">${row.latest_date || '—'}</small></td>
            <td>${priceHtml(row.close)}</td>
            <td>${trendHtml(row.trend)}</td>
            <td>${rsiBadge(row.rsi_zone, row.rsi_value)}</td>
            <td>${patHtml(row.pattern)}</td>
            <td>${priceHtml(row.support)}</td>
            <td>${priceHtml(row.resistance)}</td>
            <td>
                <small style="font-size:10px;color:#64748b;">
                    🟢${row.buy_count||0} 🔴${row.sell_count||0} 🟡${row.hold_count||0}
                    <br><span style="color:#94a3b8;">${row.total_signals||0} total</span>
                </small>
            </td>
            <td>
                <button class="btn btn-sm btn-outline-primary" style="font-size:10px;padding:2px 8px;"
                        onclick="openDetail('${row.symbol}','${row.latest_date||''}')">
                    <i class="fas fa-expand-alt"></i>
                </button>
            </td>
        </tr>`;
    }

    function render(rows) {
        if (!rows || !rows.length) {
            $('#ss_tbody').html('<tr><td colspan="13" class="text-center py-4 text-muted">No results found</td></tr>');
            return;
        }
        $('#ss_tbody').html(rows.map((r, i) => buildRow(r, i + 1)).join(''));
    }

    // ── Search ────────────────────────────────────────────────────────────────

    $('#symbol_search').on('input', function () {
        const q = $(this).val().toLowerCase().trim();
        render(q ? allRows.filter(r => (r.symbol||'').toLowerCase().includes(q)) : allRows);
    });

    // ── Stats ─────────────────────────────────────────────────────────────────

    function showStats(res) {
        $('#summary_stats').show();
        $('#st_symbols').text(res.total_symbols || 0);
        $('#st_today_buys').text(res.today_buys || 0);
        $('#st_today_sells').text(res.today_sells || 0);
        $('#st_high').text(res.high_conf || 0);
        $('#st_med').text(res.med_conf || 0);
        $('#st_total').text(res.total_signals || 0);
        $('#result_pill').text(res.message || '').show();
    }

    // ── Run scan ──────────────────────────────────────────────────────────────

    function runScan() {
        const from    = $('#ss_from').val();
        const to      = $('#ss_to').val();
        const signal  = $('#ss_signal').val();
        const minConf = $('#ss_minconf').val();

        if (!from || !to) { alert('Please select both dates'); return; }

        $('#ss_loading').show();
        $('#summary_stats').hide();
        $('#result_pill').hide();
        $('#symbol_search').hide();
        allRows = [];

        $.ajax({
            url    : '{{ route("stock-signals.analyze") }}',
            type   : 'GET',
            data   : { from_date: from, to_date: to, signal_type: signal, min_confidence: minConf },
            timeout: 120000,
            success: function (res) {
                $('#ss_loading').hide();
                if (!res.success || !res.data || !res.data.length) {
                    $('#ss_tbody').html(`<tr><td colspan="13" class="text-center py-4 text-muted">
                        <i class="fas fa-info-circle"></i> ${res.message || 'No signals found'}
                    </td></tr>`);
                    return;
                }
                allRows = res.data;
                render(allRows);
                showStats(res);
                $('#symbol_search').show().val('');
            },
            error: function (xhr) {
                $('#ss_loading').hide();
                const msg = xhr.responseJSON?.message || 'Server error — check laravel.log';
                $('#ss_tbody').html(`<tr><td colspan="13" class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-triangle"></i> ${msg}
                </td></tr>`);
            }
        });
    }

    // ── Reset ─────────────────────────────────────────────────────────────────

    function resetAll() {
        const today = '{{ date("Y-m-d") }}';
        $('#ss_from').val(today);
        $('#ss_to').val(today);
        $('#ss_signal').val('ALL');
        $('#ss_minconf').val('65');
        $('#symbol_search').hide().val('');
        $('#summary_stats').hide();
        $('#result_pill').hide();
        allRows = [];
        $('#ss_tbody').html(`<tr><td colspan="13" class="text-center py-5">
            <i class="fas fa-chart-line" style="font-size:3rem;opacity:.2;color:#2563eb;"></i>
            <p style="font-size:1.1rem;margin-top:20px;color:#999;">
                Select a date range and click <strong>"Scan Signals"</strong>
            </p></td></tr>`);
    }

    $('#ss_run').on('click', runScan);
    $('#ss_reset').on('click', resetAll);
    runScan(); // auto-run on load

    // ── Detail modal ──────────────────────────────────────────────────────────

    window.openDetail = function(symbol, date) {
        $('#modal_title').html(`<i class="fas fa-chart-area"></i> ${symbol} — Signal Detail`);
        $('#modal_body').html(`<div class="text-center py-5">
            <div class="spinner" style="margin:auto;border-top-color:#2563eb;"></div>
            <p class="mt-3 text-muted" style="font-size:12px;">Loading ${symbol}…</p>
        </div>`);
        $('#detailModal').modal('show');

        $.ajax({
            url    : '{{ route("stock-signals.detail") }}',
            type   : 'GET',
            data   : { symbol, date },
            timeout: 30000,
            success: function (res) {
                if (!res.success) {
                    $('#modal_body').html(`<div class="alert alert-danger">${res.message}</div>`);
                    return;
                }
                $('#modal_body').html(buildDetail(res));
            },
            error: function (xhr) {
                $('#modal_body').html(`<div class="alert alert-danger">
                    ${xhr.responseJSON?.message || 'Error loading detail'}
                </div>`);
            }
        });
    };

    // ── Build detail HTML ─────────────────────────────────────────────────────

    function buildDetail(res) {
        const sig      = res.signal;
        const feat     = res.feature;
        const sim      = res.similarity || {};
        const score    = sig?.score || {};
        const history  = res.history  || [];
        const ohlc     = res.ohlc     || [];
        const pivots   = res.pivots   || [];
        const patterns = res.patterns || [];

        // Signal header
        const sigHeader = sig
            ? `${sigBadge(sig.type)} <span class="${confClass(sig.confidence)}" style="font-size:16px;margin-left:8px;">${sig.confidence}%</span>`
            : `<span class="text-muted">No signal on record for ${res.date}</span>`;

        // Score breakdown rows
        const scoreMap = {
            pattern_score   : 'Pattern Match',
            similarity_score: 'Similarity Engine',
            pivot_score     : 'Pivot Support / Resistance',
            volume_score    : 'Volume Confirmation',
            trend_score     : 'Trend Alignment',
        };
        let scoreHtml = '';
        for (const [k, label] of Object.entries(scoreMap)) {
            const v   = score[k] ?? 0;
            const cls = v > 0 ? 'score-pos' : v < 0 ? 'score-neg' : 'score-neu';
            scoreHtml += `<div class="score-row">
                <span style="color:#64748b;">${label}</span>
                <span class="${cls}">${v > 0 ? '+' : ''}${v} pts</span>
            </div>`;
        }
        if (score.raw_score !== undefined) {
            scoreHtml += `<div class="score-row" style="border-top:2px solid #cbd5e1;margin-top:4px;padding-top:8px;">
                <span style="font-weight:700;">Raw Score → Confidence</span>
                <span style="font-weight:800;">${score.raw_score > 0 ? '+' : ''}${score.raw_score} → ${score.confidence}%</span>
            </div>`;
        }
        if (sig?.reason) {
            scoreHtml += `<div style="margin-top:8px;padding:8px;background:#f1f5f9;border-radius:6px;font-size:11px;color:#475569;line-height:1.7;">
                ${sig.reason.replace(/\|/g,'<br>→')}
            </div>`;
        }

        // Similarity bar
        const bullPct  = Number(sim.bullish_pct || 0);
        const simColor = bullPct >= 65 ? '#15803d' : bullPct >= 50 ? '#f59e0b' : '#b91c1c';
        const simBlock = sim.match_count > 0
            ? `<div class="sim-bar-outer"><div class="sim-bar-inner" style="width:${bullPct}%;background:${simColor};"></div></div>
               <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:6px;font-size:11px;">
                 <span>📊 <strong>${sim.match_count}</strong> past matches</span>
                 <span>📈 <strong style="color:${simColor};">${bullPct}%</strong> bullish</span>
                 <span>🎯 Bias: <strong>${sim.signal_bias}</strong></span>
                 <span>+3d: <strong style="color:${sim.avg_return_3d >= 0 ? '#15803d' : '#b91c1c'};">${sim.avg_return_3d >= 0 ? '+' : ''}${sim.avg_return_3d}%</strong></span>
                 <span>+5d: <strong style="color:${sim.avg_return_5d >= 0 ? '#15803d' : '#b91c1c'};">${sim.avg_return_5d >= 0 ? '+' : ''}${sim.avg_return_5d}%</strong></span>
                 <span>+10d: <strong style="color:${sim.avg_return_10d >= 0 ? '#15803d' : '#b91c1c'};">${sim.avg_return_10d >= 0 ? '+' : ''}${sim.avg_return_10d}%</strong></span>
               </div>`
            : `<small class="text-muted">Not enough historical data for similarity matching yet. Need more OHLC history.</small>`;

        // Signal history
        const histHtml = history.length
            ? history.map(h => `<div class="history-row">
                <small style="color:#64748b;width:88px;">${h.date}</small>
                ${sigBadge(h.type)}
                <span class="${confClass(h.confidence)}" style="font-size:11px;">${h.confidence}%</span>
                <span style="font-size:10px;color:#94a3b8;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${h.reason || ''}</span>
              </div>`).join('')
            : `<small class="text-muted">No history available</small>`;

        // OHLC table
        const ohlcHtml = ohlc.length
            ? `<table class="table table-sm" style="font-size:11px;">
                <thead><tr><th>Date</th><th>Open</th><th>High</th><th>Low</th><th>Close</th><th>Volume</th></tr></thead>
                <tbody>${ohlc.map(r => {
                    const up = r.close >= r.open;
                    return `<tr>
                        <td>${r.date}</td>
                        <td>₹${r.open}</td>
                        <td style="color:#15803d;">₹${r.high}</td>
                        <td style="color:#b91c1c;">₹${r.low}</td>
                        <td style="color:${up?'#15803d':'#b91c1c'};font-weight:700;">₹${r.close}</td>
                        <td>${Number(r.volume).toLocaleString('en-IN')}</td>
                    </tr>`;
                }).join('')}</tbody>
               </table>`
            : `<small class="text-muted">No OHLC data</small>`;

        // Pivots
        const pivotsHtml = pivots.length
            ? pivots.map(p => `<span class="pivot-tag ${p.type === 'HIGH' ? 'pivot-high' : 'pivot-low'}">
                ${p.type === 'HIGH' ? '🔴' : '🟢'} ₹${p.price}
                <small style="opacity:.6;">(${p.date})</small>
              </span>`).join('')
            : `<small class="text-muted">No pivots found in last 90 days</small>`;

        // Patterns
        const bullTypes = ['DOUBLE_BOTTOM','BREAKOUT','SUPPORT_BOUNCE'];
        const patternsHtml = patterns.length
            ? patterns.map(p => {
                const isBull = bullTypes.includes(p.type);
                return `<span class="${isBull ? 'pat-bull' : 'pat-bear'}">
                    ${p.type.replace(/_/g,' ')} <small style="opacity:.6;">${p.confidence}%</small>
                </span>`;
              }).join('')
            : `<small class="text-muted">No patterns detected in last 60 days</small>`;

        return `
        <div class="row">

            <div class="col-md-7">

                <div class="detail-section">
                    <h6>📊 Signal &amp; Score Breakdown</h6>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div>${sigHeader}</div>
                        <div><div class="detail-label">Signal Date</div><div class="detail-val" style="font-size:13px;">${res.date}</div></div>
                    </div>
                    ${scoreHtml}
                </div>

                ${feat ? `
                <div class="detail-section">
                    <h6>🧠 Market Features</h6>
                    <div class="row text-center">
                        <div class="col-4"><div class="detail-label">Trend</div><div class="detail-val">${trendHtml(feat.trend)}</div></div>
                        <div class="col-4"><div class="detail-label">RSI (14)</div><div class="detail-val">${rsiBadge(feat.rsi_zone, feat.rsi_value)}</div></div>
                        <div class="col-4"><div class="detail-label">Volatility</div><div class="detail-val" style="font-size:13px;">${feat.volatility || '—'}</div></div>
                        <div class="col-4 mt-2"><div class="detail-label">Dist from 52w High</div><div class="detail-val" style="font-size:13px;color:#b91c1c;">${feat.dist_high}%</div></div>
                        <div class="col-4 mt-2"><div class="detail-label">Dist from 52w Low</div><div class="detail-val" style="font-size:13px;color:#15803d;">+${feat.dist_low}%</div></div>
                        <div class="col-4 mt-2"><div class="detail-label">Volume Spike</div><div class="detail-val" style="font-size:13px;">${feat.vol_spike ? '⚡ YES' : '—'}</div></div>
                    </div>
                </div>` : ''}

                <div class="detail-section">
                    <h6>🔮 Similarity Engine (Statistical Pattern Matching)</h6>
                    ${simBlock}
                </div>

                <div class="detail-section">
                    <h6>📅 Signal History (last 10)</h6>
                    ${histHtml}
                </div>

            </div>

            <div class="col-md-5">

                <div class="detail-section">
                    <h6>📈 Recent OHLC (last 5 days)</h6>
                    <div class="table-responsive">${ohlcHtml}</div>
                </div>

                <div class="detail-section">
                    <h6>📌 Key Pivot Levels (last 90 days)</h6>
                    <div style="max-height:130px;overflow-y:auto;">${pivotsHtml}</div>
                </div>

                <div class="detail-section">
                    <h6>🔍 Detected Patterns (last 60 days)</h6>
                    ${patternsHtml}
                </div>

            </div>

        </div>`;
    }

}); // end $(function)
</script>
@endpush