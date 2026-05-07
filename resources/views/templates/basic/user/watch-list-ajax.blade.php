<div class="card-body p-0">
    <div class="table-responsive--md table-responsive">
        <table class="table custom--table text-nowrap">
            <thead>
                <tr>
                    <th>Symbol Name</th>
                    <th>Exchange</th>
                    <th>LTP</th>
                    <th>OPEN</th>
                    <th>HIGH</th>
                    <th>LOW</th>
                    <th>CLOSE</th>
                    <th>Net Change</th>
                    <th>Percent Change</th>
                    <th>Avg Price</th>
                    <th>Trade Volume</th>
                    <th>Open Interest</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="watchList">

                @isset($finalResponse)
                    @if ($finalResponse != NULL)
                        @foreach ($finalResponse as $item)
                            @php $text = "text-danger"; @endphp
                            @if ($item['netChange'] > 0)
                                @php $text = "text-success"; @endphp
                            @endif
                            <tr>
                                <td class="{{$text}}">{{$item['symbol_name']}}</td>
                                <td class="{{$text}}">{{$item['exchange']}}</td>
                                <td class="{{$text}}">{{$item['ltp']}}</td>
                                <td class="{{$text}}">{{$item['open']}}</td>
                                <td class="{{$text}}">{{$item['high']}}</td>
                                <td class="{{$text}}">{{$item['low']}}</td>
                                <td class="{{$text}}">{{$item['close']}}</td>
                                <td class="{{$text}}">{{$item['netChange']}}</td>
                                <td class="{{$text}}">{{$item['percentChange']}}</td>
                                <td class="{{$text}}">{{$item['avgPrice']}}</td>
                                <td class="{{$text}}">{{$item['tradeVolume']}}</td>
                                <td class="{{$text}}">{{$item['opnInterest']}}</td>
                                <td class="{{$text}}"><button class="py-0 buyModal btn btn-primary btn-sm" data-token="{{$item['symbolToken']}}" data-symbol="{{$item['symbol_name']}}" data-ltp="{{$item['ltp']}}" data-price="{{$item['ltp']}}" data-exchange="{{$item['exchange']}}" data-type="BUY" data-bs-toggle="modal" data-bs-target="#buy">BUY</button><button class="py-0 buyModal ms-1 btn btn-danger btn-sm" data-token="{{$item['symbolToken']}}" data-symbol="{{$item['symbol_name']}}" data-ltp="{{$item['ltp']}}" data-price="{{$item['ltp']}}" data-exchange="{{$item['exchange']}}" data-type="SELL" data-bs-toggle="modal" data-bs-target="#buy">SELL</button></td>
                            </tr>
                        @endforeach
                    @else
                    <tr>
                        <td colspan="100%" class="text-center">
                            No Data Found...
                        </td>
                    </tr>
                    @endif
                @endisset
            </tbody>
        </table>
    </div>
</div>