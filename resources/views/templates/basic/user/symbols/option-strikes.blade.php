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

    .filter-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #dee2e6;
    }

    .badge-underpriced { background-color: #28a745 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-overpriced { background-color: #dc3545 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-fair { background-color: #17a2b8 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-na { background-color: #6c757d !important; color: white !important; padding: 4px 8px; border-radius: 4px; }

    .badge-ce { background-color: #007bff !important; color: white !important; padding: 4px 8px; border-radius: 4px; }
    .badge-pe { background-color: #fd7e14 !important; color: white !important; padding: 4px 8px; border-radius: 4px; }

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

    .stats-box {
        background: #fff;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        border-left: 4px solid #3498db;
        margin-bottom: 10px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <!-- Header -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4>{{ $pageTitle }}</h4>
                    <p class="text-muted">
                        <i class="fas fa-chart-bar"></i> ATM±2 Strike Analysis | 
                        <i class="fas fa-money-bill-wave"></i> Fair Price vs LTP |
                        <i class="fas fa-balance-scale"></i> Valuation Status (Current Series)
                    </p>
                </div>
                <div>
                    <a href="{{ route('symbols.backtesting') }}" class="btn btn-info">
                        <i class="fas fa-chart-line"></i> Back to Backtesting
                    </a>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="trade_date_filter" class="form-label text-dark"><strong>Trade Date:</strong></label>
                    <input type="date" id="trade_date_filter" class="form-control" value="{{ date('Y-m-d') }}" />
                </div>

                <div class="col-md-3">
                    <label for="interval_time_filter" class="form-label text-dark"><strong>Interval Time (Optional):</strong></label>
                    <select id="interval_time_filter" class="form-control">
                        <option value="">Latest Entry (Default)</option>
                        <option value="09:15">09:15 AM</option>
                        <option value="09:30">09:30 AM</option>
                        <option value="09:45">09:45 AM</option>
                        <option value="10:00">10:00 AM</option>
                        <option value="10:15">10:15 AM</option>
                        <option value="10:30">10:30 AM</option>
                        <option value="10:45">10:45 AM</option>
                        <option value="11:00">11:00 AM</option>
                        <option value="11:15">11:15 AM</option>
                        <option value="11:30">11:30 AM</option>
                        <option value="11:45">11:45 AM</option>
                        <option value="12:00">12:00 PM</option>
                        <option value="12:15">12:15 PM</option>
                        <option value="12:30">12:30 PM</option>
                        <option value="12:45">12:45 PM</option>
                        <option value="13:00">01:00 PM</option>
                        <option value="13:15">01:15 PM</option>
                        <option value="13:30">01:30 PM</option>
                        <option value="13:45">01:45 PM</option>
                        <option value="14:00">02:00 PM</option>
                        <option value="14:15">02:15 PM</option>
                        <option value="14:30">02:30 PM</option>
                        <option value="14:45">02:45 PM</option>
                        <option value="15:00">03:00 PM</option>
                        <option value="15:15">03:15 PM</option>
                        <option value="15:30">03:30 PM</option>
                    </select>
                    <small class="text-muted">Leave empty to show latest entry per symbol</small>
                </div>

                <div class="col-md-3">
                    <label for="future_symbol_filter" class="form-label text-dark"><strong>Future Symbol (Optional):</strong></label>
                    <select id="future_symbol_filter" class="form-control">
                        <option value="">All Symbols</option>
                    </select>
                    <small class="text-muted">Leave empty for all symbols</small>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" id="fetch_data" class="btn btn-success me-2">
                        <i class="fas fa-search"></i> Fetch Data
                    </button>
                    <button type="button" id="reset_filters" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-box">
                    <small>Total Records</small>
                    <strong id="total_records" class="text-dark">0</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-box" style="border-left-color: #28a745;">
                    <small>Option Series</small>
                    <strong class="text-success">Current Series</strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-box" style="border-left-color: #17a2b8;">
                    <small>Data Type</small>
                    <strong class="text-info">Latest Entry Per Symbol</strong>
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
                            <th>#</th>
                            <th>Time</th>
                            <th>FUT Symbol</th>
                            {{-- <th>FUT Price</th>
                            <th>ATM Strike</th> --}}
                            {{-- <th>Expiry Date</th> --}}
                            <th>CE</th>
                            {{-- <th>CE Strike</th> --}}
                            <th>CE OI</th>
                            <th>CE Fair Price</th>
                            <th>CE LTP</th>
                            <th>CE Valuation</th>
                            <th>PE </th>
                            {{-- <th>PE Strike</th> --}}
                            <th>PE OI</th>
                            <th>PE Fair Price</th>
                            <th>PE LTP</th>
                            <th>PE Valuation</th>
                        </tr>
                    </thead>
                    <tbody id="strikes-tbody">
                        <tr>
                            <td colspan="16" class="text-center text-muted py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-search"></i>
                                    <p>Click "Fetch Data" to view option strike selections</p>
                                    <small class="text-muted">
                                        Showing Current Series | Latest Entry Per Symbol by Default
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
    let strikeData = [];

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function loadSymbols() {
        const tradeDate = $('#trade_date_filter').val();
        
        $.ajax({
            url: '{{ route("symbols.option-strikes-symbols") }}',
            type: 'GET',
            data: { trade_date: tradeDate },
            success: function(response) {
                if (response.success && response.symbols) {
                    let options = '<option value="">All Symbols</option>';
                    response.symbols.forEach(function(symbol) {
                        options += `<option value="${symbol}">${symbol}</option>`;
                    });
                    $('#future_symbol_filter').html(options);
                }
            }
        });
    }

    function fetchData() {
        const tradeDate = $('#trade_date_filter').val();
        const intervalTime = $('#interval_time_filter').val();
        const futureSymbol = $('#future_symbol_filter').val();

        toggleLoading(true);

        $.ajax({
            url: '{{ route("symbols.option-strikes-fetch") }}',
            type: 'GET',
            data: {
                trade_date: tradeDate,
                interval_time: intervalTime,
                future_symbol: futureSymbol
            },
            success: function(response) {
                console.log('Response:', response);
                
                if (response.success && response.data && response.data.length > 0) {
                    strikeData = response.data;
                    displayTable();
                    updateStatistics();
                } else {
                    $('#strikes-tbody').html(`
                        <tr>
                            <td colspan="16" class="text-center py-5">
                                <div class="no-data-message">
                                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                                    <p class="text-info">${response.message || 'No data found'}</p>
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
                $('#strikes-tbody').html(`
                    <tr>
                        <td colspan="16" class="text-center py-5">
                            <div class="no-data-message">
                                <i class="fas fa-exclamation-circle" style="color: #dc3545;"></i>
                                <p class="text-danger">Error fetching data</p>
                            </div>
                        </td>
                    </tr>
                `);
                resetStatistics();
                toggleLoading(false);
            }
        });
    }

    function getValuationBadge(valuation) {
        const classes = {
            'UNDERPRICED': 'badge-underpriced',
            'OVERPRICED': 'badge-overpriced',
            'FAIR': 'badge-fair',
            'N/A': 'badge-na'
        };
        return classes[valuation] || 'badge-na';
    }

    function displayTable() {
        if (!strikeData || strikeData.length === 0) {
            $('#strikes-tbody').html(`
                <tr>
                    <td colspan="13" class="text-center py-5">
                        <div class="no-data-message">
                            <i class="fas fa-inbox"></i>
                            <p>No records found</p>
                        </div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        
        strikeData.forEach(function(item, index) {
            html += `
                <tr>
                    <td><strong>${index + 1}</strong></td>
                    <td>${item.interval_time}</td>
                    <td><strong>${item.future_symbol}</strong></td>
                    <td>${item.selected_ce.symbol || 'N/A'}</td>
                    <td><strong>${item.selected_ce.oi ? item.selected_ce.oi.toLocaleString() : 'N/A'}</strong></td>
                    <td><strong>₹${item.selected_ce.fair_price || 'N/A'}</strong></td>
                    <td><strong>₹${item.selected_ce.ltp || 'N/A'}</strong></td>
                    <td><span class="badge ${getValuationBadge(item.selected_ce.valuation)}">${item.selected_ce.valuation || 'N/A'}</span></td>
                    <td>${item.selected_pe.symbol || 'N/A'}</td>
                    <td><strong>${item.selected_pe.oi ? item.selected_pe.oi.toLocaleString() : 'N/A'}</strong></td>
                    <td><strong>₹${item.selected_pe.fair_price || 'N/A'}</strong></td>
                    <td><strong>₹${item.selected_pe.ltp || 'N/A'}</strong></td>
                    <td><span class="badge ${getValuationBadge(item.selected_pe.valuation)}">${item.selected_pe.valuation || 'N/A'}</span></td>
                </tr>
            `;
        });

        $('#strikes-tbody').html(html);
    }

    function updateStatistics() {
        if (!strikeData || strikeData.length === 0) {
            resetStatistics();
            return;
        }

        $('#total_records').text(strikeData.length);
    }

    function resetStatistics() {
        $('#total_records').text('0');
    }

    function resetFilters() {
        $('#trade_date_filter').val('{{ date("Y-m-d") }}');
        $('#interval_time_filter').val('');
        $('#future_symbol_filter').val('');
        
        strikeData = [];
        
        $('#strikes-tbody').html(`
            <tr>
                <td colspan="16" class="text-center py-5">
                    <div class="no-data-message">
                        <i class="fas fa-search"></i>
                        <p>Click "Fetch Data" to view option strike selections</p>
                        <small class="text-muted">
                            Showing Current Series | Latest Entry Per Symbol by Default
                        </small>
                    </div>
                </td>
            </tr>
        `);
        
        resetStatistics();
        loadSymbols();
    }

    $(document).ready(function() {
        // Load symbols on page load
        loadSymbols();

        // Auto-fetch data on page load with current date
        fetchData();

        $('#trade_date_filter').change(function() {
            loadSymbols();
        });

        $('#fetch_data').click(function() {
            fetchData();
        });

        $('#reset_filters').click(function() {
            resetFilters();
        });
    });
</script>
@endpush