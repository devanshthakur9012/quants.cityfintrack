<div class="card-body p-0">
    <div class="table-responsive--md table-responsive">
        <table class="table custom--table text-nowrap">
            <thead>
                <tr>
                    <th>ORDER ID</th>
                    <th>TNX TYPE</th>
                    <th>SYMBOL NAME</th>
                    <th>QTY</th>
                    <th>PRICE</th>
                    <th>STATUS</th>
                    <th>ORDER DATE</th>
                </tr>
            </thead>
            <tbody >
                @isset($wishlistorder)
                @if (count($wishlistorder))
                    @foreach ($wishlistorder as $item)
                        <tr>
                            <td>{{$item->order_id}}</td>
                            <td>{{$item->type}}</td>
                            <td>{{$item->symbol}}</td>
                            <td>{{$item->quantity}}</td>
                            <td>{{$item->buy_price}}</td>
                            <td>{{ucfirst($item->status)}}</td>
                            <td>{{($item->created_at)->format('d-M, Y H:i:s')}}</td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="100%" class="d-flex justify-content-center text-center">
                            No Data Found...
                        </td>
                    </tr>
                @endif
                @endisset
            </tbody>
        </table>
    </div>
</div>