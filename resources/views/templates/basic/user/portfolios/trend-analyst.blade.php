@extends($activeTemplate . 'layouts.master')

@section('content')
    <div class="container">
        <h2 class="mb-4">3-Day Trend Analysis</h2>

        {{-- Filter Form --}}
        <form id="trendFilterForm" class="row g-3 mb-4">
            <div class="col-md-3">
                <label for="date_filter" class="form-label">Select Date</label>
                <input type="date" id="date_filter" name="date_filter" class="form-control"
                    value="{{ now()->toDateString() }}">
            </div>
            <div class="col-md-3">
                <label for="symbol_filter" class="form-label">Symbol</label>
                <input type="text" id="symbol_filter" name="symbol_filter" class="form-control"
                    placeholder="all or NIFTY">
            </div>
            <div class="col-md-2">
                <label for="trade_type" class="form-label">Sentiment</label>
                <select id="trade_type" name="trade_type" class="form-control">
                    <option value="all">All</option>
                    <option value="Strong Bullish">Strong Bullish</option>
                    <option value="Bullish Breakout Possible">Bullish Breakout Possible</option>
                    <option value="Neutral">Neutral</option>
                    <option value="Bearish Breakout Possible">Bearish Breakout Possible</option>
                    <option value="Strong Bearish">Strong Bearish</option>
                </select>
            </div>
            <div class="col-md-2">
                <label for="strength_score" class="form-label">Strength Score</label>
                <select id="strength_score" name="strength_score" class="form-control">
                    <option value="all">All</option>
                    <option value="gt2">> 2</option>
                    <option value="lt-2">
                        < -2</option>
                    <option value="between">Between -1 & 1</option>
                </select>
            </div>
            <div class="col-md-2">
                <label>OI Change</label>
                <select id="oi_change" class="form-select">
                    <option value="all">Select Type</option>
                    <option value="ce_low_to_high">CE - Low to High</option>
                    <option value="ce_high_to_low">CE - High to Low</option>
                    <option value="pe_low_to_high">PE - Low to High</option>
                    <option value="pe_high_to_low">PE - High to Low</option>
                    <option value="fut_low_to_high">FUT - Low to High</option>
                    <option value="fut_high_to_low">FUT - High to Low</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" id="fetchDataBtn" class="btn btn-success btn-sm">Fetch Data</button>
            </div>
        </form>

        {{-- Table --}}
        <div class="table-responsive">
            <table id="trendTable" class="table custom--table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Symbol</th>
                        <th>Future OI</th>
                        <th>CE OI</th>
                        <th>PE OI</th>
                        <th>Signal</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection

@push('script')
    <script>
        function fetchTrendData() {
            let formData = {
                date_filter: $('#date_filter').val(),
                symbol_filter: $('#symbol_filter').val(),
                trade_type: $('#trade_type').val(),
                oi_change: $('#oi_change').val(),
                strength_score: $('#strength_score').val(),
                _token: '{{ csrf_token() }}'
            };

            $.ajax({
                url: "{{ route('user.trend-fetch') }}",
                method: "POST",
                data: formData,
                beforeSend: function() {
                    $('#trendTable tbody').html('<tr><td colspan="7" class="text-center">Loading...</td></tr>');
                    $('#fetchDataBtn').prop('disabled', true).text('Loading...');
                },
                success: function(response) {
                    let rows = '';

                    if (response.status === 'success' && response.positions && response.positions.length > 0) {
                        response.positions.forEach(function(item) {
                            let badgeClass = '';
                            switch (item.signal) {
                                case 'Strong Bullish':
                                    badgeClass = 'bg-success text-white';
                                    break;
                                case 'Bullish Breakout Possible':
                                    badgeClass = 'bg-light text-success border border-success';
                                    break;
                                case 'Strong Bearish':
                                    badgeClass = 'bg-danger text-white';
                                    break;
                                case 'Bearish Breakout Possible':
                                    badgeClass = 'bg-light text-danger border border-danger';
                                    break;
                                default:
                                    badgeClass = 'bg-secondary text-white';
                            }

                            rows += `
                        <tr>
                            <td>${item.date}</td>
                            <td>${item.symbol_name}</td>
                            <td>${item.future_oi_sum}</td>
                            <td>${item.ce_oi_sum}</td>
                            <td>${item.pe_oi_sum}</td>
                            <td><span class="badge ${badgeClass}">${item.signal}</span></td>
                            <td>${item.score}</td>
                        </tr>
                    `;
                        });
                    } else {
                        let message = response.message || 'No Data Found';
                        rows = `<tr><td colspan="7" class="text-center">${message}</td></tr>`;
                    }

                    $('#trendTable tbody').html(rows);
                },
                error: function(xhr) {
                    let errorMessage = xhr.responseJSON?.message || 'Error fetching data';
                    $('#trendTable tbody').html(
                        `<tr><td colspan="7" class="text-center text-danger">${errorMessage}</td></tr>`
                    );
                },
                complete: function() {
                    $('#fetchDataBtn').prop('disabled', false).text('Fetch Data');
                }
            });
        }

        $(document).ready(function() {
            fetchTrendData();
            $('#trendFilterForm').on('submit', function(e) {
                e.preventDefault();
                fetchTrendData();
            });
            $('#fetchDataBtn').on('click', fetchTrendData);
            $('#date_filter, #symbol_filter, #trade_type, #oi_change, #strength_score').on('change',
            fetchTrendData);
        });
    </script>
@endpush