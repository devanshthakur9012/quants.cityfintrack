@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: left !important;
        padding: 8px !important;
        font-size: 12px;
    }

    .filter-container {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .filter-row {
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }

    .filter-item {
        display: flex;
        flex-direction: column;
        min-width: 150px;
    }

    .filter-item label {
        font-weight: 600;
        margin-bottom: 5px;
        color: #333;
    }

    .summary-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        min-width: 120px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .trend-strong-bullish {
        background: #28a745;
        color: white;
    }

    .trend-mild-bullish {
        background: #6f42c1;
        color: white;
    }

    .trend-neutral {
        background: #6c757d;
        color: white;
    }

    .trend-mild-bearish {
        background: #fd7e14;
        color: white;
    }

    .trend-strong-bearish {
        background: #dc3545;
        color: white;
    }

    .trend-bullish-breakout {
        background: #b8f7b0;
        color: #0b5137;
    }

    .trend-bearish-breakout {
        background: #f7b0b0;
        color: #58151c;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <h4 class="mb-4">{{ $pageTitle }}</h4>

        <!-- Filters -->
        <div class="filter-container">
            <div class="filter-row">
                <div class="filter-item">
                    <label>Date Filter:</label>
                    <input type="date" id="dateFilter" class="form-control" value="{{ now()->toDateString() }}">
                </div>



                <div class="filter-item">
                    <label>Symbol:</label>
                    <select id="symbolFilter" class="form-select">
                        <option value="all">All Symbols</option>
                        @foreach ($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-item">
                    <label>Sentiment:</label>
                    <select id="sentimentFilter" class="form-select">
                        <option value="all">All Sentiments</option>
                        <option value="Strong Bullish">Strong Bullish</option>
                        <option value="Strong Bearish">Strong Bearish</option>
                        <option value="Bullish Breakout Possible">Bullish Breakout Possible</option>
                        <option value="Bearish Breakout Possible">Bearish Breakout Possible</option>
                        <option value="Neutral">Neutral</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label>Strength Score (≥):</label>
                    <input type="number" id="strengthScoreFilter" class="form-control" placeholder="e.g. 1.5">
                </div>

                <div class="filter-item">
                    <label>Sort by Strength</label>
                    <select id="sortOrder" class="form-control">
                        <option value="">Sort By</option>
                        <option value="desc">High → Low</option>
                        <option value="asc">Low → High</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label>Search:</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Search symbols...">
                </div>

                <div class="filter-item">
                    <label>&nbsp;</label>
                    <button id="applyFilters" class="btn btn--base">Apply Filters</button>
                </div>

                <div class="filter-item">
                    <label>&nbsp;</label>
                    <button id="clearFilters" class="btn btn-secondary">Clear</button>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="custom--card card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">Options Sentiment Data</span>
                <button class="btn btn-sm btn--base reload-btn rounded-pill" id="reloadData">
                    <i class="las la-sync"></i> Reload
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table custom--table">
                        <thead class="">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Symbol</th>
                                <th>CE OI %</th>
                                <th>PE OI %</th>
                                <th>Sentiment</th>
                                <th>Pattern</th>
                                <th>Strength</th>
                                <th>Support</th>
                                <th>Resistance</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="10" class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Loading data...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="paginationContainer" class="mt-3"></div>
    </div>
</section>

@endsection

@push('script')
<script>
$(document).ready(function() {
    let lastFetchedData = [];

    // Initial load
    fetchHistoricalData();

    $('#applyFilters').on('click', fetchHistoricalData);
    $('#reloadData').on('click', fetchHistoricalData);

    $('#clearFilters').on('click', function() {
        $('#dateFilter').val('');
        $('#symbolFilter').val('all');
        $('#sentimentFilter').val('all');
        $('#strengthScoreFilter').val('');
        $('#searchFilter').val('');
        $('#sortOrder').val('desc');
        fetchHistoricalData();
    });

    $('#searchFilter').on('keypress', function(e) {
        if (e.which === 13) fetchHistoricalData();
    });

    $('#sortOrder').on('change', renderTable); // instant sort

    function fetchHistoricalData() {
        const filters = {
            _token: '{{ csrf_token() }}',
            date_filter: $('#dateFilter').val() || 'all',
            symbol_filter: $('#symbolFilter').val(),
            trade_type: $('#sentimentFilter').val(),
            search_term: $('#searchFilter').val(),
            strength_score: $('#strengthScoreFilter').val()
        };

        $('#tableBody').html(`
            <tr>
                <td colspan="10" class="text-center">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2">Loading data...</p>
                </td>
            </tr>
        `);

        $.ajax({
            url: "{{ route('user.historical.analysis.fetch') }}",
            type: "POST",
            data: filters,
            success: function(response) {
                if (response.status === 'success' && response.data.length > 0) {
                    lastFetchedData = response.data;
                    renderTable();
                } else {
                    $('#tableBody').html('<tr><td colspan="10" class="text-center text-muted">No data found.</td></tr>');
                }
            },
            error: function() {
                $('#tableBody').html('<tr><td colspan="10" class="text-center text-danger">Error fetching data.</td></tr>');
            }
        });
    }

    function renderTable() {
        let data = [...lastFetchedData];
        const sortOrder = $('#sortOrder').val();

        // ✅ Only sort when user selects ASC or DESC
        if (sortOrder === 'asc') {
            data.sort((a, b) => a.strength_score - b.strength_score);
        } else if (sortOrder === 'desc') {
            data.sort((a, b) => b.strength_score - a.strength_score);
        }

        if (data.length === 0) {
            $('#tableBody').html('<tr><td colspan="10" class="text-center text-muted">No records found.</td></tr>');
            return;
        }

        let html = '';
        data.forEach((item, index) => {
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${item.date}</td>
                    <td>${item.underlying}</td>
                    <td>${item.avg_ce_oi_change}%</td>
                    <td>${item.avg_pe_oi_change}%</td>
                    <td><span class="badge ${getTrendClass(item.sentiment)}">${item.sentiment}</span></td>
                    <td>${item.pattern ?? '-'}</td>
                    <td>${item.strength_score}</td>
                    <td>${item.support_zone ?? '-'}</td>
                    <td>${item.resistance_zone ?? '-'}</td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
    }

    function getTrendClass(sentiment) {
        const map = {
            'strong bullish': 'trend-strong-bullish',
            'mild bullish': 'trend-mild-bullish',
            'bullish breakout possible': 'trend-bullish-breakout',
            'bearish breakout possible': 'trend-bearish-breakout',
            'neutral': 'trend-neutral',
            'mild bearish': 'trend-mild-bearish',
            'strong bearish': 'trend-strong-bearish'
        };
        return map[sentiment?.toLowerCase()] || 'trend-neutral';
    }
});
</script>
@endpush