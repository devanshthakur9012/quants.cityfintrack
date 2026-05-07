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

    .badge-buy-ce { 
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important; 
        color: white !important; 
        padding: 5px 10px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }
    
    .badge-buy-pe { 
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%) !important; 
        color: white !important; 
        padding: 5px 10px; 
        border-radius: 6px;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    .badge-reversal {
        background-color: #17a2b8 !important;
        color: white !important;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-breakout {
        background-color: #ffc107 !important;
        color: #333 !important;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 700;
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

    .btn-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        font-weight: 600;
        padding: 10px 20px;
        border-radius: 8px;
        transition: all 0.3s;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        color: white;
    }

    .level-badge {
        background-color: #6c757d;
        color: white;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        margin: 2px;
        display: inline-block;
    }

    .level-h3 { background-color: #dc3545; }
    .level-h4 { background-color: #fd7e14; }
    .level-l3 { background-color: #28a745; }
    .level-l4 { background-color: #20c997; }

    .signal-bullish {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 3px 6px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
    }

    .signal-bearish {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        color: white;
        padding: 3px 6px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }

    .signal-neutral {
        background-color: #6c757d;
        color: white;
        padding: 3px 6px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 10px;
        display: inline-block;
    }

    .exit-time-selector {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
        color: white;
    }

    .exit-time-selector label {
        color: white !important;
        font-weight: 600;
    }

    .exit-time-selector .form-control {
        border: 2px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.9);
    }

    .highest-price-badge {
        background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
        color: #333;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 700;
        display: inline-block;
        margin-top: 4px;
    }

    .custom--table thead th, .custom--table tbody td{
        font-size: 10px !important;
    }

    .strategy-info {
        background: rgba(255, 255, 255, 0.1);
        padding: 15px;
        border-radius: 8px;
        margin-top: 15px;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .strategy-info h6 {
        color: white;
        margin-bottom: 10px;
        font-weight: 700;
    }

    .strategy-info ul {
        margin: 0;
        padding-left: 20px;
        color: rgba(255, 255, 255, 0.9);
    }

    .strategy-info ul li {
        margin-bottom: 5px;
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
                    <p>Trade reversals & breakouts using Camarilla levels (H3, H4, L3, L4) with OI filtering</p>
                </div>
                <div>
                    <a href="{{ route('symbols.one-percent') }}" class="btn btn-light me-2">
                        <i class="fas fa-percentage"></i> 1% Move
                    </a>
                    <a href="{{ route('symbols.analysis') }}" class="btn btn-light me-2">
                        <i class="fas fa-chart-line"></i> Technical
                    </a>
                    <a href="{{ route('symbols.backtesting') }}" class="btn btn-light">
                        <i class="fas fa-history"></i> Backtest
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
                    <label for="option_series_filter"><i class="fas fa-layer-group"></i> Option Series:</label>
                    <select id="option_series_filter" class="form-control">
                        <option value="current" selected>Current Series</option>
                        <option value="next">Next Series</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="symbol_filter"><i class="fas fa-filter"></i> Symbols (Optional):</label>
                    <select id="symbol_filter" class="form-control" multiple size="3">
                        @foreach($monitoredSymbols as $symbol)
                            <option value="{{ $symbol->trading_symbol }}">{{ $symbol->trading_symbol }}</option>
                        @endforeach
                    </select>
                    <small style="color: rgba(255,255,255,0.8);">Leave empty for all symbols</small>
                </div>
            </div>

            <!-- Strategy Info -->
            <div class="strategy-info">
                <h6><i class="fas fa-info-circle"></i> Camarilla Strategy Rules:</h6>
                <ul style="font-size: 0.85rem;">
                    <li><strong>REVERSAL:</strong> L3 rejection (60%+ wick) → BUY CE | H3 rejection → BUY PE</li>
                    <li><strong>BREAKOUT:</strong> Close above H4 → BUY CE | Close below L4 → BUY PE</li>
                    <li><strong>OI FILTER:</strong> CE signal needs CE_BULLISH | PE signal needs PE_BULLISH</li>
                    <li><strong>Levels:</strong> Calculated from previous day's OHLC (Day timeframe)</li>
                </ul>
            </div>

            <div class="row mt-3">
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

        <!-- Exit Time Selector -->
        <div class="exit-time-selector" id="exit-time-section" style="display: none;">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label for="exit_time_selector"><i class="fas fa-clock"></i> Select Exit Time:</label>
                    <select id="exit_time_selector" class="form-control">
                        <option value="15:15">3:15 PM (15:15)</option>
                        <option value="15:20">3:20 PM (15:20)</option>
                        <option value="15:25">3:25 PM (15:25)</option>
                        <option value="15:30" selected>3:30 PM (15:30)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="button" id="calculate_profit" class="btn btn-light btn-lg" style="min-width: 200px;">
                        <i class="fas fa-calculator"></i> Calculate P/L
                    </button>
                </div>
                <div class="col-md-6">
                    <div class="alert mb-0" style="background: rgba(255, 255, 255, 0.2); border: 2px solid rgba(255, 255, 255, 0.3); color: white;">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Buy @ Signal Time | Sell @ Exit Time
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Total Signals</small>
                    <strong id="total_signals" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>CE Signals</small>
                    <strong id="ce_signals" style="color: #28a745;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="border-left-color: #dc3545;">
                    <small>PE Signals</small>
                    <strong id="pe_signals" style="color: #dc3545;">0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Investment</small>
                    <strong id="total_investment" class="text-dark">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Exit P/L</small>
                    <strong id="total_profit" class="text-dark">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <small style="color: white;">Best P/L</small>
                    <strong id="total_highest_profit">₹0</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Win Rate</small>
                    <strong id="win_rate" style="color: #17a2b8;">0%</strong>
                </div>
            </div>
            <div class="col-md-2">
                <div class="stats-box">
                    <small>Exit ROI</small>
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
                            <th>Date</th>
                            <th>Time</th>
                            <th>Symbol</th>
                            <th>Signal</th>
                            <th>Type</th>
                            <th>Level</th>
                            <th>Price</th>
                            <th>Levels</th>
                            <th>FUT</th>
                            <th>CE</th>
                            <th>PE</th>
                            <th>Option</th>
                            <th>Buy</th>
                            <th>Sell</th>
                            <th>Best</th>
                            <th>Qty</th>
                            <th>Investment</th>
                            <th>Exit P/L</th>
                            <th>Best P/L</th>
                            <th>Exit ROI</th>
                        </tr>
                    </thead>
                    <tbody id="analysis-tbody">
                        <tr>
                            <td colspan="21" class="text-center py-5">
                                <div style="padding: 60px 20px;">
                                    <i class="fas fa-chart-bar" style="font-size: 4rem; color: #6c757d; opacity: 0.5;"></i>
                                    <p style="font-size: 1.1rem; margin-top: 20px;">Select date range and click <strong>"Run Analysis"</strong></p>
                                    <small style="color: #999;">Camarilla levels calculated from previous day's OHLC</small>
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
        const optionSeries = $('#option_series_filter').val();

        if (!fromDate || !toDate) {
            alert('Please select both From and To dates');
            return;
        }

        toggleLoading(true);
        analysisData = [];
        profitData = [];

        $.ajax({
            url: '{{ route("camarilla.analyze") }}',
            type: 'GET',
            data: {
                from_date: fromDate,
                to_date: toDate,
                symbols: selectedSymbols,
                option_series: optionSeries
            },
            success: function (response) {
                console.log('Camarilla Analysis Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    analysisData = response.data;
                    displayAnalysisTable();
                    updateBasicStatistics();
                    $('#exit-time-section').show();
                    $('#export_csv').show();
                } else {
                    $('#analysis-tbody').html(`
                        <tr>
                            <td colspan="21" class="text-center py-5">
                                <div style="padding: 60px 20px;">
                                    <i class="fas fa-info-circle" style="font-size: 3rem; color: #17a2b8;"></i>
                                    <p class="text-info" style="margin-top: 20px;">${response.message || 'No Camarilla signals found'}</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                    $('#exit-time-section').hide();
                    $('#export_csv').hide();
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Camarilla Analysis Error:', error);
                $('#analysis-tbody').html(`
                    <tr>
                        <td colspan="21" class="text-center py-5">
                            <div style="padding: 60px 20px;">
                                <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: #dc3545;"></i>
                                <p class="text-danger" style="margin-top: 20px;">Error running analysis</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function formatSignal(signal) {
        if (!signal || signal === 'N/A' || signal === 'NEUTRAL') {
            return '<span class="signal-neutral">NEUTRAL</span>';
        }

        let cssClass = signal === 'BULLISH' ? 'signal-bullish' : 'signal-bearish';
        return `<span class="${cssClass}">${signal}</span>`;
    }

    function displayAnalysisTable() {
        if (!analysisData || analysisData.length === 0) {
            return;
        }

        let html = '';
        
        analysisData.forEach(function (row, index) {
            let signalClass = row.signal === 'BUY_CE' ? 'badge-buy-ce' : 'badge-buy-pe';
            let signalText = row.signal === 'BUY_CE' ? 'CE ▲' : 'PE ▼';
            let typeClass = row.signal_type === 'REVERSAL' ? 'badge-reversal' : 'badge-breakout';
            let levelClass = `level-${row.level.toLowerCase()}`;
            const timeOnly = row.signal_time ? row.signal_time.split(' ')[1].substring(0, 5) : '-';

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${row.date}</strong></td>
                    <td>${timeOnly}</td>
                    <td><strong style="color: #667eea;">${row.symbol}</strong></td>
                    <td><span class="badge ${signalClass}">${signalText}</span></td>
                    <td><span class="badge ${typeClass}">${row.signal_type}</span></td>
                    <td><span class="level-badge ${levelClass}">${row.level}</span></td>
                    <td><strong>₹${row.signal_price}</strong></td>
                    <td>
                        <span class="level-badge level-h4">H4: ${row.h4}</span>
                        <span class="level-badge level-h3">H3: ${row.h3}</span><br>
                        <span class="level-badge level-l3">L3: ${row.l3}</span>
                        <span class="level-badge level-l4">L4: ${row.l4}</span>
                    </td>
                    <td>${formatSignal(row.fut_signal)}</td>
                    <td>${formatSignal(row.ce_signal)}</td>
                    <td>${formatSignal(row.pe_signal)}</td>
                    <td><strong>${row.option_symbol || '-'}</strong></td>
                    <td colspan="8" class="text-center text-muted">
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

        const exitTime = $('#exit_time_selector').val();
        toggleLoading(true);

        $.ajax({
            url: '{{ route("camarilla.calculate-profit") }}',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                signals: analysisData,
                exit_time: exitTime
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
                alert('Error calculating profit. Check console for details.');
                toggleLoading(false);
            }
        });
    }

    function displayProfitTable() {
        if (!profitData || profitData.length === 0) {
            return;
        }

        let html = '';
        
        analysisData.forEach(function (signal, index) {
            const profit = profitData.find(p => p.option_symbol === signal.option_symbol);
            
            if (!profit) {
                return;
            }

            let signalClass = signal.signal === 'BUY_CE' ? 'badge-buy-ce' : 'badge-buy-pe';
            let signalText = signal.signal === 'BUY_CE' ? 'CE ▲' : 'PE ▼';
            let typeClass = signal.signal_type === 'REVERSAL' ? 'badge-reversal' : 'badge-breakout';
            let levelClass = `level-${signal.level.toLowerCase()}`;
            const profitClass = profit.profit_loss >= 0 ? 'badge-profit' : 'badge-loss';
            const highestProfitClass = profit.highest_profit >= 0 ? 'badge-profit' : 'badge-loss';
            const profitSign = profit.profit_loss >= 0 ? '+' : '';
            const highestProfitSign = profit.highest_profit >= 0 ? '+' : '';
            const timeOnly = signal.signal_time ? signal.signal_time.split(' ')[1].substring(0, 5) : '-';

            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td><strong>${signal.date}</strong></td>
                    <td>${timeOnly}</td>
                    <td><strong style="color: #667eea;">${signal.symbol}</strong></td>
                    <td><span class="badge ${signalClass}">${signalText}</span></td>
                    <td><span class="badge ${typeClass}">${signal.signal_type}</span></td>
                    <td><span class="level-badge ${levelClass}">${signal.level}</span></td>
                    <td><strong>₹${signal.signal_price}</strong></td>
                    <td>
                        <span class="level-badge level-h4">H4: ${signal.h4}</span>
                        <span class="level-badge level-h3">H3: ${signal.h3}</span><br>
                        <span class="level-badge level-l3">L3: ${signal.l3}</span>
                        <span class="level-badge level-l4">L4: ${signal.l4}</span>
                    </td>
                    <td>${formatSignal(signal.fut_signal)}</td>
                    <td>${formatSignal(signal.ce_signal)}</td>
                    <td>${formatSignal(signal.pe_signal)}</td>
                    <td><strong>${profit.option_symbol}</strong></td>
                    <td><strong>₹${profit.buy_price}</strong></td>
                    <td><strong>₹${profit.sell_price}</strong></td>
                    <td>
                        <span class="highest-price-badge">₹${profit.highest_price}</span><br>
                        <small style="color: #666;">@ ${profit.highest_price_time}</small>
                    </td>
                    <td>${profit.quantity}</td>
                    <td><strong>₹${profit.investment.toLocaleString('en-IN')}</strong></td>
                    <td><span class="badge ${profitClass}">${profitSign}₹${Math.round(profit.profit_loss)}</span></td>
                    <td><span class="badge ${highestProfitClass}">${highestProfitSign}₹${Math.round(profit.highest_profit)}</span></td>
                    <td><span class="badge ${profitClass}">${profitSign}${profit.return_percent}%</span></td>
                </tr>
            `;
        });

        $('#analysis-tbody').html(html);
    }

    function updateBasicStatistics() {
        if (!analysisData || analysisData.length === 0) {
            resetStatistics();
            return;
        }

        const totalSignals = analysisData.length;
        const ceCount = analysisData.filter(r => r.signal === 'BUY_CE').length;
        const peCount = analysisData.filter(r => r.signal === 'BUY_PE').length;

        $('#total_signals').text(totalSignals);
        $('#ce_signals').text(ceCount);
        $('#pe_signals').text(peCount);
    }

    function updateProfitStatistics(summary) {
        if (!summary) return;

        $('#total_investment').text('₹' + summary.total_investment.toLocaleString('en-IN'));
        $('#total_profit').text('₹' + summary.total_profit_loss.toLocaleString('en-IN'));
        $('#total_highest_profit').text('₹' + summary.total_highest_profit.toLocaleString('en-IN'));
        $('#win_rate').text(summary.win_rate + '%');
        $('#roi_percent').text(summary.roi_percent + '%');
    }

    function resetStatistics() {
        $('#total_signals').text('0');
        $('#ce_signals').text('0');
        $('#pe_signals').text('0');
        $('#total_investment').text('₹0');
        $('#total_profit').text('₹0');
        $('#total_highest_profit').text('₹0');
        $('#win_rate').text('0%');
        $('#roi_percent').text('0%');
    }

    function resetFilters() {
        $('#from_date').val('{{ date("Y-m-d") }}');
        $('#to_date').val('{{ date("Y-m-d") }}');
        $('#symbol_filter').val('');
        $('#option_series_filter').val('current');
        $('#exit_time_selector').val('15:30');
        
        analysisData = [];
        profitData = [];
        $('#analysis-tbody').html(`
            <tr>
                <td colspan="21" class="text-center py-5">
                    <div style="padding: 60px 20px;">
                        <i class="fas fa-chart-bar" style="font-size: 4rem; color: #6c757d; opacity: 0.5;"></i>
                        <p style="font-size: 1.1rem; margin-top: 20px;">Select date range and click <strong>"Run Analysis"</strong></p>
                    </div>
                </td>
            </tr>
        `);
        resetStatistics();
        $('#exit-time-section').hide();
        $('#export_csv').hide();
    }

    function exportCSV() {
        if (!analysisData || analysisData.length === 0) {
            alert('No data to export');
            return;
        }

        $.ajax({
            url: '{{ route("camarilla.export") }}',
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
                a.download = 'camarilla_analysis_' + new Date().toISOString().slice(0, 10) + '.csv';
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
        $('#run_analysis').click(runAnalysis);
        $('#calculate_profit').click(calculateProfit);
        $('#export_csv').click(exportCSV);
        $('#reset_filters').click(resetFilters);
    });
</script>
@endpush