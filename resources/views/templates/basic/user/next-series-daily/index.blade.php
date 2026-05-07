@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    /* ── Base table ─────────────────────────────────────── */
    .custom--table thead th,
    .custom--table tbody td {
        text-align: center !important;
        padding: 8px 6px !important;
        font-size: 11px !important;
        vertical-align: middle;
    }
    .custom--table thead th:first-child,
    .custom--table tbody td:first-child,
    .custom--table thead th:nth-child(2),
    .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3),
    .custom--table tbody td:nth-child(3) { text-align: left !important; }

    /* ── Responsive + sticky first 3 cols ──────────────── */
    .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .custom--table    { min-width: 920px; }

    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1),
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2),
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { position: sticky; z-index: 10; }
    .custom--table thead th:nth-child(1), .custom--table tbody td:nth-child(1) { left: 0; }
    .custom--table thead th:nth-child(2), .custom--table tbody td:nth-child(2) { left: 40px; }
    .custom--table thead th:nth-child(3), .custom--table tbody td:nth-child(3) { left: 120px; }

    /* ── Loading overlay ────────────────────────────────── */
    .loading-overlay {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(8,16,28,0.96);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 1000; border-radius: 12px;
    }
    .spinner {
        width: 48px; height: 48px;
        border: 5px solid rgba(255,255,255,0.08);
        border-top: 5px solid #00d2ff;
        border-radius: 50%; animation: spin 0.85s linear infinite;
    }
    .loading-text { color: #67e8f9; margin-top: 18px; font-size: 15px; font-weight: 600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    /* ── Page header ────────────────────────────────────── */
    .page-header {
        background: linear-gradient(135deg,#0a1628,#0f2540,#0d3060);
        border: 1.5px solid rgba(0,210,255,0.3);
        color: white; padding: 22px 26px; border-radius: 14px;
        margin-bottom: 22px;
        box-shadow: 0 6px 28px rgba(0,0,0,0.5);
    }
    .next-badge {
        background: linear-gradient(135deg,#00d2ff,#3a7bd5);
        color: white; padding: 3px 10px; border-radius: 20px;
        font-size: 10px; font-weight: 700; letter-spacing: .6px;
        text-transform: uppercase; vertical-align: middle;
    }

    /* ── Filter panel ───────────────────────────────────── */
    .filter-panel {
        background: linear-gradient(135deg,#0a1628,#0e1f38);
        border: 1px solid rgba(0,210,255,0.18);
        padding: 22px; border-radius: 14px;
        margin-bottom: 22px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.4);
    }
    .filter-panel label { color: rgba(255,255,255,0.75) !important; font-weight: 600; margin-bottom: 5px; font-size: 12px; display: block; }
    .filter-panel .form-control {
        border: 1.5px solid rgba(0,210,255,0.22);
        background: rgba(255,255,255,0.92);
        color: #0d1b2a; font-size: 12px; padding: 7px 10px; border-radius: 7px;
    }
    .filter-panel .form-control:focus { border-color: #00d2ff; outline: none; box-shadow: 0 0 0 3px rgba(0,210,255,0.12); }

    /* ── Buttons ────────────────────────────────────────── */
    .btn-cyan {
        background: linear-gradient(135deg,#00d2ff,#3a7bd5);
        color: white; border: none; font-weight: 700; font-size: 13px;
        padding: 10px 30px; border-radius: 8px; cursor: pointer;
        transition: opacity .2s, transform .15s;
    }
    .btn-cyan:hover { opacity: .85; transform: translateY(-1px); color: white; }

    /* ── Stat cards ─────────────────────────────────────── */
    .stat-card {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.07);
        border-left: 4px solid #00d2ff;
        border-radius: 10px; padding: 13px 10px;
        text-align: center; margin-bottom: 14px;
        transition: transform .18s;
    }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-card small  { display: block; color: rgba(255,255,255,0.45); font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; }
    .stat-card strong { display: block; font-size: 1.3rem; font-weight: 800; margin-top: 4px; color: #fff; }
    .stat-card.green  { border-left-color: #22c55e; }
    .stat-card.red    { border-left-color: #ef4444; }
    .stat-card.gold   { border-left-color: #f59e0b; }
    .stat-card.purple { border-left-color: #a855f7; }
    .stat-card.cyan   { border-left-color: #00d2ff; }

    /* ── Table chrome ───────────────────────────────────── */
    .table-wrapper {
        background: #081018;
        border: 1px solid rgba(0,210,255,0.12);
        border-radius: 14px; overflow: hidden;
    }
    .custom--table { background: transparent; margin: 0; }
    .custom--table thead th {
        background: #060e1a !important;
        color: #67e8f9 !important;
        border-bottom: 2px solid rgba(0,210,255,0.2) !important;
        white-space: nowrap;
    }
    .custom--table tbody td {
        background: #081018;
        color: rgba(255,255,255,0.8);
        border-color: rgba(255,255,255,0.04) !important;
    }
    .custom--table tbody tr:nth-child(even) td { background: #0a1624; }
    .custom--table tbody tr:hover td { background: rgba(0,210,255,0.05) !important; }

    /* ── Value colours ──────────────────────────────────── */
    .val-up   { color: #4ade80 !important; font-weight: 700; }
    .val-down { color: #f87171 !important; font-weight: 700; }
    .val-flat { color: rgba(255,255,255,0.35) !important; }

    /* ── Sentiment badges ───────────────────────────────── */
    .badge-bullish { background: linear-gradient(135deg,#052e16,#15803d); color: #86efac; padding: 4px 10px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; white-space: nowrap; }
    .badge-bearish { background: linear-gradient(135deg,#450a0a,#b91c1c); color: #fca5a5; padding: 4px 10px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; white-space: nowrap; }
    .badge-neutral { background: rgba(71,85,105,.4); color: rgba(255,255,255,.55); padding: 4px 10px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; white-space: nowrap; }

    /* ── Strength badges ────────────────────────────────── */
    .badge-str-bull { background: linear-gradient(135deg,#052e16,#166534); color: #86efac; padding: 3px 8px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; }
    .badge-str-bear { background: linear-gradient(135deg,#450a0a,#991b1b); color: #fca5a5; padding: 3px 8px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; }
    .badge-str-norm { background: rgba(255,255,255,.06); color: rgba(255,255,255,.45); padding: 3px 8px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; }

    /* ── Condition badges ───────────────────────────────── */
    .badge-cond-ce-up-pe-down { background: linear-gradient(135deg,#7f1d1d,#c2410c); color: #fed7aa; padding: 3px 8px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; white-space: nowrap; }
    .badge-cond-ce-dn-pe-up   { background: linear-gradient(135deg,#052e16,#15803d); color: #86efac; padding: 3px 8px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; white-space: nowrap; }
    .badge-cond-both-up       { background: linear-gradient(135deg,#2e1065,#6d28d9); color: #ddd6fe; padding: 3px 8px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; white-space: nowrap; }
    .badge-cond-both-down     { background: rgba(51,65,85,.55); color: rgba(255,255,255,.55); padding: 3px 8px; border-radius: 5px; font-weight: 700; font-size: 10px; display: inline-block; white-space: nowrap; }
    .badge-cond-flat          { background: rgba(255,255,255,.05); color: rgba(255,255,255,.35); padding: 3px 8px; border-radius: 5px; font-size: 10px; display: inline-block; }

    /* ── Buy Strike badge ───────────────────────────────── */
    .th-buy-strike { background: rgba(16,185,129,0.08) !important; color: #6ee7b7 !important; }

    .buy-strike-ce {
        background: linear-gradient(135deg,#052e16,#065f46);
        border: 1px solid rgba(52,211,153,0.35);
        color: #6ee7b7;
        padding: 5px 10px; border-radius: 6px;
        font-weight: 700; font-size: 10px;
        display: inline-block; white-space: nowrap;
        letter-spacing: .3px; font-family: monospace;
    }
    .buy-strike-pe {
        background: linear-gradient(135deg,#450a0a,#7f1d1d);
        border: 1px solid rgba(248,113,113,0.35);
        color: #fca5a5;
        padding: 5px 10px; border-radius: 6px;
        font-weight: 700; font-size: 10px;
        display: inline-block; white-space: nowrap;
        letter-spacing: .3px; font-family: monospace;
    }
    .buy-strike-na {
        color: rgba(255,255,255,0.2);
        font-size: 11px;
    }

    /* volume sub-text */
    .vol-sub { color: rgba(255,255,255,0.3); font-size: 9px; margin-top: 2px; display: block; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ──────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:12px;">
            <div>
                <h4 style="color:#67e8f9; margin-bottom:6px;">
                    {{ $pageTitle }}
                    <span class="next-badge ml-2">NEXT SERIES</span>
                </h4>
                <p style="color:rgba(255,255,255,0.5); margin:0; font-size:12px;">
                    Tracks the <strong style="color:#00d2ff;">next expiry</strong> series &nbsp;|&nbsp;
                    Prev day → Today OI change &nbsp;|&nbsp;
                    <strong style="color:#6ee7b7;">Buy Strike</strong> = highest-volume CE/PE based on signal
                </p>
            </div>
            <div>
                <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-sm btn-outline-light mr-2">
                    <i class="fas fa-chart-bar"></i> Current Series
                </a>
                <a href="{{ route('oiiv-auto.index') }}" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-chart-line"></i> OI+IV
                </a>
            </div>
        </div>
    </div>

    {{-- ── Logic strip ─────────────────────────────────────── --}}
    <div style="background:rgba(0,210,255,0.05); border:1px solid rgba(0,210,255,0.15); border-radius:10px; padding:11px 18px; margin-bottom:20px; font-size:11px; color:rgba(255,255,255,0.55); display:flex; flex-wrap:wrap; gap:16px;">
        <span>📊 <strong style="color:#67e8f9;">CE ↓ + PE ↑</strong> = BULLISH → Buy CE with highest volume</span>
        <span>📊 <strong style="color:#f87171;">CE ↑ + PE ↓</strong> = BEARISH → Buy PE with highest volume</span>
        <span>🏆 Strength Rank = |CE% − PE%| &nbsp; R1 &gt;40 &nbsp; R2 &gt;25 &nbsp; R3 &gt;10 &nbsp; R4 &gt;5</span>
    </div>

    {{-- ── Filters ──────────────────────────────────────────── --}}
    <div class="filter-panel">
        <div class="row mb-3">
            <div class="col-6 col-md-3">
                <label><i class="fas fa-calendar-alt mr-1"></i> From Date</label>
                <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-6 col-md-3">
                <label><i class="fas fa-calendar-alt mr-1"></i> To Date</label>
                <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-6 col-md-3">
                <label><i class="fas fa-filter mr-1"></i> Symbols <small style="color:rgba(255,255,255,.35); font-size:10px;">(empty = all)</small></label>
                <select id="symbol_filter" class="form-control" multiple size="3"></select>
            </div>
            <div class="col-6 col-md-3">
                <label><i class="fas fa-signal mr-1"></i> Sentiment</label>
                <select id="sentiment_filter" class="form-control">
                    <option value="">All Sentiments</option>
                    <option value="BULLISH">🟢 BULLISH</option>
                    <option value="BEARISH">🔴 BEARISH</option>
                    <option value="NEUTRAL">⚪ NEUTRAL</option>
                </select>
            </div>
        </div>
        <div class="text-center">
            <button type="button" id="btn_run" class="btn-cyan">
                <i class="fas fa-search mr-1"></i> View Data
            </button>
            <button type="button" id="btn_reset" class="btn btn-outline-light btn-lg ml-2" style="font-size:13px; padding:10px 24px;">
                <i class="fas fa-undo mr-1"></i> Reset
            </button>
        </div>
    </div>

    {{-- ── Stats ────────────────────────────────────────────── --}}
    <div class="row mb-3">
        <div class="col-6 col-md-2">
            <div class="stat-card cyan">
                <small>Total Records</small>
                <strong id="stat_total">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card green">
                <small>🟢 Bullish</small>
                <strong id="stat_bullish" style="color:#4ade80;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card red">
                <small>🔴 Bearish</small>
                <strong id="stat_bearish" style="color:#f87171;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card gold">
                <small>⚪ Neutral</small>
                <strong id="stat_neutral" style="color:#fbbf24;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card purple">
                <small>🏆 Rank 1 &amp; 2</small>
                <strong id="stat_strong" style="color:#c084fc;">0</strong>
            </div>
        </div>
        <div class="col-6 col-md-2">
            <div class="stat-card">
                <small>📅 Dates</small>
                <strong id="stat_dates" style="color:#67e8f9;">0</strong>
            </div>
        </div>
    </div>

    {{-- ── Table ────────────────────────────────────────────── --}}
    <div class="table-wrapper" style="position: relative; min-height: 420px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text">Loading next-series data…</div>
        </div>
        <div class="table-responsive">
            <table class="table custom--table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>CE OI<br><small style="opacity:.55;font-weight:400;">Today</small></th>
                        <th>CE %<br><small style="opacity:.55;font-weight:400;">vs Prev</small></th>
                        <th>PE OI<br><small style="opacity:.55;font-weight:400;">Today</small></th>
                        <th>PE %<br><small style="opacity:.55;font-weight:400;">vs Prev</small></th>
                        <th>Condition</th>
                        <th>Sentiment</th>
                        <th>Strength</th>
                        <th class="th-buy-strike">
                            Buy Strike
                            <br><small style="font-weight:400;opacity:.7;">Highest Vol</small>
                        </th>
                    </tr>
                </thead>
                <tbody id="tbody">
                    <tr>
                        <td colspan="11" class="text-center py-5">
                            <i class="fas fa-chart-area" style="font-size:3rem; color:rgba(0,210,255,0.15);"></i>
                            <p style="margin-top:16px; color:rgba(255,255,255,0.3); font-size:1rem;">
                                Click <strong style="color:#00d2ff;">"View Data"</strong> to load next-series OI signals
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
    let tableData = [];

    /* ── Init ─────────────────────────────────────────────── */
    $(document).ready(function () {
        loadSymbols();
        setTimeout(() => runAnalysis(), 500);
    });

    /* ── Symbols dropdown ─────────────────────────────────── */
    function loadSymbols() {
        $.get('{{ route("next-series-daily.symbols") }}', function (res) {
            if (!res.success) return;
            const opts = res.symbols.map(s => `<option value="${s}">${s}</option>`).join('');
            $('#symbol_filter').html(opts);
        });
    }

    /* ── Run ──────────────────────────────────────────────── */
    function runAnalysis() {
        const from      = $('#from_date').val();
        const to        = $('#to_date').val();
        const symbols   = $('#symbol_filter').val() || [];
        const sentiment = $('#sentiment_filter').val();

        if (!from || !to) { alert('Please select both dates.'); return; }

        $('#loading-overlay').show();
        tableData = [];

        $.ajax({
            url  : '{{ route("next-series-daily.analyze") }}',
            type : 'GET',
            data : { from_date: from, to_date: to, symbols, sentiment },
            success: function (res) {
                $('#loading-overlay').hide();
                if (res.success && res.data && res.data.length > 0) {
                    tableData = res.data;
                    renderTable();
                    updateStats();
                } else {
                    showEmpty(res.message || 'No data found for the selected range.');
                    resetStats();
                }
            },
            error: function () {
                $('#loading-overlay').hide();
                showEmpty('Server error — please try again.');
                resetStats();
            }
        });
    }

    /* ── Render ───────────────────────────────────────────── */
    function renderTable() {
        let html = '';

        tableData.forEach(function (row, i) {
            html += `
            <tr>
                <td><span style="color:rgba(255,255,255,0.4);">${i + 1}</span></td>
                <td><strong style="color:#67e8f9;">${row.date}</strong></td>
                <td><strong style="color:#e2e8f0;">${row.symbol}</strong></td>

                <td>
                    <strong>${fmtOI(row.ce_oi)}</strong>
                    <small class="vol-sub">${Number(row.ce_oi).toLocaleString('en-IN')}</small>
                </td>
                <td>${pctCell(row.ce_oi_change_pct)}</td>

                <td>
                    <strong>${fmtOI(row.pe_oi)}</strong>
                    <small class="vol-sub">${Number(row.pe_oi).toLocaleString('en-IN')}</small>
                </td>
                <td>${pctCell(row.pe_oi_change_pct)}</td>

                <td>${condBadge(row.oi_condition)}</td>
                <td>${sentBadge(row.final_sentiment)}</td>
                <td>${strBadge(row.strength_rank, row.final_sentiment, row.strength_diff)}</td>
                <td>${buyStrikeCell(row)}</td>
            </tr>`;
        });

        $('#tbody').html(html);
    }

    /* ── Buy Strike cell ──────────────────────────────────── */
    function buyStrikeCell(row) {
        if (!row.buy_trading_symbol) {
            return '<span class="buy-strike-na">—</span>';
        }

        const cls = row.buy_option_type === 'CE' ? 'buy-strike-ce' : 'buy-strike-pe';
        const icon = row.buy_option_type === 'CE' ? '📈' : '📉';
        const vol  = row.buy_volume > 0
            ? `<small class="vol-sub">Vol: ${fmtOI(row.buy_volume)}</small>`
            : `<small class="vol-sub">OI: ${fmtOI(row.buy_oi)}</small>`;

        return `<span class="${cls}">${icon} ${row.buy_trading_symbol}</span>${vol}`;
    }

    /* ── Badge helpers ────────────────────────────────────── */
    function fmtOI(v) {
        const n = Number(v) || 0;
        if (n >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
        if (n >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
        return n.toString();
    }

    function pctCell(pct) {
        const n   = Number(pct) || 0;
        const cls = n > 0 ? 'val-up' : (n < 0 ? 'val-down' : 'val-flat');
        return `<strong class="${cls}">${n > 0 ? '+' : ''}${n.toFixed(2)}%</strong>`;
    }

    function condBadge(cond) {
        if (!cond) return '<span class="badge-cond-flat">N/A</span>';
        if (cond.includes('CE ↑ + PE ↓')) return `<span class="badge-cond-ce-up-pe-down">${cond}</span>`;
        if (cond.includes('CE ↓ + PE ↑')) return `<span class="badge-cond-ce-dn-pe-up">${cond}</span>`;
        if (cond.includes('Both ↑'))       return `<span class="badge-cond-both-up">${cond}</span>`;
        if (cond.includes('Both ↓'))       return `<span class="badge-cond-both-down">${cond}</span>`;
        return `<span class="badge-cond-flat">${cond}</span>`;
    }

    function sentBadge(s) {
        if (s === 'BULLISH') return '<span class="badge-bullish">🟢 BULLISH</span>';
        if (s === 'BEARISH') return '<span class="badge-bearish">🔴 BEARISH</span>';
        return '<span class="badge-neutral">⚪ NEUTRAL</span>';
    }

    function strBadge(rank, sentiment, diff) {
        if (rank === 'Normal') return '<span class="badge-str-norm">Normal</span>';
        const n    = (rank || '').replace('Rank ', '');
        const bull = sentiment === 'BULLISH';
        return `<span class="${bull ? 'badge-str-bull' : 'badge-str-bear'}">${bull ? '🟢' : '🔴'} R${n} <small style="opacity:.65;">(${diff})</small></span>`;
    }

    /* ── Stats ────────────────────────────────────────────── */
    function updateStats() {
        $('#stat_total').text(tableData.length);
        $('#stat_bullish').text(tableData.filter(r => r.final_sentiment === 'BULLISH').length);
        $('#stat_bearish').text(tableData.filter(r => r.final_sentiment === 'BEARISH').length);
        $('#stat_neutral').text(tableData.filter(r => r.final_sentiment === 'NEUTRAL').length);
        $('#stat_strong').text(tableData.filter(r => r.strength_rank === 'Rank 1' || r.strength_rank === 'Rank 2').length);
        $('#stat_dates').text([...new Set(tableData.map(r => r.date))].length);
    }

    function resetStats() {
        $('#stat_total,#stat_bullish,#stat_bearish,#stat_neutral,#stat_strong,#stat_dates').text('0');
    }

    function showEmpty(msg) {
        $('#tbody').html(`
            <tr><td colspan="11" class="text-center py-5">
                <i class="fas fa-info-circle" style="font-size:3rem; color:rgba(0,210,255,0.15);"></i>
                <p style="margin-top:16px; color:rgba(255,255,255,0.3);">${msg}</p>
            </td></tr>`);
    }

    /* ── Buttons ──────────────────────────────────────────── */
    $('#btn_run').on('click', () => runAnalysis());
    $('#btn_reset').on('click', function () {
        $('#from_date, #to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter').val(null);
        $('#sentiment_filter').val('');
        tableData = [];
        showEmpty('Click "View Data" to load next-series OI signals');
        resetStats();
    });
</script>
@endpush