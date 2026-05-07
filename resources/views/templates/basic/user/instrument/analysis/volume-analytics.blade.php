@extends($activeTemplate . 'layouts.master')
@section('content')
@push('style')
<style>
    .filter-container {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .filter-row {
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }
    .filter-item {
        display: flex;
        flex-direction: column;
        min-width: 150px;
    }
    .filter-item label {
        font-weight: 600;
        margin-bottom: 5px;
    }
    .result-card {
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .result-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #eee;
    }
    .underlying-name {
        font-size: 20px;
        font-weight: bold;
        color: #333;
    }
    .signal-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-weight: bold;
        font-size: 12px;
    }
    .bullish { background: #28a745; color: white; }
    .bearish { background: #dc3545; color: white; }
    .neutral { background: #6c757d; color: white; }
    .volume-bar {
        display: flex;
        height: 30px;
        border-radius: 5px;
        overflow: hidden;
        margin: 10px 0;
    }
    .ce-bar {
        background: #28a745;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        font-weight: bold;
    }
    .pe-bar {
        background: #dc3545;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 12px;
        font-weight: bold;
    }
    .metric-box {
        padding: 10px;
        background: #f8f9fa;
        border-radius: 5px;
        text-align: center;
    }
    .metric-label {
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
    }
    .metric-value {
        font-size: 18px;
        font-weight: bold;
        margin-top: 5px;
    }
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <h4 class="mb-4">{{ $pageTitle }}</h4>

        <!-- Filters -->
        <div class="filter-container">
            <div class="filter-row">
                <div class="filter-item">
                    <label class="text-dark">Date Filter:</label>
                    <input type="date" id="dateFilter" class="form-control">
                </div>
                
                <div class="filter-item">
                    <label class="text-dark">Symbol:</label>
                    <select id="symbolFilter" class="form-select">
                        <option value="all">All Symbols</option>
                        @foreach($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-item">
                    <label class="text-dark">Signal:</label>
                    <select id="tradeType" class="form-select">
                        <option value="all">All Signals</option>
                        <option value="Bullish">Bullish</option>
                        <option value="Bearish">Bearish</option>
                        <option value="Neutral">Neutral</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label class="text-dark">Min Strength Score:</label>
                    <input type="number" id="strengthScore" class="form-control" placeholder="e.g. 30" min="0" max="100">
                </div>

                <div class="filter-item">
                    <label class="text-dark">Search:</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Search...">
                </div>

                <div class="filter-item">
                    <label class="text-dark">&nbsp;</label>
                    <button id="applyFilters" class="btn btn--base">Analyze</button>
                </div>

                <div class="filter-item">
                    <label class="text-dark">&nbsp;</label>
                    <button id="clearFilters" class="btn btn-secondary">Clear</button>
                </div>
            </div>
        </div>

        <!-- Results Container -->
        <div id="resultsContainer">
            <div class="text-center">
                <p class="text-muted">Click "Analyze" to view volume-based analytics</p>
            </div>
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
$(document).ready(function() {
    // Load initial data
    fetchAnalysis();

    $('#applyFilters, #clearFilters').on('click', function() {
        if ($(this).attr('id') === 'clearFilters') {
            $('#dateFilter, #symbolFilter, #tradeType, #strengthScore, #searchFilter').val('');
            $('#symbolFilter, #tradeType').val('all');
        }
        fetchAnalysis();
    });

    function fetchAnalysis() {
        $('#resultsContainer').html(`
            <div class="text-center">
                <div class="spinner-border" role="status"></div>
                <p class="mt-2">Analyzing volume data...</p>
            </div>
        `);

        $.post("{{ route('user.instrument.volume.analytics.fetch') }}", {
            _token: '{{ csrf_token() }}',
            date_filter: $('#dateFilter').val(),
            symbol_filter: $('#symbolFilter').val(),
            trade_type: $('#tradeType').val(),
            strength_score: $('#strengthScore').val(),
            search_term: $('#searchFilter').val()
        }, function(response) {
            if (response.status === 'error') {
                $('#resultsContainer').html(`
                    <div class="alert alert-warning">${response.message}</div>
                `);
                return;
            }

            if (response.data.length === 0) {
                $('#resultsContainer').html(`
                    <div class="alert alert-info">No results found for selected criteria.</div>
                `);
                return;
            }

            let html = '';
            response.data.forEach(item => {
                const signalClass = item.signal.toLowerCase();
                const totalVolume = item.total_ce_volume + item.total_pe_volume;
                const ceWidth = totalVolume > 0 ? (item.total_ce_volume / totalVolume * 100) : 50;
                const peWidth = totalVolume > 0 ? (item.total_pe_volume / totalVolume * 100) : 50;
                
                html += `
                    <div class="result-card">
                        <div class="result-header">
                            <div>
                                <div class="underlying-name">${item.underlying}</div>
                                <small class="text-muted">${item.date}</small>
                            </div>
                            <span class="signal-badge ${signalClass}">${item.signal}</span>
                        </div>
                        
                        <div class="volume-bar">
                            <div class="ce-bar" style="width: ${ceWidth}%">
                                CE: ${formatNumber(item.total_ce_volume)}
                            </div>
                            <div class="pe-bar" style="width: ${peWidth}%">
                                PE: ${formatNumber(item.total_pe_volume)}
                            </div>
                        </div>
                        
                        <div class="metrics-grid">
                            <div class="metric-box">
                                <div class="metric-label">CE Volume</div>
                                <div class="metric-value" style="color: #28a745;">
                                    ${formatNumber(item.total_ce_volume)}
                                </div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-label">PE Volume</div>
                                <div class="metric-value" style="color: #dc3545;">
                                    ${formatNumber(item.total_pe_volume)}
                                </div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-label">CE/PE Ratio</div>
                                <div class="metric-value text-dark">${item.volume_ratio}</div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-label">Strength Score</div>
                                <div class="metric-value text-dark">${item.strength_score}/100</div>
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#resultsContainer').html(html);
        }).fail(function() {
            $('#resultsContainer').html(`
                <div class="alert alert-danger">Error loading analysis. Please try again.</div>
            `);
        });
    }

    function formatNumber(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(2) + 'M';
        } else if (num >= 1000) {
            return (num / 1000).toFixed(2) + 'K';
        }
        return num.toFixed(0);
    }
});
</script>
@endpush