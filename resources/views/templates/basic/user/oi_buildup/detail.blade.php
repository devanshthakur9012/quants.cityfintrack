@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-50 pb-50">
    <div class="container content-container">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-3">{{ $pageTitle }}</h4>
            <a href="{{ route('user.oi-buildup') }}" class="btn btn-sm btn-secondary mb-3">
                <i class="las la-arrow-left"></i> Back
            </a>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <strong>Type:</strong> {{ ucfirst($type) }} |
                <strong>Symbol:</strong> {{ $symbol }}
            </div>
            <form method="GET">
                <select name="filter" onchange="this.form.submit()" class="form-select form-select-sm">
                    <option value="today" {{ $filter === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="1d" {{ $filter === '1d' ? 'selected' : '' }}>Last 1 Day</option>
                    <option value="5d" {{ $filter === '5d' ? 'selected' : '' }}>Last 5 Days</option>
                    <!-- <option value="all" {{ $filter === 'all' ? 'selected' : '' }}>All Time</option> -->
                </select>
            </form>
        </div>

        <div class="custom--card card">
            <div class="card-body table-responsive p-0">
                <table class="custom--table table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>LTP</th>
                            <th>% Change</th>
                            <th>OI</th>
                            <th>OI Change</th>
                            <th>Signal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
                            <td>{{ $row->ltp }}</td>
                            <td>{{ $row->per_change }}</td>
                            <td>{{ $row->oi }}</td>
                            <td>{{ $row->oi_change }}</td>
                            <td>{{ ucfirst($row->oi_signal) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">No data available</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection