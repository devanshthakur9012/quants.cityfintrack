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
        background: rgba(19,45,57,0.95);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 1000; border-radius: 0 0 12px 12px;
    }
    .spinner { width:55px;height:55px;border:5px solid #f3f3f3;border-top:5px solid #3498db;border-radius:50%;animation:spin 1s linear infinite; }
    .loading-text { color:white;margin-top:20px;font-size:15px;font-weight:600; }
    .loading-sub  { color:#aaa;margin-top:6px;font-size:12px; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    .page-header {
        background: linear-gradient(135deg,#1a1a2e,#16213e,#0f3460);
        color:white;padding:22px;border-radius:12px;
        margin-bottom:20px;box-shadow:0 4px 20px rgba(0,0,0,0.4);
    }
    .filter-section {
        background:linear-gradient(135deg,#667eea,#764ba2);
        padding:20px;border-radius:12px;margin-bottom:20px;
        box-shadow:0 4px 15px rgba(102,126,234,0.4);
    }
    .filter-section label  { color:white !important;font-weight:600;margin-bottom:6px;font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.3);background:rgba(255,255,255,0.92);color:#333;font-size:12px;padding:6px 10px; }

    .stats-box {
        background:#fff;padding:14px;border-radius:10px;
        text-align:center;border-left:4px solid #3498db;
        margin-bottom:12px;box-shadow:0 3px 10px rgba(0,0,0,.1);transition:transform .2s;
    }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block;color:#666;font-size:10px;text-transform:uppercase;letter-spacing:.3px; }
    .stats-box strong { display:block;font-size:1.5rem;font-weight:700;margin-top:3px; }

    .win-excellent { color:#16a34a;font-weight:800;font-size:13px; }
    .win-good      { color:#22c55e;font-weight:700;font-size:13px; }
    .win-ok        { color:#f59e0b;font-weight:700;font-size:13px; }
    .win-bad       { color:#ef4444;font-weight:700;font-size:13px; }

    .rank-badge { display:inline-block;width:28px;height:28px;line-height:28px;border-radius:50%;font-size:11px;font-weight:700;color:white;text-align:center; }
    .rank-1 { background:linear-gradient(135deg,#f59e0b,#d97706); }
    .rank-2 { background:linear-gradient(135deg,#9ca3af,#6b7280); }
    .rank-3 { background:linear-gradient(135deg,#cd7f32,#a0522d); }
    .rank-n { background:linear-gradient(135deg,#6366f1,#4f46e5);font-size:10px; }

    .symbol-name { color:#1e3a5f;font-weight:800;font-size:13px; }

    .win-bar-wrap { background:#f0f0f0;border-radius:20px;height:8px;width:80px;display:inline-block;vertical-align:middle;margin-left:6px; }
    .win-bar-fill { height:8px;border-radius:20px; }

    .badge-win   { background:#dcfce7;color:#15803d;padding:2px 8px;border-radius:4px;font-weight:700;font-size:11px; }
    .badge-loss  { background:#fee2e2;color:#b91c1c;padding:2px 8px;border-radius:4px;font-weight:700;font-size:11px; }
    .badge-days  { background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:4px;font-weight:700;font-size:11px; }
    .badge-noise { background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:4px;font-weight:600;font-size:10px; }

    .table-card { border-radius:10px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12);position:relative;min-height:400px; }
    .table-card-header { background:linear-gradient(135deg,#1a1a2e,#0f3460);color:white;padding:14px 20px;font-weight:700;font-size:14px;display:flex;align-items:center;gap:10px; }

    .new-feature-badge { background:linear-gradient(135deg,#f093fb,#f5576c);color:white;padding:2px 7px;border-radius:3px;font-size:9px;font-weight:700; }
    .logic-badge { background:rgba(255,255,255,0.15);color:white;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600; }
    .info-pill { background:rgba(255,255,255,0.15);color:white;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600; }

    #symbol_search { max-width:220px;font-size:12px; }

    /* legend strip */
    .logic-strip {
        background:linear-gradient(135deg,#0f172a,#1e293b);
        color:#94a3b8;font-size:11px;padding:10px 20px;
        display:flex;gap:20px;flex-wrap:wrap;align-items:center;
        border-bottom:1px solid rgba(255,255,255,0.05);
    }
    .logic-strip span { color:white;font-weight:600; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
            <div>
                <h4 style="margin:0;">
                    🔭 Universal BTST Scanner
                    <span class="new-feature-badge">100+ Symbols</span>
                    <span class="logic-badge ml-2">Pro Logic v2</span>
                </h4>
                <p style="margin:4px 0 0;font-size:11px;opacity:.75;line-height:1.8;">
                    📥 <strong>Entry</strong>: Blended OHLC avg of 15:00 + 15:15 candles &nbsp;|&nbsp;
                    📤 <strong>Exit</strong>: Blended OHLC avg of 09:20 + 09:25 + 09:30 candles next day &nbsp;|&nbsp;
                    ✅ <strong>Win</strong>: profit &gt; +0.5% &nbsp;|&nbsp;
                    ❌ <strong>Loss</strong>: profit &lt; -0.5% &nbsp;|&nbsp;
                    ⚪ <strong>Noise</strong>: |profit| ≤ 0.5% (ignored) &nbsp;|&nbsp;
                    🚫 <strong>Skip</strong>: buy price &lt; ₹20
                </p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a href="{{ route('account-wise.zzl') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-wallet"></i> Account-Wise</a>
                <a href="{{ route('oiiv-auto.index') }}"  class="btn btn-outline-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="filter-section">
        <div class="row align-items-end">
            <div class="col-md-3 col-sm-6">
                <label><i class="fas fa-calendar-alt"></i> From Date</label>
                <input type="date" id="ub_from" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-3 col-sm-6">
                <label><i class="fas fa-calendar-alt"></i> To Date</label>
                <input type="date" id="ub_to" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="col-md-6 col-sm-12 text-center mt-3 mt-md-0" style="padding-top:22px;">
                <button type="button" id="ub_run" class="btn btn-light btn-lg" style="min-width:170px;font-size:13px;font-weight:700;">
                    <i class="fas fa-search"></i> Scan All Symbols
                </button>
                <button type="button" id="ub_reset" class="btn btn-outline-light btn-lg ml-2" style="min-width:110px;font-size:13px;">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- Summary stats --}}
    <div class="row mb-3" id="summary_stats" style="display:none;">
        <div class="col-6 col-md-2">
            <div class="stats-box">
                <small>Symbols Scanned</small>
                <strong id="st_symbols" class="text-dark">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#16a34a;">
                <small>Win Rate ≥ 70%</small>
                <strong id="st_70" style="color:#16a34a;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#22c55e;">
                <small>Win Rate ≥ 50%</small>
                <strong id="st_50" style="color:#22c55e;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#ef4444;">
                <small>Win Rate &lt; 40%</small>
                <strong id="st_40" style="color:#ef4444;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#6366f1;">
                <small>Trading Days</small>
                <strong id="st_days" style="color:#6366f1;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stats-box" style="border-left-color:#94a3b8;">
                <small>⚪ Noise Filtered</small>
                <strong id="st_noise" style="color:#94a3b8;">0</strong>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="table-card">
        <div class="table-card-header">
            <span>📊 Symbol Performance</span>
            <span id="result_pill" class="info-pill" style="display:none;"></span>
            <div style="margin-left:auto;">
                <input type="text" id="symbol_search" class="form-control form-control-sm"
                       placeholder="🔍 Filter symbol…" style="display:none;background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);color:white;">
            </div>
        </div>

        <div class="logic-strip">
            ✅ <span>WIN</span>: profit &gt; +0.5% &nbsp;|&nbsp;
            ❌ <span>LOSS</span>: profit &lt; -0.5% &nbsp;|&nbsp;
            ⚪ <span>NOISE</span>: |profit| ≤ 0.5% — not counted &nbsp;|&nbsp;
            🚫 <span>SKIP</span>: buy price &lt; ₹20 or no data &nbsp;|&nbsp;
            Win% = wins ÷ (wins + losses) only
        </div>

        <div class="loading-overlay" id="ub_loading" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Scanning all symbols…</div>
            <div class="loading-sub">May take 15–45 sec for large date ranges</div>
        </div>

        <div class="table-responsive">
            <table class="table custom--table" id="result_table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Symbol</th>
                        <th>Tradeable Days</th>
                        <th>✅ Wins</th>
                        <th>❌ Losses</th>
                        <th>⚪ Noise</th>
                        <th>Win %</th>
                    </tr>
                </thead>
                <tbody id="ub_tbody">
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <i class="fas fa-satellite-dish" style="font-size:3rem;opacity:0.3;"></i>
                            <p style="font-size:1.1rem;margin-top:20px;color:#999;">
                                Select a date range and click <strong>"Scan All Symbols"</strong>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>
@endsection

@push('script')
<script>
$(function () {

    let allRows = [];

    function winClass(pct) {
        if (pct >= 70) return 'win-excellent';
        if (pct >= 55) return 'win-good';
        if (pct >= 40) return 'win-ok';
        return 'win-bad';
    }
    function barColor(pct) {
        if (pct >= 70) return '#16a34a';
        if (pct >= 55) return '#22c55e';
        if (pct >= 40) return '#f59e0b';
        return '#ef4444';
    }
    function rankBadge(i) {
        if (i === 1) return `<span class="rank-badge rank-1">1</span>`;
        if (i === 2) return `<span class="rank-badge rank-2">2</span>`;
        if (i === 3) return `<span class="rank-badge rank-3">3</span>`;
        return `<span class="rank-badge rank-n">${i}</span>`;
    }

    function buildRow(row, i) {
        const pct    = Number(row.win_pct) || 0;
        const barW   = Math.min(pct, 100);
        const color  = barColor(pct);
        const cls    = winClass(pct);
        const noise  = Number(row.noise) || 0;

        return `
        <tr data-symbol="${row.symbol.toLowerCase()}">
            <td>${rankBadge(i)}</td>
            <td><span class="symbol-name">${row.symbol}</span></td>
            <td><span class="badge-days">${row.total_tradeable} days</span></td>
            <td><span class="badge-win">✅ ${row.wins}</span></td>
            <td><span class="badge-loss">❌ ${row.losses}</span></td>
            <td>${noise > 0 ? `<span class="badge-noise">⚪ ${noise}</span>` : '<span style="color:#ccc;">—</span>'}</td>
            <td>
                <span class="${cls}">${pct.toFixed(1)}%</span>
                <span class="win-bar-wrap">
                    <span class="win-bar-fill" style="width:${barW}%;background:${color};"></span>
                </span>
            </td>
        </tr>`;
    }

    function render(rows) {
        if (!rows || !rows.length) {
            $('#ub_tbody').html('<tr><td colspan="7" class="text-center py-4 text-muted">No results found</td></tr>');
            return;
        }
        let html = '';
        rows.forEach((r, i) => { html += buildRow(r, i + 1); });
        $('#ub_tbody').html(html);
    }

    $('#symbol_search').on('input', function () {
        const q = $(this).val().toLowerCase().trim();
        render(q ? allRows.filter(r => r.symbol.toLowerCase().includes(q)) : allRows);
    });

    function showStats(data, meta) {
        $('#summary_stats').show();
        $('#st_symbols').text(data.length);
        $('#st_70').text(data.filter(r => r.win_pct >= 70).length);
        $('#st_50').text(data.filter(r => r.win_pct >= 50).length);
        $('#st_40').text(data.filter(r => r.win_pct <  40).length);
        $('#st_days').text(meta.total_dates || '—');
        const totalNoise = data.reduce((s, r) => s + (Number(r.noise) || 0), 0);
        $('#st_noise').text(totalNoise);
        $('#result_pill').text(meta.message || '').show();
    }

    function runScan() {
        const from = $('#ub_from').val();
        const to   = $('#ub_to').val();
        if (!from || !to) { alert('Please select both dates'); return; }

        $('#ub_loading').show();
        $('#summary_stats').hide();
        $('#result_pill').hide();
        $('#symbol_search').hide();
        allRows = [];

        $.ajax({
            url    : '{{ route("universal-btst.analyze") }}',
            type   : 'GET',
            data   : { from_date: from, to_date: to },
            timeout: 120000,
            success: function (res) {
                $('#ub_loading').hide();
                if (!res.success || !res.data || !res.data.length) {
                    $('#ub_tbody').html(`<tr><td colspan="7" class="text-center py-4 text-muted">${res.message || 'No data found'}</td></tr>`);
                    return;
                }
                allRows = res.data;
                render(allRows);
                showStats(allRows, res);
                $('#symbol_search').show().val('');
            },
            error: function (xhr) {
                $('#ub_loading').hide();
                const msg = xhr.responseJSON?.message || 'Server error — try a smaller date range';
                $('#ub_tbody').html(`<tr><td colspan="7" class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle"></i> ${msg}</td></tr>`);
            }
        });
    }

    function resetAll() {
        const today = '{{ date("Y-m-d") }}';
        $('#ub_from').val(today);
        $('#ub_to').val(today);
        $('#symbol_search').hide().val('');
        $('#summary_stats').hide();
        $('#result_pill').hide();
        allRows = [];
        $('#ub_tbody').html(`<tr><td colspan="7" class="text-center py-5">
            <p style="font-size:1.1rem;margin-top:20px;color:#999;">
                Select a date range and click <strong>"Scan All Symbols"</strong>
            </p></td></tr>`);
    }

    $('#ub_run').on('click', runScan);
    $('#ub_reset').on('click', resetAll);
    runScan();
});
</script>
@endpush