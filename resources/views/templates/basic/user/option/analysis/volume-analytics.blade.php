@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .trend-bullish { background: #28a745; color: white; }
    .trend-bearish { background: #dc3545; color: white; }
    .trend-neutral { background: #6c757d; color: white; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <h4 class="mb-4">{{ $pageTitle }}</h4>

        <div class="filter-container mb-5">
            <div class="row align-items-end">
                <div class="col-lg-2">
                    <label>Date:</label>
                    <input type="date" id="dateFilter" class="form-control" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-lg-2">
                    <label>Symbol:</label>
                    <select id="symbolFilter" class="form-select">
                        <option value="all">All Symbols</option>
                        @foreach ($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label>Signal:</label>
                    <select id="signalFilter" class="form-select">
                        <option value="all">All</option>
                        <option value="Bullish">Bullish</option>
                        <option value="Bearish">Bearish</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <label>Strength ≥</label>
                    <input type="number" id="strengthFilter" class="form-control" placeholder="e.g. 10">
                </div>
                <div class="col-lg-2">
                    <label>Sort by Strength:</label>
                    <select id="sortOrder" class="form-select">
                        <option value="">Sort</option>
                        <option value="desc">High → Low</option>
                        <option value="asc">Low → High</option>
                    </select>
                </div>
                <div class="col-lg-2">
                    <button class="btn btn--base" id="applyFilters">Apply</button>
                </div>
            </div>
        </div>

        <div class="custom--card card">
            <div class="card-header d-flex justify-content-between">
                <span class="fw-bold">Volume-Based Sentiment Data</span>
                <button id="reloadData" class="btn btn-sm btn--base"><i class="las la-sync"></i> Reload</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Symbol</th>
                                <th>CE Volume</th>
                                <th>PE Volume</th>
                                <th>Volume Ratio (CE/PE)</th>
                                <th>Signal</th>
                                <th>Strength</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
$(document).ready(function() {
    let lastFetched = [];

    fetchVolumeData();

    $('#applyFilters, #reloadData').on('click', fetchVolumeData);
    $('#sortOrder').on('change', renderTable);

    function fetchVolumeData() {
        const filters = {
            _token: '{{ csrf_token() }}',
            date_filter: $('#dateFilter').val() || 'all',
            symbol_filter: $('#symbolFilter').val(),
            trade_type: $('#signalFilter').val(),
            strength_score: $('#strengthFilter').val()
        };

        $('#tableBody').html(`<tr><td colspan="8" class="text-center text-muted">Loading...</td></tr>`);

        $.ajax({
            url: "{{ route('user.volume.analytics.fetch') }}",
            type: "POST",
            data: filters,
            success: function(res) {
                if (res.status === 'success' && res.data.length > 0) {
                    lastFetched = res.data;
                    renderTable();
                } else {
                    $('#tableBody').html('<tr><td colspan="8" class="text-center text-muted">No data found.</td></tr>');
                }
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="8" class="text-center text-danger">Error loading data.</td></tr>');
            }
        });
    }

    function renderTable() {
        let data = [...lastFetched];
        const sortOrder = $('#sortOrder').val();

        if (sortOrder === 'asc') data.sort((a, b) => a.strength_score - b.strength_score);
        else if (sortOrder === 'desc') data.sort((a, b) => b.strength_score - a.strength_score);

        let html = '';
        data.forEach((row, i) => {
            html += `
                <tr>
                    <td>${i + 1}</td>
                    <td>${row.date}</td>
                    <td>${row.underlying}</td>
                    <td>${row.total_ce_volume}</td>
                    <td>${row.total_pe_volume}</td>
                    <td>${row.volume_ratio}</td>
                    <td><span class="badge ${getTrendClass(row.signal)}">${row.signal}</span></td>
                    <td>${row.strength_score}%</td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
    }

    function getTrendClass(signal) {
        const map = {
            'bullish': 'trend-bullish',
            'bearish': 'trend-bearish',
            'neutral': 'trend-neutral'
        };
        return map[signal.toLowerCase()] || 'trend-neutral';
    }
});
</script>
@endpush