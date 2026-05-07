<table class="custom--table table mb-0">
    <thead>
        <tr>
            <th style="padding:10px !important;">#</th>
            <th style="padding:10px !important;">Timestamp</th>
            <th style="padding:10px !important;">Symbol</th>
            <th style="padding:10px !important;">LTP</th>
            <th style="padding:10px !important;">% LTP Change</th>
            <th style="padding:10px !important;">OI</th>
            <th style="padding:10px !important;">OI Change</th>
            <th style="padding:10px !important;">OI Change %</th>
            <th style="padding:10px !important;">Signal</th>
            <th style="padding:10px !important;">Detail</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
        <tr>
            <td>{{ $loop->iteration }}</td>
            <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d M Y H:i') }}</td>
            <td>{{ $row->symbol }}</td>
            <td>{{ $row->ltp }}</td>
            <td>{{ $row->per_change }}</td>
            <td>{{ $row->oi }}</td>
            <td>{{ $row->oi_change }}</td>
            <td>{{ round(($row->oi_change / $row->oi) * 100, 2) }}%</td>
            <td>{{ ucfirst($row->oi_signal) }}</td>
            <td>
                <a href="{{ route('user.oi.buildup.detail', ['symbol' => $row->symbol, 'type' => $type]) }}" class="btn btn-sm btn-success">
                    <i class="las la-arrow-right"></i>
                </a>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="9" class="text-center">No Data Found</td>
        </tr>
        @endforelse
    </tbody>
</table>