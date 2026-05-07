@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .trend-bullish { background: #28a745; color: white; padding: 4px 8px; border-radius: 4px; }
    .trend-bearish { background: #dc3545; color: white; padding: 4px 8px; border-radius: 4px; }
    .trend-neutral { background: #6c757d; color: white; padding: 4px 8px; border-radius: 4px; }
    .filter-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 25px;
    }
    .filter-label text-dark {
        font-weight: 600;
        margin-bottom: 5px;
        font-size: 13px;
    }
    .consensus-badge {
        display: inline-block;
        padding: 8px 12px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 12px;
    }
    .consensus-all-bullish { background: #d4edda; color: #155724; }
    .consensus-all-bearish { background: #f8d7da; color: #721c24; }
    .consensus-mixed { background: #fff3cd; color: #856404; }
    .strength-bar {
        height: 6px;
        background: #e9ecef;
        border-radius: 3px;
        overflow: hidden;
    }
    .strength-fill {
        height: 100%;
        background: linear-gradient(90deg, #28a745, #ffc107, #dc3545);
        transition: width 0.3s ease;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container-fluid content-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="mb-0">{{ $pageTitle }}</h4>
            <div>
                <span class="badge bg-info text-white me-2">Combined Analytics</span>
                <button id="reloadData" class="btn btn-sm btn--base"><i class="las la-sync"></i> Reload</button>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h6 class="mb-3 text-dark"><i class="las la-filter"></i> Advanced Filters</h6>
            <div class="row g-3">
                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">Date:</label>
                    <input type="date" id="dateFilter" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                </div>
                
                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">Symbol:</label>
                    <select id="symbolFilter" class="form-select form-select-sm">
                        <option value="all">All Symbols</option>
                        @foreach ($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">Search:</label>
                    <input type="text" id="searchTerm" class="form-control form-control-sm" placeholder="Symbol...">
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">Hist Trend:</label>
                    <select id="histTrendFilter" class="form-select form-select-sm">
                        <option value="all">All</option>
                        <option value="Strong Bullish">Strong Bullish</option>
                        <option value="Mild Bullish">Mild Bullish</option>
                        <option value="Neutral / Sideways">Neutral</option>
                        <option value="Mild Bearish">Mild Bearish</option>
                        <option value="Strong Bearish">Strong Bearish</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">New OI Sentiment:</label>
                    <select id="sentimentFilter" class="form-select form-select-sm">
                        <option value="all">All</option>
                        <option value="Bullish">Bullish</option>
                        <option value="Bearish">Bearish</option>
                        <option value="Neutral">Neutral</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">Vol Signal:</label>
                    <select id="volSignalFilter" class="form-select form-select-sm">
                        <option value="all">All</option>
                        <option value="Bullish">Bullish</option>
                        <option value="Bearish">Bearish</option>
                        <option value="Neutral">Neutral</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">Min OI Strength:</label>
                    <input type="number" id="minHistStrength" class="form-control form-control-sm" placeholder="e.g. 20">
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">Min Vol Strength:</label>
                    <input type="number" id="minVolStrength" class="form-control form-control-sm" placeholder="e.g. 15">
                </div>

                <div class="col-lg-2 col-md-4">
                    <label class="filter-label text-dark">Consensus:</label>
                    <select id="consensusFilter" class="form-select form-select-sm">
                        <option value="all">All</option>
                        <option value="all_bullish">All Bullish ✅</option>
                        <option value="all_bearish">All Bearish ❌</option>
                        <option value="mixed">Mixed Signals 🔀</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn--base btn-sm w-100" id="applyFilters">
                        <i class="las la-search"></i> Apply Filters
                    </button>
                </div>

                <div class="col-lg-2 col-md-4 d-flex align-items-end">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="resetFilters">
                        <i class="las la-redo-alt"></i> Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4" id="summaryCards" style="display: none;">
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-success mb-2" id="totalBullish">0</h3>
                        <p class="mb-0 text-dark">Total Bullish</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-danger mb-2" id="totalBearish">0</h3>
                        <p class="mb-0 text-dark">Total Bearish</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-warning mb-2" id="totalMixed">0</h3>
                        <p class="mb-0 text-dark">Mixed Signals</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card">
                    <div class="card-body text-center">
                        <h3 class="text-info mb-2" id="totalRecords">0</h3>
                        <p class="mb-0 text-dark">Total Records</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="custom--card card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="las la-chart-line"></i> Unified Options Analysis</span>
                <span class="badge bg-secondary" id="recordCount">0 records</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table custom--table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Symbol</th>
                                <th>Historical Trend</th>
                                <th>New OI Sentiment</th>
                                <th>New OI Strength</th>
                                <th>Vol Signal</th>
                                <th>Vol Strength</th>
                                <th>Consensus</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="las la-spinner la-spin la-2x"></i><br>Loading data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
$(document).ready(function() {
    let lastFetchedData = [];

    // Initial load
    fetchUnifiedData();

    // Event listeners
    $('#applyFilters, #reloadData').on('click', fetchUnifiedData);
    $('#resetFilters').on('click', resetAllFilters);

    function resetAllFilters() {
        $('#dateFilter').val('{{ now()->toDateString() }}');
        $('#symbolFilter').val('all');
        $('#searchTerm').val('');
        $('#histTrendFilter').val('all');
        $('#sentimentFilter').val('all');
        $('#volSignalFilter').val('all');
        $('#minHistStrength').val('');
        $('#minVolStrength').val('');
        $('#consensusFilter').val('all');
        fetchUnifiedData();
    }

    function fetchUnifiedData() {
        const filters = {
            _token: '{{ csrf_token() }}',
            date_filter: $('#dateFilter').val() || 'all',
            symbol_filter: $('#symbolFilter').val(),
            search_term: $('#searchTerm').val(),
            hist_trend: $('#histTrendFilter').val(),
            sentiment: $('#sentimentFilter').val(),
            vol_signal: $('#volSignalFilter').val(),
            min_hist_strength: $('#minHistStrength').val(),
            min_vol_strength: $('#minVolStrength').val(),
            consensus: $('#consensusFilter').val()
        };

        $('#tableBody').html(`
            <tr>
                <td colspan="9" class="text-center py-4">
                    <i class="las la-spinner la-spin la-2x"></i><br>Loading unified analysis...
                </td>
            </tr>
        `);

        $.ajax({
            url: "{{ route('user.unified.analysis.fetch') }}",
            type: "POST",
            data: filters,
            success: function(res) {
                if (res.status === 'success' && res.data.length > 0) {
                    lastFetchedData = res.data;
                    renderTable(res.data);
                    updateSummaryCards(res.data);
                } else {
                    $('#tableBody').html(`
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                <i class="las la-info-circle la-2x"></i><br>
                                ${res.message || 'No data found for selected criteria.'}
                            </td>
                        </tr>
                    `);
                    $('#recordCount').text('0 records');
                    $('#summaryCards').hide();
                }
            },
            error: function(xhr) {
                $('#tableBody').html(`
                    <tr>
                        <td colspan="9" class="text-center text-danger py-4">
                            <i class="las la-exclamation-triangle la-2x"></i><br>
                            Error loading data. Please try again.
                        </td>
                    </tr>
                `);
            }
        });
    }

    function renderTable(data) {
        let html = '';
        
        data.forEach((row, index) => {
            const consensus = getConsensus(row);
            
            html += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${row.date}</td>
                    <td><strong>${row.underlying}</strong></td>
                    <td><span class="badge ${getTrendClass(row.hist_trend)}">${row.hist_trend}</span></td>
                    <td><span class="badge ${getTrendClass(row.sentiment)}">${row.sentiment}</span></td>
                    <td>
                        <div>${row.hist_strength}%</div>
                        <div class="strength-bar mt-1">
                            <div class="strength-fill" style="width: ${Math.min(row.hist_strength, 100)}%"></div>
                        </div>
                    </td>
                    <td><span class="badge ${getTrendClass(row.vol_signal)}">${row.vol_signal}</span></td>
                    <td>
                        <div>${row.vol_strength}%</div>
                        <div class="strength-bar mt-1">
                            <div class="strength-fill" style="width: ${Math.min(row.vol_strength, 100)}%"></div>
                        </div>
                    </td>
                    <td><span class="consensus-badge ${consensus.class}">${consensus.text}</span></td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
        $('#recordCount').text(`${data.length} records`);
    }

    function updateSummaryCards(data) {
        let bullish = 0, bearish = 0, mixed = 0;

        data.forEach(row => {
            const consensus = getConsensus(row);
            if (consensus.text === 'All Bullish') bullish++;
            else if (consensus.text === 'All Bearish') bearish++;
            else mixed++;
        });

        $('#totalBullish').text(bullish);
        $('#totalBearish').text(bearish);
        $('#totalMixed').text(mixed);
        $('#totalRecords').text(data.length);
        $('#summaryCards').show();
    }

    function getConsensus(row) {
        const isBullishHist = row.hist_trend.toLowerCase().includes('bullish');
        const isBearishHist = row.hist_trend.toLowerCase().includes('bearish');
        const isBullishSent = row.sentiment.toLowerCase().includes('bullish');
        const isBearishSent = row.sentiment.toLowerCase().includes('bearish');
        const isBullishVol = row.vol_signal.toLowerCase() === 'bullish';
        const isBearishVol = row.vol_signal.toLowerCase() === 'bearish';

        if (isBullishHist && isBullishSent && isBullishVol) {
            return { text: 'All Bullish', class: 'consensus-all-bullish' };
        }
        if (isBearishHist && isBearishSent && isBearishVol) {
            return { text: 'All Bearish', class: 'consensus-all-bearish' };
        }
        return { text: 'Mixed', class: 'consensus-mixed' };
    }

    function getTrendClass(text) {
        const lower = text.toLowerCase();
        if (lower.includes('bullish')) return 'trend-bullish';
        if (lower.includes('bearish')) return 'trend-bearish';
        return 'trend-neutral';
    }
});
</script>
@endpush