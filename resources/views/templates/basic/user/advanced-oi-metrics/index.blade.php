{{-- FILE: resources/views/themes/{active_theme}/user/advanced-oi-metrics/index.blade.php --}}
@extends($activeTemplate . 'layouts.frontend')
@section('content')
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Syne:wght@600;700;800&family=Space+Grotesk:wght@400;500;600&display=swap"
        rel="stylesheet">

    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --c-bg: #0B0E11;
            --c-surface: #131722;
            --c-panel: #1C2030;
            --c-border: rgba(255, 255, 255, .06);
            --c-border2: rgba(255, 255, 255, .11);
            --c-lime: #7DFF00;
            --c-blue: #00B8D4;
            --c-red: #EF5350;
            --c-teal: #26A69A;
            --c-amber: #FFA726;
            --c-text: #D1D4DC;
            --c-muted: #787B86;
            --f-sans: 'DM Sans', system-ui, sans-serif;
            --f-display: 'Syne', sans-serif;
            --f-mono: 'Space Grotesk', monospace;
        }

        .aom-wrap {
            font-family: var(--f-sans);
            color: var(--c-text);
            background: var(--c-bg);
        }

        .aom-wrap * {
            box-sizing: border-box;
        }

        .mono {
            font-family: var(--f-mono);
        }

        @keyframes aomSpin {
            to {
                transform: rotate(360deg);
            }
        }

        .aom-hero {
            position: relative;
            overflow: hidden;
            background: var(--c-bg);
            border-bottom: 1px solid var(--c-border);
            padding: 32px;
        }

        .aom-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(125, 255, 0, .02) 1px, transparent 1px), linear-gradient(90deg, rgba(125, 255, 0, .02) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
            pointer-events: none;
        }

        .aom-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--c-lime);
            margin-bottom: 8px;
            position: relative;
        }

        .aom-hero h1 {
            font-family: var(--f-display);
            font-size: clamp(20px, 3vw, 30px);
            font-weight: 800;
            color: #fff;
            position: relative;
        }

        .aom-hero p {
            font-size: 12px;
            color: var(--c-muted);
            margin-top: 8px;
            max-width: 640px;
            position: relative;
            line-height: 1.6;
        }

        .aom-filter-bar {
            background: var(--c-surface);
            border-bottom: 1px solid var(--c-border);
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 200;
            box-shadow: 0 4px 24px rgba(0, 0, 0, .3);
        }

        .aom-filter-inner {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 0;
            flex-wrap: wrap;
        }

        .aom-label {
            font-size: 10px;
            color: var(--c-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            font-family: var(--f-mono);
        }

        .aom-select,
        .aom-input {
            background: var(--c-panel);
            border: 1px solid var(--c-border2);
            border-radius: 7px;
            font-family: var(--f-mono);
            font-size: 12px;
            font-weight: 600;
            color: var(--c-text);
            outline: none;
            padding: 6px 11px;
            min-width: 110px;
        }

        .aom-select {
            padding-right: 28px;
            appearance: none;
            cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .aom-input::-webkit-calendar-picker-indicator {
            filter: invert(1) opacity(.4);
            cursor: pointer;
        }

        .aom-select:focus,
        .aom-input:focus {
            border-color: rgba(125, 255, 0, .45);
        }

        .aom-btn {
            background: var(--c-lime);
            color: #000;
            border: none;
            border-radius: 7px;
            padding: 7px 20px;
            font-family: var(--f-display);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 0 14px rgba(125, 255, 0, .2);
        }

        .aom-btn:hover {
            background: #8FFF1A;
        }

        .aom-content {
            padding: 24px 32px 64px;
        }

        .aom-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        @media(max-width:800px) {
            .aom-summary {
                grid-template-columns: 1fr;
            }
        }

        .aom-sum-card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: 10px;
            padding: 16px 18px;
        }

        .aom-sum-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--c-muted);
            font-family: var(--f-mono);
            margin-bottom: 8px;
        }

        .aom-sum-val {
            font-family: var(--f-display);
            font-size: 26px;
            font-weight: 800;
            color: #fff;
        }

        .aom-sig-BULLISH {
            color: #4DB6AC;
        }

        .aom-sig-BEARISH {
            color: #EF9A9A;
        }

        .aom-sig-NEUTRAL {
            color: var(--c-muted);
        }

        .aom-sig-INSUFFICIENT_DATA {
            color: var(--c-amber);
        }

        .aom-sum-note {
            font-size: 10px;
            color: var(--c-muted);
            margin-top: 6px;
            font-family: var(--f-mono);
        }

        .aom-card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .aom-card-header {
            padding: 13px 18px;
            border-bottom: 1px solid var(--c-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0, 0, 0, .2);
        }

        .aom-card-title {
            font-family: var(--f-display);
            font-size: 14px;
            font-weight: 700;
        }

        .aom-tscroll {
            overflow-x: auto;
        }

        .aom-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--f-mono);
            min-width: 900px;
        }

        .aom-table thead th {
            padding: 9px 12px;
            text-align: left;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--c-muted);
            background: rgba(0, 0, 0, .25);
            border-bottom: 1px solid var(--c-border);
            white-space: nowrap;
        }

        .aom-table tbody td {
            padding: 12px;
            font-size: 11px;
            border-bottom: 1px solid var(--c-border);
            vertical-align: top;
            color: var(--c-text);
        }

        .aom-table tbody tr:nth-child(even) {
            background: rgba(0, 0, 0, .15);
        }

        .aom-metric-name {
            font-family: var(--f-sans);
            font-weight: 700;
            color: #fff;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .aom-info {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 1px solid var(--c-border2);
            color: var(--c-muted);
            font-size: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: help;
            flex-shrink: 0;
        }

        .aom-formula {
            font-size: 9px;
            color: rgba(120, 123, 134, .6);
            margin-top: 3px;
        }

        .aom-val-row {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .aom-badge {
            display: inline-block;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: 9px;
            font-weight: 800;
            font-family: var(--f-sans);
        }

        .b-trig-bull {
            background: rgba(38, 166, 154, .12);
            color: #4DB6AC;
            border: 1px solid rgba(38, 166, 154, .3);
        }

        .b-trig-bear {
            background: rgba(239, 83, 80, .1);
            color: #EF9A9A;
            border: 1px solid rgba(239, 83, 80, .3);
        }

        .b-not {
            background: var(--c-panel);
            color: var(--c-muted);
            border: 1px solid var(--c-border2);
        }

        .b-insuff {
            background: rgba(255, 167, 38, .1);
            color: var(--c-amber);
            border: 1px solid rgba(255, 167, 38, .25);
        }

        .na {
            color: rgba(120, 123, 134, .5);
        }

        .aom-empty {
            text-align: center;
            padding: 52px 20px;
            color: var(--c-muted);
            font-family: var(--f-mono);
            font-size: 12px;
        }

        .aom-spinner-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 52px;
            color: var(--c-muted);
            font-size: 12px;
            font-family: var(--f-mono);
        }

        .aom-spinner {
            width: 26px;
            height: 26px;
            border: 2px solid var(--c-border2);
            border-top: 2px solid var(--c-lime);
            border-radius: 50%;
            animation: aomSpin .9s linear infinite;
        }

        .aom-meta {
            font-size: 10px;
            color: var(--c-muted);
            font-family: var(--f-mono);
            margin-top: 10px;
        }
    </style>

    <div class="aom-wrap">

        <div class="aom-hero">
            <div class="aom-eyebrow">Advanced Confirmation Layer</div>
            <h1>Advanced OI Metrics</h1>
            <p>Six confirmation metrics computed from existing 15-minute option chain data. This is a supplementary layer
                only — it does not replace or alter the primary BUY&nbsp;CE / BUY&nbsp;PE / WAIT sentiment logic.</p>
        </div>

        <div class="aom-filter-bar">
            <div class="aom-filter-inner">
                <span class="aom-label">Symbol</span>
                <select id="aom-sym" class="aom-select">
                    <option value="">— Select —</option>
                </select>
                <span class="aom-label">Date</span>
                <input type="date" id="aom-date" class="aom-input" value="{{ now()->toDateString() }}"
                    max="{{ now()->toDateString() }}">
                <span class="aom-label">Time</span>
                <input type="time" id="aom-time" class="aom-input" value="14:45">
                <button class="aom-btn" onclick="aomAnalyze()">⊙ Analyze</button>
            </div>
        </div>

        <div class="aom-content">

            <div class="aom-summary">
                <div class="aom-sum-card">
                    <div class="aom-sum-label">Bullish Triggers</div>
                    <div class="aom-sum-val" id="aom-bull-score">—</div>
                    <div class="aom-sum-note">of 6 metrics</div>
                </div>
                <div class="aom-sum-card">
                    <div class="aom-sum-label">Bearish Triggers</div>
                    <div class="aom-sum-val" id="aom-bear-score">—</div>
                    <div class="aom-sum-note">of 6 metrics</div>
                </div>
                <div class="aom-sum-card">
                    <div class="aom-sum-label">Advanced OI Confirmation</div>
                    <div class="aom-sum-val" id="aom-signal">—</div>
                    <div class="aom-sum-note">Confirmation only — not a trade signal</div>
                </div>
            </div>

            <div class="aom-card">
                <div class="aom-card-header">
                    <div class="aom-card-title">⊙ Advanced OI Metrics</div>
                </div>
                <div class="aom-tscroll">
                    <table class="aom-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Metric</th>
                                <th>CE Result</th>
                                <th>PE Result</th>
                                <th>Bullish Setup</th>
                                <th>Bearish Setup</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="aom-tbody">
                            <tr>
                                <td colspan="7">
                                    <div class="aom-empty">Select a symbol, date and time, then click Analyze.</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="aom-meta" id="aom-meta" style="padding:0 18px 16px;"></div>
            </div>

        </div>
    </div>
@endsection
@push('script')
    <script>
        var AOM_ANALYZE = '{{ route('advanced-oi-metrics.analyze') }}',
            AOM_SYMBOLS = '{{ route('advanced-oi-metrics.symbols') }}';

        function el(id) {
            return document.getElementById(id);
        }
        document.addEventListener('DOMContentLoaded', function() {
            aomLoadSymbols();
        });

        function aomLoadSymbols() {
            fetch(AOM_SYMBOLS, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(r => r.json()).then(function(res) {
                var sel = el('aom-sym'),
                    opts = '<option value="">— Select —</option>';
                (res.symbols || []).forEach(function(s) {
                    opts += '<option value="' + s + '">' + s + '</option>';
                });
                sel.innerHTML = opts;
                if (res.symbols && res.symbols.length) {
                    sel.value = res.symbols[0];
                    aomAnalyze();
                }
            });
        }

        function aomAnalyze() {
            var symbol = el('aom-sym').value,
                date = el('aom-date').value,
                time = el('aom-time').value;
            if (!symbol || !date) {
                return;
            }
            el('aom-tbody').innerHTML =
                '<tr><td colspan="7"><div class="aom-spinner-row"><div class="aom-spinner"></div>Calculating advanced OI metrics…</div></td></tr>';
            el('aom-meta').textContent = '';
            var params = new URLSearchParams({
                symbol: symbol,
                date: date,
                time: time
            });
            fetch(AOM_ANALYZE + '?' + params.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.json())
                .then(function(res) {
                    if (!res.success) {
                        aomEmpty(res.message || 'Error loading data.');
                        return;
                    }
                    if (res.no_config) {
                        aomEmpty(res.message);
                        return;
                    }
                    if (res.no_data) {
                        aomEmpty(res.message);
                        aomRender(res.advanced_oi, null);
                        return;
                    }
                    aomRender(res.advanced_oi, res.advanced_oi.meta || null);
                })
                .catch(function(err) {
                    aomEmpty('⚠ ' + err.message);
                });
        }

        function badge(status) {
            if (status === 'TRIGGERED') return '<span class="aom-badge b-trig-bull">✓ TRIGGERED</span>';
            if (status === 'INSUFFICIENT_DATA') return '<span class="aom-badge b-insuff">⚠ INSUFFICIENT DATA</span>';
            return '<span class="aom-badge b-not">— NOT TRIGGERED</span>';
        }

        function bearBadge(status) {
            if (status === 'TRIGGERED') return '<span class="aom-badge b-trig-bear">✓ TRIGGERED</span>';
            if (status === 'INSUFFICIENT_DATA') return '<span class="aom-badge b-insuff">⚠ INSUFFICIENT DATA</span>';
            return '<span class="aom-badge b-not">— NOT TRIGGERED</span>';
        }

        function v(x) {
            return (x === null || x === undefined) ? '<span class="na">N/A</span>' : x;
        }

        function info(tip) {
            return '<span class="aom-info" title="' + tip.replace(/"/g, '&quot;') + '">i</span>';
        }

        function aomRender(a, meta) {
            var rows = [{
                    n: 1,
                    name: 'Decay Velocity (V)',
                    tip: '3-strike aggregated live OI divided by 3-strike aggregated anchor OI.',
                    formula: 'ATM-1/ATM/ATM+1 live OI ÷ same basket anchor OI',
                    ce: v(a.decay_velocity.ce),
                    pe: v(a.decay_velocity.pe),
                    bull: 'CE ≤ 0.70',
                    bear: 'PE ≤ 0.70',
                    status: badge(a.decay_velocity.ce_status) + ' ' + bearBadge(a.decay_velocity.pe_status)
                },
                {
                    n: 2,
                    name: 'OI-to-Volume Efficiency (E)',
                    tip: 'Absolute OI change divided by traded volume on the candle.',
                    formula: '|Live OI − Anchor OI| ÷ Volume',
                    ce: v(a.oi_volume_efficiency.ce),
                    pe: v(a.oi_volume_efficiency.pe),
                    bull: 'CE ≥ 0.40',
                    bear: 'PE ≥ 0.40',
                    status: badge(a.oi_volume_efficiency.ce_status) + ' ' + bearBadge(a.oi_volume_efficiency.pe_status)
                },
                {
                    n: 3,
                    name: 'NTM Bias Ratio',
                    tip: 'PE OI across ATM-1, ATM and ATM+1 divided by CE OI across the same strikes.',
                    formula: 'PE(ATM-1,ATM,ATM+1) ÷ CE(ATM-1,ATM,ATM+1)',
                    ce: v(a.ntm_bias.ce_sum),
                    pe: v(a.ntm_bias.pe_sum),
                    bull: 'Ratio ≥ 1.25',
                    bear: 'Ratio ≤ 0.75',
                    status: (a.ntm_bias.ratio !== null ? '<span class="mono" style="margin-right:8px;">Ratio: ' + a
                        .ntm_bias.ratio + '</span>' : '') + badge(a.ntm_bias.bullish_status) + ' ' + bearBadge(a
                        .ntm_bias.bearish_status)
                },
                {
                    n: 4,
                    name: 'Strike Roll-Over Velocity (R)',
                    tip: 'OI change in next-month expiry divided by OI change in current-month expiry.',
                    formula: 'ΔOI(next expiry) ÷ ΔOI(current expiry)',
                    ce: v(a.rollover_velocity.ce),
                    pe: v(a.rollover_velocity.pe),
                    bull: 'CE < 0.20',
                    bear: 'PE < 0.20',
                    status: badge('INSUFFICIENT_DATA') + ' ' + bearBadge('INSUFFICIENT_DATA')
                },
                {
                    n: 5,
                    name: 'Deep OTM Inflection Index (I)',
                    tip: 'OI change at ATM+4 strikes divided by OI change at ATM.',
                    formula: 'ΔOI(ATM+4) ÷ ΔOI(ATM)',
                    ce: v(a.deep_otm_inflection.ce),
                    pe: v(a.deep_otm_inflection.pe),
                    bull: 'CE ≥ 3.0',
                    bear: 'PE ≥ 3.0',
                    status: badge(a.deep_otm_inflection.ce_status) + ' ' + bearBadge(a.deep_otm_inflection.pe_status)
                },
                {
                    n: 6,
                    name: 'Intraday OI Momentum Delta (ΔM)',
                    tip: 'Change in 5-minute OI change, used to detect negative acceleration.',
                    formula: 'ΔOI_5m(t) − ΔOI_5m(t−5m)',
                    ce: v(a.intraday_momentum.ce),
                    pe: v(a.intraday_momentum.pe),
                    bull: 'CE: Negative Accel.',
                    bear: 'PE: Negative Accel.',
                    status: badge('INSUFFICIENT_DATA') + ' ' + bearBadge('INSUFFICIENT_DATA')
                },
            ];

            var h = '';
            rows.forEach(function(r) {
                h += '<tr><td class="mono" style="color:rgba(120,123,134,.5);">' + r.n + '</td>' +
                    '<td><div class="aom-metric-name">' + r.name + ' ' + info(r.tip) +
                    '</div><div class="aom-formula">' + r.formula + '</div></td>' +
                    '<td class="mono">' + r.ce + '</td>' +
                    '<td class="mono">' + r.pe + '</td>' +
                    '<td class="mono" style="color:#4DB6AC;">' + r.bull + '</td>' +
                    '<td class="mono" style="color:#EF9A9A;">' + r.bear + '</td>' +
                    '<td>' + r.status + '</td></tr>';
            });
            el('aom-tbody').innerHTML = h;

            el('aom-bull-score').textContent = (a.bullish_score !== undefined ? a.bullish_score : '—') + ' / 6';
            el('aom-bear-score').textContent = (a.bearish_score !== undefined ? a.bearish_score : '—') + ' / 6';
            var sigEl = el('aom-signal');
            sigEl.textContent = a.signal || '—';
            sigEl.className = 'aom-sum-val aom-sig-' + (a.signal || 'NEUTRAL');

            if (meta) {
                el('aom-meta').textContent = 'Expiry used: ' + (meta.expiry_used || '—') + ' · ATM: ' + (meta.atm_strike ||
                        '—') +
                    ' · Anchor: ' + meta.anchor_date + ' ' + meta.anchor_time +
                    ' · Roll-over Velocity & Momentum Delta are structurally unavailable with current 15-min single-expiry data (see docs).';
            }
        }

        function aomEmpty(msg) {
            el('aom-tbody').innerHTML = '<tr><td colspan="7"><div class="aom-empty">' + msg + '</div></td></tr>';
            el('aom-bull-score').textContent = '—';
            el('aom-bear-score').textContent = '—';
            var sigEl = el('aom-signal');
            sigEl.textContent = '—';
            sigEl.className = 'aom-sum-val';
        }
    </script>
@endpush
