{{-- resources/views/templates/basic/user/options-new/intraday.blade.php --}}
@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
.op-wrap{background:#0d1117;padding:24px;border-radius:10px;min-height:80vh}
.op-h1{color:#e2e8f0;font-size:1.2rem;font-weight:700;margin-bottom:2px}
.op-sub{color:#8b949e;font-size:.78rem}
.op-bar{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:12px 16px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.op-bar label{color:#8b949e;font-size:.72rem}
.op-bar select{background:#0d1117;border:1px solid #30363d;color:#e2e8f0;padding:6px 10px;border-radius:6px;font-size:.8rem;outline:none;cursor:pointer}
.btn-g{background:#238636;border:1px solid #2ea043;color:#fff;padding:6px 14px;border-radius:6px;font-size:.75rem;cursor:pointer;font-family:inherit}
.btn-o{background:transparent;border:1px solid #30363d;color:#8b949e;padding:6px 14px;border-radius:6px;font-size:.75rem;text-decoration:none;display:inline-block}
.btn-o:hover{border-color:#58a6ff;color:#58a6ff}
.sum-row{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:14px}
.sum-box{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:11px 14px;text-align:center}
.sum-box small{display:block;color:#8b949e;font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}
.sum-box strong{font-size:1.4rem;font-weight:800}
.sig-wrap{background:#161b22;border:1px solid #30363d;border-radius:10px;overflow:hidden}
.sig-th{padding:11px 16px;border-bottom:1px solid #30363d;display:flex;align-items:center;justify-content:space-between}
table.st{width:100%;border-collapse:collapse}
table.st thead th{color:#8b949e;font-size:.63rem;text-transform:uppercase;letter-spacing:.5px;padding:8px 12px;text-align:left;border-bottom:1px solid #30363d;background:#0d1117;white-space:nowrap}
table.st tbody td{padding:9px 12px;border-bottom:1px solid rgba(48,54,61,.4);font-size:.8rem;color:#e2e8f0;vertical-align:middle}
table.st tbody tr:hover td{background:rgba(255,255,255,.025)}
table.st tbody tr.go td{background:rgba(63,185,80,.02)}
table.st tbody tr.avoid td{opacity:.55}
.sym{font-weight:800;font-size:.88rem;color:#e2e8f0}
.c-up{color:#3fb950} .c-dn{color:#f85149} .c-mu{color:#8b949e} .c-bl{color:#58a6ff} .c-or{color:#f59e0b}
.act{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:20px;font-weight:700;font-size:.76rem;white-space:nowrap}
.act.ce{background:rgba(63,185,80,.15);color:#3fb950;border:1px solid rgba(63,185,80,.25)}
.act.pe{background:rgba(248,81,73,.15);color:#f85149;border:1px solid rgba(248,81,73,.25)}
.act.no{background:rgba(139,148,158,.08);color:#8b949e;border:1px solid #30363d}
.act.av{background:rgba(248,81,73,.06);color:#ffa198;border:1px solid rgba(248,81,73,.15)}
.cf{display:flex;align-items:center;gap:5px}
.cf-track{width:44px;height:4px;background:#30363d;border-radius:2px;overflow:hidden}
.cf-fill{height:100%;border-radius:2px}
.tag{display:inline-block;padding:2px 6px;border-radius:4px;font-size:.62rem;margin:1px}
.tag-b{background:rgba(248,81,73,.08);color:#ffa198;border:1px solid rgba(248,81,73,.12)}
.tag-r{background:rgba(63,185,80,.07);color:#7ee787;border:1px solid rgba(63,185,80,.12)}
.tag-o{background:rgba(245,158,11,.08);color:#f59e0b;border:1px solid rgba(245,158,11,.15)}
.iv-b{display:inline-block;padding:1px 5px;border-radius:4px;font-size:.65rem}
.iv-HIGH{background:rgba(248,81,73,.12);color:#f85149}
.iv-LOW{background:rgba(63,185,80,.12);color:#3fb950}
.iv-NORMAL{background:rgba(139,148,158,.07);color:#8b949e}
.loader-wrap{text-align:center;padding:50px;color:#8b949e}
.spinner{display:inline-block;width:28px;height:28px;border:3px solid #30363d;border-top-color:#58a6ff;border-radius:50%;animation:spin .8s linear infinite;margin-bottom:10px}
@keyframes spin{to{transform:rotate(360deg)}}
#market-banner{border-radius:8px;padding:9px 16px;margin-bottom:14px;font-size:.78rem;font-weight:600;display:none;align-items:center;gap:10px}
#market-banner.bull{background:rgba(63,185,80,.07);border:1px solid rgba(63,185,80,.25);color:#3fb950}
#market-banner.bear{background:rgba(248,81,73,.07);border:1px solid rgba(248,81,73,.2);color:#f85149}
#market-banner.neut{background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.2);color:#f59e0b}
.exit-tgt{display:inline-block;padding:1px 5px;border-radius:3px;font-size:.62rem;background:rgba(63,185,80,.12);color:#3fb950}
.exit-sl{display:inline-block;padding:1px 5px;border-radius:3px;font-size:.62rem;background:rgba(248,81,73,.12);color:#f85149}
.exit-time{display:inline-block;padding:1px 5px;border-radius:3px;font-size:.62rem;background:rgba(139,148,158,.1);color:#8b949e}
</style>
@endpush

<section class="pt-30 pb-50">
<div class="container-fluid content-container">
<div class="op-wrap">

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="op-h1">📈 Intraday Signals</div>
        <div class="op-sub">All symbols · Window 9:30–11:00 · Entry next candle · Exit SL/TGT/15:00 · SL 25% / Target 50% · Score ≥±3</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('options.swing') }}" class="btn-o">Swing →</a>
        <a href="{{ route('options.intraday-backtest') }}" class="btn-o">Backtest</a>
    </div>
</div>

<div class="op-bar">
    <label>Date</label>
    <select id="dateSelect" style="min-width:140px">
        <option value="">Auto (latest per symbol)</option>
        @foreach($dates as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
    </select>
    <button class="btn-g" onclick="loadData()">🔍 Load</button>
    <span id="date-label"></span>
    <span id="load-time" style="color:#8b949e;font-size:.72rem;margin-left:auto"></span>
</div>

{{-- Market bias banner --}}
<div id="market-banner">
    <span id="market-icon">📊</span>
    <span id="market-text">Loading market direction…</span>
    <span style="margin-left:auto;font-size:.7rem;opacity:.7">CE allowed only in bullish · PE allowed only in bearish</span>
</div>

<div class="sum-row">
    <div class="sum-box" style="border-left:3px solid #58a6ff"><small>Scanned</small><strong class="c-bl" id="cnt-total">—</strong></div>
    <div class="sum-box" style="border-left:3px solid #3fb950"><small>Buy CE</small><strong class="c-up" id="cnt-ce">—</strong></div>
    <div class="sum-box" style="border-left:3px solid #f85149"><small>Buy PE</small><strong class="c-dn" id="cnt-pe">—</strong></div>
    <div class="sum-box" style="border-left:3px solid #8b949e"><small>No Trade</small><strong class="c-mu" id="cnt-watch">—</strong></div>
    <div class="sum-box" style="border-left:3px solid #f59e0b"><small>Wins Today</small><strong class="c-or" id="cnt-wins">—</strong></div>
</div>

<div class="sig-wrap">
    <div class="sig-th">
        <span style="color:#e2e8f0;font-size:.85rem;font-weight:700">Decision Table</span>
        <small style="color:#8b949e;font-size:.72rem">Score ≥±3 · Market filter ON · Tradeable first → |score| desc</small>
    </div>
    <div id="table-container">
        <div class="loader-wrap"><div class="spinner"></div><div>Loading signals…</div></div>
    </div>
</div>

</div>
</div>
</section>

@push('script')
<script>
const AJAX_URL = '{{ route("options.intraday-data") }}';

function loadData(date) {
    const d = date ?? document.getElementById('dateSelect').value;
    document.getElementById('table-container').innerHTML =
        '<div class="loader-wrap"><div class="spinner"></div><div>Loading signals…</div></div>';
    ['cnt-total','cnt-ce','cnt-pe','cnt-watch','cnt-wins'].forEach(id =>
        document.getElementById(id).textContent = '—');

    const t0 = Date.now();
    fetch(AJAX_URL + (d ? '?date=' + d : ''))
        .then(r => r.json())
        .then(data => {
            const rows    = data.rows || [];
            const elapsed = ((Date.now() - t0) / 1000).toFixed(1);
            const mb      = data.market_bias;

            if (data.date) {
                document.getElementById('date-label').innerHTML =
                    'Data date: <strong>' + data.date + '</strong>';
            }
            document.getElementById('load-time').textContent = elapsed + 's';

            // Market bias banner
            if (mb) {
                const banner = document.getElementById('market-banner');
                banner.style.display = 'flex';
                banner.className = mb.direction === 'BULLISH' ? 'bull' : (mb.direction === 'BEARISH' ? 'bear' : 'neut');
                document.getElementById('market-icon').textContent =
                    mb.direction === 'BULLISH' ? '📈' : (mb.direction === 'BEARISH' ? '📉' : '📊');
                document.getElementById('market-text').textContent =
                    'Market Direction: ' + mb.direction + ' — ' + mb.label;
            }

            const tradeable = rows.filter(r => r.active);
            const wins      = rows.filter(r => r.result && r.result.status === 'win');
            document.getElementById('cnt-total').textContent = rows.length;
            document.getElementById('cnt-ce').textContent    = tradeable.filter(r => r.side === 'CE').length;
            document.getElementById('cnt-pe').textContent    = tradeable.filter(r => r.side === 'PE').length;
            document.getElementById('cnt-watch').textContent = rows.filter(r => !r.active).length;
            document.getElementById('cnt-wins').textContent  = wins.length + '/' + tradeable.length;

            if (rows.length === 0) {
                document.getElementById('table-container').innerHTML =
                    '<div class="loader-wrap" style="color:#8b949e">📭 No data for this date.</div>';
                return;
            }

            let html = '<div class="table-responsive"><table class="st"><thead><tr>';
            ['Symbol','Action','Strike','Premium','SL (25%)','Tgt (50%)','Score','Conf','PCR','OI Zone','IV','Result','Exit','Reason'].forEach(h => {
                html += '<th>' + h + '</th>';
            });
            html += '</tr></thead><tbody>';

            rows.forEach(r => {
                const sc  = r.score;
                const scC = sc > 0 ? '#3fb950' : (sc < 0 ? '#f85149' : '#8b949e');
                const cp  = r.conf_pct;
                const cc  = cp >= 80 ? '#3fb950' : (cp >= 50 ? '#f59e0b' : '#8b949e');
                const oiC = r.oi_bias === 'BULLISH' ? '#3fb950' : (r.oi_bias === 'BEARISH' ? '#f85149' : '#f59e0b');
                const pcrC= (r.pcr ?? 1) > 1 ? '#3fb950' : '#f85149';
                const stC = r.active && r.side === 'CE' ? '#3fb950' : (r.active && r.side === 'PE' ? '#f85149' : '#8b949e');

                let actionHtml = '';
                if (r.active && r.side) {
                    actionHtml = '<span class="act ' + r.side.toLowerCase() + '">'
                        + (r.side === 'CE' ? '🟢' : '🔴') + ' BUY ' + r.side + '</span>';
                } else if (r.blocks && r.blocks.length) {
                    const mktBlock = r.blocks.find(b => b && b.includes && b.includes('Market'));
                    actionHtml = '<span class="act av">⚪ Avoid</span>'
                        + (mktBlock ? '<div style="color:#f59e0b;font-size:.6rem;margin-top:2px">📊 Mkt filter</div>' : '');
                } else {
                    actionHtml = '<span class="act no">⚪ Watch</span>';
                }

                // Result + Exit
                const res = r.result;
                let resultHtml = '<span class="c-mu">—</span>';
                let exitHtml   = '<span class="c-mu">—</span>';
                if (res && res.status !== 'unknown') {
                    const rc = res.status === 'win' ? 'c-up' : 'c-dn';
                    resultHtml = '<span style="font-weight:700" class="' + rc + '">' + (res.label || '—') + '</span>';
                    const exitBadge = res.reason === 'tgt' ? 'exit-tgt' : (res.reason === 'sl' ? 'exit-sl' : 'exit-time');
                    const exitIcon  = res.reason === 'tgt' ? '🎯' : (res.reason === 'sl' ? '🛑' : '⏱');
                    exitHtml = '<span class="' + exitBadge + '">' + exitIcon + ' '
                        + (res.reason_label || res.reason || '—') + '</span>';
                } else if (res && res.label) {
                    resultHtml = '<span class="c-mu">' + res.label + '</span>';
                }

                let reasonHtml = '';
                if (r.blocks && r.blocks.length) {
                    reasonHtml = r.blocks.slice(0,2).map(b => {
                        const isMkt = b && b.includes && b.includes('Market');
                        return '<span class="tag ' + (isMkt ? 'tag-o' : 'tag-b') + '">' + b + '</span>';
                    }).join('');
                } else if (r.reasons && r.reasons.length) {
                    reasonHtml = r.reasons.slice(0,2).map(b =>
                        '<span class="tag tag-r">' + b + '</span>').join('');
                } else {
                    reasonHtml = '<span class="c-mu">—</span>';
                }

                const ivBadge = r.iv
                    ? '<span class="iv-b iv-' + r.iv_regime + '">' + r.iv + '%</span>'
                    : '<span class="c-mu">—</span>';

                html += `<tr class="${r.active ? 'go' : 'avoid'}">
                    <td><div class="sym">${r.symbol}</div><div style="color:#8b949e;font-size:.65rem">${r.time}</div></td>
                    <td>${actionHtml}</td>
                    <td><span style="font-weight:700;color:${stC}">${r.strike}</span>${r.delta !== null ? '<div style="color:#8b949e;font-size:.63rem">Δ ' + r.delta + '</div>' : ''}</td>
                    <td>${r.premium ? '₹' + r.premium : '—'}</td>
                    <td class="c-dn">${r.sl ? '₹' + r.sl : '—'}</td>
                    <td class="c-up">${r.tgt ? '₹' + r.tgt : '—'}</td>
                    <td><span style="font-weight:800;font-size:.9rem;color:${scC}">${sc > 0 ? '+' : ''}${sc}</span></td>
                    <td>
                        <div class="cf">
                            <div class="cf-track"><div class="cf-fill" style="width:${cp}%;background:${cc}"></div></div>
                            <span style="font-size:.7rem;color:${cc}">${cp}%</span>
                        </div>
                    </td>
                    <td style="font-weight:600;color:${pcrC}">${r.pcr ?? '—'}</td>
                    <td style="font-size:.72rem;font-weight:600;color:${oiC}">${r.oi_bias}</td>
                    <td>${ivBadge}</td>
                    <td>${resultHtml}</td>
                    <td>${exitHtml}</td>
                    <td style="max-width:200px">${reasonHtml}</td>
                </tr>`;
            });

            html += '</tbody></table></div>';
            document.getElementById('table-container').innerHTML = html;
        })
        .catch(err => {
            document.getElementById('table-container').innerHTML =
                '<div class="loader-wrap" style="color:#f85149">❌ Error: ' + err.message + '</div>';
        });
}

document.addEventListener('DOMContentLoaded', () => loadData());
document.getElementById('dateSelect').addEventListener('change', function() { loadData(this.value); });
</script>
@endpush
@endsection