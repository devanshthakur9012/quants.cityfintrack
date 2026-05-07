@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
@import url('https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=DM+Sans:wght@300;400;500;600;700&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bull: #00c853;
    --bear: #ff1744;
    --neut: #607d8b;
    --side: #ffa000;
    --bg: #0d1117;
    --card: #161b22;
    --border: #30363d;
    --text: #e6edf3;
    --muted: #8b949e;
    --accent: #58a6ff;
}

body { background: var(--bg); color: var(--text); font-family: 'DM Sans', sans-serif; }

.tp-wrap { max-width: 900px; margin: 0 auto; padding: 28px 16px 60px; }

/* Header */
.tp-head {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 12px;
    border-bottom: 1px solid var(--border);
    padding-bottom: 18px; margin-bottom: 24px;
}
.tp-head-left h2 {
    font-family: 'Space Mono', monospace;
    font-size: 18px; font-weight: 700; color: var(--text);
    letter-spacing: -0.3px;
}
.tp-head-left p { font-size: 12px; color: var(--muted); margin-top: 4px; }

/* Filter bar */
.tp-filter {
    display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 16px 18px; margin-bottom: 24px;
}
.tp-filter .fld { display: flex; flex-direction: column; gap: 5px; }
.tp-filter label { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.6px; color: var(--muted); }
.tp-filter select,
.tp-filter input[type="date"] {
    background: var(--bg); border: 1px solid var(--border);
    color: var(--text); border-radius: 6px; padding: 8px 12px;
    font-size: 13px; font-family: 'DM Sans', sans-serif;
    outline: none; min-width: 140px;
}
.tp-filter select:focus,
.tp-filter input:focus { border-color: var(--accent); }

.btn-predict {
    background: var(--accent); color: #0d1117;
    border: none; border-radius: 6px; padding: 9px 22px;
    font-size: 13px; font-weight: 700; cursor: pointer;
    font-family: 'DM Sans', sans-serif; white-space: nowrap;
    transition: opacity .15s;
}
.btn-predict:hover { opacity: .85; }
.btn-predict:disabled { opacity: .4; cursor: not-allowed; }

/* Result panel */
#result-panel { display: none; }

/* Verdict card */
.verdict-card {
    border-radius: 12px; padding: 28px 24px;
    border: 2px solid var(--border); margin-bottom: 20px;
    position: relative; overflow: hidden;
    transition: border-color .3s;
}
.verdict-card.bull { border-color: var(--bull); background: rgba(0,200,83,.04); }
.verdict-card.bear { border-color: var(--bear); background: rgba(255,23,68,.04); }
.verdict-card.side { border-color: var(--side); background: rgba(255,160,0,.04); }

.verdict-top { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
.verdict-trend {
    font-family: 'Space Mono', monospace;
    font-size: 32px; font-weight: 700; letter-spacing: -1px;
}
.verdict-trend.bull { color: var(--bull); }
.verdict-trend.bear { color: var(--bear); }
.verdict-trend.side { color: var(--side); }

.action-badge {
    display: inline-block;
    font-family: 'Space Mono', monospace;
    font-size: 15px; font-weight: 700; letter-spacing: 0.5px;
    padding: 10px 22px; border-radius: 8px;
}
.action-badge.buy-ce  { background: rgba(0,200,83,.15); color: var(--bull); border: 1.5px solid var(--bull); }
.action-badge.buy-pe  { background: rgba(255,23,68,.15); color: var(--bear); border: 1.5px solid var(--bear); }
.action-badge.no-trade { background: rgba(255,160,0,.1); color: var(--side); border: 1.5px solid var(--side); }

.verdict-meta { font-size: 12px; color: var(--muted); }
.verdict-meta span { color: var(--text); font-weight: 600; }

/* Confidence bar */
.conf-row { display: flex; align-items: center; gap: 12px; margin-top: 14px; }
.conf-bar-bg { flex: 1; background: var(--border); border-radius: 20px; height: 6px; }
.conf-bar    { height: 6px; border-radius: 20px; transition: width .5s ease; }
.conf-label  { font-size: 11px; font-weight: 700; font-family: 'Space Mono', monospace; min-width: 36px; }

/* Signal grid */
.signal-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
    margin-bottom: 20px;
}
@media (max-width: 600px) { .signal-grid { grid-template-columns: 1fr; } }

.sig-card {
    background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 14px 16px;
}
.sig-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.sig-label { font-size: 10px; text-transform: uppercase; letter-spacing: .6px; color: var(--muted); font-weight: 600; }
.sig-val { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 4px; font-family: 'Space Mono', monospace; }
.sig-val.bull { background: rgba(0,200,83,.15); color: var(--bull); }
.sig-val.bear { background: rgba(255,23,68,.15); color: var(--bear); }
.sig-val.neut { background: rgba(96,125,139,.15); color: var(--neut); }
.sig-detail { font-size: 12px; color: var(--text); line-height: 1.5; }
.sig-num { font-family: 'Space Mono', monospace; color: var(--accent); }

/* Day stats bar */
.day-stats {
    display: flex; flex-wrap: wrap; gap: 8px;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 10px; padding: 14px 16px; margin-bottom: 20px;
}
.ds { flex: 1 1 80px; min-width: 70px; }
.ds small { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin-bottom: 3px; }
.ds strong { display: block; font-size: 14px; font-weight: 700; font-family: 'Space Mono', monospace; color: var(--text); }

/* Votes row */
.votes-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; }
.vote-pill {
    flex: 1 1 80px; text-align: center;
    background: var(--card); border: 1px solid var(--border);
    border-radius: 8px; padding: 10px 8px;
}
.vote-pill small { display: block; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); margin-bottom: 4px; }
.vote-count { font-size: 22px; font-weight: 700; font-family: 'Space Mono', monospace; }
.vote-count.bull { color: var(--bull); }
.vote-count.bear { color: var(--bear); }
.vote-count.neut { color: var(--neut); }

/* Note strip */
.note-strip {
    background: rgba(88,166,255,.07); border: 1px solid rgba(88,166,255,.2);
    border-radius: 8px; padding: 10px 14px;
    font-size: 11px; color: var(--muted); line-height: 1.6;
}
.note-strip strong { color: var(--accent); }

/* Spinner */
.tp-spin-wrap { display: none; text-align: center; padding: 40px; }
.tp-spinner {
    width: 36px; height: 36px; border: 3px solid var(--border);
    border-top: 3px solid var(--accent); border-radius: 50%;
    animation: spin .7s linear infinite; margin: 0 auto 14px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.tp-spin-wrap p { font-size: 12px; color: var(--muted); }

/* Error */
.tp-error {
    display: none; background: rgba(255,23,68,.06);
    border: 1px solid rgba(255,23,68,.3); border-radius: 8px;
    padding: 14px 16px; font-size: 13px; color: var(--bear); margin-bottom: 16px;
}
</style>
@endpush

<div class="tp-wrap">

    {{-- Header --}}
    <div class="tp-head">
        <div class="tp-head-left">
            <h2>// TREND PREDICTOR</h2>
            <p>Uses previous day's 1-min data → predicts next day's direction</p>
        </div>
    </div>

    {{-- Filter --}}
    <div class="tp-filter">
        <div class="fld">
            <label>Symbol</label>
            <select id="tp-symbol">
                <option value="NIFTY">NIFTY</option>
            </select>
        </div>
        <div class="fld">
            <label>Predict For Date</label>
            <input type="date" id="tp-date" value="{{ date('Y-m-d') }}">
        </div>
        <button class="btn-predict" id="btn-predict">▶ Predict</button>
    </div>

    {{-- Spinner --}}
    <div class="tp-spin-wrap" id="tp-spinner">
        <div class="tp-spinner"></div>
        <p>Analysing previous day data…</p>
    </div>

    {{-- Error --}}
    <div class="tp-error" id="tp-error"></div>

    {{-- Result --}}
    <div id="result-panel">

        {{-- Verdict --}}
        <div class="verdict-card" id="verdict-card">
            <div class="verdict-top">
                <div>
                    <div style="font-size:10px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:6px;" id="verd-label">PREDICTION FOR</div>
                    <div class="verdict-trend" id="verd-trend">—</div>
                </div>
                <div class="action-badge" id="verd-action">—</div>
            </div>
            <div class="verdict-meta" id="verd-meta"></div>
            <div class="conf-row">
                <div class="conf-bar-bg"><div class="conf-bar" id="conf-bar" style="width:0%"></div></div>
                <div class="conf-label" id="conf-label">0%</div>
            </div>
        </div>

        {{-- Prev day OHLC --}}
        <div class="day-stats" id="day-stats">
            <div class="ds"><small>Prev Day Open</small><strong id="ds-open">—</strong></div>
            <div class="ds"><small>Prev Day High</small><strong id="ds-high">—</strong></div>
            <div class="ds"><small>Prev Day Low</small><strong id="ds-low">—</strong></div>
            <div class="ds"><small>Prev Day Close</small><strong id="ds-close">—</strong></div>
            <div class="ds"><small>Close Position</small><strong id="ds-cp">—</strong></div>
        </div>

        {{-- Signal cards --}}
        <div class="signal-grid">
            {{-- Signal A --}}
            <div class="sig-card">
                <div class="sig-card-head">
                    <div class="sig-label">① Close Position</div>
                    <div class="sig-val" id="sa-badge">—</div>
                </div>
                <div class="sig-detail" id="sa-detail">—</div>
            </div>

            {{-- Signal B --}}
            <div class="sig-card">
                <div class="sig-card-head">
                    <div class="sig-label">② Last 1-Hour Price</div>
                    <div class="sig-val" id="sb-badge">—</div>
                </div>
                <div class="sig-detail" id="sb-detail">—</div>
            </div>

            {{-- Signal C --}}
            <div class="sig-card">
                <div class="sig-card-head">
                    <div class="sig-label">③ OI vs Price</div>
                    <div class="sig-val" id="sc-badge">—</div>
                </div>
                <div class="sig-detail" id="sc-detail">—</div>
            </div>

            {{-- Signal D --}}
            <div class="sig-card">
                <div class="sig-card-head">
                    <div class="sig-label">④ ATM CE vs PE OI</div>
                    <div class="sig-val" id="sd-badge">—</div>
                </div>
                <div class="sig-detail" id="sd-detail">—</div>
            </div>
        </div>

        {{-- Vote summary --}}
        <div class="votes-row">
            <div class="vote-pill">
                <small>Bullish Votes</small>
                <div class="vote-count bull" id="v-bull">0</div>
            </div>
            <div class="vote-pill">
                <small>Bearish Votes</small>
                <div class="vote-count bear" id="v-bear">0</div>
            </div>
            <div class="vote-pill">
                <small>Neutral Votes</small>
                <div class="vote-count neut" id="v-neut">0</div>
            </div>
        </div>

        {{-- Note --}}
        <div class="note-strip">
            <strong>⚠ Backtesting Note:</strong>
            This prediction is built from 4 signals (Close Position · Last-Hour Price · OI trend · ATM CE/PE OI).
            Minimum 3/4 signals required for BUY CE / BUY PE. 2/4 signals → cautious trade. All neutral → NO TRADE.
            Always test on 20+ days before live use.
        </div>

    </div>

</div>
@endsection

@push('script')
<script>
$(function () {
    loadSymbols();
    $('#btn-predict').click(runPredict);
});

function loadSymbols() {
    $.get('{{ route("trend-predictor.symbols") }}', function (r) {
        if (!r.success) return;
        const opts = r.symbols.map(s => `<option value="${s}"${s === 'NIFTY' ? ' selected' : ''}>${s}</option>`).join('');
        $('#tp-symbol').html(opts);
    });
}

function runPredict() {
    const date = $('#tp-date').val();
    const sym  = $('#tp-symbol').val();
    if (!date) { alert('Please select a date'); return; }

    $('#result-panel').hide();
    $('#tp-error').hide().text('');
    $('#tp-spinner').show();
    $('#btn-predict').prop('disabled', true);

    $.get('{{ route("trend-predictor.predict") }}', { date, symbol: sym })
        .done(function (r) {
            $('#tp-spinner').hide();
            $('#btn-predict').prop('disabled', false);

            if (!r.success) {
                $('#tp-error').text(r.message).show();
                return;
            }
            renderResult(r);
        })
        .fail(function () {
            $('#tp-spinner').hide();
            $('#btn-predict').prop('disabled', false);
            $('#tp-error').text('Server error. Please try again.').show();
        });
}

// ─── Render ───────────────────────────────────────────────────────────────

function renderResult(r) {
    // Verdict card
    const trendClass = r.trend === 'BULLISH' ? 'bull' : r.trend === 'BEARISH' ? 'bear' : 'side';
    const actionClass = r.action === 'BUY CE' ? 'buy-ce' : r.action === 'BUY PE' ? 'buy-pe' : 'no-trade';
    const trendIcon  = r.trend === 'BULLISH' ? '▲ BULLISH' : r.trend === 'BEARISH' ? '▼ BEARISH' : '→ SIDEWAYS';

    $('#verdict-card').removeClass('bull bear side').addClass(trendClass);
    $('#verd-label').text(`PREDICTION FOR ${r.pred_date} (based on ${r.prev_date})`);
    $('#verd-trend').removeClass('bull bear side').addClass(trendClass).text(trendIcon);
    $('#verd-action').removeClass('buy-ce buy-pe no-trade').addClass(actionClass).text(r.action);
    $('#verd-meta').html(`Symbol: <span>${r.symbol}</span> &nbsp;·&nbsp; Signals agreed: <span>${r.votes.BULLISH + r.votes.BEARISH > 0 ? Math.max(r.votes.BULLISH, r.votes.BEARISH) : '0'}/4</span> &nbsp;·&nbsp; Confidence: <span>${r.confidence}%</span>`);

    // Confidence bar
    const barColor = trendClass === 'bull' ? 'var(--bull)' : trendClass === 'bear' ? 'var(--bear)' : 'var(--side)';
    $('#conf-bar').css({ width: r.confidence + '%', background: barColor });
    $('#conf-label').text(r.confidence + '%');

    // Day stats
    $('#ds-open').text(r.day_open.toFixed(2));
    $('#ds-high').text(r.day_high.toFixed(2));
    $('#ds-low').text(r.day_low.toFixed(2));
    $('#ds-close').text(r.day_close.toFixed(2));
    $('#ds-cp').text((r.close_position * 100).toFixed(1) + '%');

    // Signal A — Close Position
    const cpPct = (r.close_position * 100).toFixed(1);
    const cpTxt = cpPct >= 70 ? `Close near HIGH (${cpPct}%) → Bullish carry`
                : cpPct <= 30 ? `Close near LOW (${cpPct}%) → Bearish carry`
                : `Close mid-range (${cpPct}%) → Neutral`;
    setBadge('#sa-badge', r.signal_a);
    $('#sa-detail').html(`<span class="sig-num">${cpPct}%</span> — ${cpTxt}`);

    // Signal B — Last 1-hour price
    const lhDiff = (r.last_hour_close - r.last_hour_open).toFixed(2);
    const lhDir  = lhDiff > 0 ? '↑ Rising' : lhDiff < 0 ? '↓ Falling' : '→ Flat';
    setBadge('#sb-badge', r.signal_b);
    $('#sb-detail').html(`14:30→15:29 | Open <span class="sig-num">${r.last_hour_open.toFixed(2)}</span> → Close <span class="sig-num">${r.last_hour_close.toFixed(2)}</span> | <span class="sig-num">${lhDir} (${lhDiff > 0 ? '+' : ''}${lhDiff} pts)</span>`);

    // Signal C — OI vs Price
    setBadge('#sc-badge', r.signal_c);
    const ceo = r.ce_oi_change >= 0 ? `+${r.ce_oi_change.toLocaleString()}` : r.ce_oi_change.toLocaleString();
    const peo = r.pe_oi_change >= 0 ? `+${r.pe_oi_change.toLocaleString()}` : r.pe_oi_change.toLocaleString();
    $('#sc-detail').html(`CE OI change: <span class="sig-num">${ceo}</span> | PE OI change: <span class="sig-num">${peo}</span>`);

    // Signal D — ATM CE vs PE OI
    setBadge('#sd-badge', r.signal_d);
    $('#sd-detail').html(`ATM CE OI: <span class="sig-num">${r.atm_ce_oi.toLocaleString()}</span> | ATM PE OI: <span class="sig-num">${r.atm_pe_oi.toLocaleString()}</span>`);

    // Votes
    $('#v-bull').text(r.votes.BULLISH);
    $('#v-bear').text(r.votes.BEARISH);
    $('#v-neut').text(r.votes.NEUTRAL);

    $('#result-panel').show();
}

function setBadge(sel, signal) {
    const map = { BULLISH: ['bull', '▲ BULL'], BEARISH: ['bear', '▼ BEAR'], NEUTRAL: ['neut', '→ NEUT'] };
    const [cls, txt] = map[signal] || ['neut', '— N/A'];
    $(sel).removeClass('bull bear neut').addClass(cls).text(txt);
}
</script>
@endpush