@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: left !important;
        padding: 8px !important;
        font-size: 0.85rem;
    }

    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(19, 45, 57, 0.9);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
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

    .badge-long-buildup { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important; 
        color: white !important; 
        padding: 6px 12px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }
    
    .badge-short-buildup { 
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%) !important; 
        color: white !important; 
        padding: 6px 12px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
    }
    
    .badge-long-unwinding { 
        background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important; 
        color: white !important; 
        padding: 6px 12px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }
    
    .badge-short-covering { 
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important; 
        color: white !important; 
        padding: 6px 12px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(23, 162, 184, 0.3);
    }

    .badge-no-clear { 
        background-color: #6c757d !important; 
        color: white !important; 
        padding: 6px 12px; 
        border-radius: 6px;
        font-weight: 600;
    }

    .badge-bullish {
        background-color: #28a745 !important;
        color: white !important;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
    }

    .badge-bearish {
        background-color: #dc3545 !important;
        color: white !important;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
    }

    .badge-profit {
        background-color: #28a745 !important;
        color: white !important;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
    }

    .badge-loss {
        background-color: #dc3545 !important;
        color: white !important;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 600;
    }

    .filter-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        color: white;
    }

    .filter-section label {
        color: white !important;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .filter-section .form-control {
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.9);
        color: #333;
    }

    .filter-section .form-control:focus {
        border-color: #fff;
        box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
    }

    .stats-box {
        background: #fff;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        border-left: 5px solid #3498db;
        margin-bottom: 15px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s;
    }

    .stats-box:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
    }

    .stats-box small {
        display: block;
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stats-box strong {
        display: block;
        font-size: 1.8rem;
        margin-top: 5px;
        font-weight: 700;
    }

    .stats-box.bullish-stats {
        border-left-color: #28a745;
    }

    .stats-box.bearish-stats {
        border-left-color: #dc3545;
    }

    .stats-box.neutral-stats {
        border-left-color: #6c757d;
    }

    .stats-box.profit-stats {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-left-color: #fff;
    }

    .stats-box.profit-stats small {
        color: rgba(255,255,255,0.9);
    }

    .profit-section {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
        color: white;
    }

    .profit-section label {
        color: white !important;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .profit-section .alert {
        background: rgba(255, 255, 255, 0.2);
        border: 2px solid rgba(255, 255, 255, 0.3);
        color: white;
    }

    .no-data-message {
        padding: 60px 20px;
        text-align: center;
        color: #6c757d;
    }

    .no-data-message i {
        font-size: 4rem;
        margin-bottom: 20px;
        display: block;
        opacity: 0.5;
    }

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .page-header h4 {
        margin: 0;
        font-weight: 700;
        font-size: 1.8rem;
    }

    .page-header p {
        margin: 10px 0 0 0;
        opacity: 0.9;
    }

    .price-positive {
        color: #28a745;
        font-weight: 700;
    }

    .price-negative {
        color: #dc3545;
        font-weight: 700;
    }

    .table-responsive {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .table thead {
        background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
        color: white;
    }

    .table tbody tr:hover {
        background-color: rgba(102, 126, 234, 0.05);
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}</h4>
                    <p>OI-Based Market Positioning Analysis: ATM-1, ATM, ATM+1 Strikes with P/L Calculation</p>
                </div>
                <div>
                    <a href="{{ route('symbols.analysis') }}" class="btn btn-light me-2">
                        <i class="fas fa-chart-line"></i> Price Analysis
                    </a>
                    <a href="{{ route('symbols.one-percent') }}" class="btn btn-light">
                        <i class="fas fa-percentage"></i> 1% Move
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row mb-3">
                <div class="col-md-2">
                    <label for="from_date"><i class="fas fa-calendar-alt"></i> From Date:</label>
                    <input type="date" id="from_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="to_date"><i class="fas fa-calendar-alt"></i> To Date:</label>
                    <input type="date" id="to_date" class="form-control" 
                           value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-2">
                    <label for="lookback_period"><i class="fas fa-clock"></i> Lookback Period:</label>
                    <select id="lookback_period" class="form-control">
                        <option value="3" selected>3 candles</option>
                        <option value="5">5 candles</option>
                        <option value="7">7 candles</option>
                        <option value="10">10 candles</option>
                    </select>
                    <small style="color: rgba(255,255,255,0.8);">For OI trend detection</small>
                </div>

                <div class="col-md-4">
                    <label for="symbol_filter"><i class="fas fa-filter"></i> Symbols (Optional):</label>
                    <select id="symbol_filter" class="form-control" multiple size="3">
                        @foreach($underlyings as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                    <small style="color: rgba(255,255,255,0.8);">Leave empty for all symbols</small>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12 text-center">
                    <button type="button" id="run_analysis" class="btn btn-light btn-lg me-3" style="min-width: 180px;">
                        <i class="fas fa-play-circle"></i> Run Analysis
                    </button>
                    <button type="button" id="export_csv" class="btn btn-outline-light btn-lg me-3" style="min-width: 180px; display: none;">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-outline-light btn-lg" style="min-width: 180px;">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Profit Calculation Section (shown after analysis) -->
        <div class="profit-section" id="profit-section" style="display: none;">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <h5 style="margin-bottom: 15px;"><i class="fas fa-calculator"></i> Profit/Loss Calculation</h5>
                    <p style="font-size: 0.9rem; opacity: 0.9;">Calculate theoretical P/L based on OI signals (Bullish/Bearish pressure only)</p>
                </div>
                <div class="col-md-3">
                    <button type="button" id="calculate_profit" class="btn btn-light btn-lg" style="min-width: 200px;">
                        <i class="fas fa-calculator"></i> Calculate P/L
                    </button>
                </div>
                <div class="col-md-5">
                    <div class="alert mb-0">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Trades ATM options based on market bias | Entry: 9:15 AM | Exit: 3:25 PM
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Total Symbols</small>
                    <strong id="total_symbols" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box bullish-stats">
                    <small>Bullish</small>
                    <strong id="bullish_count" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box bearish-stats">
                    <small>Bearish</small>
                    <strong id="bearish_count" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box neutral-stats">
                    <small>Neutral/Range</small>
                    <strong id="neutral_count" style="color: #6c757d;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Investment</small>
                    <strong id="total_investment" style="color: #17a2b8;">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box profit-stats">
                    <small>Total P/L</small>
                    <strong id="total_profit">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box profit-stats">
                    <small>Win Rate</small>
                    <strong id="win_rate">0%</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>ROI %</small>
                    <strong id="roi_percent" style="color: #ffc107;">0%</strong>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div style="position: relative; min-height: 400px;">
            <div class="loading-overlay" id="loading-overlay" style="display: none;">
                <div class="spinner"></div>
            </div>

            <div class="table-responsive">
                <table class="table custom--table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Symbol</th>
                            <th>Market Bias</th>
                            <th>CE Score</th>
                            <th>PE Score</th>
                            <th>ATM-1 CE</th>
                            <th>ATM CE</th>
                            <th>ATM+1 CE</th>
                            <th>ATM-1 PE</th>
                            <th>ATM PE</th>
                            <th>ATM+1 PE</th>
                            <th>Trade</th>
                            <th>Buy Price</th>
                            <th>Sell Price</th>
                            <th>P/L</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="15" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-chart-bar"></i>
                                    <p style="font-size: 1.1rem;">Select date range and click <strong>"Run Analysis"</strong></p>
                                    <small style="display: block; margin-top: 10px; color: #999;">
                                        Pure OI-based analysis - no price or technical indicators
                                    </small>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
    let analysisData = [];
    let profitData = [];

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function runAnalysis() {
        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        const selectedSymbols = $('#symbol_filter').val() || [];
        const lookback = $('#lookback_period').val();

        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }

        toggleLoading(true);
        analysisData = [];
        profitData = [];

        $.ajax({
            url: '{{ route("options.oi-analysis-fetch") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                lookback: lookback
            },
            success: function (response) {
                console.log('Analysis Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    analysisData = response.data;
                    displayAnalysisTable();
                    updateBasicStatistics();
                    $('#profit-section').show();
                    $('#export_csv').show();
                } else {
                    $('#analysis-tbody').html(`
                        <tr>
                            <td colspan="15" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                                    <p class="text-info">${response.message || 'No data found'}</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                    $('#profit-section').hide();
                    $('#export_csv').hide();
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Analysis Error:', error);
                $('#analysis-tbody').html(`
                    <tr>
                        <td colspan="15" class="text-center py-5">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error running analysis</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function displayAnalysisTable() {
        if (!analysisData || analysisData.length === 0) {
            return;
        }

        let html = '';
        
        analysisData.forEach(function (row, index) {
            const biasClass = getBiasClass(row.market_bias);
            const biasText = row.market_bias.replace(/_/g, ' ');

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong style="color: #667eea;">${row.underlying}</strong></td>
                    <td><span class="badge ${biasClass}">${biasText}</span></td>
                    <td><strong class="${row.ce_analysis.net_score > 0 ? 'price-positive' : (row.ce_analysis.net_score < 0 ? 'price-negative' : '')}">${row.ce_analysis.net_score}</strong></td>
                    <td><strong class="${row.pe_analysis.net_score > 0 ? 'price-positive' : (row.pe_analysis.net_score < 0 ? 'price-negative' : '')}">${row.pe_analysis.net_score}</strong></td>
                    <td>${getBadgeHTML(row.ce_analysis.strike_wise['ATM-1'])}</td>
                    <td>${getBadgeHTML(row.ce_analysis.strike_wise['ATM'])}</td>
                    <td>${getBadgeHTML(row.ce_analysis.strike_wise['ATM+1'])}</td>
                    <td>${getBadgeHTML(row.pe_analysis.strike_wise['ATM-1'])}</td>
                    <td>${getBadgeHTML(row.pe_analysis.strike_wise['ATM'])}</td>
                    <td>${getBadgeHTML(row.pe_analysis.strike_wise['ATM+1'])}</td>
                    <td colspan="4" class="text-center text-muted">
                        <em>Click "Calculate P/L" to fetch prices</em>
                    </td>
                </tr>
            `;
        });

        $('#analysis-tbody').html(html);
    }

    function calculateProfit() {
        if (!analysisData || analysisData.length === 0) {
            alert('No analysis data available. Please run analysis first.');
            return;
        }

        const fromDate = $('#from_date').val();
        const toDate = $('#to_date').val();
        toggleLoading(true);

        $.ajax({
            url: '{{ route("options.oi-profit") }}',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                signals: analysisData,
                from_date: fromDate,
                to_date: toDate
            }),
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            success: function (response) {
                console.log('Profit Response:', response);
                
                if (response.success && response.data) {
                    profitData = response.data;
                    displayProfitTable();
                    updateProfitStatistics(response.summary);
                } else {
                    alert(response.message || 'Error calculating profit');
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Profit Calculation Error:', error);
                alert('Error calculating profit. Check if option data is available.');
                toggleLoading(false);
            }
        });
    }

    function displayProfitTable() {
        if (!profitData || profitData.length === 0) {
            return;
        }

        let html = '';
        let displayIndex = 1;
        
        analysisData.forEach(function (signal, index) {
            const profit = profitData.find(p => p.underlying === signal.underlying);
            const biasClass = getBiasClass(signal.market_bias);
            const biasText = signal.market_bias.replace(/_/g, ' ');

            if (!profit) {
                // Show signal without profit data
                html += `
                    <tr>
                        <td><strong>${displayIndex++}</strong></td>
                        <td><strong style="color: #667eea;">${signal.underlying}</strong></td>
                        <td><span class="badge ${biasClass}">${biasText}</span></td>
                        <td><strong class="${signal.ce_analysis.net_score > 0 ? 'price-positive' : (signal.ce_analysis.net_score < 0 ? 'price-negative' : '')}">${signal.ce_analysis.net_score}</strong></td>
                        <td><strong class="${signal.pe_analysis.net_score > 0 ? 'price-positive' : (signal.pe_analysis.net_score < 0 ? 'price-negative' : '')}">${signal.pe_analysis.net_score}</strong></td>
                        <td>${getBadgeHTML(signal.ce_analysis.strike_wise['ATM-1'])}</td>
                        <td>${getBadgeHTML(signal.ce_analysis.strike_wise['ATM'])}</td>
                        <td>${getBadgeHTML(signal.ce_analysis.strike_wise['ATM+1'])}</td>
                        <td>${getBadgeHTML(signal.pe_analysis.strike_wise['ATM-1'])}</td>
                        <td>${getBadgeHTML(signal.pe_analysis.strike_wise['ATM'])}</td>
                        <td>${getBadgeHTML(signal.pe_analysis.strike_wise['ATM+1'])}</td>
                        <td colspan="4" class="text-center text-muted"><em>N/A (Neutral bias)</em></td>
                    </tr>
                `;
                return;
            }

            const profitClass = profit.profit_loss >= 0 ? 'badge-profit' : 'badge-loss';
            const profitSign = profit.profit_loss >= 0 ? '+' : '';

            html += `
                <tr>
                    <td><strong>${displayIndex++}</strong></td>
                    <td><strong style="color: #667eea;">${signal.underlying}</strong></td>
                    <td><span class="badge ${biasClass}">${biasText}</span></td>
                    <td><strong class="${signal.ce_analysis.net_score > 0 ? 'price-positive' : (signal.ce_analysis.net_score < 0 ? 'price-negative' : '')}">${signal.ce_analysis.net_score}</strong></td>
                    <td><strong class="${signal.pe_analysis.net_score > 0 ? 'price-positive' : (signal.pe_analysis.net_score < 0 ? 'price-negative' : '')}">${signal.pe_analysis.net_score}</strong></td>
                    <td>${getBadgeHTML(signal.ce_analysis.strike_wise['ATM-1'])}</td>
                    <td>${getBadgeHTML(signal.ce_analysis.strike_wise['ATM'])}</td>
                    <td>${getBadgeHTML(signal.ce_analysis.strike_wise['ATM+1'])}</td>
                    <td>${getBadgeHTML(signal.pe_analysis.strike_wise['ATM-1'])}</td>
                    <td>${getBadgeHTML(signal.pe_analysis.strike_wise['ATM'])}</td>
                    <td>${getBadgeHTML(signal.pe_analysis.strike_wise['ATM+1'])}</td>
                    <td><strong>${profit.option_symbol}</strong><br><small>${profit.option_type}</small></td>
                    <td><strong>₹${profit.buy_price}</strong></td>
                    <td><strong>₹${profit.sell_price}</strong></td>
                    <td>
                        <span class="badge ${profitClass}">${profitSign}₹${profit.profit_loss}</span><br>
                        <small class="${profitClass}" style="font-weight: 600;">${profitSign}${profit.return_percent}%</small>
                    </td>
                </tr>
            `;
        });

        $('#analysis-tbody').html(html);
    }

    function getBiasClass(bias) {
        if (bias.includes('BULLISH')) return 'badge-bullish';
        if (bias.includes('BEARISH')) return 'badge-bearish';
        return 'badge-no-clear';
    }

    function getBadgeHTML(signal) {
        // Handle undefined or null signals
        if (!signal) {
            return '<span class="badge badge-no-clear" style="font-size: 0.75rem;">N/A</span>';
        }
        
        const badgeClasses = {
            'LONG_BUILDUP': 'badge-long-buildup',
            'SHORT_BUILDUP': 'badge-short-buildup',
            'LONG_UNWINDING': 'badge-long-unwinding',
            'SHORT_COVERING': 'badge-short-covering',
            'NO_CLEAR_BUILDUP': 'badge-no-clear'
        };

        const badgeClass = badgeClasses[signal] || 'badge-no-clear';
        const displayText = signal.replace(/_/g, ' ');

        return `<span class="badge ${badgeClass}" style="font-size: 0.75rem;">${displayText}</span>`;
    }

    function updateBasicStatistics() {
        if (!analysisData || analysisData.length === 0) {
            resetStatistics();
            return;
        }

        const totalSymbols = analysisData.length;
        const bullishCount = analysisData.filter(r => r.market_bias.includes('BULLISH')).length;
        const bearishCount = analysisData.filter(r => r.market_bias.includes('BEARISH')).length;
        const neutralCount = totalSymbols - bullishCount - bearishCount;

        $('#total_symbols').text(totalSymbols);
        $('#bullish_count').text(bullishCount);
        $('#bearish_count').text(bearishCount);
        $('#neutral_count').text(neutralCount);
    }

    function updateProfitStatistics(summary) {
        if (!summary) return;

        $('#total_investment').text('₹' + summary.total_investment.toLocaleString('en-IN'));
        $('#total_profit').text('₹' + summary.total_profit_loss.toLocaleString('en-IN'));
        $('#win_rate').text(summary.win_rate + '%');
        $('#roi_percent').text(summary.roi_percent + '%');
    }

    function resetStatistics() {
        $('#total_symbols').text('0');
        $('#bullish_count').text('0');
        $('#bearish_count').text('0');
        $('#neutral_count').text('0');
        $('#total_investment').text('₹0');
        $('#total_profit').text('₹0');
        $('#win_rate').text('0%');
        $('#roi_percent').text('0%');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter').val('');
        $('#lookback_period').val('3');
        
        analysisData = [];
        profitData = [];
        $('#analysis-tbody').html(`
            <tr>
                <td colspan="15" class="text-center py-5">
                    <div class="no-data-message">
                        <i class="fas fa-chart-bar"></i>
                        <p style="font-size: 1.1rem;">Select date range and click <strong>"Run Analysis"</strong></p>
                    </div>
                </td>
            </tr>
        `);
        resetStatistics();
        $('#profit-section').hide();
        $('#export_csv').hide();
    }

    function exportCSV() {
        if (!analysisData || analysisData.length === 0) {
            alert('No data to export');
            return;
        }

        $.ajax({
            url: '{{ route("options.oi-export") }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                data: JSON.stringify(analysisData)
            },
            xhrFields: {
                responseType: 'blob'
            },
            success: function (blob) {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'oi_analysis_' + new Date().toISOString().slice(0, 10) + '.csv';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            },
            error: function () {
                alert('Error exporting CSV');
            }
        });
    }

    $(document).ready(function () {
        $('#run_analysis').click(function () {
            runAnalysis();
        });

        $('#calculate_profit').click(function () {
            calculateProfit();
        });

        $('#export_csv').click(function () {
            exportCSV();
        });

        $('#reset_filters').click(function () {
            resetFilters();
        });
    });
</script>
@endpush