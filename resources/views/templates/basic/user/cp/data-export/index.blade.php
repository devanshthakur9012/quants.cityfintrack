@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .exp-page-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:26px 30px;border-radius:12px;margin-bottom:25px;box-shadow:0 4px 15px rgba(102,126,234,.35);}
    .exp-page-header h4{margin:0;}
    .exp-card{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 10px rgba(0,0,0,.06);}
    .exp-note{background:#fff8e1;border-left:4px solid #ffc107;padding:12px 16px;border-radius:6px;font-size:.85rem;color:#7a5c00;margin-top:20px;}
</style>
@endpush

<section class="pt-50 pb-50">
<div class="container-fluid content-container">

    <div class="exp-page-header">
        <h4>{{ $pageTitle }}</h4>
        <small style="opacity:.9;">Download Stock + Futures + Option (ATM-1 / ATM / ATM+1) OHLC data as a ZIP of Excel files.</small>
    </div>

    <div class="exp-card">
        <form action="{{ route('cp.data-export.download') }}" method="POST">
            @csrf
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Timeframe <span class="text-danger">*</span></label>
                    <select name="timeframe" id="exp_timeframe" class="form-control" required onchange="expLoadSymbols()">
                        <option value="15min">15 Min</option>
                        <option value="30min">30 Min</option>
                        <option value="1hr">1 Hour</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Symbol <span class="text-danger">*</span></label>
                    <select name="symbol" id="exp_symbol" class="form-control" required>
                        <option value="">— Select Symbol —</option>
                        @foreach($symbols as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">From Date <span class="text-danger">*</span></label>
                    <input type="date" name="date_from" id="exp_date_from" class="form-control" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">To Date <span class="text-danger">*</span></label>
                    <input type="date" name="date_to" id="exp_date_to" class="form-control" required>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary" id="exp_submit_btn">
                    <i class="fas fa-file-archive"></i> Download ZIP
                </button>
            </div>
        </form>

        <div class="exp-note">
            <i class="fas fa-info-circle"></i>
            The ZIP contains one Excel workbook with 4 sheets — <strong>Stock</strong>, <strong>Futures</strong>, <strong>Option CE</strong>, <strong>Option PE</strong>.
            Option sheets show <strong>ATM-1 / ATM / ATM+1</strong> strikes side-by-side per row. Larger date ranges take longer to generate.
        </div>
    </div>
</div>
</section>

@push('script')
<script>
$(function () {
    const today = new Date().toISOString().split('T')[0];
    $('#exp_date_from').val(today);
    $('#exp_date_to').val(today);

    $('form').on('submit', function () {
        $('#exp_submit_btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generating…');
        setTimeout(() => {
            $('#exp_submit_btn').prop('disabled', false).html('<i class="fas fa-file-archive"></i> Download ZIP');
        }, 8000);
    });
});
</script>
@endpush
@endsection