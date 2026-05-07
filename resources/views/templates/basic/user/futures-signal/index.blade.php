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
    .summary-cards {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .summary-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 15px;
        text-align: center;
        min-width: 120px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .summary-card h6 {
        margin: 0;
        font-size: 24px;
        font-weight: bold;
    }
    .summary-card p {
        margin: 5px 0 0 0;
        font-size: 12px;
        color: #666;
    }
    .signal-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .signal-buy { background: #28a745; color: white; }
    .signal-sell { background: #dc3545; color: white; }
    .signal-no-trade { background: #6c757d; color: white; }
    
    .structure-badge {
        padding: 4px 8px;
        border-radius: 8px;
        font-size: 10px;
        font-weight: 600;
    }
    .struct-long-buildup { background: #28a745; color: white; }
    .struct-short-buildup { background: #dc3545; color: white; }
    .struct-short-covering { background: #17a2b8; color: white; }
    .struct-long-unwinding { background: #ffc107; color: #000; }
    .struct-neutral { background: #6c757d; color: white; }
    
    .ha-badge {
        padding: 4px 8px;
        border-radius: 8px;
        font-size: 10px;
        font-weight: 600;
    }
    .ha-green { background: #28a745; color: white; }
    .ha-red { background: #dc3545; color: white; }
    
    .positive { color: #28a745; font-weight: bold; }
    .negative { color: #dc3545; font-weight: bold; }
    .zero { color: #6c757d; }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }
    .info-item {
        padding: 8px;
        background: #f8f9fa;
        border-radius: 4px;
    }
    .info-label {
        font-size: 10px;
        color: #666;
        margin-bottom: 4px;
    }
    .info-value {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">{{ $pageTitle }}</h4>
            <div class="badge bg-info text-dark">
                Combined Signal Analysis
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-container">
            <div class="filter-row">
                <div class="filter-item">
                    <label>Date Filter:</label>
                    <input type="date" id="dateFilter" class="form-control">
                </div>
                
                <div class="filter-item">
                    <label>Symbol:</label>
                    <select id="symbolFilter" class="form-select">
                        <option value="all">All Symbols</option>
                        @foreach($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-item">
                    <label>Final Signal:</label>
                    <select id="signalFilter" class="form-select">
                        <option value="all">All Signals</option>
                        <option value="BUY">BUY</option>
                        <option value="SELL">SELL</option>
                        <option value="NO TRADE">NO TRADE</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>Structure Type:</label>
                    <select id="structureFilter" class="form-select">
                        <option value="all">All Structures</option>
                        <option value="LONG_BUILDUP">Long Buildup</option>
                        <option value="SHORT_BUILDUP">Short Buildup</option>
                        <option value="SHORT_COVERING">Short Covering</option>
                        <option value="LONG_UNWINDING">Long Unwinding</option>
                        <option value="NEUTRAL">Neutral</option>
                    </select>
                </div>
                
                <div class="filter-item">
                    <label>HA Color:</label>
                    <select id="haColorFilter" class="form-select">
                        <option value="all">All Colors</option>
                        <option value="GREEN">Green</option>
                        <option value="RED">Red</option>
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

        <!-- Summary Cards -->
        <div class="summary-cards" id="summaryCards">
            <div class="summary-card">
                <h6 id="totalRecords" class="text-dark">0</h6>
                <p>Total Candles</p>
            </div>
            <div class="summary-card">
                <h6 id="buyCount" class="text-success">0</h6>
                <p>BUY Signals</p>
            </div>
            <div class="summary-card">
                <h6 id="sellCount" class="text-danger">0</h6>
                <p>SELL Signals</p>
            </div>
            <div class="summary-card">
                <h6 id="noTradeCount" class="text-muted">0</h6>
                <p>NO TRADE</p>
            </div>
        </div>

        <!-- Structure & HA Summary -->
        <div class="summary-cards">
            <div class="summary-card">
                <h6 id="longBuildupCount" class="text-success">0</h6>
                <p>Long Buildup</p>
            </div>
            <div class="summary-card">
                <h6 id="shortBuildupCount" class="text-danger">0</h6>
                <p>Short Buildup</p>
            </div>
            <div class="summary-card">
                <h6 id="shortCoveringCount" class="text-info">0</h6>
                <p>Short Covering</p>
            </div>
            <div class="summary-card">
                <h6 id="longUnwindingCount" class="text-warning">0</h6>
                <p>Long Unwinding</p>
            </div>
            <div class="summary-card">
                <h6 id="greenHACount" class="text-success">0</h6>
                <p>Green HA</p>
            </div>
            <div class="summary-card">
                <h6 id="redHACount" class="text-danger">0</h6>
                <p>Red HA</p>
            </div>
        </div>

        <!-- Data Table -->
        <div class="custom--card card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">Combined Trading Signals</span>
                <button class="btn btn-sm btn--base reload-btn rounded-pill" id="reloadData">
                    <i class="las la-sync"></i> Reload
                </button>
            </div>
            <div class="card-body" id="dataTableContainer">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading signals...</p>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div id="paginationContainer" class="mt-3"></div>
    </div>
</section>
@endsection

@push('script')
<script>
$(document).ready(function() {
    // Load initial data
    fetchSignalData();

    // Apply filters
    $('#applyFilters').on('click', function() {
        fetchSignalData();
    });

    // Clear filters
    $('#clearFilters').on('click', function() {
        $('#dateFilter').val('');
        $('#symbolFilter').val('all');
        $('#signalFilter').val('all');
        $('#structureFilter').val('all');
        $('#haColorFilter').val('all');
        $('#searchFilter').val('');
        fetchSignalData();
    });

    // Reload data
    $('#reloadData').on('click', function() {
        fetchSignalData();
    });

    // Enter key search
    $('#searchFilter').on('keypress', function(e) {
        if (e.which == 13) {
            fetchSignalData();
        }
    });

    function fetchSignalData() {
        $('#dataTableContainer').html(`
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading signals...</p>
            </div>
        `);

        const filters = {
            _token: '{{ csrf_token() }}',
            date_filter: $('#dateFilter').val(),
            symbol_filter: $('#symbolFilter').val(),
            signal_filter: $('#signalFilter').val(),
            structure_filter: $('#structureFilter').val(),
            ha_color_filter: $('#haColorFilter').val(),
            search_term: $('#searchFilter').val()
        };

        $.post("{{ route('user.futures.signal.fetch') }}", filters, function(response) {
            // Update table
            $('#dataTableContainer').html(response.html);
            
            // Update pagination
            $('#paginationContainer').html(response.pagination);
            
            // Update summary cards
            updateSummaryCards(response.summary);
            
        }).fail(function() {
            $('#dataTableContainer').html(`
                <div class="text-center text-danger">
                    <i class="las la-exclamation-triangle la-3x"></i>
                    <p class="mt-2">Error loading data. Please try again.</p>
                </div>
            `);
        });
    }

    function updateSummaryCards(summary) {
        $('#totalRecords').text(summary.total);
        $('#buyCount').text(summary.buy);
        $('#sellCount').text(summary.sell);
        $('#noTradeCount').text(summary.no_trade);
        $('#longBuildupCount').text(summary.long_buildup);
        $('#shortBuildupCount').text(summary.short_buildup);
        $('#shortCoveringCount').text(summary.short_covering);
        $('#longUnwindingCount').text(summary.long_unwinding);
        $('#greenHACount').text(summary.green_ha);
        $('#redHACount').text(summary.red_ha);
    }

    // Handle pagination clicks
    $(document).on('click', '.pagination a', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if (url) {
            fetchPaginatedData(url);
        }
    });

    function fetchPaginatedData(url) {
        $('#dataTableContainer').html(`
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);

        const filters = {
            _token: '{{ csrf_token() }}',
            date_filter: $('#dateFilter').val(),
            symbol_filter: $('#symbolFilter').val(),
            signal_filter: $('#signalFilter').val(),
            structure_filter: $('#structureFilter').val(),
            ha_color_filter: $('#haColorFilter').val(),
            search_term: $('#searchFilter').val()
        };

        $.post(url, filters, function(response) {
            $('#dataTableContainer').html(response.html);
            $('#paginationContainer').html(response.pagination);
            updateSummaryCards(response.summary);
        });
    }
});
</script>
@endpush