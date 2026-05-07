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

            .badge-buy { background-color: #28a745 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
            .badge-sell { background-color: #dc3545 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
            .badge-hold { background-color: #6c757d !important; color: white !important; padding: 4px 8px; border-radius: 4px; }

            .badge-trend { background-color: #28a745 !important; color: white !important; }
            .badge-acceptance { background-color: #17a2b8 !important; color: white !important; }
            .badge-volatile { background-color: #ffc107 !important; color: #000 !important; }
            .badge-chop { background-color: #dc3545 !important; color: white !important; }

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
                margin-bottom: 10px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .stats-box.trend { border-left: 4px solid #28a745; }
            .stats-box.acceptance { border-left: 4px solid #17a2b8; }
            .stats-box.volatile { border-left: 4px solid #ffc107; }
            .stats-box.chop { border-left: 4px solid #dc3545; }

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

            .metric-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 0.75rem;
                font-weight: bold;
                margin: 2px;
            }

            .metric-high { background-color: #28a745; color: white; }
            .metric-medium { background-color: #ffc107; color: #000; }
            .metric-low { background-color: #dc3545; color: white; }

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

            .behavior-legend {
                background: #fff;
                padding: 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .behavior-legend h6 {
                margin-bottom: 10px;
                font-weight: bold;
            }

            .behavior-item {
                display: inline-block;
                margin: 5px 10px 5px 0;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 0.85rem;
            }

            .strength-bar {
                height: 20px;
                background: linear-gradient(to right, #dc3545, #ffc107, #28a745);
                border-radius: 10px;
                position: relative;
                overflow: hidden;
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
                        <p class="text-muted">Pure price behavior analysis using Price, Range, Volume & Time - NO traditional indicators</p>
                    </div>
                    <div>
                        <a href="{{ route('symbols.analysis') }}" class="btn btn-info me-2">
                            <i class="fas fa-chart-line"></i> Indicator Analysis
                        </a>
                        <a href="{{ route('custom.backtesting') }}" class="btn btn-success">
                            <i class="fas fa-history"></i> Backtesting
                        </a>
                    </div>
                </div>
            </div>

            <!-- Behavior Legend -->
            <div class="behavior-legend">
                <h6 class="text-dark"><i class="fas fa-info-circle"></i> Stock Behavior Types</h6>
                <div>
                    <span class="behavior-item badge-trend">
                        <strong>TREND_DOMINANT:</strong> High DE + High PAR → Trade continuations only
                    </span>
                    <span class="behavior-item badge-acceptance">
                        <strong>ACCEPTANCE_BASED:</strong> Medium DE + High CLV → Trade pullback entries
                    </span>
                    <span class="behavior-item badge-volatile">
                        <strong>VOLATILE_EXPANSION:</strong> Sudden RES + VPI → Trade break + momentum
                    </span>
                    <span class="behavior-item badge-chop">
                        <strong>CHOP_ZONE:</strong> Low DE + Low PAR → Mostly NO TRADE
                    </span>
                </div>
                <small class="text-muted mt-2 d-block">
                    <strong>Metrics:</strong> PAR (Price Acceptance) | DE (Directional Efficiency) | RES (Range Expansion) | VPI (Volume Participation) | CLV (Close Location)
                </small>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row">
                    <div class="col-md-3">
                        <label for="trading_symbol_filter" class="form-label text-dark"><strong>Trading Symbol:</strong></label>
                        <select id="trading_symbol_filter" class="form-control">
                            <option value="">-- Select Symbol --</option>
                            @foreach ($monitoredSymbols as $symbol)
                                <option value="{{ $symbol->trading_symbol }}">{{ $symbol->trading_symbol }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="interval_filter" class="form-label text-dark"><strong>Timeframe:</strong></label>
                        <select id="interval_filter" class="form-control">
                            <option value="minute">1 Minute</option>
                            <option value="5minute" selected>5 Minutes</option>
                            <option value="15minute">15 Minutes</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="from_date" class="form-label text-dark"><strong>From Date:</strong></label>
                        <input type="date" id="from_date" class="form-control" value="{{ date('Y-m-d') }}" />
                    </div>

                    <div class="col-md-2">
                        <label for="to_date" class="form-label text-dark"><strong>To Date:</strong></label>
                        <input type="date" id="to_date" class="form-control" value="{{ date('Y-m-d') }}" />
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" id="load_data" class="btn btn-success me-2">
                            <i class="fas fa-search"></i> Analyze
                        </button>
                        <button type="button" id="refresh_data" class="btn btn-info me-2">
                            <i class="fas fa-redo"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4" id="stats-container">
                <div class="col-md-2">
                    <div class="stats-box">
                        <small>Total Candles</small>
                        <strong id="total_candles" class="text-dark">0</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box trend">
                        <small>Trend Dominant</small>
                        <strong id="trend_count" style="color: #28a745;">0</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box acceptance">
                        <small>Acceptance Based</small>
                        <strong id="acceptance_count" style="color: #17a2b8;">0</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box volatile">
                        <small>Volatile Expansion</small>
                        <strong id="volatile_count" style="color: #ffc107;">0</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box chop">
                        <small>Chop Zone</small>
                        <strong id="chop_count" style="color: #dc3545;">0</strong>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="stats-box">
                        <small>Signals Generated</small>
                        <strong id="signal_count" class="text-primary">0</strong>
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
                        <thead class="table-dark">
                            <tr>
                                <th>Date & Time</th>
                                <th>Symbol</th>
                                <th>OHLC</th>
                                <th>Behavior Type</th>
                                <th>Confidence</th>
                                <th>Metrics</th>
                                <th>Signal</th>
                                <th>Strength</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody id="analysis-tbody">
                            <tr>
                                <td colspan="9" class="text-center text-muted">
                                    <div class="no-data-message">
                                        <i class="fas fa-brain"></i>
                                        <p>Select a trading symbol and timeframe, then click "Analyze"</p>
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
        let currentData = [];

        function toggleLoading(show) {
            $('#loading-overlay').toggle(show);
        }

        function loadAnalysis() {
            const tradingSymbol = $('#trading_symbol_filter').val();
            const interval = $('#interval_filter').val();
            const fromDate = $('#from_date').val();
            const toDate = $('#to_date').val();

            if (!tradingSymbol) {
                alert('Please select a trading symbol');
                return;
            }

            toggleLoading(true);

            $.ajax({
                url: '{{ route('custom.analysis-fetch') }}',
                type: 'GET',
                data: {
                    trading_symbol: tradingSymbol,
                    interval: interval,
                    from_date: fromDate,
                    to_date: toDate
                },
                success: function(response) {
                    console.log('Response:', response);

                    if (response.success && response.data && response.data.length > 0) {
                        currentData = response.data;
                        displayTable();
                        updateStatistics();
                    } else {
                        $('#analysis-tbody').html(`
                            <tr>
                                <td colspan="9" class="text-center">
                                    <div class="no-data-message">
                                        <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
                                        <p class="text-warning">${response.message || 'No data available'}</p>
                                    </div>
                                </td>
                            </tr>
                        `);
                        resetStatistics();
                    }
                    toggleLoading(false);
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    $('#analysis-tbody').html(`
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="no-data-message">
                                    <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                    <p class="text-danger">Error loading data. Please try again.</p>
                                </div>
                            </td>
                        </tr>
                    `);
                    resetStatistics();
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
                const behaviorType = row.classification.type;
                const behaviorClass = getBehaviorClass(behaviorType);
                const signalClass = row.signal.signal === 'BUY' ? 'badge-buy' : 
                                   (row.signal.signal === 'SELL' ? 'badge-sell' : 'badge-hold');

                const metrics = row.classification.metrics;
                const metricsHtml = `
                    <span class="${getMetricClass(metrics.par)}" title="PAR">P:${metrics.par}</span>
                    <span class="${getMetricClass(metrics.de)}" title="DE">D:${metrics.de}</span>
                    <span class="${getMetricClass(metrics.res)}" title="RES">R:${metrics.res}</span>
                    <span class="${getMetricClass(metrics.vpi)}" title="VPI">V:${metrics.vpi}</span>
                    <span class="${getMetricClass(metrics.clv)}" title="CLV">C:${metrics.clv}</span>
                `;

                html += `
                    <tr>
                        <td><strong>${row.date || row.timestamp}</strong></td>
                        <td><strong>${row.symbol}</strong></td>
                        <td>
                            <small>O:${row.candle.open} H:${row.candle.high}<br>
                            L:${row.candle.low} C:<strong>${row.candle.close}</strong></small>
                        </td>
                        <td><span class="badge ${behaviorClass}">${formatBehaviorType(behaviorType)}</span></td>
                        <td><strong>${row.classification.confidence}%</strong></td>
                        <td>${metricsHtml}</td>
                        <td><span class="badge ${signalClass}">${row.signal.signal}</span></td>
                        <td>
                            <small>${row.signal.strength}%</small>
                        </td>
                        <td><small>${row.signal.reason || '-'}</small></td>
                    </tr>
                `;
            });

            $('#analysis-tbody').html(html);
        }

        function getBehaviorClass(type) {
            const classes = {
                'TREND_DOMINANT': 'badge-trend',
                'ACCEPTANCE_BASED': 'badge-acceptance',
                'VOLATILE_EXPANSION': 'badge-volatile',
                'CHOP_ZONE': 'badge-chop'
            };
            return classes[type] || 'badge-secondary';
        }

        function formatBehaviorType(type) {
            return type.replace(/_/g, ' ');
        }

        function getMetricClass(value) {
            if (value >= 0.7) return 'metric-badge metric-high';
            if (value >= 0.4) return 'metric-badge metric-medium';
            return 'metric-badge metric-low';
        }

        function updateStatistics() {
            if (!currentData || currentData.length === 0) {
                resetStatistics();
                return;
            }

            const total = currentData.length;
            const trendCount = currentData.filter(r => r.classification.type === 'TREND_DOMINANT').length;
            const acceptanceCount = currentData.filter(r => r.classification.type === 'ACCEPTANCE_BASED').length;
            const volatileCount = currentData.filter(r => r.classification.type === 'VOLATILE_EXPANSION').length;
            const chopCount = currentData.filter(r => r.classification.type === 'CHOP_ZONE').length;
            const signalCount = currentData.filter(r => r.signal.signal !== 'HOLD').length;

            $('#total_candles').text(total);
            $('#trend_count').text(trendCount);
            $('#acceptance_count').text(acceptanceCount);
            $('#volatile_count').text(volatileCount);
            $('#chop_count').text(chopCount);
            $('#signal_count').text(signalCount);
        }

        function resetStatistics() {
            $('#total_candles').text('0');
            $('#trend_count').text('0');
            $('#acceptance_count').text('0');
            $('#volatile_count').text('0');
            $('#chop_count').text('0');
            $('#signal_count').text('0');
        }

        $(document).ready(function() {
            $('#load_data').click(function() {
                loadAnalysis();
            });

            $('#refresh_data').click(function() {
                if ($('#trading_symbol_filter').val()) {
                    loadAnalysis();
                } else {
                    alert('Please select a trading symbol first');
                }
            });

            $('#trading_symbol_filter').change(function() {
                if ($(this).val()) {
                    loadAnalysis();
                }
            });
        });
    </script>
@endpush