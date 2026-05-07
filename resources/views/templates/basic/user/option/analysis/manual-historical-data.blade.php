@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .manual-form label { font-weight: 600; margin-bottom: 5px; font-size: 13px; }
    .manual-form .form-control { font-size: 13px; }
    .modal-header { background-color: #f8f9fa; }
    
    .strike-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 15px;
        border-left: 4px solid #007bff;
    }
    
    .strike-section h6 {
        margin-bottom: 12px;
        color: #333;
        font-weight: 700;
    }

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
        color: #333;
    }

    .trend-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
    }

    .trend-strong-bullish { background: #28a745; color: white; }
    .trend-bullish-breakout { background: #b8f7b0; color: #0b5137; }
    .trend-bearish-breakout { background: #f7b0b0; color: #58151c; }
    .trend-neutral { background: #6c757d; color: white; }
    .trend-strong-bearish { background: #dc3545; color: white; }

    .positive { color: #28a745; font-weight: bold; }
    .negative { color: #dc3545; font-weight: bold; }
    .zero { color: #6c757d; }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container">
        <h4 class="mb-4">{{ $pageTitle }}</h4>

        <button class="btn btn--base mb-3" data-bs-toggle="modal" data-bs-target="#manualDataModal">
            <i class="las la-plus"></i> Add Manual OI Data
        </button>

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
                        @foreach ($symbols as $symbol)
                            <option value="{{ $symbol }}">{{ $symbol }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-item">
                    <label class="text-dark">Sentiment:</label>
                    <select id="sentimentFilter" class="form-select">
                        <option value="all">All Sentiments</option>
                        <option value="Strong Bullish">Strong Bullish</option>
                        <option value="Strong Bearish">Strong Bearish</option>
                        <option value="Bullish Breakout Possible">Bullish Breakout Possible</option>
                        <option value="Bearish Breakout Possible">Bearish Breakout Possible</option>
                        <option value="Neutral">Neutral</option>
                    </select>
                </div>

                <div class="filter-item">
                    <label class="text-dark">Strength Score (≥):</label>
                    <input type="number" id="strengthScoreFilter" class="form-control" placeholder="e.g. 1.5">
                </div>

                <div class="filter-item">
                    <label class="text-dark">Search:</label>
                    <input type="text" id="searchFilter" class="form-control" placeholder="Search symbols...">
                </div>

                <div class="filter-item">
                    <label class="text-dark">&nbsp;</label>
                    <button id="applyFilters" class="btn btn--base">Apply Filters</button>
                </div>

                <div class="filter-item">
                    <label class="text-dark">&nbsp;</label>
                    <button id="clearFilters" class="btn btn-secondary">Clear</button>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="custom--card card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-bold">Manual Options Analysis Results</span>
                <button class="btn btn-sm btn--base reload-btn rounded-pill" id="reloadData">
                    <i class="las la-sync"></i> Reload
                </button>
            </div>
            <div class="card-body" id="manualDataTable">
                <div class="text-center p-4">
                    <div class="spinner-border"></div>
                    <p class="mt-2">Loading data...</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Modal for Manual Data Entry -->
<div class="modal fade" id="manualDataModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold text-dark">Add Manual Option OI Data</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="manualDataForm" class="manual-form">
          @csrf
          
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <label class="text-dark">Underlying Symbol *</label>
              <select name="underlying" class="form-select" required>
                <option value="">Select Symbol</option>
                @foreach($symbols as $s)
                  <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6">
              <label class="text-dark">Date *</label>
              <input type="date" name="date" class="form-control" required>
            </div>
          </div>

          <!-- ATM-2 -->
          <div class="strike-section">
            <h6>🔻 ATM-2 Strike</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="text-dark">CE Open Interest</label>
                <input type="number" step="0.01" name="atm_minus_2_ce_oi" class="form-control" placeholder="e.g. 252000">
              </div>
              <div class="col-md-6">
                <label class="text-dark">PE Open Interest</label>
                <input type="number" step="0.01" name="atm_minus_2_pe_oi" class="form-control" placeholder="e.g. 189000">
              </div>
            </div>
          </div>

          <!-- ATM-1 -->
          <div class="strike-section">
            <h6>🔻 ATM-1 Strike</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="text-dark">CE Open Interest</label>
                <input type="number" step="0.01" name="atm_minus_1_ce_oi" class="form-control" placeholder="e.g. 321000">
              </div>
              <div class="col-md-6">
                <label class="text-dark">PE Open Interest</label>
                <input type="number" step="0.01" name="atm_minus_1_pe_oi" class="form-control" placeholder="e.g. 434000">
              </div>
            </div>
          </div>

          <!-- ATM -->
          <div class="strike-section">
            <h6>🎯 ATM Strike</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="text-dark">CE Open Interest</label>
                <input type="number" step="0.01" name="atm_ce_oi" class="form-control" placeholder="e.g. 546000">
              </div>
              <div class="col-md-6">
                <label class="text-dark">PE Open Interest</label>
                <input type="number" step="0.01" name="atm_pe_oi" class="form-control" placeholder="e.g. 459000">
              </div>
            </div>
          </div>

          <!-- ATM+1 -->
          <div class="strike-section">
            <h6>🔺 ATM+1 Strike</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="text-dark">CE Open Interest</label>
                <input type="number" step="0.01" name="atm_plus_1_ce_oi" class="form-control" placeholder="e.g. 389000">
              </div>
              <div class="col-md-6">
                <label class="text-dark">PE Open Interest</label>
                <input type="number" step="0.01" name="atm_plus_1_pe_oi" class="form-control" placeholder="e.g. 678000">
              </div>
            </div>
          </div>

          <!-- ATM+2 -->
          <div class="strike-section">
            <h6>🔺 ATM+2 Strike</h6>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="text-dark">CE Open Interest</label>
                <input type="number" step="0.01" name="atm_plus_2_ce_oi" class="form-control" placeholder="e.g. 590000">
              </div>
              <div class="col-md-6">
                <label class="text-dark">PE Open Interest</label>
                <input type="number" step="0.01" name="atm_plus_2_pe_oi" class="form-control" placeholder="e.g. 670000">
              </div>
            </div>
          </div>

          <div class="mt-4 text-end">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn--base">Save & Analyze</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

@push('script')
<script>
$(function(){
    loadManualData();

    // Initial load
    $('#applyFilters').on('click', function() {
        loadManualData();
    });

    $('#clearFilters').on('click', function() {
        $('#dateFilter').val('');
        $('#symbolFilter').val('all');
        $('#sentimentFilter').val('all');
        $('#strengthScoreFilter').val('');
        $('#searchFilter').val('');
        loadManualData();
    });

    $('#reloadData').on('click', function() {
        loadManualData();
    });

    $('#searchFilter').on('keypress', function(e) {
        if (e.which == 13) loadManualData();
    });

    // Submit form
    $('#manualDataForm').on('submit', function(e){
        e.preventDefault();
        
        $.post('{{ route('user.manual.historical.store') }}', $(this).serialize(), function(res){
            if(res.status === 'success'){
                iziToast.success({
                  message: res.message,
                  position: "topRight"
                });
                $('#manualDataModal').modal('hide');
                $('#manualDataForm')[0].reset();
                loadManualData();
            } else {
                iziToast.error({
                  message: 'Failed to add data.',
                  position: "topRight"
                });
            }
        }).fail(function(xhr){
            if(xhr.responseJSON && xhr.responseJSON.errors) {
                let errors = xhr.responseJSON.errors;
                Object.keys(errors).forEach(key => {
                  iziToast.error({
                    message: errors[key][0],
                    position: "topRight"
                  });
                });
            } else {
                iziToast.error({
                  message: 'Error saving data.',
                  position: "topRight"
                });
            }
        });
    });

    function loadManualData(){
        $('#manualDataTable').html(`
            <div class="text-center p-4">
                <div class="spinner-border"></div>
                <p class="mt-2">Analyzing data...</p>
            </div>
        `);

        const filters = {
            _token: '{{ csrf_token() }}',
            date_filter: $('#dateFilter').val(),
            symbol_filter: $('#symbolFilter').val(),
            trade_type: $('#sentimentFilter').val(),
            search_term: $('#searchFilter').val(),
            strength_score: $('#strengthScoreFilter').val(),
        };

        $.post('{{ route('user.manual.historical.fetch') }}', filters, function(res){
            if(res.status === 'success'){
                let html = `
                    <div class="table-responsive">
                        <table class="custom--table table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Underlying</th>
                                    <th>Avg CE OI % Chg</th>
                                    <th>Avg PE OI % Chg</th>
                                    <th>Sentiment</th>
                                    <th>Pattern</th>
                                    <th>Strength Score</th>
                                    <th>Support Zone</th>
                                    <th>Resistance Zone</th>
                                </tr>
                            </thead><tbody>`;

                if (res.data.length > 0) {
                    res.data.forEach(row => {
                        let trendClass = getTrendClass(row.sentiment);
                        html += `<tr>
                            <td>${row.date || '-'}</td>
                            <td><strong>${row.underlying || '-'}</strong></td>
                            <td class="${getColor(row.avg_ce_oi_change)}">${formatPercent(row.avg_ce_oi_change)}</td>
                            <td class="${getColor(row.avg_pe_oi_change)}">${formatPercent(row.avg_pe_oi_change)}</td>
                            <td><span class="trend-badge ${trendClass}">${row.sentiment}</span></td>
                            <td style="font-size:11px;">${row.pattern || '-'}</td>
                            <td class="${getColor(row.strength_score)}">${row.strength_score.toFixed(2)}</td>
                            <td style="font-size:11px;">${row.support_zone || '-'}</td>
                            <td style="font-size:11px;">${row.resistance_zone || '-'}</td>
                        </tr>`;
                    });
                } else {
                    html += `<tr><td colspan="9" class="text-center text-muted">No Data Found</td></tr>`;
                }

                html += '</tbody></table></div>';
                $('#manualDataTable').html(html);
            } else {
                $('#manualDataTable').html(`
                    <div class="text-center text-danger p-4">
                        <i class="las la-exclamation-triangle la-3x"></i>
                        <p class="mt-2">${res.message}</p>
                    </div>
                `);
            }
        }).fail(function(){
            $('#manualDataTable').html(`
                <div class="text-center text-danger p-4">
                    <i class="las la-exclamation-triangle la-3x"></i>
                    <p class="mt-2">Error loading data. Please try again.</p>
                </div>
            `);
        });
    }

    // Helper functions
    function getTrendClass(sentiment) {
        const map = {
            'strong bullish': 'trend-strong-bullish',
            'bullish breakout possible': 'trend-bullish-breakout',
            'bearish breakout possible': 'trend-bearish-breakout',
            'neutral': 'trend-neutral',
            'neutral / unwinding': 'trend-neutral',
            'strong bearish': 'trend-strong-bearish'
        };
        return map[sentiment?.toLowerCase()] || 'trend-neutral';
    }

    function getColor(value) {
        if (value > 0) return 'positive';
        if (value < 0) return 'negative';
        return 'zero';
    }

    function formatPercent(value) {
        if (value == null) return '-';
        return (value > 0 ? '+' : '') + value.toFixed(2) + '%';
    }
});
</script>
@endpush
@endsection