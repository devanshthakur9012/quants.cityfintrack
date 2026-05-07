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
    .custom--table tbody td:nth-child(2) { text-align: left !important; }

    /* ── Loading overlay ────────────────────────────────── */
    .loading-overlay {
        position: absolute; top:0; left:0; right:0; bottom:0;
        background: rgba(19,45,57,0.95);
        display: flex; flex-direction: column;
        justify-content: center; align-items: center;
        z-index: 1000; border-radius: 12px;
    }
    .spinner { width:50px; height:50px; border:5px solid #f3f3f3; border-top:5px solid #3498db; border-radius:50%; animation:spin 1s linear infinite; }
    .loading-text { color:white; margin-top:20px; font-size:16px; font-weight:600; }
    @keyframes spin { 0%{transform:rotate(0deg)} 100%{transform:rotate(360deg)} }

    /* ── Signal severity badges ─────────────────────────── */
    .badge-critical { background:linear-gradient(135deg,#ff4500,#c0392b); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; animation: pulse-fire 1.2s ease-in-out infinite; }
    .badge-high     { background:linear-gradient(135deg,#ff6b00,#e67e22); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .badge-medium   { background:linear-gradient(135deg,#f39c12,#f1c40f); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .badge-low      { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    @keyframes pulse-fire { 0%,100%{opacity:1} 50%{opacity:0.6} }

    /* ── Alert type tags ────────────────────────────────── */
    .tag-fire   { background:rgba(255,69,0,0.15);   color:#ff6b35; border:1px solid rgba(255,69,0,0.3);   padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .tag-green  { background:rgba(40,167,69,0.15);  color:#28a745; border:1px solid rgba(40,167,69,0.3);  padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .tag-red    { background:rgba(220,53,69,0.15);  color:#dc3545; border:1px solid rgba(220,53,69,0.3);  padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .tag-yellow { background:rgba(255,214,0,0.15);  color:#e6b800; border:1px solid rgba(255,214,0,0.3);  padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .tag-orange { background:rgba(255,107,0,0.15);  color:#ff6b00; border:1px solid rgba(255,107,0,0.3);  padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .tag-blue   { background:rgba(59,130,246,0.15); color:#3b82f6; border:1px solid rgba(59,130,246,0.3); padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }

    /* ── Mode badges ────────────────────────────────────── */
    .mode-long_buildup   { background:rgba(40,167,69,0.12);  color:#28a745; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .mode-short_buildup  { background:rgba(220,53,69,0.12);  color:#dc3545; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .mode-short_covering { background:rgba(0,212,255,0.12);  color:#00b4d8; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .mode-long_unwinding { background:rgba(255,214,0,0.12);  color:#e6b800; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }
    .mode-neutral        { background:rgba(108,117,125,0.2); color:#6c757d; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; display:inline-block; }

    /* ── Sentiment badges ───────────────────────────────── */
    .sentiment-bullish { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-bearish { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }
    .sentiment-neutral { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:3px 8px; border-radius:4px; font-weight:700; font-size:9px; display:inline-block; }

    /* ── Bias badges ────────────────────────────────────── */
    .bias-badge-bull { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:5px 14px; border-radius:6px; font-weight:700; font-size:13px; display:inline-block; }
    .bias-badge-bear { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:5px 14px; border-radius:6px; font-weight:700; font-size:13px; display:inline-block; }
    .bias-badge-neut { background:linear-gradient(135deg,#6c757d,#5a6268); color:white; padding:5px 14px; border-radius:6px; font-weight:700; font-size:13px; display:inline-block; }

    /* ── Stats boxes ────────────────────────────────────── */
    .stats-box { background:#fff; padding:12px; border-radius:10px; text-align:center; border-left:4px solid #3498db; margin-bottom:12px; box-shadow:0 3px 10px rgba(0,0,0,.1); transition:transform .2s; }
    .stats-box:hover { transform:translateY(-2px); }
    .stats-box small  { display:block; color:#666; font-size:10px; text-transform:uppercase; letter-spacing:.3px; }
    .stats-box strong { display:block; font-size:1.4rem; font-weight:700; margin-top:3px; }

    /* ── Filter section ─────────────────────────────────── */
    .filter-section { background:linear-gradient(135deg,#667eea,#764ba2); padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(102,126,234,0.4); color:white; }
    .filter-section label { color:white !important; font-weight:600; margin-bottom:6px; font-size:13px; }
    .filter-section .form-control { border:2px solid rgba(255,255,255,0.3); background:rgba(255,255,255,0.9); color:#333; font-size:12px; padding:6px 10px; }

    /* ── Page header ────────────────────────────────────── */
    .page-header { background:linear-gradient(135deg,#0f2027,#203a43,#2c5364); color:white; padding:20px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(0,210,255,0.25); border:1px solid rgba(0,210,255,0.2); }

    /* ── Summary box ────────────────────────────────────── */
    .summary-box { border-radius:12px; padding:18px 22px; margin-bottom:20px; border:1px solid transparent; }
    .summary-box.bullish { background:rgba(40,167,69,0.08); border-color:rgba(40,167,69,0.3); }
    .summary-box.bearish { background:rgba(220,53,69,0.08); border-color:rgba(220,53,69,0.3); }
    .summary-box.neutral { background:rgba(108,117,125,0.08); border-color:rgba(108,117,125,0.25); }

    /* ── Strike dominance panel ─────────────────────────── */
    .aligned-section { background:linear-gradient(135deg,#0f2027,#203a43,#2c5364); border:2px solid #00d2ff; border-radius:14px; padding:16px 20px 8px; margin-bottom:20px; box-shadow:0 4px 20px rgba(0,210,255,0.25); }
    .aligned-section-header { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
    .aligned-section-header h6 { color:#00d2ff; font-size:13px; font-weight:700; margin:0; text-transform:uppercase; letter-spacing:.5px; }
    .aligned-tag { background:linear-gradient(135deg,#00d2ff,#3a7bd5); color:white; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; text-transform:uppercase; }

    /* ── Strike table ───────────────────────────────────── */
    .strike-table { width:100%; border-collapse:collapse; font-size:11px; }
    .strike-table th { text-align:left; padding:5px 8px; font-size:10px; letter-spacing:1px; text-transform:uppercase; color:#888; border-bottom:1px solid #dee2e6; }
    .strike-table td { padding:7px 8px; border-bottom:1px solid rgba(0,0,0,0.05); }
    .strike-table tr:hover td { background:rgba(0,0,0,0.02); }
    .oi-bar-wrap { width:80px; background:rgba(0,0,0,0.08); border-radius:3px; height:5px; display:inline-block; }
    .oi-bar { height:5px; border-radius:3px; }
    .oi-bar.ce { background:#28a745; }
    .oi-bar.pe { background:#dc3545; }

    /* ── Signal timeline table ──────────────────────────── */
    .table-responsive { overflow-x:auto; -webkit-overflow-scrolling:touch; }
    .custom--table { min-width:1200px; }

    /* ── New feature badge ──────────────────────────────── */
    .new-feature-badge { background:linear-gradient(135deg,#f093fb,#f5576c); color:white; padding:2px 6px; border-radius:3px; font-size:9px; font-weight:700; margin-left:5px; }

    /* ── PCR bar ────────────────────────────────────────── */
    .pcr-bar-wrap { height:8px; border-radius:4px; background:#dee2e6; overflow:hidden; display:flex; margin:6px 0; }
    .pcr-bar-bull { background:#28a745; transition:width .5s; }
    .pcr-bar-neut { background:#dee2e6; flex:1; }
    .pcr-bar-bear { background:#dc3545; transition:width .5s; }

    /* ── Live dot ───────────────────────────────────────── */
    .live-dot { width:7px; height:7px; border-radius:50%; background:#28a745; display:inline-block; animation:blink 1.5s ease infinite; }
    @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 style="color:#00d2ff;">{{ $pageTitle }} <span class="new-feature-badge">1-MIN CANDLES</span></h4>
                <p style="margin:0; font-size:13px; color:rgba(255,255,255,0.7);">
                    OI + Volume + PCR + IV expansion → institutional move detection on expiry day
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('nifty50-sector.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-bar"></i> NIFTY50 Sector</a>
                <a href="{{ route('oiiv-auto.index') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-line"></i> OI+IV</a>
            </div>
        </div>
    </div>

    {{-- ── Logic Summary ──────────────────────────────────────────────── --}}
    <div class="alert" style="background:linear-gradient(135deg,#667eea,#764ba2); color:white; border:none; border-radius:12px; margin-bottom:20px; padding:15px;">
        <h6 style="color:white; margin-bottom:10px; font-size:14px;"><i class="fas fa-info-circle"></i> <strong>Signal Logic (6 Layers, Scored):</strong></h6>
        <div class="row">
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>📊 OI × Price (Market Mode)</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li><strong>Price ↑ + OI ↑</strong> → Long Build-Up</li>
                    <li><strong>Price ↓ + OI ↑</strong> → Short Build-Up</li>
                    <li><strong>Price ↑ + OI ↓</strong> → Short Covering 🚀</li>
                    <li><strong>Price ↓ + OI ↓</strong> → Long Unwinding</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>📈 Accumulation Signal</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>OI rising 15+ mins continuously</li>
                    <li>AND price range-bound ≤ 0.3%</li>
                    <li>→ Smart money loading quietly</li>
                    <li>→ Score: +3 pts</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>🔥 Volume Spike</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>Current vol > 3× rolling 10-min avg</li>
                    <li>→ Institutional entry detected</li>
                    <li>IV Expansion: ATM price ↑ ≥ ₹5</li>
                    <li>while FUT barely moved (&lt;20 pts)</li>
                </ul>
            </div>
            <div class="col-md-3">
                <small style="font-size:11px;"><strong>🚨 Explosion = All 3 Together</strong></small>
                <ul style="font-size:10px; margin-top:5px;">
                    <li>OI Build + Vol Spike + Mode</li>
                    <li>Score ≥ 8 = CRITICAL entry</li>
                    <li>Best time: 09:30–11:30 signals</li>
                    <li>Buy ATM CE or PE on trigger</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- ── Filters ─────────────────────────────────────────────────────── --}}
    <div class="filter-section">
        <div class="row align-items-end">
            <div class="col-md-3">
                <label><i class="fas fa-calendar-alt"></i> Expiry Date:</label>
                <input type="date" id="analysis_date" class="form-control" value="{{ date('Y-m-d') }}" />
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-filter"></i> Severity Filter:</label>
                <select id="severity_filter" class="form-control">
                    <option value="">All Signals</option>
                    <option value="critical">🚨 Critical Only (score ≥ 8)</option>
                    <option value="high">🔴 High+ (score ≥ 5)</option>
                    <option value="medium">🟡 Medium+ (score ≥ 3)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label><i class="fas fa-sort"></i> Sort By:</label>
                <select id="sort_filter" class="form-control">
                    <option value="score">Score (High → Low)</option>
                    <option value="time">Time (Chronological)</option>
                </select>
            </div>
            <div class="col-md-3">
                <label>&nbsp;</label>
                <div>
                    <button type="button" id="btn_analyze" class="btn btn-light btn-lg" style="min-width:140px; font-size:13px;">
                        <i class="fas fa-bolt"></i> Analyze
                    </button>
                    <button type="button" id="btn_reset" class="btn btn-outline-light btn-lg ml-2" style="min-width:100px; font-size:13px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
                <small style="color:rgba(255,255,255,0.7); font-size:10px; margin-top:4px; display:block;" id="analyzed_at_label"></small>
            </div>
        </div>
    </div>

    {{-- ── Stats Row ───────────────────────────────────────────────────── --}}
    <div class="row">
        <div class="col-md-2 col-6"><div class="stats-box"><small>ATM Strike</small><strong id="stat_atm" class="text-dark" style="font-size:1.1rem;">—</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#28a745;"><small>📈 ATM CE Move</small><strong id="stat_ce_move" style="color:#28a745;">—</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#dc3545;"><small>📉 ATM PE Move</small><strong id="stat_pe_move" style="color:#dc3545;">—</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#ff4500;"><small>🚨 Explosions</small><strong id="stat_explosions" style="color:#ff4500;">0</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#667eea;"><small>Total Signals</small><strong id="stat_signals" style="color:#667eea;">0</strong></div></div>
        <div class="col-md-2 col-6"><div class="stats-box" style="border-left-color:#f39c12;"><small>Top Signal @</small><strong id="stat_top_time" style="color:#f39c12; font-size:1rem;">—</strong></div></div>
    </div>

    {{-- ── Day Bias Summary ────────────────────────────────────────────── --}}
    <div class="summary-box neutral" id="summary_box">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div style="font-size:11px; color:#888; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px;">Day Bias (OI Market Mode Analysis)</div>
                <div id="bias_badge_wrap"><span class="bias-badge-neut">— Select date & analyze</span></div>
                <div id="bias_reason" style="font-size:12px; color:#666; margin-top:8px;"></div>
            </div>
            <div>
                <div style="font-size:11px; color:#888; margin-bottom:4px;">ATM CE: Open → Peak</div>
                <div style="font-weight:700; font-size:15px; color:#28a745;" id="ce_range_lbl">₹— → ₹—</div>
            </div>
            <div>
                <div style="font-size:11px; color:#888; margin-bottom:4px;">ATM PE: Open → Peak</div>
                <div style="font-weight:700; font-size:15px; color:#dc3545;" id="pe_range_lbl">₹— → ₹—</div>
            </div>
            <div>
                <div style="font-size:11px; color:#888; margin-bottom:4px;">PCR Bias Today</div>
                <div class="pcr-bar-wrap" style="width:180px; margin:0 0 4px;">
                    <div class="pcr-bar-bull" id="pcr_bull_bar" style="width:0%;"></div>
                    <div class="pcr-bar-bear" id="pcr_bear_bar" style="width:0%;"></div>
                </div>
                <div style="font-size:10px; display:flex; justify-content:space-between; width:180px;">
                    <span class="text-success" id="pcr_bull_lbl">🟢 Bull 0%</span>
                    <span class="text-danger"  id="pcr_bear_lbl">🔴 Bear 0%</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Strike Dominance Panel ──────────────────────────────────────── --}}
    <div class="aligned-section" id="strike_panel" style="display:none;">
        <div class="aligned-section-header">
            <span style="font-size:20px;">🎯</span>
            <h6>Strike Dominance — Top OI Concentration</h6>
            <span class="aligned-tag">Cumulative Day</span>
        </div>
        <div class="row" id="strike_dominance_row"></div>
    </div>

    {{-- ── Signal Timeline Table ───────────────────────────────────────── --}}
    <div style="position:relative; min-height:400px;">
        <div class="loading-overlay" id="loading-overlay" style="display:none;">
            <div class="spinner"></div>
            <div class="loading-text" id="loading-text">Analysing SENSEX expiry candles...</div>
        </div>

        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Time</th>
                        <th>Severity</th>
                        <th>Score</th>
                        <th>Market Mode</th>
                        <th>FUT Price</th>
                        <th>ATM CE ₹</th>
                        <th>ATM PE ₹</th>
                        <th>PCR</th>
                        <th>OI Δ</th>
                        <th>Vol Spike</th>
                        <th>Signals Fired</th>
                        <th>Top Detail</th>
                    </tr>
                </thead>
                <tbody id="signal-tbody">
                    <tr>
                        <td colspan="13" class="text-center py-5">
                            <i class="fas fa-rocket" style="font-size:3rem; opacity:0.4;"></i>
                            <p style="margin-top:16px;">Select a date and click <strong>Analyze</strong></p>
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
let allData = null;

/* ── Loading ──────────────────────────────────────────────────────────────── */
function toggleLoading(show, msg) {
    if (show) {
        if (msg) $('#loading-text').text(msg);
        $('#loading-overlay').show();
        $('#btn_analyze').prop('disabled', true);
    } else {
        $('#loading-overlay').hide();
        $('#btn_analyze').prop('disabled', false);
    }
}

/* ── Run Analysis ─────────────────────────────────────────────────────────── */
function runAnalysis() {
    const date = $('#analysis_date').val();
    if (!date) { alert('Please select a date'); return; }

    toggleLoading(true, 'Analysing SENSEX expiry 1-min candles...');

    $.ajax({
        url:  '{{ route("sensex-expiry.analyze") }}',
        type: 'GET',
        data: { date },
        success: function(res) {
            toggleLoading(false);
            if (!res.success) {
                alert('⚠️ ' + (res.message || 'Unknown error'));
                return;
            }
            allData = res;
            $('#analyzed_at_label').text('Analysed: ' + res.analyzed_at + ' | ' + res.total_candles + ' candles');
            renderAll(res);
            applyFilters();
        },
        error: function() {
            toggleLoading(false);
            alert('Network error — please try again');
        }
    });
}

/* ── Render Everything ────────────────────────────────────────────────────── */
function renderAll(data) {
    const s   = data.summary;
    const pcr = data.pcr || [];

    // Stats
    $('#stat_atm').text(s.atm_strike ? '₹' + Number(s.atm_strike).toLocaleString('en-IN') : '—');
    $('#stat_ce_move').text('+' + s.atm_ce_move_pct + '%');
    $('#stat_pe_move').text('+' + s.atm_pe_move_pct + '%');
    $('#stat_explosions').text(s.explosion_count);
    $('#stat_signals').text(s.total_signals);
    $('#stat_top_time').text(s.top_signal_time + (s.top_signal_score ? ' ('+s.top_signal_score+'pts)' : ''));

    // CE/PE range
    $('#ce_range_lbl').text('₹' + s.atm_ce_open + ' → ₹' + s.atm_ce_high);
    $('#pe_range_lbl').text('₹' + s.atm_pe_open + ' → ₹' + s.atm_pe_high);

    // Bias box
    const dir = s.day_bias;
    $('#summary_box').removeClass('bullish bearish neutral').addClass(dir);
    let badge = '';
    if (dir === 'bullish') badge = '<span class="bias-badge-bull">🟢 BULLISH DAY</span>';
    else if (dir === 'bearish') badge = '<span class="bias-badge-bear">🔴 BEARISH DAY</span>';
    else badge = '<span class="bias-badge-neut">⚪ NEUTRAL DAY</span>';
    badge += ` <span class="badge badge-secondary ml-2" style="font-size:10px;">Signals: ${s.total_signals} | 🚨 ${s.explosion_count}</span>`;
    $('#bias_badge_wrap').html(badge);
    $('#bias_reason').text('ATM CE ₹' + s.atm_ce_open + '→' + s.atm_ce_high +
        ' (+' + s.atm_ce_move_pct + '%) | ATM PE ₹' + s.atm_pe_open + '→' + s.atm_pe_high +
        ' (+' + s.atm_pe_move_pct + '%) | Top Signal @ ' + s.top_signal_time);

    // PCR bar
    const bullMins = pcr.filter(p => p.bias === 'bullish').length;
    const bearMins = pcr.filter(p => p.bias === 'bearish').length;
    const total    = pcr.length || 1;
    const bPct     = Math.round(bullMins / total * 100);
    const rPct     = Math.round(bearMins / total * 100);
    $('#pcr_bull_bar').css('width', bPct + '%');
    $('#pcr_bear_bar').css('width', rPct + '%');
    $('#pcr_bull_lbl').text('🟢 Bull ' + bPct + '%');
    $('#pcr_bear_lbl').text('🔴 Bear ' + rPct + '%');

    // Strike dominance panel
    renderStrikeDominance(data.strike_dominance);
}

/* ── Strike Dominance ─────────────────────────────────────────────────────── */
function renderStrikeDominance(dom) {
    if (!dom) return;
    $('#strike_panel').show();

    const buildTable = (rows, type) => {
        if (!rows || !rows.length) return '<p class="text-muted" style="font-size:11px;">No data</p>';
        const maxOi = rows[0].total_oi || 1;
        let html = `<table class="strike-table">
            <thead><tr><th>Strike</th><th>OI Bar</th><th>Total OI</th><th>Volume</th><th>Peak ₹</th></tr></thead><tbody>`;
        rows.forEach((r, i) => {
            const pct = Math.min(100, Math.round(r.total_oi / maxOi * 100));
            const badge = i === 0 ? ' <span class="badge badge-warning" style="font-size:8px;">MAX</span>' : '';
            html += `<tr>
                <td><strong>${Number(r.strike).toLocaleString('en-IN')}${badge}</strong></td>
                <td><span class="oi-bar-wrap"><span class="oi-bar ${type}" style="width:${pct}%;display:block;"></span></span></td>
                <td>${fmtNum(r.total_oi)}</td>
                <td>${fmtNum(r.total_vol)}</td>
                <td>₹${Number(r.max_price).toFixed(2)}</td>
            </tr>`;
        });
        return html + '</tbody></table>';
    };

    $('#strike_dominance_row').html(`
        <div class="col-md-6">
            <div style="background:rgba(40,167,69,0.08); border:1px solid rgba(40,167,69,0.2); border-radius:8px; padding:12px; margin-bottom:10px;">
                <div style="font-size:11px; font-weight:700; color:#28a745; margin-bottom:8px;">▲ CE — Top 5 Strikes by OI</div>
                ${buildTable(dom.ce, 'ce')}
            </div>
        </div>
        <div class="col-md-6">
            <div style="background:rgba(220,53,69,0.08); border:1px solid rgba(220,53,69,0.2); border-radius:8px; padding:12px; margin-bottom:10px;">
                <div style="font-size:11px; font-weight:700; color:#dc3545; margin-bottom:8px;">▼ PE — Top 5 Strikes by OI</div>
                ${buildTable(dom.pe, 'pe')}
            </div>
        </div>
    `);
}

/* ── Apply Filters & Render Table ─────────────────────────────────────────── */
function applyFilters() {
    if (!allData) return;

    const severityFilter = $('#severity_filter').val();
    const sortBy         = $('#sort_filter').val();

    let signals = [...(allData.signals || [])];

    if (severityFilter === 'critical') signals = signals.filter(s => s.score >= 8);
    else if (severityFilter === 'high') signals = signals.filter(s => s.score >= 5);
    else if (severityFilter === 'medium') signals = signals.filter(s => s.score >= 3);

    if (sortBy === 'time') signals.sort((a, b) => a.time.localeCompare(b.time));
    // score sort is already default from controller

    renderTable(signals);
}

/* ── Table Render ─────────────────────────────────────────────────────────── */
function renderTable(signals) {
    if (!signals || signals.length === 0) {
        $('#signal-tbody').html(`<tr><td colspan="13" class="text-center text-warning py-4">
            No signals match the selected filter for this date.</td></tr>`);
        return;
    }

    let html = '';
    signals.forEach((row, i) => {
        // Severity badge
        const sevBadge = row.severity === 'critical'
            ? '<span class="badge-critical">🚨 CRITICAL</span>'
            : row.severity === 'high'
            ? '<span class="badge-high">🔴 HIGH</span>'
            : row.severity === 'medium'
            ? '<span class="badge-medium">🟡 MEDIUM</span>'
            : '<span class="badge-low">⚪ LOW</span>';

        // Mode badge
        const modeBadge = `<span class="mode-${row.mode}">${row.mode.replace(/_/g,' ')}</span>`;

        // Alert tags
        const tags = (row.alerts || []).map(a =>
            `<span class="tag-${a.color}" title="${a.detail}">${a.label}</span>`
        ).join(' ');

        // Top detail
        const topDetail = row.alerts && row.alerts.length ? row.alerts[0].detail : '—';

        // Vol spike badge
        const volBadge = row.vol_spike
            ? '<span class="sentiment-bearish" style="background:linear-gradient(135deg,#ff6b00,#e67e22);">🔥 SPIKE</span>'
            : '<span class="sentiment-neutral">—</span>';

        // OI change colour
        const oiCls = row.oi_change > 0 ? 'text-success' : (row.oi_change < 0 ? 'text-danger' : '');
        const oiStr = (row.oi_change > 0 ? '+' : '') + fmtNum(row.oi_change);

        // PCR colour
        const pcrVal = row.pcr || 0;
        const pcrCls = pcrVal >= 1.2 ? 'text-success' : (pcrVal <= 0.8 ? 'text-danger' : '');

        html += `<tr class="${row.severity === 'critical' ? 'table-danger' : ''}">
            <td><strong>${i + 1}</strong></td>
            <td><strong style="color:#667eea; font-size:13px;">${row.time}</strong></td>
            <td>${sevBadge}</td>
            <td><strong style="font-size:13px;">${row.score}</strong> <small style="color:#888;">pts</small></td>
            <td>${modeBadge}</td>
            <td>${row.fut_price ? '₹' + Number(row.fut_price).toLocaleString('en-IN') : '—'}</td>
            <td class="text-success"><strong>₹${Number(row.atm_ce || 0).toFixed(2)}</strong></td>
            <td class="text-danger"><strong>₹${Number(row.atm_pe || 0).toFixed(2)}</strong></td>
            <td class="${pcrCls}"><strong>${pcrVal}</strong></td>
            <td class="${oiCls}"><strong>${oiStr}</strong></td>
            <td>${volBadge}</td>
            <td style="max-width:220px;">${tags}</td>
            <td><small style="color:#666;">${topDetail}</small></td>
        </tr>`;
    });

    $('#signal-tbody').html(html);
}

/* ── Number formatter ─────────────────────────────────────────────────────── */
function fmtNum(val) {
    const n = Number(val) || 0;
    if (Math.abs(n) >= 1_000_000) return (n / 1_000_000).toFixed(2) + 'M';
    if (Math.abs(n) >= 1_000)     return (n / 1_000).toFixed(1) + 'K';
    return n.toString();
}

/* ── Event Handlers ───────────────────────────────────────────────────────── */
$('#btn_analyze').click(runAnalysis);

$('#btn_reset').click(function () {
    $('#analysis_date').val('{{ date("Y-m-d") }}');
    $('#severity_filter, #sort_filter').val('');
    allData = null;
    $('#signal-tbody').html('<tr><td colspan="13" class="text-center py-4"><p class="text-muted">Filters reset — click Analyze to reload</p></td></tr>');
    $('#strike_panel').hide();
    $('#stat_atm,#stat_top_time').text('—');
    $('#stat_ce_move,#stat_pe_move').text('—');
    $('#stat_explosions,#stat_signals').text('0');
    $('#bias_badge_wrap').html('<span class="bias-badge-neut">— Select date & analyze</span>');
    $('#bias_reason').text('');
    $('#ce_range_lbl').text('₹— → ₹—');
    $('#pe_range_lbl').text('₹— → ₹—');
    $('#pcr_bull_bar,#pcr_bear_bar').css('width', '0%');
    $('#pcr_bull_lbl').text('🟢 Bull 0%');
    $('#pcr_bear_lbl').text('🔴 Bear 0%');
    $('#summary_box').removeClass('bullish bearish').addClass('neutral');
    $('#analyzed_at_label').text('');
});

$('#severity_filter, #sort_filter').change(function () {
    if (allData) applyFilters();
});

// Auto-run on load
$(document).ready(function () { setTimeout(runAnalysis, 400); });
</script>
@endpush


