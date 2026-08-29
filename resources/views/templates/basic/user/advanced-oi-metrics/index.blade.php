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
            min-width: 1100px;
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
            line-height: 1.6;
        }

        .aom-table thead th span {
            color: rgba(120, 123, 134, .55);
        }

        .aom-table tbody td {
            padding: 12px;
            font-size: 11px;
            border-bottom: 1px solid var(--c-border);
            vertical-align: top;
            color: var(--c-text);
            white-space: nowrap;
        }

        .aom-table tbody tr:nth-child(even) {
            background: rgba(0, 0, 0, .15);
        }

        .aom-table tbody tr:hover {
            background: rgba(125, 255, 0, .03);
        }

        .aom-val-row {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .aom-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            width: 16px;
            height: 16px;
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
        }
    </style>

    <div class="aom-wrap">

        <div class="aom-hero">
            <div class="aom-eyebrow">Advanced Confirmation Layer</div>
            <h1>Advanced OI Metrics</h1>
            <p>Six confirmation metrics computed from existing 15-minute option chain data, for every symbol in the
                active config. This is a supplementary layer only — it does not replace or alter the primary
                BUY&nbsp;CE / BUY&nbsp;PE / WAIT sentiment logic.</p>
        </div>

        <div class="aom-filter-bar">
            <div class="aom-filter-inner">
                <span class="aom-label">Date</span>
                <input type="date" id="aom-date" class="aom-input" value="{{ now()->toDateString() }}"
                    max="{{ now()->toDateString() }}">
                <span class="aom-label">Time</span>
                <input type="time" id="aom-time" class="aom-input" value="14:45">
                <button class="aom-btn" onclick="aomAnalyze()">⊙ Analyze All Symbols</button>
            </div>
        </div>

        <div class="aom-content">

            <div class="aom-card">
                <div class="aom-card-header">
                    <div class="aom-card-title">⊙ Advanced OI Metrics — All Symbols</div>
                </div>
                <div class="aom-tscroll">
                    <table class="aom-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Symbol</th>
                                <th>Decay V<br><span>CE≤0.70 / PE≤0.70</span></th>
                                <th>Efficiency<br><span>CE≥0.40 / PE≥0.40</span></th>
                                <th>NTM Bias<br><span>≥1.25 / ≤0.75</span></th>
                                <th>Rollover<br><span>CE&lt;0.20 / PE&lt;0.20</span></th>
                                <th>Deep OTM<br><span>CE≥3.0 / PE≥3.0</span></th>
                                <th>Momentum<br><span>Neg. Accel.</span></th>
                                <th>Bull</th>
                                <th>Bear</th>
                                <th>Signal</th>
                            </tr>
                        </thead>
                        <tbody id="aom-tbody">
                            <tr>
                                <td colspan="11">
                                    <div class="aom-empty">Select a date and time, then click Analyze.</div>
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
        var AOM_ANALYZE_ALL = '{{ route('advanced-oi-metrics.analyze-all') }}';

        function el(id) {
            return document.getElementById(id);
        }

        document.addEventListener('DOMContentLoaded', function() {
            aomAnalyze();
        });

        function aomAnalyze() {
            var date = el('aom-date').value,
                time = el('aom-time').value;
            if (!date) return;

            el('aom-tbody').innerHTML =
                '<tr><td colspan="11"><div class="aom-spinner-row"><div class="aom-spinner"></div>Calculating advanced OI metrics for all symbols…</div></td></tr>';
            el('aom-meta').textContent = '';

            var params = new URLSearchParams({
                date: date,
                time: time
            });

            fetch(AOM_ANALYZE_ALL + '?' + params.toString(), {
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
                    aomRenderAll(res.rows, res.date, res.time, res.message);
                })
                .catch(function(err) {
                    aomEmpty('⚠ ' + err.message);
                });
        }

        function badge(status, side) {
            if (status === 'TRIGGERED') {
                return '<span class="aom-badge ' + (side === 'bear' ? 'b-trig-bear' : 'b-trig-bull') +
                    '" title="TRIGGERED">✓</span>';
            }
            if (status === 'INSUFFICIENT_DATA')
                return '<span class="aom-badge b-insuff" title="INSUFFICIENT DATA">⚠</span>';
            return '<span class="aom-badge b-not" title="NOT TRIGGERED">—</span>';
        }

        function v(x) {
            return (x === null || x === undefined) ? '<span class="na">N/A</span>' : x;
        }

        // Renders a CE/PE pair stacked with its trigger badge
        function pairCell(ceVal, peVal, ceStatus, peStatus) {
            return '<div class="aom-val-row">' +
                '<span class="mono">CE ' + v(ceVal) + ' ' + badge(ceStatus, 'bull') + '</span>' +
                '<span class="mono">PE ' + v(peVal) + ' ' + badge(peStatus, 'bear') + '</span>' +
                '</div>';
        }

        function ntmCell(a) {
            var n = a.ntm_bias;
            if (n.ratio === null) {
                return '<span class="na">N/A</span> ' + badge('INSUFFICIENT_DATA');
            }
            return '<div class="aom-val-row">' +
                '<span class="mono">Ratio ' + n.ratio + '</span>' +
                '<span>' + badge(n.bullish_status, 'bull') + ' ' + badge(n.bearish_status, 'bear') + '</span>' +
                '</div>';
        }

        function insufficientCell() {
            return '<span class="na">N/A</span> ' + badge('INSUFFICIENT_DATA', 'bull') + ' ' + badge('INSUFFICIENT_DATA',
                'bear');
        }

        function aomRenderAll(rows, date, time, emptyMessage) {
            if (!rows || !rows.length) {
                aomEmpty(emptyMessage || 'No symbols found in the active config.');
                return;
            }

            var h = '';
            rows.forEach(function(row, i) {
                if (!row.success) {
                    h += '<tr><td class="mono">' + (i + 1) + '</td><td><strong>' + row.symbol + '</strong></td>' +
                        '<td colspan="9" class="na">Error: ' + (row.message || 'failed') + '</td></tr>';
                    return;
                }
                if (row.no_data) {
                    h += '<tr><td class="mono">' + (i + 1) + '</td><td><strong>' + row.symbol + '</strong></td>' +
                        '<td colspan="9" class="na">No data for this date/time</td></tr>';
                    return;
                }

                var a = row.advanced_oi;
                var meta = a.meta || {};
                var symTip = 'Expiry: ' + (meta.expiry_used || '—') + ' · ATM: ' + (meta.atm_strike || '—');

                h += '<tr>' +
                    '<td class="mono">' + (i + 1) + '</td>' +
                    '<td><strong title="' + symTip + '">' + row.symbol + '</strong></td>' +
                    '<td>' + pairCell(a.decay_velocity.ce, a.decay_velocity.pe, a.decay_velocity.ce_status, a
                        .decay_velocity.pe_status) + '</td>' +
                    '<td>' + pairCell(a.oi_volume_efficiency.ce, a.oi_volume_efficiency.pe, a.oi_volume_efficiency
                        .ce_status, a.oi_volume_efficiency.pe_status) + '</td>' +
                    '<td>' + ntmCell(a) + '</td>' +
                    '<td>' + insufficientCell() + '</td>' +
                    '<td>' + pairCell(a.deep_otm_inflection.ce, a.deep_otm_inflection.pe, a.deep_otm_inflection
                        .ce_status, a.deep_otm_inflection.pe_status) + '</td>' +
                    '<td>' + insufficientCell() + '</td>' +
                    '<td class="mono">' + a.bullish_score + '</td>' +
                    '<td class="mono">' + a.bearish_score + '</td>' +
                    '<td><span class="aom-sig-' + a.signal + '" style="font-weight:800;">' + a.signal +
                    '</span></td>' +
                    '</tr>';
            });

            el('aom-tbody').innerHTML = h;
            el('aom-meta').textContent = 'Snapshot: ' + date + ' ' + time +
                ' · Roll-over Velocity & Momentum Delta are structurally unavailable with current 15-min single-expiry data.';
        }

        function aomEmpty(msg) {
            el('aom-tbody').innerHTML = '<tr><td colspan="11"><div class="aom-empty">' + msg + '</div></td></tr>';
            el('aom-meta').textContent = '';
        }
    </script>
@endpush
