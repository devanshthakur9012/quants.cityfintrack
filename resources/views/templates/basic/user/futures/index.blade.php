@extends($activeTemplate . 'layouts.master')
@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2>📊 Futures Trading Signals</h2>
            <p class="text-muted">VWAP + SuperTrend + Reaction Low Strategy (15-min timeframe)</p>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('futures.index') }}" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Symbol</label>
                    <select name="symbol" class="form-select">
                        <option value="">All Symbols</option>
                        @foreach($monitoredFutures as $future)
                            <option value="{{ $future->trading_symbol }}" 
                                {{ request('symbol') == $future->trading_symbol ? 'selected' : '' }}>
                                {{ $future->trading_symbol }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Signal</label>
                    <select name="signal" class="form-select">
                        <option value="">All</option>
                        <option value="BUY" {{ request('signal') == 'BUY' ? 'selected' : '' }}>BUY</option>
                        <option value="SELL" {{ request('signal') == 'SELL' ? 'selected' : '' }}>SELL</option>
                        <option value="NO_TRADE" {{ request('signal') == 'NO_TRADE' ? 'selected' : '' }}>NO_TRADE</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" class="form-control" 
                           value="{{ request('from_date', '2025-12-20') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" class="form-control" 
                           value="{{ request('to_date', date('Y-m-d')) }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Filter
                        </button>
                        <a href="{{ route('futures.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </a>
                        <a href="{{ route('futures.export') }}?{{ http_build_query(request()->all()) }}" 
                           class="btn btn-success">
                            <i class="bi bi-download"></i> Export
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>Timestamp</th>
                            <th>Symbol</th>
                            <th>Open</th>
                            <th>High</th>
                            <th>Low</th>
                            <th>Close</th>
                            <th>Volume</th>
                            <th>OI</th>
                            <th>Signal</th>
                            <th>Trend</th>
                            <th>Entry</th>
                            <th>SL</th>
                            <th>Target</th>
                            <th>VWAP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($futuresData as $data)
                        <tr>
                            <td>{{ $data->timestamp->format('Y-m-d H:i') }}</td>
                            <td><strong>{{ $data->trading_symbol }}</strong></td>
                            <td>{{ number_format($data->open, 2) }}</td>
                            <td>{{ number_format($data->high, 2) }}</td>
                            <td>{{ number_format($data->low, 2) }}</td>
                            <td>{{ number_format($data->close, 2) }}</td>
                            <td>{{ number_format($data->volume) }}</td>
                            <td>{{ number_format($data->oi) }}</td>
                            <td>
                                @if($data->signal == 'BUY')
                                    <span class="badge bg-success">{{ $data->signal }}</span>
                                @elseif($data->signal == 'SELL')
                                    <span class="badge bg-danger">{{ $data->signal }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ $data->signal }}</span>
                                @endif
                            </td>
                            <td>
                                @if($data->trend == 'UP')
                                    <span class="badge bg-info">{{ $data->trend }}</span>
                                @elseif($data->trend == 'DOWN')
                                    <span class="badge bg-warning">{{ $data->trend }}</span>
                                @else
                                    <span class="badge bg-light text-dark">{{ $data->trend }}</span>
                                @endif
                            </td>
                            <td>{{ $data->entry ? number_format($data->entry, 2) : '-' }}</td>
                            <td>{{ $data->sl ? number_format($data->sl, 2) : '-' }}</td>
                            <td>{{ $data->target ? number_format($data->target, 2) : '-' }}</td>
                            <td>{{ $data->vwap ? number_format($data->vwap, 2) : '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="14" class="text-center text-muted">
                                No data found. Run: php artisan futures:fetch-historical --from=2025-12-20 --to=2026-01-09
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="mt-3">
                {{ $futuresData->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

<style>
    .table th {
        white-space: nowrap;
    }
    .table td {
        font-size: 0.9rem;
        color:#000;
    }
</style>
@endsection