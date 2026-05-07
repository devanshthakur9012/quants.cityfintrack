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

    .badge-buy {
        background-color: #28a745 !important;
        color: white !important;
    }

    .badge-sell {
        background-color: #dc3545 !important;
        color: white !important;
    }

    .badge-hold {
        background-color: #ffc107 !important;
        color: #000 !important;
    }

    .uptrend {
        color: #28a745;
        font-weight: bold;
    }

    .downtrend {
        color: #dc3545;
        font-weight: bold;
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
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .stats-box small {
        display: block;
        color: #666;
        font-size: 0.9rem;
    }

    .stats-box strong {
        display: block;
        font-size: 1.5rem;
        margin-top: 5px;
    }

    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <!-- Header -->
        <div class="mb-4">
            <h4>{{ $pageTitle }}</h4>
            <p class="text-muted">Real-time Supertrend analysis with ATR calculations from historical data</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-2">
                    <label for="underlying_filter" class="form-label text-dark">Underlying:</label>
                    <select id="underlying_filter" class="form-control form-control-sm">
                        <option value="">-- Select Underlying --</option>
                        @foreach($underlyings as $underlying)
                            <option value="{{ $underlying }}">{{ $underlying }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="type_filter" class="form-label text-dark">Type:</label>
                    <select id="type_filter" class="form-control form-control-sm">
                        <option value="future">FUTURE</option>
                        <option value="ce">CALL (CE)</option>
                        <option value="pe">PUT (PE)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label for="atr_period" class="form-label text-dark">ATR Period:</label>
                    <input type="number" id="atr_period" class="form-control form-control-sm" value="10" min="5" max="50" />
                </div>

                <div class="col-md-2">
                    <label for="multiplier" class="form-label text-dark">Multiplier:</label>
                    <input type="number" id="multiplier" class="form-control form-control-sm" value="3" min="1" max="10" step="0.5" />
                </div>

                <div class="col-md-2">
                    <label for="days" class="form-label text-dark">Days Back:</label>
                    <input type="number" id="days" class="form-control form-control-sm" value="50" min="10" max="365" />
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" id="apply_filter" class="btn btn-success btn-sm w-100">Load Data</button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mb-4" id="stats-container">
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Total Candles</small>
                    <strong id="total_records" class="text-dark">-</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Buy Signals</small>
                    <strong id="buy_signals" style="color: #28a745;">-</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Sell Signals</small>
                    <strong id="sell_signals" style="color: #dc3545;">-</strong>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-box">
                    <small>Current Trend</small>
                    <strong id="current_trend" class="text-dark">-</strong>
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
                            <th>Date</th>
                            <th>Symbol</th>
                            <th>Open</th>
                            <th>High</th>
                            <th>Low</th>
                            <th>Close</th>
                            <th>Volume</th>
                            <th>ATR</th>
                            <th>Upper Band</th>
                            <th>Lower Band</th>
                            <th>Supertrend</th>
                            <th>Direction</th>
                            <th>Signal</th>
                        </tr>
                    </thead>
                    <tbody id="supertrend-tbody">
                        <tr>
                            <td colspan="12" class="text-center text-muted">
                                <i class="fas fa-inbox"></i> Select filters and click "Load Data" to view analysis
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
    class SupertrendCalculator {
        constructor(ohlcData, atrPeriod = 10, multiplier = 3) {
            this.data = ohlcData;
            this.atrPeriod = atrPeriod;
            this.multiplier = multiplier;
            this.results = [];
            this.trArray = [];
        }

        calculateTR(current, previous) {
            const high = parseFloat(current.high) || 0;
            const low = parseFloat(current.low) || 0;
            const prevClose = previous ? parseFloat(previous.close) || 0 : parseFloat(current.close) || 0;

            return Math.max(
                high - low,
                Math.abs(high - prevClose),
                Math.abs(low - prevClose)
            );
        }

        calculateTRArray() {
            const trs = [];

            for (let i = 1; i < this.data.length; i++) {
                const tr = this.calculateTR(this.data[i], this.data[i - 1]);
                trs.push(tr);
            }

            this.trArray = trs;
        }

        getAverageTR(index) {
            const startIndex = Math.max(0, index - this.atrPeriod);
            const slice = this.trArray.slice(startIndex, index + 1);
            return slice.length > 0 ? slice.reduce((a, b) => a + b, 0) / slice.length : 0;
        }

        calculateSupertrend() {
            this.calculateTRArray();

            let prevSupertrend = null;
            let prevClose = null;
            let prevDirection = 'UP';
            let prevUpperBand = null;
            let prevLowerBand = null;
            let currentTrend = null; // Track the active trend

            for (let i = this.atrPeriod; i < this.data.length; i++) {
                const current = this.data[i];
                const high = parseFloat(current.high) || 0;
                const low = parseFloat(current.low) || 0;
                const close = parseFloat(current.close) || 0;
                const open = parseFloat(current.open) || 0;
                const volume = current.volume || 0;
                const symbol = current.symbol || 'N/A';

                // Calculate ATR
                const atr = this.getAverageTR(i - 1);

                // Calculate basic bands
                const hl2 = (high + low) / 2;
                const basicUpperBand = hl2 + (this.multiplier * atr);
                const basicLowerBand = hl2 - (this.multiplier * atr);

                // Adjust bands
                let finalUpperBand = basicUpperBand;
                let finalLowerBand = basicLowerBand;

                if (prevUpperBand !== null) {
                    finalUpperBand = basicUpperBand < prevUpperBand || this.data[i - 1].close > prevUpperBand 
                        ? basicUpperBand 
                        : prevUpperBand;
                }

                if (prevLowerBand !== null) {
                    finalLowerBand = basicLowerBand > prevLowerBand || this.data[i - 1].close < prevLowerBand 
                        ? basicLowerBand 
                        : prevLowerBand;
                }

                // Determine supertrend and direction
                let supertrend;
                let direction;

                if (prevSupertrend === null) {
                    supertrend = finalUpperBand;
                    direction = 'DOWN';
                } else {
                    if (close > prevSupertrend) {
                        supertrend = finalLowerBand;
                        direction = 'UP';
                    } else {
                        supertrend = finalUpperBand;
                        direction = 'DOWN';
                    }
                }

                // Generate signal based on trend change
                let signal = 'HOLD';

                if (prevClose !== null && prevSupertrend !== null) {
                    // BUY Signal: Price crosses above supertrend (trend change to UP)
                    if (prevClose <= prevSupertrend && close > supertrend && prevDirection === 'DOWN' && direction === 'UP') {
                        signal = 'BUY';
                        currentTrend = 'BUY';
                    }
                    // SELL Signal: Price crosses below supertrend (trend change to DOWN)
                    else if (prevClose >= prevSupertrend && close < supertrend && prevDirection === 'UP' && direction === 'DOWN') {
                        signal = 'SELL';
                        currentTrend = 'SELL';
                    }
                    // Persist the current trend while direction remains the same
                    else if (direction === 'UP' && currentTrend === 'BUY') {
                        signal = 'BUY';
                    }
                    else if (direction === 'DOWN' && currentTrend === 'SELL') {
                        signal = 'SELL';
                    }
                } else if (i === this.atrPeriod) {
                    // First calculation
                    currentTrend = direction === 'UP' ? 'BUY' : 'SELL';
                    signal = currentTrend;
                }

                this.results.push({
                    date: this.data[i].date,
                    symbol: symbol,
                    open: open.toFixed(2),
                    high: high.toFixed(2),
                    low: low.toFixed(2),
                    close: close.toFixed(2),
                    volume: this.formatVolume(volume),
                    atr: atr.toFixed(4),
                    basicUpperBand: basicUpperBand.toFixed(2),
                    basicLowerBand: basicLowerBand.toFixed(2),
                    supertrend: supertrend.toFixed(2),
                    direction: direction,
                    signal: signal
                });

                prevSupertrend = supertrend;
                prevClose = close;
                prevDirection = direction;
                prevUpperBand = finalUpperBand;
                prevLowerBand = finalLowerBand;
            }

            return this.results;
        }

        formatVolume(volume) {
            if (volume >= 1000000) {
                return (volume / 1000000).toFixed(2) + 'M';
            } else if (volume >= 1000) {
                return (volume / 1000).toFixed(2) + 'K';
            }
            return volume.toString();
        }
    }

    function toggleLoading(show) {
        $('#loading-overlay').toggle(show);
    }

    function loadSupertrendData() {
        const underlying = $('#underlying_filter').val();
        const type = $('#type_filter').val();
        const atrPeriod = parseInt($('#atr_period').val());
        const multiplier = parseFloat($('#multiplier').val());
        const days = parseInt($('#days').val());

        if (!underlying) {
            alert('Please select an underlying');
            return;
        }

        toggleLoading(true);

        $.ajax({
            url: '{{ $route }}',
            type: 'GET',
            data: {
                underlying: underlying,
                type: type,
                atr_period: atrPeriod,
                multiplier: multiplier,
                days: days
            },
            success: function (response) {
                if (response.success && response.data.length > 0) {
                    const calculator = new SupertrendCalculator(
                        response.data,
                        atrPeriod,
                        multiplier
                    );
                    const results = calculator.calculateSupertrend();

                    displaySupertrendTable(results);
                    updateStatistics(results);
                } else {
                    alert('Error: ' + (response.message || 'No data available'));
                    $('#supertrend-tbody').html(`
                        <tr>
                            <td colspan="12" class="text-center text-danger">
                                <i class="fas fa-exclamation-triangle"></i> ${response.message || 'No data found'}
                            </td>
                        </tr>
                    `);
                }
                toggleLoading(false);
            },
            error: function (xhr, status, error) {
                console.error('Error:', error);
                alert('Error loading data');
                $('#supertrend-tbody').html(`
                    <tr>
                        <td colspan="12" class="text-center text-danger">
                            <i class="fas fa-exclamation-triangle"></i> Error loading data. Please try again.
                        </td>
                    </tr>
                `);
                toggleLoading(false);
            }
        });
    }

    // Display table function - now shows symbol and persistent BUY/SELL
    function displaySupertrendTable(results) {
        let html = '';

        results.forEach(function (row) {
            const directionClass = row.direction === 'UP' ? 'uptrend' : 'downtrend';
            const signalClass = row.signal === 'BUY' ? 'badge-buy' : row.signal === 'SELL' ? 'badge-sell' : 'badge-hold';
            const signalIcon = row.signal === 'BUY' ? '▲' : row.signal === 'SELL' ? '▼' : '→';

            html += `
                <tr>
                    <td><strong>${row.date}</strong></td>
                    <td><strong>${row.symbol}</strong></td>
                    <td>${row.open}</td>
                    <td>${row.high}</td>
                    <td>${row.low}</td>
                    <td><strong>${row.close}</strong></td>
                    <td>${row.volume}</td>
                    <td>${row.atr}</td>
                    <td>${row.basicUpperBand}</td>
                    <td>${row.basicLowerBand}</td>
                    <td><strong>${row.supertrend}</strong></td>
                    <td><span class="${directionClass}">${row.direction}</span></td>
                    <td><span class="badge ${signalClass}">${signalIcon} ${row.signal}</span></td>
                </tr>
            `;
        });

        $('#supertrend-tbody').html(html);
    }

    function updateStatistics(results) {
        if (!results || results.length === 0) {
            $('#total_records').text(0);
            $('#buy_signals').text(0);
            $('#sell_signals').text(0);
            $('#current_trend').html('N/A');
            return;
        }

        const buyCount = results.filter(r => r.signal === 'BUY').length;
        const sellCount = results.filter(r => r.signal === 'SELL').length;
        const lastRecord = results[results.length - 1];
        const currentTrend = lastRecord && lastRecord.direction === 'UP' ? '📈 Uptrend' : '📉 Downtrend';

        $('#total_records').text(results.length);
        $('#buy_signals').text(buyCount);
        $('#sell_signals').text(sellCount);
        $('#current_trend').html(currentTrend);
    }

    $(document).ready(function () {
        $('#apply_filter').click(function () {
            loadSupertrendData();
        });

        $('#underlying_filter').change(function () {
            if ($(this).val()) {
                loadSupertrendData();
            }
        });

        $(document).keydown(function (e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                loadSupertrendData();
            }
        });
    });
</script>
@endpush