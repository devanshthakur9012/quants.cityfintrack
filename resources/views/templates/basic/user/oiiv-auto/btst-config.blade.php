@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
.btst-hdr { background:linear-gradient(135deg,#0f2027,#203a43,#2c5364); color:white; padding:18px 22px; border-radius:12px; margin-bottom:18px; }
.btst-hdr h4 { margin:0; font-size:17px; font-weight:700; }
.btst-hdr p  { margin:3px 0 0; font-size:12px; opacity:.7; }

/* ── Config cards ── */
.bc { border:1px solid #e9ecef; border-radius:12px; background:white; padding:18px; transition:box-shadow .15s; }
.bc:hover { box-shadow:0 4px 20px rgba(0,0,0,.08); }
.bc-badge { font-size:10px; font-weight:700; padding:2px 9px; border-radius:20px; }
.bc-both   { background:#f3e8ff;color:#6d28d9; }
.bc-ce     { background:#dbeafe;color:#1d4ed8; }
.bc-pe     { background:#fce7f3;color:#9d174d; }
.bc-on     { background:#d1fae5;color:#065f46; }
.bc-off    { background:#fee2e2;color:#991b1b; }

.bc-val { font-size:1.4rem; font-weight:700; line-height:1.1; }
.bc-lbl { font-size:10px; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-top:2px; }
.bc-sl   { color:#ef4444; }
.bc-prof { color:#10b981; }
.bc-old  { color:#f59e0b; }

.bc-btn { font-size:11px; padding:4px 12px; border-radius:6px; border:none; cursor:pointer; transition:opacity .15s; }
.bc-btn:hover { opacity:.85; }
.bc-run   { background:linear-gradient(135deg,#10b981,#059669); color:white; }
.bc-edit  { background:#f0f9ff; color:#0369a1; border:1px solid #bae6fd!important; }
.bc-pause { background:#fef3c7; color:#92400e; border:1px solid #fcd34d!important; }
.bc-res   { background:#d1fae5; color:#065f46; border:1px solid #6ee7b7!important; }
.bc-del   { background:#fff1f2; color:#be123c; border:1px solid #fda4af!important; }
.btn-9am  { background:linear-gradient(135deg,#667eea,#764ba2); color:white; font-size:12px; padding:6px 16px; border-radius:7px; border:none; cursor:pointer; }
.btn-10am { background:linear-gradient(135deg,#f59e0b,#d97706); color:white; font-size:12px; padding:6px 16px; border-radius:7px; border:none; cursor:pointer; }

/* ── Section label ── */
.slbl { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#667eea; border-bottom:2px solid #667eea33; padding-bottom:5px; margin:16px 0 10px; }

/* ── BTST orders table ── */
.bt { font-size:11px; }
.bt thead th { background:#f8f9fa; font-size:10px; font-weight:700; padding:7px 8px; text-transform:uppercase; }
.bt tbody td { padding:7px 8px; vertical-align:middle; }
.sig-sl   { background:#fee2e2;color:#991b1b;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700; }
.sig-prof { background:#d1fae5;color:#065f46;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700; }
.sig-sw   { background:#fef3c7;color:#b45309;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700; }
.sig-c2c  { background:#e0e7ff;color:#3730a3;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700; }

/* ── Output ── */
.out-log { background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;font-size:11px;max-height:320px;overflow-y:auto;font-family:monospace;white-space:pre-wrap;word-break:break-all; }
.ok   { color:#4ade80; }
.err  { color:#f87171; }
.warn { color:#fbbf24; }
.inf  { color:#60a5fa; }
</style>
@endpush

<section class="pt-40 pb-60">
<div class="container-fluid content-container">

    {{-- Header ──────────────────────────────────────────────────────── --}}
    <div class="btst-hdr d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h4>🔔 OIIV BTST Exit Configuration</h4>
            <p>
                9:15 AM — Place SL + profit orders for yesterday's positions &nbsp;|&nbsp;
                10:00 AM — Sweep: modify/close remaining
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <span style="background:rgba(255,255,255,.15);border-radius:8px;padding:6px 12px;font-size:12px;">
                📊 <strong>{{ $openPositions }}</strong> open position(s)
            </span>
            <button class="btn btn-outline-light btn-sm" onclick="openAdd()">
                <i class="fas fa-plus"></i> Add Config
            </button>
            <button class="btn-9am" onclick="run('9am')">⏰ Run 9AM</button>
            <button class="btn-10am" onclick="run('10am')">🧹 Run 10AM</button>
        </div>
    </div>

    {{-- Flow info ────────────────────────────────────────────────────── --}}
    <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:10px;padding:14px 18px;margin-bottom:18px;font-size:12px;">
        <div class="row g-3">
            <div class="col-md-6">
                <strong style="color:#166534">⏰ 9:15 AM Phase (Fresh positions = yesterday's signals)</strong><br>
                • Place <strong>SL-L order</strong> at <em>AVG × (1 − SL%)</em> — sits idle on exchange until triggered<br>
                • Place <strong>LIMIT SELL</strong> at profit target. If LTP already above target → book at actual LTP<br>
                • Old positions (≥ T-2): wider SL + cost-to-cost or LTP if in profit
            </div>
            <div class="col-md-6">
                <strong style="color:#b45309">🧹 10:00 AM Sweep</strong><br>
                • Still-open positions in profit ≥ min% → <strong>modify existing order</strong> to current LTP<br>
                • Old positions → modify to cost-to-cost (AVG price)<br>
                • All exit orders appear in the <strong>Order Book</strong> (same table, SELL type)<br>
                • Order Book shows BTST_SL / BTST_PROFIT / BTST_SWEEP / BTST_COSTCOST
            </div>
        </div>
    </div>

    @if($brokers->isEmpty())
    <div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px;margin-bottom:16px;">
        No active Zerodha brokers. <a href="{{ route('zerodha-broker.index') }}">Go to Brokers →</a>
    </div>
    @endif

    {{-- Config cards ─────────────────────────────────────────────────── --}}
    <div class="row g-3 mb-4" id="configCards">
        @forelse($configs as $cfg)
        <div class="col-xl-4 col-md-6" id="cfg-{{ $cfg->id }}">
            <div class="bc">
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <span class="bc-badge bc-{{ strtolower($cfg->symbol_type) }}">{{ $cfg->symbol_type }}</span>
                    <span class="bc-badge {{ $cfg->is_active ? 'bc-on' : 'bc-off' }}">
                        {{ $cfg->is_active ? '● Active' : '○ Paused' }}
                    </span>
                    <span class="bc-badge" style="background:#f1f5f9;color:#475569">
                        {{ $cfg->broker->client_name ?? '—' }}
                    </span>
                </div>

                <div class="row g-2 text-center mb-3">
                    <div class="col-4"><div class="bc-val bc-sl">{{ $cfg->sl_percent }}%</div><div class="bc-lbl">Fresh SL</div></div>
                    <div class="col-4"><div class="bc-val bc-prof">{{ $cfg->profit_percent }}%</div><div class="bc-lbl">Profit Target</div></div>
                    <div class="col-4"><div class="bc-val bc-old">{{ $cfg->old_position_sl_percent }}%</div><div class="bc-lbl">Old SL</div></div>
                </div>

                <div style="font-size:11px;color:#888;background:#f8fafc;border-radius:6px;padding:7px 10px;margin-bottom:12px;">
                    Min profit sweep: <strong>{{ $cfg->min_profit_percent }}%</strong> &nbsp;|&nbsp;
                    Old: <strong>{{ str_replace('_',' ',$cfg->old_position_action) }}</strong> &nbsp;|&nbsp;
                    Sweep: <strong>{{ $cfg->enable_10am_sweep ? '✅ '.substr($cfg->sweep_time,0,5) : '❌ Off' }}</strong>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <button class="bc-btn bc-run"  onclick="run('9am',  {{ $cfg->broker_api_id }})">⏰ 9AM</button>
                    <button class="bc-btn bc-run"  onclick="run('10am', {{ $cfg->broker_api_id }})">🧹 10AM</button>
                    <button class="bc-btn bc-edit" onclick="openEdit({{ $cfg->id }})">✏ Edit</button>
                    <button class="bc-btn {{ $cfg->is_active ? 'bc-pause' : 'bc-res' }}" onclick="toggleActive({{ $cfg->id }})">
                        {{ $cfg->is_active ? '⏸ Pause' : '▶ Resume' }}
                    </button>
                    <button class="bc-btn bc-del ms-auto" onclick="delCfg({{ $cfg->id }})">🗑</button>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12" style="text-align:center;padding:40px;color:#aaa;">
            <i class="fas fa-bell" style="font-size:3rem;opacity:.3;display:block;margin-bottom:10px;"></i>
            No configs yet. Click <strong>Add Config</strong>.
        </div>
        @endforelse
    </div>

    {{-- Today's BTST orders (from oiiv_order_book) ───────────────────── --}}
    <div style="background:white;border:1px solid #e9ecef;border-radius:10px;overflow:hidden;">
        <div style="background:#f8f9fa;padding:11px 16px;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;">
            <h6 style="margin:0;font-size:13px;font-weight:700;">📋 Today's BTST Orders <small style="font-weight:400;color:#888;">(from Order Book)</small></h6>
            <div class="d-flex gap-2">
                <select id="logBroker" class="form-select form-select-sm" style="font-size:12px;width:auto" onchange="loadOrders()">
                    <option value="">All Brokers</option>
                    @foreach($brokers as $b)
                    <option value="{{ $b->id }}">{{ $b->client_name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-secondary" style="font-size:11px" onclick="loadOrders()">
                    <i class="fas fa-sync" id="refIcon"></i> Refresh
                </button>
                <a href="{{ route('oiiv-orders.order-book') }}" class="btn btn-sm btn-outline-primary" style="font-size:11px">
                    📖 Full Order Book
                </a>
            </div>
        </div>
        <div style="overflow-x:auto;max-height:380px;overflow-y:auto;">
            <table class="table bt mb-0">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Symbol</th>
                        <th>Type</th>
                        <th>Order</th>
                        <th>Units</th>
                        <th>Avg Entry ₹</th>
                        <th>LTP at Order ₹</th>
                        <th>Placed ₹</th>
                        <th>Avg Fill ₹</th>
                        <th>Status</th>
                        <th>Modified</th>
                        <th>Zerodha ID</th>
                    </tr>
                </thead>
                <tbody id="ordersBody">
                    @forelse($todayOrders as $o)
                    <tr>
                        <td><small style="color:#888">{{ $o->placed_at ? \Carbon\Carbon::parse($o->placed_at)->format('H:i:s') : '—' }}</small></td>
                        <td><strong style="font-size:11px">{{ $o->trading_symbol }}</strong></td>
                        <td>{{ $o->signal_type }}</td>
                        <td><span style="font-size:10px;font-weight:700">{{ $o->order_type }}</span></td>
                        <td>{{ $o->quantity_units }}</td>
                        <td>{{ $o->spot_price_at_signal ? '₹'.number_format($o->spot_price_at_signal,2) : '—' }}</td>
                        <td>{{ $o->trigger_price ? '₹'.number_format($o->trigger_price,2) : '—' }}</td>
                        <td>₹{{ number_format($o->placed_price,2) }}</td>
                        <td>{{ $o->average_price ? '₹'.number_format($o->average_price,2) : '—' }}</td>
                        <td>{{ $o->status }}</td>
                        <td>{{ ($o->modify_count ?? 0) > 0 ? '<span style="color:#f59e0b;font-size:10px;font-weight:700">Modified '.($o->modify_count).'×</span>' : '—' }}</td>
                        <td><small style="color:#aaa;font-size:9px">{{ $o->zerodha_order_id ?? '—' }}</small></td>
                    </tr>
                    @empty
                    <tr><td colspan="12" style="text-align:center;padding:28px;color:#aaa;">No BTST orders today</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Output log ───────────────────────────────────────────────────── --}}
    <div id="outCard" style="display:none;margin-top:14px;background:white;border:1px solid #e9ecef;border-radius:10px;overflow:hidden;">
        <div style="background:#0f172a;padding:9px 14px;display:flex;justify-content:space-between;">
            <span style="color:#60a5fa;font-size:12px;font-weight:700;">⚡ Execution Output</span>
            <button onclick="document.getElementById('outCard').style.display='none'" style="background:none;border:none;color:#aaa;cursor:pointer;">✕</button>
        </div>
        <pre class="out-log" id="outLog"></pre>
    </div>

</div>
</section>

{{-- ── ADD / EDIT MODAL ── --}}
<div class="modal fade" id="cfgModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background:linear-gradient(135deg,#0f2027,#2c5364);">
                <h5 class="modal-title text-white mb-0">
                    🔔 <span id="mTitle">Add BTST Config</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">

                <div class="row g-3 mb-2" id="addOnlyRow">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:12px">Broker <sup class="text-danger">*</sup></label>
                        <select class="form-select form-select-sm" id="fBroker">
                            <option value="">-- Select --</option>
                            @foreach($brokers as $b)
                            <option value="{{ $b->id }}">{{ $b->client_name }} ({{ $b->account_user_name }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="font-size:12px">Symbol Type <sup class="text-danger">*</sup></label>
                        <select class="form-select form-select-sm" id="fSymType">
                            <option value="BOTH">BOTH (CE &amp; PE)</option>
                            <option value="CE">CE only</option>
                            <option value="PE">PE only</option>
                        </select>
                    </div>
                </div>

                <p class="slbl">📈 Fresh Positions (yesterday's signals)</p>
                <div style="background:#f8f9ff;border-radius:8px;padding:14px;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12px">Stop Loss % <sup class="text-danger">*</sup></label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="fSl" value="15" step="0.5" min="1" max="100" oninput="updatePreview()">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted" style="font-size:10px" id="slHint">SL trigger = AVG × 0.85</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12px">Profit Target % <sup class="text-danger">*</sup></label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="fProfit" value="20" step="0.5" min="1" max="500" oninput="updatePreview()">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted" style="font-size:10px">If LTP > target at 9:15 → book at actual LTP%</small>
                        </div>
                    </div>
                </div>

                <p class="slbl">🕙 10 AM Sweep</p>
                <div style="background:#f8f9ff;border-radius:8px;padding:14px;">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:12px">Min Profit % to Close</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="fMinProfit" value="0" step="0.5" min="0" oninput="updatePreview()">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted" style="font-size:10px">0 = close any position in profit</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:12px">Enable Sweep</label>
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="fSweep" checked>
                                <label class="form-check-label" style="font-size:12px">Active</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold" style="font-size:12px">Sweep Time</label>
                            <input type="time" class="form-control form-control-sm" id="fSweepTime" value="10:00">
                        </div>
                    </div>
                </div>

                <p class="slbl">🗓 Old Positions (held ≥ 2 days)</p>
                <div style="background:#f8f9ff;border-radius:8px;padding:14px;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12px">Old Position SL %</label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="fOldSl" value="20" step="0.5" min="1" max="100" oninput="updatePreview()">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" style="font-size:12px">Old Position Action</label>
                            <select class="form-select form-select-sm" id="fOldAct">
                                <option value="cost_to_cost">Cost-to-cost (sell at AVG)</option>
                                <option value="close_profit">Close if in profit (sell at LTP)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:11px;margin-top:14px;font-size:12px;">
                    <strong>📋 Summary:</strong> <span id="previewTxt">Fill fields above.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveBtn" onclick="saveConfig()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
var _configs = {!! json_encode(
    $configs->values()->map(function ($c) {
        return [
            'id' => $c->id,
            'broker_api_id' => $c->broker_api_id,
            'symbol_type' => $c->symbol_type,
            'sl_percent' => $c->sl_percent,
            'profit_percent' => $c->profit_percent,
            'min_profit_percent' => $c->min_profit_percent,
            'enable_10am_sweep' => $c->enable_10am_sweep,
            'sweep_time' => substr($c->sweep_time, 0, 5),
            'old_position_sl_percent' => $c->old_position_sl_percent,
            'old_position_action' => $c->old_position_action,
            'is_active' => $c->is_active,
        ];
    })
) !!};

function openAdd() {
    document.getElementById('editId').value = '';
    document.getElementById('mTitle').textContent = 'Add BTST Config';
    document.getElementById('addOnlyRow').style.display = '';
    document.getElementById('fBroker').value    = '';
    document.getElementById('fSymType').value   = 'BOTH';
    document.getElementById('fSl').value        = '15';
    document.getElementById('fProfit').value    = '20';
    document.getElementById('fMinProfit').value = '0';
    document.getElementById('fSweep').checked   = true;
    document.getElementById('fSweepTime').value = '10:00';
    document.getElementById('fOldSl').value     = '20';
    document.getElementById('fOldAct').value    = 'cost_to_cost';
    updatePreview();
    new bootstrap.Modal(document.getElementById('cfgModal')).show();
}

function openEdit(id) {
    var c = _configs.find(function(x){ return x.id == id; });
    if (!c) return;
    document.getElementById('editId').value = id;
    document.getElementById('mTitle').textContent = 'Edit BTST Config';
    document.getElementById('addOnlyRow').style.display = 'none';
    document.getElementById('fSl').value        = c.sl_percent;
    document.getElementById('fProfit').value    = c.profit_percent;
    document.getElementById('fMinProfit').value = c.min_profit_percent;
    document.getElementById('fSweep').checked   = !!c.enable_10am_sweep;
    document.getElementById('fSweepTime').value = c.sweep_time || '10:00';
    document.getElementById('fOldSl').value     = c.old_position_sl_percent;
    document.getElementById('fOldAct').value    = c.old_position_action;
    updatePreview();
    new bootstrap.Modal(document.getElementById('cfgModal')).show();
}

function updatePreview() {
    var sl      = parseFloat(document.getElementById('fSl').value) || 15;
    var profit  = parseFloat(document.getElementById('fProfit').value) || 20;
    var minP    = parseFloat(document.getElementById('fMinProfit').value) || 0;
    var sweep   = document.getElementById('fSweep').checked;
    var oldSl   = parseFloat(document.getElementById('fOldSl').value) || 20;
    var oldAct  = document.getElementById('fOldAct').value;

    var slAt   = (100*(1-sl/100)).toFixed(2);
    var profAt = (100*(1+profit/100)).toFixed(2);
    var oldSlAt= (100*(1-oldSl/100)).toFixed(2);

    document.getElementById('slHint').textContent = 'e.g. AVG ₹100 → SL trigger ₹'+slAt;
    document.getElementById('previewTxt').innerHTML =
        'Fresh: SL at ₹<strong>'+slAt+'</strong> | Profit ₹<strong>'+profAt+'</strong> (if LTP>₹'+profAt+' already → book at LTP). ' +
        (sweep ? '10AM sweep: close if profit≥'+minP+'%. ' : 'Sweep off. ') +
        'Old positions: SL at ₹<strong>'+oldSlAt+'</strong> | '+oldAct.replace(/_/g,' ')+'.';
}

function saveConfig() {
    var editId = document.getElementById('editId').value;
    var isEdit = !!editId;

    var p = {
        broker_api_id:           document.getElementById('fBroker').value,
        symbol_type:             document.getElementById('fSymType').value,
        sl_percent:              parseFloat(document.getElementById('fSl').value),
        profit_percent:          parseFloat(document.getElementById('fProfit').value),
        min_profit_percent:      parseFloat(document.getElementById('fMinProfit').value),
        enable_10am_sweep:       document.getElementById('fSweep').checked ? 1 : 0,
        sweep_time:              document.getElementById('fSweepTime').value || '10:00',
        old_position_sl_percent: parseFloat(document.getElementById('fOldSl').value),
        old_position_action:     document.getElementById('fOldAct').value,
    };

    if (!isEdit && !p.broker_api_id) { iziToast.error({ message: 'Select a broker', position: 'topRight' }); return; }

    var btn = document.getElementById('saveBtn');
    btn.disabled = true; btn.textContent = 'Saving...';

    fetch(isEdit ? '{{ route("oiiv-btst.update", ":id") }}'.replace(':id', editId) : '{{ route("oiiv-btst.store") }}', {
        method: isEdit ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(p),
    })
    .then(function(r){ return r.json(); })
    .then(function(res) {
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-save"></i> Save';
        if (res.success) {
            iziToast.success({ message: res.message, position: 'topRight' });
            bootstrap.Modal.getInstance(document.getElementById('cfgModal')).hide();
            setTimeout(function(){ location.reload(); }, 700);
        } else {
            iziToast.error({ message: res.message, position: 'topRight', timeout: 5000 });
        }
    })
    .catch(function(){ btn.disabled=false; btn.innerHTML='<i class="fas fa-save"></i> Save'; iziToast.error({ message: 'Network error', position: 'topRight' }); });
}

function toggleActive(id) {
    fetch('{{ route("oiiv-btst.toggle", ":id") }}'.replace(':id', id), {
        method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(function(r){ return r.json(); })
    .then(function(res) {
        if (res.success) { iziToast.success({ message: res.message, position: 'topRight' }); setTimeout(function(){ location.reload(); }, 600); }
        else iziToast.error({ message: res.message, position: 'topRight' });
    });
}

function delCfg(id) {
    if (!confirm('Delete this BTST config?')) return;
    fetch('{{ route("oiiv-btst.destroy", ":id") }}'.replace(':id', id), {
        method: 'DELETE', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    })
    .then(function(r){ return r.json(); })
    .then(function(res) {
        if (res.success) { iziToast.success({ message: res.message, position: 'topRight' }); document.getElementById('cfg-'+id).remove(); }
        else iziToast.error({ message: res.message, position: 'topRight' });
    });
}

function run(phase, brokerId) {
    var label = phase === '9am' ? '⏰ 9:15 AM orders' : '🧹 10 AM sweep';
    if (!confirm('Run '+label+' now?\n\nThis places real orders on Zerodha.')) return;

    document.getElementById('outCard').style.display = '';
    document.getElementById('outLog').textContent = 'Running '+label+'...\n';

    fetch('{{ route("oiiv-btst.run") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ phase: phase, broker_id: brokerId || null }),
    })
    .then(function(r){ return r.json(); })
    .then(function(res) {
        if (res.success) iziToast.success({ message: res.message, position: 'topRight', timeout: 4000 });
        else             iziToast.error({ message: res.message || 'Failed', position: 'topRight', timeout: 5000 });
        showOutput(res.output || '(no output)');
        loadOrders();
    })
    .catch(function(e){ iziToast.error({ message: 'Network error', position: 'topRight' }); });
}

function loadOrders() {
    var brokerId = document.getElementById('logBroker').value;
    document.getElementById('refIcon').classList.add('fa-spin');

    fetch('{{ route("oiiv-btst.today-orders") }}' + (brokerId ? '?broker_id='+brokerId : ''))
    .then(function(r){ return r.json(); })
    .then(function(res) {
        document.getElementById('refIcon').classList.remove('fa-spin');
        if (!res.success) return;
        var tbody = document.getElementById('ordersBody');
        if (!res.data || !res.data.length) {
            tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:26px;color:#aaa;">No BTST orders today</td></tr>';
            return;
        }
        tbody.innerHTML = res.data.map(function(o) {
            return '<tr>' +
                '<td><small style="color:#888">' + (o.placed_at||'—') + '</small></td>' +
                '<td><strong style="font-size:11px">' + o.symbol + '</strong></td>' +
                '<td>' + sigBadge(o.signal_type) + '</td>' +
                '<td><span style="font-size:10px;font-weight:700">' + o.order_type + '</span></td>' +
                '<td>' + (o.quantity_units||0) + '</td>' +
                '<td>' + (o.avg_entry ? '₹'+Number(o.avg_entry).toFixed(2) : '—') + '</td>' +
                '<td>' + (o.trigger_price ? '₹'+Number(o.trigger_price).toFixed(2) : '—') + '</td>' +
                '<td>₹' + Number(o.placed_price||0).toFixed(2) + '</td>' +
                '<td>' + (o.avg_fill ? '₹'+Number(o.avg_fill).toFixed(2) : '—') + '</td>' +
                '<td>' + stBadge(o.status) + '</td>' +
                '<td>' + (o.was_modified ? '<span style="color:#f59e0b;font-size:10px;font-weight:700">×'+o.modify_count+'</span>' : '—') + '</td>' +
                '<td><small style="color:#aaa;font-size:9px">' + (o.zerodha_id||'—') + '</small></td>' +
                '</tr>';
        }).join('');
    })
    .catch(function(){ document.getElementById('refIcon').classList.remove('fa-spin'); });
}

function sigBadge(t) {
    var m = { 'BTST_SL':'sig-sl 🛑 SL', 'BTST_PROFIT':'sig-prof 💰 Profit', 'BTST_SWEEP':'sig-sw 🧹 Sweep', 'BTST_COSTCOST':'sig-c2c 🔄 Cost2Cost' };
    var parts = (m[t]||'sig-sw '+t).split(' ');
    var cls   = parts.shift();
    return '<span class="'+cls+'">'+parts.join(' ')+'</span>';
}

function stBadge(st) {
    if (!st) return '—';
    var s = st.toUpperCase();
    if (s==='COMPLETE') return '<span class="st-complete">✅ DONE</span>';
    if (s==='OPEN')     return '<span class="st-open">🔵 OPEN</span>';
    if (s==='CANCELLED')return '<span class="st-cancel">❌ CANCELLED</span>';
    if (s==='REJECTED') return '<span class="st-reject">🚫 REJECTED</span>';
    if (s==='DRY_RUN')  return '<span style="color:#6d28d9;font-weight:700">DRY</span>';
    return '<span>'+st+'</span>';
}

function showOutput(text) {
    var log = document.getElementById('outLog');
    log.innerHTML = text.split('\n').map(function(l) {
        if (/✅|placed/i.test(l))   return '<span class="ok">'+esc(l)+'</span>';
        if (/✗|❌|fail/i.test(l))   return '<span class="err">'+esc(l)+'</span>';
        if (/⚠️?|warn/i.test(l))   return '<span class="warn">'+esc(l)+'</span>';
        if (/──|Broker|Phase/i.test(l)) return '<span class="inf">'+esc(l)+'</span>';
        return esc(l);
    }).join('\n');
    log.scrollTop = log.scrollHeight;
    document.getElementById('outCard').scrollIntoView({ behavior:'smooth', block:'nearest' });
}

function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

document.addEventListener('DOMContentLoaded', function(){ updatePreview(); });
</script>
@endpush