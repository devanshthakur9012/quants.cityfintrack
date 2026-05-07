@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-100 pb-100">
    <div class="container content-container">

        {{-- ── PAGE HEADER ── --}}
        <div class="custom--card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-1"><i class="las la-shield-alt"></i> Stop Loss Configuration</h5>
                    <p class="text-muted small mb-0">Define SL rules per broker &amp; symbol type. Each rule can be triggered individually or all at once.</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn--base btn-sm" onclick="openAddModal()">
                        <i class="las la-plus"></i> Add Config
                    </button>
                    <button class="btn btn--danger btn-sm" id="runAllBtn" onclick="runAll()">
                        <i class="las la-play"></i> Run All Active
                    </button>
                </div>
            </div>
        </div>

        {{-- ── NO BROKER WARNING ── --}}
        @if($brokers->isEmpty())
        <div class="alert alert--danger">
            <i class="las la-exclamation-triangle"></i>
            <strong>No active Zerodha brokers found.</strong>
            <a href="{{ route('zerodha-broker.index') }}" class="ms-2">Go to Brokers →</a>
        </div>
        @endif

        {{-- ── CONFIG CARDS ── --}}
        <div id="configArea">
            @if($configs->isEmpty())
            <div class="custom--card">
                <div class="card-body text-center py-5 text-muted" id="emptyState">
                    <i class="las la-shield-alt" style="font-size:48px;opacity:.3"></i>
                    <p class="mt-2 mb-0">No Stop Loss configurations yet. Click <strong>Add Config</strong> to get started.</p>
                </div>
            </div>
            @else
            <div class="row g-3" id="configCards">
                {{-- Cards are rendered by buildCardHtml() in JS below --}}
            </div>
            @endif
        </div>

        {{-- ── OUTPUT LOG ── --}}
        <div class="custom--card mt-4" id="outputCard" style="display:none">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="las la-terminal"></i> Execution Output</h6>
                <button class="btn btn-sm btn--secondary" onclick="document.getElementById('outputCard').style.display='none'">
                    <i class="las la-times"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <pre id="outputLog" style="background:#0f172a;color:#e2e8f0;padding:16px;margin:0;font-size:12px;max-height:400px;overflow-y:auto;border-radius:0 0 8px 8px;white-space:pre-wrap;word-break:break-word"></pre>
            </div>
        </div>

    </div>
</section>

{{-- ══════════════════════════════════════════════════════════════
     ADD / EDIT MODAL
══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="slModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#f59e0b,#d97706)">
                <h5 class="modal-title text-white">
                    <i class="las la-shield-alt"></i>
                    <span id="modalTitle">Add Stop Loss Config</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editConfigId">

                <div class="row g-3">
                    {{-- Broker --}}
                    <div class="col-md-6" id="brokerWrap">
                        <label class="form-label fw-semibold">Broker <sup class="text--danger">*</sup></label>
                        <select class="form--control" id="fBroker">
                            <option value="">-- Select Broker --</option>
                            @foreach($brokers as $b)
                            <option value="{{ $b->id }}">{{ $b->client_name }} ({{ $b->account_user_name }})</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Symbol Type --}}
                    <div class="col-md-6" id="symbolTypeWrap">
                        <label class="form-label fw-semibold">Symbol Type <sup class="text--danger">*</sup></label>
                        <select class="form--control" id="fSymbolType">
                            <option value="BOTH">BOTH (CE &amp; PE)</option>
                            <option value="CE">CE only</option>
                            <option value="PE">PE only</option>
                        </select>
                    </div>

                    {{-- Price Type — hidden, always AVG --}}
                    <input type="hidden" id="fPriceType" value="AVG">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Base Price</label>
                        <div class="p-2 rounded d-flex align-items-center gap-2"
                             style="background:#f0fdf4;border:1px solid #86efac">
                            <i class="las la-lock text--success"></i>
                            <div>
                                <strong class="text--success">Always Entry Price (AVG)</strong>
                                <div class="text-muted" style="font-size:11px">
                                    SL is fixed at entry price × SL%. LTP movement never shifts your SL.
                                    This is a disaster fail-safe, not a trailing stop.
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SL Percent --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Stop Loss % <sup class="text--danger">*</sup></label>
                        <div class="input-group">
                            <input type="number" class="form--control" id="fSlPct" step="0.5" min="-100" max="100"
                                   placeholder="e.g. -30" oninput="updateSlHint()">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted" id="slHint">Negative = stop at loss %, Positive = lock profit %</small>
                    </div>

                    {{-- Quantity % --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Quantity % to Sell <sup class="text--danger">*</sup></label>
                        <div class="input-group">
                            <input type="number" class="form--control" id="fQtyPct" min="1" max="100" value="100">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="text-muted">100 = full position, 50 = half exit</small>
                    </div>

                    {{-- Position Filter --}}
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Position Filter <sup class="text--danger">*</sup></label>
                        <select class="form--control" id="fPositionFilter">
                            <option value="BOTH">BOTH (profit &amp; loss)</option>
                            <option value="PROFIT">PROFIT positions only</option>
                            <option value="LOSS">LOSS positions only</option>
                        </select>
                        <small class="text-muted">SL only fires for positions matching this filter.</small>
                    </div>

                    {{-- Skip toggles --}}
                    <div class="col-12">
                        <label class="form-label fw-semibold d-block mb-2">Position Age Filters</label>
                        <div class="d-flex gap-4 flex-wrap">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="fSkipOld" role="switch">
                                <label class="form-check-label" for="fSkipOld">Skip OLD positions (older than T-1)</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="fSkipFresh" role="switch">
                                <label class="form-check-label" for="fSkipFresh">Skip FRESH positions (today / T-1)</label>
                            </div>
                        </div>
                    </div>

                    {{-- Live preview --}}
                    <div class="col-12">
                        <div class="p-3 rounded" style="background:#fef3c7;border:1px solid #fcd34d" id="slPreviewBox">
                            <h6 class="mb-1"><i class="las la-info-circle text--warning"></i> Config Summary</h6>
                            <p class="mb-0 small" id="slPreviewText">Fill in the fields above to see a summary.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--secondary" data-bs-dismiss="modal"><i class="las la-times"></i> Cancel</button>
                <button type="button" class="btn btn--base" id="saveConfigBtn" onclick="saveConfig()">
                    <i class="las la-save"></i> Save Configuration
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

{{-- ══════════════════════════════════════════════════════════════
     INLINE PARTIAL: config card
══════════════════════════════════════════════════════════════ --}}
{{-- We render cards dynamically via JS so no separate partial needed --}}

@push('style')
<style>
/* ── base overrides ───────────────────────────────────────────── */
.sl-card        { border-radius:12px;border:1px solid #e5e7eb;transition:box-shadow .15s; }
.sl-card:hover  { box-shadow:0 4px 20px rgba(0,0,0,.08); }
.sl-badge       { font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600; }
.sl-badge.ce    { background:#dbeafe;color:#1d4ed8; }
.sl-badge.pe    { background:#fce7f3;color:#9d174d; }
.sl-badge.both  { background:#f3e8ff;color:#6d28d9; }
.sl-badge.on    { background:#d1fae5;color:#065f46; }
.sl-badge.off   { background:#fee2e2;color:#991b1b; }
.sl-value       { font-size:22px;font-weight:700;line-height:1; }
.sl-label       { font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px; }
.sl-divider     { border-color:#f1f5f9;margin:10px 0; }
.action-btn     { font-size:12px;padding:4px 12px;border-radius:6px;border:none;cursor:pointer;transition:opacity .15s; }
.action-btn:hover { opacity:.85; }
.btn-run        { background:linear-gradient(135deg,#10b981,#059669);color:#fff; }
.btn-edit       { background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd!important; }
.btn-toggle-on  { background:#fef3c7;color:#92400e;border:1px solid #fcd34d!important; }
.btn-toggle-off { background:#d1fae5;color:#065f46;border:1px solid #6ee7b7!important; }
.btn-del        { background:#fff1f2;color:#be123c;border:1px solid #fda4af!important; }
.running-spinner { display:none;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle }
@keyframes spin { to { transform:rotate(360deg); } }
.output-line-ok  { color:#4ade80; }
.output-line-err { color:#f87171; }
.output-line-warn{ color:#fbbf24; }
.text--warning { color:#f59e0b!important; }
.alert--danger  { background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;padding:12px; }
.badge--danger  { background:#ef4444;color:#fff; }
.btn--danger    { background:linear-gradient(135deg,#ef4444,#dc2626);border:none;color:#fff; }
.btn--danger:hover { opacity:.9;color:#fff; }
.btn--base:hover { color:#fff; }
.btn--secondary { background:#6c757d;border:none;color:#fff; }
</style>
@endpush

@push('script')
<script>
// ══════════════════════════════════════════════
// DATA — server-rendered, safe JSON
// ══════════════════════════════════════════════
var _configs = @json($configs->values());
var _brokers = @json($brokers->values()->map(fn($b) => ['id' => $b->id, 'label' => $b->client_name . ' (' . $b->account_user_name . ')']));

// ══════════════════════════════════════════════
// INIT — render cards from JS so edit/delete work cleanly
// ══════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {
    renderAllCards();

    // Live preview updater
    ['fPriceType','fSlPct','fQtyPct','fPositionFilter','fSkipOld','fSkipFresh','fSymbolType'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', updateSlHint);
        if (el && el.type === 'number') el.addEventListener('input', updateSlHint);
    });
});

// ══════════════════════════════════════════════
// RENDER CARDS
// ══════════════════════════════════════════════
function renderAllCards() {
    var area = document.getElementById('configCards');
    if (!area) {
        // First time: replace the whole configArea
        var configArea = document.getElementById('configArea');
        if (_configs.length === 0) return; // keep empty state
        configArea.innerHTML = '<div class="row g-3" id="configCards"></div>';
        area = document.getElementById('configCards');
    }
    area.innerHTML = _configs.map(buildCardHtml).join('');
}

function buildCardHtml(cfg) {
    var stCls    = cfg.symbol_type === 'CE' ? 'ce' : (cfg.symbol_type === 'PE' ? 'pe' : 'both');
    var isActive = cfg.is_active;
    var slPct    = parseFloat(cfg.stop_loss_percent);
    var slColor  = slPct < 0 ? '#ef4444' : '#10b981';
    var slLabel  = slPct < 0 ? 'Stop Loss' : 'Profit Lock';
    var brokerName = cfg.broker_api ? (cfg.broker_api.client_name + ' (' + cfg.broker_api.account_user_name + ')') : '—';

    return '<div class="col-xl-4 col-md-6" id="card-' + cfg.id + '">' +
        '<div class="custom--card sl-card h-100">' +
        '  <div class="card-body">' +

        // Header row
        '  <div class="d-flex justify-content-between align-items-start mb-3">' +
        '    <div>' +
        '      <span class="sl-badge ' + stCls + '">' + cfg.symbol_type + '</span>' +
        '      <span class="sl-badge ms-1 ' + (isActive ? 'on' : 'off') + '">' + (isActive ? '● Active' : '○ Paused') + '</span>' +
        '    </div>' +
        '    <small class="text-muted">#' + cfg.id + '</small>' +
        '  </div>' +

        // SL value
        '  <div class="text-center py-2 mb-3" style="border-radius:8px;background:#f8fafc">' +
        '    <div class="sl-value" style="color:' + slColor + '">' + (slPct >= 0 ? '+' : '') + slPct + '%</div>' +
        '    <div class="sl-label mt-1">' + slLabel + ' · AVG entry</div>' +
        '  </div>' +

        // Details grid
        '  <div class="row g-2 text-center mb-3">' +
        '    <div class="col-4"><div class="sl-label">Qty Sell</div><div class="fw-semibold">' + cfg.quantity_percent + '%</div></div>' +
        '    <div class="col-4"><div class="sl-label">Filter</div><div class="fw-semibold">' + cfg.position_filter + '</div></div>' +
        '    <div class="col-4"><div class="sl-label">Broker</div><div class="fw-semibold text-truncate" title="' + brokerName + '" style="font-size:11px">' + (cfg.broker_api ? cfg.broker_api.account_user_name : '—') + '</div></div>' +
        '  </div>' +

        // Skip flags
        (cfg.skip_old_positions || cfg.skip_fresh_positions
            ? '<div class="d-flex gap-2 mb-3 flex-wrap">' +
              (cfg.skip_old_positions   ? '<span class="sl-badge off">Skip Old</span>'   : '') +
              (cfg.skip_fresh_positions ? '<span class="sl-badge off">Skip Fresh</span>' : '') +
              '</div>'
            : '') +

        '  <hr class="sl-divider">' +

        // Action buttons
        '  <div class="d-flex gap-2 flex-wrap">' +
        '    <button class="action-btn btn-run" onclick="runOne(' + cfg.id + ', this)">' +
        '      <span class="running-spinner" id="spin-' + cfg.id + '"></span>' +
        '      <i class="las la-play"></i> Run' +
        '    </button>' +
        '    <button class="action-btn btn-edit" onclick="openEditModal(' + cfg.id + ')">' +
        '      <i class="las la-pen"></i> Edit' +
        '    </button>' +
        '    <button class="action-btn ' + (isActive ? 'btn-toggle-on' : 'btn-toggle-off') + '" onclick="toggleActive(' + cfg.id + ', this)">' +
        '      <i class="las la-' + (isActive ? 'pause' : 'play') + '"></i> ' + (isActive ? 'Pause' : 'Resume') +
        '    </button>' +
        '    <button class="action-btn btn-del ms-auto" onclick="deleteConfig(' + cfg.id + ')">' +
        '      <i class="las la-trash"></i>' +
        '    </button>' +
        '  </div>' +

        '  </div>' + // card-body
        '</div>' +   // sl-card
        '</div>';    // col
}

// ══════════════════════════════════════════════
// MODAL — ADD
// ══════════════════════════════════════════════
function openAddModal() {
    document.getElementById('editConfigId').value = '';
    document.getElementById('modalTitle').textContent  = 'Add Stop Loss Config';
    document.getElementById('brokerWrap').style.display     = '';
    document.getElementById('symbolTypeWrap').style.display = '';
    document.getElementById('fBroker').value      = '';
    document.getElementById('fSymbolType').value  = 'BOTH';
    document.getElementById('fPriceType').value   = 'AVG';
    document.getElementById('fSlPct').value       = '-30';
    document.getElementById('fQtyPct').value      = '100';
    document.getElementById('fPositionFilter').value = 'BOTH';
    document.getElementById('fSkipOld').checked   = false;
    document.getElementById('fSkipFresh').checked = false;
    updateSlHint();
    new bootstrap.Modal(document.getElementById('slModal')).show();
}

// ══════════════════════════════════════════════
// MODAL — EDIT
// ══════════════════════════════════════════════
function openEditModal(id) {
    var cfg = _configs.find(function(c) { return c.id == id; });
    if (!cfg) return;

    document.getElementById('editConfigId').value = cfg.id;
    document.getElementById('modalTitle').textContent  = 'Edit Stop Loss Config';
    document.getElementById('brokerWrap').style.display     = 'none'; // cant change broker on edit
    document.getElementById('symbolTypeWrap').style.display = 'none'; // cant change type on edit

    document.getElementById('fPriceType').value       = cfg.price_type;
    document.getElementById('fSlPct').value           = cfg.stop_loss_percent;
    document.getElementById('fQtyPct').value          = cfg.quantity_percent;
    document.getElementById('fPositionFilter').value  = cfg.position_filter;
    document.getElementById('fSkipOld').checked       = !!cfg.skip_old_positions;
    document.getElementById('fSkipFresh').checked     = !!cfg.skip_fresh_positions;
    updateSlHint();
    new bootstrap.Modal(document.getElementById('slModal')).show();
}

// ══════════════════════════════════════════════
// SAVE (ADD + EDIT)
// ══════════════════════════════════════════════
function saveConfig() {
    var editId  = document.getElementById('editConfigId').value;
    var isEdit  = !!editId;

    var payload = {
        broker_api_id:        document.getElementById('fBroker').value,
        symbol_type:          document.getElementById('fSymbolType').value,
        price_type:           document.getElementById('fPriceType').value,
        stop_loss_percent:    parseFloat(document.getElementById('fSlPct').value),
        quantity_percent:     parseInt(document.getElementById('fQtyPct').value),
        position_filter:      document.getElementById('fPositionFilter').value,
        skip_old_positions:   document.getElementById('fSkipOld').checked  ? 1 : 0,
        skip_fresh_positions: document.getElementById('fSkipFresh').checked ? 1 : 0,
    };

    if (!isEdit && !payload.broker_api_id) { iziToast.error({ message: 'Select a broker', position: 'topRight' }); return; }
    if (isNaN(payload.stop_loss_percent))  { iziToast.error({ message: 'Enter a valid SL %', position: 'topRight' }); return; }

    var url    = isEdit
        ? '{{ route("portfolio.broker-stop-loss-config.update", ":id") }}'.replace(':id', editId)
        : '{{ route("portfolio.broker-stop-loss-config.store") }}';
    var method = isEdit ? 'PUT' : 'POST';

    var btn = document.getElementById('saveConfigBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="las la-spinner la-spin"></i> Saving...';

    fetch(url, {
        method:  method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body:    JSON.stringify(payload),
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled = false;
        btn.innerHTML = '<i class="las la-save"></i> Save Configuration';
        if (res.success) {
            iziToast.success({ message: res.message, position: 'topRight', timeout: 3000 });
            bootstrap.Modal.getInstance(document.getElementById('slModal')).hide();
            if (isEdit) {
                var idx = _configs.findIndex(function(c) { return c.id == editId; });
                if (idx >= 0) _configs[idx] = res.data;
            } else {
                _configs.push(res.data);
            }
            renderAllCards();
        } else {
            iziToast.error({ message: res.message, position: 'topRight', timeout: 5000 });
        }
    })
    .catch(function(err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="las la-save"></i> Save Configuration';
        iziToast.error({ message: 'Network error: ' + err.message, position: 'topRight' });
    });
}

// ══════════════════════════════════════════════
// TOGGLE ACTIVE
// ══════════════════════════════════════════════
function toggleActive(id, btn) {
    fetch('{{ route("portfolio.broker-stop-loss-config.toggle", ":id") }}'.replace(':id', id), {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            iziToast.success({ message: res.message, position: 'topRight', timeout: 2000 });
            var idx = _configs.findIndex(function(c) { return c.id == id; });
            if (idx >= 0) _configs[idx] = res.data;
            renderAllCards();
        } else {
            iziToast.error({ message: res.message, position: 'topRight' });
        }
    });
}

// ══════════════════════════════════════════════
// DELETE
// ══════════════════════════════════════════════
function deleteConfig(id) {
    if (!confirm('Delete this Stop Loss configuration?')) return;

    fetch('{{ route("portfolio.broker-stop-loss-config.destroy", ":id") }}'.replace(':id', id), {
        method:  'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            iziToast.success({ message: res.message, position: 'topRight', timeout: 2000 });
            _configs = _configs.filter(function(c) { return c.id != id; });
            renderAllCards();
            if (_configs.length === 0) {
                document.getElementById('configArea').innerHTML =
                    '<div class="custom--card"><div class="card-body text-center py-5 text-muted" id="emptyState">' +
                    '<i class="las la-shield-alt" style="font-size:48px;opacity:.3"></i>' +
                    '<p class="mt-2 mb-0">No Stop Loss configurations yet.</p></div></div>';
            }
        } else {
            iziToast.error({ message: res.message, position: 'topRight' });
        }
    });
}

// ══════════════════════════════════════════════
// RUN ONE CONFIG
// ══════════════════════════════════════════════
function runOne(id, btnEl) {
    var spin = document.getElementById('spin-' + id);
    if (spin) spin.style.display = 'inline-block';
    btnEl.disabled = true;

    fetch('{{ route("portfolio.broker-stop-loss-config.execute-one", ":id") }}'.replace(':id', id), {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (spin) spin.style.display = 'none';
        btnEl.disabled = false;
        if (res.success) {
            iziToast.success({ message: res.message, position: 'topRight', timeout: 4000 });
        } else {
            iziToast.error({ message: res.message || 'Execution failed', position: 'topRight', timeout: 5000 });
        }
        showOutput(res.output || '(no output)');
    })
    .catch(function(err) {
        if (spin) spin.style.display = 'none';
        btnEl.disabled = false;
        iziToast.error({ message: 'Network error: ' + err.message, position: 'topRight' });
    });
}

// ══════════════════════════════════════════════
// RUN ALL
// ══════════════════════════════════════════════
function runAll() {
    var btn = document.getElementById('runAllBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="las la-spinner la-spin"></i> Running...';

    fetch('{{ route("portfolio.broker-stop-loss-config.execute") }}', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        btn.disabled  = false;
        btn.innerHTML = '<i class="las la-play"></i> Run All Active';
        if (res.success) {
            iziToast.success({ message: res.message, position: 'topRight', timeout: 4000 });
        } else {
            iziToast.error({ message: res.message || 'Execution failed', position: 'topRight', timeout: 5000 });
        }
        showOutput(res.output || '(no output)');
    })
    .catch(function(err) {
        btn.disabled  = false;
        btn.innerHTML = '<i class="las la-play"></i> Run All Active';
        iziToast.error({ message: 'Network error: ' + err.message, position: 'topRight' });
    });
}

// ══════════════════════════════════════════════
// OUTPUT LOG
// ══════════════════════════════════════════════
function showOutput(text) {
    var card = document.getElementById('outputCard');
    var log  = document.getElementById('outputLog');
    card.style.display = '';

    // Colorize lines
    var html = text.split('\n').map(function(line) {
        if (line.match(/✅|SUCCESS|placed/i)) return '<span class="output-line-ok">' + escHtml(line) + '</span>';
        if (line.match(/❌|FAILED|Error/i))  return '<span class="output-line-err">' + escHtml(line) + '</span>';
        if (line.match(/⚠️|WARNING|warn/i)) return '<span class="output-line-warn">' + escHtml(line) + '</span>';
        return escHtml(line);
    }).join('\n');

    log.innerHTML = html;
    log.scrollTop = log.scrollHeight;
    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function escHtml(s) {
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

// ══════════════════════════════════════════════
// SL HINT / PREVIEW
// ══════════════════════════════════════════════
function updateSlHint() {
    var pct       = parseFloat(document.getElementById('fSlPct').value) || 0;
    var qty       = document.getElementById('fQtyPct').value;
    var filter    = document.getElementById('fPositionFilter').value;
    var skipOld   = document.getElementById('fSkipOld').checked;
    var skipFresh = document.getElementById('fSkipFresh').checked;
    var symType   = document.getElementById('fSymbolType') ? document.getElementById('fSymbolType').value : '—';

    var hintEl    = document.getElementById('slHint');
    var previewEl = document.getElementById('slPreviewText');

    // SL is ALWAYS based on AVG entry price — never LTP
    if (pct < 0) {
        if (hintEl) hintEl.innerHTML = '🔴 SL-M placed at <strong>' + Math.abs(pct) + '% below your entry (AVG)</strong>. ' +
            'e.g. entry ₹100 → trigger ₹' + (100 * (1 + pct/100)).toFixed(2) + '. ' +
            'Sits idle on Zerodha — fires automatically if price falls to trigger.';
    } else if (pct > 0) {
        if (hintEl) hintEl.innerHTML = '🟡 Trigger placed <strong>' + pct + '% above entry</strong> — ' +
            'acts as a profit-lock. Note: still disaster SL from entry, not trailing.';
    } else {
        if (hintEl) hintEl.textContent = '⚪ SL = 0%: trigger = entry price exactly.';
    }

    var slAt    = (100 * (1 + pct / 100)).toFixed(2);
    var skipTxt = [skipOld ? 'old' : '', skipFresh ? 'fresh' : ''].filter(Boolean).join(' & ') || 'none';

    if (previewEl) {
        previewEl.innerHTML =
            '<strong>Disaster SL Rule:</strong> For <strong>' + symType + '</strong> positions — ' +
            'place SL-M trigger at <strong>entry price ' + (pct >= 0 ? '+' : '') + pct + '%</strong> ' +
            '(e.g. ₹' + slAt + ' on ₹100 entry). ' +
            'Sell <strong>' + qty + '% of qty</strong> if triggered. ' +
            'Apply to <strong>' + filter + '</strong> positions. ' +
            'LTP can move to ₹200 — your SL stays at ₹' + slAt + '. ' +
            'Skip: <strong>' + skipTxt + '</strong>.';
    }
}
</script>
@endpush