@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
/* ─── Page shell ─────────────────────────────────── */
.poc-wrap { max-width: 1100px; margin: 0 auto; }
.poc-hdr  { background: linear-gradient(135deg,#1a0f00,#2a1500);
            border: 1px solid rgba(251,146,60,.25); border-radius: 14px;
            padding: 18px 22px; margin-bottom: 20px; }

/* ─── Broker table ───────────────────────────────── */
.broker-table { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
.broker-table thead th {
    color: rgba(255,255,255,.4); font-size: 10px; text-transform: uppercase;
    letter-spacing: .4px; padding: 6px 14px; background: transparent;
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.broker-row {
    background: linear-gradient(135deg,#1a1000,#201500);
    border: 1px solid rgba(255,255,255,.07); border-radius: 10px;
}
.broker-row td { padding: 14px 14px; vertical-align: middle; }
.broker-row td:first-child { border-radius: 10px 0 0 10px; }
.broker-row td:last-child  { border-radius: 0 10px 10px 0; }
.bname { font-weight:700; color:white; font-size:13px; }
.buser { font-size:10px; color:rgba(255,255,255,.4); }

/* ─── Tags ───────────────────────────────────────── */
.tag { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; }
.tag-ok   { background:rgba(34,197,94,.15);   color:#4ade80; border:1px solid rgba(34,197,94,.3);  }
.tag-err  { background:rgba(220,53,69,.15);   color:#f87171; border:1px solid rgba(220,53,69,.3);  }
.tag-cfg  { background:rgba(251,146,60,.15);  color:#fb923c; border:1px solid rgba(251,146,60,.35); }
.tag-na   { background:rgba(255,255,255,.05); color:rgba(255,255,255,.3); border:1px solid rgba(255,255,255,.1); }

/* ─── Level mini display ─────────────────────────── */
.cfg-mini { font-size:10px; line-height:1.8; color:rgba(255,255,255,.55); }
.cfg-mini strong { color:rgba(255,255,255,.8); }
.lv { display:inline-block; padding:1px 7px; border-radius:6px; font-size:9px; font-weight:700; margin-right:4px; }
.lv-s1 { background:rgba(253,126,20,.2); color:#fd7e14; }
.lv-s2 { background:rgba(220,53,69,.2);  color:#dc3545; }
.lv-s3 { background:rgba(139,0,0,.3);    color:#fca5a5; }

/* ─── Action buttons ─────────────────────────────── */
.btn-cfg  { background:rgba(251,146,60,.12); color:#fb923c; border:1px solid rgba(251,146,60,.35);
            border-radius:8px; padding:6px 14px; font-size:11px; font-weight:700; cursor:pointer; transition:.15s; }
.btn-cfg:hover { background:rgba(251,146,60,.28); color:white; }

.btn-run  { background:linear-gradient(135deg,#c2410c,#ea580c); color:white; border:none;
            border-radius:8px; padding:7px 16px; font-size:11px; font-weight:700; cursor:pointer; transition:.15s; }
.btn-run:hover  { transform:translateY(-1px); box-shadow:0 3px 12px rgba(234,88,12,.45); }
.btn-run:disabled { opacity:.5; cursor:not-allowed; transform:none; box-shadow:none; }

.btn-prev2 { background:rgba(168,85,247,.12); color:#c084fc; border:1px solid rgba(168,85,247,.35);
             border-radius:8px; padding:6px 14px; font-size:11px; font-weight:700; cursor:pointer; transition:.15s; }
.btn-prev2:hover { background:rgba(168,85,247,.28); color:white; }

.btn-del2 { background:transparent; color:rgba(255,255,255,.28); border:1px solid rgba(255,255,255,.1);
            border-radius:8px; padding:6px 12px; font-size:11px; cursor:pointer; transition:.15s; }
.btn-del2:hover { border-color:rgba(220,53,69,.5); color:#f87171; }

/* ─── Spinner ────────────────────────────────────── */
.spin { display:inline-block; width:12px; height:12px;
        border:2px solid rgba(255,255,255,.2); border-top:2px solid white;
        border-radius:50%; animation:sp .7s linear infinite; vertical-align:middle; margin-right:5px; }
@keyframes sp { to { transform:rotate(360deg) } }

/* ─── Modal ──────────────────────────────────────── */
.modal-content {
    background: linear-gradient(135deg,#1a0f00,#2a1500) !important;
    border: 1px solid rgba(251,146,60,.35) !important;
    border-radius: 14px !important; color: white;
}
.modal-header {
    background: rgba(251,146,60,.08) !important;
    border-bottom: 1px solid rgba(251,146,60,.18) !important;
    border-radius: 14px 14px 0 0 !important;
}
.modal-title { color: white; font-weight: 700; font-size: 14px; }
.modal-footer { border-top: 1px solid rgba(255,255,255,.08) !important; }
.btn-close-white { filter: invert(1); }

/* ─── Form elements ──────────────────────────────── */
.mform-label { color:rgba(255,255,255,.6); font-size:11px; font-weight:600;
               text-transform:uppercase; letter-spacing:.3px; margin-bottom:5px; }
.mform-ctrl  { background:rgba(255,255,255,.07) !important; border:1px solid rgba(255,255,255,.15) !important;
               color:white !important; border-radius:8px !important; font-size:12px; }
.mform-ctrl:focus { border-color:#fb923c !important; box-shadow:0 0 0 2px rgba(251,146,60,.18) !important; color:white !important; }
.mform-ctrl option { background:#1a0f00; color:white; }

/* ─── Chip toggle ────────────────────────────────── */
.chip-toggle { display:flex; gap:6px; flex-wrap:wrap; }
.ctog { padding:6px 16px; border-radius:20px; font-size:11px; font-weight:700; cursor:pointer;
        border:2px solid rgba(255,255,255,.12); color:rgba(255,255,255,.4);
        background:rgba(255,255,255,.04); transition:.15s; user-select:none; }
.ctog:hover { border-color:rgba(255,255,255,.25); color:rgba(255,255,255,.7); }
.ctog.on { color:white; }
.ctog-std.on      { background:linear-gradient(135deg,#1e3a5f,#2563eb); border-color:#3b82f6; }
.ctog-cam.on      { background:linear-gradient(135deg,#2d1b4e,#7c3aed); border-color:#8b5cf6; }
.ctog-ce.on       { background:linear-gradient(135deg,#14532d,#16a34a); border-color:#4ade80; }
.ctog-pe.on       { background:linear-gradient(135deg,#7f1d1d,#dc2626);  border-color:#f87171; }
.ctog-both.on     { background:linear-gradient(135deg,#1e1b4b,#6d28d9);  border-color:#a855f7; }
.ctog-market.on   { background:linear-gradient(135deg,#7c2d12,#ea580c);  border-color:#fb923c; }
.ctog-limit.on    { background:linear-gradient(135deg,#1e3a5f,#2563eb);  border-color:#3b82f6; }
.ctog-mis.on      { background:linear-gradient(135deg,#064e3b,#059669);  border-color:#34d399; }
.ctog-nrml.on     { background:linear-gradient(135deg,#312e81,#4f46e5);  border-color:#818cf8; }

/* ─── Level rows ─────────────────────────────────── */
.mlevel { border-radius:10px; padding:12px 14px; margin-bottom:8px; }
.mlevel-s1 { background:rgba(253,126,20,.07); border:1px solid rgba(253,126,20,.2); }
.mlevel-s2 { background:rgba(220,53,69,.07);  border:1px solid rgba(220,53,69,.22); }
.mlevel-s3 { background:rgba(139,0,0,.1);     border:1px solid rgba(139,0,0,.4);   }
.mlabel    { padding:4px 10px; border-radius:6px; font-weight:800; font-size:14px; display:inline-block; }
.mlabel-s1 { background:rgba(253,126,20,.2); color:#fd7e14; border:1px solid rgba(253,126,20,.5); }
.mlabel-s2 { background:rgba(220,53,69,.2);  color:#dc3545; border:1px solid rgba(220,53,69,.5); }
.mlabel-s3 { background:rgba(139,0,0,.3);    color:#fca5a5; border:1px solid rgba(139,0,0,.7);   }
.hint      { font-size:9px; color:rgba(255,255,255,.28); margin-top:3px; font-style:italic; }

/* ─── Result table ───────────────────────────────── */
.res-tbl { width:100%; border-collapse:collapse; font-size:11px; }
.res-tbl th { background:rgba(0,0,0,.3); color:rgba(255,255,255,.4); font-size:9px;
              text-transform:uppercase; padding:7px 10px; text-align:center; }
.res-tbl td { padding:7px 10px; text-align:center; color:rgba(255,255,255,.75);
              border-bottom:1px solid rgba(255,255,255,.04); }
.p-s1 { background:rgba(253,126,20,.2); color:#fd7e14; padding:1px 7px; border-radius:8px; font-weight:700; }
.p-s2 { background:rgba(220,53,69,.2);  color:#dc3545; padding:1px 7px; border-radius:8px; font-weight:700; }
.p-s3 { background:rgba(139,0,0,.25);   color:#fca5a5; padding:1px 7px; border-radius:8px; font-weight:700; }
.p-ce { background:rgba(40,167,69,.2);  color:#4ade80; padding:1px 7px; border-radius:8px; font-weight:700; }
.p-pe { background:rgba(220,53,69,.2);  color:#f87171; padding:1px 7px; border-radius:8px; font-weight:700; }
.r-ok  { color:#4ade80; font-weight:700; }
.r-err { color:#f87171; font-weight:700; }
.r-skip{ color:#fbbf24; font-weight:700; }
.p-market { background:rgba(234,88,12,.2);  color:#fb923c; padding:1px 7px; border-radius:8px; font-weight:700; font-size:9px; }
.p-limit  { background:rgba(59,130,246,.2); color:#60a5fa; padding:1px 7px; border-radius:8px; font-weight:700; font-size:9px; }
.p-mis    { background:rgba(16,185,129,.2); color:#34d399; padding:1px 7px; border-radius:8px; font-weight:700; font-size:9px; }
.p-nrml   { background:rgba(99,102,241,.2); color:#818cf8; padding:1px 7px; border-radius:8px; font-weight:700; font-size:9px; }

/* ─── Toast ──────────────────────────────────────── */
#toast { position:fixed; bottom:26px; right:26px; z-index:9999;
         background:#2a1500; border:1px solid #fb923c; border-radius:10px;
         padding:11px 20px; color:white; font-size:12px; font-weight:600;
         display:none; box-shadow:0 4px 20px rgba(251,146,60,.25); }
#toast.err { border-color:#dc3545; }
</style>
@endpush

<section class="pt-40 pb-60">
<div class="container-fluid content-container">
<div class="poc-wrap">

    {{-- Header --}}
    <div class="poc-hdr">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="mb-1" style="color:white;">🟠 Pivot Normal Order Config</h4>
                <p class="mb-0" style="color:rgba(255,255,255,.45);font-size:11px;">
                    Regular intraday orders (market hours) · MARKET or LIMIT · MIS or NRML
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('pivot-order-config.index') }}" class="btn btn-sm btn-outline-warning">
                    🌙 AMO Config
                </a>
                <a href="{{ route('pivot.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Pivots
                </a>
            </div>
        </div>
    </div>

    @if($brokers->isEmpty())
        <div style="background:rgba(220,53,69,.1);border:1px solid rgba(220,53,69,.3);border-radius:12px;padding:20px;text-align:center;color:#f87171;">
            <i class="fas fa-exclamation-triangle" style="font-size:2rem;margin-bottom:10px;display:block;"></i>
            No active Zerodha brokers with valid tokens found.
        </div>
    @else

    {{-- Broker table --}}
    <div style="background:linear-gradient(135deg,#1a0f00,#2a1500);border:1px solid rgba(251,146,60,.18);border-radius:14px;padding:20px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 style="color:#fb923c;font-weight:700;font-size:11px;text-transform:uppercase;margin:0;">
                🏦 Broker Configurations — Normal Orders
            </h6>
            <span style="font-size:10px;color:rgba(255,255,255,.3);">{{ $brokers->count() }} broker(s)</span>
        </div>

        <table class="broker-table">
            <thead>
                <tr>
                    <th>Broker</th>
                    <th>Token</th>
                    <th>Config</th>
                    <th>Levels</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($brokers as $broker)
                    @php $cfg = $configs[$broker->id] ?? null; @endphp
                    <tr class="broker-row" id="broker-row-{{ $broker->id }}">

                        <td>
                            <div class="bname">{{ $broker->client_name }}</div>
                            <div class="buser">{{ $broker->account_user_name }}</div>
                        </td>

                        <td>
                            @if($broker->is_token_valid)
                                <span class="tag tag-ok">✓ Active</span>
                            @else
                                <span class="tag tag-err">✗ Invalid</span>
                            @endif
                        </td>

                        <td>
                            @if($cfg)
                                <span class="tag tag-cfg">{{ $cfg->model_type }} · {{ $cfg->instrument_type }}</span>
                                <div style="margin-top:3px;">
                                    <span style="font-size:9px;padding:2px 7px;border-radius:6px;font-weight:700;
                                        background:rgba(234,88,12,.15);color:#fb923c;border:1px solid rgba(234,88,12,.3);">
                                        {{ $cfg->order_variety }}
                                    </span>
                                    <span style="font-size:9px;padding:2px 7px;border-radius:6px;font-weight:700;margin-left:4px;
                                        background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3);">
                                        {{ $cfg->product }}
                                    </span>
                                </div>
                                <div style="font-size:9px;color:rgba(255,255,255,.35);margin-top:2px;">
                                    {{ $cfg->is_active ? '● Active' : '○ Inactive' }}
                                </div>
                            @else
                                <span class="tag tag-na">No Config</span>
                            @endif
                        </td>

                        <td>
                            @if($cfg)
                                <div class="cfg-mini">
                                    <span class="lv lv-s1">S1</span>
                                    qty:<strong>{{ $cfg->s1_qty }}</strong>
                                    disc:<strong>{{ $cfg->s1_discount }}{{ $cfg->s1_discount_type === 'percent' ? '%' : 'pt' }}</strong>
                                    &nbsp;
                                    <span class="lv lv-s2">S2</span>
                                    qty:<strong>{{ $cfg->s2_qty }}</strong>
                                    disc:<strong>{{ $cfg->s2_discount }}{{ $cfg->s2_discount_type === 'percent' ? '%' : 'pt' }}</strong>
                                    &nbsp;
                                    <span class="lv lv-s3">S3</span>
                                    qty:<strong>{{ $cfg->s3_qty }}</strong>
                                    buf:<strong>{{ $cfg->s3_buffer }}{{ $cfg->s3_buffer_type === 'percent' ? '%' : 'pt' }}</strong>
                                </div>
                            @else
                                <span style="color:rgba(255,255,255,.2);font-size:10px;">—</span>
                            @endif
                        </td>

                        <td style="text-align:right;">
                            <div class="d-flex gap-2 justify-content-end flex-wrap">
                                <button class="btn-cfg"
                                        onclick="openConfigModal({{ $broker->id }}, '{{ addslashes($broker->client_name) }}')">
                                    <i class="fas fa-cog"></i> Config
                                </button>
                                @if($cfg)
                                    <button class="btn-prev2" id="prev-btn-{{ $broker->id }}"
                                            onclick="previewBroker({{ $broker->id }})">
                                        <i class="fas fa-eye"></i> Preview
                                    </button>
                                    <button class="btn-run" id="run-btn-{{ $broker->id }}"
                                            onclick="runBroker({{ $broker->id }}, '{{ addslashes($broker->client_name) }}')">
                                        🟠 Place Orders
                                    </button>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- Expandable result row --}}
                    <tr id="result-row-{{ $broker->id }}" style="display:none;">
                        <td colspan="5" style="padding:0 14px 14px;">
                            <div id="result-box-{{ $broker->id }}"
                                 style="background:rgba(0,0,0,.25);border-radius:10px;padding:14px;overflow-x:auto;"></div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @endif
</div>
</div>
</section>

{{-- ═══════════════════════════════════════════════
     BOOTSTRAP 5 CONFIG MODAL
═══════════════════════════════════════════════ --}}
<div class="modal fade" id="configModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="configModalTitle">🟠 Normal Order Config</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" style="padding:22px 24px;">
                <input type="hidden" id="modal_broker_id">

                {{-- Row 1: Model + Instrument --}}
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="mform-label">Pivot Model</label>
                        <div class="chip-toggle" id="model-chips">
                            <div class="ctog ctog-std on" onclick="toggleChip('model','Standard',this)">📐 Standard</div>
                            <div class="ctog ctog-cam"    onclick="toggleChip('model','Camarilla',this)">🔮 Camarilla</div>
                        </div>
                        <input type="hidden" id="m_model_type" value="Standard">
                    </div>
                    <div class="col-md-6">
                        <label class="mform-label">Instrument</label>
                        <div class="chip-toggle" id="inst-chips">
                            <div class="ctog ctog-ce"      onclick="toggleChip('inst','CE',this)">🟢 CE</div>
                            <div class="ctog ctog-pe"      onclick="toggleChip('inst','PE',this)">🔴 PE</div>
                            <div class="ctog ctog-both on" onclick="toggleChip('inst','Both',this)">🔵 Both</div>
                        </div>
                        <input type="hidden" id="m_instrument_type" value="Both">
                    </div>
                </div>

                {{-- Row 2: Order Variety + Product (extra vs AMO) --}}
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="mform-label">Order Type</label>
                        <div class="chip-toggle" id="variety-chips">
                            <div class="ctog ctog-limit on" onclick="toggleChip('variety','LIMIT',this)">📋 LIMIT</div>
                            <div class="ctog ctog-market"   onclick="toggleChip('variety','MARKET',this)">⚡ MARKET</div>
                        </div>
                        <input type="hidden" id="m_order_variety" value="LIMIT">
                        <p class="hint mt-1">LIMIT = buy at/below S level &nbsp;·&nbsp; MARKET = instant fill at best price</p>
                    </div>
                    <div class="col-md-6">
                        <label class="mform-label">Product</label>
                        <div class="chip-toggle" id="product-chips">
                            <div class="ctog ctog-mis on" onclick="toggleChip('product','MIS',this)">📅 MIS (Intraday)</div>
                            <div class="ctog ctog-nrml"   onclick="toggleChip('product','NRML',this)">📦 NRML (Positional)</div>
                        </div>
                        <input type="hidden" id="m_product" value="MIS">
                    </div>
                </div>

                {{-- S1 --}}
                <div class="mlevel mlevel-s1 mb-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto"><span class="mlabel mlabel-s1">S1</span></div>
                        <div class="col-md-3">
                            <label class="mform-label">Quantity (lots)</label>
                            <input type="number" class="form-control mform-ctrl" id="m_s1_qty" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col">
                            <label class="mform-label">Discount (order placed BELOW S1)</label>
                            <div class="d-flex gap-2">
                                <input type="number" class="form-control mform-ctrl" id="m_s1_discount" min="0" step="0.5" placeholder="0" value="0">
                                <select class="form-select mform-ctrl" id="m_s1_discount_type" style="max-width:80px;">
                                    <option value="points">pts</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                            <p class="hint">Price = S1 − discount &nbsp;·&nbsp; 0 = buy at S1 &nbsp;·&nbsp; ignored for MARKET orders</p>
                        </div>
                    </div>
                </div>

                {{-- S2 --}}
                <div class="mlevel mlevel-s2 mb-3">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto"><span class="mlabel mlabel-s2">S2</span></div>
                        <div class="col-md-3">
                            <label class="mform-label">Quantity (lots)</label>
                            <input type="number" class="form-control mform-ctrl" id="m_s2_qty" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col">
                            <label class="mform-label">Discount (order placed BELOW S2)</label>
                            <div class="d-flex gap-2">
                                <input type="number" class="form-control mform-ctrl" id="m_s2_discount" min="0" step="0.5" placeholder="0" value="0">
                                <select class="form-select mform-ctrl" id="m_s2_discount_type" style="max-width:80px;">
                                    <option value="points">pts</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                            <p class="hint">Price = S2 − discount</p>
                        </div>
                    </div>
                </div>

                {{-- S3 --}}
                <div class="mlevel mlevel-s3 mb-2">
                    <div class="row g-3 align-items-center">
                        <div class="col-auto"><span class="mlabel mlabel-s3">S3</span></div>
                        <div class="col-md-3">
                            <label class="mform-label">Quantity (lots)</label>
                            <input type="number" class="form-control mform-ctrl" id="m_s3_qty" min="0" placeholder="0" value="0">
                        </div>
                        <div class="col">
                            <label class="mform-label">Buffer (order placed ABOVE S3)</label>
                            <div class="d-flex gap-2">
                                <input type="number" class="form-control mform-ctrl" id="m_s3_buffer" min="0" step="0.5" placeholder="0" value="0">
                                <select class="form-select mform-ctrl" id="m_s3_buffer_type" style="max-width:80px;">
                                    <option value="points">pts</option>
                                    <option value="percent">%</option>
                                </select>
                            </div>
                            <p class="hint" style="color:rgba(252,165,165,.4);">Price = S3 + buffer (safer entry above S3)</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteConfig()">
                    <i class="fas fa-trash-alt"></i> Delete
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" onclick="saveConfig()" id="modal-save-btn"
                        style="background:linear-gradient(135deg,#c2410c,#ea580c);border:none;color:white;">
                    <i class="fas fa-save"></i> Save Config
                </button>
            </div>

        </div>
    </div>
</div>

<div id="toast"></div>
@endsection

@push('script')
<script>
/* ═══════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════ */
const savedConfigs = @json($configs->toArray());

/* ═══════════════════════════════════════════════
   OPEN CONFIG MODAL
═══════════════════════════════════════════════ */
function openConfigModal(brokerId, brokerName) {
    document.getElementById('modal_broker_id').value = brokerId;
    document.getElementById('configModalTitle').textContent = `🟠 Normal Order Config — ${brokerName}`;

    const cfg = savedConfigs[brokerId] ?? null;
    if (cfg) {
        setChipVal('model',   cfg.model_type);
        setChipVal('inst',    cfg.instrument_type);
        setChipVal('variety', cfg.order_variety ?? 'LIMIT');
        setChipVal('product', cfg.product        ?? 'MIS');
        document.getElementById('m_model_type').value      = cfg.model_type;
        document.getElementById('m_instrument_type').value = cfg.instrument_type;
        document.getElementById('m_order_variety').value   = cfg.order_variety ?? 'LIMIT';
        document.getElementById('m_product').value         = cfg.product        ?? 'MIS';
        document.getElementById('m_s1_qty').value          = cfg.s1_qty         ?? 0;
        document.getElementById('m_s2_qty').value          = cfg.s2_qty         ?? 0;
        document.getElementById('m_s3_qty').value          = cfg.s3_qty         ?? 0;
        document.getElementById('m_s1_discount').value     = cfg.s1_discount    ?? 0;
        document.getElementById('m_s1_discount_type').value= cfg.s1_discount_type ?? 'points';
        document.getElementById('m_s2_discount').value     = cfg.s2_discount    ?? 0;
        document.getElementById('m_s2_discount_type').value= cfg.s2_discount_type ?? 'points';
        document.getElementById('m_s3_buffer').value       = cfg.s3_buffer      ?? 0;
        document.getElementById('m_s3_buffer_type').value  = cfg.s3_buffer_type ?? 'points';
    } else {
        setChipVal('model', 'Standard'); setChipVal('inst', 'Both');
        setChipVal('variety', 'LIMIT');  setChipVal('product', 'MIS');
        ['m_s1_qty','m_s2_qty','m_s3_qty','m_s1_discount','m_s2_discount','m_s3_buffer'].forEach(id => {
            document.getElementById(id).value = 0;
        });
        ['m_s1_discount_type','m_s2_discount_type','m_s3_buffer_type'].forEach(id => {
            document.getElementById(id).value = 'points';
        });
    }

    new bootstrap.Modal(document.getElementById('configModal')).show();
}

/* ═══════════════════════════════════════════════
   CHIP HELPERS
═══════════════════════════════════════════════ */
function toggleChip(group, val, el) {
    el.closest('.chip-toggle').querySelectorAll('.ctog').forEach(c => c.classList.remove('on'));
    el.classList.add('on');
    const map = { model:'m_model_type', inst:'m_instrument_type', variety:'m_order_variety', product:'m_product' };
    document.getElementById(map[group]).value = val;
}

function setChipVal(group, val) {
    const containerIds = { model:'model-chips', inst:'inst-chips', variety:'variety-chips', product:'product-chips' };
    const hiddenIds    = { model:'m_model_type', inst:'m_instrument_type', variety:'m_order_variety', product:'m_product' };
    const container    = document.getElementById(containerIds[group]);
    if (!container) return;
    container.querySelectorAll('.ctog').forEach(c => {
        c.classList.remove('on');
        if (c.textContent.trim().toLowerCase().includes(val.toLowerCase())) c.classList.add('on');
    });
    document.getElementById(hiddenIds[group]).value = val;
}

/* ═══════════════════════════════════════════════
   SAVE
═══════════════════════════════════════════════ */
function saveConfig() {
    const brokerId = document.getElementById('modal_broker_id').value;
    if (!brokerId) { toast('❌ No broker selected', true); return; }

    const saveBtn = document.getElementById('modal-save-btn');
    saveBtn.innerHTML = '<span class="spin"></span> Saving...';
    saveBtn.disabled  = true;

    const payload = {
        broker_api_id     : brokerId,
        model_type        : document.getElementById('m_model_type').value,
        instrument_type   : document.getElementById('m_instrument_type').value,
        order_variety     : document.getElementById('m_order_variety').value,
        product           : document.getElementById('m_product').value,
        s1_qty            : document.getElementById('m_s1_qty').value            || 0,
        s2_qty            : document.getElementById('m_s2_qty').value            || 0,
        s3_qty            : document.getElementById('m_s3_qty').value            || 0,
        s1_discount       : document.getElementById('m_s1_discount').value       || 0,
        s1_discount_type  : document.getElementById('m_s1_discount_type').value,
        s2_discount       : document.getElementById('m_s2_discount').value       || 0,
        s2_discount_type  : document.getElementById('m_s2_discount_type').value,
        s3_buffer         : document.getElementById('m_s3_buffer').value         || 0,
        s3_buffer_type    : document.getElementById('m_s3_buffer_type').value,
        _token            : '{{ csrf_token() }}',
    };

    $.ajax({
        url    : '{{ route("pivot-normal-order-config.save") }}',
        type   : 'POST',
        data   : payload,
        success(res) {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Config';
            saveBtn.disabled  = false;
            if (res.success) {
                savedConfigs[brokerId] = res.config;
                toast('✅ ' + res.message, false);
                bootstrap.Modal.getInstance(document.getElementById('configModal')).hide();
                setTimeout(() => location.reload(), 800);
            } else {
                toast('❌ ' + (res.message || 'Failed'), true);
            }
        },
        error(xhr) {
            saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Config';
            saveBtn.disabled  = false;
            const errs = xhr.responseJSON?.errors;
            toast('❌ ' + (errs ? Object.values(errs).flat().join(' · ') : 'Server error'), true);
        }
    });
}

/* ═══════════════════════════════════════════════
   DELETE
═══════════════════════════════════════════════ */
function deleteConfig() {
    const brokerId = document.getElementById('modal_broker_id').value;
    if (!brokerId || !confirm('Delete config for this broker?')) return;
    $.ajax({
        url    : '{{ route("pivot-normal-order-config.reset") }}',
        type   : 'DELETE',
        data   : { broker_api_id: brokerId, _token: '{{ csrf_token() }}' },
        success(res) {
            if (res.success) {
                toast('🗑 ' + res.message, false);
                delete savedConfigs[brokerId];
                bootstrap.Modal.getInstance(document.getElementById('configModal')).hide();
                setTimeout(() => location.reload(), 800);
            }
        }
    });
}

/* ═══════════════════════════════════════════════
   PREVIEW
═══════════════════════════════════════════════ */
function previewBroker(brokerId) {
    const btn = document.getElementById(`prev-btn-${brokerId}`);
    btn.innerHTML = '<span class="spin"></span>';
    btn.disabled  = true;
    document.getElementById(`result-row-${brokerId}`).style.display = 'none';

    $.ajax({
        url    : '{{ route("pivot-normal-order-config.preview") }}',
        type   : 'GET',
        data   : { broker_api_id: brokerId },
        success(res) {
            btn.innerHTML = '<i class="fas fa-eye"></i> Preview';
            btn.disabled  = false;
            if (!res.success) { toast('❌ ' + res.message, true); return; }
            showResultBox(brokerId, res.orders, false, res.data_date, res.model, res.instrument, res.order_variety, res.product);
        },
        error(xhr) {
            btn.innerHTML = '<i class="fas fa-eye"></i> Preview';
            btn.disabled  = false;
            toast('❌ ' + (xhr.responseJSON?.message || 'Server error'), true);
        }
    });
}

/* ═══════════════════════════════════════════════
   RUN NORMAL ORDERS
═══════════════════════════════════════════════ */
function runBroker(brokerId, brokerName) {
    if (!confirm(`Place NORMAL orders for ${brokerName}?\nThis submits REAL regular intraday orders to Zerodha NOW.`)) return;

    const btn = document.getElementById(`run-btn-${brokerId}`);
    btn.innerHTML = '<span class="spin"></span> Placing...';
    btn.disabled  = true;
    document.getElementById(`result-row-${brokerId}`).style.display = 'none';

    $.ajax({
        url    : '{{ route("pivot-normal-order-config.execute") }}',
        type   : 'POST',
        data   : { broker_api_id: brokerId, _token: '{{ csrf_token() }}' },
        success(res) {
            btn.innerHTML = '🟠 Place Orders';
            btn.disabled  = false;
            if (!res.success && !res.results) { toast('❌ ' + res.message, true); return; }
            showResultBox(brokerId, res.results, true, null, null, null, null, null, res.placed, res.skipped, res.failed);
            toast(res.message, (res.failed ?? 0) > 0);
        },
        error(xhr) {
            btn.innerHTML = '🟠 Place Orders';
            btn.disabled  = false;
            toast('❌ ' + (xhr.responseJSON?.message || 'Server error'), true);
        }
    });
}

/* ═══════════════════════════════════════════════
   SHOW RESULT BOX
═══════════════════════════════════════════════ */
function showResultBox(brokerId, orders, isExecuted, dataDate, model, instrument, orderVariety, product, placed, skipped, failed) {
    const lvPill  = l => `<span class="p-${l.toLowerCase()}">${l}</span>`;
    const typPill = t => `<span class="p-${t.toLowerCase()}">${t}</span>`;
    const varPill = v => v ? `<span class="p-${v.toLowerCase()}">${v}</span>` : '';
    const prdPill = p => p ? `<span class="p-${p.toLowerCase()}">${p}</span>` : '';

    let header = '';
    if (dataDate) {
        header = `<div style="font-size:10px;color:rgba(255,255,255,.4);margin-bottom:10px;">
            📅 Data: <strong style="color:#fb923c;">${dataDate}</strong>
            &nbsp;·&nbsp; Model: <strong style="color:white;">${model}</strong>
            &nbsp;·&nbsp; Inst: <strong style="color:white;">${instrument}</strong>
            &nbsp;·&nbsp; ${varPill(orderVariety)} ${prdPill(product)}
            &nbsp;·&nbsp; ${orders.length} order(s)
        </div>`;
    }
    if (isExecuted && placed !== undefined) {
        header += `<div style="font-size:11px;font-weight:700;margin-bottom:10px;padding:6px 12px;border-radius:8px;
            background:rgba(251,146,60,.1);border:1px solid rgba(251,146,60,.3);color:#fb923c;">
            ✅ Placed: ${placed} &nbsp;|&nbsp; ⏭ Skipped: ${skipped} &nbsp;|&nbsp; ❌ Failed: ${failed}
        </div>`;
    }

    const rows = (orders || []).map(o => {
        const priceDisplay = (o.order_variety === 'MARKET')
            ? '<span class="p-market">MARKET</span>'
            : `<span style="font-weight:700;color:#fb923c;">₹${Number(o.order_price ?? 0).toFixed(2)}</span>`;

        let resultCell = '<span style="color:rgba(255,255,255,.2);font-size:9px;">—</span>';
        if (isExecuted) {
            if (o.success)       resultCell = `<span class="r-ok">✅ ${o.order_id ?? 'placed'}</span>`;
            else if (o.skipped)  resultCell = `<span class="r-skip">⏭ Skipped</span>`;
            else                 resultCell = `<span class="r-err">❌ ${(o.message||'').substring(0,50)}</span>`;
        }

        return `<tr>
            <td style="font-weight:700;color:#fb923c;">${o.symbol}</td>
            <td>${typPill(o.type)}</td>
            <td>${lvPill(o.level)}</td>
            <td style="font-size:10px;color:rgba(255,255,255,.45);">${o.trading_sym}</td>
            <td style="color:rgba(255,255,255,.4);">₹${Number(o.raw_price ?? 0).toFixed(2)}</td>
            <td>${priceDisplay}</td>
            <td style="font-size:10px;">
                <span style="color:rgba(255,255,255,.5);">${o.lots ?? o.qty}</span>
                ${o.lot_size ? `<span style="color:rgba(255,255,255,.25);">×${o.lot_size}</span>=<strong style="color:white;">${o.qty}</strong>` : ''}
            </td>
            <td>${varPill(o.order_variety ?? orderVariety)}</td>
            <td>${prdPill(o.product ?? product)}</td>
            <td>${resultCell}</td>
        </tr>`;
    }).join('') || '<tr><td colspan="10" style="text-align:center;color:rgba(255,255,255,.25);padding:16px;">No orders</td></tr>';

    document.getElementById(`result-box-${brokerId}`).innerHTML = `
        ${header}
        <table class="res-tbl">
            <thead>
                <tr>
                    <th>Symbol</th><th>Type</th><th>Level</th>
                    <th>Trading Sym</th><th>Raw Price</th><th>Order Price</th>
                    <th>Lots×Size=Qty</th><th>Variety</th><th>Product</th>
                    <th>${isExecuted ? 'Result' : 'Order'}</th>
                </tr>
            </thead>
            <tbody>${rows}</tbody>
        </table>`;

    document.getElementById(`result-row-${brokerId}`).style.display = 'table-row';
}

/* ═══════════════════════════════════════════════
   TOAST
═══════════════════════════════════════════════ */
function toast(msg, isErr = false) {
    const el = document.getElementById('toast');
    el.textContent   = msg;
    el.className     = isErr ? 'err' : '';
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 3500);
}
</script>
@endpush