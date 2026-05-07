@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<style>
    @import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syne:wght@400;600;700;800&display=swap');

    :root {
        --bg-deep:       #0a0f1e;
        --bg-card:       #0f1a2e;
        --bg-card2:      #111e35;
        --border:        rgba(255,255,255,0.07);
        --bull-green:    #00e5a0;
        --bear-red:      #ff4466;
        --neutral-gray:  #8899aa;
        --gold:          #f5c842;
        --blue-accent:   #4a90e2;
        --text-primary:  #e8f0fe;
        --text-muted:    #6b7f9e;
        --font-head:     'Syne', sans-serif;
        --font-mono:     'Space Mono', monospace;
    }

    * { box-sizing: border-box; }

    body { background: var(--bg-deep) !important; color: var(--text-primary); }

    /* ─── Page Layout ─── */
    .nst-wrap { max-width: 1400px; margin: 0 auto; padding: 30px 20px; }

    /* ─── Header ─── */
    .nst-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 20px;
    }

    .nst-title {
        font-family: var(--font-head);
        font-size: 1.75rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        background: linear-gradient(90deg, var(--bull-green), var(--blue-accent));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin: 0;
    }

    .nst-subtitle {
        font-family: var(--font-mono);
        font-size: 11px;
        color: var(--text-muted);
        margin: 4px 0 0;
    }

    /* ─── Date Picker Bar ─── */
    .nst-controls {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .nst-controls label {
        font-family: var(--font-mono);
        font-size: 11px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .nst-controls input[type="date"] {
        background: var(--bg-deep);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text-primary);
        padding: 8px 14px;
        font-family: var(--font-mono);
        font-size: 13px;
        cursor: pointer;
        outline: none;
        transition: border-color 0.2s;
    }

    .nst-controls input[type="date"]:focus {
        border-color: var(--bull-green);
    }

    .btn-analyze {
        background: linear-gradient(135deg, var(--bull-green), #00b87d);
        color: #0a0f1e;
        border: none;
        border-radius: 8px;
        padding: 9px 24px;
        font-family: var(--font-head);
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        transition: opacity 0.2s, transform 0.15s;
    }

    .btn-analyze:hover { opacity: 0.9; transform: translateY(-1px); }
    .btn-analyze:disabled { opacity: 0.4; cursor: not-allowed; }

    /* ─── Bias Card (Big Center) ─── */
    .bias-card {
        border-radius: 16px;
        padding: 32px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
        border: 1px solid transparent;
        transition: all 0.4s ease;
        position: relative;
        overflow: hidden;
    }

    .bias-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        opacity: 0.06;
        pointer-events: none;
    }

    .bias-card.bullish {
        background: linear-gradient(135deg, rgba(0,229,160,0.08), rgba(0,184,125,0.04));
        border-color: rgba(0,229,160,0.3);
    }
    .bias-card.bullish::before { background: var(--bull-green); }

    .bias-card.bearish {
        background: linear-gradient(135deg, rgba(255,68,102,0.08), rgba(200,30,60,0.04));
        border-color: rgba(255,68,102,0.3);
    }
    .bias-card.bearish::before { background: var(--bear-red); }

    .bias-card.sideways {
        background: linear-gradient(135deg, rgba(136,153,170,0.08), rgba(80,100,130,0.04));
        border-color: rgba(136,153,170,0.25);
    }

    .bias-card.loading-state {
        background: var(--bg-card);
        border-color: var(--border);
        min-height: 160px;
        justify-content: center;
    }

    .bias-direction {
        font-family: var(--font-head);
        font-size: 3rem;
        font-weight: 800;
        letter-spacing: -1px;
        line-height: 1;
    }

    .bias-direction.bullish { color: var(--bull-green); }
    .bias-direction.bearish { color: var(--bear-red); }
    .bias-direction.sideways { color: var(--neutral-gray); }

    .bias-meta {
        font-family: var(--font-mono);
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 8px;
        line-height: 1.7;
    }

    .bias-score-ring {
        width: 110px;
        height: 110px;
        position: relative;
        flex-shrink: 0;
    }

    .confidence-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-family: var(--font-mono);
        font-size: 10px;
        font-weight: 700;
        margin-top: 10px;
        letter-spacing: 0.5px;
    }

    .confidence-badge.strong { background: rgba(0,229,160,0.15); color: var(--bull-green); }
    .confidence-badge.moderate { background: rgba(245,200,66,0.15); color: var(--gold); }
    .confidence-badge.weak { background: rgba(136,153,170,0.15); color: var(--neutral-gray); }

    /* ─── Trade Plan Card ─── */
    .trade-plan-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 22px 24px;
        margin-bottom: 24px;
    }

    .trade-plan-card h6 {
        font-family: var(--font-head);
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 18px;
    }

    .trade-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
    }

    .trade-item {
        background: var(--bg-deep);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 12px 16px;
    }

    .trade-item-label {
        font-family: var(--font-mono);
        font-size: 9px;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 6px;
    }

    .trade-item-value {
        font-family: var(--font-head);
        font-size: 14px;
        font-weight: 700;
        color: var(--text-primary);
    }

    .trade-item-value.ce { color: var(--bull-green); }
    .trade-item-value.pe { color: var(--bear-red); }
    .trade-item-value.none { color: var(--neutral-gray); }

    /* ─── Sector Bars ─── */
    .sector-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }

    .sector-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px;
        transition: border-color 0.2s;
    }

    .sector-card:hover { border-color: rgba(74,144,226,0.3); }

    .sector-name {
        font-family: var(--font-head);
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .sector-weight-bar {
        height: 4px;
        border-radius: 2px;
        background: var(--bg-deep);
        margin: 10px 0;
        overflow: hidden;
    }

    .sector-weight-fill {
        height: 100%;
        border-radius: 2px;
        transition: width 0.8s ease;
    }

    .sector-score-display {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 10px;
    }

    .sector-score-val {
        font-family: var(--font-mono);
        font-size: 13px;
        font-weight: 700;
    }

    /* ─── Stock Table ─── */
    .stocks-section {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 24px;
    }

    .stocks-section-head {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .stocks-section-head h6 {
        font-family: var(--font-head);
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin: 0;
    }

    .stock-table {
        width: 100%;
        border-collapse: collapse;
    }

    .stock-table thead th {
        background: var(--bg-deep);
        padding: 10px 16px;
        font-family: var(--font-mono);
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        text-align: center;
        border-bottom: 1px solid var(--border);
    }

    .stock-table thead th:first-child { text-align: left; }

    .stock-table tbody td {
        padding: 11px 16px;
        border-bottom: 1px solid rgba(255,255,255,0.04);
        font-family: var(--font-mono);
        font-size: 12px;
        color: var(--text-primary);
        text-align: center;
        vertical-align: middle;
    }

    .stock-table tbody td:first-child { text-align: left; }
    .stock-table tbody tr:last-child td { border-bottom: none; }
    .stock-table tbody tr:hover { background: rgba(255,255,255,0.02); }

    .sym-badge {
        display: inline-block;
        background: rgba(74,144,226,0.12);
        color: var(--blue-accent);
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 700;
        font-size: 11px;
        letter-spacing: 0.5px;
    }

    .sector-tag {
        display: inline-block;
        padding: 2px 7px;
        border-radius: 3px;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 700;
    }

    .sector-tag.financial { background: rgba(245,200,66,0.12); color: var(--gold); }
    .sector-tag.oil       { background: rgba(255,120,60,0.12); color: #ff7840; }
    .sector-tag.it        { background: rgba(74,144,226,0.12); color: var(--blue-accent); }

    /* ─── Direction Pills ─── */
    .dir-pill {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        font-family: var(--font-mono);
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .dir-pill.STRONG_BULLISH { background: rgba(0,229,160,0.15); color: var(--bull-green); }
    .dir-pill.BULLISH        { background: rgba(0,229,160,0.08); color: #00c080; }
    .dir-pill.STRONG_BEARISH { background: rgba(255,68,102,0.15); color: var(--bear-red); }
    .dir-pill.BEARISH        { background: rgba(255,68,102,0.08); color: #dd3355; }
    .dir-pill.SIDEWAYS       { background: rgba(136,153,170,0.12); color: var(--neutral-gray); }

    /* ─── Breadth Meter ─── */
    .breadth-bar {
        height: 8px;
        border-radius: 4px;
        background: var(--bg-deep);
        overflow: hidden;
        display: flex;
        margin: 10px 0;
    }

    .breadth-bull { background: var(--bull-green); transition: width 0.8s ease; }
    .breadth-bear { background: var(--bear-red);   transition: width 0.8s ease; }
    .breadth-neut { background: var(--neutral-gray); flex: 1; }

    /* ─── Spinner ─── */
    .nst-spinner {
        width: 36px; height: 36px;
        border: 3px solid rgba(255,255,255,0.1);
        border-top-color: var(--bull-green);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    .nst-spinner-wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        padding: 40px;
        width: 100%;
    }

    .nst-spinner-label {
        font-family: var(--font-mono);
        font-size: 11px;
        color: var(--text-muted);
        letter-spacing: 1px;
    }

    /* ─── Empty / Error states ─── */
    .nst-empty {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
        font-family: var(--font-mono);
        font-size: 13px;
    }

    .nst-empty i { font-size: 2.5rem; margin-bottom: 16px; opacity: 0.3; display: block; }

    /* ─── Stats row ─── */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }

    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 16px;
        text-align: center;
    }

    .stat-label {
        font-family: var(--font-mono);
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
        margin-bottom: 8px;
    }

    .stat-value {
        font-family: var(--font-head);
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1;
    }

    /* ─── SVG Score Ring ─── */
    .ring-svg { transform: rotate(-90deg); }
    .ring-track { fill: none; stroke: rgba(255,255,255,0.06); stroke-width: 8; }
    .ring-fill  { fill: none; stroke-width: 8; stroke-linecap: round; transition: stroke-dashoffset 0.8s ease; }
    .ring-label {
        position: absolute;
        inset: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-family: var(--font-mono);
    }
    .ring-label-pct  { font-size: 18px; font-weight: 700; }
    .ring-label-text { font-size: 9px; color: var(--text-muted); letter-spacing: 1px; }

    /* ─── Responsive ─── */
    @media (max-width: 768px) {
        .bias-direction { font-size: 2rem; }
        .nst-title { font-size: 1.3rem; }
        .bias-score-ring { display: none; }
    }
</style>
@endpush

<section class="pt-30 pb-50">
<div class="nst-wrap">

    <!-- Header -->
    <div class="nst-header">
        <div>
            <h1 class="nst-title">⚡ NIFTY Sector Trend Analyzer</h1>
            <p class="nst-subtitle">Next-day bias via weighted sector VWAP + OI analysis</p>
        </div>
    </div>

    <!-- Controls -->
    <div class="nst-controls">
        <div>
            <label for="analysis_date">Analysis Date</label>
            <div style="margin-top:6px;">
                <input type="date" id="analysis_date" value="{{ date('Y-m-d') }}" />
            </div>
        </div>
        <div style="display:flex; align-items:flex-end; padding-bottom:0;">
            <button class="btn-analyze" id="btn_analyze" onclick="runAnalysis()">
                <i class="fas fa-bolt"></i> Analyze
            </button>
        </div>
        <div style="margin-left:auto; font-family:var(--font-mono); font-size:11px; color:var(--text-muted); text-align:right; line-height:1.8;">
            <div>⏰ Best time: 3:00–3:15 PM</div>
            <div id="analyzed_at" style="opacity:0.6;"></div>
        </div>
    </div>

    <!-- Main Results (hidden until loaded) -->
    <div id="results_wrap" style="display:none;">

        <!-- Bias Card -->
        <div class="bias-card" id="bias_card">
            <div style="flex:1;">
                <div style="font-family:var(--font-mono); font-size:11px; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:10px;">NIFTY Next-Day Bias</div>
                <div class="bias-direction" id="bias_direction_text">—</div>
                <div class="bias-meta" id="bias_reason">—</div>
                <div id="confidence_badge" class="confidence-badge moderate" style="margin-top:10px;"></div>
            </div>
            <div class="bias-score-ring" id="score_ring_wrap">
                <svg class="ring-svg" viewBox="0 0 100 100" width="110" height="110">
                    <circle class="ring-track" cx="50" cy="50" r="42"/>
                    <circle class="ring-fill" id="ring_fill" cx="50" cy="50" r="42"
                        stroke-dasharray="264"
                        stroke-dashoffset="264"/>
                </svg>
                <div class="ring-label">
                    <span class="ring-label-pct" id="ring_pct">0%</span>
                    <span class="ring-label-text">SCORE</span>
                </div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">NIFTY Score</div>
                <div class="stat-value" id="stat_score">—</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Financial Sector</div>
                <div class="stat-value" id="stat_fin" style="font-size:1rem;">—</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Oil & Gas</div>
                <div class="stat-value" id="stat_oil" style="font-size:1rem;">—</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">IT Sector</div>
                <div class="stat-value" id="stat_it" style="font-size:1rem;">—</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Breadth Signal</div>
                <div class="stat-value" id="stat_breadth" style="font-size:1rem;">—</div>
            </div>
        </div>

        <!-- Trade Plan -->
        <div class="trade-plan-card">
            <h6><i class="fas fa-bullseye"></i> &nbsp;Next Day Trade Plan</h6>
            <div class="trade-grid" id="trade_grid">
                <!-- populated by JS -->
            </div>
            <div style="margin-top:16px; padding:12px 16px; background: rgba(255,255,255,0.03); border-radius:8px; font-family:var(--font-mono); font-size:11px; color:var(--text-muted); line-height:1.9;" id="entry_trigger_box">
                <!-- entry trigger -->
            </div>
        </div>

        <!-- Sector Cards -->
        <div class="sector-grid" id="sector_grid">
            <!-- populated by JS -->
        </div>

        <!-- Breadth Bar -->
        <div class="trade-plan-card">
            <h6><i class="fas fa-chart-pie"></i> &nbsp;Market Breadth (Tracked Stocks)</h6>
            <div class="breadth-bar">
                <div class="breadth-bull" id="breadth_bull_bar" style="width:0%"></div>
                <div class="breadth-bear" id="breadth_bear_bar" style="width:0%"></div>
                <div class="breadth-neut"></div>
            </div>
            <div style="display:flex; justify-content:space-between; font-family:var(--font-mono); font-size:11px; color:var(--text-muted); margin-top:8px;">
                <span id="breadth_bull_lbl" style="color:var(--bull-green);">Bullish: 0</span>
                <span id="breadth_neutral_lbl">Neutral: 0</span>
                <span id="breadth_bear_lbl" style="color:var(--bear-red);">Bearish: 0</span>
            </div>
        </div>

        <!-- Stock Table -->
        <div class="stocks-section">
            <div class="stocks-section-head">
                <h6><i class="fas fa-table"></i> &nbsp;Stock-Level Scores</h6>
                <span style="font-family:var(--font-mono); font-size:11px; color:var(--text-muted);">Power Hour (2:30–3:15 PM)</span>
            </div>
            <div class="table-responsive">
                <table class="stock-table">
                    <thead>
                        <tr>
                            <th>Symbol</th>
                            <th>Sector</th>
                            <th>Close</th>
                            <th>VWAP</th>
                            <th>VWAP Dev%</th>
                            <th>Momentum</th>
                            <th>OI Signal</th>
                            <th>Score%</th>
                            <th>Direction</th>
                        </tr>
                    </thead>
                    <tbody id="stock_tbody">
                        <tr><td colspan="9" class="nst-empty">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Loading State -->
    <div id="loading_state" style="display:none;" class="bias-card loading-state">
        <div class="nst-spinner-wrap">
            <div class="nst-spinner"></div>
            <div class="nst-spinner-label">RUNNING SECTOR ANALYSIS...</div>
        </div>
    </div>

    <!-- Initial Empty State -->
    <div id="empty_state">
        <div class="bias-card sideways" style="justify-content:center; min-height:180px; flex-direction:column; gap:14px;">
            <div style="font-size:2.5rem;">📊</div>
            <div style="font-family:var(--font-head); font-size:1.2rem; font-weight:700; color:var(--text-muted);">
                Select a date and click <span style="color:var(--bull-green);">Analyze</span>
            </div>
            <div style="font-family:var(--font-mono); font-size:11px; color:var(--text-muted);">
                Best used at 3:00–3:15 PM for EOD bias
            </div>
        </div>
    </div>

</div>
</section>

@endsection

@push('script')
<script>
const DIRECTION_LABELS = {
    STRONG_BULLISH: '🟢 STRONG BULLISH',
    BULLISH:        '🔵 BULLISH',
    SIDEWAYS:       '⚪ SIDEWAYS',
    BEARISH:        '🔴 BEARISH',
    STRONG_BEARISH: '🔴 STRONG BEARISH',
};

const DIRECTION_CLASSES = {
    STRONG_BULLISH: 'bullish',
    BULLISH:        'bullish',
    SIDEWAYS:       'sideways',
    BEARISH:        'bearish',
    STRONG_BEARISH: 'bearish',
};

const SECTOR_LABELS = {
    financial: '🏦 Financial Services',
    oil:       '⛽ Oil Gas & Fuels',
    it:        '💻 Information Technology',
};

const SECTOR_COLORS = {
    financial: '#f5c842',
    oil:       '#ff7840',
    it:        '#4a90e2',
};

function runAnalysis() {
    const date = document.getElementById('analysis_date').value;
    if (!date) { alert('Please pick a date'); return; }

    document.getElementById('empty_state').style.display   = 'none';
    document.getElementById('results_wrap').style.display  = 'none';
    document.getElementById('loading_state').style.display = 'flex';
    document.getElementById('btn_analyze').disabled        = true;

    fetch(`{{ route('nifty-sector.analyze') }}?date=${date}`)
        .then(r => r.json())
        .then(data => {
            document.getElementById('loading_state').style.display = 'none';
            document.getElementById('btn_analyze').disabled        = false;

            if (!data.success) {
                alert('Error: ' + (data.message || 'Unknown error'));
                document.getElementById('empty_state').style.display = 'block';
                return;
            }

            renderResults(data);
            document.getElementById('results_wrap').style.display = 'block';
        })
        .catch(err => {
            document.getElementById('loading_state').style.display = 'none';
            document.getElementById('btn_analyze').disabled        = false;
            document.getElementById('empty_state').style.display   = 'block';
            alert('Network error: ' + err.message);
        });
}

function renderResults(data) {
    const bias = data.bias;
    const plan = data.trade_plan;
    const breadth = data.breadth;

    // ── Analyzed at ───────────────────────────────────────────────────────
    document.getElementById('analyzed_at').textContent = 'Last run: ' + data.analyzed_at;

    // ── Bias Card ─────────────────────────────────────────────────────────
    const biasCard = document.getElementById('bias_card');
    biasCard.className = 'bias-card ' + (DIRECTION_CLASSES[bias.direction] || 'sideways');

    const dirText = document.getElementById('bias_direction_text');
    dirText.textContent  = DIRECTION_LABELS[bias.direction] || bias.direction;
    dirText.className    = 'bias-direction ' + (DIRECTION_CLASSES[bias.direction] || 'sideways');

    document.getElementById('bias_reason').innerHTML =
        `Score: <strong>${bias.score_pct > 0 ? '+' : ''}${bias.score_pct}%</strong> &nbsp;|&nbsp; ${bias.reason}`;

    const confBadge = document.getElementById('confidence_badge');
    confBadge.textContent = `${bias.strength} · ${bias.confidence}% confidence`;
    confBadge.className   = 'confidence-badge ' + (bias.confidence >= 80 ? 'strong' : bias.confidence >= 60 ? 'moderate' : 'weak');

    // ── Score Ring ────────────────────────────────────────────────────────
    const absPct = Math.min(Math.abs(bias.score_pct) * 20, 100); // scale 5% → 100%
    const dashoffset = 264 - (264 * absPct / 100);
    const ringFill = document.getElementById('ring_fill');
    ringFill.style.strokeDashoffset = dashoffset;
    ringFill.style.stroke = DIRECTION_CLASSES[bias.direction] === 'bullish'
        ? 'var(--bull-green)'
        : DIRECTION_CLASSES[bias.direction] === 'bearish'
        ? 'var(--bear-red)'
        : 'var(--neutral-gray)';
    document.getElementById('ring_pct').textContent = (bias.score_pct > 0 ? '+' : '') + bias.score_pct + '%';

    // ── Stats Row ─────────────────────────────────────────────────────────
    document.getElementById('stat_score').textContent = (bias.score_pct > 0 ? '+' : '') + bias.score_pct + '%';
    document.getElementById('stat_score').style.color = DIRECTION_CLASSES[bias.direction] === 'bullish'
        ? 'var(--bull-green)' : DIRECTION_CLASSES[bias.direction] === 'bearish' ? 'var(--bear-red)' : 'var(--neutral-gray)';

    const sectorMap = {};
    data.sectors.forEach(s => sectorMap[s.key] = s);
    renderDirectionStat('stat_fin',  sectorMap['financial']?.direction);
    renderDirectionStat('stat_oil',  sectorMap['oil']?.direction);
    renderDirectionStat('stat_it',   sectorMap['it']?.direction);
    renderDirectionStat('stat_breadth', breadth.breadth_signal);

    // ── Trade Plan ────────────────────────────────────────────────────────
    const actionClass = plan.option_type === 'CE' ? 'ce' : plan.option_type === 'PE' ? 'pe' : 'none';
    document.getElementById('trade_grid').innerHTML = `
        <div class="trade-item">
            <div class="trade-item-label">Trade Date</div>
            <div class="trade-item-value">${plan.trade_date || '—'}</div>
        </div>
        <div class="trade-item">
            <div class="trade-item-label">Action</div>
            <div class="trade-item-value ${actionClass}">${plan.action || '—'}</div>
        </div>
        <div class="trade-item">
            <div class="trade-item-label">Strike</div>
            <div class="trade-item-value ${actionClass}">${plan.strike || '—'}</div>
        </div>
        <div class="trade-item">
            <div class="trade-item-label">Entry Time</div>
            <div class="trade-item-value">${plan.entry_time || '—'}</div>
        </div>
        <div class="trade-item">
            <div class="trade-item-label">Stop Loss</div>
            <div class="trade-item-value" style="color:var(--bear-red);">${plan.stop_loss || '—'}</div>
        </div>
        <div class="trade-item">
            <div class="trade-item-label">Target</div>
            <div class="trade-item-value" style="color:var(--bull-green);">${plan.target || '—'}</div>
        </div>
    `;
    document.getElementById('entry_trigger_box').innerHTML =
        `<strong style="color:var(--text-primary);">Entry Trigger:</strong> ${plan.entry_trigger || '—'}`;

    // ── Sector Cards ──────────────────────────────────────────────────────
    let sectorHtml = '';
    data.sectors.forEach(s => {
        const color = SECTOR_COLORS[s.key] || '#888';
        const dirClass = s.direction || 'SIDEWAYS';
        sectorHtml += `
        <div class="sector-card">
            <div class="sector-name">${SECTOR_LABELS[s.key] || s.label}</div>
            <div style="font-family:var(--font-mono); font-size:10px; color:var(--text-muted);">Weight: ${s.weight_pct}% of NIFTY</div>
            <div class="sector-weight-bar">
                <div class="sector-weight-fill" style="width:${s.weight_pct}%; background:${color};"></div>
            </div>
            <div class="sector-score-display">
                <span class="sector-score-val" style="color:${s.score_pct >= 0 ? 'var(--bull-green)' : 'var(--bear-red)'};">
                    ${s.score_pct > 0 ? '+' : ''}${s.score_pct}%
                </span>
                <span class="dir-pill ${dirClass}">${dirClass.replace('_', ' ')}</span>
            </div>
            <div style="font-family:var(--font-mono); font-size:10px; color:var(--text-muted); margin-top:8px;">
                ${s.valid_stocks} stock${s.valid_stocks !== 1 ? 's' : ''} tracked
            </div>
        </div>`;
    });
    document.getElementById('sector_grid').innerHTML = sectorHtml;

    // ── Breadth ───────────────────────────────────────────────────────────
    document.getElementById('breadth_bull_bar').style.width  = breadth.bull_pct + '%';
    document.getElementById('breadth_bear_bar').style.width  = breadth.bear_pct + '%';
    document.getElementById('breadth_bull_lbl').textContent  = `Bullish: ${breadth.bullish} (${breadth.bull_pct}%)`;
    document.getElementById('breadth_bear_lbl').textContent  = `Bearish: ${breadth.bearish} (${breadth.bear_pct}%)`;
    document.getElementById('breadth_neutral_lbl').textContent = `Neutral: ${breadth.neutral}`;

    // ── Stock Table ───────────────────────────────────────────────────────
    if (!data.stocks || data.stocks.length === 0) {
        document.getElementById('stock_tbody').innerHTML =
            '<tr><td colspan="9" class="nst-empty"><i class="fas fa-database"></i>No 15-min OHLC data found for selected date</td></tr>';
        return;
    }

    let tHtml = '';
    data.stocks.forEach(s => {
        const vDev = s.vwap_deviation;
        const vDevColor = vDev > 0 ? 'var(--bull-green)' : vDev < 0 ? 'var(--bear-red)' : 'var(--neutral-gray)';
        const oiLabel = s.oi_signal > 0 ? '↑ Bull' : s.oi_signal < 0 ? '↓ Bear' : '→ Neut';
        const oiColor = s.oi_signal > 0 ? 'var(--bull-green)' : s.oi_signal < 0 ? 'var(--bear-red)' : 'var(--text-muted)';

        tHtml += `<tr>
            <td><span class="sym-badge">${s.symbol}</span></td>
            <td><span class="sector-tag ${s.sector}">${s.sector}</span></td>
            <td>₹${(s.last_close || 0).toLocaleString('en-IN')}</td>
            <td>₹${(s.vwap || 0).toLocaleString('en-IN')}</td>
            <td style="color:${vDevColor}; font-weight:700;">${vDev > 0 ? '+' : ''}${vDev}%</td>
            <td style="color:${s.momentum >= 0 ? 'var(--bull-green)' : 'var(--bear-red)'};">${s.momentum > 0 ? '+' : ''}${(s.momentum || 0).toFixed(2)}</td>
            <td style="color:${oiColor};">${oiLabel}</td>
            <td style="color:${s.score_pct >= 0 ? 'var(--bull-green)' : 'var(--bear-red)'}; font-weight:700;">
                ${s.score_pct > 0 ? '+' : ''}${s.score_pct}%
            </td>
            <td><span class="dir-pill ${s.direction}">${(s.direction || '').replace('_', ' ')}</span></td>
        </tr>`;
    });
    document.getElementById('stock_tbody').innerHTML = tHtml;
}

function renderDirectionStat(elId, direction) {
    const el = document.getElementById(elId);
    if (!el || !direction) return;
    const textMap = {
        STRONG_BULLISH: '🟢 S.Bull', BULLISH: '🔵 Bull',
        SIDEWAYS: '⚪ Sideways',
        BEARISH: '🔴 Bear', STRONG_BEARISH: '🔴 S.Bear',
    };
    const colorMap = {
        STRONG_BULLISH: 'var(--bull-green)', BULLISH: '#00c080',
        SIDEWAYS: 'var(--neutral-gray)',
        BEARISH: '#dd3355', STRONG_BEARISH: 'var(--bear-red)',
    };
    el.textContent = textMap[direction] || direction;
    el.style.color = colorMap[direction] || 'var(--text-primary)';
}

// Auto-run on page load
document.addEventListener('DOMContentLoaded', () => {
    // Slightly delay so UI renders first
    setTimeout(() => runAnalysis(), 400);
});
</script>
@endpush