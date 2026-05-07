@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: left !important;
        padding: 5px !important;
    }
    
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: #132d39;
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    
    .table-container {
        position: relative;
        min-height: 300px;
    }
    
    .date-filter-container {
        margin-bottom: 15px;
    }
    
    .spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-3">{{ $pageTitle }}</h4>
            <div class="btn-box">
                <!-- Will be populated via AJAX -->
                <button class="btn btn-sm btn-danger">No. of Positions : <span class="spinner-border spinner-border-sm"></span></button>
                <button class="btn btn-sm btn-primary">Total Investment : <span class="spinner-border spinner-border-sm"></span></button>
                <button class="btn btn-sm btn-light">Total Profit : <span class="spinner-border spinner-border-sm"></span></button>
                <button class="btn btn-sm btn-warning">Profit % : <span class="spinner-border spinner-border-sm"></span></button>
            </div>
        </div>
        
        <!-- Date Filter -->
        <div class="date-filter-container">
            <div class="row">
                <div class="col-md-3">
                    <label for="date_filter" class="form-label">Filter by Date:</label>
                    <input type="date" id="date_filter" class="form-control" />
                </div>
                <div class="col-md-3">
                    <label for="type_filter" class="form-label">Filter by Type:</label>
                    <select id="type_filter" class="form-control">
                        <option value="">All Types</option>
                        <option value="Long Unwinding">Long Unwinding</option>
                        <option value="Short Built Up">Short Built Up</option>
                        <option value="Long Built Up">Long Built Up</option>
                        <option value="Short Covering">Short Covering</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" id="apply_filter" class="btn btn-success btn-sm me-2">Apply Filter</button>
                    <button type="button" id="clear_filter" class="btn btn-secondary btn-sm">Clear Filter</button>
                </div>
            </div>
        </div>
        
        <!-- <div class="loading-overlay">
            <div class="spinner"></div>
        </div> -->
        <div class="table-container">
            <div class="loading-overlay" id="loading-overlay">
                <div class="spinner"></div>
            </div>
            
            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Symbol</th>
                            <th>LTP</th>
                            <th>H LTP</th>
                            <th>H Time</th>
                            <th>TXN Type</th>
                            <th>Lot Size</th>
                            <th>Buy Qty</th>
                            <th>Buy Price</th>
                            <th>Sell Qty</th>
                            <th>Sell Price</th>
                            <th>Total Value</th>
                            <th>Profit</th>
                            <th>Realised</th>
                            <th>Unrealised</th>
                            <th>Type</th>
                        </tr>
                    </thead>
                    <tbody id="portfolio-tbody">
                        <!-- Will be populated via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
    let refreshInterval;
    let currentDateFilter = null;
    
    // Show/hide loading overlay
    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }
    
    // Refresh portfolio data
    function refreshPortfolioData(dateFilter = null, showLoading = true) {
        if (showLoading) {
            toggleLoading(true);
        }
        
        let ajaxData = {};
        if (dateFilter) {
            ajaxData.date_filter = dateFilter;
        }

        let typeValue = $('#type_filter').val();
        if (typeValue) {
            ajaxData.type_filter = typeValue;
        }
        
        $.ajax({
            url: '{{ route("user.futures-direct-fetch") }}',
            type: 'GET',
            data: ajaxData,
            success: function (response) {
                updatePortfolioDisplay(response);
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error("Error fetching portfolio data:", error);
                toggleLoading(false);
                
                // Show error message in table
                $('#portfolio-tbody').html(`
                    <tr>
                        <td colspan="15" class="text-center text-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Error loading data. Please try again.
                        </td>
                    </tr>
                `);
            }
        });
    }
    
    // Update the display with new data
    function updatePortfolioDisplay(response) {
        // Update table body
        let tbodyHtml = '';
        if (response.positions.length === 0) {
            tbodyHtml = `
                <tr>
                    <td colspan="15" class="text-center text-muted">
                        <i class="fas fa-inbox"></i> 
                        No positions found for the selected criteria.
                    </td>
                </tr>
            `;
        } else {
            response.positions.forEach(function (data) {
                let profitClass = data.profit > 0 ? 'text-success' : 'text-danger';
                let formattedDate = new Date(data.created_at).toLocaleString();
                let formattedHighestTime = data.highest_time 
                    ? new Date(`1970-01-01T${data.highest_time}`).toLocaleTimeString('en-IN', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    })
                    : 'N/A';

                
                tbodyHtml += `
                    <tr>
                        <td>${formattedDate}</td>
                        <td><strong>${data.symbol_name}</strong></td>
                        <td>${data.latest_ltp}</td>
                        <td>${data.highest_ltp}</td>
                        <td><small>${formattedHighestTime}</small></td>
                        <td><span class="badge ${data.transaction_type === 'BUY' ? 'bg-primary' : 'bg-danger'}">${data.transaction_type}</span></td>
                        <td>${data.lot_size}</td>
                        <td>${data.buy_quantity || 0}</td>
                        <td>₹${parseFloat(data.buy_price || 0).toFixed(2)}</td>
                        <td>${data.sell_quantity || 0}</td>
                        <td>₹${parseFloat(data.sell_price || 0).toFixed(2)}</td>
                        <td><strong>₹${parseFloat(data.total_value).toFixed(2)}</strong></td>
                        <td class="${profitClass}"><strong>₹${parseFloat(data.profit).toFixed(2)}</strong></td>
                        <td>₹${parseFloat(data.realised_profit || 0).toFixed(2)}</td>
                        <td>₹${parseFloat(data.unrealised_profit || 0).toFixed(2)}</td>
                        <td>${data.buildup_type}</td>
                    </tr>
                `;
            });
        }
        
        $('#portfolio-tbody').html(tbodyHtml);
        
        // Update summary buttons
        let totalProfitClass = response.totalProfitRaw > 0 ? 'text-success' : 'text-danger';
        $('.btn-box').html(`
            <button class="btn btn-sm btn-danger">
                <i class="fas fa-chart-line"></i> No. of Positions : <strong>${response.noOfPositions}</strong>
            </button>
            <button class="btn btn-sm btn-primary">
                <i class="fas fa-rupee-sign"></i> Total Investment : <strong>₹${response.totalInvestment}</strong>
            </button>
            <button class="btn btn-sm btn-light">
                <i class="fas fa-chart-bar"></i> Total Profit : <span class="${totalProfitClass}"><strong>₹${response.totalProfit}</strong></span>
            </button>
            <button class="btn btn-sm btn-warning">
                <i class="fas fa-percentage"></i> Profit % : <strong>${response.profitPercentage}%</strong>
            </button>
        `);
    }
    
    // Start auto-refresh
    function startAutoRefresh() {
        // Clear existing interval
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        
        // Set new interval for 3 seconds
        refreshInterval = setInterval(function() {
            refreshPortfolioData(currentDateFilter, false); // Don't show loading for auto-refresh
        }, 10000);
    }
    
    // Stop auto-refresh
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }
    
    // Event handlers
    $(document).ready(function() {
        // Initial load
        refreshPortfolioData();
        startAutoRefresh();
        
        // Apply date filter
        $('#apply_filter').click(function() {
            let dateValue = $('#date_filter').val();
            if (dateValue) {
                currentDateFilter = dateValue;
                refreshPortfolioData(dateValue);
                // Restart auto-refresh with new filter
                startAutoRefresh();
            } else {
                alert('Please select a date first.');
            }
        });
        
        // Clear date filter
        $('#clear_filter').click(function() {
            $('#date_filter').val('');
            currentDateFilter = null;
            refreshPortfolioData();
            // Restart auto-refresh without filter
            startAutoRefresh();
        });
        
        // Stop auto-refresh when page is not visible
        $(document).on('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });
        
        // Stop auto-refresh when user leaves the page
        $(window).on('beforeunload', function() {
            stopAutoRefresh();
        });

        $('#type_filter').change(function() {
            refreshPortfolioData(currentDateFilter);
            startAutoRefresh();
        });
    });
    
    // Optional: Add keyboard shortcut for refresh
    $(document).keydown(function(e) {
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshPortfolioData(currentDateFilter);
        }
    });
</script>
@endpush