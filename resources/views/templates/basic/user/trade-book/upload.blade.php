@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Syne:wght@400;600;800&display=swap" rel="stylesheet">
<style>
:root {
    --bg-primary:#0a0e17; --bg-card:#0f1520; --bg-panel:#151d2e; --bg-row:#111827;
    --border:#1e2d45; --border-bright:#2a3f5f;
    --text-primary:#e2e8f0; --text-muted:#64748b; --text-dim:#94a3b8;
    --accent:#3b82f6; --accent-glow:rgba(59,130,246,0.18);
    --bullish:#10b981; --bearish:#ef4444;
    --mono:'JetBrains Mono',monospace;
}
*{box-sizing:border-box;} body{background:var(--bg-primary);color:var(--text-primary);}
.tb-page{min-height:100vh;padding:28px 20px 60px;background:radial-gradient(ellipse 80% 40% at 50% -10%,rgba(59,130,246,0.07) 0%,transparent 70%),var(--bg-primary);}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;}
.page-title{font-weight:800;font-size:1.45rem;letter-spacing:-0.5px;}
.page-title span{color:var(--accent);}
.upload-card{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:32px;max-width:640px;margin:0 auto 32px;}
.upload-card h2{font-weight:700;font-size:1rem;color:var(--text-primary);margin-bottom:22px;display:flex;align-items:center;gap:8px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
.form-group{display:flex;flex-direction:column;gap:6px;}
.form-group label{font-size:0.68rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;font-weight:500;}
.form-hint{font-size:0.66rem;color:var(--text-muted);margin-top:3px;}
.form-control{background:var(--bg-row);border:1px solid var(--border);border-radius:8px;color:var(--text-primary);padding:9px 13px;font-size:0.84rem;outline:none;width:100%;transition:border-color .2s;}
.form-control:focus{border-color:var(--accent);}
.form-control option{background:var(--bg-row);}
.no-broker-warn{background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:8px;padding:12px 16px;font-size:0.82rem;color:#f59e0b;margin-bottom:18px;display:flex;align-items:flex-start;gap:8px;}
.drop-zone{border:2px dashed var(--border-bright);border-radius:10px;padding:32px 20px;text-align:center;cursor:pointer;transition:all .25s;margin-bottom:16px;background:var(--bg-row);}
.drop-zone.drag-over{border-color:var(--accent);background:rgba(59,130,246,0.05);}
.drop-zone i{font-size:2rem;color:var(--text-muted);display:block;margin-bottom:8px;}
.drop-zone p{color:var(--text-muted);font-size:0.82rem;margin:0;}
.drop-zone .file-name{color:var(--accent);font-weight:600;font-size:0.84rem;margin-top:8px;}
#fileInput{display:none;}
.btn-upload{background:var(--accent);color:white;border:none;border-radius:8px;padding:11px 28px;font-size:0.88rem;font-weight:700;cursor:pointer;width:100%;transition:opacity .2s;display:flex;align-items:center;justify-content:center;gap:8px;}
.btn-upload:hover{opacity:0.88;}
.btn-upload:disabled{opacity:0.45;cursor:not-allowed;}
.alert{padding:12px 16px;border-radius:8px;font-size:0.83rem;margin-bottom:20px;}
.alert-success{background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.3);color:var(--bullish);}
.alert-error{background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:var(--bearish);}
.history-card{background:var(--bg-card);border:1px solid var(--border);border-radius:12px;overflow:hidden;}
.history-card h2{font-weight:700;font-size:0.95rem;color:var(--text-primary);padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px;}
.hist-table{width:100%;border-collapse:collapse;font-size:0.8rem;}
.hist-table th{padding:10px 14px;text-align:left;font-size:0.64rem;text-transform:uppercase;letter-spacing:1px;color:var(--text-muted);background:var(--bg-panel);white-space:nowrap;}
.hist-table td{padding:11px 14px;border-bottom:1px solid var(--border);vertical-align:middle;}
.hist-table tr:last-child td{border-bottom:none;}
.hist-table tr:hover td{background:var(--bg-panel);}
.broker-badge{display:inline-block;padding:2px 9px;border-radius:4px;font-size:0.68rem;font-weight:700;background:var(--accent-glow);color:var(--accent);border:1px solid rgba(59,130,246,0.3);}
.month-badge{font-family:var(--mono);font-weight:700;font-size:0.8rem;color:#38bdf8;}
.btn-report{background:transparent;border:1px solid var(--accent);color:var(--accent);border-radius:6px;padding:4px 12px;font-size:0.72rem;cursor:pointer;text-decoration:none;transition:all .2s;display:inline-flex;align-items:center;gap:5px;}
.btn-report:hover{background:var(--accent);color:white;}
.btn-del{background:transparent;border:1px solid rgba(239,68,68,0.3);color:var(--bearish);border-radius:6px;padding:4px 10px;font-size:0.72rem;cursor:pointer;transition:all .2s;}
.btn-del:hover{background:rgba(239,68,68,0.1);}
.empty-hist{text-align:center;padding:40px 20px;color:var(--text-muted);}
.empty-hist i{font-size:2rem;display:block;margin-bottom:8px;}
.format-note{background:var(--bg-panel);border:1px solid var(--border);border-radius:10px;padding:14px 18px;font-size:0.76rem;color:var(--text-muted);line-height:1.9;max-width:640px;margin:0 auto 20px;}
.format-note strong{color:var(--text-dim);}
.format-note code{font-family:var(--mono);background:var(--bg-row);padding:1px 5px;border-radius:3px;color:#38bdf8;font-size:0.73rem;}
</style>
@endpush

<div class="tb-page">
<div style="max-width:1000px;margin:0 auto;">

    <div class="page-header">
        <h1 class="page-title">Trade Book — <span>Upload</span></h1>
        <a href="{{ route('trade-book.report') }}"
           style="background:var(--bg-panel);border:1px solid var(--border);border-radius:8px;color:var(--text-dim);padding:7px 16px;font-size:0.8rem;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            📊 View Reports
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="max-width:640px;margin:0 auto 20px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-error" style="max-width:640px;margin:0 auto 20px;">
            @foreach($errors->all() as $e){{ $e }}<br>@endforeach
        </div>
    @endif

    <div class="format-note">
        <strong>📋 Supported Brokers — Zerodha, Upstox, Angel, Fyers</strong><br>
        Download your trade book from your broker console → Reports → Trade Book. Export as <code>.xlsx</code> or <code>.csv</code>.<br>
        Required columns: <code>Symbol</code> · <code>Trade Date</code> · <code>Trade Type</code> · <code>Quantity</code> · <code>Price</code> · <code>Order Execution Time</code>
    </div>

    {{-- ── UPLOAD FORM ── --}}
    <div class="upload-card">
        <h2>⬆️ Upload Trade File</h2>

        @if($brokers->isEmpty())
            <div class="no-broker-warn">
                ⚠️ <span>No broker accounts linked to your profile. Please add a broker account first before uploading trade data.</span>
            </div>
        @endif

        <form method="POST" action="{{ route('trade-book.process-upload') }}"
              enctype="multipart/form-data" id="uploadForm">
            @csrf

            <div class="form-row">

                {{-- Broker dropdown — value = broker_apis.id --}}
                <div class="form-group">
                    <label>Broker Account</label>
                    <select name="broker_api_id" class="form-control" required
                            {{ $brokers->isEmpty() ? 'disabled' : '' }}>
                        <option value="">— Select Broker —</option>
                        @foreach($brokers as $b)
                            <option value="{{ $b->id }}"
                                {{ old('broker_api_id') == $b->id ? 'selected' : '' }}>
                                {{ $b->broker_name }}{{ $b->client_name ? ' — '.$b->client_name : '' }}
                            </option>
                        @endforeach
                    </select>
                    <span class="form-hint">
                        {{ $brokers->count() }} account(s) linked &nbsp;·&nbsp;
                        Uploading same broker + month will replace existing data
                    </span>
                </div>

                {{-- Month picker --}}
                <div class="form-group">
                    <label>Report Month</label>
                    <input type="month" name="upload_month" class="form-control" required
                           value="{{ old('upload_month', date('Y-m')) }}"
                           {{ $brokers->isEmpty() ? 'disabled' : '' }}>
                    <span class="form-hint">e.g. Feb 2026 → select 2026-02</span>
                </div>
            </div>

            {{-- Drop zone --}}
            <div class="drop-zone" id="dropZone"
                 onclick="{{ !$brokers->isEmpty() ? 'document.getElementById(\'fileInput\').click()' : '' }}"
                 style="{{ $brokers->isEmpty() ? 'opacity:0.35;cursor:not-allowed;' : '' }}">
                <i class="las la-cloud-upload-alt"></i>
                <p>Click to select or drag &amp; drop your trade file</p>
                <p style="font-size:0.71rem;margin-top:4px;opacity:0.7;">
                    Supports <strong>.xlsx</strong> &nbsp;·&nbsp; <strong>.xls</strong> &nbsp;·&nbsp; <strong>.csv</strong>
                    &nbsp;&nbsp;Max 10 MB
                </p>
                <div class="file-name" id="fileName" style="display:none;"></div>
            </div>
            <input type="file" id="fileInput" name="trade_file" accept=".xlsx,.xls,.csv"
                   {{ $brokers->isEmpty() ? 'disabled' : '' }}>

            <button type="submit" class="btn-upload" id="submitBtn" disabled>
                <span id="btnText">
                    @if($brokers->isEmpty()) ⚠ Add a broker account first
                    @else ⬆ Select a file to continue @endif
                </span>
            </button>
        </form>
    </div>

    {{-- ── UPLOAD HISTORY ── --}}
    <div class="history-card">
        <h2>📁 Uploaded Reports</h2>
        @if($uploadHistory->isEmpty())
            <div class="empty-hist">
                <i class="las la-inbox"></i>
                <p>No reports uploaded yet.</p>
            </div>
        @else
        <div style="overflow-x:auto;">
        <table class="hist-table">
            <thead>
                <tr>
                    <th>Broker Account</th>
                    <th>Month</th>
                    <th>Total Rows</th>
                    <th>Buy</th>
                    <th>Sell</th>
                    <th>Date Range</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($uploadHistory as $h)
            <tr>
                <td>
                    <span class="broker-badge">{{ $h->broker_name }}</span>
                    @if($h->client_name)
                        <span style="font-size:0.72rem;color:var(--text-muted);margin-left:5px;">{{ $h->client_name }}</span>
                    @endif
                </td>
                <td>
                    <span class="month-badge">
                        {{ \Carbon\Carbon::parse($h->upload_month.'-01')->format('M Y') }}
                    </span>
                </td>
                <td style="font-weight:600;">{{ number_format($h->total_rows) }}</td>
                <td style="color:var(--bullish);font-weight:600;">{{ number_format($h->buy_rows) }}</td>
                <td style="color:var(--bearish);font-weight:600;">{{ number_format($h->sell_rows) }}</td>
                <td style="font-size:0.74rem;color:var(--text-dim);">
                    {{ \Carbon\Carbon::parse($h->from_date)->format('d M') }}
                    → {{ \Carbon\Carbon::parse($h->to_date)->format('d M Y') }}
                </td>
                <td>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <a href="{{ route('trade-book.report', ['broker_api_id' => $h->broker_api_id, 'upload_month' => $h->upload_month]) }}"
                           class="btn-report">📊 Report</a>

                        <form method="POST" action="{{ route('trade-book.delete-upload') }}"
                              onsubmit="return confirm('Delete all {{ $h->total_rows }} rows for {{ $h->broker_name }} — {{ $h->upload_month }}?')">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="broker_api_id" value="{{ $h->broker_api_id }}">
                            <input type="hidden" name="upload_month"  value="{{ $h->upload_month }}">
                            <button type="submit" class="btn-del">🗑 Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

</div>
</div>

@push('script')
<script>
@if(!$brokers->isEmpty())
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileName  = document.getElementById('fileName');
const submitBtn = document.getElementById('submitBtn');
const btnText   = document.getElementById('btnText');

fileInput.addEventListener('change', () => {
    if (!fileInput.files.length) return;
    const f  = fileInput.files[0];
    const kb = f.size / 1024;
    const sz = kb > 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb.toFixed(0) + ' KB';
    fileName.textContent = '📎 ' + f.name + '  (' + sz + ')';
    fileName.style.display = 'block';
    submitBtn.disabled   = false;
    btnText.textContent  = '⬆ Upload & Import Trades';
});

['dragenter','dragover'].forEach(e =>
    dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.add('drag-over'); })
);
['dragleave','drop'].forEach(e =>
    dropZone.addEventListener(e, ev => { ev.preventDefault(); dropZone.classList.remove('drag-over'); })
);
dropZone.addEventListener('drop', ev => {
    if (!ev.dataTransfer.files.length) return;
    const dt = new DataTransfer();
    dt.items.add(ev.dataTransfer.files[0]);
    fileInput.files = dt.files;
    fileInput.dispatchEvent(new Event('change'));
});

document.getElementById('uploadForm').addEventListener('submit', () => {
    submitBtn.disabled  = true;
    btnText.textContent = '⏳ Importing… please wait';
});
@endif
</script>
@endpush
@endsection