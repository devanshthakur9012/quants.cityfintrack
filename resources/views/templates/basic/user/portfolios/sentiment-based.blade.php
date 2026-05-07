@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: left !important;
        padding: 5px !important;
        font-size: 11px;
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
    
    .filter-container {
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
        font-size: 10px;
    }
    
    .badge-pe {
        background-color: #dc3545;
        color: white;
        font-size: 10px;
    }
    
    .sentiment-badge {
        font-size: 9px;
        padding: 2px 6px;
    }
    
    .badge-strong-bullish {
        background-color: #28a745;
    }
    
    .badge-bullish-breakout {
        background-color: #20c997;
    }
    
    .badge-strong-bearish {
        background-color: #dc3545;
    }
    
    .badge-bearish-breakout {
        background-color: #fd7e14;
    }
    
    .badge-neutral {
        background-color: #6c757d;
    }
    
    .strength-score {
        font-weight: bold;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 10px;
    }
    
    .strength-high {
        background-color: #d4edda;
        color: #155724;
    }
    
    .strength-medium {
        background-color: #fff3cd;
        color: #856404;
    }
    
    .strength-low {
        background-color: #f8d7da;
        color: #721c24;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-3">{{ $pageTitle }}</h4>
            <div class="btn-box">
                <button class="btn btn-sm btn-danger">Positions : <span class="spinner-border spinner-border-sm"></span></button>
                <button class="btn btn-sm btn-primary">Investment : <span class="spinner-border spinner-border-sm"></span></button>
                <button class="btn btn-sm btn-light">P/L : <span class="spinner-border spinner-border-sm"></span></button>
                <button class="btn btn-sm btn-warning">P/L % : <span class="spinner-border spinner-border-sm"></span></button>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-container">
            <div class="row">
                <div class="col-md-2">
                    <label for="date_filter" class="form-label">Date:</label>
                    <input type="date" id="date_filter" class="form-control form-control-sm" />
                </div>
                <div class="col-md-3">
                    <label for="sentiment_filter" class="form-label">Sentiment:</label>
                    <select id="sentiment_filter" class="form-control form-control-sm">
                        <option value="Strong Bullish" selected>Strong Bullish</option>
                        <option value="Bullish Breakout Possible">Bullish Breakout Possible</option>
                        <option value="Strong Bearish">Strong Bearish</option>
                        <option value="Bearish Breakout Possible">Bearish Breakout Possible</option>
                        <option value="Neutral / Unwinding">Neutral / Unwinding</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="min_strength" class="form-label">Min Strength:</label>
                    <input type="number" id="min_strength" class="form-control form-control-sm" 
                           value="0" min="0" max="100" step="5" placeholder="0-100">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" id="apply_filter" class="btn btn-success btn-sm me-2">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                    <button type="button" id="clear_filter" class="btn btn-secondary btn-sm">
                        <i class="fas fa-redo"></i> Reset
                    </button>
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
                            <th>Sentiment</th>
                            <th>Pattern</th>
                            <th>Strength</th>
                            <th>CE OI%</th>
                            <th>PE OI%</th>
                            <th>LTP</th>
                            <th>H-LTP</th>
                            <th>H-Time</th>
                            <th>Lot</th>
                            <th>Buy ₹</th>
                            <th>Investment</th>
                            <th>Current</th>
                            <th>P/L</th>
                            <th>P/L %</th>
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
    let currentSentiment = 'Strong Bullish';
    let currentMinStrength = 0;
    
    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }
    
    function refreshPortfolioData(dateFilter = null, showLoading = true) {
        if (showLoading) {
            toggleLoading(true);
        }
        
        let ajaxData = {
            sentiment_filter: $('#sentiment_filter').val(),
            min_strength: $('#min_strength').val() || 0
        };
        
        if (dateFilter) {
            ajaxData.date_filter = dateFilter;
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
                        <td colspan="18" class="text-center text-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Error loading data. Please try again.
                        </td>
                    </tr>
                `);
            }
        });
    }
    
    function getSentimentBadgeClass(sentiment) {
        if (sentiment === 'Strong Bullish') return 'badge-strong-bullish';
        if (sentiment === 'Bullish Breakout Possible') return 'badge-bullish-breakout';
        if (sentiment === 'Strong Bearish') return 'badge-strong-bearish';
        if (sentiment === 'Bearish Breakout Possible') return 'badge-bearish-breakout';
        return 'badge-neutral';
    }
    
    function getStrengthClass(score) {
        if (score >= 50) return 'strength-high';
        if (score >= 25) return 'strength-medium';
        return 'strength-low';
    }
    
    function updatePortfolioDisplay(response) {
        let tbodyHtml = '';
        
        if (response.positions.length === 0) {
            tbodyHtml = `
                <tr>
                    <td colspan="18" class="text-center text-muted">
                        <i class="fas fa-inbox"></i> 
                        ${response.message || 'No positions found for the selected criteria.'}
                    </td>
                </tr>
            `;
        } else {
            response.positions.forEach(function (data) {
                let profitClass = data.profit > 0 ? 'profit-positive' : 
                                 data.profit < 0 ? 'profit-negative' : 'profit-neutral';
                
                let formattedHighestTime = "-";
                if (data.highest_time && data.highest_time !== "null") {
                    const [hour, minute] = data.highest_time.split(":");
                    const h = parseInt(hour);
                    const ampm = h >= 12 ? "PM" : "AM";
                    const hour12 = ((h + 11) % 12 + 1);
                    formattedHighestTime = `${hour12}:${minute} ${ampm}`;
                }
                
                let typeBadge = data.buildup_type.includes('CE') ? 
                    '<span class="badge badge-ce">CE</span>' : 
                    '<span class="badge badge-pe">PE</span>';
                
                let sentimentBadge = `<span class="badge sentiment-badge ${getSentimentBadgeClass(data.sentiment)}">${data.sentiment}</span>`;
                let strengthBadge = `<span class="strength-score ${getStrengthClass(data.strength_score)}">${data.strength_score}</span>`;
                
                tbodyHtml += `
                    <tr>
                        <td><small>${data.date}</small></td>
                        <td><strong>${data.underlying}</strong></td>
                        <td><small>${data.symbol_name}</small></td>
                        <td>${typeBadge}</td>
                        <td>${sentimentBadge}</td>
                        <td><small title="${data.pattern}">${data.pattern.substring(0, 25)}...</small></td>
                        <td>${strengthBadge}</td>
                        <td><small>${data.avg_ce_oi}%</small></td>
                        <td><small>${data.avg_pe_oi}%</small></td>
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
            currentDateFilter = $('#date_filter').val() || null;
            currentSentiment = $('#sentiment_filter').val();
            currentMinStrength = $('#min_strength').val();
            refreshPortfolioData(currentDateFilter);
            startAutoRefresh();
        });
        
        $('#clear_filter').click(function() {
            $('#date_filter').val('');
            $('#sentiment_filter').val('Strong Bullish');
            $('#min_strength').val('0');
            currentDateFilter = null;
            currentSentiment = 'Strong Bullish';
            currentMinStrength = 0;
            refreshPortfolioData();
            startAutoRefresh();
        });
        
        $('#sentiment_filter, #min_strength').change(function() {
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