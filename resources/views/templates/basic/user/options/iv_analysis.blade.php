{{-- resources/views/templates/basic/user/options/iv_analysis.blade.php --}}

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

            .table-container {
                position: relative;
                min-height: 400px;
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

            .filter-section {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
                border: 1px solid #dee2e6;
            }

            .stats-box {
                background: #fff;
                padding: 15px;
                border-radius: 8px;
                text-align: center;
                border-left: 4px solid #3498db;
                margin-bottom: 10px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .stats-box small {
                display: block;
                color: #666;
                font-size: 0.9rem;
                margin-bottom: 5px;
            }

            .stats-box strong {
                display: block;
                font-size: 1.5rem;
                margin-top: 5px;
            }

            .analysis-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 20px;
            }

            .analysis-card h5 {
                color: white;
                margin-bottom: 15px;
            }

            .analysis-item {
                background: rgba(255, 255, 255, 0.1);
                padding: 10px;
                border-radius: 5px;
                margin-bottom: 10px;
            }

            .analysis-item strong {
                display: block;
                font-size: 0.9rem;
                margin-bottom: 5px;
            }

            .analysis-item span {
                font-size: 1.2rem;
                font-weight: bold;
            }

            .badge-regime-low {
                background-color: #28a745 !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 0.9rem;
            }

            .badge-regime-normal {
                background-color: #17a2b8 !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 0.9rem;
            }

            .badge-regime-high {
                background-color: #dc3545 !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 4px;
                font-size: 0.9rem;
            }

            .badge-trend-rising {
                background-color: #ff6b6b !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 4px;
            }

            .badge-trend-falling {
                background-color: #51cf66 !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 4px;
            }

            .badge-trend-flat {
                background-color: #6c757d !important;
                color: white !important;
                padding: 4px 12px;
                border-radius: 4px;
            }

            .iv-high {
                color: #dc3545;
                font-weight: bold;
            }

            .iv-low {
                color: #28a745;
                font-weight: bold;
            }

            .iv-normal {
                color: #17a2b8;
                font-weight: bold;
            }

            .chart-container {
                background: white;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .no-data-message {
                padding: 40px 20px;
                text-align: center;
                color: #6c757d;
            }

            .no-data-message i {
                font-size: 3rem;
                margin-bottom: 15px;
                display: block;
            }

            .recommendation-box {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                border-radius: 5px;
                margin-top: 15px;
            }

            .recommendation-box.success {
                background: #d4edda;
                border-left-color: #28a745;
            }

            .recommendation-box.danger {
                background: #f8d7da;
                border-left-color: #dc3545;
            }
        </style>
    @endpush

    <section class="pt-50 pb-50">
        <div class="container-fluid content-container">
            <!-- Header -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4>{{ $pageTitle }}</h4>
                        <p class="text-muted">Analyze Implied Volatility (IV) movement for ATM options (ATM-1, ATM, ATM+1)</p>
                    </div>
                    <div>
                        <button type="button" id="manual_fetch_iv_btn" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Fetch Latest IV Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row">
                    <div class="col-md-3">
                        <label for="symbol_filter" class="form-label text-dark">Symbol:</label>
                        <select id="symbol_filter" class="form-control">
                            <option value="">-- Select Symbol --</option>
                            @foreach ($symbols as $symbol)
                                <option value="{{ $symbol }}">{{ $symbol }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="from_date" class="form-label text-dark">From Date:</label>
                        <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
                    </div>

                    <div class="col-md-2">
                        <label for="to_date" class="form-label text-dark">To Date:</label>
                        <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" id="load_iv_data" class="btn btn-success me-2">
                            <i class="fas fa-search"></i> Load
                        </button>
                        <button type="button" id="refresh_iv_data" class="btn btn-info me-2">
                            <i class="fas fa-redo"></i> Refresh
                        </button>
                        <button type="button" id="export_iv_csv" class="btn btn-warning">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- IV Analysis Card -->
            <div class="row" id="analysis-container" style="display: none;">
                <div class="col-md-12">
                    <div class="analysis-card">
                        <h5><i class="fas fa-chart-line"></i> Live IV Analysis</h5>
                        <div class="row">
                            <div class="col-md-2">
                                <div class="analysis-item">
                                    <strong>Symbol</strong>
                                    <span id="analysis_symbol">-</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="analysis-item">
                                    <strong>ATM Strike</strong>
                                    <span id="analysis_atm_strike">-</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="analysis-item">
                                    <strong>Avg IV</strong>
                                    <span id="analysis_avg_iv">-</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="analysis-item">
                                    <strong>IV Regime</strong>
                                    <span id="analysis_iv_regime">-</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="analysis-item">
                                    <strong>IV Trend</strong>
                                    <span id="analysis_iv_trend">-</span>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="analysis-item">
                                    <strong>IV Speed</strong>
                                    <span id="analysis_iv_speed">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-3">
                                <div class="analysis-item">
                                    <strong>CE Avg IV</strong>
                                    <span id="analysis_ce_iv">-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analysis-item">
                                    <strong>PE Avg IV</strong>
                                    <span id="analysis_pe_iv">-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analysis-item">
                                    <strong>IV Skew</strong>
                                    <span id="analysis_iv_skew">-</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="analysis-item">
                                    <strong>OI PCR</strong>
                                    <span id="analysis_oi_pcr">-</span>
                                </div>
                            </div>
                        </div>

                        <div class="recommendation-box" id="recommendation_box">
                            <strong><i class="fas fa-lightbulb"></i> Trading Recommendation:</strong>
                            <p id="recommendation_text" class="mb-0 mt-2">-</p>
                        </div>

                        <div class="mt-3">
                            <small><strong>Expected Behavior:</strong> <span id="expected_behavior">-</span></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Chart -->
            <div class="row">
                <div class="col-md-12">
                    <div class="chart-container" id="chart-container" style="display: none;">
                        <h5>IV Trend Chart</h5>
                        <canvas id="ivChart" height="80"></canvas>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4" id="stats-container">
                <div class="col-md-2">
                    <div class="stats-box">
                        <small>Total Records</small>
                        <strong id="total_records" class="text-dark">0</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box">
                        <small>Latest Timestamp</small>
                        <strong id="latest_timestamp" class="text-dark" style="font-size: 1rem;">-</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box">
                        <small>IV Change (5m)</small>
                        <strong id="iv_change_5m" class="text-dark">-</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box">
                        <small>IV Change (15m)</small>
                        <strong id="iv_change_15m" class="text-dark">-</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box">
                        <small>IV Change (Day)</small>
                        <strong id="iv_change_day" class="text-dark">-</strong>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-container">
                <div class="loading-overlay" id="loading-overlay" style="display: none;">
                    <div class="spinner"></div>
                </div>

                <div class="table-responsive">
                    <table class="table custom--table">
                        <thead class="table-dark">
                            <tr>
                                <th>Timestamp</th>
                                <th>ATM Strike</th>
                                <th colspan="3" class="text-center" style="background: #28a745;">CE (Call)</th>
                                <th colspan="3" class="text-center" style="background: #dc3545;">PE (Put)</th>
                                <th>Avg IV</th>
                                <th>IV Skew</th>
                                <th>OI PCR</th>
                            </tr>
                            <tr>
                                <th></th>
                                <th></th>
                                <th>ATM-1</th>
                                <th>ATM</th>
                                <th>ATM+1</th>
                                <th>ATM-1</th>
                                <th>ATM</th>
                                <th>ATM+1</th>
                                <th></th>
                                <th></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="iv-tbody">
                            <tr>
                                <td colspan="11" class="text-center text-muted">
                                    <div class="no-data-message">
                                        <i class="fas fa-chart-bar"></i>
                                        <p>Select a symbol and click "Load Data" to view IV analysis</p>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let currentData = [];
        let ivChart = null;

        function toggleLoading(show) {
            $('#loading-overlay').toggle(show);
        }

        function loadIVData() {
            const symbol = $('#symbol_filter').val();
            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();

            if (!symbol) {
                alert('Please select a symbol');
                return;
            }

            toggleLoading(true);

            $.ajax({
                url: '{{ route('options.iv-analysis-fetch') }}',
                type: 'GET',
                data: {
                    symbol: symbol,
                    from_date: fromDate,
                    to_date: toDate
                },
                success: function(response) {
                    console.log('Response:', response);

                    if (response.success && response.data && response.data.length > 0) {
                        currentData = response.data;
                        displayTable();
                        updateStatistics(response.data);
                        displayAnalysis(response.analysis);
                        loadIVChart(symbol, fromDate, toDate);
                    } else {
                        $('#iv-tbody').html(`
                            <tr>
                                <td colspan="11" class="text-center">
                                    <div class="no-data-message">
                                        <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                        <p class="text-warning">${response.message || 'No data available'}</p>
                                    </div>
                                </td>
                            </tr>
                        `);
                        resetStatistics();
                        $('#analysis-container').hide();
                        $('#chart-container').hide();
                    }
                    toggleLoading(false);
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    $('#iv-tbody').html(`
                        <tr>
                            <td colspan="11" class="text-center">
                                <div class="no-data-message">
                                    <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                    <p class="text-danger">Error loading data. Please try again.</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
                    $('#analysis-container').hide();
                    $('#chart-container').hide();
                    toggleLoading(false);
                }
            });
        }

        function displayTable() {
            if (!currentData || currentData.length === 0) {
                return;
            }

            let html = '';

            currentData.forEach(function(row) {
                const formatIV = (val) => val != null ? parseFloat(val).toFixed(2) + '%' : '-';

                html += `
                    <tr>
                        <td><strong>${row.timestamp}</strong></td>
                        <td><strong>${row.atm_strike}</strong></td>
                        <td>${formatIV(row.ce_atm_minus_1_iv)}</td>
                        <td><strong>${formatIV(row.ce_atm_iv)}</strong></td>
                        <td>${formatIV(row.ce_atm_plus_1_iv)}</td>
                        <td>${formatIV(row.pe_atm_minus_1_iv)}</td>
                        <td><strong>${formatIV(row.pe_atm_iv)}</strong></td>
                        <td>${formatIV(row.pe_atm_plus_1_iv)}</td>
                        <td><strong class="iv-normal">${row.avg_iv}%</strong></td>
                        <td><span class="${row.iv_skew > 0 ? 'text-danger' : 'text-success'}">${row.iv_skew}</span></td>
                        <td>${row.oi_pcr}</td>
                    </tr>
                `;
            });

            $('#iv-tbody').html(html);
        }

        function updateStatistics(data) {
            $('#total_records').text(data.length);
            $('#latest_timestamp').text(data[0].timestamp);
        }

        function displayAnalysis(analysis) {
            if (!analysis) return;

            $('#analysis-container').show();

            // Basic Info
            $('#analysis_symbol').text(analysis.symbol);
            $('#analysis_atm_strike').text(analysis.atm_strike);
            $('#analysis_avg_iv').text(analysis.avg_iv_total + '%');

            // IV Regime
            let regimeClass = 'badge-regime-normal';
            if (analysis.iv_regime === 'LOW') regimeClass = 'badge-regime-low';
            if (analysis.iv_regime === 'HIGH') regimeClass = 'badge-regime-high';
            $('#analysis_iv_regime').html(`<span class="${regimeClass}">${analysis.iv_regime}</span>`);

            // IV Trend
            let trendClass = 'badge-trend-flat';
            if (analysis.iv_trend === 'RISING') trendClass = 'badge-trend-rising';
            if (analysis.iv_trend === 'FALLING') trendClass = 'badge-trend-falling';
            $('#analysis_iv_trend').html(`<span class="${trendClass}">${analysis.iv_trend}</span>`);

            // IV Speed
            let speedColor = analysis.iv_speed === 'FAST' ? '#dc3545' : '#28a745';
            $('#analysis_iv_speed').html(`<span style="color: ${speedColor}">${analysis.iv_speed}</span>`);

            // CE/PE IV
            $('#analysis_ce_iv').text(analysis.avg_iv_ce + '%');
            $('#analysis_pe_iv').text(analysis.avg_iv_pe + '%');
            $('#analysis_iv_skew').text(analysis.iv_skew);
            $('#analysis_oi_pcr').text(analysis.oi_pcr);

            // Changes
            $('#iv_change_5m').html(formatChange(analysis.iv_change_5m));
            $('#iv_change_15m').html(formatChange(analysis.iv_change_15m));
            $('#iv_change_day').html(formatChange(analysis.iv_change_day));

            // Recommendation
            let recommendationClass = '';
            if (analysis.recommendation.includes('✅')) {
                recommendationClass = 'success';
            } else if (analysis.recommendation.includes('⚠️')) {
                recommendationClass = 'danger';
            }
            $('#recommendation_box').removeClass('success danger').addClass(recommendationClass);
            $('#recommendation_text').text(analysis.recommendation);
            $('#expected_behavior').text(analysis.expected_behavior);
        }

        function formatChange(value) {
            if (value > 0) {
                return `<span style="color: #dc3545;">+${value}%</span>`;
            } else if (value < 0) {
                return `<span style="color: #28a745;">${value}%</span>`;
            }
            return `<span style="color: #6c757d;">${value}%</span>`;
        }

        function loadIVChart(symbol, fromDate, toDate) {
            $.ajax({
                url: '{{ route('options.iv-trend-chart') }}',
                type: 'GET',
                data: {
                    symbol: symbol,
                    from_date: fromDate,
                    to_date: toDate
                },
                success: function(response) {
                    if (response.success) {
                        $('#chart-container').show();
                        renderIVChart(response.data);
                    }
                }
            });
        }

        function renderIVChart(data) {
            const ctx = document.getElementById('ivChart').getContext('2d');

            if (ivChart) {
                ivChart.destroy();
            }

            ivChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'CE IV',
                            data: data.ce_iv,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'PE IV',
                            data: data.pe_iv,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Avg IV',
                            data: data.avg_iv,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'IV Movement Over Time'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: {
                                display: true,
                                text: 'IV (%)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Time'
                            }
                        }
                    }
                }
            });
        }

        function resetStatistics() {
            $('#total_records').text('0');
            $('#latest_timestamp').text('-');
            $('#iv_change_5m').html('-');
            $('#iv_change_15m').html('-');
            $('#iv_change_day').html('-');
        }

        function exportCSV() {
            const symbol = $('#symbol_filter').val();
            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();

            const params = new URLSearchParams({
                symbol: symbol,
                from_date: fromDate || '',
                to_date: toDate || ''
            });

            window.location.href = '{{ route('options.iv-export') }}?' + params.toString();
        }

        $(document).ready(function() {
            $('#load_iv_data').click(function() {
                loadIVData();
            });

            $('#refresh_iv_data').click(function() {
                if ($('#symbol_filter').val()) {
                    loadIVData();
                } else {
                    alert('Please select a symbol first');
                }
            });

            $('#export_iv_csv').click(function() {
                exportCSV();
            });

            $('#symbol_filter').change(function() {
                if ($(this).val()) {
                    loadIVData();
                }
            });

            // Manual Fetch IV Button
            $('#manual_fetch_iv_btn').click(function() {
                if (!confirm('This will fetch the latest IV data for all symbols. Continue?')) {
                    return;
                }

                const $btn = $(this);
                $btn.prop('disabled', true);
                $btn.html('<i class="fas fa-spinner fa-spin"></i> Fetching...');

                $.ajax({
                    url: '{{ route('options.iv-manual-fetch') }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        alert('✅ ' + response.message);
                        if ($('#symbol_filter').val()) {
                            loadIVData();
                        }
                    },
                    error: function(xhr) {
                        const message = xhr.responseJSON?.message || 'Error fetching IV data';
                        alert('❌ ' + message);
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                        $btn.html('<i class="fas fa-sync-alt"></i> Fetch Latest IV Data');
                    }
                });
            });
        });
    </script>
@endpush