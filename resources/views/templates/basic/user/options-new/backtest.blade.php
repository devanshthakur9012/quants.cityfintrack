{{-- resources/views/templates/basic/user/options-new/backtest.blade.php --}}
@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
.op-wrap{background:#0d1117;padding:24px;border-radius:10px;min-height:80vh}
.op-h1{color:#e2e8f0;font-size:1.2rem;font-weight:700;margin-bottom:2px}
.op-sub{color:#8b949e;font-size:.78rem}
.op-filter{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:12px 16px;margin-bottom:18px}
.op-filter label{color:#8b949e;font-size:.7rem;display:block;margin-bottom:3px}
.op-filter select,.op-filter input[type=date]{background:#0d1117;border:1px solid #30363d;color:#e2e8f0;padding:6px 10px;border-radius:6px;font-size:.8rem;width:100%;outline:none}
.btn-o{background:transparent;border:1px solid #30363d;color:#8b949e;padding:6px 14px;border-radius:6px;font-size:.75rem;text-decoration:none;display:inline-block}
.btn-o:hover{border-color:#58a6ff;color:#58a6ff}
.btn-g{background:#238636;border:1px solid #2ea043;color:#fff;padding:7px 18px;border-radius:6px;font-size:.8rem;cursor:pointer}
.c-up{color:#3fb950} .c-dn{color:#f85149} .c-mu{color:#8b949e} .c-bl{color:#58a6ff}

/* METRICS */
.mg{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
@media(max-width:800px){.mg{grid-template-columns:repeat(2,1fr)}}
.mc{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px 16px}
.mc small{display:block;color:#8b949e;font-size:.66rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.mc strong{font-size:1.5rem;font-weight:800}
.mc .ms{color:#8b949e;font-size:.66rem;margin-top:3px}

/* EQUITY CURVE */
.eq-p{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px;margin-bottom:18px}
.eq-p h6{color:#e2e8f0;font-size:.82rem;font-weight:700;margin-bottom:10px}
.eq-canvas{height:140px;position:relative}

/* VERDICT BANNER */
.vb{border-radius:8px;padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.vb.good{background:rgba(63,185,80,.07);border:1px solid rgba(63,185,80,.25)}
.vb.bad{background:rgba(248,81,73,.07);border:1px solid rgba(248,81,73,.2)}
.vb.neutral{background:#161b22;border:1px solid #30363d}
.vb-title{color:#e2e8f0;font-size:1.1rem;font-weight:700}
.vb-sub{color:#8b949e;font-size:.78rem;margin-top:2px}

/* TRADE LOG */
.tl-wrap{background:#161b22;border:1px solid #30363d;border-radius:8px;overflow:hidden}
.tl-head{padding:10px 14px;font-weight:700;font-size:.82rem;color:#e2e8f0;border-bottom:1px solid #30363d;display:flex;align-items:center;justify-content:space-between}
.tl-tbl{width:100%;border-collapse:collapse}
.tl-tbl thead th{color:#8b949e;font-size:.62rem;text-transform:uppercase;letter-spacing:.4px;padding:7px 10px;border-bottom:1px solid #30363d;background:#0d1117;text-align:left}
.tl-tbl tbody td{padding:7px 10px;border-bottom:1px solid rgba(48,54,61,.3);font-size:.76rem;color:#e2e8f0}
.tl-tbl tr.win td{background:rgba(63,185,80,.02)}
.tl-tbl tr.loss td{background:rgba(248,81,73,.02)}
.tl-tbl tr:hover td{background:rgba(255,255,255,.02)}
.ep{display:inline-block;padding:2px 7px;border-radius:6px;font-size:.62rem;font-weight:600}
.ep-tgt{background:rgba(63,185,80,.15);color:#3fb950}
.ep-sl{background:rgba(248,81,73,.15);color:#f85149}
.ep-time{background:rgba(139,148,158,.1);color:#8b949e}
.side-ce{color:#3fb950;font-weight:700} .side-pe{color:#f85149;font-weight:700}

/* DAILY SUMMARY MINI TABLE */
.daily-sum{background:#161b22;border:1px solid #30363d;border-radius:8px;overflow:hidden;margin-bottom:18px}
.ds-head{padding:10px 14px;font-weight:700;font-size:.82rem;color:#e2e8f0;border-bottom:1px solid #30363d}
.ds-tbl{width:100%;border-collapse:collapse}
.ds-tbl thead th{color:#8b949e;font-size:.62rem;text-transform:uppercase;letter-spacing:.4px;padding:7px 10px;border-bottom:1px solid #30363d;background:#0d1117;text-align:left}
.ds-tbl tbody td{padding:6px 10px;border-bottom:1px solid rgba(48,54,61,.3);font-size:.74rem;color:#e2e8f0}
.ds-tbl tr.win td:first-child{border-left:3px solid #3fb950}
.ds-tbl tr.loss td:first-child{border-left:3px solid #f85149}
.ds-tbl tr.no-trade td:first-child{border-left:3px solid #30363d}
</style>
@endpush

<section class="pt-30 pb-50">
<div class="container-fluid content-container">
<div class="op-wrap">

@php $strat = $strategy ?? 'intraday'; @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="op-h1">📈 {{ $strat==='intraday'?'Intraday':'Swing' }} Backtest — {{ $symbol }}</div>
        <div class="op-sub">
            @if($strat==='intraday')
            Window 9:30–11:00 · Entry confirmation required · 1% slippage · SL 25% / Target 50%
            @else
            Signal 13:30–14:30 · Score trending check · 1% slippage · SL 30% / Target 60%
            @endif
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('options.'.$strat, ['symbol'=>$symbol]) }}" class="btn-o">← Analysis</a>
        @if($strat==='intraday')
        <a href="{{ route('options.swing-backtest', ['symbol'=>$symbol,'from'=>$fromDate,'to'=>$toDate]) }}" class="btn-o">Swing BT</a>
        @else
        <a href="{{ route('options.intraday-backtest', ['symbol'=>$symbol,'from'=>$fromDate,'to'=>$toDate]) }}" class="btn-o">Intraday BT</a>
        @endif
    </div>
</div>

<div class="op-filter">
    <form method="GET" action="{{ route('options.'.$strat.'-backtest') }}" class="row g-2 align-items-end">
        <div class="col-md-2 col-sm-6"><label>Symbol</label>
            <select name="symbol">
                @foreach($symbols as $s)<option value="{{ $s }}" {{ $s===$symbol?'selected':'' }}>{{ $s }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2 col-sm-6"><label>From</label><input type="date" name="from" value="{{ $fromDate }}"></div>
        <div class="col-md-2 col-sm-6"><label>To</label><input type="date" name="to" value="{{ $toDate }}"></div>
        <div class="col-md-2 col-sm-6 d-flex align-items-end">
            <button type="submit" class="btn-g w-100">▶ Run</button>
        </div>
    </form>
</div>

@if(empty($trades))
<div style="text-align:center;padding:60px;color:#8b949e">
    <div style="font-size:2.5rem;margin-bottom:10px">📭</div>
    <div>No trades found in this range.</div>
    <div style="margin-top:8px;font-size:.78rem">Trades only fire when score ≥ ±5 AND all conditions pass (IV, delta, OI, confirmation). Sideways markets = 0 trades.</div>
</div>
@else

{{-- VERDICT BANNER --}}
@php
$wr = $metrics['win_rate'];
$pf = $metrics['profit_factor'];
$vClass = ($wr >= 55 && $pf >= 1.5) ? 'good' : ($wr < 40 ? 'bad' : 'neutral');
$vTitle = ($wr >= 55 && $pf >= 1.5) ? '✅ Profitable System' : ($wr < 40 ? '❌ Needs More Data / Refine' : '⚠️ Marginal — Continue Testing');
@endphp
<div class="vb {{ $vClass }}">
    <div>
        <div class="vb-title">{{ $vTitle }}</div>
        <div class="vb-sub">
            {{ $metrics['total'] }} trades · Win rate {{ $wr }}% · Profit factor {{ $pf }} ·
            Net P&amp;L {{ $metrics['net_pnl']>0?'+':'' }}{{ $metrics['net_pnl'] }}% on ₹100 capital
        </div>
    </div>
</div>

{{-- METRICS --}}
<div class="mg">
    <div class="mc" style="border-left:3px solid #{{ $wr>=60?'3fb950':($wr>=40?'f59e0b':'f85149') }}">
        <small>Win Rate</small>
        <strong class="{{ $wr>=60?'c-up':($wr>=40?'':'c-dn') }}" style="{{ $wr>=40&&$wr<60?'color:#f59e0b':'' }}">{{ $wr }}%</strong>
        <div class="ms">{{ $metrics['wins'] }}W / {{ $metrics['losses'] }}L of {{ $metrics['total'] }} trades</div>
    </div>
    <div class="mc" style="border-left:3px solid {{ $metrics['net_pnl']>=0?'#3fb950':'#f85149' }}">
        <small>Net P&amp;L</small>
        <strong class="{{ $metrics['net_pnl']>=0?'c-up':'c-dn' }}">{{ $metrics['net_pnl']>0?'+':'' }}{{ $metrics['net_pnl'] }}%</strong>
        <div class="ms">₹100 → ₹{{ 100+$metrics['net_pnl'] }}</div>
    </div>
    <div class="mc" style="border-left:3px solid #f59e0b">
        <small>Profit Factor</small>
        <strong style="color:#f59e0b">{{ $metrics['profit_factor'] }}</strong>
        <div class="ms">&gt;1.5 = good · &gt;2 = excellent</div>
    </div>
    <div class="mc" style="border-left:3px solid #f85149">
        <small>Max Drawdown</small>
        <strong class="c-dn">{{ $metrics['max_dd'] }}%</strong>
        <div class="ms">Avg win {{ $metrics['avg_win'] }}% / loss {{ $metrics['avg_loss'] }}%</div>
    </div>
</div>

{{-- EQUITY CURVE --}}
@if(count($curve) > 1)
<div class="eq-p">
    <h6>📊 Equity Curve (₹100 start)</h6>
    <div class="eq-canvas"><canvas id="ec"></canvas></div>
</div>
@endif

{{-- DAILY SUMMARY TABLE — simple, clear --}}
<div class="daily-sum">
    <div class="ds-head">📋 Daily Trade Log — {{ count($trades) }} trades</div>
    <div class="table-responsive">
    <table class="ds-tbl">
        <thead><tr>
            <th>Date</th>
            <th>Signal</th>
            <th>Score</th>
            <th>Side</th>
            <th>Strike</th>
            <th>Entry ₹</th>
            <th>Exit ₹</th>
            <th>Exit</th>
            <th>Result</th>
            <th>P&amp;L %</th>
        </tr></thead>
        <tbody>
        @foreach($trades as $t)
        <tr class="{{ $t['win']?'win':'loss' }}">
            <td style="font-weight:600;color:#e2e8f0">{{ $t['date'] }}</td>
            <td style="color:#8b949e;font-size:.7rem">{{ $t['signal_time'] }}</td>
            <td class="{{ $t['score']>0?'c-up':'c-dn' }}" style="font-weight:700">{{ $t['score']>0?'+':'' }}{{ $t['score'] }}</td>
            <td><span class="{{ $t['side']==='CE'?'side-ce':'side-pe' }}">{{ $t['side'] }}</span></td>
            <td class="c-mu">{{ $t['strike'] }}</td>
            <td>₹{{ $t['entry_px'] }}</td>
            <td>₹{{ $t['exit_px'] }}</td>
            <td>
                <span class="ep {{ $t['exit_reason']==='tgt'?'ep-tgt':($t['exit_reason']==='sl'?'ep-sl':'ep-time') }}">
                    {{ $t['exit_reason']==='tgt'?'Target':($t['exit_reason']==='sl'?'SL':'Time') }}
                    @if(isset($t['exit_date'])) ({{ $t['exit_date'] }})@endif
                </span>
            </td>
            <td>
                @if($t['win'])
                    <span style="color:#3fb950;font-weight:700">✓ Win</span>
                @else
                    <span style="color:#f85149;font-weight:700">✗ Loss</span>
                @endif
            </td>
            <td class="{{ $t['pnl_pct']>=0?'c-up':'c-dn' }}" style="font-weight:700">
                {{ $t['pnl_pct']>0?'+':'' }}{{ $t['pnl_pct'] }}%
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>

@endif
</div>
</div>
</section>

@push('script')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
@if(!empty($curve) && count($curve) > 1)
(function(){
    const ctx = document.getElementById('ec');
    if(!ctx) return;
    const labels = @json(array_column($curve,'date'));
    const data   = @json(array_column($curve,'equity'));
    const last   = data[data.length-1];
    const clr    = last >= 100 ? '#3fb950' : '#f85149';
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data,
                borderColor: clr,
                backgroundColor: clr+'14',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.3,
                fill: true,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color:'#8b949e',font:{size:10},maxTicksLimit:8 }, grid: { color:'rgba(48,54,61,.4)' } },
                y: { ticks: { color:'#8b949e',font:{size:10} }, grid: { color:'rgba(48,54,61,.4)' },
                     title: { display:true, text:'₹ Equity', color:'#8b949e', font:{size:10} } }
            }
        }
    });
})();
@endif
</script>
@endpush
@endsection