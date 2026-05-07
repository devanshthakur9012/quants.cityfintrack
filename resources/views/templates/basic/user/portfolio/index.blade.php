@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">

        <div class="custom--card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-1"><i class="las la-briefcase"></i> Portfolio & Positions</h5>
                    <p class="text-muted small mb-0">Live positions synced from Zerodha</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <small class="text-muted" id="lastSyncBadge" style="display:none">
                        <i class="las la-clock"></i> <span id="lastSyncTime">--</span>
                    </small>
                    <button class="btn btn--base btn-sm" onclick="refreshCurrentTab()">
                        <i class="las la-sync" id="refreshIcon"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                @if($brokers->isEmpty())
                    <div class="alert alert--danger">
                        <i class="las la-exclamation-triangle"></i>
                        <strong>No Active Brokers Found!</strong>
                        <a href="{{ route('zerodha-broker.index') }}" class="ms-2">Go to Brokers</a>
                    </div>
                @else
                    <div class="row align-items-end g-3">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label fw-semibold">Select Broker</label>
                            <select class="form--control" id="brokerSelect" onchange="onBrokerChange()">
                                @foreach($brokers as $broker)
                                    <option value="{{ $broker->id }}">{{ $broker->client_name }} ({{ $broker->account_user_name }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-8 col-md-6" id="quickPnlBar" style="display:none">
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="pnl-pill">
                                    <small class="text-muted d-block">Open MTM</small>
                                    <strong id="headerMtm" class="fs-6">₹0.00</strong>
                                </div>
                                <div class="pnl-pill">
                                    <small class="text-muted d-block">Today Booked</small>
                                    <strong id="headerBooked" class="fs-6">₹0.00</strong>
                                </div>
                                <div class="pnl-pill">
                                    <small class="text-muted d-block">Combined</small>
                                    <strong id="headerCombined" class="fs-6">₹0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="row mb-4" id="summaryCards" style="display:none">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="custom--card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="summary-icon bg--base"><i class="las la-layer-group text-white fs-4"></i></div>
                        <div>
                            <div class="fs-4 fw-bold" id="sumOpenCount">0</div>
                            <small class="text-muted">Open Positions</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="custom--card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="summary-icon bg--success"><i class="las la-chart-line text-white fs-4"></i></div>
                        <div>
                            <div class="fs-5 fw-bold" id="sumTotalPnl">₹0.00</div>
                            <small class="text-muted">Unrealized P&L</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="custom--card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="summary-icon bg--success"><i class="las la-arrow-circle-up text-white fs-4"></i></div>
                        <div>
                            <div class="fs-4 fw-bold text--success" id="sumWinCount">0</div>
                            <small class="text-muted">In Profit</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="custom--card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="summary-icon bg--danger"><i class="las la-arrow-circle-down text-white fs-4"></i></div>
                        <div>
                            <div class="fs-4 fw-bold text--danger" id="sumLossCount">0</div>
                            <small class="text-muted">In Loss</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Card with Tabs --}}
        <div class="custom--card">
            <div class="card-body p-0">

                {{-- Tab Nav --}}
                <div class="portfolio-tabs border-bottom px-3 pt-3">
                    <ul class="nav nav-tabs border-0" id="portfolioTabNav">
                        <li class="nav-item">
                            <a class="nav-link active px-4" href="#" onclick="switchTab('open',this);return false;">
                                <i class="las la-folder-open me-1"></i> Open
                                <span class="badge badge--base ms-1" id="tabOpenCount">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link px-4" href="#" onclick="switchTab('closed',this);return false;">
                                <i class="las la-check-circle me-1"></i> Closed
                                <span class="badge badge--secondary ms-1" id="tabClosedCount">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link px-4" href="#" onclick="switchTab('today',this);return false;">
                                <i class="las la-calendar-day me-1"></i> Today
                            </a>
                        </li>
                    </ul>
                </div>

                {{-- ── OPEN PANE ── --}}
                <div id="paneOpen">
                    <div class="px-3 py-2 border-bottom bg-light d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2">
                            <label class="mb-0 text-muted small fw-semibold">Date:</label>
                            <select id="purchaseDateFilter" class="form--control form-control-sm" style="min-width:160px" onchange="loadOpenPositions()">
                                <option value="">All Dates</option>
                            </select>
                        </div>
                        <small class="text-muted ms-auto" id="openUpdatedAt"></small>
                    </div>

                    <div id="openLoading" class="text-center py-5" style="display:none">
                        <div class="spinner-border text--base"></div>
                        <p class="mt-2 text-muted small">Syncing from Zerodha...</p>
                    </div>

                    <div class="table-responsive">
                        <table class="table custom--table mb-0">
                            <thead>
                                <tr>
                                    <th>Symbol</th>
                                    <th class="text-center">Type</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Entry Price</th>
                                    <th class="text-end">LTP <span class="badge badge--success" style="font-size:9px">LIVE</span></th>
                                    <th class="text-end">Unrealized P&L</th>
                                    <th class="text-end">P&L %</th>
                                    <th class="text-center">Holding</th>
                                    <th class="text-center">Since</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="openTableBody">
                                <tr><td colspan="10" class="text-center py-5 text-muted">Loading positions...</td></tr>
                            </tbody>
                            <tfoot id="openTableFoot" style="display:none">
                                <tr class="fw-bold table-secondary">
                                    <td colspan="5" class="text-end">Total Unrealized P&L:</td>
                                    <td class="text-end" id="openTotalPnl">₹0.00</td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- ── CLOSED PANE ── --}}
                <div id="paneClosed" style="display:none">
                    <div class="px-3 py-2 border-bottom bg-light d-flex align-items-center gap-3 flex-wrap">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <label class="mb-0 text-muted small fw-semibold">From:</label>
                            <input type="date" id="closedFrom" class="form--control form-control-sm" style="width:auto" onchange="loadClosedPositions()">
                            <label class="mb-0 text-muted small fw-semibold">To:</label>
                            <input type="date" id="closedTo" class="form--control form-control-sm" style="width:auto" onchange="loadClosedPositions()">
                            <button class="btn btn-sm btn--secondary" onclick="clearClosedFilter()">Clear</button>
                        </div>
                        <div class="ms-auto d-flex gap-2 flex-wrap">
                            <span class="badge badge--success">Win: <span id="closedWin">0</span></span>
                            <span class="badge badge--danger">Loss: <span id="closedLoss">0</span></span>
                            <span class="badge badge--base">Realized: <span id="closedRealized">₹0</span></span>
                        </div>
                    </div>
                    <div id="closedLoading" class="text-center py-5" style="display:none">
                        <div class="spinner-border text--base"></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table custom--table mb-0">
                            <thead>
                                <tr>
                                    <th>Symbol</th>
                                    <th class="text-center">Type</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Entry Price</th>
                                    <th class="text-end">Exit Price</th>
                                    <th class="text-end">Realized P&L</th>
                                    <th class="text-center">Held For</th>
                                    <th class="text-center">Opened</th>
                                    <th class="text-center">Closed</th>
                                    <th class="text-center">Via</th>
                                </tr>
                            </thead>
                            <tbody id="closedTableBody">
                                <tr><td colspan="10" class="text-center py-5 text-muted">Click Closed tab to load history</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- ── TODAY PANE ── --}}
                <div id="paneToday" style="display:none" class="p-3">
                    <div class="row mb-4 g-3">
                        <div class="col-md-4">
                            <div class="today-card border-start border-4 border-success ps-3 py-2">
                                <small class="text-muted d-block">Booked P&L Today</small>
                                <div class="fs-4 fw-bold" id="todayBooked">₹0.00</div>
                                <small class="text-muted">Positions closed today</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="today-card border-start border-4 border-warning ps-3 py-2">
                                <small class="text-muted d-block">MTM (Open)</small>
                                <div class="fs-4 fw-bold" id="todayMtm">₹0.00</div>
                                <small class="text-muted">Positions opened today</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="today-card border-start border-4 border-primary ps-3 py-2">
                                <small class="text-muted d-block">Combined P&L</small>
                                <div class="fs-4 fw-bold" id="todayCombined">₹0.00</div>
                                <small class="text-muted">Booked + MTM</small>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="custom--card h-100">
                                <div class="card-header"><h6 class="mb-0"><i class="las la-plus-circle text--success"></i> Opened Today</h6></div>
                                <div class="table-responsive">
                                    <table class="table custom--table table-sm mb-0">
                                        <thead><tr><th>Symbol</th><th class="text-end">Qty</th><th class="text-end">Entry</th><th class="text-end">LTP</th><th class="text-end">MTM</th></tr></thead>
                                        <tbody id="todayOpenedBody"><tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="custom--card h-100">
                                <div class="card-header"><h6 class="mb-0"><i class="las la-lock text--danger"></i> Closed Today</h6></div>
                                <div class="table-responsive">
                                    <table class="table custom--table table-sm mb-0">
                                        <thead><tr><th>Symbol</th><th class="text-end">Qty</th><th class="text-end">Entry</th><th class="text-end">Exit</th><th class="text-end">P&L</th></tr></thead>
                                        <tbody id="todayClosedBody"><tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

{{-- Exit Modal --}}
<div class="modal fade" id="exitModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg--danger">
                <h5 class="modal-title text-white"><i class="las la-times-circle"></i> Square Off Position</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert--info mb-4">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block">Symbol</small>
                            <strong id="exitSymbol" class="fs-6">-</strong>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block">Type</small>
                            <span id="exitTypeBadge" class="badge">-</span>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block">Product</small>
                            <strong id="exitProduct">-</strong>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block">Available Qty</small>
                            <strong id="exitAvailableQty">-</strong>
                        </div>
                    </div>
                    <hr class="my-2">
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block">Entry Price</small>
                            <strong class="text--primary">₹<span id="exitEntryPrice">0.00</span></strong>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block">Current LTP</small>
                            <strong class="text--base">₹<span id="exitLTP">0.00</span></strong>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block">Current P&L</small>
                            <strong id="exitCurrentPnl">₹0.00</strong>
                        </div>
                        <div class="col-md-3 col-6">
                            <small class="text-muted d-block">P&L %</small>
                            <strong id="exitPnlPct">0.00%</strong>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Quantity to Exit <sup class="text--danger">*</sup></label>
                        <input type="number" class="form--control" id="exitQtyInput" min="1" required>
                        <small class="text-muted">Max: <span id="exitMaxQty">0</span></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Order Type <sup class="text--danger">*</sup></label>
                        <select class="form--control" id="exitOrderType" onchange="toggleExitPrice()">
                            <option value="MARKET">MARKET (Instant execution)</option>
                            <option value="LIMIT">LIMIT (Set your price)</option>
                        </select>
                    </div>
                </div>

                <div class="mt-3 p-3 rounded" style="background:#f0fdf4;border:1px solid #86efac">
                    <h6 class="mb-3"><i class="las la-calculator text--success"></i> Target Price Calculator</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small">Target Profit %</label>
                            <input type="number" class="form--control" id="targetPct" step="0.1" min="0" placeholder="e.g. 10">
                            <small class="text-muted">Enter % → auto-calculate sell price</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Calculated Sell Price</label>
                            <input type="text" class="form--control" id="calcSellPrice" readonly placeholder="Auto-calculated">
                            <small class="text-muted">Based on entry price + profit %</small>
                        </div>
                    </div>
                    <div class="mt-2" id="useCalcPriceWrap" style="display:none">
                        <button type="button" class="btn btn--success btn-sm" onclick="applyCalcPrice()">
                            <i class="las la-check"></i> Use Calculated Price (₹<span id="calcPriceVal">0</span>)
                        </button>
                    </div>
                </div>

                <div class="mt-3" id="limitPriceWrap" style="display:none">
                    <label class="form-label fw-semibold">Limit Price <sup class="text--danger">*</sup></label>
                    <input type="number" class="form--control" id="exitLimitPrice" step="0.05" placeholder="Enter limit price">
                    <small class="text-muted">LTP: ₹<span id="ltpHint">0</span> | Entry: ₹<span id="entryHint">0</span></small>
                </div>

                <div class="alert alert--warning mt-3 mb-0">
                    <i class="las la-exclamation-triangle"></i>
                    <span id="exitWarningMsg">Orders will be split into chunks of 20 qty automatically.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="las la-times"></i> Cancel</button>
                <button type="button" class="btn btn--danger" id="exitConfirmBtn" onclick="confirmExit()">
                    <i class="las la-check"></i> Confirm Square Off
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('style')
<style>
.summary-icon { width:48px;height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.pnl-pill { background:#f8f9fa;border:1px solid #e9ecef;border-radius:8px;padding:6px 14px; }
.today-card { background:#fff;border-radius:8px; }
.portfolio-tabs .nav-link { color:#6c757d;border:none;border-bottom:2px solid transparent;border-radius:0; }
.portfolio-tabs .nav-link.active { color:var(--base-color);border-bottom-color:var(--base-color);font-weight:600; }
.portfolio-tabs .nav-link:hover { color:var(--base-color); }
.badge--base    { background:var(--base-color);color:#fff; }
.badge--success { background:#10b981;color:#fff; }
.badge--danger  { background:#ef4444;color:#fff; }
.badge--secondary { background:#6c757d;color:#fff; }
.text--success { color:#10b981!important; }
.text--danger  { color:#ef4444!important; }
/* .text--base    { color:var(--base-color)!important; } */
.text--primary { color:#3b82f6!important; }
.bg--success   { background:#10b981!important; }
.bg--danger    { background:#ef4444!important; }
.bg--base      { background:var(--base-color)!important; }
.alert--info    { background:#dbeafe;border:1px solid #93c5fd;color:#1e40af;border-radius:8px;padding:14px; }
.alert--warning { background:#fef3c7;border:1px solid #fcd34d;color:#92400e;border-radius:8px;padding:12px; }
.alert--danger  { background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:12px; }
.btn--danger  { background:linear-gradient(135deg,#ef4444,#dc2626);border:none;color:#fff; }
.btn--danger:hover { background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff; }
.btn--success { background:linear-gradient(135deg,#10b981,#059669);border:none;color:#fff; }
.btn--success:hover { background:linear-gradient(135deg,#059669,#047857);color:#fff; }
.btn--secondary { background:#6c757d;border:none;color:#fff; }
.modal-header.bg--danger { background:linear-gradient(135deg,#ef4444,#dc2626); }
.modal-lg { max-width:860px; }
.holding-badge { background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;border-radius:20px;padding:2px 10px;font-size:11px;font-weight:600;white-space:nowrap; }
.holding-badge.today { background:#fef3c7;color:#b45309;border-color:#fcd34d; }
.exit-btn { font-size:11px;padding:3px 10px;border-radius:4px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border:none;cursor:pointer;transition:opacity .15s; }
.exit-btn:hover { opacity:.85; }
.custom--table td,.custom--table th { font-size:13px; }
.custom--table thead th { font-size:12px;text-transform:uppercase;letter-spacing:.4px; }
</style>
@endpush

@push('script')
<script>
// ═══════════════════════════════════════════
// GLOBALS
// ═══════════════════════════════════════════
var currentTab      = 'open';
var _exitPos        = null;
var _posStore       = {};   // id → position object (avoids HTML injection)

// ═══════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Portfolio] DOM ready. Initializing...');
    var sel = document.getElementById('brokerSelect');
    if (sel && sel.options.length > 0) {
        console.log('[Portfolio] Broker found, loading positions...');
        loadOpenPositions();
        loadTodayActivity();
    } else {
        console.warn('[Portfolio] No broker select or no options found.');
    }

    // Exit modal reset on close
    var exitModal = document.getElementById('exitModal');
    if (exitModal) {
        exitModal.addEventListener('hidden.bs.modal', function() {
            document.getElementById('targetPct').value       = '';
            document.getElementById('calcSellPrice').value  = '';
            document.getElementById('exitLimitPrice').value = '';
            document.getElementById('exitOrderType').value  = 'MARKET';
            document.getElementById('limitPriceWrap').style.display   = 'none';
            document.getElementById('useCalcPriceWrap').style.display = 'none';
            document.getElementById('exitWarningMsg').textContent = 'Orders will be split into chunks of 20 qty automatically.';
            _exitPos = null;
        });
    }

    // Target % calculator
    var targetPctEl = document.getElementById('targetPct');
    if (targetPctEl) {
        targetPctEl.addEventListener('input', function() {
            var pct   = parseFloat(this.value) || 0;
            var entry = parseFloat(_exitPos ? _exitPos.entry_price : 0) || 0;
            var qty   = Math.abs(parseInt(_exitPos ? _exitPos.quantity : 0) || 0);
            if (pct > 0 && entry > 0) {
                var calcPrice = entry * (1 + pct / 100);
                var potPnl    = (calcPrice - entry) * qty;
                document.getElementById('calcSellPrice').value      = '₹' + calcPrice.toFixed(2);
                document.getElementById('calcPriceVal').textContent = calcPrice.toFixed(2);
                var isLimit = document.getElementById('exitOrderType').value === 'LIMIT';
                document.getElementById('useCalcPriceWrap').style.display = isLimit ? '' : 'none';
                document.getElementById('exitWarningMsg').innerHTML =
                    'Potential P&L: <strong class="' + pnlClass(potPnl) + '">' + fmtPnl(potPnl) + '</strong> | Splits into chunks of 20 qty.';
            } else {
                document.getElementById('calcSellPrice').value = '';
                document.getElementById('useCalcPriceWrap').style.display = 'none';
                document.getElementById('exitWarningMsg').textContent = 'Orders will be split into chunks of 20 qty automatically.';
            }
        });
    }
});

// Event delegation for Exit buttons (avoids HTML encoding issues completely)
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.exit-btn');
    if (!btn) return;
    var posId = btn.getAttribute('data-pos-id');
    var pos   = _posStore[posId];
    if (!pos) {
        console.error('[Portfolio] Position not found in store for id:', posId);
        return;
    }
    openExitModal(pos);
});

// ═══════════════════════════════════════════
// TAB SWITCHING
// ═══════════════════════════════════════════
function switchTab(tab, el) {
    currentTab = tab;
    document.querySelectorAll('#portfolioTabNav .nav-link').forEach(function(a) { a.classList.remove('active'); });
    el.classList.add('active');
    ['open','closed','today'].forEach(function(t) {
        var pane = document.getElementById('pane' + t.charAt(0).toUpperCase() + t.slice(1));
        if (pane) pane.style.display = (t === tab) ? '' : 'none';
    });
    if (tab === 'open')   loadOpenPositions();
    if (tab === 'closed') loadClosedPositions();
    if (tab === 'today')  loadTodayActivity();
}

function onBrokerChange() {
    refreshCurrentTab();
    loadTodayActivity();
}

function refreshCurrentTab() {
    var ico = document.getElementById('refreshIcon');
    if (ico) { ico.classList.add('la-spin'); setTimeout(function(){ ico.classList.remove('la-spin'); }, 1500); }
    if (currentTab === 'open')   loadOpenPositions();
    if (currentTab === 'closed') loadClosedPositions();
    if (currentTab === 'today')  loadTodayActivity();
}

// ═══════════════════════════════════════════
// NUMBER HELPERS
// ═══════════════════════════════════════════
function safeFloat(v)  { return parseFloat(v)  || 0; }
function safeInt(v)    { return parseInt(v)    || 0; }
function fmtPnl(val) {
    var num  = safeFloat(val);
    var sign = num >= 0 ? '+' : '-';
    return sign + '₹' + Math.abs(num).toFixed(2);
}
function pnlClass(val) { return safeFloat(val) >= 0 ? 'text--success' : 'text--danger'; }
function pnlIcon(val)  { return safeFloat(val) >= 0 ? '▲' : '▼'; }

// ═══════════════════════════════════════════
// OPEN POSITIONS
// ═══════════════════════════════════════════
function loadOpenPositions() {
    var brokerId   = document.getElementById('brokerSelect').value;
    var dateFilter = document.getElementById('purchaseDateFilter').value;
    if (!brokerId) { console.warn('[Open] No broker selected'); return; }

    console.log('[Open] Loading... broker=' + brokerId + ' date=' + dateFilter);
    setLoading('open', true);

    fetch('{{ route("portfolio.fetch-positions") }}', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body:    JSON.stringify({ broker_id: brokerId, purchase_date: dateFilter || null })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        setLoading('open', false);
        console.log('[Open] API response:', res);

        if (!res.success) {
            console.error('[Open] API returned error:', res.message);
            tableError('openTableBody', 10, res.message);
            return;
        }

        // ── Bulletproof destructure (works whether wrapped in data or not) ──
        var payload         = res.data || res;
        var positions       = Array.isArray(payload.positions)       ? payload.positions       : [];
        var total_pnl       = payload.total_pnl       !== undefined  ? payload.total_pnl       : 0;
        var total_positions = payload.total_positions !== undefined  ? payload.total_positions : positions.length;
        var fetched_at      = payload.fetched_at      || '';
        var available_dates = Array.isArray(payload.available_dates) ? payload.available_dates : [];

        console.log('[Open] Positions count:', positions.length);
        console.log('[Open] First position:', positions[0]);

        // Update UI counters
        updateDateFilter(available_dates, dateFilter);
        setText('tabOpenCount',  total_positions);
        setText('sumOpenCount',  total_positions);
        setText('openUpdatedAt', fetched_at ? 'Updated: ' + fetched_at : '');
        setText('lastSyncTime',  fetched_at);
        show('lastSyncBadge');
        show('summaryCards');
        show('quickPnlBar');

        var pnl   = safeFloat(total_pnl);
        var pnlEl = document.getElementById('sumTotalPnl');
        pnlEl.textContent = fmtPnl(pnl);
        pnlEl.className   = 'fs-5 fw-bold ' + pnlClass(pnl);

        var profitCount = positions.filter(function(p){ return safeFloat(p.unrealized_pnl) >= 0; }).length;
        var lossCount   = positions.filter(function(p){ return safeFloat(p.unrealized_pnl) <  0; }).length;
        setText('sumWinCount',  profitCount);
        setText('sumLossCount', lossCount);

        var mtmEl = document.getElementById('headerMtm');
        mtmEl.textContent = fmtPnl(pnl);
        mtmEl.className   = 'fs-6 ' + pnlClass(pnl);

        var tbody = document.getElementById('openTableBody');

        if (positions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="las la-inbox" style="font-size:40px"></i><br>No open positions</td></tr>';
            hide('openTableFoot');
            return;
        }

        // Store positions safely (no HTML encoding needed)
        _posStore = {};
        positions.forEach(function(pos) { _posStore[pos.id] = pos; });

        tbody.innerHTML = positions.map(function(pos) {
            var entryPrice = safeFloat(pos.entry_price);
            var ltp        = safeFloat(pos.ltp);
            var unrealPnl  = safeFloat(pos.unrealized_pnl);
            var pnlPct     = safeFloat(pos.pnl_percentage);
            var qty        = safeInt(pos.quantity);
            var holdDays   = safeInt(pos.holding_days);
            var isLong     = pos.position_type === 'LONG';

            var pnlCls    = unrealPnl >= 0 ? 'text--success' : 'text--danger';
            var pnlIco    = unrealPnl >= 0 ? '▲' : '▼';
            var typeBadge = isLong
                ? '<span class="badge badge--success">LONG</span>'
                : '<span class="badge badge--danger">SHORT</span>';
            var holdBadge = holdDays === 0
                ? '<span class="holding-badge today">Today</span>'
                : '<span class="holding-badge">' + holdDays + 'd</span>';

            return '<tr>' +
                '<td><div class="fw-semibold">' + pos.tradingsymbol + '</div><small class="text-muted">' + pos.exchange + ' · ' + pos.product + '</small></td>' +
                '<td class="text-center">' + typeBadge + '</td>' +
                '<td class="text-end fw-bold">' + Math.abs(qty) + '</td>' +
                '<td class="text-end">₹' + entryPrice.toFixed(2) + '</td>' +
                '<td class="text-end fw-bold text--base">₹' + ltp.toFixed(2) + '</td>' +
                '<td class="text-end fw-bold ' + pnlCls + '">' + pnlIco + ' ₹' + Math.abs(unrealPnl).toFixed(2) + '</td>' +
                '<td class="text-end ' + pnlCls + '">' + pnlIco + ' ' + pnlPct.toFixed(2) + '%</td>' +
                '<td class="text-center">' + holdBadge + '</td>' +
                '<td class="text-center"><small class="text-muted">' + (pos.purchase_date || '') + '</small></td>' +
                '<td class="text-center"><button class="exit-btn" data-pos-id="' + pos.id + '"><i class="las la-times-circle"></i> Exit</button></td>' +
                '</tr>';
        }).join('');

        console.log('[Open] Table rendered with ' + positions.length + ' rows.');

        show('openTableFoot');
        var ftEl = document.getElementById('openTotalPnl');
        ftEl.textContent = fmtPnl(pnl);
        ftEl.className   = 'text-end fw-bold ' + pnlClass(pnl);
    })
    .catch(function(err) {
        setLoading('open', false);
        console.error('[Open] Fetch error:', err);
        tableError('openTableBody', 10, 'Network error: ' + err.message);
    });
}

// ═══════════════════════════════════════════
// CLOSED POSITIONS
// ═══════════════════════════════════════════
function loadClosedPositions() {
    var brokerId = document.getElementById('brokerSelect').value;
    if (!brokerId) return;

    console.log('[Closed] Loading...');
    setLoading('closed', true);

    fetch('{{ route("portfolio.closed-positions") }}', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body:    JSON.stringify({
            broker_id: brokerId,
            from_date: document.getElementById('closedFrom').value || null,
            to_date:   document.getElementById('closedTo').value   || null,
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        setLoading('closed', false);
        console.log('[Closed] API response:', res);

        if (!res.success) { tableError('closedTableBody', 10, res.message); return; }

        var payload        = res.data || res;
        var history        = Array.isArray(payload.history)        ? payload.history        : [];
        var total_realized = payload.total_realized !== undefined   ? payload.total_realized : 0;
        var winning_trades = payload.winning_trades !== undefined   ? payload.winning_trades : 0;
        var losing_trades  = payload.losing_trades  !== undefined   ? payload.losing_trades  : 0;

        setText('tabClosedCount', history.length);
        setText('closedWin',      winning_trades);
        setText('closedLoss',     losing_trades);

        var realEl = document.getElementById('closedRealized');
        realEl.textContent = fmtPnl(total_realized);
        realEl.className   = 'fw-bold ' + pnlClass(total_realized);

        var hBook = document.getElementById('headerBooked');
        hBook.textContent = fmtPnl(total_realized);
        hBook.className   = 'fs-6 ' + pnlClass(total_realized);

        var tbody = document.getElementById('closedTableBody');
        if (history.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-5 text-muted"><i class="las la-inbox" style="font-size:40px"></i><br>No closed positions found</td></tr>';
            return;
        }

        tbody.innerHTML = history.map(function(h) {
            var ep  = safeFloat(h.entry_price);
            var xp  = safeFloat(h.exit_price);
            var pnl = safeFloat(h.realized_pnl);
            var pnlCls = pnl >= 0 ? 'text--success' : 'text--danger';
            var pnlIco = pnl >= 0 ? '▲' : '▼';
            var typeBadge = h.position_type === 'LONG'
                ? '<span class="badge badge--success">LONG</span>'
                : '<span class="badge badge--danger">SHORT</span>';
            var viaBadge = h.exit_source === 'MANUAL_ZERODHA'
                ? '<span class="badge badge--secondary">📱 Zerodha</span>'
                : '<span class="badge badge--base">🤖 System</span>';
            var holdCls = (safeInt(h.holding_days) === 0) ? 'today' : '';

            return '<tr>' +
                '<td><div class="fw-semibold">' + h.symbol + '</div><small class="text-muted">' + h.exchange + ' · ' + h.product + '</small></td>' +
                '<td class="text-center">' + typeBadge + '</td>' +
                '<td class="text-end">' + h.qty + '</td>' +
                '<td class="text-end">₹' + ep.toFixed(2) + '</td>' +
                '<td class="text-end fw-bold">₹' + xp.toFixed(2) + '</td>' +
                '<td class="text-end fw-bold ' + pnlCls + '">' + pnlIco + ' ₹' + Math.abs(pnl).toFixed(2) + '</td>' +
                '<td class="text-center"><span class="holding-badge ' + holdCls + '">' + (h.holding_label || '') + '</span></td>' +
                '<td class="text-center"><small>' + h.entry_date + '</small></td>' +
                '<td class="text-center"><small>' + h.exit_date + '</small></td>' +
                '<td class="text-center">' + viaBadge + '</td>' +
                '</tr>';
        }).join('');
    })
    .catch(function(err) {
        setLoading('closed', false);
        console.error('[Closed] Fetch error:', err);
        tableError('closedTableBody', 10, err.message);
    });
}

function clearClosedFilter() {
    document.getElementById('closedFrom').value = '';
    document.getElementById('closedTo').value   = '';
    loadClosedPositions();
}

// ═══════════════════════════════════════════
// TODAY ACTIVITY
// ═══════════════════════════════════════════
function loadTodayActivity() {
    var brokerId = document.getElementById('brokerSelect').value;
    if (!brokerId) return;

    console.log('[Today] Loading...');

    fetch('{{ route("portfolio.today-activity") }}', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body:    JSON.stringify({ broker_id: brokerId })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        console.log('[Today] API response:', res);
        if (!res.success) return;

        var d = res.data || res;

        setTextColor('todayBooked',   fmtPnl(d.total_booked_pnl),   'fs-4 fw-bold ' + pnlClass(d.total_booked_pnl));
        setTextColor('todayMtm',      fmtPnl(d.total_mtm_pnl),      'fs-4 fw-bold ' + pnlClass(d.total_mtm_pnl));
        setTextColor('todayCombined', fmtPnl(d.total_combined_pnl),  'fs-4 fw-bold ' + pnlClass(d.total_combined_pnl));

        var hBook = document.getElementById('headerBooked');
        hBook.textContent = fmtPnl(d.total_booked_pnl);
        hBook.className   = 'fs-6 ' + pnlClass(d.total_booked_pnl);

        var openedToday = Array.isArray(d.opened_today) ? d.opened_today : [];
        var closedToday = Array.isArray(d.closed_today) ? d.closed_today : [];

        document.getElementById('todayOpenedBody').innerHTML = openedToday.length === 0
            ? '<tr><td colspan="5" class="text-center text-muted py-3">None opened today</td></tr>'
            : openedToday.map(function(p) {
                var pnl = safeFloat(p.pnl);
                return '<tr><td><strong>' + p.symbol + '</strong></td>' +
                    '<td class="text-end">' + safeInt(p.qty) + '</td>' +
                    '<td class="text-end">₹' + safeFloat(p.entry_price).toFixed(2) + '</td>' +
                    '<td class="text-end">₹' + safeFloat(p.ltp).toFixed(2) + '</td>' +
                    '<td class="text-end fw-bold ' + pnlClass(pnl) + '">' + pnlIcon(pnl) + ' ₹' + Math.abs(pnl).toFixed(2) + '</td></tr>';
            }).join('');

        document.getElementById('todayClosedBody').innerHTML = closedToday.length === 0
            ? '<tr><td colspan="5" class="text-center text-muted py-3">None closed today</td></tr>'
            : closedToday.map(function(p) {
                var pnl = safeFloat(p.realized_pnl);
                return '<tr><td><strong>' + p.symbol + '</strong></td>' +
                    '<td class="text-end">' + safeInt(p.qty) + '</td>' +
                    '<td class="text-end">₹' + safeFloat(p.entry_price).toFixed(2) + '</td>' +
                    '<td class="text-end">₹' + safeFloat(p.exit_price).toFixed(2) + '</td>' +
                    '<td class="text-end fw-bold ' + pnlClass(pnl) + '">' + pnlIcon(pnl) + ' ₹' + Math.abs(pnl).toFixed(2) + '</td></tr>';
            }).join('');
    })
    .catch(function(err) { console.error('[Today] Fetch error:', err); });
}

// ═══════════════════════════════════════════
// EXIT MODAL
// ═══════════════════════════════════════════
function openExitModal(pos) {
    _exitPos = pos;
    var entry  = safeFloat(pos.entry_price);
    var ltp    = safeFloat(pos.ltp);
    var qty    = Math.abs(safeInt(pos.quantity));
    var isLong = pos.position_type === 'LONG';
    var pnl    = isLong ? (ltp - entry) * qty : (entry - ltp) * qty;
    var pct    = entry > 0 ? ((ltp - entry) / entry * 100) : 0;

    setText('exitSymbol',       pos.tradingsymbol);
    setText('exitProduct',      pos.product);
    setText('exitAvailableQty', qty);
    setText('exitEntryPrice',   entry.toFixed(2));
    setText('exitLTP',          ltp.toFixed(2));
    setText('exitMaxQty',       qty);
    setText('ltpHint',          ltp.toFixed(2));
    setText('entryHint',        entry.toFixed(2));

    document.getElementById('exitQtyInput').value  = qty;
    document.getElementById('exitQtyInput').max    = qty;
    document.getElementById('exitLimitPrice').value = ltp.toFixed(2);
    document.getElementById('exitOrderType').value  = 'MARKET';
    document.getElementById('targetPct').value      = '';
    document.getElementById('calcSellPrice').value  = '';
    hide('limitPriceWrap');
    hide('useCalcPriceWrap');

    var typeBadge = document.getElementById('exitTypeBadge');
    typeBadge.textContent = pos.position_type;
    typeBadge.className   = 'badge ' + (isLong ? 'badge--success' : 'badge--danger');

    setTextColor('exitCurrentPnl', (pnl >= 0 ? '+' : '') + '₹' + pnl.toFixed(2), 'fw-bold ' + (pnl >= 0 ? 'text--success' : 'text--danger'));
    setTextColor('exitPnlPct', pct.toFixed(2) + '%', 'fw-bold ' + (pnl >= 0 ? 'text--success' : 'text--danger'));

    new bootstrap.Modal(document.getElementById('exitModal')).show();
}

function toggleExitPrice() {
    var isLimit = document.getElementById('exitOrderType').value === 'LIMIT';
    document.getElementById('limitPriceWrap').style.display = isLimit ? '' : 'none';
    var hasCalc = document.getElementById('calcSellPrice').value !== '';
    document.getElementById('useCalcPriceWrap').style.display = (isLimit && hasCalc) ? '' : 'none';
}

function applyCalcPrice() {
    var raw = document.getElementById('calcSellPrice').value.replace('₹', '');
    document.getElementById('exitLimitPrice').value = raw;
    iziToast.success({ message: 'Calculated price applied!', position: 'topRight', timeout: 2000 });
}

function confirmExit() {
    if (!_exitPos) return;
    var brokerId  = document.getElementById('brokerSelect').value;
    var qty       = safeInt(document.getElementById('exitQtyInput').value);
    var orderType = document.getElementById('exitOrderType').value;
    var price     = document.getElementById('exitLimitPrice').value;
    var btn       = document.getElementById('exitConfirmBtn');

    if (qty <= 0) { iziToast.error({ message: 'Enter valid quantity', position: 'topRight' }); return; }
    if (orderType === 'LIMIT' && (!price || safeFloat(price) <= 0)) { iziToast.error({ message: 'Enter valid limit price', position: 'topRight' }); return; }

    btn.disabled  = true;
    btn.innerHTML = '<i class="las la-spinner la-spin"></i> Placing...';

    fetch('{{ route("portfolio.sell-position") }}', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body:    JSON.stringify({
            broker_id:     brokerId,
            tradingsymbol: _exitPos.tradingsymbol,
            exchange:      _exitPos.exchange,
            product:       _exitPos.product,
            quantity:      qty,
            position_type: _exitPos.position_type,
            order_type:    orderType,
            price:         orderType === 'LIMIT' ? price : null,
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled  = false;
        btn.innerHTML = '<i class="las la-check"></i> Confirm Square Off';
        bootstrap.Modal.getInstance(document.getElementById('exitModal')).hide();
        if (res.success) {
            iziToast.success({ message: res.message + ' — Position will update in ~1 min.', position: 'topRight', timeout: 5000 });
            setTimeout(loadOpenPositions, 30000);
        } else {
            iziToast.error({ message: 'Failed: ' + res.message, position: 'topRight', timeout: 6000 });
        }
    })
    .catch(function(err) {
        btn.disabled  = false;
        btn.innerHTML = '<i class="las la-check"></i> Confirm Square Off';
        iziToast.error({ message: 'Error: ' + err.message, position: 'topRight' });
    });
}

// ═══════════════════════════════════════════
// UI HELPERS
// ═══════════════════════════════════════════
function setLoading(tab, show) {
    var el = document.getElementById(tab + 'Loading');
    if (el) el.style.display = show ? 'block' : 'none';
}
function tableError(tbodyId, cols, msg) {
    var el = document.getElementById(tbodyId);
    if (el) el.innerHTML = '<tr><td colspan="' + cols + '" class="text-center py-4 text--danger"><i class="las la-exclamation-circle"></i> ' + msg + '</td></tr>';
}
function updateDateFilter(dates, currentVal) {
    var sel = document.getElementById('purchaseDateFilter');
    if (!sel) return;
    sel.innerHTML = '<option value="">All Dates</option>' + dates.map(function(d) {
        return '<option value="' + d + '"' + (d === currentVal ? ' selected' : '') + '>' + d + '</option>';
    }).join('');
}
function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
}
function setTextColor(id, text, cls) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = text;
    el.className   = cls;
}
function show(id) { var el = document.getElementById(id); if (el) el.style.display = ''; }
function hide(id) { var el = document.getElementById(id); if (el) el.style.display = 'none'; }
</script>
@endpush