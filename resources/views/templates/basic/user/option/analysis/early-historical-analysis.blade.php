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
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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

            .trend-badge {
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: bold;
                text-transform: uppercase;
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

            .positive {
                color: #28a745;
                font-weight: bold;
            }

            .negative {
                color: #dc3545;
                font-weight: bold;
            }

            .zero {
                color: #6c757d;
            }

            .trend-bullish-breakout {
                background: #b8f7b0; /* light green */
                color: #0b5137;
            }

            .trend-bearish-breakout {
                background: #f7b0b0; /* light red */
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
                        <input type="date" id="dateFilter" class="form-control">
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
            {{-- <div class="summary-cards" id="summaryCards">
                <div class="summary-card">
                    <h6 id="totalRecords" class="text-dark">0</h6>
                    <p>Total Records</p>
                </div>
                <div class="summary-card">
                    <h6 id="bullishMCount" class="text-success">0</h6>
                    <p>Mild Bullish</p>
                </div>
                <div class="summary-card">
                    <h6 id="bullishSCount" class="text-success">0</h6>
                    <p>Strong Bullish</p>
                </div>
                <div class="summary-card">
                    <h6 id="bearishMCount" class="text-danger">0</h6>
                    <p>Mild Bearish</p>
                </div>
                <div class="summary-card">
                    <h6 id="bearishSCount" class="text-danger">0</h6>
                    <p>Strong Bearish</p>
                </div>
                <div class="summary-card">
                    <h6 id="neutralCount" class="text-muted">0</h6>
                    <p>Neutral</p>
                </div>
            </div> --}}

            <!-- Data Table -->
            <div class="custom--card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Options Sentiment Data</span>
                    <button class="btn btn-sm btn--base reload-btn rounded-pill" id="reloadData">
                        <i class="las la-sync"></i> Reload
                    </button>
                </div>
                <div class="card-body" id="dataTableContainer">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading data...</p>
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

            // Initial load
            fetchHistoricalData();

            // Apply filters
            $('#applyFilters').on('click', function() {
                fetchHistoricalData();
            });

            // Clear filters
            $('#clearFilters').on('click', function() {
                $('#dateFilter').val('');
                $('#symbolFilter').val('all');
                $('#sentimentFilter').val('all');
                $('#strengthScoreFilter').val('');
                $('#searchFilter').val('');
                fetchHistoricalData();
            });

            // Reload button
            $('#reloadData').on('click', function() {
                fetchHistoricalData();
            });

            // Enter key triggers search
            $('#searchFilter').on('keypress', function(e) {
                if (e.which == 13) fetchHistoricalData();
            });

            // Fetch historical data
            function fetchHistoricalData(pageUrl = "{{ route('user.early-historical.analysis.fetch') }}") {
                $('#dataTableContainer').html(`
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading data...</p>
            </div>
        `);

                const filters = {
                    _token: '{{ csrf_token() }}',
                    date_filter: $('#dateFilter').val(),
                    symbol_filter: $('#symbolFilter').val(),
                    trade_type: $('#sentimentFilter').val(), // changed ID
                    search_term: $('#searchFilter').val(),
                    strength_score: $('#strengthScoreFilter').val(), // ✅ new
                };


                $.post(pageUrl, filters, function(response) {
                    if (response.status === 'success') {
                        let tableHtml = `
                <div class="table-responsive">
                    <table class="custom--table table mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Underlying</th>
                                <th>Avg CE OI % Chg</th>
                                <th>Avg PE OI % Chg</th>
                                <th>Sentiment</th>
                                <th>Pattern</th>
                                <th>Strength Score</th>
                                <th>Support Zone</th>
                                <th>Resistance Zone</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                        if (response.data.length > 0) {
                            response.data.forEach(row => {
                                let trendClass = getTrendClass(row.sentiment);
                                tableHtml += `
                        <tr>
                            <td>${row.date ?? '-'}</td>
                            <td>${row.underlying ?? '-'}</td>
                            <td class="${getColor(row.avg_ce_oi_change)}">${formatPercent(row.avg_ce_oi_change)}</td>
                            <td class="${getColor(row.avg_pe_oi_change)}">${formatPercent(row.avg_pe_oi_change)}</td>
                            <td><span class="trend-badge ${trendClass}">${row.sentiment}</span></td>
                            <td>${row.pattern ?? '-'}</td>
                            <td class="${getColor(row.strength_score)}">${row.strength_score.toFixed(2)}</td>
                            <td>${row.support_zone ?? '-'}</td>
                            <td>${row.resistance_zone ?? '-'}</td>
                        </tr>`;
                            });
                        } else {
                            tableHtml +=
                                `<tr><td colspan="9" class="text-center text-muted">No Data Found</td></tr>`;
                        }

                        tableHtml += '</tbody></table></div>';
                        $('#dataTableContainer').html(tableHtml);
                        updateSummaryCards(response.summary ?? {});
                        $('#paginationContainer').html(response.pagination ?? '');
                    } else {
                        $('#dataTableContainer').html(
                            `<div class="text-center text-danger">${response.message}</div>`);
                    }
                }).fail(() => {
                    $('#dataTableContainer').html(`
                <div class="text-center text-danger">
                    <i class="las la-exclamation-triangle la-3x"></i>
                    <p class="mt-2">Error loading data. Please try again.</p>
                </div>
            `);
                });
            }

            // Helpers
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

            function getColor(value) {
                if (value > 0) return 'positive';
                if (value < 0) return 'negative';
                return 'zero';
            }

            function formatPercent(value) {
                if (value == null) return '-';
                return (value > 0 ? '+' : '') + value.toFixed(2) + '%';
            }

            function updateSummaryCards(summary) {
                $('#totalRecords').text(summary.total || 0);
                $('#bullishMCount').text(summary.bullish_m || 0);
                $('#bullishSCount').text(summary.bullish_s || 0);
                $('#bearishMCount').text(summary.bearish_m || 0);
                $('#bearishSCount').text(summary.bearish_s || 0);
                $('#neutralCount').text(summary.neutral || 0);
            }

            // Pagination
            $(document).on('click', '.pagination a', function(e) {
                e.preventDefault();
                const pageUrl = $(this).attr('href');
                if (pageUrl) fetchHistoricalData(pageUrl);
            });
        });
    </script>
@endpush