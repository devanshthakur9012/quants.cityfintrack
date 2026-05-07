@extends($activeTemplate . 'layouts.master')

@section('content')

@push('style')
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=Outfit:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<style>
:root {
    --void:       #060912;
    --bg:         #0c1017;
    --panel:      #111620;
    --card:       #141b27;
    --line:       #1c2840;
    --line-hi:    #263354;
    --txt:        #c9d8f0;
    --txt-muted:  #4a6080;
    --txt-dim:    #7a90b0;
    --accent:     #4f8ef7;
    --accent-dim: rgba(79,142,247,0.15);
    --super:      #bf5fff;
    --super-bg:   rgba(191,95,255,0.12);
    --high:       #00e5a0;
    --high-bg:    rgba(0,229,160,0.10);
    --med:        #f0c040;
    --med-bg:     rgba(240,192,64,0.10);
    --low:        #4a6080;
    --low-bg:     rgba(74,96,128,0.08);
    --trap:       #ff4560;
    --trap-bg:    rgba(255,69,96,0.10);
    --bull:       #00e5a0;
    --bear:       #ff4560;
    --morning:    #5bc4ff;
    --lateday:    #ff9f43;
    --highvol:    #bf5fff;
    --lowvol:     #4a6080;
    --mono: 'Space Mono', monospace;
    --body: 'Outfit', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { background: var(--void); color: var(--txt); font-family: var(--body); }

/* ── PAGE ─────────────────────────────────────────────────────── */
.se-page {
    min-height: 100vh;
    padding: 28px 24px 80px;
    background: radial-gradient(ellipse 120% 40% at 50% -5%, rgba(79,142,247,0.06) 0%, transparent 60%), var(--void);
}

/* ── HEADER ──────────────────────────────────────────────────── */
.se-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 16px; margin-bottom: 32px;
    padding-bottom: 24px; border-bottom: 1px solid var(--line);
}
.label-tag   { font-family: var(--mono); font-size: 0.62rem; letter-spacing: 3px; text-transform: uppercase; color: var(--accent); margin-bottom: 6px; }
.se-title    { font-size: 1.7rem; font-weight: 900; letter-spacing: -1px; line-height: 1.1; }
.se-title span { color: var(--accent); }
.se-sub      { font-size: 0.78rem; color: var(--txt-muted); margin-top: 6px; max-width: 600px; line-height: 1.6; }
.badge-live  {
    display: flex; align-items: center; gap: 8px;
    background: var(--panel); border: 1px solid var(--line); border-radius: 24px;
    padding: 6px 16px; font-family: var(--mono); font-size: 0.68rem; color: var(--txt-dim); white-space: nowrap;
}
.dot-live { width: 8px; height: 8px; border-radius: 50%; background: var(--high); animation: blink 2s ease-in-out infinite; }
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.2} }

/* ── FILTER BAR ──────────────────────────────────────────────── */
.se-filters {
    background: var(--panel); border: 1px solid var(--line); border-radius: 12px;
    padding: 20px 22px; margin-bottom: 24px;
    display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end;
}
.fg { display: flex; flex-direction: column; gap: 6px; }
.fg label { font-size: 0.62rem; letter-spacing: 2px; text-transform: uppercase; color: var(--txt-muted); font-family: var(--mono); }
.fc {
    background: var(--card); border: 1px solid var(--line); border-radius: 7px;
    color: var(--txt); padding: 9px 12px; font-size: 0.82rem; font-family: var(--body);
    outline: none; transition: border-color 0.2s; min-width: 110px;
}
.fc:focus { border-color: var(--accent); }
.fc option { background: var(--card); }
.btn-run {
    background: var(--accent); color: #fff; border: none; border-radius: 8px;
    padding: 9px 26px; font-size: 0.84rem; font-weight: 700; cursor: pointer;
    transition: opacity 0.2s; white-space: nowrap;
}
.btn-run:hover { opacity: 0.85; }
.btn-back {
    display: inline-flex; align-items: center; gap: 8px;
    background: var(--panel); border: 1px solid var(--line); border-radius: 7px;
    color: var(--txt-dim); padding: 8px 18px; font-size: 0.8rem;
    text-decoration: none; transition: all 0.2s;
}
.btn-back:hover { border-color: var(--accent); color: var(--accent); }
.thresh-toggle { font-size: 0.72rem; color: var(--accent); cursor: pointer; text-decoration: underline; align-self: flex-end; padding-bottom: 10px; white-space: nowrap; }
.thresh-grid   { width: 100%; display: none; grid-template-columns: repeat(auto-fill, minmax(175px, 1fr)); gap: 12px; padding-top: 12px; border-top: 1px solid var(--line); margin-top: 8px; }
.thresh-grid.open { display: grid; }

/* ── STATS ROW ───────────────────────────────────────────────── */
.stats-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 22px; }
.stat-card {
    background: var(--panel); border: 1px solid var(--line); border-radius: 10px;
    padding: 14px 20px; text-align: center; min-width: 85px; flex: 1;
}
.stat-card .n { font-family: var(--mono); font-size: 1.5rem; font-weight: 700; line-height: 1; }
.stat-card .l { font-size: 0.6rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--txt-muted); margin-top: 5px; }
.stat-card.super .n { color: var(--super); }
.stat-card.high  .n { color: var(--high);  }
.stat-card.med   .n { color: var(--med);   }
.stat-card.trap  .n { color: var(--trap);  }
.stat-card.total .n { color: var(--accent);}

/* ── VOL REGIME BADGE ────────────────────────────────────────── */
.regime-badge { font-size: 0.62rem; font-family: var(--mono); font-weight: 700; padding: 2px 7px; border-radius: 3px; }
.regime-badge.HIGH_VOL  { background: rgba(191,95,255,0.12); color: var(--highvol); border: 1px solid rgba(191,95,255,0.3); }
.regime-badge.LOW_VOL   { background: rgba(74,96,128,0.12);  color: var(--lowvol);  border: 1px solid rgba(74,96,128,0.3); }
.regime-badge.NORMAL    { background: rgba(79,142,247,0.08); color: var(--accent);  border: 1px solid rgba(79,142,247,0.2); }

/* ── SESSION PHASE BADGE ─────────────────────────────────────── */
.phase-badge { font-size: 0.61rem; font-family: var(--mono); font-weight: 700; padding: 2px 6px; border-radius: 3px; }
.phase-badge.MORNING  { background: rgba(91,196,255,0.12); color: var(--morning); }
.phase-badge.MIDDAY   { background: rgba(79,142,247,0.08); color: var(--txt-muted); }
.phase-badge.LATEDAY  { background: rgba(255,159,67,0.12); color: var(--lateday); }

/* ── BADGES ──────────────────────────────────────────────────── */
.score-badge {
    display: inline-flex; align-items: center;
    font-family: var(--mono); font-weight: 700; font-size: 0.68rem;
    padding: 3px 9px; border-radius: 5px; min-width: 56px; justify-content: center;
}
.score-badge.SUPER  { background: var(--super-bg); color: var(--super); border: 1px solid rgba(191,95,255,0.3); box-shadow: 0 0 12px rgba(191,95,255,0.15); }
.score-badge.HIGH   { background: var(--high-bg);  color: var(--high);  border: 1px solid rgba(0,229,160,0.25);  box-shadow: 0 0 10px rgba(0,229,160,0.1); }
.score-badge.MEDIUM { background: var(--med-bg);   color: var(--med);   border: 1px solid rgba(240,192,64,0.25); }
.score-badge.LOW    { background: var(--low-bg);   color: var(--low);   border: 1px solid rgba(74,96,128,0.2);   }
.score-badge.TRAP   { background: var(--trap-bg);  color: var(--trap);  border: 1px solid rgba(255,69,96,0.25);  }

.oi-badge { font-size: 0.65rem; font-family: var(--mono); padding: 2px 7px; border-radius: 3px; }
.oi-badge.LONG_BUILDUP  { background: rgba(0,229,160,0.1);  color: var(--bull); }
.oi-badge.SHORT_BUILDUP { background: rgba(255,69,96,0.1);  color: var(--bear); }
.oi-badge.SHORT_COVER   { background: rgba(0,229,160,0.06); color: #6bdcb4; }
.oi-badge.LONG_UNWIND   { background: rgba(255,69,96,0.06); color: #f48c9f; }
.oi-badge.NEUTRAL       { background: rgba(74,96,128,0.1);  color: var(--txt-muted); }

.trend-badge { font-size: 0.63rem; font-family: var(--mono); font-weight: 700; padding: 2px 6px; border-radius: 3px; }
.trend-badge.UP   { background: rgba(0,229,160,0.1);  color: var(--bull); }
.trend-badge.DOWN { background: rgba(255,69,96,0.1);  color: var(--bear); }
.trend-badge.FLAT { background: rgba(74,96,128,0.1);  color: var(--txt-muted); }

.bp      { font-size: 0.6rem; font-family: var(--mono); padding: 2px 6px; border-radius: 3px; background: var(--accent-dim); color: var(--accent); }
.bp.warn { background: rgba(255,69,96,0.12); color: var(--trap); }
.bp.ok   { background: rgba(0,229,160,0.1);  color: var(--bull); }
.bp.morn { background: rgba(91,196,255,0.12); color: var(--morning); }
.bp.late { background: rgba(255,159,67,0.12); color: var(--lateday); }
.bp.vup  { background: rgba(191,95,255,0.1);  color: var(--highvol); }
.bp.vdn  { background: rgba(74,96,128,0.1);   color: var(--lowvol);  }
.breakdown-pills { display: flex; flex-wrap: wrap; gap: 4px; max-width: 360px; }

/* ── MISC ────────────────────────────────────────────────────── */
.num-mono { font-family: var(--mono); font-size: 0.75rem; color: var(--txt-dim); }
.pcr-val  { font-family: var(--mono); font-size: 0.73rem; }
.pcr-up   { color: var(--bull); }
.pcr-dn   { color: var(--bear); }
.pcr-n    { color: var(--txt-muted); }
.fwd-pos  { color: var(--bull); font-family: var(--mono); font-size: 0.7rem; font-weight: 600; }
.fwd-neg  { color: var(--bear); font-family: var(--mono); font-size: 0.7rem; font-weight: 600; }
.fwd-na   { color: var(--txt-muted); font-size: 0.7rem; }
.comp-tag { font-size: 0.6rem; font-family: var(--mono); color: var(--med); font-weight: 700; }

.score-bar-wrap { display: flex; align-items: center; gap: 8px; }
.score-num      { font-family: var(--mono); font-size: 0.75rem; font-weight: 700; min-width: 20px; text-align: right; }
.score-bar      { height: 4px; border-radius: 2px; background: var(--line); flex: 1; min-width: 50px; overflow: hidden; }
.score-bar-fill { height: 100%; border-radius: 2px; }

.sym-link { text-decoration: none; font-weight: 800; font-size: 0.9rem; color: var(--txt); transition: color 0.15s; }
.sym-link:hover { color: var(--accent); }
.time-sub  { font-family: var(--mono); font-size: 0.65rem; color: var(--txt-muted); margin-top: 2px; }
.time-chip { font-family: var(--mono); font-size: 0.72rem; font-weight: 700; color: #5b9cf6; }

/* ── OVERVIEW TABLE ──────────────────────────────────────────── */
.tbl-wrap { background: var(--card); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
.ov-tbl   { width: 100%; border-collapse: collapse; font-size: 0.78rem; }
.ov-tbl thead tr { background: var(--panel); border-bottom: 1px solid var(--line-hi); }
.ov-tbl th { padding: 11px 13px; font-size: 0.58rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--txt-muted); font-family: var(--mono); text-align: center; white-space: nowrap; }
.ov-tbl th.L { text-align: left; }
.ov-tbl tbody tr { border-bottom: 1px solid var(--line); transition: background 0.15s; cursor: pointer; }
.ov-tbl tbody tr:hover { background: rgba(79,142,247,0.04); }
.ov-tbl tbody tr:last-child { border-bottom: none; }
.ov-tbl td { padding: 9px 13px; text-align: center; white-space: nowrap; }
.ov-tbl td.L { text-align: left; }

/* ── TIERED BACKTEST TABLE ─────────────────────────────────────── */
.bt-wrap  { margin-bottom: 14px; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
.bt-table { width: 100%; border-collapse: collapse; font-size: 0.74rem; }
.bt-table thead tr { background: var(--card); border-bottom: 1px solid var(--line-hi); }
.bt-table th { padding: 9px 14px; font-size: 0.58rem; text-transform: uppercase; letter-spacing: 1.5px; color: var(--txt-muted); font-family: var(--mono); text-align: center; white-space: nowrap; }
.bt-table tbody tr { border-bottom: 1px solid var(--line); }
.bt-table tbody tr:last-child { border-bottom: none; }
.bt-table td { padding: 9px 14px; text-align: center; font-family: var(--mono); font-size: 0.72rem; }
.bt-table tr.t-super td.tier-lbl { color: var(--super); font-weight: 700; }
.bt-table tr.t-high  td.tier-lbl { color: var(--high);  font-weight: 700; }
.bt-table tr.t-med   td.tier-lbl { color: var(--med);   font-weight: 700; }
.bt-table tr.t-low   td.tier-lbl { color: var(--low);   font-weight: 700; }

.bt-verdict { margin-bottom: 14px; padding: 10px 16px; background: var(--panel); border: 1px solid var(--line); border-radius: 8px; font-size: 0.72rem; color: var(--txt-muted); line-height: 1.9; }
.bt-verdict strong { color: var(--txt-dim); }

/* ── DETAIL TABLE ────────────────────────────────────────────── */
.dt-wrap { background: var(--card); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
.dt-tbl  { width: 100%; border-collapse: collapse; font-size: 0.74rem; }
.dt-tbl thead tr { background: var(--panel); border-bottom: 1px solid var(--line-hi); }
.dt-tbl th { padding: 10px 11px; font-size: 0.57rem; letter-spacing: 1.5px; text-transform: uppercase; color: var(--txt-muted); font-family: var(--mono); text-align: center; white-space: nowrap; }
.dt-tbl th.L { text-align: left; }
.dt-tbl tbody tr { border-bottom: 1px solid rgba(28,40,64,0.5); transition: background 0.12s; }
.dt-tbl tbody tr:hover { background: var(--panel); }
.dt-tbl tbody tr.r-super { background: rgba(191,95,255,0.04); border-left: 2px solid var(--super); }
.dt-tbl tbody tr.r-high  { background: rgba(0,229,160,0.03);  border-left: 2px solid var(--high); }
.dt-tbl tbody tr.r-trap  { background: rgba(255,69,96,0.03);  border-left: 2px solid var(--trap); }
.dt-tbl tbody tr.r-last  { background: rgba(79,142,247,0.04); border-top: 1px solid var(--line-hi); }
.dt-tbl tbody tr:last-child { border-bottom: none; }
.dt-tbl td { padding: 7px 11px; text-align: center; white-space: nowrap; }
.dt-tbl td.L { text-align: left; }

/* ── DETAIL HEADER ───────────────────────────────────────────── */
.detail-hdr { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; }
.detail-title { font-size: 1.2rem; font-weight: 900; }
.detail-title span { color: var(--accent); }

/* ── LEGEND ──────────────────────────────────────────────────── */
.legend-box { margin-top: 16px; padding: 14px 18px; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; font-size: 0.72rem; color: var(--txt-muted); line-height: 2.1; }
.legend-box strong { color: var(--txt-dim); }
.lg-s { color: var(--super); font-weight: 700; }
.lg-h { color: var(--high);  font-weight: 700; }
.lg-m { color: var(--med);   font-weight: 700; }
.lg-t { color: var(--trap);  font-weight: 700; }

.empty-state { text-align: center; padding: 60px 24px; color: var(--txt-muted); }
.empty-state h4 { font-size: 1rem; margin-bottom: 8px; color: var(--txt-dim); }

@media(max-width:768px){
    .ov-tbl th,.ov-tbl td { padding: 6px 8px; }
    .dt-tbl th,.dt-tbl td { padding: 5px 7px; }
    .se-page { padding: 16px 12px 60px; }
}
</style>
@endpush

<div class="se-page">
<div style="max-width:1900px; margin:0 auto;">

{{-- ══ HEADER ══════════════════════════════════════════════════════════════ --}}
<div class="se-header">
    <div>
        <div class="label-tag">// Smart Money Detection — v3</div>
        <div class="se-title">Signal <span>Engine</span></div>
        <div class="se-sub">
            Multi-candle compression accumulation · CLOSE-based breakouts (no range double-count) ·
            SMA slope trend · Rolling PCR array · Genuine OI-spike trap · MFE win rate ·
            Volatility regime filter · Session phase weighting.
        </div>
    </div>
    <div class="badge-live">
        <span class="dot-live"></span>
        {{ $selectedDate ? \Carbon\Carbon::parse($selectedDate)->format('d M Y') : '—' }}
    </div>
</div>

{{-- ══ FILTER BAR ══════════════════════════════════════════════════════════ --}}
<form method="GET" action="{{ request()->url() }}">
<div class="se-filters">

    <div class="fg">
        <label>Trade Date</label>
        <select name="trade_date" class="fc">
            @foreach($availableDates as $d)
                <option value="{{ $d }}" {{ $selectedDate == $d ? 'selected' : '' }}>
                    {{ \Carbon\Carbon::parse($d)->format('d M Y') }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="fg">
        <label>Symbol</label>
        <select name="symbol" class="fc" style="min-width:155px;">
            <option value="">All Symbols (Overview)</option>
            @foreach($availableSymbols as $sym)
                <option value="{{ $sym }}" {{ $selectedSymbol == $sym ? 'selected' : '' }}>{{ $sym }}</option>
            @endforeach
        </select>
    </div>

    <div class="fg">
        <label>Lookback</label>
        <input type="number" name="lookback" class="fc" value="{{ $params['lookback'] }}" min="2" max="20" style="width:80px;">
    </div>

    <div class="fg">
        <label>Fwd Lookahead</label>
        <input type="number" name="forward_lookahead" class="fc" value="{{ $params['forward_lookahead'] }}" min="1" max="10" style="width:80px;" title="MFE / MAE window (candles ahead)">
    </div>

    <div class="fg">
        <label>Super Score ≥</label>
        <input type="number" name="super_high_score" class="fc" value="{{ $params['super_high_score'] }}" min="1" max="25" style="width:80px;">
    </div>

    <div class="fg">
        <label>High Score ≥</label>
        <input type="number" name="high_score" class="fc" value="{{ $params['high_score'] }}" min="1" max="20" style="width:80px;">
    </div>

    <span class="thresh-toggle" onclick="document.getElementById('advT').classList.toggle('open')">⚙ Advanced Thresholds</span>

    <button type="submit" class="btn-run">▶ Run Engine</button>

    @if($selectedSymbol)
        <a href="{{ request()->url() }}?trade_date={{ $selectedDate }}&{{ http_build_query($params) }}" class="btn-back">← All Symbols</a>
    @endif

    {{-- Advanced threshold grid --}}
    <div class="thresh-grid" id="advT">
        <div class="fg">
            <label>Price Flat % ≤</label>
            <input type="number" step="0.1" name="price_flat_pct" class="fc" value="{{ $params['price_flat_pct'] }}" title="Max price % change = flat for multi-candle accumulation">
        </div>
        <div class="fg">
            <label>OI Min Rise % ≥</label>
            <input type="number" step="0.5" name="oi_min_pct" class="fc" value="{{ $params['oi_min_pct'] }}" title="Min OI % rise for accumulation + trap signals">
        </div>
        <div class="fg">
            <label>Vol × Avg (accum)</label>
            <input type="number" step="0.1" name="vol_multiplier" class="fc" value="{{ $params['vol_multiplier'] }}" title="Vol must exceed N× avg for accumulation + breakout confirm">
        </div>
        <div class="fg">
            <label>Compression Ratio</label>
            <input type="number" step="0.05" name="compression_ratio" class="fc" value="{{ $params['compression_ratio'] }}" title="FIX-A: All 3 candles must have range below N× avg for ACCUM. Single narrow candle not enough.">
        </div>
        <div class="fg">
            <label>OI Spike × Avg</label>
            <input type="number" step="0.1" name="oi_spike_multiplier" class="fc" value="{{ $params['oi_spike_multiplier'] }}" title="FIX-E: Also used for trap detection — prev OI must spike vs avg, not just % change">
        </div>
        <div class="fg">
            <label>Vol Spike × Avg</label>
            <input type="number" step="0.1" name="vol_spike_multiplier" class="fc" value="{{ $params['vol_spike_multiplier'] }}">
        </div>
        <div class="fg">
            <label>Range Expand × Avg</label>
            <input type="number" step="0.1" name="range_expand_multiplier" class="fc" value="{{ $params['range_expand_multiplier'] }}" title="FIX-B: Range expand NOT added on breakout candles to prevent double-counting">
        </div>
        <div class="fg">
            <label>PCR Shift % ≥</label>
            <input type="number" step="1" name="pcr_shift_pct" class="fc" value="{{ $params['pcr_shift_pct'] }}" title="FIX-C: PCR change now via rolling history array, not time-map re-lookup">
        </div>
        <div class="fg">
            <label>Trap Spike % (prev)</label>
            <input type="number" step="0.1" name="trap_price_spike_pct" class="fc" value="{{ $params['trap_price_spike_pct'] }}" title="FIX-E: Prev candle price spike %. Also requires prev OI spike vs avg (not just % change).">
        </div>
        <div class="fg">
            <label>Trend SMA Period</label>
            <input type="number" name="trend_period" class="fc" value="{{ $params['trend_period'] }}" min="3" max="30" title="FIX-D: SMA SLOPE (3 consecutive SMA values) — not just price vs SMA">
        </div>
        <div class="fg">
            <label>Morning Cutoff</label>
            <input type="text" name="morning_cutoff" class="fc" value="{{ $params['morning_cutoff'] }}" placeholder="10:30" title="FIX-H: Candles before this time get +1 morning bonus">
        </div>
        <div class="fg">
            <label>Late-Day Cutoff</label>
            <input type="text" name="lateday_cutoff" class="fc" value="{{ $params['lateday_cutoff'] }}" placeholder="14:30" title="FIX-H: Candles at or after this time get -1 late-day penalty">
        </div>
        <div class="fg">
            <label>MFE Win Threshold %</label>
            <input type="number" step="0.05" name="mfe_win_threshold" class="fc" value="{{ $params['mfe_win_threshold'] }}" title="FIX-F: MFE must reach this % for MFE win rate calculation">
        </div>
        <div class="fg">
            <label>Med Score ≥</label>
            <input type="number" name="medium_score" class="fc" value="{{ $params['medium_score'] }}">
        </div>
        <div class="fg">
            <label>Trap Score ≤</label>
            <input type="number" name="trap_score" class="fc" value="{{ $params['trap_score'] }}">
        </div>
    </div>

</div>
</form>

{{-- ══ NO DATA ══════════════════════════════════════════════════════════════ --}}
@if(!$selectedDate)
    <div class="empty-state"><h4>No Data</h4><p>No option OHLC data found. Run the live collector first.</p></div>

{{-- ══ OVERVIEW MODE ════════════════════════════════════════════════════════ --}}
@elseif(!$detailMode)

    <div class="stats-row">
        <div class="stat-card total"><div class="n">{{ $summaryStats['total']  }}</div><div class="l">Symbols</div></div>
        <div class="stat-card super"><div class="n">{{ $summaryStats['super']  }}</div><div class="l">Super ≥{{ $params['super_high_score'] }}</div></div>
        <div class="stat-card high" ><div class="n">{{ $summaryStats['high']   }}</div><div class="l">High ≥{{ $params['high_score'] }}</div></div>
        <div class="stat-card med"  ><div class="n">{{ $summaryStats['medium'] }}</div><div class="l">Medium</div></div>
        <div class="stat-card"      ><div class="n" style="color:var(--low);">{{ $summaryStats['low'] }}</div><div class="l">Low</div></div>
        <div class="stat-card trap" ><div class="n">{{ $summaryStats['trap']   }}</div><div class="l">Trap</div></div>
    </div>

    @if($rows->isEmpty())
        <div class="empty-state"><h4>No FUT data for {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}</h4><p>Make sure the live collector ran for this day.</p></div>
    @else

    <div class="tbl-wrap"><div style="overflow-x:auto;">
    <table class="ov-tbl">
        <thead>
            <tr>
                <th class="L" style="width:34px;">#</th>
                <th class="L" style="min-width:130px;">Symbol</th>
                <th style="min-width:100px;">Score</th>
                <th>Signal</th>
                <th title="FIX-D: SMA slope trend">Trend</th>
                <th title="FIX-G: Volatility regime">Vol Regime</th>
                <th>OI Class</th>
                <th>FUT Close</th>
                <th>FUT OI</th>
                <th>FUT Vol</th>
                <th>PCR</th>
                <th>PCR Δ3c</th>
                <th>Candles</th>
                <th title="Candles today scoring ≥ high_score threshold">Hi Zones</th>
                <th class="L">Breakdown (last candle)</th>
            </tr>
        </thead>
        <tbody>
        @foreach($rows as $idx => $row)
        @php
            $maxScore  = 20;
            $barPct    = min(100, round(max(0, $row['score']) / $maxScore * 100));
            $barColor  = match($row['score_label']) {
                'SUPER'  => 'var(--super)', 'HIGH'   => 'var(--high)',
                'MEDIUM' => 'var(--med)',   'TRAP'   => 'var(--trap)',
                default  => 'var(--low)',
            };
            $detailUrl = request()->url().'?trade_date='.$selectedDate.'&symbol='.$row['symbol'].'&'.http_build_query($params);
        @endphp
        <tr onclick="window.location='{{ $detailUrl }}'">

            <td class="L" style="color:var(--txt-muted);font-size:0.68rem;">{{ $idx+1 }}</td>

            <td class="L">
                <a href="{{ $detailUrl }}" class="sym-link" onclick="event.stopPropagation()">{{ $row['symbol'] }}</a>
                <div class="time-sub">{{ $row['last_time'] ?? '—' }}</div>
            </td>

            <td>
                <div class="score-bar-wrap">
                    <div class="score-bar"><div class="score-bar-fill" style="width:{{ $barPct }}%;background:{{ $barColor }};"></div></div>
                    <span class="score-num" style="color:{{ $barColor }};">{{ $row['score'] }}</span>
                </div>
            </td>

            <td><span class="score-badge {{ $row['score_label'] }}">{{ $row['score_label'] }}</span></td>

            {{-- FIX-D: Slope-based trend --}}
            <td><span class="trend-badge {{ $row['trend'] ?? 'FLAT' }}">{{ $row['trend'] ?? '—' }}</span></td>

            {{-- FIX-G: Vol regime --}}
            <td><span class="regime-badge {{ $row['vol_regime'] ?? 'NORMAL' }}">{{ str_replace('_', ' ', $row['vol_regime'] ?? 'NORMAL') }}</span></td>

            <td><span class="oi-badge {{ $row['oi_class'] }}">{{ str_replace('_', ' ', $row['oi_class']) }}</span></td>

            <td class="num-mono">₹{{ number_format($row['fut_close'], 2) }}</td>
            <td class="num-mono">{{ number_format($row['fut_oi']) }}</td>
            <td class="num-mono">{{ number_format($row['fut_vol']) }}</td>

            <td>
                @if($row['pcr'] !== null)
                    @php $pc = $row['pcr'] > 1.2 ? 'pcr-up' : ($row['pcr'] < 0.8 ? 'pcr-dn' : 'pcr-n'); @endphp
                    <span class="pcr-val {{ $pc }}">{{ number_format($row['pcr'], 2) }}</span>
                @else<span class="fwd-na">—</span>@endif
            </td>

            <td>
                @if($row['pcr_change'] !== null)
                    @php $pcc = $row['pcr_change']; @endphp
                    <span class="{{ $pcc > 0 ? 'fwd-pos' : 'fwd-neg' }}">{{ $pcc > 0 ? '+' : '' }}{{ number_format($pcc, 1) }}%</span>
                @else<span class="fwd-na">—</span>@endif
            </td>

            <td class="num-mono">{{ $row['candles'] }}</td>

            <td>
                @if($row['high_score_candles'] > 0)
                    <span style="color:var(--high);font-family:var(--mono);font-weight:700;">{{ $row['high_score_candles'] }}</span>
                @else<span class="fwd-na">0</span>@endif
            </td>

            <td class="L">
                <div class="breakdown-pills">
                    @php
                        $shortLabels = [
                            'silent_accumulation' => 'ACCUM', 'long_buildup'  => 'LNG-BLD',
                            'short_buildup'       => 'SHT-BLD','short_cover'  => 'SHT-COV',
                            'long_unwind'         => 'LNG-UNW','oi_spike'     => 'OI-SPK',
                            'volume_spike'        => 'VOL-SPK','pcr_shift'    => 'PCR-SHF',
                            'breakout'            => 'BO',     'range_expand' => 'RNG-EXP',
                        ];
                        $hasAny = false;
                    @endphp
                    @foreach($row['breakdown'] as $bk => $bv)
                        @if($bk === 'trap_warning')
                            <span class="bp warn">⚠ TRAP</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'trend_align')
                            <span class="bp ok">↑ TREND {{ is_string($bv) ? $bv : '' }}</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'trend_against')
                            <span class="bp warn">↕ CTRD</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'morning_bonus')
                            <span class="bp morn">☀ AM+1</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'lateday_penalty')
                            <span class="bp late">🌙 PM-1</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'vol_regime_boost')
                            <span class="bp vup">⚡ HVOL+1</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'vol_regime_cut')
                            <span class="bp vdn">↓ LVOL-1</span>@php $hasAny = true; @endphp
                        @elseif(!in_array($bk, ['oi_class', 'trend', 'session_phase']))
                            <span class="bp">{{ $shortLabels[$bk] ?? strtoupper($bk) }}{{ is_string($bv) ? ' '.$bv : '' }}</span>@php $hasAny = true; @endphp
                        @endif
                    @endforeach
                    @if(!$hasAny)<span class="fwd-na" style="font-size:0.65rem;">—</span>@endif
                </div>
            </td>

        </tr>
        @endforeach
        </tbody>
    </table>
    </div></div>

    <div class="legend-box">
        <strong>Score tiers →</strong>
        <span class="lg-s">SUPER ≥{{ $params['super_high_score'] }}</span> |
        <span class="lg-h">HIGH ≥{{ $params['high_score'] }}</span> |
        <span class="lg-m">MEDIUM ≥{{ $params['medium_score'] }}</span> |
        <span class="lg-t">TRAP ≤{{ $params['trap_score'] }}</span><br>

        <strong>FIX-A — ACCUM</strong>: ALL 3 consecutive candles must be narrow (below {{ $params['compression_ratio'] }}× avg range). Single-candle squeeze no longer qualifies. &nbsp;·&nbsp;
        <strong>FIX-B — BO</strong>: CLOSE breaks prev 3c high/low. Range Expand score NOT added on same candle (prevents inflation).<br>

        <strong>FIX-C — PCR</strong>: Rolling array lookback (not time-map re-index). Safe with missing intervals. &nbsp;·&nbsp;
        <strong>FIX-D — Trend</strong>: SMA slope (3 consecutive SMA values must agree). Flat markets with drift no longer trigger UP/DOWN.<br>

        <strong>FIX-E — ⚠ TRAP</strong>: Prev OI validated vs avgOIChange (genuine institutional spike required, not just % change). &nbsp;·&nbsp;
        <strong>FIX-G — Vol Regime</strong>: HIGH VOL = breakout +1 · LOW VOL = spike signals −1.<br>

        <strong>FIX-H — Session</strong>: ☀ AM+1 = morning bonus (before {{ $params['morning_cutoff'] }}) · 🌙 PM-1 = late-day penalty (after {{ $params['lateday_cutoff'] }}). &nbsp;·&nbsp;
        Click any row → candle breakdown + MFE/MAE tiered backtest
    </div>

    @endif

{{-- ══ DETAIL MODE ══════════════════════════════════════════════════════════ --}}
@else

    @php $volRegime = $detailData['vol_regime'] ?? 'NORMAL'; @endphp

    <div class="detail-hdr">
        <a href="{{ request()->url() }}?trade_date={{ $selectedDate }}&{{ http_build_query($params) }}" class="btn-back">← All Symbols</a>
        <div>
            <div class="detail-title">{{ $selectedSymbol }} — <span>Candle Breakdown</span></div>
            <div class="time-sub">
                {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}
                &nbsp;·&nbsp; {{ $detailData['total_candles'] ?? 0 }} candles
                &nbsp;·&nbsp; MFE/MAE window: {{ $params['forward_lookahead'] }}c
                &nbsp;·&nbsp; Trend SMA slope: {{ $params['trend_period'] }}
                &nbsp;·&nbsp; Vol Regime: <span class="regime-badge {{ $volRegime }}" style="font-size:0.65rem;">{{ str_replace('_',' ',$volRegime) }}</span>
            </div>
        </div>
    </div>

    {{-- ── TIERED BACKTEST TABLE (FIX-F: MFE win rate added) ──────────── --}}
    @if(!empty($detailData['backtest']['tiers']))
    @php
        $tiers = $detailData['backtest']['tiers'];
        $fwdN  = $detailData['backtest']['forward_n'] ?? $params['forward_lookahead'];
    @endphp

    <div class="bt-wrap"><div style="overflow-x:auto;">
    <table class="bt-table">
        <thead>
            <tr>
                <th>Tier</th>
                <th>Candles</th>
                <th>Avg +1C Ret%</th>
                <th>Avg +3C Ret%</th>
                <th>Avg MFE +{{ $fwdN }}C</th>
                <th>Avg MAE +{{ $fwdN }}C</th>
                <th>Avg Exp Ratio</th>
                <th>Win Rate +1C</th>
                <th>Win Rate +3C</th>
                <th title="FIX-F: % candles where MFE ≥ {{ $params['mfe_win_threshold'] }}% — better than close-to-close">MFE Win%</th>
                <th>Strong Moves</th>
                <th title="FIX-H: Phase split">AM / PM</th>
                <th>Verdict</th>
            </tr>
        </thead>
        <tbody>
        @foreach(['SUPER','HIGH','MEDIUM','LOW'] as $tier)
        @php
            $t = $tiers[$tier] ?? null;
            if (!$t || $t['count'] === 0) continue;
            $er       = $t['avg_expansion'] ?? 0;
            $mfeWin   = $t['mfe_win_rate'] ?? 0;
            $verdict  = $er >= 1.5 ? ['WORKING',  'var(--high)']
                      : ($er >= 1.0 ? ['MARGINAL', 'var(--med)']
                      : ($er > 0    ? ['ADJUST',   'var(--trap)']
                                    : ['NO DATA',  'var(--txt-muted)']));
            $rowClass = ['SUPER'=>'t-super','HIGH'=>'t-high','MEDIUM'=>'t-med','LOW'=>'t-low'][$tier] ?? '';
        @endphp
        <tr class="{{ $rowClass }}">
            <td class="tier-lbl"><span class="score-badge {{ $tier }}">{{ $tier }}</span></td>
            <td>{{ $t['count'] }}</td>
            <td>
                @if($t['avg_fwd_1'] !== null)
                    <span class="{{ $t['avg_fwd_1'] >= 0 ? 'fwd-pos' : 'fwd-neg' }}">{{ $t['avg_fwd_1'] >= 0 ? '+' : '' }}{{ $t['avg_fwd_1'] }}%</span>
                @else<span class="fwd-na">—</span>@endif
            </td>
            <td>
                @if($t['avg_fwd_3'] !== null)
                    <span class="{{ $t['avg_fwd_3'] >= 0 ? 'fwd-pos' : 'fwd-neg' }}">{{ $t['avg_fwd_3'] >= 0 ? '+' : '' }}{{ $t['avg_fwd_3'] }}%</span>
                @else<span class="fwd-na">—</span>@endif
            </td>
            <td>@if($t['avg_mfe'] !== null)<span class="fwd-pos">+{{ $t['avg_mfe'] }}%</span>@else<span class="fwd-na">—</span>@endif</td>
            <td>@if($t['avg_mae'] !== null)<span class="fwd-neg">{{ $t['avg_mae'] }}%</span>@else<span class="fwd-na">—</span>@endif</td>
            <td>
                <span style="font-family:var(--mono);font-weight:700;color:{{ $er >= 1.5 ? 'var(--high)' : ($er >= 1.0 ? 'var(--med)' : 'var(--bear)') }};">
                    {{ $er }}x
                </span>
            </td>
            <td><span class="{{ $t['win_rate_1'] >= 50 ? 'fwd-pos' : 'fwd-neg' }}">{{ $t['win_rate_1'] }}%</span></td>
            <td><span class="{{ $t['win_rate_3'] >= 50 ? 'fwd-pos' : 'fwd-neg' }}">{{ $t['win_rate_3'] }}%</span></td>
            {{-- FIX-F: MFE-based win rate --}}
            <td>
                <span class="{{ $mfeWin >= 50 ? 'fwd-pos' : 'fwd-neg' }}" title="MFE ≥ {{ $params['mfe_win_threshold'] }}% — price actually moved in our favour">{{ $mfeWin }}%</span>
            </td>
            <td><span style="color:var(--high);font-family:var(--mono);">{{ $t['strong_moves'] }}</span></td>
            {{-- FIX-H: Morning / Late-day split --}}
            <td>
                <span style="color:var(--morning);font-family:var(--mono);font-size:0.68rem;">{{ $t['morning_count'] ?? 0 }}</span>
                <span style="color:var(--txt-muted);font-family:var(--mono);font-size:0.65rem;"> / </span>
                <span style="color:var(--lateday);font-family:var(--mono);font-size:0.68rem;">{{ $t['lateday_count'] ?? 0 }}</span>
            </td>
            <td><span style="font-size:0.68rem;font-weight:700;color:{{ $verdict[1] }};">{{ $verdict[0] }}</span></td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div></div>

    <div class="bt-verdict">
        <strong>How to read (v3):</strong>
        SUPER tier should outperform HIGH, which should outperform MEDIUM.
        <strong>MFE Win%</strong> (FIX-F) = % of candles where price moved ≥ {{ $params['mfe_win_threshold'] }}% in your favour
        — more reliable than close-to-close which misses intra-candle moves. &nbsp;·&nbsp;
        <strong>AM / PM</strong> (FIX-H) = morning vs late-day signal count per tier; expect AM signals to outperform statistically. &nbsp;&nbsp;
        <strong style="color:var(--trap);">⚠ FIX 10: Minimum 30 trading days required for statistical confidence.
        1-day backtest = anecdote, not evidence.</strong>
    </div>
    @endif

    {{-- ── CANDLE TABLE ────────────────────────────────────────── --}}
    @if(empty($detailData['candles']))
        <div class="empty-state"><h4>No candle data for this symbol / date.</h4></div>
    @else

    <div class="dt-wrap"><div style="overflow-x:auto;">
    <table class="dt-tbl">
        <thead>
            <tr>
                <th style="width:30px;">#</th>
                <th>Time</th>
                <th title="FIX-H: Session phase">Phase</th>
                <th>Close</th>
                <th>Price Δ%</th>
                <th>OI</th>
                <th>OI Δ%</th>
                <th>Vol</th>
                <th title="Volume ÷ rolling avg">Vol×</th>
                <th title="Range ÷ rolling avg range">Rng×</th>
                <th title="FIX-A: All 3 candles compressed?">Cmp3</th>
                <th title="FIX-D: SMA slope trend">Trend</th>
                <th>OI Class</th>
                <th title="FIX-C: PCR via rolling array">PCR</th>
                <th>Score</th>
                <th>Signal</th>
                <th title="Close-to-close: next 1 candle">+1C%</th>
                <th title="Close-to-close: next 3 candles">+3C%</th>
                <th title="Best HIGH in next N candles">MFE</th>
                <th title="Worst LOW in next N candles">MAE</th>
                <th title="MFE ÷ MAE — >1.5 = good R:R">ExpR</th>
                <th>Move</th>
                <th class="L">Breakdown</th>
            </tr>
        </thead>
        <tbody>
        @php
            $cArr  = $detailData['candles'];
            $total = count($cArr);
            $shortLabels = [
                'silent_accumulation' => 'ACCUM',   'long_buildup'   => 'LNG-BLD',
                'short_buildup'       => 'SHT-BLD', 'short_cover'    => 'SHT-COV',
                'long_unwind'         => 'LNG-UNW', 'oi_spike'       => 'OI-SPK',
                'volume_spike'        => 'VOL-SPK', 'pcr_shift'      => 'PCR-SHF',
                'breakout'            => 'BO',       'range_expand'   => 'RNG-EXP',
            ];
        @endphp
        @foreach($cArr as $ri => $c)
        @php
            $isLast   = ($ri === $total - 1);
            $isSuper  = $c['score'] >= $params['super_high_score'];
            $isHigh   = !$isSuper && $c['score'] >= $params['high_score'];
            $isTrap   = isset($c['breakdown']['trap_warning']);
            $rowCls   = $isLast ? 'r-last' : ($isSuper ? 'r-super' : ($isHigh ? 'r-high' : ($isTrap ? 'r-trap' : '')));

            $barPct   = min(100, round(max(0, $c['score']) / 20 * 100));
            $barColor = match($c['score_label']) {
                'SUPER'  => 'var(--super)', 'HIGH'   => 'var(--high)',
                'MEDIUM' => 'var(--med)',   'TRAP'   => 'var(--trap)',
                default  => 'var(--low)',
            };
            $f1   = $c['fwd_return_1']    ?? null;
            $f3   = $c['fwd_return_3']    ?? null;
            $mfe  = $c['mfe_n']           ?? null;
            $mae  = $c['mae_n']           ?? null;
            $er   = $c['expansion_ratio'] ?? null;
            $mq   = $c['move_quality']    ?? '—';
            $rr   = $c['range_ratio']     ?? null;
            $cmp3 = $c['range_compressed'] ?? false;   // FIX-A
            $tr   = $c['trend']           ?? 'FLAT';
            $ph   = $c['session_phase']   ?? 'MIDDAY'; // FIX-H
            $mqColor = match($mq) { 'STRONG'=>'var(--high)', 'MODERATE'=>'var(--med)', default=>'var(--txt-muted)' };
        @endphp
        <tr class="{{ $rowCls }}">

            <td style="color:var(--txt-muted);font-size:0.63rem;">
                {{ $ri + 1 }}
                @if($isSuper)<span style="color:var(--super);">★</span>
                @elseif($isHigh)<span style="color:var(--high);">⚡</span>@endif
                @if($isLast)<span style="color:var(--accent);">◀</span>@endif
            </td>

            <td class="time-chip">{{ $c['time'] }}</td>

            {{-- FIX-H: Session phase --}}
            <td><span class="phase-badge {{ $ph }}">{{ $ph }}</span></td>

            <td class="num-mono">{{ number_format($c['close'], 2) }}</td>
            <td><span class="{{ $c['price_pct'] >= 0 ? 'fwd-pos' : 'fwd-neg' }}">{{ $c['price_pct'] >= 0 ? '+' : '' }}{{ $c['price_pct'] }}%</span></td>
            <td class="num-mono">{{ number_format($c['oi']) }}</td>
            <td><span class="{{ $c['oi_pct'] >= 0 ? 'fwd-pos' : 'fwd-neg' }}">{{ $c['oi_pct'] >= 0 ? '+' : '' }}{{ $c['oi_pct'] }}%</span></td>
            <td class="num-mono">{{ number_format($c['volume']) }}</td>

            {{-- Vol × avg --}}
            <td>
                @if($c['vol_vs_avg'] !== null)
                    <span class="{{ $c['vol_vs_avg'] >= $params['vol_multiplier'] ? 'fwd-pos' : 'num-mono' }}">{{ $c['vol_vs_avg'] }}×</span>
                @else<span class="fwd-na">—</span>@endif
            </td>

            {{-- Range × avg --}}
            <td>
                @if($rr !== null)
                    <span class="{{ $rr >= $params['range_expand_multiplier'] ? 'fwd-pos' : 'num-mono' }}" title="Candle range vs rolling avg">{{ $rr }}×</span>
                @else<span class="fwd-na">—</span>@endif
            </td>

            {{-- FIX-A: Multi-candle compression indicator --}}
            <td>
                @if($cmp3)
                    <span class="comp-tag" title="All 3 candles below {{ $params['compression_ratio'] }}× avg range — multi-candle squeeze confirmed">CMP3</span>
                @else<span class="fwd-na">—</span>@endif
            </td>

            {{-- FIX-D: Slope-based trend --}}
            <td><span class="trend-badge {{ $tr }}">{{ $tr }}</span></td>

            {{-- OI Class --}}
            <td><span class="oi-badge {{ $c['oi_class'] }}">{{ str_replace('_', ' ', $c['oi_class']) }}</span></td>

            {{-- FIX-C: PCR via rolling array --}}
            <td>
                @if($c['pcr'] !== null)
                    @php $pcc2 = $c['pcr_change'] ?? 0; @endphp
                    <span class="pcr-val {{ $c['pcr'] > 1.2 ? 'pcr-up' : ($c['pcr'] < 0.8 ? 'pcr-dn' : 'pcr-n') }}">{{ number_format($c['pcr'], 2) }}</span>
                    @if($pcc2)<span style="font-size:0.6rem;color:{{ $pcc2 > 0 ? 'var(--bull)' : 'var(--bear)' }};"> {{ $pcc2 > 0 ? '+' : '' }}{{ round($pcc2, 1) }}%</span>@endif
                @else<span class="fwd-na">—</span>@endif
            </td>

            {{-- Score bar --}}
            <td>
                <div class="score-bar-wrap" style="min-width:70px;">
                    <div class="score-bar"><div class="score-bar-fill" style="width:{{ $barPct }}%;background:{{ $barColor }};"></div></div>
                    <span class="score-num" style="color:{{ $barColor }};">{{ $c['score'] }}</span>
                </div>
            </td>

            <td><span class="score-badge {{ $c['score_label'] }}">{{ $c['score_label'] }}</span></td>

            {{-- Forward returns --}}
            <td>@if($f1 !== null)<span class="{{ $f1 >= 0 ? 'fwd-pos' : 'fwd-neg' }}">{{ $f1 >= 0 ? '+' : '' }}{{ $f1 }}%</span>@else<span class="fwd-na">—</span>@endif</td>
            <td>@if($f3 !== null)<span class="{{ $f3 >= 0 ? 'fwd-pos' : 'fwd-neg' }}">{{ $f3 >= 0 ? '+' : '' }}{{ $f3 }}%</span>@else<span class="fwd-na">—</span>@endif</td>

            {{-- MFE/MAE --}}
            <td>@if($mfe !== null)<span class="fwd-pos" title="Best high +{{ $params['forward_lookahead'] }} candles">+{{ $mfe }}%</span>@else<span class="fwd-na">—</span>@endif</td>
            <td>@if($mae !== null)<span class="fwd-neg" title="Worst low +{{ $params['forward_lookahead'] }} candles">{{ $mae }}%</span>@else<span class="fwd-na">—</span>@endif</td>

            {{-- Expansion ratio --}}
            <td>
                @if($er !== null)
                    <span style="font-family:var(--mono);font-size:0.72rem;font-weight:700;color:{{ $er >= 1.5 ? 'var(--high)' : ($er >= 1.0 ? 'var(--med)' : 'var(--bear)') }};" title="MFE ÷ MAE. >1.5 = good R:R">{{ $er }}x</span>
                @else<span class="fwd-na">—</span>@endif
            </td>

            {{-- Move quality --}}
            <td><span style="font-size:0.65rem;font-family:var(--mono);font-weight:700;color:{{ $mqColor }};">{{ $mq }}</span></td>

            {{-- Breakdown pills --}}
            <td class="L">
                <div class="breakdown-pills">
                    @php $hasAny = false; @endphp
                    @foreach($c['breakdown'] as $bk => $bv)
                        @if($bk === 'trap_warning')
                            <span class="bp warn">⚠ TRAP</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'trend_align')
                            <span class="bp ok">↑ {{ is_string($bv) ? $bv : 'ALIGN' }}</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'trend_against')
                            <span class="bp warn">↕ CTRD</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'morning_bonus')
                            <span class="bp morn">☀ AM+1</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'lateday_penalty')
                            <span class="bp late">🌙 PM-1</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'vol_regime_boost')
                            <span class="bp vup">⚡ HVOL+1</span>@php $hasAny = true; @endphp
                        @elseif($bk === 'vol_regime_cut')
                            <span class="bp vdn">↓ LVOL-1</span>@php $hasAny = true; @endphp
                        @elseif(!in_array($bk, ['oi_class', 'trend', 'session_phase']))
                            <span class="bp">{{ $shortLabels[$bk] ?? strtoupper(str_replace('_', ' ', $bk)) }}{{ is_string($bv) ? ' '.$bv : '' }}</span>@php $hasAny = true; @endphp
                        @endif
                    @endforeach
                    @if(!$hasAny)<span class="fwd-na" style="font-size:0.65rem;">—</span>@endif
                </div>
            </td>

        </tr>
        @endforeach
        </tbody>
    </table>
    </div></div>

    <div class="legend-box">
        <strong>Detail key — v3 changes:</strong>
        ★ = SUPER candle &nbsp;·&nbsp; ⚡ = HIGH candle &nbsp;·&nbsp; ◀ = last candle &nbsp;·&nbsp;
        <span class="lg-s">PURPLE row</span> = super zone &nbsp;·&nbsp;
        <span class="lg-h">GREEN row</span> = high zone &nbsp;·&nbsp;
        <span class="lg-t">RED row</span> = trap warning<br>

        <strong>FIX-A — CMP3</strong>: All 3 recent candles below {{ $params['compression_ratio'] }}× avg range.
        ACCUM now requires a sustained squeeze — one random narrow candle is no longer enough.<br>

        <strong>FIX-B — BO</strong>: CLOSE breaks prev 3c high/low + vol.
        Range Expand score is NOT added on the same candle (was artificially inflating breakout scores).<br>

        <strong>FIX-C — PCR</strong>: Computed from rolling history array keyed by loop index.
        Safe even when FUT data has missing candles between CE/PE time slots.<br>

        <strong>FIX-D — Trend</strong>: SMA(i) &gt; SMA(i−1) &gt; SMA(i−2) for UP.
        Flat markets with slight tilt no longer produce UP/DOWN falsely.<br>

        <strong>FIX-E — ⚠ TRAP</strong>: Prev OI change must exceed avgOIChange × oi_spike_multiplier (genuine spike).
        Organic OI drift no longer qualifies as trap setup.<br>

        <strong>FIX-F — MFE Win%</strong>: % of candles where MFE ≥ {{ $params['mfe_win_threshold'] }}%.
        More honest than close-to-close win rate (price moved our way even if it reversed at close).<br>

        <strong>FIX-G — Vol Regime</strong> (day-level):
        HIGH VOL (avg range ≥ 1.4× opening baseline) → breakout +1.
        LOW VOL (avg range ≤ 0.7×) → spike signals −1 (spikes in quiet days are often noise).<br>

        <strong>FIX-H — Phase</strong>:
        ☀ MORNING (before {{ $params['morning_cutoff'] }}) = +1 for directional signals.
        🌙 LATEDAY (after {{ $params['lateday_cutoff'] }}) = −1 (expiry unwinding noise).<br>

        <strong style="color:var(--trap);">FIX 10: 30+ trading days required for statistical validation. 1-day backtest = anecdote.</strong>
    </div>
    @endif

@endif {{-- detail / overview --}}

</div>
</div>

@endsection

@push('script')
<script>
// Auto-open advanced thresholds if any non-default values present in URL
(function () {
    const defaults = {
        price_flat_pct: 0.5, oi_min_pct: 3.0, vol_multiplier: 1.3,
        compression_ratio: 0.7, oi_spike_multiplier: 2.0, vol_spike_multiplier: 1.8,
        range_expand_multiplier: 1.8, pcr_shift_pct: 15.0, trap_price_spike_pct: 1.0,
        trend_period: 10, medium_score: 3, trap_score: 0, mfe_win_threshold: 0.3,
        morning_cutoff: '10:30', lateday_cutoff: '14:30'
    };
    const p = new URLSearchParams(window.location.search);
    const hasCustom = Object.keys(defaults).some(k => {
        if (!p.has(k)) return false;
        const v = p.get(k);
        const d = defaults[k];
        return typeof d === 'number' ? parseFloat(v) !== d : v !== d;
    });
    if (hasCustom) document.getElementById('advT')?.classList.add('open');
})();
</script>
@endpush