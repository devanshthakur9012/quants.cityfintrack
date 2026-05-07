@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: left !important;
        padding: 5px !important;
        font-size: 12px;
    }
    
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(19, 45, 57, 0.95);
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
    
    .profit-positive {
        color: #28a745 !important;
        font-weight: bold;
    }
    
    .profit-negative {
        color: #dc3545 !important;
        font-weight: bold;
    }
    
    .profit-neutral {
        color: #6c757d !important;
    }
    
    .badge-ce {
        background-color: #28a745;
        color: white;
    }
    
    .badge-pe {
        background-color: #dc3545;
        color: white;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-3">{{ $pageTitle }}</h4>
            <div class="btn-box">
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
                        <option value="Strong Bullish" selected>Strong Bullish</option>
                        <option value="Mild Bullish">Mild Bullish</option>
                        <option value="Strong Bearish">Strong Bearish</option>
                        <option value="Mild Bearish">Mild Bearish</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" id="apply_filter" class="btn btn-success btn-sm me-2">Apply Filter</button>
                    <button type="button" id="clear_filter" class="btn btn-secondary btn-sm">Clear Filter</button>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <div class="loading-overlay" id="loading-overlay">
                <div class="spinner"></div>
            </div>
            
            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Underlying</th>
                            <th>Symbol</th>
                            <th>Type</th>
                            <th>LTP</th>
                            <th>H LTP</th>
                            <th>H Time</th>
                            <th>Lot Size</th>
                            <th>Buy Price</th>
                            <th>Investment</th>
                            <th>Current Value</th>
                            <th>Profit/Loss</th>
                            <th>P/L %</th>
                            <th>OI Chg %</th>
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
    
    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }
    
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
            url: '{{ $route }}',
            type: 'GET',
            data: ajaxData,
            success: function (response) {
                updatePortfolioDisplay(response);
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error("Error fetching portfolio data:", error);
                toggleLoading(false);
                
                $('#portfolio-tbody').html(`
                    <tr>
                        <td colspan="14" class="text-center text-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Error loading data. Please try again.
                        </td>
                    </tr>
                `);
            }
        });
    }
    
    function updatePortfolioDisplay(response) {
        let tbodyHtml = '';
        
        if (response.positions.length === 0) {
            tbodyHtml = `
                <tr>
                    <td colspan="14" class="text-center text-muted">
                        <i class="fas fa-inbox"></i> 
                        No valid positions found for the selected criteria.
                    </td>
                </tr>
            `;
        } else {
            response.positions.forEach(function (data) {
                // Determine profit class based on actual profit value
                let profitClass = 'profit-neutral';
                if (data.profit > 0) {
                    profitClass = 'profit-positive';
                } else if (data.profit < 0) {
                    profitClass = 'profit-negative';
                }
                
                // Format highest time
                let formattedHighestTime = "-";
                if (data.highest_time && data.highest_time !== "null") {
                    const [hour, minute] = data.highest_time.split(":");
                    const h = parseInt(hour);
                    const ampm = h >= 12 ? "PM" : "AM";
                    const hour12 = ((h + 11) % 12 + 1);
                    formattedHighestTime = `${hour12}:${minute} ${ampm}`;
                }
                
                // Option type badge
                let typeBadge = data.buildup_type.includes('CE') ? 
                    '<span class="badge badge-ce">CE</span>' : 
                    '<span class="badge badge-pe">PE</span>';
                
                tbodyHtml += `
                    <tr>
                        <td><small>${data.date}</small></td>
                        <td><strong>${data.underlying}</strong></td>
                        <td><small>${data.symbol_name}</small></td>
                        <td>${typeBadge}</td>
                        <td>₹${parseFloat(data.ltp).toFixed(2)}</td>
                        <td>₹${parseFloat(data.highest_ltp).toFixed(2)}</td>
                        <td><small>${formattedHighestTime}</small></td>
                        <td>${data.lot_size}</td>
                        <td>₹${parseFloat(data.buy_price).toFixed(2)}</td>
                        <td><strong>₹${parseFloat(data.total_value).toFixed(2)}</strong></td>
                        <td><strong>₹${parseFloat(data.current_value).toFixed(2)}</strong></td>
                        <td class="${profitClass}">
                            <strong>₹${parseFloat(data.profit).toFixed(2)}</strong>
                        </td>
                        <td class="${profitClass}">
                            <strong>${data.profit_percentage}%</strong>
                        </td>
                        <td><small>${parseFloat(data.oi_change_pct).toFixed(2)}%</small></td>
                    </tr>
                `;
            });
        }
        
        $('#portfolio-tbody').html(tbodyHtml);
        
        // Update summary buttons
        let totalProfitClass = response.totalProfitRaw > 0 ? 'profit-positive' : 
                               response.totalProfitRaw < 0 ? 'profit-negative' : 'profit-neutral';
        
        $('.btn-box').html(`
            <button class="btn btn-sm btn-danger">
                <i class="fas fa-chart-line"></i> Positions : <strong>${response.noOfPositions}</strong>
            </button>
            <button class="btn btn-sm btn-primary">
                <i class="fas fa-rupee-sign"></i> Investment : <strong>₹${response.totalInvestment}</strong>
            </button>
            <button class="btn btn-sm btn-light">
                <i class="fas fa-chart-bar"></i> P/L : <span class="${totalProfitClass}"><strong>₹${response.totalProfit}</strong></span>
            </button>
            <button class="btn btn-sm btn-warning">
                <i class="fas fa-percentage"></i> P/L % : <strong>${response.profitPercentage}%</strong>
            </button>
        `);
    }
    
    function startAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
        }
        refreshInterval = setInterval(function() {
            refreshPortfolioData(currentDateFilter, false);
        }, 10000);
    }
    
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }
    
    $(document).ready(function() {
        refreshPortfolioData();
        startAutoRefresh();
        
        $('#apply_filter').click(function() {
            let dateValue = $('#date_filter').val();
            currentDateFilter = dateValue || null;
            refreshPortfolioData(currentDateFilter);
            startAutoRefresh();
        });
        
        $('#clear_filter').click(function() {
            $('#date_filter').val('');
            $('#type_filter').val('Strong Bullish');
            currentDateFilter = null;
            refreshPortfolioData();
            startAutoRefresh();
        });
        
        $('#type_filter').change(function() {
            refreshPortfolioData(currentDateFilter);
            startAutoRefresh();
        });
        
        $(document).on('visibilitychange', function() {
            if (document.hidden) {
                stopAutoRefresh();
            } else {
                startAutoRefresh();
            }
        });
        
        $(window).on('beforeunload', function() {
            stopAutoRefresh();
        });
    });
    
    $(document).keydown(function(e) {
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            refreshPortfolioData(currentDateFilter);
        }
    });
</script>
@endpush