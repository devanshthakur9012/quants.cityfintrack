@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ── Layout ─────────────────────────────────────────────── */
.ob-header { background:linear-gradient(135deg,#1a1a2e,#16213e); color:white; padding:18px 22px; border-radius:12px; margin-bottom:18px; }
.ob-header h4 { margin:0; font-size:17px; font-weight:700; }
.ob-header p  { margin:0; font-size:11px; opacity:.7; margin-top:4px; }

/* ── Broker bar ─────────────────────────────────────────── */
.broker-bar { background:#f8f9fa; border:1px solid #e9ecef; border-radius:10px; padding:10px 16px; margin-bottom:14px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.broker-bar label { font-size:12px; font-weight:600; color:#555; margin:0; }
.broker-bar select, .broker-bar input[type=date] { font-size:12px; padding:5px 10px; border:1px solid #ddd; border-radius:6px; }

/* ── Live dot ───────────────────────────────────────────── */
.live-dot { width:8px; height:8px; background:#28a745; border-radius:50%; display:inline-block; animation:pulse-dot 2s infinite; }
@keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.7)} }
.sync-info { font-size:11px; color:#888; display:flex; align-items:center; gap:5px; }

/* ── Summary pills ──────────────────────────────────────── */
.sum-pill { background:white; border:1px solid #e9ecef; border-radius:8px; padding:6px 14px; text-align:center; min-width:80px; }
.sum-pill small { display:block; font-size:10px; color:#888; }
.sum-pill strong { display:block; font-size:1.1rem; font-weight:700; margin-top:1px; }

/* ── Tab nav ────────────────────────────────────────────── */
.ob-tabs { display:flex; border-bottom:2px solid #e9ecef; padding:0 16px; }
.ob-tab { padding:10px 20px; font-size:12px; font-weight:600; cursor:pointer; color:#888; border-bottom:3px solid transparent; margin-bottom:-2px; transition:color .15s,border-color .15s; user-select:none; }
.ob-tab:hover { color:#667eea; }
.ob-tab.active { color:#667eea; border-bottom-color:#667eea; }
.ob-tab .cnt { background:#e9ecef; color:#555; font-size:10px; font-weight:700; padding:1px 7px; border-radius:20px; margin-left:5px; }
.ob-tab.active .cnt { background:#667eea; color:white; }

/* ── Table ──────────────────────────────────────────────── */
.ob-table { min-width:1440px; font-size:11px; border-collapse:separate; border-spacing:0; }
.ob-table thead th { background:#f8f9fa; font-size:10px; text-transform:uppercase; letter-spacing:.3px; font-weight:700; color:#555; padding:8px 8px; vertical-align:middle; border-bottom:2px solid #dee2e6; white-space:nowrap; position:sticky; top:0; z-index:5; }
.ob-table tbody td { padding:7px 8px; vertical-align:middle; border-bottom:1px solid #f4f4f4; white-space:nowrap; }
.ob-table tbody tr:hover { background:#fafbff; }

/* ── Status badges ──────────────────────────────────────── */
.st-open    { background:#dbeafe; color:#1d4ed8; padding:2px 9px; border-radius:12px; font-size:10px; font-weight:700; }
.st-complete{ background:#dcfce7; color:#166534; padding:2px 9px; border-radius:12px; font-size:10px; font-weight:700; }
.st-cancel  { background:#f1f5f9; color:#64748b; padding:2px 9px; border-radius:12px; font-size:10px; font-weight:700; }
.st-reject  { background:#fee2e2; color:#991b1b; padding:2px 9px; border-radius:12px; font-size:10px; font-weight:700; }
.st-pending { background:#fef3c7; color:#b45309; padding:2px 9px; border-radius:12px; font-size:10px; font-weight:700; }

/* ── Signal badges ──────────────────────────────────────── */
.s-bull { background:linear-gradient(135deg,#28a745,#20c997); color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.s-bear { background:linear-gradient(135deg,#dc3545,#c82333); color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.s-sell { background:linear-gradient(135deg,#ef4444,#b91c1c); color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.s-ce   { background:#28a745; color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }
.s-pe   { background:#dc3545; color:white; padding:2px 7px; border-radius:4px; font-size:9px; font-weight:700; }

/* ── Square-off / SELL row badge ───────────────────────── */
.sell-sq-badge {
    background: linear-gradient(135deg,#ef4444,#b91c1c);
    color: white;
    font-size: 9px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 3px;
    display: inline-block;
    letter-spacing: .3px;
}

/* ── Price columns ──────────────────────────────────────── */
.price-orig  { color:#666; font-size:10px; }
.price-curr  { font-weight:700; color:#1a1a2e; font-size:11px; }
.price-mod   { color:#f59e0b; font-weight:700; font-size:10px; }
.price-avg   { color:#166534; font-weight:700; font-size:11px; }
.mod-badge   { background:#fef3c7; color:#b45309; border:1px solid #fcd34d; font-size:9px; font-weight:700; padding:1px 6px; border-radius:8px; margin-left:3px; }

/* ── LTP column ─────────────────────────────────────────── */
.ltp-cell    { font-weight:700; font-size:11px; color:#667eea; }
.ltp-na      { color:#ccc; font-size:10px; }
.ltp-up      { color:#166534; }
.ltp-down    { color:#991b1b; }
.ltp-ts      { display:block; font-size:9px; color:#bbb; font-weight:400; margin-top:1px; }

/* smooth LTP update flash */
@keyframes ltp-flash { 0%{background:#e8f5e9} 100%{background:transparent} }
.ltp-flashing { animation: ltp-flash .6s ease-out; }

/* ── Action buttons ─────────────────────────────────────── */
.btn-modify { font-size:10px; padding:3px 10px; border-radius:4px; background:#f59e0b; color:white; border:none; cursor:pointer; }
.btn-cancel { font-size:10px; padding:3px 10px; border-radius:4px; background:#ef4444; color:white; border:none; cursor:pointer; }
.btn-modify:hover { background:#d97706; }
.btn-cancel:hover { background:#dc2626; }
.btn-modify:disabled,.btn-cancel:disabled { opacity:.35; cursor:not-allowed; }

/* ── Chunk badge ────────────────────────────────────────── */
.chunk-badge { background:#e0e7ff; color:#3730a3; font-size:9px; padding:1px 6px; border-radius:10px; font-weight:600; }

/* ── Empty state ────────────────────────────────────────── */
.ob-empty { text-align:center; padding:60px 20px; color:#aaa; }
.ob-empty i { font-size:2.5rem; display:block; margin-bottom:10px; }

/* ── Highlight column headers ───────────────────────────── */
.th-price { background:rgba(102,126,234,.06) !important; }
.th-ltp   { background:rgba(40,167,69,.06)   !important; }
</style>
@endpush

<section class="pt-40 pb-60">
<div class="container-fluid content-container">

    {{-- Header --}}
    <div class="ob-header d-flex justify-content-between align-items-center">
        <div>
            <h4><i class="fas fa-list-alt"></i> OIIV Order Book</h4>
            <p>Real-time order tracking — LTP updates every 15 s &nbsp;|&nbsp; Status syncs every 30 s</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('oiiv-orders.positions') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-briefcase"></i> Positions</a>
            <a href="{{ route('oiiv-auto.pece-analysis') }}" class="btn btn-outline-light btn-sm"><i class="fas fa-chart-bar"></i> Analysis</a>
        </div>
    </div>

    {{-- Broker + controls bar --}}
    <div class="broker-bar">
        <label>Broker:</label>
        <select id="brokerSelect" onchange="hardReload()">
            @foreach($brokers as $b)
                <option value="{{ $b->id }}">{{ $b->client_name }} ({{ $b->account_user_name }})</option>
            @endforeach
        </select>

        <label>Date:</label>
        <input type="date" id="dateFilter" value="{{ date('Y-m-d') }}" onchange="hardReload()">

        <button class="btn btn-sm btn-primary" style="font-size:11px" onclick="manualSync()">
            <i class="fas fa-sync" id="syncIcon"></i> Sync Now
        </button>

        <div class="sync-info">
            <span class="live-dot" id="liveDot"></span>
            <span id="syncedAt">—</span>
        </div>

        <div class="ms-auto d-flex gap-2 flex-wrap">
            <div class="sum-pill"><small>Open</small><strong id="cntOpen" style="color:#1d4ed8">0</strong></div>
            <div class="sum-pill"><small>Executed</small><strong id="cntExecuted" style="color:#166534">0</strong></div>
            <div class="sum-pill"><small>Cancelled</small><strong id="cntCancelled" style="color:#64748b">0</strong></div>
            <div class="sum-pill" style="border-left:3px solid #a855f7"><small>Investment</small><strong id="sumInvestment" style="color:#a855f7;font-size:1rem">₹0</strong></div>
        </div>
    </div>

    {{-- Table card --}}
    <div style="background:white;border:1px solid #e9ecef;border-radius:10px;overflow:hidden;">

        {{-- Tab nav --}}
        <div class="ob-tabs" style="padding-top:8px;">
            <div class="ob-tab active" onclick="switchTab('all',this)">All <span class="cnt" id="tabAllCnt">0</span></div>
            <div class="ob-tab" onclick="switchTab('open',this)">Open <span class="cnt" id="tabOpenCnt">0</span></div>
            <div class="ob-tab" onclick="switchTab('executed',this)">Executed <span class="cnt" id="tabExecutedCnt">0</span></div>
            <div class="ob-tab" onclick="switchTab('cancelled',this)">Cancelled <span class="cnt" id="tabCancelledCnt">0</span></div>
        </div>

        <div style="overflow-x:auto;">
            <table class="table ob-table mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Symbol</th>
                        <th>Signal</th>
                        <th>Type</th>
                        <th>Lots</th>
                        <th>Units</th>
                        {{-- Price group --}}
                        <th class="th-price">Trigger ₹</th>
                        <th class="th-price">Original ₹<br><span style="font-weight:400;opacity:.7;text-transform:none;">at placement</span></th>
                        <th class="th-price">Current ₹<br><span style="font-weight:400;opacity:.7;text-transform:none;">placed / modified</span></th>
                        <th class="th-price">Avg Fill ₹</th>
                        {{-- LTP --}}
                        <th class="th-ltp">LTP ₹ <span class="live-dot" style="margin-left:3px;"></span></th>
                        {{-- Execution --}}
                        <th>Filled</th>
                        <th>Status</th>
                        <th>Chunk</th>
                        <th>Zerodha ID</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <tr><td colspan="16" class="ob-empty"><i class="fas fa-spinner fa-spin"></i></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
</section>

{{-- Modify Order Modal --}}
<div class="modal fade" id="modifyModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header" style="background:#f59e0b;padding:12px 16px;">
                <h6 class="modal-title text-white mb-0">✏️ Modify Order</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size:12px;">
                <div style="background:#fef3c7;border-radius:6px;padding:8px 10px;margin-bottom:10px;">
                    <strong id="modSymbol">—</strong><br>
                    <span>Original: ₹<strong id="modOrigPrice">—</strong></span><br>
                    <span>Current:  ₹<strong id="modCurrPrice">—</strong></span><br>
                    <span>LTP:      ₹<strong id="modLtp">—</strong></span>
                </div>
                <label style="font-size:11px;font-weight:600;">New Price</label>
                <input type="number" id="modifyNewPrice" class="form-control form-control-sm mb-2" step="0.05" min="0.05">
                <label style="font-size:11px;font-weight:600;">Order Type</label>
                <select id="modifyOrderType" class="form-control form-control-sm">
                    <option value="LIMIT">LIMIT</option>
                    <option value="MARKET">MARKET</option>
                </select>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" id="modifyConfirmBtn" onclick="submitModify()">Modify</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
// ─────────────────────────────────────────────────────────────────────────────
//  STATE
// ─────────────────────────────────────────────────────────────────────────────
var currentTab       = 'all';
var ordersData       = {};     // id → order object (for LTP merge)
var _modifyOrderId   = null;
var _fullReloadTimer = null;
var _ltpTimer        = null;
var _isFullLoading   = false;

// ─────────────────────────────────────────────────────────────────────────────
//  INIT
// ─────────────────────────────────────────────────────────────────────────────
$(document).ready(function() {
    hardReload();
    startPolling();
});

// ─────────────────────────────────────────────────────────────────────────────
//  POLLING SETUP
//  • Full table reload   every 30 s (smooth — no flash if data unchanged)
//  • LTP-only update     every 15 s (only updates LTP cells in-place)
// ─────────────────────────────────────────────────────────────────────────────
function startPolling() {
    clearInterval(_fullReloadTimer);
    clearInterval(_ltpTimer);

    // Silent full reload every 30 s
    _fullReloadTimer = setInterval(function() {
        if (!_isFullLoading) silentReload();
    }, 30000);

    // LTP-only poll every 15 s
    _ltpTimer = setInterval(function() {
        pollLtps();
    }, 15000);
}

function stopPolling() {
    clearInterval(_fullReloadTimer);
    clearInterval(_ltpTimer);
}

// ─────────────────────────────────────────────────────────────────────────────
//  HARD RELOAD — full table rebuild (on tab switch / date change / broker change)
// ─────────────────────────────────────────────────────────────────────────────
function hardReload() {
    _isFullLoading = true;
    $('#ordersTableBody').html('<tr><td colspan="16" class="ob-empty"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>');
    loadOrders(function() { _isFullLoading = false; });
}

// ─────────────────────────────────────────────────────────────────────────────
//  SILENT RELOAD — rebuild table without clearing it first (no flicker)
// ─────────────────────────────────────────────────────────────────────────────
function silentReload() {
    loadOrders(null, true);
}

// ─────────────────────────────────────────────────────────────────────────────
//  CORE LOAD ORDERS
// ─────────────────────────────────────────────────────────────────────────────
function loadOrders(callback, silent) {
    var brokerId = $('#brokerSelect').val();
    var date     = $('#dateFilter').val();
    if (!brokerId) return;

    $.ajax({
        url:  '{{ route("oiiv-orders.fetch-orders") }}',
        type: 'GET',
        data: { broker_id: brokerId, tab: currentTab, date: date },
        success: function(res) {
            if (!res.success) { if (!silent) showErr('ordersTableBody',16,res.message); return; }
            var d = res.data;

            // Update counts
            $('#cntOpen').text(d.counts.open);
            $('#cntExecuted').text(d.counts.executed);
            $('#cntCancelled').text(d.counts.cancelled);
            $('#sumInvestment').text('₹' + Number(d.total_investment||0).toLocaleString('en-IN',{maximumFractionDigits:0}));
            $('#tabAllCnt').text(d.counts.all);
            $('#tabOpenCnt').text(d.counts.open);
            $('#tabExecutedCnt').text(d.counts.executed);
            $('#tabCancelledCnt').text(d.counts.cancelled);
            $('#syncedAt').text('Synced ' + d.synced_at);

            // Cache orders by id for LTP merge
            ordersData = {};
            (d.orders || []).forEach(function(o) { ordersData[o.id] = o; });

            if (!d.orders || d.orders.length === 0) {
                $('#ordersTableBody').html('<tr><td colspan="16" class="ob-empty"><i class="fas fa-inbox"></i><br>No orders found</td></tr>');
                callback && callback();
                return;
            }

            renderTable(d.orders);
            callback && callback();
        },
        error: function() { if (!silent) showErr('ordersTableBody',16,'Network error'); callback && callback(); }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  RENDER TABLE
// ─────────────────────────────────────────────────────────────────────────────
function renderTable(orders) {
    var html = '';
    orders.forEach(function(o) {
        // ── Detect square-off / SELL orders ─────────────────────────
        var isSell    = (o.transaction_type === 'SELL');
        var isSquareOff = (o.signal_type === 'SQUARE_OFF');

        var sentBadge = isSell
            ? '<span class="s-sell">SELL</span>'
            : (o.sentiment === 'BULLISH' ? '<span class="s-bull">BULL</span>'
              : o.sentiment === 'BEARISH'  ? '<span class="s-bear">BEAR</span>' : '—');
        var otBadge   = o.option_type === 'CE' ? '<span class="s-ce">CE</span>'
                      : o.option_type === 'PE'  ? '<span class="s-pe">PE</span>' : '—';

        // ── Original price cell ──────────────────────────────────────
        var origCell = o.original_placed_price
            ? '<span class="price-orig">₹' + o.original_placed_price.toFixed(2) + '</span>'
            : '<span class="price-orig">₹' + (o.placed_price||0).toFixed(2) + '</span>';

        // ── Current (possibly modified) price cell ───────────────────
        // If modified: show last_modified_price as the active price (what's on exchange now)
        // If not modified: show placed_price (original)
        var activePrice = (o.was_modified && o.last_modified_price)
            ? Number(o.last_modified_price)
            : Number(o.placed_price || 0);

        var currCell;
        if (o.was_modified && o.last_modified_price) {
            // Modified order: show current modified price prominently
            currCell = '<span class="price-curr">₹' + activePrice.toFixed(2) + '</span>'
                + ' <span class="mod-badge">Modified ' + o.modify_count + '×</span>';
        } else {
            // Unmodified order: just show placed price
            currCell = '<span class="price-curr">₹' + activePrice.toFixed(2) + '</span>';
        }

        // ── Avg fill cell ────────────────────────────────────────────
        var avgCell = o.average_price
            ? '<span class="price-avg">₹' + o.average_price.toFixed(2) + '</span>'
            : '<span style="color:#ccc">—</span>';

        // ── LTP cell (will be updated by pollLtps) ───────────────────
        var ltpCell = o.current_ltp
            ? '<span class="ltp-cell" id="ltp-val-' + o.id + '">₹' + o.current_ltp.toFixed(2) + '</span><span class="ltp-ts" id="ltp-ts-' + o.id + '">' + (o.ltp_updated_at||'') + '</span>'
            : '<span class="ltp-na" id="ltp-val-' + o.id + '">—</span><span class="ltp-ts" id="ltp-ts-' + o.id + '"></span>';

        // ── Chunk badge ──────────────────────────────────────────────
        var chunkBadge = o.lot_chunk_total > 1
            ? '<span class="chunk-badge">' + o.lot_chunk_number + '/' + o.lot_chunk_total + '</span>'
            : '<span style="color:#ddd">—</span>';

        var canModify = o.is_live;
        var canCancel = o.is_live;

        // Row background: SELL/square-off gets a subtle red tint
        var rowStyle = isSell
            ? 'style="background:rgba(239,68,68,0.04);border-left:3px solid #ef4444;"'
            : '';

        // Symbol cell label: show SELL badge for square-off orders
        var sellLabel = isSquareOff
            ? '<span class="sell-sq-badge">↩ SQUARE OFF</span>'
            : (isSell ? '<span class="sell-sq-badge">↩ SELL</span>' : '');

        html += '<tr id="order-row-' + o.id + '" ' + rowStyle + '>' +
            '<td><small style="color:#888">' + (o.placed_at||'—') + '</small></td>' +
            '<td>' +
                (sellLabel ? sellLabel + '<br>' : '') +
                '<strong style="color:' + (isSell ? '#ef4444' : '#667eea') + ';font-size:11px">' + o.trading_symbol + '</strong>' +
                '<br><small style="color:#bbb;font-size:9px">' + (o.strike_price ? '₹'+o.strike_price : '') + ' ' + (o.expiry_date||'') + '</small>' +
            '</td>' +
            '<td>' + sentBadge + '</td>' +
            '<td>' + otBadge + ' <small style="color:#aaa">' + (o.product||'') + '</small></td>' +
            '<td><strong style="color:#000;">' + (o.quantity||0) + '</strong></td>' +
            '<td style="color:#888">' + (o.quantity_units||'—') + '</td>' +
            '<td class="th-price"><span class="price-orig">₹' + (o.trigger_price ? Number(o.trigger_price).toFixed(2) : '—') + '</span></td>' +
            '<td class="th-price">' + origCell + '</td>' +
            '<td class="th-price">' + currCell + '</td>' +
            '<td class="th-price">' + avgCell + '</td>' +
            '<td class="th-ltp" id="ltp-cell-' + o.id + '">' + ltpCell + '</td>' +
            '<td>' + (o.filled_quantity > 0 ? '<strong style="color:#166534">' + o.filled_quantity + '</strong>' : '<span style="color:#ccc">0</span>') + '</td>' +
            '<td>' + statusBadge(o.status) + '</td>' +
            '<td>' + chunkBadge + '</td>' +
            '<td><small style="color:#aaa;font-size:9px">' + (o.zerodha_order_id||'—') + '</small></td>' +
            '<td>' +
                '<button class="btn-modify me-1" ' + (!canModify ? 'disabled' : '') + ' onclick="openModify(' + o.id + ')">' +
                    '✏ Modify' +
                '</button>' +
                '<button class="btn-cancel" ' + (!canCancel ? 'disabled' : '') + ' onclick="cancelOrder(' + o.id + ',\'' + o.trading_symbol + '\')">' +
                    '✕ Cancel' +
                '</button>' +
            '</td>' +
            '</tr>';
    });

    $('#ordersTableBody').html(html);
}

// ─────────────────────────────────────────────────────────────────────────────
//  LTP-ONLY POLL — updates only LTP cells, no table rebuild
// ─────────────────────────────────────────────────────────────────────────────
function pollLtps() {
    var brokerId = $('#brokerSelect').val();
    if (!brokerId) return;

    $.ajax({
        url:  '{{ route("oiiv-orders.fetch-ltps") }}',
        type: 'GET',
        data: { broker_id: brokerId },
        success: function(res) {
            if (!res.success) return;

            $('#syncedAt').text('LTP ' + res.server_ts);

            $.each(res.ltps, function(orderId, data) {
                var ltpValEl = $('#ltp-val-' + orderId);
                var ltpTsEl  = $('#ltp-ts-' + orderId);

                if (!ltpValEl.length) return;

                var ltp    = data.ltp;
                var cached = ordersData[orderId];
                var prevLtp = cached ? cached.current_ltp : null;

                if (ltp !== null && ltp !== undefined) {
                    // Determine direction vs previous LTP
                    var cls = 'ltp-cell';
                    if (prevLtp !== null && prevLtp !== undefined) {
                        if (ltp > prevLtp)       cls = 'ltp-cell ltp-up';
                        else if (ltp < prevLtp)  cls = 'ltp-cell ltp-down';
                    }
                    ltpValEl.attr('class', cls).text('₹' + ltp.toFixed(2));
                    ltpTsEl.text(data.ltp_at || '');

                    // Flash the cell on change
                    if (prevLtp !== null && ltp !== prevLtp) {
                        var cell = $('#ltp-cell-' + orderId);
                        cell.addClass('ltp-flashing');
                        setTimeout(function() { cell.removeClass('ltp-flashing'); }, 700);
                    }

                    // Update cache
                    if (cached) cached.current_ltp = ltp;
                }

                // Also update current placed_price / modify info if changed
                if (data.last_modified_price && cached) {
                    cached.last_modified_price = data.last_modified_price;
                }
            });
        }
        // silent fail on error — don't disturb UI
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  TAB SWITCH
// ─────────────────────────────────────────────────────────────────────────────
function switchTab(tab, el) {
    currentTab = tab;
    $('.ob-tab').removeClass('active');
    $(el).addClass('active');
    hardReload();
}

// ─────────────────────────────────────────────────────────────────────────────
//  MANUAL SYNC
// ─────────────────────────────────────────────────────────────────────────────
function manualSync() {
    var brokerId = $('#brokerSelect').val();
    if (!brokerId) return;
    $('#syncIcon').addClass('fa-spin');
    $.ajax({
        url:  '{{ route("oiiv-orders.trigger-sync") }}',
        type: 'POST',
        data: { _token: '{{ csrf_token() }}', broker_id: brokerId },
        success: function(res) {
            $('#syncIcon').removeClass('fa-spin');
            if (res.success) { silentReload(); pollLtps(); }
        },
        error: function() { $('#syncIcon').removeClass('fa-spin'); }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  MODIFY
// ─────────────────────────────────────────────────────────────────────────────
function openModify(id) {
    _modifyOrderId = id;
    var o = ordersData[id];
    if (!o) return;

    $('#modSymbol').text(o.trading_symbol);
    $('#modOrigPrice').text(o.original_placed_price ? o.original_placed_price.toFixed(2) : (o.placed_price||0).toFixed(2));
    $('#modCurrPrice').text((o.placed_price||0).toFixed(2));
    $('#modLtp').text(o.current_ltp ? o.current_ltp.toFixed(2) : 'N/A');
    // Pre-fill with last modified price (if modified before), else original placed price.
    // Do NOT pre-fill with LTP — user should consciously choose the new price.
    var prefillPrice = o.last_modified_price
        ? Number(o.last_modified_price).toFixed(2)
        : (o.placed_price ? Number(o.placed_price).toFixed(2) : '0.00');
    $('#modifyNewPrice').val(prefillPrice);
    $('#modifyOrderType').val('LIMIT');
    $('#modifyConfirmBtn').prop('disabled', false).text('Modify');
    new bootstrap.Modal(document.getElementById('modifyModal')).show();
}

function submitModify() {
    if (!_modifyOrderId) return;
    var newPrice = $('#modifyNewPrice').val();
    if (!newPrice || parseFloat(newPrice) <= 0) { alert('Enter a valid price'); return; }

    $('#modifyConfirmBtn').prop('disabled', true).text('Modifying...');
    $.ajax({
        url:  '{{ route("oiiv-orders.modify-order") }}',
        type: 'POST',
        data: { _token: '{{ csrf_token() }}', order_id: _modifyOrderId, new_price: newPrice, order_type: $('#modifyOrderType').val() },
        success: function(res) {
            $('#modifyConfirmBtn').prop('disabled', false).text('Modify');
            bootstrap.Modal.getInstance(document.getElementById('modifyModal')).hide();
            if (res.success) {
                iziToast.success({ message: res.message, position: 'topRight' });
                // Update the cached order data immediately
                if (res.order && ordersData[_modifyOrderId]) {
                    var updated = res.order;
                    ordersData[_modifyOrderId] = Object.assign(ordersData[_modifyOrderId], updated);
                    // Re-render just that row by doing a silent full reload
                    silentReload();
                }
            } else {
                iziToast.error({ message: res.message, position: 'topRight' });
            }
        },
        error: function() {
            $('#modifyConfirmBtn').prop('disabled', false).text('Modify');
            iziToast.error({ message: 'Network error', position: 'topRight' });
        }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  CANCEL
// ─────────────────────────────────────────────────────────────────────────────
function cancelOrder(id, symbol) {
    if (!confirm('Cancel order for ' + symbol + '?')) return;
    $.ajax({
        url:  '{{ route("oiiv-orders.cancel-order") }}',
        type: 'POST',
        data: { _token: '{{ csrf_token() }}', order_id: id },
        success: function(res) {
            if (res.success) { iziToast.success({ message: res.message, position: 'topRight' }); silentReload(); }
            else              { iziToast.error({ message: res.message, position: 'topRight' }); }
        },
        error: function() { iziToast.error({ message: 'Network error', position: 'topRight' }); }
    });
}

// ─────────────────────────────────────────────────────────────────────────────
//  HELPERS
// ─────────────────────────────────────────────────────────────────────────────
function statusBadge(st) {
    if (!st) return '<span class="st-pending">—</span>';
    var s = (st+'').toUpperCase();
    if (s === 'COMPLETE')        return '<span class="st-complete">✅ EXECUTED</span>';
    if (s === 'OPEN')            return '<span class="st-open">🔵 OPEN</span>';
    if (s === 'TRIGGER_PENDING') return '<span class="st-pending">⏳ PENDING</span>';
    if (s === 'CANCELLED')       return '<span class="st-cancel">❌ CANCELLED</span>';
    if (s === 'REJECTED')        return '<span class="st-reject">🚫 REJECTED</span>';
    return '<span class="st-pending">' + st + '</span>';
}

function showErr(tbody, cols, msg) {
    $('#' + tbody).html('<tr><td colspan="' + cols + '" style="text-align:center;color:#ef4444;padding:30px;">' + msg + '</td></tr>');
}
</script>
@endpush