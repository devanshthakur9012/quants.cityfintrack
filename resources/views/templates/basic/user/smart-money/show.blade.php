@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ── Page Header ── */
.page-header {
    background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
    color: white; padding: 18px 24px; border-radius: 12px;
    margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.4);
}
.page-header h4 { color: white; margin: 0; }
.page-header p  { color: rgba(255,255,255,0.75); margin: 4px 0 0; font-size: 12px; }

/* ── Signal hero strip ── */
.signal-strip {
    padding: 18px 20px; border-radius: 12px; margin-bottom: 20px;
    border: 1px solid rgba(255,255,255,0.08);
    background: rgba(255,255,255,0.03);
    display: flex; align-items: center; gap: 20px; flex-wrap: wrap;
}

.big-sig-badge {
    padding: 12px 24px; border-radius: 10px;
    font-size: 18px; font-weight: 800; letter-spacing: .4px; white-space: nowrap;
}
.bsig-buy          { background:rgba(40,167,69,0.22);  color:#51cf66; border:1px solid rgba(40,167,69,0.5); }
.bsig-sell         { background:rgba(220,53,69,0.22);  color:#ff6b6b; border:1px solid rgba(220,53,69,0.5); }
.bsig-buy_pullback { background:rgba(255,165,2,0.18);  color:#ffa502; border:1px solid rgba(255,165,2,0.5); }
.bsig-sell_pullback{ background:rgba(253,121,168,0.18);color:#fd79a8; border:1px solid rgba(253,121,168,0.5); }
.bsig-no_trade     { background:rgba(255,255,255,0.06);color:rgba(255,255,255,.4); border:1px solid rgba(255,255,255,0.12); }
.bsig-no_data      { background:rgba(255,255,255,0.04);color:rgba(255,255,255,.25); border:1px solid rgba(255,255,255,0.08); }

.sig-reason { font-size: 15px; font-weight: 600; color: white; margin-bottom: 5px; }
.sig-meta   { font-size: 11px; color: rgba(255,255,255,.45); }
.sig-meta strong { color: rgba(255,255,255,.7); }

/* ── Detail grid ── */
.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.d-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px;
    overflow: hidden;
}
.d-card-header {
    padding: 10px 16px;
    background: rgba(0,0,0,0.25);
    border-bottom: 1px solid rgba(255,255,255,0.06);
    font-size: 9px; font-weight: 800; text-transform: uppercase;
    letter-spacing: .8px; color: rgba(255,255,255,.4);
    display: flex; align-items: center; gap: 6px;
}
.d-card-body { padding: 14px 16px; }

/* Metric rows */
.metric-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 8px 0; border-bottom: 1px solid rgba(255,255,255,0.05);
    font-size: 12px;
}
.metric-row:last-child { border-bottom: none; }
.metric-lbl { color: rgba(255,255,255,.45); }
.metric-val { font-weight: 700; font-family: monospace; color: white; font-size: 12px; }

/* Bool pills */
.bool-yes  { display:inline-block; background:rgba(40,167,69,0.2); color:#51cf66; border:1px solid rgba(40,167,69,0.4); border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }
.bool-no   { display:inline-block; background:rgba(255,255,255,0.04); color:rgba(255,255,255,.25); border:1px solid rgba(255,255,255,0.08); border-radius:5px; padding:2px 8px; font-size:10px; }
.bool-warn { display:inline-block; background:rgba(255,165,2,0.18); color:#ffa502; border:1px solid rgba(255,165,2,0.4); border-radius:5px; padding:2px 8px; font-size:10px; font-weight:800; }

/* Trend badge */
.trend-up   { display:inline-block; background:rgba(40,167,69,0.18); color:#51cf66; border:1px solid rgba(40,167,69,0.4); border-radius:5px; padding:3px 10px; font-size:11px; font-weight:800; }
.trend-down { display:inline-block; background:rgba(220,53,69,0.18); color:#ff6b6b; border:1px solid rgba(220,53,69,0.4); border-radius:5px; padding:3px 10px; font-size:11px; font-weight:800; }
.trend-side { display:inline-block; background:rgba(255,255,255,0.06); color:rgba(255,255,255,.35); border:1px solid rgba(255,255,255,0.1); border-radius:5px; padding:3px 10px; font-size:11px; font-weight:600; }

/* Checklist */
.check-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 12px;
}
.check-row:last-child { border-bottom: none; }
.check-lbl { color: rgba(255,255,255,.45); }

/* Chart card — full width */
.chart-card {
    grid-column: 1 / -1;
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 12px; overflow: hidden;
}
.chart-card .d-card-header { display: block; }

canvas#sparkline { width: 100% !important; height: 200px !important; display: block; }

/* Zone strip */
.zone-strip {
    display: flex; gap: 10px; padding: 10px 0; flex-wrap: wrap;
}
.zone-badge {
    flex: 1; min-width: 120px;
    padding: 10px 14px; border-radius: 8px; text-align: center;
    font-size: 11px;
}
.zone-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; opacity: .6; margin-bottom: 4px; }
.zone-val   { font-family: monospace; font-size: 14px; font-weight: 800; }
.zone-bull  { background: rgba(40,167,69,0.12); border: 1px solid rgba(40,167,69,0.3); color: #51cf66; }
.zone-bear  { background: rgba(220,53,69,0.12); border: 1px solid rgba(220,53,69,0.3); color: #ff6b6b; }
.zone-na    { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); color: rgba(255,255,255,.25); }

/* ── Back btn ── */
.back-btn {
    display:inline-flex; align-items:center; gap:6px;
    background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.2);
    color:white; border-radius:8px; padding:6px 14px; font-size:12px;
    font-weight:700; text-decoration:none; transition:.15s;
}
.back-btn:hover { background:rgba(255,255,255,0.18); color:white; }

/* ── Info note ── */
.info-note {
    background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);
    border-radius:8px; padding:10px 14px; font-size:11px;
    color:rgba(255,255,255,.4); line-height:1.6; margin-top:10px;
}
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    @php
        $sigCss = strtolower($signal['signal']);
        $sigIcons = [
            'BUY'          => '&#129033; BUY',
            'SELL'         => '&#129035; SELL',
            'BUY_PULLBACK' => '&#8629; BUY PULLBACK',
            'SELL_PULLBACK'=> '&#8629; SELL PULLBACK',
            'NO_TRADE'     => '&#9675; NO TRADE',
            'NO_DATA'      => '? NO DATA',
        ];
        // Date vars are passed from controller; provide defaults for safety
        $selectedDate = $selectedDate ?? now()->toDateString();
        $isToday      = $isToday      ?? true;
        $todayStr     = $todayStr     ?? now()->toDateString();
    @endphp

    {{-- ── Page Header ── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>&#129504; {{ $symbol }}
                    <span style="background:rgba(255,255,255,0.15);padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;margin-left:6px;">Smart Money Analysis</span>
                    @if(!$isToday)
                        <span style="background:rgba(255,193,7,0.25);color:#ffc107;border:1px solid rgba(255,193,7,0.4);padding:2px 8px;border-radius:4px;font-size:10px;font-weight:700;margin-left:6px;">
                            &#128197; {{ \Carbon\Carbon::parse($selectedDate)->format('d M Y') }}
                        </span>
                    @endif
                </h4>
                <p>
                    SMC breakdown &mdash; Trend &middot; Liquidity Sweep &middot; FVG &middot; Order Block &middot; EMA-20
                    @if($signal['last_date'])
                        &nbsp;&middot;&nbsp; Data as of: <strong>{{ \Carbon\Carbon::parse($signal['last_date'])->format('d M Y') }}</strong>
                    @endif
                </p>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                {{-- Back to list — preserve date --}}
                <a href="{{ route('smart-money.index', $isToday ? [] : ['date' => $selectedDate]) }}"
                   class="back-btn">&#8592; All Symbols</a>
                {{-- Date navigation on show page --}}
                <div style="display:flex;align-items:center;gap:6px;">
                    <input type="date"
                           value="{{ $selectedDate }}"
                           max="{{ $todayStr }}"
                           onchange="goDate(this.value)"
                           style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.25);border-radius:6px;color:white;padding:4px 8px;font-size:11px;outline:none;cursor:pointer;">
                    @if($isToday)
                        <span style="font-size:10px;font-weight:700;padding:2px 9px;border-radius:10px;background:rgba(0,255,136,0.2);color:#00ff88;border:1px solid rgba(0,255,136,0.3);">&#9679; Live</span>
                    @else
                        <a href="{{ route('smart-money.show', $symbol) }}"
                           style="font-size:10px;font-weight:700;padding:2px 9px;border-radius:10px;background:rgba(255,255,255,0.1);color:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,0.2);text-decoration:none;">
                            Today
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Signal Hero Strip ── --}}
    <div class="signal-strip">
        <div class="big-sig-badge bsig-{{ $sigCss }}">
            {!! $sigIcons[$signal['signal']] ?? $signal['signal'] !!}
        </div>
        <div style="flex:1;">
            <div class="sig-reason">{{ $signal['reason'] }}</div>
            <div class="sig-meta">
                @if($signal['last_close'])
                    <strong>LTP &#8377;{{ number_format($signal['last_close'], 2) }}</strong>
                    &nbsp;&middot;&nbsp;
                    <strong>EMA-20 &#8377;{{ number_format($signal['ema20'], 2) }}</strong>
                    &nbsp;&middot;&nbsp;
                    @if($signal['last_close'] > $signal['ema20'])
                        <span style="color:#51cf66;font-weight:700;">Price above EMA &#9650;</span>
                    @else
                        <span style="color:#ff6b6b;font-weight:700;">Price below EMA &#9660;</span>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- ── Detail Grid ── --}}
    <div class="detail-grid">

        {{-- Chart -- full width --}}
        <div class="chart-card">
            <div class="d-card-header">&#128200; Price Chart &mdash; Last 30 Candles &nbsp;&middot;&nbsp; <span style="color:#6c757d;">Dashed line = EMA-20</span></div>
            <canvas id="sparkline"></canvas>
        </div>

        {{-- Market Structure --}}
        <div class="d-card">
            <div class="d-card-header">&#128200; Market Structure</div>
            <div class="d-card-body">
                <div style="margin-bottom:12px;">
                    @if($signal['trend'] === 'UPTREND')
                        <span class="trend-up">&#8593; UPTREND</span>
                    @elseif($signal['trend'] === 'DOWNTREND')
                        <span class="trend-down">&#8595; DOWNTREND</span>
                    @elseif($signal['trend'] === 'SIDEWAYS')
                        <span class="trend-side">&#8594; SIDEWAYS</span>
                    @else
                        <span style="color:rgba(255,255,255,.2);font-size:11px;">&mdash;</span>
                    @endif
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">Last Close</span>
                    <span class="metric-val">&#8377;{{ number_format($signal['last_close'] ?? 0, 2) }}</span>
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">20-Day High</span>
                    <span class="metric-val" style="color:#ff9f7f;">&#8377;{{ number_format($signal['recent_high'] ?? 0, 2) }}</span>
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">20-Day Low</span>
                    <span class="metric-val" style="color:#7fff9f;">&#8377;{{ number_format($signal['recent_low'] ?? 0, 2) }}</span>
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">EMA-20</span>
                    <span class="metric-val" style="color:#74b9ff;">&#8377;{{ number_format($signal['ema20'] ?? 0, 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Volume --}}
        <div class="d-card">
            <div class="d-card-header">&#128314; Volume Analysis</div>
            <div class="d-card-body">
                <div class="metric-row">
                    <span class="metric-lbl">Volume Spike (&gt;1.5×)</span>
                    {!! $signal['volume_spike']
                        ? '<span class="bool-yes">&#10003; YES</span>'
                        : '<span class="bool-no">&#10007; NO</span>' !!}
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">20-Day Avg Volume</span>
                    <span class="metric-val">{{ $signal['avg_volume'] ? number_format($signal['avg_volume']) : '&mdash;' }}</span>
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">Spike Threshold</span>
                    <span class="metric-val" style="color:rgba(255,255,255,.35);">1.5&times; average</span>
                </div>
                <div class="info-note">
                    Volume spike confirms institutional participation — a signal without volume is weak.
                </div>
            </div>
        </div>

        {{-- Liquidity Sweeps --}}
        <div class="d-card">
            <div class="d-card-header">&#127959; Liquidity Sweeps</div>
            <div class="d-card-body">
                <div class="metric-row">
                    <span class="metric-lbl">Sweep Low (Bullish)</span>
                    {!! $signal['liquidity_sweep_low']
                        ? '<span class="bool-yes">&#10003; DETECTED</span>'
                        : '<span class="bool-no">&#10007; NONE</span>' !!}
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">Sweep High (Bearish)</span>
                    {!! $signal['liquidity_sweep_high']
                        ? '<span class="bool-warn">&#10003; DETECTED</span>'
                        : '<span class="bool-no">&#10007; NONE</span>' !!}
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">Lookback Period</span>
                    <span class="metric-val" style="color:rgba(255,255,255,.35);">20 candles</span>
                </div>
                <div class="info-note">
                    Sweep = wick beyond recent high/low but close back inside. Signals a stop-hunt by smart money before the real move.
                </div>
            </div>
        </div>

        {{-- FVG --}}
        <div class="d-card">
            <div class="d-card-header">&#9644; Fair Value Gaps</div>
            <div class="d-card-body">
                <div class="metric-row">
                    <span class="metric-lbl">Bullish FVG</span>
                    {!! $signal['fvg_bullish']
                        ? '<span class="bool-yes">&#10003; PRESENT</span>'
                        : '<span class="bool-no">&#10007; NONE</span>' !!}
                </div>
                <div class="metric-row">
                    <span class="metric-lbl">Bearish FVG</span>
                    {!! $signal['fvg_bearish']
                        ? '<span class="bool-warn">&#10003; PRESENT</span>'
                        : '<span class="bool-no">&#10007; NONE</span>' !!}
                </div>
                <div class="info-note">
                    Bull FVG: candle[n&minus;2] high &lt; candle[n&minus;1] low &mdash; price moved up leaving an imbalance gap likely to be filled.
                </div>
            </div>
        </div>

        {{-- Order Blocks --}}
        <div class="d-card">
            <div class="d-card-header">&#9633; Order Blocks (Last 10 Candles)</div>
            <div class="d-card-body">
                <div class="zone-strip">
                    @if($signal['order_block_bull'])
                        <div class="zone-badge zone-bull">
                            <div class="zone-label">&#129033; Bull OB (Demand)</div>
                            <div class="zone-val">&#8377;{{ number_format($signal['order_block_bull'], 2) }}</div>
                        </div>
                    @else
                        <div class="zone-badge zone-na">
                            <div class="zone-label">&#129033; Bull OB</div>
                            <div class="zone-val">&mdash;</div>
                        </div>
                    @endif
                    @if($signal['order_block_bear'])
                        <div class="zone-badge zone-bear">
                            <div class="zone-label">&#129035; Bear OB (Supply)</div>
                            <div class="zone-val">&#8377;{{ number_format($signal['order_block_bear'], 2) }}</div>
                        </div>
                    @else
                        <div class="zone-badge zone-na">
                            <div class="zone-label">&#129035; Bear OB</div>
                            <div class="zone-val">&mdash;</div>
                        </div>
                    @endif
                </div>
                <div class="info-note">
                    Bull OB = low of last bearish candle before bullish move (demand zone).
                    Bear OB = high of last bullish candle before bearish move (supply zone).
                </div>
            </div>
        </div>

        {{-- Signal Checklist --}}
        <div class="d-card">
            <div class="d-card-header">&#9989; Signal Conditions Checklist</div>
            <div class="d-card-body">
                @php
                    $conditions = [
                        ['&#8593; Uptrend structure',         $signal['trend'] === 'UPTREND'],
                        ['&#8595; Downtrend structure',       $signal['trend'] === 'DOWNTREND'],
                        ['&#128314; Volume spike (&gt;1.5×)', $signal['volume_spike']],
                        ['&#127959; Liquidity sweep low',     $signal['liquidity_sweep_low']],
                        ['&#127959; Liquidity sweep high',    $signal['liquidity_sweep_high']],
                        ['&#9644; Bullish FVG',               $signal['fvg_bullish']],
                        ['&#9644; Bearish FVG',               $signal['fvg_bearish']],
                        ['&#128200; Price above EMA-20',      $signal['last_close'] && $signal['ema20'] && $signal['last_close'] > $signal['ema20']],
                        ['&#9633; Bull order block present',  !is_null($signal['order_block_bull'])],
                        ['&#9633; Bear order block present',  !is_null($signal['order_block_bear'])],
                    ];
                @endphp
                @foreach($conditions as [$label, $pass])
                <div class="check-row">
                    <span class="check-lbl">{!! $label !!}</span>
                    <span style="font-size:14px;">{{ $pass ? '&#9989;' : '&#11036;' }}</span>
                </div>
                @endforeach
            </div>
        </div>

    </div>{{-- /detail-grid --}}

</div>
</section>
@endsection

@push('script')
<script>
(function () {
    const candles = @json($chartCandles);
    if (!candles || !candles.length) return;

    const canvas = document.getElementById('sparkline');
    const ctx    = canvas.getContext('2d');
    const dpr    = window.devicePixelRatio || 1;
    const W      = canvas.offsetWidth || canvas.parentElement.offsetWidth || 800;
    const H      = 200;

    canvas.width  = W * dpr;
    canvas.height = H * dpr;
    ctx.scale(dpr, dpr);

    const pad = { top: 20, bottom: 28, left: 10, right: 10 };

    const closes = candles.map(c => parseFloat(c.close));
    const highs  = candles.map(c => parseFloat(c.high));
    const lows   = candles.map(c => parseFloat(c.low));
    const minP   = Math.min(...lows);
    const maxP   = Math.max(...highs);
    const range  = maxP - minP || 1;

    const sy = v => pad.top + (1 - (v - minP) / range) * (H - pad.top - pad.bottom);
    const sx = i => pad.left + (i / Math.max(candles.length - 1, 1)) * (W - pad.left - pad.right);

    // Background
    ctx.fillStyle = 'rgba(0,0,0,0)';
    ctx.fillRect(0, 0, W, H);

    // Grid lines
    ctx.strokeStyle = 'rgba(255,255,255,0.05)';
    ctx.lineWidth = 1;
    [0.25, 0.5, 0.75].forEach(t => {
        const y = pad.top + t * (H - pad.top - pad.bottom);
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(W - pad.right, y); ctx.stroke();
    });

    // EMA line
    const ema20 = {{ $signal['ema20'] ?? 0 }};
    if (ema20 > 0 && ema20 >= minP && ema20 <= maxP) {
        const ey = sy(ema20);
        ctx.strokeStyle = 'rgba(116,185,255,0.55)';
        ctx.lineWidth = 1.5;
        ctx.setLineDash([5, 5]);
        ctx.beginPath(); ctx.moveTo(pad.left, ey); ctx.lineTo(W - pad.right, ey); ctx.stroke();
        ctx.setLineDash([]);
        ctx.fillStyle = 'rgba(116,185,255,0.65)';
        ctx.font = '9px monospace';
        ctx.fillText('EMA20 ₹' + ema20.toFixed(0), pad.left + 4, ey - 4);
    }

    // Candle bars
    const barW = Math.max(2, (W - pad.left - pad.right) / candles.length * 0.6);

    candles.forEach((c, i) => {
        const x     = sx(i);
        const isUp  = parseFloat(c.close) >= parseFloat(c.open);
        const color = isUp ? '#51cf66' : '#ff6b6b';
        const yHigh = sy(parseFloat(c.high));
        const yLow  = sy(parseFloat(c.low));
        const yOpen = sy(parseFloat(c.open));
        const yClose= sy(parseFloat(c.close));

        // Wick
        ctx.strokeStyle = color;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(x, yHigh);
        ctx.lineTo(x, yLow);
        ctx.stroke();

        // Body
        const bodyTop = Math.min(yOpen, yClose);
        const bodyH   = Math.max(Math.abs(yClose - yOpen), 1.5);
        ctx.fillStyle = color;
        ctx.fillRect(x - barW / 2, bodyTop, barW, bodyH);
    });

    // Price labels — high / low
    ctx.fillStyle = 'rgba(255,255,255,0.3)';
    ctx.font = '9px monospace';
    ctx.textAlign = 'right';
    ctx.fillText('₹' + maxP.toFixed(0), W - pad.right - 2, pad.top + 10);
    ctx.fillText('₹' + minP.toFixed(0), W - pad.right - 2, H - pad.bottom - 4);

    // Last close dot
    const lx = sx(candles.length - 1);
    const ly = sy(closes[closes.length - 1]);
    ctx.beginPath();
    ctx.arc(lx, ly, 4, 0, Math.PI * 2);
    const lastColor = closes[closes.length-1] >= closes[0] ? '#51cf66' : '#ff6b6b';
    ctx.fillStyle = lastColor;
    ctx.fill();
    ctx.strokeStyle = 'rgba(0,0,0,0.4)';
    ctx.lineWidth = 1.5;
    ctx.stroke();
})();

function goDate(val) {
    if (!val) return;
    const today = '{{ $todayStr }}';
    if (val > today) val = today;
    window.location.href = '{{ route("smart-money.show", $symbol) }}?date=' + val;
}
</script>
@endpush