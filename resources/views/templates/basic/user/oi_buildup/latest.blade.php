@extends($activeTemplate . 'layouts.master')

@section('content')
<section class="pt-50 pb-50">
    <div class="container content-container">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="mb-3">{{ $pageTitle }}</h4>
            <div class="btn-box">
                <button class="btn btn-sm btn-primary">Total Investment : ₹89,000.00</button>
                <button class="btn btn-sm btn-success">Total Profit : ₹89,00.00</button>
                <button class="btn btn-sm btn-warning">Profit % : 40%</button>
            </div>
        </div>

        <div class="custom--card card">
            <div class="card-body table-responsive p-0">
                <table class="custom--table table mb-0" id="oi-buildup-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Timestamp</th>
                            <th>Symbol</th>
                            <th>LTP</th>
                            <th>Highest LTP</th>
                            <th>TXN Type</th>
                            <th>Lot Size</th>
                            <th>Buy Qty</th>
                            <th>Buy Price</th>
                            <th>Sell Qty</th>
                            <th>Sell Price</th>
                            <th>Total Value</th>
                            <th>Profit</th>
                            <th>Realised Profit</th>
                            <th>UnRealised Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $index = 1;
                            $lastCreatedAt = null;
                            $loadedSymbols = [];
                        @endphp
                        @foreach($oiBuildupData as $item)
                            <tr>
                                <td>{{ $index++ }}</td>
                                <td>{{ $item->inserted_at }}</td>
                                <td>{{ $item->option_symbol }}</td>
                                <td>{{ $item->ltp }}</td>
                                <td>{{ $item->ltp }}</td>
                                <td>
                                    @if(isset($item->txn_type) && $item->txn_type && $item->txn_type !== '---')
                                        {{ $item->txn_type }}
                                    @else
                                        <span class="text-muted">---</span>
                                    @endif
                                </td>
                                <td>{{ $item->lot_size ?? '---' }}</td>
                                <!-- <td>{{ $item->net_change }}</td>
                                <td>{{ $item->percent_change }}</td>
                                <td>{{ $item->open_interest }}</td>
                                <td>{{ $item->net_change_open_interest }}</td>
                                <td>{{ $item->buildup_type }}</td> -->
                                <!-- <td>{{ $item->option_symbol ?? '---' }}</td>
                                <td>{{ $item->option_token ?? '---' }}</td> -->
                                <td>{{ $item->buy_quantity ?? 0 }}</td>
                                <td>{{ $item->ltp ?? 0 }}</td>
                                <td>{{ $item->sell_qty ?? 0 }}</td>
                                <td>{{ $item->sell_price ?? 0 }}</td>
                                @php
                                    $totalValue = $item->buy_quantity*$item->ltp*$item->lot_size;
                                    $currentValue = $item->ltp*$item->buy_quantity*$item->lot_size;
                                    $profit = $currentValue - $totalValue;
                                @endphp
                                <td>{{ ($totalValue) ?? 0 }}</td>
                                <td>{{ $profit ?? 0 }}</td>
                                <td>{{ $item->realised_profit ?? 0 }}</td>
                                <td>{{ $item->unrealised_profit ?? 0 }}</td>
                            </tr>
                            @php
                                $lastCreatedAt = $item->inserted_at;
                                $loadedSymbols[] = $item->symbol;
                            @endphp
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@push('script')
<script>
    let lastLoadedTime = @json($lastCreatedAt ?? now()->toDateTimeString());
    let loadedSymbols = @json($loadedSymbols);

    function fetchNewData() {
        $.ajax({
            url: "{{ route('user.fetch-oi-buildup-data') }}", // define route
            method: "GET",
            data: {
                last_loaded_time: lastLoadedTime,
                loaded_symbols: loadedSymbols
            },
            success: function(response) {
                let newData = response.new_data;

                if (newData.length > 0) {
                    newData.forEach((item, index) => {
                        let txnTypeBadge = item.txn_type && item.txn_type !== '---'
                            ? `<span class="badge badge-${item.txn_type === 'BUY' ? 'success' : 'danger'}">${item.txn_type}</span>`
                            : '<span class="text-muted">---</span>';
                        
                        $('#oi-buildup-table tbody').append(`
                            <tr>
                                <td>#</td>
                                <td>${item.inserted_at}</td>
                                <td>${item.option_symbol}</td>
                                <td>${item.ltp}</td>
                                <td>${item.ltp}</td>
                                <td>${txnTypeBadge}</td>
                                <td>${item.lot_size}</td>
                                <td>${item.buy_quantity}</td>
                                <td>${item.ltp}</td>
                                <td>${item.sell_qty || ''}</td>
                                <td>${item.sell_price || ''}</td>
                                <td>${(item.buy_quantity*item.ltp*item.lot_size) || ''}</td>
                                <td>${item.profit || ''}</td>
                                <td>${item.realised_profit || ''}</td>
                                <td>${item.unrealised_profit || ''}</td>
                            </tr>
                        `);
                        loadedSymbols.push(item.symbol);
                        lastLoadedTime = item.inserted_at;
                    });

                    // update row index numbers
                    $('#oi-buildup-table tbody tr').each(function(i) {
                        $(this).find('td:first').text(i + 1);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching new data:', error);
            }
        });
    }

    
    // <td>${item.option_symbol || '---'}</td>
    // <td>${item.option_token || '---'}</td>

    setInterval(fetchNewData, 30000); // 30 sec interval
</script>
@endpush