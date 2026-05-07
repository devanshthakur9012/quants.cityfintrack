@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
.page-header {
    background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
    color:white; padding:22px 26px; border-radius:14px; margin-bottom:22px;
    border:1px solid rgba(243,156,18,.3); box-shadow:0 6px 24px rgba(0,0,0,.4);
}
.page-header h4 { margin:0 0 5px; font-size:18px; font-weight:800; }
.page-header p  { margin:0; font-size:11px; color:rgba(255,255,255,.6); }
.stats-box {
    background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08);
    padding:14px 10px; border-radius:10px; text-align:center;
    border-left:4px solid #f39c12; margin-bottom:14px; transition:transform .2s;
}
.stats-box:hover { transform:translateY(-2px); }
.stats-box small  { display:block; color:rgba(255,255,255,.5); font-size:10px; text-transform:uppercase; letter-spacing:.4px; }
.stats-box strong { display:block; font-size:1.4rem; font-weight:800; margin-top:4px; color:white; }
.custom--table thead th,
.custom--table tbody td { vertical-align:middle; font-size:12px; padding:9px 10px !important; }
.modal-header-grad {
    background:linear-gradient(135deg,#0f2027,#203a43,#2c5364);
    color:white; border-radius:12px 12px 0 0; padding:18px 24px;
    border-bottom:1px solid rgba(243,156,18,.3);
}
.modal-header-grad .btn-close { filter:invert(1) grayscale(1); opacity:1; }
.modal-content { border-radius:14px; border:none; box-shadow:0 20px 60px rgba(0,0,0,.4); background:#1a1f2e; color:#eee; }
.modal-footer  { border-top:1px solid rgba(255,255,255,.08); background:#1a1f2e; border-radius:0 0 14px 14px; padding:14px 24px; }
.modal-body label, label { color:rgba(255,255,255,.8) !important; font-size:12px; font-weight:600; }
.modal-body .form-control,
.modal-body .form-select { background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.15); color:#eee; font-size:12px; border-radius:6px; }
.modal-body .form-control:focus,
.modal-body .form-select:focus { background:rgba(255,255,255,.12); border-color:#f39c12; box-shadow:0 0 0 2px rgba(243,156,18,.2); color:#eee; }
.modal-body .form-control option,
.modal-body .form-select option { background:#1a1f2e; color:#eee; }
.ig-dark { background:rgba(255,255,255,.1) !important; color:#eee !important; border-color:rgba(255,255,255,.15) !important; }
.section-label {
    font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.8px;
    color:#f39c12; margin:18px 0 10px; padding-bottom:6px; border-bottom:2px solid rgba(243,156,18,.25);
}
.form-section { background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:16px; margin-bottom:4px; }
.qty-mode-pills { display:flex; gap:8px; margin-bottom:12px; }
.qty-pill { flex:1; text-align:center; padding:8px; border-radius:8px; cursor:pointer; border:2px solid rgba(255,255,255,.15); font-size:12px; font-weight:700; color:rgba(255,255,255,.6); transition:all .2s; user-select:none; }
.qty-pill.active { border-color:#f39c12; background:rgba(243,156,18,.12); color:#f39c12; }
.inv-info { background:rgba(168,85,247,.08); border:1px solid rgba(168,85,247,.25); border-radius:8px; padding:10px 14px; margin-bottom:12px; font-size:11px; color:rgba(255,255,255,.75); line-height:1.7; }
.inv-info code { background:rgba(168,85,247,.2); color:#c084fc; padding:1px 5px; border-radius:4px; }
.per-symbol-note { background:rgba(234,179,8,.08); border:1px solid rgba(234,179,8,.3); border-radius:8px; padding:10px 14px; margin-bottom:12px; font-size:11px; color:rgba(255,255,255,.8); line-height:1.7; }
.per-symbol-note strong { color:#facc15; }
.sl-section { border:1px solid rgba(231,76,60,.3); background:rgba(231,76,60,.05); border-radius:10px; padding:14px; margin-top:4px; }
.sl-info { background:rgba(231,76,60,.08); border:1px solid rgba(231,76,60,.2); border-radius:6px; padding:9px 12px; margin-bottom:12px; font-size:11px; color:rgba(255,255,255,.65); line-height:1.7; }
.target-section { border:1px solid rgba(34,197,94,.3); background:rgba(34,197,94,.04); border-radius:10px; padding:14px; margin-top:12px; }
.target-info { background:rgba(34,197,94,.07); border:1px solid rgba(34,197,94,.2); border-radius:6px; padding:9px 12px; margin-bottom:12px; font-size:11px; color:rgba(255,255,255,.65); line-height:1.7; }
/* SL stepper */
.sl-stepper-wrap { display:flex; align-items:stretch; border:1px solid rgba(231,76,60,.45); border-radius:8px; overflow:hidden; }
.sl-step-btn { width:42px; min-width:42px; background:rgba(231,76,60,.18); border:none; color:#f87171; font-size:22px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .15s; user-select:none; padding:0; }
.sl-step-btn:hover  { background:rgba(231,76,60,.38); color:#fff; }
.sl-step-input { flex:1; min-width:0; background:rgba(255,255,255,.07) !important; border:none !important; border-left:1px solid rgba(231,76,60,.3) !important; border-right:1px solid rgba(231,76,60,.3) !important; color:#fff !important; font-size:16px !important; font-weight:700 !important; text-align:center !important; border-radius:0 !important; }
.sl-step-input:focus { box-shadow:none !important; background:rgba(255,255,255,.13) !important; }
.sl-step-input::-webkit-outer-spin-button,.sl-step-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.sl-step-input[type=number] { -moz-appearance:textfield; }
/* Target stepper */
.tgt-stepper-wrap { display:flex; align-items:stretch; border:1px solid rgba(34,197,94,.45); border-radius:8px; overflow:hidden; }
.tgt-step-btn { width:42px; min-width:42px; background:rgba(34,197,94,.18); border:none; color:#4ade80; font-size:22px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:background .15s; user-select:none; padding:0; }
.tgt-step-btn:hover  { background:rgba(34,197,94,.38); color:#fff; }
.tgt-step-input { flex:1; min-width:0; background:rgba(255,255,255,.07) !important; border:none !important; border-left:1px solid rgba(34,197,94,.3) !important; border-right:1px solid rgba(34,197,94,.3) !important; color:#fff !important; font-size:16px !important; font-weight:700 !important; text-align:center !important; border-radius:0 !important; }
.tgt-step-input:focus { box-shadow:none !important; background:rgba(255,255,255,.13) !important; }
.tgt-step-input::-webkit-outer-spin-button,.tgt-step-input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
.tgt-step-input[type=number] { -moz-appearance:textfield; }
/* Preset chips */
.sl-presets,.tgt-presets { display:flex; gap:5px; flex-wrap:wrap; margin-top:7px; }
.sl-preset { padding:3px 11px; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer; user-select:none; background:rgba(231,76,60,.10); border:1px solid rgba(231,76,60,.3); color:#fca5a5; transition:all .15s; }
.sl-preset:hover  { background:rgba(231,76,60,.30); color:#fff; border-color:#f87171; }
.sl-preset.active { background:rgba(231,76,60,.50); color:#fff; border-color:#ef4444; }
.tgt-preset { padding:3px 11px; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer; user-select:none; background:rgba(34,197,94,.10); border:1px solid rgba(34,197,94,.3); color:#86efac; transition:all .15s; }
.tgt-preset:hover  { background:rgba(34,197,94,.30); color:#fff; border-color:#4ade80; }
.tgt-preset.active { background:rgba(34,197,94,.50); color:#fff; border-color:#22c55e; }
.form-check-input { background-color:rgba(255,255,255,.15); border-color:rgba(255,255,255,.3); }
.form-check-input:checked { background-color:#f39c12; border-color:#f39c12; }
.form-check-label { color:rgba(255,255,255,.8) !important; font-size:12px; font-weight:600; }
.status-active   { background:#d4edda; color:#155724; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
.status-inactive { background:#f8d7da; color:#721c24; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
.badge { color:#000 !important; }
.btn-amber       { background:linear-gradient(135deg,#f39c12,#e67e22); color:white; border:none; font-weight:700; border-radius:8px; }
.btn-amber:hover { background:linear-gradient(135deg,#e67e22,#d35400); color:white; }
.btn-outline-amber       { border:1px solid #f39c12; color:#f39c12; background:transparent; font-weight:600; border-radius:8px; }
.btn-outline-amber:hover { background:rgba(243,156,18,.1); color:#f39c12; }
.btn-action { padding:5px 10px; font-size:11px; border-radius:6px; margin:1px; }
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    {{-- ── Header ── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
            <div>
                <h4>⚡ {{ $pageTitle }}</h4>
                <p>Manage NIFTY breakout configs — threshold, quantity (lots / investment), stop-loss, profit target, signal filter</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('nifty-driven-breakout.index') }}" class="btn btn-sm btn-outline-amber">
                    <i class="fas fa-chart-line"></i> Analysis
                </a>
                <button class="btn btn-sm btn-amber" data-bs-toggle="modal" data-bs-target="#createModal">
                    <i class="fas fa-plus"></i> New Config
                </button>
                <form action="{{ route('nifty-driven-breakout.run-now') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-warning fw-bold"
                        onclick="return confirm('Run signal detection + place orders NOW?')">
                        <i class="fas fa-play-circle"></i> Run Now
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Stats ── --}}
    <div class="row mb-3">
        <div class="col-6 col-md-3"><div class="stats-box"><small>Total Configs</small><strong style="color:#f39c12;">{{ $configs->total() }}</strong></div></div>
        <div class="col-6 col-md-3"><div class="stats-box" style="border-left-color:#27ae60;"><small>Active</small><strong style="color:#27ae60;">{{ $configs->where('status',true)->count() }}</strong></div></div>
        <div class="col-6 col-md-3"><div class="stats-box" style="border-left-color:#e74c3c;"><small>Inactive</small><strong style="color:#e74c3c;">{{ $configs->where('status',false)->count() }}</strong></div></div>
        <div class="col-6 col-md-3"><div class="stats-box" style="border-left-color:#3498db;"><small>Total Orders</small><strong style="color:#3498db;">{{ $configs->sum('orders_count') }}</strong></div></div>
    </div>

    {{-- ── Table ── --}}
    <div class="table-responsive">
        <table class="table custom--table">
            <thead>
                <tr>
                    <th>#</th><th>Broker</th><th>Threshold</th><th>Filter</th><th>Mode</th>
                    <th>Order</th><th>Product</th><th>Qty Mode</th>
                    <th>Index CE</th><th>Index PE</th><th>Stock CE</th><th>Stock PE</th>
                    <th>Stop-Loss</th><th>Target</th><th>Symbols</th><th>Orders</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($configs as $i => $cfg)
                @php $isInv = $cfg->quantity_mode === 'investment'; @endphp
                <tr>
                    <td><strong>{{ $configs->firstItem() + $i }}</strong></td>
                    <td><strong style="color:#f39c12;">{{ $cfg->broker->client_name ?? 'N/A' }}</strong></td>
                    <td><span class="badge bg-warning text-dark" style="font-size:11px;font-weight:800;">±{{ $cfg->threshold }} pts</span></td>
                    <td>
                        @if($cfg->filter==='CE') <span class="badge bg-success">CE Only</span>
                        @elseif($cfg->filter==='PE') <span class="badge bg-danger">PE Only</span>
                        @else <span class="badge bg-primary">Both</span>
                        @endif
                    </td>
                    <td>
                        @if($cfg->signal_mode==='align') <span class="badge bg-info text-dark">↗ Align</span>
                        @else <span class="badge bg-dark">↔ Opposite</span>
                        @endif
                    </td>
                    <td><span class="badge bg-{{ $cfg->order_type==='MARKET'?'success':'secondary' }}">{{ $cfg->order_type }}</span></td>
                    <td><span class="badge bg-{{ $cfg->product==='NRML'?'primary':'warning' }} {{ $cfg->product==='NRML'?'':'text-dark' }}">{{ $cfg->product }}</span></td>
                    <td>
                        @if($isInv) <span class="badge" style="background:#a855f7;color:white!important;">₹ Investment</span>
                        @else <span class="badge bg-secondary">Lots</span>
                        @endif
                    </td>
                    <td><small class="text-success fw-bold">{{ $isInv ? '₹'.number_format($cfg->index_ce_investment).'/sym' : $cfg->index_ce_quantity.' L' }}</small></td>
                    <td><small class="text-danger fw-bold">{{ $isInv ? '₹'.number_format($cfg->index_pe_investment).'/sym' : $cfg->index_pe_quantity.' L' }}</small></td>
                    <td><small class="text-success fw-bold">{{ $isInv ? '₹'.number_format($cfg->stock_ce_investment).'/sym' : $cfg->stock_ce_quantity.' L' }}</small></td>
                    <td><small class="text-danger fw-bold">{{ $isInv ? '₹'.number_format($cfg->stock_pe_investment).'/sym' : $cfg->stock_pe_quantity.' L' }}</small></td>
                    <td>
                        @if($cfg->enable_stoploss)
                            <span class="badge bg-danger" style="font-size:10px;">🛡 -{{ $cfg->stoploss_value }}{{ $cfg->stoploss_type==='pct'?'%':'pts' }} ({{ $cfg->stoploss_order_type }})</span>
                        @else <span class="text-muted" style="font-size:11px;">None</span>
                        @endif
                    </td>
                    <td>
                        @if($cfg->enable_target)
                            <span class="badge bg-success" style="font-size:10px;">🎯 +{{ $cfg->target_value }}{{ $cfg->target_type==='pct'?'%':'pts' }} ({{ $cfg->target_order_type }})</span>
                        @else <span class="text-muted" style="font-size:11px;">None</span>
                        @endif
                    </td>
                    <td>
                        @if($cfg->allowed_symbols)
                            <span style="font-size:10px;color:#f39c12;">{{ Str::limit($cfg->allowed_symbols,30) }}</span>
                        @else <span class="text-muted" style="font-size:10px;">All</span>
                        @endif
                    </td>
                    <td><a href="{{ route('nifty-driven-breakout.orders',$cfg->id) }}" class="text-primary fw-bold">{{ $cfg->orders_count }}</a></td>
                    <td>
                        @if($cfg->status) <span class="status-active">✅ Active</span>
                        @else <span class="status-inactive">❌ Off</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-info btn-action btn-edit"
                            data-id="{{ $cfg->id }}"
                            data-broker="{{ $cfg->broker_api_id }}"
                            data-threshold="{{ $cfg->threshold }}"
                            data-filter="{{ $cfg->filter }}"
                            data-signal_mode="{{ $cfg->signal_mode }}"
                            data-order_type="{{ $cfg->order_type }}"
                            data-product="{{ $cfg->product }}"
                            data-disc_ltp="{{ $cfg->disc_ltp }}"
                            data-quantity_mode="{{ $cfg->quantity_mode }}"
                            data-index_ce_quantity="{{ $cfg->index_ce_quantity }}"
                            data-index_pe_quantity="{{ $cfg->index_pe_quantity }}"
                            data-stock_ce_quantity="{{ $cfg->stock_ce_quantity }}"
                            data-stock_pe_quantity="{{ $cfg->stock_pe_quantity }}"
                            data-index_ce_investment="{{ $cfg->index_ce_investment }}"
                            data-index_pe_investment="{{ $cfg->index_pe_investment }}"
                            data-stock_ce_investment="{{ $cfg->stock_ce_investment }}"
                            data-stock_pe_investment="{{ $cfg->stock_pe_investment }}"
                            data-enable_stoploss="{{ $cfg->enable_stoploss?'1':'0' }}"
                            data-stoploss_type="{{ $cfg->stoploss_type }}"
                            data-stoploss_value="{{ $cfg->stoploss_value }}"
                            data-stoploss_order_type="{{ $cfg->stoploss_order_type }}"
                            data-enable_target="{{ $cfg->enable_target?'1':'0' }}"
                            data-target_type="{{ $cfg->target_type }}"
                            data-target_value="{{ $cfg->target_value }}"
                            data-target_order_type="{{ $cfg->target_order_type }}"
                            data-allowed_symbols="{{ $cfg->allowed_symbols }}"
                            data-status="{{ $cfg->status?'1':'0' }}"
                            title="Edit"><i class="fas fa-edit"></i></button>

                        <form action="{{ route('nifty-driven-breakout.toggle',$cfg->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-{{ $cfg->status?'warning':'success' }} btn-action"
                                onclick="return confirm('{{ $cfg->status?'Deactivate':'Activate' }} this config?')"
                                title="{{ $cfg->status?'Deactivate':'Activate' }}">
                                <i class="fas fa-{{ $cfg->status?'pause':'play' }}"></i>
                            </button>
                        </form>

                        <a href="{{ route('nifty-driven-breakout.orders',$cfg->id) }}" class="btn btn-primary btn-action" title="Orders">
                            <i class="fas fa-list"></i>
                        </a>

                        <form action="{{ route('nifty-driven-breakout.destroy',$cfg->id) }}" method="POST" class="d-inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-action"
                                onclick="return confirm('Delete this config?')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="18" class="text-center py-5">
                        <i class="fas fa-cog" style="font-size:2.5rem;opacity:.3;"></i>
                        <p class="mt-3" style="color:rgba(255,255,255,.4);">No configs yet. <a href="#" data-bs-toggle="modal" data-bs-target="#createModal" style="color:#f39c12;">Create one</a>.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($configs->hasPages()) <div class="mt-3">{{ $configs->links() }}</div> @endif

</div>
</section>

{{-- ═══════════════════ CREATE MODAL ═══════════════════ --}}
<div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-grad">
                <h5 class="modal-title mb-0"><i class="fas fa-plus-circle me-2"></i> New NIFTY Breakout Config</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('nifty-driven-breakout.config-store') }}" method="POST" novalidate>
                @csrf
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog me-1"></i> Broker & Signal Settings</p>
                    <div class="form-section">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Broker Account</label>
                                <select name="broker_api_id" id="c_broker_api_id" class="form-select" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $b)<option value="{{ $b->id }}">{{ $b->client_name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">NIFTY Threshold (pts)</label>
                                <input type="number" name="threshold" id="c_threshold" class="form-control" value="30" min="1" max="500" step="1" required />
                                <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">Signal fires when NIFTY moves ±this from 09:15 close</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Signal Filter</label>
                                <select name="filter" id="c_filter" class="form-select" required>
                                    <option value="BOTH">Both CE + PE</option>
                                    <option value="CE">CE Only</option>
                                    <option value="PE">PE Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-md-4">
                                <label class="form-label">Signal Mode</label>
                                <select name="signal_mode" id="c_signal_mode" class="form-select" required>
                                    <option value="align">↗ ALIGN — CE signal → Buy CE</option>
                                    <option value="opposite">↔ OPPOSITE — CE signal → Buy PE</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" id="c_status" class="form-select" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Symbol Filter <small style="font-size:9px;opacity:.55;">(empty = ALL)</small></label>
                                <select name="allowed_symbols[]" id="c_allowed_symbols" class="form-select" multiple size="3">
                                    @foreach($availableSymbols as $sym)<option value="{{ $sym }}">{{ $sym }}</option>@endforeach
                                </select>
                                <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">Ctrl+click to multi-select. Blank = all symbols.</div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-shopping-cart me-1"></i> Order Settings</p>
                    <div class="form-section">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Order Type</label>
                                <select name="order_type" id="c_order_type" class="form-select" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Product</label>
                                <select name="product" id="c_product" class="form-select" required>
                                    <option value="NRML">NRML (carry-forward)</option>
                                    <option value="MIS">MIS (intraday)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Discount % on LTP <small style="opacity:.55;">(LIMIT only)</small></label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" id="c_disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <span class="input-group-text ig-dark">%</span>
                                </div>
                                <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">0 = place at exact LTP</div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-layer-group me-1"></i> Quantity / Investment</p>
                    <input type="hidden" name="quantity_mode" id="c_quantity_mode" value="lots" />
                    <div class="qty-mode-pills">
                        <div class="qty-pill active" data-modal="c" data-val="lots">📦 Fixed Lots</div>
                        <div class="qty-pill" data-modal="c" data-val="investment">💰 Fixed Investment (₹)</div>
                    </div>
                    <div id="c_lots_section">
                        <div class="form-section">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#22c55e;">Index CE</span> (lots)</label>
                                    <input type="number" name="index_ce_quantity" id="c_index_ce_quantity" class="form-control" value="0" min="0" />
                                    <div class="form-text" style="color:rgba(255,255,255,.3);font-size:10px;">NIFTY / BANKNIFTY etc.</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#ef4444;">Index PE</span> (lots)</label>
                                    <input type="number" name="index_pe_quantity" id="c_index_pe_quantity" class="form-control" value="0" min="0" />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#22c55e;">Stock CE</span> (lots)</label>
                                    <input type="number" name="stock_ce_quantity" id="c_stock_ce_quantity" class="form-control" value="0" min="0" />
                                    <div class="form-text" style="color:rgba(255,255,255,.3);font-size:10px;">RELIANCE, INFY etc.</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#ef4444;">Stock PE</span> (lots)</label>
                                    <input type="number" name="stock_pe_quantity" id="c_stock_pe_quantity" class="form-control" value="0" min="0" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="c_investment_section" style="display:none;">
                        <div class="form-section">
                            <div class="per-symbol-note">
                                <strong>📌 Investment is per symbol, per order — not shared.</strong><br>
                                If Index CE = ₹1,00,000 and 10 index symbols match, <strong>each symbol independently gets ₹1,00,000.</strong><br>
                                Total deployed = ₹1L × number of matching symbols.
                            </div>
                            <div class="inv-info">💡 Lots = <code>floor(investment ÷ (LTP × lot_size))</code>&nbsp;&nbsp;<span style="color:rgba(255,255,255,.5);">e.g. ₹1,00,000 ÷ (₹250 × 25) = <strong style="color:#c084fc;">16 lots</strong></span></div>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#22c55e;">Index CE</span> (₹/symbol)</label>
                                    <div class="input-group"><span class="input-group-text ig-dark">₹</span><input type="number" name="index_ce_investment" id="c_index_ce_investment" class="form-control" value="0" min="0" step="1000" /></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#ef4444;">Index PE</span> (₹/symbol)</label>
                                    <div class="input-group"><span class="input-group-text ig-dark">₹</span><input type="number" name="index_pe_investment" id="c_index_pe_investment" class="form-control" value="0" min="0" step="1000" /></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#22c55e;">Stock CE</span> (₹/symbol)</label>
                                    <div class="input-group"><span class="input-group-text ig-dark">₹</span><input type="number" name="stock_ce_investment" id="c_stock_ce_investment" class="form-control" value="0" min="0" step="1000" /></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#ef4444;">Stock PE</span> (₹/symbol)</label>
                                    <div class="input-group"><span class="input-group-text ig-dark">₹</span><input type="number" name="stock_pe_investment" id="c_stock_pe_investment" class="form-control" value="0" min="0" step="1000" /></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-shield-alt me-1"></i> Stop-Loss <small style="font-size:10px;opacity:.55;font-weight:400;">(protects against downside loss)</small></p>
                    <div class="sl-section">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="c_enable_stoploss" name="enable_stoploss" value="1" onchange="toggleBody('c_sl_body',this.checked)" />
                                <label class="form-check-label" for="c_enable_stoploss">Enable Stop-Loss Order</label>
                            </div>
                            <small style="color:rgba(255,255,255,.4);font-size:10px;">SELL placed immediately after buy succeeds</small>
                        </div>
                        <div id="c_sl_body" style="display:none;">
                            <div class="sl-info">
                                <strong style="color:#f87171;">Trigger price (below entry):</strong><br>
                                % → entry × (1 − value/100) &nbsp;<span style="opacity:.5;">e.g. ₹200, SL 30% → ₹140.00</span><br>
                                pts → entry − value &nbsp;<span style="opacity:.5;">e.g. ₹200, SL 50 pts → ₹150.00</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Stop-Loss Type</label>
                                    <select name="stoploss_type" id="c_stoploss_type" class="form-select" onchange="updatePreview('c','sl')">
                                        <option value="pct">Percentage (% drop)</option>
                                        <option value="points">Points (absolute drop)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Stop-Loss Value <span class="badge bg-danger ms-1" id="c_sl_unit">%</span></label>
                                    <div class="sl-stepper-wrap">
                                        <button type="button" class="sl-step-btn" onclick="stepVal('c_stoploss_value',-0.5,'c','sl')">−</button>
                                        <input type="number" name="stoploss_value" id="c_stoploss_value" class="form-control sl-step-input" value="30" min="0.1" max="10000" step="0.5" oninput="updatePreview('c','sl');syncChips('c','sl')" />
                                        <button type="button" class="sl-step-btn" onclick="stepVal('c_stoploss_value',0.5,'c','sl')">+</button>
                                    </div>
                                    <div class="sl-presets">
                                        <span class="sl-preset" onclick="pickChip(this,'c_stoploss_value','c','sl')">10</span>
                                        <span class="sl-preset" onclick="pickChip(this,'c_stoploss_value','c','sl')">20</span>
                                        <span class="sl-preset active" onclick="pickChip(this,'c_stoploss_value','c','sl')">30</span>
                                        <span class="sl-preset" onclick="pickChip(this,'c_stoploss_value','c','sl')">50</span>
                                        <span class="sl-preset" onclick="pickChip(this,'c_stoploss_value','c','sl')">100</span>
                                    </div>
                                    <div class="form-text" id="c_sl_preview" style="color:#f87171;font-size:10px;margin-top:5px;">📌 Entry ₹200 × (1 − 30/100) = ₹140.00</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">SL Order Type</label>
                                    <select name="stoploss_order_type" id="c_stoploss_order_type" class="form-select">
                                        <option value="SL-M">SL-M (market on trigger)</option>
                                        <option value="SL">SL (limit on trigger)</option>
                                    </select>
                                    <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">SL-M recommended — guaranteed exit</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label" style="color:#22c55e;border-bottom-color:rgba(34,197,94,.25);"><i class="fas fa-bullseye me-1"></i> Profit Target <small style="font-size:10px;opacity:.55;font-weight:400;">(locks in upside gain)</small></p>
                    <div class="target-section">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="c_enable_target" name="enable_target" value="1" onchange="toggleBody('c_target_body',this.checked)" />
                                <label class="form-check-label" for="c_enable_target">Enable Profit Target Order</label>
                            </div>
                            <small style="color:rgba(255,255,255,.4);font-size:10px;">SELL placed immediately after buy succeeds</small>
                        </div>
                        <div id="c_target_body" style="display:none;">
                            <div class="target-info">
                                <strong style="color:#4ade80;">Target price (above entry):</strong><br>
                                % → entry × (1 + value/100) &nbsp;<span style="opacity:.5;">e.g. ₹200, Target 50% → ₹300.00</span><br>
                                pts → entry + value &nbsp;<span style="opacity:.5;">e.g. ₹200, Target 80 pts → ₹280.00</span><br>
                                <span style="color:rgba(255,255,255,.4);font-size:10px;">⚠ If both SL + Target placed, only ONE executes. The other stays open till EOD.</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Target Type</label>
                                    <select name="target_type" id="c_target_type" class="form-select" onchange="updatePreview('c','tgt')">
                                        <option value="pct">Percentage (% gain)</option>
                                        <option value="points">Points (absolute gain)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Target Value <span class="badge bg-success ms-1" id="c_tgt_unit">%</span></label>
                                    <div class="tgt-stepper-wrap">
                                        <button type="button" class="tgt-step-btn" onclick="stepVal('c_target_value',-0.5,'c','tgt')">−</button>
                                        <input type="number" name="target_value" id="c_target_value" class="form-control tgt-step-input" value="50" min="0.1" max="10000" step="0.5" oninput="updatePreview('c','tgt');syncChips('c','tgt')" />
                                        <button type="button" class="tgt-step-btn" onclick="stepVal('c_target_value',0.5,'c','tgt')">+</button>
                                    </div>
                                    <div class="tgt-presets">
                                        <span class="tgt-preset" onclick="pickChip(this,'c_target_value','c','tgt')">20</span>
                                        <span class="tgt-preset" onclick="pickChip(this,'c_target_value','c','tgt')">30</span>
                                        <span class="tgt-preset active" onclick="pickChip(this,'c_target_value','c','tgt')">50</span>
                                        <span class="tgt-preset" onclick="pickChip(this,'c_target_value','c','tgt')">100</span>
                                        <span class="tgt-preset" onclick="pickChip(this,'c_target_value','c','tgt')">200</span>
                                    </div>
                                    <div class="form-text" id="c_tgt_preview" style="color:#4ade80;font-size:10px;margin-top:5px;">📌 Entry ₹200 × (1 + 50/100) = ₹300.00</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Target Order Type</label>
                                    <select name="target_order_type" id="c_target_order_type" class="form-select">
                                        <option value="LIMIT">LIMIT (resting sell at target)</option>
                                        <option value="SL-M">SL-M (market sell on trigger)</option>
                                        <option value="SL">SL (limit sell on trigger)</option>
                                    </select>
                                    <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">LIMIT recommended — exact target price</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-amber px-4"><i class="fas fa-save me-1"></i> Create Config</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ═══════════════════ EDIT MODAL ═══════════════════ --}}
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header modal-header-grad">
                <h5 class="modal-title mb-0"><i class="fas fa-edit me-2"></i> Edit NIFTY Breakout Config</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" action="#" method="POST" novalidate>
                @csrf @method('PUT')
                <div class="modal-body px-4 py-3">

                    <p class="section-label"><i class="fas fa-cog me-1"></i> Broker & Signal Settings</p>
                    <div class="form-section">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Broker Account</label>
                                <select name="broker_api_id" id="e_broker_api_id" class="form-select" required>
                                    <option value="">-- Select Broker --</option>
                                    @foreach($brokers as $b)<option value="{{ $b->id }}">{{ $b->client_name }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">NIFTY Threshold (pts)</label>
                                <input type="number" name="threshold" id="e_threshold" class="form-control" value="30" min="1" max="500" step="1" required />
                                <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">Signal fires when NIFTY moves ±this from 09:15 close</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Signal Filter</label>
                                <select name="filter" id="e_filter" class="form-select" required>
                                    <option value="BOTH">Both CE + PE</option>
                                    <option value="CE">CE Only</option>
                                    <option value="PE">PE Only</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-0">
                            <div class="col-md-4">
                                <label class="form-label">Signal Mode</label>
                                <select name="signal_mode" id="e_signal_mode" class="form-select" required>
                                    <option value="align">↗ ALIGN — CE signal → Buy CE</option>
                                    <option value="opposite">↔ OPPOSITE — CE signal → Buy PE</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" id="e_status" class="form-select" required>
                                    <option value="1">✅ Active</option>
                                    <option value="0">❌ Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Symbol Filter <small style="font-size:9px;opacity:.55;">(empty = ALL)</small></label>
                                <select name="allowed_symbols[]" id="e_allowed_symbols" class="form-select" multiple size="3">
                                    @foreach($availableSymbols as $sym)<option value="{{ $sym }}">{{ $sym }}</option>@endforeach
                                </select>
                                <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">Ctrl+click to multi-select. Blank = all symbols.</div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-shopping-cart me-1"></i> Order Settings</p>
                    <div class="form-section">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Order Type</label>
                                <select name="order_type" id="e_order_type" class="form-select" required>
                                    <option value="LIMIT">LIMIT</option>
                                    <option value="MARKET">MARKET</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Product</label>
                                <select name="product" id="e_product" class="form-select" required>
                                    <option value="NRML">NRML (carry-forward)</option>
                                    <option value="MIS">MIS (intraday)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Discount % on LTP <small style="opacity:.55;">(LIMIT only)</small></label>
                                <div class="input-group">
                                    <input type="number" name="disc_ltp" id="e_disc_ltp" class="form-control" value="0" min="0" max="100" step="0.1" required />
                                    <span class="input-group-text ig-dark">%</span>
                                </div>
                                <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">0 = place at exact LTP</div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-layer-group me-1"></i> Quantity / Investment</p>
                    <input type="hidden" name="quantity_mode" id="e_quantity_mode" value="lots" />
                    <div class="qty-mode-pills">
                        <div class="qty-pill active" data-modal="e" data-val="lots">📦 Fixed Lots</div>
                        <div class="qty-pill" data-modal="e" data-val="investment">💰 Fixed Investment (₹)</div>
                    </div>
                    <div id="e_lots_section">
                        <div class="form-section">
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#22c55e;">Index CE</span> (lots)</label>
                                    <input type="number" name="index_ce_quantity" id="e_index_ce_quantity" class="form-control" value="0" min="0" />
                                    <div class="form-text" style="color:rgba(255,255,255,.3);font-size:10px;">NIFTY / BANKNIFTY etc.</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#ef4444;">Index PE</span> (lots)</label>
                                    <input type="number" name="index_pe_quantity" id="e_index_pe_quantity" class="form-control" value="0" min="0" />
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#22c55e;">Stock CE</span> (lots)</label>
                                    <input type="number" name="stock_ce_quantity" id="e_stock_ce_quantity" class="form-control" value="0" min="0" />
                                    <div class="form-text" style="color:rgba(255,255,255,.3);font-size:10px;">RELIANCE, INFY etc.</div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#ef4444;">Stock PE</span> (lots)</label>
                                    <input type="number" name="stock_pe_quantity" id="e_stock_pe_quantity" class="form-control" value="0" min="0" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="e_investment_section" style="display:none;">
                        <div class="form-section">
                            <div class="per-symbol-note">
                                <strong>📌 Investment is per symbol, per order — not shared.</strong><br>
                                If Index CE = ₹1,00,000 and 10 index symbols match, <strong>each symbol independently gets ₹1,00,000.</strong><br>
                                Total deployed = ₹1L × number of matching symbols.
                            </div>
                            <div class="inv-info">💡 Lots = <code>floor(investment ÷ (LTP × lot_size))</code>&nbsp;&nbsp;<span style="color:rgba(255,255,255,.5);">e.g. ₹1,00,000 ÷ (₹250 × 25) = <strong style="color:#c084fc;">16 lots</strong></span></div>
                            <div class="row g-3">
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#22c55e;">Index CE</span> (₹/symbol)</label>
                                    <div class="input-group"><span class="input-group-text ig-dark">₹</span><input type="number" name="index_ce_investment" id="e_index_ce_investment" class="form-control" value="0" min="0" step="1000" /></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#ef4444;">Index PE</span> (₹/symbol)</label>
                                    <div class="input-group"><span class="input-group-text ig-dark">₹</span><input type="number" name="index_pe_investment" id="e_index_pe_investment" class="form-control" value="0" min="0" step="1000" /></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#22c55e;">Stock CE</span> (₹/symbol)</label>
                                    <div class="input-group"><span class="input-group-text ig-dark">₹</span><input type="number" name="stock_ce_investment" id="e_stock_ce_investment" class="form-control" value="0" min="0" step="1000" /></div>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label"><span style="color:#ef4444;">Stock PE</span> (₹/symbol)</label>
                                    <div class="input-group"><span class="input-group-text ig-dark">₹</span><input type="number" name="stock_pe_investment" id="e_stock_pe_investment" class="form-control" value="0" min="0" step="1000" /></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label"><i class="fas fa-shield-alt me-1"></i> Stop-Loss <small style="font-size:10px;opacity:.55;font-weight:400;">(protects against downside loss)</small></p>
                    <div class="sl-section">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="e_enable_stoploss" name="enable_stoploss" value="1" onchange="toggleBody('e_sl_body',this.checked)" />
                                <label class="form-check-label" for="e_enable_stoploss">Enable Stop-Loss Order</label>
                            </div>
                            <small style="color:rgba(255,255,255,.4);font-size:10px;">SELL placed immediately after buy succeeds</small>
                        </div>
                        <div id="e_sl_body" style="display:none;">
                            <div class="sl-info">
                                <strong style="color:#f87171;">Trigger price (below entry):</strong><br>
                                % → entry × (1 − value/100) &nbsp;<span style="opacity:.5;">e.g. ₹200, SL 30% → ₹140.00</span><br>
                                pts → entry − value &nbsp;<span style="opacity:.5;">e.g. ₹200, SL 50 pts → ₹150.00</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Stop-Loss Type</label>
                                    <select name="stoploss_type" id="e_stoploss_type" class="form-select" onchange="updatePreview('e','sl')">
                                        <option value="pct">Percentage (% drop)</option>
                                        <option value="points">Points (absolute drop)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Stop-Loss Value <span class="badge bg-danger ms-1" id="e_sl_unit">%</span></label>
                                    <div class="sl-stepper-wrap">
                                        <button type="button" class="sl-step-btn" onclick="stepVal('e_stoploss_value',-0.5,'e','sl')">−</button>
                                        <input type="number" name="stoploss_value" id="e_stoploss_value" class="form-control sl-step-input" value="30" min="0.1" max="10000" step="0.5" oninput="updatePreview('e','sl');syncChips('e','sl')" />
                                        <button type="button" class="sl-step-btn" onclick="stepVal('e_stoploss_value',0.5,'e','sl')">+</button>
                                    </div>
                                    <div class="sl-presets">
                                        <span class="sl-preset e-sl-chip" onclick="pickChip(this,'e_stoploss_value','e','sl')">10</span>
                                        <span class="sl-preset e-sl-chip" onclick="pickChip(this,'e_stoploss_value','e','sl')">20</span>
                                        <span class="sl-preset e-sl-chip" onclick="pickChip(this,'e_stoploss_value','e','sl')">30</span>
                                        <span class="sl-preset e-sl-chip" onclick="pickChip(this,'e_stoploss_value','e','sl')">50</span>
                                        <span class="sl-preset e-sl-chip" onclick="pickChip(this,'e_stoploss_value','e','sl')">100</span>
                                    </div>
                                    <div class="form-text" id="e_sl_preview" style="color:#f87171;font-size:10px;margin-top:5px;">📌 Entry ₹200 × (1 − 30/100) = ₹140.00</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">SL Order Type</label>
                                    <select name="stoploss_order_type" id="e_stoploss_order_type" class="form-select">
                                        <option value="SL-M">SL-M (market on trigger)</option>
                                        <option value="SL">SL (limit on trigger)</option>
                                    </select>
                                    <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">SL-M recommended — guaranteed exit</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="section-label" style="color:#22c55e;border-bottom-color:rgba(34,197,94,.25);"><i class="fas fa-bullseye me-1"></i> Profit Target <small style="font-size:10px;opacity:.55;font-weight:400;">(locks in upside gain)</small></p>
                    <div class="target-section">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="e_enable_target" name="enable_target" value="1" onchange="toggleBody('e_target_body',this.checked)" />
                                <label class="form-check-label" for="e_enable_target">Enable Profit Target Order</label>
                            </div>
                            <small style="color:rgba(255,255,255,.4);font-size:10px;">SELL placed immediately after buy succeeds</small>
                        </div>
                        <div id="e_target_body" style="display:none;">
                            <div class="target-info">
                                <strong style="color:#4ade80;">Target price (above entry):</strong><br>
                                % → entry × (1 + value/100) &nbsp;<span style="opacity:.5;">e.g. ₹200, Target 50% → ₹300.00</span><br>
                                pts → entry + value &nbsp;<span style="opacity:.5;">e.g. ₹200, Target 80 pts → ₹280.00</span><br>
                                <span style="color:rgba(255,255,255,.4);font-size:10px;">⚠ If both SL + Target placed, only ONE executes. The other stays open till EOD.</span>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Target Type</label>
                                    <select name="target_type" id="e_target_type" class="form-select" onchange="updatePreview('e','tgt')">
                                        <option value="pct">Percentage (% gain)</option>
                                        <option value="points">Points (absolute gain)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Target Value <span class="badge bg-success ms-1" id="e_tgt_unit">%</span></label>
                                    <div class="tgt-stepper-wrap">
                                        <button type="button" class="tgt-step-btn" onclick="stepVal('e_target_value',-0.5,'e','tgt')">−</button>
                                        <input type="number" name="target_value" id="e_target_value" class="form-control tgt-step-input" value="50" min="0.1" max="10000" step="0.5" oninput="updatePreview('e','tgt');syncChips('e','tgt')" />
                                        <button type="button" class="tgt-step-btn" onclick="stepVal('e_target_value',0.5,'e','tgt')">+</button>
                                    </div>
                                    <div class="tgt-presets">
                                        <span class="tgt-preset e-tgt-chip" onclick="pickChip(this,'e_target_value','e','tgt')">20</span>
                                        <span class="tgt-preset e-tgt-chip" onclick="pickChip(this,'e_target_value','e','tgt')">30</span>
                                        <span class="tgt-preset e-tgt-chip active" onclick="pickChip(this,'e_target_value','e','tgt')">50</span>
                                        <span class="tgt-preset e-tgt-chip" onclick="pickChip(this,'e_target_value','e','tgt')">100</span>
                                        <span class="tgt-preset e-tgt-chip" onclick="pickChip(this,'e_target_value','e','tgt')">200</span>
                                    </div>
                                    <div class="form-text" id="e_tgt_preview" style="color:#4ade80;font-size:10px;margin-top:5px;">📌 Entry ₹200 × (1 + 50/100) = ₹300.00</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Target Order Type</label>
                                    <select name="target_order_type" id="e_target_order_type" class="form-select">
                                        <option value="LIMIT">LIMIT (resting sell at target)</option>
                                        <option value="SL-M">SL-M (market sell on trigger)</option>
                                        <option value="SL">SL (limit sell on trigger)</option>
                                    </select>
                                    <div class="form-text" style="color:rgba(255,255,255,.4);font-size:10px;">LIMIT recommended — exact target price</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-amber px-4"><i class="fas fa-save me-1"></i> Update Config</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
@push('script')
<script>
/* ═══════════════════════════════════════════════════════
   BUG FIXES:
   1. Removed duplicate .qty-pill listener that was
      nested inside the edit-button handler (caused
      "Cannot set properties of null" on quantity_mode).
   2. Both forms have novalidate so the browser never
      tries to focus hidden inputs (stoploss_value /
      target_value inside display:none wrappers).
   ═══════════════════════════════════════════════════════ */

/* ── Qty mode pills ─── */
document.querySelectorAll('.qty-pill').forEach(function(pill) {
    pill.addEventListener('click', function() {
        var m   = this.dataset.modal;
        var val = this.dataset.val;
        var qmEl = document.getElementById(m + '_quantity_mode');
        if (!qmEl) return;
        qmEl.value = val;
        document.getElementById(m + '_lots_section').style.display       = val === 'investment' ? 'none'  : 'block';
        document.getElementById(m + '_investment_section').style.display = val === 'investment' ? 'block' : 'none';
        document.querySelectorAll('.qty-pill[data-modal="' + m + '"]').forEach(function(p) {
            p.classList.toggle('active', p.dataset.val === val);
        });
    });
});

/* ── Show / hide collapsible bodies ─── */
function toggleBody(id, show) {
    var el = document.getElementById(id);
    if (el) el.style.display = show ? 'block' : 'none';
}

/* ── Live preview + unit badge ─── */
function updatePreview(m, kind) {
    var isSl   = kind === 'sl';
    var field  = isSl ? 'stoploss' : 'target';
    var typeEl = document.getElementById(m + '_' + field + '_type');
    var valEl  = document.getElementById(m + '_' + field + '_value');
    var unitEl = document.getElementById(m + '_' + (isSl ? 'sl' : 'tgt') + '_unit');
    var prevEl = document.getElementById(m + '_' + (isSl ? 'sl' : 'tgt') + '_preview');
    if (!typeEl || !valEl) return;
    var isPct = typeEl.value === 'pct';
    var v     = parseFloat(valEl.value) || (isSl ? 30 : 50);
    if (unitEl) unitEl.textContent = isPct ? '%' : 'pts';
    if (prevEl) {
        if (isSl) {
            prevEl.textContent = isPct
                ? '📌 Entry ₹200 × (1 − ' + v + '/100) = ₹' + (200*(1-v/100)).toFixed(2)
                : '📌 Entry ₹200 − ' + v + ' pts = ₹' + (200-v).toFixed(2);
        } else {
            prevEl.textContent = isPct
                ? '📌 Entry ₹200 × (1 + ' + v + '/100) = ₹' + (200*(1+v/100)).toFixed(2)
                : '📌 Entry ₹200 + ' + v + ' pts = ₹' + (200+v).toFixed(2);
        }
    }
}

/* ── Stepper ─── */
function stepVal(inputId, delta, m, kind) {
    var inp = document.getElementById(inputId);
    if (!inp) return;
    var v = parseFloat((parseFloat(inp.value || 0) + delta).toFixed(2));
    inp.value = Math.min(parseFloat(inp.max) || 10000, Math.max(parseFloat(inp.min) || 0.1, v));
    updatePreview(m, kind);
    syncChips(m, kind);
}

/* ── Preset chip click ─── */
function pickChip(el, inputId, m, kind) {
    var inp = document.getElementById(inputId);
    if (!inp) return;
    inp.value = parseFloat(el.textContent);
    updatePreview(m, kind);
    syncChips(m, kind);
}

/* ── Sync active chip highlight ─── */
function syncChips(m, kind) {
    var isSl  = kind === 'sl';
    var field = isSl ? 'stoploss' : 'target';
    var inp   = document.getElementById(m + '_' + field + '_value');
    if (!inp) return;
    var v    = parseFloat(inp.value);
    var cls  = isSl ? '.sl-preset' : '.tgt-preset';
    var body = document.getElementById(m + '_' + (isSl ? 'sl' : 'target') + '_body');
    if (!body) return;
    body.querySelectorAll(cls).forEach(function(chip) {
        chip.classList.toggle('active', parseFloat(chip.textContent) === v);
    });
}

/* ── Edit button: populate all fields then open modal ─── */
document.querySelectorAll('.btn-edit').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var d = btn.dataset;

        document.getElementById('editForm').action =
            '{{ route("nifty-driven-breakout.config-update", ":id") }}'.replace(':id', d.id);

        /* Simple value fields — guard with if(el) to prevent null errors */
        ['broker_api_id','threshold','filter','signal_mode','order_type','product','disc_ltp','status',
         'index_ce_quantity','index_pe_quantity','stock_ce_quantity','stock_pe_quantity',
         'index_ce_investment','index_pe_investment','stock_ce_investment','stock_pe_investment',
         'stoploss_type','stoploss_value','stoploss_order_type',
         'target_type','target_value','target_order_type'
        ].forEach(function(f) {
            var el = document.getElementById('e_' + f);
            if (el) el.value = (d[f] !== undefined && d[f] !== null) ? d[f] : '';
        });

        /* Qty mode */
        var qm = d.quantity_mode || 'lots';
        var qmEl = document.getElementById('e_quantity_mode');
        if (qmEl) qmEl.value = qm;
        var lotsEl = document.getElementById('e_lots_section');
        var invEl  = document.getElementById('e_investment_section');
        if (lotsEl) lotsEl.style.display = qm === 'investment' ? 'none'  : 'block';
        if (invEl)  invEl.style.display  = qm === 'investment' ? 'block' : 'none';
        document.querySelectorAll('.qty-pill[data-modal="e"]').forEach(function(p) {
            p.classList.toggle('active', p.dataset.val === qm);
        });

        /* SL toggle */
        var slChk = document.getElementById('e_enable_stoploss');
        var slOn  = d.enable_stoploss === '1';
        if (slChk) slChk.checked = slOn;
        toggleBody('e_sl_body', slOn);

        /* Target toggle */
        var tgtChk = document.getElementById('e_enable_target');
        var tgtOn  = d.enable_target === '1';
        if (tgtChk) tgtChk.checked = tgtOn;
        toggleBody('e_target_body', tgtOn);

        /* Refresh previews + chips */
        updatePreview('e', 'sl');
        updatePreview('e', 'tgt');
        syncChips('e', 'sl');
        syncChips('e', 'tgt');

        /* Allowed symbols multi-select */
        var syms = (d.allowed_symbols || '').split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        var sel  = document.getElementById('e_allowed_symbols');
        if (sel) Array.from(sel.options).forEach(function(o) { o.selected = syms.includes(o.value); });

        bootstrap.Modal.getOrCreateInstance(document.getElementById('editModal')).show();
    });
});
</script>
@endpush