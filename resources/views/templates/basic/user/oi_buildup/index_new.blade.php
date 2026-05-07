@extends($activeTemplate . 'layouts.master')

@section('content')
@push('style')
<style>
    .custom--table thead th,
    .custom--table tbody td {
        text-align: left !important;
        padding: 5px !important;
    }
</style>
@endpush

<section class="pt-50 pb-50">
    <div class="container content-container">
        <h4 class="mb-4">{{ $pageTitle }}</h4>

        <div class="table-responsive">
            <table class="table custom--table">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>LTP</th>
                        <th>LTP Change (%)</th>
                        <th>OI Change</th>
                        <th>OI Category</th>
                        <th>PCR</th>
                        <th>Volume Trend</th>
                        <th>Signal</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                     @foreach($results as $row)
            <tr>
                <td>{{ $row->symbol }}</td>
                <td>{{ $row->ltp }}</td>
                <td>{{ $row->ltp_change }}%</td>
                <td>{{ $row->oi_change }}</td>
                <td>{{ $row->oi_category }}</td>
                <td>{{ $row->pcr }}</td>
                <td>{{ $row->volume_trend }}</td>
                <td><strong>{{ $row->signal }}</strong></td>
                <td>{{ $row->score }}</td>
            </tr>
            @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection