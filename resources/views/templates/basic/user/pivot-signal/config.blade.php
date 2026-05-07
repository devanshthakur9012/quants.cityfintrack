@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
/* ══════════════════════════════════════════════════
   UNIFIED PIVOT CONFIG — BOTH 1HR + 15MIN
   ══════════════════════════════════════════════════ */

.page-header {
    background: linear-gradient(135deg, #0f0c29, #302b63, #24243e);
    color: white; padding: 20px 28px; border-radius: 14px;
    margin-bottom: 22px;
    box-shadow: 0 4px 30px rgba(0,0,0,0.5);
    border: 1px solid rgba(255,255,255,0.07);
    position: relative; overflow: hidden;
}
.page-header::before {
    content: ''; position: absolute; top: -40px; right: -40px;
    width: 200px; height: 200px; border-radius: 50%;
    background: radial-gradient(circle, rgba(102,126,234,0.25) 0%, transparent 70%);
    pointer-events: none;
}
.page-header h4 { color: white; margin: 0; font-size: 18px; font-weight: 800; letter-spacing: .3px; }
.page-header p  { color: rgba(255,255,255,0.55); margin: 5px 0 0; font-size: 11px; }

/* ── Interval filter tabs ─────────────────────── */
.interval-tabs {
    display: flex; gap: 8px; margin-bottom: 20px;
}
.itab {
    padding: 7px 20px; border-radius: 30px; font-size: 11px; font-weight: 800;
    cursor: pointer; border: 1px solid rgba(255,255,255,0.12);
    background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.45);
    transition: all .2s; user-select: none;
}
.itab:hover { border-color: rgba(255,255,255,0.25); color: rgba(255,255,255,0.7); }
.itab.active-all  { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.3); color: white; }
.itab.active-1hr  { background: rgba(81,207,102,0.15); border-color: rgba(81,207,102,0.5); color: #51cf66; }
.itab.active-15min{ background: rgba(247,183,51,0.15); border-color: rgba(247,183,51,0.5); color: #f7b733; }

/* ── Config card ─────────────────────────────── */
.config-card {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08);
    border-radius: 13px; margin-bottom: 14px; overflow: hidden;
    transition: border-color .2s;
}
.config-card:hover { border-color: rgba(255,255,255,0.14); }
.config-card.is-1hr  { border-left: 3px solid rgba(81,207,102,0.5); }
.config-card.is-15min{ border-left: 3px solid rgba(247,183,51,0.5); }

.config-card-header {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 11px 18px; background: rgba(0,0,0,0.25);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.config-card-body { padding: 14px 18px; }

/* ── Interval badge ──────────────────────────── */
.ibadge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 10px; border-radius: 20px; font-size: 10px; font-weight: 800;
}
.ibadge-1hr  { background: rgba(81,207,102,0.15); color: #51cf66; border: 1px solid rgba(81,207,102,0.4); }
.ibadge-15min{ background: rgba(247,183,51,0.15);  color: #f7b733; border: 1px solid rgba(247,183,51,0.4); }

/* ── Section labels ──────────────────────────── */
.section-lbl {
    font-size: 10px; font-weight: 800; text-transform: uppercase;
    letter-spacing: .6px; margin-bottom: 8px; padding: 3px 9px;
    border-radius: 6px; display: inline-block;
}
.lbl-s1-ce { background: rgba(81,207,102,0.15); color: #51cf66; border: 1px solid rgba(81,207,102,0.3); }
.lbl-s1-pe { background: rgba(81,207,102,0.10); color: #74d68b; border: 1px solid rgba(81,207,102,0.2); }
.lbl-r1-ce { background: rgba(255,107,107,0.15); color: #ff6b6b; border: 1px solid rgba(255,107,107,0.3); }
.lbl-r1-pe { background: rgba(255,107,107,0.10); color: #ff9f9f; border: 1px solid rgba(255,107,107,0.2); }

/* ── Layer grid ──────────────────────────────── */
.layer-grid {
    display: grid; grid-template-columns: 110px 80px 80px 32px;
    gap: 6px; align-items: center; margin-bottom: 5px;
}
.layer-grid-header { font-size: 9px; color: rgba(255,255,255,0.3); font-weight: 700; text-transform: uppercase; }
.layer-grid input, .layer-grid select {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.11);
    border-radius: 6px; color: white; padding: 5px 8px; font-size: 11px;
    outline: none; width: 100%;
}
.layer-grid input:focus, .layer-grid select:focus { border-color: rgba(147,112,219,0.6); }
.layer-grid select option { background: #1e1e3a; }
.btn-rm-layer {
    background: rgba(220,53,69,0.2); border: 1px solid rgba(220,53,69,0.3);
    color: #ff6b6b; border-radius: 6px; width: 28px; height: 28px;
    cursor: pointer; font-size: 14px; display: flex; align-items: center; justify-content: center;
}
.btn-rm-layer:hover { background: rgba(220,53,69,0.4); }
.btn-add-layer {
    background: rgba(147,112,219,0.1); border: 1px solid rgba(147,112,219,0.3);
    color: #9370db; border-radius: 6px; padding: 4px 12px;
    font-size: 10px; font-weight: 700; cursor: pointer;
}
.btn-add-layer:hover { background: rgba(147,112,219,0.2); }

/* ── Form controls ───────────────────────────── */
.form-lbl { font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.45); margin-bottom: 4px; }
.form-ctrl {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.11);
    border-radius: 8px; color: white; padding: 7px 12px; font-size: 12px;
    outline: none; width: 100%;
}
.form-ctrl:focus { border-color: rgba(147,112,219,0.6); }
.form-ctrl option { background: #1e1e3a; }

/* ── Interval toggle (form) ──────────────────── */
.interval-toggle-wrap {
    display: flex; gap: 0; border-radius: 10px; overflow: hidden;
    border: 1px solid rgba(255,255,255,0.12); width: fit-content;
}
.itoggle-btn {
    padding: 7px 18px; font-size: 11px; font-weight: 800; cursor: pointer;
    border: none; outline: none; background: rgba(255,255,255,0.04);
    color: rgba(255,255,255,0.4); transition: all .2s;
}
.itoggle-btn:first-child { border-right: 1px solid rgba(255,255,255,0.12); }
.itoggle-btn.sel-1hr  { background: rgba(81,207,102,0.2); color: #51cf66; }
.itoggle-btn.sel-15min{ background: rgba(247,183,51,0.2); color: #f7b733; }
.itoggle-btn:hover:not(.sel-1hr):not(.sel-15min) {
    background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.7);
}

/* ── Symbol picker ───────────────────────────── */
.symbol-picker-wrap {
    background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px; padding: 12px 14px;
}
.symbol-search-box {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.11);
    border-radius: 7px; color: white; padding: 6px 10px; font-size: 11px;
    outline: none; width: 100%; margin-bottom: 10px;
}
.symbol-search-box::placeholder { color: rgba(255,255,255,.25); }
.symbol-search-box:focus { border-color: rgba(147,112,219,0.5); }
.symbol-grid {
    display: flex; flex-wrap: wrap; gap: 5px;
    max-height: 175px; overflow-y: auto; padding-right: 4px;
}
.symbol-grid::-webkit-scrollbar { width: 3px; }
.symbol-grid::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 4px; }
.sym-chip {
    display: inline-flex; align-items: center; padding: 3px 10px;
    border-radius: 20px; font-size: 10px; font-weight: 700; cursor: pointer;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.4); transition: .15s;
}
.sym-chip:hover { border-color: rgba(147,112,219,0.4); color: rgba(147,112,219,0.9); }
.sym-chip.selected { background: rgba(147,112,219,0.15); border-color: rgba(147,112,219,0.5); color: #9370db; }
.sym-chip.hidden { display: none; }
.symbol-footer {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 10px; font-size: 10px; color: rgba(255,255,255,0.3);
}
.symbol-footer strong { color: #9370db; }
.btn-sym-ctrl {
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.45); border-radius: 5px; padding: 2px 8px;
    font-size: 9px; cursor: pointer; font-weight: 700;
}
.btn-sym-ctrl:hover { background: rgba(255,255,255,0.1); color: white; }

/* ── Status + misc pills ─────────────────────── */
.status-pill { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 10px; font-weight: 800; }
.status-on   { background: rgba(40,167,69,0.2); color: #51cf66; border: 1px solid rgba(40,167,69,0.4); }
.status-off  { background: rgba(220,53,69,0.15); color: #ff6b6b; border: 1px solid rgba(220,53,69,0.3); }
.sym-tag {
    display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 9px; font-weight: 800;
    background: rgba(147,112,219,0.1); color: #9370db;
    border: 1px solid rgba(147,112,219,0.25); margin: 1px;
}
.sym-tag-more { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.35); border-color: rgba(255,255,255,0.1); }

/* ── Buttons ─────────────────────────────────── */
.btn-save {
    background: linear-gradient(135deg, #667eea, #764ba2);
    border: none; color: white; border-radius: 8px;
    padding: 8px 24px; font-weight: 800; font-size: 12px; cursor: pointer;
}
.btn-save:hover { opacity: .85; }
.btn-cancel {
    background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.13);
    color: rgba(255,255,255,0.55); border-radius: 8px; padding: 8px 16px; font-size: 12px; cursor: pointer;
}

/* ── Note box ────────────────────────────────── */
.note-box {
    background: rgba(147,112,219,0.07); border: 1px solid rgba(147,112,219,0.2);
    border-radius: 9px; padding: 10px 14px; font-size: 11px;
    color: rgba(255,255,255,0.5); margin-bottom: 18px; line-height: 1.65;
}
.note-box strong { color: #9370db; }
.divider { border: none; border-top: 1px solid rgba(255,255,255,0.06); margin: 16px 0; }

/* ── Form card ───────────────────────────────── */
.form-card {
    background: rgba(255,255,255,0.025); border: 1px solid rgba(147,112,219,0.25);
    border-radius: 13px; margin-bottom: 24px; overflow: hidden;
    box-shadow: 0 0 30px rgba(147,112,219,0.08);
}
.form-card-header {
    padding: 13px 20px; background: rgba(147,112,219,0.08);
    border-bottom: 1px solid rgba(147,112,219,0.15);
    display: flex; align-items: center; gap: 10px;
}
.form-card-body { padding: 20px; }

/* ── Empty state ─────────────────────────────── */
.empty-state {
    text-align: center; padding: 70px 20px; color: rgba(255,255,255,0.25);
}
.empty-state .empty-icon { font-size: 48px; margin-bottom: 12px; opacity: .4; }
.empty-state p { font-size: 13px; }
.empty-state strong { color: #9370db; }
</style>
@endpush

<section class="pt-40 pb-50">
<div class="container-fluid content-container">

    {{-- ── Page Header ── --}}
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4>&#9881; Pivot Order Config
                    <span style="background:rgba(255,255,255,0.1);padding:2px 9px;border-radius:5px;
                                 font-size:10px;font-weight:700;margin-left:8px;letter-spacing:.5px;">
                        BOTH 1HR &amp; 15MIN
                    </span>
                </h4>
                <p>One config table for all intervals &nbsp;·&nbsp; S1 / R1 order layers for CE and PE &nbsp;·&nbsp; Symbols per config</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <a href="{{ route('pivot-signal.index') }}"
                   class="btn btn-sm"
                   style="background:rgba(81,207,102,0.15);border:1px solid rgba(81,207,102,0.4);color:#51cf66;font-size:11px;">
                    &#9889; 1Hr Signals
                </a>
                <a href="{{ route('pivot-signal-15.index') }}"
                   class="btn btn-sm"
                   style="background:rgba(247,183,51,0.15);border:1px solid rgba(247,183,51,0.4);color:#f7b733;font-size:11px;">
                    &#9889; 15Min Signals
                </a>
                <button class="btn btn-sm"
                        style="background:linear-gradient(135deg,#667eea,#764ba2);border:none;color:white;font-weight:800;"
                        onclick="showCreateForm()">
                    + New Config
                </button>
            </div>
        </div>
    </div>

    {{-- ── How it works note ── --}}
    <div class="note-box">
        <strong>How it works:</strong>
        Each config targets <strong>specific symbols</strong> and runs on either <strong style="color:#51cf66;">1 Hour</strong> or <strong style="color:#f7b733;">15 Min</strong> candle data.
        &nbsp;·&nbsp; <strong>1Hr</strong> cron fires ~2 min after each hourly bar close &nbsp;·&nbsp;
        <strong>15Min</strong> cron fires every 15 min during market hours.
        &nbsp;·&nbsp; S1 = BUY level &nbsp;·&nbsp; R1 = SELL level &nbsp;·&nbsp;
        Run Now triggers the appropriate command instantly for that config.
        &nbsp;·&nbsp; <strong>No symbols = no orders placed.</strong>
    </div>

    {{-- ── Interval Filter Tabs ── --}}
    <div class="interval-tabs">
        <div class="itab active-all" data-filter="all" onclick="filterByInterval(this, 'all')">
            &#9776; All Configs <span id="count-all" style="opacity:.6;font-size:9px;margin-left:3px;"></span>
        </div>
        <div class="itab" data-filter="1hr" onclick="filterByInterval(this, '1hr')">
            &#9200; 1 Hour <span id="count-1hr" style="opacity:.6;font-size:9px;margin-left:3px;"></span>
        </div>
        <div class="itab" data-filter="15min" onclick="filterByInterval(this, '15min')">
            &#9203; 15 Min <span id="count-15min" style="opacity:.6;font-size:9px;margin-left:3px;"></span>
        </div>
    </div>

    {{-- ── Create / Edit Form ── --}}
    <div id="config-form-wrap" style="display:none; margin-bottom:24px;">
        <div class="form-card">
            <div class="form-card-header">
                <span style="font-weight:800;color:white;font-size:13px;" id="form-title">New Config</span>
                <span id="form-interval-badge" class="ibadge ibadge-1hr" style="margin-left:4px;">⏰ 1 Hour</span>
            </div>
            <div class="form-card-body">
                <form id="config-form" onsubmit="submitConfig(event)">
                    <input type="hidden" id="edit-id" value="">

                    {{-- Row 1: Broker / OrderType / Product / Status / Interval ── --}}
                    <div class="row g-3 mb-3">
                        <div class="col-md-4 col-sm-6">
                            <div class="form-lbl">Broker Account <span style="color:#ff6b6b;">*</span></div>
                            <select id="f-broker" class="form-ctrl" required>
                                <option value="">-- Select Broker --</option>
                                @foreach($brokers as $b)
                                    <option value="{{ $b->id }}">{{ $b->client_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-3">
                            <div class="form-lbl">Order Type</div>
                            <select id="f-order-type" class="form-ctrl">
                                <option value="LIMIT">LIMIT</option>
                                <option value="MARKET">MARKET</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-3">
                            <div class="form-lbl">Product</div>
                            <select id="f-product" class="form-ctrl">
                                <option value="MIS">MIS</option>
                                <option value="NRML">NRML</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-3">
                            <div class="form-lbl">Status</div>
                            <select id="f-status" class="form-ctrl">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-3">
                            <div class="form-lbl">Interval Type <span style="color:#ff6b6b;">*</span></div>
                            <div class="interval-toggle-wrap mt-1" id="interval-toggle">
                                <button type="button" class="itoggle-btn sel-1hr"
                                        id="btn-1hr" onclick="setIntervalType('1hr')">
                                    ⏰ 1 Hour
                                </button>
                                <button type="button" class="itoggle-btn"
                                        id="btn-15min" onclick="setIntervalType('15min')">
                                    ⏱ 15 Min
                                </button>
                            </div>
                            <input type="hidden" id="f-interval-type" value="1hr">
                        </div>
                    </div>

                    {{-- Symbol Picker ── --}}
                    <div class="mb-3">
                        <div class="form-lbl mb-2">
                            &#127919; Select Symbols
                            <span style="color:rgba(255,100,100,.7);font-size:9px;margin-left:4px;">
                                * Required &mdash; orders only placed for selected symbols
                            </span>
                        </div>
                        <div class="symbol-picker-wrap">
                            <input type="text" class="symbol-search-box" id="symbol-search"
                                   placeholder="&#128269; Search symbols (e.g. NIFTY, BANK, SENSEX...)"
                                   oninput="filterSymbols(this.value)">
                            <div class="symbol-grid" id="symbol-grid">
                                @foreach($allSymbols as $sym)
                                <div class="sym-chip" data-symbol="{{ $sym }}" onclick="toggleSymbol(this)">
                                    {{ $sym }}
                                </div>
                                @endforeach
                            </div>
                            <div class="symbol-footer">
                                <span><strong id="selected-count">0</strong> symbol(s) selected</span>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn-sym-ctrl" onclick="selectQuickSet('index')">Indices</button>
                                    <button type="button" class="btn-sym-ctrl" onclick="selectQuickSet('all')">All</button>
                                    <button type="button" class="btn-sym-ctrl" onclick="selectQuickSet('none')">Clear</button>
                                </div>
                            </div>
                        </div>
                        <div id="symbol-error" style="color:#ff6b6b;font-size:10px;margin-top:4px;display:none;">
                            &#9888; Please select at least one symbol.
                        </div>
                    </div>

                    <hr class="divider">

                    {{-- 4 Layer Sections ── --}}
                    <div class="row g-4">
                        <div class="col-md-6">
                            <span class="section-lbl lbl-s1-ce">&#128200; S1 — CE Layers (BUY CE at S1)</span>
                            <div class="layer-grid layer-grid-header">
                                <div>Direction</div><div>Discount %</div><div>Quantity</div><div></div>
                            </div>
                            <div id="s1-ce-layers"></div>
                            <button type="button" class="btn-add-layer mt-1" onclick="addLayer('s1-ce')">+ Add Layer</button>
                        </div>
                        <div class="col-md-6">
                            <span class="section-lbl lbl-s1-pe">&#128201; S1 — PE Layers (BUY PE at S1)</span>
                            <div class="layer-grid layer-grid-header">
                                <div>Direction</div><div>Discount %</div><div>Quantity</div><div></div>
                            </div>
                            <div id="s1-pe-layers"></div>
                            <button type="button" class="btn-add-layer mt-1" onclick="addLayer('s1-pe')">+ Add Layer</button>
                        </div>
                        <div class="col-md-6">
                            <span class="section-lbl lbl-r1-ce">📈 R1 — CE Layers (SELL CE at R1)</span>
                            <div class="layer-grid layer-grid-header">
                                <div>Direction</div><div>Discount %</div><div>Quantity</div><div></div>
                            </div>
                            <div id="r1-ce-layers"></div>
                            <button type="button" class="btn-add-layer mt-1" onclick="addLayer('r1-ce')">+ Add Layer</button>
                        </div>
                        <div class="col-md-6">
                            <span class="section-lbl lbl-r1-pe">📉 R1 — PE Layers (SELL PE at R1)</span>
                            <div class="layer-grid layer-grid-header">
                                <div>Direction</div><div>Discount %</div><div>Quantity</div><div></div>
                            </div>
                            <div id="r1-pe-layers"></div>
                            <button type="button" class="btn-add-layer mt-1" onclick="addLayer('r1-pe')">+ Add Layer</button>
                        </div>
                    </div>

                    <hr class="divider">
                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn-save">&#10003; Save Config</button>
                        <button type="button" class="btn-cancel" onclick="hideForm()">Cancel</button>
                        <span id="form-save-note" style="font-size:10px;color:rgba(255,255,255,.3);margin-left:4px;"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ── Config List ── --}}
    <div id="config-list">
        @forelse($configs as $cfg)
        <div class="config-card {{ $cfg->interval_type === '15min' ? 'is-15min' : 'is-1hr' }}"
             data-interval="{{ $cfg->interval_type ?? '1hr' }}">
            <div class="config-card-header">

                {{-- ID --}}
                <span style="font-size:13px;font-weight:800;color:white;min-width:28px;">#{{ $cfg->id }}</span>

                {{-- Interval badge --}}
                @if(($cfg->interval_type ?? '1hr') === '15min')
                    <span class="ibadge ibadge-15min">⏱ 15 Min</span>
                @else
                    <span class="ibadge ibadge-1hr">⏰ 1 Hour</span>
                @endif

                {{-- Broker --}}
                <span style="font-size:12px;color:rgba(255,255,255,.55);">{{ $cfg->broker->client_name ?? '—' }}</span>

                {{-- Order/Product --}}
                <span style="font-size:10px;color:rgba(255,255,255,.35);background:rgba(255,255,255,.05);
                             padding:2px 8px;border-radius:6px;">
                    {{ $cfg->order_type }} / {{ $cfg->product }}
                </span>

                {{-- Symbol tags --}}
                <span style="display:inline-flex;align-items:center;flex-wrap:wrap;gap:3px;">
                    @if(!empty($cfg->symbols))
                        @foreach(array_slice($cfg->symbols, 0, 5) as $sym)
                            <span class="sym-tag">{{ $sym }}</span>
                        @endforeach
                        @if(count($cfg->symbols) > 5)
                            <span class="sym-tag sym-tag-more">+{{ count($cfg->symbols) - 5 }}</span>
                        @endif
                    @else
                        <span style="font-size:10px;color:rgba(255,100,100,.65);">⚠ No symbols</span>
                    @endif
                </span>

                {{-- Orders count --}}
                <span style="font-size:10px;color:rgba(147,112,219,.7);">{{ $cfg->orders_count }} orders</span>

                {{-- Status --}}
                <span class="status-pill {{ $cfg->status ? 'status-on' : 'status-off' }}">
                    {{ $cfg->status ? 'Active' : 'Inactive' }}
                </span>

                {{-- Actions --}}
                <div class="ms-auto d-flex gap-2 align-items-center flex-wrap">
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick='editConfig(@json($cfg))'>Edit</button>

                    <form method="POST" action="{{ route('pivot-signal.config.toggle', $cfg->id) }}" style="display:inline;">
                        @csrf
                        <button class="btn btn-sm {{ $cfg->status ? 'btn-outline-warning' : 'btn-outline-success' }}">
                            {{ $cfg->status ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>

                    <button class="btn btn-sm {{ ($cfg->interval_type ?? '1hr') === '15min' ? 'btn-outline-warning' : 'btn-outline-success' }} run-now-btn"
                            data-id="{{ $cfg->id }}"
                            data-active="{{ $cfg->status ? '1' : '0' }}"
                            data-has-symbols="{{ !empty($cfg->symbols) ? '1' : '0' }}"
                            data-interval="{{ $cfg->interval_type ?? '1hr' }}"
                            title="Run {{ ($cfg->interval_type ?? '1hr') === '15min' ? 'pivot15:place-orders' : 'pivot:place-orders' }} for config #{{ $cfg->id }}">
                        <span class="btn-label">&#9654; Run Now</span>
                        <span class="btn-spinner d-none">&#9203;</span>
                    </button>

                    <a href="{{ route('pivot-signal.config.orders', $cfg->id) }}"
                       class="btn btn-sm btn-outline-secondary">Orders</a>

                    <form method="POST" action="{{ route('pivot-signal.config.destroy', $cfg->id) }}" style="display:inline;"
                          onsubmit="return confirm('Delete config #{{ $cfg->id }}?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            </div>

            {{-- Layers summary ── --}}
            <div class="config-card-body">
                <div class="row g-3" style="font-size:11px;">
                    @foreach([
                        ['s1_ce_layers', 'S1 CE', 'lbl-s1-ce', '#51cf66'],
                        ['s1_pe_layers', 'S1 PE', 'lbl-s1-pe', '#51cf66'],
                        ['r1_ce_layers', 'R1 CE', 'lbl-r1-ce', '#ff6b6b'],
                        ['r1_pe_layers', 'R1 PE', 'lbl-r1-pe', '#ff6b6b'],
                    ] as [$field, $label, $cls, $color])
                    <div class="col-md-3 col-sm-6">
                        <span class="section-lbl {{ $cls }}" style="font-size:9px;">{{ $label }}</span>
                        @if(!empty($cfg->$field))
                            @foreach($cfg->$field as $i => $layer)
                            <div style="color:rgba(255,255,255,.5);font-size:10px;margin-bottom:2px;">
                                L{{ $i+1 }}:
                                <strong style="color:white;">{{ $layer['quantity'] }}</strong> qty
                                &nbsp;@&nbsp;
                                <span style="color:{{ $color }};">
                                    {{ $layer['discount_direction'] === 'positive' ? '+' : '-' }}{{ $layer['discount_pct'] }}%
                                </span>
                            </div>
                            @endforeach
                        @else
                            <span style="color:rgba(255,255,255,.2);font-size:10px;">— none —</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @empty
        <div class="empty-state">
            <div class="empty-icon">&#9881;</div>
            <p>No configs yet. Click <strong>+ New Config</strong> to create one.</p>
        </div>
        @endforelse
    </div>

    {{ $configs->links() }}

</div>
</section>
@endsection

@push('script')
<script>
// ── Constants ─────────────────────────────────────────────────────────────────
const ALL_SYMBOLS    = @json($allSymbols);
const INDEX_SYMBOLS  = ['NIFTY', 'BANKNIFTY', 'FINNIFTY', 'MIDCPNIFTY', 'SENSEX', 'BANKEX'];
let selectedSymbols  = new Set();
let currentInterval  = '1hr'; // tracks form state

// ── On load: count badges ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const cards    = document.querySelectorAll('.config-card[data-interval]');
    const all      = cards.length;
    const oneHr    = [...cards].filter(c => c.dataset.interval === '1hr').length;
    const fifteenM = [...cards].filter(c => c.dataset.interval === '15min').length;

    const setCount = (id, n) => {
        const el = document.getElementById(id);
        if (el) el.textContent = n ? `(${n})` : '';
    };
    setCount('count-all',   all);
    setCount('count-1hr',   oneHr);
    setCount('count-15min', fifteenM);
});

// ── Interval filter tabs ──────────────────────────────────────────────────────
function filterByInterval(el, type) {
    document.querySelectorAll('.itab').forEach(t => {
        t.classList.remove('active-all', 'active-1hr', 'active-15min');
    });
    el.classList.add('active-' + type);

    document.querySelectorAll('.config-card[data-interval]').forEach(card => {
        if (type === 'all') {
            card.style.display = '';
        } else {
            card.style.display = (card.dataset.interval === type) ? '' : 'none';
        }
    });
}

// ── Interval toggle in form ───────────────────────────────────────────────────
function setIntervalType(type) {
    currentInterval = type;
    document.getElementById('f-interval-type').value = type;

    const btn1hr   = document.getElementById('btn-1hr');
    const btn15min = document.getElementById('btn-15min');
    const badge    = document.getElementById('form-interval-badge');
    const note     = document.getElementById('form-save-note');

    btn1hr.className   = 'itoggle-btn' + (type === '1hr'   ? ' sel-1hr'   : '');
    btn15min.className = 'itoggle-btn' + (type === '15min' ? ' sel-15min' : '');

    if (type === '15min') {
        badge.className   = 'ibadge ibadge-15min';
        badge.textContent = '⏱ 15 Min';
        note.textContent  = 'Will trigger: pivot15:place-orders';
    } else {
        badge.className   = 'ibadge ibadge-1hr';
        badge.textContent = '⏰ 1 Hour';
        note.textContent  = 'Will trigger: pivot:place-orders';
    }
}

// ── Symbol picker ─────────────────────────────────────────────────────────────
function toggleSymbol(chip) {
    const sym = chip.dataset.symbol;
    if (selectedSymbols.has(sym)) {
        selectedSymbols.delete(sym);
        chip.classList.remove('selected');
    } else {
        selectedSymbols.add(sym);
        chip.classList.add('selected');
    }
    updateSelectedCount();
}
function updateSelectedCount() {
    document.getElementById('selected-count').textContent = selectedSymbols.size;
}
function filterSymbols(query) {
    const q = query.trim().toUpperCase();
    document.querySelectorAll('#symbol-grid .sym-chip').forEach(chip => {
        chip.classList.toggle('hidden', !(!q || chip.dataset.symbol.includes(q)));
    });
}
function selectQuickSet(type) {
    const toSelect = type === 'all' ? ALL_SYMBOLS : type === 'index' ? INDEX_SYMBOLS : [];
    selectedSymbols.clear();
    document.querySelectorAll('#symbol-grid .sym-chip').forEach(c => c.classList.remove('selected'));
    toSelect.forEach(sym => {
        selectedSymbols.add(sym);
        const chip = document.querySelector(`#symbol-grid .sym-chip[data-symbol="${sym}"]`);
        if (chip) chip.classList.add('selected');
    });
    updateSelectedCount();
}
function setSelectedSymbols(symbols) {
    selectedSymbols.clear();
    document.querySelectorAll('#symbol-grid .sym-chip').forEach(c => c.classList.remove('selected'));
    (symbols || []).forEach(sym => {
        sym = sym.toUpperCase();
        selectedSymbols.add(sym);
        const chip = document.querySelector(`#symbol-grid .sym-chip[data-symbol="${sym}"]`);
        if (chip) chip.classList.add('selected');
    });
    updateSelectedCount();
}

// ── Layer defaults ────────────────────────────────────────────────────────────
const DEFAULTS = {
    's1-ce': [{discount_direction:'negative',discount_pct:2,quantity:0},{discount_direction:'negative',discount_pct:4,quantity:0},{discount_direction:'negative',discount_pct:6,quantity:0}],
    's1-pe': [{discount_direction:'negative',discount_pct:2,quantity:0},{discount_direction:'negative',discount_pct:4,quantity:0},{discount_direction:'negative',discount_pct:6,quantity:0}],
    'r1-ce': [{discount_direction:'positive',discount_pct:2,quantity:0},{discount_direction:'positive',discount_pct:4,quantity:0},{discount_direction:'positive',discount_pct:6,quantity:0}],
    'r1-pe': [{discount_direction:'positive',discount_pct:2,quantity:0},{discount_direction:'positive',discount_pct:4,quantity:0},{discount_direction:'positive',discount_pct:6,quantity:0}],
};

// ── Layer rendering ───────────────────────────────────────────────────────────
function renderLayer(key, idx, layer) {
    const wrap = document.getElementById(key + '-layers');
    const row  = document.createElement('div');
    row.className  = 'layer-grid';
    row.dataset.idx = idx;
    row.innerHTML  = `
        <select name="${key}_layers[${idx}][discount_direction]">
            <option value="negative" ${layer.discount_direction==='negative'?'selected':''}>&#8722; Negative</option>
            <option value="positive" ${layer.discount_direction==='positive'?'selected':''}>+ Positive</option>
        </select>
        <input type="number" name="${key}_layers[${idx}][discount_pct]"
               value="${layer.discount_pct}" min="0" max="100" step="0.1" placeholder="%" required>
        <input type="number" name="${key}_layers[${idx}][quantity]"
               value="${layer.quantity}" min="0" placeholder="Qty" required>
        <button type="button" class="btn-rm-layer" onclick="removeLayer('${key}',this)" title="Remove">&#215;</button>`;
    wrap.appendChild(row);
}
function loadLayers(key, layers) {
    document.getElementById(key + '-layers').innerHTML = '';
    (layers || DEFAULTS[key]).forEach((l, i) => renderLayer(key, i, l));
}
function addLayer(key) {
    const wrap   = document.getElementById(key + '-layers');
    const idx    = wrap.children.length;
    const defDir = key.startsWith('r1') ? 'positive' : 'negative';
    renderLayer(key, idx, {discount_direction: defDir, discount_pct: 0, quantity: 0});
}
function removeLayer(key, btn) {
    const wrap = document.getElementById(key + '-layers');
    if (wrap.children.length <= 1) { alert('At least 1 layer required.'); return; }
    btn.closest('.layer-grid').remove();
    Array.from(wrap.children).forEach((row, i) => {
        row.querySelectorAll('[name]').forEach(el => {
            el.name = el.name
                .replace(/\[\d+\]\[/, '[' + i + '][')
                .replace(/^([^[]+)\[\d+\]$/, '$1[' + i + ']');
        });
    });
}

// ── Form show / hide ──────────────────────────────────────────────────────────
function showCreateForm() {
    document.getElementById('edit-id').value        = '';
    document.getElementById('form-title').textContent = 'New Config';
    document.getElementById('f-broker').value       = '';
    document.getElementById('f-order-type').value   = 'LIMIT';
    document.getElementById('f-product').value      = 'MIS';
    document.getElementById('f-status').value       = '1';
    document.getElementById('symbol-search').value  = '';
    filterSymbols('');
    setSelectedSymbols([]);
    setIntervalType('1hr');
    ['s1-ce','s1-pe','r1-ce','r1-pe'].forEach(k => loadLayers(k, null));
    document.getElementById('symbol-error').style.display = 'none';
    document.getElementById('config-form-wrap').style.display = 'block';
    document.getElementById('config-form-wrap').scrollIntoView({behavior:'smooth', block:'start'});
}

function editConfig(cfg) {
    document.getElementById('edit-id').value        = cfg.id;
    document.getElementById('form-title').textContent = 'Edit Config #' + cfg.id;
    document.getElementById('f-broker').value       = cfg.broker_api_id;
    document.getElementById('f-order-type').value   = cfg.order_type;
    document.getElementById('f-product').value      = cfg.product;
    document.getElementById('f-status').value       = cfg.status ? '1' : '0';
    document.getElementById('symbol-search').value  = '';
    filterSymbols('');
    setSelectedSymbols(cfg.symbols || []);
    setIntervalType(cfg.interval_type || '1hr');
    loadLayers('s1-ce', cfg.s1_ce_layers);
    loadLayers('s1-pe', cfg.s1_pe_layers);
    loadLayers('r1-ce', cfg.r1_ce_layers);
    loadLayers('r1-pe', cfg.r1_pe_layers);
    document.getElementById('symbol-error').style.display = 'none';
    document.getElementById('config-form-wrap').style.display = 'block';
    document.getElementById('config-form-wrap').scrollIntoView({behavior:'smooth', block:'start'});
}

function hideForm() {
    document.getElementById('config-form-wrap').style.display = 'none';
}

// ── Submit ────────────────────────────────────────────────────────────────────
function submitConfig(e) {
    e.preventDefault();

    if (selectedSymbols.size === 0) {
        document.getElementById('symbol-error').style.display = 'block';
        return;
    }
    document.getElementById('symbol-error').style.display = 'none';

    const editId = document.getElementById('edit-id').value;
    const isEdit = !!editId;

    function collectLayers(key) {
        return Array.from(document.getElementById(key + '-layers').children).map(row => ({
            discount_direction: row.querySelector('select').value,
            discount_pct:       parseFloat(row.querySelectorAll('input')[0].value) || 0,
            quantity:           parseInt(row.querySelectorAll('input')[1].value)   || 0,
        }));
    }

    const payload = {
        broker_api_id:  document.getElementById('f-broker').value,
        order_type:     document.getElementById('f-order-type').value,
        product:        document.getElementById('f-product').value,
        status:         document.getElementById('f-status').value,
        interval_type:  document.getElementById('f-interval-type').value,
        s1_ce_layers:   collectLayers('s1-ce'),
        s1_pe_layers:   collectLayers('s1-pe'),
        r1_ce_layers:   collectLayers('r1-ce'),
        r1_pe_layers:   collectLayers('r1-pe'),
        _token:         '{{ csrf_token() }}',
    };

    // Append symbols as indexed array keys for Laravel
    Array.from(selectedSymbols).forEach((sym, i) => {
        payload[`symbols[${i}]`] = sym;
    });

    if (isEdit) payload._method = 'PUT';

    const url = isEdit
        ? '{{ url("pivot-signal/config") }}/' + editId
        : '{{ route("pivot-signal.config.store") }}';

    const saveBtn = document.querySelector('.btn-save');
    saveBtn.disabled    = true;
    saveBtn.textContent = 'Saving…';

    $.ajax({
        url, method: 'POST', data: payload,
        success(res) {
            if (res.success) {
                window.location.reload();
            } else {
                alert(res.message || 'Error saving config.');
                saveBtn.disabled    = false;
                saveBtn.textContent = '✓ Save Config';
            }
        },
        error(xhr) {
            const errs = xhr.responseJSON?.errors;
            alert(errs ? Object.values(errs).flat().join('\n') : (xhr.responseJSON?.message || 'Server error.'));
            saveBtn.disabled    = false;
            saveBtn.textContent = '✓ Save Config';
        }
    });
}

// ── Run Now ───────────────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.run-now-btn');
    if (!btn) return;

    const id         = btn.dataset.id;
    const active     = btn.dataset.active === '1';
    const hasSymbols = btn.dataset.hasSymbols === '1';
    const interval   = btn.dataset.interval || '1hr';
    const command    = interval === '15min' ? 'pivot15:place-orders' : 'pivot:place-orders';

    if (!active)     { alert('Config #' + id + ' is inactive. Activate it first.'); return; }
    if (!hasSymbols) { alert('⚠ No symbols selected for config #' + id + '. Please edit first.'); return; }
    if (!confirm(`Trigger [${command}] for Config #${id} right now?`)) return;

    const label   = btn.querySelector('.btn-label');
    const spinner = btn.querySelector('.btn-spinner');
    btn.disabled  = true;
    label.classList.add('d-none');
    spinner.classList.remove('d-none');

    $.ajax({
        url:    '{{ url("pivot-signal/config") }}/' + id + '/run-now',
        method: 'POST',
        data:   { _token: '{{ csrf_token() }}' },
        success(res) {
            alert((res.success ? '✅ ' : '⚠️ ') + res.message + (res.output ? '\n\n' + res.output : ''));
        },
        error(xhr) {
            alert('❌ ' + (xhr.responseJSON?.message || 'Server error.'));
        },
        complete() {
            btn.disabled = false;
            label.classList.remove('d-none');
            spinner.classList.add('d-none');
        }
    });
});
</script>
@endpush