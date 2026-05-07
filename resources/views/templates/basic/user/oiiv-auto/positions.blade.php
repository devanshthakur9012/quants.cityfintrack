@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
.pos-header { background:linear-gradient(135deg,#0f2027,#203a43,#2c5364); color:white; padding:18px 22px; border-radius:12px; margin-bottom:18px; }
.pos-header h4 { margin:0; font-size:17px; font-weight:700; }

.broker-bar { background:#f8f9fa; border:1px solid #e9ecef; border-radius:10px; padding:12px 16px; margin-bottom:16px; display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.broker-bar label { font-size:12px; font-weight:600; color:#555; margin:0; }
.broker-bar select,.broker-bar input[type=date] { font-size:12px; padding:5px 10px; border:1px solid #ddd; border-radius:6px; }

.ob-tabs { display:flex; border-bottom:2px solid #e9ecef; margin-bottom:0; padding:0 16px; }
.ob-tab  { padding:10px 20px; font-size:12px; font-weight:600; cursor:pointer; color:#888; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .15s; }
.ob-tab.active { color:#2c5364; border-bottom-color:#2c5364; }
.ob-tab .cnt { background:#e9ecef; color:#555; font-size:10px; font-weight:700; padding:1px 7px; border-radius:20px; margin-left:5px; }
.ob-tab.active .cnt { background:#2c5364; color:white; }

.pos-table { min-width:1300px; font-size:11px; }
.pos-table thead th { background:#f8f9fa; font-size:10px; text-transform:uppercase; letter-spacing:.3px; font-weight:700; color:#555; padding:8px; white-space:nowrap; border-bottom:2px solid #dee2e6; }
.pos-table tbody td { padding:8px; vertical-align:middle; border-bottom:1px solid #f0f0f0; white-space:nowrap; }
.pos-table tbody tr:hover { background:#f9fafb; }

.pnl-pos { color:#166534; font-weight:700; }
.pnl-neg { color:#991b1b; font-weight:700; }

.status-open   { background:#dbeafe; color:#1d4ed8; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:700; }
.status-closed { background:#f1f5f9; color:#475569; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:700; }

.s-bull { background:#dcfce7; color:#166534; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.s-bear { background:#fee2e2; color:#991b1b; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.s-ce   { background:#28a745; color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.s-pe   { background:#dc3545; color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }

.sum-row { background:linear-gradient(135deg,#f8f9fa,#e9ecef); border-top:2px solid #dee2e6; }
.sum-row td { padding:8px; font-size:11px; font-weight:700; }

.sqoff-btn { font-size:10px; padding:3px 10px; border-radius:4px; background:linear-gradient(135deg,#ef4444,#dc2626); color:white; border:none; cursor:pointer; }
.sqoff-btn:hover { opacity:.85; }

.holding-pill { background:#e0e7ff; color:#3730a3; padding:2px 8px; border-radius:12px; font-size:10px; font-weight:600; }
.holding-pill.today { background:#fef3c7; color:#b45309; }

.ob-empty { text-align:center; padding:60px 20px; color:#aaa; }
.ob-empty i { font-size:2.5rem; display:block; margin-bottom:12px; }

/* ── Summary strip ── */
.summary-strip { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:14px; }
.ss-card { background:white; border:1px solid #e9ecef; border-radius:8px; padding:8px 14px; text-align:center; min-width:100px; }
.ss-card small { display:block; font-size:10px; color:#888; }
.ss-card strong { display:block; font-size:1.1rem; font-weight:700; margin-top:2px; }
</style>
@endpush

<section class="pt-40 pb-60">
<div class="container-fluid content-container">

    <div class="pos-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fas fa-briefcase"></i> OIIV Positions</h4>
            <p style="font-size:11px;opacity:.7;margin:4px 0 0">
                Only positions from OIIV auto-trading — manual Zerodha trades excluded
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('oiiv-orders.order-book') }}" class="btn btn-outline-light btn-sm">
                <i class="fas fa-list-alt"></i> Order Book
            </a>
            <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-outline-light btn-sm">
                <i class="fas fa-chart-bar"></i> Analysis
            </a>
        </div>
    </div>

    {{-- Broker bar --}}
    <div class="broker-bar">
        <label>Broker:</label>
        <select id="brokerSelect" onchange="loadPositions()">
            @foreach($brokers as $b)
                <option value="{{ $b->id }}">{{ $b->client_name }} ({{ $b->account_user_name }})</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-primary" style="font-size:11px" onclick="syncAndLoad()">
            <i class="fas fa-sync" id="syncIcon"></i> Sync LTPs
        </button>
        <small class="text-muted" id="syncedAt"></small>
    </div>

    {{-- Summary strip --}}
    <div class="summary-strip" id="summaryStrip" style="display:none">
        <div class="ss-card">
            <small>Open Positions</small>
            <strong id="sumOpen" style="color:#1d4ed8">0</strong>
        </div>
        <div class="ss-card">
            <small>Unrealized P&L</small>
            <strong id="sumUnrealized">₹0</strong>
        </div>
        <div class="ss-card">
            <small>Closed Today</small>
            <strong id="sumClosedToday" style="color:#475569">0</strong>
        </div>
        <div class="ss-card">
            <small>Realized P&L</small>
            <strong id="sumRealized">₹0</strong>
        </div>
        <div class="ss-card">
            <small>Win Rate (closed)</small>
            <strong id="sumWinRate">—</strong>
        </div>
    </div>

    {{-- Tabs + table --}}
    <div style="background:white; border:1px solid #e9ecef; border-radius:10px; overflow:hidden;">
        <div class="ob-tabs" style="padding-top:8px">
            <div class="ob-tab active" onclick="switchTab('open', this)">
                Open <span class="cnt" id="tabOpenCnt">0</span>
            </div>
            <div class="ob-tab" onclick="switchTab('closed', this)">
                Closed / History <span class="cnt" id="tabClosedCnt">0</span>
            </div>
        </div>

        {{-- Closed date filter --}}
        <div id="closedFilters" style="display:none; background:#f8f9fa; padding:10px 16px; border-bottom:1px solid #e9ecef; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <label style="font-size:11px;font-weight:600">From:</label>
            <input type="date" id="fromDate" style="font-size:11px;padding:4px 8px;border:1px solid #ddd;border-radius:5px" onchange="loadPositions()">
            <label style="font-size:11px;font-weight:600">To:</label>
            <input type="date" id="toDate"   style="font-size:11px;padding:4px 8px;border:1px solid #ddd;border-radius:5px" onchange="loadPositions()">
            <button onclick="clearDates()" style="font-size:11px;padding:3px 10px;border:1px solid #ddd;border-radius:5px;background:white;cursor:pointer">Clear</button>
        </div>

        <div style="overflow-x:auto">
            <table class="table pos-table mb-0">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Signal Date</th>
                        <th>Sentiment</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>Qty (Lots)</th>
                        <th>Entry ₹</th>
                        <th>LTP / Exit ₹</th>
                        <th>P&L</th>
                        <th>P&L %</th>
                        <th>Held For</th>
                        <th>Entry At</th>
                        <th>Status</th>
                        <th>Exit Via</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="posTableBody">
                    <tr><td colspan="15" class="ob-empty"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>

{{-- Square-off modal --}}
<div class="modal fade" id="sqoffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#ef4444,#dc2626)">
                <h6 class="modal-title text-white mb-0">🔴 Square Off Position</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:12px;margin-bottom:14px;font-size:12px">
                    <div><strong id="sqSymbol">—</strong></div>
                    <div>Entry: ₹<span id="sqEntry">0</span> &nbsp;|&nbsp; LTP: ₹<span id="sqLtp">0</span></div>
                    <div>Qty: <span id="sqQtyUnits">0</span> units | P&L: <span id="sqPnl">—</span></div>
                </div>
                <div class="mb-2">
                    <label style="font-size:12px;font-weight:600">Order Type</label>
                    <select id="sqOrderType" class="form-control form-control-sm" onchange="toggleSqPrice()">
                        <option value="MARKET">MARKET (Instant)</option>
                        <option value="LIMIT">LIMIT (Set price)</option>
                    </select>
                </div>
                <div id="sqPriceWrap" style="display:none">
                    <label style="font-size:12px;font-weight:600">Limit Price</label>
                    <input type="number" id="sqPrice" class="form-control form-control-sm" step="0.05" placeholder="Enter sell price">
                </div>
                <div style="margin-top:10px;font-size:11px;color:#888">
                    ⚠ A SELL order will be placed in Zerodha. Position status updates automatically on next sync.
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm" id="sqConfirmBtn" onclick="submitSquareOff()">
                    Confirm Square Off
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
var currentTab = 'open';
var _sqPositionId = null;
var _autoTimer = null;

$(document).ready(function() {
    loadPositions();
    startAutoRefresh();
});

function startAutoRefresh() {
    clearInterval(_autoTimer);
    _autoTimer = setInterval(function() {
        var h = new Date().getHours();
        if (h >= 9 && h < 16) loadPositions(true);
    }, 60000);
}

function switchTab(tab, el) {
    currentTab = tab;
    $('.ob-tab').removeClass('active');
    $(el).addClass('active');
    $('#closedFilters').toggle(tab === 'closed');
    loadPositions();
}

function loadPositions(silent) {
    var brokerId = $('#brokerSelect').val();
    if (!brokerId) return;
    if (!silent) $('#posTableBody').html('<tr><td colspan="15" class="ob-empty"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');

    var params = { broker_id: brokerId, tab: currentTab };
    if (currentTab === 'closed') {
        params.from_date = $('#fromDate').val() || '';
        params.to_date   = $('#toDate').val()   || '';
    }

    $.ajax({
        url: '{{ route("oiiv-orders.fetch-positions") }}',
        type: 'GET',
        data: params,
        success: function(res) {
            if (!res.success) { showErr('posTableBody', 15, res.message); return; }
            var d = res.data;

            $('#tabOpenCnt').text(d.counts.open);
            $('#tabClosedCnt').text(d.counts.closed);
            $('#syncedAt').text('Synced: ' + d.synced_at);

            // Summary
            var unrealized = d.total_unrealized_pnl || 0;
            var realized   = d.total_realized_pnl   || 0;
            $('#sumOpen').text(d.counts.open);
            $('#sumUnrealized').text(fmtPnl(unrealized)).addClass(unrealized >= 0 ? 'pnl-pos' : 'pnl-neg');
            $('#sumRealized').text(fmtPnl(realized)).addClass(realized >= 0 ? 'pnl-pos' : 'pnl-neg');

            // Win rate
            var wins  = d.positions.filter(function(p){ return (p.realized_pnl||0) > 0; }).length;
            var total = d.positions.filter(function(p){ return p.status === 'closed'; }).length;
            $('#sumWinRate').text(total > 0 ? Math.round((wins/total)*100) + '%' : '—');
            $('#summaryStrip').show();

            if (!d.positions || d.positions.length === 0) {
                $('#posTableBody').html('<tr><td colspan="15" class="ob-empty"><i class="fas fa-inbox"></i><br>No positions found</td></tr>');
                return;
            }

            var html = '';
            var totalPnl = 0;

            d.positions.forEach(function(p) {
                var isOpen   = p.status === 'open';
                var pnl      = isOpen ? (p.pnl || 0) : (p.pnl || 0);
                var pnlCls   = pnl >= 0 ? 'pnl-pos' : 'pnl-neg';
                var pnlSign  = pnl >= 0 ? '+' : '';
                totalPnl    += pnl;

                var sentBadge = p.sentiment === 'BULLISH'
                    ? '<span class="s-bull">BULL</span>'
                    : p.sentiment === 'BEARISH' ? '<span class="s-bear">BEAR</span>' : '—';
                var otBadge = p.option_type === 'CE'
                    ? '<span class="s-ce">CE</span>'
                    : p.option_type === 'PE' ? '<span class="s-pe">PE</span>' : '—';

                var holdCls = p.holding_days === 0 ? 'today' : '';
                var holdPill = '<span class="holding-pill ' + holdCls + '">' + p.holding_label + '</span>';

                var ltpOrExit = isOpen
                    ? '₹<strong style="color:#667eea">' + Number(p.ltp||p.entry_price).toFixed(2) + '</strong>' +
                      '<br><small style="font-size:9px;color:#aaa">LTP</small>'
                    : '₹<strong>' + (p.exit_price ? Number(p.exit_price).toFixed(2) : '—') + '</strong>' +
                      '<br><small style="font-size:9px;color:#aaa">Exit</small>';

                var statusBadge = isOpen
                    ? '<span class="status-open">🔵 OPEN</span>'
                    : '<span class="status-closed">✅ CLOSED</span>';

                var exitVia = p.exit_source
                    ? '<small style="color:#888">' + p.exit_source.replace(/_/g,' ') + '</small>'
                    : '—';

                var actionBtn = isOpen
                    ? '<button class="sqoff-btn" onclick="openSqOff(' + p.id + ',\'' + p.trading_symbol + '\',' + Number(p.entry_price).toFixed(2) + ',' + Number(p.ltp||0).toFixed(2) + ',' + (p.quantity_units||0) + ',' + Number(p.pnl||0).toFixed(2) + ')">🔴 Square Off</button>'
                    : '—';

                html += '<tr>' +
                    '<td><strong style="color:#2c5364;font-size:11px">' + p.trading_symbol + '</strong>' +
                        '<br><small style="color:#aaa">' + (p.strike_price ? '₹'+p.strike_price : '') + ' ' + (p.expiry_date||'') + '</small></td>' +
                    '<td><small>' + (p.signal_date||'—') + '</small></td>' +
                    '<td>' + sentBadge + '</td>' +
                    '<td>' + otBadge + '</td>' +
                    '<td><small>' + (p.product||'—') + '</small></td>' +
                    '<td><strong>' + (p.quantity||0) + '</strong><small style="color:#aaa"> lots</small></td>' +
                    '<td>₹' + Number(p.entry_price).toFixed(2) + '</td>' +
                    '<td>' + ltpOrExit + '</td>' +
                    '<td class="' + pnlCls + '">' + pnlSign + '₹' + Math.abs(pnl).toFixed(2) + '</td>' +
                    '<td class="' + pnlCls + '">' + pnlSign + Number(p.pnl_percentage||0).toFixed(2) + '%</td>' +
                    '<td>' + holdPill + '</td>' +
                    '<td><small style="color:#888">' + (p.entry_at||'—') + '</small></td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + exitVia + '</td>' +
                    '<td>' + actionBtn + '</td>' +
                    '</tr>';
            });

            // Summary row
            var sumCls = totalPnl >= 0 ? 'pnl-pos' : 'pnl-neg';
            html += '<tr class="sum-row">' +
                '<td colspan="8" style="text-align:right">Total ' + (currentTab === 'open' ? 'Unrealized' : 'Realized') + ' P&L:</td>' +
                '<td class="' + sumCls + '">' + (totalPnl >= 0 ? '+' : '') + '₹' + Math.abs(totalPnl).toFixed(2) + '</td>' +
                '<td colspan="6"></td>' +
                '</tr>';

            $('#posTableBody').html(html);
        },
        error: function() { showErr('posTableBody', 15, 'Network error'); }
    });
}

// ── Square off ────────────────────────────────────────────────────────────
function openSqOff(id, symbol, entry, ltp, units, pnl) {
    _sqPositionId = id;
    $('#sqSymbol').text(symbol);
    $('#sqEntry').text(Number(entry).toFixed(2));
    $('#sqLtp').text(Number(ltp).toFixed(2));
    $('#sqQtyUnits').text(units);
    var pnlHtml = '<span class="' + (pnl >= 0 ? 'pnl-pos' : 'pnl-neg') + '">' + (pnl >= 0 ? '+' : '') + '₹' + Math.abs(pnl).toFixed(2) + '</span>';
    $('#sqPnl').html(pnlHtml);
    $('#sqPrice').val(Number(ltp).toFixed(2));
    $('#sqOrderType').val('MARKET');
    $('#sqPriceWrap').hide();
    new bootstrap.Modal(document.getElementById('sqoffModal')).show();
}

function toggleSqPrice() {
    $('#sqPriceWrap').toggle($('#sqOrderType').val() === 'LIMIT');
}

function submitSquareOff() {
    if (!_sqPositionId) return;
    var orderType = $('#sqOrderType').val();
    var price     = $('#sqPrice').val();
    if (orderType === 'LIMIT' && (!price || parseFloat(price) <= 0)) { alert('Enter a valid limit price'); return; }

    $('#sqConfirmBtn').prop('disabled', true).text('Placing...');
    $.ajax({
        url: '{{ route("oiiv-orders.square-off") }}',
        type: 'POST',
        data: { _token: '{{ csrf_token() }}', position_id: _sqPositionId, order_type: orderType, price: price || null },
        success: function(res) {
            $('#sqConfirmBtn').prop('disabled', false).text('Confirm Square Off');
            bootstrap.Modal.getInstance(document.getElementById('sqoffModal')).hide();
            if (res.success) { iziToast.success({ message: res.message, position: 'topRight', timeout: 5000 }); setTimeout(loadPositions, 3000); }
            else { iziToast.error({ message: res.message, position: 'topRight' }); }
        },
        error: function() { $('#sqConfirmBtn').prop('disabled', false).text('Confirm Square Off'); iziToast.error({ message: 'Network error', position: 'topRight' }); }
    });
}

function syncAndLoad() {
    var brokerId = $('#brokerSelect').val();
    if (!brokerId) return;
    $('#syncIcon').addClass('fa-spin');
    $.ajax({
        url: '{{ route("oiiv-orders.trigger-sync") }}',
        type: 'POST',
        data: { _token: '{{ csrf_token() }}', broker_id: brokerId },
        complete: function() { $('#syncIcon').removeClass('fa-spin'); loadPositions(); }
    });
}

function clearDates() {
    $('#fromDate,#toDate').val('');
    loadPositions();
}

function fmtPnl(v) {
    var n = parseFloat(v) || 0;
    return (n >= 0 ? '+₹' : '-₹') + Math.abs(n).toFixed(2);
}

function showErr(tbody, cols, msg) {
    $('#' + tbody).html('<tr><td colspan="' + cols + '" style="text-align:center;color:#ef4444;padding:30px;">' + msg + '</td></tr>');
}
</script>
@endpush