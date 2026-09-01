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
            --c-purple: #AB47BC;
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

        @keyframes aomUp {
            from {
                opacity: 0;
                transform: translateY(14px)
            }

            to {
                opacity: 1;
                transform: none
            }
        }

        .aom-anim {
            animation: aomUp .5s ease both;
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
            padding: 36px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .aom-hero::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(rgba(125, 255, 0, .022) 1px, transparent 1px), linear-gradient(90deg, rgba(125, 255, 0, .022) 1px, transparent 1px);
            background-size: 56px 56px;
            mask-image: radial-gradient(ellipse 80% 80% at 20% 50%, black, transparent);
            pointer-events: none;
        }

        .aom-hero-left {
            position: relative;
            z-index: 1;
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
            margin-bottom: 10px;
        }

        .aom-eyebrow::before {
            content: '';
            display: block;
            width: 16px;
            height: 1px;
            background: var(--c-lime);
        }

        .aom-hero h1 {
            font-family: var(--f-display);
            font-size: clamp(22px, 3.5vw, 34px);
            font-weight: 800;
            color: #fff;
            line-height: 1.1;
            letter-spacing: -.015em;
            margin-bottom: 10px;
        }

        .aom-hero p {
            font-size: 13px;
            color: var(--c-muted);
            line-height: 1.7;
            max-width: 640px;
        }

        .aom-hero-icon {
            position: relative;
            z-index: 1;
            width: 72px;
            height: 72px;
            border-radius: 12px;
            background: var(--c-surface);
            border: 1px solid var(--c-border2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 30px;
            color: var(--c-lime);
            flex-shrink: 0;
            box-shadow: 0 0 24px rgba(125, 255, 0, .1);
        }

        @media(max-width:768px) {
            .aom-hero {
                flex-direction: column;
                padding: 24px 18px;
            }

            .aom-hero-icon {
                display: none;
            }
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
            flex-shrink: 0;
        }

        .aom-sep {
            width: 1px;
            height: 26px;
            background: var(--c-border2);
            flex-shrink: 0;
        }

        .aom-select,
        .aom-date-input {
            background: var(--c-panel);
            border: 1px solid var(--c-border2);
            border-radius: 7px;
            font-family: var(--f-mono);
            font-size: 12px;
            font-weight: 600;
            color: var(--c-text);
            outline: none;
            padding: 6px 28px 6px 11px;
            transition: border-color .2s;
            appearance: none;
            cursor: pointer;
            min-width: 130px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%23787B86'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }

        .aom-date-input {
            min-width: auto;
            padding: 6px 10px;
            background-image: none;
        }

        .aom-date-input::-webkit-calendar-picker-indicator {
            filter: invert(1) opacity(.4);
            cursor: pointer;
        }

        .aom-select:focus,
        .aom-date-input:focus {
            border-color: rgba(125, 255, 0, .45);
        }

        .aom-date-wrap {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .aom-date-nav {
            width: 28px;
            height: 30px;
            background: var(--c-panel);
            border: 1px solid var(--c-border2);
            border-radius: 6px;
            color: var(--c-muted);
            cursor: pointer;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .2s;
            font-family: var(--f-sans);
        }

        .aom-date-nav:hover {
            border-color: rgba(125, 255, 0, .3);
            color: var(--c-lime);
        }

        .aom-today-btn {
            width: auto;
            padding: 0 10px;
            font-size: 9px;
            font-family: var(--f-mono);
            font-weight: 700;
            letter-spacing: .1em;
        }

        .aom-live-badge {
            background: rgba(38, 166, 154, .12);
            color: #4DB6AC;
            border: 1px solid rgba(38, 166, 154, .25);
            border-radius: 100px;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 9px;
            font-family: var(--f-mono);
        }

        .aom-hist-badge {
            background: rgba(255, 167, 38, .1);
            color: var(--c-amber);
            border: 1px solid rgba(255, 167, 38, .25);
            border-radius: 100px;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 9px;
            font-family: var(--f-mono);
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
            letter-spacing: .06em;
            cursor: pointer;
            transition: all .2s;
            box-shadow: 0 0 14px rgba(125, 255, 0, .2);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .aom-btn:hover {
            background: #8FFF1A;
            box-shadow: 0 0 22px rgba(125, 255, 0, .35);
            transform: translateY(-1px);
        }

        .aom-btn-outline {
            background: var(--c-panel);
            color: var(--c-lime);
            border: 1px solid rgba(125, 255, 0, .35);
            box-shadow: none;
        }

        .aom-btn-outline:hover {
            background: rgba(125, 255, 0, .08);
        }

        .aom-reset-btn {
            background: var(--c-panel);
            border: 1px solid var(--c-border2);
            color: var(--c-muted);
            border-radius: 7px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            font-family: var(--f-sans);
            transition: all .2s;
        }

        .aom-reset-btn:hover {
            color: var(--c-text);
        }

        .aom-filter-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .aom-info-text {
            font-size: 10px;
            color: var(--c-muted);
            font-family: var(--f-mono);
        }

        .aom-upd-text {
            font-size: 10px;
            color: rgba(120, 123, 134, .45);
            font-family: var(--f-mono);
        }

        .aom-hist-wrap {
            display: none;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            background: rgba(125, 255, 0, .04);
            border: 1px solid rgba(125, 255, 0, .15);
            border-radius: 8px;
            padding: 8px 12px;
        }

        .aom-hist-wrap.show {
            display: flex;
        }

        @media(max-width:768px) {
            .aom-filter-bar {
                padding: 0 16px;
            }

            .aom-filter-inner {
                gap: 8px;
            }

            .aom-filter-right {
                margin-left: 0;
                width: 100%;
            }
        }

        .aom-content {
            padding: 24px 32px 64px;
        }

        @media(max-width:768px) {
            .aom-content {
                padding: 16px 12px 48px;
            }
        }

        .aom-warn {
            background: rgba(255, 167, 38, .08);
            border: 1px solid rgba(255, 167, 38, .25);
            border-radius: 9px;
            padding: 14px 18px;
            margin-bottom: 18px;
            display: none;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--c-amber);
        }

        .aom-warn.show {
            display: flex;
        }

        .aom-warn strong {
            color: #fff;
        }

        .aom-card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .aom-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 16px;
            right: 16px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--c-lime), transparent);
            opacity: .3;
        }

        .aom-card-header {
            padding: 13px 18px;
            border-bottom: 1px solid var(--c-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            background: rgba(0, 0, 0, .2);
        }

        .aom-card-title {
            font-family: var(--f-display);
            font-size: 14px;
            font-weight: 700;
            color: var(--c-text);
        }

        .aom-card-subtitle {
            font-size: 10px;
            color: var(--c-muted);
            font-family: var(--f-mono);
        }

        .aom-tscroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .aom-table {
            width: 100%;
            border-collapse: collapse;
            font-family: var(--f-mono);
            min-width: 1500px;
        }

        .aom-table thead tr.th-group th {
            padding: 8px 10px 4px;
            text-align: center;
            font-family: var(--f-sans);
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            background: var(--c-panel);
            white-space: nowrap;
        }

        .aom-table thead tr.th-cols th {
            padding: 4px 10px 8px;
            text-align: center;
            font-family: var(--f-mono);
            font-size: 9px;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            background: rgba(0, 0, 0, .25);
            color: var(--c-muted);
            border-bottom: 1px solid var(--c-border);
            white-space: nowrap;
        }

        .g-info {
            color: var(--c-blue) !important;
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

        .aom-table tbody td {
            padding: 9px 10px;
            text-align: center;
            font-size: 11px;
            border-bottom: 1px solid var(--c-border);
            vertical-align: middle;
            white-space: nowrap;
            color: var(--c-text);
            transition: background .15s;
        }

        .aom-table tbody tr:hover td {
            background: rgba(255, 255, 255, .02) !important;
        }

        .tr-even {
            background: var(--c-surface);
        }

        .tr-odd {
            background: rgba(0, 0, 0, .15);
        }

        .c-num {
            font-size: 9px;
            color: rgba(120, 123, 134, .35);
        }

        .c-sym {
            font-size: 12px;
            font-weight: 800;
            color: var(--c-blue);
        }

        .c-date {
            font-size: 11px;
            font-weight: 700;
            color: var(--c-lime);
        }

        .c-atm {
            font-size: 10px;
            color: var(--c-amber);
            font-weight: 700;
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

        .sig-bull {
            display: inline-block;
            background: rgba(38, 166, 154, .12);
            color: #4DB6AC;
            border: 1px solid rgba(38, 166, 154, .3);
            border-radius: 5px;
            padding: 3px 9px;
            font-family: var(--f-sans);
            font-size: 9px;
            font-weight: 800;
            white-space: nowrap;
        }

        .sig-bear {
            display: inline-block;
            background: rgba(239, 83, 80, .1);
            color: #EF9A9A;
            border: 1px solid rgba(239, 83, 80, .3);
            border-radius: 5px;
            padding: 3px 9px;
            font-family: var(--f-sans);
            font-size: 9px;
            font-weight: 800;
            white-space: nowrap;
        }

        .sig-neut {
            display: inline-block;
            background: var(--c-panel);
            color: var(--c-muted);
            border: 1px solid var(--c-border2);
            border-radius: 5px;
            padding: 3px 9px;
            font-family: var(--f-sans);
            font-size: 9px;
            white-space: nowrap;
        }

        .sig-insuff {
            display: inline-block;
            background: rgba(255, 167, 38, .1);
            color: var(--c-amber);
            border: 1px solid rgba(255, 167, 38, .25);
            border-radius: 5px;
            padding: 3px 9px;
            font-family: var(--f-sans);
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }

        .aom-empty {
            text-align: center;
            padding: 52px 20px;
            color: var(--c-muted);
        }

        .aom-empty-icon {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: var(--c-panel);
            border: 1px solid var(--c-border);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 20px;
        }

        .aom-empty p {
            font-size: 12px;
            font-family: var(--f-mono);
            margin-top: 4px;
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
            width: 28px;
            height: 28px;
            border: 2px solid var(--c-border2);
            border-top: 2px solid var(--c-lime);
            border-radius: 50%;
            animation: aomSpin .9s linear infinite;
            flex-shrink: 0;
        }
    </style>

    <div class="aom-wrap">

        <div class="aom-hero aom-anim">
            <div class="aom-hero-left">
                <div class="aom-eyebrow">Advanced Confirmation Layer</div>
                <h1>Advanced <span style="color:var(--c-lime);">OI</span> Metrics</h1>
                <p>OI Decay Velocity ((OI_prev − OI_curr) / (OI_prev × Δt) × 100) + OI Signal, computed at three
                    intraday checkpoints today — 10:15, 11:15 and 12:15 — each measured against the previous
                    trading day's 15:00 close, with Δt = trading hours since today's market open. Supplementary
                    layer only — does not replace or alter the primary BUY&nbsp;CE / BUY&nbsp;PE / WAIT sentiment
                    logic.</p>
            </div>
            <div class="aom-hero-icon"><i class="las la-layer-group"></i></div>
        </div>

        <div class="aom-filter-bar">
            <div class="aom-filter-inner">
                <span class="aom-label">Symbol</span>
                <select id="aom-sym" class="aom-select" onchange="aomOnSymbolChange()">
                    <option value="ALL">— All Symbols —</option>
                </select>
                <div class="aom-sep"></div>
                <span class="aom-label">Date</span>
                <div class="aom-date-wrap">
                    <button class="aom-date-nav" onclick="aomShiftDate(-1)">‹</button>
                    <input type="date" id="aom-date" class="aom-date-input" value="{{ now()->toDateString() }}"
                        max="{{ now()->toDateString() }}" onchange="aomAnalyze()">
                    <button class="aom-date-nav" onclick="aomShiftDate(1)">›</button>
                    <button class="aom-date-nav aom-today-btn" onclick="aomGoToday()">TODAY</button>
                    <span id="aom-date-badge"></span>
                </div>
                <button class="aom-btn" onclick="aomAnalyze()"><i class="las la-search"></i> Analyze</button>
                <button class="aom-reset-btn" onclick="aomReset()">↺ Reset</button>

                <div class="aom-hist-wrap" id="aom-hist-wrap">
                    <span class="aom-label" style="color:var(--c-lime);">📈 History for <span
                            id="aom-hist-sym-label"></span></span>
                    <span class="aom-label">From</span>
                    <input type="date" id="aom-hist-from" class="aom-date-input" max="{{ now()->toDateString() }}">
                    <span class="aom-label">To</span>
                    <input type="date" id="aom-hist-to" class="aom-date-input" value="{{ now()->toDateString() }}"
                        max="{{ now()->toDateString() }}">
                    <button class="aom-btn aom-btn-outline" onclick="aomLoadHistory()">Load History</button>
                    <button class="aom-reset-btn" onclick="aomExitHistory()">✕ Exit History</button>
                </div>

                <div class="aom-filter-right">
                    <span class="aom-info-text" id="aom-info"></span>
                    <span class="aom-upd-text" id="aom-upd"></span>
                </div>
            </div>
        </div>

        <div class="aom-content">
            <div class="aom-warn" id="aom-warn"><i class="las la-exclamation-triangle"></i>
                <div><strong>No Analysis Config Found</strong>
                    <div style="font-size:12px;margin-top:3px;color:var(--c-muted);" id="aom-warn-msg">Go to Admin →
                        Analysis Config and create a config with symbols.</div>
                </div>
            </div>

            <div class="aom-card aom-anim">
                <div class="aom-card-header">
                    <div class="aom-card-title">⊙ Advanced OI Metrics</div>
                    <span class="aom-card-subtitle" id="aom-subtitle">Detecting last available date…</span>
                </div>
                <div class="aom-tscroll">
                    <table class="aom-table">
                        <thead>
                            <tr class="th-group">
                                <th colspan="4" class="g-info">Market Info</th>
                                <th colspan="4" class="g-slot1 sep-l">10:15 · Δt=1hr (vs prev close)</th>
                                <th colspan="4" class="g-slot2 sep-l">11:15 · Δt=2hr (vs prev close)</th>
                                <th colspan="4" class="g-slot3 sep-l">12:15 · Δt=3hr (vs prev close)</th>
                            </tr>
                            <tr class="th-cols">
                                <th>#</th>
                                <th>Symbol</th>
                                <th>Date</th>
                                <th>ATM</th>
                                <th class="sep-l">CE ≥5%/hr</th>
                                <th>PE ≥5%/hr</th>
                                <th>Signal</th>
                                <th>OI Signal</th>
                                <th class="sep-l">CE ≥5%/hr</th>
                                <th>PE ≥5%/hr</th>
                                <th>Signal</th>
                                <th>OI Signal</th>
                                <th class="sep-l">CE ≥5%/hr</th>
                                <th>PE ≥5%/hr</th>
                                <th>Signal</th>
                                <th>OI Signal</th>
                            </tr>
                        </thead>
                        <tbody id="aom-tbody">
                            <tr>
                                <td colspan="16">
                                    <div class="aom-spinner-row">
                                        <div class="aom-spinner"></div>Detecting last available date…
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script')
    <script>
        var AOM_ANALYZE = '{{ route('advanced-oi-metrics.analyze') }}',
            AOM_SYMBOLS = '{{ route('advanced-oi-metrics.symbols') }}',
            AOM_LASTDATE = '{{ route('advanced-oi-metrics.last.date') }}',
            AOM_TODAY = '{{ now()->toDateString() }}';
        var aomSymCache = null,
            aomHistoryMode = false;
        var AOM_SLOT_LABELS = ['10:15', '11:15', '12:15'];

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
        document.addEventListener('DOMContentLoaded', function() {
            var d = new Date();
            d.setDate(d.getDate() - 7);
            el('aom-hist-from').value = d.toISOString().split('T')[0];
            aomResolveLastDateAndLoad();
        });

        function aomResolveLastDateAndLoad() {
            fetch(AOM_LASTDATE, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(r) {
                return r.json();
            }).then(function(res) {
                if (res.last_date) el('aom-date').value = res.last_date;
                aomLoadSymbols(function() {
                    aomAnalyze();
                });
            }).catch(function() {
                aomLoadSymbols(function() {
                    aomAnalyze();
                });
            });
        }

        function aomGetDate() {
            return el('aom-date').value;
        }

        function aomShiftDate(d) {
            var p = el('aom-date'),
                dt = new Date(p.value);
            dt.setDate(dt.getDate() + d);
            var s = dt.toISOString().split('T')[0];
            if (s > AOM_TODAY) return;
            p.value = s;
            aomAnalyze();
        }

        function aomGoToday() {
            el('aom-date').value = AOM_TODAY;
            aomAnalyze();
        }

        function aomUpdateDateBadge(isToday) {
            el('aom-date-badge').innerHTML = isToday ? '<span class="aom-live-badge">● Live</span>' :
                '<span class="aom-hist-badge">📅 Historical</span>';
        }

        function aomLoadSymbols(callback) {
            if (aomSymCache !== null) {
                aomRebuildSym(aomSymCache);
                if (callback) callback();
                return;
            }
            fetch(AOM_SYMBOLS, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(r) {
                return r.json();
            }).then(function(res) {
                if (res.no_config) {
                    aomShowWarn(res.message || '');
                    aomSymCache = [];
                    aomRebuildSym([]);
                } else {
                    aomHideWarn();
                    aomSymCache = res.symbols || [];
                    aomRebuildSym(aomSymCache);
                }
                if (callback) callback();
            }).catch(function() {
                if (callback) callback();
            });
        }

        function aomRebuildSym(syms) {
            var sel = el('aom-sym'),
                prev = sel.value,
                opts = '<option value="ALL">— All Symbols —</option>';
            syms.forEach(function(s) {
                opts += '<option value="' + s + '"' + (s === prev ? ' selected' : '') + '>' + s + '</option>';
            });
            sel.innerHTML = opts;
            if (prev && prev !== 'ALL') {
                sel.value = prev;
                if (sel.value !== prev) sel.value = 'ALL';
            }
        }

        function aomOnSymbolChange() {
            var sym = el('aom-sym').value;
            if (sym && sym !== 'ALL') {
                el('aom-hist-wrap').classList.add('show');
                txt('aom-hist-sym-label', sym);
            } else {
                el('aom-hist-wrap').classList.remove('show');
                if (aomHistoryMode) aomExitHistory();
            }
            if (!aomHistoryMode) aomAnalyze();
        }

        function aomExitHistory() {
            aomHistoryMode = false;
            aomAnalyze();
        }

        function aomLoadHistory() {
            var sym = el('aom-sym').value;
            if (!sym || sym === 'ALL') return;
            var from = el('aom-hist-from').value,
                to = el('aom-hist-to').value;
            if (!from || !to) return;
            aomHistoryMode = true;
            aomHideWarn();
            html('aom-tbody',
                '<tr><td colspan="16"><div class="aom-spinner-row"><div class="aom-spinner"></div>Loading history for ' +
                sym + ' (' + from + ' → ' + to + ')…</div></td></tr>');
            txt('aom-subtitle', sym + ' · Loading history…');
            var params = new URLSearchParams({
                from_date: from,
                to_date: to
            });
            params.append('symbols[]', sym);
            fetch(AOM_ANALYZE + '?' + params.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(r) {
                    if (!r.ok) throw new Error('Server error ' + r.status);
                    return r.json();
                })
                .then(function(res) {
                    if (!res.success || !res.data || !res.data.length) {
                        aomEmptyTable(res.message || 'No history found for this range.');
                        txt('aom-subtitle', sym + ' · No history found');
                        return;
                    }
                    aomRenderTable(res.data);
                    txt('aom-subtitle', sym + ' · ' + res.message);
                    txt('aom-upd', 'Updated ' + new Date().toLocaleTimeString());
                    el('aom-info').textContent = 'History: ' + from + ' → ' + to;
                })
                .catch(function(err) {
                    aomEmptyTable('⚠ ' + err.message);
                });
        }

        function aomAnalyze() {
            if (aomHistoryMode) return;
            var date = aomGetDate(),
                sym = el('aom-sym').value;
            if (!date) return;
            aomHideWarn();
            html('aom-tbody',
                '<tr><td colspan="16"><div class="aom-spinner-row"><div class="aom-spinner"></div>Calculating advanced OI metrics for ' +
                date + '…</div></td></tr>');
            txt('aom-subtitle', date + ' · Loading…');
            var params = new URLSearchParams({
                date: date
            });
            if (sym && sym !== 'ALL') params.append('symbols[]', sym);
            fetch(AOM_ANALYZE + '?' + params.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(r) {
                    if (!r.ok) throw new Error('Server error ' + r.status);
                    return r.json();
                })
                .then(function(res) {
                    if (typeof res.is_today !== 'undefined') aomUpdateDateBadge(res.is_today);
                    if (res.available_symbols && res.available_symbols.length) {
                        aomSymCache = res.available_symbols;
                        aomRebuildSym(aomSymCache);
                        if (sym && sym !== 'ALL') el('aom-sym').value = sym;
                    }
                    if (res.no_config) {
                        aomShowWarn(res.message);
                        aomEmptyTable('No active config.');
                        return;
                    }
                    if (!res.success || !res.data || !res.data.length) {
                        aomEmptyTable(res.message || 'No data found for this date.');
                        txt('aom-subtitle', date + ' · No data found');
                        return;
                    }
                    aomRenderTable(res.data);
                    txt('aom-subtitle', date + ' · ' + res.message);
                    txt('aom-upd', 'Updated ' + new Date().toLocaleTimeString());
                    el('aom-info').textContent = '';
                })
                .catch(function(err) {
                    aomEmptyTable('⚠ ' + err.message);
                });
        }

        function v(x) {
            return (x === null || x === undefined) ? '<span class="na">N/A</span>' : x;
        }

        // Decay Velocity values are a %/hr rate now (not a 0–1 ratio) — append '%' for clarity.
        function vPct(x) {
            return (x === null || x === undefined) ? '<span class="na">N/A</span>' : x + '%';
        }

        function badge(status, side) {
            if (status === 'TRIGGERED') return '<span class="aom-badge ' + (side === 'bear' ? 'b-trig-bear' :
                'b-trig-bull') + '" title="TRIGGERED">✓</span>';
            if (status === 'INSUFFICIENT_DATA')
                return '<span class="aom-badge b-insuff" title="INSUFFICIENT DATA">⚠</span>';
            return '<span class="aom-badge b-not" title="NOT TRIGGERED">—</span>';
        }

        function signalBadge(sig, strength, tip) {
            var t = tip ? ' title="' + esc(tip) + '"' : '';
            var cls = 'sig-neut',
                arrow = '—',
                label = 'NEUTRAL';
            if (sig === 'BULLISH') {
                cls = 'sig-bull';
                arrow = '▲';
                label = 'BULLISH';
            } else if (sig === 'BEARISH') {
                cls = 'sig-bear';
                arrow = '▼';
                label = 'BEARISH';
            } else if (sig === 'INSUFFICIENT_DATA') {
                cls = 'sig-insuff';
                arrow = '⚠';
                label = 'N/A';
            }
            var strengthLine = strength ? '<div style="font-size:8px;color:rgba(120,123,134,.6);margin-top:2px;">' +
                strength + '</div>' : '';
            return '<div class="aom-val-row"><span class="' + cls + '"' + t + '>' + arrow + ' ' + label + '</span>' +
                strengthLine + '</div>';
        }

        function aomRenderTable(rows) {
            var h = '',
                num = 1;
            rows.forEach(function(row, i) {
                var zebra = i % 2 === 0 ? 'tr-even' : 'tr-odd';

                if (!row.success) {
                    h += '<tr class="' + zebra + '"><td class="c-num">' + num++ + '</td><td class="c-sym">' + esc(
                            row.symbol) + '</td><td class="c-date">' + esc(row.date || '') +
                        '</td><td colspan="13" class="na">Error: ' + esc(row.message || 'failed') + '</td></tr>';
                    return;
                }
                if (row.no_data) {
                    h += '<tr class="' + zebra + '"><td class="c-num">' + num++ + '</td><td class="c-sym">' + esc(
                            row.symbol) + '</td><td class="c-date">' + esc(row.date || '') +
                        '</td><td colspan="13" class="na">No data for this date</td></tr>';
                    return;
                }

                var a = row.advanced_oi,
                    meta = a.meta || {};

                var rowHtml = '<tr class="' + zebra + '">' +
                    '<td class="c-num">' + num++ + '</td>' +
                    '<td class="c-sym">' + esc(row.symbol) + '</td>' +
                    '<td class="c-date">' + esc(row.date || meta.date || '') + '</td>' +
                    '<td><span class="c-atm">' + (meta.atm_strike || '—') + '</span></td>';

                AOM_SLOT_LABELS.forEach(function(label) {
                    var s = a.slots && a.slots[label] ? a.slots[label] : null;

                    if (!s || s.no_data) {
                        rowHtml += '<td class="sep-l na" colspan="4">No data (' + label + ')</td>';
                        return;
                    }

                    var oiTip = s.oi_signal.condition + ' — ' + s.oi_signal.reason + ' (CE ' + s.oi_signal
                        .ce_oi_pct + '% / PE ' + s.oi_signal.pe_oi_pct + '%)';

                    rowHtml +=
                        '<td class="sep-l">' + vPct(s.decay_velocity.ce) + ' ' + badge(s.decay_velocity
                            .ce_status,
                            'bull') + '</td>' +
                        '<td>' + vPct(s.decay_velocity.pe) + ' ' + badge(s.decay_velocity.pe_status,
                        'bear') +
                        '</td>' +
                        '<td>' + signalBadge(s.decay_signal, s.decay_strength) + '</td>' +
                        '<td>' + signalBadge(s.oi_signal.sentiment, null, oiTip) + '</td>';
                });

                rowHtml += '</tr>';
                h += rowHtml;
            });
            html('aom-tbody', h || aomEmptyHtml('No results.'));
        }

        function aomShowWarn(msg) {
            el('aom-warn').classList.add('show');
            txt('aom-warn-msg', msg || '');
        }

        function aomHideWarn() {
            el('aom-warn').classList.remove('show');
        }

        function aomEmptyTable(msg) {
            html('aom-tbody', aomEmptyHtml(msg));
        }

        function aomEmptyHtml(msg) {
            return '<tr><td colspan="16"><div class="aom-empty"><div class="aom-empty-icon"><i class="las la-layer-group"></i></div><p>' +
                (msg || 'No data found.') + '</p></div></td></tr>';
        }

        function aomReset() {
            aomHistoryMode = false;
            el('aom-hist-wrap').classList.remove('show');
            fetch(AOM_LASTDATE, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(r) {
                return r.json();
            }).then(function(res) {
                el('aom-date').value = res.last_date || AOM_TODAY;
                el('aom-sym').value = 'ALL';
                aomHideWarn();
                aomAnalyze();
            }).catch(function() {
                el('aom-date').value = AOM_TODAY;
                el('aom-sym').value = 'ALL';
                aomHideWarn();
                aomAnalyze();
            });
        }

        function esc(s) {
            return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    </script>
@endpush
