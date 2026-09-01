{{-- FILE: resources/views/themes/{active_theme}/user/price-oi-confirmation/index.blade.php --}}
@extends($activeTemplate . 'layouts.frontend')
@section('content')
    <style>
        :root {
            --c-bg: #0B0E11;
            --c-surface: #131722;
            --c-panel: #1C2030;
            --c-border: rgba(255, 255, 255, .06);
            --c-border2: rgba(255, 255, 255, .11);
            --c-lime: #7DFF00;
            --c-teal: #26A69A;
            --c-amber: #FFA726;
            --c-purple: #AB47BC;
            --c-text: #D1D4DC;
            --c-muted: #787B86;
            --f-sans: 'DM Sans', system-ui, sans-serif;
            --f-mono: 'Space Grotesk', monospace;
        }

        .poc-wrap {
            font-family: var(--f-sans);
            color: var(--c-text);
            background: var(--c-bg);
        }

        .poc-wrap * {
            box-sizing: border-box;
        }

        .poc-hero {
            padding: 28px 32px;
            border-bottom: 1px solid var(--c-border);
        }

        .poc-hero h1 {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 6px;
        }

        .poc-hero p {
            font-size: 13px;
            color: var(--c-muted);
            max-width: 700px;
            line-height: 1.6;
        }

        .poc-filter-bar {
            background: var(--c-surface);
            border-bottom: 1px solid var(--c-border);
            padding: 12px 32px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .poc-select,
        .poc-date-input {
            background: var(--c-panel);
            border: 1px solid var(--c-border2);
            border-radius: 7px;
            font-family: var(--f-mono);
            font-size: 12px;
            color: var(--c-text);
            padding: 6px 10px;
        }

        .poc-btn {
            background: var(--c-lime);
            color: #000;
            border: none;
            border-radius: 7px;
            padding: 7px 18px;
            font-weight: 700;
            font-size: 12px;
            cursor: pointer;
        }

        .poc-content {
            padding: 24px 32px 64px;
        }

        .poc-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--f-mono);
            min-width: 1400px;
        }

        .poc-table thead th {
            padding: 8px 10px;
            text-align: center;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            background: var(--c-panel);
            white-space: nowrap;
            border-bottom: 1px solid var(--c-border);
        }

        .g-slot1 {
            color: var(--c-teal) !important;
        }

        .g-slot2 {
            color: var(--c-amber) !important;
        }

        .g-slot3 {
            color: var(--c-purple) !important;
        }

        .sep-l {
            border-left: 1px solid var(--c-border2) !important;
        }

        .poc-table tbody td {
            padding: 9px 10px;
            text-align: center;
            font-size: 11px;
            border-bottom: 1px solid var(--c-border);
            white-space: nowrap;
        }

        .tr-even {
            background: var(--c-surface);
        }

        .tr-odd {
            background: rgba(0, 0, 0, .15);
        }

        .c-sym {
            font-weight: 800;
            color: #00B8D4;
        }

        .c-date {
            font-weight: 700;
            color: var(--c-lime);
        }

        .na {
            color: rgba(120, 123, 134, .5);
        }

        .sig-buy {
            display: inline-block;
            background: rgba(38, 166, 154, .15);
            color: #4DB6AC;
            border: 1px solid rgba(38, 166, 154, .35);
            border-radius: 5px;
            padding: 3px 9px;
            font-weight: 800;
            font-size: 10px;
        }

        .sig-wait {
            display: inline-block;
            background: var(--c-panel);
            color: var(--c-muted);
            border: 1px solid var(--c-border2);
            border-radius: 5px;
            padding: 3px 9px;
            font-size: 10px;
        }

        .sig-insuff {
            display: inline-block;
            background: rgba(255, 167, 38, .12);
            color: var(--c-amber);
            border: 1px solid rgba(255, 167, 38, .3);
            border-radius: 5px;
            padding: 3px 9px;
            font-size: 10px;
        }

        .poc-empty {
            text-align: center;
            padding: 48px;
            color: var(--c-muted);
        }

        .poc-spin-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 48px;
            color: var(--c-muted);
            font-size: 12px;
        }

        .poc-spinner {
            width: 24px;
            height: 24px;
            border: 2px solid var(--c-border2);
            border-top-color: var(--c-lime);
            border-radius: 50%;
            animation: pocspin .9s linear infinite;
        }

        @keyframes pocspin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>

    <div class="poc-wrap">
        <div class="poc-hero">
            <h1>Price + OI <span style="color:var(--c-lime);">Buy Confirmation</span></h1>
            <p>Price action logic (VWAP, Higher Low, breakout, volume) combined with OI Decay + OI Signal, checked at
                three intraday checkpoints — 10:15, 11:15, 12:15. BUY requires both sides to confirm.</p>
        </div>

        <div class="poc-filter-bar">
            <span style="font-size:10px;color:var(--c-muted);font-family:var(--f-mono);">SYMBOL</span>
            <select id="poc-sym" class="poc-select" onchange="pocAnalyze()">
                <option value="ALL">— All Symbols —</option>
            </select>
            <span style="font-size:10px;color:var(--c-muted);font-family:var(--f-mono);">DATE</span>
            <input type="date" id="poc-date" class="poc-date-input" value="{{ now()->toDateString() }}"
                max="{{ now()->toDateString() }}" onchange="pocAnalyze()">
            <button class="poc-btn" onclick="pocAnalyze()">Analyze</button>
            <span id="poc-subtitle"
                style="margin-left:auto;font-size:10px;color:var(--c-muted);font-family:var(--f-mono);"></span>
        </div>

        <div class="poc-content">
            <div style="overflow-x:auto;">
                <table class="poc-table">
                    <thead>
                        <tr>
                            <th rowspan="2">#</th>
                            <th rowspan="2">Symbol</th>
                            <th rowspan="2">Date</th>
                            <th colspan="3" class="g-slot1 sep-l">10:15</th>
                            <th colspan="3" class="g-slot2 sep-l">11:15</th>
                            <th colspan="3" class="g-slot3 sep-l">12:15</th>
                        </tr>
                        <tr>
                            <th class="sep-l">Price</th>
                            <th>OI Score</th>
                            <th>Final</th>
                            <th class="sep-l">Price</th>
                            <th>OI Score</th>
                            <th>Final</th>
                            <th class="sep-l">Price</th>
                            <th>OI Score</th>
                            <th>Final</th>
                        </tr>
                    </thead>
                    <tbody id="poc-tbody">
                        <tr>
                            <td colspan="12">
                                <div class="poc-spin-row">
                                    <div class="poc-spinner"></div>Loading…
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
@push('script')
    <script>
        var POC_ANALYZE = '{{ route('price-oi-confirmation.analyze') }}',
            POC_SYMBOLS = '{{ route('price-oi-confirmation.symbols') }}',
            POC_LASTDATE = '{{ route('price-oi-confirmation.last.date') }}',
            POC_TODAY = '{{ now()->toDateString() }}';
        var POC_SLOTS = ['10:15', '11:15', '12:15'];

        function el(id) {
            return document.getElementById(id);
        }

        function html(id, h) {
            var e = el(id);
            if (e) e.innerHTML = h;
        }

        function txt(id, t) {
            var e = el(id);
            if (e) e.textContent = t;
        }

        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        document.addEventListener('DOMContentLoaded', function() {
            fetch(POC_LASTDATE, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.json())
                .then(res => {
                    if (res.last_date) el('poc-date').value = res.last_date;
                    return fetch(POC_SYMBOLS, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                })
                .then(r => r.json())
                .then(res => {
                    var sel = el('poc-sym'),
                        opts = '<option value="ALL">— All Symbols —</option>';
                    (res.symbols || []).forEach(s => opts += '<option value="' + s + '">' + s + '</option>');
                    sel.innerHTML = opts;
                    pocAnalyze();
                })
                .catch(pocAnalyze);
        });

        function pocAnalyze() {
            var date = el('poc-date').value,
                sym = el('poc-sym').value;
            if (!date) return;
            html('poc-tbody',
                '<tr><td colspan="12"><div class="poc-spin-row"><div class="poc-spinner"></div>Calculating…</div></td></tr>'
            );
            var params = new URLSearchParams({
                date: date
            });
            if (sym && sym !== 'ALL') params.append('symbols[]', sym);
            fetch(POC_ANALYZE + '?' + params.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.json())
                .then(res => {
                    if (!res.success || !res.data || !res.data.length) {
                        html('poc-tbody', '<tr><td colspan="12"><div class="poc-empty">' + esc(res.message ||
                            'No data.') + '</div></td></tr>');
                        txt('poc-subtitle', res.message || '');
                        return;
                    }
                    pocRender(res.data);
                    txt('poc-subtitle', res.message);
                })
                .catch(err => html('poc-tbody', '<tr><td colspan="12"><div class="poc-empty">⚠ ' + esc(err.message) +
                    '</div></td></tr>'));
        }

        function sigBadge(sig) {
            if (sig === 'BUY') return '<span class="sig-buy">▲ BUY</span>';
            if (sig === 'INSUFFICIENT_DATA') return '<span class="sig-insuff">⚠ N/A</span>';
            return '<span class="sig-wait">— WAIT</span>';
        }

        function pocRender(rows) {
            var h = '',
                num = 1;
            rows.forEach(function(row, i) {
                var zebra = i % 2 === 0 ? 'tr-even' : 'tr-odd';
                if (!row.success || row.no_data) {
                    h += '<tr class="' + zebra + '"><td>' + num++ + '</td><td class="c-sym">' + esc(row.symbol) +
                        '</td><td class="c-date">' + esc(row.date) + '</td><td colspan="9" class="na">' +
                        esc(row.message || 'No data') + '</td></tr>';
                    return;
                }
                var rowHtml = '<tr class="' + zebra + '"><td>' + num++ + '</td><td class="c-sym">' + esc(row
                        .symbol) +
                    '</td><td class="c-date">' + esc(row.date) + '</td>';

                POC_SLOTS.forEach(function(label) {
                    var s = row.slots[label];
                    var priceCell = s.price.has_data ?
                        (s.price.signal === 'BUY' ? '<span class="sig-buy">▲ ' + s.price.score +
                            '/8</span>' : '<span class="sig-wait">' + s.price.score + '/8</span>') :
                        '<span class="na">N/A</span>';
                    var oiCell = s.oi.has_data ?
                        '<span>' + s.oi.score + '</span>' :
                        '<span class="na">N/A</span>';
                    rowHtml += '<td class="sep-l">' + priceCell + '</td><td>' + oiCell + '</td><td>' +
                        sigBadge(s.final_signal) + '</td>';
                });

                rowHtml += '</tr>';
                h += rowHtml;
            });
            html('poc-tbody', h);
        }
    </script>
@endpush
